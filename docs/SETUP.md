# Setup

Run commands from the project root unless stated otherwise. These steps install a new local instance.

## 1. Prerequisites

- PHP 8.3+, Composer, Git, and MySQL/MariaDB.
- PHP extensions required by `composer.lock`; commonly PDO MySQL, mbstring, OpenSSL, fileinfo, XML/DOM, ctype, GD, and ZIP. Composer reports missing extensions during installation.
- Write access to `storage/` and `bootstrap/cache/`.

Node.js is optional: it is used by the `composer dev` helper, not by the frontend. Use `php artisan serve` directly for the setup below.

## 2. Download and install

```bash
git clone https://github.com/ferrerasjm/prism-capstone-public.git
cd prism-capstone-public
composer install
```

Copy `.env.example` to `.env` with `cp .env.example .env` (Bash) or `Copy-Item .env.example .env` (PowerShell).

## 3. Configure the database and local environment

Create an empty database named `prism` and a local database user with access to it. In `.env`, replace the database credentials and set:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
PRISM_PUBLIC_URL=http://localhost:8000
APP_TIMEZONE=Asia/Manila
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=prism
DB_USERNAME=your_local_database_user
DB_PASSWORD=your_private_database_password
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
LOGIN_OTP_CONSOLE=true
LOGIN_OTP_MAIL_ENABLED=true
RECAPTCHA_ENABLED=false
PRISM_ML_CLASSIFIER_ENABLED=false
```

`MAIL_MAILER=log` writes messages to `storage/logs/laravel.log`; it does not send email. The local OTP console setting displays the login code on the verification page. Keep this instance local while using these development settings.

## 4. Provision the administrator

Set `PRISM_ADMIN_EMAIL` to an authorized email and `PRISM_ADMIN_PASSWORD` to a private, unique password of at least 12 characters in `.env`. Then run:

```bash
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
php artisan storage:link
```

Seeders initialize the administrator, offense catalog, sanctions, risk weights, and optional handbook. They do not supply student rosters or demo case records. The administrator seeder skips missing credentials and never resets an existing account.

After provisioning, clear `PRISM_ADMIN_EMAIL` and `PRISM_ADMIN_PASSWORD` in `.env`, then run `php artisan config:clear`. Keep `APP_KEY` stable after storing encrypted data.

## 5. Start the application

Run each command in its own terminal:

```bash
php artisan serve
php artisan queue:work --sleep=3 --tries=3 --timeout=120
php artisan schedule:work
```

Visit `http://localhost:8000` and sign in with the administrator account. Create staff and student accounts through User Management. Account activation links expire after 72 hours by default (`AUTH_ACCOUNT_SETUP_EXPIRE`); with log mail, read the activation message from the local Laravel log. Database-queued messages require the worker above.

Bulk student imports require institutional `@students.nu-clark.edu.ph` addresses. Use disposable test data for local evaluation. Student-status CSV examples are explained [here](test-data/student-statuses/README.md).

## 6. Configure optional services

| Service | Configuration |
| --- | --- |
| SMTP email | Set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_SCHEME`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` to your provider's settings. Keep credentials private. |
| Mobile API | Run `php artisan jwt:secret` once for a new instance. Configure `MOBILE_OTP_ENABLED` and `MOBILE_OTP_MAIL_ENABLED` for the intended login flow; preserve the JWT secret during updates. |
| reCAPTCHA | Supply private site/secret keys and the deployment hostname before enabling `RECAPTCHA_ENABLED`. |
| ML classifier | Follow [classifier setup](../ml/README.md). With ML disabled or unavailable, Laravel uses keyword suggestions. |
| Handbook | Supply the authorized PDF privately at `database/seeders/assets/NU-Clark-Student-Handbook-2025.pdf` before seeding, or upload it through the application. A missing PDF is skipped. |
| University Service contacts | Supply the private directory at `storage/app/private/university-service-contacts.php`, or set `PRISM_US_CONTACTS_FILE` to its absolute path. It returns an office-to-contacts array, optionally wrapped in `contacts`. |

After `.env` changes, run `php artisan config:clear` locally and restart long-running workers. Contact addresses in the public privacy notice are placeholders: replace them with approved institutional contacts before deployment.

## 7. Production deployment

Configure HTTPS, the actual `APP_URL` and `PRISM_PUBLIC_URL`, real SMTP delivery, and:

```dotenv
APP_ENV=production
APP_DEBUG=false
LOGIN_OTP_CONSOLE=false
LOGIN_OTP_REVEAL_EMAILS=
LOGIN_OTP_MAIL_ENABLED=true
PASSWORD_OTP_CONSOLE=false
PASSWORD_OTP_MAIL_ENABLED=true
```

If mobile OTP is enabled, set `MOBILE_OTP_MAIL_ENABLED=true` so its code is not returned in the API response. Configure reCAPTCHA and review [security settings](SECURITY_PRIVACY_CONTROLS.md).

Point the web server document root to `public/`, then:

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Use a process manager for `php artisan queue:work --sleep=3 --tries=3 --timeout=120`. On Linux, schedule `php artisan schedule:run` every minute from the project root; on Windows, follow the [deployment guide](../deploy/windows/README.md). Restart queue workers with `php artisan queue:restart` after updates.

## Checks and troubleshooting

- `composer test`: application tests using the committed testing environment and SQLite in memory; requires development dependencies and PDO SQLite.
- `php artisan schedule:list`: registered scheduled work.
- `composer ops:check`: automation and privacy checks; these do not perform access reviews or restore drills.
- `php artisan queue:failed`: failed queued work; inspect the failure before retrying.
- `php artisan optimize:clear`: clear stale configuration, route, and view caches locally; rebuild production caches afterward.
- Check `storage/logs/laravel.log`, database connectivity, worker status, and storage permissions when setup fails.

Back up the database and persistent storage before updates. Preserve application keys and private configuration; see [security and privacy](SECURITY_PRIVACY_CONTROLS.md).
