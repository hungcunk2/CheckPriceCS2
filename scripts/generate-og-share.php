<?php

/**
 * Ảnh chia sẻ Facebook / Zalo / X (1200×630). Chạy: php scripts/generate-og-share.php
 */

$source = dirname(__DIR__).'/public/images/logo.png';
$out = dirname(__DIR__).'/public/images/og-share.jpg';

if (! is_file($source)) {
    fwrite(STDERR, "Missing {$source}\n");
    exit(1);
}

if (! extension_loaded('gd')) {
    fwrite(STDERR, "Cần PHP GD.\n");
    exit(1);
}

$width = 1200;
$height = 630;

$src = imagecreatefrompng($source);
if ($src === false) {
    fwrite(STDERR, "Cannot read logo\n");
    exit(1);
}

$dst = imagecreatetruecolor($width, $height);
$bg = imagecolorallocate($dst, 13, 17, 23);
imagefilledrectangle($dst, 0, 0, $width, $height, $bg);

$accent = imagecolorallocate($dst, 88, 166, 255);
$white = imagecolorallocate($dst, 240, 246, 252);
$muted = imagecolorallocate($dst, 148, 163, 184);

imagestring($dst, 5, 48, 48, 'CheckPrice CS2', $white);
imagestring($dst, 3, 48, 88, 'Tra gia kho CS2 theo Buff163 - VND / USD', $muted);

$sw = imagesx($src);
$sh = imagesy($src);
$logoMax = 280;
$scale = min($logoMax / $sw, $logoMax / $sh);
$lw = max(1, (int) round($sw * $scale));
$lh = max(1, (int) round($sh * $scale));
$lx = (int) floor(($width - $lw) / 2);
$ly = (int) floor(($height - $lh) / 2) + 20;

imagealphablending($dst, true);
imagesavealpha($src, true);
imagecopyresampled($dst, $src, $lx, $ly, 0, 0, $lw, $lh, $sw, $sh);
imagedestroy($src);

imagerectangle($dst, 0, 0, $width - 1, $height - 1, $accent);

if (! imagejpeg($dst, $out, 90)) {
    fwrite(STDERR, "Cannot write {$out}\n");
    exit(1);
}

imagedestroy($dst);

echo "Wrote {$out} ({$width}x{$height})\n";
