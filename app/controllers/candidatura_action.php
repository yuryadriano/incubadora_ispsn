<?php
// app/controllers/candidatura_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['admin', 'superadmin', 'mentor']);

// ── Verificação CSRF Universal (obrigatória em todos os POST) ──
csrf_verificar();

$action   = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/candidaturas.php';
$idAdmin  = (int)$_SESSION['usuario_id'];

if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/app/views/admin/candidaturas.php';
}

$isAjaxCand = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ||
              str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');

// ── Segurança de Perfil por Ação ──
$perfilUsuario = $_SESSION['usuario_perfil'] ?? 'utilizador';
$acoesAdmin = [
    'criar_processo', 'toggle_processo', 'triagem_automatica', 'mudar_estado_cand', 
    'remover_candidatura', 'convocar_candidato', 'reagendar_convocatoria', 
    'reconvocar_candidato', 'marcar_ausencia', 'marcar_recusa', 
    'reprovar_reuniao', 'validar_presencial_cand', 'enviar_convite_registo', 
    'reenviar_convite_registo', 'gerar_convite_seguro', 'gerar_convite_ajax'
];
if (in_array($action, $acoesAdmin) && !in_array($perfilUsuario, ['admin', 'superadmin'])) {
    if ($isAjaxCand) {
        http_response_code(403);
        echo json_encode(['sucesso' => false, 'erro' => '⚠️ Permissão negada para esta ação.']);
        exit;
    }
    $_SESSION['flash_erro'] = '⚠️ Permissão negada para esta ação.';
    header("Location: $redirect");
    exit;
}

// ── FUNÇÃO DE REGISTO DE HISTÓRICO DE TRANSIÇÕES (AUDITORIA) ──
function registarHistoricoCandidatura(mysqli $mysqli, int $idCand, ?string $de, string $para, int $idUsuario, ?string $nota): void {
    $stmt = $mysqli->prepare("
        INSERT INTO candidatura_historico (candidatura_id, estado_anterior, estado_novo, alterado_por, alterado_em, nota)
        VALUES (?, ?, ?, ?, NOW(), ?)
    ");
    if ($stmt) {
        $stmt->bind_param('issis', $idCand, $de, $para, $idUsuario, $nota);
        $stmt->execute();
        $stmt->close();
    }
}

// ── TEMPLATE E GERADOR DE MENSAGEM DE CONVOCATÓRIA (SEM LINK DE REGISTO) ──
function gerarMensagemConvocatoria(array $cand, array $conv): array {
    $primeiroNome = explode(' ', trim($cand['nome']))[0];
    $dataFmt = date('d/m/Y', strtotime($conv['data_reuniao']));
    $horaFmt = substr($conv['hora_reuniao'], 0, 5);
    $local = !empty($conv['local_reuniao']) ? $conv['local_reuniao'] : 'Gabinete da Incubadora Académica - ISPSN';
    $docs = !empty($conv['documentos_a_levar']) ? $conv['documentos_a_levar'] : 'Bilhete de Identidade, Cartão de Estudante e Dossier preliminar do projeto';
    $obs = !empty($conv['observacoes']) ? "\n\nℹ️ *Observações:* " . $conv['observacoes'] : "";

    $msg = "Olá, {$primeiroNome}! 🎉\n\n";
    $msg .= "Temos o prazer de informar que a sua candidatura do projeto *\"{$cand['titulo_ideia']}\"* foi *SELECIONADA* pela Comissão da *Incubadora Académica ISPSN*! 🚀\n\n";
    $msg .= "Para formalizar a sua admissão, convocamo-lo(a) para uma *reunião presencial* com a Direção da Incubadora:\n\n";
    $msg .= "📅 *Data:* {$dataFmt}\n";
    $msg .= "⏰ *Hora:* {$horaFmt}\n";
    $msg .= "📍 *Local:* {$local}\n";
    $msg .= "📄 *Documentos a levar:* {$docs}{$obs}\n\n";
    $msg .= "Por favor, responda a esta mensagem para confirmar a sua presença.\n\n";
    $msg .= "Parabéns pelo seu trabalho até aqui!\n\n";
    $msg .= "Atenciosamente,\n";
    $msg .= "_Direção da Incubadora Académica ISPSN_";

    $telClean = preg_replace('/\D/', '', $cand['telefone']);
    if (strlen($telClean) === 9 && str_starts_with($telClean, '9')) {
        $telClean = '244' . $telClean;
    }
    $waUrl = 'https://wa.me/' . $telClean . '?text=' . rawurlencode($msg);

    return [
        'texto' => $msg,
        'wa_url' => $waUrl
    ];
}

// ── FUNÇÃO ÚNICA E CENTRALIZADA DE GERAÇÃO DE CONVITE DE REGISTO ──
// REGRA INVIOLÁVEL: O token de registo (48h) só pode ser gerado se estado = 'validado_presencial'
// (ou 'convite_enviado' no caso exclusivo de reenvio autorizado pelo Super Admin).
function gerarConviteRegisto(mysqli $mysqli, int $idCand, int $idAdmin, bool $reenvio = false): array {
    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) {
            throw new Exception("Candidatura não encontrada.");
        }

        if (!$reenvio && $cand['estado'] !== 'validado_presencial') {
            throw new Exception("Operação recusada: O envio de convite de registo só é permitido após validação presencial concluída. Estado atual: " . $cand['estado']);
        }

        if ($reenvio && $cand['estado'] !== 'convite_enviado') {
            throw new Exception("Operação recusada: Reenvio de convite só é permitido para candidaturas que já receberam convite prévio. Estado atual: " . $cand['estado']);
        }

        // Invalidar tokens anteriores não utilizados desta candidatura
        $stmtInv = $mysqli->prepare("UPDATE convites SET data_expiracao = NOW() WHERE id_candidatura = ? AND aceite = 0");
        $stmtInv->bind_param('i', $idCand);
        $stmtInv->execute();
        $stmtInv->close();

        // Gerar novo token seguro criptográfico (48 chars hex) válido estritamente por 48 horas
        $token     = bin2hex(random_bytes(24));
        $expiracao = date('Y-m-d H:i:s', strtotime('+48 hours'));
        $email     = $cand['email'];
        $numEst    = $cand['numero_estudante'];
        $tel       = $cand['telefone'];
        $perfil    = 'utilizador';

        $stmtIns = $mysqli->prepare("
            INSERT INTO convites (email, token, perfil, criado_por, data_expiracao, id_candidatura, telefone, numero_estudante, usos_maximos)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmtIns->bind_param('sssisiss', $email, $token, $perfil, $idAdmin, $expiracao, $idCand, $tel, $numEst);
        if (!$stmtIns->execute()) {
            throw new Exception("Erro ao registar convite: " . $mysqli->error);
        }
        $stmtIns->close();

        // Atualizar estado da candidatura para convite_enviado
        $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'convite_enviado', token_convite = ?, convite_enviado_em = NOW() WHERE id = ?");
        $stmtUp->bind_param('si', $token, $idCand);
        $stmtUp->execute();
        $stmtUp->close();

        // Registar transição no histórico
        $notaHist = $reenvio 
            ? "Reenvio de convite de registo com novo token 48h (invalidação do token anterior)" 
            : "Emissão oficial do convite de registo (validade 48h) após validação presencial";
        registarHistoricoCandidatura($mysqli, $idCand, $cand['estado'], 'convite_enviado', $idAdmin, $notaHist);

        $mysqli->commit();

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $baseUrl  = $protocol . $_SERVER['HTTP_HOST'];
        $link     = $baseUrl . '/incubadora_ispsn/public/register.php?invite=' . $token;

        $primeiroNome = explode(' ', trim($cand['nome']))[0];
        $isPre = ($cand['tipo_candidato'] ?? '') === 'pre_licenciado';
        $detalhesEst = !$isPre ? "\n• *Nº de Estudante:* {$cand['numero_estudante']}" : "";

        $msg = "Olá, {$primeiroNome}! 🎉\n\n";
        $msg .= "Após a validação da sua presença e reunião presencial com a Direção, a sua admissão na *Incubadora Académica ISPSN* foi *CONCLUÍDA COM SUCESSO!* 🚀🌟\n\n";
        $msg .= "Para criar a sua conta oficial no portal e ativar o seu projeto incubado, aceda ao link seguro exclusivo abaixo:\n\n";
        $msg .= "🔗 {$link}\n\n";
        $msg .= "⏰ *Atenção:* Este link é pessoal, intransferível e válido por apenas *48 horas*.\n\n";
        $msg .= "Ao registar-se, use os seus dados oficiais:\n";
        $msg .= "• *E-mail:* {$cand['email']}{$detalhesEst}\n\n";
        $msg .= "Bem-vindo(a) oficialmente ao ecossistema da Incubadora Académica ISPSN!\n\n";
        $msg .= "Atenciosamente,\n";
        $msg .= "_Direção da Incubadora Académica ISPSN_";

        $telClean = preg_replace('/\D/', '', $cand['telefone']);
        if (strlen($telClean) === 9 && str_starts_with($telClean, '9')) {
            $telClean = '244' . $telClean;
        }
        $waUrl = 'https://wa.me/' . $telClean . '?text=' . rawurlencode($msg);

        return [
            'sucesso' => true,
            'token' => $token,
            'link_registo' => $link,
            'wa_url' => $waUrl,
            'candidato' => [
                'id' => $cand['id'],
                'nome' => $cand['nome'],
                'email' => $cand['email'],
                'telefone' => $cand['telefone']
            ]
        ];
    } catch (Exception $e) {
        $mysqli->rollback();
        return [
            'sucesso' => false,
            'erro' => $e->getMessage()
        ];
    }
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
    // Apenas transições de triagem/decisão são manuais aqui; o fluxo de convocatória/convite exige rotas dedicadas
    $estadosManuais = ['pendente','em_analise','selecionado','rejeitado'];

    if ($idCand && in_array($estado, $estadosManuais)) {
        // Apenas Super Admin (DG/PR) pode aprovar ou rejeitar candidaturas na decisão
        $perfilUsuario = $_SESSION['usuario_perfil'] ?? '';
        if (in_array($estado, ['selecionado', 'rejeitado']) && $perfilUsuario !== 'superadmin') {
            $_SESSION['flash_erro'] = '⚠️ Permissão negada: Apenas o Super ADMIN (DG/PR) pode aprovar ou rejeitar candidaturas na fase de decisão.';
            header("Location: $redirect"); exit;
        }

        $mysqli->begin_transaction();
        try {
            $stmtCur = $mysqli->prepare("SELECT estado FROM candidaturas WHERE id = ? FOR UPDATE");
            $stmtCur->bind_param('i', $idCand);
            $stmtCur->execute();
            $estAtual = $stmtCur->get_result()->fetch_assoc()['estado'] ?? 'pendente';
            $stmtCur->close();

            $stmt = $mysqli->prepare("UPDATE candidaturas SET estado=?, avaliado_por=?, avaliado_em=NOW() WHERE id=?");
            $stmt->bind_param('sii', $estado, $idAdmin, $idCand);
            $stmt->execute();
            $stmt->close();

            registarHistoricoCandidatura($mysqli, $idCand, $estAtual, $estado, $idAdmin, "Alteração de estado via painel administrativo");
            $mysqli->commit();

            $label = ['selecionado'=>'Selecionado ✓','rejeitado'=>'Rejeitado ✗','em_analise'=>'Em Análise','pendente'=>'Pendente'];
            $_SESSION['flash_ok'] = 'Candidatura marcada como: ' . ($label[$estado] ?? $estado);
        } catch (Exception $e) {
            $mysqli->rollback();
            $_SESSION['flash_erro'] = 'Erro ao atualizar candidatura: ' . $e->getMessage();
        }
    } else {
        $_SESSION['flash_erro'] = 'Transição de estado inválida para esta operação.';
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

/* ── CONVOCAR CANDIDATO PARA REUNIÃO PRESENCIAL ── */
if ($action === 'convocar_candidato') {
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $dataReuniao = trim($_POST['data_reuniao'] ?? '');
    $horaReuniao = trim($_POST['hora_reuniao'] ?? '');
    $localReuniao = trim($_POST['local_reuniao'] ?? 'Gabinete da Incubadora Académica - ISPSN');
    $documentos = trim($_POST['documentos_a_levar'] ?? 'Bilhete de Identidade, Cartão de Estudante e Dossier preliminar');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (!$idCand || empty($dataReuniao) || empty($horaReuniao)) {
        $erro = 'Data e hora da reunião são de preenchimento obrigatório.';
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $erro]); exit; }
        $_SESSION['flash_erro'] = $erro;
        header("Location: $redirect"); exit;
    }

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) throw new Exception("Candidatura não encontrada.");
        if ($cand['estado'] !== 'selecionado') {
            throw new Exception("Operação recusada: apenas candidaturas em estado 'selecionado' podem ser convocadas. Estado atual: " . $cand['estado']);
        }

        // Marcar qualquer convocatória agendada anterior como reagendada (unicidade da ativa)
        $stmtOld = $mysqli->prepare("UPDATE convocatorias SET estado_convocatoria = 'reagendada' WHERE candidatura_id = ? AND estado_convocatoria = 'agendada'");
        $stmtOld->bind_param('i', $idCand);
        $stmtOld->execute();
        $stmtOld->close();

        // Inserir nova convocatória oficial (SEM link de registo)
        $stmtIns = $mysqli->prepare("
            INSERT INTO convocatorias (candidatura_id, data_reuniao, hora_reuniao, local_reuniao, documentos_a_levar, observacoes, convocado_por, convocado_em, estado_convocatoria)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'agendada')
        ");
        $stmtIns->bind_param('isssssi', $idCand, $dataReuniao, $horaReuniao, $localReuniao, $documentos, $observacoes, $idAdmin);
        $stmtIns->execute();
        $stmtIns->close();

        // Atualizar estado da candidatura
        $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'convocado' WHERE id = ?");
        $stmtUp->bind_param('i', $idCand);
        $stmtUp->execute();
        $stmtUp->close();

        registarHistoricoCandidatura($mysqli, $idCand, 'selecionado', 'convocado', $idAdmin, "Convocado para reunião presencial em {$dataReuniao} às {$horaReuniao} ({$localReuniao})");
        $mysqli->commit();

        $convInfo = [
            'data_reuniao' => $dataReuniao,
            'hora_reuniao' => $horaReuniao,
            'local_reuniao' => $localReuniao,
            'documentos_a_levar' => $documentos,
            'observacoes' => $observacoes
        ];
        $msgData = gerarMensagemConvocatoria($cand, $convInfo);

        if ($isAjaxCand) {
            echo json_encode([
                'sucesso' => true,
                'wa_url' => $msgData['wa_url'],
                'mensagem' => $msgData['texto'],
                'candidato' => ['id' => $cand['id'], 'nome' => $cand['nome'], 'telefone' => $cand['telefone']]
            ]);
            exit;
        }

        $_SESSION['flash_ok'] = "Candidato {$cand['nome']} convocado para a reunião com sucesso!";
        $_SESSION['wa_redirect'] = $msgData['wa_url'];
        header("Location: $redirect"); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]); exit; }
        $_SESSION['flash_erro'] = $e->getMessage();
        header("Location: $redirect"); exit;
    }
}

/* ── REAGENDAR CONVOCATÓRIA ──────────────── */
if ($action === 'reagendar_convocatoria') {
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $dataReuniao = trim($_POST['data_reuniao'] ?? '');
    $horaReuniao = trim($_POST['hora_reuniao'] ?? '');
    $localReuniao = trim($_POST['local_reuniao'] ?? 'Gabinete da Incubadora Académica - ISPSN');
    $documentos = trim($_POST['documentos_a_levar'] ?? 'Bilhete de Identidade, Cartão de Estudante e Dossier preliminar');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (!$idCand || empty($dataReuniao) || empty($horaReuniao)) {
        $erro = 'Data e hora da reunião são obrigatórias para reagendamento.';
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $erro]); exit; }
        $_SESSION['flash_erro'] = $erro;
        header("Location: $redirect"); exit;
    }

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) throw new Exception("Candidatura não encontrada.");
        if ($cand['estado'] !== 'convocado') {
            throw new Exception("Operação recusada: apenas candidaturas em estado 'convocado' podem ser reagendadas. Estado atual: " . $cand['estado']);
        }

        // Marcar anterior como reagendada
        $stmtOld = $mysqli->prepare("UPDATE convocatorias SET estado_convocatoria = 'reagendada' WHERE candidatura_id = ? AND estado_convocatoria = 'agendada'");
        $stmtOld->bind_param('i', $idCand);
        $stmtOld->execute();
        $stmtOld->close();

        // Inserir nova convocatória ativa
        $stmtIns = $mysqli->prepare("
            INSERT INTO convocatorias (candidatura_id, data_reuniao, hora_reuniao, local_reuniao, documentos_a_levar, observacoes, convocado_por, convocado_em, estado_convocatoria)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'agendada')
        ");
        $stmtIns->bind_param('isssssi', $idCand, $dataReuniao, $horaReuniao, $localReuniao, $documentos, $observacoes, $idAdmin);
        $stmtIns->execute();
        $stmtIns->close();

        registarHistoricoCandidatura($mysqli, $idCand, 'convocado', 'convocado', $idAdmin, "Reagendamento de reunião para {$dataReuniao} às {$horaReuniao}. Obs: {$observacoes}");
        $mysqli->commit();

        $convInfo = [
            'data_reuniao' => $dataReuniao,
            'hora_reuniao' => $horaReuniao,
            'local_reuniao' => $localReuniao,
            'documentos_a_levar' => $documentos,
            'observacoes' => $observacoes
        ];
        $msgData = gerarMensagemConvocatoria($cand, $convInfo);

        if ($isAjaxCand) {
            echo json_encode([
                'sucesso' => true,
                'wa_url' => $msgData['wa_url'],
                'mensagem' => $msgData['texto'],
                'candidato' => ['id' => $cand['id'], 'nome' => $cand['nome'], 'telefone' => $cand['telefone']]
            ]);
            exit;
        }

        $_SESSION['flash_ok'] = "Reunião de {$cand['nome']} reagendada com sucesso!";
        $_SESSION['wa_redirect'] = $msgData['wa_url'];
        header("Location: $redirect"); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]); exit; }
        $_SESSION['flash_erro'] = $e->getMessage();
        header("Location: $redirect"); exit;
    }
}

/* ── RECONVOCAR CANDIDATO (AUSENTE → CONVOCADO) ──── */
if ($action === 'reconvocar_candidato') {
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $dataReuniao = trim($_POST['data_reuniao'] ?? '');
    $horaReuniao = trim($_POST['hora_reuniao'] ?? '');
    $localReuniao = trim($_POST['local_reuniao'] ?? 'Gabinete da Incubadora Académica - ISPSN');
    $documentos = trim($_POST['documentos_a_levar'] ?? 'Bilhete de Identidade, Cartão de Estudante e Dossier preliminar');
    $observacoes = trim($_POST['observacoes'] ?? '');

    if (!$idCand || empty($dataReuniao) || empty($horaReuniao)) {
        $erro = 'Data e hora da reunião são obrigatórias para reconvocatória.';
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $erro]); exit; }
        $_SESSION['flash_erro'] = $erro;
        header("Location: $redirect"); exit;
    }

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) throw new Exception("Candidatura não encontrada.");
        if ($cand['estado'] !== 'ausente') {
            throw new Exception("Operação recusada: apenas candidatos em estado 'ausente' podem ser reconvocados. Estado atual: " . $cand['estado']);
        }

        // Inserir nova convocatória ativa
        $stmtIns = $mysqli->prepare("
            INSERT INTO convocatorias (candidatura_id, data_reuniao, hora_reuniao, local_reuniao, documentos_a_levar, observacoes, convocado_por, convocado_em, estado_convocatoria)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'agendada')
        ");
        $stmtIns->bind_param('isssssi', $idCand, $dataReuniao, $horaReuniao, $localReuniao, $documentos, $observacoes, $idAdmin);
        $stmtIns->execute();
        $stmtIns->close();

        // Mudar estado para convocado
        $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'convocado' WHERE id = ?");
        $stmtUp->bind_param('i', $idCand);
        $stmtUp->execute();
        $stmtUp->close();

        registarHistoricoCandidatura($mysqli, $idCand, 'ausente', 'convocado', $idAdmin, "Candidato reconvocado após ausência para {$dataReuniao} às {$horaReuniao}");
        $mysqli->commit();

        $convInfo = [
            'data_reuniao' => $dataReuniao,
            'hora_reuniao' => $horaReuniao,
            'local_reuniao' => $localReuniao,
            'documentos_a_levar' => $documentos,
            'observacoes' => $observacoes
        ];
        $msgData = gerarMensagemConvocatoria($cand, $convInfo);

        if ($isAjaxCand) {
            echo json_encode([
                'sucesso' => true,
                'wa_url' => $msgData['wa_url'],
                'mensagem' => $msgData['texto'],
                'candidato' => ['id' => $cand['id'], 'nome' => $cand['nome'], 'telefone' => $cand['telefone']]
            ]);
            exit;
        }

        $_SESSION['flash_ok'] = "Candidato reconvocado com sucesso!";
        $_SESSION['wa_redirect'] = $msgData['wa_url'];
        header("Location: $redirect"); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $e->getMessage()]); exit; }
        $_SESSION['flash_erro'] = $e->getMessage();
        header("Location: $redirect"); exit;
    }
}

/* ── MARCAR AUSÊNCIA NA REUNIÃO ──────────── */
if ($action === 'marcar_ausencia') {
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? 'Não compareceu à reunião presencial agendada.');

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) throw new Exception("Candidatura não encontrada.");
        if ($cand['estado'] !== 'convocado') {
            throw new Exception("Operação recusada: apenas candidaturas em estado 'convocado' podem ser marcadas como ausentes.");
        }

        $stmtOld = $mysqli->prepare("UPDATE convocatorias SET estado_convocatoria = 'ausente' WHERE candidatura_id = ? AND estado_convocatoria = 'agendada'");
        $stmtOld->bind_param('i', $idCand);
        $stmtOld->execute();
        $stmtOld->close();

        $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'ausente', observacoes_admin = ? WHERE id = ?");
        $stmtUp->bind_param('si', $motivo, $idCand);
        $stmtUp->execute();
        $stmtUp->close();

        registarHistoricoCandidatura($mysqli, $idCand, 'convocado', 'ausente', $idAdmin, "Falta na reunião presencial: " . $motivo);
        $mysqli->commit();

        $_SESSION['flash_ok'] = "Candidatura de {$cand['nome']} marcada como ausente.";
        header("Location: $redirect"); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['flash_erro'] = $e->getMessage();
        header("Location: $redirect"); exit;
    }
}

/* ── MARCAR RECUSA DA ADMISSÃO ───────────── */
if ($action === 'marcar_recusa') {
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? 'Candidato comunicou desistência ou recusa da vaga.');

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) throw new Exception("Candidatura não encontrada.");
        if (!in_array($cand['estado'], ['selecionado', 'convocado'])) {
            throw new Exception("Operação recusada: apenas candidaturas em 'selecionado' ou 'convocado' podem ser marcadas como recusa.");
        }

        $stmtOld = $mysqli->prepare("UPDATE convocatorias SET estado_convocatoria = 'recusou' WHERE candidatura_id = ? AND estado_convocatoria = 'agendada'");
        $stmtOld->bind_param('i', $idCand);
        $stmtOld->execute();
        $stmtOld->close();

        $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'recusou', observacoes_admin = ? WHERE id = ?");
        $stmtUp->bind_param('si', $motivo, $idCand);
        $stmtUp->execute();
        $stmtUp->close();

        registarHistoricoCandidatura($mysqli, $idCand, $cand['estado'], 'recusou', $idAdmin, "Candidato recusou admissão: " . $motivo);
        $mysqli->commit();

        $_SESSION['flash_ok'] = "Candidatura de {$cand['nome']} registada como recusa.";
        header("Location: $redirect"); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['flash_erro'] = $e->getMessage();
        header("Location: $redirect"); exit;
    }
}

/* ── REPROVAR NA REUNIÃO PRESENCIAL ──────── */
if ($action === 'reprovar_reuniao') {
    if ($perfilUsuario !== 'superadmin') {
        $_SESSION['flash_erro'] = '⚠️ Permissão negada: Apenas a Direção Geral (Super Admin) pode reprovar candidaturas pós-reunião.';
        header("Location: $redirect"); exit;
    }
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $motivo = trim($_POST['motivo'] ?? 'Projeto/candidato não validado na entrevista com a Direção da Incubadora.');

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) throw new Exception("Candidatura não encontrada.");
        if ($cand['estado'] !== 'convocado') {
            throw new Exception("Operação recusada: apenas candidaturas em estado 'convocado' podem ser reprovadas na fase de reunião.");
        }

        $stmtOld = $mysqli->prepare("UPDATE convocatorias SET estado_convocatoria = 'reprovada' WHERE candidatura_id = ? AND estado_convocatoria = 'agendada'");
        $stmtOld->bind_param('i', $idCand);
        $stmtOld->execute();
        $stmtOld->close();

        $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'reprovado_reuniao', observacoes_admin = ?, avaliado_por = ?, avaliado_em = NOW() WHERE id = ?");
        $stmtUp->bind_param('sii', $motivo, $idAdmin, $idCand);
        $stmtUp->execute();
        $stmtUp->close();

        registarHistoricoCandidatura($mysqli, $idCand, 'convocado', 'reprovado_reuniao', $idAdmin, "Reprovado na reunião presencial: " . $motivo);
        $mysqli->commit();

        $_SESSION['flash_ok'] = "Candidatura de {$cand['nome']} marcada como reprovada na reunião presencial.";
        header("Location: $redirect"); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['flash_erro'] = $e->getMessage();
        header("Location: $redirect"); exit;
    }
}

/* ── VALIDAR PRESENÇA PRESENCIAL (SUPER ADMIN) ── */
if ($action === 'validar_presencial_cand') {
    if ($perfilUsuario !== 'superadmin') {
        $_SESSION['flash_erro'] = '⚠️ Permissão negada: Apenas a Direção Geral (Super Admin) pode validar presenças presenciais.';
        header("Location: $redirect"); exit;
    }
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $obsVal = trim($_POST['observacoes'] ?? 'Presença validada e entrevista presencial com parecer favorável.');

    $mysqli->begin_transaction();
    try {
        $stmt = $mysqli->prepare("SELECT * FROM candidaturas WHERE id = ? FOR UPDATE");
        $stmt->bind_param('i', $idCand);
        $stmt->execute();
        $cand = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cand) throw new Exception("Candidatura não encontrada.");
        if ($cand['estado'] !== 'convocado') {
            throw new Exception("Operação recusada: apenas candidaturas em estado 'convocado' podem ter presença validada. Estado atual: " . $cand['estado']);
        }

        // Atualizar convocatória para compareceu e gravar validador
        $stmtConv = $mysqli->prepare("
            UPDATE convocatorias 
            SET presenca_validada_em = NOW(), validado_por = ?, estado_convocatoria = 'compareceu', observacoes = CONCAT(IFNULL(observacoes,''), '\n[Validação DG/PR]: ', ?)
            WHERE candidatura_id = ? AND estado_convocatoria = 'agendada'
        ");
        $stmtConv->bind_param('isi', $idAdmin, $obsVal, $idCand);
        $stmtConv->execute();
        $stmtConv->close();

        // Atualizar candidatura para validado_presencial
        $stmtUp = $mysqli->prepare("UPDATE candidaturas SET estado = 'validado_presencial', avaliado_por = ?, avaliado_em = NOW() WHERE id = ?");
        $stmtUp->bind_param('ii', $idAdmin, $idCand);
        $stmtUp->execute();
        $stmtUp->close();

        registarHistoricoCandidatura($mysqli, $idCand, 'convocado', 'validado_presencial', $idAdmin, "Presença validada pelo Super Admin. Parecer: " . $obsVal);
        $mysqli->commit();

        $_SESSION['flash_ok'] = "🎉 Presença e reunião de {$cand['nome']} validadas com sucesso! O candidato está agora apto para receber o convite de registo de conta.";
        header("Location: $redirect"); exit;
    } catch (Exception $e) {
        $mysqli->rollback();
        $_SESSION['flash_erro'] = $e->getMessage();
        header("Location: $redirect"); exit;
    }
}

/* ── ENVIAR CONVITE DE REGISTO (48H) / REENVIAR (SUPER ADMIN) ── */
if ($action === 'enviar_convite_registo' || $action === 'reenviar_convite_registo') {
    if ($perfilUsuario !== 'superadmin') {
        $erroMsg = '⚠️ Permissão negada: Apenas a Direção Geral (Super Admin) pode emitir convites de registo de conta.';
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $erroMsg]); exit; }
        $_SESSION['flash_erro'] = $erroMsg;
        header("Location: $redirect"); exit;
    }

    $idCand = (int)($_POST['id_cand'] ?? 0);
    if (!$idCand) {
        $erroMsg = 'Candidatura inválida.';
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $erroMsg]); exit; }
        $_SESSION['flash_erro'] = $erroMsg;
        header("Location: $redirect"); exit;
    }

    $isReenvio = ($action === 'reenviar_convite_registo');
    $res = gerarConviteRegisto($mysqli, $idCand, $idAdmin, $isReenvio);

    if ($isAjaxCand) {
        echo json_encode($res);
        exit;
    }

    if ($res['sucesso']) {
        $_SESSION['flash_ok'] = ($isReenvio ? "🔄 Convite reemitido com sucesso!" : "✅ Convite oficial gerado com sucesso!") . " Link de 48h gerado. WhatsApp aberto.";
        $_SESSION['wa_redirect'] = $res['wa_url'];
    } else {
        $_SESSION['flash_erro'] = $res['erro'];
    }
    header("Location: $redirect"); exit;
}

/* ── COMPATIBILIDADE LEGADA: REDIRECIONAR PARA GERAR CONVITE SEGURO ── */
if ($action === 'gerar_convite_seguro' || $action === 'gerar_convite_ajax') {
    // REGRA DE SEGURANÇA: Qualquer chamada legada é tratada como enviar_convite_registo e exige validado_presencial
    if ($perfilUsuario !== 'superadmin') {
        $erroMsg = '⚠️ Permissão negada: Apenas a Direção Geral (Super Admin) pode gerar links de registo.';
        if ($isAjaxCand) { echo json_encode(['sucesso' => false, 'erro' => $erroMsg]); exit; }
        $_SESSION['flash_erro'] = $erroMsg;
        header("Location: $redirect"); exit;
    }
    $idCand = (int)($_POST['id_cand'] ?? 0);
    $res = gerarConviteRegisto($mysqli, $idCand, $idAdmin, false);
    if ($isAjaxCand) {
        echo json_encode($res);
        exit;
    }
    if ($res['sucesso']) {
        $_SESSION['flash_ok'] = "✅ Convite gerado! Link válido 48h. WhatsApp aberto automaticamente.";
        $_SESSION['wa_redirect'] = $res['wa_url'];
    } else {
        $_SESSION['flash_erro'] = $res['erro'];
    }
    header("Location: $redirect"); exit;
}

/* ── AVALIAR PITCH DE CANDIDATURA ────────── */
if ($action === 'avaliar_pitch_candidatura') {
    $idCand                 = (int)($_POST['id_cand'] ?? 0);
    $pitchProblema          = min(10, max(0, (float)($_POST['pitch_problema'] ?? 0)));
    $pitchSolucao           = min(10, max(0, (float)($_POST['pitch_solucao'] ?? 0)));
    $pitchModeloNegocio     = min(10, max(0, (float)($_POST['pitch_modelo_negocio'] ?? 0)));
    $pitchInovacao          = min(10, max(0, (float)($_POST['pitch_inovacao'] ?? 0)));
    $pitchSustentabilidade  = min(10, max(0, (float)($_POST['pitch_sustentabilidade'] ?? 0)));
    $pitchObservacoes       = trim($_POST['pitch_observacoes'] ?? '');

    if ($idCand > 0) {
        $pitchNotaFinal = ($pitchProblema + $pitchSolucao + $pitchModeloNegocio + $pitchInovacao + $pitchSustentabilidade) / 5;

        $stmt = $mysqli->prepare("
            UPDATE candidaturas SET 
                pitch_problema = ?,
                pitch_solucao = ?,
                pitch_modelo_negocio = ?,
                pitch_inovacao = ?, 
                pitch_sustentabilidade = ?, 
                pitch_nota_final = ?, 
                pitch_observacoes = ?, 
                pitch_avaliado_por = ?, 
                pitch_avaliado_em = NOW(),
                estado = 'em_analise'
            WHERE id = ?
        ");
        $stmt->bind_param('ddddddsii', 
            $pitchProblema,
            $pitchSolucao,
            $pitchModeloNegocio,
            $pitchInovacao, 
            $pitchSustentabilidade, 
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
    obrigarPerfil(['admin', 'superadmin']);
    $idCand = (int)($_POST['id_cand'] ?? 0);

    if ($idCand > 0) {
        // Remover convites de conta associados em cascata (permite limpeza de candidaturas de teste)
        $stmtDelConv = $mysqli->prepare("DELETE FROM convites WHERE id_candidatura = ?");
        $stmtDelConv->bind_param('i', $idCand);
        $stmtDelConv->execute();
        $stmtDelConv->close();

        // Remover a candidatura
        $stmt = $mysqli->prepare("DELETE FROM candidaturas WHERE id = ?");
        $stmt->bind_param('i', $idCand);
        if ($stmt->execute()) {
            $_SESSION['flash_ok'] = '🗑️ Candidatura e convites de teste eliminados com sucesso!';
        } else {
            $_SESSION['flash_erro'] = 'Erro ao remover a candidatura: ' . $mysqli->error;
        }
        $stmt->close();
    }
    header("Location: $redirect");
    exit;
}

header("Location: $redirect"); exit;

