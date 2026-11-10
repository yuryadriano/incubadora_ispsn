<?php
// Partial partilhado para garantir carregamento 100% imediato do Design System (imune a cache de CDN/Cloudflare em ficheiros .css estáticos)
$cssVersion = defined('ASSET_VERSION') ? ASSET_VERSION : '2026111005';
$cssFilePath = __DIR__ . '/../assets/style.css';
?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/incubadora_ispsn/public/website/assets/ipil-theme.css?v=<?= htmlspecialchars($cssVersion) ?>">
    <?php if (is_file($cssFilePath)): ?>
    <style id="ipil-core-styles"><?php readfile($cssFilePath); ?></style>
    <?php endif; ?>

