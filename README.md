# CRM369

CRM369 is a modular corporate CRM and shared workspace for managing sales, internal operations, and team communications in one system.

The platform includes:

- contacts, public forms, CRM pipelines, and deals;
- projects, tasks, calendars, company news, and organizational structure;
- chats, conferences, a knowledge base, and file storage;
- procurement, warehouses, handheld terminals, equipment, production, and electronic document management;
- reference directories, business processes, API access, webhooks, and integrations with messengers, telephony, and 1C;
- user groups, granular permissions, security audits, two-factor authentication, and passkeys.

The backend uses Laravel 13 and PostgreSQL. The frontend uses Inertia.js 3, Vue 3, and Tailwind CSS 4. Redis provides realtime caches and notification queues.

## Install on a clean Ubuntu server

The automated installer supports:

- Ubuntu Server 22.04 or 24.04 LTS on `amd64`;
- at least 4 GB of free space;
- a domain with an A record pointing to the server before installation;
- outbound HTTP and HTTPS access;
- root access, either directly or through `sudo`.

Connect to the new server over SSH and run one command:

```bash
curl -fsSL https://raw.githubusercontent.com/webnetkz/crm369/main/scripts/install-ubuntu.sh | sudo bash
```

The command downloads [scripts/install-ubuntu.sh](scripts/install-ubuntu.sh) from the public [webnetkz/crm369](https://github.com/webnetkz/crm369) repository and starts the interactive installer.

The installer asks for:

1. The CRM369 domain without `https://`.
2. The initial super administrator's name and email address.
3. An email address for Let's Encrypt certificate notifications.
4. The super administrator's password and confirmation. Password input is hidden.

Production passwords must contain at least 12 characters, uppercase and lowercase letters, a number, and a symbol. Laravel also checks that the password has not appeared in a known data breach.

## What the installer configures

The installer automatically provisions:

- Nginx and a free Let's Encrypt TLS certificate with an HTTP-to-HTTPS redirect;
- PHP 8.4 FPM and the required PHP extensions;
- PostgreSQL with a dedicated database, role, and randomly generated password;
- Redis, Supervisor, and separate workers for the default and notification queues;
- the Laravel scheduler through system cron;
- Node.js 22, Composer, and a production Vite build;
- a production `.env`, secure file permissions, Laravel caches, and log rotation.

The application is installed in `/var/www/crm369`. Successful installation metadata is written to `/etc/crm369/installed`. Administrator and database passwords are never written to shell history or installation metadata.

## Clean database state

The installer runs only `php artisan migrate --force`. It does **not** run `db:seed` or `migrate --seed`.

After installation:

- the users table contains only the super administrator entered during installation;
- no demo users, deals, contacts, tasks, files, or other sample business data are created;
- only mandatory system records created by migrations remain, such as the Administrators group, default system stages, and disabled integration definitions.

When installation finishes, CRM369 is available at `https://your-domain`.

## Email delivery

The initial installation uses `MAIL_MAILER=log`. Outbound messages are written to the Laravel log and are not delivered externally. To enable password recovery and notification delivery, add SMTP settings to `/var/www/crm369/.env`, then rebuild the application cache:

```bash
cd /var/www/crm369 && sudo -u www-data php8.4 artisan optimize
```

## Native Android API and Firebase push notifications

The native Android application lives in [applicationsOfMobileAndroid](applicationsOfMobileAndroid/README.md) and uses the `/api/mobile/v1` API. One application build works with any CRM369 installation: the user enters the portal domain, the application discovers it through the public `GET /api/mobile/v1/portal` endpoint and signs in. Mobile sessions are stored as hashed bearer tokens and expire after 365 days by default. Administration pages and conference rooms open inside the application through a single-use web-session link (`POST /api/mobile/v1/web-session`). System and chat push notifications use Firebase Cloud Messaging HTTP v1 and the existing `notifications` queue worker.

To enable real push delivery, create a Firebase service account with access to Firebase Cloud Messaging and store its JSON credentials outside the application directory. For example:

```bash
sudo install -o root -g www-data -m 0640 firebase-service-account.json /etc/crm369/firebase-service-account.json
```

Add the following values to `/var/www/crm369/.env` without committing the credentials:

```dotenv
MOBILE_SESSION_DAYS=365
FCM_PROJECT_ID=your-firebase-project-id
FCM_SERVICE_ACCOUNT_PATH=/etc/crm369/firebase-service-account.json
FCM_CONNECT_TIMEOUT_SECONDS=5
FCM_TIMEOUT_SECONDS=10
FCM_ANDROID_APP_ID=1:000000000000:android:0000000000000000
FCM_ANDROID_API_KEY=your-android-client-api-key
FCM_SENDER_ID=000000000000
```

The last three values are the public client options of the Android application (package `com.appswebnetkz.crm369.android`) registered in the same Firebase project: `mobilesdk_app_id`, `api_key.current_key` and `project_number` from its `google-services.json`. The portal hands them to the application at sign-in, so the application does not need a `google-services.json` of its own.

Apply migrations, rebuild Laravel's cache, and restart the notification worker:

```bash
cd /var/www/crm369
sudo -u www-data php8.4 artisan migrate --force --no-interaction
sudo -u www-data php8.4 artisan optimize
sudo supervisorctl restart crm369-notifications
```

If the Firebase values are empty, the native API remains available but FCM delivery is disabled.

## Run an already downloaded installer

If the repository is already present on the server, run:

```bash
sudo ./scripts/install-ubuntu.sh
```

The installer does not overwrite an existing `/var/www/crm369` directory or an existing PostgreSQL database or role named `crm369`.
