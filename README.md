# Damvolt — backend (Laravel 13)

Admin panel + JSON API for the Damvolt website ([frontend repo](https://github.com/hinguland-ui/damvolt-frontend)).
Everything on the website (logos, SEO, banner slides, home sections, services, legal pages, contact details…) is
edited here, in Blade, and served to the React site through one cached endpoint: `GET /api/content`.

## Requirements
PHP 8.3+, Composer, MySQL (or MariaDB), and a web server pointed at `public/` (Apache / nginx).

## Install
```bash
composer install --no-dev --optimize-autoloader     # drop --no-dev for local work / tests
cp .env.example .env
php artisan key:generate
```
Edit `.env`:

| Key | What it is |
| --- | --- |
| `APP_URL` | Public URL of this backend — image links are built from it |
| `FRONTEND_URL` | Website origin(s), comma separated. **Only these origins may call the API** (CORS + contact form) |
| `DB_*` | MySQL connection |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | First admin created by the seeder (empty password → a random one is printed once) |
| `TRUSTED_PROXIES` | `*` when behind nginx / Cloudflare, so rate limits see real visitor IPs |
| `APP_DEBUG` | **`false` in production** |

```bash
php artisan migrate --force
php artisan db:seed --force        # first admin + the starter content and pictures
php artisan storage:link
```
Admin panel: `https://your-backend/admin`

### Scheduler (daily clean-up of the 7-day activity log)
```
* * * * * cd /path/to/backend && php artisan schedule:run >> /dev/null 2>&1
```

## Uploads are not in git
Pictures uploaded in the admin panel are stored in `storage/app/public/uploads/` **on each server** and are
git-ignored — local and live never overwrite each other. The starter pictures that ship with the project are in
`database/seeders/assets/` and are copied into storage by `db:seed`.

## Security notes
- Admin login lasts 24 hours, is rate-limited, and supports Google reCAPTCHA v2 (Site Settings → reCAPTCHA).
- Locked out by a mis-configured captcha? `php artisan admin:captcha-off`
- `public/.htaccess` (and `.htaccess` in the project root, for hosts whose document root is not `public/`) block
  hidden files, config files and script execution in uploads.
- Contact-form e-mail is sent through the SMTP saved in Site Settings. Watch it live: `php artisan mail:watch`

## Tests
```bash
php artisan test        # 69 tests
./vendor/bin/pint --test
```
