<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Vary: Accept-Encoding');

$css_version     = ASSET_VERSION;
$activePage      = 'inicio';
$fallbackBlogImg = '/incubadora_ispsn/assets/img/hero_ispsn_real.jpg';
$fallbackGenImg  = '/incubadora_ispsn/assets/img/ilustracao_criatividade.png';

// 1. Processo de candidatura aberto
$processo = null;
$resProc = $mysqli->query("SELECT * FROM processos_candidatura WHERE estado='aberto' ORDER BY criado_em DESC LIMIT 1");
if ($resProc) {
    $processo = $resProc->fetch_assoc();
}

// 2. Estatísticas reais sincronizadas em tempo real com a Base de Dados do Sistema
$stats = ['startups' => 0, 'mentores' => 0, 'incubados' => 0, 'membros' => 0];
$resStats = $mysqli->query("
    SELECT
        (
            (SELECT COUNT(*) FROM candidaturas WHERE estado IN ('selecionado','convocado','validado_presencial','convite_enviado','registado')) +
            (SELECT COUNT(*) FROM projetos WHERE estado IN ('aprovado','incubado','fundo_investimento','concluido'))
        ) AS total_startups,
        (SELECT COUNT(*) FROM usuarios WHERE perfil = 'mentor' AND activo = 1) AS total_mentores,
        (
            (SELECT COUNT(*) FROM candidaturas WHERE estado IN ('validado_presencial','registado')) +
            (SELECT COUNT(*) FROM projetos WHERE estado IN ('incubado','fundo_investimento','concluido'))
        ) AS total_incubados,
        (SELECT COUNT(*) FROM usuarios WHERE perfil = 'utilizador' AND activo = 1) AS total_membros
");
if ($resStats && ($rowSt = $resStats->fetch_assoc())) {
    $stats['startups']  = (int)($rowSt['total_startups']  ?? 0);
    $stats['mentores']  = (int)($rowSt['total_mentores']  ?? 0);
    $stats['incubados'] = (int)($rowSt['total_incubados'] ?? 0);
    $stats['membros']   = (int)($rowSt['total_membros']   ?? 0);
}

// 3. Buscar Startups JÁ SELECIONADAS (Candidaturas selecionadas/convocadas + Projetos incubados/aprovados)
$projetos = [];

// 3.1 Candidaturas Selecionadas ou Convocadas
$resCand = $mysqli->query("
    SELECT 
        c.id,
        c.titulo_ideia AS titulo,
        COALESCE(NULLIF(c.descricao_ideia, ''), c.solucao, c.problema, 'Startup selecionada no processo de incubação do ISPSN.') AS descricao,
        c.area_tematica,
        'ideacao' AS fase,
        'Inovação' AS tipo,
        c.estado,
        100 AS pontos,
        1 AS destaque_publico,
        c.nome AS autor,
        c.criado_em,
        'candidatura' AS origem
    FROM candidaturas c
    WHERE c.estado IN ('selecionado', 'convocado', 'validado_presencial', 'convite_enviado', 'registado')
    ORDER BY FIELD(c.estado, 'registado', 'validado_presencial', 'convocado', 'convite_enviado', 'selecionado'),
             c.criado_em DESC
    LIMIT 6
");
if ($resCand) {
    while ($row = $resCand->fetch_assoc()) {
        $projetos[] = $row;
    }
}

// 3.2 Projetos Aprovados / Incubados
$limRestante = max(0, 6 - count($projetos));
if ($limRestante > 0) {
    $resProj = $mysqli->query("
        SELECT p.id, p.titulo, p.descricao, p.area_tematica, p.fase, p.tipo, p.estado, p.pontos, p.destaque_publico, u.nome AS autor, p.criado_em, 'projeto' AS origem
        FROM projetos p
        JOIN usuarios u ON u.id = p.criado_por
        WHERE p.estado IN ('aprovado', 'incubado', 'fundo_investimento', 'concluido')
        ORDER BY p.destaque_publico DESC,
                 FIELD(p.estado, 'incubado', 'fundo_investimento', 'concluido', 'aprovado'),
                 p.pontos DESC,
                 p.criado_em DESC
        LIMIT {$limRestante}
    ");
    if ($resProj) {
        while ($row = $resProj->fetch_assoc()) {
            $projetos[] = $row;
        }
    }
}

// 4. Publicações recentes
$noticias = [];
$resNot = $mysqli->query("SELECT * FROM publicacoes_website WHERE status='publicado' ORDER BY criado_em DESC LIMIT 3");
if ($resNot) {
    while ($row = $resNot->fetch_assoc()) {
        $noticias[] = $row;
    }
}

// 5. Galeria
$galeria = [];
$resGal = $mysqli->query("SELECT * FROM galeria_website WHERE ativo=1 ORDER BY ordem ASC, criado_em DESC LIMIT 6");
if ($resGal) {
    while ($row = $resGal->fetch_assoc()) {
        $galeria[] = $row;
    }
}

// Configurações dinâmicas ISPSN
$heroBadge     = get_config('hero_badge', 'Inovação · Empreendedorismo · Tecnologia');
$heroSubtitulo = get_config('hero_subtitulo', 'Aceleramos o talento académico angolano através de mentoria, recursos e um ecossistema focado no sucesso empresarial.');
$textoMissao   = get_config('missao', 'Promover o desenvolvimento das competências profissionais, científicas e tecnológicas dos futuros líderes, através de um ensino superior inovador e de qualidade, fundado nos valores do humanismo e da responsabilidade individual.');
$textoVisao    = get_config('visao', 'Promover ensino de qualidade e rigor no âmbito interdisciplinar, a investigação, a formação avançada e a extensão universitária.');
$heroImg       = resolver_imagem_site(get_config('hero_imagem', ''), $fallbackBlogImg);

$labelsFase = [
    'ideacao'   => 'Ideação',
    'validacao' => 'Validação',
    'mvp'       => 'MVP',
    'tracao'    => 'Tração',
    'mercado'   => 'Mercado',
];
$labelsEstado = [
    'selecionado'        => 'Selecionada',
    'convocado'          => 'Convocada',
    'validado_presencial'=> 'Admitida',
    'convite_enviado'    => 'Selecionada',
    'registado'          => 'Incubada',
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
    <title><?= htmlspecialchars(get_config('site_titulo', 'Incubadora Académica do ISPSN')) ?></title>
    <meta name="description" content="<?= htmlspecialchars($heroSubtitulo) ?>">
    <?php require __DIR__ . '/partials/_head_assets.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 font-poppins">
<div class="flex flex-col min-h-screen bg-gray-50">

    <?php require __DIR__ . '/partials/_navbar.php'; ?>

    <main class="flex-1">
        <!-- 1. HERO SECTION (Padrão IPIL + Imagem Real do Stand ISPSN) -->
        <section id="home" class="relative flex flex-col items-center justify-center text-white text-center overflow-hidden min-h-screen px-4">
            <img src="<?= htmlspecialchars($heroImg) ?>?v=<?= $css_version ?>"
                 alt="Instituto Superior Politécnico Sol Nascente — ISPSN"
                 class="absolute inset-0 w-full h-full object-cover object-center"
                 onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/hero_ispsn_real.jpg';">
            <div class="absolute inset-0 bg-linear-to-b from-black/85 via-black/60 to-black/80"></div>
            <!-- Aura Sol Nascente inspirada nos raios dourados do logótipo oficial -->
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[300px] bg-orange-500/15 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>

            <div class="relative z-10 max-w-3xl px-4 sm:px-6 py-20">
                <div class="flex items-center justify-center mb-6">
                    <div class="relative">
                        <div class="absolute inset-0 bg-amber-400/20 rounded-full blur-2xl pointer-events-none"></div>
                        <img src="/incubadora_ispsn/assets/img/logo_sn_dark_bg.png"
                             alt="Incubadora de Empresas Sol Nascente — ISPSN"
                             class="relative h-20 sm:h-24 w-auto object-contain drop-shadow-2xl"
                             onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn_transparent.png';">
                    </div>
                </div>

                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-500/40 bg-orange-500/10 backdrop-blur-sm mb-6">
                    <span class="w-2 h-2 rounded-full bg-orange-400 animate-pulse"></span>
                    <span class="text-orange-300 text-xs sm:text-sm font-medium tracking-wide"><?= htmlspecialchars($heroBadge) ?></span>
                </div>

                <h1 class="text-3xl sm:text-4xl md:text-5xl lg:text-6xl font-bold mb-5 leading-tight tracking-tight font-poppins">
                    Incubadora Académica <span class="text-orange-400">do ISPSN</span>
                </h1>

                <p class="text-sm sm:text-base md:text-lg mb-10 text-gray-300 leading-relaxed max-w-2xl mx-auto">
                    <?= htmlspecialchars($heroSubtitulo) ?>
                </p>

                <div class="flex flex-col sm:flex-row gap-4 items-center justify-center">
                    <a href="/incubadora_ispsn/public/website/candidatura.php"
                       class="flex items-center gap-2 px-7 py-3.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl shadow-lg shadow-orange-500/30 transition-all duration-300 hover:scale-105 hover:-translate-y-0.5 cursor-pointer text-sm sm:text-base w-full sm:w-auto justify-center">
                        <i class="fa-solid fa-rocket text-xs"></i>
                        <span><?= $processo ? 'Candidatar ao Edital Aberto' : 'Submeter Candidatura' ?></span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                    <a href="/incubadora_ispsn/public/website/startups.php"
                       class="flex items-center justify-center gap-2 px-7 py-3.5 bg-white/10 backdrop-blur-sm hover:bg-white/20 text-white font-semibold rounded-xl border border-white/20 transition-all duration-300 hover:scale-105 cursor-pointer text-sm sm:text-base w-full sm:w-auto">
                        <span>Startups Selecionadas (<?= (int)$stats['startups'] ?>)</span>
                    </a>
                </div>
            </div>

            <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-1 pointer-events-none">
                <span class="text-white/40 text-xs tracking-widest uppercase">scroll</span>
                <div class="w-px h-8 bg-linear-to-b from-white/40 to-transparent"></div>
            </div>
        </section>

        <!-- 2. NOSSA ESTRUTURA & ADN DO SUCESSO ACADÉMICO (2 Colunas + 4 Mini-Cards) -->
        <section id="adn" class="relative bg-white py-20 sm:py-28 overflow-hidden">
            <div class="absolute -top-20 -right-20 w-80 h-80 bg-orange-100 rounded-full blur-3xl opacity-60 pointer-events-none"></div>
            <div class="relative max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col-reverse md:flex-row items-center gap-12 lg:gap-20">
                    <div class="w-full md:w-1/2 space-y-6">
                        <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase">Nosso Conceito</span>
                        <h2 class="text-3xl sm:text-4xl font-bold text-black leading-snug">
                            O ADN do <span class="text-orange-500">Sucesso Académico</span>
                        </h2>
                        <p class="text-gray-600 leading-relaxed text-sm sm:text-base">
                            <span class="text-orange-500 font-semibold">Na Incubadora Académica do ISPSN</span>, transformamos o conhecimento académico e científico em valor real de mercado. O nosso ecossistema no Huambo foi desenhado para apoiar estudantes e pré-licenciados em todas as etapas do negócio.
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <?php
                            $adnItems = [
                                ['icon' => 'fa-puzzle-piece',  'title' => 'Criatividade',   'desc' => 'Soluções inovadoras e científicas para problemas reais.'],
                                ['icon' => 'fa-chart-line',    'title' => 'Oportunidade',   'desc' => 'Acesso direto a mercados, parceiros e investidores.'],
                                ['icon' => 'fa-brain',         'title' => 'Conhecimento',   'desc' => 'Mentoria técnica e acompanhamento de alto nível académico.'],
                                ['icon' => 'fa-shield-halved', 'title' => 'Resiliência',    'desc' => 'Suporte estruturado desde a ideação até à entrada no mercado.'],
                            ];
                            foreach ($adnItems as $item):
                            ?>
                                <div class="flex gap-4 items-start bg-gray-50 border border-gray-100 rounded-2xl p-5 hover:shadow-md hover:border-orange-200 transition-all duration-300 group">
                                    <div class="bg-orange-500 group-hover:bg-orange-600 p-2.5 rounded-xl shrink-0 transition-colors duration-300 text-white flex items-center justify-center w-10 h-10">
                                        <i class="fa-solid <?= $item['icon'] ?> text-sm"></i>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-black mb-1"><?= $item['title'] ?></h3>
                                        <p class="text-gray-500 text-xs leading-relaxed"><?= $item['desc'] ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="pt-2 flex flex-wrap gap-3">
                            <a href="/incubadora_ispsn/public/website/sobre.php"
                               class="inline-flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white px-6 py-3 rounded-xl font-semibold shadow-md shadow-orange-200 transition-all duration-300 hover:scale-105 hover:-translate-y-0.5 text-sm sm:text-base">
                                <span>Conheça a nossa história</span>
                                <i class="fa-solid fa-building-columns text-xs"></i>
                            </a>
                            <a href="/incubadora_ispsn/public/website/candidatura.php"
                               class="inline-flex items-center gap-2 bg-gray-100 hover:bg-orange-50 text-gray-800 hover:text-orange-600 border border-gray-200 hover:border-orange-200 px-6 py-3 rounded-xl font-semibold transition-all duration-300 text-sm sm:text-base">
                                <i class="fa-solid fa-rocket text-xs text-orange-500"></i>
                                <span>Inscrever Ideia</span>
                            </a>
                        </div>
                    </div>

                    <div class="w-full md:w-1/2 relative">
                        <div class="absolute -bottom-6 -left-6 w-48 h-48 bg-orange-500/10 rounded-full blur-2xl pointer-events-none"></div>
                        <div class="relative rounded-2xl overflow-hidden ring-1 ring-black/5 shadow-xl bg-white p-3 sm:p-5">
                            <img src="/incubadora_ispsn/assets/img/ilustracao_criatividade.png?v=<?= $css_version ?>"
                                 alt="Inovação e Criatividade — Incubadora Sol Nascente ISPSN"
                                 class="w-full h-auto max-h-[420px] object-contain mx-auto hover:scale-105 transition-transform duration-700">
                            <div class="mt-3 bg-black/85 backdrop-blur-md text-white px-4 py-2.5 rounded-xl border border-white/15 flex items-center gap-3">
                                <img src="/incubadora_ispsn/assets/img/logo_sn_bulb_dark.png" alt="SN" class="w-8 h-8 object-contain shrink-0">
                                <div>
                                    <p class="text-[10px] text-amber-400 font-semibold tracking-wider">HONOR · LABOR · MERITUM</p>
                                    <p class="text-xs font-bold">Incubadora de Empresas Sol Nascente</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. POR QUE NOS ESCOLHER / NOSSOS DIFERENCIAIS (Grelha 3x2 de 6 Cards) -->
        <section class="py-20 sm:py-28 bg-white border-t border-gray-100">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Por que nos escolher</span>
                    <h2 class="text-3xl sm:text-4xl font-bold text-black mb-4">
                        Nossos <span class="text-orange-500">Diferenciais</span>
                    </h2>
                    <p class="text-gray-500 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        O que torna o ecossistema de inovação e empreendedorismo do Instituto Superior Politécnico Sol Nascente único
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    <?php
                    $diferenciais = [
                        ['icon' => 'fa-network-wired',  'title' => 'Rede Colaborativa',    'desc' => 'Conectamos estudantes, mentores e investidores para criar oportunidades reais de negócio.', 'accent' => 'bg-orange-50 text-orange-500 border-orange-100'],
                        ['icon' => 'fa-chart-pie',      'title' => 'Apoio Estratégico',    'desc' => 'Orientação contínua em validação de modelo de negócio, gestão, tecnologia e finanças.',       'accent' => 'bg-blue-50 text-blue-500 border-blue-100'],
                        ['icon' => 'fa-crosshairs',     'title' => 'Foco na Inovação',     'desc' => 'Soluções criativas, científicas e tecnológicas com impacto social e económico sustentável.', 'accent' => 'bg-purple-50 text-purple-500 border-purple-100'],
                        ['icon' => 'fa-graduation-cap', 'title' => 'Mentores Experientes', 'desc' => 'Acompanhamento personalizado por docentes e especialistas para consolidar a sua startup.',     'accent' => 'bg-green-50 text-green-500 border-green-100'],
                        ['icon' => 'fa-award',          'title' => 'Captação de Recursos', 'desc' => 'Preparamos o seu pitch e apoiamos na atração de financiamento público e privado.',           'accent' => 'bg-amber-50 text-amber-500 border-amber-100'],
                        ['icon' => 'fa-globe',          'title' => 'Impacto Nacional',     'desc' => 'Projetos nascidos na academia com visão de escala para todo o mercado angolano.',            'accent' => 'bg-indigo-50 text-indigo-500 border-indigo-100'],
                    ];
                    foreach ($diferenciais as $dif):
                    ?>
                        <div class="group">
                            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:border-orange-200 transition-all duration-400 h-full flex flex-col">
                                <div class="mb-6">
                                    <div class="w-14 h-14 rounded-2xl border flex items-center justify-center <?= $dif['accent'] ?> group-hover:scale-110 transition-transform duration-300">
                                        <i class="fa-solid <?= $dif['icon'] ?> text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="text-lg font-bold text-black mb-3"><?= $dif['title'] ?></h3>
                                <p class="text-gray-500 text-sm leading-relaxed flex-1"><?= $dif['desc'] ?></p>
                                <div class="w-10 h-0.5 bg-gray-200 rounded-full mt-6 group-hover:w-16 group-hover:bg-orange-400 transition-all duration-500"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 4. RESULTADOS REAIS (Contadores Sincronizados com a BD) & PILARES ISPSN -->
        <section class="py-20 sm:py-28 bg-gray-50">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Resultados em Tempo Real</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-black mb-4">
                        Impacto e <span class="text-orange-500">Propósito ISPSN</span>
                    </h2>
                    <p class="text-gray-500 max-w-2xl mx-auto text-sm sm:text-base leading-relaxed">
                        Indicadores sincronizados diretamente com o sistema de gestão da Incubadora Académica do ISPSN.
                    </p>
                </div>

                <!-- 4 Contadores Reais da BD -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 mb-20">
                    <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center shadow-sm hover:shadow-md hover:border-orange-200 transition-all duration-300 group">
                        <h3 class="text-4xl sm:text-5xl font-bold text-orange-500 mb-2 group-hover:scale-110 transition-transform duration-300 inline-block"><?= (int)$stats['startups'] ?></h3>
                        <p class="text-gray-600 font-medium text-sm sm:text-base">Startups Selecionadas</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center shadow-sm hover:shadow-md hover:border-orange-200 transition-all duration-300 group">
                        <h3 class="text-4xl sm:text-5xl font-bold text-orange-500 mb-2 group-hover:scale-110 transition-transform duration-300 inline-block"><?= (int)$stats['incubados'] ?></h3>
                        <p class="text-gray-600 font-medium text-sm sm:text-base">Startups Incubadas</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center shadow-sm hover:shadow-md hover:border-orange-200 transition-all duration-300 group">
                        <h3 class="text-4xl sm:text-5xl font-bold text-orange-500 mb-2 group-hover:scale-110 transition-transform duration-300 inline-block"><?= (int)$stats['mentores'] ?></h3>
                        <p class="text-gray-600 font-medium text-sm sm:text-base">Mentores Ativos</p>
                    </div>
                    <div class="bg-white border border-gray-100 rounded-2xl p-8 text-center shadow-sm hover:shadow-md hover:border-orange-200 transition-all duration-300 group">
                        <h3 class="text-4xl sm:text-5xl font-bold text-orange-500 mb-2 group-hover:scale-110 transition-transform duration-300 inline-block"><?= (int)$stats['membros'] ?></h3>
                        <p class="text-gray-600 font-medium text-sm sm:text-base">Empreendedores</p>
                    </div>
                </div>

                <!-- 3 Cards de Propósito Institucional ISPSN (Missão, Visão, Valores) no formato de Card do IPIL -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                    <?php
                    $pilares = [
                        [
                            'initials' => 'MS',
                            'name'     => 'Nossa Missão',
                            'role'     => 'Formação & Liderança',
                            'text'     => $textoMissao,
                        ],
                        [
                            'initials' => 'VS',
                            'name'     => 'Nossa Visão',
                            'role'     => 'Excelência Interdisciplinar',
                            'text'     => $textoVisao,
                        ],
                        [
                            'initials' => 'VL',
                            'name'     => 'Valores Fundamentais',
                            'role'     => 'HONOR · LABOR · MERITUM',
                            'text'     => 'Cientificismo na produção de conhecimento real, Humanismo focado na dignidade da pessoa humana e Responsabilidade Individual no exercício consciente da liberdade.',
                        ],
                    ];
                    foreach ($pilares as $pil):
                    ?>
                        <div class="group relative">
                            <div class="absolute -inset-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-3xl blur opacity-0 group-hover:opacity-15 transition duration-500 pointer-events-none"></div>
                            <div class="relative bg-white p-8 rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:shadow-orange-100/40 transition-all duration-500 flex flex-col h-full">
                                <div class="text-5xl text-orange-500 font-serif leading-none mb-4 select-none">&ldquo;</div>
                                <p class="text-gray-600 text-sm sm:text-base leading-relaxed flex-1 group-hover:text-gray-800 transition-colors duration-300">
                                    <?= htmlspecialchars($pil['text']) ?>
                                </p>
                                <div class="w-full h-px bg-gray-100 group-hover:bg-orange-100 transition-colors duration-500 my-6"></div>
                                <div class="flex items-center gap-4">
                                    <div class="relative shrink-0">
                                        <div class="relative w-11 h-11 bg-linear-to-br from-orange-500 to-amber-500 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-md">
                                            <?= $pil['initials'] ?>
                                        </div>
                                    </div>
                                    <div>
                                        <h4 class="font-bold text-black text-sm group-hover:text-orange-600 transition-colors duration-300"><?= $pil['name'] ?></h4>
                                        <span class="text-gray-500 text-xs group-hover:text-orange-400 transition-colors duration-300"><?= $pil['role'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 5. O QUE OFERECEMOS / NOSSOS SERVIÇOS (3 Cards com Barra Gradiente Superior) -->
        <section class="py-20 sm:py-28 bg-gray-50 border-t border-gray-100">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">O que oferecemos</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-black mb-4">
                        Nossos <span class="text-orange-500">Serviços</span>
                    </h2>
                    <p class="text-gray-500 max-w-2xl mx-auto text-sm sm:text-base leading-relaxed">
                        Oferecemos soluções completas e acompanhamento contínuo para transformar projetos académicos em empresas sólidas.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                    <?php
                    $servicos = [
                        [
                            'icon'  => 'fa-table-cells-large',
                            'title' => 'Espaços de Incubação & Cowork',
                            'desc'  => 'Disponibilizamos salas e espaços colaborativos no ISPSN equipados para reuniões de equipa, desenvolvimento de protótipos e sessões de trabalho.',
                        ],
                        [
                            'icon'  => 'fa-arrow-trend-up',
                            'title' => 'Apoio ao Financiamento & Investidores',
                            'desc'  => 'Auxiliamos na estruturação financeira e preparação de apresentações (Pitch Decks) perante bancas de avaliação e investidores estratégicos.',
                        ],
                        [
                            'icon'  => 'fa-compass',
                            'title' => 'Mentoria & Capacitação Intensiva',
                            'desc'  => 'Realizamos diagnósticos de maturidade, definição de metas (OKRs), workshops e acompanhamento direto com mentores especializados.',
                        ],
                    ];
                    foreach ($servicos as $srv):
                    ?>
                        <div class="group relative bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:shadow-orange-100/30 hover:border-orange-200 transition-all duration-400 overflow-hidden">
                            <div class="absolute top-0 left-0 right-0 h-0.5 bg-linear-to-r from-orange-500 to-amber-400 scale-x-0 group-hover:scale-x-100 transition-transform duration-500 origin-left"></div>
                            <div class="p-8">
                                <div class="mb-6 p-3.5 bg-orange-50 border border-orange-100 rounded-2xl w-fit group-hover:bg-orange-500 transition-colors duration-300">
                                    <i class="fa-solid <?= $srv['icon'] ?> text-xl text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                                </div>
                                <h3 class="text-lg font-bold text-black mb-4 leading-snug"><?= $srv['title'] ?></h3>
                                <p class="text-gray-500 text-sm leading-relaxed"><?= $srv['desc'] ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 6. VITRINE DE STARTUPS & PROJETOS SELECIONADOS (Sincronizada com a BD) -->
        <section id="startups" class="py-20 sm:py-28 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Inovação em ação</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-black mb-4">
                        Startups &amp; <span class="text-transparent bg-clip-text bg-linear-to-r from-orange-500 to-amber-500">Projetos Selecionados</span>
                    </h2>
                    <p class="text-gray-500 max-w-2xl mx-auto text-sm sm:text-base leading-relaxed">
                        Conheça as startups já aprovadas e incubadas pela Comissão Técnica do ISPSN.
                    </p>
                </div>

                <?php if (empty($projetos)): ?>
                    <div class="bg-gray-50 border border-gray-100 rounded-2xl p-10 text-center max-w-2xl mx-auto shadow-sm">
                        <div class="w-16 h-16 bg-orange-50 border border-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                            <i class="fa-solid fa-rocket text-2xl text-orange-500"></i>
                        </div>
                        <h3 class="text-xl font-bold text-black mb-2">Seja a próxima Startup selecionada no ISPSN</h3>
                        <p class="text-gray-500 text-sm leading-relaxed mb-6">
                            As startups aprovadas e incubadas pela Comissão Técnica são exibidas automaticamente nesta vitrine oficial. Submeta já a sua candidatura!
                        </p>
                        <a href="/incubadora_ispsn/public/website/candidatura.php"
                           class="inline-flex items-center gap-2 px-7 py-3 bg-linear-to-r from-orange-500 to-amber-500 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 hover:scale-105 transition-all duration-300">
                            <i class="fa-solid fa-paper-plane text-xs"></i>
                            <span>Submeter Candidatura</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 lg:gap-8">
                        <?php foreach ($projetos as $idx => $proj):
                            $faseKey   = (string)($proj['fase'] ?? 'ideacao');
                            $estadoKey = (string)($proj['estado'] ?? 'aprovado');
                            $faseTxt   = $labelsFase[$faseKey]     ?? ucfirst($faseKey);
                            $estadoTxt = $labelsEstado[$estadoKey] ?? ucfirst($estadoKey);
                            $areaTxt   = ucfirst((string)($proj['area_tematica'] ?? 'Tecnologia'));
                            $cardImgs  = [$fallbackGenImg, $fallbackBlogImg, '/incubadora_ispsn/assets/img/galeria/gal_1777456481.png'];
                            $cardImg   = resolver_imagem_site('', $cardImgs[$idx % count($cardImgs)]);
                        ?>
                            <div class="relative group h-80 sm:h-96 cursor-pointer rounded-2xl overflow-hidden shadow-lg"
                                 onclick="window.location.href='/incubadora_ispsn/public/website/startups.php'">
                                <img src="<?= htmlspecialchars($cardImg) ?>"
                                     alt="<?= htmlspecialchars($proj['titulo']) ?>"
                                     class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                                <div class="absolute inset-0 bg-linear-to-t from-black/90 via-black/55 to-black/20"></div>
                                <div class="absolute inset-0 bg-linear-to-t from-orange-600/30 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                                <div class="absolute inset-0 rounded-2xl ring-1 ring-white/10 group-hover:ring-orange-400/40 transition-all duration-500"></div>

                                <div class="absolute top-5 left-5">
                                    <span class="px-3 py-1.5 bg-white/20 backdrop-blur-sm text-white text-xs font-semibold rounded-full border border-white/20">
                                        <?= htmlspecialchars($areaTxt) ?>
                                    </span>
                                </div>
                                <div class="absolute top-5 right-5">
                                    <span class="px-3 py-1.5 bg-linear-to-r from-orange-500 to-amber-500 text-white text-xs font-bold rounded-full shadow-md">
                                        <?= htmlspecialchars($estadoTxt) ?> · <?= htmlspecialchars($faseTxt) ?>
                                    </span>
                                </div>

                                <div class="absolute bottom-0 left-0 right-0 p-6 text-white">
                                    <h3 class="text-xl font-bold mb-1.5"><?= htmlspecialchars($proj['titulo']) ?></h3>
                                    <p class="text-gray-300 text-sm leading-relaxed mb-3 line-clamp-2">
                                        <?= htmlspecialchars($proj['descricao']) ?>
                                    </p>
                                    <div class="flex items-center justify-between pt-3 border-t border-white/15">
                                        <span class="text-xs text-orange-300 font-medium">
                                            <i class="fa-solid fa-user-graduate mr-1"></i> <?= htmlspecialchars($proj['autor']) ?>
                                        </span>
                                        <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-white group-hover:text-orange-300 transition-colors">
                                            Explorar <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-12 flex flex-wrap justify-center gap-4">
                    <a href="/incubadora_ispsn/public/website/startups.php"
                       class="inline-flex items-center gap-3 px-8 py-3.5 bg-linear-to-r from-orange-500 to-amber-500 text-white font-semibold rounded-xl shadow-lg shadow-orange-200 hover:shadow-orange-300/50 hover:scale-105 hover:-translate-y-0.5 transition-all duration-300 text-sm sm:text-base">
                        <span>Ver Portfólio de Startups</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                    <a href="/incubadora_ispsn/public/website/projetos.php"
                       class="inline-flex items-center gap-3 px-8 py-3.5 bg-gray-100 hover:bg-orange-50 text-gray-800 hover:text-orange-600 border border-gray-200 hover:border-orange-200 font-semibold rounded-xl transition-all duration-300 text-sm sm:text-base">
                        <span>Ver Todos os Projetos</span>
                        <i class="fa-solid fa-layer-group text-xs"></i>
                    </a>
                </div>

                <?php if ($processo): ?>
                    <div class="mt-14 relative overflow-hidden bg-[#0A0A0A] rounded-2xl p-8 sm:p-10 text-white shadow-xl">
                        <div class="absolute top-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>
                        <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-6">
                            <div>
                                <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full border border-orange-500/30 bg-orange-500/10 mb-3">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                                    <span class="text-orange-400 text-xs font-semibold uppercase tracking-wide">Edital Aberto</span>
                                </div>
                                <h3 class="text-xl sm:text-2xl font-bold text-white mb-2"><?= htmlspecialchars($processo['titulo'] ?? $processo['nome'] ?? 'Processo de Candidatura ISPSN') ?></h3>
                                <p class="text-gray-400 text-sm leading-relaxed max-w-2xl">
                                    <?= htmlspecialchars($processo['descricao'] ?: 'Estão abertas as candidaturas para novos projetos de estudantes e pré-licenciados do ISPSN.') ?>
                                    <?php if (!empty($processo['data_fim'])): ?>
                                        · Encerra em <strong class="text-orange-400"><?= date('d/m/Y', strtotime($processo['data_fim'])) ?></strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <a href="/incubadora_ispsn/public/website/candidatura.php"
                               class="shrink-0 inline-flex items-center gap-2 px-7 py-3.5 bg-orange-500 hover:bg-orange-600 text-white font-semibold rounded-xl shadow-lg shadow-orange-500/25 transition-all duration-300 text-sm">
                                <i class="fa-solid fa-paper-plane text-xs"></i>
                                <span>Candidatar-me Agora</span>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 7. ÚLTIMAS NOTÍCIAS (Sincronizada com publicacoes_website) -->
        <?php if (!empty($noticias)): ?>
        <section id="noticias" class="py-20 sm:py-28 bg-gray-50 border-t border-gray-100">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col sm:flex-row sm:items-end justify-between mb-14 gap-4">
                    <div>
                        <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Sala de Visitas</span>
                        <h2 class="text-3xl sm:text-4xl font-bold text-black">
                            Actualidade &amp; <span class="text-orange-500">Eventos</span>
                        </h2>
                    </div>
                    <a href="/incubadora_ispsn/public/website/noticias.php"
                       class="inline-flex items-center gap-2 text-sm font-semibold text-orange-500 hover:text-orange-600">
                        <span>Ver todas as notícias</span>
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 lg:gap-8">
                    <?php foreach ($noticias as $n):
                        $nImg = resolver_imagem_site($n['imagem'] ?? '', $fallbackBlogImg);
                    ?>
                        <article class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:shadow-orange-100/30 hover:border-orange-200 transition-all duration-400 overflow-hidden flex flex-col h-full">
                            <a href="/incubadora_ispsn/public/website/noticia.php?id=<?= (int)$n['id'] ?>" class="relative h-48 overflow-hidden bg-gray-100 shrink-0 block">
                                <img src="<?= htmlspecialchars($nImg) ?>"
                                     alt="<?= htmlspecialchars($n['titulo']) ?>"
                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                     onerror="this.onerror=null;this.src='<?= $fallbackBlogImg ?>';">
                                <div class="absolute top-3 left-3">
                                    <span class="px-2.5 py-1 bg-black/50 backdrop-blur-sm text-white text-xs font-medium rounded-full border border-white/20">
                                        <?= htmlspecialchars($n['categoria'] ?: 'Notícia') ?>
                                    </span>
                                </div>
                            </a>
                            <div class="p-6 flex flex-col flex-1">
                                <div class="flex items-center gap-2 text-xs text-gray-400 mb-3">
                                    <i class="fa-regular fa-calendar text-orange-400"></i>
                                    <span><?= date('d/m/Y', strtotime($n['criado_em'])) ?></span>
                                </div>
                                <h3 class="text-base sm:text-lg font-bold text-black mb-2 group-hover:text-orange-600 transition-colors duration-300 leading-snug">
                                    <a href="/incubadora_ispsn/public/website/noticia.php?id=<?= (int)$n['id'] ?>">
                                        <?= htmlspecialchars($n['titulo']) ?>
                                    </a>
                                </h3>
                                <p class="text-gray-500 text-sm leading-relaxed line-clamp-3 mb-4 flex-1">
                                    <?= htmlspecialchars($n['resumo']) ?>
                                </p>
                                <div class="flex items-center justify-between pt-4 border-t border-gray-50 mt-auto">
                                    <span class="text-xs text-gray-400"><i class="fa-regular fa-user mr-1"></i> ISPSN</span>
                                    <a href="/incubadora_ispsn/public/website/noticia.php?id=<?= (int)$n['id'] ?>"
                                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-orange-500 hover:text-orange-600">
                                        <span>Ler mais</span>
                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                    </a>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- 8. PARCEIROS E APOIOS INSTITUCIONAIS (Carrossel Estilo IPIL) -->
        <section class="py-16 sm:py-24 bg-white border-t border-gray-100">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Quem nos apoia</span>
                    <h2 class="text-3xl sm:text-4xl font-bold text-black mb-4">
                        Ecossistema e <span class="text-orange-500">Apoios</span>
                    </h2>
                    <p class="text-gray-500 max-w-2xl mx-auto text-sm sm:text-base">
                        Pilares institucionais que apoiam a nossa missão de inovação académica e empreendedorismo em Angola.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-6 max-w-4xl mx-auto">
                    <?php
                    $parceiros = [
                        ['nome' => 'Incubadora Sol Nascente',    'sub' => 'Empreendedorismo & Inovação', 'img' => '/incubadora_ispsn/assets/img/logo_sn_oficial.png'],
                        ['nome' => 'Educa Futuro Huambo',        'sub' => 'HONOR · LABOR · MERITUM',     'img' => '/incubadora_ispsn/assets/img/galeria/gal_1777456481.png'],
                        ['nome' => 'ISPSN — Ensino Superior',    'sub' => 'Investigação Científica',     'img' => '/incubadora_ispsn/assets/img/logo_sn_bulb_light.png'],
                        ['nome' => 'GTI — Tecnologia',           'sub' => 'Transformação Digital',       'img' => '/incubadora_ispsn/assets/img/logo_sn_oficial.png'],
                    ];
                    foreach ($parceiros as $parc):
                    ?>
                        <div class="group bg-gray-50 border border-gray-100 rounded-2xl p-6 text-center hover:border-orange-200 hover:bg-orange-50/30 transition-all duration-300">
                            <img src="<?= $parc['img'] ?>"
                                 alt="<?= htmlspecialchars($parc['nome']) ?>"
                                 class="h-12 w-auto object-contain mx-auto mb-3 grayscale opacity-60 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-300"
                                 onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn_oficial.png';">
                            <p class="text-xs font-bold text-gray-800 group-hover:text-orange-600 transition-colors"><?= htmlspecialchars($parc['nome']) ?></p>
                            <p class="text-[11px] text-gray-400 mt-0.5"><?= htmlspecialchars($parc['sub']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
</div>
</body>
</html>
