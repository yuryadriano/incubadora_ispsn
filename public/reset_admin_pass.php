<?php
/**
 * FERRAMENTA DE RECUPERAÇÃO DE PALAVRA-PASSE — USO ÚNICO
 * Aceda a esta página via: http://localhost/incubadora_ispsn/public/reset_admin_pass.php
 * APAGUE ESTE FICHEIRO DEPOIS DE USAR!
 */

// Protecção extra: só permitir acesso a partir de localhost
$allowedIPs = ['127.0.0.1', '::1', 'localhost'];
$clientIP   = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($clientIP, $allowedIPs)) {
    http_response_code(403);
    die('Acesso negado.');
}

require_once __DIR__ . '/../config/config.php';

$mensagem = '';
$tipo     = '';
$usuarios = [];

// Buscar todos os utilizadores admin/superadmin
$res = $mysqli->query("SELECT id, nome, email, perfil, activo FROM usuarios WHERE perfil IN ('admin','superadmin') ORDER BY perfil DESC, nome ASC");
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $usuarios[] = $r;
    }
}

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idUsuario  = (int)($_POST['id_usuario'] ?? 0);
    $novaSenha  = trim($_POST['nova_senha'] ?? '');
    $confirmar  = trim($_POST['confirmar'] ?? '');

    if ($idUsuario <= 0) {
        $mensagem = 'Selecione um utilizador válido.';
        $tipo     = 'erro';
    } elseif (strlen($novaSenha) < 6) {
        $mensagem = 'A palavra-passe deve ter pelo menos 6 caracteres.';
        $tipo     = 'erro';
    } elseif ($novaSenha !== $confirmar) {
        $mensagem = 'As palavras-passe não coincidem.';
        $tipo     = 'erro';
    } else {
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $mysqli->prepare("UPDATE usuarios SET senha_hash = ? WHERE id = ?");
        $stmt->bind_param('si', $hash, $idUsuario);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // Buscar o nome do utilizador
            $stmtN = $mysqli->prepare("SELECT nome, email FROM usuarios WHERE id = ?");
            $stmtN->bind_param('i', $idUsuario);
            $stmtN->execute();
            $u = $stmtN->get_result()->fetch_assoc();
            $mensagem = "✅ Palavra-passe do utilizador <strong>" . htmlspecialchars($u['nome']) . "</strong> (" . htmlspecialchars($u['email']) . ") alterada com sucesso!<br><strong>⚠️ APAGUE ESTE FICHEIRO IMEDIATAMENTE APÓS USAR!</strong>";
            $tipo     = 'ok';
        } else {
            $mensagem = 'Erro ao alterar a palavra-passe: ' . $mysqli->error . ' (O utilizador pode não existir)';
            $tipo     = 'erro';
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-pt">
<head>
    <meta charset="UTF-8">
    <title>Recuperação de Palavra-passe — Admin Only</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
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
        }
        .card {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.5);
        }
        .badge-danger {
            display: inline-block;
            background: #ef444422;
            border: 1px solid #ef444455;
            color: #fca5a5;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 4px 12px;
            border-radius: 100px;
            margin-bottom: 16px;
        }
        h1 {
            color: #fff;
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .sub {
            color: rgba(255,255,255,0.4);
            font-size: 0.82rem;
            margin-bottom: 28px;
            line-height: 1.6;
        }
        label {
            display: block;
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255,255,255,0.45);
            margin-bottom: 8px;
            margin-top: 18px;
        }
        select, input[type="password"] {
            width: 100%;
            padding: 12px 14px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            color: #fff;
            font-size: 0.9rem;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: 0.2s;
        }
        select option { background: #1e293b; color: #fff; }
        select:focus, input[type="password"]:focus {
            border-color: #D97706;
            background: rgba(217,119,6,0.06);
        }
        .btn {
            width: 100%;
            margin-top: 24px;
            padding: 13px;
            background: linear-gradient(135deg, #D97706, #B45309);
            color: #fff;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .alert {
            padding: 14px 16px;
            border-radius: 10px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            line-height: 1.6;
        }
        .alert-ok   { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #6EE7B7; }
        .alert-erro { background: rgba(239,68,68,0.1);  border: 1px solid rgba(239,68,68,0.3);  color: #FCA5A5; }
        .warning-box {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.3);
            border-radius: 10px;
            padding: 12px 16px;
            color: #FCD34D;
            font-size: 0.8rem;
            margin-top: 20px;
            line-height: 1.6;
        }
        .user-list {
            margin-top: 28px;
            border-top: 1px solid rgba(255,255,255,0.06);
            padding-top: 20px;
        }
        .user-list h3 { color: rgba(255,255,255,0.6); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 12px; }
        .user-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        .user-row .badge {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 100px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-super { background: rgba(217,119,6,0.2); color: #FCD34D; }
        .badge-admin { background: rgba(99,102,241,0.2); color: #a5b4fc; }
        .badge-off { background: rgba(239,68,68,0.15); color: #fca5a5; }
        .user-row .nome { color: #fff; font-size: 0.88rem; flex: 1; }
        .user-row .email { color: rgba(255,255,255,0.35); font-size: 0.78rem; }
        a.back { display: block; text-align: center; margin-top: 20px; color: rgba(255,255,255,0.3); font-size: 0.8rem; text-decoration: none; }
        a.back:hover { color: #D97706; }
    </style>
</head>
<body>
<div class="card">
    <div class="badge-danger">🔒 Ferramenta Restrita — Localhost Only</div>
    <h1>Recuperar Palavra-passe</h1>
    <p class="sub">Use esta ferramenta para redefinir a palavra-passe de um administrador. <strong>Apague este ficheiro depois de usar.</strong></p>

    <?php if ($mensagem): ?>
    <div class="alert alert-<?= $tipo ?>">
        <?= $mensagem ?>
    </div>
    <?php endif; ?>

    <?php if ($tipo !== 'ok'): ?>
    <form method="POST">
        <label for="id_usuario">Selecionar Utilizador</label>
        <select name="id_usuario" id="id_usuario" required>
            <option value="">— Selecione —</option>
            <?php foreach ($usuarios as $u): ?>
            <option value="<?= $u['id'] ?>" <?= (!empty($_POST['id_usuario']) && $_POST['id_usuario'] == $u['id']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($u['nome']) ?> (<?= htmlspecialchars($u['email']) ?>) — <?= strtoupper($u['perfil']) ?> <?= !$u['activo'] ? '[INACTIVO]' : '' ?>
            </option>
            <?php endforeach; ?>
        </select>

        <label for="nova_senha">Nova Palavra-passe</label>
        <input type="password" name="nova_senha" id="nova_senha" placeholder="Mínimo 6 caracteres" required minlength="6">

        <label for="confirmar">Confirmar Palavra-passe</label>
        <input type="password" name="confirmar" id="confirmar" placeholder="Repita a palavra-passe" required minlength="6">

        <button type="submit" class="btn">🔑 Redefinir Palavra-passe</button>
    </form>
    <?php endif; ?>

    <div class="warning-box">
        ⚠️ <strong>Aviso de Segurança:</strong> Este ficheiro só é acessível a partir de <code>localhost</code>. 
        Após usar, elimine o ficheiro <code>reset_admin_pass.php</code> do servidor imediatamente.
    </div>

    <?php if (!empty($usuarios)): ?>
    <div class="user-list">
        <h3>Contas de Administração</h3>
        <?php foreach ($usuarios as $u): ?>
        <div class="user-row">
            <div class="nome"><?= htmlspecialchars($u['nome']) ?></div>
            <div class="email"><?= htmlspecialchars($u['email']) ?></div>
            <span class="badge <?= $u['perfil'] === 'superadmin' ? 'badge-super' : 'badge-admin' ?>">
                <?= $u['perfil'] === 'superadmin' ? 'Super Admin' : 'Admin' ?>
            </span>
            <?php if (!$u['activo']): ?>
            <span class="badge badge-off">Inactivo</span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <a class="back" href="/incubadora_ispsn/public/login.php">← Voltar ao Login</a>
</div>
</body>
</html>
