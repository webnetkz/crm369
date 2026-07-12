<?php

namespace App\Support;

use Illuminate\Support\Str;

class UserAgentDetails
{
    public function __construct(
        public readonly ?string $browser,
        public readonly ?string $platform,
        public readonly string $deviceType,
        public readonly string $signature,
    ) {}

    public static function from(?string $userAgent): self
    {
        $normalized = Str::of($userAgent ?? '')->lower();

        if ($normalized->isEmpty()) {
            return new self(
                browser: null,
                platform: null,
                deviceType: 'unknown',
                signature: hash('sha256', 'unknown'),
            );
        }

        return new self(
            browser: self::detectBrowser($normalized->value()),
            platform: self::detectPlatform($normalized->value()),
            deviceType: self::detectDeviceType($normalized->value()),
            signature: hash('sha256', $normalized->squish()->value()),
        );
    }

    private static function detectBrowser(string $userAgent): ?string
    {
        return match (true) {
            Str::contains($userAgent, 'edg/') => 'Microsoft Edge',
            Str::contains($userAgent, ['opr/', 'opera']) => 'Opera',
            Str::contains($userAgent, 'firefox/') => 'Firefox',
            Str::contains($userAgent, 'chrome/') && ! Str::contains($userAgent, 'edg/') => 'Chrome',
            Str::contains($userAgent, 'safari/') && ! Str::contains($userAgent, 'chrome/') => 'Safari',
            Str::contains($userAgent, ['msie', 'trident/']) => 'Internet Explorer',
            default => null,
        };
    }

    private static function detectPlatform(string $userAgent): ?string
    {
        return match (true) {
            Str::contains($userAgent, 'iphone') => 'iOS',
            Str::contains($userAgent, 'ipad') => 'iPadOS',
            Str::contains($userAgent, 'android') => 'Android',
            Str::contains($userAgent, 'windows') => 'Windows',
            Str::contains($userAgent, ['mac os x', 'macintosh']) => 'macOS',
            Str::contains($userAgent, 'linux') => 'Linux',
            default => null,
        };
    }

    private static function detectDeviceType(string $userAgent): string
    {
        return match (true) {
            Str::contains($userAgent, ['ipad', 'tablet']) => 'tablet',
            Str::contains($userAgent, ['iphone', 'android', 'mobile']) => 'mobile',
            Str::contains($userAgent, ['windows', 'macintosh', 'mac os x', 'linux']) => 'desktop',
            default => 'unknown',
        };
    }
}
