<?php
// app/controllers/candidatura_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['admin', 'superadmin', 'mentor']);

// ── Verificação CSRF (excepto AJAX/JSON) ──
$isAjaxCand = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
              str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
if (!$isAjaxCand) {
    csrf_verificar();
}

$action   = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/candidaturas.php';
$idAdmin  = (int)$_SESSION['usuario_id'];

if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/app/views/admin/candidaturas.php';
}

// ── Segurança de Perfil por Ação ──
$perfilUsuario = $_SESSION['usuario_perfil'] ?? 'utilizador';
$acoesAdmin = ['criar_processo', 'toggle_processo', 'triagem_automatica', 'gerar_convite_seguro', 'gerar_convite_ajax', 'mudar_estado_cand', 'remover_candidatura'];
if (in_array($action, $acoesAdmin) && !in_array($perfilUsuario, ['admin', 'superadmin'])) {
    $_SESSION['flash_erro'] = '⚠️ Permissão negada para esta ação.';
    header("Location: $redirect");
    exit;
}

/* ── CRIAR PROCESSO ─────────────────────── */
if ($action === 'criar_processo') {
    $nome     = limpar($_POST['nome'] ?? '');
    $desc     = limpar($_POST['descricao'] ?? '');
    $vagas    = max(1, (int)($_POST['vagas'] ?? 20));
    $estado   = in_array($_POST['estado'] ?? '', ['aberto','em_preparacao']) ? $_POST['estado'] : 'em_preparacao';

    if (strlen($nome) < 3) {
        $_SESSION['flash_erro'] = 'Nome do processo muito curto.';
    } else {
        $stmt = $mysqli->prepare("INSERT INTO processos_candidatura (nome, descricao, vagas, estado, criado_por) VALUES (?,?,?,?,?)");
        $stmt->bind_param('ssisi', $nome, $desc, $vagas, $estado, $idAdmin);
        if ($stmt->execute()) {
            $newId = $mysqli->insert_id;
            $_SESSION['flash_ok'] = "Processo \"$nome\" criado com sucesso!";
            $redirect = '/incubadora_ispsn/app/views/admin/candidaturas.php?processo=' . $newId;
        } else {
            $_SESSION['flash_erro'] = 'Erro ao criar processo.';
        }
    }
    header("Location: $redirect"); exit;
}

/* ── TOGGLE PROCESSO (abrir/fechar) ──────── */
if ($action === 'toggle_processo') {
    $id = (int)($_POST['id_processo'] ?? 0);
    if ($id) {
        $stmt = $mysqli->prepare("SELECT estado FROM processos_candidatura WHERE id=?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $proc = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        
        if ($proc) {
            $novoEstado = ($proc['estado'] === 'aberto') ? 'fechado' : 'aberto';
            $stmtUp = $mysqli->prepare("UPDATE processos_candidatura SET estado=?, atualizado_em=NOW() WHERE id=?");
            $stmtUp->bind_param('si', $novoEstado, $id);
            $stmtUp->execute();
            $stmtUp->close();
            
            $_SESSION['flash_ok'] = $novoEstado === 'aberto'
                ? '✅ Inscrições abertas ao público!'
                : '🔒 Inscrições encerradas. Novos formulários bloqueados.';
        }
    }
    header("Location: $redirect"); exit;
}

/* ── MUDAR ESTADO DE CANDIDATURA ─────────── */
if ($action === 'mudar_estado_cand') {
    $idCand  = (int)($_POST['id_cand'] ?? 0);
    $estado  = $_POST['estado'] ?? '';
    $estados = ['pendente','em_analise','selecionado','rejeitado','convite_enviado','registado'];

    if ($idCand && in_array($estado, $estados)) {
        // Apenas Super Admin (DG/PR) pode aprovar ou rejeitar candidaturas
        $perfilUsuario = $_SESSION['usuario_perfil'] ?? '';
        if (in_array($estado, ['selecionado', 'rejeitado']) && $perfilUsuario !== 'superadmin') {
            $_SESSION['flash_erro'] = '⚠️ Permissão negada: Apenas o Super ADMIN (DG/PR) pode aprovar ou rejeitar candidaturas na fase de decisão.';
            header("Location: $redirect"); exit;
        }

        $stmt = $mysqli->prepare("UPDATE candidaturas SET estado=?, avaliado_por=?, avaliado_em=NOW() WHERE id=?");
        $stmt->bind_param('sii', $estado, $idAdmin, $idCand);
        $stmt->execute();
        $label = ['selecionado'=>'Selecionado ✓','rejeitado'=>'Rejeitado ✗','em_analise'=>'Em Análise'];
        $_SESSION['flash_ok'] = 'Candidatura marcada como: ' . ($label[$estado] ?? $estado);
    }
    header("Location: $redirect"); exit;
}

/* ── TRIAGEM AUTOMÁTICA (Fase 1) ────────── */
if ($action === 'triagem_automatica') {
    $idProcesso = (int)($_POST['id_processo'] ?? 0);
    if (!$idProcesso) {
        $_SESSION['flash_erro'] = 'Processo inválido.';
        header("Location: $redirect"); exit;
    }

    // Buscar candidaturas pendentes deste processo
    $res = $mysqli->query("SELECT * FROM candidaturas WHERE id_processo = $idProcesso AND estado = 'pendente'");
    if (!$res) {
        $_SESSION['flash_erro'] = 'Erro ao buscar candidaturas pendentes.';
        header("Location: $redirect"); exit;
    }

    $aprovados = 0;
    $rejeitados = 0;

    while ($c = $res->fetch_assoc()) {
        $motivoRejeicao = '';
        $isPre = ($c['tipo_candidato'] ?? '') === 'pre_licenciado';

        // 1. Validação do e-mail
        if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) {
            $motivoRejeicao = 'E-mail em formato inválido.';
        } elseif (!$isPre && !str_ends_with($c['email'], '@ispsn.org')) {
            $motivoRejeicao = 'O e-mail deve ser institucional (@ispsn.org).';
        }

        // 2. Validação do telefone
        $telefoneClean = preg_replace('/\D/', '', $c['telefone']);
        if (strlen($telefoneClean) < 9) {
            $motivoRejeicao = 'Telefone inválido (mínimo de 9 dígitos).';
        }

        // 3. Validação do número de estudante
        if (!$isPre && (empty($c['numero_estudante']) || strlen(trim($c['numero_estudante'])) < 5)) {
            $motivoRejeicao = 'Número de estudante inválido ou curto demais.';
        }

        // 4. Validação da ideia
        if (strlen($c['titulo_ideia']) < 5) {
            $motivoRejeicao = 'Título da ideia muito curto (mínimo 5 caracteres).';
        } elseif (strlen($c['descricao_ideia']) < 30) {
            $motivoRejeicao = 'Descrição da ideia muito curta (mínimo 30 caracteres).';
        }

        // 5. Validação de duplicados
        if (empty($motivoRejeicao)) {
            $emailEscaped = $mysqli->real_escape_string($c['email']);
            $idCand = (int)$c['id'];
            
            if ($isPre) {
                $chkDup = $mysqli->query("
                    SELECT id FROM candidaturas 
                    WHERE id_processo = $idProcesso 
                      AND id != $idCand 
                      AND email = '$emailEscaped'
                      AND estado IN ('em_analise', 'selecionado', 'convite_enviado', 'registado')
                    LIMIT 1
                ");
            } else {
                $numEstEscaped = $mysqli->real_escape_string($c['numero_estudante']);
                $chkDup = $mysqli->query("
                    SELECT id FROM candidaturas 
                    WHERE id_processo = $idProcesso 
                      AND id != $idCand 
                      AND (email = '$emailEscaped' OR (numero_estudante != '' AND numero_estudante = '$numEstEscaped'))
                      AND estado IN ('em_analise', 'selecionado', 'convite_enviado', 'registado')
                    LIMIT 1
                ");
            }
            if ($chkDup && $chkDup->num_rows > 0) {
                $motivoRejeicao = 'Duplicidade de candidatura identificada (já existe registo para este estudante/e-mail neste processo).';
            }
        }

        // Atualizar estado conforme triagem
        if (empty($motivoRejeicao)) {
            $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'em_analise', avaliado_por = ?, avaliado_em = NOW() WHERE id = ?");
            $stmtUp->bind_param('ii', $idAdmin, $c['id']);
            $stmtUp->execute();
            $stmtUp->close();
            $aprovados++;
        } else {
            $obsAdmin = 'Reprovado na Triagem Automática: ' . $motivoRejeicao;
            $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'rejeitado', observacoes_admin = ?, avaliado_por = ?, avaliado_em = NOW() WHERE id = ?");
            $stmtUp->bind_param('sii', $obsAdmin, $idAdmin, $c['id']);
            $stmtUp->execute();
            $stmtUp->close();
            $rejeitados++;
        }
    }

    $_SESSION['flash_ok'] = "⚡ Triagem automática concluída! Processados: " . ($aprovados + $rejeitados) . " candidatura(s). Aprovados para Análise: $aprovados. Rejeitados: $rejeitados.";
    header("Location: $redirect"); exit;
}

/* ── GERAR CONVITE SEGURO + LINK WHATSAPP ── */
if ($action === 'gerar_convite_seguro') {
    $idCand     = (int)($_POST['id_cand'] ?? 0);
    $idProcesso = (int)($_POST['id_processo'] ?? 0);

    if (!$idCand) {
        $_SESSION['flash_erro'] = 'Candidatura inválida.';
        header("Location: $redirect"); exit;
    }

    // Buscar dados da candidatura
    $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $idCand);
    $stmt->execute();
    $cand = $stmt->get_result()->fetch_assoc();

    if (!$cand) {
        $_SESSION['flash_erro'] = 'Candidatura não encontrada.';
        header("Location: $redirect"); exit;
    }

    // Verificar se já tem convite ativo não expirado
    $chk = $mysqli->prepare("SELECT id, token FROM convites WHERE id_candidatura=? AND aceite=0 AND (data_expiracao IS NULL OR data_expiracao > NOW()) LIMIT 1");
    $chk->bind_param('i', $idCand);
    $chk->execute();
    $conviteExistente = $chk->get_result()->fetch_assoc();

    if ($conviteExistente) {
        // Reutilizar token existente
        $token = $conviteExistente['token'];
    } else {
        // Gerar novo token seguro
        $token      = bin2hex(random_bytes(24)); // 48 chars hex
        $expiracao  = date('Y-m-d H:i:s', strtotime('+48 hours'));
        $email      = $cand['email'];
        $numEst     = $cand['numero_estudante'];
        $tel        = $cand['telefone'];
        $perfil     = 'utilizador';

        // Criar tabela convites se não existir (compatibilidade)
        $mysqli->query("CREATE TABLE IF NOT EXISTS convites (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            token VARCHAR(100) NOT NULL,
            perfil VARCHAR(50) NOT NULL DEFAULT 'utilizador',
            id_projeto INT UNSIGNED DEFAULT NULL,
            criado_por INT UNSIGNED NOT NULL,
            aceite TINYINT(1) DEFAULT 0,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_expiracao DATETIME NULL,
            id_candidatura INT UNSIGNED NULL,
            telefone VARCHAR(30) NULL,
            numero_estudante VARCHAR(50) NULL,
            usos_maximos TINYINT DEFAULT 1
        )");

        $stmt = $mysqli->prepare("
            INSERT INTO convites (email, token, perfil, criado_por, data_expiracao, id_candidatura, telefone, numero_estudante, usos_maximos)
            VALUES (?,?,?,?,?,?,?,?,1)
        ");
        $stmt->bind_param('sssisiss', $email, $token, $perfil, $idAdmin, $expiracao, $idCand, $tel, $numEst);

        if (!$stmt->execute()) {
            $_SESSION['flash_erro'] = 'Erro ao gerar token: ' . $mysqli->error;
            header("Location: $redirect"); exit;
        }

        // Atualizar candidatura
        $stmt2 = $mysqli->prepare("UPDATE candidaturas SET estado='convite_enviado', token_convite=?, convite_enviado_em=NOW() WHERE id=?");
        $stmt2->bind_param('si', $token, $idCand);
        $stmt2->execute();
    }

    // Construir link de registo
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
    $link    = $baseUrl . '/incubadora_ispsn/public/register.php?invite=' . $token;

    // Construir mensagem WhatsApp
    $nome   = $cand['nome'];
    $primeiroNome = explode(' ', $nome)[0];
    $isPre = ($cand['tipo_candidato'] ?? '') === 'pre_licenciado';
    $msg = "Olá {$primeiroNome}! 🎉\n\n";
    $msg .= "A sua candidatura à *Incubadora Académica ISPSN* foi *APROVADA!* 🚀\n\n";
    $msg .= "Para criar a sua conta no portal e iniciar o processo, aceda ao link abaixo:\n\n";
    $msg .= "🔗 {$link}\n\n";
    $msg .= "⏰ *Atenção:* Este link é válido por apenas *48 horas* e pode ser usado *uma única vez*.\n\n";
    $msg .= "Ao registar-se, use:\n";
    $msg .= "• *Email:* {$cand['email']}\n";
    if (!$isPre) {
        $msg .= "• *Nº Estudante:* {$cand['numero_estudante']}\n\n";
    } else {
        $msg .= "\n";
    }
    $msg .= "Este link é *pessoal e intransferível* — não o partilhe.\n\n";
    $msg .= "Bem-vindo(a) à família ISPSN! 🌟\n";
    $msg .= "_Incubadora Académica ISPSN_";

    // Número de telefone: remover espaços e garantir formato internacional Angola (+244)
    $telefone = preg_replace('/\D/', '', $cand['telefone']);
    if (strlen($telefone) === 9 && str_starts_with($telefone, '9')) {
        $telefone = '244' . $telefone;
    }

    $waUrl = 'https://wa.me/' . $telefone . '?text=' . rawurlencode($msg);

    // Guardar na sessão para redirecionar
    $_SESSION['flash_ok'] = "✅ Convite gerado! Link válido 48h. WhatsApp aberto automaticamente.";
    $_SESSION['wa_redirect'] = $waUrl;

    header("Location: /incubadora_ispsn/app/views/admin/candidaturas.php?processo={$idProcesso}&wa=1&token=" . urlencode($token) . "&cand={$idCand}");
    exit;
}

/* ── GERAR CONVITE VIA AJAX (Dinamizar Envio) ────── */
if ($action === 'gerar_convite_ajax') {
    header('Content-Type: application/json');
    $idCand = (int)($_POST['id_cand'] ?? 0);

    if (!$idCand) {
        echo json_encode(['sucesso' => false, 'erro' => 'Candidatura inválida.']);
        exit;
    }

    // Buscar candidatura
    $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $idCand);
    $stmt->execute();
    $cand = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$cand) {
        echo json_encode(['sucesso' => false, 'erro' => 'Candidatura não encontrada.']);
        exit;
    }

    if ($cand['estado'] !== 'selecionado' && $cand['estado'] !== 'convite_enviado') {
        echo json_encode(['sucesso' => false, 'erro' => 'Candidatura não está no estado aprovado.']);
        exit;
    }

    // Verificar se já tem convite ativo não expirado
    $chk = $mysqli->prepare("SELECT token FROM convites WHERE id_candidatura=? AND aceite=0 AND (data_expiracao IS NULL OR data_expiracao > NOW()) LIMIT 1");
    $chk->bind_param('i', $idCand);
    $chk->execute();
    $conviteExistente = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($conviteExistente) {
        $token = $conviteExistente['token'];
    } else {
        $token      = bin2hex(random_bytes(24));
        $expiracao  = date('Y-m-d H:i:s', strtotime('+48 hours'));
        $email      = $cand['email'];
        $numEst     = $cand['numero_estudante'];
        $tel        = $cand['telefone'];
        $perfil     = 'utilizador';

        // Garantir que a tabela existe
        $mysqli->query("CREATE TABLE IF NOT EXISTS convites (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            email VARCHAR(150) NOT NULL,
            token VARCHAR(100) NOT NULL,
            perfil VARCHAR(50) NOT NULL DEFAULT 'utilizador',
            id_projeto INT UNSIGNED DEFAULT NULL,
            criado_por INT UNSIGNED NOT NULL,
            aceite TINYINT(1) DEFAULT 0,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_expiracao DATETIME NULL,
            id_candidatura INT UNSIGNED NULL,
            telefone VARCHAR(30) NULL,
            numero_estudante VARCHAR(50) NULL,
            usos_maximos TINYINT DEFAULT 1
        )");

        $stmt = $mysqli->prepare("
            INSERT INTO convites (email, token, perfil, criado_por, data_expiracao, id_candidatura, telefone, numero_estudante, usos_maximos)
            VALUES (?,?,?,?,?,?,?,?,1)
        ");
        $stmt->bind_param('sssisiss', $email, $token, $perfil, $idAdmin, $expiracao, $idCand, $tel, $numEst);
        
        if (!$stmt->execute()) {
            echo json_encode(['sucesso' => false, 'erro' => 'Erro ao registar convite: ' . $mysqli->error]);
            exit;
        }
        $stmt->close();

        // Atualizar candidatura
        $stmt2 = $mysqli->prepare("UPDATE candidaturas SET estado='convite_enviado', token_convite=?, convite_enviado_em=NOW() WHERE id=?");
        $stmt2->bind_param('si', $token, $idCand);
        $stmt2->execute();
        $stmt2->close();
    }

    // Links e mensagens
    $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
    $link    = $baseUrl . '/incubadora_ispsn/public/register.php?invite=' . $token;

    $nome   = $cand['nome'];
    $primeiroNome = explode(' ', $nome)[0];
    $isPre = ($cand['tipo_candidato'] ?? '') === 'pre_licenciado';
    $msg = "Olá {$primeiroNome}! 🎉\n\n";
    $msg .= "A sua candidatura à *Incubadora Académica ISPSN* foi *APROVADA!* 🚀\n\n";
    $msg .= "Para criar a sua conta no portal e iniciar o processo, aceda ao link abaixo:\n\n";
    $msg .= "🔗 {$link}\n\n";
    $msg .= "⏰ *Atenção:* Este link é válido por apenas *48 horas* e pode ser usado *uma única vez*.\n\n";
    $msg .= "Ao registar-se, use:\n";
    $msg .= "• *Email:* {$cand['email']}\n";
    if (!$isPre) {
        $msg .= "• *Nº Estudante:* {$cand['numero_estudante']}\n\n";
    } else {
        $msg .= "\n";
    }
    $msg .= "Este link é *pessoal e intransferível* — não o partilhe.\n\n";
    $msg .= "Bem-vindo(a) à família ISPSN! 🌟\n";
    $msg .= "_Incubadora Académica ISPSN_";

    $telefone = preg_replace('/\D/', '', $cand['telefone']);
    if (strlen($telefone) === 9 && str_starts_with($telefone, '9')) {
        $telefone = '244' . $telefone;
    }

    $waUrl = 'https://wa.me/' . $telefone . '?text=' . rawurlencode($msg);

    echo json_encode([
        'sucesso' => true,
        'token' => $token,
        'link_registo' => $link,
        'wa_url' => $waUrl,
        'candidato' => [
            'nome' => $cand['nome'],
            'telefone' => $cand['telefone']
        ]
    ]);
    exit;
}

/* ── AVALIAR PITCH DE CANDIDATURA ────────── */
if ($action === 'avaliar_pitch_candidatura') {
    $idCand                 = (int)($_POST['id_cand'] ?? 0);
    $pitchInovacao          = min(10, max(0, (int)($_POST['pitch_inovacao'] ?? 0)));
    $pitchSustentabilidade  = min(10, max(0, (int)($_POST['pitch_sustentabilidade'] ?? 0)));
    $pitchEmpreendedorismo  = min(10, max(0, (int)($_POST['pitch_empreendedorismo'] ?? 0)));
    $pitchObservacoes       = trim($_POST['pitch_observacoes'] ?? '');

    if ($idCand > 0) {
        $pitchNotaFinal = ($pitchInovacao + $pitchSustentabilidade + $pitchEmpreendedorismo) / 3;

        $stmt = $mysqli->prepare("
            UPDATE candidaturas SET 
                pitch_inovacao = ?, 
                pitch_sustentabilidade = ?, 
                pitch_empreendedorismo = ?, 
                pitch_nota_final = ?, 
                pitch_observacoes = ?, 
                pitch_avaliado_por = ?, 
                pitch_avaliado_em = NOW(),
                estado = 'em_analise'
            WHERE id = ?
        ");
        $stmt->bind_param('ddddsii', 
            $pitchInovacao, 
            $pitchSustentabilidade, 
            $pitchEmpreendedorismo, 
            $pitchNotaFinal, 
            $pitchObservacoes, 
            $idAdmin, 
            $idCand
        );
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = 'Avaliação do Pitch registada com sucesso! A candidatura foi movida para a Fila de Seleção.';
        } else {
            $_SESSION['flash_erro'] = 'Erro ao registar a avaliação do pitch: ' . $mysqli->error;
        }
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}

/* ── REMOVER CANDIDATURA ─────────────────── */
if ($action === 'remover_candidatura') {
    obrigarPerfil(['admin', 'superadmin']); // Permite Admin ou SuperAdmin eliminar candidaturas por facilidade de gestão
    $idCand = (int)($_POST['id_cand'] ?? 0);

    if ($idCand > 0) {
        // Verificar se existem convites associados a esta candidatura
        $stmtChk = $mysqli->prepare("SELECT id FROM convites WHERE id_candidatura = ?");
        $stmtChk->bind_param('i', $idCand);
        $stmtChk->execute();
        $hasConvite = $stmtChk->get_result()->num_rows > 0;
        $stmtChk->close();

        if ($hasConvite) {
            $_SESSION['flash_erro'] = 'Não é possível remover esta candidatura pois já existe um convite de conta associado a ela.';
        } else {
            $stmt = $mysqli->prepare("DELETE FROM candidaturas WHERE id = ?");
            $stmt->bind_param('i', $idCand);
            if ($stmt->execute()) {
                $_SESSION['flash_ok'] = 'Candidatura removida com sucesso!';
            } else {
                $_SESSION['flash_erro'] = 'Erro ao remover a candidatura: ' . $mysqli->error;
            }
            $stmt->close();
        }
    }
    header("Location: $redirect");
    exit;
}

header("Location: $redirect"); exit;
