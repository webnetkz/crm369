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
        ->not->toContain('db:seed')
        ->not->toContain('migrate --seed');
});

test('readme documents the private repository one-command installation', function () {
    $readme = file_get_contents(base_path('README.md'));

    expect($readme)->toBeString()
        ->toContain('https://github.com/webnetkz/crm369')
        ->toContain('https://api.github.com/repos/webnetkz/crm369/contents/scripts/install-ubuntu.sh?ref=main')
        ->toContain('CRM369_GITHUB_TOKEN')
        ->toContain('php artisan migrate --force')
        ->toContain('не запускает')
        ->toContain('только указанный super-admin');
});
