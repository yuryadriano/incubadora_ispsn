<?php
// app/views/auth/perfil.php
// O Meu Perfil — 1 Único Cartão Limpo

require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$tituloPagina = 'O Meu Perfil';
$paginaActiva = 'perfil';

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);
$isAdminPreview = ($isAdminOrSuper && !empty($_GET['id_usuario']));

$idUsuario = $isAdminPreview ? (int)$_GET['id_usuario'] : (int)$_SESSION['usuario_id'];

$stmt = $mysqli->prepare("SELECT nome, email, perfil, tipo_utilizador, criado_em FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($isAdminPreview && $usuario) {
    $nomeUsuario = $usuario['nome'];
    $perfil      = 'utilizador';
}

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<div class="page-header">
    <div>
        <div class="page-header-title">O Meu Perfil</div>
        <div class="page-header-sub">Dados pessoais e credenciais de acesso.</div>
    </div>
</div>

<!-- 1 ÚNICO CARTÃO DE PERFIL -->
<div class="card-custom">
    <div class="card-body-custom p-4">
        <div class="d-flex align-items-center gap-3 pb-4 mb-4 border-bottom" style="border-color:var(--border) !important;">
            <div style="width:56px;height:56px;border-radius:50%;background:var(--primary);color:#fff;font-size:1.4rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <?= strtoupper(substr($usuario['nome'] ?? 'U', 0, 1)) ?>
            </div>
            <div>
                <h4 class="fw-bold mb-0" style="font-size:1.1rem;"><?= htmlspecialchars($usuario['nome'] ?? '') ?></h4>
                <div class="text-muted small">
                    <?= htmlspecialchars($usuario['email'] ?? '') ?> · <?= htmlspecialchars(ucfirst($usuario['tipo_utilizador'] ?? 'Estudante')) ?>
                </div>
            </div>
        </div>

        <?php if (!$isAdminPreview): ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <h6 class="fw-bold mb-3">Dados Pessoais</h6>
                    <form action="/incubadora_ispsn/app/controllers/perfil_action.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="atualizar_perfil">
                        <div class="mb-3">
                            <label class="form-label-custom">Nome Completo</label>
                            <input type="text" name="nome" class="form-control-custom" required value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">E-mail</label>
                            <input type="email" name="email" class="form-control-custom" required value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
                        </div>
                        <button type="submit" class="btn-primary-custom"><i class="fa fa-save"></i> Guardar Dados</button>
                    </form>
                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold mb-3">Alterar Senha</h6>
                    <form action="/incubadora_ispsn/app/controllers/perfil_action.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="atualizar_senha">
                        <div class="mb-3">
                            <label class="form-label-custom">Senha Atual</label>
                            <input type="password" name="senha_antiga" class="form-control-custom" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label-custom">Nova Senha</label>
                                <input type="password" name="senha_nova" class="form-control-custom" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label-custom">Confirmar</label>
                                <input type="password" name="senha_confirmacao" class="form-control-custom" required>
                            </div>
                        </div>
                        <button type="submit" class="btn-ghost"><i class="fa fa-key"></i> Atualizar Senha</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <div class="text-muted small">
                Conta registada em <?= !empty($usuario['criado_em']) ? date('d/m/Y', strtotime($usuario['criado_em'])) : '—' ?>.
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
