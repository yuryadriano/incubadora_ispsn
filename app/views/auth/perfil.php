<?php
// app/views/auth/perfil.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarLogin();

$tituloPagina = 'O Meu Perfil';
$paginaActiva = 'perfil';

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);
$isAdminPreview = ($isAdminOrSuper && !empty($_GET['id_usuario']));

if ($isAdminPreview) {
    $idUsuario = (int)$_GET['id_usuario'];
} else {
    $idUsuario = (int)$_SESSION['usuario_id'];
}

// Obter dados atuais atualizados
$stmt = $mysqli->prepare("SELECT nome, email, perfil, tipo_utilizador, criado_em FROM usuarios WHERE id = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($isAdminPreview && $usuario) {
    $nomeUsuario = $usuario['nome'];
    $perfil = 'utilizador';
}

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="<?= ($perfil === 'utilizador') ? 'std-painel' : '' ?>">

    <!-- FLASH -->
    <?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
    <?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

    <!-- PAGE HEADER UNIFICADO -->
    <div class="page-header">
        <div>
            <div class="page-header-title">
                <i class="fa fa-user-circle me-2" style="color:var(--primary)"></i>
                <?= htmlspecialchars($usuario['nome'] ?? 'Configurações de Conta', ENT_QUOTES, 'UTF-8') ?>
            </div>
            <div class="page-header-sub">
                Gerencie as suas informações pessoais, credenciais académicas e segurança da conta.
            </div>
        </div>
        <span class="badge-estado badge-aprovado">
            Conta Ativa · <?= htmlspecialchars(ucfirst((string)($usuario['tipo_utilizador'] ?? 'Estudante')), ENT_QUOTES, 'UTF-8') ?>
        </span>
    </div>

    <div class="row g-4">
        <!-- INFO BÁSICA -->
        <div class="col-md-4">
            <div class="card-custom h-100">
                <div class="card-body-custom text-center py-5">
                    <div style="width:80px;height:80px;border-radius:50%;background:var(--primary);color:#fff;
                                font-size:2rem;font-weight:800;display:flex;align-items:center;justify-content:center;margin:0 auto 16px">
                        <?= strtoupper(substr($usuario['nome'] ?? 'U', 0, 1)) ?>
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($usuario['nome'] ?? '') ?></h5>
                    <p class="text-muted mb-3"><?= htmlspecialchars($usuario['email'] ?? '') ?></p>
                    <?php if (!empty($usuario['criado_em'])): ?>
                    <div class="d-inline-block px-3 py-1 rounded-pill" style="background:var(--surface-2);font-size:0.8rem;font-weight:600">
                        Registado em <?= date('d/m/Y', strtotime($usuario['criado_em'])) ?>
                    </div>
                    <?php endif; ?>
                    <hr class="my-4">
                    <div class="text-start">
                        <p class="mb-2"><small class="text-muted">Perfil de Acesso:</small><br><strong><?= htmlspecialchars(ucfirst($usuario['perfil'] ?? '')) ?></strong></p>
                        <p class="mb-0"><small class="text-muted">Tipo de Conta:</small><br><strong><?= htmlspecialchars(ucfirst($usuario['tipo_utilizador'] ?? '')) ?></strong></p>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$isAdminPreview): ?>
        <!-- FORMULÁRIOS DE EDIÇÃO (APENAS PARA O PRÓPRIO UTILIZADOR AUTENTICADO) -->
        <div class="col-md-8 d-flex flex-column gap-4">
            
            <!-- Editar Dados -->
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa fa-pen-to-square"></i> Editar Dados Pessoais</div>
                </div>
                <div class="card-body-custom">
                    <form action="/incubadora_ispsn/app/controllers/perfil_action.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="atualizar_perfil">
                        <div class="mb-3">
                            <label class="form-label-custom">Nome Completo</label>
                            <input type="text" name="nome" class="form-control-custom" required value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label-custom">Endereço de E-mail</label>
                            <input type="email" name="email" class="form-control-custom" required value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
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
                        <?= csrf_field() ?>
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
        <?php else: ?>
        <div class="col-md-8 d-flex flex-column gap-4">
            <div class="card-custom">
                <div class="card-header-custom">
                    <div class="card-title-custom"><i class="fa-solid fa-rocket"></i> Startups &amp; Participações deste Estudante</div>
                </div>
                <div class="card-body-custom">
                    <?php
                    $stmtPUser = $mysqli->prepare("
                        SELECT id, titulo, estado, fase, pontos, criado_em
                        FROM projetos
                        WHERE criado_por = ?
                        ORDER BY criado_em DESC
                    ");
                    $stmtPUser->bind_param('i', $idUsuario);
                    $stmtPUser->execute();
                    $projsUser = $stmtPUser->get_result()->fetch_all(MYSQLI_ASSOC);
                    $stmtPUser->close();
                    ?>
                    <?php if (empty($projsUser)): ?>
                        <div class="sp-empty-line">
                            <i class="fa-regular fa-folder-open"></i>
                            <span>Este estudante ainda não submeteu projetos como líder.</span>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($projsUser as $pu): ?>
                                <div class="p-3 rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border);">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars((string)$pu['titulo'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div style="font-size: 0.76rem; color: var(--sp-text-muted);">
                                            Fase: <?= htmlspecialchars(ucfirst((string)$pu['fase']), ENT_QUOTES, 'UTF-8') ?> · Estado: <?= htmlspecialchars(ucfirst((string)$pu['estado']), ENT_QUOTES, 'UTF-8') ?> · <?= (int)$pu['pontos'] ?> SP
                                        </div>
                                    </div>
                                    <a href="/incubadora_ispsn/app/views/dashboard/utilizador.php?id_projeto=<?= (int)$pu['id'] ?>&id_usuario=<?= (int)$idUsuario ?>" class="sp-btn-primary py-1 px-3" style="min-height: 32px; font-size: 0.76rem;">
                                        Abrir no Painel →
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
