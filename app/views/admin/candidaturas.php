<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin', 'superadmin', 'mentor']);

// Auto-migração resiliente de esquema para colunas do Pitch (executa 1 vez por sessão para evitar locks de BD)
if (empty($_SESSION['auto_migrated_pitch_cols'])) {
    try {
        $chkCol = $mysqli->query("SHOW COLUMNS FROM `candidaturas` LIKE 'pitch_problema'");
        if ($chkCol && $chkCol->num_rows === 0) {
            $mysqli->query("ALTER TABLE `candidaturas` ADD COLUMN `tipo_projeto` ENUM('startup_tecnologica','negocio_tradicional','individual','equipa','impacto_social') DEFAULT 'startup_tecnologica'");
            $mysqli->query("ALTER TABLE `candidaturas` ADD COLUMN `publico_alvo` TEXT NULL");
            $mysqli->query("ALTER TABLE `candidaturas` ADD COLUMN `modelo_negocio` TEXT NULL");
            $mysqli->query("ALTER TABLE `candidaturas` ADD COLUMN `diferencial` TEXT NULL");
            $mysqli->query("ALTER TABLE `candidaturas` ADD COLUMN `pitch_problema` DECIMAL(4,2) DEFAULT 0");
            $mysqli->query("ALTER TABLE `candidaturas` ADD COLUMN `pitch_solucao` DECIMAL(4,2) DEFAULT 0");
            $mysqli->query("ALTER TABLE `candidaturas` ADD COLUMN `pitch_modelo_negocio` DECIMAL(4,2) DEFAULT 0");
        }
    } catch (Throwable $e) {
        // Silencioso se MariaDB/MySQL já tiver criado
    }
    $_SESSION['auto_migrated_pitch_cols'] = true;
}

$tituloPagina = 'Candidaturas';
$paginaActiva = 'candidaturas';

// Pré-carregar contagens de candidaturas por processo
$contProc = [];
$resProc = $mysqli->query("SELECT id_processo, COUNT(*) n FROM candidaturas GROUP BY id_processo");
if ($resProc) while ($rp = $resProc->fetch_assoc()) $contProc[$rp['id_processo']] = $rp['n'];

// Processos existentes
$processos = [];
$res = $mysqli->query("SELECT * FROM processos_candidatura ORDER BY criado_em DESC");
if ($res) while ($r = $res->fetch_assoc()) $processos[] = $r;

// Processo selecionado para ver candidaturas
$id_processo_sel = (int)($_GET['processo'] ?? ($processos[0]['id'] ?? 0));
$filtro_fase     = $_GET['fase'] ?? 'rastreio_pitch';

// Candidaturas do processo selecionado
$candidaturas = [];
if ($id_processo_sel) {
    if ($filtro_fase === 'rastreio_pitch') {
        $where = "WHERE c.id_processo = $id_processo_sel AND (c.estado = 'pendente' OR (c.estado = 'em_analise' AND (c.pitch_nota_final IS NULL OR c.pitch_nota_final = 0)))";
    } elseif ($filtro_fase === 'selecao_admissao') {
        $where = "WHERE c.id_processo = $id_processo_sel AND c.estado = 'em_analise' AND c.pitch_nota_final > 0";
    } elseif ($filtro_fase === 'admitidos') {
        $where = "WHERE c.id_processo = $id_processo_sel AND c.estado IN ('selecionado', 'convite_enviado', 'registado')";
    } elseif ($filtro_fase === 'rejeitados') {
        $where = "WHERE c.id_processo = $id_processo_sel AND c.estado = 'rejeitado'";
    } else {
        $where = "WHERE c.id_processo = $id_processo_sel";
    }

    $res = $mysqli->query("
        SELECT c.*, u.nome as avaliador_nome
        FROM candidaturas c
        LEFT JOIN usuarios u ON u.id = c.avaliado_por
        $where
        ORDER BY c.criado_em DESC
    ");
    if ($res) while ($r = $res->fetch_assoc()) $candidaturas[] = $r;
}

// Contagens operacionais unificadas numa única consulta ultra-rápida
$contFases = ['rastreio_pitch' => 0, 'selecao_admissao' => 0, 'admitidos' => 0, 'rejeitados' => 0];
if ($id_processo_sel) {
    $resStats = $mysqli->query("
        SELECT 
            SUM(CASE WHEN (estado = 'pendente' OR (estado = 'em_analise' AND (pitch_nota_final IS NULL OR pitch_nota_final = 0))) THEN 1 ELSE 0 END) as rastreio_pitch,
            SUM(CASE WHEN estado = 'em_analise' AND pitch_nota_final > 0 THEN 1 ELSE 0 END) as selecao_admissao,
            SUM(CASE WHEN estado IN ('selecionado', 'convite_enviado', 'registado') THEN 1 ELSE 0 END) as admitidos,
            SUM(CASE WHEN estado = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados
        FROM candidaturas 
        WHERE id_processo = $id_processo_sel
    ");
    if ($resStats && $st = $resStats->fetch_assoc()) {
        $contFases['rastreio_pitch'] = (int)($st['rastreio_pitch'] ?? 0);
        $contFases['selecao_admissao'] = (int)($st['selecao_admissao'] ?? 0);
        $contFases['admitidos'] = (int)($st['admitidos'] ?? 0);
        $contFases['rejeitados'] = (int)($st['rejeitados'] ?? 0);
    }
}
$totalCand = array_sum($contFases);
$totalCand = array_sum($contFases);

// Contagens por Tipo de Projeto / Entidade (estilo Coordenações)
$contTiposProjeto = [
    'startup_tecnologica' => 0,
    'negocio_tradicional' => 0,
    'individual' => 0,
    'equipa' => 0,
    'impacto_social' => 0
];
if ($id_processo_sel) {
    try {
        $resTipos = $mysqli->query("SELECT tipo_projeto, COUNT(*) n FROM candidaturas WHERE id_processo = $id_processo_sel GROUP BY tipo_projeto");
        if ($resTipos) {
            while ($rt = $resTipos->fetch_assoc()) {
                $tp = $rt['tipo_projeto'] ?? 'startup_tecnologica';
                if (isset($contTiposProjeto[$tp])) {
                    $contTiposProjeto[$tp] = (int)$rt['n'];
                }
            }
        }
    } catch (Throwable $e) {
        // Proteção se a coluna tipo_projeto ainda não estiver sincronizada
    }
}

// Flash messages
$flash_ok   = $_SESSION['flash_ok'] ?? '';
$flash_erro = $_SESSION['flash_erro'] ?? '';
$wa_redirect = $_SESSION['wa_redirect'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_erro'], $_SESSION['wa_redirect']);

// WhatsApp auto-redirect logic
$waLink = '';
if (isset($_GET['wa']) && isset($_GET['token'])) {
    $tkn = $_GET['token'];
    $stmt = $mysqli->prepare("SELECT c.*, cand.telefone as tel, cand.tipo_candidato FROM convites c LEFT JOIN candidaturas cand ON cand.id = c.id_candidatura WHERE c.token = ? LIMIT 1");
    $stmt->bind_param('s', $tkn);
    $stmt->execute();
    $cv = $stmt->get_result()->fetch_assoc();
    if ($cv) {
        $baseUrl = 'http://' . $_SERVER['HTTP_HOST'];
        $link    = $baseUrl . '/incubadora_ispsn/public/register.php?invite=' . $tkn;
        $nome    = $cv['nome_sugerido'] ?? 'Estudante';
        $tel     = preg_replace('/\D/', '', $cv['telefone'] ?? $cv['tel'] ?? '');
        if (strlen($tel) === 9) $tel = '244' . $tel;
        if (($cv['tipo_candidato'] ?? '') === 'pre_licenciado') {
            $msg = "Olá! 🎉\n\nA sua candidatura à *Incubadora Académica ISPSN* foi *APROVADA!* 🚀\n\nCrie a sua conta aqui (válido 48h, uso único):\n🔗 {$link}\n\nUse o seu email ao registar-se.\n\n_Este link é pessoal e intransferível._";
        } else {
            $msg = "Olá! 🎉\n\nA sua candidatura à *Incubadora Académica ISPSN* foi *APROVADA!* 🚀\n\nCrie a sua conta aqui (válido 48h, uso único):\n🔗 {$link}\n\nUse o seu email e número de estudante ao registar-se.\n\n_Este link é pessoal e intransferível._";
        }
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

@media (max-width: 992px) {
    .cand-item-v2 {
        display: flex;
        flex-direction: column;
        align-items: stretch;
        gap: 14px;
        padding: 16px 20px;
    }
    .cand-item-v2 > div {
        width: 100% !important;
    }
    .cand-item-v2 > div:last-child {
        justify-content: flex-start;
        border-top: 1px solid var(--slate-200);
        padding-top: 12px;
        margin-top: 4px;
    }
}

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

/* Cartões Estilo Coordenações */
.tipo-cand-card {
    background: #ffffff;
    border: 1px solid var(--slate-200);
    border-radius: 16px;
    padding: 16px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    cursor: pointer;
    height: 100%;
}
.tipo-cand-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px -6px rgba(0, 0, 0, 0.08);
    border-color: var(--primary);
}
.tipo-cand-card.active-card {
    border-width: 2px;
    border-color: var(--primary);
    background: linear-gradient(135deg, rgba(217,119,6,0.05) 0%, rgba(255,255,255,1) 100%);
    box-shadow: 0 8px 16px -4px rgba(217,119,6,0.15);
}
.cat-badge-icon {
    width: 42px;
    height: 42px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    color: #fff;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    flex-shrink: 0;
}
</style>

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

    <!-- CABEÇALHO UNIFICADO E COMPACTO -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-2 border-bottom">
        <div>
            <div class="h4 fw-bold mb-1" style="color:var(--slate-900);">
                <i class="fa fa-inbox me-2" style="color:var(--primary)"></i>Gestão de Candidaturas
            </div>
            <div class="text-muted small">Pipeline de triagem e admissão de startups</div>
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <!-- Seletor de Processo Ativo & Botão de Abertura/Fecho -->
            <?php 
            $procSelObj = null;
            foreach ($processos as $pr) {
                if ($pr['id'] == $id_processo_sel) { $procSelObj = $pr; break; }
            }
            ?>
            <div class="d-flex align-items-center gap-2 bg-white border rounded-3 px-3 py-1.5 shadow-sm">
                <i class="fa fa-folder-open text-warning"></i>
                <span class="small fw-bold text-secondary">Edição:</span>
                <select class="form-select form-select-sm border-0 fw-bold bg-transparent text-dark p-0 me-2" style="cursor:pointer; font-size:0.85rem;" onchange="location.href='?processo='+this.value">
                    <?php foreach ($processos as $proc): 
                        $cnt = $contProc[(int)$proc['id']] ?? 0;
                    ?>
                        <option value="<?= $proc['id'] ?>" <?= $proc['id'] == $id_processo_sel ? 'selected' : '' ?>>
                            <?= htmlspecialchars($proc['nome']) ?> (<?= $cnt ?> submissões · <?= $proc['vagas'] ?> vagas)
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($procSelObj): 
                    $isAberto = ($procSelObj['estado'] === 'aberto');
                ?>
                <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin: 0;">
                    <input type="hidden" name="action" value="toggle_processo">
                    <input type="hidden" name="id_processo" value="<?= $procSelObj['id'] ?>">
                    <button type="submit" class="btn btn-sm <?= $isAberto ? 'btn-success' : 'btn-danger' ?> rounded-2 fw-bold d-inline-flex align-items-center gap-1 px-2 py-0.5" style="font-size:0.75rem;" title="<?= $isAberto ? 'Processo Aberto (clique para Fechar)' : 'Processo Fechado (clique para Abrir)' ?>">
                        <i class="fa <?= $isAberto ? 'fa-lock-open' : 'fa-lock' ?>"></i>
                        <?= $isAberto ? 'Aberto' : 'Fechado' ?>
                    </button>
                </form>
                <?php endif; ?>
            </div>

            <?php if ($id_processo_sel && ($contFases['rastreio_pitch'] ?? 0) > 0): ?>
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin: 0;">
                <input type="hidden" name="action" value="triagem_automatica">
                <input type="hidden" name="id_processo" value="<?= $id_processo_sel ?>">
                <input type="hidden" name="redirect" value="<?= $_SERVER['REQUEST_URI'] ?>">
                <button type="submit" class="btn btn-warning fw-bold text-white px-3 py-2 rounded-3 shadow-sm d-inline-flex align-items-center gap-2" style="background:#D97706; border:none; font-size:0.82rem;">
                    <i class="fa fa-bolt"></i> Triagem Automática
                </button>
            </form>
            <?php endif; ?>

            <button class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalNovoProcesso" style="font-size:0.82rem; padding:8px 16px;">
                <i class="fa fa-plus me-1.5"></i> Novo Processo
            </button>
        </div>
    </div>

    <!-- TIRA DE MÉTRICAS COMPACTAS -->
    <div class="row g-2 mb-4">
        <div class="col-md-3">
            <div class="bg-white border rounded-3 p-3 d-flex align-items-center gap-3 shadow-sm">
                <div class="kpi-icon-v2" style="background:#F1F5F9; color:#475569; width:38px; height:38px;"><i class="fa fa-inbox"></i></div>
                <div><div class="h5 fw-bold mb-0"><?= $totalCand ?></div><div class="label-mini">Total no Filtro</div></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bg-white border rounded-3 p-3 d-flex align-items-center gap-3 shadow-sm" onclick="window.location.href='?processo=<?= $id_processo_sel ?>&fase=rastreio_pitch'" style="cursor:pointer;">
                <div class="kpi-icon-v2" style="background:#FEF3C7; color:#B45309; width:38px; height:38px;"><i class="fa fa-magnifying-glass"></i></div>
                <div><div class="h5 fw-bold mb-0"><?= $contFases['rastreio_pitch'] ?></div><div class="label-mini">Rastreio Pitch</div></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bg-white border rounded-3 p-3 d-flex align-items-center gap-3 shadow-sm" onclick="window.location.href='?processo=<?= $id_processo_sel ?>&fase=selecao_admissao'" style="cursor:pointer;">
                <div class="kpi-icon-v2" style="background:#DCFCE7; color:#166534; width:38px; height:38px;"><i class="fa fa-circle-check"></i></div>
                <div><div class="h5 fw-bold mb-0"><?= $contFases['selecao_admissao'] ?></div><div class="label-mini">Para Seleção</div></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="bg-white border rounded-3 p-3 d-flex align-items-center gap-3 shadow-sm" onclick="window.location.href='?processo=<?= $id_processo_sel ?>&fase=admitidos'" style="cursor:pointer;">
                <div class="kpi-icon-v2" style="background:#DBEAFE; color:#1E40AF; width:38px; height:38px;"><i class="fa fa-user-plus"></i></div>
                <div><div class="h5 fw-bold mb-0"><?= $contFases['admitidos'] ?></div><div class="label-mini">Admitidos</div></div>
            </div>
        </div>
    </div>

    <div class="card-custom p-0 overflow-hidden shadow-sm border mt-3 mb-4" style="background:#fff; border-radius:18px;">
        <!-- CONTROLES UNIFICADOS DA TABELA -->
        <div class="p-3 bg-light border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3">
            
            <!-- ABAS DO FUNIL DE TRIAGEM -->
            <div class="filter-tabs-v2 mb-0" style="margin-bottom:0 !important;">
                <?php
                $fases = [
                    'rastreio_pitch' => '1. Rastreio Pitch 🔬',
                    'selecao_admissao' => '2. Fila de Seleção 🗳️',
                    'admitidos' => '3. Admitidos ✉️',
                    'rejeitados' => 'Recusados ✗'
                ];
                foreach ($fases as $k=>$v):
                    $count = $contFases[$k] ?? 0;
                ?>
                <a href="?processo=<?= $id_processo_sel ?>&fase=<?= $k ?>" class="filter-tab-v2 <?= $filtro_fase===$k?'active':'' ?>">
                    <?= $v ?> <span class="tab-count"><?= $count ?></span>
                </a>
                <?php endforeach; ?>
            </div>

            <!-- FILTRO DE CATEGORIA + CAMPO DE BUSCA + EXPORTAR -->
            <div class="d-flex align-items-center gap-2 flex-wrap" style="flex:1; justify-content:flex-end; min-width:300px;">
                <!-- Filtro de Tipo de Projeto / Entidade -->
                <select class="form-select form-select-sm rounded-3 border bg-white fw-semibold text-secondary" style="width: auto; font-size: 0.8rem; cursor:pointer;" onchange="filtrarPorTipoProjeto(this.value)">
                    <option value="todos">⚡ Todas as Categorias (<?= array_sum($contTiposProjeto) ?>)</option>
                    <option value="startup_tecnologica">🚀 Startup Tech (<?= $contTiposProjeto['startup_tecnologica'] ?>)</option>
                    <option value="negocio_tradicional">🏢 Empresa / Negócio (<?= $contTiposProjeto['negocio_tradicional'] ?>)</option>
                    <option value="individual">👤 Individual (<?= $contTiposProjeto['individual'] ?>)</option>
                    <option value="equipa">👥 Equipa / Grupo (<?= $contTiposProjeto['equipa'] ?>)</option>
                    <option value="impacto_social">🌍 Impacto Social (<?= $contTiposProjeto['impacto_social'] ?>)</option>
                </select>

                <!-- Input de Pesquisa Direta -->
                <div style="min-width: 220px;">
                    <input type="text" class="form-control form-control-sm px-3 py-1.5 rounded-3 border bg-white" id="candSearchInput" placeholder="🔍 Pesquisar candidato, email ou ideia..." onkeyup="filtrarCandidaturasTabela()" style="font-size:0.8rem;">
                </div>

                <!-- Exportar CSV -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-3 px-2.5 py-1.5" type="button" data-bs-toggle="dropdown" style="font-size:0.8rem;">
                        <i class="fa fa-file-csv me-1 text-success"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" style="border-radius:12px; font-size:0.82rem;">
                        <li><a class="dropdown-item py-2" href="/incubadora_ispsn/app/controllers/exportar_action.php?tipo=candidaturas&processo=<?= $id_processo_sel ?>"><i class="fa fa-list me-2 text-primary"></i>Todas as candidaturas</a></li>
                        <li><a class="dropdown-item py-2" href="/incubadora_ispsn/app/controllers/exportar_action.php?tipo=candidaturas&processo=<?= $id_processo_sel ?>&estado=selecionado"><i class="fa fa-check-circle me-2 text-success"></i>Só aprovados</a></li>
                        <li><a class="dropdown-item py-2" href="/incubadora_ispsn/app/controllers/exportar_action.php?tipo=candidaturas&processo=<?= $id_processo_sel ?>&estado=rejeitado"><i class="fa fa-times-circle me-2 text-danger"></i>Só recusados</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <?php if (empty($candidaturas)): ?>
            <div class="text-center p-5 bg-white">
                <i class="fa fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
                <h6 class="fw-bold">Sem candidaturas registradas</h6>
                <p class="text-muted small">Não foram encontradas candidaturas com este filtro.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabelaCandidaturas">
                    <thead class="table-light small text-uppercase fw-bold text-secondary border-bottom">
                        <tr>
                            <th style="padding: 14px 20px;">Candidato & Ideia</th>
                            <th style="padding: 14px 15px;">Contacto & Perfil</th>
                            <th style="padding: 14px 15px;">Entidade / Projeto</th>
                            <th style="padding: 14px 15px;">Estado / Pitch</th>
                            <th style="padding: 14px 20px;" class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
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

                            $tiposProjMap = [
                                'startup_tecnologica' => '🚀 Startup Tech',
                                'negocio_tradicional' => '🏢 Negócio / Empresa',
                                'individual' => '👤 Individual',
                                'equipa' => '👥 Equipa / Grupo',
                                'impacto_social' => '🌍 Impacto Social'
                            ];
                            $projText = $tiposProjMap[$c['tipo_projeto'] ?? ''] ?? ($c['tipo_projeto'] ?? '🚀 Startup Tech');
                        ?>
                        <tr class="cand-table-row" data-tipo-projeto="<?= htmlspecialchars($c['tipo_projeto'] ?? 'startup_tecnologica') ?>">
                            <td style="padding: 14px 20px;">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="cand-avatar-v2" style="background:<?= $color ?>; flex-shrink:0;"><?= strtoupper($iniciais) ?></div>
                                    <div>
                                        <strong class="d-block text-dark" style="font-size:0.9rem;"><?= htmlspecialchars($c['nome']) ?></strong>
                                        <span class="small text-muted d-block mt-0.5"><i class="fa fa-lightbulb text-warning me-1"></i><?= htmlspecialchars(mb_substr($c['titulo_ideia'],0,45)) ?>...</span>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 14px 15px;">
                                <div class="small fw-bold text-dark"><?= htmlspecialchars($c['email']) ?></div>
                                <div class="small text-muted mt-0.5">
                                    <i class="fa fa-phone text-success me-1"></i><?= htmlspecialchars($c['telefone']) ?>
                                    · <?php if ($c['tipo_candidato'] === 'pre_licenciado'): ?>
                                        <span class="badge bg-info-subtle text-info px-2 py-0.5" style="font-size:0.65rem;">Pré-licenciado</span>
                                    <?php else: ?>
                                        Nº <?= htmlspecialchars($c['numero_estudante']) ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td style="padding: 14px 15px;">
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-2" style="font-size:0.75rem; font-weight:700;">
                                    <?= $projText ?>
                                </span>
                                <div class="small text-muted mt-1"><i class="fa fa-tag me-1 text-secondary"></i><?= htmlspecialchars($c['area_tematica'] ?? 'Geral') ?></div>
                            </td>
                            <td style="padding: 14px 15px;">
                                <?php if ($filtro_fase === 'rastreio_pitch'): ?>
                                    <span class="badge bg-warning-subtle text-warning-dark fw-bold px-2 py-1 rounded-2" style="font-size:0.72rem;"><i class="fa fa-clock me-1"></i>Aguardando Notas</span>
                                <?php elseif ($filtro_fase === 'selecao_admissao'): ?>
                                    <div class="fw-bold text-dark" style="font-size:0.85rem">Média: <?= number_format($c['pitch_nota_final'], 1) ?>/10</div>
                                    <div class="small text-muted" style="font-size:0.7rem">Ino: <?= $c['pitch_inovacao'] ?> · Aut: <?= $c['pitch_sustentabilidade'] ?> · Emp: <?= $c['pitch_empreendedorismo'] ?></div>
                                <?php elseif ($filtro_fase === 'admitidos'): ?>
                                    <span class="status-badge-v2" style="background:<?= $stBadge['bg'] ?>; color:<?= $stBadge['color'] ?>">
                                        <?= $st === 'registado' ? 'Conta Aberta' : ($st === 'convite_enviado' ? 'Convite Enviado' : 'Aprovado') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge-v2" style="background:<?= $stBadge['bg'] ?>; color:<?= $stBadge['color'] ?>">
                                        <?= ucfirst(str_replace('_',' ',$st)) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 20px;" class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1">
                                    <button class="btn-action-v2" title="Dossier Completo" onclick='abrirModal(<?= json_encode($c) ?>)'><i class="fa fa-id-card"></i></button>
                                    
                                    <?php if ($filtro_fase === 'rastreio_pitch'): ?>
                                        <button class="btn-action-v2 btn-warning" title="Avaliar Pitch" onclick='abrirModalAvaliar(<?= json_encode($c) ?>)'><i class="fa fa-star"></i></button>
                                    <?php endif; ?>

                                    <?php if ($filtro_fase === 'selecao_admissao'): ?>
                                        <?php if ($_SESSION['usuario_perfil'] === 'superadmin'): ?>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;">
                                            <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="selecionado">
                                            <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
                                            <button type="submit" class="btn-action-v2 btn-success" title="Aprovar Admissão"><i class="fa fa-check"></i></button>
                                        </form>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;">
                                            <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="rejeitado">
                                            <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
                                            <button type="submit" class="btn-action-v2 btn-danger" title="Rejeitar Candidatura" onclick="return confirm('Tem a certeza que deseja rejeitar esta candidatura?')"><i class="fa fa-ban"></i></button>
                                        </form>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary small px-2 py-1 rounded-2" style="font-size:0.65rem;" title="Apenas Super Admin (DG/PR) pode aprovar a admissão"><i class="fa fa-user-shield me-1"></i>Aguardando DG/PR</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($filtro_fase === 'admitidos'): ?>
                                        <?php if ($st === 'selecionado'): ?>
                                            <button class="btn-action-v2" style="color:#25D366; border-color:#25D366; background:#F0FDF4" title="Enviar Convite WhatsApp" onclick='gerarConvite(<?= json_encode($c) ?>)'><i class="fa-brands fa-whatsapp"></i></button>
                                        <?php endif; ?>

                                        <?php if ($st !== 'registado' && $_SESSION['usuario_perfil'] === 'superadmin'): ?>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;">
                                            <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="rejeitado">
                                            <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
                                            <button type="submit" class="btn-action-v2 btn-danger" title="Cancelar Admissão / Rejeitar" onclick="return confirm('Cancelar admissão e rejeitar?')"><i class="fa fa-ban"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if (in_array($_SESSION['usuario_perfil'], ['admin', 'superadmin'])): ?>
                                    <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;" onsubmit="return confirm('Tem a certeza que deseja eliminar DEFINITIVAMENTE esta candidatura? Esta acção não pode ser desfeita.')">
                                        <input type="hidden" name="action" value="remover_candidatura">
                                        <input type="hidden" name="id_cand" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
                                        <button type="submit" class="btn-action-v2 text-danger" style="border-color:#FEE2E2; background:#FEF2F2;" title="Eliminar Candidatura">
                                            <i class="fa fa-trash-can"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
<!-- MODAIS -->
<!-- MODAL AVALIAÇÃO DE PITCH (LADO A LADO) -->
<div class="modal-overlay" id="modalAvaliarPitch">
    <div class="modal-box-v2" style="max-width: 960px; width: 95vw; border-radius: 20px;">
        <div class="modal-header-v2" style="background: linear-gradient(135deg, #0F172A 0%, var(--primary) 100%); padding: 18px 24px;">
            <h6 class="mb-0 fw-bold text-white"><i class="fa fa-star me-2 text-warning"></i> Avaliação Qualitativa do Pitch & Dossier da Startup</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalAvaliarPitch')"></button>
        </div>
        <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" id="formAvaliarPitch">
            <input type="hidden" name="action" value="avaliar_pitch_candidatura">
            <input type="hidden" name="id_cand" id="avaliarIdCand">
            <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
            
            <div class="modal-body-v2 p-4">
                <div class="row g-4">
                    <!-- ESQUERDA: DOSSIER E PITCH CANVAS DA STARTUP (60%) -->
                    <div class="col-lg-7 border-end pe-lg-4" style="max-height: 520px; overflow-y: auto;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge bg-primary-subtle text-primary fw-bold px-2.5 py-1" id="avaliarArea">Área Temática</span>
                            <span class="badge bg-warning-subtle text-warning-dark fw-bold px-2.5 py-1" id="avaliarTipoProj">Startup Tech</span>
                        </div>
                        <h4 class="fw-bold text-dark mb-1" id="avaliarTituloIdeia">Título da Ideia</h4>
                        <div class="small text-muted mb-3"><i class="fa fa-user me-1"></i> <span id="avaliarCandidatoNome">Candidato</span></div>

                        <div class="p-3 bg-light rounded-3 border mb-3">
                            <strong class="small text-uppercase text-secondary d-block mb-1"><i class="fa fa-align-left me-1"></i> Descrição Geral da Ideia:</strong>
                            <p class="small text-dark mb-0" id="avaliarDescricao" style="line-height:1.6"></p>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100">
                                    <strong class="small text-danger text-uppercase d-block mb-1" style="font-size:0.7rem;"><i class="fa fa-circle-exclamation me-1"></i> Problema:</strong>
                                    <div class="small text-secondary" id="avaliarProblema" style="line-height:1.5">—</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 bg-white rounded-3 border h-100">
                                    <strong class="small text-success text-uppercase d-block mb-1" style="font-size:0.7rem;"><i class="fa fa-lightbulb me-1"></i> Solução:</strong>
                                    <div class="small text-secondary" id="avaliarSolucao" style="line-height:1.5">—</div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <div class="p-3 bg-white rounded-3 border h-100">
                                    <strong class="small text-primary text-uppercase d-block mb-1" style="font-size:0.7rem;"><i class="fa fa-users me-1"></i> Público-Alvo:</strong>
                                    <div class="small text-secondary" id="avaliarPublicoAlvo" style="line-height:1.5">—</div>
                                </div>
                            </div>
                            <div class="col-md-6 mt-2">
                                <div class="p-3 bg-white rounded-3 border h-100">
                                    <strong class="small text-warning text-uppercase d-block mb-1" style="font-size:0.7rem;"><i class="fa fa-coins me-1"></i> Modelo de Negócio:</strong>
                                    <div class="small text-secondary" id="avaliarModeloNegocio" style="line-height:1.5">—</div>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 bg-white rounded-3 border mb-3" id="avaliarDiferencialWrapper">
                            <strong class="small text-info text-uppercase d-block mb-1" style="font-size:0.7rem;"><i class="fa fa-shield-halved me-1"></i> Diferencial Competitivo:</strong>
                            <div class="small text-secondary" id="avaliarDiferencial" style="line-height:1.5">—</div>
                        </div>

                        <div id="avaliarPitchFileContainer" style="display:none;" class="mt-2">
                            <a href="#" id="avaliarPitchFileLink" target="_blank" class="btn btn-sm btn-outline-warning fw-bold py-2 px-3 rounded-3" style="font-size:0.8rem; border-color:var(--primary); color:var(--primary); text-decoration:none;">
                                <i class="fa fa-file-pdf me-2"></i> Abrir Anexo do Pitch Deck (PDF/PPT)
                            </a>
                        </div>
                    </div>

                    <!-- DIREITA: FICHA DE PONTUAÇÃO & CÁLCULO DE MÉDIA (40%) -->
                    <div class="col-lg-5 ps-lg-4">
                        <div class="p-3 bg-light rounded-3 border mb-3 text-center shadow-sm">
                            <div class="label-mini text-uppercase fw-bold text-secondary">Média Ponderada Calculada</div>
                            <div class="display-6 fw-bold text-warning my-1" id="avaliarMediaCalc">5.0</div>
                            <small class="text-muted" style="font-size:0.7rem;">Pontuação de precisão de 0.0 a 10.0</small>
                        </div>

                        <div class="mb-2">
                            <label class="form-label-custom fw-bold d-flex justify-content-between mb-1" style="font-size:0.8rem;">
                                <span>1. Clareza do Problema</span>
                                <span class="badge bg-danger text-white fw-bold" id="valProb">5</span>
                            </label>
                            <input type="range" name="pitch_problema" class="form-range" min="0" max="10" step="1" value="5" oninput="calcMediaAvaliacao()" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label-custom fw-bold d-flex justify-content-between mb-1" style="font-size:0.8rem;">
                                <span>2. Eficácia da Solução</span>
                                <span class="badge bg-success text-white fw-bold" id="valSol">5</span>
                            </label>
                            <input type="range" name="pitch_solucao" class="form-range" min="0" max="10" step="1" value="5" oninput="calcMediaAvaliacao()" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label-custom fw-bold d-flex justify-content-between mb-1" style="font-size:0.8rem;">
                                <span>3. Modelo de Negócio</span>
                                <span class="badge bg-warning text-white fw-bold" id="valMod">5</span>
                            </label>
                            <input type="range" name="pitch_modelo_negocio" class="form-range" min="0" max="10" step="1" value="5" oninput="calcMediaAvaliacao()" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label-custom fw-bold d-flex justify-content-between mb-1" style="font-size:0.8rem;">
                                <span>4. Inovação & Diferencial</span>
                                <span class="badge bg-primary text-white fw-bold" id="valIno">5</span>
                            </label>
                            <input type="range" name="pitch_inovacao" class="form-range" min="0" max="10" step="1" value="5" oninput="calcMediaAvaliacao()" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-custom fw-bold d-flex justify-content-between mb-1" style="font-size:0.8rem;">
                                <span>5. Autossustentabilidade</span>
                                <span class="badge bg-info text-white fw-bold" id="valSust">5</span>
                            </label>
                            <input type="range" name="pitch_sustentabilidade" class="form-range" min="0" max="10" step="1" value="5" oninput="calcMediaAvaliacao()" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">PARECER TÉCNICO / OBSERVAÇÕES</label>
                            <textarea name="pitch_observacoes" class="form-control-custom" rows="2" placeholder="Insira o seu parecer qualificado sobre a ideia..."></textarea>
                        </div>

                        <!-- BOTÃO DE CONFIRMAÇÃO DIRETO NA COLUNA DA DIREITA -->
                        <div class="p-3 bg-white border rounded-3 text-center shadow-sm">
                            <div class="form-check text-start mb-2">
                                <input class="form-check-input" type="checkbox" id="chkConfirmarAvaliacao" required checked>
                                <label class="form-check-label small fw-bold text-secondary" for="chkConfirmarAvaliacao" style="font-size:0.75rem;">
                                    Confirmo o parecer e as 5 notas atribuídas
                                </label>
                            </div>
                            <button type="submit" class="btn btn-warning w-100 fw-bold py-2.5 rounded-3 text-white shadow-sm" id="btnConfirmarAvaliacaoSubmit" style="background:var(--primary); border:none; font-size:0.88rem;">
                                <i class="fa fa-check-circle me-1.5"></i> Confirmar Avaliação (<span id="btnMediaText">5.0</span>)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer-v2 bg-light justify-content-end">
                <button type="button" class="btn btn-light border fw-bold px-4 py-2 rounded-3" onclick="fecharModal('modalAvaliarPitch')">Cancelar</button>
            </div>
        </form>
    </div>
</div>

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

<!-- MODAL: Assistente de Envio Wizard -->
<div class="modal-overlay" id="modalWizard">
    <div class="modal-box-v2" style="max-width: 600px;">
        <div class="modal-header-v2" style="background: #25D366;">
            <h6 class="mb-0 fw-bold text-white"><i class="fa-brands fa-whatsapp me-2"></i> Assistente de Envio de Convites</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalWizard')"></button>
        </div>
        <div class="modal-body-v2">
            <div id="wizardContent">
                <!-- Conteúdo dinâmico preenchido por JS -->
            </div>
        </div>
        <div class="modal-footer-v2">
            <span class="text-muted small me-auto" id="wizardProgress">Progresso: 0 / 0</span>
            <button class="btn btn-light fw-bold px-3 py-2 rounded-3" onclick="fecharModal('modalWizard')">Cancelar</button>
            <button class="btn btn-success fw-bold text-white px-3 py-2 rounded-3" id="btnWizardNext" style="display:none;" onclick="wizardNext()">Avançar para o Próximo <i class="fa fa-arrow-right ms-1"></i></button>
        </div>
    </div>
</div>

<!-- MODAL: Exportação em Massa -->
<div class="modal-overlay" id="modalMassa">
    <div class="modal-box-v2" style="max-width: 650px;">
        <div class="modal-header-v2" style="background: var(--slate-900);">
            <h6 class="mb-0 fw-bold text-white"><i class="fa fa-copy me-2"></i> Exportar Links em Massa</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalMassa')"></button>
        </div>
        <div class="modal-body-v2">
            <p class="text-muted small">Abaixo estão listados todos os candidatos aprovados. Clique no botão para gerar tokens de convites e criar uma lista de envio formatada para copiar para a área de transferência.</p>
            
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="chkSelecionarTodosMassa" checked onchange="toggleSelectMassa(this)">
                <label class="form-check-label fw-bold small text-uppercase" for="chkSelecionarTodosMassa">Selecionar Todos</label>
            </div>
            
            <div id="massaList" style="max-height: 180px; overflow-y: auto; margin-bottom: 20px; border: 1px solid var(--slate-200); border-radius: 12px; padding: 12px;">
                <!-- Lista de checkboxes para cada candidato -->
            </div>
            
            <div class="mb-3" style="display:none;" id="massaTextAreaWrapper">
                <label class="label-mini">Lista Formatada de Convites</label>
                <textarea class="form-control-custom w-100" id="massaTextArea" rows="6" readonly style="font-family: monospace; font-size: 0.8rem; background: var(--slate-50); outline: none; border: 1px solid var(--slate-200); padding: 10px; border-radius: 8px;"></textarea>
            </div>
            
            <button class="btn btn-warning fw-bold w-100 py-3 rounded-3 text-white" id="btnMassaProcessar" style="background:#D97706; border:none;" onclick="processarMassa()">
                <i class="fa fa-bolt me-1"></i> Gerar e Copiar Links Selecionados
            </button>
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
const usuarioPerfil = <?= json_encode($_SESSION['usuario_perfil']) ?>;
const candidatosAprovados = <?= json_encode(array_values(array_filter($candidaturas, function($c) { return $c['estado'] === 'selecionado'; }))) ?>;

function abrirModal(c) {
    const isPre = c.tipo_candidato === 'pre_licenciado';
    const emailLabel = isPre ? 'Email Pessoal' : 'Email Institucional';
    const numEstudanteText = isPre ? 'N/A (Pré-licenciado)' : (c.numero_estudante || '—');
    const tipoBadge = isPre ? '<span class="badge bg-info text-white ms-2" style="font-size:0.7rem;">Pré-licenciado</span>' : '<span class="badge bg-primary text-white ms-2" style="font-size:0.7rem;">Estudante ISPSN</span>';

    const tiposProjMap = {
        'startup_tecnologica': '🚀 Startup Tecnológica',
        'negocio_tradicional': '🏢 Negócio Tradicional / Empresa',
        'individual': '👤 Projeto Individual',
        'equipa': '👥 Equipa / Grupo de Estudantes',
        'impacto_social': '🌍 Projeto de Impacto Social'
    };
    const projText = tiposProjMap[c.tipo_projeto] || c.tipo_projeto || '—';

    document.getElementById('modalConteudo').innerHTML = `
        <div class="row g-3">
            <div class="col-md-6">
                <div class="label-mini">Nome Completo</div>
                <div class="fw-bold border-bottom pb-2">${c.nome} ${tipoBadge}</div>
            </div>
            <div class="col-md-6">
                <div class="label-mini">${emailLabel}</div>
                <div class="fw-bold border-bottom pb-2">${c.email}</div>
            </div>
            <div class="col-md-4">
                <div class="label-mini">Nº Estudante</div>
                <div class="fw-bold border-bottom pb-2">${numEstudanteText}</div>
            </div>
            <div class="col-md-4">
                <div class="label-mini">Telefone</div>
                <div class="fw-bold border-bottom pb-2">${c.telefone}</div>
            </div>
            <div class="col-md-4">
                <div class="label-mini">Formato / Entidade</div>
                <div class="fw-bold border-bottom pb-2 text-warning">${projText}</div>
            </div>

            <!-- FICHA DE PITCH DA CANDIDATURA -->
            <div class="col-12 mt-3">
                <div class="p-4 bg-light rounded-4 border">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="label-mini text-primary"><i class="fa fa-rocket me-1"></i> Ficha de Pitch da Ideia</div>
                        <span class="badge bg-warning-subtle text-warning-dark">${c.area_tematica || 'Geral'}</span>
                    </div>
                    <h5 class="fw-bold mb-3" style="color:var(--slate-800);">${c.titulo_ideia}</h5>
                    
                    <div class="mb-3">
                        <strong class="small text-uppercase text-muted d-block mb-1">Descrição Geral:</strong>
                        <p class="text-secondary small mb-0" style="line-height:1.6">${c.descricao_ideia}</p>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded-3 border">
                                <strong class="small text-danger text-uppercase d-block mb-1"><i class="fa fa-circle-exclamation me-1"></i> Problema:</strong>
                                <span class="small text-muted">${c.problema || '—'}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded-3 border">
                                <strong class="small text-success text-uppercase d-block mb-1"><i class="fa fa-lightbulb me-1"></i> Solução:</strong>
                                <span class="small text-muted">${c.solucao || '—'}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded-3 border">
                                <strong class="small text-primary text-uppercase d-block mb-1"><i class="fa fa-users me-1"></i> Público-Alvo:</strong>
                                <span class="small text-muted">${c.publico_alvo || '—'}</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded-3 border">
                                <strong class="small text-warning text-uppercase d-block mb-1"><i class="fa fa-coins me-1"></i> Modelo de Negócio:</strong>
                                <span class="small text-muted">${c.modelo_negocio || '—'}</span>
                            </div>
                        </div>
                    </div>

                    ${c.diferencial ? `
                        <div class="mt-3 p-3 bg-white rounded-3 border">
                            <strong class="small text-info text-uppercase d-block mb-1"><i class="fa fa-shield-halved me-1"></i> Diferencial Competitivo:</strong>
                            <span class="small text-muted">${c.diferencial}</span>
                        </div>
                    ` : ''}

                    ${c.pitch_path ? `
                        <div class="mt-3">
                            <a href="/incubadora_ispsn/${c.pitch_path}" target="_blank" class="btn btn-sm btn-outline-warning fw-bold py-2 px-3 rounded-3" style="font-size:0.8rem; border-color:var(--primary); color:var(--primary); text-decoration:none;">
                                <i class="fa fa-file-pdf me-2"></i> Abrir Anexo do Pitch Deck (PDF/PPT)
                            </a>
                        </div>
                    ` : ''}
                </div>
            </div>
        </div>
    `;

    let footer = '';
    let candJsonEscaped = JSON.stringify(c).replace(/'/g, "&apos;");
    if (c.estado === 'pendente' || !c.pitch_inovacao) {
        footer = `
            <button type="button" class="btn btn-warning fw-bold text-white px-4 py-2 rounded-3" style="background:var(--primary); border:none;" onclick="fecharModal('modalDetalhe'); abrirModalAvaliar(${candJsonEscaped});">
                <i class="fa fa-star me-2"></i> Avaliar Pitch
            </button>
        `;
    } else if (c.estado === 'em_analise') {
        if (usuarioPerfil === 'superadmin') {
            footer = `
                <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;">
                    <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="${c.id}"><input type="hidden" name="estado" value="selecionado">
                    <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
                    <button type="submit" class="btn btn-success fw-bold px-4 py-2 rounded-3"><i class="fa fa-check me-2"></i> Aprovar Admissão</button>
                </form>
            `;
        } else {
            footer = `<span class="text-muted small me-3"><i class="fa fa-user-shield me-1"></i> Aguardando Decisão Final do Super Admin (DG/PR)</span>`;
        }
    }
    document.getElementById('modalFooter').innerHTML = footer + `<button class="btn btn-light fw-bold px-4 py-2 rounded-3" onclick="fecharModal('modalDetalhe')">Fechar</button>`;
    document.getElementById('modalDetalhe').classList.add('open');
}

function fecharModal(id) { document.getElementById(id).classList.remove('open'); }

function abrirModalAvaliar(c) {
    document.getElementById('avaliarIdCand').value = c.id;
    document.getElementById('avaliarCandidatoNome').textContent = `${c.nome} (${c.email})`;
    document.getElementById('avaliarTituloIdeia').textContent = c.titulo_ideia || 'Sem Título';
    document.getElementById('avaliarArea').textContent = c.area_tematica || 'Geral';
    
    const tiposProjMap = {
        'startup_tecnologica': '🚀 Startup Tech',
        'negocio_tradicional': '🏢 Empresa',
        'individual': '👤 Individual',
        'equipa': '👥 Equipa',
        'impacto_social': '🌍 Impacto Social'
    };
    document.getElementById('avaliarTipoProj').textContent = tiposProjMap[c.tipo_projeto] || c.tipo_projeto || 'Startup Tech';
    document.getElementById('avaliarDescricao').textContent = c.descricao_ideia || '—';
    document.getElementById('avaliarProblema').textContent = c.problema || '—';
    document.getElementById('avaliarSolucao').textContent = c.solucao || '—';
    document.getElementById('avaliarPublicoAlvo').textContent = c.publico_alvo || '—';
    document.getElementById('avaliarModeloNegocio').textContent = c.modelo_negocio || '—';
    document.getElementById('avaliarDiferencial').textContent = c.diferencial || '—';

    const fileContainer = document.getElementById('avaliarPitchFileContainer');
    const fileLink = document.getElementById('avaliarPitchFileLink');
    if (fileContainer && fileLink) {
        if (c.pitch_path) {
            fileLink.href = `/incubadora_ispsn/${c.pitch_path}`;
            fileContainer.style.display = 'block';
        } else {
            fileContainer.style.display = 'none';
        }
    }
    
    calcMediaAvaliacao();
    document.getElementById('modalAvaliarPitch').classList.add('open');
}

function calcMediaAvaliacao() {
    const f = document.getElementById('formAvaliarPitch');
    if (!f) return;
    const prob = parseFloat(f.querySelector('[name="pitch_problema"]').value) || 0;
    const sol = parseFloat(f.querySelector('[name="pitch_solucao"]').value) || 0;
    const mod = parseFloat(f.querySelector('[name="pitch_modelo_negocio"]').value) || 0;
    const ino = parseFloat(f.querySelector('[name="pitch_inovacao"]').value) || 0;
    const sust = parseFloat(f.querySelector('[name="pitch_sustentabilidade"]').value) || 0;
    
    if (document.getElementById('valProb')) document.getElementById('valProb').textContent = prob;
    if (document.getElementById('valSol')) document.getElementById('valSol').textContent = sol;
    if (document.getElementById('valMod')) document.getElementById('valMod').textContent = mod;
    if (document.getElementById('valIno')) document.getElementById('valIno').textContent = ino;
    if (document.getElementById('valSust')) document.getElementById('valSust').textContent = sust;
    
    const media = ((prob + sol + mod + ino + sust) / 5).toFixed(1);
    if (document.getElementById('avaliarMediaCalc')) document.getElementById('avaliarMediaCalc').textContent = media;
    if (document.getElementById('btnMediaText')) document.getElementById('btnMediaText').textContent = media;
}

function gerarConvite(c) {
    document.getElementById('conviteIdCand').value = c.id;
    document.getElementById('conviteNome').textContent = c.nome;
    document.getElementById('modalConvite').classList.add('open');
}

document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); }));

// Lógica de Envio Dinâmico Wizard
let wizardQueue = [];
let wizardIndex = 0;

function iniciarWizardEnvio() {
    wizardQueue = candidatosAprovados.filter(c => c.estado === 'selecionado');
    if (wizardQueue.length === 0) {
        alert('Nenhum candidato aguardando envio de convite nesta lista.');
        return;
    }
    wizardIndex = 0;
    mostrarWizardStep();
    document.getElementById('modalWizard').classList.add('open');
}

function mostrarWizardStep() {
    if (wizardIndex >= wizardQueue.length) {
        document.getElementById('wizardContent').innerHTML = `
            <div class="text-center p-5">
                <i class="fa fa-circle-check fa-4x text-success mb-3"></i>
                <h5 class="fw-bold">Fila Concluída!</h5>
                <p class="text-muted small">Todos os convites da fila atual foram abertos no WhatsApp.</p>
                <button class="btn btn-primary fw-bold px-4 py-2 mt-2 rounded-3" style="background:var(--primary); border:none;" onclick="location.reload()">Concluir e Recarregar</button>
            </div>
        `;
        document.getElementById('btnWizardNext').style.display = 'none';
        document.getElementById('wizardProgress').textContent = `Fila Concluída!`;
        return;
    }

    const c = wizardQueue[wizardIndex];
    document.getElementById('wizardProgress').textContent = `Candidato ${wizardIndex + 1} de ${wizardQueue.length}`;
    document.getElementById('btnWizardNext').style.display = 'none';

    const isPre = c.tipo_candidato === 'pre_licenciado';
    const numEstudanteText = isPre ? 'Pré-licenciado' : 'Nº ' + c.numero_estudante;

    document.getElementById('wizardContent').innerHTML = `
        <div class="p-3 bg-light rounded-4 border mb-4">
            <div class="label-mini text-primary">Candidato Atual</div>
            <h5 class="fw-bold mb-1">${c.nome}</h5>
            <div class="text-muted small">${c.email} · ${numEstudanteText}</div>
            <div class="mt-2"><span class="badge bg-success-subtle text-success small" style="font-size:0.8rem; color:#166534;"><i class="fa fa-phone me-1"></i>${c.telefone}</span></div>
        </div>

        <div class="mb-4">
            <div class="label-mini">Visualização da Mensagem</div>
            <div class="p-3 bg-white border rounded-3 small text-muted" style="white-space: pre-wrap; font-family: sans-serif; max-height: 150px; overflow-y: auto;">
Olá ${c.nome.split(' ')[0]}! 🎉

A sua candidatura à *Incubadora Académica ISPSN* foi *APROVADA!* 🚀

Para criar a sua conta no portal e iniciar o processo, aceda ao link abaixo:
🔗 http://${window.location.host}/incubadora_ispsn/public/register.php?invite=[Token Seguro]

⏰ *Atenção:* Este link é válido por apenas *48 horas* e pode ser usado *uma única vez*.
            </div>
        </div>

        <div class="text-center animate__animated animate__fadeIn" id="wizardActionArea">
            <button class="btn btn-success btn-lg w-100 py-3 fw-bold rounded-4 shadow-sm" style="background:#25D366; border:none;" onclick="gerarEAbrirWhatsApp(${c.id})">
                <i class="fa-brands fa-whatsapp me-2"></i> Gerar Link e Abrir WhatsApp
            </button>
        </div>
    `;
}

function gerarEAbrirWhatsApp(idCand) {
    const actionArea = document.getElementById('wizardActionArea');
    actionArea.innerHTML = `<button class="btn btn-success btn-lg w-100 py-3 fw-bold rounded-4 shadow-sm" disabled style="background:#25D366; border:none;"><i class="fa fa-spinner fa-spin me-2"></i> A gerar link seguro...</button>`;

    const formData = new FormData();
    formData.append('action', 'gerar_convite_ajax');
    formData.append('id_cand', idCand);

    fetch('/incubadora_ispsn/app/controllers/candidatura_action.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.sucesso) {
            window.open(data.wa_url, '_blank');
            actionArea.innerHTML = `
                <div class="alert alert-success border-0 small py-3 mb-0 rounded-4" style="background:#DCFCE7; color:#166534;">
                    <i class="fa fa-circle-check me-2"></i> Convite seguro gerado e WhatsApp aberto em nova janela!
                </div>
            `;
            document.getElementById('btnWizardNext').style.display = 'inline-block';
        } else {
            alert('Erro: ' + data.erro);
            mostrarWizardStep();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao processar convite via AJAX.');
        mostrarWizardStep();
    });
}

function wizardNext() {
    wizardIndex++;
    mostrarWizardStep();
}

// Lógica de Envio em Lote / Cópia em Massa
function abrirModalMassa() {
    const list = document.getElementById('massaList');
    const aprovados = candidatosAprovados.filter(c => c.estado === 'selecionado');
    
    if (aprovados.length === 0) {
        alert('Nenhum candidato aprovado para exportar.');
        return;
    }

    list.innerHTML = aprovados.map(c => `
        <div class="form-check py-1 border-bottom d-flex align-items-center">
            <input class="form-check-input chk-massa-cand me-2" type="checkbox" value="${c.id}" id="chkMassa_${c.id}" checked>
            <label class="form-check-label small mb-0" for="chkMassa_${c.id}">
                <strong>${c.nome}</strong> (${c.telefone})
            </label>
        </div>
    `).join('');

    document.getElementById('massaTextAreaWrapper').style.display = 'none';
    document.getElementById('btnMassaProcessar').style.display = 'block';
    document.getElementById('modalMassa').classList.add('open');
}

function toggleSelectMassa(master) {
    document.querySelectorAll('.chk-massa-cand').forEach(chk => chk.checked = master.checked);
}

function processarMassa() {
    const checkboxes = document.querySelectorAll('.chk-massa-cand:checked');
    if (checkboxes.length === 0) {
        alert('Por favor, selecione pelo menos um candidato.');
        return;
    }

    const ids = Array.from(checkboxes).map(chk => parseInt(chk.value));
    const btn = document.getElementById('btnMassaProcessar');
    btn.innerHTML = `<i class="fa fa-spinner fa-spin me-2"></i> A processar convites em lote...`;
    btn.disabled = true;

    let resultadosText = "";
    
    // Processamento assíncrono sequencial
    async function processarItens() {
        for (let id of ids) {
            const formData = new FormData();
            formData.append('action', 'gerar_convite_ajax');
            formData.append('id_cand', id);

            try {
                let response = await fetch('/incubadora_ispsn/app/controllers/candidatura_action.php', {
                    method: 'POST',
                    body: formData
                });
                let data = await response.json();
                if (data.sucesso) {
                    resultadosText += `Candidato: ${data.candidato.nome}\nTelefone: ${data.candidato.telefone}\nLink Único: ${data.link_registo}\n\n`;
                }
            } catch (e) {
                console.error("Erro no id " + id, e);
            }
        }
    }

    processarItens().then(() => {
        btn.innerHTML = `<i class="fa fa-check me-2"></i> Convites Gerados!`;
        btn.style.background = '#10B981';
        
        const textArea = document.getElementById('massaTextArea');
        textArea.value = resultadosText;
        document.getElementById('massaTextAreaWrapper').style.display = 'block';

        textArea.select();
        textArea.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(resultadosText)
        .then(() => {
            alert('Todos os links foram gerados com sucesso e copiados para a área de transferência!');
        })
        .catch(() => {
            alert('Links gerados! Por favor, copie manualmente da caixa de texto.');
        });

        setTimeout(() => {
            btn.innerHTML = `<i class="fa fa-bolt me-1"></i> Gerar e Copiar Links Selecionados`;
            btn.style.background = '#D97706';
            btn.disabled = false;
        }, 3000);
    });
}

function filtrarCandidaturasTabela() {
    const query = (document.getElementById('candSearchInput')?.value || '').toLowerCase();
    const rows = document.querySelectorAll('.cand-table-row');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(query) ? '' : 'none';
    });
}

function filtrarPorTipoProjeto(tipo, el) {
    document.querySelectorAll('.tipo-cand-card').forEach(c => c.classList.remove('active-card'));
    if (el) el.classList.add('active-card');
    
    const rows = document.querySelectorAll('.cand-table-row');
    rows.forEach(row => {
        if (tipo === 'todos') {
            row.style.display = '';
        } else {
            const dataTipo = row.getAttribute('data-tipo-projeto') || '';
            row.style.display = (dataTipo === tipo) ? '' : 'none';
        }
    });
}
</script>

<?php require_once __DIR__ . '/../partials/_layout_end.php'; ?>

