<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Vary: Accept-Encoding');

$css_version     = ASSET_VERSION;
$activePage      = 'sobre';
$fallbackGenImg  = '/incubadora_ispsn/assets/img/ilustracao_criatividade.png';
$fallbackBlogImg = '/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png';

// Estatísticas reais sincronizadas com a BD
$stats = ['startups' => 0, 'mentores' => 0, 'membros' => 0];
$resStats = $mysqli->query("
    SELECT
        (SELECT COUNT(*) FROM projetos WHERE estado IN ('aprovado','incubado','fundo_investimento','concluido')) AS total_startups,
        (SELECT COUNT(*) FROM usuarios WHERE perfil = 'mentor' AND activo = 1) AS total_mentores,
        (SELECT COUNT(*) FROM usuarios WHERE perfil = 'utilizador' AND activo = 1) AS total_membros
");
if ($resStats && ($rowSt = $resStats->fetch_assoc())) {
    $stats['startups'] = (int)($rowSt['total_startups'] ?? 0);
    $stats['mentores'] = (int)($rowSt['total_mentores'] ?? 0);
    $stats['membros']  = (int)($rowSt['total_membros']  ?? 0);
}

// Equipa & Mentores ativos sincronizados com a BD (colunas validadas com database/schema.sql)
$equipa = [];
$resEq = @$mysqli->query("
    SELECT u.nome, u.perfil,
           COALESCE(NULLIF(m.especialidade, ''), 'Inovação & Empreendedorismo') AS area,
           'ISPSN — Sol Nascente' AS instituicao
    FROM usuarios u
    LEFT JOIN mentores m ON m.id_usuario = u.id
    WHERE u.activo = 1 AND u.perfil IN ('admin', 'funcionario', 'mentor')
    ORDER BY FIELD(u.perfil, 'admin', 'mentor', 'funcionario'), u.id ASC
    LIMIT 6
");
if ($resEq) {
    while ($row = $resEq->fetch_assoc()) {
        $equipa[] = $row;
    }
}

$textoMissao = get_config('missao', 'O ISPSN tem como missão promover o desenvolvimento das competências profissionais, científicas e tecnológicas dos futuros líderes, através de um ensino superior inovador e de qualidade, fundado nos valores do humanismo e da responsabilidade individual.');
$textoVisao  = get_config('visao', 'Promover ensino de qualidade e rigor no âmbito interdisciplinar, a investigação, a formação avançada e a extensão universitária.');
$bannerSobre = resolver_imagem_site(get_config('sobre_imagem', ''), $fallbackBlogImg);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sobre Nós — Incubadora Académica do ISPSN</title>
    <?php require __DIR__ . '/partials/_head_assets.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 font-poppins">
<div class="flex flex-col min-h-screen bg-gray-50">

    <?php require __DIR__ . '/partials/_navbar.php'; ?>

    <main class="flex-1">
        <!-- 1. BANNER HERO COMPACTO (Padrão IPIL) -->
        <section class="relative h-56 sm:h-72 md:h-96 overflow-hidden flex items-center justify-center">
            <img src="<?= htmlspecialchars($bannerSobre) ?>"
                 alt="Sobre a Incubadora ISPSN"
                 class="absolute inset-0 w-full h-full object-cover"
                 onerror="this.onerror=null;this.src='<?= $fallbackGenImg ?>';">
            <div class="absolute inset-0 bg-linear-to-b from-black/80 via-black/60 to-black/70"></div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>

            <div class="relative z-10 flex flex-col items-center text-center px-4">
                <img src="/incubadora_ispsn/assets/img/logo_sn_bulb_dark.png"
                     alt="Logo Incubadora Sol Nascente"
                     class="w-14 h-14 sm:w-18 sm:h-18 object-contain drop-shadow-2xl mb-4"
                     onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn_transparent.png';">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-500/40 bg-orange-500/10 backdrop-blur-sm mb-4">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-400 animate-pulse"></span>
                    <span class="text-orange-300 text-xs font-medium tracking-wide">Página Institucional · HONOR · LABOR · MERITUM</span>
                </div>
                <h1 class="text-2xl sm:text-4xl md:text-5xl font-bold font-poppins text-white mb-2 leading-tight">
                    Quem Nós <span class="text-orange-400">Somos</span>
                </h1>
                <p class="text-gray-300 text-sm sm:text-base max-w-md leading-relaxed">
                    Conheça a missão, visão e valores da Incubadora Académica do ISPSN
                </p>
            </div>
        </section>

        <!-- 2. QUEM SOMOS NÓS? (2 Colunas: Card História + 3 Cards Visão/Missão/Valores) -->
        <section class="relative bg-white py-20 sm:py-28 px-4 sm:px-6 lg:px-8 overflow-hidden">
            <div class="absolute top-0 left-0 w-72 h-72 bg-orange-100 rounded-full -translate-x-1/2 -translate-y-1/2 blur-3xl opacity-40 pointer-events-none"></div>
            <div class="absolute bottom-0 right-0 w-96 h-96 bg-amber-50 rounded-full translate-x-1/3 translate-y-1/3 blur-3xl opacity-50 pointer-events-none"></div>

            <div class="relative z-10 max-w-7xl mx-auto">
                <div class="mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">A nossa história</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-black">
                        Onde o Conhecimento Encontra o <span class="text-orange-500">Propósito</span>
                    </h2>
                </div>

                <div class="grid lg:grid-cols-2 gap-10 lg:gap-16 items-start">
                    <!-- Coluna Esquerda: Card Institucional -->
                    <div>
                        <div class="bg-white rounded-2xl p-8 sm:p-10 border border-gray-100 shadow-sm hover:shadow-lg hover:border-orange-100 transition-all duration-500 group">
                            <div class="flex items-start gap-4 mb-8">
                                <div class="p-3 bg-orange-50 border border-orange-100 rounded-xl group-hover:bg-orange-500 transition-colors duration-300 shrink-0">
                                    <i class="fa-solid fa-arrow-trend-up text-xl text-orange-500 group-hover:text-white transition-colors duration-300"></i>
                                </div>
                                <h3 class="text-xl sm:text-2xl font-bold text-black pt-1 leading-snug">
                                    Transformando <span class="text-orange-500">Ideias Académicas</span> em Realidade
                                </h3>
                            </div>

                            <div class="space-y-5 text-gray-600 text-sm sm:text-base leading-relaxed">
                                <p>
                                    Como deriva da nossa divisa oficial, <span class="font-bold text-orange-500">HONOR – LABOR – MERITUM</span>, o Instituto Superior Politécnico Sol Nascente (ISPSN) não pretende ser apenas mais uma instituição de ensino superior, mas uma realidade universitária distinta em Angola.
                                </p>
                                <div class="p-5 bg-orange-50 rounded-xl border-l-4 border-orange-500">
                                    <p class="text-gray-700 italic text-sm leading-relaxed">
                                        &ldquo;Assente nos pilares da cientificidade, do humanismo e da responsabilidade individual, a Incubadora Académica do ISPSN apoia estudantes e pré-licenciados na criação de negócios sustentáveis e inovadores.&rdquo;
                                    </p>
                                </div>
                                <p>
                                    Através de recursos físicos, mentoria especializada, capacitação técnica e ligação a oportunidades de financiamento, acompanhamos cada projeto desde a validação do problema até ao lançamento no mercado.
                                </p>
                            </div>

                            <div class="mt-8 pt-6 border-t border-gray-100 grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <p class="text-2xl font-bold text-orange-500"><?= (int)$stats['membros'] ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Empreendedores</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-orange-500"><?= (int)$stats['startups'] ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Startups Selecionadas</p>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-orange-500"><?= (int)$stats['mentores'] ?></p>
                                    <p class="text-xs text-gray-500 mt-0.5">Mentores</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Coluna Direita: 3 Cards Empilhados (Visão, Missão, Valores) -->
                    <div class="space-y-5">
                        <!-- Card 1: Nossa Visão -->
                        <div class="group relative">
                            <div class="absolute -inset-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-2xl blur opacity-0 group-hover:opacity-20 transition duration-500 pointer-events-none"></div>
                            <div class="relative bg-white p-7 rounded-2xl border border-gray-100 shadow-sm group-hover:border-orange-200 group-hover:shadow-md transition-all duration-400">
                                <div class="flex items-start gap-5">
                                    <div class="p-3 bg-linear-to-br from-orange-500 to-amber-500 rounded-xl text-white shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shadow-md shadow-orange-200">
                                        <i class="fa-solid fa-eye text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-bold text-black mb-2 group-hover:text-orange-600 transition-colors duration-300">Nossa Visão</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed"><?= htmlspecialchars($textoVisao) ?></p>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 bg-gray-50 border border-gray-100 px-3 py-1 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-orange-400 shrink-0"></span>Rigor Interdisciplinar
                                            </span>
                                            <span class="inline-flex items-center gap-1.5 text-xs text-gray-600 bg-gray-50 border border-gray-100 px-3 py-1 rounded-full">
                                                <span class="w-1.5 h-1.5 rounded-full bg-orange-400 shrink-0"></span>Investigação &amp; Extensão
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Nossa Missão -->
                        <div class="group relative">
                            <div class="absolute -inset-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-2xl blur opacity-0 group-hover:opacity-20 transition duration-500 pointer-events-none"></div>
                            <div class="relative bg-white p-7 rounded-2xl border border-gray-100 shadow-sm group-hover:border-orange-200 group-hover:shadow-md transition-all duration-400">
                                <div class="flex items-start gap-5">
                                    <div class="p-3 bg-linear-to-br from-orange-500 to-amber-500 rounded-xl text-white shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shadow-md shadow-orange-200">
                                        <i class="fa-solid fa-bullseye text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-bold text-black mb-2 group-hover:text-orange-600 transition-colors duration-300">Nossa Missão</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed"><?= htmlspecialchars($textoMissao) ?></p>
                                        <ul class="mt-4 space-y-2">
                                            <li class="flex items-center gap-2 text-xs text-gray-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 shrink-0"></span>Competências profissionais, científicas e tecnológicas
                                            </li>
                                            <li class="flex items-center gap-2 text-xs text-gray-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 shrink-0"></span>Acompanhamento de startups e projetos de inovação
                                            </li>
                                            <li class="flex items-center gap-2 text-xs text-gray-600">
                                                <span class="w-1.5 h-1.5 rounded-full bg-orange-500 shrink-0"></span>Formação de líderes com responsabilidade individual
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Nossos Valores (Cientificismo, Humanismo, Responsabilidade) -->
                        <div class="group relative">
                            <div class="absolute -inset-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-2xl blur opacity-0 group-hover:opacity-20 transition duration-500 pointer-events-none"></div>
                            <div class="relative bg-white p-7 rounded-2xl border border-gray-100 shadow-sm group-hover:border-orange-200 group-hover:shadow-md transition-all duration-400">
                                <div class="flex items-start gap-5">
                                    <div class="p-3 bg-linear-to-br from-orange-500 to-amber-500 rounded-xl text-white shrink-0 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-300 shadow-md shadow-orange-200">
                                        <i class="fa-solid fa-scale-balanced text-lg"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-lg font-bold text-black mb-2 group-hover:text-orange-600 transition-colors duration-300">Nossos Valores</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed">
                                            A nossa missão incorpora três valores fundamentais que sustentam toda a atividade académica e empreendedora do ISPSN:
                                        </p>
                                        <div class="mt-5 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                            <div class="bg-orange-50 border border-orange-100 p-3 rounded-xl text-center hover:bg-orange-100 transition-colors duration-300">
                                                <div class="text-orange-500 mb-1.5 flex justify-center"><i class="fa-solid fa-microscope"></i></div>
                                                <h4 class="font-semibold text-gray-800 text-xs mb-1">Cientificismo</h4>
                                                <p class="text-xs text-gray-500 leading-snug">Produção e construção de conhecimento científico real</p>
                                            </div>
                                            <div class="bg-orange-50 border border-orange-100 p-3 rounded-xl text-center hover:bg-orange-100 transition-colors duration-300">
                                                <div class="text-orange-500 mb-1.5 flex justify-center"><i class="fa-solid fa-hands-holding-circle"></i></div>
                                                <h4 class="font-semibold text-gray-800 text-xs mb-1">Humanismo</h4>
                                                <p class="text-xs text-gray-500 leading-snug">Ciência focada no valor e na dignidade humana</p>
                                            </div>
                                            <div class="bg-orange-50 border border-orange-100 p-3 rounded-xl text-center hover:bg-orange-100 transition-colors duration-300">
                                                <div class="text-orange-500 mb-1.5 flex justify-center"><i class="fa-solid fa-shield-halved"></i></div>
                                                <h4 class="font-semibold text-gray-800 text-xs mb-1">Responsabilidade</h4>
                                                <p class="text-xs text-gray-500 leading-snug">Exercício consciente da liberdade pelo conhecimento</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. O QUE NOS DISTINGUE / NOSSOS DIFERENCIAIS (Grelha 2x3 Horizontal Estilo IPIL) -->
        <section class="py-20 sm:py-28 px-4 sm:px-6 lg:px-8 bg-gray-50">
            <div class="max-w-5xl mx-auto">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">O que nos distingue</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-black mb-4">
                        Nossos <span class="text-orange-500">Diferenciais</span>
                    </h2>
                    <p class="text-gray-500 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        Pilares operacionais que impulsionam as startups e projetos da comunidade académica do ISPSN
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 lg:gap-6">
                    <?php
                    $difsSobre = [
                        ['icon' => 'fa-lightbulb',     'title' => 'Inovação',     'desc' => 'Fomentamos a inovação tecnológica e a criatividade em todos os cursos e projetos do ISPSN.'],
                        ['icon' => 'fa-rocket',        'title' => 'Aceleração',   'desc' => 'Aceleramos o crescimento de startups estudantis com elevado potencial de impacto em Angola.'],
                        ['icon' => 'fa-users',         'title' => 'Colaboração',  'desc' => 'Promovemos equipas multidisciplinares e a partilha de conhecimento entre departamentos.'],
                        ['icon' => 'fa-handshake',     'title' => 'Mentoria',     'desc' => 'Oferecemos orientação contínua com docentes e profissionais experientes do mercado.'],
                        ['icon' => 'fa-coins',         'title' => 'Recursos',     'desc' => 'Disponibilizamos infraestrutura técnica, salas de reunião e suporte na validação de MVPs.'],
                        ['icon' => 'fa-network-wired', 'title' => 'Networking',   'desc' => 'Conectamos jovens empreendedores a uma rede de parceiros institucionais e investidores.'],
                    ];
                    foreach ($difsSobre as $ds):
                    ?>
                        <div class="group">
                            <div class="bg-white p-7 rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg group-hover:border-orange-200 transition-all duration-400 h-full">
                                <div class="flex items-start gap-5">
                                    <div class="bg-linear-to-br from-orange-500 to-amber-500 w-14 h-14 rounded-2xl flex items-center justify-center shrink-0 shadow-md shadow-orange-100 group-hover:scale-110 group-hover:rotate-3 transition-transform duration-400">
                                        <i class="fa-solid <?= $ds['icon'] ?> text-lg text-white"></i>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-base sm:text-lg font-bold text-black mb-2 group-hover:text-orange-600 transition-colors duration-300"><?= $ds['title'] ?></h3>
                                        <p class="text-gray-500 text-sm leading-relaxed"><?= $ds['desc'] ?></p>
                                    </div>
                                </div>
                                <div class="mt-5 h-0.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full w-0 group-hover:w-full bg-linear-to-r from-orange-500 to-amber-400 transition-all duration-700 rounded-full"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- 4. COORDENAÇÃO & COMISSÃO TÉCNICA ISPSN -->
        <?php if (!empty($equipa)): ?>
        <section class="bg-white py-20 sm:py-28 px-4 sm:px-6 lg:px-8 border-t border-gray-100">
            <div class="max-w-6xl mx-auto">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Coordenação &amp; Mentoria</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-black mb-4">
                        Nossa <span class="text-orange-500">Equipa</span>
                    </h2>
                    <p class="text-gray-500 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        Equipa técnica e mentores ativos sincronizados com o ecossistema da Incubadora Académica do ISPSN
                    </p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                    <?php foreach ($equipa as $m):
                        $cargoMap = [
                            'admin'       => 'Coordenação da Incubadora',
                            'mentor'      => 'Mentor Especialista',
                            'funcionario' => 'Equipa Técnica ISPSN',
                        ];
                        $cargo = $cargoMap[$m['perfil']] ?? 'Comissão Técnica';
                    ?>
                        <div class="group relative">
                            <div class="absolute -inset-0.5 bg-linear-to-br from-orange-500 to-amber-400 rounded-2xl blur opacity-0 group-hover:opacity-25 transition duration-500 pointer-events-none"></div>
                            <div class="relative bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm group-hover:shadow-xl group-hover:shadow-orange-100/40 transition-all duration-500 p-6">
                                <div class="flex items-center gap-4 mb-4">
                                    <div class="w-14 h-14 rounded-2xl bg-linear-to-br from-orange-500 to-amber-500 text-white font-bold text-lg flex items-center justify-center shrink-0 shadow-md">
                                        <?= htmlspecialchars(mb_strtoupper(mb_substr($m['nome'], 0, 2))) ?>
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-base font-bold text-black group-hover:text-orange-600 transition-colors truncate"><?= htmlspecialchars($m['nome']) ?></h3>
                                        <div class="flex items-center gap-2 mt-1">
                                            <div class="w-6 h-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-full group-hover:w-10 transition-all duration-500"></div>
                                            <p class="text-orange-500 text-xs font-semibold truncate"><?= htmlspecialchars($cargo) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 pt-4 border-t border-gray-100">
                                    <div class="w-8 h-8 bg-orange-50 border border-orange-100 rounded-lg flex items-center justify-center shrink-0 group-hover:bg-orange-500 group-hover:border-orange-500 transition-all duration-300">
                                        <i class="fa-solid fa-briefcase text-xs text-orange-500 group-hover:text-white transition-colors"></i>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-xs text-gray-400">Área de Atuação</p>
                                        <p class="text-xs sm:text-sm font-semibold text-gray-700 truncate"><?= htmlspecialchars($m['area']) ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
</div>
</body>
</html>
