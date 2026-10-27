<?php
require_once __DIR__ . '/../../../config/auth.php';
obrigarPerfil(['admin', 'superadmin', 'mentor']);

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
$filtro_sub      = $_GET['sub'] ?? 'todos';

// Candidaturas do processo selecionado
$candidaturas = [];
if ($id_processo_sel) {
    if ($filtro_fase === 'rastreio_pitch') {
        $where = "WHERE c.id_processo = $id_processo_sel AND (c.estado = 'pendente' OR (c.estado = 'em_analise' AND (c.pitch_nota_final IS NULL OR c.pitch_nota_final = 0)))";
    } elseif ($filtro_fase === 'selecao_admissao') {
        $where = "WHERE c.id_processo = $id_processo_sel AND c.estado = 'em_analise' AND c.pitch_nota_final > 0";
    } elseif ($filtro_fase === 'admitidos') {
        $where = "WHERE c.id_processo = $id_processo_sel AND c.estado IN ('selecionado', 'convocado', 'validado_presencial', 'convite_enviado', 'registado', 'ausente', 'recusou', 'reprovado_reuniao')";
        if ($filtro_sub === 'selecionados') {
            $where .= " AND c.estado = 'selecionado'";
        } elseif ($filtro_sub === 'convocados') {
            $where .= " AND c.estado = 'convocado'";
        } elseif ($filtro_sub === 'validados') {
            $where .= " AND c.estado = 'validado_presencial'";
        } elseif ($filtro_sub === 'convite_enviado') {
            $where .= " AND c.estado = 'convite_enviado'";
        } elseif ($filtro_sub === 'registados') {
            $where .= " AND c.estado = 'registado'";
        } elseif ($filtro_sub === 'ocorrencias') {
            $where .= " AND c.estado IN ('ausente', 'recusou', 'reprovado_reuniao')";
        }
    } elseif ($filtro_fase === 'rejeitados') {
        $where = "WHERE c.id_processo = $id_processo_sel AND c.estado = 'rejeitado'";
    } else {
        $where = "WHERE c.id_processo = $id_processo_sel";
    }

    $res = $mysqli->query("
        SELECT c.*, u.nome as avaliador_nome,
               conv.id as conv_id, conv.data_reuniao, conv.hora_reuniao, conv.local_reuniao,
               conv.documentos_a_levar, conv.observacoes as conv_obs, conv.estado_convocatoria,
               conv.convocado_em, conv.presenca_validada_em, conv.validado_por
        FROM candidaturas c
        LEFT JOIN usuarios u ON u.id = c.avaliado_por
        LEFT JOIN convocatorias conv ON conv.id = (
            SELECT id FROM convocatorias WHERE candidatura_id = c.id ORDER BY id DESC LIMIT 1
        )
        $where
        ORDER BY c.criado_em DESC
    ");
    if ($res) while ($r = $res->fetch_assoc()) $candidaturas[] = $r;
}

// Contagens operacionais unificadas numa única consulta ultra-rápida
$contFases = [
    'rastreio_pitch' => 0, 
    'selecao_admissao' => 0, 
    'admitidos' => 0, 
    'rejeitados' => 0,
    'sub_selecionado' => 0,
    'sub_convocado' => 0,
    'sub_validado' => 0,
    'sub_convite_enviado' => 0,
    'sub_registado' => 0,
    'sub_ocorrencias' => 0
];
if ($id_processo_sel) {
    $resStats = $mysqli->query("
        SELECT 
            SUM(CASE WHEN (estado = 'pendente' OR (estado = 'em_analise' AND (pitch_nota_final IS NULL OR pitch_nota_final = 0))) THEN 1 ELSE 0 END) as rastreio_pitch,
            SUM(CASE WHEN estado = 'em_analise' AND pitch_nota_final > 0 THEN 1 ELSE 0 END) as selecao_admissao,
            SUM(CASE WHEN estado IN ('selecionado', 'convocado', 'validado_presencial', 'convite_enviado', 'registado', 'ausente', 'recusou', 'reprovado_reuniao') THEN 1 ELSE 0 END) as admitidos,
            SUM(CASE WHEN estado = 'rejeitado' THEN 1 ELSE 0 END) as rejeitados,
            SUM(CASE WHEN estado = 'selecionado' THEN 1 ELSE 0 END) as sub_selecionado,
            SUM(CASE WHEN estado = 'convocado' THEN 1 ELSE 0 END) as sub_convocado,
            SUM(CASE WHEN estado = 'validado_presencial' THEN 1 ELSE 0 END) as sub_validado,
            SUM(CASE WHEN estado = 'convite_enviado' THEN 1 ELSE 0 END) as sub_convite_enviado,
            SUM(CASE WHEN estado = 'registado' THEN 1 ELSE 0 END) as sub_registado,
            SUM(CASE WHEN estado IN ('ausente', 'recusou', 'reprovado_reuniao') THEN 1 ELSE 0 END) as sub_ocorrencias
        FROM candidaturas 
        WHERE id_processo = $id_processo_sel
    ");
    if ($resStats && $st = $resStats->fetch_assoc()) {
        $contFases['rastreio_pitch'] = (int)($st['rastreio_pitch'] ?? 0);
        $contFases['selecao_admissao'] = (int)($st['selecao_admissao'] ?? 0);
        $contFases['admitidos'] = (int)($st['admitidos'] ?? 0);
        $contFases['rejeitados'] = (int)($st['rejeitados'] ?? 0);
        $contFases['sub_selecionado'] = (int)($st['sub_selecionado'] ?? 0);
        $contFases['sub_convocado'] = (int)($st['sub_convocado'] ?? 0);
        $contFases['sub_validado'] = (int)($st['sub_validado'] ?? 0);
        $contFases['sub_convite_enviado'] = (int)($st['sub_convite_enviado'] ?? 0);
        $contFases['sub_registado'] = (int)($st['sub_registado'] ?? 0);
        $contFases['sub_ocorrencias'] = (int)($st['sub_ocorrencias'] ?? 0);
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

        <?php if ($filtro_fase === 'admitidos'): ?>
        <!-- SUB-SEPARADORES OPERACIONAIS DO FUNIL DE ADMISSÃO -->
        <div class="px-3 pt-2.5 pb-2.5 bg-white border-bottom d-flex align-items-center justify-content-between gap-2 flex-wrap" style="font-size: 0.8rem;">
            <div class="d-flex align-items-center gap-1.5 flex-wrap">
                <span class="text-muted small fw-bold text-uppercase me-1" style="font-size:0.7rem;"><i class="fa fa-stream me-1"></i>Etapas:</span>
                <a href="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=todos" class="btn btn-sm <?= ($filtro_sub==='todos')?'btn-dark':'btn-outline-secondary' ?> py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                    Todos (<?= $contFases['admitidos'] ?>)
                </a>
                <a href="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=selecionados" class="btn btn-sm <?= ($filtro_sub==='selecionados')?'btn-warning text-dark fw-bold':'btn-outline-secondary' ?> py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa fa-circle-check me-1 text-success"></i>1. Selecionados (<?= $contFases['sub_selecionado'] ?>)
                </a>
                <a href="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=convocados" class="btn btn-sm <?= ($filtro_sub==='convocados')?'btn-info text-dark fw-bold':'btn-outline-secondary' ?> py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa fa-calendar-days me-1 text-primary"></i>2. Convocados (<?= $contFases['sub_convocado'] ?>)
                </a>
                <a href="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=validados" class="btn btn-sm <?= ($filtro_sub==='validados')?'btn-success text-white fw-bold':'btn-outline-secondary' ?> py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa fa-user-check me-1 text-white"></i>3. Validados Presencial (<?= $contFases['sub_validado'] ?>)
                </a>
                <a href="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=convite_enviado" class="btn btn-sm <?= ($filtro_sub==='convite_enviado')?'btn-primary text-white fw-bold':'btn-outline-secondary' ?> py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa fa-paper-plane me-1"></i>4. Convite 48h Enviado (<?= $contFases['sub_convite_enviado'] ?>)
                </a>
                <a href="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=registados" class="btn btn-sm <?= ($filtro_sub==='registados')?'btn-dark text-white fw-bold':'btn-outline-secondary' ?> py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa fa-id-badge me-1 text-success"></i>5. Registados (<?= $contFases['sub_registado'] ?>)
                </a>
                <a href="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=ocorrencias" class="btn btn-sm <?= ($filtro_sub==='ocorrencias')?'btn-danger text-white fw-bold':'btn-outline-secondary' ?> py-1 px-2.5 rounded-pill" style="font-size:0.75rem;">
                    <i class="fa fa-triangle-exclamation me-1"></i>Ocorrências (<?= $contFases['sub_ocorrencias'] ?>)
                </a>
            </div>

            <!-- BOTÕES DE AÇÃO COLETIVA BIFURCADOS -->
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-primary rounded-3 fw-bold py-1 px-2.5" onclick="iniciarWizardEnvio('convocatoria')" style="font-size:0.75rem;">
                    <i class="fa fa-calendar-check me-1"></i>Assistente de Convocatórias
                </button>
                <button class="btn btn-sm btn-outline-secondary rounded-3 fw-bold py-1 px-2.5" onclick="abrirModalMassa('convocatoria')" style="font-size:0.75rem;">
                    <i class="fa fa-copy me-1"></i>Convocatórias em Massa
                </button>

                <?php if ($_SESSION['usuario_perfil'] === 'superadmin'): ?>
                <button class="btn btn-sm btn-success text-white rounded-3 fw-bold py-1 px-2.5" onclick="iniciarWizardEnvio('convite')" style="font-size:0.75rem;">
                    <i class="fa fa-paper-plane me-1"></i>Assistente Convites 48h (Validados)
                </button>
                <button class="btn btn-sm btn-outline-success rounded-3 fw-bold py-1 px-2.5" onclick="abrirModalMassa('convite')" style="font-size:0.75rem;">
                    <i class="fa fa-bolt me-1"></i>Convites 48h em Massa
                </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($candidaturas)): ?>
            <div class="text-center p-5 bg-white">
                <i class="fa fa-inbox fa-3x text-muted opacity-25 mb-3"></i>
                <h6 class="fw-bold">Sem candidaturas registadas</h6>
                <p class="text-muted small">Não foram encontradas candidaturas nesta etapa com os filtros selecionados.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="tabelaCandidaturas">
                    <thead class="table-light small text-uppercase fw-bold text-secondary border-bottom">
                        <tr>
                            <th style="padding: 14px 20px;">Candidato & Ideia</th>
                            <th style="padding: 14px 15px;">Contacto & Perfil</th>
                            <th style="padding: 14px 15px;">Entidade / Projeto</th>
                            <th style="padding: 14px 15px;">Estado / Agendamento</th>
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
                                'pendente'            => ['bg'=>'#F1F5F9', 'color'=>'#475569', 'label'=>'Pendente'],
                                'em_analise'          => ['bg'=>'#FEF3C7', 'color'=>'#92400E', 'label'=>'Em Análise'],
                                'selecionado'         => ['bg'=>'#FEF3C7', 'color'=>'#B45309', 'label'=>'1. Selecionado (Aguardando Convocatória)'],
                                'convocado'           => ['bg'=>'#E0F2FE', 'color'=>'#0369A1', 'label'=>'2. Convocado (Reunião Agendada)'],
                                'validado_presencial' => ['bg'=>'#DCFCE7', 'color'=>'#166534', 'label'=>'3. Presença Validada (Apto p/ Convite)'],
                                'convite_enviado'     => ['bg'=>'#DBEAFE', 'color'=>'#1E40AF', 'label'=>'4. Convite 48h Enviado'],
                                'registado'           => ['bg'=>'#F5F3FF', 'color'=>'#5B21B6', 'label'=>'5. Conta Registada'],
                                'ausente'             => ['bg'=>'#FEE2E2', 'color'=>'#991B1B', 'label'=>'Ausente / Falta'],
                                'recusou'             => ['bg'=>'#F3F4F6', 'color'=>'#4B5563', 'label'=>'Recusou Vaga'],
                                'reprovado_reuniao'   => ['bg'=>'#FEE2E2', 'color'=>'#991B1B', 'label'=>'Reprovado na Reunião'],
                                'rejeitado'           => ['bg'=>'#FEE2E2', 'color'=>'#991B1B', 'label'=>'Rejeitado']
                            ][$st] ?? ['bg'=>'#eee', 'color'=>'#333', 'label'=>$st];

                            $tiposProjMap = [
                                'startup_tecnologica' => '🚀 Startup Tech',
                                'negocio_tradicional' => '🏢 Negócio / Empresa',
                                'individual'          => '👤 Individual',
                                'equipa'              => '👥 Equipa / Grupo',
                                'impacto_social'      => '🌍 Impacto Social'
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
                                        <?= $stBadge['label'] ?>
                                    </span>
                                    <?php if (!empty($c['data_reuniao'])): ?>
                                        <div class="small text-muted mt-1" style="font-size:0.72rem;">
                                            <i class="fa fa-calendar me-1 text-primary"></i><?= date('d/m/Y', strtotime($c['data_reuniao'])) ?> às <?= substr($c['hora_reuniao'],0,5) ?>
                                        </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="status-badge-v2" style="background:<?= $stBadge['bg'] ?>; color:<?= $stBadge['color'] ?>">
                                        <?= $stBadge['label'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 14px 20px;" class="text-end">
                                <div class="d-flex align-items-center justify-content-end gap-1 flex-wrap">
                                    <button class="btn-action-v2" title="Dossier Completo" onclick='abrirModal(<?= json_encode($c) ?>)'><i class="fa fa-id-card"></i></button>
                                    
                                    <?php if ($filtro_fase === 'rastreio_pitch'): ?>
                                        <button class="btn-action-v2 btn-warning" title="Avaliar Pitch" onclick='abrirModalAvaliar(<?= json_encode($c) ?>)'><i class="fa fa-star"></i></button>
                                    <?php endif; ?>

                                    <?php if ($filtro_fase === 'selecao_admissao'): ?>
                                        <?php if ($_SESSION['usuario_perfil'] === 'superadmin'): ?>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="selecionado">
                                            <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
                                            <button type="submit" class="btn-action-v2 btn-success" title="Aprovar Seleção (Avançar p/ Convocatória)"><i class="fa fa-check"></i></button>
                                        </form>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="rejeitado">
                                            <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>">
                                            <button type="submit" class="btn-action-v2 btn-danger" title="Rejeitar Candidatura" onclick="return confirm('Tem a certeza que deseja rejeitar esta candidatura?')"><i class="fa fa-ban"></i></button>
                                        </form>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary small px-2 py-1 rounded-2" style="font-size:0.65rem;" title="Apenas Super Admin (DG/PR) pode aprovar a admissão"><i class="fa fa-user-shield me-1"></i>Aguardando DG/PR</span>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($filtro_fase === 'admitidos'): ?>
                                        <!-- ESTADO 1: SELECIONADO -->
                                        <?php if ($st === 'selecionado'): ?>
                                            <button class="btn-action-v2" style="color:#D97706; border-color:#FDE68A; background:#FFFBEB" title="Convocar para Reunião Presencial" onclick='abrirModalConvocar(<?= json_encode($c) ?>)'><i class="fa fa-calendar-check"></i></button>
                                            <button class="btn-action-v2 text-secondary" title="Marcar Recusa" onclick='abrirModalOcorrencia(<?= json_encode($c) ?>, "recusa")'><i class="fa fa-user-xmark"></i></button>
                                        
                                        <!-- ESTADO 2: CONVOCADO -->
                                        <?php elseif ($st === 'convocado'): ?>
                                            <button class="btn-action-v2" style="color:#25D366; border-color:#86EFAC; background:#F0FDF4" title="Abrir Convocatória no WhatsApp (sem link)" onclick='abrirWaConvocatoria(<?= json_encode($c) ?>)'><i class="fa-brands fa-whatsapp"></i></button>
                                            <button class="btn-action-v2 text-primary" style="border-color:#BFDBFE; background:#EFF6FF" title="Reagendar Reunião" onclick='abrirModalReagendar(<?= json_encode($c) ?>)'><i class="fa fa-clock-rotate-left"></i></button>
                                            
                                            <?php if ($_SESSION['usuario_perfil'] === 'superadmin'): ?>
                                                <button class="btn-action-v2" style="color:#166534; border-color:#86EFAC; background:#DCFCE7;" title="Validar Presença Presencial (DG/PR)" onclick='abrirModalValidarPresenca(<?= json_encode($c) ?>)'><i class="fa fa-user-check"></i></button>
                                                <button class="btn-action-v2 text-danger" style="border-color:#FECACA; background:#FEF2F2;" title="Reprovar na Reunião (DG/PR)" onclick='abrirModalOcorrencia(<?= json_encode($c) ?>, "reprovar")'><i class="fa fa-thumbs-down"></i></button>
                                            <?php endif; ?>
                                            <button class="btn-action-v2 text-muted" title="Marcar Ausência (Falta)" onclick='abrirModalOcorrencia(<?= json_encode($c) ?>, "ausencia")'><i class="fa fa-user-slash"></i></button>
                                        
                                        <!-- ESTADO 3: VALIDADO PRESENCIAL -->
                                        <?php elseif ($st === 'validado_presencial'): ?>
                                            <?php if ($_SESSION['usuario_perfil'] === 'superadmin'): ?>
                                                <button class="btn-action-v2" style="color:#16A34A; border-color:#86EFAC; background:#DCFCE7; font-weight:bold;" title="Emitir Convite Oficial de Registo (Link 48h)" onclick='abrirModalEmitirConvite(<?= json_encode($c) ?>)'><i class="fa fa-paper-plane"></i></button>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary small px-1.5 py-1 rounded" style="font-size:0.65rem;" title="Apenas o Super Admin pode emitir o link de registo"><i class="fa fa-user-shield me-1"></i>Aguardando DG/PR</span>
                                            <?php endif; ?>

                                        <!-- ESTADO 4: CONVITE ENVIADO -->
                                        <?php elseif ($st === 'convite_enviado'): ?>
                                            <button class="btn-action-v2 text-success" title="Reabrir WhatsApp com Convite" onclick='abrirWaConviteEnviado(<?= json_encode($c) ?>)'><i class="fa-brands fa-whatsapp"></i></button>
                                            <?php if ($_SESSION['usuario_perfil'] === 'superadmin'): ?>
                                                <button class="btn-action-v2 text-primary" title="Reenviar / Regenerar Convite 48h" onclick='reenviarConvite(<?= json_encode($c) ?>)'><i class="fa fa-rotate-right"></i></button>
                                            <?php endif; ?>

                                        <!-- ESTADO 5: AUSENTE -->
                                        <?php elseif ($st === 'ausente'): ?>
                                            <button class="btn-action-v2 text-warning-dark" style="border-color:#FDE68A; background:#FFFBEB" title="Reconvocar Candidato em Falta" onclick='abrirModalReconvocar(<?= json_encode($c) ?>)'><i class="fa fa-calendar-plus"></i></button>
                                        <?php endif; ?>

                                        <?php if ($st !== 'registado' && $_SESSION['usuario_perfil'] === 'superadmin'): ?>
                                        <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" style="margin:0;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="mudar_estado_cand"><input type="hidden" name="id_cand" value="<?= $c['id'] ?>"><input type="hidden" name="estado" value="rejeitado">
                                            <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=<?= $filtro_fase ?>&sub=<?= $filtro_sub ?>">
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

<!-- MODAL: CONVOCAR CANDIDATO -->
<div class="modal-overlay" id="modalConvocar">
    <div class="modal-box-v2" style="max-width: 580px;">
        <div class="modal-header-v2" style="background: linear-gradient(135deg, #1E293B 0%, #D97706 100%);">
            <h6 class="mb-0 fw-bold text-white"><i class="fa fa-calendar-check me-2"></i> Convocatória para Reunião Presencial</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalConvocar')"></button>
        </div>
        <div class="modal-body-v2">
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" id="formConvocar">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="convocar_candidato">
                <input type="hidden" name="id_cand" id="convocarIdCand">
                <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=convocados">

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="label-mini text-primary">Candidato Selecionado</div>
                    <div class="fw-bold fs-6" id="convocarNome"></div>
                    <div class="small text-muted" id="convocarEmailTel"></div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Data da Reunião *</label>
                        <input type="date" name="data_reuniao" class="form-control-custom w-100" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+2 days')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Hora da Reunião *</label>
                        <input type="time" name="hora_reuniao" class="form-control-custom w-100" value="10:00" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Local da Reunião *</label>
                    <input type="text" name="local_reuniao" class="form-control-custom w-100" value="Gabinete da Incubadora Académica - ISPSN" required>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Documentos a Levar</label>
                    <textarea name="documentos_a_levar" class="form-control-custom w-100" rows="2">Bilhete de Identidade, Cartão de Estudante e Dossier preliminar do projeto</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Observações / Recomendações Adicionais</label>
                    <textarea name="observacoes" class="form-control-custom w-100" rows="2" placeholder="Ex: Trazer amostra física ou apresentação em formato pendrive..."></textarea>
                </div>

                <div class="alert alert-warning border-0 small py-2 mb-3 rounded-3" style="font-size:0.75rem;">
                    <i class="fa fa-info-circle me-1"></i> <strong>Nota:</strong> Esta ação <strong>NÃO</strong> gera link de registo de conta. O candidato receberá a mensagem oficial de convocatória para agendamento.
                </div>

                <button type="submit" class="btn btn-warning w-100 py-3 fw-bold rounded-3 text-white shadow-sm" style="background:#D97706; border:none;">
                    <i class="fa fa-calendar-check me-2"></i> Gravar Convocatória e Abrir WhatsApp
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: REAGENDAR CONVOCATÓRIA -->
<div class="modal-overlay" id="modalReagendar">
    <div class="modal-box-v2" style="max-width: 580px;">
        <div class="modal-header-v2" style="background: linear-gradient(135deg, #1E293B 0%, #2563EB 100%);">
            <h6 class="mb-0 fw-bold text-white"><i class="fa fa-clock-rotate-left me-2"></i> Reagendar Reunião Presencial</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalReagendar')"></button>
        </div>
        <div class="modal-body-v2">
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" id="formReagendar">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reagendar_convocatoria">
                <input type="hidden" name="id_cand" id="reagendarIdCand">
                <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=convocados">

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="label-mini text-primary">Candidato Convocado</div>
                    <div class="fw-bold fs-6" id="reagendarNome"></div>
                    <div class="small text-muted" id="reagendarDadosAtuais"></div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Nova Data da Reunião *</label>
                        <input type="date" name="data_reuniao" id="reagendarData" class="form-control-custom w-100" min="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Nova Hora *</label>
                        <input type="time" name="hora_reuniao" id="reagendarHora" class="form-control-custom w-100" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Local da Reunião *</label>
                    <input type="text" name="local_reuniao" id="reagendarLocal" class="form-control-custom w-100" required>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Documentos a Levar</label>
                    <textarea name="documentos_a_levar" id="reagendarDocs" class="form-control-custom w-100" rows="2"></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Motivo do Reagendamento / Observações</label>
                    <textarea name="observacoes" id="reagendarObs" class="form-control-custom w-100" rows="2" placeholder="Ex: Remarcado a pedido do candidato por motivos académicos..."></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 py-3 fw-bold rounded-3 text-white shadow-sm" style="background:#2563EB; border:none;">
                    <i class="fa fa-clock-rotate-left me-2"></i> Reagendar Reunião e Notificar
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: RECONVOCAR CANDIDATO AUSENTE -->
<div class="modal-overlay" id="modalReconvocar">
    <div class="modal-box-v2" style="max-width: 580px;">
        <div class="modal-header-v2" style="background: linear-gradient(135deg, #1E293B 0%, #D97706 100%);">
            <h6 class="mb-0 fw-bold text-white"><i class="fa fa-calendar-plus me-2"></i> Reconvocar Candidato em Falta</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalReconvocar')"></button>
        </div>
        <div class="modal-body-v2">
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" id="formReconvocar">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reconvocar_candidato">
                <input type="hidden" name="id_cand" id="reconvocarIdCand">
                <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=convocados">

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="label-mini text-danger">Candidato Anteriormente Ausente</div>
                    <div class="fw-bold fs-6" id="reconvocarNome"></div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Nova Data *</label>
                        <input type="date" name="data_reuniao" class="form-control-custom w-100" min="<?= date('Y-m-d') ?>" value="<?= date('Y-m-d', strtotime('+3 days')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Nova Hora *</label>
                        <input type="time" name="hora_reuniao" class="form-control-custom w-100" value="11:00" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Local *</label>
                    <input type="text" name="local_reuniao" class="form-control-custom w-100" value="Gabinete da Incubadora Académica - ISPSN" required>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Documentos</label>
                    <textarea name="documentos_a_levar" class="form-control-custom w-100" rows="2">Bilhete de Identidade, Cartão de Estudante e Justificativo de ausência prévia</textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Observações da Reconvocatória</label>
                    <textarea name="observacoes" class="form-control-custom w-100" rows="2" placeholder="Ex: Segunda chamada concedida pela Coordenação..."></textarea>
                </div>

                <button type="submit" class="btn btn-warning w-100 py-3 fw-bold rounded-3 text-white shadow-sm" style="background:#D97706; border:none;">
                    <i class="fa fa-calendar-plus me-2"></i> Confirmar Reconvocatória
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: VALIDAR PRESENÇA PRESENCIAL (DG/PR) -->
<div class="modal-overlay" id="modalValidarPresenca">
    <div class="modal-box-v2" style="max-width: 520px;">
        <div class="modal-header-v2" style="background: linear-gradient(135deg, #064E3B 0%, #16A34A 100%);">
            <h6 class="mb-0 fw-bold text-white"><i class="fa fa-user-check me-2"></i> Validação Presencial (Decisão DG/PR)</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalValidarPresenca')"></button>
        </div>
        <div class="modal-body-v2">
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="validar_presencial_cand">
                <input type="hidden" name="id_cand" id="validarIdCand">
                <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=validados">

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="label-mini text-success">Candidato Convocado</div>
                    <div class="fw-bold fs-6" id="validarNome"></div>
                    <div class="small text-muted" id="validarIdeia"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" style="font-size:0.8rem;">Parecer da Direção / Observações da Entrevista</label>
                    <textarea name="observacoes" class="form-control-custom w-100" rows="3" required>Presença e documentação conferidas presencialmente com a Direção da Incubadora. Parecer favorável à incubação.</textarea>
                </div>

                <div class="form-check p-3 bg-white border rounded-3 mb-3">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="chkConfirmaPresenca" required checked>
                    <label class="form-check-label small fw-bold text-secondary" for="chkConfirmaPresenca" style="font-size:0.8rem;">
                        Confirmo que o candidato compareceu à reunião presencial e que o projeto foi validado para receber convite de acesso.
                    </label>
                </div>

                <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-3 text-white shadow-sm" style="background:#16A34A; border:none;">
                    <i class="fa fa-check-double me-2"></i> Validar Presença e Habilitar Envio de Convite
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: EMITIR CONVITE OFICIAL DE REGISTO (48H) -->
<div class="modal-overlay" id="modalEmitirConvite">
    <div class="modal-box-v2" style="max-width: 520px;">
        <div class="modal-header-v2" style="background: linear-gradient(135deg, #0F172A 0%, #16A34A 100%);">
            <h6 class="mb-0 fw-bold text-white"><i class="fa fa-paper-plane me-2"></i> Emitir Convite Oficial de Registo (48h)</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalEmitirConvite')"></button>
        </div>
        <div class="modal-body-v2">
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="enviar_convite_registo">
                <input type="hidden" name="id_cand" id="emitirIdCand">
                <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=convite_enviado">

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="label-mini text-success"><i class="fa fa-circle-check me-1"></i>Presença Validada Presencialmente</div>
                    <div class="fw-bold fs-6" id="emitirNome"></div>
                    <div class="small text-muted" id="emitirEmailTel"></div>
                </div>

                <div class="p-3 bg-light border border-dashed rounded-3 small mb-3 text-secondary">
                    <div class="fw-bold text-dark mb-1"><i class="fa fa-shield-halved me-1 text-primary"></i>Garantias de Segurança:</div>
                    • Gera um token criptográfico de uso único exclusivo para este candidato.<br>
                    • Validade estrita de <strong>48 horas</strong> a partir deste instante.<br>
                    • O registo ativará a conta e criará o projeto incubado automaticamente no portal.
                </div>

                <button type="submit" class="btn btn-success w-100 py-3 fw-bold rounded-3 text-white shadow-sm" style="background:#16A34A; border:none;">
                    <i class="fa fa-paper-plane me-2"></i> Gerar Token de 48h e Abrir WhatsApp
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: REGISTO DE OCORRÊNCIA (AUSÊNCIA / RECUSA / REPROVAÇÃO) -->
<div class="modal-overlay" id="modalOcorrencia">
    <div class="modal-box-v2" style="max-width: 500px;">
        <div class="modal-header-v2" id="modalOcorrenciaHeader" style="background:#DC2626;">
            <h6 class="mb-0 fw-bold text-white" id="modalOcorrenciaTitulo"><i class="fa fa-triangle-exclamation me-2"></i> Registo de Ocorrência</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalOcorrencia')"></button>
        </div>
        <div class="modal-body-v2">
            <form method="post" action="/incubadora_ispsn/app/controllers/candidatura_action.php" id="formOcorrencia">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="ocorrenciaAction" value="marcar_ausencia">
                <input type="hidden" name="id_cand" id="ocorrenciaIdCand">
                <input type="hidden" name="redirect" value="?processo=<?= $id_processo_sel ?>&fase=admitidos&sub=ocorrencias">

                <div class="p-3 bg-light rounded-3 mb-3 border">
                    <div class="label-mini text-secondary">Candidato</div>
                    <div class="fw-bold fs-6" id="ocorrenciaNome"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label-custom fw-bold mb-1" id="ocorrenciaMotivoLabel" style="font-size:0.8rem;">Justificativo / Motivo *</label>
                    <textarea name="motivo" id="ocorrenciaMotivo" class="form-control-custom w-100" rows="3" required placeholder="Descreva os detalhes da ocorrência..."></textarea>
                </div>

                <button type="submit" class="btn btn-danger w-100 py-3 fw-bold rounded-3 text-white shadow-sm" id="ocorrenciaBtnSubmit">
                    Confirmar Registo
                </button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL: Assistente de Envio Wizard (BIFURCADO) -->
<div class="modal-overlay" id="modalWizard">
    <div class="modal-box-v2" style="max-width: 600px;">
        <div class="modal-header-v2" id="wizardHeader" style="background: #25D366;">
            <h6 class="mb-0 fw-bold text-white" id="wizardTitulo"><i class="fa-brands fa-whatsapp me-2"></i> Assistente de Envio</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalWizard')"></button>
        </div>
        <div class="modal-body-v2">
            <div id="wizardContent"></div>
        </div>
        <div class="modal-footer-v2">
            <span class="text-muted small me-auto" id="wizardProgress">Progresso: 0 / 0</span>
            <button class="btn btn-light fw-bold px-3 py-2 rounded-3" onclick="fecharModal('modalWizard')">Fechar</button>
            <button class="btn btn-success fw-bold text-white px-3 py-2 rounded-3" id="btnWizardNext" style="display:none;" onclick="wizardNext()">Avançar para o Próximo <i class="fa fa-arrow-right ms-1"></i></button>
        </div>
    </div>
</div>

<!-- MODAL: Exportação / Disparo em Massa (BIFURCADO) -->
<div class="modal-overlay" id="modalMassa">
    <div class="modal-box-v2" style="max-width: 650px;">
        <div class="modal-header-v2" style="background: var(--slate-900);">
            <h6 class="mb-0 fw-bold text-white" id="massaModalTitulo"><i class="fa fa-copy me-2"></i> Exportação em Massa</h6>
            <button class="btn-close btn-close-white" onclick="fecharModal('modalMassa')"></button>
        </div>
        <div class="modal-body-v2">
            <p class="text-muted small" id="massaDescricao">Selecione os candidatos na lista abaixo para gerar o lote de mensagens.</p>
            
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="chkSelecionarTodosMassa" checked onchange="toggleSelectMassa(this)">
                <label class="form-check-label fw-bold small text-uppercase" for="chkSelecionarTodosMassa">Selecionar Todos</label>
            </div>
            
            <div id="massaList" style="max-height: 180px; overflow-y: auto; margin-bottom: 20px; border: 1px solid var(--slate-200); border-radius: 12px; padding: 12px;"></div>
            
            <div class="mb-3" style="display:none;" id="massaTextAreaWrapper">
                <label class="label-mini" id="massaTextAreaLabel">Lista Formatada de Mensagens</label>
                <textarea class="form-control-custom w-100" id="massaTextArea" rows="6" readonly style="font-family: monospace; font-size: 0.8rem; background: var(--slate-50); outline: none; border: 1px solid var(--slate-200); padding: 10px; border-radius: 8px;"></textarea>
            </div>
            
            <button class="btn btn-warning fw-bold w-100 py-3 rounded-3 text-white" id="btnMassaProcessar" style="background:#D97706; border:none;" onclick="processarMassa()">
                <i class="fa fa-bolt me-1"></i> Processar Lote Selecionado
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
                    <?= csrf_field() ?>
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
const csrfToken = <?= json_encode(csrf_token()) ?>;
const candidaturasListaGlobal = <?= json_encode($candidaturas) ?>;

function fecharModal(id) { 
    const el = document.getElementById(id);
    if (el) el.classList.remove('open'); 
}

document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { 
    if (e.target === m) m.classList.remove('open'); 
}));

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
                    </div>

                    ${c.data_reuniao ? `
                        <div class="mt-3 p-3 bg-white rounded-3 border border-primary">
                            <strong class="small text-primary text-uppercase d-block mb-1"><i class="fa fa-calendar-check me-1"></i> Agendamento Presencial:</strong>
                            <div class="small text-secondary">
                                <strong>Data:</strong> ${c.data_reuniao} às ${c.hora_reuniao}<br>
                                <strong>Local:</strong> ${c.local_reuniao || 'Incubadora ISPSN'}<br>
                                <strong>Documentos:</strong> ${c.documentos_a_levar || 'Padrão'}
                            </div>
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
    }
    document.getElementById('modalFooter').innerHTML = footer + `<button class="btn btn-light fw-bold px-4 py-2 rounded-3" onclick="fecharModal('modalDetalhe')">Fechar</button>`;
    document.getElementById('modalDetalhe').classList.add('open');
}

function abrirModalAvaliar(c) {
    document.getElementById('avaliarIdCand').value = c.id;
    document.getElementById('avaliarCandidatoNome').textContent = `${c.nome} (${c.email})`;
    document.getElementById('avaliarTituloIdeia').textContent = c.titulo_ideia || 'Sem Título';
    document.getElementById('avaliarArea').textContent = c.area_tematica || 'Geral';
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
}

// ── FUNÇÕES DE MODAIS DE TRANSIÇÃO ESPECÍFICA ──
function abrirModalConvocar(c) {
    document.getElementById('convocarIdCand').value = c.id;
    document.getElementById('convocarNome').textContent = c.nome;
    document.getElementById('convocarEmailTel').textContent = `${c.email} · Tel: ${c.telefone}`;
    document.getElementById('modalConvocar').classList.add('open');
}

function abrirModalReagendar(c) {
    document.getElementById('reagendarIdCand').value = c.id;
    document.getElementById('reagendarNome').textContent = c.nome;
    document.getElementById('reagendarDadosAtuais').textContent = `Agendamento anterior: ${c.data_reuniao || '—'} às ${c.hora_reuniao || '—'} (${c.local_reuniao || 'Incubadora'})`;
    document.getElementById('reagendarData').value = c.data_reuniao || '<?= date('Y-m-d', strtotime('+2 days')) ?>';
    document.getElementById('reagendarHora').value = c.hora_reuniao ? c.hora_reuniao.substring(0,5) : '10:00';
    document.getElementById('reagendarLocal').value = c.local_reuniao || 'Gabinete da Incubadora Académica - ISPSN';
    document.getElementById('reagendarDocs').value = c.documentos_a_levar || 'Bilhete de Identidade, Cartão de Estudante e Dossier preliminar do projeto';
    document.getElementById('reagendarObs').value = c.conv_obs || '';
    document.getElementById('modalReagendar').classList.add('open');
}

function abrirModalReconvocar(c) {
    document.getElementById('reconvocarIdCand').value = c.id;
    document.getElementById('reconvocarNome').textContent = `${c.nome} (${c.email})`;
    document.getElementById('modalReconvocar').classList.add('open');
}

function abrirModalValidarPresenca(c) {
    document.getElementById('validarIdCand').value = c.id;
    document.getElementById('validarNome').textContent = c.nome;
    document.getElementById('validarIdeia').textContent = `Projeto: ${c.titulo_ideia} · Tel: ${c.telefone}`;
    document.getElementById('modalValidarPresenca').classList.add('open');
}

function abrirModalEmitirConvite(c) {
    document.getElementById('emitirIdCand').value = c.id;
    document.getElementById('emitirNome').textContent = c.nome;
    document.getElementById('emitirEmailTel').textContent = `${c.email} · Tel: ${c.telefone}`;
    document.getElementById('modalEmitirConvite').classList.add('open');
}

function abrirModalOcorrencia(c, tipo) {
    document.getElementById('ocorrenciaIdCand').value = c.id;
    document.getElementById('ocorrenciaNome').textContent = `${c.nome} (${c.titulo_ideia})`;
    const header = document.getElementById('modalOcorrenciaHeader');
    const titulo = document.getElementById('modalOcorrenciaTitulo');
    const actionInput = document.getElementById('ocorrenciaAction');
    const motivoLabel = document.getElementById('ocorrenciaMotivoLabel');
    const motivoText = document.getElementById('ocorrenciaMotivo');
    const btnSubmit = document.getElementById('ocorrenciaBtnSubmit');

    if (tipo === 'ausencia') {
        header.style.background = '#DC2626';
        titulo.innerHTML = '<i class="fa fa-user-slash me-2"></i> Marcar Falta / Ausência';
        actionInput.value = 'marcar_ausencia';
        motivoLabel.textContent = 'Motivo da Falta à Reunião *';
        motivoText.value = 'Não compareceu à reunião presencial agendada e não apresentou justificação prévia.';
        btnSubmit.textContent = 'Confirmar Ausência';
    } else if (tipo === 'recusa') {
        header.style.background = '#4B5563';
        titulo.innerHTML = '<i class="fa fa-user-xmark me-2"></i> Marcar Desistência / Recusa';
        actionInput.value = 'marcar_recusa';
        motivoLabel.textContent = 'Motivo da Recusa / Desistência *';
        motivoText.value = 'Candidato informou que não possui disponibilidade para prosseguir com a incubação.';
        btnSubmit.textContent = 'Confirmar Recusa';
    } else if (tipo === 'reprovar') {
        header.style.background = '#991B1B';
        titulo.innerHTML = '<i class="fa fa-thumbs-down me-2"></i> Reprovar Candidato na Reunião (DG/PR)';
        actionInput.value = 'reprovar_reuniao';
        motivoLabel.textContent = 'Parecer de Reprovação na Entrevista *';
        motivoText.value = 'Após alinhamento presencial com a Direção, o projeto não preenche os requisitos essenciais para incubação.';
        btnSubmit.textContent = 'Confirmar Reprovação na Reunião';
    }
    document.getElementById('modalOcorrencia').classList.add('open');
}

// ── DISPARO DE MENSAGENS AVULSAS WHATSAPP ──
function abrirWaConvocatoria(c) {
    const primeiroNome = (c.nome || '').split(' ')[0];
    const dataFmt = c.data_reuniao ? c.data_reuniao.split('-').reverse().join('/') : 'A definir';
    const horaFmt = c.hora_reuniao ? c.hora_reuniao.substring(0,5) : '10:00';
    const local = c.local_reuniao || 'Gabinete da Incubadora Académica - ISPSN';
    const docs = c.documentos_a_levar || 'Bilhete de Identidade, Cartão de Estudante e Dossier preliminar do projeto';

    let msg = `Olá, ${primeiroNome}! 🎉\n\n`;
    msg += `Temos o prazer de informar que a sua candidatura do projeto *"${c.titulo_ideia}"* foi *SELECIONADA* pela Comissão da *Incubadora Académica ISPSN*! 🚀\n\n`;
    msg += `Para formalizar a sua admissão, convocamo-lo(a) para uma *reunião presencial* com a Direção da Incubadora:\n\n`;
    msg += `📅 *Data:* ${dataFmt}\n`;
    msg += `⏰ *Hora:* ${horaFmt}\n`;
    msg += `📍 *Local:* ${local}\n`;
    msg += `📄 *Documentos a levar:* ${docs}\n\n`;
    msg += `Por favor, responda a esta mensagem para confirmar a sua presença.\n\n`;
    msg += `Atenciosamente,\n_Direção da Incubadora Académica ISPSN_`;

    let telClean = (c.telefone || '').replace(/\D/g, '');
    if (telClean.length === 9 && telClean.startsWith('9')) telClean = '244' + telClean;
    const waUrl = 'https://wa.me/' + telClean + '?text=' + encodeURIComponent(msg);
    window.open(waUrl, '_blank');
}

function abrirWaConviteEnviado(c) {
    if (!c.token_convite) {
        alert('Esta candidatura ainda não possui token de convite gerado.');
        return;
    }
    const primeiroNome = (c.nome || '').split(' ')[0];
    const link = `http://${window.location.host}/incubadora_ispsn/public/register.php?invite=${c.token_convite}`;
    let msg = `Olá, ${primeiroNome}! 🎉\n\n`;
    msg += `A sua admissão na *Incubadora Académica ISPSN* foi *CONCLUÍDA COM SUCESSO!* 🚀🌟\n\n`;
    msg += `Para criar a sua conta oficial no portal e ativar o seu projeto incubado, aceda ao link seguro exclusivo abaixo:\n\n`;
    msg += `🔗 ${link}\n\n`;
    msg += `⏰ *Atenção:* Este link é pessoal e intransferível, com validade de 48 horas.\n\n`;
    msg += `Atenciosamente,\n_Direção da Incubadora Académica ISPSN_`;

    let telClean = (c.telefone || '').replace(/\D/g, '');
    if (telClean.length === 9 && telClean.startsWith('9')) telClean = '244' + telClean;
    const waUrl = 'https://wa.me/' + telClean + '?text=' + encodeURIComponent(msg);
    window.open(waUrl, '_blank');
}

function reenviarConvite(c) {
    if (!confirm(`Deseja reemitir um novo token de 48h para ${c.nome}? O link anterior será invalidado.`)) return;

    const fd = new FormData();
    fd.append('action', 'reenviar_convite_registo');
    fd.append('id_cand', c.id);
    fd.append('_csrf_token', csrfToken);

    fetch('/incubadora_ispsn/app/controllers/candidatura_action.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso) {
            window.open(data.wa_url, '_blank');
            alert('Novo convite de 48h emitido e WhatsApp aberto com sucesso!');
            location.reload();
        } else {
            alert('Erro: ' + (data.erro || 'Falha na reemissão'));
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao processar pedido.');
    });
}

// ── ASSISTENTE WIZARD DINÂMICO (BIFURCADO) ──
let wizardModo = 'convocatoria';
let wizardQueue = [];
let wizardIndex = 0;

function iniciarWizardEnvio(modo) {
    wizardModo = modo;
    if (modo === 'convocatoria') {
        wizardQueue = candidaturasListaGlobal.filter(c => c.estado === 'selecionado');
        document.getElementById('wizardTitulo').innerHTML = '<i class="fa fa-calendar-check me-2"></i> Assistente de Convocatórias (Sem Link)';
        document.getElementById('wizardHeader').style.background = '#D97706';
    } else {
        wizardQueue = candidaturasListaGlobal.filter(c => c.estado === 'validado_presencial');
        document.getElementById('wizardTitulo').innerHTML = '<i class="fa fa-paper-plane me-2"></i> Assistente de Convites Oficiais (Link 48h)';
        document.getElementById('wizardHeader').style.background = '#16A34A';
    }

    if (wizardQueue.length === 0) {
        alert(modo === 'convocatoria' 
            ? 'Nenhum candidato aguardando convocatória (estado "selecionado") nesta lista.'
            : 'Nenhum candidato validado presencialmente aguardando envio de convite.');
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
                <p class="text-muted small">Todos os candidatos da fila foram processados com sucesso.</p>
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

    if (wizardModo === 'convocatoria') {
        document.getElementById('wizardContent').innerHTML = `
            <div class="p-3 bg-light rounded-4 border mb-3">
                <div class="label-mini text-warning">Candidato Selecionado (Aguardando Convocatória)</div>
                <h5 class="fw-bold mb-1">${c.nome}</h5>
                <div class="text-muted small">${c.email} · Tel: ${c.telefone}</div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="label-mini">Data da Reunião</label>
                    <input type="date" id="wizData" class="form-control-custom w-100" value="<?= date('Y-m-d', strtotime('+2 days')) ?>">
                </div>
                <div class="col-6">
                    <label class="label-mini">Hora</label>
                    <input type="time" id="wizHora" class="form-control-custom w-100" value="10:00">
                </div>
            </div>
            <div class="mb-3">
                <label class="label-mini">Local</label>
                <input type="text" id="wizLocal" class="form-control-custom w-100" value="Gabinete da Incubadora Académica - ISPSN">
            </div>
            <div class="text-center" id="wizardActionArea">
                <button class="btn btn-warning btn-lg w-100 py-3 fw-bold rounded-4 shadow-sm text-white" style="background:#D97706; border:none;" onclick="processarWizardConvocatoria(${c.id})">
                    <i class="fa fa-calendar-check me-2"></i> Gravar Convocatória e Abrir WhatsApp
                </button>
            </div>
        `;
    } else {
        document.getElementById('wizardContent').innerHTML = `
            <div class="p-3 bg-light rounded-4 border mb-3">
                <div class="label-mini text-success"><i class="fa fa-circle-check me-1"></i>Presença Validada Presencialmente</div>
                <h5 class="fw-bold mb-1">${c.nome}</h5>
                <div class="text-muted small">${c.email} · Tel: ${c.telefone}</div>
            </div>
            <div class="alert alert-success border-0 small py-2 mb-3 rounded-3" style="font-size:0.75rem;">
                <i class="fa fa-shield-halved me-1"></i> Esta ação gera um token exclusivo de 48 horas e atualiza o estado para "convite_enviado".
            </div>
            <div class="text-center" id="wizardActionArea">
                <button class="btn btn-success btn-lg w-100 py-3 fw-bold rounded-4 shadow-sm" style="background:#16A34A; border:none;" onclick="processarWizardConvite(${c.id})">
                    <i class="fa fa-paper-plane me-2"></i> Gerar Link 48h e Abrir WhatsApp
                </button>
            </div>
        `;
    }
}

function processarWizardConvocatoria(idCand) {
    const actionArea = document.getElementById('wizardActionArea');
    actionArea.innerHTML = `<button class="btn btn-warning btn-lg w-100 py-3 fw-bold rounded-4 shadow-sm text-white" disabled><i class="fa fa-spinner fa-spin me-2"></i> A gravar convocatória...</button>`;

    const fd = new FormData();
    fd.append('action', 'convocar_candidato');
    fd.append('id_cand', idCand);
    fd.append('data_reuniao', document.getElementById('wizData').value);
    fd.append('hora_reuniao', document.getElementById('wizHora').value);
    fd.append('local_reuniao', document.getElementById('wizLocal').value);
    fd.append('_csrf_token', csrfToken);

    fetch('/incubadora_ispsn/app/controllers/candidatura_action.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso) {
            window.open(data.wa_url, '_blank');
            actionArea.innerHTML = `<div class="alert alert-success border-0 small py-3 mb-0 rounded-4"><i class="fa fa-circle-check me-2"></i> Convocatória registada e WhatsApp aberto!</div>`;
            document.getElementById('btnWizardNext').style.display = 'inline-block';
        } else {
            alert('Erro: ' + data.erro);
            mostrarWizardStep();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao gravar convocatória.');
        mostrarWizardStep();
    });
}

function processarWizardConvite(idCand) {
    const actionArea = document.getElementById('wizardActionArea');
    actionArea.innerHTML = `<button class="btn btn-success btn-lg w-100 py-3 fw-bold rounded-4 shadow-sm" disabled><i class="fa fa-spinner fa-spin me-2"></i> A gerar link seguro 48h...</button>`;

    const fd = new FormData();
    fd.append('action', 'enviar_convite_registo');
    fd.append('id_cand', idCand);
    fd.append('_csrf_token', csrfToken);

    fetch('/incubadora_ispsn/app/controllers/candidatura_action.php', {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd
    })
    .then(r => r.json())
    .then(data => {
        if (data.sucesso) {
            window.open(data.wa_url, '_blank');
            actionArea.innerHTML = `<div class="alert alert-success border-0 small py-3 mb-0 rounded-4"><i class="fa fa-circle-check me-2"></i> Convite oficial 48h gerado e WhatsApp aberto!</div>`;
            document.getElementById('btnWizardNext').style.display = 'inline-block';
        } else {
            alert('Erro: ' + data.erro);
            mostrarWizardStep();
        }
    })
    .catch(err => {
        console.error(err);
        alert('Erro ao gerar convite.');
        mostrarWizardStep();
    });
}

function wizardNext() {
    wizardIndex++;
    mostrarWizardStep();
}

// ── EXPORTAÇÃO / DISPARO EM MASSA (BIFURCADO) ──
let massaModo = 'convocatoria';

function abrirModalMassa(modo) {
    massaModo = modo;
    const list = document.getElementById('massaList');
    const titulo = document.getElementById('massaModalTitulo');
    const desc = document.getElementById('massaDescricao');
    const btn = document.getElementById('btnMassaProcessar');

    let candidatos = [];
    if (modo === 'convocatoria') {
        candidatos = candidaturasListaGlobal.filter(c => c.estado === 'selecionado');
        titulo.innerHTML = '<i class="fa fa-calendar-check me-2"></i> Convocatórias em Massa';
        desc.textContent = 'Abaixo estão listados os candidatos selecionados. Ao processar, cada um será colocado em "convocado" e a lista de mensagens de convocatória será copiada.';
        btn.innerHTML = '<i class="fa fa-calendar-check me-1"></i> Gerar e Copiar Convocatórias Selecionadas';
        btn.style.background = '#D97706';
    } else {
        candidatos = candidaturasListaGlobal.filter(c => c.estado === 'validado_presencial');
        titulo.innerHTML = '<i class="fa fa-paper-plane me-2"></i> Convites de Registo 48h em Massa';
        desc.textContent = 'Apenas candidatos com presença validada pelo Super Admin. Ao processar, serão gerados tokens individuais de 48h e gerada a lista de links para envio.';
        btn.innerHTML = '<i class="fa fa-bolt me-1"></i> Gerar Tokens 48h e Copiar Links';
        btn.style.background = '#16A34A';
    }

    if (candidatos.length === 0) {
        alert(modo === 'convocatoria' 
            ? 'Nenhum candidato selecionado aguardando convocatória.' 
            : 'Nenhum candidato validado presencialmente aguardando convite de registo.');
        return;
    }

    list.innerHTML = candidatos.map(c => `
        <div class="form-check py-1 border-bottom d-flex align-items-center">
            <input class="form-check-input chk-massa-cand me-2" type="checkbox" value="${c.id}" id="chkMassa_${c.id}" checked>
            <label class="form-check-label small mb-0" for="chkMassa_${c.id}">
                <strong>${c.nome}</strong> (${c.telefone}) · <em>${c.titulo_ideia}</em>
            </label>
        </div>
    `).join('');

    document.getElementById('massaTextAreaWrapper').style.display = 'none';
    btn.style.display = 'block';
    btn.disabled = false;
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
    btn.innerHTML = `<i class="fa fa-spinner fa-spin me-2"></i> A processar lote com guarda de integridade...`;
    btn.disabled = true;

    let resultadosText = "";
    const actionAlvo = (massaModo === 'convocatoria') ? 'convocar_candidato' : 'enviar_convite_registo';

    async function processarItens() {
        for (let id of ids) {
            const fd = new FormData();
            fd.append('action', actionAlvo);
            fd.append('id_cand', id);
            fd.append('_csrf_token', csrfToken);
            if (massaModo === 'convocatoria') {
                fd.append('data_reuniao', '<?= date('Y-m-d', strtotime('+2 days')) ?>');
                fd.append('hora_reuniao', '10:00');
                fd.append('local_reuniao', 'Gabinete da Incubadora Académica - ISPSN');
            }

            try {
                let res = await fetch('/incubadora_ispsn/app/controllers/candidatura_action.php', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: fd
                });
                let data = await res.json();
                if (data.sucesso) {
                    if (massaModo === 'convocatoria') {
                        resultadosText += `CANDIDATO: ${data.candidato.nome}\nTELEFONE: ${data.candidato.telefone}\nMENSAGEM:\n${data.mensagem}\n------------------------\n\n`;
                    } else {
                        resultadosText += `CANDIDATO: ${data.candidato.nome}\nTELEFONE: ${data.candidato.telefone}\nLINK EXCLUSIVO (48h): ${data.link_registo}\n------------------------\n\n`;
                    }
                }
            } catch (e) {
                console.error("Erro no id " + id, e);
            }
        }
    }

    processarItens().then(() => {
        btn.innerHTML = `<i class="fa fa-check me-2"></i> Lote Processado com Sucesso!`;
        btn.style.background = '#10B981';

        const textArea = document.getElementById('massaTextArea');
        textArea.value = resultadosText;
        document.getElementById('massaTextAreaWrapper').style.display = 'block';

        textArea.select();
        textArea.setSelectionRange(0, 99999);
        navigator.clipboard.writeText(resultadosText)
        .then(() => alert('Processamento concluído! Todos os dados formatados foram copiados para a área de transferência.'))
        .catch(() => alert('Processamento concluído! Por favor, copie da caixa de texto.'));
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

function filtrarPorTipoProjeto(tipo) {
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

