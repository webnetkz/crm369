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
        ->toContain('postgresql-contrib')
        ->toContain('supervisor')
        ->toContain('ufw')
        ->toContain('php8.4-fpm')
        ->toContain('php8.4-pgsql')
        ->toContain('php8.4-redis')
        ->toContain('npm ci')
        ->toContain('npm run build')
        ->toContain('composer check-platform-reqs --no-dev')
        ->toContain('appendonly yes')
        ->toContain('appendfsync everysec')
        ->toContain('maxmemory-policy noeviction')
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

test('ubuntu installer creates a domain-specific nginx site and enables https', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));

    expect($installer)->toBeString()
        ->toContain('nginx_available_path="/etc/nginx/sites-available/${domain}.conf"')
        ->toContain('nginx_enabled_path="/etc/nginx/sites-enabled/${domain}.conf"')
        ->toContain('cat > "$nginx_available_path"')
        ->toContain('ln -sfn "$nginx_available_path" "$nginx_enabled_path"')
        ->toContain('server_name ${domain};')
        ->toContain('root ${APP_DIR}/public;')
        ->toContain('access_log /var/log/nginx/${domain}.access.log;')
        ->toContain('error_log /var/log/nginx/${domain}.error.log;')
        ->toContain("ufw allow 'Nginx Full'")
        ->toContain('certbot --nginx')
        ->toContain('--domains "$domain"')
        ->toContain('systemctl enable --now certbot.timer')
        ->toContain('certbot renew --dry-run')
        ->toContain('/etc/letsencrypt/live/${domain}/fullchain.pem')
        ->toContain('/etc/letsencrypt/live/${domain}/privkey.pem')
        ->not->toContain("}\n}\n}\nNGINX");
});

test('ubuntu installer verifies every required production service', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));

    expect($installer)->toBeString()
        ->toContain('systemctl is-active --quiet nginx')
        ->toContain('systemctl is-active --quiet "php${PHP_VERSION}-fpm"')
        ->toContain('systemctl is-active --quiet postgresql')
        ->toContain('systemctl is-active --quiet redis-server')
        ->toContain('systemctl is-active --quiet supervisor')
        ->toContain('systemctl is-active --quiet certbot.timer')
        ->toContain('runuser -u postgres -- pg_isready')
        ->toContain('[[ -S "/run/php/php${PHP_VERSION}-fpm.sock" ]]')
        ->toContain('/usr/bin/php8.4 -m')
        ->toContain('CONFIG GET appendonly')
        ->toContain('CONFIG GET maxmemory-policy')
        ->toContain('redis-cli ping')
        ->toContain('supervisorctl status crm369-default')
        ->toContain('supervisorctl status crm369-notifications')
        ->toContain('curl -fsS --max-time 20 --resolve');
});

test('ubuntu installer keeps queue retry windows above worker timeouts', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));

    expect(preg_match('~DB_QUEUE_RETRY_AFTER=(\d+)~', $installer, $databaseRetryAfter))->toBe(1)
        ->and(preg_match('~REDIS_QUEUE_RETRY_AFTER=(\d+)~', $installer, $redisRetryAfter))->toBe(1)
        ->and(preg_match('~queue:work database[^\n]+--timeout=(\d+)~', $installer, $databaseWorkerTimeout))->toBe(1)
        ->and(preg_match('~queue:work redis[^\n]+--timeout=(\d+)~', $installer, $redisWorkerTimeout))->toBe(1)
        ->and((int) $databaseRetryAfter[1])->toBeGreaterThan((int) $databaseWorkerTimeout[1])
        ->and((int) $redisRetryAfter[1])->toBeGreaterThan((int) $redisWorkerTimeout[1]);
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
