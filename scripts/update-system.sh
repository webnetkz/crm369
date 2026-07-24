#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

readonly APP_PATH='/var/www/crm369'
readonly RELEASES_PATH='/var/www/crm369-releases'
readonly SHARED_PATH='/var/www/crm369-shared'
readonly BACKUPS_PATH='/var/backups/crm369'
readonly COMPOSER_HOME_PATH='/var/cache/crm369/composer'
readonly STATE_PATH='/etc/crm369'
readonly PROGRESS_PATH='/var/lib/crm369/updates'
readonly REPOSITORY='webnetkz/crm369'
readonly APP_USER='www-data'
readonly APP_GROUP='www-data'
readonly PHP_VERSION='8.4'

run_uuid=''
component=''
target=''
version_label=''
state_file=''
steps_file=''
log_file=''
previous_release=''
release_switched='false'
laravel_backup_path=''
laravel_candidate_path=''
laravel_dependencies_switched='false'
database_backup_file=''
database_migrations_started='false'
maintenance_started='false'
workers_stopped='false'

usage() {
    printf 'Usage: crm369-updater start <uuid> <component> [target] [version]\n' >&2
    exit 64
}

validate_arguments() {
    [[ "$run_uuid" =~ ^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$ ]] \
        || usage

    case "$component" in
        application|laravel|php|postgresql|redis|nginx|node|composer|ubuntu)
            ;;
        *)
            usage
            ;;
    esac

    if [[ "$component" == 'application' ]]; then
        [[ "$target" =~ ^[0-9a-f]{40}$ ]] || usage
        if [[ -n "$version_label" ]]; then
            [[ "$version_label" =~ ^[A-Za-z0-9._-]{1,64}$ ]] || usage
        fi
    elif [[ -n "$target" ]]; then
        [[ "$target" =~ ^[A-Za-z0-9.+:~_-]{1,100}$ ]] || usage
        [[ -z "$version_label" ]] || usage
    fi
}

prepare_progress_files() {
    install -d -m 0750 -o root -g "$APP_GROUP" "$PROGRESS_PATH"
    state_file="${PROGRESS_PATH}/${run_uuid}.json"
    steps_file="${PROGRESS_PATH}/${run_uuid}.steps"
    log_file="${PROGRESS_PATH}/${run_uuid}.log"
    install -m 0640 -o root -g "$APP_GROUP" /dev/null "$steps_file"
    install -m 0640 -o root -g "$APP_GROUP" /dev/null "$log_file"
}

write_progress() {
    local status="$1"
    local progress="$2"
    local stage="$3"
    local message="$4"
    local finished_at=''

    if [[ "$status" == 'completed' || "$status" == 'failed' ]]; then
        finished_at="$(date --iso-8601=seconds)"
    fi

    python3 - "$state_file" "$steps_file" "$status" "$progress" "$stage" "$message" "$finished_at" <<'PY'
import json
import os
import sys
import tempfile
from datetime import datetime, timezone

state_path, steps_path, status, progress, stage, message, finished_at = sys.argv[1:]
now = datetime.now(timezone.utc).isoformat()
started_at = now

try:
    with open(state_path, encoding="utf-8") as source:
        previous = json.load(source)
        started_at = previous.get("started_at") or now
except (FileNotFoundError, json.JSONDecodeError, OSError):
    pass

state = {
    "status": status,
    "progress": max(0, min(100, int(progress))),
    "stage": stage[:120],
    "message": message[:1000],
    "started_at": started_at,
    "finished_at": finished_at or None,
}

directory = os.path.dirname(state_path)
fd, temporary_path = tempfile.mkstemp(dir=directory, prefix=".progress-", text=True)
with os.fdopen(fd, "w", encoding="utf-8") as output:
    json.dump(state, output, ensure_ascii=False)
    output.write("\n")
os.chmod(temporary_path, 0o640)
os.replace(temporary_path, state_path)

step = {
    "at": now,
    "progress": state["progress"],
    "stage": state["stage"],
    "message": state["message"],
}
with open(steps_path, "a", encoding="utf-8") as output:
    output.write(json.dumps(step, ensure_ascii=False) + "\n")
PY
    chown root:"$APP_GROUP" "$state_file" "$steps_file"
}

fail_update() {
    local line_number="$1"
    local exit_code="$2"
    local database_restore_failed='false'

    trap - ERR
    set +e

    if [[ "$database_migrations_started" == 'true' ]]; then
        if [[ "$workers_stopped" != 'true' ]]; then
            workers_stop
        fi

        if [[ -f "${APP_PATH}/artisan" ]]; then
            app_command down --retry=10 --refresh=5 --no-interaction
            maintenance_started='true'
        fi
    fi

    if [[ "$component" == 'laravel' && -n "$laravel_backup_path" ]]; then
        if [[ "$laravel_dependencies_switched" == 'true' ]]; then
            restore_laravel_dependencies >>"$log_file" 2>&1
        else
            rm -rf -- "$laravel_backup_path"
        fi

        laravel_backup_path=''
    fi

    cleanup_laravel_candidate

    if [[ "$release_switched" == 'true' && -n "$previous_release" && -d "$previous_release" ]]; then
        if [[ ! -e "${previous_release}/.env" && -e "${SHARED_PATH}/.env" ]]; then
            ln -sfn "${SHARED_PATH}/.env" "${previous_release}/.env"
        fi
        if [[ ! -e "${previous_release}/storage" && -e "${SHARED_PATH}/storage" ]]; then
            ln -sfn "${SHARED_PATH}/storage" "${previous_release}/storage"
        fi
        ln -sfn "$previous_release" "$APP_PATH"
    fi

    if [[ "$database_migrations_started" == 'true' ]] && ! database_restore; then
        database_restore_failed='true'
    fi

    if [[ -f "${APP_PATH}/artisan" && (
        "$maintenance_started" == 'true'
        || "$database_migrations_started" == 'true'
        || "$laravel_dependencies_switched" == 'true'
        || "$release_switched" == 'true'
    ) ]]; then
        app_command optimize --no-interaction
        app_command up --no-interaction
        maintenance_started='false'
        systemctl reload "php${PHP_VERSION}-fpm" >>"$log_file" 2>&1
    fi

    if [[ "$workers_stopped" == 'true' ]]; then
        workers_start
    fi

    if [[ "$database_restore_failed" == 'true' ]]; then
        write_progress 'failed' '100' 'rollback_failed' "Обновление остановлено на этапе ${component} (строка ${line_number}). Код ошибки: ${exit_code}. Автоматическое восстановление PostgreSQL завершилось ошибкой; резервная копия: ${database_backup_file}."
    else
        write_progress 'failed' '100' 'failed' "Обновление остановлено на этапе ${component} (строка ${line_number}). Код ошибки: ${exit_code}. Код и база данных возвращены к предыдущему состоянию."
    fi

    exit "$exit_code"
}

app_command() {
    runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_PATH}/artisan" "$@" >>"$log_file" 2>&1
}

composer_command() {
    install -d -m 0700 -o root -g root "$COMPOSER_HOME_PATH"
    COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_HOME="$COMPOSER_HOME_PATH" composer "$@"
}

maintenance_begin() {
    app_command down --retry=10 --refresh=5 --no-interaction
    maintenance_started='true'
    workers_stop
}

ensure_storage_link() {
    local storage_link="${APP_PATH}/public/storage"
    local storage_target="${APP_PATH}/storage/app/public"

    [[ ! -e "$storage_link" || -L "$storage_link" ]]
    ln -sfn "$storage_target" "$storage_link"
    chown -h root:"$APP_GROUP" "$storage_link"
}

maintenance_finish() {
    app_command migrate --force --no-interaction --isolated
    ensure_storage_link
    app_command optimize --no-interaction
    app_command up --no-interaction
    maintenance_started='false'
    systemctl reload "php${PHP_VERSION}-fpm" >>"$log_file" 2>&1
}

workers_stop() {
    local program=''

    for program in crm369-default crm369-notifications; do
        if supervisorctl status "$program" 2>>"$log_file" | grep -qE '^[^[:space:]]+[[:space:]]+RUNNING([[:space:]]|$)'; then
            supervisorctl stop "$program" >>"$log_file" 2>&1
        fi
    done

    workers_stopped='true'
}

workers_start() {
    local program=''

    for program in crm369-default crm369-notifications; do
        if ! supervisorctl status "$program" 2>>"$log_file" | grep -qE '^[^[:space:]]+[[:space:]]+RUNNING([[:space:]]|$)'; then
            supervisorctl start "$program" >>"$log_file" 2>&1
        fi
    done

    workers_stopped='false'
}

health_check() {
    local app_url=''
    local health_status=''
    local login_status=''

    app_url="$(awk -F= '$1 == "APP_URL" { sub(/^[^=]*=/, ""); print; exit }' "${APP_PATH}/.env")"
    [[ "$app_url" =~ ^https?://[^[:space:]]+$ ]]

    health_status="$(curl -sS --max-time 30 --retry 5 --retry-delay 2 --output /dev/null --write-out '%{http_code}' "${app_url}/up")"
    login_status="$(curl -sS --max-time 30 --retry 5 --retry-delay 2 --output /dev/null --write-out '%{http_code}' "${app_url}/login")"

    [[ "$health_status" == '200' && "$login_status" == '200' ]]
}

database_backup() {
    database_backup_file="${BACKUPS_PATH}/database-${run_uuid}.dump"

    install -d -m 0700 -o root -g root "$BACKUPS_PATH"
    install -m 0600 -o root -g root /dev/null "$database_backup_file"

    if ! runuser -u postgres -- pg_dump --format=custom crm369 >"$database_backup_file" 2>>"$log_file"; then
        rm -f -- "$database_backup_file"
        database_backup_file=''

        return 1
    fi
}

database_restore() {
    [[ -n "$database_backup_file" && -f "$database_backup_file" ]] || return 1

    printf 'Restoring PostgreSQL from %s\n' "$database_backup_file" >>"$log_file"
    if ! runuser -u postgres -- psql \
        --dbname=postgres \
        --set=ON_ERROR_STOP=1 \
        --command="SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = 'crm369' AND pid <> pg_backend_pid();" >>"$log_file" 2>&1; then
        return 1
    fi

    runuser -u postgres -- pg_restore \
        --clean \
        --if-exists \
        --exit-on-error \
        --dbname=crm369 <"$database_backup_file" >>"$log_file" 2>&1
}

normalize_laravel_permissions() {
    chown root:"$APP_GROUP" "${APP_PATH}/composer.json" "${APP_PATH}/composer.lock"
    chmod 0640 "${APP_PATH}/composer.json" "${APP_PATH}/composer.lock"
    chown -R root:"$APP_GROUP" "${APP_PATH}/vendor"
    find "${APP_PATH}/vendor" -type d -exec chmod 0750 {} +
    find "${APP_PATH}/vendor" -type f -exec chmod 0640 {} +
    chown -R "$APP_USER:$APP_GROUP" "${APP_PATH}/bootstrap/cache"
    find "${APP_PATH}/bootstrap/cache" -type d -exec chmod 0770 {} +
    find "${APP_PATH}/bootstrap/cache" -type f -exec chmod 0660 {} +
}

restore_laravel_dependencies() {
    [[ "$laravel_backup_path" == /var/tmp/crm369-laravel.* && -d "${laravel_backup_path}/vendor" ]] || return 1

    cp -a "${laravel_backup_path}/composer.json" "${laravel_backup_path}/composer.lock" "$APP_PATH/"
    rm -rf -- "${APP_PATH}/vendor"
    mv "${laravel_backup_path}/vendor" "${APP_PATH}/vendor"
    normalize_laravel_permissions
    app_command optimize --no-interaction
    rm -rf -- "$laravel_backup_path"
    laravel_dependencies_switched='false'
}

cleanup_laravel_candidate() {
    if [[ "$laravel_candidate_path" == /var/tmp/crm369-laravel-candidate.* && -d "$laravel_candidate_path" ]]; then
        rm -rf -- "$laravel_candidate_path"
    fi

    laravel_candidate_path=''
}

apt_upgrade_packages() {
    export DEBIAN_FRONTEND=noninteractive
    apt-get update >>"$log_file" 2>&1
    apt-get install -y --only-upgrade --no-install-recommends "$@" >>"$log_file" 2>&1
}

update_application() {
    local incoming_path="${RELEASES_PATH}/.${target}.incoming"
    local release_path="${RELEASES_PATH}/${target}"
    local archive_path=''
    local current_reference=''
    local legacy_release=''

    install -d -m 0750 -o root -g "$APP_GROUP" "$RELEASES_PATH" "$SHARED_PATH"
    install -d -m 0700 -o root -g root "$BACKUPS_PATH"
    archive_path="$(mktemp "/var/tmp/crm369-${target}.XXXXXX.tar.gz")"
    [[ ! -e "$release_path" ]]

    write_progress 'running' '8' 'download' 'Скачивание проверенного commit из GitHub.'
    curl -fsSL --retry 3 "https://github.com/${REPOSITORY}/archive/${target}.tar.gz" -o "$archive_path"
    tar -tzf "$archive_path" | awk '$0 ~ /^\// || $0 ~ /(^|\/)\.\.(\/|$)/ { exit 1 }'

    rm -rf -- "$incoming_path"
    mkdir -p "$incoming_path"
    tar -xzf "$archive_path" --strip-components=1 --no-same-owner --no-same-permissions -C "$incoming_path"
    rm -f -- "$archive_path"
    [[ -f "${incoming_path}/artisan" && -f "${incoming_path}/composer.lock" && -f "${incoming_path}/package-lock.json" ]]

    write_progress 'running' '20' 'dependencies' 'Установка PHP-зависимостей во временный release.'
    composer_command install \
        --working-dir="$incoming_path" \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-progress >>"$log_file" 2>&1
    composer_command check-platform-reqs --working-dir="$incoming_path" --no-dev >>"$log_file" 2>&1

    write_progress 'running' '32' 'frontend' 'Сборка frontend-ресурсов.'
    (
        cd "$incoming_path"
        npm ci --no-audit --no-fund >>"$log_file" 2>&1
        VITE_APP_NAME=CRM369 npm run build >>"$log_file" 2>&1
    )
    rm -rf -- "${incoming_path}/node_modules"

    write_progress 'running' '45' 'maintenance' 'Остановка очередей и перевод CRM369 в режим обслуживания.'
    maintenance_begin

    write_progress 'running' '50' 'backup' 'Создание согласованной резервной копии PostgreSQL.'
    database_backup

    if [[ -L "$APP_PATH" ]]; then
        previous_release="$(readlink -f "$APP_PATH")"
    else
        current_reference="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1])).get("reference", ""))' "${STATE_PATH}/version.json" 2>/dev/null || true)"
        legacy_release="${RELEASES_PATH}/${current_reference:-legacy-$(date +%Y%m%d%H%M%S)}"
        [[ ! -e "$legacy_release" ]]
        mv "$APP_PATH" "$legacy_release"
        previous_release="$legacy_release"
        release_switched='true'

        if [[ ! -e "${SHARED_PATH}/.env" ]]; then
            mv "${legacy_release}/.env" "${SHARED_PATH}/.env"
        fi
        if [[ ! -e "${SHARED_PATH}/storage" ]]; then
            mv "${legacy_release}/storage" "${SHARED_PATH}/storage"
        fi
        ln -sfn "${SHARED_PATH}/.env" "${legacy_release}/.env"
        ln -sfn "${SHARED_PATH}/storage" "${legacy_release}/storage"
    fi

    ln -sfn "${SHARED_PATH}/.env" "${incoming_path}/.env"
    rm -rf -- "${incoming_path}/storage"
    ln -sfn "${SHARED_PATH}/storage" "${incoming_path}/storage"
    chown -R root:"$APP_GROUP" "$incoming_path"
    find "$incoming_path" -type d -exec chmod 0750 {} +
    find "$incoming_path" -type f -exec chmod 0640 {} +
    chown -R "$APP_USER:$APP_GROUP" "${incoming_path}/bootstrap/cache"
    find "${incoming_path}/bootstrap/cache" -type d -exec chmod 0770 {} +
    find "${incoming_path}/bootstrap/cache" -type f -exec chmod 0660 {} +
    mv "$incoming_path" "$release_path"
    ln -sfn "$release_path" "$APP_PATH"
    release_switched='true'

    write_progress 'running' '70' 'migrations' 'Применение миграций базы данных.'
    database_migrations_started='true'
    maintenance_finish

    write_progress 'running' '86' 'services' 'Проверка и перезапуск системных служб.'
    nginx -t >>"$log_file" 2>&1
    systemctl reload nginx >>"$log_file" 2>&1
    supervisorctl reread >>"$log_file" 2>&1
    supervisorctl update >>"$log_file" 2>&1
    workers_stop

    write_progress 'running' '94' 'health' 'Проверка страниц /up и /login.'
    health_check

    install -d -m 0750 -o root -g "$APP_GROUP" "$STATE_PATH"
    python3 - "${STATE_PATH}/version.json" "$target" "$version_label" <<'PY'
import json
import os
import sys
from datetime import datetime, timezone

path, reference, version = sys.argv[1:]
temporary = path + ".tmp"
with open(temporary, "w", encoding="utf-8") as output:
    json.dump({
        "version": version or ("main @ " + reference[:8]),
        "reference": reference,
        "installed_at": datetime.now(timezone.utc).isoformat(),
    }, output, ensure_ascii=False)
    output.write("\n")
os.chmod(temporary, 0o640)
os.replace(temporary, path)
PY
    chown root:"$APP_GROUP" "${STATE_PATH}/version.json"
    install -m 0755 -o root -g root "${release_path}/scripts/update-system.sh" /usr/local/sbin/crm369-updater
    workers_start
    database_migrations_started='false'
    release_switched='false'
}

update_laravel() {
    laravel_candidate_path="$(mktemp -d /var/tmp/crm369-laravel-candidate.XXXXXX)"
    cp -a "${APP_PATH}/composer.json" "${APP_PATH}/composer.lock" "$laravel_candidate_path/"

    write_progress 'running' '20' 'dependencies' 'Подготовка обновлённых Laravel-зависимостей без остановки CRM369.'
    composer_command update laravel/framework \
        --working-dir="$laravel_candidate_path" \
        --with-all-dependencies \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --optimize-autoloader \
        --no-scripts \
        --no-progress >>"$log_file" 2>&1
    composer_command check-platform-reqs --working-dir="$laravel_candidate_path" --no-dev >>"$log_file" 2>&1
    [[ -f "${laravel_candidate_path}/composer.lock" && -d "${laravel_candidate_path}/vendor" ]]

    laravel_backup_path="$(mktemp -d /var/tmp/crm369-laravel.XXXXXX)"
    cp -a "${APP_PATH}/composer.json" "${APP_PATH}/composer.lock" "$laravel_backup_path/"

    write_progress 'running' '55' 'maintenance' 'Краткое переключение зависимостей и применение миграций.'
    maintenance_begin
    database_backup

    mv "${APP_PATH}/vendor" "${laravel_backup_path}/vendor"
    laravel_dependencies_switched='true'
    cp -a "${laravel_candidate_path}/composer.json" "${laravel_candidate_path}/composer.lock" "$APP_PATH/"
    mv "${laravel_candidate_path}/vendor" "${APP_PATH}/vendor"

    normalize_laravel_permissions
    app_command package:discover --no-interaction
    database_migrations_started='true'
    maintenance_finish
    health_check
    workers_start
    database_migrations_started='false'
    laravel_dependencies_switched='false'
    cleanup_laravel_candidate
    rm -rf -- "$laravel_backup_path"
    laravel_backup_path=''
}

execute_update() {
    local postgresql_major=''
    local postgresql_version=''

    exec 9>/run/lock/crm369-update.lock

    if ! flock -n 9; then
        write_progress 'failed' '100' 'locked' 'Другой процесс обновления уже выполняется.'
        exit 75
    fi

    trap 'fail_update "$LINENO" "$?"' ERR
    write_progress 'running' '2' 'starting' 'Проверка параметров и подготовка обновления.'

    case "$component" in
        application)
            update_application
            ;;
        laravel)
            write_progress 'running' '10' 'dependencies' 'Подготовка обновления Laravel.'
            update_laravel
            write_progress 'running' '92' 'health' 'Проверка приложения после обновления Laravel.'
            ;;
        php)
            write_progress 'running' '20' 'packages' 'Обновление пакетов PHP 8.4.'
            apt_upgrade_packages php8.4-bcmath php8.4-cli php8.4-curl php8.4-fpm php8.4-gd php8.4-imagick php8.4-intl php8.4-mbstring php8.4-opcache php8.4-pgsql php8.4-readline php8.4-redis php8.4-xml php8.4-zip
            systemctl restart "php${PHP_VERSION}-fpm" >>"$log_file" 2>&1
            ;;
        postgresql)
            write_progress 'running' '15' 'backup' 'Создание резервной копии PostgreSQL.'
            database_backup
            write_progress 'running' '35' 'packages' 'Обновление PostgreSQL.'
            postgresql_version="$(runuser -u postgres -- psql --version)"
            [[ "$postgresql_version" =~ ([0-9]+)\. ]]
            postgresql_major="${BASH_REMATCH[1]}"
            apt_upgrade_packages postgresql postgresql-contrib "postgresql-${postgresql_major}" "postgresql-client-${postgresql_major}"
            systemctl restart postgresql >>"$log_file" 2>&1
            runuser -u postgres -- pg_isready --quiet
            ;;
        redis)
            write_progress 'running' '25' 'packages' 'Обновление Redis.'
            apt_upgrade_packages redis-server
            systemctl restart redis-server >>"$log_file" 2>&1
            runuser -u "$APP_USER" -- redis-cli ping | grep -qx PONG
            ;;
        nginx)
            write_progress 'running' '25' 'packages' 'Обновление Nginx.'
            apt_upgrade_packages nginx
            nginx -t >>"$log_file" 2>&1
            systemctl reload nginx >>"$log_file" 2>&1
            ;;
        node)
            write_progress 'running' '25' 'packages' 'Обновление Node.js.'
            apt_upgrade_packages nodejs
            node --version >>"$log_file" 2>&1
            ;;
        composer)
            write_progress 'running' '30' 'packages' 'Обновление Composer.'
            composer_command self-update --stable --no-interaction >>"$log_file" 2>&1
            composer_command --version --no-ansi >>"$log_file" 2>&1
            ;;
        ubuntu)
            write_progress 'running' '20' 'packages' 'Обновление системных пакетов Ubuntu.'
            export DEBIAN_FRONTEND=noninteractive
            apt-get update >>"$log_file" 2>&1
            apt-get upgrade -y --no-install-recommends >>"$log_file" 2>&1
            ;;
    esac

    write_progress 'running' '96' 'verification' 'Финальная проверка состояния CRM369.'
    systemctl is-active --quiet nginx
    systemctl is-active --quiet "php${PHP_VERSION}-fpm"
    systemctl is-active --quiet postgresql
    systemctl is-active --quiet redis-server
    health_check
    write_progress 'completed' '100' 'completed' 'Обновление успешно завершено.'
}

[[ $EUID -eq 0 ]] || {
    printf 'crm369-updater must run as root.\n' >&2
    exit 77
}

mode="${1:-}"
run_uuid="${2:-}"
component="${3:-}"
target="${4:-}"
version_label="${5:-}"

case "$mode" in
    start|execute)
        [[ $# -ge 3 && $# -le 5 ]] || usage
        ;;
    *)
        usage
        ;;
esac

validate_arguments
prepare_progress_files

case "$mode" in
    start)
        unit_name="crm369-update-${run_uuid//-/}"
        systemd-run \
            --unit="$unit_name" \
            --collect \
            --property=Type=exec \
            --property=TimeoutStartSec=1h \
            /usr/local/sbin/crm369-updater execute "$run_uuid" "$component" "$target" "$version_label" >/dev/null
        ;;
    execute)
        execute_update
        ;;
    *)
        usage
        ;;
esac
