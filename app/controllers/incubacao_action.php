<?php
// app/controllers/incubacao_action.php
// Controller de Incubação — Termos, Assinatura Digital, Workflow
require_once __DIR__ . '/../../config/auth.php';
obrigarLogin();

$perfil    = $_SESSION['usuario_perfil'] ?? 'utilizador';
$idUsuario = (int)$_SESSION['usuario_id'];
$action    = $_POST['action'] ?? '';
$redirect  = $_POST['redirect'] ?? '/incubadora_ispsn/public/index.php';

if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/public/index.php';
}

/* ════════════════════════════════════════════════
   ACÇÃO: gerar_termo
   Admin gera o Termo de Incubação e envia ao SuperAdmin
════════════════════════════════════════════════ */
if ($action === 'gerar_termo' && in_array($perfil, ['admin','superadmin'])) {
    $idProjeto  = (int)($_POST['id_projeto'] ?? 0);
    $idMentor   = (int)($_POST['id_mentor'] ?? 0);
    
    if ($idProjeto > 0) {
        // Buscar dados do projecto e da avaliação mais recente
        $stmt = $mysqli->prepare("
            SELECT p.*, u.nome as autor, u.email as autor_email
            FROM projetos p
            JOIN usuarios u ON u.id = p.criado_por
            WHERE p.id = ? AND p.estado = 'aprovado'
        ");
        $stmt->bind_param('i', $idProjeto);
        $stmt->execute();
        $projeto = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$projeto) {
            $_SESSION['flash_erro'] = "Projecto não encontrado ou não está no estado 'aprovado'.";
            header("Location: $redirect"); exit;
        }
        
        // Buscar avaliação mais recente
        $stmt = $mysqli->prepare("SELECT * FROM avaliacoes WHERE id_projeto = ? ORDER BY avaliado_em DESC LIMIT 1");
        $stmt->bind_param('i', $idProjeto);
        $stmt->execute();
        $avaliacao = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$avaliacao) {
            $_SESSION['flash_erro'] = "O projecto não possui avaliação registada.";
            header("Location: $redirect"); exit;
        }
        
        // Verificar se já existe termo pendente
        $stmtCheck = $mysqli->prepare("SELECT id FROM termos_incubacao WHERE id_projeto = ? AND estado IN ('gerado','pendente_assinatura')");
        $stmtCheck->bind_param('i', $idProjeto);
        $stmtCheck->execute();
        if ($stmtCheck->get_result()->num_rows > 0) {
            $_SESSION['flash_erro'] = "Já existe um termo pendente para este projecto.";
            $stmtCheck->close();
            header("Location: $redirect"); exit;
        }
        $stmtCheck->close();
        
        // Gerar código do termo: ISPSN-INC-2026-001
        $ano = date('Y');
        $stmtSeq = $mysqli->query("SELECT COUNT(*)+1 as seq FROM termos_incubacao WHERE YEAR(criado_em) = $ano");
        $seq = str_pad($stmtSeq->fetch_assoc()['seq'], 3, '0', STR_PAD_LEFT);
        $codigoTermo = "ISPSN-INC-{$ano}-{$seq}";
        
        // Buscar info do mentor
        $mentorNome = 'A definir';
        if ($idMentor > 0) {
            $stmtM = $mysqli->prepare("SELECT u.nome FROM mentores m JOIN usuarios u ON u.id = m.id_usuario WHERE m.id = ?");
            $stmtM->bind_param('i', $idMentor);
            $stmtM->execute();
            $mentorData = $stmtM->get_result()->fetch_assoc();
            if ($mentorData) $mentorNome = $mentorData['nome'];
            $stmtM->close();
        }
        
        // Snapshot dos dados para o PDF
        $tipoContrato = $_POST['tipo_contrato'] ?? 'incubacao';
        if ($tipoContrato !== 'pre_incubacao') $tipoContrato = 'incubacao';
        $duracaoMeses = $tipoContrato === 'pre_incubacao' ? 3 : 12;

        $dadosJson = json_encode([
            'projeto' => [
                'id' => $projeto['id'],
                'titulo' => $projeto['titulo'],
                'tipo' => $projeto['tipo'],
                'area' => $projeto['area_tematica'] ?? '',
                'descricao' => $projeto['descricao'] ?? '',
                'autor' => $projeto['autor'],
                'email' => $projeto['autor_email'],
            ],
            'avaliacao' => [
                'pontuacao' => $avaliacao['pontuacao_total'],
                'decisao' => $avaliacao['decisao'],
                'data' => $avaliacao['avaliado_em'],
                'notas' => [
                    'inovacao' => $avaliacao['nota_inovacao'] ?? 0,
                    'viabilidade' => $avaliacao['nota_viabilidade'] ?? 0,
                    'impacto' => $avaliacao['nota_impacto'] ?? 0,
                    'equipa' => $avaliacao['nota_equipa'] ?? 0,
                    'sustentabilidade' => $avaliacao['nota_sustentabilidade'] ?? 0,
                    'escalabilidade' => $avaliacao['nota_escalabilidade'] ?? 0,
                    'mercado' => $avaliacao['nota_mercado'] ?? 0,
                    'proposta' => $avaliacao['nota_proposta'] ?? 0,
                ],
            ],
            'mentor' => $mentorNome,
            'tipo_contrato' => $tipoContrato,
            'duracao_meses' => $duracaoMeses,
            'gerado_por' => $_SESSION['usuario_nome'],
            'data_geracao' => date('Y-m-d H:i:s'),
        ], JSON_UNESCAPED_UNICODE);
        
        // Inserir termo
        $stmt = $mysqli->prepare("
            INSERT INTO termos_incubacao (id_projeto, id_avaliacao, id_mentor, codigo_termo, dados_json, tipo_contrato, duracao_meses, estado)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pendente_assinatura')
        ");
        $mentorVal = $idMentor > 0 ? $idMentor : null;
        $stmt->bind_param('iiissis', $idProjeto, $avaliacao['id'], $mentorVal, $codigoTermo, $dadosJson, $tipoContrato, $duracaoMeses);
        $stmt->execute();
        $termoId = $stmt->insert_id;
        $stmt->close();
        
        // Log de histórico
        $stmt = $mysqli->prepare("INSERT INTO historico_estados (id_projeto, estado_anterior, estado_novo, id_usuario, motivo) VALUES (?, 'aprovado', 'pendente_incubacao', ?, ?)");
        $lblContrato = $tipoContrato === 'pre_incubacao' ? 'Contrato de Pré-Incubação' : 'Contrato de Incubação de Empresa';
        $motivo = "{$lblContrato} {$codigoTermo} gerado e enviado para assinatura do SuperAdmin.";
        $stmt->bind_param('iis', $idProjeto, $idUsuario, $motivo);
        $stmt->execute();
        $stmt->close();
        
        // Notificar todos os SuperAdmins
        $admins = $mysqli->query("SELECT id FROM usuarios WHERE perfil = 'superadmin' AND activo = 1");
        if ($admins) {
            while ($a = $admins->fetch_assoc()) {
                enviarNotificacao($a['id'], "📄 Termo de Incubação Pendente", 
                    "O contrato {$codigoTermo} para o projecto \"" . htmlspecialchars($projeto['titulo']) . "\" aguarda a sua assinatura digital.", 'info');
            }
        }
        
        $_SESSION['flash_ok'] = "{$lblContrato} {$codigoTermo} gerado e enviado para assinatura do SuperAdmin!";
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: assinar_termo
   SuperAdmin assina digitalmente o termo
════════════════════════════════════════════════ */
if ($action === 'assinar_termo' && $perfil === 'superadmin') {
    $idTermo = (int)($_POST['id_termo'] ?? 0);
    $senhaConfirmacao = $_POST['senha_confirmacao'] ?? '';
    
    if ($idTermo > 0 && !empty($senhaConfirmacao)) {
        // Verificar senha do SuperAdmin
        $stmtUser = $mysqli->prepare("SELECT senha FROM usuarios WHERE id = ?");
        $stmtUser->bind_param('i', $idUsuario);
        $stmtUser->execute();
        $userData = $stmtUser->get_result()->fetch_assoc();
        $stmtUser->close();
        
        if (!$userData || !password_verify($senhaConfirmacao, $userData['senha'])) {
            $_SESSION['flash_erro'] = "Senha incorrecta. A assinatura digital requer confirmação da sua identidade.";
            header("Location: $redirect"); exit;
        }
        
        // Buscar termo
        $stmt = $mysqli->prepare("
            SELECT t.*, p.titulo as proj_titulo, p.id as proj_id, p.criado_por
            FROM termos_incubacao t
            JOIN projetos p ON p.id = t.id_projeto
            WHERE t.id = ? AND t.estado = 'pendente_assinatura'
        ");
        $stmt->bind_param('i', $idTermo);
        $stmt->execute();
        $termo = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if (!$termo) {
            $_SESSION['flash_erro'] = "Termo não encontrado ou já foi assinado.";
            header("Location: $redirect"); exit;
        }
        
        // Gerar hash de assinatura digital (SHA-512)
        $timestamp = date('Y-m-d H:i:s');
        $assinaturaHash = hash('sha512', $termo['dados_json'] . $timestamp . $idUsuario . $senhaConfirmacao);
        
        // Actualizar termo como assinado
        $stmt = $mysqli->prepare("UPDATE termos_incubacao SET estado='assinado', assinado_por=?, assinatura_hash=?, assinado_em=? WHERE id=?");
        $stmt->bind_param('issi', $idUsuario, $assinaturaHash, $timestamp, $idTermo);
        $stmt->execute();
        $stmt->close();
        
        // Mudar estado do projecto para 'incubado'
        $stmt = $mysqli->prepare("UPDATE projetos SET estado='incubado', fase='ideacao' WHERE id=?");
        $stmt->bind_param('i', $termo['proj_id']);
        $stmt->execute();
        $stmt->close();
        
        // Log de histórico
        $stmt = $mysqli->prepare("INSERT INTO historico_estados (id_projeto, estado_anterior, estado_novo, id_usuario, motivo) VALUES (?, 'aprovado', 'incubado', ?, ?)");
        $motivo = "Termo " . $termo['codigo_termo'] . " assinado digitalmente. Hash: " . substr($assinaturaHash, 0, 16) . "...";
        $stmt->bind_param('iis', $termo['proj_id'], $idUsuario, $motivo);
        $stmt->execute();
        $stmt->close();
        
        // Inicializar metas da fase ideação para o projecto
        $stmtMetas = $mysqli->prepare("SELECT id FROM metas_padrao WHERE fase = 'ideacao' AND activo = 1");
        $stmtMetas->execute();
        $metasIdeacao = $stmtMetas->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtMetas->close();
        
        $stmtInsert = $mysqli->prepare("INSERT IGNORE INTO metas_projeto (id_projeto, id_meta_padrao, estado) VALUES (?, ?, 'inactiva')");
        foreach ($metasIdeacao as $m) {
            $stmtInsert->bind_param('ii', $termo['proj_id'], $m['id']);
            $stmtInsert->execute();
        }
        $stmtInsert->close();

        // Gerar o PDF assinado e enviar por e-mail para o estudante
        $stmtT = $mysqli->prepare("SELECT * FROM termos_incubacao WHERE id = ?");
        $stmtT->bind_param('i', $idTermo);
        $stmtT->execute();
        $termoAtualizado = $stmtT->get_result()->fetch_assoc();
        $stmtT->close();
        
        $pdfPath = null;
        if ($termoAtualizado) {
            require_once __DIR__ . '/../utils/GeradorPDF.php';
            $fileName = "termo_signed_" . $idTermo . "_" . time() . ".pdf";
            $pdfDir = __DIR__ . '/../../uploads/termos';
            $pdfPath = $pdfDir . '/' . $fileName;
            
            if (\App\Utils\GeradorPDF::salvarTermoPDF($termoAtualizado, $pdfPath)) {
                $dbPath = "uploads/termos/" . $fileName;
                $stmtUpPath = $mysqli->prepare("UPDATE termos_incubacao SET path_pdf = ? WHERE id = ?");
                $stmtUpPath->bind_param('si', $dbPath, $idTermo);
                $stmtUpPath->execute();
                $stmtUpPath->close();
                
                // Buscar dados do estudante
                $stmtEmail = $mysqli->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
                $stmtEmail->bind_param('i', $termo['criado_por']);
                $stmtEmail->execute();
                $stmtEmail->bind_result($estNome, $estEmail);
                if ($stmtEmail->fetch()) {
                    $stmtEmail->close();
                    if (!empty($estEmail) && filter_var($estEmail, FILTER_VALIDATE_EMAIL)) {
                        require_once __DIR__ . '/../utils/Mailer.php';
                        $assuntoEst = "Termo de Incubação Assinado — " . $termo['codigo_termo'];
                        
                        $bodyEst = "
                        <!DOCTYPE html>
                        <html>
                        <head>
                            <meta charset='utf-8'>
                            <style>
                                body { font-family: 'Inter', sans-serif; background-color: #f8fafc; color: #334155; margin: 0; padding: 0; }
                                .email-container { max-width: 600px; margin: 40px auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; }
                                .header { background: #0f172a; padding: 32px; text-align: center; border-bottom: 3px solid #10b981; }
                                .logo { font-size: 24px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; }
                                .logo span { color: #f59e0b; }
                                .content { padding: 40px; line-height: 1.6; }
                                .greeting { font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 0; margin-bottom: 12px; }
                                .congrats-card { background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 12px; padding: 24px; margin: 24px 0; }
                                .congrats-title { font-size: 16px; font-weight: 700; color: #065f46; margin-bottom: 8px; }
                                .congrats-text { font-size: 14px; color: #065f46; }
                                .footer { background: #f8fafc; padding: 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
                            </style>
                        </head>
                        <body>
                            <div class='email-container'>
                                <div class='header'>
                                    <div class='logo'>INCUBADORA<span>ISPSN</span></div>
                                </div>
                                <div class='content'>
                                    <div class='greeting'>Parabéns, " . htmlspecialchars(explode(' ', $estNome)[0]) . "!</div>
                                    
                                    <div class='congrats-card'>
                                        <div class='congrats-title'>🎉 Startup Oficialmente Incubada!</div>
                                        <div class='congrats-text'>
                                            O Termo de Incubação <strong>" . htmlspecialchars($termo['codigo_termo']) . "</strong> do seu projecto <strong>\"" . htmlspecialchars($termo['proj_titulo']) . "\"</strong> foi assinado digitalmente pelo Presidente do ISPSN e encontra-se em anexo a este e-mail.
                                        </div>
                                    </div>
                                    
                                    <p>A partir de agora, o seu projeto tem acesso pleno ao ecossistema da Incubadora. Pode começar a executar as metas iniciais da fase de ideação acedendo ao seu painel no sistema.</p>
                                </div>
                                <div class='footer'>
                                    Este é um e-mail automático enviado pelo Sistema da Incubadora Académica ISPSN.<br>
                                    &copy; " . date('Y') . " ISPSN. Todos os direitos reservados.
                                </div>
                            </div>
                        </body>
                        </html>
                        ";
                        
                        $mailError = "";
                        \App\Utils\Mailer::send($estEmail, $assuntoEst, $bodyEst, $mailError, $pdfPath);
                    }
                } else {
                    $stmtEmail->close();
                }
            }
        }
        
        // Notificar estudante
        enviarNotificacao($termo['criado_por'], "🎉 Projecto Oficialmente Incubado!", 
            "O Termo de Incubação " . $termo['codigo_termo'] . " foi assinado pelo Presidente do ISPSN. O seu projecto \"" . htmlspecialchars($termo['proj_titulo']) . "\" está agora oficialmente incubado! Acompanhe as suas metas no dashboard.", 'sucesso');
        
        $_SESSION['flash_ok'] = "Termo " . $termo['codigo_termo'] . " assinado com sucesso! Projecto \"" . htmlspecialchars($termo['proj_titulo']) . "\" está agora INCUBADO.";
    } else {
        $_SESSION['flash_erro'] = "É necessário confirmar com a sua senha para assinar o termo.";
    }
    header("Location: $redirect");
    exit;
}

/* ════════════════════════════════════════════════
   ACÇÃO: revogar_termo
   SuperAdmin revoga um termo assinado
════════════════════════════════════════════════ */
if ($action === 'revogar_termo' && $perfil === 'superadmin') {
    $idTermo = (int)($_POST['id_termo'] ?? 0);
    $motivo  = trim($_POST['motivo_revogacao'] ?? '');
    
    if ($idTermo > 0 && !empty($motivo)) {
        $stmt = $mysqli->prepare("UPDATE termos_incubacao SET estado='revogado', motivo_revogacao=? WHERE id=? AND estado='assinado'");
        $stmt->bind_param('si', $motivo, $idTermo);
        $stmt->execute();
        
        if ($stmt->affected_rows > 0) {
            // Buscar projecto para log
            $stmtT = $mysqli->prepare("SELECT id_projeto FROM termos_incubacao WHERE id=?");
            $stmtT->bind_param('i', $idTermo);
            $stmtT->execute();
            $termoData = $stmtT->get_result()->fetch_assoc();
            $stmtT->close();
            
            if ($termoData) {
                $stmtLog = $mysqli->prepare("INSERT INTO historico_estados (id_projeto, estado_anterior, estado_novo, id_usuario, motivo) VALUES (?, 'incubado', 'revogado', ?, ?)");
                $stmtLog->bind_param('iis', $termoData['id_projeto'], $idUsuario, $motivo);
                $stmtLog->execute();
                $stmtLog->close();
            }
            
            $_SESSION['flash_ok'] = "Termo revogado com sucesso.";
        }
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}

header("Location: $redirect");
exit;
