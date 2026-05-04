<?php
$inputFile = __DIR__ . '/assets/img/logo_sn_premium.png';
$outputFile = __DIR__ . '/assets/img/logo_sn_transparent.png';

$img = imagecreatefromjpeg($inputFile);
if (!$img) {
    die("Failed to open image");
}

imagealphablending($img, false);
imagesavealpha($img, true);

$width = imagesx($img);
$height = imagesy($img);

// Define a tolerância para o branco (0 a 255).
$tolerance = 15;

for ($x = 0; $x < $width; $x++) {
    for ($y = 0; $y < $height; $y++) {
        $color = imagecolorat($img, $x, $y);
        $r = ($color >> 16) & 0xFF;
        $g = ($color >> 8) & 0xFF;
        $b = $color & 0xFF;

        // Se for muito próximo de branco, torna transparente
        if ($r >= (255 - $tolerance) && $g >= (255 - $tolerance) && $b >= (255 - $tolerance)) {
            $transparent = imagecolorallocatealpha($img, 255, 255, 255, 127);
            imagesetpixel($img, $x, $y, $transparent);
        }
    }
}

imagepng($img, $outputFile);
imagedestroy($img);

echo "Image created successfully at: " . $outputFile;
?>
