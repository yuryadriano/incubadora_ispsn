<?php
// public/forgot_password.php
// Recuperação de password — passo 1: inserir email

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) session_start();

// Se já logado, redirecionar
if (!empty($_SESSION['usuario_id'])) {
    header('Location: /incubadora_ispsn/public/index.php');
    exit;
}

$mensagem = '';
$tipo     = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verificar();
    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = 'Por favor, introduza um e-mail válido.';
        $tipo     = 'erro';
    } else {
        // Verificar se o email existe e conta está activa
        $stmt = $mysqli->prepare("SELECT id, nome FROM usuarios WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
            // Gerar token seguro
            $token     = bin2hex(random_bytes(32)); // 64 chars hex
            $expiracao = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $idUser    = $user['id'];

            // Garantir que a tabela existe (criamos aqui para zero downtime)
            $mysqli->query("CREATE TABLE IF NOT EXISTS password_resets (
                id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                id_usuario INT NOT NULL,
                token     VARCHAR(128) NOT NULL,
                expiracao DATETIME NOT NULL,
                usado     TINYINT(1) DEFAULT 0,
                criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX (token),
                INDEX (id_usuario)
            )");

            // Invalidar tokens anteriores do mesmo user
            $del = $mysqli->prepare("DELETE FROM password_resets WHERE id_usuario = ?");
            $del->bind_param('i', $idUser);
            $del->execute();
            $del->close();

            // Inserir novo token
            $ins = $mysqli->prepare("INSERT INTO password_resets (id_usuario, token, expiracao) VALUES (?, ?, ?)");
            $ins->bind_param('iss', $idUser, $token, $expiracao);
            $ins->execute();
            $ins->close();

            // Enviar email
            $baseUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
            $linkReset = $baseUrl . '/incubadora_ispsn/public/reset_password.php?token=' . $token;
            $primeiroNome = htmlspecialchars(explode(' ', $user['nome'])[0]);

            $assunto = 'Recuperação de Password — Incubadora ISPSN';
            $corpo   = "<!DOCTYPE html>
<html lang='pt'>
<head><meta charset='utf-8'>
<style>
  body { font-family: 'Inter', sans-serif; background: #f8fafc; color: #334155; margin: 0; }
  .container { max-width: 560px; margin: 40px auto; background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid #e2e8f0; }
  .header { background: #0f172a; padding: 28px; text-align: center; }
  .logo { color: #fff; font-size: 20px; font-weight: 800; }
  .logo span { color: #f59e0b; }
  .body { padding: 36px; }
  .btn { display: inline-block; padding: 14px 32px; background: #D97706; color: #fff !important; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 15px; margin: 20px 0; }
  .note { background: #FEF3C7; border-left: 4px solid #D97706; padding: 12px 16px; border-radius: 8px; font-size: 13px; color: #92400E; margin-top: 20px; }
  .footer { background: #f8fafc; padding: 20px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
</style>
</head>
<body>
  <div class='container'>
    <div class='header'><div class='logo'>INCUBADORA<span>ISPSN</span></div></div>
    <div class='body'>
      <h3 style='margin-top:0; font-weight:800; color:#0f172a;'>Olá, {$primeiroNome}! 👋</h3>
      <p>Recebemos um pedido de recuperação de password para a sua conta na Incubadora Académica ISPSN.</p>
      <p>Clique no botão abaixo para definir uma nova password:</p>
      <div style='text-align:center;'>
        <a href='{$linkReset}' class='btn'>🔑 Redefinir a minha Password</a>
      </div>
      <div class='note'>
        <strong>⏰ Atenção:</strong> Este link é válido por apenas <strong>1 hora</strong> e só pode ser usado <strong>uma vez</strong>.<br>
        Se não solicitou esta recuperação, ignore este email — a sua conta está segura.
      </div>
    </div>
    <div class='footer'>
      © " . date('Y') . " ISPSN — Sistema de Gestão da Incubadora Académica<br>
      <a href='http://www.ispsn.org' style='color:#D97706;'>www.ispsn.org</a>
    </div>
  </div>
</body>
</html>";

            require_once __DIR__ . '/../app/utils/Mailer.php';
            $errMail = '';
            \App\Utils\Mailer::send($email, $assunto, $corpo, $errMail);

            // Mensagem de sucesso genérica (não revelar se o email existe ou não — anti-enumeration)
            $mensagem = 'Se existir uma conta com esse e-mail, receberá um link de recuperação em breve. Verifique também a pasta de spam.';
            $tipo     = 'ok';
        } else {
            // Mesmo se email não existir, dar a mesma mensagem (anti-enumeration)
            $mensagem = 'Se existir uma conta com esse e-mail, receberá um link de recuperação em breve. Verifique também a pasta de spam.';
            $tipo     = 'ok';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
<meta charset="UTF-8">
<title>Recuperar Password — Incubadora ISPSN</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #0f172a;
    padding: 20px;
    position: relative;
    overflow: hidden;
}
body::before {
    content: '';
    position: absolute;
    width: 600px; height: 600px;
    background: radial-gradient(circle, rgba(217,119,6,0.12) 0%, transparent 70%);
    top: -200px; right: -200px;
}
body::after {
    content: '';
    position: absolute;
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(99,102,241,0.08) 0%, transparent 70%);
    bottom: -150px; left: -150px;
}
.card {
    background: #1e293b;
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 24px;
    padding: 44px 40px;
    width: 100%;
    max-width: 420px;
    position: relative;
    z-index: 1;
    box-shadow: 0 40px 80px rgba(0,0,0,0.5);
    animation: fadeUp 0.5s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(20px); }
    to   { opacity: 1; transform: translateY(0); }
}
.logo { text-align: center; margin-bottom: 32px; }
.logo-text { font-size: 22px; font-weight: 800; color: #fff; letter-spacing: -0.5px; }
.logo-text span { color: #F59E0B; }
.logo-sub { font-size: 12px; color: rgba(255,255,255,0.35); margin-top: 4px; letter-spacing: 0.5px; text-transform: uppercase; }

.icon-circle {
    width: 64px; height: 64px; border-radius: 50%;
    background: rgba(217,119,6,0.15);
    border: 1px solid rgba(217,119,6,0.3);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 24px;
    font-size: 24px; color: #F59E0B;
}
h2 { color: #fff; font-size: 1.5rem; font-weight: 800; text-align: center; margin-bottom: 8px; }
.sub { color: rgba(255,255,255,0.45); font-size: 0.83rem; text-align: center; margin-bottom: 28px; line-height: 1.6; }
label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; color: rgba(255,255,255,0.5); margin-bottom: 8px; }
input[type="email"] {
    width: 100%;
    padding: 13px 16px;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 12px;
    color: #fff;
    font-size: 0.95rem;
    font-family: 'Inter', sans-serif;
    transition: 0.2s;
    outline: none;
}
input[type="email"]:focus { border-color: #D97706; background: rgba(217,119,6,0.05); }
input[type="email"]::placeholder { color: rgba(255,255,255,0.2); }
.btn-submit {
    width: 100%; margin-top: 20px;
    padding: 14px;
    background: linear-gradient(135deg, #D97706, #B45309);
    color: #fff; border: none; border-radius: 12px;
    font-size: 0.95rem; font-weight: 700;
    cursor: pointer; transition: 0.2s;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.btn-submit:hover { transform: translateY(-1px); box-shadow: 0 8px 24px rgba(217,119,6,0.35); }
.alert {
    padding: 14px 16px; border-radius: 12px;
    font-size: 0.84rem; margin-bottom: 20px; display: flex; align-items: flex-start; gap: 10px;
}
.alert-ok   { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6EE7B7; }
.alert-erro { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  color: #FCA5A5; }
.back-link  { display: block; text-align: center; margin-top: 24px; color: rgba(255,255,255,0.35); font-size: 0.82rem; text-decoration: none; transition: color 0.2s; }
.back-link:hover { color: #F59E0B; }
</style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-text">INCUBADORA<span>ISPSN</span></div>
        <div class="logo-sub">Sistema de Gestão</div>
    </div>

    <div class="icon-circle"><i class="fa fa-key"></i></div>
    <h2>Recuperar Password</h2>
    <p class="sub">Introduza o seu e-mail institucional e enviaremos um link para redefinir a sua password.</p>

    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo === 'ok' ? 'ok' : 'erro' ?>">
        <i class="fa fa-<?= $tipo === 'ok' ? 'circle-check' : 'triangle-exclamation' ?>"></i>
        <span><?= htmlspecialchars($mensagem) ?></span>
    </div>
    <?php endif; ?>

    <?php if ($tipo !== 'ok'): ?>
    <form method="POST" action="">
        <?= csrf_field() ?>
        <div>
            <label for="email">E-mail Institucional</label>
            <input type="email" id="email" name="email" required
                   placeholder="nome@ispsn.org"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <button type="submit" class="btn-submit">
            <i class="fa fa-paper-plane"></i> Enviar Link de Recuperação
        </button>
    </form>
    <?php else: ?>
    <div style="text-align:center; padding: 10px 0;">
        <i class="fa fa-envelope-open fa-2x" style="color:#F59E0B; margin-bottom:12px;"></i>
        <p style="color:rgba(255,255,255,0.5); font-size:0.83rem;">Verifique a sua caixa de entrada e clique no link que enviámos.</p>
    </div>
    <?php endif; ?>

    <a href="/incubadora_ispsn/public/login.php" class="back-link">
        <i class="fa fa-arrow-left me-1"></i> Voltar ao Login
    </a>
</div>
</body>
</html>
