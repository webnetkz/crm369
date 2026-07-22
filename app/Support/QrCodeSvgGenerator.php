<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeSvgGenerator
{
    private const int QR_CODE_SIZE = 192;

    private const int QR_CODE_MARGIN = 4;

    private const int QR_BRAND_WIDTH = 84;

    private const int QR_BRAND_HEIGHT = 28;

    private const int QR_BRAND_RADIUS = 8;

    private const int QR_BRAND_FONT_SIZE = 15;

    public function svg(string $value): string
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(
                    self::QR_CODE_SIZE,
                    self::QR_CODE_MARGIN,
                    null,
                    null,
                    Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(15, 23, 42)),
                ),
                new SvgImageBackEnd,
            )
        ))->writeString($value, ecLevel: ErrorCorrectionLevel::H());

        return $this->injectBrandLabel(trim(substr($svg, strpos($svg, "\n") + 1)));
    }

    public function dataUri(string $value): string
    {
        return 'data:image/svg+xml;utf8,'.rawurlencode($this->svg($value));
    }

    private function injectBrandLabel(string $svg): string
    {
        $brandX = (self::QR_CODE_SIZE - self::QR_BRAND_WIDTH) / 2;
        $brandY = (self::QR_CODE_SIZE - self::QR_BRAND_HEIGHT) / 2;
        $textX = self::QR_CODE_SIZE / 2;
        $textY = ($brandY + (self::QR_BRAND_HEIGHT / 2)) + 5;

        $overlay = sprintf(
            '<g aria-label="CRM369 mark">'
                .'<rect x="%d" y="%d" width="%d" height="%d" rx="%d" fill="#ffffff" stroke="#0f172a" stroke-width="1.5" />'
                .'<text x="%d" y="%d" text-anchor="middle" font-family="Instrument Sans, Arial, sans-serif" font-size="%d" font-weight="700" letter-spacing="0.8" fill="#0f172a">CRM369</text>'
            .'</g>',
            $brandX,
            $brandY,
            self::QR_BRAND_WIDTH,
            self::QR_BRAND_HEIGHT,
            self::QR_BRAND_RADIUS,
            $textX,
            $textY,
            self::QR_BRAND_FONT_SIZE,
        );

        return str_replace('</svg>', $overlay.'</svg>', $svg);
    }
}
