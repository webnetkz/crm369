<?php

namespace App\Support;

use App\Models\User;
use GdImage;
use RuntimeException;

final class UserAvatarGenerator
{
    private const int SIZE = 512;

    private const array BACKGROUND_PALETTES = [
        ['#0F766E', '#5EEAD4'],
        ['#1D4ED8', '#93C5FD'],
        ['#7E22CE', '#D8B4FE'],
        ['#BE123C', '#FDA4AF'],
        ['#C2410C', '#FDBA74'],
        ['#3F6212', '#BEF264'],
        ['#4338CA', '#A5B4FC'],
        ['#A21CAF', '#F0ABFC'],
    ];

    private const array SKIN_TONES = [
        '#F8D5B7',
        '#EDB98A',
        '#D89A6A',
        '#B8734F',
        '#8D5524',
        '#6B3E26',
    ];

    private const array HAIR_COLORS = [
        '#241C18',
        '#4A2C20',
        '#7A4E2D',
        '#C58C58',
        '#E7C46A',
        '#5A4A42',
        '#B23A48',
    ];

    private const array CLOTHING_COLORS = [
        '#0F172A',
        '#1E3A8A',
        '#155E75',
        '#166534',
        '#7C2D12',
        '#701A75',
        '#9F1239',
    ];

    public function generate(User $user): string
    {
        $seed = array_values(unpack('C*', hash(
            'sha256',
            $user->getKey().':'.$user->email,
            true,
        )));

        $image = imagecreatetruecolor(self::SIZE, self::SIZE);

        if (! $image instanceof GdImage) {
            throw new RuntimeException('Unable to create an avatar canvas.');
        }

        imageantialias($image, true);

        $background = self::BACKGROUND_PALETTES[$this->seedValue($seed, 0) % count(self::BACKGROUND_PALETTES)];
        $skinColor = self::SKIN_TONES[$this->seedValue($seed, 1) % count(self::SKIN_TONES)];
        $hairColor = self::HAIR_COLORS[$this->seedValue($seed, 2) % count(self::HAIR_COLORS)];
        $clothingColor = self::CLOTHING_COLORS[$this->seedValue($seed, 3) % count(self::CLOTHING_COLORS)];

        $this->drawGradient($image, $background[0], $background[1]);
        $this->drawBackgroundPattern($image, $seed);
        $this->drawPortrait($image, $seed, $skinColor, $hairColor, $clothingColor);

        ob_start();
        $wasWritten = imagepng($image, null, 8);
        $png = ob_get_clean();
        imagedestroy($image);

        if (! $wasWritten || ! is_string($png)) {
            throw new RuntimeException('Unable to encode the generated avatar.');
        }

        return $png;
    }

    private function drawGradient(GdImage $image, string $from, string $to): void
    {
        [$fromRed, $fromGreen, $fromBlue] = $this->rgb($from);
        [$toRed, $toGreen, $toBlue] = $this->rgb($to);

        for ($y = 0; $y < self::SIZE; $y++) {
            $ratio = $y / (self::SIZE - 1);
            $color = imagecolorallocate(
                $image,
                (int) round($fromRed + (($toRed - $fromRed) * $ratio)),
                (int) round($fromGreen + (($toGreen - $fromGreen) * $ratio)),
                (int) round($fromBlue + (($toBlue - $fromBlue) * $ratio)),
            );

            imageline($image, 0, $y, self::SIZE, $y, $color);
        }
    }

    /**
     * @param  array<int, int>  $seed
     */
    private function drawBackgroundPattern(GdImage $image, array $seed): void
    {
        $accent = imagecolorallocatealpha($image, 255, 255, 255, 95);

        for ($index = 0; $index < 6; $index++) {
            $x = ($this->seedValue($seed, 4 + ($index * 2)) * 2) % self::SIZE;
            $y = ($this->seedValue($seed, 5 + ($index * 2)) * 2) % self::SIZE;
            $diameter = 36 + ($this->seedValue($seed, 18 + $index) % 88);

            imagefilledellipse($image, $x, $y, $diameter, $diameter, $accent);
        }
    }

    /**
     * @param  array<int, int>  $seed
     */
    private function drawPortrait(
        GdImage $image,
        array $seed,
        string $skin,
        string $hair,
        string $clothing,
    ): void {
        $skinColor = $this->allocateColor($image, $skin);
        $skinShadow = $this->allocateColor($image, $this->shade($skin, -24));
        $hairColor = $this->allocateColor($image, $hair);
        $clothingColor = $this->allocateColor($image, $clothing);
        $clothingAccent = $this->allocateColor($image, $this->shade($clothing, 34));
        $featureColor = $this->allocateColor($image, '#292524');
        $eyeWhite = $this->allocateColor($image, '#FFFBEB');
        $mouthColor = $this->allocateColor($image, '#9F1239');

        $headWidth = 224 + ($this->seedValue($seed, 24) % 34);
        $headHeight = 286 + ($this->seedValue($seed, 25) % 28);
        $eyeSpacing = 40 + ($this->seedValue($seed, 26) % 18);
        $eyeY = 235 + ($this->seedValue($seed, 27) % 12);

        imagefilledellipse($image, 256, 510, 430, 260, $clothingColor);
        imagefilledpolygon($image, [184, 512, 219, 375, 293, 375, 328, 512], $clothingAccent);
        imagefilledrectangle($image, 222, 330, 290, 420, $skinShadow);

        if ($this->seedValue($seed, 28) % 4 === 0) {
            imagefilledellipse($image, 256, 85, 112, 112, $hairColor);
        }

        imagefilledellipse($image, 150, 258, 68, 82, $skinShadow);
        imagefilledellipse($image, 362, 258, 68, 82, $skinShadow);
        imagefilledellipse($image, 256, 238, $headWidth, $headHeight, $hairColor);
        imagefilledellipse($image, 256, 250, $headWidth - 12, $headHeight - 8, $skinColor);

        $hairStyle = $this->seedValue($seed, 29) % 4;

        if ($hairStyle === 0) {
            imagefilledellipse($image, 256, 125, $headWidth, 128, $hairColor);
        } elseif ($hairStyle === 1) {
            imagefilledpolygon($image, [142, 175, 187, 92, 265, 105, 236, 183], $hairColor);
            imagefilledpolygon($image, [236, 183, 265, 105, 342, 114, 370, 183], $hairColor);
        } elseif ($hairStyle === 2) {
            imagefilledellipse($image, 256, 118, $headWidth - 18, 90, $hairColor);
            imagefilledrectangle($image, 145, 128, 170, 235, $hairColor);
            imagefilledrectangle($image, 342, 128, 367, 235, $hairColor);
        }

        foreach ([-1, 1] as $direction) {
            $eyeX = 256 + ($eyeSpacing * $direction);

            imagefilledellipse($image, $eyeX, $eyeY, 31, 20, $eyeWhite);
            imagefilledellipse($image, $eyeX, $eyeY, 11, 14, $featureColor);
            imageline($image, $eyeX - 17, $eyeY - 20, $eyeX + 16, $eyeY - 24, $featureColor);
        }

        imageline($image, 256, $eyeY + 10, 247, 292, $skinShadow);
        imageline($image, 247, 292, 261, 294, $skinShadow);

        if ($this->seedValue($seed, 30) % 3 === 0) {
            imagearc($image, 256, 310, 70, 40, 15, 165, $mouthColor);
        } else {
            imagearc($image, 256, 327, 74, 54, 200, 340, $mouthColor);
        }

        if ($this->seedValue($seed, 31) % 4 === 0) {
            $glassesColor = $this->allocateColor($image, '#334155');
            imageellipse($image, 256 - $eyeSpacing, $eyeY, 54, 42, $glassesColor);
            imageellipse($image, 256 + $eyeSpacing, $eyeY, 54, 42, $glassesColor);
            imageline($image, 229, $eyeY, 283, $eyeY, $glassesColor);
        }
    }

    /**
     * @param  array<int, int>  $seed
     */
    private function seedValue(array $seed, int $index): int
    {
        return $seed[$index % count($seed)];
    }

    private function allocateColor(GdImage $image, string $hex): int
    {
        return imagecolorallocate($image, ...$this->rgb($hex));
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function shade(string $hex, int $amount): string
    {
        $channels = array_map(
            fn (int $channel): int => max(0, min(255, $channel + $amount)),
            $this->rgb($hex),
        );

        return sprintf('#%02X%02X%02X', ...$channels);
    }
}
