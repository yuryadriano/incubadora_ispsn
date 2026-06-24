<?php
// public/reset_password.php
// Recuperação de password — passo 2: redefinir com token

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

if (!empty($_SESSION['usuario_id'])) {
    header('Location: /incubadora_ispsn/public/index.php');
    exit;
}

$token    = trim($_GET['token'] ?? '');
$mensagem = '';
$tipo     = '';
$tokenValido = false;
$userInfo    = null;

// Validar token
if ($token) {
    $stmt = $mysqli->prepare("
        SELECT pr.id, pr.id_usuario, u.nome, u.email
        FROM password_resets pr
        JOIN usuarios u ON u.id = pr.id_usuario
        WHERE pr.token = ? AND pr.usado = 0 AND pr.expiracao > NOW()
        LIMIT 1
    ");
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $tokenData = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($tokenData) {
        $tokenValido = true;
        $userInfo    = $tokenData;
    } else {
        $mensagem = 'Este link é inválido ou já expirou. Por favor, solicite um novo link de recuperação.';
        $tipo     = 'erro';
    }
}

// Processar nova password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $tokenValido) {
    csrf_verificar();

    $novaSenha    = $_POST['nova_senha']    ?? '';
    $confirmaSenha = $_POST['confirma_senha'] ?? '';

    if (strlen($novaSenha) < 8) {
        $mensagem = 'A password deve ter pelo menos 8 caracteres.';
        $tipo     = 'erro';
    } elseif ($novaSenha !== $confirmaSenha) {
        $mensagem = 'As passwords não coincidem. Tente novamente.';
        $tipo     = 'erro';
    } else {
        $hash = password_hash($novaSenha, PASSWORD_BCRYPT, ['cost' => 12]);
        $idUser = (int)$userInfo['id_usuario'];

        // Atualizar password
        $stmtUp = $mysqli->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
        $stmtUp->bind_param('si', $hash, $idUser);
        $stmtUp->execute();
        $stmtUp->close();

        // Marcar token como usado
        $stmtTk = $mysqli->prepare("UPDATE password_resets SET usado = 1 WHERE token = ?");
        $stmtTk->bind_param('s', $token);
        $stmtTk->execute();
        $stmtTk->close();

        $mensagem = 'Password redefinida com sucesso! Já pode entrar com a sua nova password.';
        $tipo     = 'ok';
        $tokenValido = false; // Esconder o form após sucesso
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<title>Redefinir Password — Incubadora ISPSN</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif; min-height: 100vh;
    display: flex; align-items: center; justify-content: center;
    background: #0f172a; padding: 20px; overflow: hidden; position: relative;
}
body::before {
    content:''; position:absolute; width:600px; height:600px;
    background: radial-gradient(circle, rgba(217,119,6,0.12) 0%, transparent 70%);
    top:-200px; right:-200px;
}
.card {
    background: #1e293b; border: 1px solid rgba(255,255,255,0.06);
    border-radius: 24px; padding: 44px 40px; width: 100%; max-width: 420px;
    position: relative; z-index: 1; box-shadow: 0 40px 80px rgba(0,0,0,0.5);
    animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fadeUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
.logo { text-align: center; margin-bottom: 28px; }
.logo-text { font-size: 22px; font-weight: 800; color: #fff; }
.logo-text span { color: #F59E0B; }
.icon-circle {
    width:64px; height:64px; border-radius:50%;
    background:rgba(217,119,6,0.15); border:1px solid rgba(217,119,6,0.3);
    display:flex; align-items:center; justify-content:center;
    margin: 0 auto 24px; font-size:24px; color:#F59E0B;
}
h2 { color:#fff; font-size:1.4rem; font-weight:800; text-align:center; margin-bottom:8px; }
.sub { color:rgba(255,255,255,0.4); font-size:0.83rem; text-align:center; margin-bottom:28px; line-height:1.6; }
.field { margin-bottom: 18px; }
label { display:block; font-size:0.72rem; font-weight:700; text-transform:uppercase; letter-spacing:0.5px; color:rgba(255,255,255,0.45); margin-bottom:7px; }
input[type="password"] {
    width:100%; padding:13px 16px;
    background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1);
    border-radius:12px; color:#fff; font-size:0.95rem; font-family:'Inter',sans-serif;
    transition:0.2s; outline:none;
}
input[type="password"]:focus { border-color:#D97706; background:rgba(217,119,6,0.05); }
input[type="password"]::placeholder { color:rgba(255,255,255,0.2); }
.strength-bar { height:4px; border-radius:4px; margin-top:6px; background:#334155; overflow:hidden; }
.strength-fill { height:100%; border-radius:4px; transition:all 0.3s; }
.btn-submit {
    width:100%; padding:14px; background:linear-gradient(135deg,#D97706,#B45309);
    color:#fff; border:none; border-radius:12px; font-size:0.95rem; font-weight:700;
    cursor:pointer; transition:0.2s; display:flex; align-items:center; justify-content:center; gap:8px;
}
.btn-submit:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(217,119,6,0.35); }
.alert { padding:14px 16px; border-radius:12px; font-size:0.84rem; margin-bottom:20px; display:flex; align-items:flex-start; gap:10px; }
.alert-ok   { background:rgba(16,185,129,0.1); border:1px solid rgba(16,185,129,0.3); color:#6EE7B7; }
.alert-erro { background:rgba(239,68,68,0.1);  border:1px solid rgba(239,68,68,0.3);  color:#FCA5A5; }
.back-link  { display:block; text-align:center; margin-top:24px; color:rgba(255,255,255,0.35); font-size:0.82rem; text-decoration:none; transition:color 0.2s; }
.back-link:hover { color:#F59E0B; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-text">INCUBADORA<span>ISPSN</span></div>
    </div>

    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo === 'ok' ? 'ok' : 'erro' ?>">
        <i class="fa fa-<?= $tipo === 'ok' ? 'circle-check' : 'triangle-exclamation' ?>"></i>
        <span><?= htmlspecialchars($mensagem) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($tokenValido): ?>
    <div class="icon-circle"><i class="fa fa-lock-open"></i></div>
    <h2>Nova Password</h2>
    <p class="sub">Olá, <strong style="color:#F59E0B"><?= htmlspecialchars(explode(' ', $userInfo['nome'])[0]) ?></strong>! Escolha uma nova password segura.</p>

    <form method="POST" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="field">
            <label for="nova_senha">Nova Password</label>
            <input type="password" id="nova_senha" name="nova_senha" required
                   placeholder="Mínimo 8 caracteres" oninput="checkStrength(this.value)">
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
        </div>

        <div class="field">
            <label for="confirma_senha">Confirmar Password</label>
            <input type="password" id="confirma_senha" name="confirma_senha" required
                   placeholder="Repita a nova password">
        </div>

        <button type="submit" class="btn-submit">
            <i class="fa fa-shield-halved"></i> Definir Nova Password
        </button>
    </form>

    <?php elseif ($tipo === 'ok'): ?>
    <div class="icon-circle" style="background:rgba(16,185,129,0.15); border-color:rgba(16,185,129,0.3);">
        <i class="fa fa-check" style="color:#10B981;"></i>
    </div>
    <h2 style="color:#10B981;">Sucesso!</h2>
    <p class="sub">A sua password foi redefinida. Clique abaixo para entrar.</p>
    <a href="/incubadora_ispsn/public/login.php"
       style="display:block; text-align:center; padding:14px; background:linear-gradient(135deg,#10B981,#059669); color:#fff; border-radius:12px; font-weight:700; text-decoration:none; margin-top:8px;">
        <i class="fa fa-right-to-bracket me-2"></i> Entrar no Sistema
    </a>
    <?php endif; ?>

    <a href="/incubadora_ispsn/public/login.php" class="back-link">
        <i class="fa fa-arrow-left"></i> Voltar ao Login
    </a>
</div>

<script>
function checkStrength(val) {
    const fill = document.getElementById('strengthFill');
    if (!fill) return;
    let score = 0;
    if (val.length >= 8)  score++;
    if (val.length >= 12) score++;
    if (/[A-Z]/.test(val)) score++;
    if (/[0-9]/.test(val)) score++;
    if (/[^A-Za-z0-9]/.test(val)) score++;

    const colors = ['#EF4444','#F97316','#F59E0B','#10B981','#059669'];
    const widths = ['20%','40%','60%','80%','100%'];
    fill.style.background = colors[score - 1] || '#334155';
    fill.style.width = score > 0 ? widths[score - 1] : '0%';
}
</script>
</body>
</html>
