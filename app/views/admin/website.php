<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$tituloPagina = 'Gestão do Website';
$paginaActiva = 'gestao_website';

// 1. Buscar Configurações Globais
$configs = [];
$resC = $mysqli->query("SELECT * FROM config_website ORDER BY grupo, chave");
if ($resC) while ($r = $resC->fetch_assoc()) $configs[$r['grupo']][] = $r;

// 2. Buscar Publicações
$publicacoes = [];
$resP = $mysqli->query("SELECT p.*, u.nome as autor_nome FROM publicacoes_website p LEFT JOIN usuarios u ON u.id = p.criado_por ORDER BY p.criado_em DESC");
if ($resP) while ($r = $resP->fetch_assoc()) $publicacoes[] = $r;

// 3. Buscar Galeria
$galeria = [];
$resG = $mysqli->query("SELECT * FROM galeria_website ORDER BY ordem ASC, criado_em DESC");
if ($resG) while ($r = $resG->fetch_assoc()) $galeria[] = $r;

// Flash messages
$flash_ok   = $_SESSION['flash_ok'] ?? '';
$flash_erro = $_SESSION['flash_erro'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
.content-hub-tabs { display: flex; gap: 10px; margin-bottom: 30px; background: var(--slate-100); padding: 8px; border-radius: 18px; width: fit-content; border: 1px solid var(--slate-200); }
.hub-tab { padding: 10px 24px; border-radius: 12px; border: none; background: transparent; font-size: 0.88rem; font-weight: 700; color: var(--slate-600); transition: 0.3s; cursor: pointer; text-decoration: none; display: flex; align-items: center; gap: 8px; }
.hub-tab.active { background: #fff; color: var(--primary); box-shadow: 0 4px 10px rgba(0,0,0,0.05); }

.hub-section { display: none; animation: fadeIn 0.4s ease; }
.hub-section.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

.config-card { background: #fff; border-radius: 20px; padding: 25px; border: 1px solid var(--slate-200); margin-bottom: 20px; }
.pub-list-item { background: #fff; border: 1px solid var(--slate-200); border-radius: 16px; padding: 15px 20px; display: flex; align-items: center; gap: 20px; margin-bottom: 12px; transition: 0.2s; }
.pub-list-item:hover { border-color: var(--primary); transform: translateX(5px); }
.pub-list-img { width: 60px; height: 60px; border-radius: 12px; object-fit: cover; background: #f1f5f9; }
.pub-list-info { flex: 1; }
.pub-list-title { font-weight: 700; color: var(--slate-900); font-size: 0.95rem; margin-bottom: 2px; }
.pub-list-meta { font-size: 0.75rem; color: var(--slate-500); }

.btn-primary-hub { background: var(--primary); color: #fff; border: none; padding: 10px 20px; border-radius: 10px; font-weight: 700; transition: 0.2s; }
.btn-primary-hub:hover { background: var(--primary-dark); transform: translateY(-2px); color: #fff; }

.status-badge-hub { padding: 4px 10px; border-radius: 8px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
.status-publicado { background: #DCFCE7; color: #166534; }
.status-rascunho { background: #F1F5F9; color: #475569; }

.gallery-admin-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 20px; }
.gallery-admin-card { background: #fff; border-radius: 16px; overflow: hidden; border: 1px solid var(--slate-200); position: relative; }
.gallery-admin-img { width: 100%; height: 150px; object-fit: cover; }
.gallery-admin-actions { padding: 10px; display: flex; justify-content: space-between; }
</style>

<div class="page-header mt-0">
    <div>
        <div class="page-header-title"><i class="fa fa-screwdriver-wrench me-2" style="color:var(--primary)"></i>Gestão de Conteúdo</div>
        <div class="page-header-sub">Hub estratégico para controlar toda a presença digital da incubadora</div>
    </div>
    <div class="d-flex gap-2">
        <a href="/incubadora_ispsn/public/website/" target="_blank" class="btn btn-outline-dark rounded-pill fw-bold px-4">
            <i class="fa fa-external-link me-2"></i> Ver Website
        </a>
    </div>
</div>

<?php if ($flash_ok): ?><div class="alert alert-success border-0 shadow-sm mb-4"><?= htmlspecialchars($flash_ok) ?></div><?php endif; ?>
<?php if ($flash_erro): ?><div class="alert alert-danger border-0 shadow-sm mb-4"><?= htmlspecialchars($flash_erro) ?></div><?php endif; ?>

<div class="content-hub-tabs">
    <button class="hub-tab active" onclick="switchTab('config')"><i class="fa fa-sliders"></i> Configurações</button>
    <button class="hub-tab" onclick="switchTab('blog')"><i class="fa fa-newspaper"></i> Blog & Notícias</button>
    <button class="hub-tab" onclick="switchTab('galeria')"><i class="fa fa-images"></i> Galeria</button>
</div>

<!-- TAB 1: CONFIGURAÇÕES -->
<div id="section-config" class="hub-section active">
    <form method="post" action="/incubadora_ispsn/app/controllers/website_action.php" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_website">
        <?php foreach ($configs as $grupo => $itens): ?>
        <div class="config-card">
            <h5 class="fw-bold mb-4">Secção: <?= $grupo ?></h5>
            <div class="row g-4">
                <?php foreach ($itens as $c): ?>
                <div class="col-md-<?= ($c['tipo']==='textarea'||$c['tipo']==='html')?'12':'6' ?>">
                    <label class="small fw-bold text-uppercase text-muted mb-2"><?= str_replace('_',' ',$c['chave']) ?></label>
                    <?php if ($c['tipo']==='text'): ?>
                        <input type="text" name="config[<?= $c['chave'] ?>]" class="form-control rounded-3" value="<?= htmlspecialchars($c['valor']) ?>">
                    <?php elseif ($c['tipo']==='textarea'||$c['tipo']==='html'): ?>
                        <textarea name="config[<?= $c['chave'] ?>]" class="form-control rounded-3" rows="3"><?= htmlspecialchars($c['valor']) ?></textarea>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="text-end"><button type="submit" class="btn-primary-hub px-5 py-3 shadow-lg"><i class="fa fa-save me-2"></i> Guardar Alterações</button></div>
    </form>
</div>

<!-- TAB 2: BLOG/NOTÍCIAS -->
<div id="section-blog" class="hub-section">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="fw-bold mb-0">Feed de Publicações</h5><button class="btn btn-dark btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalPublicacao"><i class="fa fa-plus me-1"></i> Nova Publicação</button></div>
    <div class="pub-list">
        <?php foreach ($publicacoes as $p): ?>
        <div class="pub-list-item">
            <img src="<?= $p['imagem'] ?: '/incubadora_ispsn/assets/img/placeholder.png' ?>" class="pub-list-img">
            <div class="pub-list-info"><div class="pub-list-title"><?= htmlspecialchars($p['titulo']) ?></div><div class="pub-list-meta"><span class="status-badge-hub status-<?= $p['status'] ?>"><?= $p['status'] ?></span> · <?= date('d/m/Y', strtotime($p['criado_em'])) ?></div></div>
            <div class="d-flex gap-2"><button class="btn btn-light btn-sm rounded-pill" onclick='editarPub(<?= json_encode($p) ?>)'><i class="fa fa-edit"></i></button><a href="/incubadora_ispsn/app/controllers/website_action.php?action=delete_pub&id=<?= $p['id'] ?>" class="btn btn-light btn-sm rounded-pill text-danger" onclick="return confirm('Excluir?')"><i class="fa fa-trash"></i></a></div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- TAB 3: GALERIA -->
<div id="section-galeria" class="hub-section">
    <div class="d-flex justify-content-between align-items-center mb-4"><h5 class="fw-bold mb-0">Galeria de Imagens</h5><button class="btn btn-dark btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalGaleria"><i class="fa fa-plus me-1"></i> Adicionar Foto</button></div>
    <div class="gallery-admin-grid">
        <?php foreach ($galeria as $g): ?>
        <div class="gallery-admin-card">
            <img src="<?= $g['imagem'] ?>" class="gallery-admin-img">
            <div class="gallery-admin-actions">
                <span class="small fw-bold"><?= htmlspecialchars($g['titulo']) ?></span>
                <a href="/incubadora_ispsn/app/controllers/website_action.php?action=delete_galeria&id=<?= $g['id'] ?>" class="text-danger" onclick="return confirm('Remover da galeria?')"><i class="fa fa-trash"></i></a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- MODAL PUBLICAÇÃO -->
<div class="modal fade" id="modalPublicacao" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0"><h5 class="fw-bold">Gestão de Conteúdo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <form method="post" action="/incubadora_ispsn/app/controllers/website_action.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_pub">
                    <input type="hidden" name="id" id="pubId">
                    <div class="row g-3">
                        <div class="col-12"><label class="small fw-bold">Título</label><input type="text" name="titulo" id="pubTitulo" class="form-control" required></div>
                        <div class="col-md-6"><label class="small fw-bold">Categoria</label><select name="categoria" id="pubCategoria" class="form-select"><option>Notícia</option><option>Evento</option><option>Destaque</option></select></div>
                        <div class="col-md-6"><label class="small fw-bold">Status</label><select name="status" id="pubStatus" class="form-select"><option value="rascunho">Rascunho</option><option value="publicado">Publicado</option></select></div>
                        <div class="col-12"><label class="small fw-bold">Conteúdo</label><textarea name="conteudo" id="pubConteudo" class="form-control" rows="5"></textarea></div>
                        <div class="col-md-6"><label class="small fw-bold">Imagem de Capa</label><input type="file" name="imagem" class="form-control"></div>
                        <div class="col-md-6"><label class="small fw-bold">Galeria de Imagens (Múltiplas)</label><input type="file" name="galeria[]" class="form-control" multiple></div>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-3 mt-4 rounded-3 shadow-sm">Guardar Publicação</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL GALERIA -->
<div class="modal fade" id="modalGaleria" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0"><h5 class="fw-bold">Adicionar Foto à Galeria</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <form method="post" action="/incubadora_ispsn/app/controllers/website_action.php" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="save_galeria">
                    <div class="row g-3">
                        <div class="col-12"><label class="small fw-bold">Título do Momento</label><input type="text" name="titulo" class="form-control" required></div>
                        <div class="col-12"><label class="small fw-bold">Descrição Curta</label><input type="text" name="descricao" class="form-control"></div>
                        <div class="col-12"><label class="small fw-bold">Ficheiro de Imagem</label><input type="file" name="imagem" class="form-control" required></div>
                    </div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-3 mt-4 rounded-3 shadow-sm">Adicionar à Galeria</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tab) {
    document.querySelectorAll('.hub-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.hub-section').forEach(s => s.classList.remove('active'));
    event.currentTarget.classList.add('active');
    document.getElementById('section-' + tab).classList.add('active');
}
function editarPub(p) {
    document.getElementById('pubId').value = p.id;
    document.getElementById('pubTitulo').value = p.titulo;
    document.getElementById('pubCategoria').value = p.categoria;
    document.getElementById('pubStatus').value = p.status;
    document.getElementById('pubConteudo').value = p.conteudo;
    new bootstrap.Modal(document.getElementById('modalPublicacao')).show();
}
</script>
<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
