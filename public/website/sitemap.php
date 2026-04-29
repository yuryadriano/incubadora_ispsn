<?php
require_once __DIR__ . '/../../config/config.php';

// Set content type to XML
header("Content-type: text/xml");

echo '<?xml version="1.0" encoding="UTF-8"?>';
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

// Base URL (detect automatically)
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
$host = $_SERVER['HTTP_HOST'];
$base_url = $protocol . "://" . $host . "/incubadora_ispsn/public/website/";

// Static Pages
$static_pages = [
    'index.php',
    'sobre.php',
    'candidatura.php'
];

foreach ($static_pages as $page) {
    echo '<url>';
    echo '<loc>' . $base_url . $page . '</loc>';
    echo '<changefreq>weekly</changefreq>';
    echo '<priority>0.8</priority>';
    echo '</url>';
}

// Dynamic News Pages
$res = $mysqli->query("SELECT id, atualizado_em, criado_em FROM publicacoes_website WHERE status = 'publicado' ORDER BY criado_em DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $date = $row['atualizado_em'] ?: $row['criado_em'];
        echo '<url>';
        echo '<loc>' . $base_url . 'noticia.php?id=' . $row['id'] . '</loc>';
        echo '<lastmod>' . date('Y-m-d', strtotime($date)) . '</lastmod>';
        echo '<changefreq>monthly</changefreq>';
        echo '<priority>0.6</priority>';
        echo '</url>';
    }
}

echo '</urlset>';
