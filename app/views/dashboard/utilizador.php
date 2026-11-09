<?php
require_once __DIR__ . '/../../../config/auth.php';
require_once __DIR__ . '/../../../config/config.php';

obrigarLogin();

$realPerfil     = $_SESSION['usuario_perfil'] ?? 'utilizador';
$isAdminOrSuper = in_array($realPerfil, ['admin', 'superadmin'], true);

if ($isAdminOrSuper) {
    $idUsuario = (int)($_GET['id_usuario'] ?? 0);
    if ($idUsuario <= 0) {
        $resFallback = $mysqli->query("
            SELECT u.id, u.nome 
            FROM usuarios u 
            JOIN projetos p ON p.criado_por = u.id 
            ORDER BY p.criado_em DESC LIMIT 1
        ");
        $fallback  = $resFallback ? $resFallback->fetch_assoc() : null;
        $idUsuario = $fallback ? (int)$fallback['id'] : 0;
    }

    $stmtU = $mysqli->prepare("SELECT nome FROM usuarios WHERE id = ?");
    if ($stmtU) {
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uData = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $nome = $uData['nome'] ?? 'Estudante';
    } else {
        $nome = 'Estudante';
    }

    $nomeUsuario = $nome;
    $perfil      = 'utilizador';
} else {
    $idUsuario = (int)($_SESSION['usuario_id'] ?? 0);
    $nome      = $_SESSION['usuario_nome'] ?? 'Utilizador';
}

$qsPreview        = ($isAdminOrSuper && $idUsuario > 0) ? ('?id_usuario=' . (int)$idUsuario) : '';
$hrefFichaProjeto = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php' . $qsPreview;
$hrefMetasProjeto = '/incubadora_ispsn/app/views/utilizador/meu_projeto.php'
    . ($qsPreview !== '' ? ($qsPreview . '&tab=metas') : '?tab=metas');
$hrefReservas     = '/incubadora_ispsn/app/views/utilizador/reservas.php' . $qsPreview;
$hrefRanking      = '/incubadora_ispsn/app/views/admin/ranking.php' . $qsPreview;

/* ── 1. LISTA DE PROJETOS DO ESTUDANTE & PROJETO SELECIONADO ── */
$meusProjetosLista = [];
$stmtLista = $mysqli->prepare("
    SELECT id, titulo, estado, fase, criado_em
    FROM projetos
    WHERE criado_por = ?
    ORDER BY criado_em DESC
");
if ($stmtLista) {
    $stmtLista->bind_param('i', $idUsuario);
    $stmtLista->execute();
    $meusProjetosLista = $stmtLista->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtLista->close();
}

$idProjetoSel = (int)($_GET['id_projeto'] ?? 0);
$ultimoProjeto = null;
if ($idProjetoSel > 0) {
    $stmtP = $mysqli->prepare("SELECT * FROM projetos WHERE id = ? AND criado_por = ? LIMIT 1");
    if ($stmtP) {
        $stmtP->bind_param('ii', $idProjetoSel, $idUsuario);
        $stmtP->execute();
        $ultimoProjeto = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();
    }
}
if (!$ultimoProjeto && !empty($meusProjetosLista)) {
    $idFirst = (int)$meusProjetosLista[0]['id'];
    $stmtP = $mysqli->prepare("SELECT * FROM projetos WHERE id = ? LIMIT 1");
    if ($stmtP) {
        $stmtP->bind_param('i', $idFirst);
        $stmtP->execute();
        $ultimoProjeto = $stmtP->get_result()->fetch_assoc();
        $stmtP->close();
    }
}

$labelsEstado = [
    'submetido'          => 'Submetido (Em Triagem)',
    'em_avaliacao'       => 'Em Avaliação Técnica',
    'aprovado'           => 'Aprovado para Incubação',
    'rejeitado'          => 'Rejeitado',
    'incubado'           => 'Em Incubação',
    'fundo_investimento' => 'Pronto p/ Investimento',
    'concluido'          => 'Graduado',
];

$labelsFase = [
    'ideacao'            => 'Ideação',
    'validacao'          => 'Validação',
    'mvp'                => 'MVP',
    'tracao'             => 'Tração',
    'mercado'            => 'Mercado',
    'fundo_investimento' => 'Financiado',
];

$labelsTipo = [
    'startup_tecnologica' => 'Startup Tecnológica',
    'negocio_tradicional' => 'Negócio Tradicional',
    'impacto_social'      => 'Impacto Social',
    'outro'               => 'Outro',
];

$mentorAtivo         = null;
$termoProjeto        = null;
$ultimaAvaliacao     = null;
$ultimoComentario    = null;
$metasFase           = [];
$tarefasPendentes    = [];
$minhasReunioes      = [];
$ultimaSessao        = null;
$metasValidadasCount = 0;
$totalMetasFase      = 0;
$metaPrincipal       = null;
$metaEmAvaliacao     = null;
$numMembros          = 0;
$numDocumentos       = 0;
$numComentarios      = 0;
$numAvalAtrib        = 0;
$numAvalConcl        = 0;
$membrosProjeto      = [];
$documentosProjeto   = [];
$mensagensRecentes   = [];
$timelineEventos     = [];
$posicaoRanking      = null;

if ($ultimoProjeto) {
    $idProj     = (int)$ultimoProjeto['id'];
    $estadoProj = (string)($ultimoProjeto['estado'] ?? '');
    $faseActual = (string)($ultimoProjeto['fase'] ?? 'ideacao');

    /* ── CONTAGENS REAIS DO DOSSIÊ E DA AVALIAÇÃO ── */
    $stmtCnt = $mysqli->prepare("
        SELECT
            (SELECT COUNT(*) FROM membros_projeto WHERE id_projeto = ?) AS n_membros,
            (SELECT COUNT(*) FROM ficheiros_projeto WHERE id_projeto = ?) AS n_docs,
            (SELECT COUNT(*) FROM comentarios_projetos WHERE id_projeto = ?) AS n_com,
            (SELECT COUNT(*) FROM avaliacoes_atribuicao WHERE projeto_id = ?) AS n_aval_atrib,
            (SELECT COUNT(*) FROM avaliacoes_atribuicao WHERE projeto_id = ? AND estado = 'concluido') AS n_aval_concl
    ");
    if ($stmtCnt) {
        $stmtCnt->bind_param('iiiii', $idProj, $idProj, $idProj, $idProj, $idProj);
        $stmtCnt->execute();
        $cntRow = $stmtCnt->get_result()->fetch_assoc();
        $stmtCnt->close();
        $numMembros     = max(1, (int)($cntRow['n_membros'] ?? 0));
        $numDocumentos  = (int)($cntRow['n_docs'] ?? 0) + (!empty($ultimoProjeto['pitch_path']) ? 1 : 0);
        $numComentarios = (int)($cntRow['n_com'] ?? 0);
        $numAvalAtrib   = (int)($cntRow['n_aval_atrib'] ?? 0);
        $numAvalConcl   = (int)($cntRow['n_aval_concl'] ?? 0);
    }

    /* ── POSIÇÃO NO RANKING (Quando possui pontos > 0) ── */
    $ptsProj = (int)($ultimoProjeto['pontos'] ?? 0);
    if ($ptsProj > 0) {
        $stmtRk = $mysqli->prepare("SELECT COUNT(*) + 1 AS pos FROM projetos WHERE pontos > ?");
        if ($stmtRk) {
            $stmtRk->bind_param('i', $ptsProj);
            $stmtRk->execute();
            $rkRow = $stmtRk->get_result()->fetch_assoc();
            $stmtRk->close();
            $posicaoRanking = (int)($rkRow['pos'] ?? 1);
        }
    }

    /* ── EQUIPA DO PROJECTO ── */
    $stmtEq = $mysqli->prepare("
        SELECT u.nome, u.email, mp.papel, mp.id_usuario
        FROM membros_projeto mp
        JOIN usuarios u ON u.id = mp.id_usuario
        WHERE mp.id_projeto = ?
    ");
    if ($stmtEq) {
        $stmtEq->bind_param('i', $idProj);
        $stmtEq->execute();
        $membrosProjeto = $stmtEq->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtEq->close();
    }

    /* ── DOCUMENTOS DO PROJECTO ── */
    $stmtDocs = $mysqli->prepare("
        SELECT f.*, u.nome AS quem_submeteu
        FROM ficheiros_projeto f
        LEFT JOIN usuarios u ON u.id = f.id_usuario_up
        WHERE f.id_projeto = ?
        ORDER BY f.id DESC LIMIT 6
    ");
    if ($stmtDocs) {
        $stmtDocs->bind_param('i', $idProj);
        $stmtDocs->execute();
        $documentosProjeto = $stmtDocs->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtDocs->close();
    }

    /* ── ÚLTIMO COMENTÁRIO OFICIAL ── */
    $stmtCom = $mysqli->prepare("
        SELECT c.comentario, c.criado_em, u.nome AS autor_nome, u.perfil AS autor_perfil
        FROM comentarios_projetos c
        JOIN usuarios u ON u.id = c.id_usuario
        WHERE c.id_projeto = ?
        ORDER BY c.criado_em DESC LIMIT 3
    ");
    if ($stmtCom) {
        $stmtCom->bind_param('i', $idProj);
        $stmtCom->execute();
        $comsList = $stmtCom->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtCom->close();
        if (!empty($comsList)) {
            $ultimoComentario = $comsList[0];
            foreach ($comsList as $cm) {
                $timelineEventos[] = [
                    'data'   => $cm['criado_em'],
                    'icone'  => 'fa-comment-dots',
                    'cor'    => '#3B82F6',
                    'bg'     => 'rgba(59, 130, 246, 0.12)',
                    'titulo' => 'Parecer de ' . $cm['autor_nome'],
                    'texto'  => mb_strimwidth((string)$cm['comentario'], 0, 110, '...')
                ];
            }
        }
    }

    /* ── HISTÓRICO DE TRANSIÇÕES DE ESTADO ── */
    $stmtHist = $mysqli->prepare("
        SELECT h.estado_anterior, h.estado_novo, h.motivo, h.criado_em
        FROM historico_estados h
        WHERE h.id_projeto = ?
        ORDER BY h.criado_em DESC LIMIT 4
    ");
    if ($stmtHist) {
        $stmtHist->bind_param('i', $idProj);
        $stmtHist->execute();
        $histRows = $stmtHist->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtHist->close();
        foreach ($histRows as $hr) {
            $novoEstLbl = $labelsEstado[$hr['estado_novo']] ?? ucfirst((string)$hr['estado_novo']);
            $timelineEventos[] = [
                'data'   => $hr['criado_em'],
                'icone'  => 'fa-arrow-right-arrow-left',
                'cor'    => '#D97706',
                'bg'     => 'rgba(217, 119, 6, 0.12)',
                'titulo' => 'Estado alterado para ' . $novoEstLbl,
                'texto'  => !empty($hr['motivo']) ? mb_strimwidth((string)$hr['motivo'], 0, 100, '...') : 'Transição registada no sistema.'
            ];
        }
    }

    if (!empty($ultimoProjeto['criado_em'])) {
        $timelineEventos[] = [
            'data'   => $ultimoProjeto['criado_em'],
            'icone'  => 'fa-rocket',
            'cor'    => '#10B981',
            'bg'     => 'rgba(16, 185, 129, 0.12)',
            'titulo' => 'Proposta submetida na plataforma',
            'texto'  => 'Iniciativa "' . $ultimoProjeto['titulo'] . '" registada.'
        ];
    }

    usort($timelineEventos, fn($a, $b) => strtotime((string)$b['data']) <=> strtotime((string)$a['data']));
    $timelineEventos = array_slice($timelineEventos, 0, 5);

    /* ── MENSAGENS DO CHAT DO PROJECTO ── */
    $stmtMsg = $mysqli->prepare("
        SELECT m.*, u.nome, u.perfil
        FROM mensagens m
        JOIN usuarios u ON u.id = m.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY m.criado_at DESC LIMIT 4
    ");
    if ($stmtMsg) {
        $stmtMsg->bind_param('i', $idProj);
        $stmtMsg->execute();
        $mensagensRecentes = array_reverse($stmtMsg->get_result()->fetch_all(MYSQLI_ASSOC));
        $stmtMsg->close();
    }

    /* ── 2. MENTOR ATRIBUÍDO (MENTORIA ATIVA) ── */
    $stmtMent = $mysqli->prepare("
        SELECT m.id AS id_mentoria, u.nome AS mentor_nome, u.email AS mentor_email, mt.especialidade
        FROM mentorias m
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ? AND m.estado = 'activa'
        ORDER BY m.id DESC LIMIT 1
    ");
    if ($stmtMent) {
        $stmtMent->bind_param('i', $idProj);
        $stmtMent->execute();
        $mentorAtivo = $stmtMent->get_result()->fetch_assoc();
        $stmtMent->close();
    }

    /* ── 3. TERMO DE INCUBAÇÃO ── */
    $stmtTermo = $mysqli->prepare("
        SELECT *
        FROM termos_incubacao
        WHERE id_projeto = ? AND estado IN ('gerado', 'pendente_assinatura', 'assinado')
        ORDER BY criado_em DESC LIMIT 1
    ");
    if ($stmtTermo) {
        $stmtTermo->bind_param('i', $idProj);
        $stmtTermo->execute();
        $termoProjeto = $stmtTermo->get_result()->fetch_assoc();
        $stmtTermo->close();
    }

    /* ── 4. ÚLTIMA AVALIAÇÃO OFICIAL ── */
    $stmtAv = $mysqli->prepare("
        SELECT pontuacao_total, observacoes, avaliado_em
        FROM avaliacoes
        WHERE id_projeto = ?
        ORDER BY avaliado_em DESC LIMIT 1
    ");
    if ($stmtAv) {
        $stmtAv->bind_param('i', $idProj);
        $stmtAv->execute();
        $ultimaAvaliacao = $stmtAv->get_result()->fetch_assoc();
        $stmtAv->close();
    }

    /* ── 5. METAS DA FASE ATUAL (quando incubado/fundo_investimento/concluido) ── */
    if (in_array($estadoProj, ['incubado', 'fundo_investimento', 'concluido'], true)) {
        $stmtM = $mysqli->prepare("
            SELECT mp.*, mpd.numero, mpd.titulo AS meta_titulo, mpd.descricao AS meta_descricao,
                   mpd.evidencia_tipo, mpd.evidencia_desc, mpd.peso_percentual, mpd.prazo_dias
            FROM metas_projeto mp
            JOIN metas_padrao mpd ON mpd.id = mp.id_meta_padrao
            WHERE mp.id_projeto = ? AND mpd.fase = ?
            ORDER BY mpd.numero ASC
        ");
        if ($stmtM) {
            $stmtM->bind_param('is', $idProj, $faseActual);
            $stmtM->execute();
            $metasFase = $stmtM->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtM->close();
        }

        $totalMetasFase = count($metasFase);
        foreach ($metasFase as $m) {
            $estM = (string)($m['estado'] ?? 'inactiva');
            if ($estM === 'concluida') {
                $metasValidadasCount++;
            } elseif (($estM === 'reprovada' || $estM === 'activa') && $metaPrincipal === null) {
                $metaPrincipal = $m;
            } elseif ($estM === 'em_avaliacao' && $metaEmAvaliacao === null) {
                $metaEmAvaliacao = $m;
            }
        }
    }

    /* ── 6. TAREFAS PENDENTES DO MENTOR ── */
    $stmtT = $mysqli->prepare("
        SELECT *
        FROM tarefas
        WHERE id_projeto = ? AND COALESCE(validada_mentor, 0) = 0
        ORDER BY data_limite ASC, id DESC
        LIMIT 4
    ");
    if ($stmtT) {
        $stmtT->bind_param('i', $idProj);
        $stmtT->execute();
        $tarefasPendentes = $stmtT->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtT->close();
    }

    /* ── 7. PRÓXIMAS REUNIÕES AGENDADAS ── */
    $stmtR = $mysqli->prepare("
        SELECT r.*, u.nome AS mentor_nome
        FROM reunioes r
        JOIN usuarios u ON u.id = r.id_mentor
        WHERE r.id_projeto = ? AND r.data_reuniao >= NOW() AND r.status = 'agendada'
        ORDER BY r.data_reuniao ASC LIMIT 2
    ");
    if ($stmtR) {
        $stmtR->bind_param('i', $idProj);
        $stmtR->execute();
        $minhasReunioes = $stmtR->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmtR->close();
    }

    /* ── 8. ÚLTIMA SESSÃO DE MENTORIA REALIZADA ── */
    $stmtS = $mysqli->prepare("
        SELECT s.*, u.nome AS mentor_nome
        FROM sessoes_mentoria s
        JOIN mentorias m ON m.id = s.id_mentoria
        JOIN mentores mt ON mt.id = m.id_mentor
        JOIN usuarios u ON u.id = mt.id_usuario
        WHERE m.id_projeto = ?
        ORDER BY s.data_sessao DESC LIMIT 1
    ");
    if ($stmtS) {
        $stmtS->bind_param('i', $idProj);
        $stmtS->execute();
        $ultimaSessao = $stmtS->get_result()->fetch_assoc();
        $stmtS->close();
    }
}

/* ── 9. RESERVAS DE ESPAÇO DO UTILIZADOR ── */
$minhasReservasPainel = [];
$stmtRes = $mysqli->prepare("
    SELECT r.*, e.nome AS espaco_nome, e.tipo AS espaco_tipo
    FROM reservas_espaco r
    JOIN espacos e ON e.id = r.id_espaco
    WHERE r.id_usuario = ?
    ORDER BY r.data_reserva DESC, r.hora_inicio DESC
    LIMIT 4
");
if ($stmtRes) {
    $stmtRes->bind_param('i', $idUsuario);
    $stmtRes->execute();
    $minhasReservasPainel = $stmtRes->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtRes->close();
}

$reservasConfirmadas = array_values(array_filter(
    $minhasReservasPainel,
    fn($r) => ($r['status'] ?? '') === 'confirmada'
));
$totalReservasConfirmadas = count($reservasConfirmadas);

/* ── DETERMINAÇÃO DO ESTADO DA MÁQUINA DE ESTADOS (E0 a E5 + E_FALLBACK) ── */
$uiState = 'E0';
if ($ultimoProjeto) {
    $estP = (string)($ultimoProjeto['estado'] ?? '');
    if (in_array($estP, ['submetido', 'em_avaliacao'], true)) {
        $uiState = 'E1';
    } elseif ($estP === 'aprovado') {
        $uiState = 'E2';
    } elseif (in_array($estP, ['incubado', 'fundo_investimento'], true)) {
        if ($totalMetasFase > 0 && $metasValidadasCount === $totalMetasFase) {
            $uiState = 'E4';
        } else {
            $uiState = 'E3';
        }
    } elseif ($estP === 'concluido') {
        $uiState = 'E4';
    } elseif ($estP === 'rejeitado') {
        $uiState = 'E5';
    } else {
        $uiState = 'E_FALLBACK';
    }
}

$tituloPagina = 'Painel Geral';
$paginaActiva = 'dashboard';
$flashOk   = $_SESSION['flash_ok']   ?? ''; unset($_SESSION['flash_ok']);
$flashErro = $_SESSION['flash_erro'] ?? ''; unset($_SESSION['flash_erro']);

require_once __DIR__ . '/../partials/_layout.php';
?>

<div class="std-painel">

    <?php if ($flashOk): ?>
        <div class="alert-custom alert-success mb-3" role="status">
            <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashOk, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>
    <?php if ($flashErro): ?>
        <div class="alert-custom alert-danger mb-3" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span><?= htmlspecialchars($flashErro, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($uiState === 'E0'): ?>
        <!-- ══════════════════════════════════════════════════════════════
             ESTADO E0: CONTA SEM PROJETO SUBMETIDO
             ══════════════════════════════════════════════════════════════ -->
        <div class="std-hero-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="sp-kicker mb-1"><i class="fa-solid fa-seedling me-1" style="color:#D97706;"></i> Ecossistema de Empreendedorismo ISPSN</div>
                    <h1 class="sp-title mb-2">Olá, <?= htmlspecialchars(explode(' ', $nome)[0], ENT_QUOTES, 'UTF-8') ?>!</h1>
                    <p class="m-0" style="color: var(--sp-text-sec); max-width: 640px;">
                        Submeta a sua primeira proposta de startup para iniciar a avaliação técnica, desbloquear mentoria especializada e aceder aos laboratórios da incubadora.
                    </p>
                </div>
                <span class="std-live-pill" style="background: var(--sp-surface-muted); color: var(--sp-text-sec); border: 1px solid var(--sp-border);">
                    <span class="std-live-dot" style="background: #94A3B8;"></span>
                    Sem Projecto Activo
                </span>
            </div>

            <?php if (!$isAdminOrSuper): ?>
                <div class="mt-4 d-flex flex-wrap gap-2">
                    <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalProjeto">
                        <i class="fa-solid fa-rocket" aria-hidden="true"></i>
                        <span>Submeter Primeira Ideia</span>
                    </button>
                    <a href="<?= htmlspecialchars($hrefReservas, ENT_QUOTES, 'UTF-8') ?>" class="btn-ghost">
                        <i class="fa-solid fa-bookmark" aria-hidden="true"></i>
                        <span>Reservar Espaço de Trabalho</span>
                    </a>
                </div>
            <?php else: ?>
                <div class="sp-empty-line mt-4">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <span>Esta conta de estudante ainda não submeteu nenhuma proposta de projecto.</span>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <?php
        $idProj      = (int)$ultimoProjeto['id'];
        $estadoRaw   = (string)($ultimoProjeto['estado'] ?? '');
        $faseKey     = (string)($ultimoProjeto['fase'] ?? 'ideacao');
        $faseLabel   = $labelsFase[$faseKey] ?? strtoupper(str_replace('_', ' ', $faseKey));
        $estadoLabel = $labelsEstado[$estadoRaw] ?? $estadoRaw;
        $tipoKey     = (string)($ultimoProjeto['tipo'] ?? '');
        $tipoLabel   = $labelsTipo[$tipoKey] ?? ($tipoKey !== '' ? ucfirst(str_replace('_', ' ', $tipoKey)) : 'Startup');
        $pontos      = (int)($ultimoProjeto['pontos'] ?? 0);
        $dataCriacao = !empty($ultimoProjeto['criado_em'])
            ? date('d/m/Y', strtotime((string)$ultimoProjeto['criado_em']))
            : '—';

        $pillBg    = 'rgba(245, 158, 11, 0.14)';
        $pillColor = '#B45309';
        $dotColor  = '#F59E0B';
        if (in_array($estadoRaw, ['aprovado', 'incubado', 'fundo_investimento', 'concluido'], true)) {
            $pillBg    = 'rgba(16, 185, 129, 0.14)';
            $pillColor = '#059669';
            $dotColor  = '#10B981';
        } elseif ($estadoRaw === 'rejeitado') {
            $pillBg    = 'rgba(239, 68, 68, 0.14)';
            $pillColor = '#DC2626';
            $dotColor  = '#EF4444';
        }

        $isIncubado = in_array($uiState, ['E3', 'E4'], true);

        // Checklist real de prontidão do dossiê
        $temDescricao = strlen(trim((string)($ultimoProjeto['descricao'] ?? ''))) >= 20;
        $temProbSol   = !empty(trim((string)($ultimoProjeto['problema'] ?? ''))) && !empty(trim((string)($ultimoProjeto['solucao'] ?? '')));
        $temPitch     = !empty($ultimoProjeto['pitch_path']) || !empty($documentosProjeto);
        $temEquipa    = $numMembros >= 1;
        $itensProntos = ($temDescricao ? 1 : 0) + ($temProbSol ? 1 : 0) + ($temPitch ? 1 : 0) + ($temEquipa ? 1 : 0);
        ?>

        <!-- ══════════════════════════════════════════════════════════════
             1. HERO DINÂMICO DA STARTUP + ACÇÕES RÁPIDAS + PIPELINE
             ══════════════════════════════════════════════════════════════ -->
        <div class="std-hero-card">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-2">
                        <span class="std-live-pill" style="background: <?= $pillBg ?>; color: <?= $pillColor ?>;">
                            <span class="std-live-dot" style="background: <?= $dotColor ?>;"></span>
                            <?= htmlspecialchars($estadoLabel, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                        <span class="sp-meta-pill">
                            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                            <span><?= htmlspecialchars(ucfirst((string)($ultimoProjeto['area_tematica'] ?? 'Tecnologia')), ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span class="sp-meta-pill">
                            <i class="fa-solid fa-tag" aria-hidden="true"></i>
                            <span><?= htmlspecialchars($tipoLabel, ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                        <span class="sp-meta-pill sp-num">
                            <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                            <span>Submetido em <?= htmlspecialchars($dataCriacao, ENT_QUOTES, 'UTF-8') ?></span>
                        </span>
                    </div>

                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <h1 class="sp-title"><?= htmlspecialchars((string)$ultimoProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?></h1>

                        <?php if (count($meusProjetosLista) > 1): ?>
                            <form method="GET" class="m-0">
                                <?php if ($isAdminOrSuper && $idUsuario > 0): ?>
                                    <input type="hidden" name="id_usuario" value="<?= (int)$idUsuario ?>">
                                <?php endif; ?>
                                <select name="id_projeto" class="form-control-custom py-1 px-2" style="font-size:0.78rem; width:auto;" onchange="this.form.submit()" aria-label="Alternar projecto">
                                    <?php foreach ($meusProjetosLista as $pl): ?>
                                        <option value="<?= (int)$pl['id'] ?>" <?= ((int)$pl['id'] === $idProj) ? 'selected' : '' ?>>
                                            Alternar: <?= htmlspecialchars((string)$pl['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Botões Interativos Diretos no Painel -->
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <?php if (!$isAdminOrSuper): ?>
                        <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            <span>Editar Proposta</span>
                        </button>
                        <button type="button" class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalUploadDocDash">
                            <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
                            <span>Carregar Documento</span>
                        </button>
                        <button type="button" class="btn-ghost" data-bs-toggle="modal" data-bs-target="#modalProjeto" title="Submeter outra ideia">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            <span>Nova Ideia</span>
                        </button>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="btn-primary-custom">
                            <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                            <span>Abrir Dossiê Completo</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- PIPELINE VISUAL CONTÍNUO (ADMISSÃO OU MATURIDADE DE INCUBAÇÃO) -->
            <?php if (!$isIncubado): ?>
                <?php
                // Pipeline de 4 Etapas de Admissão & Formalização (para submetido, em_avaliacao, aprovado, rejeitado)
                $admStep1Done = true;
                $admStep2Done = ($numAvalConcl >= 3) || in_array($estadoRaw, ['aprovado', 'incubado'], true);
                $admStep2Act  = in_array($estadoRaw, ['submetido', 'em_avaliacao'], true) && !$admStep2Done;
                $admStep3Done = ($estadoRaw === 'aprovado' && $termoProjeto && ($termoProjeto['estado'] ?? '') === 'assinado');
                $admStep3Act  = ($estadoRaw === 'aprovado' && !$admStep3Done);
                ?>
                <div class="std-pipeline" role="list" aria-label="Etapas do processo de admissão">
                    <div class="std-pipe-step is-done" role="listitem">
                        <span class="std-pipe-icon"><i class="fa-solid fa-check"></i></span>
                        <div style="min-width:0;">
                            <div style="font-size:0.7rem; text-transform:uppercase; font-weight:700; opacity:0.8;">Etapa 1 · Concluída</div>
                            <div class="fw-bold text-truncate" style="font-size:0.84rem;">Submissão Registada</div>
                        </div>
                    </div>

                    <div class="std-pipe-step <?= $admStep2Done ? 'is-done' : ($admStep2Act ? 'is-active' : '') ?>" role="listitem">
                        <span class="std-pipe-icon">
                            <?php if ($admStep2Done): ?><i class="fa-solid fa-check"></i><?php else: ?><i class="fa-solid fa-magnifying-glass"></i><?php endif; ?>
                        </span>
                        <div style="min-width:0;">
                            <div style="font-size:0.7rem; text-transform:uppercase; font-weight:700; opacity:0.8;">
                                Etapa 2 · <?= $admStep2Done ? 'Concluída' : ($numAvalConcl . ' de 3 pareceres') ?>
                            </div>
                            <div class="fw-bold text-truncate" style="font-size:0.84rem;">Avaliação Técnica</div>
                        </div>
                    </div>

                    <div class="std-pipe-step <?= $admStep3Done ? 'is-done' : ($admStep3Act ? 'is-active' : '') ?>" role="listitem">
                        <span class="std-pipe-icon">
                            <?php if ($admStep3Done): ?><i class="fa-solid fa-check"></i><?php else: ?>3<?php endif; ?>
                        </span>
                        <div style="min-width:0;">
                            <div style="font-size:0.7rem; text-transform:uppercase; font-weight:700; opacity:0.8;">
                                Etapa 3 · <?= $termoProjeto ? 'Termo ' . htmlspecialchars((string)$termoProjeto['codigo_termo'], ENT_QUOTES, 'UTF-8') : 'Formalização' ?>
                            </div>
                            <div class="fw-bold text-truncate" style="font-size:0.84rem;">Aprovação &amp; Termo</div>
                        </div>
                    </div>

                    <div class="std-pipe-step" role="listitem">
                        <span class="std-pipe-icon">4</span>
                        <div style="min-width:0;">
                            <div style="font-size:0.7rem; text-transform:uppercase; font-weight:700; opacity:0.8;">Etapa 4 · Incubação</div>
                            <div class="fw-bold text-truncate" style="font-size:0.84rem;">Desbloqueio de Metas</div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php
                // Pipeline de 6 Fases de Maturidade na Incubadora
                $stepsInc = [
                    ['id' => 'ideacao',            'label' => 'Ideação',    'icon' => 'fa-lightbulb'],
                    ['id' => 'validacao',          'label' => 'Validação',  'icon' => 'fa-flask'],
                    ['id' => 'mvp',                'label' => 'MVP',        'icon' => 'fa-cube'],
                    ['id' => 'tracao',             'label' => 'Tração',     'icon' => 'fa-chart-line'],
                    ['id' => 'mercado',            'label' => 'Mercado',    'icon' => 'fa-store'],
                    ['id' => 'fundo_investimento', 'label' => 'Financiado', 'icon' => 'fa-coins'],
                ];
                $currIdx = 0;
                foreach ($stepsInc as $idx => $st) {
                    if ($faseKey === $st['id']) {
                        $currIdx = $idx;
                        break;
                    }
                }
                ?>
                <div class="std-pipeline" role="list" aria-label="Jornada de maturidade na incubadora">
                    <?php foreach ($stepsInc as $idx => $st):
                        $isDone = ($idx < $currIdx) || ($estadoRaw === 'concluido');
                        $isCurr = ($idx === $currIdx) && ($estadoRaw !== 'concluido');
                    ?>
                        <div class="std-pipe-step <?= $isDone ? 'is-done' : ($isCurr ? 'is-active' : '') ?>" role="listitem">
                            <span class="std-pipe-icon">
                                <?php if ($isDone): ?>
                                    <i class="fa-solid fa-check"></i>
                                <?php else: ?>
                                    <i class="fa-solid <?= $st['icon'] ?>"></i>
                                <?php endif; ?>
                            </span>
                            <div style="min-width:0;">
                                <div style="font-size:0.68rem; text-transform:uppercase; font-weight:700; opacity:0.8;">
                                    Fase <?= (int)($idx + 1) ?><?= $isCurr ? ' · Actual' : ($isDone ? ' · Concluída' : '') ?>
                                </div>
                                <div class="fw-bold text-truncate" style="font-size:0.84rem;"><?= htmlspecialchars($st['label'], ENT_QUOTES, 'UTF-8') ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             2. GRELHA DE 4 KPI CARDS (100% DADOS REAIS E VERIFICÁVEIS)
             ══════════════════════════════════════════════════════════════ -->
        <div class="kpi-grid">
            <!-- KPI 1: Fase & Estado -->
            <div class="kpi-card" style="--kpi-color: #D97706;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-icon"><i class="fa-solid fa-compass"></i></div>
                    <span class="badge-estado badge-<?= $isIncubado ? 'aprovado' : 'em_analise' ?>">
                        <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </div>
                <div class="kpi-value" style="font-size:1.35rem; margin-bottom:6px;">
                    <?= htmlspecialchars($isIncubado ? ('Fase: ' . $faseLabel) : ($estadoRaw === 'aprovado' ? 'Aprovado' : 'Em Apreciação'), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="kpi-label">Situação na Incubadora</div>
                <div class="kpi-trend" style="color: var(--sp-text-sec);">
                    <i class="fa-solid fa-circle-check" style="color:#D97706;"></i>
                    <span><?= $isIncubado ? 'Incubação activa' : ($numAvalAtrib > 0 ? "{$numAvalAtrib}/3 avaliadores atribuídos" : 'Em fila de avaliação') ?></span>
                </div>
            </div>

            <!-- KPI 2: Avaliação Técnica ou Metas da Fase -->
            <div class="kpi-card" style="--kpi-color: #10B981;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-icon"><i class="fa-solid fa-list-check"></i></div>
                    <span class="sp-code fw-bold" style="color:#10B981;">
                        <?= $isIncubado ? "{$metasValidadasCount}/{$totalMetasFase}" : "{$numAvalConcl}/3" ?>
                    </span>
                </div>
                <div class="kpi-value sp-num">
                    <?= $isIncubado ? "{$metasValidadasCount} de {$totalMetasFase}" : "{$numAvalConcl} de 3" ?>
                </div>
                <div class="kpi-label"><?= $isIncubado ? 'Metas Validadas na Fase' : 'Pareceres da Comissão' ?></div>
                <div class="kpi-trend trend-up">
                    <i class="fa-solid fa-bolt"></i>
                    <span>
                        <?php if ($isIncubado): ?>
                            <?= count($tarefasPendentes) ?> tarefa<?= count($tarefasPendentes) !== 1 ? 's' : '' ?> do mentor pendente<?= count($tarefasPendentes) !== 1 ? 's' : '' ?>
                        <?php else: ?>
                            <?= (3 - $numAvalConcl) > 0 ? ('Faltam ' . (3 - $numAvalConcl) . ' avaliação(ões) p/ decisão') : 'Avaliações consolidadas' ?>
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- KPI 3: Dossiê & Equipa -->
            <div class="kpi-card" style="--kpi-color: #3B82F6;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-icon"><i class="fa-solid fa-folder-tree"></i></div>
                    <span class="sp-code fw-bold" style="color:#3B82F6;"><?= $itensProntos ?>/4 itens</span>
                </div>
                <div class="kpi-value sp-num"><?= (int)$numDocumentos ?> <span style="font-size:1rem; font-weight:600; color:var(--sp-text-muted);">doc<?= $numDocumentos !== 1 ? 's' : '' ?></span></div>
                <div class="kpi-label">Dossiê &amp; Equipa (<?= (int)$numMembros ?> membro<?= $numMembros !== 1 ? 's' : '' ?>)</div>
                <div class="kpi-trend" style="color: #3B82F6;">
                    <i class="fa-solid <?= $temPitch ? 'fa-check-circle' : 'fa-cloud-arrow-up' ?>"></i>
                    <span><?= $temPitch ? 'Pitch / documento anexado' : 'Adicione o seu Pitch Deck' ?></span>
                </div>
            </div>

            <!-- KPI 4: Ranking & Agenda / Reservas -->
            <div class="kpi-card" style="--kpi-color: #8B5CF6;">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="kpi-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <?php if ($posicaoRanking !== null && $isIncubado): ?>
                        <span class="sp-chip sp-chip-warn">#<?= (int)$posicaoRanking ?> no Ranking</span>
                    <?php endif; ?>
                </div>
                <div class="kpi-value sp-num">
                    <?= !empty($minhasReunioes) ? count($minhasReunioes) : $totalReservasConfirmadas ?>
                </div>
                <div class="kpi-label">
                    <?= !empty($minhasReunioes) ? 'Reuniões Agendadas' : 'Reservas de Espaço Activas' ?>
                </div>
                <div class="kpi-trend" style="color:#8B5CF6;">
                    <a href="<?= htmlspecialchars($hrefReservas, ENT_QUOTES, 'UTF-8') ?>" style="color:inherit; text-decoration:none;">
                        <span>Gerir agenda e reservas</span>
                        <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════
             3. ÁREA DE TRABALHO INTERATIVA (2 COLUNAS)
             ══════════════════════════════════════════════════════════════ -->
        <div class="row g-4 align-items-start">

            <!-- COLUNA PRINCIPAL (8 COLUNAS): ABAS INTERATIVAS NO PRÓPRIO PAINEL -->
            <div class="col-lg-8">

                <!-- Navegação de Abas Rápidas do Painel -->
                <div class="sp-tabs" role="tablist" aria-label="Secções interativas do painel">
                    <button type="button" class="sp-tab-btn active" onclick="alternarAbaDash('acoes', this)">
                        <i class="fa-solid fa-bolt" aria-hidden="true"></i>
                        <span><?= $isIncubado ? 'Metas & Tarefas Activas' : 'Estado da Avaliação & Prontidão' ?></span>
                    </button>
                    <button type="button" class="sp-tab-btn" onclick="alternarAbaDash('proposta', this)">
                        <i class="fa-solid fa-lightbulb" aria-hidden="true"></i>
                        <span>Proposta &amp; Equipa (<?= (int)$numMembros ?>)</span>
                    </button>
                    <button type="button" class="sp-tab-btn" onclick="alternarAbaDash('documentos', this)">
                        <i class="fa-solid fa-folder-open" aria-hidden="true"></i>
                        <span>Documentos (<?= (int)$numDocumentos ?>)</span>
                    </button>
                </div>

                <!-- ── ABA 1: ACÇÕES, PRONTIDÃO DO DOSSIÊ OU METAS DE INCUBAÇÃO ── -->
                <div id="dash-pane-acoes" class="dash-tab-pane">

                    <?php if ($uiState === 'E1'): ?>
                        <!-- CARTÃO DE ACOMPANHAMENTO DE AVALIAÇÃO + CHECKLIST INTERATIVA DE PRONTIDÃO -->
                        <div class="card-custom mb-4">
                            <div class="card-header-custom">
                                <div class="card-title-custom">
                                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                                    <span>Progresso da Avaliação pela Comissão Técnica</span>
                                </div>
                                <span class="sp-chip sp-chip-warn sp-num">
                                    <?= (int)$numAvalConcl ?> de 3 avaliações concluídas
                                </span>
                            </div>
                            <div class="card-body-custom">
                                <p class="mb-3" style="color: var(--sp-text-sec); font-size:0.9rem;">
                                    A sua proposta está a ser analisada pela comissão técnica (são necessários 3 pareceres independentes para consolidação automática da decisão). Enquanto decorre a análise, <strong>pode aperfeiçoar o seu dossiê abaixo</strong>.
                                </p>

                                <?php if ($ultimoComentario): ?>
                                    <div class="p-3 mb-4" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-left: 4px solid #3B82F6; border-radius: 10px;">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="sp-kicker" style="color:#3B82F6;">Último Parecer Registado · <?= htmlspecialchars((string)$ultimoComentario['autor_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span class="sp-num" style="font-size:0.74rem; color:var(--sp-text-muted);"><?= date('d/m/Y H:i', strtotime((string)$ultimoComentario['criado_em'])) ?></span>
                                        </div>
                                        <div style="font-size:0.86rem; color:var(--sp-text);">
                                            <?= nl2br(htmlspecialchars((string)$ultimoComentario['comentario'], ENT_QUOTES, 'UTF-8')) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="sp-kicker mb-2">
                                    <i class="fa-solid fa-clipboard-check me-1" style="color:#10B981;"></i>
                                    Checklist Interativa do Dossiê da Startup (<?= $itensProntos ?>/4 completos)
                                </div>

                                <div class="std-check-grid">
                                    <!-- Item 1: Descrição -->
                                    <div class="std-check-card <?= $temDescricao ? 'done' : '' ?>">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.85rem;">
                                                <i class="fa-solid <?= $temDescricao ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' ?> me-1"></i>
                                                Enquadramento Geral
                                            </div>
                                            <div style="font-size:0.76rem; color:var(--sp-text-sec); margin-top:2px;">
                                                <?= $temDescricao ? 'Descrição registada na proposta.' : 'Adicione uma descrição detalhada.' ?>
                                            </div>
                                        </div>
                                        <?php if (!$isAdminOrSuper): ?>
                                            <button type="button" class="btn-ghost py-1 px-2" style="font-size:0.74rem;" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Item 2: Problema & Solução -->
                                    <div class="std-check-card <?= $temProbSol ? 'done' : '' ?>">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.85rem;">
                                                <i class="fa-solid <?= $temProbSol ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' ?> me-1"></i>
                                                Problema &amp; Solução
                                            </div>
                                            <div style="font-size:0.76rem; color:var(--sp-text-sec); margin-top:2px;">
                                                <?= $temProbSol ? 'Problema e solução definidos.' : 'Especifique o problema e a solução.' ?>
                                            </div>
                                        </div>
                                        <?php if (!$isAdminOrSuper): ?>
                                            <button type="button" class="btn-ghost py-1 px-2" style="font-size:0.74rem;" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                                                <?= $temProbSol ? '<i class="fa-solid fa-pen"></i>' : 'Completar' ?>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Item 3: Pitch Deck / Documento -->
                                    <div class="std-check-card <?= $temPitch ? 'done' : '' ?>">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.85rem;">
                                                <i class="fa-solid <?= $temPitch ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-warning' ?> me-1"></i>
                                                Pitch Deck / Anexos
                                            </div>
                                            <div style="font-size:0.76rem; color:var(--sp-text-sec); margin-top:2px;">
                                                <?= $temPitch ? "{$numDocumentos} documento(s) no dossiê." : 'Carregue a apresentação PDF/PPT.' ?>
                                            </div>
                                        </div>
                                        <?php if (!$isAdminOrSuper): ?>
                                            <button type="button" class="btn-ghost py-1 px-2" style="font-size:0.74rem;" data-bs-toggle="modal" data-bs-target="#modalUploadDocDash">
                                                <i class="fa-solid fa-upload"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Item 4: Equipa -->
                                    <div class="std-check-card <?= $numMembros > 1 ? 'done' : '' ?>">
                                        <div>
                                            <div class="fw-bold" style="font-size:0.85rem;">
                                                <i class="fa-solid <?= $numMembros > 1 ? 'fa-circle-check text-success' : 'fa-user-plus text-primary' ?> me-1"></i>
                                                Equipa Fundadora
                                            </div>
                                            <div style="font-size:0.76rem; color:var(--sp-text-sec); margin-top:2px;">
                                                <?= (int)$numMembros ?> membro<?= $numMembros !== 1 ? 's' : '' ?> associado<?= $numMembros !== 1 ? 's' : '' ?> ao projecto.
                                            </div>
                                        </div>
                                        <?php if (!$isAdminOrSuper): ?>
                                            <button type="button" class="btn-ghost py-1 px-2" style="font-size:0.74rem;" data-bs-toggle="modal" data-bs-target="#modalAddMembroDash">
                                                <i class="fa-solid fa-user-plus"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php elseif ($uiState === 'E2'): ?>
                        <!-- ESTADO E2: APROVADO E EM FORMALIZAÇÃO DO TERMO -->
                        <div class="card-custom mb-4">
                            <div class="card-header-custom">
                                <div class="card-title-custom">
                                    <i class="fa-solid fa-file-signature"></i>
                                    <span>Proposta Aprovada · Formalização do Termo de Incubação</span>
                                </div>
                                <span class="sp-chip sp-chip-ok">Aprovado</span>
                            </div>
                            <div class="card-body-custom">
                                <?php if ($termoProjeto):
                                    $estTermo = (string)($termoProjeto['estado'] ?? '');
                                ?>
                                    <div class="p-3 mb-3" style="background: var(--sp-surface-muted); border: 1px solid var(--sp-border); border-radius: 12px;">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                            <div>
                                                <div class="sp-kicker">Código do Contrato / Termo</div>
                                                <div class="sp-code fw-bold" style="font-size:1.05rem;"><?= htmlspecialchars((string)$termoProjeto['codigo_termo'], ENT_QUOTES, 'UTF-8') ?></div>
                                            </div>
                                            <span class="sp-chip <?= $estTermo === 'assinado' ? 'sp-chip-ok' : 'sp-chip-warn' ?>">
                                                <?= htmlspecialchars($estTermo === 'assinado' ? 'Assinado' : 'Aguardando Assinatura Digital', ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <p class="mb-3" style="color: var(--sp-text-sec);">
                                        O seu projecto foi aprovado! A coordenação está a preparar o Termo de Incubação e a designação do seu mentor para iniciar a Fase 1 (Ideação).
                                    </p>
                                <?php endif; ?>
                                <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="btn-primary-custom">
                                    <i class="fa-solid fa-folder-open"></i>
                                    <span>Abrir Dossiê Completo</span>
                                </a>
                            </div>
                        </div>

                    <?php elseif ($isIncubado): ?>
                        <!-- ESTADO E3 / E4: METAS DA FASE ACTUAL + TAREFAS DO MENTOR -->
                        <div class="card-custom mb-4">
                            <div class="card-header-custom">
                                <div class="card-title-custom">
                                    <i class="fa-solid fa-bullseye"></i>
                                    <span>Metas da Fase: <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <span class="sp-chip sp-chip-ok sp-num"><?= (int)$metasValidadasCount ?> / <?= (int)$totalMetasFase ?> concluídas</span>
                            </div>
                            <div class="card-body-custom p-0">
                                <?php if (empty($metasFase)): ?>
                                    <div class="p-4 text-center text-muted small">
                                        Nenhuma meta activa registada para a fase de <?= htmlspecialchars($faseLabel, ENT_QUOTES, 'UTF-8') ?>.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($metasFase as $m):
                                        $estM = (string)($m['estado'] ?? 'inactiva');
                                        $chipM = 'sp-chip-neutral';
                                        $lblM  = 'Bloqueada';
                                        if ($estM === 'concluida') { $chipM = 'sp-chip-ok'; $lblM = 'Validada'; }
                                        elseif ($estM === 'activa') { $chipM = 'sp-chip-warn'; $lblM = 'Activa'; }
                                        elseif ($estM === 'em_avaliacao') { $chipM = 'sp-chip-warn'; $lblM = 'Em Avaliação'; }
                                        elseif ($estM === 'reprovada') { $chipM = 'sp-chip-err'; $lblM = 'Devolvida'; }
                                        $canSubM = (!$isAdminOrSuper) && in_array($estM, ['activa', 'reprovada'], true);
                                        $payloadM = json_encode([
                                            'id'     => (int)$m['id'],
                                            'titulo' => (string)$m['meta_titulo'],
                                            'tipo'   => (string)$m['evidencia_tipo'],
                                            'desc'   => (string)$m['evidencia_desc'],
                                            'texto'  => (string)($m['evidencia_texto'] ?? ''),
                                            'link'   => (string)($m['evidencia_link'] ?? ''),
                                            'path'   => (string)($m['evidencia_path'] ?? ''),
                                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                                    ?>
                                        <div class="sp-list-row">
                                            <div style="flex:1; min-width:220px;">
                                                <div class="d-flex align-items-center gap-2 mb-1">
                                                    <span class="sp-code fw-bold" style="color:#D97706;">#<?= (int)$m['numero'] ?></span>
                                                    <strong><?= htmlspecialchars((string)$m['meta_titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <span class="sp-chip <?= $chipM ?>"><?= htmlspecialchars($lblM, ENT_QUOTES, 'UTF-8') ?></span>
                                                </div>
                                                <div style="font-size:0.8rem; color:var(--sp-text-sec);">
                                                    <?= htmlspecialchars((string)$m['meta_descricao'], ENT_QUOTES, 'UTF-8') ?>
                                                </div>
                                                <?php if (!empty($m['feedback_mentor'])): ?>
                                                    <div class="mt-1 small" style="color:var(--sp-err-text);">
                                                        <strong>Feedback do Mentor:</strong> <?= htmlspecialchars((string)$m['feedback_mentor'], ENT_QUOTES, 'UTF-8') ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($canSubM): ?>
                                                <button type="button" class="btn-primary-custom py-1 px-3" style="font-size:0.78rem;"
                                                        onclick="abrirSubmeterEvidencia(<?= htmlspecialchars((string)$payloadM, ENT_QUOTES, 'UTF-8') ?>)">
                                                    <i class="fa-solid fa-paper-plane"></i>
                                                    <span>Submeter Evidência</span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TAREFAS DO MENTOR -->
                        <div class="card-custom mb-4">
                            <div class="card-header-custom">
                                <div class="card-title-custom">
                                    <i class="fa-solid fa-clipboard-list"></i>
                                    <span>Tarefas Atribuídas pelo Mentor</span>
                                </div>
                                <span class="sp-code text-muted"><?= count($tarefasPendentes) ?> pendente<?= count($tarefasPendentes) !== 1 ? 's' : '' ?></span>
                            </div>
                            <div class="card-body-custom p-0">
                                <?php if (empty($tarefasPendentes)): ?>
                                    <div class="p-4 text-center text-muted small">
                                        <i class="fa-solid fa-check-circle text-success me-1"></i> Não existem tarefas pendentes atribuídas pelo mentor.
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($tarefasPendentes as $t):
                                        $prazoT = !empty($t['data_limite']) ? date('d/m/Y', strtotime((string)$t['data_limite'])) : 'Sem prazo';
                                        $statusT = (string)($t['status'] ?? 'pendente');
                                    ?>
                                        <div class="sp-list-row">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars((string)$t['titulo'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php if (!empty($t['descricao'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars((string)$t['descricao'], ENT_QUOTES, 'UTF-8') ?></div>
                                                <?php endif; ?>
                                                <div class="small mt-1" style="color:#D97706;"><i class="fa-regular fa-clock me-1"></i>Prazo: <?= htmlspecialchars($prazoT, ENT_QUOTES, 'UTF-8') ?></div>
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!$isAdminOrSuper && $statusT !== 'concluida'): ?>
                                                    <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="m-0">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action" value="atualizar_estado_tarefa">
                                                        <input type="hidden" name="id_tarefa" value="<?= (int)$t['id'] ?>">
                                                        <input type="hidden" name="status" value="concluida">
                                                        <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                                                        <button type="submit" class="btn-primary-custom py-1 px-3" style="font-size:0.75rem; background:#059669;">
                                                            <i class="fa-solid fa-check"></i> Marcar Concluída
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="sp-chip sp-chip-warn"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $statusT)), ENT_QUOTES, 'UTF-8') ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php elseif ($uiState === 'E5'): ?>
                        <!-- ESTADO E5: PROPOSTA REJEITADA -->
                        <div class="card-custom mb-4">
                            <div class="card-header-custom">
                                <div class="card-title-custom">
                                    <i class="fa-solid fa-circle-xmark text-danger"></i>
                                    <span>Decisão Registada sobre a Submissão</span>
                                </div>
                                <span class="sp-chip sp-chip-err">Rejeitado</span>
                            </div>
                            <div class="card-body-custom">
                                <?php if (!empty($ultimaAvaliacao['observacoes'])): ?>
                                    <div class="p-3 mb-3" style="background: var(--sp-err-bg); border: 1px solid var(--sp-err-border); border-radius: 10px;">
                                        <div class="sp-kicker mb-1" style="color:var(--sp-err-text);">Observações da Avaliação</div>
                                        <div><?= nl2br(htmlspecialchars((string)$ultimaAvaliacao['observacoes'], ENT_QUOTES, 'UTF-8')) ?></div>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php if (!$isAdminOrSuper): ?>
                                        <button type="button" class="btn-primary-custom" data-bs-toggle="modal" data-bs-target="#modalProjeto">
                                            <i class="fa-solid fa-plus"></i> Submeter Nova Proposta
                                        </button>
                                    <?php endif; ?>
                                    <a href="<?= htmlspecialchars($hrefFichaProjeto, ENT_QUOTES, 'UTF-8') ?>" class="btn-ghost">
                                        Ver Dossiê Submetido
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- ── ABA 2: PROPOSTA & EQUIPA (INTERATIVA NO PAINEL) ── -->
                <div id="dash-pane-proposta" class="dash-tab-pane d-none">
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <div class="card-title-custom">
                                <i class="fa-solid fa-file-lines"></i>
                                <span>Síntese da Proposta &amp; Equipa</span>
                            </div>
                            <?php if (!$isAdminOrSuper): ?>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn-ghost py-1 px-3" style="font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalAddMembroDash">
                                        <i class="fa-solid fa-user-plus"></i> Convidar Membro
                                    </button>
                                    <button type="button" class="btn-primary-custom py-1 px-3" style="font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalEditarProposta">
                                        <i class="fa-solid fa-pen"></i> Editar
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-body-custom">
                            <div class="mb-4" style="white-space: pre-line; color: var(--sp-text);">
                                <?= htmlspecialchars((string)($ultimoProjeto['descricao'] ?? 'Sem descrição registada.'), ENT_QUOTES, 'UTF-8') ?>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <div class="p-3 h-100" style="background: var(--sp-warn-bg); border: 1px solid var(--sp-warn-border); border-left: 4px solid #D97706; border-radius: 12px;">
                                        <div class="sp-kicker mb-1" style="color:#B45309;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Problema Identificado</div>
                                        <div style="font-size:0.86rem;">
                                            <?= !empty($ultimoProjeto['problema']) ? nl2br(htmlspecialchars((string)$ultimoProjeto['problema'], ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">Ainda não especificado. Clique em Editar Proposta para preencher.</span>' ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="p-3 h-100" style="background: var(--sp-ok-bg); border: 1px solid var(--sp-ok-border); border-left: 4px solid #10B981; border-radius: 12px;">
                                        <div class="sp-kicker mb-1" style="color:#059669;"><i class="fa-solid fa-lightbulb me-1"></i> Solução Proposta</div>
                                        <div style="font-size:0.86rem;">
                                            <?= !empty($ultimoProjeto['solucao']) ? nl2br(htmlspecialchars((string)$ultimoProjeto['solucao'], ENT_QUOTES, 'UTF-8')) : '<span class="text-muted">Ainda não especificada. Clique em Editar Proposta para preencher.</span>' ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="sp-kicker mb-2"><i class="fa-solid fa-users me-1" style="color:#3B82F6;"></i> Membros da Equipa (<?= (int)$numMembros ?>)</div>
                            <div class="row g-2">
                                <?php if (empty($membrosProjeto)): ?>
                                    <div class="col-12">
                                        <div class="p-3 rounded-3 border d-flex justify-content-between align-items-center" style="background:var(--sp-surface-muted);">
                                            <div>
                                                <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>
                                                <span class="sp-chip sp-chip-ok ms-2">Líder / Fundador</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($membrosProjeto as $mb): ?>
                                        <div class="col-md-6">
                                            <div class="p-3 rounded-3 border d-flex justify-content-between align-items-center" style="background:var(--sp-surface-muted);">
                                                <div style="min-width:0;">
                                                    <div class="fw-bold text-truncate" style="font-size:0.86rem;"><?= htmlspecialchars((string)$mb['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                                    <div class="text-muted text-truncate" style="font-size:0.75rem;"><?= htmlspecialchars((string)$mb['email'], ENT_QUOTES, 'UTF-8') ?></div>
                                                </div>
                                                <span class="sp-chip sp-chip-neutral"><?= htmlspecialchars((string)($mb['papel'] ?? 'Membro'), ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ── ABA 3: DOCUMENTOS & PITCH DECK (INTERATIVA NO PAINEL) ── -->
                <div id="dash-pane-documentos" class="dash-tab-pane d-none">
                    <div class="card-custom mb-4">
                        <div class="card-header-custom">
                            <div class="card-title-custom">
                                <i class="fa-solid fa-folder-open"></i>
                                <span>Repositório de Documentos da Startup</span>
                            </div>
                            <?php if (!$isAdminOrSuper): ?>
                                <button type="button" class="btn-primary-custom py-1 px-3" style="font-size:0.78rem;" data-bs-toggle="modal" data-bs-target="#modalUploadDocDash">
                                    <i class="fa-solid fa-cloud-arrow-up"></i> Carregar Ficheiro
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body-custom">
                            <?php if (!empty($ultimoProjeto['pitch_path'])): ?>
                                <div class="p-3 mb-3 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--sp-surface-tint); border: 1px solid var(--sp-warn-border); border-radius: 10px;">
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa-solid fa-file-powerpoint fs-4" style="color:#D97706;"></i>
                                        <div>
                                            <div class="fw-bold" style="font-size:0.88rem;">Pitch Deck Principal da Proposta</div>
                                            <div class="small text-muted">Anexado na ficha principal da startup</div>
                                        </div>
                                    </div>
                                    <span class="sp-chip sp-chip-ok">Anexado</span>
                                </div>
                            <?php endif; ?>

                            <?php if (empty($documentosProjeto)): ?>
                                <div class="sp-empty-line">
                                    <i class="fa-regular fa-file-lines"></i>
                                    <span>Ainda não foram carregados documentos complementares. Utilize o botão "Carregar Ficheiro" para adicionar o seu Pitch ou Plano de Negócio.</span>
                                </div>
                            <?php else: ?>
                                <div class="table-wrapper">
                                    <table class="table-custom">
                                        <thead>
                                            <tr>
                                                <th>Documento</th>
                                                <th>Categoria</th>
                                                <th>Submetido por</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($documentosProjeto as $doc): ?>
                                                <tr>
                                                    <td class="fw-semibold"><i class="fa-regular fa-file me-2 text-warning"></i><?= htmlspecialchars((string)$doc['titulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                                    <td><span class="sp-chip sp-chip-neutral"><?= htmlspecialchars((string)($doc['tipo'] ?? 'Outro'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                                    <td class="text-muted small"><?= htmlspecialchars((string)($doc['quem_submeteu'] ?? 'Utilizador'), ENT_QUOTES, 'UTF-8') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- COLUNA LATERAL (4 COLUNAS): MENTORIA, CANAL DIRETO & ATIVIDADE -->
            <div class="col-lg-4 d-flex flex-column gap-4">

                <!-- CARD 1: ORIENTAÇÃO, MENTOR & AGENDA -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom">
                            <i class="fa-solid fa-user-tie"></i>
                            <span>Acompanhamento &amp; Agenda</span>
                        </div>
                    </div>
                    <div class="card-body-custom py-2 px-3">
                        <!-- Mentor -->
                        <div class="sp-aside-block">
                            <span class="sp-aside-icon"><i class="fa-solid fa-chalkboard-user"></i></span>
                            <div class="sp-aside-body">
                                <div class="sp-kicker">Mentor Designado</div>
                                <?php if ($mentorAtivo): ?>
                                    <div class="fw-bold"><?= htmlspecialchars((string)$mentorAtivo['mentor_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($mentorAtivo['especialidade'])): ?>
                                        <div class="small text-muted"><?= htmlspecialchars((string)$mentorAtivo['especialidade'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($mentorAtivo['mentor_email'])): ?>
                                        <div class="sp-code mt-1 text-muted" style="font-size:0.75rem;"><?= htmlspecialchars((string)$mentorAtivo['mentor_email'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="small text-muted">
                                        <?= $isIncubado ? 'Em atribuição pela coordenação.' : 'Atribuído após aprovação para incubação.' ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reuniões -->
                        <div class="sp-aside-block">
                            <span class="sp-aside-icon" style="background:rgba(16,185,129,0.12); color:#10B981;"><i class="fa-solid fa-calendar-day"></i></span>
                            <div class="sp-aside-body">
                                <div class="sp-kicker">Próxima Sessão / Reunião</div>
                                <?php if (!empty($minhasReunioes)):
                                    $proxR = $minhasReunioes[0];
                                ?>
                                    <div class="fw-bold" style="font-size:0.86rem;"><?= htmlspecialchars((string)$proxR['titulo'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="sp-num small fw-semibold" style="color:#10B981;"><?= date('d/m/Y \à\s H:i', strtotime((string)$proxR['data_reuniao'])) ?></div>
                                <?php elseif ($ultimaSessao): ?>
                                    <div class="small text-muted">Última sessão realizada em <?= date('d/m/Y', strtotime((string)$ultimaSessao['data_sessao'])) ?></div>
                                <?php else: ?>
                                    <div class="small text-muted">Sem reuniões agendadas no momento.</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Reservas de Espaço -->
                        <div class="sp-aside-block">
                            <span class="sp-aside-icon" style="background:rgba(139,92,246,0.12); color:#8B5CF6;"><i class="fa-solid fa-building"></i></span>
                            <div class="sp-aside-body">
                                <div class="sp-kicker">Espaços &amp; Laboratórios</div>
                                <div class="fw-semibold" style="font-size:0.85rem;">
                                    <?= $totalReservasConfirmadas > 0
                                        ? $totalReservasConfirmadas . ' reserva' . ($totalReservasConfirmadas > 1 ? 's' : '') . ' confirmada' . ($totalReservasConfirmadas > 1 ? 's' : '')
                                        : 'Nenhuma reserva activa' ?>
                                </div>
                                <a href="<?= htmlspecialchars($hrefReservas, ENT_QUOTES, 'UTF-8') ?>" class="sp-link mt-1">
                                    <span>Reservar sala ou laboratório</span>
                                    <i class="fa-solid fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- CARD 2: CANAL DIRETO & LINHA DO TEMPO DA STARTUP -->
                <div class="card-custom">
                    <div class="card-header-custom">
                        <div class="card-title-custom">
                            <i class="fa-solid fa-comments"></i>
                            <span>Canal Direto &amp; Atividade</span>
                        </div>
                        <a href="<?= htmlspecialchars($hrefFichaProjeto . ($qsPreview !== '' ? '&tab=comunicacao' : '?tab=comunicacao'), ENT_QUOTES, 'UTF-8') ?>" class="small fw-semibold" style="color:var(--sp-accent); text-decoration:none;">
                            Histórico &rarr;
                        </a>
                    </div>
                    <div class="card-body-custom">
                        <!-- Mensagens recentes -->
                        <?php if (!empty($mensagensRecentes)): ?>
                            <div class="mb-3 d-flex flex-column gap-2" style="max-height:170px; overflow-y:auto;">
                                <?php foreach ($mensagensRecentes as $mr):
                                    $isMe = ((int)$mr['id_usuario'] === (int)$idUsuario);
                                ?>
                                    <div class="p-2 px-3 rounded-3" style="background: <?= $isMe ? 'var(--sp-warn-bg)' : 'var(--sp-surface-muted)' ?>; border: 1px solid var(--sp-border); font-size:0.8rem;">
                                        <div class="fw-bold" style="font-size:0.72rem; color:var(--sp-text-muted);"><?= htmlspecialchars((string)$mr['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <div><?= nl2br(htmlspecialchars((string)$mr['mensagem'], ENT_QUOTES, 'UTF-8')) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$isAdminOrSuper): ?>
                            <form action="/incubadora_ispsn/app/controllers/projeto_action.php" method="POST" class="d-flex gap-2 mb-3">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="enviar_mensagem">
                                <input type="hidden" name="id_projeto" value="<?= (int)$idProj ?>">
                                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                                <input type="text" name="mensagem" class="form-control-custom py-2" style="font-size:0.82rem;" placeholder="Enviar mensagem à coordenação/mentor..." required>
                                <button type="submit" class="btn-primary-custom px-3" title="Enviar">
                                    <i class="fa-solid fa-paper-plane"></i>
                                </button>
                            </form>
                        <?php endif; ?>

                        <div class="sp-kicker mb-2">Registo Cronológico do Projecto</div>
                        <?php foreach ($timelineEventos as $ev): ?>
                            <div class="std-feed-item">
                                <div class="std-feed-dot" style="background: <?= $ev['bg'] ?>; color: <?= $ev['cor'] ?>;">
                                    <i class="fa-solid <?= $ev['icone'] ?>"></i>
                                </div>
                                <div style="flex:1; min-width:0;">
                                    <div class="fw-bold" style="font-size:0.8rem;"><?= htmlspecialchars((string)$ev['titulo'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div style="font-size:0.75rem; color:var(--sp-text-sec);"><?= htmlspecialchars((string)$ev['texto'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="sp-num" style="font-size:0.68rem; color:var(--sp-text-muted); margin-top:2px;">
                                        <?= !empty($ev['data']) ? date('d/m/Y H:i', strtotime((string)$ev['data'])) : '' ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>

        </div>

        <script>
        function alternarAbaDash(paneId, btnEl) {
            document.querySelectorAll('.dash-tab-pane').forEach(function(el) {
                el.classList.add('d-none');
            });
            document.querySelectorAll('.sp-tab-btn').forEach(function(b) {
                b.classList.remove('active');
            });
            var target = document.getElementById('dash-pane-' + paneId);
            if (target) target.classList.remove('d-none');
            if (btnEl) btnEl.classList.add('active');
        }
        </script>
    <?php endif; ?>

</div>

<?php if (!$isAdminOrSuper): ?>
<!-- ══ MODAL 1: SUBMETER NOVA IDEIA DE PROJETO ══ -->
<div class="modal fade" id="modalProjeto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="criar_projeto">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">
                <div class="modal-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-rocket me-2 text-warning" aria-hidden="true"></i>Submeter Nova Ideia de Startup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Nome da Startup *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: AppAngola — Transporte Inteligente">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Tipo *</label>
                            <select name="tipo" class="form-control-custom" required>
                                <option value="startup_tecnologica">Startup Tecnológica</option>
                                <option value="negocio_tradicional">Negócio Tradicional</option>
                                <option value="impacto_social">Impacto Social</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Área Temática</label>
                            <select name="area" class="form-control-custom">
                                <option value="tecnologia">Tecnologia</option>
                                <option value="saude">Saúde</option>
                                <option value="educacao">Educação</option>
                                <option value="agro">Agronegócio</option>
                                <option value="financas">Finanças</option>
                                <option value="outro">Outro</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Descrição da Proposta (mín. 20 caracteres) *</label>
                        <textarea name="descricao" class="form-control-custom" rows="3" required placeholder="Descreva a proposta de valor do projecto..."></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Problema que Resolve</label>
                            <textarea name="problema" class="form-control-custom" rows="2" placeholder="Qual o problema real identificado?"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Solução Proposta</label>
                            <textarea name="solucao" class="form-control-custom" rows="2" placeholder="Como a sua startup resolve esse problema?"></textarea>
                        </div>
                    </div>
                    <div>
                        <label class="form-label-custom">Pitch Deck (PDF, PPT, PPTX — Opcional)</label>
                        <input type="file" name="pitch_ficheiro" class="form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-paper-plane me-1"></i>Submeter Proposta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if ($ultimoProjeto): ?>
<!-- ══ MODAL 2: EDITAR PROPOSTA DA STARTUP ACTUAL ══ -->
<div class="modal fade" id="modalEditarProposta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="editar_proposta">
                <input type="hidden" name="id_projeto" value="<?= (int)$ultimoProjeto['id'] ?>">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php?id_projeto=<?= (int)$ultimoProjeto['id'] ?>">
                <div class="modal-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold m-0"><i class="fa-solid fa-pen-to-square me-2 text-warning" aria-hidden="true"></i>Actualizar Proposta da Startup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Nome da Startup *</label>
                        <input type="text" name="titulo" class="form-control-custom" required value="<?= htmlspecialchars((string)$ultimoProjeto['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Tipo *</label>
                            <select name="tipo" class="form-control-custom" required>
                                <?php foreach ($labelsTipo as $tk => $tl): ?>
                                    <option value="<?= htmlspecialchars($tk, ENT_QUOTES, 'UTF-8') ?>" <?= (($ultimoProjeto['tipo'] ?? '') === $tk) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tl, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Área Temática</label>
                            <select name="area" class="form-control-custom">
                                <?php
                                $areasMap = ['tecnologia' => 'Tecnologia', 'saude' => 'Saúde', 'educacao' => 'Educação', 'agro' => 'Agronegócio', 'financas' => 'Finanças', 'outro' => 'Outro'];
                                foreach ($areasMap as $ak => $al):
                                ?>
                                    <option value="<?= $ak ?>" <?= (($ultimoProjeto['area_tematica'] ?? '') === $ak) ? 'selected' : '' ?>><?= $al ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Descrição Geral *</label>
                        <textarea name="descricao" class="form-control-custom" rows="3" required><?= htmlspecialchars((string)($ultimoProjeto['descricao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label-custom">Problema que Resolve</label>
                            <textarea name="problema" class="form-control-custom" rows="3" placeholder="Qual o problema real identificado?"><?= htmlspecialchars((string)($ultimoProjeto['problema'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label-custom">Solução Proposta</label>
                            <textarea name="solucao" class="form-control-custom" rows="3" placeholder="Como a sua startup resolve esse problema?"><?= htmlspecialchars((string)($ultimoProjeto['solucao'] ?? ''), ENT_QUOTES, 'UTF-8') ?></textarea>
                        </div>
                    </div>
                    <div>
                        <label class="form-label-custom">Actualizar Pitch Deck (PDF, PPT, PPTX — Opcional)</label>
                        <input type="file" name="pitch_ficheiro" class="form-control-custom" accept=".pdf,.ppt,.pptx,.zip">
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL 3: CARREGAR DOCUMENTO DIRETO DO PAINEL ══ -->
<div class="modal fade" id="modalUploadDocDash" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="upload_documento">
                <input type="hidden" name="id_projeto" value="<?= (int)$ultimoProjeto['id'] ?>">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php?id_projeto=<?= (int)$ultimoProjeto['id'] ?>">
                <div class="modal-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-cloud-arrow-up me-2 text-primary"></i>Carregar Documento no Dossiê</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">Título do Documento *</label>
                        <input type="text" name="titulo" class="form-control-custom" required placeholder="Ex: Pitch Deck v2, Canvas do Modelo de Negócio">
                    </div>
                    <div class="mb-3">
                        <label class="form-label-custom">Categoria</label>
                        <select name="tipo" class="form-control-custom">
                            <option value="Pitch">Pitch / Apresentação</option>
                            <option value="Plano de Negócio">Plano de Negócio</option>
                            <option value="Contrato">Documento Legal</option>
                            <option value="Outro">Outro</option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label-custom">Ficheiro (PDF, Office, ZIP ou Imagem — máx. 10 MB) *</label>
                        <input type="file" name="ficheiro" class="form-control-custom" required>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-upload me-1"></i>Carregar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ MODAL 4: CONVIDAR MEMBRO DA EQUIPA DIRETO DO PAINEL ══ -->
<div class="modal fade" id="modalAddMembroDash" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form method="post" action="/incubadora_ispsn/app/controllers/projeto_action.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="adicionar_membro">
                <input type="hidden" name="id_projeto" value="<?= (int)$ultimoProjeto['id'] ?>">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php?id_projeto=<?= (int)$ultimoProjeto['id'] ?>">
                <div class="modal-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="modal-title fw-bold m-0"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Adicionar Membro à Equipa</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body-custom">
                    <div class="mb-3">
                        <label class="form-label-custom">E-mail do Utilizador Registado *</label>
                        <input type="email" name="email" class="form-control-custom" required placeholder="colega@ispsn.org">
                    </div>
                    <div>
                        <label class="form-label-custom">Papel / Função na Startup</label>
                        <select name="papel" class="form-control-custom">
                            <option value="Cofundador">Cofundador</option>
                            <option value="Desenvolvedor">Desenvolvedor</option>
                            <option value="Marketing & Vendas">Marketing &amp; Vendas</option>
                            <option value="Operações & Finanças">Operações &amp; Finanças</option>
                            <option value="Membro">Membro</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom"><i class="fa-solid fa-plus me-1"></i>Adicionar à Equipa</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL 5: SUBMETER EVIDÊNCIA DE META ══ -->
<div class="modal fade" id="modalSubmeterEvidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modal-content-custom">
            <form action="/incubadora_ispsn/app/controllers/metas_action.php" method="POST" enctype="multipart/form-data" class="m-0">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="submeter_evidencia">
                <input type="hidden" name="id_meta_projeto" id="submeterMetaId">
                <input type="hidden" name="redirect" value="/incubadora_ispsn/app/views/dashboard/utilizador.php">

                <div class="modal-header-custom">
                    <h5 class="modal-title fw-bold m-0" id="submeterTituloMeta">Submeter Evidência</h5>
                </div>

                <div class="modal-body-custom">
                    <div class="alert-custom alert-warning py-2 px-3 mb-3">
                        <div><strong>Entregável esperado:</strong> <span id="submeterDescEvidencia"></span></div>
                    </div>

                    <div class="mb-3 text-start">
                        <label class="form-label-custom" for="submeterTextoInput">Relatório/Descrição da Execução *</label>
                        <textarea name="evidencia_texto" id="submeterTextoInput" class="form-control-custom" rows="4" required placeholder="Especifique em detalhe as tarefas que realizou..."></textarea>
                    </div>

                    <div class="mb-3 text-start" id="submeterLinkContainer">
                        <label class="form-label-custom" for="submeterLinkInput">Link Externo (URL) *</label>
                        <input type="url" name="evidencia_link" id="submeterLinkInput" class="form-control-custom" placeholder="https://exemplo.com/doc-ou-drive">
                    </div>

                    <div class="mb-0 text-start" id="submeterFicheiroContainer">
                        <label class="form-label-custom" for="submeterFicheiroInput">Ficheiro Comprovativo (PDF, Zip, Imagem) *</label>
                        <input type="file" name="evidencia_ficheiro" id="submeterFicheiroInput" class="form-control-custom">
                        <div id="submeterFicheiroAtual" class="small text-muted mt-1"></div>
                    </div>
                </div>

                <div class="modal-footer-custom">
                    <button type="button" class="btn-ghost" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-primary-custom">Enviar Evidência</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function abrirSubmeterEvidencia(meta) {
    document.getElementById('submeterMetaId').value = meta.id || '';
    document.getElementById('submeterTituloMeta').textContent = 'Submeter: ' + (meta.titulo || '');
    document.getElementById('submeterDescEvidencia').textContent = (meta.desc || '') + ' (Tipo: ' + String(meta.tipo || '').toUpperCase() + ')';
    document.getElementById('submeterTextoInput').value = meta.texto || '';

    const linkContainer = document.getElementById('submeterLinkContainer');
    const linkInput     = document.getElementById('submeterLinkInput');
    const fileContainer = document.getElementById('submeterFicheiroContainer');
    const fileInput     = document.getElementById('submeterFicheiroInput');
    const fileAtualDiv  = document.getElementById('submeterFicheiroAtual');

    linkInput.value = meta.link || '';
    fileInput.value = '';
    fileAtualDiv.textContent = '';

    if (meta.tipo === 'link') {
        linkContainer.style.display = 'block';
        linkInput.required = true;
        fileContainer.style.display = 'none';
        fileInput.required = false;
    } else if (meta.tipo === 'ficheiro') {
        linkContainer.style.display = 'block';
        linkInput.required = false;
        fileContainer.style.display = 'block';

        if (meta.path) {
            fileInput.required = false;
            fileAtualDiv.textContent = 'Já existe um comprovativo submetido anteriormente.';
        } else {
            fileInput.required = true;
        }
    } else {
        linkContainer.style.display = 'none';
        linkInput.required = false;
        fileContainer.style.display = 'none';
        fileInput.required = false;
    }

    new bootstrap.Modal(document.getElementById('modalSubmeterEvidencia')).show();
}
</script>
<?php endif; ?>

<?php
require_once __DIR__ . '/../partials/_layout_end.php';
?>
