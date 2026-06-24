<?php
// scratch/fix_encoding.php

function removeBOM($filename) {
    if (!file_exists($filename)) return false;
    $content = file_get_contents($filename);
    $bom = pack("CCC", 0xef, 0xbb, 0xbf);
    if (0 === strncmp($content, $bom, 3)) {
        echo "BOM detectado e removido de: $filename\n";
        $content = substr($content, 3);
        file_put_contents($filename, $content);
        return true;
    }
    return false;
}

function recursiveScan($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || $file === 'vendor' || $file === '.git') continue;
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            recursiveScan($path);
        } elseif (is_file($path) && pathinfo($path, PATHINFO_EXTENSION) === 'php') {
            removeBOM($path);
        }
    }
}

echo "=== SCANNING FOR BOM IN ALL PHP FILES ===\n";
recursiveScan(dirname(__DIR__));
echo "Scan concluído!\n";
?>
