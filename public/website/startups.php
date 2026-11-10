<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Vary: Accept-Encoding');

$css_version     = ASSET_VERSION;
$activePage      = 'startups';
$fallbackGenImg  = '/incubadora_ispsn/assets/img/ilustracao_criatividade.png';
$fallbackBlogImg = '/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png';

// Processo de candidatura aberto
$processo = null;
$resProc = $mysqli->query("SELECT * FROM processos_candidatura WHERE estado='aberto' ORDER BY criado_em DESC LIMIT 1");
if ($resProc) {
    $processo = $resProc->fetch_assoc();
}

// Estatísticas reais sincronizadas em tempo real com a BD
$stats = ['startups' => 0, 'membros' => 0, 'incubados' => 0];
$resStats = $mysqli->query("
    SELECT
        (SELECT COUNT(*) FROM projetos WHERE estado IN ('aprovado','incubado','fundo_investimento','concluido')) AS total_startups,
        (SELECT COUNT(*) FROM usuarios WHERE perfil = 'utilizador' AND activo = 1) AS total_membros,
        (SELECT COUNT(*) FROM projetos WHERE estado IN ('incubado','fundo_investimento','concluido')) AS total_incubados
");
if ($resStats && ($rowSt = $resStats->fetch_assoc())) {
    $stats['startups']  = (int)($rowSt['total_startups']  ?? 0);
    $stats['membros']   = (int)($rowSt['total_membros']   ?? 0);
    $stats['incubados'] = (int)($rowSt['total_incubados'] ?? 0);
}

// Buscar Startups JÁ SELECIONADAS na Base de Dados
$startups = [];
$resProj = $mysqli->query("
    SELECT p.id, p.titulo, p.descricao, p.problema, p.solucao, p.publico_alvo, p.modelo_negocio, p.diferencial,
           p.area_tematica, p.fase, p.tipo, p.estado, p.pontos, p.destaque_publico, p.criado_em,
           u.nome AS autor, u.curso
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.estado IN ('aprovado', 'incubado', 'fundo_investimento', 'concluido')
    ORDER BY p.destaque_publico DESC,
             FIELD(p.estado, 'incubado', 'fundo_investimento', 'concluido', 'aprovado'),
             p.pontos DESC,
             p.criado_em DESC
");
if ($resProj) {
    while ($row = $resProj->fetch_assoc()) {
        $startups[] = $row;
    }
}

$labelsFase = [
    'ideacao'   => 'Ideação',
    'validacao' => 'Validação',
    'mvp'       => 'MVP',
    'tracao'    => 'Tração',
    'mercado'   => 'Mercado',
];
$labelsEstado = [
    'aprovado'           => 'Aprovada',
    'incubado'           => 'Incubada',
    'fundo_investimento' => 'Investimento',
    'concluido'          => 'Graduada',
];
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Startups Incubadas — Incubadora Académica do ISPSN</title>
    <?php require __DIR__ . '/partials/_head_assets.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 font-poppins">
<div class="flex flex-col min-h-screen bg-gray-50">

    <?php require __DIR__ . '/partials/_navbar.php'; ?>

    <main class="flex-1">
        <!-- 1. BANNER HERO COMPACTO (Padrão IPIL) -->
        <section class="relative h-56 sm:h-72 md:h-96 overflow-hidden flex items-center justify-center">
            <img src="<?= htmlspecialchars($fallbackBlogImg) ?>"
                 alt="Banner Startups — Incubadora ISPSN"
                 class="absolute inset-0 w-full h-full object-cover object-center"
                 onerror="this.onerror=null;this.src='<?= $fallbackGenImg ?>';">
            <div class="absolute inset-0 bg-linear-to-b from-black/85 via-black/60 to-black/80"></div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>

            <div class="relative z-10 flex flex-col items-center text-center px-4">
                <img src="/incubadora_ispsn/assets/img/logo_sn_transparent.png"
                     alt="Logo ISPSN"
                     class="w-12 h-12 sm:w-16 sm:h-16 object-contain drop-shadow-2xl mb-4"
                     onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn.jpg';">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-500/40 bg-orange-500/10 backdrop-blur-sm mb-4">
                    <i class="fa-solid fa-rocket text-xs text-orange-400"></i>
                    <span class="text-orange-300 text-xs font-medium tracking-wide">Ecossistema de Inovação ISPSN</span>
                </div>
                <h1 class="text-2xl sm:text-4xl md:text-5xl font-bold font-poppins text-white mb-3 leading-tight">
                    Startups <span class="text-orange-400">Incubadas</span>
                </h1>
                <p class="text-gray-300 text-sm sm:text-base max-w-xl leading-relaxed">
                    Transformamos ideias inovadoras da comunidade académica em negócios sustentáveis de impacto real
                </p>
            </div>
        </section>

        <!-- 2. O QUE É UMA STARTUP INCUBADA? + 3 MÉTRICAS REAIS -->
        <section class="py-16 sm:py-20 bg-white border-b border-gray-100">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Sobre o programa</span>
                    <h2 class="text-3xl sm:text-4xl font-bold text-black mb-5 leading-snug">
                        O que é uma <span class="text-orange-500">Startup Incubada?</span>
                    </h2>
                    <div class="h-px w-16 bg-linear-to-r from-transparent via-orange-500 to-transparent mx-auto mb-6"></div>
                    <p class="text-gray-500 text-sm sm:text-base leading-relaxed max-w-3xl mx-auto">
                        Uma startup incubada no ISPSN é um empreendimento nascido na academia que recebe apoio integral da nossa incubadora — desde mentoria estratégica, diagnóstico de maturidade e infraestrutura até conexões com investidores — para transformar ideias inovadoras em negócios sustentáveis.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-3 gap-4 sm:gap-6 max-w-2xl mx-auto">
                    <div class="group text-center p-5 sm:p-6 bg-gray-50 flex-1 border border-gray-100 rounded-2xl hover:border-orange-200 hover:bg-orange-50/40 transition-all duration-300">
                        <div class="w-10 h-10 bg-orange-50 border border-orange-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-orange-500 group-hover:border-orange-500 transition-all duration-300">
                            <i class="fa-solid fa-arrow-trend-up text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                        </div>
                        <p class="text-2xl sm:text-3xl font-bold text-orange-500 mb-1 leading-none"><?= (int)$stats['startups'] ?></p>
                        <p class="text-xs text-gray-500 leading-snug">Startups Selecionadas</p>
                    </div>
                    <div class="group text-center p-5 sm:p-6 bg-gray-50 flex-1 border border-gray-100 rounded-2xl hover:border-orange-200 hover:bg-orange-50/40 transition-all duration-300">
                        <div class="w-10 h-10 bg-orange-50 border border-orange-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-orange-500 group-hover:border-orange-500 transition-all duration-300">
                            <i class="fa-solid fa-users text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                        </div>
                        <p class="text-2xl sm:text-3xl font-bold text-orange-500 mb-1 leading-none"><?= (int)$stats['membros'] ?></p>
                        <p class="text-xs text-gray-500 leading-snug">Empreendedores</p>
                    </div>
                    <div class="group text-center p-5 sm:p-6 bg-gray-50 flex-1 border border-gray-100 rounded-2xl hover:border-orange-200 hover:bg-orange-50/40 transition-all duration-300">
                        <div class="w-10 h-10 bg-orange-50 border border-orange-100 rounded-xl flex items-center justify-center mx-auto mb-3 group-hover:bg-orange-500 group-hover:border-orange-500 transition-all duration-300">
                            <i class="fa-solid fa-lightbulb text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                        </div>
                        <p class="text-2xl sm:text-3xl font-bold text-orange-500 mb-1 leading-none"><?= (int)$stats['incubados'] ?></p>
                        <p class="text-xs text-gray-500 leading-snug">Startups em Incubação</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. PORTFÓLIO / CARROSSEL DE STARTUPS SELECIONADAS (Sincronizado com a BD) -->
        <section id="portfolio" class="py-16 sm:py-20 bg-gray-50/60">
            <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-10">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Portfólio Oficial</span>
                    <h2 class="text-3xl sm:text-4xl font-bold text-black mb-3">
                        As nossas <span class="text-orange-500">Startups</span>
                    </h2>
                    <div class="h-px w-16 bg-linear-to-r from-transparent via-orange-500 to-transparent mx-auto mb-4"></div>
                    <p class="text-gray-500 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        Conheça os empreendimentos inovadores aprovados e acompanhados pela Incubadora Académica do ISPSN
                    </p>
                </div>

                <?php if (empty($startups)): ?>
                    <div class="group relative max-w-4xl mx-auto">
                        <div class="relative bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                            <div class="grid md:grid-cols-2 gap-0">
                                <div class="relative h-60 sm:h-72 md:h-full min-h-[280px] overflow-hidden bg-gray-100">
                                    <img src="<?= htmlspecialchars($fallbackGenImg) ?>" alt="Incubadora ISPSN" class="w-full h-full object-cover">
                                    <div class="absolute inset-0 bg-linear-to-t from-black/55 via-black/10 to-transparent"></div>
                                    <div class="absolute top-4 left-4">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white text-xs font-bold rounded-full shadow-md shadow-orange-500/30 uppercase tracking-wide">
                                            <i class="fa-solid fa-rocket text-[11px]"></i> Seleções ISPSN
                                        </span>
                                    </div>
                                    <div class="absolute bottom-4 left-4 right-4 flex flex-wrap gap-2">
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-black/50 backdrop-blur-sm text-white text-xs rounded-full border border-white/15">
                                            <i class="fa-solid fa-location-dot text-[11px]"></i> Huambo, Angola
                                        </span>
                                    </div>
                                </div>
                                <div class="p-7 sm:p-8 md:p-10 flex flex-col justify-between">
                                    <div>
                                        <div class="mb-5">
                                            <h3 class="text-2xl sm:text-3xl font-bold text-black mb-1 leading-snug">
                                                A sua Startup pode estar aqui
                                            </h3>
                                            <div class="flex items-center gap-2 mt-2">
                                                <div class="w-8 h-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-full"></div>
                                                <p class="text-orange-500 text-xs font-semibold uppercase tracking-wide">Portfólio Sincronizado em Tempo Real</p>
                                            </div>
                                        </div>
                                        <p class="text-gray-500 text-sm sm:text-base leading-relaxed">
                                            Todas as startups selecionadas e aprovadas pela Comissão Técnica do ISPSN aparecem automaticamente neste portfólio oficial. Submeta a sua candidatura para integrar o próximo ciclo de incubação.
                                        </p>
                                    </div>
                                    <div class="flex flex-col sm:flex-row gap-3 mt-8 pt-5 border-t border-gray-100">
                                        <a href="/incubadora_ispsn/public/website/candidatura.php"
                                           class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 hover:scale-105 transition-all duration-300">
                                            <i class="fa-solid fa-rocket text-xs"></i>
                                            <span>Candidatar a Minha Ideia</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Carrossel Interativo 2 Colunas (Exatamente como incubadora.ipil.ao/startups) -->
                    <div id="startupCarousel" class="relative">
                        <?php foreach ($startups as $idx => $st):
                            $faseTxt   = $labelsFase[$st['fase'] ?? 'ideacao'] ?? ucfirst((string)$st['fase']);
                            $estadoTxt = $labelsEstado[$st['estado'] ?? 'aprovado'] ?? ucfirst((string)$st['estado']);
                            $setorTxt  = ucfirst((string)($st['area_tematica'] ?? 'Tecnologia'));
                            $imgs      = [$fallbackGenImg, $fallbackBlogImg, '/incubadora_ispsn/assets/img/galeria/gal_1777456481.png'];
                            $stImg     = resolver_imagem_site('', $imgs[$idx % count($imgs)]);
                        ?>
                            <div class="startup-slide <?= $idx === 0 ? '' : 'hidden' ?> group relative max-w-4xl mx-auto" data-index="<?= $idx ?>">
                                <div class="absolute -inset-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-2xl blur opacity-0 group-hover:opacity-20 transition duration-500 pointer-events-none"></div>
                                <div class="relative bg-white rounded-2xl border border-gray-100 shadow-sm group-hover:shadow-xl group-hover:shadow-orange-100/30 group-hover:border-orange-200 overflow-hidden transition-all duration-500">
                                    <div class="grid md:grid-cols-2 gap-0">
                                        <div class="relative h-60 sm:h-72 md:h-full min-h-[280px] overflow-hidden bg-gray-100">
                                            <img src="<?= htmlspecialchars($stImg) ?>"
                                                 alt="<?= htmlspecialchars($st['titulo']) ?>"
                                                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                            <div class="absolute inset-0 bg-linear-to-t from-black/55 via-black/10 to-transparent"></div>
                                            <div class="absolute top-4 left-4">
                                                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white text-xs font-bold rounded-full shadow-md shadow-orange-500/30 uppercase tracking-wide">
                                                    <i class="fa-solid fa-rocket text-[11px]"></i>
                                                    <span><?= htmlspecialchars($setorTxt) ?></span>
                                                </span>
                                            </div>
                                            <div class="absolute bottom-4 left-4 right-4 flex flex-wrap gap-2">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-black/50 backdrop-blur-sm text-white text-xs rounded-full border border-white/15">
                                                    <i class="fa-solid fa-location-dot text-[11px]"></i> Huambo — ISPSN
                                                </span>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 bg-black/50 backdrop-blur-sm text-white text-xs rounded-full border border-white/15">
                                                    <i class="fa-solid fa-layer-group text-[11px]"></i> Fase: <?= htmlspecialchars($faseTxt) ?> (<?= htmlspecialchars($estadoTxt) ?>)
                                                </span>
                                            </div>
                                        </div>

                                        <div class="p-7 sm:p-8 md:p-10 flex flex-col justify-between">
                                            <div>
                                                <div class="mb-5">
                                                    <h3 class="text-2xl sm:text-3xl font-bold text-black mb-1 leading-snug group-hover:text-orange-600 transition-colors duration-300">
                                                        <?= htmlspecialchars($st['titulo']) ?>
                                                    </h3>
                                                    <div class="flex items-center gap-2 mt-2">
                                                        <div class="w-8 h-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-full"></div>
                                                        <p class="text-orange-500 text-xs font-semibold uppercase tracking-wide">
                                                            <?= htmlspecialchars($setorTxt) ?> · Fundador: <?= htmlspecialchars($st['autor']) ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                <p class="text-gray-500 text-sm sm:text-base leading-relaxed line-clamp-4">
                                                    <?= htmlspecialchars($st['descricao']) ?>
                                                </p>
                                            </div>

                                            <div class="flex flex-col sm:flex-row gap-3 mt-8 pt-5 border-t border-gray-100">
                                                <button type="button"
                                                        onclick="openStartupModal(<?= $idx ?>)"
                                                        class="flex-1 inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 hover:shadow-orange-300/50 hover:scale-105 transition-all duration-300 cursor-pointer">
                                                    <i class="fa-solid fa-circle-info text-xs"></i>
                                                    <span>Ver Detalhes</span>
                                                </button>
                                                <a href="/incubadora_ispsn/public/website/candidatura.php"
                                                   class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-gray-50 border border-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:border-orange-200 hover:text-orange-600 hover:bg-orange-50 transition-all duration-300">
                                                    <i class="fa-solid fa-rocket text-xs"></i>
                                                    <span>Candidatar</span>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>

                        <!-- Controlos do Carrossel (Padrão IPIL) -->
                        <div class="flex items-center justify-center gap-6 mt-8">
                            <button type="button" onclick="changeSlide(-1)" aria-label="Startup anterior"
                                    class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-linear-to-r from-orange-500 to-amber-500 text-white flex items-center justify-center shadow-md shadow-orange-200 hover:shadow-lg hover:scale-105 transition-all duration-300 cursor-pointer">
                                <i class="fa-solid fa-chevron-left"></i>
                            </button>
                            <div class="flex flex-col items-center gap-2">
                                <button type="button" id="pauseBtn" onclick="toggleCarouselPause()" aria-label="Pausar carrossel"
                                        class="w-10 h-10 rounded-full bg-[#0A0A0A] border border-white/10 text-white flex items-center justify-center shadow-md hover:border-orange-500/40 transition-all duration-300 cursor-pointer">
                                    <i id="pauseIcon" class="fa-solid fa-pause text-xs"></i>
                                </button>
                                <span class="text-xs font-medium text-gray-400">
                                    <span id="currentSlideNum" class="text-orange-500 font-bold">1</span> / <?= count($startups) ?>
                                </span>
                            </div>
                            <button type="button" onclick="changeSlide(1)" aria-label="Próxima startup"
                                    class="w-12 h-12 sm:w-14 sm:h-14 rounded-full bg-linear-to-r from-orange-500 to-amber-500 text-white flex items-center justify-center shadow-md shadow-orange-200 hover:shadow-lg hover:scale-105 transition-all duration-300 cursor-pointer">
                                <i class="fa-solid fa-chevron-right"></i>
                            </button>
                        </div>

                        <div class="flex justify-center gap-2 mt-5">
                            <?php foreach ($startups as $idx => $_s): ?>
                                <button type="button" onclick="goToSlide(<?= $idx ?>)"
                                        class="slide-dot h-1.5 rounded-full transition-all duration-300 <?= $idx === 0 ? 'w-8 bg-orange-500' : 'w-1.5 bg-gray-200 hover:bg-orange-300' ?>"></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 4. POR QUE ESCOLHER A NOSSA INCUBADORA? (3 Cards) -->
        <section class="py-20 sm:py-28 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">As nossas vantagens</span>
                    <h2 class="text-3xl sm:text-4xl font-bold text-black mb-4">
                        Por que escolher a nossa <span class="text-orange-500">Incubadora?</span>
                    </h2>
                    <div class="h-px w-16 bg-linear-to-r from-transparent via-orange-500 to-transparent mx-auto mb-4"></div>
                    <p class="text-gray-500 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        Oferecemos um ecossistema completo para o sucesso da sua startup no ISPSN
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                    <?php
                    $vantagens = [
                        ['icon' => 'fa-handshake',     'title' => 'Nossa Missão',           'desc' => 'Impulsionar o crescimento de startups inovadoras nascidas no ISPSN, promovendo desenvolvimento tecnológico e económico sustentável em Angola.'],
                        ['icon' => 'fa-screwdriver-wrench', 'title' => 'Apoio Oferecido',   'desc' => 'Mentoria especializada, diagnóstico de maturidade por fases, salas de trabalho e suporte integral para o desenvolvimento do seu negócio.'],
                        ['icon' => 'fa-network-wired', 'title' => 'Parceiros Estratégicos', 'desc' => 'Colaboramos com departamentos académicos, investidores e empresas para criar um ecossistema de inovação robusto.'],
                    ];
                    foreach ($vantagens as $v):
                    ?>
                        <div class="group">
                            <div class="bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:shadow-orange-100/30 hover:border-orange-200 transition-all duration-400 h-full flex flex-col">
                                <div class="w-14 h-14 bg-orange-50 border border-orange-100 rounded-2xl flex items-center justify-center mb-6 group-hover:bg-orange-500 group-hover:border-orange-500 transition-all duration-300">
                                    <i class="fa-solid <?= $v['icon'] ?> text-xl text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                                </div>
                                <h3 class="text-lg font-bold text-black mb-3"><?= $v['title'] ?></h3>
                                <p class="text-gray-500 text-sm leading-relaxed flex-1"><?= $v['desc'] ?></p>
                                <div class="mt-6 h-px bg-gray-100 overflow-hidden rounded-full">
                                    <div class="h-full w-0 group-hover:w-full bg-linear-to-r from-orange-500 to-amber-400 transition-all duration-700 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 5. PASSO A PASSO / COMO FUNCIONA A INCUBAÇÃO (4 Etapas com Linha Conectora) -->
        <section id="processo" class="py-20 sm:py-28 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Passo a passo</span>
                    <h2 class="text-3xl sm:text-4xl font-bold text-black mb-4">
                        Como funciona a <span class="text-orange-500">Incubação</span>
                    </h2>
                    <div class="h-px w-16 bg-linear-to-r from-transparent via-orange-500 to-transparent mx-auto mb-4"></div>
                    <p class="text-gray-500 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        Um processo estruturado em 4 etapas para o sucesso da sua startup no ISPSN
                    </p>
                </div>

                <div class="relative grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 lg:gap-5">
                    <div class="hidden lg:block absolute top-10 left-[calc(12.5%+20px)] right-[calc(12.5%+20px)] h-px bg-linear-to-r from-orange-200 via-orange-400 to-orange-200 pointer-events-none"></div>

                    <?php
                    $etapas = [
                        ['icon' => 'fa-paper-plane',    'title' => 'Submissão',  'desc' => 'Submeta a sua ideia ou projeto no formulário de candidatura online.'],
                        ['icon' => 'fa-magnifying-glass', 'title' => 'Avaliação', 'desc' => 'A Comissão Técnica analisa o potencial, viabilidade e impacto do projeto.'],
                        ['icon' => 'fa-graduation-cap', 'title' => 'Mentoria',   'desc' => 'Acompanhamento contínuo por mentores especialistas e evolução por fases.'],
                        ['icon' => 'fa-rocket',         'title' => 'Lançamento', 'desc' => 'Lançamos juntos o seu negócio no mercado com ligação a investidores.'],
                    ];
                    foreach ($etapas as $idx => $et):
                    ?>
                        <div class="group relative">
                            <div class="bg-white border border-gray-100 rounded-2xl p-7 text-center shadow-sm hover:shadow-lg hover:shadow-orange-100/30 hover:border-orange-200 transition-all duration-400">
                                <div class="relative w-20 h-20 mx-auto mb-5">
                                    <div class="absolute inset-0 rounded-full bg-orange-50 border border-orange-100 group-hover:bg-orange-500 group-hover:border-orange-500 transition-all duration-400"></div>
                                    <span class="absolute -top-2 -right-1 w-6 h-6 rounded-full bg-[#0A0A0A] text-white text-xs font-bold flex items-center justify-center border-2 border-white z-10">
                                        <?= $idx + 1 ?>
                                    </span>
                                    <div class="relative h-full flex items-center justify-center">
                                        <i class="fa-solid <?= $et['icon'] ?> text-2xl text-orange-500 group-hover:text-white transition-colors duration-400"></i>
                                    </div>
                                </div>
                                <h3 class="text-base font-bold text-black mb-2 group-hover:text-orange-600 transition-colors duration-300"><?= $et['title'] ?></h3>
                                <p class="text-gray-500 text-xs sm:text-sm leading-relaxed"><?= $et['desc'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 6. SECÇÃO ESCURA CTA: TEM UMA IDEIA INOVADORA? (Padrão IPIL + Edital ISPSN) -->
        <section class="py-20 sm:py-28 bg-[#0A0A0A] relative overflow-hidden">
            <div class="absolute top-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-[600px] h-[300px] bg-orange-500/6 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 right-0 w-80 h-80 bg-orange-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-2 gap-12 lg:gap-16 items-center">
                    <div>
                        <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-500/30 bg-orange-500/10 mb-6">
                            <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                            <span class="text-orange-400 text-xs font-medium tracking-wide"><?= $processo ? 'Candidaturas abertas' : 'Programa de Incubação ISPSN' ?></span>
                        </div>
                        <h2 class="text-3xl sm:text-4xl md:text-5xl font-bold text-white mb-5 leading-tight">
                            Tem uma ideia <span class="text-orange-400">inovadora?</span>
                        </h2>
                        <p class="text-gray-400 text-sm sm:text-base leading-relaxed mb-8">
                            Junte-se ao programa de incubação do ISPSN e transforme a sua ideia num projeto de sucesso. A nossa equipa técnica e mentores estão prontos para apoiá-lo em cada etapa do caminho.
                        </p>

                        <ul class="space-y-3 mb-10">
                            <?php
                            $beneficios = [
                                'Mentoria com docentes e especialistas de mercado',
                                'Acesso a rede de parceiros e investidores',
                                'Infraestrutura académica e suporte técnico',
                                'Apoio na validação de MVP e modelo de negócio',
                            ];
                            foreach ($beneficios as $ben):
                            ?>
                                <li class="flex items-center gap-3 text-sm text-gray-300">
                                    <i class="fa-regular fa-circle-check text-orange-500 shrink-0"></i>
                                    <span><?= $ben ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <div class="flex flex-col sm:flex-row gap-4">
                            <a href="/incubadora_ispsn/public/website/candidatura.php"
                               class="inline-flex items-center justify-center gap-2.5 px-8 py-3.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl shadow-lg shadow-orange-500/25 hover:shadow-orange-500/40 transition-all duration-300 text-sm">
                                <i class="fa-solid fa-rocket text-xs"></i>
                                <span>Submeter Candidatura</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                            <a href="#processo"
                               class="inline-flex items-center justify-center gap-2 text-sm text-gray-400 hover:text-white transition-colors duration-300 px-6 py-3.5 border border-white/10 rounded-xl hover:border-white/20">
                                <span>Ver o processo</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>

                    <div class="relative">
                        <div class="absolute -inset-0.5 bg-linear-to-br from-orange-500/40 to-amber-500/20 rounded-2xl blur-md pointer-events-none"></div>
                        <div class="relative bg-white/5 border border-white/10 rounded-2xl p-8 sm:p-10 backdrop-blur-sm">
                            <div class="text-center mb-8">
                                <p class="text-xs font-semibold tracking-widest text-orange-400 uppercase mb-2">Ciclo Académico</p>
                                <p class="text-4xl font-bold text-white mb-1"><?= date('Y') ?></p>
                                <p class="text-gray-400 text-sm"><?= $processo ? htmlspecialchars($processo['titulo'] ?? $processo['nome'] ?? 'Candidaturas em aberto') : 'Inscrições para Estudantes e Pré-licenciados' ?></p>
                            </div>

                            <div class="space-y-4">
                                <div class="flex items-center justify-between py-3 border-b border-white/8">
                                    <span class="text-gray-400 text-sm">Público-Alvo</span>
                                    <span class="text-white text-sm font-semibold">Estudantes &amp; Pré-licenciados ISPSN</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-white/8">
                                    <span class="text-gray-400 text-sm">Etapas de Maturidade</span>
                                    <span class="text-white text-sm font-semibold">5 Fases (Ideação ao Mercado)</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-white/8">
                                    <span class="text-gray-400 text-sm">Modalidade</span>
                                    <span class="text-white text-sm font-semibold">Presencial &amp; Híbrido</span>
                                </div>
                                <div class="flex items-center justify-between py-3 border-b border-white/8">
                                    <span class="text-gray-400 text-sm">Investimento</span>
                                    <span class="text-white text-sm font-semibold">Gratuito</span>
                                </div>
                            </div>

                            <a href="/incubadora_ispsn/public/website/candidatura.php"
                               class="mt-8 w-full inline-flex items-center justify-center gap-2 py-3.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl transition-all duration-300 text-sm shadow-lg shadow-orange-500/20">
                                <span>Candidatar-me agora</span>
                                <i class="fa-solid fa-arrow-right text-xs"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- MODAL DE DETALHES DA STARTUP (Padrão IPIL) -->
    <?php if (!empty($startups)): ?>
    <div id="startupModal" class="hidden fixed inset-0 z-50 overflow-y-auto">
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" onclick="closeStartupModal()"></div>
        <div class="flex min-h-full items-center justify-center p-4">
            <div class="relative bg-white rounded-2xl w-full max-w-4xl overflow-hidden shadow-2xl" onclick="event.stopPropagation()">
                <div class="relative h-52 sm:h-64 overflow-hidden bg-gray-100">
                    <img id="modalStImg" src="<?= htmlspecialchars($fallbackGenImg) ?>" alt="Startup" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-linear-to-t from-black/75 via-black/25 to-transparent"></div>
                    <button type="button" onclick="closeStartupModal()"
                            class="absolute top-4 right-4 w-9 h-9 flex items-center justify-center bg-white/15 backdrop-blur-sm border border-white/20 rounded-full text-white hover:bg-orange-500 hover:border-orange-500 transition-all duration-300 cursor-pointer">
                        <i class="fa-solid fa-xmark text-sm"></i>
                    </button>
                    <div class="absolute top-4 left-4">
                        <span id="modalStSetor" class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white text-xs font-bold rounded-full shadow-md shadow-orange-500/30 uppercase tracking-wide"></span>
                    </div>
                    <div class="absolute bottom-5 left-5 right-14">
                        <h2 id="modalStTitle" class="text-2xl sm:text-3xl font-bold text-white leading-snug"></h2>
                        <p id="modalStAutor" class="text-white/75 text-sm mt-1"></p>
                    </div>
                </div>
                <div class="max-h-[65vh] overflow-y-auto p-6 sm:p-8 space-y-6">
                    <div>
                        <h3 class="text-sm font-bold text-black uppercase tracking-wider mb-2">Sobre a Startup</h3>
                        <p id="modalStDesc" class="text-gray-600 text-sm sm:text-base leading-relaxed"></p>
                    </div>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <div class="p-5 bg-orange-50 border-l-4 border-orange-500 rounded-r-xl">
                            <h4 class="text-xs font-bold text-black uppercase mb-1.5">Problema que Soluciona</h4>
                            <p id="modalStProb" class="text-gray-600 text-sm leading-relaxed"></p>
                        </div>
                        <div class="p-5 bg-gray-50 border border-gray-100 rounded-xl">
                            <h4 class="text-xs font-bold text-black uppercase mb-1.5">Solução Proposta</h4>
                            <p id="modalStSol" class="text-gray-600 text-sm leading-relaxed"></p>
                        </div>
                    </div>
                </div>
                <div class="border-t border-gray-100 p-5 sm:p-6 bg-gray-50/60 flex items-center justify-between">
                    <span class="text-xs text-gray-500">Incubadora Académica do ISPSN</span>
                    <button type="button" onclick="closeStartupModal()"
                            class="px-5 py-2.5 bg-gray-200 text-gray-700 text-sm font-semibold rounded-xl hover:bg-gray-300 transition-colors cursor-pointer">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    const startupsData = <?= json_encode(array_map(function($s) use ($fallbackGenImg) {
        return [
            'titulo'    => $s['titulo'],
            'descricao' => $s['descricao'],
            'problema'  => $s['problema'] ?: 'Em desenvolvimento pela equipa.',
            'solucao'   => $s['solucao'] ?: 'Solução em validação técnica na Incubadora ISPSN.',
            'setor'     => ucfirst((string)($s['area_tematica'] ?? 'Tecnologia')),
            'autor'     => 'Fundador: ' . ($s['autor'] ?? 'Estudante ISPSN'),
            'imagem'    => $fallbackGenImg,
        ];
    }, $startups), JSON_UNESCAPED_UNICODE) ?>;

    let currentSlide = 0;
    let isCarouselPaused = false;
    const slides = document.querySelectorAll('.startup-slide');
    const dots = document.querySelectorAll('.slide-dot');
    const slideNumEl = document.getElementById('currentSlideNum');

    function goToSlide(index) {
        if (!slides.length) return;
        currentSlide = (index + slides.length) % slides.length;
        slides.forEach((s, i) => s.classList.toggle('hidden', i !== currentSlide));
        dots.forEach((d, i) => {
            d.className = 'slide-dot h-1.5 rounded-full transition-all duration-300 ' + (i === currentSlide ? 'w-8 bg-orange-500' : 'w-1.5 bg-gray-200 hover:bg-orange-300');
        });
        if (slideNumEl) slideNumEl.textContent = (currentSlide + 1);
    }

    function changeSlide(delta) {
        goToSlide(currentSlide + delta);
    }

    function toggleCarouselPause() {
        isCarouselPaused = !isCarouselPaused;
        const icon = document.getElementById('pauseIcon');
        if (icon) icon.className = isCarouselPaused ? 'fa-solid fa-play text-xs ml-0.5' : 'fa-solid fa-pause text-xs';
    }

    setInterval(() => {
        if (!isCarouselPaused && slides.length > 1) {
            changeSlide(1);
        }
    }, 7000);

    function openStartupModal(idx) {
        const item = startupsData[idx];
        if (!item) return;
        isCarouselPaused = true;
        document.getElementById('modalStTitle').textContent = item.titulo;
        document.getElementById('modalStAutor').textContent = item.autor;
        document.getElementById('modalStSetor').textContent = item.setor;
        document.getElementById('modalStDesc').textContent = item.descricao;
        document.getElementById('modalStProb').textContent = item.problema;
        document.getElementById('modalStSol').textContent = item.solucao;
        document.getElementById('startupModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function closeStartupModal() {
        document.getElementById('startupModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
        isCarouselPaused = false;
    }
    </script>
    <?php endif; ?>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
</div>
</body>
</html>

