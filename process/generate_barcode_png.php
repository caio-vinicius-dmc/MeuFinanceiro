<?php
// Gera um PNG de código de barras Interleaved 2 of 5 (ITF) para leitura por apps móveis.
// Uso: process/generate_barcode_png.php?code=26091337299149385389835100000005312610000260161
$scale = isset($_GET['scale']) ? max(1, (int)$_GET['scale']) : 1; // escala para gerar imagens de maior resolução (ex: 2,3)
$narrow_base = isset($_GET['narrow']) ? max(1, (int)$_GET['narrow']) : 2; // largura do traço estreito em pixels (base)
$height = isset($_GET['height']) ? max(30, (int)$_GET['height']) : 80; // altura do código de barras (base)
$margin = isset($_GET['margin']) ? max(0, (int)$_GET['margin']) : 8; // margem esquerda/direita em pixels (base)
$fontHeight = isset($_GET['font']) ? max(8, (int)$_GET['font']) : 16; // espaço para o texto legível (base)

// Tipo de código (apenas 'itf' suportado por enquanto)
$type = isset($_GET['type']) ? strtolower($_GET['type']) : 'itf';
if ($type !== 'itf') {
    header('HTTP/1.1 501 Not Implemented');
    echo 'Requested barcode type not implemented. Supported: itf';
    exit;
}

if (!extension_loaded('gd')) {
    header('HTTP/1.1 500 Internal Server Error');
    echo 'GD extension is not available.';
    exit;
}

$code = isset($_GET['code']) ? preg_replace('/\D/', '', $_GET['code']) : '';

// If the input looks like a 'linha digitavel' (47 digits), convert to 44-digit barcode
if (strlen($code) === 47) {
    // Structure of linha digitavel (positions 1-based):
    // 1-9 (field1), 10 DV1, 11-20 (field2), 21 DV2, 22-31 (field3), 32 DV3, 33 DV geral, 34-47 fator+valor
    // We need to build barcode (44): bank(1-3) + currency(4) + dvGeral(33) + fator(34-37) + valor(38-47) + campoLivre(25)
    $linha = $code;
    $bank = substr($linha, 0, 3);
    $currency = substr($linha, 3, 1);
    $dvGeral = substr($linha, 32, 1);
    $fator = substr($linha, 33, 4);
    $valor = substr($linha, 37, 10);
    // free fields: positions (1-based) 5-9 -> 0-based 4..8 length 5
    $free1 = substr($linha, 4, 5);
    // positions 11-20 -> 0-based 10 length 10
    $free2 = substr($linha, 10, 10);
    // positions 22-31 -> 0-based 21 length 10
    $free3 = substr($linha, 21, 10);
    $campoLivre = $free1 . $free2 . $free3; // 5 + 10 + 10 = 25
    $barcode44 = $bank . $currency . $dvGeral . $fator . $valor . $campoLivre;
    if (strlen($barcode44) === 44) {
        $code = $barcode44;
    } else {
        // conversion failed, keep original (will attempt rendering but likely invalid)
        error_log('Linha digitavel conversion produced length ' . strlen($barcode44));
    }
}
if (empty($code)) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Missing code parameter.';
    exit;
}

// Pad with leading zero if odd length for ITF
if ((strlen($code) % 2) !== 0) {
    $code = '0' . $code;
}

// Detect bank by first 3 digits of the boleto barcode (common for Brazilian boletos)
$bankCode = substr($code, 0, 3);
$bankPresets = [
    '001' => ['name' => 'Banco do Brasil', 'narrow' => 2, 'height' => 90, 'marginMult' => 12, 'font' => 16, 'stop_variant' => 'std'],
    '033' => ['name' => 'Santander', 'narrow' => 2, 'height' => 80, 'marginMult' => 10, 'font' => 16, 'stop_variant' => 'std'],
    '104' => ['name' => 'Caixa', 'narrow' => 2, 'height' => 90, 'marginMult' => 12, 'font' => 16, 'stop_variant' => 'std'],
    '237' => ['name' => 'Bradesco', 'narrow' => 2, 'height' => 80, 'marginMult' => 10, 'font' => 16, 'stop_variant' => 'std'],
    '341' => ['name' => 'Itaú', 'narrow' => 2, 'height' => 80, 'marginMult' => 10, 'font' => 16, 'stop_variant' => 'std'],
    '260' => ['name' => 'Cora', 'narrow' => 1, 'height' => 100, 'marginMult' => 14, 'font' => 16, 'stop_variant' => 'alt'],
    '756' => ['name' => 'Sicoob', 'narrow' => 2, 'height' => 80, 'marginMult' => 10, 'font' => 16, 'stop_variant' => 'std']
];

// If user didn't pass narrow/height/margin explicitly, apply bank presets to improve compatibility
$userPassedNarrow = array_key_exists('narrow', $_GET);
$userPassedHeight = array_key_exists('height', $_GET);
$userPassedMargin = array_key_exists('margin', $_GET);
$userPassedFont = array_key_exists('font', $_GET);

// Apply presets to base values (before scaling)
if (isset($bankPresets[$bankCode])) {
    $preset = $bankPresets[$bankCode];
    if (!$userPassedNarrow) $narrow_base = $preset['narrow'];
    if (!$userPassedHeight) $height = $preset['height'];
    if (!$userPassedFont) $fontHeight = $preset['font'];
    if (!$userPassedMargin) $margin = max($margin, $preset['marginMult'] * $narrow_base);
    // allow preset to influence stop variant later
    if (!array_key_exists('stop_variant', $_GET)) {
        $_GET['stop_variant'] = $preset['stop_variant'];
    }
}

// Now apply global scale: this multiplies module pixel sizes to produce higher DPI images
$narrow = $narrow_base * $scale;
$height = $height * $scale;
$margin = $margin * $scale;
$fontHeight = $fontHeight * $scale;

// Digit patterns for ITF (5 modules, 2 wide per digit)
$patterns = [
    '0' => '00110',
    '1' => '10001',
    '2' => '01001',
    '3' => '11000',
    '4' => '00101',
    '5' => '10100',
    '6' => '01100',
    '7' => '00011',
    '8' => '10010',
    '9' => '01010'
];

// Start pattern (narrow bar, narrow space, narrow bar, narrow space)
$start = [1,1,1,1];

// Module scale: wide = 3 * narrow (standard ITF)
$wide_multiplier = 3;

// Ensure minimum quiet zone (recommended >= 10 * narrow module)
$margin = max($margin, 10 * $narrow);

// Allow alternative stop pattern via GET for testing compatibility with scanners
$stop_variant = isset($_GET['stop_variant']) && $_GET['stop_variant'] === 'alt' ? 'alt' : 'std';
// Standard stop historically used is [3,1,1] but some readers expect [1,1,3]
$stop = ($stop_variant === 'alt') ? [1,1,3] : [3,1,1];

$
// Build sequence of module widths; even indices are bars, odd are spaces
$sequence = [];
// Add start
foreach ($start as $s) $sequence[] = $s;

// Process digits in pairs
for ($i = 0; $i < strlen($code); $i += 2) {
    $d1 = $code[$i];
    $d2 = $code[$i+1];
    $p1 = $patterns[$d1];
    $p2 = $patterns[$d2];
    // Interleave: bar widths from p1, space widths from p2
    for ($j = 0; $j < 5; $j++) {
        $barWidth = ($p1[$j] === '1') ? $wide_multiplier : 1;
        $spaceWidth = ($p2[$j] === '1') ? $wide_multiplier : 1;
        $sequence[] = $barWidth;
        $sequence[] = $spaceWidth;
    }
}

// Add stop
foreach ($stop as $s) $sequence[] = $s;

// Calculate image width
$totalModules = array_sum($sequence);
// Calculate image width (use narrow module in pixels as base)
$imgWidth = $margin * 2 + $totalModules * $narrow;
$imgHeight = $height + $fontHeight + 10; // espaço extra para texto

$im = imagecreatetruecolor($imgWidth, $imgHeight);
$white = imagecolorallocate($im, 255, 255, 255);
$black = imagecolorallocate($im, 0, 0, 0);
imagefilledrectangle($im, 0, 0, $imgWidth, $imgHeight, $white);

$x = $margin;
$isBar = true; // sequence starts with a bar
foreach ($sequence as $module) {
    $widthPx = $module * $narrow;
    if ($isBar) {
        // Draw black bar
        imagefilledrectangle($im, $x, 0, $x + $widthPx - 1, $height, $black);
    }
    $x += $widthPx;
    $isBar = !$isBar;
}

// Draw human-readable text centered
$text = chunk_split($code, 4, ' ');
$font = 3; // built-in font size
$textBoxWidth = imagefontwidth($font) * strlen($text);
$textX = (int)(($imgWidth - $textBoxWidth) / 2);
$textY = $height + 4;
imagestring($im, $font, $textX, $textY, $text, $black);

header('Content-Type: image/png');
// Prefer using Picqer library if available (more compatible rendering)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $generator = new Picqer\Barcode\BarcodeGeneratorPNG();
        // width factor controls narrow module width; height controls bar height
        $widthFactor = max(1, $narrow); // use 'narrow' as widthFactor
        $barHeight = $height;
        $pngData = $generator->getBarcode($code, $generator::TYPE_INTERLEAVED_2_OF_5, $widthFactor, $barHeight);
        // If requested, save to disk as well
        $shouldSave = isset($_GET['save']) && ($_GET['save'] == '1' || $_GET['save'] === 'true');
        $saveId = isset($_GET['id']) ? preg_replace('/\D/', '', $_GET['id']) : null;
        if ($shouldSave) {
            $outDir = __DIR__ . '/tmp_barcodes';
            if (!is_dir($outDir)) @mkdir($outDir, 0755, true);
            if (!empty($saveId)) {
                $outFile = $outDir . '/boleto_cobranca_' . $saveId . '.png';
                $outBmp = $outDir . '/boleto_cobranca_' . $saveId . '.bmp';
            } else {
                $outFile = $outDir . '/boleto_' . sha1($code) . '.png';
                $outBmp = $outDir . '/boleto_' . sha1($code) . '.bmp';
            }
            @file_put_contents($outFile, $pngData);
            // Try to create BMP (uncompressed) from PNG data if possible
            if (function_exists('imagecreatefromstring') && function_exists('imagebmp')) {
                $imFrom = @imagecreatefromstring($pngData);
                if ($imFrom) {
                    @imagebmp($imFrom, $outBmp);
                    imagedestroy($imFrom);
                }
            }
        }
        header('Content-Type: image/png');
        echo $pngData;
        exit;
    } catch (Throwable $e) {
        // fallback to GD rendering below
        error_log('Picqer barcode generation failed: ' . $e->getMessage());
    }
}

// Fallback: original GD output
// Capture PNG bytes and optionally save
ob_start();
imagepng($im);
$pngBytes = ob_get_clean();

$shouldSaveGd = isset($_GET['save']) && ($_GET['save'] == '1' || $_GET['save'] === 'true');
$saveIdGd = isset($_GET['id']) ? preg_replace('/\D/', '', $_GET['id']) : null;
    if ($shouldSaveGd) {
    $outDir = __DIR__ . '/tmp_barcodes';
    if (!is_dir($outDir)) @mkdir($outDir, 0755, true);
    if (!empty($saveIdGd)) {
        $outFile = $outDir . '/boleto_cobranca_' . $saveIdGd . '.png';
        $outBmp = $outDir . '/boleto_cobranca_' . $saveIdGd . '.bmp';
    } else {
        $outFile = $outDir . '/boleto_' . sha1($code) . '.png';
        $outBmp = $outDir . '/boleto_' . sha1($code) . '.bmp';
    }
    @file_put_contents($outFile, $pngBytes);
    // Try to save BMP from GD image resource if available
    if (function_exists('imagebmp')) {
        // Recreate image from PNG bytes and save BMP
        $imFrom = @imagecreatefromstring($pngBytes);
        if ($imFrom) {
            @imagebmp($imFrom, $outBmp);
            imagedestroy($imFrom);
        }
    }
}

echo $pngBytes;
imagedestroy($im);
exit;

?>
