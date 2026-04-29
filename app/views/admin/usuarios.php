<?php
// app/views/admin/usuarios.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin','superadmin']);

$tituloPagina = 'Gestão de Utilizadores';
$paginaActiva = 'usuarios';

// Filtros
$filtroBusca  = trim($_GET['q'] ?? '');
$filtroPerfil = $_GET['perfil'] ?? '';

$where = ["1=1"];
$params = [];
$types = '';

if ($filtroBusca) {
    $like = "%$filtroBusca%";
    $where[] = '(nome LIKE ? OR email LIKE ?)';
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
if ($filtroPerfil) {
    $where[] = 'perfil = ?';
    $params[] = $filtroPerfil;
    $types .= 's';
}

$whereSQL = implode(' AND ', $where);

// Buscar utilizadores
$sql = "SELECT * FROM usuarios WHERE $whereSQL ORDER BY id DESC";
$stmt = $mysqli->prepare($sql);
if ($types) $stmt->bind_param($types, ...$params);
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Contadores
$r = $mysqli->query("SELECT perfil, COUNT(*) n FROM usuarios GROUP BY perfil");
$contadores = [];
if ($r) while ($row = $r->fetch_assoc()) $contadores[$row['perfil']] = (int)$row['n'];
$total = array_sum($contadores);

// Flash messages
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<!-- FLASH MESSAGES -->
<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<!-- PAGE HEADER -->
<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa fa-users me-2" style="color:var(--primary)"></i>
            Gestão de Utilizadores
        </div>
        <div class="page-header-sub"><?= count($usuarios) ?> utilizador(es) listado(s)</div>
    </div>
    <div class="d-flex gap-2">
        <button class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalConvidarMentor">
            <i class="fa fa-paper-plane"></i> Convidar Mentor
        </button>
        <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoUser">
            <i class="fa fa-user-plus"></i> Novo Utilizador
        </button>
    </div>
</div>

<!-- MODAL CONVIDAR MENTOR -->
<div class="modal fade" id="modalConvidarMentor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/convite_action.php">
                <input type="hidden" name="action" value="enviar_convite">
                <input type="hidden" name="perfil" value="mentor">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-envelope-open-text me-2"></i>Convidar Mentor</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <p class="text-muted small mb-3">Envie um convite formal para um especialista. Ele receberá um e-mail com o link para registar o seu perfil na incubadora.</p>
                    <div class="mb-3">
                        <label class="form-label-custom">E-mail do Especialista / Mentor *</label>
                        <input type="email" name="email" class="form-control-custom" required placeholder="exemplo@mentor.com" list="listaEmails">
                        <datalist id="listaEmails">
                            <?php foreach($usuarios as $u): ?>
                                <option value="<?= htmlspecialchars($u['email']) ?>"><?= htmlspecialchars($u['nome']) ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Mensagem Personalizada (Opcional)</label>
                        <textarea name="mensagem" class="form-control-custom" rows="3" placeholder="Olá, gostaríamos de o convidar para ser mentor da nossa incubadora..."></textarea>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-check"></i> Enviar Convite</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="card-custom mb-4">
    <div class="card-body-custom">
        <form method="get" class="d-flex gap-3 flex-wrap align-items-end">
            <div style="flex:1;min-width:220px">
                <label class="form-label-custom">Pesquisar</label>
                <input type="text" name="q" class="form-control-custom" placeholder="Nome ou email…" value="<?= htmlspecialchars($filtroBusca) ?>">
            </div>
            <div style="min-width:160px">
                <label class="form-label-custom">Perfil</label>
                <select name="perfil" class="form-control-custom">
                    <option value="">Todos</option>
                    <option value="superadmin"  <?= $filtroPerfil==='superadmin' ? 'selected':'' ?>>Super Admin</option>
                    <option value="admin"       <?= $filtroPerfil==='admin'      ? 'selected':'' ?>>Admin</option>
                    <option value="funcionario" <?= $filtroPerfil==='funcionario'? 'selected':'' ?>>Funcionário / Recepcionista</option>
                    <option value="mentor"      <?= $filtroPerfil==='mentor'     ? 'selected':'' ?>>Mentor</option>
                    <option value="utilizador"  <?= $filtroPerfil==='utilizador' ? 'selected':'' ?>>Estudante (Utilizador)</option>
                </select>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn-primary-custom">
                    <i class="fa fa-magnifying-glass"></i> Filtrar
                </button>
                <a href="?" class="btn-ghost"><i class="fa fa-rotate-left"></i></a>
            </div>
        </form>
    </div>
</div>

<!-- TABELA -->
<div class="card-custom">
    <div class="card-header-custom">
        <div class="card-title-custom"><i class="fa fa-table"></i> Lista de Utilizadores</div>
    </div>
    <div class="table-wrapper">
        <table class="table-custom">
            <thead>
                <tr><th>Nome & Email</th><th>Perfil</th><th>Tipo</th><th>Estado</th><th>Acções</th></tr>
            </thead>
            <tbody>
            <?php foreach ($usuarios as $u): ?>
            <tr>
                <td>
                    <div style="font-weight:600"><?= htmlspecialchars($u['nome']) ?></div>
                    <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                </td>
                <td>
                    <?php 
                        $perfColor = ['superadmin'=>'#7C3AED','admin'=>'#4F46E5','funcionario'=>'#10B981','mentor'=>'#8B5CF6','utilizador'=>'#F59E0B'][$u['perfil']] ?? '#64748B';
                    ?>
                    <span style="font-size:0.72rem;padding:3px 9px;border-radius:20px;
                                 background:<?= $perfColor ?>22;color:<?= $perfColor ?>;font-weight:700">
                        <?= ucfirst($u['perfil']) ?>
                    </span>
                </td>
                <td><small class="text-muted"><?= ucfirst($u['tipo_utilizador']) ?></small></td>
                <td>
                    <?php if ($u['activo']): ?>
                        <span class="badge-estado badge-aprovado">Activo</span>
                    <?php else: ?>
                        <span class="badge-estado badge-rejeitado">Inactivo</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div class="d-flex gap-2">
                        <button class="btn-ghost" style="padding:6px 12px;font-size:0.78rem" data-bs-toggle="modal" data-bs-target="#modalEdit<?= $u['id'] ?>">
                            <i class="fa fa-pen"></i>
                        </button>
                        <?php if ($u['id'] != $_SESSION['usuario_id']): ?>
                        <form method="post" action="/incubadora_ispsn/app/controllers/usuario_action.php" style="display:inline">
                            <input type="hidden" name="action" value="mudar_estado">
                            <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                            <input type="hidden" name="estado" value="<?= $u['activo'] ? 0 : 1 ?>">
                            <button class="btn-ghost" style="padding:6px 12px;font-size:0.78rem;color:<?= $u['activo'] ? 'var(--danger)' : 'var(--success)' ?>"
                                    title="<?= $u['activo'] ? 'Desactivar Conta' : 'Activar Conta' ?>"
                                    onclick="return confirm('<?= $u['activo'] ? 'Desactivar' : 'Activar' ?> este utilizador?')">
                                <i class="fa <?= $u['activo'] ? 'fa-ban' : 'fa-check' ?>"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>

            <!-- MODAL EDITAR UTILIZADOR -->
            <div class="modal fade" id="modalEdit<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content modal-content-custom">
                        <form method="post" action="/incubadora_ispsn/app/controllers/usuario_action.php">
                            <input type="hidden" name="action" value="editar_usuario">
                            <input type="hidden" name="id_usuario" value="<?= $u['id'] ?>">
                            <div class="modal-header-custom">
                                <h5 class="modal-title fw-bold"><i class="fa fa-user-pen me-2"></i>Editar Utilizador</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body-custom">
                                <div class="mb-3">
                                    <label class="form-label-custom">Nome Completo</label>
                                    <input type="text" name="nome" class="form-control-custom" required value="<?= htmlspecialchars($u['nome']) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label-custom">E-mail</label>
                                    <input type="email" name="email" class="form-control-custom" required value="<?= htmlspecialchars($u['email']) ?>">
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Perfil</label>
                                        <select name="perfil" class="form-control-custom" required>
                                            <option value="utilizador"  <?= $u['perfil']==='utilizador' ?'selected':''?>>Utilizador</option>
                                            <option value="mentor"      <?= $u['perfil']==='mentor'     ?'selected':''?>>Mentor</option>
                                            <option value="funcionario" <?= $u['perfil']==='funcionario'?'selected':''?>>Funcionário / Recepcionista</option>
                                            <option value="admin"       <?= $u['perfil']==='admin'      ?'selected':''?>>Admin</option>
                                            <option value="superadmin"  <?= $u['perfil']==='superadmin' ?'selected':''?>>Super Admin</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label-custom">Tipo</label>
                                        <select name="tipo_utilizador" class="form-control-custom" required>
                                            <option value="estudante" <?= $u['tipo_utilizador']==='estudante'?'selected':''?>>Estudante</option>
                                            <option value="docente"   <?= $u['tipo_utilizador']==='docente'  ?'selected':''?>>Docente</option>
                                            <option value="mentor"    <?= $u['tipo_utilizador']==='mentor'   ?'selected':''?>>Mentor</option>
                                            <option value="outro"     <?= $u['tipo_utilizador']==='outro'    ?'selected':''?>>Outro</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer-custom">
                                <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn-primary-custom"><i class="fa fa-save"></i> Salvar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL NOVO UTILIZADOR -->
<div class="modal fade" id="modalNovoUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/usuario_action.php">
                <input type="hidden" name="action" value="criar_usuario">
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa fa-user-plus me-2"></i>Novo Utilizador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Nome Completo *</label>
                        <input type="text" name="nome" class="form-control-custom" required placeholder="Ex: João Silva">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">E-mail Institucional *</label>
                        <input type="email" name="email" class="form-control-custom" required placeholder="email@solnascente.ao">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Perfil de Acesso</label>
                            <select name="perfil" class="form-control-custom" required>
                                <option value="utilizador">Utilizador (Estudante)</option>
                                <option value="mentor">Mentor Externo</option>
                                <option value="funcionario">Funcionário / Recepcionista</option>
                                <option value="admin">Administrador</option>
                                <option value="superadmin">Super Admin</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Tipo de Conta</label>
                            <select name="tipo_utilizador" class="form-control-custom" required>
                                <option value="estudante">Estudante</option>
                                <option value="docente">Docente</option>
                                <option value="mentor">Mentor</option>
                                <option value="outro">Equipa Parceira</option>
                            </select>
                        </div>
                    </div>
                    <div class="alert alert-info mt-3 flex" style="font-size:0.85rem">
                        <i class="fa fa-info-circle me-2"></i> A senha padrão (123456) será atribuída. O utilizador poderá alterá-la depois.
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa fa-check"></i> Criar Conta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
