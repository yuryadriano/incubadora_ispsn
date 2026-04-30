<?php
require_once __DIR__ . '/../config/auth.php';

// Processa login
$erro = '';

// Verificar se existem cookies para preenchimento
$lembrar_email = $_COOKIE['lembrar_email'] ?? '';
$lembrar_senha = $_COOKIE['lembrar_senha'] ?? '';
$lembrar_checked = isset($_COOKIE['lembrar_email']) ? 'checked' : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = limpar($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $lembrar = isset($_POST['lembrar']);

    $usuario = encontrarUsuarioPorEmail($email);

    if ($usuario) {
        // Se ainda estiver com senha TEMP, vamos gerar um hash na primeira entrada
        if ($usuario['senha_hash'] === 'TEMP' && $senha === '123456') {
            $novoHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $mysqli->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $novoHash, $usuario['id']);
            $stmt->execute();
            $usuario['senha_hash'] = $novoHash;
        }

        if (password_verify($senha, $usuario['senha_hash'])) {
            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_perfil'] = $usuario['perfil'];

            registarLogAcesso($usuario['id'], 1);

            // Gerir cookies de "Lembrar-me"
            if ($lembrar) {
                setcookie('lembrar_email', $email, time() + (30 * 24 * 60 * 60), "/");
                setcookie('lembrar_senha', $senha, time() + (30 * 24 * 60 * 60), "/");
            } else {
                setcookie('lembrar_email', '', time() - 3600, "/");
                setcookie('lembrar_senha', '', time() - 3600, "/");
            }

            header('Location: /incubadora_ispsn/public/index.php');
            exit;
        }
    }

    // se chegou aqui, falhou
    if ($usuario) {
        registarLogAcesso($usuario['id'], 0);
    }
    $erro = 'Credenciais inválidas. Verifique o email e a palavra-passe.';
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <title>Login — Incubadora Académica ISPSN</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        :root {
            --primary: #D97706;
            --primary-dark: #B45309;
            --secondary: #1C1917;
            --bg: #FDFAF5;
            --text-main: #0F172A;
            --text-muted: #64748B;
            --radius: 16px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #FFEDD5 0%, #FDFAF5 50%, #FDE68A 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow-x: hidden;
        }

        /* Pattern overlay */
        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d97706' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
            z-index: -1;
        }

        .login-card {
            width: 100%;
            max-width: 440px;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-radius: var(--radius);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            padding: 40px;
            position: relative;
            z-index: 10;
        }

        .logo-wrapper {
            margin-bottom: 30px;
            text-align: center;
        }

        .logo-img {
            max-height: 90px;
            width: auto;
            border-radius: 12px;
            transition: transform 0.3s ease;
        }

        .logo-img:hover {
            transform: scale(1.05);
        }

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 8px;
        }

        .login-header p {
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .form-label-custom {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            display: block;
        }

        .input-group-custom {
            position: relative;
            margin-bottom: 20px;
        }

        .input-group-custom i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
            font-size: 0.9rem;
        }

        .form-control-custom {
            width: 100%;
            padding: 12px 16px 12px 42px;
            background: #fff;
            border: 1.5px solid #E2E8F0;
            border-radius: 10px;
            font-size: 0.95rem;
            color: var(--text-main);
            transition: all 0.2s ease;
            outline: none;
        }

        .form-control-custom:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(217, 119, 6, 0.1);
        }

        .btn-login {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 10px;
            width: 100%;
            font-weight: 700;
            font-size: 1rem;
            letter-spacing: 0.02em;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(217, 119, 6, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert-error {
            background: #FEE2E2;
            color: #991B1B;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 0.85rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            border-left: 4px solid #EF4444;
        }

        .register-link {
            text-align: center;
            margin-top: 25px;
            font-size: 0.9rem;
            color: var(--text-muted);
        }

        .register-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .footer-text {
            position: fixed;
            bottom: 20px;
            width: 100%;
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            z-index: 1;
        }

        /* Floating particles for depth */
        .circle {
            position: absolute;
            border-radius: 50%;
            background: var(--primary);
            filter: blur(60px);
            opacity: 0.15;
            z-index: 0;
        }
        .c1 { width: 300px; height: 300px; top: -100px; left: -100px; }
        .c2 { width: 250px; height: 250px; bottom: -50px; right: -50px; background: #F59E0B; }
    </style>
</head>
<body>

    <div class="circle c1"></div>
    <div class="circle c2"></div>

    <div class="login-card">
        <div class="logo-wrapper d-flex justify-content-center">
            <div class="nav-logo" style="padding: 0; width: fit-content; color: var(--dark);">
                <img src="/incubadora_ispsn/assets/img/logo_ispsn.svg" alt="ISPSN" style="height: 100px;">
            </div>
        </div>

        <div class="login-header">
            <h1>Bem-vindo à Incubadora</h1>
            <p>Faça login para gerir as suas inovações</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="alert-error">
                <i class="fa fa-triangle-exclamation"></i>
                <span><?= htmlspecialchars($erro) ?></span>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <div class="mb-4">
                <label for="email" class="form-label-custom">E-mail Institucional</label>
                <div class="input-group-custom">
                    <i class="fa fa-envelope"></i>
                    <input type="email" name="email" id="email" class="form-control-custom" 
                           placeholder="ex: nome@solnascente.ao" value="<?= htmlspecialchars($lembrar_email) ?>" required>
                </div>
            </div>

            <div class="mb-3">
                <label for="senha" class="form-label-custom">Palavra-passe</label>
                <div class="input-group-custom">
                    <i class="fa fa-lock"></i>
                    <input type="password" name="senha" id="senha" class="form-control-custom" 
                           placeholder="••••••••" value="<?= htmlspecialchars($lembrar_senha) ?>" required>
                </div>
            </div>

            <div class="mb-4 d-flex align-items-center">
                <input type="checkbox" name="lembrar" id="lembrar" class="form-check-input" style="cursor:pointer;" <?= $lembrar_checked ?>>
                <label for="lembrar" class="ms-2 mb-0" style="font-size: 0.85rem; color: var(--text-muted); cursor:pointer;">
                    Guardar senha e email
                </label>
            </div>

            <button type="submit" class="btn-login">
                Entrar no Ecossistema
                <i class="fa fa-arrow-right"></i>
            </button>

            <div class="register-link">
                Ainda não tem conta? <a href="register.php">Registe a sua ideia</a>
            </div>
        </form>
    </div>

    <div class="footer-text">
        &copy; <?= date('Y'); ?> Incubadora Académica — Instituto Superior Politécnico Sol Nascente
    </div>

</body>
</html>

