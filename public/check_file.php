<?php
// public/check_file.php
$file = $_GET['file'] ?? '';
if (empty($file)) {
    die("Passe ?file=nome_do_arquivo.pdf");
}

$path = __DIR__ . '/../uploads/pitches/' . basename($file);
echo "<h3>Verificação de Ficheiro</h3>";
echo "<p><strong>Caminho absoluto procurado:</strong> " . realpath(dirname($path)) . '/' . basename($path) . "</p>";
if (file_exists($path)) {
    echo "<p style='color: green; font-weight: bold;'>O ficheiro EXISTE no servidor!</p>";
    echo "<p>Tamanho: " . filesize($path) . " bytes</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>O ficheiro NÃO EXISTE no servidor (404 físico).</p>";
}
?>
