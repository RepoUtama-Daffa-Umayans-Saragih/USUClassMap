<?php

namespace App\Services\Payment\Services;

class QrCodeSvgGenerator
{
    /**
     * Generate pure SVG string for a given text / QRIS payload.
     *
     * @param string $data The string payload to encode
     * @param int $size Display width/height in px
     * @param int $margin Number of quiet zone modules
     * @return string Valid SVG markup
     */
    public static function generateSvg(string $data, int $size = 280, int $margin = 4): string
    {
        require_once __DIR__ . '/QRCodeLib.php';

        // Use L error correction level for maximum data density (standard for EMVCo QRIS dynamic)
        $qr = \QRCode::getMinimumQRCode($data, QR_ERROR_CORRECT_LEVEL_L);
        $count = $qr->getModuleCount();
        $viewBoxSize = $count + ($margin * 2);

        $pathD = '';
        for ($r = 0; $r < $count; $r++) {
            for ($c = 0; $c < $count; $c++) {
                if ($qr->isDark($r, $c)) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $pathD .= "M{$x},{$y}h1v1h-1z ";
                }
            }
        }

        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$viewBoxSize} {$viewBoxSize}" width="{$size}" height="{$size}" shape-rendering="crispEdges" class="mx-auto select-none rounded-lg shadow-sm">
    <rect width="100%" height="100%" fill="#ffffff" rx="8" />
    <path d="{$pathD}" fill="#111827" />
</svg>
SVG;

        return trim($svg);
    }

    /**
     * Generate data URI format for use in img src attributes.
     */
    public static function generateDataUri(string $data, int $size = 280): string
    {
        $svg = self::generateSvg($data, $size);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
