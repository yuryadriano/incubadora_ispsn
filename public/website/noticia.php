<?php
define('NO_SESSION', true);
require_once __DIR__ . '/../../config/config.php';

header('Cache-Control: no-cache, no-store, must-revalidate');
header('Vary: Accept-Encoding');

$css_version     = ASSET_VERSION;
$activePage      = 'noticias';
$fallbackBlogImg = '/incubadora_ispsn/assets/img/hero_ispsn_real.jpg';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header("Location: noticias.php");
    exit;
}

$stmt = $mysqli->prepare("
    SELECT p.*, u.nome as autor_nome 
    FROM publicacoes_website p 
    LEFT JOIN usuarios u ON u.id = p.criado_por 
    WHERE p.id = ? AND p.status = 'publicado'
    LIMIT 1
");
$stmt->bind_param('i', $id);
$stmt->execute();
$noticia = $stmt->get_result()->fetch_assoc();

if (!$noticia) {
    header("Location: noticias.php");
    exit;
}

$sugestoes = [];
$stmtS = $mysqli->prepare("SELECT id, titulo, resumo, categoria, imagem, criado_em FROM publicacoes_website WHERE status='publicado' AND id != ? ORDER BY criado_em DESC LIMIT 3");
$stmtS->bind_param('i', $id);
$stmtS->execute();
$resS = $stmtS->get_result();
if ($resS) {
    while ($row = $resS->fetch_assoc()) {
        $sugestoes[] = $row;
    }
}

$capaNoticia = resolver_imagem_site($noticia['imagem'] ?? '', $fallbackBlogImg);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($noticia['titulo']) ?> — Incubadora Académica do ISPSN</title>
    <?php require __DIR__ . '/partials/_head_assets.php'; ?>
</head>
<body class="bg-gray-50 text-gray-800 font-poppins">
<div class="flex flex-col min-h-screen bg-gray-50">

    <?php require __DIR__ . '/partials/_navbar.php'; ?>

    <main class="flex-1 py-12 sm:py-16">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

            <!-- Botão Voltar -->
            <div class="mb-6">
                <a href="/incubadora_ispsn/public/website/noticias.php"
                   class="inline-flex items-center gap-2 text-xs font-semibold text-gray-500 hover:text-orange-500 transition-colors">
                    <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    <span>Voltar para Notícias</span>
                </a>
            </div>

            <!-- Card do Artigo (Padrão Modal/Leitura IPIL) -->
            <article class="bg-white rounded-2xl border border-gray-100 overflow-hidden shadow-lg">
                <div class="relative h-64 sm:h-80 md:h-96 overflow-hidden bg-gray-100">
                    <img src="<?= htmlspecialchars($capaNoticia) ?>"
                         alt="<?= htmlspecialchars($noticia['titulo']) ?>"
                         class="w-full h-full object-cover"
                         onerror="this.onerror=null;this.src='<?= $fallbackBlogImg ?>';">
                    <div class="absolute inset-0 bg-linear-to-t from-black/80 via-black/25 to-transparent"></div>
                    <div class="absolute bottom-6 left-6 right-6">
                        <span class="inline-block px-3 py-1 bg-orange-500 text-white text-xs font-bold rounded-full mb-3 shadow-md shadow-orange-500/30 uppercase">
                            <?= htmlspecialchars($noticia['categoria'] ?: 'Notícia') ?>
                        </span>
                        <h1 class="text-2xl sm:text-3xl md:text-4xl font-bold text-white leading-snug">
                            <?= htmlspecialchars($noticia['titulo']) ?>
                        </h1>
                    </div>
                </div>

                <div class="p-6 sm:p-10">
                    <div class="flex flex-wrap items-center justify-between gap-4 mb-6 pb-5 border-b border-gray-100">
                        <div class="flex flex-wrap items-center gap-4 text-xs text-gray-500">
                            <div class="flex items-center gap-1.5">
                                <i class="fa-regular fa-user text-orange-400"></i>
                                <span class="font-medium text-gray-700"><?= htmlspecialchars($noticia['autor_nome'] ?: 'Redacção ISPSN') ?></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="fa-regular fa-calendar text-orange-400"></i>
                                <span><?= date('d/m/Y', strtotime($noticia['criado_em'])) ?></span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="text-xs text-gray-400 hidden sm:block">Partilhar:</span>
                            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode('https://incubadora.ispsn.app/incubadora_ispsn/public/website/noticia.php?id=' . (int)$noticia['id']) ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 border border-gray-100 text-gray-500 hover:text-blue-600 hover:bg-blue-50 transition-all">
                                <i class="fa-brands fa-facebook-f text-xs"></i>
                            </a>
                            <a href="https://wa.me/?text=<?= urlencode($noticia['titulo'] . ' — https://incubadora.ispsn.app/incubadora_ispsn/public/website/noticia.php?id=' . (int)$noticia['id']) ?>"
                               target="_blank" rel="noopener noreferrer"
                               class="w-8 h-8 flex items-center justify-center rounded-lg bg-gray-50 border border-gray-100 text-gray-500 hover:text-green-600 hover:bg-green-50 transition-all">
                                <i class="fa-brands fa-whatsapp text-xs"></i>
                            </a>
                        </div>
                    </div>

                    <?php if (!empty($noticia['resumo'])): ?>
                        <div class="bg-orange-50 border-l-4 border-orange-500 rounded-r-xl p-4 mb-6">
                            <p class="text-gray-700 text-sm sm:text-base leading-relaxed font-medium">
                                <?= htmlspecialchars($noticia['resumo']) ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="text-gray-600 leading-relaxed whitespace-pre-line text-sm sm:text-base space-y-4">
                        <?= nl2br(htmlspecialchars((string)$noticia['conteudo'])) ?>
                    </div>
                </div>
            </article>

            <!-- Outras Notícias -->
            <?php if (!empty($sugestoes)): ?>
                <div class="mt-14">
                    <h3 class="text-lg font-bold text-black mb-6">Outras Notícias Recentes</h3>
                    <div class="grid sm:grid-cols-3 gap-5">
                        <?php foreach ($sugestoes as $s):
                            $imgSug = resolver_imagem_site($s['imagem'] ?? '', $fallbackBlogImg);
                        ?>
                            <a href="noticia.php?id=<?= (int)$s['id'] ?>"
                               class="group bg-white rounded-2xl border border-gray-100 shadow-sm hover:shadow-lg hover:border-orange-200 transition-all overflow-hidden flex flex-col">
                                <div class="h-36 overflow-hidden bg-gray-100">
                                    <img src="<?= htmlspecialchars($imgSug) ?>"
                                         alt="<?= htmlspecialchars($s['titulo']) ?>"
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                         onerror="this.onerror=null;this.src='<?= $fallbackBlogImg ?>';">
                                </div>
                                <div class="p-4 flex-1 flex flex-col justify-between">
                                    <h4 class="text-sm font-bold text-black group-hover:text-orange-600 transition-colors line-clamp-2 mb-2">
                                        <?= htmlspecialchars($s['titulo']) ?>
                                    </h4>
                                    <span class="text-xs text-gray-400"><?= date('d/m/Y', strtotime($s['criado_em'])) ?></span>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require __DIR__ . '/partials/_footer.php'; ?>
</div>
</body>
</html>
