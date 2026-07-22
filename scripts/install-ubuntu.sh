#!/usr/bin/env bash

set -Eeuo pipefail
umask 027

readonly APP_DIR='/var/www/crm369'
readonly APP_USER='www-data'
readonly APP_GROUP='www-data'
readonly DB_NAME='crm369'
readonly DB_USER='crm369'
readonly GITHUB_REPOSITORY='webnetkz/crm369'
readonly GITHUB_REF='main'
readonly PHP_VERSION='8.4'
readonly INSTALLER_VERSION='2026.07.22.3'
readonly INSTALL_STATE_DIR='/etc/crm369'
readonly INSTALL_STATE_FILE='/etc/crm369/installed'
readonly INSTALL_PROGRESS_FILE='/etc/crm369/installing'
readonly TTY_DEVICE='/dev/tty'

TEMP_DIR=''
resume_mode='false'
resume_domain=''
resume_admin_email=''
source_commit='unrecorded'

print_info() {
    printf '\033[1;34m[CRM369]\033[0m %s\n' "$*"
}

print_success() {
    printf '\033[1;32m[CRM369]\033[0m %s\n' "$*"
}

print_warning() {
    printf '\033[1;33m[CRM369]\033[0m %s\n' "$*" >&2
}

fail() {
    printf '\033[1;31m[CRM369]\033[0m %s\n' "$*" >&2
    exit 1
}

read_env_value() {
    local key="$1"

    awk -F= -v key="$key" '$1 == key { sub(/^[^=]*=/, ""); print; exit }' "${APP_DIR}/.env"
}

validate_resume_installation() {
    local progress_app_dir=''
    local progress_domain=''
    local progress_source_commit=''
    local progress_status=''
    local progress_version=''
    local resume_app_url=''
    local resume_database_password=''

    [[ -d "$APP_DIR" && ! -L "$APP_DIR" ]] \
        || fail "Для продолжения требуется обычный каталог ${APP_DIR}."
    [[ -f "${APP_DIR}/artisan" && -f "${APP_DIR}/composer.lock" && -f "${APP_DIR}/package-lock.json" && -f "${APP_DIR}/.env" ]] \
        || fail 'Продолжение разрешено только для ранее распакованного приложения CRM369 с production-конфигурацией.'
    [[ -f "${APP_DIR}/app/Console/Commands/InstallCrmCommand.php" && -f "${APP_DIR}/config/admin.php" ]] \
        || fail 'Существующий каталог не прошёл проверку исходного кода CRM369.'
    [[ -f "${APP_DIR}/vendor/autoload.php" && -f "${APP_DIR}/public/build/manifest.json" ]] \
        || fail 'Для продолжения требуются ранее установленные PHP-зависимости и собранные frontend-ресурсы.'

    [[ "$(read_env_value APP_ENV)" == 'production' ]] \
        || fail 'Существующий .env не является production-конфигурацией CRM369.'
    [[ "$(read_env_value APP_DEBUG)" == 'false' ]] \
        || fail 'В существующем .env должен быть отключён APP_DEBUG.'
    [[ "$(read_env_value DB_CONNECTION)" == 'pgsql' ]] \
        || fail 'Существующий .env использует неподдерживаемое подключение к базе данных.'
    [[ "$(read_env_value DB_HOST)" == '127.0.0.1' && "$(read_env_value DB_PORT)" == '5432' ]] \
        || fail 'Существующий .env должен использовать локальный PostgreSQL.'
    [[ "$(read_env_value DB_DATABASE)" == "$DB_NAME" && "$(read_env_value DB_USERNAME)" == "$DB_USER" ]] \
        || fail 'Существующий .env указывает на другую базу или роль PostgreSQL.'

    resume_database_password="$(read_env_value DB_PASSWORD)"
    [[ -n "$resume_database_password" ]] || fail 'В существующем .env отсутствует пароль PostgreSQL.'

    resume_app_url="$(read_env_value APP_URL)"
    [[ "$resume_app_url" =~ ^https://([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$ ]] \
        || fail 'В существующем .env указан некорректный APP_URL.'
    resume_domain="${resume_app_url#https://}"

    resume_admin_email="$(read_env_value SUPER_ADMIN_EMAIL)"
    [[ "$resume_admin_email" =~ ^[A-Za-z0-9.!#%+_=-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63}$ ]] \
        || fail 'В существующем .env указан некорректный SUPER_ADMIN_EMAIL.'

    if [[ -f "$INSTALL_PROGRESS_FILE" && ! -L "$INSTALL_PROGRESS_FILE" ]]; then
        progress_status="$(awk -F= '$1 == "status" { print $2; exit }' "$INSTALL_PROGRESS_FILE")"
        progress_version="$(awk -F= '$1 == "installer_version" { print $2; exit }' "$INSTALL_PROGRESS_FILE")"
        progress_domain="$(awk -F= '$1 == "domain" { print $2; exit }' "$INSTALL_PROGRESS_FILE")"
        progress_app_dir="$(awk -F= '$1 == "app_dir" { print $2; exit }' "$INSTALL_PROGRESS_FILE")"
        progress_source_commit="$(awk -F= '$1 == "source_commit" { print $2; exit }' "$INSTALL_PROGRESS_FILE")"

        [[ "$progress_status" == 'installing' && "$progress_domain" == "$resume_domain" && "$progress_app_dir" == "$APP_DIR" ]] \
            || fail 'Файл состояния частичной установки не соответствует существующему приложению.'
        case "$progress_version" in
            '2026.07.22.1'|'2026.07.22.2'|'2026.07.22.3')
                ;;
            *)
                fail "Версия частичной установки ${progress_version:-неизвестна} несовместима с установщиком ${INSTALLER_VERSION}."
                ;;
        esac

        if [[ -n "$progress_source_commit" ]]; then
            [[ "$progress_source_commit" =~ ^[0-9a-f]{40}$ || "$progress_source_commit" == 'unrecorded' ]] \
                || fail 'Файл состояния содержит некорректный commit исходного кода.'
            source_commit="$progress_source_commit"
        fi
    elif [[ -e "$INSTALL_PROGRESS_FILE" || -L "$INSTALL_PROGRESS_FILE" ]]; then
        fail "Файл состояния ${INSTALL_PROGRESS_FILE} имеет небезопасный тип."
    fi
}

cleanup() {
    local exit_code=$?

    set +e
    if [[ -n "$TEMP_DIR" && -d "$TEMP_DIR" ]]; then
        rm -rf -- "$TEMP_DIR"
    fi

    exit "$exit_code"
}

handle_error() {
    local exit_code=$?
    local line_number="$1"

    if [[ "${BASH_SUBSHELL:-0}" -gt 0 ]]; then
        return "$exit_code"
    fi

    print_warning "Установка остановлена на строке ${line_number}. Частично созданные данные не удалены автоматически; после проверки продолжите командой с --resume."
    exit "$exit_code"
}

trap cleanup EXIT
trap 'handle_error "$LINENO"' ERR

show_help() {
    cat <<'HELP'
CRM369 — установщик production-среды для чистого Ubuntu Server 22.04/24.04 (amd64).

Использование:
  sudo bash scripts/install-ubuntu.sh
  sudo bash scripts/install-ubuntu.sh --resume
  curl -fsSL https://raw.githubusercontent.com/webnetkz/crm369/main/scripts/install-ubuntu.sh | sudo bash -s -- --resume

Опция --resume продолжает только проверенную частичную установку CRM369,
не пересоздавая существующие базу PostgreSQL, роль и production .env.

Установщик интерактивно запросит:
  - домен CRM;
  - имя, email и пароль единственного super-admin;
  - email для сертификата Let's Encrypt.

Будут установлены Nginx, PHP 8.4, PostgreSQL, Redis, Supervisor,
Node.js 22, Composer и Certbot. Демо-данные и seeders не запускаются.
HELP
}

case "${1:-}" in
    --help|-h)
        show_help
        exit 0
        ;;
    --resume)
        resume_mode='true'
        shift
        ;;
    '')
        ;;
    *)
        show_help >&2
        fail "Неизвестный аргумент: $1"
        ;;
esac

if [[ $# -gt 0 ]]; then
    show_help >&2
    fail "Неизвестный аргумент: $1"
fi

[[ $EUID -eq 0 ]] || fail 'Запустите установщик от root через sudo.'
[[ -r "$TTY_DEVICE" && -w "$TTY_DEVICE" ]] || fail 'Для интерактивной установки требуется терминал /dev/tty.'

print_info "Версия установщика: ${INSTALLER_VERSION}"

exec 9>/run/lock/crm369-install.lock
flock -n 9 || fail 'Другой процесс установки CRM369 уже запущен.'

if [[ -f "$INSTALL_STATE_FILE" ]]; then
    print_success "CRM369 уже установлена. Состояние: ${INSTALL_STATE_FILE}"
    exit 0
fi

[[ -r /etc/os-release ]] || fail 'Не удалось определить операционную систему.'

# shellcheck disable=SC1091
source /etc/os-release

[[ "${ID:-}" == 'ubuntu' ]] || fail 'Поддерживается только Ubuntu Server.'
[[ "${VERSION_ID:-}" == '22.04' || "${VERSION_ID:-}" == '24.04' ]] || fail 'Поддерживаются Ubuntu Server 22.04 и 24.04 LTS.'
[[ "$(dpkg --print-architecture)" == 'amd64' ]] || fail 'Автоматическая установка поддерживает архитектуру amd64.'
command -v curl >/dev/null 2>&1 || fail 'Команда curl не найдена. Установите её: apt-get install -y curl'

if [[ "$resume_mode" == 'true' ]]; then
    validate_resume_installation
    print_info "Продолжение прерванной установки из ${APP_DIR}."
elif [[ -e "$APP_DIR" || -L "$APP_DIR" ]]; then
    fail "Каталог ${APP_DIR} уже существует. Если это частичная установка CRM369, проверьте её и используйте --resume."
fi

available_kilobytes="$(df -Pk /var | awk 'NR == 2 {print $4}')"

if [[ ! "$available_kilobytes" =~ ^[0-9]+$ || "$available_kilobytes" -lt 4194304 ]]; then
    fail 'Для установки требуется не менее 4 ГБ свободного места в /var.'
fi

prompt_value() {
    local variable_name="$1"
    local prompt="$2"
    local default_value="${3:-}"
    local value=''

    if [[ -n "$default_value" ]]; then
        printf '%s [%s]: ' "$prompt" "$default_value" >"$TTY_DEVICE"
    else
        printf '%s: ' "$prompt" >"$TTY_DEVICE"
    fi

    IFS= read -r value <"$TTY_DEVICE"
    value="${value:-$default_value}"
    printf -v "$variable_name" '%s' "$value"
}

confirm_installation() {
    local answer=''

    prompt_value answer 'Продолжить установку? (Y/n)' 'Y'
    [[ "$answer" =~ ^([Yy]|[Yy][Ee][Ss]|[Дд]|[Дд][Аа])$ ]]
}

domain=''

while true; do
    prompt_value domain 'Домен CRM369 (например crm.example.com)' "$resume_domain"
    domain="${domain,,}"

    if [[ "$domain" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$ ]]; then
        break
    fi

    print_warning 'Введите доменное имя без https://, пути и завершающего слеша.'
done

admin_name=''

while true; do
    prompt_value admin_name 'Имя super-admin' 'Администратор'

    if [[ -n "$admin_name" && ${#admin_name} -le 255 ]]; then
        break
    fi

    print_warning 'Имя обязательно и не должно быть длиннее 255 символов.'
done

admin_email=''

while true; do
    prompt_value admin_email 'Email super-admin' "$resume_admin_email"
    admin_email="${admin_email,,}"

    if [[ "$admin_email" =~ ^[A-Za-z0-9.!#%+_=-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63}$ ]]; then
        break
    fi

    print_warning 'Введите корректный email.'
done

certificate_email=''

while true; do
    prompt_value certificate_email "Email для Let's Encrypt" "$admin_email"
    certificate_email="${certificate_email,,}"

    if [[ "$certificate_email" =~ ^[A-Za-z0-9.!#%+_=-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,63}$ ]]; then
        break
    fi

    print_warning 'Введите корректный email.'
done

if [[ "$resume_mode" == 'true' ]]; then
    [[ "$domain" == "$resume_domain" ]] \
        || fail "Домен должен совпадать с существующим APP_URL: ${resume_domain}."
    [[ "$admin_email" == "$resume_admin_email" ]] \
        || fail "Email должен совпадать с существующим SUPER_ADMIN_EMAIL: ${resume_admin_email}."
fi

printf '\nПараметры установки:\n' >"$TTY_DEVICE"
printf '  URL:         https://%s\n' "$domain" >"$TTY_DEVICE"
printf '  Super-admin: %s <%s>\n' "$admin_name" "$admin_email" >"$TTY_DEVICE"
printf '  Каталог:     %s\n\n' "$APP_DIR" >"$TTY_DEVICE"

if [[ "$resume_mode" == 'true' ]]; then
    printf '  Режим:       продолжение прерванной установки\n\n' >"$TTY_DEVICE"
fi

confirm_installation || fail 'Установка отменена.'

if ! getent ahosts "$domain" >/dev/null 2>&1; then
    fail "Домен ${domain} не разрешается через DNS. Создайте A-запись на этот сервер и повторите установку."
fi

nginx_available_path="/etc/nginx/sites-available/${domain}.conf"
nginx_enabled_path="/etc/nginx/sites-enabled/${domain}.conf"

if [[ "$resume_mode" == 'false' && ( -e "$nginx_available_path" || -L "$nginx_available_path" || -e "$nginx_enabled_path" || -L "$nginx_enabled_path" ) ]]; then
    fail "Конфигурация Nginx для ${domain} уже существует и не будет перезаписана."
fi

if [[ "$resume_mode" == 'true' && -L "$nginx_available_path" ]]; then
    fail "Конфигурация ${nginx_available_path} не должна быть символической ссылкой."
fi

if [[ "$resume_mode" == 'true' && -e "$nginx_available_path" ]]; then
    grep -Fq "server_name ${domain};" "$nginx_available_path" \
        || fail "Существующая конфигурация Nginx ${nginx_available_path} принадлежит другому домену."
    grep -Fq "root ${APP_DIR}/public;" "$nginx_available_path" \
        || fail "Существующая конфигурация Nginx ${nginx_available_path} использует другой корневой каталог."
fi

if [[ "$resume_mode" == 'true' && ( -e "$nginx_enabled_path" || -L "$nginx_enabled_path" ) ]]; then
    [[ -L "$nginx_enabled_path" && "$(readlink -f "$nginx_enabled_path")" == "$nginx_available_path" ]] \
        || fail "Существующая конфигурация ${nginx_enabled_path} не принадлежит CRM369."
fi

TEMP_DIR="$(mktemp -d /tmp/crm369-install.XXXXXX)"

print_info 'Обновление Ubuntu и установка системных пакетов...'
export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y --no-install-recommends \
    ca-certificates \
    cron \
    curl \
    git \
    gnupg \
    lsb-release \
    openssl \
    software-properties-common \
    unzip

if ! compgen -G '/etc/apt/sources.list.d/ondrej-ubuntu-php-*.list' >/dev/null; then
    add-apt-repository -y ppa:ondrej/php
fi

install -d -m 0755 /etc/apt/keyrings
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
    | gpg --dearmor --yes --output /etc/apt/keyrings/nodesource.gpg
chmod 0644 /etc/apt/keyrings/nodesource.gpg
printf 'deb [arch=%s signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main\n' \
    "$(dpkg --print-architecture)" > /etc/apt/sources.list.d/nodesource.list

apt-get update
apt-get install -y --no-install-recommends \
    certbot \
    nginx \
    nodejs \
    php8.4-bcmath \
    php8.4-cli \
    php8.4-curl \
    php8.4-fpm \
    php8.4-gd \
    php8.4-imagick \
    php8.4-intl \
    php8.4-mbstring \
    php8.4-opcache \
    php8.4-pgsql \
    php8.4-readline \
    php8.4-redis \
    php8.4-xml \
    php8.4-zip \
    postgresql \
    postgresql-contrib \
    python3-certbot-nginx \
    redis-server \
    supervisor \
    ufw

if ! grep -q '^# CRM369 production queue durability\.$' /etc/redis/redis.conf; then
    cat >> /etc/redis/redis.conf <<'REDIS'

# CRM369 production queue durability.
appendonly yes
appendfsync everysec
maxmemory-policy noeviction
REDIS
fi

systemctl enable --now cron nginx "php${PHP_VERSION}-fpm" postgresql redis-server supervisor
systemctl restart redis-server

database_exists='false'
database_role_exists='false'
database_owner=''

if runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_database WHERE datname = '${DB_NAME}'" | grep -q 1; then
    database_exists='true'
    database_owner="$(runuser -u postgres -- psql -tAc "SELECT pg_get_userbyid(datdba) FROM pg_database WHERE datname = '${DB_NAME}'")"
fi

if runuser -u postgres -- psql -tAc "SELECT 1 FROM pg_roles WHERE rolname = '${DB_USER}'" | grep -q 1; then
    database_role_exists='true'
fi

if [[ "$resume_mode" == 'true' ]]; then
    [[ "$database_exists" == 'true' && "$database_role_exists" == 'true' ]] \
        || fail 'Для продолжения должны существовать и база crm369, и роль crm369. Частичное состояние PostgreSQL не изменено.'
    [[ "$database_owner" == "$DB_USER" ]] \
        || fail "Владельцем базы ${DB_NAME} должна быть роль ${DB_USER}; существующая база не изменена."
elif [[ "$database_exists" == 'true' || "$database_role_exists" == 'true' ]]; then
    fail 'База или роль PostgreSQL crm369 уже существует; существующие данные не будут перезаписаны. Для проверенной частичной установки используйте --resume.'
fi

if [[ "$resume_mode" == 'false' ]]; then
    print_info 'Установка Composer с проверкой подписи...'
    composer_installer="${TEMP_DIR}/composer-setup.php"
    composer_signature="$(curl -fsSL https://composer.github.io/installer.sig)"
    curl -fsSL https://getcomposer.org/installer -o "$composer_installer"
    printf '%s  %s\n' "$composer_signature" "$composer_installer" | sha384sum --check --status
    /usr/bin/php8.4 "$composer_installer" --quiet --install-dir=/usr/local/bin --filename=composer

    print_info 'Скачивание исходного кода CRM369 из публичного репозитория...'
    source_commit="$(git ls-remote --exit-code \
        "https://github.com/${GITHUB_REPOSITORY}.git" \
        "refs/heads/${GITHUB_REF}" \
        | awk 'NR == 1 { print $1 }')"
    [[ "$source_commit" =~ ^[0-9a-f]{40}$ ]] \
        || fail "Не удалось определить commit ветки ${GITHUB_REF}."

    print_info "Исходный код зафиксирован на commit ${source_commit}."
    source_archive="${TEMP_DIR}/crm369.tar.gz"
    curl -fsSL --retry 3 \
        "https://github.com/${GITHUB_REPOSITORY}/archive/${source_commit}.tar.gz" \
        -o "$source_archive"

    mkdir -p "$APP_DIR"
    tar -xzf "$source_archive" --strip-components=1 -C "$APP_DIR"

    [[ -f "${APP_DIR}/artisan" && -f "${APP_DIR}/composer.lock" && -f "${APP_DIR}/package-lock.json" ]] \
        || fail 'Архив репозитория не содержит ожидаемое приложение CRM369.'

    print_info 'Установка PHP- и frontend-зависимостей...'
    (
        cd "$APP_DIR"
        COMPOSER_ALLOW_SUPERUSER=1 \
            /usr/local/bin/composer install \
                --no-dev \
                --no-interaction \
                --prefer-dist \
                --optimize-autoloader \
                --no-progress

        /usr/local/bin/composer check-platform-reqs --no-dev

        npm ci --no-audit --no-fund
        VITE_APP_NAME=CRM369 npm run build
    )

    rm -rf -- "${APP_DIR}/node_modules"
else
    print_info 'Исходный код, зависимости и frontend-сборка частичной установки прошли проверку и будут использованы без перезаписи.'
fi

if [[ "$resume_mode" == 'false' ]]; then
    print_info 'Создание изолированной базы PostgreSQL...'
    database_password="$(openssl rand -hex 32)"

{
    printf "\\set db_name '%s'\n" "$DB_NAME"
    printf "\\set db_user '%s'\n" "$DB_USER"
    printf "\\set db_password '%s'\n" "$database_password"
    cat <<'SQL'
SELECT format('CREATE ROLE %I LOGIN PASSWORD %L', :'db_user', :'db_password') \gexec
SELECT format('CREATE DATABASE %I OWNER %I', :'db_name', :'db_user') \gexec
SQL
} | runuser -u postgres -- psql --set=ON_ERROR_STOP=1 postgres

print_info 'Создание production-конфигурации...'
install -m 0660 -o "$APP_USER" -g "$APP_GROUP" /dev/null "${APP_DIR}/.env"

{
    printf 'APP_NAME=CRM369\n'
    printf 'APP_ENV=production\n'
    printf 'APP_KEY=\n'
    printf 'APP_DEBUG=false\n'
    printf 'APP_URL=https://%s\n' "$domain"
    printf '\n'
    printf 'APP_LOCALE=ru\n'
    printf 'APP_FALLBACK_LOCALE=en\n'
    printf 'APP_FAKER_LOCALE=ru_RU\n'
    printf 'SUPER_ADMIN_EMAIL=%s\n' "$admin_email"
    printf 'TWO_FACTOR_ISSUER=CRM369\n'
    printf '\n'
    printf 'APP_MAINTENANCE_DRIVER=file\n'
    printf 'BCRYPT_ROUNDS=12\n'
    printf 'LOG_CHANNEL=stack\n'
    printf 'LOG_STACK=daily\n'
    printf 'LOG_LEVEL=warning\n'
    printf 'LOG_DAILY_DAYS=14\n'
    printf '\n'
    printf 'DB_CONNECTION=pgsql\n'
    printf 'DB_HOST=127.0.0.1\n'
    printf 'DB_PORT=5432\n'
    printf 'DB_DATABASE=%s\n' "$DB_NAME"
    printf 'DB_USERNAME=%s\n' "$DB_USER"
    printf 'DB_PASSWORD=%s\n' "$database_password"
    printf 'DB_SSLMODE=prefer\n'
    printf '\n'
    printf 'SESSION_DRIVER=database\n'
    printf 'SESSION_LIFETIME=120\n'
    printf 'SESSION_ENCRYPT=false\n'
    printf 'SESSION_PATH=/\n'
    printf 'SESSION_DOMAIN=%s\n' "$domain"
    printf 'SESSION_SECURE_COOKIE=true\n'
    printf 'SESSION_SAME_SITE=lax\n'
    printf '\n'
    printf 'BROADCAST_CONNECTION=log\n'
    printf 'FILESYSTEM_DISK=local\n'
    printf 'QUEUE_CONNECTION=database\n'
    printf 'DB_QUEUE_RETRY_AFTER=120\n'
    printf 'NOTIFICATION_QUEUE_CONNECTION=redis\n'
    printf 'NOTIFICATION_QUEUE=notifications\n'
    printf 'CACHE_STORE=database\n'
    printf 'NOTIFICATION_CACHE_STORE=redis\n'
    printf 'CHAT_CACHE_STORE=redis\n'
    printf '\n'
    printf 'REDIS_CLIENT=phpredis\n'
    printf 'REDIS_HOST=127.0.0.1\n'
    printf 'REDIS_PASSWORD=null\n'
    printf 'REDIS_PORT=6379\n'
    printf 'REDIS_QUEUE_CONNECTION=default\n'
    printf 'REDIS_QUEUE=default\n'
    printf 'REDIS_QUEUE_BLOCK_FOR=5\n'
    printf 'REDIS_QUEUE_RETRY_AFTER=120\n'
    printf 'NOTIFICATION_SHARED_TTL_SECONDS=300\n'
    printf 'NOTIFICATION_MOBILE_TTL_SECONDS=120\n'
    printf 'CHAT_SHARED_TTL_SECONDS=60\n'
    printf 'CHAT_SIDEBAR_TTL_SECONDS=60\n'
    printf 'CHAT_UNREAD_TTL_SECONDS=60\n'
    printf '\n'
    printf 'MAIL_MAILER=log\n'
    printf 'MAIL_FROM_ADDRESS=%s\n' "$admin_email"
    printf 'MAIL_FROM_NAME=CRM369\n'
    printf '\n'
    printf 'VITE_APP_NAME=CRM369\n'
} >"${APP_DIR}/.env"

    database_password=''
else
    print_info 'Существующие база PostgreSQL и production .env прошли проверку и будут использованы без перезаписи.'
fi

install -d -m 0700 "$INSTALL_STATE_DIR"
{
    printf 'status=installing\n'
    printf 'installer_version=%s\n' "$INSTALLER_VERSION"
    printf 'domain=%s\n' "$domain"
    printf 'app_dir=%s\n' "$APP_DIR"
    printf 'mode=%s\n' "$resume_mode"
    printf 'source_commit=%s\n' "$source_commit"
} >"$INSTALL_PROGRESS_FILE"
chmod 0600 "$INSTALL_PROGRESS_FILE"

chown -R root:"$APP_GROUP" "$APP_DIR"
find "$APP_DIR" -path "${APP_DIR}/storage" -prune -o -path "${APP_DIR}/bootstrap/cache" -prune -o -type d -exec chmod 0750 {} +
find "$APP_DIR" -path "${APP_DIR}/storage" -prune -o -path "${APP_DIR}/bootstrap/cache" -prune -o -type f -exec chmod 0640 {} +
chown -R "$APP_USER:$APP_GROUP" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 0770 {} +
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type f -exec chmod 0660 {} +
chown "$APP_USER:$APP_GROUP" "${APP_DIR}/.env"
chmod 0660 "${APP_DIR}/.env"

app_key="$(read_env_value APP_KEY)"

if [[ -z "$app_key" ]]; then
    runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_DIR}/artisan" key:generate --force --no-interaction
else
    print_info 'Существующий APP_KEY сохранён без изменения.'
fi

app_key=''
chown root:"$APP_GROUP" "${APP_DIR}/.env"
chmod 0640 "${APP_DIR}/.env"

migration_dependency_options=(
    '--path=database/migrations/0001_01_01_000000_create_users_table.php'
    '--path=database/migrations/2026_06_28_134816_create_user_groups_table.php'
    '--path=database/migrations/2026_06_29_005139_create_chat_conversations_table.php'
    '--path=database/migrations/2026_06_29_010910_create_knowledge_bases_table.php'
    '--path=database/migrations/2026_06_29_142826_create_crm_funnels_table.php'
)

for migration_option in "${migration_dependency_options[@]}"; do
    migration_file="${migration_option#--path=database/migrations/}"
    [[ -f "${APP_DIR}/database/migrations/${migration_file}" ]] \
        || fail "Не найдена обязательная миграция ${migration_file}."
done

print_info 'Применение миграций в порядке зависимостей PostgreSQL...'
runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_DIR}/artisan" migrate \
    --force \
    --no-interaction \
    "${migration_dependency_options[@]}"

runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_DIR}/artisan" migrate --force --no-interaction

expected_migration_count="$(find "${APP_DIR}/database/migrations" -maxdepth 1 -type f -name '*.php' | wc -l)"
applied_migration_count="$(runuser -u postgres -- psql --dbname="$DB_NAME" -tAc 'SELECT COUNT(*) FROM migrations')"

[[ "$expected_migration_count" =~ ^[[:space:]]*[0-9]+[[:space:]]*$ && "$applied_migration_count" =~ ^[0-9]+$ ]] \
    || fail 'Не удалось проверить количество миграций PostgreSQL.'
[[ "$expected_migration_count" -eq "$applied_migration_count" ]] \
    || fail "Применено миграций ${applied_migration_count}, ожидалось ${expected_migration_count}."

critical_table_count="$(runuser -u postgres -- psql --dbname="$DB_NAME" -tAc \
    "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_name IN ('migrations', 'users', 'jobs', 'notifications', 'chat_conversations', 'chat_conversation_participants', 'chat_messages', 'knowledge_bases', 'knowledge_base_group', 'knowledge_base_articles', 'crm_funnels', 'crm_funnel_stages', 'crm_deals')")"
[[ "$critical_table_count" == '13' ]] \
    || fail "Созданы не все критические таблицы CRM369: найдено ${critical_table_count} из 13."

print_success "Все ${applied_migration_count} миграций и критические таблицы PostgreSQL проверены."

user_count="$(runuser -u postgres -- psql --dbname="$DB_NAME" -tAc 'SELECT COUNT(*) FROM users')"

if [[ "$user_count" == '0' ]]; then
    print_info 'Создание единственного пользователя. Пароль вводится скрыто и не сохраняется в shell-history.'
    runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_DIR}/artisan" crm369:install \
        --name="$admin_name" \
        --email="$admin_email" <"$TTY_DEVICE"
elif [[ "$user_count" == '1' ]]; then
    installed_admin_email="$(runuser -u postgres -- psql --dbname="$DB_NAME" -tAc 'SELECT email FROM users ORDER BY id LIMIT 1')"
    [[ "${installed_admin_email,,}" == "$admin_email" ]] \
        || fail "В базе уже существует другой пользователь ${installed_admin_email}; автоматическое продолжение остановлено."
    print_info "Super-admin ${admin_email} уже существует и не будет создан повторно."
else
    fail "В базе обнаружено пользователей: ${user_count}. Автоматическое продолжение остановлено."
fi

/usr/bin/php8.4 "${APP_DIR}/artisan" storage:link --force --no-interaction
runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_DIR}/artisan" optimize --no-interaction

production_environment_report="$(runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_DIR}/artisan" config:show app.env \
    --no-ansi \
    --no-interaction)"
production_debug_report="$(runuser -u "$APP_USER" -- /usr/bin/php8.4 "${APP_DIR}/artisan" config:show app.debug \
    --no-ansi \
    --no-interaction)"

grep -Eq 'app\.env[[:space:].]+production' <<<"$production_environment_report" \
    || fail 'Laravel запущен не в production-окружении.'
grep -Eq 'app\.debug[[:space:].]+false' <<<"$production_debug_report" \
    || fail 'Laravel debug-режим не отключён.'

print_success 'Laravel настроен для production; debug-режим отключён.'

print_info 'Настройка PHP-FPM, Nginx, очередей и планировщика...'

cat > "/etc/php/${PHP_VERSION}/fpm/conf.d/99-crm369.ini" <<'PHPINI'
expose_php = Off
memory_limit = 512M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 120
opcache.enable = 1
opcache.validate_timestamps = 0
PHPINI

cat > "$nginx_available_path" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name ${domain};
    root ${APP_DIR}/public;

    access_log /var/log/nginx/${domain}.access.log;
    error_log /var/log/nginx/${domain}.error.log;

    index index.php;
    charset utf-8;
    client_max_body_size 100M;
    server_tokens off;

    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    location ~ ^/index\.php(/|\$) {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php${PHP_VERSION}-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_hide_header X-Powered-By;
        fastcgi_read_timeout 120s;
        internal;
    }

    location ~ \.php\$ {
        return 404;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
NGINX

ln -sfn "$nginx_available_path" "$nginx_enabled_path"

if [[ -L /etc/nginx/sites-enabled/default ]]; then
    unlink /etc/nginx/sites-enabled/default
fi

cat > /etc/supervisor/conf.d/crm369.conf <<SUPERVISOR
[program:crm369-default]
command=/usr/bin/php8.4 ${APP_DIR}/artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=90 --max-time=3600
directory=${APP_DIR}
user=${APP_USER}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/log/supervisor/crm369-default.log

[program:crm369-notifications]
command=/usr/bin/php8.4 ${APP_DIR}/artisan queue:work redis --queue=notifications --sleep=3 --tries=3 --timeout=90 --max-time=3600
directory=${APP_DIR}
user=${APP_USER}
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
stopwaitsecs=120
redirect_stderr=true
stdout_logfile=/var/log/supervisor/crm369-notifications.log
SUPERVISOR

cat > /etc/cron.d/crm369 <<CRON
* * * * * ${APP_USER} /usr/bin/php8.4 ${APP_DIR}/artisan schedule:run >> /dev/null 2>&1
CRON
chmod 0644 /etc/cron.d/crm369

cat > /etc/logrotate.d/crm369 <<LOGROTATE
${APP_DIR}/storage/logs/*.log {
    daily
    rotate 14
    missingok
    notifempty
    compress
    delaycompress
    copytruncate
    su ${APP_USER} ${APP_GROUP}
}
LOGROTATE

chown -R root:"$APP_GROUP" "$APP_DIR"
find "$APP_DIR" -path "${APP_DIR}/storage" -prune -o -path "${APP_DIR}/bootstrap/cache" -prune -o -type d -exec chmod 0750 {} +
find "$APP_DIR" -path "${APP_DIR}/storage" -prune -o -path "${APP_DIR}/bootstrap/cache" -prune -o -type f -exec chmod 0640 {} +
chown -R "$APP_USER:$APP_GROUP" "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache"
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type d -exec chmod 0770 {} +
find "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" -type f -exec chmod 0660 {} +
chown root:"$APP_GROUP" "${APP_DIR}/.env"
chmod 0640 "${APP_DIR}/.env"

ufw allow 'Nginx Full'

nginx -t
systemctl restart "php${PHP_VERSION}-fpm"
systemctl reload nginx
supervisorctl reread
supervisorctl update

pre_tls_health_status="$(curl -sS --max-time 20 \
    --resolve "${domain}:80:127.0.0.1" \
    --output /dev/null \
    --write-out '%{http_code}' \
    "http://${domain}/up")"
[[ "$pre_tls_health_status" == '200' ]] \
    || fail "Laravel не прошёл HTTP-проверку перед выпуском сертификата; получен статус ${pre_tls_health_status}."

public_http_health_status="$(curl -sS --max-time 30 \
    --retry 5 \
    --retry-delay 2 \
    --retry-connrefused \
    --output /dev/null \
    --write-out '%{http_code}' \
    "http://${domain}/up")"
[[ "$public_http_health_status" == '200' ]] \
    || fail "Домен ${domain} или порт 80 не ведёт к Laravel на этом сервере; публичная HTTP-проверка вернула ${public_http_health_status}."

print_info "Получение TLS-сертификата Let's Encrypt для ${domain}..."
certbot --nginx \
    --domains "$domain" \
    --email "$certificate_email" \
    --agree-tos \
    --non-interactive \
    --redirect \
    --no-eff-email

nginx -t
systemctl reload nginx

systemctl enable --now certbot.timer
certbot renew --dry-run --non-interactive

http_redirect_status="$(curl -sS --max-time 20 \
    --resolve "${domain}:80:127.0.0.1" \
    --output /dev/null \
    --write-out '%{http_code}' \
    "http://${domain}/up")"
[[ "$http_redirect_status" =~ ^30[1278]$ ]] \
    || fail "HTTP для ${domain} не перенаправляет на HTTPS; получен статус ${http_redirect_status}."

print_info 'Проверка HTTPS и состояния сервисов...'
[[ -s "/etc/letsencrypt/live/${domain}/fullchain.pem" ]] \
    || fail "Сертификат для ${domain} не создан."
[[ -s "/etc/letsencrypt/live/${domain}/privkey.pem" ]] \
    || fail "Закрытый ключ сертификата для ${domain} не создан."

systemctl is-active --quiet nginx || fail 'Nginx не запущен.'
systemctl is-active --quiet "php${PHP_VERSION}-fpm" || fail "PHP ${PHP_VERSION}-FPM не запущен."
systemctl is-active --quiet postgresql || fail 'PostgreSQL не запущен.'
systemctl is-active --quiet redis-server || fail 'Redis не запущен.'
systemctl is-active --quiet supervisor || fail 'Supervisor не запущен.'
systemctl is-active --quiet certbot.timer || fail 'Таймер автопродления TLS-сертификата не запущен.'
runuser -u postgres -- pg_isready --quiet || fail 'PostgreSQL не принимает подключения.'
[[ -S "/run/php/php${PHP_VERSION}-fpm.sock" ]] || fail 'Сокет PHP-FPM не создан.'
/usr/bin/php8.4 -m | grep -qxF pdo_pgsql || fail 'PHP-расширение pdo_pgsql не загружено.'
/usr/bin/php8.4 -m | grep -qxF redis || fail 'PHP-расширение redis не загружено.'
redis-cli --raw CONFIG GET appendonly | grep -qx yes || fail 'Redis AOF не включён.'
redis-cli --raw CONFIG GET maxmemory-policy | grep -qx noeviction || fail 'Redis может удалять задания очереди при нехватке памяти.'
local_https_health_status="$(curl -sS --max-time 20 \
    --resolve "${domain}:443:127.0.0.1" \
    --output /dev/null \
    --write-out '%{http_code}' \
    "https://${domain}/up")"
[[ "$local_https_health_status" == '200' ]] \
    || fail "Laravel не прошёл локальную HTTPS-проверку; получен статус ${local_https_health_status}."

public_https_health_status="$(curl -sS --max-time 30 \
    --retry 5 \
    --retry-delay 2 \
    --retry-connrefused \
    --output /dev/null \
    --write-out '%{http_code}' \
    "https://${domain}/up")"
[[ "$public_https_health_status" == '200' ]] \
    || fail "Laravel не прошёл публичную HTTPS-проверку; получен статус ${public_https_health_status}."
supervisorctl status crm369-default | grep -q RUNNING
supervisorctl status crm369-notifications | grep -q RUNNING
runuser -u "$APP_USER" -- redis-cli ping | grep -q PONG

print_success "HTTPS-проверка Laravel успешно завершена: https://${domain}/up"

install -d -m 0700 "$INSTALL_STATE_DIR"
{
    printf 'installed_at=%s\n' "$(date --iso-8601=seconds)"
    printf 'installer_version=%s\n' "$INSTALLER_VERSION"
    printf 'domain=%s\n' "$domain"
    printf 'app_dir=%s\n' "$APP_DIR"
    printf 'repository=%s\n' "$GITHUB_REPOSITORY"
    printf 'ref=%s\n' "$GITHUB_REF"
    printf 'source_commit=%s\n' "$source_commit"
    printf 'admin_email=%s\n' "$admin_email"
} >"$INSTALL_STATE_FILE"
chmod 0600 "$INSTALL_STATE_FILE"
rm -f -- "$INSTALL_PROGRESS_FILE"

print_success "CRM369 установлена: https://${domain}"
print_success "В базе создан только super-admin ${admin_email}; демонстрационные данные отсутствуют."
