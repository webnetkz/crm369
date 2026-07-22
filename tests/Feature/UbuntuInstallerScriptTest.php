<?php

use Symfony\Component\Process\Process;

test('ubuntu installer has valid bash syntax and exposes usage without root access', function () {
    $installerPath = base_path('scripts/install-ubuntu.sh');

    expect($installerPath)->toBeFile();

    $syntaxCheck = new Process(['bash', '-n', $installerPath]);
    $syntaxCheck->run();

    expect($syntaxCheck->isSuccessful())->toBeTrue($syntaxCheck->getErrorOutput());

    $help = new Process(['bash', $installerPath, '--help']);
    $help->run();

    expect($help->isSuccessful())->toBeTrue($help->getErrorOutput())
        ->and($help->getOutput())->toContain('CRM369');
});

test('ubuntu installer provisions a production stack without demo data', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));

    expect($installer)->toBeString()
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('DB_CONNECTION=pgsql')
        ->toContain('redis-server')
        ->toContain('php8.4-fpm')
        ->toContain('npm ci')
        ->toContain('npm run build')
        ->toContain('artisan migrate --force')
        ->toContain('artisan crm369:install')
        ->toContain('artisan schedule:run')
        ->toContain('queue:work database')
        ->toContain('queue:work redis')
        ->toContain('certbot --nginx')
        ->toContain('https://github.com/${GITHUB_REPOSITORY}/archive/refs/heads/${GITHUB_REF}.tar.gz')
        ->not->toContain('GITHUB_TOKEN')
        ->not->toContain('Authorization: Bearer')
        ->not->toContain('db:seed')
        ->not->toContain('migrate --seed');
});

test('readme documents the public repository one-command installation in English', function () {
    $readme = file_get_contents(base_path('README.md'));

    expect($readme)->toBeString()
        ->toContain('https://github.com/webnetkz/crm369')
        ->toContain('curl -fsSL https://raw.githubusercontent.com/webnetkz/crm369/main/scripts/install-ubuntu.sh | sudo bash')
        ->toContain('php artisan migrate --force')
        ->toContain('does **not** run')
        ->toContain('contains only the super administrator')
        ->not->toContain('GitHub token')
        ->not->toContain('CRM369_GITHUB_TOKEN');
});

test('gitignore excludes local artifacts without hiding application source files', function () {
    $ignoredPaths = [
        '.env.local',
        '.idea/workspace.xml',
        'node_modules/example/index.js',
        'public/build/assets/app.js',
        'database/database.sqlite-wal',
        'playwright-report/index.html',
        'storage/logs/laravel.log',
        'applicationsOfMobileAndroid/.gradle/cache.bin',
        'applicationsOfMobileAndroid/app/build/app.apk',
        'applicationsOfMobileIos/DerivedData/cache.db',
        'applicationsOfMobileIos/CRM369iOS.xcodeproj/xcuserdata/user.xcuserdatad/UserInterfaceState.xcuserstate',
    ];

    foreach ($ignoredPaths as $path) {
        $check = new Process(['git', 'check-ignore', '--quiet', '--', $path], base_path());
        $check->run();

        expect($check->isSuccessful())->toBeTrue("Expected [{$path}] to be ignored.");
    }

    $sourcePaths = [
        '.env.example',
        'README.md',
        'scripts/install-ubuntu.sh',
        'applicationsOfMobileAndroid/app/src/main/java/kz/crm369/NewScreen.kt',
        'applicationsOfMobileAndroid/keystore.properties.example',
        'applicationsOfMobileIos/CRM369iOS/NewScreen.swift',
        'applicationsOfMobileIos/project.yml',
    ];

    foreach ($sourcePaths as $path) {
        $check = new Process(['git', 'check-ignore', '--quiet', '--', $path], base_path());
        $check->run();

        expect($check->isSuccessful())->toBeFalse("Expected [{$path}] to remain visible to Git.");
    }
});
