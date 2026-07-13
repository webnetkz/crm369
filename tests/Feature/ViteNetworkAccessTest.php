<?php

test('vite is configured for lan device access during development', function () {
    $viteConfig = file_get_contents(base_path('vite.config.ts'));

    expect($viteConfig)->toContain('loadEnv')
        ->and($viteConfig)->toContain("host: '0.0.0.0'")
        ->and($viteConfig)->toContain('const devServerPort = Number(env.VITE_PORT || 5173);')
        ->and($viteConfig)->toContain('const appUrl = resolveAppUrl(env.APP_URL);')
        ->and($viteConfig)->toContain("const detectTls = appUrl?.protocol === 'https:' ? appUrl.hostname : false;")
        ->and($viteConfig)->toContain('origin: devServerOrigin')
        ->and($viteConfig)->toContain('host: appUrl.hostname')
        ->and($viteConfig)->toContain('port: devServerPort')
        ->and($viteConfig)->toContain("protocol: appUrl.protocol === 'https:' ? 'wss' : 'ws'")
        ->and($viteConfig)->toContain('detectTls,');
});
