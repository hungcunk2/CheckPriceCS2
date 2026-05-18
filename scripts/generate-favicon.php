<?php

/**
 * Generate favicon assets from public/images/logo.png
 * Run: php scripts/generate-favicon.php
 */

$source = dirname(__DIR__) . '/public/images/logo.png';
$public = dirname(__DIR__) . '/public';

if (! is_file($source)) {
    fwrite(STDERR, "Missing source: {$source}\n");
    exit(1);
}

if (! extension_loaded('gd')) {
    fwrite(STDERR, "PHP GD extension is required.\n");
    exit(1);
}

$sizes = [
    'favicon-16x16.png' => 16,
    'favicon-32x32.png' => 32,
    'apple-touch-icon.png' => 180,
];

foreach ($sizes as $filename => $size) {
    $png = resizeLogoPng($source, $size);
    file_put_contents("{$public}/{$filename}", $png);
    echo "Wrote {$filename}\n";
}

$ico32 = resizeLogoPng($source, 32);
$ico16 = resizeLogoPng($source, 16);
file_put_contents("{$public}/favicon.ico", buildIco([16 => $ico16, 32 => $ico32]));
echo "Wrote favicon.ico\n";

function resizeLogoPng(string $source, int $size): string
{
    $src = imagecreatefrompng($source);
    if ($src === false) {
        throw new RuntimeException("Cannot read {$source}");
    }

    $w = imagesx($src);
    $h = imagesy($src);
    $dst = imagecreatetruecolor($size, $size);

    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
    imagefilledrectangle($dst, 0, 0, $size, $size, $transparent);

    $scale = min($size / $w, $size / $h);
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $ox = (int) floor(($size - $nw) / 2);
    $oy = (int) floor(($size - $nh) / 2);

    imagecopyresampled($dst, $src, $ox, $oy, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);

    ob_start();
    imagepng($dst);
    $png = (string) ob_get_clean();
    imagedestroy($dst);

    return $png;
}

/**
 * ICO container with embedded PNG (Windows Vista+ / all modern browsers).
 *
 * @param array<int, string> $pngBySize
 */
function buildIco(array $pngBySize): string
{
    ksort($pngBySize);
    $count = count($pngBySize);
    $header = pack('vvv', 0, 1, $count);
    $entries = '';
    $data = '';
    $offset = 6 + (16 * $count);

    foreach ($pngBySize as $size => $png) {
        $dim = $size >= 256 ? 0 : $size;
        $len = strlen($png);
        $entries .= pack(
            'CCCCvvVV',
            $dim,
            $dim,
            0,
            0,
            1,
            32,
            $len,
            $offset
        );
        $data .= $png;
        $offset += $len;
    }

    return $header . $entries . $data;
}
