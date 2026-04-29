<?php
// app/views/mentor/relatorios.php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['mentor', 'admin', 'superadmin']);

$idUsuario = (int)$_SESSION['usuario_id'];

// 1. Buscar startups mentoradas
$stmt = $mysqli->prepare("
    SELECT p.id, p.titulo 
    FROM projetos p
    JOIN mentorias m ON m.id_projeto = p.id
    WHERE m.id_mentor = (SELECT id FROM mentores WHERE id_usuario = ?)
    AND m.estado = 'activa'
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$minhasStartups = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 2. Buscar relatórios (Enviados por mim)
$meusRelatorios = [];
$stmt = $mysqli->prepare("
    SELECT r.*, p.titulo as projeto_nome
    FROM relatorios r
    JOIN projetos p ON p.id = r.id_projeto
    WHERE r.id_autor = ?
    ORDER BY r.criado_em DESC
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$meusRelatorios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// 3. Buscar documentos recebidos (Enviados pela Startup e visíveis para mentor)
$documentosRecebidos = [];
$stmt = $mysqli->prepare("
    SELECT r.*, p.titulo as projeto_nome, u.nome as autor_nome
    FROM relatorios r
    JOIN projetos p ON p.id = r.id_projeto
    JOIN usuarios u ON u.id = r.id_autor
    JOIN mentorias m ON m.id_projeto = p.id
    WHERE m.id_mentor = (SELECT id FROM mentores WHERE id_usuario = ?)
    AND r.id_autor != ?
    AND (r.destino = 'todos' OR r.destino = 'mentor')
    ORDER BY r.criado_em DESC
");
$stmt->bind_param('ii', $idUsuario, $idUsuario);
$stmt->execute();
$documentosRecebidos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$tituloPagina = 'Gestão de Documentos';
$paginaActiva = 'relatorios';

$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
    .nav-tabs-custom {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        border-bottom: 1px solid var(--border);
        padding-bottom: 2px;
    }
    .nav-tab-item {
        padding: 10px 20px;
        border-radius: 12px 12px 0 0;
        cursor: pointer;
        font-weight: 700;
        font-size: 0.9rem;
        color: var(--text-muted);
        transition: all 0.3s;
        border: 1px solid transparent;
        margin-bottom: -1px;
    }
    .nav-tab-item.active {
        background: white;
        color: var(--primary);
        border: 1px solid var(--border);
        border-bottom-color: white;
    }
    .nav-tab-item:hover:not(.active) {
        color: var(--primary);
        background: var(--surface-2);
    }
    .doc-card {
        background: white;
        border-radius: 16px;
        padding: 16px;
        border: 1px solid var(--border);
        margin-bottom: 12px;
        display: flex;
        align-items: center;
        gap: 15px;
        transition: all 0.2s;
    }
    .doc-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-sm);
    }
    .doc-icon {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        background: #fef2f2;
        color: #ef4444;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
    }
    .doc-info { flex: 1; min-width: 0; }
    .doc-title { font-weight: 800; font-size: 0.95rem; color: var(--text-primary); margin-bottom: 2px; }
    .doc-meta { font-size: 0.75rem; color: var(--text-muted); display: flex; gap: 12px; }
    .doc-actions { display: flex; gap: 8px; }
</style>

<div class="page-header">
    <div>
        <div class="page-header-title">
            <i class="fa-solid fa-folder-tree me-2" style="color:var(--primary)"></i>
            Documentação e Relatórios
        </div>
        <div class="page-header-sub">
            Central de intercâmbio de ficheiros e acompanhamento documental.
        </div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoRelatorio">
        <i class="fa-solid fa-file-export"></i> Enviar Novo Documento
    </button>
</div>

<?php if ($flashOk):   ?><div class="alert-custom alert-success mb-4"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashOk) ?></div><?php endif; ?>
<?php if ($flashErro): ?><div class="alert-custom alert-danger mb-4"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($flashErro) ?></div><?php endif; ?>

<div class="nav-tabs-custom">
    <div class="nav-tab-item active" onclick="switchTab('meus')">
        <i class="fa-solid fa-paper-plane me-2"></i> Meus Envios 
        <span class="badge bg-light text-dark ms-1"><?= count($meusRelatorios) ?></span>
    </div>
    <div class="nav-tab-item" onclick="switchTab('recebidos')">
        <i class="fa-solid fa-inbox me-2"></i> Recebidos das Startups
        <span class="badge bg-light text-dark ms-1"><?= count($documentosRecebidos) ?></span>
    </div>
</div>

<!-- ABA: MEUS ENVIOS -->
<div id="tab-meus">
    <?php if (empty($meusRelatorios)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-file-circle-minus"></i></div>
            <div class="empty-state-title">Sem envios realizados</div>
            <div class="empty-state-text">Os documentos que enviar para a administração ou startups aparecerão aqui.</div>
        </div>
    <?php else: ?>
        <?php foreach ($meusRelatorios as $r): ?>
        <div class="doc-card">
            <div class="doc-icon"><i class="fa-solid fa-file-pdf"></i></div>
            <div class="doc-info">
                <div class="doc-title text-truncate"><?= htmlspecialchars($r['titulo']) ?></div>
                <div class="doc-meta">
                    <span><i class="fa-solid fa-rocket me-1"></i> <?= htmlspecialchars($r['projeto_nome']) ?></span>
                    <span><i class="fa-solid fa-calendar me-1"></i> <?= date('d/m/Y', strtotime($r['criado_em'])) ?></span>
                    <span><i class="fa-solid fa-eye me-1"></i> Destino: <?= ucfirst($r['destino']) ?></span>
                </div>
            </div>
            <div class="doc-actions">
                <a href="/incubadora_ispsn/uploads/relatorios/<?= $r['ficheiro'] ?>" target="_blank" class="btn-ghost" title="Download">
                    <i class="fa-solid fa-download"></i>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ABA: RECEBIDOS -->
<div id="tab-recebidos" style="display:none">
    <?php if (empty($documentosRecebidos)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="fa-solid fa-box-open"></i></div>
            <div class="empty-state-title">Nada recebido ainda</div>
            <div class="empty-state-text">Documentos submetidos pelas startups para sua revisão aparecerão aqui.</div>
        </div>
    <?php else: ?>
        <?php foreach ($documentosRecebidos as $r): ?>
        <div class="doc-card" style="border-left: 4px solid var(--success)">
            <div class="doc-icon" style="background:#f0fdf4; color:#22c55e"><i class="fa-solid fa-file-lines"></i></div>
            <div class="doc-info">
                <div class="doc-title text-truncate"><?= htmlspecialchars($r['titulo']) ?></div>
                <div class="doc-meta">
                    <span class="fw-bold"><i class="fa-solid fa-user me-1"></i> <?= htmlspecialchars($r['autor_nome']) ?></span>
                    <span><i class="fa-solid fa-rocket me-1"></i> <?= htmlspecialchars($r['projeto_nome']) ?></span>
                    <span><i class="fa-solid fa-clock me-1"></i> <?= date('d/m/Y', strtotime($r['criado_em'])) ?></span>
                </div>
            </div>
            <div class="doc-actions">
                <a href="/incubadora_ispsn/uploads/relatorios/<?= $r['ficheiro'] ?>" target="_blank" class="btn-primary-custom" style="padding:6px 12px; font-size:0.75rem">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Revisar
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- MODAL: NOVO RELATÓRIO -->
<div class="modal fade" id="modalNovoRelatorio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="POST" action="/incubadora_ispsn/app/controllers/mentor_action.php" enctype="multipart/form-data">
                <input type="hidden" name="action" value="enviar_relatorio">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/mentor/relatorios.php">
                
                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-circle-plus me-2"></i>Novo Envio de Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Startup Relacionada *</label>
                        <select name="id_projeto" class="form-control-custom" required>
                            <option value="">— Seleccione a Startup —</option>
                            <?php foreach ($minhasStartups as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['titulo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Título do Documento *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Relatório Mensal de Mentoria">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Tipo de Documento</label>
                            <select name="tipo" class="form-control-custom">
                                <option value="mensal">Relatório Mensal</option>
                                <option value="feedback">Feedback Técnico</option>
                                <option value="guia">Guia de Estudo/Recurso</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Destinatário (Visibilidade)</label>
                            <select name="destino" class="form-control-custom">
                                <option value="admin">Apenas Admin</option>
                                <option value="startup">Apenas Startup</option>
                                <option value="todos" selected>Ambos (Todos)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Notas Adicionais</label>
                        <textarea name="descricao" class="form-control-custom" rows="2" placeholder="Opcional..."></textarea>
                    </div>
                    <div class="mb-0">
                        <label class="form-label-custom">Ficheiro (PDF, Doc, ZIP) *</label>
                        <input type="file" name="ficheiro_relatorio" class="form-control-custom" required>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Finalizar e Enviar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.getElementById('tab-meus').style.display = tab === 'meus' ? 'block' : 'none';
    document.getElementById('tab-recebidos').style.display = tab === 'recebidos' ? 'block' : 'none';
    
    const items = document.querySelectorAll('.nav-tab-item');
    items[0].classList.toggle('active', tab === 'meus');
    items[1].classList.toggle('active', tab === 'recebidos');
}
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
