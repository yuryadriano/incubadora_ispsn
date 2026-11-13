<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Vary: Accept-Encoding');

$css_version     = ASSET_VERSION;
$activePage      = 'noticias';
$fallbackBlogImg = '/incubadora_ispsn/assets/img/blog/candidaturas_brevemente.png';
$fallbackGenImg  = '/incubadora_ispsn/assets/img/ilustracao_criatividade.png';

$q   = trim((string)($_GET['q'] ?? ''));
$cat = trim((string)($_GET['cat'] ?? 'Todas'));

// Buscar todas as publicações publicadas
$todasNoticias = [];
$resNot = $mysqli->query("
    SELECT p.*, u.nome AS autor_nome
    FROM publicacoes_website p
    LEFT JOIN usuarios u ON u.id = p.criado_por
    WHERE p.status = 'publicado'
    ORDER BY p.criado_em DESC
");
if ($resNot) {
    while ($row = $resNot->fetch_assoc()) {
        $todasNoticias[] = $row;
    }
}

// Categorias disponíveis
$categorias = ['Todas'];
foreach ($todasNoticias as $n) {
    $c = trim((string)($n['categoria'] ?: 'Geral'));
    if (!in_array($c, $categorias, true)) {
        $categorias[] = $c;
    }
}

// Filtrar notícias
$noticiasFiltradas = array_values(array_filter($todasNoticias, function($n) use ($q, $cat) {
    $c = trim((string)($n['categoria'] ?: 'Geral'));
    if ($cat !== 'Todas' && strcasecmp($c, $cat) !== 0) {
        return false;
    }
    if ($q !== '') {
        $haystack = mb_strtolower(($n['titulo'] ?? '') . ' ' . ($n['resumo'] ?? '') . ' ' . ($n['conteudo'] ?? ''));
        if (mb_strpos($haystack, mb_strtolower($q)) === false) {
            return false;
        }
    }
    return true;
}));

$destaque = $todasNoticias[0] ?? null;
$emailContacto = get_config('contacto_email', 'incubadora@ispsn.org');
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notícias &amp; Eventos — Incubadora Académica do ISPSN</title>
    <?php require __DIR__ . '/partials/_head_assets.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 font-poppins">
<div class="flex flex-col min-h-screen bg-gray-50">

    <?php require __DIR__ . '/partials/_navbar.php'; ?>

    <main class="flex-1">
        <!-- 1. BANNER HERO COMPACTO (Padrão IPIL) -->
        <section class="relative h-56 sm:h-72 md:h-96 overflow-hidden flex items-center justify-center">
            <img src="<?= htmlspecialchars($fallbackBlogImg) ?>?v=<?= $css_version ?>"
                 alt="Banner Notícias — Incubadora ISPSN"
                 class="absolute inset-0 w-full h-full object-cover object-center"
                 onerror="this.onerror=null;this.src='<?= $fallbackGenImg ?>';">
            <div class="absolute inset-0 bg-linear-to-b from-black/85 via-black/60 to-black/80"></div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>

            <div class="relative z-10 flex flex-col items-center text-center px-4">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-orange-500/40 bg-orange-500/10 backdrop-blur-sm mb-4">
                    <i class="fa-regular fa-newspaper text-xs text-orange-400"></i>
                    <span class="text-orange-300 text-xs font-medium tracking-wide">Notícias &amp; Atualizações</span>
                </div>
                <h1 class="text-2xl sm:text-4xl md:text-5xl font-bold font-poppins text-white mb-3 leading-tight">
                    Fique <span class="text-orange-400">Informado</span>
                </h1>
                <p class="text-gray-300 text-sm sm:text-base max-w-xl leading-relaxed">
                    As últimas novidades, editais, eventos e iniciativas da Incubadora Académica do ISPSN
                </p>
            </div>
        </section>

        <!-- 2. LAYOUT EDITORIAL 2/3 + 1/3 BARRA LATERAL (Padrão IPIL) -->
        <section class="bg-gray-50/60 py-12 sm:py-16 lg:py-20">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-3 gap-8 lg:gap-10 xl:gap-12">

                    <!-- Coluna Principal (2/3) -->
                    <div class="lg:col-span-2 space-y-7">

                        <!-- Artigo em Destaque -->
                        <?php if ($destaque):
                            $destTituloLower = mb_strtolower((string)($destaque['titulo'] ?? ''));
                            if (str_contains($destTituloLower, 'inscri') || str_contains($destTituloLower, 'candidatura')) {
                                $destImg = '/incubadora_ispsn/assets/img/inicio_inscricoes_flyer.jpg?v=' . $css_version;
                            } else {
                                $destImg = resolver_imagem_site($destaque['imagem'] ?? '', $fallbackBlogImg);
                            }
                        ?>
                            <div class="group relative overflow-hidden rounded-2xl border border-gray-100 shadow-sm hover:shadow-xl hover:shadow-orange-100/30 transition-all duration-500 bg-white">
                                <div class="grid md:grid-cols-2 gap-0">
                                    <div class="relative h-56 sm:h-64 md:h-full min-h-60 overflow-hidden bg-gray-100">
                                        <img src="<?= htmlspecialchars($destImg) ?>"
                                             alt="<?= htmlspecialchars($destaque['titulo']) ?>"
                                             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                             onerror="this.onerror=null;this.src='<?= $fallbackBlogImg ?>';">
                                        <div class="absolute inset-0 bg-linear-to-t from-black/50 via-black/10 to-transparent"></div>
                                        <div class="absolute top-4 left-4">
                                            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-orange-500 text-white text-xs font-bold rounded-full shadow-md shadow-orange-500/30 tracking-wide uppercase">
                                                <span class="w-1.5 h-1.5 rounded-full bg-white animate-pulse"></span>
                                                <span>Destaque</span>
                                            </span>
                                        </div>
                                        <div class="absolute bottom-4 left-4">
                                            <span class="px-3 py-1 bg-black/50 backdrop-blur-sm text-white text-xs font-medium rounded-full border border-white/20">
                                                <?= htmlspecialchars($destaque['categoria'] ?: 'Institucional') ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="p-7 sm:p-8 flex flex-col justify-between">
                                        <div>
                                            <div class="flex items-center gap-4 text-xs text-gray-400 mb-4">
                                                <div class="flex items-center gap-1.5">
                                                    <i class="fa-regular fa-calendar text-orange-400"></i>
                                                    <span><?= date('d/m/Y', strtotime($destaque['criado_em'])) ?></span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <i class="fa-solid fa-tag text-orange-400"></i>
                                                    <span class="font-medium text-gray-500"><?= htmlspecialchars($destaque['categoria'] ?: 'Geral') ?></span>
                                                </div>
                                            </div>
                                            <h2 class="text-xl sm:text-2xl font-bold text-black mb-3 leading-snug group-hover:text-orange-600 transition-colors duration-300">
                                                <?= htmlspecialchars($destaque['titulo']) ?>
                                            </h2>
                                            <p class="text-gray-500 text-sm leading-relaxed line-clamp-3">
                                                <?= htmlspecialchars($destaque['resumo']) ?>
                                            </p>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-3 mt-6 pt-5 border-t border-gray-100">
                                            <a href="/incubadora_ispsn/public/website/noticia.php?id=<?= (int)$destaque['id'] ?>"
                                               class="inline-flex items-center justify-center gap-2 px-5 py-2.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold rounded-xl shadow-md shadow-orange-200 hover:shadow-orange-300/50 transition-all duration-300 hover:scale-105">
                                                <span>Ler Artigo</span>
                                                <i class="fa-solid fa-arrow-right text-xs"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Barra de Pesquisa e Filtro -->
                        <form method="get" action="noticias.php" class="bg-white border border-gray-100 rounded-2xl p-5 shadow-sm">
                            <div class="flex flex-col sm:flex-row gap-3">
                                <div class="flex-1 relative">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                                    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>"
                                           placeholder="Pesquisar notícias, editais, eventos..."
                                           class="w-full pl-10 pr-4 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-400 focus:border-transparent transition-all placeholder:text-gray-400 outline-none">
                                </div>
                                <div class="sm:w-52">
                                    <select name="cat" onchange="this.form.submit()"
                                            class="w-full px-3.5 py-2.5 text-sm border border-gray-200 rounded-xl focus:ring-2 focus:ring-orange-400 focus:border-transparent bg-white text-gray-700 transition-all cursor-pointer">
                                        <?php foreach ($categorias as $c): ?>
                                            <option value="<?= htmlspecialchars($c) ?>" <?= $cat === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php if ($q !== '' || $cat !== 'Todas'): ?>
                                    <a href="noticias.php"
                                       class="flex items-center justify-center gap-2 px-4 py-2.5 text-sm border border-gray-200 text-gray-600 font-medium rounded-xl hover:bg-gray-50 hover:border-orange-200 hover:text-orange-600 transition-all duration-200 whitespace-nowrap">
                                        <i class="fa-solid fa-xmark text-xs"></i>
                                        <span>Limpar</span>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <!-- Grelha de Notícias Recentes -->
                        <div id="noticias" class="space-y-6">
                            <div class="flex items-center justify-between">
                                <h3 class="text-lg font-bold text-black">Notícias Recentes</h3>
                                <span class="text-xs text-gray-400 bg-gray-50 border border-gray-100 px-3 py-1.5 rounded-full">
                                    <?= count($noticiasFiltradas) ?> <?= count($noticiasFiltradas) === 1 ? 'resultado' : 'resultados' ?>
                                </span>
                            </div>

                            <?php if (empty($noticiasFiltradas)): ?>
                                <div class="text-center py-20 bg-white rounded-2xl border border-gray-100">
                                    <div class="max-w-sm mx-auto">
                                        <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-5">
                                            <i class="fa-regular fa-newspaper text-2xl text-gray-400"></i>
                                        </div>
                                        <h3 class="text-lg font-bold text-gray-800 mb-2">Nenhuma notícia encontrada</h3>
                                        <p class="text-gray-500 text-sm leading-relaxed">Tente ajustar os filtros de pesquisa para encontrar o que procura.</p>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="grid sm:grid-cols-2 gap-5">
                                    <?php foreach ($noticiasFiltradas as $art):
                                        $tLower = mb_strtolower((string)($art['titulo'] ?? ''));
                                        if (str_contains($tLower, 'inscri') || str_contains($tLower, 'candidatura')) {
                                            $artImg = '/incubadora_ispsn/assets/img/inicio_inscricoes_flyer.jpg?v=' . $css_version;
                                        } else {
                                            $artImg = resolver_imagem_site($art['imagem'] ?? '', $fallbackBlogImg);
                                        }
                                    ?>
                                        <article class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:shadow-orange-100/30 hover:border-orange-200 transition-all duration-400 overflow-hidden flex flex-col h-full">
                                            <a href="/incubadora_ispsn/public/website/noticia.php?id=<?= (int)$art['id'] ?>" class="relative h-44 overflow-hidden bg-gray-50 shrink-0 block">
                                                <img src="<?= htmlspecialchars($artImg) ?>"
                                                     alt="<?= htmlspecialchars($art['titulo']) ?>"
                                                     class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-700"
                                                     onerror="this.onerror=null;this.src='<?= $fallbackBlogImg ?>';">
                                                <div class="absolute top-3 left-3">
                                                    <span class="px-2.5 py-1 bg-black/50 backdrop-blur-sm text-white text-xs font-medium rounded-full border border-white/20">
                                                        <?= htmlspecialchars($art['categoria'] ?: 'Geral') ?>
                                                    </span>
                                                </div>
                                            </a>

                                            <div class="p-5 sm:p-6 flex flex-col flex-1">
                                                <div class="flex items-center gap-3 text-xs text-gray-400 mb-3">
                                                    <div class="flex items-center gap-1.5">
                                                        <i class="fa-regular fa-calendar text-orange-400"></i>
                                                        <span><?= date('d/m/Y', strtotime($art['criado_em'])) ?></span>
                                                    </div>
                                                    <span class="w-px h-3 bg-gray-200"></span>
                                                    <div class="flex items-center gap-1.5">
                                                        <i class="fa-solid fa-tag text-orange-400"></i>
                                                        <span><?= htmlspecialchars($art['categoria'] ?: 'ISPSN') ?></span>
                                                    </div>
                                                </div>

                                                <h3 class="text-base sm:text-lg font-bold text-black mb-2 group-hover:text-orange-600 transition-colors duration-300 leading-snug">
                                                    <a href="/incubadora_ispsn/public/website/noticia.php?id=<?= (int)$art['id'] ?>">
                                                        <?= htmlspecialchars($art['titulo']) ?>
                                                    </a>
                                                </h3>

                                                <p class="text-gray-500 text-sm leading-relaxed line-clamp-3 mb-4 flex-1">
                                                    <?= htmlspecialchars($art['resumo']) ?>
                                                </p>

                                                <div class="flex items-center justify-between pt-4 border-t border-gray-50 mt-auto">
                                                    <div class="flex items-center gap-2 text-xs text-gray-400">
                                                        <i class="fa-regular fa-user"></i>
                                                        <span><?= htmlspecialchars($art['autor_nome'] ?: 'Redacção ISPSN') ?></span>
                                                    </div>
                                                    <a href="/incubadora_ispsn/public/website/noticia.php?id=<?= (int)$art['id'] ?>"
                                                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-orange-500 hover:text-orange-600 transition-colors duration-200">
                                                        <span>Ler mais</span>
                                                        <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Barra Lateral Direita (1/3 — Padrão IPIL) -->
                    <aside class="space-y-5">
                        <!-- Card 1: Categorias -->
                        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                            <div class="flex items-center gap-2.5 mb-4">
                                <div class="w-8 h-8 bg-orange-50 border border-orange-100 rounded-lg flex items-center justify-center">
                                    <i class="fa-solid fa-filter text-xs text-orange-500"></i>
                                </div>
                                <h3 class="text-sm font-bold text-black">Categorias</h3>
                            </div>
                            <div class="space-y-1">
                                <?php foreach ($categorias as $c):
                                    $isCatActive = ($cat === $c);
                                ?>
                                    <a href="noticias.php?cat=<?= urlencode($c) ?>"
                                       class="w-full text-left px-3 py-2.5 rounded-xl text-sm transition-all duration-200 flex items-center justify-between group <?= $isCatActive ? 'bg-orange-50 border border-orange-200 text-orange-600 font-semibold' : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900' ?>">
                                        <span><?= htmlspecialchars($c) ?></span>
                                        <?php if ($isCatActive): ?>
                                            <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Card 2: Tags Populares -->
                        <div class="bg-white rounded-2xl p-5 border border-gray-100 shadow-sm">
                            <div class="flex items-center gap-2.5 mb-4">
                                <div class="w-8 h-8 bg-orange-50 border border-orange-100 rounded-lg flex items-center justify-center">
                                    <i class="fa-solid fa-tag text-xs text-orange-500"></i>
                                </div>
                                <h3 class="text-sm font-bold text-black">Temas em Destaque</h3>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <?php foreach (['inovação', 'startups', 'ispsn', 'mentoria', 'edital', 'tecnologia', 'huambo', 'empreendedorismo'] as $tag): ?>
                                    <a href="noticias.php?q=<?= urlencode($tag) ?>"
                                       class="px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-500 border border-gray-100 hover:bg-orange-50 hover:text-orange-600 hover:border-orange-200 transition-all duration-200">
                                        #<?= $tag ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Card 3: Candidatura Rápida (Gradiente Laranja Estilo IPIL) -->
                        <div class="relative overflow-hidden rounded-2xl">
                            <div class="absolute inset-0 bg-linear-to-br from-orange-500 via-orange-600 to-amber-600"></div>
                            <div class="absolute -top-10 -right-10 w-40 h-40 bg-white/10 rounded-full blur-2xl pointer-events-none"></div>
                            <div class="relative p-7 sm:p-8 text-center">
                                <div class="w-12 h-12 bg-white/15 backdrop-blur-sm border border-white/20 rounded-2xl flex items-center justify-center mb-4 shadow-lg mx-auto">
                                    <i class="fa-solid fa-rocket text-white"></i>
                                </div>
                                <h3 class="text-lg font-bold text-white mb-1">Tem uma Ideia?</h3>
                                <p class="text-white/80 text-xs leading-relaxed">
                                    Participe no programa de incubação académica do ISPSN e transforme o seu talento em projetos com impacto real.
                                </p>
                            </div>
                        </div>

                        <!-- Card 4: Tem uma história? (Escuro #0A0A0A Estilo IPIL) -->
                        <div class="relative overflow-hidden bg-[#0A0A0A] rounded-2xl p-6 text-white">
                            <div class="absolute top-0 left-0 right-0 h-px bg-linear-to-r from-transparent via-orange-500 to-transparent"></div>
                            <div class="absolute -bottom-8 -right-8 w-32 h-32 bg-orange-500/10 rounded-full blur-2xl pointer-events-none"></div>
                            <div class="relative">
                                <div class="w-9 h-9 bg-orange-500/15 border border-orange-500/20 rounded-xl flex items-center justify-center mb-4">
                                    <i class="fa-regular fa-envelope text-orange-400 text-xs"></i>
                                </div>
                                <h3 class="text-base font-bold mb-2">Tem uma história?</h3>
                                <p class="text-gray-400 text-xs leading-relaxed mb-5">
                                    Quer partilhar a sua experiência empreendedora ou divulgar uma iniciativa relevante na comunidade ISPSN?
                                </p>
                                <a href="mailto:<?= htmlspecialchars($emailContacto) ?>"
                                   class="inline-flex items-center gap-2 px-4 py-2.5 bg-white text-black text-xs font-bold rounded-xl hover:bg-gray-100 transition-colors duration-200">
                                    <span>Enviar Notícia</span>
                                </a>
                            </div>
                        </div>
                    </aside>

                </div>
            </div>
        </section>
    </main>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
</div>
</body>
</html>

