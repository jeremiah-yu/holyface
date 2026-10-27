# XAMPP Setup Guide — Holy Face AMGS

Step-by-step local setup on Windows with XAMPP (Apache + MariaDB/MySQL + PHP 8).

## 1. Install XAMPP

1. Download XAMPP from [apachefriends.org](https://www.apachefriends.org/).
2. Install with **Apache**, **MySQL/MariaDB**, and **PHP 8.1+** selected.
3. Open **XAMPP Control Panel** → start **Apache** and **MySQL**.

## 2. Copy the project

Place this repository under your web root, for example:

`C:\xampp\htdocs\holyface\`

(Your path may be `D:\OneDrive\Desktop\holyface` if htdocs is symlinked — adjust URLs accordingly.)

## 3. Create the database

1. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin).
2. Import **`database/schema.sql`** (creates `holyface_amgs`).
3. Import **`database/seed.sql`** (fictional users and sample classes).
4. Import later migrations in order: `002_contact_inquiries.sql`, `003_student_app.sql`, **`004_teacher_desktop.sql`**, **`005_policies.sql`**, **`006_official_report_cards.sql`**.
5. Run **`database/create_db_user.sql`** in the SQL tab (edit password first).

## 4. Configure the API

```powershell
cd C:\xampp\htdocs\holyface\api
copy .env.example .env
composer install
```

Edit **`api/.env`**:

- `DB_USER` / `DB_PASS` — match `create_db_user.sql`
- `JWT_SECRET` — long random string (32+ characters)

Verify: [http://localhost/holyface/api/public/health](http://localhost/holyface/api/public/health)

Expected JSON: `"success": true`

### Apache note

Ensure `mod_rewrite` is enabled. The **`api/public/.htaccess`** routes all requests to `index.php`.

If rewrite fails, enable `AllowOverride All` for htdocs in `httpd.conf`.

## 5. Web promotional site

Open: [http://localhost/holyface/web/public/index.php](http://localhost/holyface/web/public/index.php)

Update **`web/config.php`** if your API URL differs.

**Logo:** replace `web/public/assets/img/hf-logo.png` (or keep the downloaded official logo).

## 6. Teacher desktop app

```powershell
cd C:\xampp\htdocs\holyface\desktop
npm install
npm run dev
```

Electron loads Vite on port **5173**. The app probes `http://127.0.0.1:8089` then Apache `http://localhost/holyface/api/public` (see `desktop/src/api.ts` and `electron/preload.js`). Override with env `AMGS_API_URL`.

If Apache is not serving `/holyface`, run the API with:

```powershell
cd D:\OneDrive\Desktop\holyface\api\public
php -S 127.0.0.1:8089
```

## 7. Student mobile app

```powershell
cd C:\xampp\htdocs\holyface\mobile
npm install
npx expo start
```

- **Android emulator:** API base `http://10.0.2.2/holyface/api/public` (in `mobile/app.json` `extra.apiBaseUrl`).
- **Physical device:** use your PC LAN IP, e.g. `http://192.168.1.10/holyface/api/public`, and allow Apache through Windows Firewall.

## 8. HTTPS (production)

Terminate TLS on Apache or a reverse proxy. Set `APP_ENV=production`, `APP_DEBUG=false`, and restrict `CORS_ALLOWED_ORIGINS` to your real web/mobile/desktop origins.

## Troubleshooting

| Issue | Fix |
|-------|-----|
| 500 on API | Check `api/.env`, DB credentials, `vendor/` after `composer install` |
| Empty strands on web | API unreachable — confirm Apache path and `/health` |
| CORS errors from desktop/mobile | Add origin to `CORS_ALLOWED_ORIGINS` in `.env` |
| Login locked | Wait 15 minutes or reset `failed_login_attempts` / `locked_until` in `users` |
