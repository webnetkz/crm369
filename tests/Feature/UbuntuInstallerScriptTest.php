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
        ->and($help->getOutput())->toContain('CRM369')
        ->and($help->getOutput())->toContain('--resume');
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
        ->toContain('"${APP_DIR}/artisan" migrate --force')
        ->toContain('"${APP_DIR}/artisan" crm369:install')
        ->toContain('artisan schedule:run')
        ->toContain('queue:work database')
        ->toContain('queue:work redis')
        ->toContain('certbot --nginx')
        ->toContain('git ls-remote --exit-code')
        ->toContain('https://github.com/${GITHUB_REPOSITORY}/archive/${source_commit}.tar.gz')
        ->toContain('[[ "$source_commit" =~ ^[0-9a-f]{40}$ ]]')
        ->toContain('source_commit=%s')
        ->not->toContain('GITHUB_TOKEN')
        ->not->toContain('Authorization: Bearer')
        ->not->toContain('db:seed')
        ->not->toContain('migrate --seed');
});

test('ubuntu installer identifies its release and verifies the Laravel health route over https', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));

    expect($installer)->toBeString()
        ->toContain("readonly INSTALLER_VERSION='2026.07.24.1'")
        ->toContain('Версия установщика: ${INSTALLER_VERSION}')
        ->toContain('installer_version=%s')
        ->toContain("'2026.07.22.1'|'2026.07.22.2'|'2026.07.22.3'|'2026.07.22.4'|'2026.07.22.5'|'2026.07.24.1'")
        ->toContain('sudo bash -s -- --resume')
        ->toContain('Версия частичной установки')
        ->toContain('"http://${domain}/up"')
        ->toContain('"https://${domain}/up"')
        ->toContain("[[ \"\$pre_tls_health_status\" == '200' ]]")
        ->toContain("[[ \"\$pre_tls_application_status\" == '200' ]]")
        ->toContain("[[ \"\$public_http_health_status\" == '200' ]]")
        ->toContain("[[ \"\$local_https_health_status\" == '200' ]]")
        ->toContain("[[ \"\$local_https_application_status\" == '200' ]]")
        ->toContain("[[ \"\$public_https_health_status\" == '200' ]]")
        ->toContain("[[ \"\$public_https_application_status\" == '200' ]]")
        ->toContain('--retry-connrefused')
        ->toContain("--write-out '%{http_code}'")
        ->toContain('HTTPS-проверка Laravel и страницы входа успешно завершена');
});

test('ubuntu installer applies every required parent migration before the general PostgreSQL pass', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));
    $dependencyMigrations = [
        '--path=database/migrations/0001_01_01_000000_create_users_table.php',
        '--path=database/migrations/2026_06_28_134816_create_user_groups_table.php',
        '--path=database/migrations/2026_06_29_005139_create_chat_conversations_table.php',
        '--path=database/migrations/2026_06_29_010910_create_knowledge_bases_table.php',
        '--path=database/migrations/2026_06_29_142826_create_crm_funnels_table.php',
    ];
    $dependencyMigrationCommandPosition = strpos($installer, '"${migration_dependency_options[@]}"');
    $generalMigrationPosition = strpos(
        $installer,
        '"${APP_DIR}/artisan" migrate --force --no-interaction',
        $dependencyMigrationCommandPosition,
    );

    foreach ($dependencyMigrations as $dependencyMigration) {
        expect(substr_count($installer, $dependencyMigration))->toBe(1);
    }

    expect($dependencyMigrationCommandPosition)->toBeInt()->toBeLessThan($generalMigrationPosition)
        ->and($generalMigrationPosition)->toBeInt()
        ->and($installer)->toContain('Применение миграций в порядке зависимостей PostgreSQL');
});

test('ubuntu installer verifies the optimized Laravel application is in production mode', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));
    $optimizePosition = strpos($installer, '"${APP_DIR}/artisan" optimize --no-interaction');
    $environmentCheckPosition = strpos($installer, 'production_environment_report=', $optimizePosition);
    $installedStatePosition = strpos($installer, '>"$INSTALL_STATE_FILE"', $environmentCheckPosition);

    expect($installer)->toBeString()
        ->toContain('config:show app.env')
        ->toContain('config:show app.debug')
        ->toContain('app\.env[[:space:].]+production')
        ->toContain('app\.debug[[:space:].]+false')
        ->and($optimizePosition)->toBeInt()->toBeLessThan($environmentCheckPosition)
        ->and($environmentCheckPosition)->toBeInt()->toBeLessThan($installedStatePosition)
        ->and($installedStatePosition)->toBeInt();
});

test('ubuntu installer verifies every migration and critical PostgreSQL table before creating the administrator', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));
    $generalMigrationPosition = strpos($installer, '"${APP_DIR}/artisan" migrate --force --no-interaction');
    $migrationCountPosition = strpos($installer, 'expected_migration_count=', $generalMigrationPosition);
    $criticalTablePosition = strpos($installer, 'critical_table_count=', $migrationCountPosition);
    $administratorPosition = strpos($installer, '"${APP_DIR}/artisan" crm369:install', $criticalTablePosition);

    expect($installer)->toBeString()
        ->toContain('SELECT COUNT(*) FROM migrations')
        ->toContain("table_name IN ('migrations', 'users', 'jobs', 'notifications', 'chat_conversations', 'chat_conversation_participants', 'chat_messages', 'knowledge_bases', 'knowledge_base_group', 'knowledge_base_articles', 'crm_funnels', 'crm_funnel_stages', 'crm_deals')")
        ->toContain('Все ${applied_migration_count} миграций и критические таблицы PostgreSQL проверены')
        ->and($generalMigrationPosition)->toBeInt()->toBeLessThan($migrationCountPosition)
        ->and($migrationCountPosition)->toBeInt()->toBeLessThan($criticalTablePosition)
        ->and($criticalTablePosition)->toBeInt()->toBeLessThan($administratorPosition)
        ->and($administratorPosition)->toBeInt();
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
        ->toContain('fastcgi_buffer_size 16k;')
        ->toContain('fastcgi_buffers 8 16k;')
        ->toContain('access_log /var/log/nginx/${domain}.access.log;')
        ->toContain('error_log /var/log/nginx/${domain}.error.log;')
        ->toContain("ufw allow 'Nginx Full'")
        ->toContain("ufw allow 'OpenSSH'")
        ->toContain('ufw --force enable')
        ->toContain("grep -qx 'Status: active'")
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
        ->toContain('pre_tls_health_status="$(curl -sS --max-time 20')
        ->toContain('pre_tls_application_status="$(curl -sS --max-time 20')
        ->toContain('public_http_health_status="$(curl -sS --max-time 30')
        ->toContain('local_https_health_status="$(curl -sS --max-time 20')
        ->toContain('local_https_application_status="$(curl -sS --max-time 20')
        ->toContain('public_https_health_status="$(curl -sS --max-time 30')
        ->toContain('public_https_application_status="$(curl -sS --max-time 30')
        ->toContain('"http://${domain}/login"')
        ->toContain('"https://${domain}/login"')
        ->toContain('is_int(\$configuration[\'queue\'][\'connections\'][\'redis\'][\'block_for\'])');
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

test('ubuntu installer uses absolute artisan paths across user boundaries', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));
    $permissionNormalizationPosition = strpos($installer, 'chown -R root:"$APP_GROUP" "$APP_DIR"');
    $writableEnvironmentPosition = strpos($installer, 'chmod 0660 "${APP_DIR}/.env"');
    $firstArtisanPosition = strpos($installer, '/usr/bin/php8.4 "${APP_DIR}/artisan" key:generate');
    $lockedEnvironmentPosition = strpos($installer, 'chmod 0640 "${APP_DIR}/.env"', $firstArtisanPosition);

    expect($installer)->toBeString()
        ->toContain('/usr/bin/php8.4 "${APP_DIR}/artisan" key:generate')
        ->toContain('/usr/bin/php8.4 "${APP_DIR}/artisan" migrate')
        ->toContain('/usr/bin/php8.4 "${APP_DIR}/artisan" crm369:install')
        ->toContain('/usr/bin/php8.4 "${APP_DIR}/artisan" storage:link')
        ->toContain('/usr/bin/php8.4 "${APP_DIR}/artisan" optimize')
        ->not->toContain('runuser -u "$APP_USER" -- /usr/bin/php8.4 artisan')
        ->and($permissionNormalizationPosition)->toBeInt()->toBeLessThan($firstArtisanPosition)
        ->and($writableEnvironmentPosition)->toBeInt()->toBeLessThan($firstArtisanPosition)
        ->and($lockedEnvironmentPosition)->toBeInt()->toBeGreaterThan($firstArtisanPosition);
});

test('ubuntu installer can safely resume its validated partial installation', function () {
    $installer = file_get_contents(base_path('scripts/install-ubuntu.sh'));

    expect($installer)->toBeString()
        ->toContain("resume_mode='false'")
        ->toContain('--resume)')
        ->toContain('read_env_value APP_URL')
        ->toContain('read_env_value SUPER_ADMIN_EMAIL')
        ->toContain('validate_resume_installation')
        ->toContain('database_exists')
        ->toContain('database_role_exists')
        ->toContain('database_owner')
        ->toContain('pg_get_userbyid')
        ->toContain('SELECT COUNT(*) FROM users')
        ->toContain('SELECT email FROM users')
        ->toContain('Продолжение прерванной установки')
        ->toContain('INSTALL_PROGRESS_FILE')
        ->not->toContain('Каталог ${APP_DIR} уже существует. Установщик не перезаписывает существующее развёртывание.')
        ->not->toContain('DROP DATABASE')
        ->not->toContain('DROP ROLE')
        ->not->toContain('rm -rf -- "$APP_DIR"');

    $freshBuildStart = strpos($installer, "if [[ \"\$resume_mode\" == 'false' ]]; then\n    print_info 'Установка Composer");
    $frontendBuildPosition = strpos($installer, 'VITE_APP_NAME=CRM369 npm run build', $freshBuildStart);
    $resumeBuildSkipPosition = strpos($installer, "else\n    print_info 'Исходный код, зависимости и frontend-сборка", $frontendBuildPosition);

    expect($freshBuildStart)->toBeInt()->toBeLessThan($frontendBuildPosition)
        ->and($frontendBuildPosition)->toBeInt()->toBeLessThan($resumeBuildSkipPosition)
        ->and($resumeBuildSkipPosition)->toBeInt();
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
