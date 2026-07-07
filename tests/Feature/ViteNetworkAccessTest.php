<?php

test('vite is configured for lan device access during development', function () {
    $viteConfig = file_get_contents(base_path('vite.config.ts'));

    expect($viteConfig)->toContain('loadEnv')
        ->and($viteConfig)->toContain("host: '0.0.0.0'")
        ->and($viteConfig)->toContain('const appUrl = resolveAppUrl(env.APP_URL);')
        ->and($viteConfig)->toContain('host: appUrl.hostname')
        ->and($viteConfig)->toContain("protocol: appUrl.protocol === 'https:' ? 'wss' : 'ws'")
        ->and($viteConfig)->toContain('detectTls: false');
});
