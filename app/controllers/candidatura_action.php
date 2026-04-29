<?php
// app/controllers/candidatura_action.php
require_once __DIR__ . '/../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$action   = $_POST['action'] ?? '';
$redirect = $_POST['redirect'] ?? '/incubadora_ispsn/app/views/admin/candidaturas.php';
$idAdmin  = (int)$_SESSION['usuario_id'];

if (!str_starts_with($redirect, '/incubadora_ispsn/')) {
    $redirect = '/incubadora_ispsn/app/views/admin/candidaturas.php';
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
        $res = $mysqli->query("SELECT estado FROM processos_candidatura WHERE id=$id");
        $proc = $res->fetch_assoc();
        $novoEstado = ($proc['estado'] === 'aberto') ? 'fechado' : 'aberto';
        $mysqli->query("UPDATE processos_candidatura SET estado='$novoEstado', atualizado_em=NOW() WHERE id=$id");
        $_SESSION['flash_ok'] = $novoEstado === 'aberto'
            ? '✅ Inscrições abertas ao público!'
            : '🔒 Inscrições encerradas. Novos formulários bloqueados.';
    }
    header("Location: $redirect"); exit;
}

/* ── MUDAR ESTADO DE CANDIDATURA ─────────── */
if ($action === 'mudar_estado_cand') {
    $idCand  = (int)($_POST['id_cand'] ?? 0);
    $estado  = $_POST['estado'] ?? '';
    $estados = ['pendente','em_analise','selecionado','rejeitado','convite_enviado','registado'];

    if ($idCand && in_array($estado, $estados)) {
        $stmt = $mysqli->prepare("UPDATE candidaturas SET estado=?, avaliado_por=?, avaliado_em=NOW() WHERE id=?");
        $stmt->bind_param('sii', $estado, $idAdmin, $idCand);
        $stmt->execute();
        $label = ['selecionado'=>'Selecionado ✓','rejeitado'=>'Rejeitado ✗','em_analise'=>'Em Análise'];
        $_SESSION['flash_ok'] = 'Candidatura marcada como: ' . ($label[$estado] ?? $estado);
    }
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
    $msg = "Olá {$primeiroNome}! 🎉\n\n";
    $msg .= "A sua candidatura à *Incubadora Académica ISPSN* foi *APROVADA!* 🚀\n\n";
    $msg .= "Para criar a sua conta no portal e iniciar o processo, aceda ao link abaixo:\n\n";
    $msg .= "🔗 {$link}\n\n";
    $msg .= "⏰ *Atenção:* Este link é válido por apenas *48 horas* e pode ser usado *uma única vez*.\n\n";
    $msg .= "Ao registar-se, use:\n";
    $msg .= "• *Email:* {$cand['email']}\n";
    $msg .= "• *Nº Estudante:* {$cand['numero_estudante']}\n\n";
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

header("Location: $redirect"); exit;
