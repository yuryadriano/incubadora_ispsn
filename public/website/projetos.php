<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Vary: Accept-Encoding');

$css_version     = ASSET_VERSION;
$activePage      = 'projetos';
$fallbackGenImg  = '/incubadora_ispsn/assets/img/equipa_humana_sn.jpg';
$fallbackBlogImg = '/incubadora_ispsn/assets/img/hero_humano_sn.jpg';

// Buscar projetos aprovados/selecionados da Base de Dados
$projetos = [];
$resProj = $mysqli->query("
    SELECT p.id, p.titulo, p.descricao, p.problema, p.solucao, p.area_tematica, p.fase, p.estado, p.criado_em,
           u.nome AS autor
    FROM projetos p
    JOIN usuarios u ON u.id = p.criado_por
    WHERE p.estado IN ('aprovado', 'incubado', 'fundo_investimento', 'concluido')
    ORDER BY p.destaque_publico DESC, p.pontos DESC, p.criado_em DESC
");
if ($resProj) {
    while ($row = $resProj->fetch_assoc()) {
        $projetos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nossos Projetos — Incubadora Académica do ISPSN</title>
    <?php require __DIR__ . '/partials/_head_assets.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 font-poppins">
<div class="flex flex-col min-h-screen bg-gray-50">

    <?php require __DIR__ . '/partials/_navbar.php'; ?>

    <main class="flex-1">
        <!-- 1. BANNER HERO COMPACTO (Padrão IPIL) -->
        <section class="relative h-56 sm:h-72 md:h-96 flex items-center justify-center text-white text-center overflow-hidden">
            <img src="<?= htmlspecialchars($fallbackGenImg) ?>"
                 alt="Banner de Projetos — Incubadora ISPSN"
                 class="absolute inset-0 w-full h-full object-cover object-center">
            <div class="absolute inset-0 bg-linear-to-b from-black/85 via-black/60 to-black/80"></div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>

            <div class="relative z-10 flex flex-col items-center px-4">
                <img src="/incubadora_ispsn/assets/img/logo_sn_bulb_dark.png"
                     alt="Logo Incubadora Sol Nascente"
                     class="w-14 h-14 sm:w-18 sm:h-18 object-contain drop-shadow-2xl mb-4"
                     onerror="this.onerror=null;this.src='/incubadora_ispsn/assets/img/logo_sn_transparent.png';">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-500/40 bg-orange-500/10 backdrop-blur-sm mb-4">
                    <i class="fa-solid fa-layer-group text-xs text-orange-400"></i>
                    <span class="text-orange-300 text-xs font-medium tracking-wide">Inovação em ação</span>
                </div>
                <h1 class="text-2xl sm:text-4xl md:text-5xl font-bold leading-tight font-poppins mb-3">
                    Nossos <span class="text-orange-400">Projetos</span>
                </h1>
                <p class="text-sm sm:text-base text-gray-300 leading-relaxed max-w-xl">
                    Descubra os projetos inovadores que estão a moldar o futuro com o apoio da Incubadora Académica do ISPSN
                </p>
            </div>
        </section>

        <!-- 2. TODOS OS PROJETOS (Padrão IPIL) -->
        <section class="py-20 sm:py-28 bg-white">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Portfólio Académico</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-black mb-4">
                        Todos os <span class="text-orange-500">Projetos</span>
                    </h2>
                    <p class="text-gray-500 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        Veja todos os projetos aprovados e descubra as ideias que estão a transformar a comunidade académica do ISPSN.
                    </p>
                </div>

                <div class="flex items-center justify-center gap-3 mb-10">
                    <div class="h-px bg-gray-200 flex-1 max-w-24"></div>
                    <span class="text-xs text-gray-400 font-medium px-3 py-1.5 bg-gray-50 border border-gray-100 rounded-full">
                        <?= count($projetos) ?> <?= count($projetos) === 1 ? 'projeto encontrado' : 'projetos encontrados' ?>
                    </span>
                    <div class="h-px bg-gray-200 flex-1 max-w-24"></div>
                </div>

                <?php if (empty($projetos)): ?>
                    <div class="text-center py-16 bg-gray-50 rounded-2xl border border-gray-100 max-w-xl mx-auto p-8">
                        <div class="w-14 h-14 bg-orange-50 border border-orange-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                            <i class="fa-solid fa-layer-group text-xl text-orange-500"></i>
                        </div>
                        <h3 class="text-lg font-bold text-black mb-2">Submeta o seu Projeto Académico</h3>
                        <p class="text-gray-500 text-sm leading-relaxed mb-6">
                            Os projetos selecionados pela Comissão Técnica aparecerão listados aqui. Inscreva já a sua proposta!
                        </p>
                        <a href="/incubadora_ispsn/public/website/candidatura.php"
                           class="inline-flex items-center gap-2 px-6 py-3 bg-linear-to-r from-orange-500 to-amber-500 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 hover:scale-105 transition-all">
                            <i class="fa-solid fa-rocket text-xs"></i>
                            <span>Candidatar Projeto</span>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8">
                        <?php foreach ($projetos as $idx => $p): ?>
                            <div class="group relative cursor-pointer" onclick="window.location.href='/incubadora_ispsn/public/website/startups.php'">
                                <div class="absolute -inset-0.5 bg-linear-to-br from-orange-500 to-amber-400 rounded-2xl blur opacity-0 group-hover:opacity-20 transition duration-500 pointer-events-none"></div>
                                <div class="relative bg-white rounded-2xl overflow-hidden border border-gray-100 shadow-sm group-hover:shadow-xl group-hover:shadow-orange-100/30 transition-all duration-500">
                                    <div class="relative h-48 sm:h-52 overflow-hidden bg-gray-100">
                                        <img src="<?= htmlspecialchars($fallbackGenImg) ?>"
                                             alt="<?= htmlspecialchars($p['titulo']) ?>"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700">
                                        <div class="absolute inset-0 bg-linear-to-t from-black/60 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                                        <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-400">
                                            <div class="flex items-center gap-2 px-4 py-2 bg-white/20 backdrop-blur-sm border border-white/30 rounded-full text-white text-xs font-semibold">
                                                <span>Ver detalhes</span>
                                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-5 sm:p-6">
                                        <div class="mb-3">
                                            <h3 class="text-base sm:text-lg font-bold text-black group-hover:text-orange-600 transition-colors duration-300">
                                                <?= htmlspecialchars($p['titulo']) ?>
                                            </h3>
                                            <div class="w-8 h-0.5 bg-linear-to-r from-orange-500 to-amber-400 rounded-full mt-2 group-hover:w-14 transition-all duration-500"></div>
                                        </div>
                                        <p class="text-gray-500 text-sm leading-relaxed line-clamp-3">
                                            <?= htmlspecialchars($p['descricao']) ?>
                                        </p>
                                        <div class="mt-4 pt-4 border-t border-gray-50 flex items-center justify-between">
                                            <span class="text-xs text-gray-400"><?= htmlspecialchars($p['autor']) ?></span>
                                            <div class="w-7 h-7 rounded-full bg-orange-50 border border-orange-100 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-300">
                                                <i class="fa-solid fa-arrow-right text-xs text-orange-500"></i>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- 3. SECTORES DE IMPACTO / ÁREAS DE ATUAÇÃO (Secção Escura 5 Colunas Padrão IPIL) -->
        <section class="py-20 sm:py-28 bg-[#0A0A0A]">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <span class="inline-block text-xs font-semibold tracking-widest text-orange-500 uppercase mb-3">Sectores de impacto</span>
                    <h2 class="text-3xl sm:text-4xl font-bold font-poppins text-white mb-4">
                        Áreas de <span class="text-orange-500">Atuação</span>
                    </h2>
                    <p class="text-gray-400 text-sm sm:text-base max-w-2xl mx-auto leading-relaxed">
                        Os projetos incubados no ISPSN abrangem diversas áreas científicas e de inovação, criando soluções reais para desafios reais.
                    </p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4 sm:gap-5">
                    <?php
                    $areasAtuacao = [
                        ['nome' => 'Tecnologia',        'desc' => 'Soluções digitais e software inovador',        'icon' => 'fa-microchip'],
                        ['nome' => 'Educação',          'desc' => 'Plataformas e ferramentas de ensino',          'icon' => 'fa-graduation-cap'],
                        ['nome' => 'Saúde & Agro',      'desc' => 'Soluções aplicadas à saúde e agronegócio',     'icon' => 'fa-seedling'],
                        ['nome' => 'Empreendedorismo',  'desc' => 'Negócios e aceleração de startups',            'icon' => 'fa-rocket'],
                        ['nome' => 'Sustentabilidade',  'desc' => 'Impacto ambiental e social positivo',          'icon' => 'fa-leaf'],
                    ];
                    foreach ($areasAtuacao as $ar):
                    ?>
                        <div class="group">
                            <div class="relative bg-white/5 border border-white/10 rounded-2xl p-6 text-center h-full hover:bg-white/8 hover:border-orange-500/30 transition-all duration-400 overflow-hidden">
                                <div class="absolute inset-0 bg-linear-to-b from-orange-500/5 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500 pointer-events-none rounded-2xl"></div>
                                <div class="relative w-14 h-14 rounded-2xl bg-white/8 border border-white/10 group-hover:border-orange-500/40 group-hover:bg-orange-500/10 flex items-center justify-center mx-auto mb-4 transition-all duration-400">
                                    <i class="fa-solid <?= $ar['icon'] ?> text-xl text-gray-400 group-hover:text-orange-400 transition-colors duration-400"></i>
                                </div>
                                <h3 class="text-sm sm:text-base font-bold text-white mb-2 leading-snug"><?= $ar['nome'] ?></h3>
                                <p class="text-xs text-gray-500 leading-relaxed group-hover:text-gray-400 transition-colors duration-300"><?= $ar['desc'] ?></p>
                                <div class="mt-5 h-px bg-white/5 rounded-full overflow-hidden">
                                    <div class="h-full w-0 group-hover:w-full bg-linear-to-r from-orange-500 to-amber-400 transition-all duration-600 rounded-full"></div>
                                </div>
                            </div>
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

