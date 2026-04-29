<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin', 'superadmin']);

$tituloPagina = 'Candidaturas';
$paginaActiva = 'candidaturas';

// Processos existentes
$processos = [];
$res = $mysqli->query("SELECT * FROM processos_candidatura ORDER BY criado_em DESC");
if ($res) while ($r = $res->fetch_assoc()) $processos[] = $r;

// Processo selecionado para ver candidaturas
$id_processo_sel = (int)($_GET['processo'] ?? ($processos[0]['id'] ?? 0));
$filtro_estado   = $_GET['estado'] ?? '';

// Candidaturas do processo selecionado
$candidaturas = [];
if ($id_processo_sel) {
    $where = "WHERE c.id_processo = $id_processo_sel";
    if ($filtro_estado) $where .= " AND c.estado = '" . $mysqli->real_escape_string($filtro_estado) . "'";
    $res = $mysqli->query("
        SELECT c.*, u.nome as avaliador_nome
        FROM candidaturas c
        LEFT JOIN usuarios u ON u.id = c.avaliado_por
        $where
        ORDER BY c.criado_em DESC
    ");
    if ($res) while ($r = $res->fetch_assoc()) $candidaturas[] = $r;
}

// Contagens por estado
$contagens = [];
if ($id_processo_sel) {
    $res = $mysqli->query("SELECT estado, COUNT(*) n FROM candidaturas WHERE id_processo=$id_processo_sel GROUP BY estado");
    if ($res) while ($r = $res->fetch_assoc()) $contagens[$r['estado']] = $r['n'];
}
$totalCand = array_sum($contagens);

// Flash messages
$flash_ok   = $_SESSION['flash_ok'] ?? '';
$flash_erro = $_SESSION['flash_erro'] ?? '';
$wa_redirect = $_SESSION['wa_redirect'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_erro'], $_SESSION['wa_redirect']);

// WhatsApp auto-redirect logic
$waLink = '';
if (isset($_GET['wa']) && isset($_GET['token'])) {
    $tkn = $_GET['token'];
    $stmt = $mysqli->prepare("SELECT c.*, cand.telefone as tel FROM convites c LEFT JOIN candidaturas cand ON cand.id = c.id_candidatura WHERE c.token = ? LIMIT 1");
    $stmt->bind_param('s', $tkn);
    $stmt->execute();
    $cv = $stmt->get_result()->fetch_assoc();
    if ($cv) {
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
        $link    = $baseUrl . '/incubadora_ispsn/public/register.php?invite=' . $tkn;
        $nome    = $cv['nome_sugerido'] ?? 'Estudante';
        $tel     = preg_replace('/\D/', '', $cv['telefone'] ?? $cv['tel'] ?? '');
        if (strlen($tel) === 9) $tel = '244' . $tel;
        $msg = "Olá! 🎉\n\nA sua candidatura à *Incubadora Académica ISPSN* foi *APROVADA!* 🚀\n\nCrie a sua conta aqui (válido 48h, uso único):\n🔗 {$link}\n\nUse o seu email e número de estudante ao registar-se.\n\n_Este link é pessoal e intransferível._";
        $waLink = 'https://wa.me/' . $tel . '?text=' . rawurlencode($msg);
    }
}

require_once __DIR__ . '/../partials/_layout.php';
?>

<style>
/* Sistema de Design Premium 2.0 - Core */
:root {
    --primary: #D97706;
    --primary-light: #FFFBEB;
    --primary-dark: #B45309;
    --slate-900: #0F172A;
    --slate-800: #1E293B;
    --slate-700: #334155;
    --slate-600: #475569;
    --slate-500: #64748B;
    --slate-400: #94A3B8;
    --slate-200: #E2E8F0;
    --slate-100: #F1F5F9;
    --slate-50: #F8FAFC;
    --white: #ffffff;
    --shadow-xl: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
}

.candidaturas-wrapper { animation: slideUp 0.5s cubic-bezier(0.16, 1, 0.3, 1); }
@keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

/* Cabeçalho de Secção */
.section-header-custom { display: flex; align-items: center; justify-content: space-between; margin: 0 0 15px 0; padding-top: 10px; }
.section-title-label { font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.15em; color: var(--slate-400); display: flex; align-items: center; gap: 8px; }

/* Grid de Processos */
.proc-grid-v2 { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 30px; }
.proc-card-v2 { 
    background: var(--white); border: 1px solid var(--slate-200); border-radius: 20px; padding: 20px; 
    cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); position: relative;
}
.proc-card-v2:hover { border-color: var(--primary); transform: translateY(-3px); box-shadow: var(--shadow-xl); }
.proc-card-v2.active { border-color: var(--primary); background: var(--primary-light); box-shadow: inset 0 0 0 2px var(--primary); }

.proc-status-pill { padding: 4px 10px; border-radius: 8px; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; display: inline-flex; align-items: center; gap: 5px; }
.status-aberto { background: #DCFCE7; color: #166534; }
.status-fechado { background: #FEE2E2; color: #991B1B; }
.status-preparacao { background: #FEF3C7; color: #92400E; }

/* Dashboard KPIs */
.kpi-row-v2 { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 30px; }
.kpi-card-v2 { 
    background: var(--white); border-radius: 20px; padding: 20px; border: 1px solid var(--slate-200);
    display: flex; align-items: center; gap: 15px; transition: 0.2s;
}
.kpi-card-v2:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }
.kpi-icon-v2 { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }

/* Filtros */
.filter-tabs-v2 { 
    display: flex; gap: 6px; margin-bottom: 25px; background: var(--slate-100); padding: 5px; border-radius: 14px; 
    width: fit-content; overflow-x: auto; border: 1px solid var(--slate-200);
}
.filter-tab-v2 { 
    padding: 7px 16px; border-radius: 10px; font-size: 0.8rem; font-weight: 700; color: var(--slate-500); 
    text-decoration: none; transition: 0.2s; display: flex; align-items: center; gap: 6px; white-space: nowrap;
}
.filter-tab-v2.active { background: var(--white); color: var(--primary); box-shadow: 0 4px 8px rgba(0,0,0,0.04); }
.tab-count { background: var(--slate-200); padding: 1px 6px; border-radius: 6px; font-size: 0.6rem; }
.active .tab-count { background: var(--primary-light); color: var(--primary); }

/* Candidatura Item */
.cand-item-v2 { 
    background: var(--white); border: 1px solid var(--slate-200); border-radius: 16px; padding: 16px 24px;
    display: grid; grid-template-columns: 2.2fr 1fr 1fr 1fr 140px; align-items: center; gap: 20px; margin-bottom: 10px;
    transition: 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}
.cand-item-v2:hover { border-color: var(--primary); transform: translateX(5px); box-shadow: var(--shadow-xl); }

.cand-avatar-v2 { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-weight: 900; color: #fff; font-size: 1rem; }
.cand-info-v2 .name { font-weight: 700; color: var(--slate-800); font-size: 0.9rem; display: block; }
.cand-info-v2 .idea { font-size: 0.75rem; color: var(--slate-500); display: block; margin-top: 2px; }

.status-badge-v2 { 
    padding: 5px 12px; border-radius: 8px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase;
    display: inline-flex; align-items: center; gap: 6px;
}
.status-badge-v2::before { content: ''; width: 5px; height: 5px; border-radius: 50%; background: currentColor; }

/* Modais */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(15, 23, 42, 0.8); backdrop-filter:blur(8px); z-index:2000; align-items:center; justify-content:center; padding:20px; }
.modal-overlay.open { display:flex; }
.modal-box-v2 { 
    background: #fff; border-radius: 24px; width: 100%; max-width: 680px; max-height: 90vh; 
    display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 40px 100px -20px rgba(0,0,0,0.4);
}
.modal-header-v2 { background: var(--slate-900); color: #fff; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; }
.modal-body-v2 { padding: 30px; overflow-y: auto; }
.modal-footer-v2 { padding: 15px 30px; background: var(--slate-50); border-top: 1px solid var(--slate-200); display: flex; gap: 10px; justify-content: flex-end; }

/* Utils */
.btn-action-v2 { 
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--slate-200); 
    display: flex; align-items: center; justify-content: center; background: #fff; color: var(--slate-500);
    transition: 0.2s; cursor: pointer;
}
.btn-action-v2:hover { background: var(--slate-100); color: var(--primary); transform: scale(1.05); }
.btn-action-v2.btn-success:hover { background: #DCFCE7; color: #166534; border-color: #166534; }
.btn-action-v2.btn-danger:hover { background: #FEE2E2; color: #991B1B; border-color: #991B1B; }

.wa-btn-v2 { 
    background: #25D366; color: #fff !important; border: none; padding: 10px 20px; border-radius: 10px; 
    font-weight: 700; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; font-size: 0.85rem;
}
.wa-btn-v2:hover { background: #1ebe5d; transform: translateY(-2px); }

.label-mini { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: var(--slate-400); letter-spacing: 0.05em; margin-bottom: 2px; }
.val-mini { font-size: 0.8rem; font-weight: 600; color: var(--slate-700); }
</style>

<div class="page-header mt-0" style="padding-top: 0; margin-bottom: 25px;">
    <div>
        <div class="page-header-title" style="font-size: 1.4rem;"><i class="fa fa-inbox me-2" style="color:var(--primary)"></i>Gestão de Candidaturas</div>
        <div class="page-header-sub">Pipeline de triagem e admissão de startups</div>
    </div>
    <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoProcesso">
        <i class="fa fa-plus me-2"></i> Novo Processo
    </button>
</div>

<?php if ($flash_ok): ?><div class="alert alert-success border-0 shadow-sm mb-4"><?= htmlspecialchars($flash_ok) ?></div><?php endif; ?>
<?php if ($flash_erro): ?><div class="alert alert-danger border-0 shadow-sm mb-4"><?= htmlspecialchars($flash_erro) ?></div><?php endif; ?>

<?php if ($waLink): ?>
<div class="card border-0 shadow-sm mb-4" style="background: linear-gradient(to right, #DCFCE7, #fff); border-left: 5px solid #25D366 !important; border-radius: 16px;">
    <div class="card-body d-flex align-items-center justify-content-between flex-wrap p-3">
        <div class="ps-2">
            <div class="fw-bold text-success mb-0" style="font-size: 1rem;"><i class="fa fa-circle-check me-2"></i>Convite Seguro Gerado</div>
            <div class="text-muted small">O link de registo está pronto para ser enviado.</div>
        </div>
        <a href="<?= htmlspecialchars($waLink) ?>" target="_blank" class="wa-btn-v2"><i class="fa-brands fa-whatsapp"></i> Enviar Convite</a>
    </div>
</div>
<?php endif; ?>

<div class="candidaturas-wrapper">
    <div class="section-header-custom">
        <div class="section-title-label"><i class="fa fa-folder-tree"></i> Processos de Candidatura</div>
    </div>
    
    <div class="proc-grid-v2">
        <?php foreach ($processos as $proc): 
            $statusClass = $proc['estado'] === 'aberto' ? 'status-aberto' : ($proc['estado'] === 'fechado' ? 'status-fechado' : 'status-preparacao');
            $statusLabel = $proc['estado'] === 'aberto' ? 'Aberto' : ($proc['estado'] === 'fechado' ? 'Fechado' : 'Preparação');
        ?>
        <div class="proc-card-v2 <?= $proc['id']==$id_processo_sel?'active':'' ?>" onclick="location.href='?processo=<?= $proc['id'] ?>'">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <span class="proc-status-pill <?= $statusClass ?>"><?= $statusLabel ?></span>
                <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" onclick="event.stopPropagation();">
                    <input type="hidden" name="action" value="toggle_processo">
                    <input type="hidden" name="id_processo" value="<?= $proc['id'] ?>">
                    <button type="submit" class="btn-action-v2" style="border:none; background:transparent" title="Alternar Estado"><i class="fa <?= $proc['estado']==='aberto'?'fa-lock-open':'fa-lock' ?>"></i></button>
                </form>
            </div>
            <div class="fw-bold mb-1" style="font-size: 0.95rem;"><?= htmlspecialchars($proc['nome']) ?></div>
            <div class="text-muted" style="font-size: 0.75rem;">
                <?php
                $cnt = $mysqli->query("SELECT COUNT(*) n FROM candidaturas WHERE id_processo=".(int)$proc['id'])->fetch_assoc()['n'];
                echo "<strong>$cnt</strong> submissões · <strong>{$proc['vagas']}</strong> vagas";
                ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="kpi-row-v2">
        <div class="kpi-card-v2">
            <div class="kpi-icon-v2" style="background:#F1F5F9; color:#475569;"><i class="fa fa-inbox"></i></div>
            <div><div class="h4 fw-900 mb-0"><?= $totalCand ?></div><div class="label-mini">Total</div></div>
        </div>
        <div class="kpi-card-v2">
            <div class="kpi-icon-v2" style="background:#FEF3C7; color:#B45309;"><i class="fa fa-clock"></i></div>
            <div><div class="h4 fw-900 mb-0"><?= $contagens['pendente']??0 ?></div><div class="label-mini">Pendentes</div></div>
        </div>
        <div class="kpi-card-v2">
            <div class="kpi-icon-v2" style="background:#DCFCE7; color:#166534;"><i class="fa fa-check-circle"></i></div>
            <div><div class="h4 fw-900 mb-0"><?= $contagens['selecionado']??0 ?></div><div class="label-mini">Aprovados</div></div>
        </div>
        <div class="kpi-card-v2">
            <div class="kpi-icon-v2" style="background:#DBEAFE; color:#1E40AF;"><i class="fa fa-user-plus"></i></div>
            <div><div class="h4 fw-900 mb-0"><?= $contagens['registado']??0 ?></div><div class="label-mini">Finalizados</div></div>
        </div>
    </div>

    <div class="filter-tabs-v2">
        <?php
        $estados = [''=> 'Todas','pendente'=>'Pendentes','em_analise'=>'Análise','selecionado'=>'Aprovados','rejeitado'=>'Recusados','convite_enviado'=>'Convidados','registado'=>'Incubados'];
        foreach ($estados as $k=>$v):
            $count = $k ? ($contagens[$k] ?? 0) : $totalCand;
        ?>
        <a href="?processo=<?= $id_processo_sel ?>&estado=<?= $k ?>" class="filter-tab-v2 <?= $filtro_estado===$k?'active':'' ?>">
            <?= $v ?> <span class="tab-count"><?= $count ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="cand-list-v2">
        <?php if (empty($candidaturas)): ?>
            <div class="text-center p-5 bg-white rounded-4 border border-dashed">
                <i class="fa fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
                <h6 class="fw-bold">Sem dados para exibir</h6>
                <p class="text-muted small">Não foram encontradas candidaturas com este filtro.</p>
            </div>
        <?php else: ?>
            <?php foreach ($candidaturas as $c): 
                $words = explode(' ', $c['nome']);
                $iniciais = mb_substr($words[0],0,1) . (isset($words[1]) ? mb_substr($words[1],0,1) : '');
                $colors = ['#6366F1','#EC4899','#F59E0B','#10B981','#3B82F6'];
                $color = $colors[ord($c['nome'][0]) % count($colors)];
                
                $st = $c['estado'];
                $stBadge = [
                    'pendente' => ['bg'=>'#F1F5F9', 'color'=>'#475569'],
                    'em_analise' => ['bg'=>'#FEF3C7', 'color'=>'#92400E'],
                    'selecionado' => ['bg'=>'#DCFCE7', 'color'=>'#166534'],
                    'rejeitado' => ['bg'=>'#FEE2E2', 'color'=>'#991B1B'],
                    'convite_enviado' => ['bg'=>'#DBEAFE', 'color'=>'#1E40AF'],
                    'registado' => ['bg'=>'#F5F3FF', 'color'=>'#5B21B6']
                ][$st] ?? ['bg'=>'#eee', 'color'=>'#333'];
            ?>
            <div class="cand-item-v2">
                <div class="d-flex align-items-center gap-3">
                    <div class="cand-avatar-v2" style="background:<?= $color ?>"><?= strtoupper($iniciais) ?></div>
                    <div class="cand-info-v2">
                        <span class="name"><?= htmlspecialchars($c['nome']) ?></span>
                        <span class="idea"><i class="fa fa-lightbulb text-warning me-1"></i> <?= htmlspecialchars(mb_substr($c['titulo_ideia'],0,50)) ?>...</span>
                    </div>
                </div>
                <div>
                    <div class="label-mini">Contacto</div>
                    <div class="val-mini text-truncate" style="max-width: 150px;"><?= htmlspecialchars($c['email']) ?></div>
                </div>
                <div>
                    <div class="label-mini">Institucional</div>
                    <div class="val-mini">Nº <?= htmlspecialchars($c['numero_estudante']) ?></div>
                </div>
                <div>
                    <span class="status-badge-v2" style="background:<?= $stBadge['bg'] ?>; color:<?= $stBadge['color'] ?>">
                        <?= ucfirst(str_replace('_',' ',$st)) ?>
                    </span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn-action-v2" title="Dossier" onclick='abrirModal(<?= json_encode($c) ?>)'><i class="fa fa-id-card"></i></button>
                    
                    <?php if (in_array($st, ['pendente','em_analise'])): ?>
                    <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php">
                        <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="selecionado">
                        <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&estado=<?= $filtro_estado ?>">
                        <button type="submit" class="btn-action-v2 btn-success" title="Aprovar"><i class="fa fa-check"></i></button>
                    </form>
                    <?php endif; ?>

                    <?php if ($st === 'selecionado'): ?>
                    <button class="btn-action-v2" style="color:#25D366; border-color:#25D366; background:#F0FDF4" title="WhatsApp" onclick='gerarConvite(<?= json_encode($c) ?>)'><i class="fa-brands fa-whatsapp"></i></button>
                    <?php endif; ?>

                    <?php if (!in_array($st, ['rejeitado','registado'])): ?>
                    <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php">
                        <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="rejeitado">
                        <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&estado=<?= $filtro_estado ?>">
                        <button type="submit" class="btn-action-v2 btn-danger" title="Rejeitar" onclick="return confirm('Rejeitar?')"><i class="fa fa-ban"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- MODAIS -->
<div class="modal-overlay" id="modalDetalhe">
    <div class="modal-box-v2">
        <div class="modal-header-v2">
            <h6 class="mb-0 fw-bold"><i class="fa fa-id-card me-2"></i> Dossier da Candidatura</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalDetalhe')"></button>
        </div>
        <div class="modal-body-v2" id="modalConteudo"></div>
        <div class="modal-footer-v2" id="modalFooter"></div>
    </div>
</div>

<div class="modal-overlay" id="modalConvite">
    <div class="modal-box-v2" style="max-width: 500px;">
        <div class="modal-header-v2">
            <h6 class="mb-0 fw-bold"><i class="fa-brands fa-whatsapp me-2"></i> Enviar Convite Seguro</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalConvite')"></button>
        </div>
        <div class="modal-body-v2">
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php">
                <input type="hidden" name="action" value="gerar_convite_seguro">
                <input type="hidden" name="id_cand" id="conviteIdCand">
                <input type="hidden" name="id_processo" value="<?= $id_processo_sel ?>">
                
                <div class="p-3 bg-light rounded-3 mb-3 border border-dashed">
                    <div class="label-mini">Candidato Selecionado</div>
                    <div class="fw-bold" id="conviteNome" style="font-size: 1.1rem;"></div>
                </div>
                
                <p class="text-muted small mb-4">Isto irá gerar um link de uso único e abrir o WhatsApp com uma mensagem personalizada.</p>
                
                <button type="submit" class="wa-btn-v2 w-100 justify-content-center py-3">
                    <i class="fa fa-rocket me-2"></i> Gerar Link e Abrir WhatsApp
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Novo Processo -->
<div class="modal fade" id="modalNovoProcesso" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-0 pb-0"><h5 class="modal-title fw-bold">Novo Processo</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-4">
                <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php">
                    <input type="hidden" name="action" value="criar_processo">
                    <div class="mb-3"><label class="form-label fw-bold small text-uppercase">Título</label><input type="text" name="nome" class="form-control rounded-3" placeholder="Ex: Batch #02 2026" required></div>
                    <div class="mb-3"><label class="form-label fw-bold small text-uppercase">Vagas</label><input type="number" name="vagas" class="form-control rounded-3" value="10" min="1"></div>
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-3 mt-2 rounded-3 shadow-sm">Criar e Ativar</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function abrirModal(c) {
    document.getElementById('modalConteudo').innerHTML = `
        <div class="row g-4">
            <div class="col-md-6">
                <div class="label-mini">Nome Completo</div>
                <div class="fw-bold border-bottom pb-2">${c.nome}</div>
            </div>
            <div class="col-md-6">
                <div class="label-mini">Email Institucional</div>
                <div class="fw-bold border-bottom pb-2">${c.email}</div>
            </div>
            <div class="col-md-6">
                <div class="label-mini">Nº Estudante</div>
                <div class="fw-bold border-bottom pb-2">${c.numero_estudante}</div>
            </div>
            <div class="col-md-6">
                <div class="label-mini">Telefone</div>
                <div class="fw-bold border-bottom pb-2">${c.telefone}</div>
            </div>
            <div class="col-12 mt-4">
                <div class="p-4 bg-light rounded-4 border">
                    <div class="label-mini text-primary mb-2">Ideia de Negócio</div>
                    <h5 class="fw-bold mb-3">${c.titulo_ideia}</h5>
                    <p class="text-muted mb-0 small" style="line-height:1.7">${c.descricao_ideia}</p>
                </div>
            </div>
        </div>
    `;

    let footer = '';
    if (c.estado === 'pendente' || c.estado === 'em_analise') {
        footer = `
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php">
                <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="${c.id}"><input type="hidden" name="estado" value="selecionado">
                <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&estado=<?= $filtro_estado ?>">
                <button type="submit" class="btn btn-success fw-bold px-4 py-2 rounded-3"><i class="fa fa-check me-2"></i> Aprovar</button>
            </form>
        `;
    }
    document.getElementById('modalFooter').innerHTML = footer + `<button class="btn btn-light fw-bold px-4 py-2 rounded-3" onclick="fecharModal('modalDetalhe')">Fechar</button>`;
    document.getElementById('modalDetalhe').classList.add('open');
}

function fecharModal(id) { document.getElementById(id).classList.remove('open'); }

function gerarConvite(c) {
    document.getElementById('conviteIdCand').value = c.id;
    document.getElementById('conviteNome').textContent = c.nome;
    document.getElementById('modalConvite').classList.add('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>
