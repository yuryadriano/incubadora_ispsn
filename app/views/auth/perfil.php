<?php
// app/views/auth/perfil.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$tituloPagina = 'O Meu Perfil';
$paginaActiva = 'perfil';

$idUsuario = (int)$_SESSION['usuario_id'];

// Obter dados atuais atualizados
$stmt = $mysqli->prepare("SELECT nome, email, perfil, tipo_utilizador, criado_em FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-user-circle me-2" style="color:var(--primary)"></i>
            Configurações de Conta
        </div>
        <div class="page-header-sub">Gerencie as suas informações pessoais e credenciais de segurança.</div>
    </div>
</div>

<div class="row g-4">
    <!-- INFO BÁSICA -->
    <div class="col-md-4">
        <div class="card-custom h-100">
            <div class="card-body-custom text-center py-5">
                <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;
                            font-size:2rem;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                    <?= strtoupper(substr($usuario['nome'], 0, 1)) ?>
                </div>
                <h5 class="fw-bold mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                <p class="text-muted mb-3"><?= htmlspecialchars($usuario['email']) ?></p>
                <div class="d-inline-block px-3 py-1 rounded-pill" style="background:var(--surface-2);font-size:0.8rem;font-weight:600">
                    Membro desde <?= date('M, Y', strtotime($usuario['criado_em'])) ?>
                </div>
                <hr class="my-4">
                <div class="text-start">
                    <p class="mb-2"><small class="text-muted">Perfil de Acesso:</small><br><strong><?= ucfirst($usuario['perfil']) ?></strong></p>
                    <p class="mb-0"><small class="text-muted">Tipo de Conta:</small><br><strong><?= ucfirst($usuario['tipo_utilizador']) ?></strong></p>
                </div>
            </div>
        </div>
    </div>

    <!-- FORMULÁRIOS DE EDIÇÃO -->
    <div class="col-md-8 d-flex flex-column gap-4">
        
        <!-- Editar Dados -->
        <div class="card-custom">
            <div class="card-header-custom">
                <div class="card-title-custom"><i class="fa fa-pen-to-square"></i> Editar Dados Pessoais</div>
            </div>
            <div class="card-body-custom">
                <form action="/incubadora_ispsn/app/controllers/perfil_action.php" method="post">
                    <input type="hidden" name="action" value="atualizar_perfil">
                    <div class="mb-3">
                        <label class="form-label-custom">Nome Completo</label>
                        <input type="text" name="nome" class="form-control-custom" required value="<?= htmlspecialchars($usuario['nome']) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Endereço de E-mail</label>
                        <input type="email" name="email" class="form-control-custom" required value="<?= htmlspecialchars($usuario['email']) ?>">
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn-primary-custom"><i class="fa fa-save"></i> Guardar Alterações</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Mudar Password -->
        <div class="card-custom">
            <div class="card-header-custom" style="background:#FFF1F2;border-bottom:1px solid #FFE4E6">
                <div class="card-title-custom" style="color:var(--danger)"><i class="fa fa-lock"></i> Segurança: Alterar Senha</div>
            </div>
            <div class="card-body-custom">
                <form action="/incubadora_ispsn/app/controllers/perfil_action.php" method="post">
                    <input type="hidden" name="action" value="atualizar_senha">
                    <div class="mb-3">
                        <label class="form-label-custom">Senha Atual</label>
                        <input type="password" name="senha_antiga" class="form-control-custom" required placeholder="Digite a senha actual (ou a padrão)">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Nova Senha</label>
                            <input type="password" name="senha_nova" class="form-control-custom" required placeholder="Mínimo 6 caracteres">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Confirmar Nova Senha</label>
                            <input type="password" name="senha_confirmacao" class="form-control-custom" required placeholder="Repita a nova senha">
                        </div>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn-primary-custom" style="background:var(--danger);border-color:var(--danger)">
                            <i class="fa fa-key"></i> Actualizar Senha
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
