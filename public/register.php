<?php
ini_set('display_errors', 0);
require_once __DIR__ . '/../config/config.php';

$erro   = '';
$sucesso = '';

// ── Verificar token obrigatório ──────────────────────
$token    = trim($_GET['invite'] ?? $_POST['token_convite'] ?? '');
$convite  = null;

if ($token) {
    $stmt = $mysqli->prepare("
        SELECT c.*, cand.numero_estudante as numero_esperado, cand.nome as nome_sugerido
        FROM convites c
        LEFT JOIN candidaturas cand ON cand.id = c.id_candidatura
        WHERE c.token = ? AND c.aceite = 0
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $convite = $stmt->get_result()->fetch_assoc();

    if (!$convite) {
        $erro = 'Este link de convite é inválido ou já foi utilizado.';
        $token = '';
    } elseif (!empty($convite['data_expiracao']) && strtotime($convite['data_expiracao']) < time()) {
        $erro = 'Este link de convite expirou. Contacte a Incubadora para solicitar um novo.';
        $convite = null;
        $token = '';
    }
}

// Sem token: bloquear registo direto
if (!$token && empty($convite)) {
    // Apenas mostrar mensagem — não expor o formulário
    $bloqueado = true;
} else {
    $bloqueado = false;
}

// ── Processar registo ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$bloqueado && $convite) {
    $nome             = limpar($_POST['nome'] ?? '');
    $email            = strtolower(limpar($_POST['email'] ?? ''));
    $telefone         = limpar($_POST['telefone'] ?? '');
    $numero_estudante = limpar($_POST['numero_estudante'] ?? '');
    $senha            = $_POST['senha'] ?? '';

    // SEGURANÇA: Ignorar email do POST — usar sempre o do convite
    $email = strtolower(trim($convite['email']));

    // SEGURANÇA: Verificar número de estudante contra a candidatura
    $numeroEsperado = $convite['numero_esperado'] ?? '';
    if ($numeroEsperado && $numero_estudante !== $numeroEsperado) {
        $erro = 'O número de estudante não corresponde ao registado na candidatura. Verifique e tente novamente.';
    } elseif (strlen($nome) < 3) {
        $erro = 'Nome muito curto.';
    } elseif (strlen($numero_estudante) < 5) {
        $erro = 'Número de estudante inválido.';
    } else {
        // Senha = número de estudante (como padrão do sistema)
        $senha  = $numero_estudante;
        $hash   = password_hash($senha, PASSWORD_DEFAULT);
        $perfil = 'utilizador';
        $tipo   = 'estudante';

        // Verificar se email já existe
        $chk = $mysqli->prepare("SELECT id FROM usuarios WHERE email=? LIMIT 1");
        $chk->bind_param('s', $email);
        $chk->execute();
        if ($chk->get_result()->fetch_assoc()) {
            $erro = 'Este email já tem uma conta registada. Aceda ao login.';
        } else {
            $stmt = $mysqli->prepare("
                INSERT INTO usuarios (nome, email, numero_estudante, telefone, senha_hash, perfil, tipo_utilizador, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, 0)
            ");
            $stmt->bind_param('sssssss', $nome, $email, $numero_estudante, $telefone, $hash, $perfil, $tipo);

            if ($stmt->execute()) {
                $novoId = $mysqli->insert_id;

                // Marcar convite como aceite + destruir token
                $stmt2 = $mysqli->prepare("UPDATE convites SET aceite=1 WHERE token=?");
                $stmt2->bind_param('s', $token);
                $stmt2->execute();

                // Atualizar candidatura como registada
                if (!empty($convite['id_candidatura'])) {
                    $stmt3 = $mysqli->prepare("UPDATE candidaturas SET estado='registado' WHERE id=?");
                    $stmt3->bind_param('i', $convite['id_candidatura']);
                    $stmt3->execute();
                }

                // Notificar admins
                $admins = $mysqli->query("SELECT id FROM usuarios WHERE perfil IN ('admin','superadmin') AND activo=1");
                if ($admins) {
                    $sn = $mysqli->prepare("INSERT INTO notificacoes (id_usuario,titulo,mensagem,tipo) VALUES (?,?,?,'sucesso')");
                    $tit = "Novo Registo: $nome";
                    $msg = "$nome ($email) criou conta via convite e aguarda ativação.";
                    while ($a = $admins->fetch_assoc()) { $sn->bind_param('iss',$a['id'],$tit,$msg); $sn->execute(); }
                }

                $sucesso = true;
            } else {
                $erro = 'Erro ao criar conta. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Criar Conta — Incubadora ISPSN</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
:root{--primary:#D97706;--dark:#0F172A;}
*{margin:0;padding:0;box-sizing:border-box;}
body{font-family:'Inter',sans-serif;background:radial-gradient(circle at top left,#FFEDD5,#FDFAF5 50%,#FDE68A);min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;}
.card{width:100%;max-width:460px;background:rgba(255,255,255,0.85);backdrop-filter:blur(16px);border-radius:20px;border:1px solid rgba(255,255,255,0.5);box-shadow:0 24px 48px rgba(0,0,0,0.08);padding:40px;}
.logo-wrap{text-align:center;margin-bottom:28px;}
.logo-wrap img{height:72px;border-radius:12px;}
h1{font-size:1.4rem;font-weight:800;color:var(--dark);text-align:center;margin-bottom:6px;}
.subtitle{font-size:0.875rem;color:#64748B;text-align:center;margin-bottom:28px;}
.alert{padding:12px 16px;border-radius:10px;font-size:0.875rem;margin-bottom:20px;display:flex;align-items:flex-start;gap:10px;}
.alert-danger{background:#FEE2E2;color:#991B1B;border-left:4px solid #EF4444;}
.alert-success{background:#DCFCE7;color:#166534;border-left:4px solid #22C55E;}
.alert-info{background:#EFF6FF;color:#1D4ED8;border-left:4px solid #3B82F6;}
.alert-warning{background:#FEF3C7;color:#92400E;border-left:4px solid #F59E0B;}
.form-group{margin-bottom:18px;}
label{display:block;font-size:0.75rem;font-weight:700;color:#64748B;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;}
input{width:100%;padding:11px 14px;border:1.5px solid #E2E8F0;border-radius:10px;font-size:0.95rem;font-family:'Inter',sans-serif;color:var(--dark);outline:none;transition:all 0.2s;}
input:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(217,119,6,0.1);}
input[readonly]{background:#F8FAFC;color:#94A3B8;cursor:not-allowed;}
.btn{width:100%;padding:14px;background:var(--primary);color:#fff;border:none;border-radius:10px;font-size:1rem;font-weight:700;cursor:pointer;transition:all 0.3s;font-family:'Inter',sans-serif;}
.btn:hover{background:#B45309;transform:translateY(-2px);box-shadow:0 6px 16px rgba(217,119,6,0.3);}
.btn:disabled{opacity:0.6;cursor:not-allowed;transform:none;}
.convite-badge{display:flex;align-items:center;gap:8px;background:#F0FDF4;border:1px solid #BBF7D0;border-radius:10px;padding:10px 14px;margin-bottom:20px;font-size:0.85rem;color:#166534;}
.convite-badge i{color:#22C55E;}
.lock-page{text-align:center;padding:20px 0;}
.lock-page i{font-size:3rem;color:#D97706;margin-bottom:16px;display:block;}
.lock-page h2{font-size:1.3rem;font-weight:800;color:var(--dark);margin-bottom:10px;}
.lock-page p{color:#64748B;font-size:0.9rem;line-height:1.6;}
.success-wrap{text-align:center;padding:10px 0;}
.success-wrap i{font-size:3.5rem;color:#22C55E;display:block;margin-bottom:16px;}
.success-wrap h2{font-size:1.4rem;font-weight:800;color:var(--dark);margin-bottom:10px;}
.success-wrap p{color:#64748B;font-size:0.875rem;line-height:1.6;margin-bottom:6px;}
.success-wrap a{display:inline-block;margin-top:20px;padding:13px 28px;background:var(--primary);color:#fff;border-radius:10px;text-decoration:none;font-weight:700;}
.timer-note{font-size:0.78rem;color:#94A3B8;text-align:center;margin-top:16px;}
</style>
</head>
<body>
<div class="card">
    <div class="logo-wrap">
        <img src="/incubadora_ispsn/assets/img/logo_sn.jpg" alt="ISPSN Logo">
    </div>

    <?php if ($sucesso): ?>
    <div class="success-wrap">
        <i class="fa fa-circle-check"></i>
        <h2>Conta Criada!</h2>
        <p>A sua conta foi criada com sucesso e está a aguardar ativação pela equipa da Incubadora.</p>
        <p>Será notificado via WhatsApp quando o acesso for activado.</p>
        <p style="margin-top:12px;font-size:0.8rem;color:#94A3B8">
            <strong>Senha inicial:</strong> o seu número de estudante
        </p>
        <a href="/incubadora_ispsn/public/login.php">Ir para o Login</a>
    </div>

    <?php elseif ($bloqueado): ?>
    <div class="lock-page">
        <i class="fa fa-shield-halved"></i>
        <h2>Registo Restrito</h2>
        <p>O registo neste portal é feito por <strong>convite exclusivo</strong>.</p>
        <p style="margin-top:12px">Para participar, candidata-te através do nosso website público e aguarda ser selecionado pela equipa.</p>
    </div>
    <div style="text-align:center;margin-top:24px">
        <a href="/incubadora_ispsn/public/website/" style="color:var(--primary);text-decoration:none;font-weight:600;font-size:0.9rem">
            <i class="fa fa-arrow-left"></i> Ir para o Website
        </a>
    </div>

    <?php else: ?>
    <h1>Criar Conta no Portal</h1>
    <p class="subtitle">Incubadora Académica — Instituto Superior Politécnico Sol Nascente</p>

    <?php if ($erro): ?>
    <div class="alert alert-danger"><i class="fa fa-triangle-exclamation"></i><span><?= htmlspecialchars($erro) ?></span></div>
    <?php endif; ?>

    <?php if ($convite): ?>
    <div class="convite-badge">
        <i class="fa fa-envelope-open-text"></i>
        Convite válido — <?= htmlspecialchars($convite['email']) ?>
        <?php if (!empty($convite['data_expiracao'])): ?>
        · expira em <?= date('d/m H:i', strtotime($convite['data_expiracao'])) ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        <span>O seu email institucional já está pré-definido. A palavra-passe inicial será o seu número de estudante.</span>
    </div>

    <form method="post">
        <input type="hidden" name="token_convite" value="<?= htmlspecialchars($token) ?>">

        <div class="form-group">
            <label>Nome Completo *</label>
            <input type="text" name="nome"
                   value="<?= htmlspecialchars($convite['nome_sugerido'] ?? '') ?>"
                   placeholder="O seu nome completo" required>
        </div>

        <div class="form-group">
            <label>Email Institucional</label>
            <input type="email" name="email"
                   value="<?= htmlspecialchars($convite['email'] ?? '') ?>"
                   readonly>
        </div>

        <div class="form-group">
            <label>Número de Estudante * <span style="color:#EF4444">(usado para verificação)</span></label>
            <input type="text" name="numero_estudante"
                   placeholder="Ex: 122400024" required
                   <?= !empty($convite['numero_esperado']) ? '' : '' ?>>
        </div>

        <div class="form-group">
            <label>Telefone / WhatsApp</label>
            <input type="tel" name="telefone"
                   value="<?= htmlspecialchars($convite['telefone'] ?? '') ?>"
                   placeholder="9XXXXXXXX">
        </div>

        <button type="submit" class="btn" id="btnSubmit">
            <i class="fa fa-user-check"></i> Criar Minha Conta
        </button>

        <div class="timer-note">
            <i class="fa fa-clock"></i>
            Este link é de uso único e intransferível.
        </div>
    </form>

    <div style="text-align:center;margin-top:20px;font-size:0.85rem;color:#94A3B8">
        Já tem conta? <a href="/incubadora_ispsn/public/login.php" style="color:var(--primary);font-weight:600">Fazer login</a>
    </div>
    <?php endif; ?>
</div>

<script>
document.querySelector('form')?.addEventListener('submit', () => {
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> A criar conta...';
});
</script>
</body>
</html>
