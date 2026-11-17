# Collaborator setup — database and first run

For anyone cloning the repo for local development. Target: XAMPP on Windows
(Apache + MySQL + PHP 8). Assumes Git is installed.

The repository ships the schema and migrations, **not** the database itself.

---

## 1. Clone into htdocs

```powershell
git clone https://github.com/Hollyweed254/CampusFind.git C:\xampp\htdocs\CampusFind
```

The folder name must stay `CampusFind` so the URL is
`http://localhost/CampusFind/` (or adjust the vhost/base path accordingly).

## 2. Start the stack

Open the XAMPP Control Panel and start **Apache** and **MySQL**. Wait for both
to show green. If MySQL fails, port 3306 is usually already taken by another
MySQL instance — stop that one first.

## 3. Create the database

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE IF NOT EXISTS campusfind CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

If your MySQL root account has a password, add `-p` and enter it.

## 4. Import the schema (fresh install only)

```powershell
C:\xampp\mysql\bin\mysql.exe -u root campusfind < C:\xampp\htdocs\CampusFind\database\campusfind.sql
```

> **Warning:** `campusfind.sql` drops and recreates every table. Use it only
> on an empty/new database. If you already have data, skip to step 8.

## 5. Optional: seed demo data

From the project folder:

```powershell
cd C:\xampp\htdocs\CampusFind
C:\xampp\php\php.exe database\seed-demo.php
```

Creates demo students, approved listings and one pending listing. Demo login:
any seeded email with password `password123`. Re-run with `--reset` to wipe
and reseed the demo data only.

## 6. Local configuration

The app runs with defaults (`root`, no password, database `campusfind`). If
your MySQL credentials differ, edit the four DB constants at the top of
`includes/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'campusfind');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Optional integrations (Google login, SMTP, SMS, M-Pesa) are **not required**.
To configure any of them:

```powershell
copy includes\config.local.example.php includes\config.local.php
```

Then fill in your own values in `includes/config.local.php`. That file is
gitignored — never commit it, and never paste real credentials into tracked
files.

## 7. Verify

- <http://localhost/CampusFind/test-connection.php> — checks DB + PHP setup
- <http://localhost/CampusFind/> — homepage
- Admin: `admin@campusfind.local` / `admin123` (change it before deploying;
  delete `test-connection.php` before any public deployment)
- Uploads: make sure `uploads/` and `storage/` exist and are writable by
  Apache. The app creates their subfolders on demand.

## 8. Already have a database with data?

Never import the full schema over it. Apply only the additive migration:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root campusfind < C:\xampp\htdocs\CampusFind\database\migrations\2026-11-11-marketplace-v2.sql
```

New migrations are dated `YYYY-MM-DD-feature.sql`; apply each one **once**, in
date order, and back up first.

---

## Troubleshooting

| Symptom | Fix |
| --- | --- |
| `SQLSTATE[HY000] [2002]` connection refused | MySQL not started, or wrong host/port |
| `Access denied for user 'root'` | Add your MySQL password to `DB_PASS` |
| `Unknown database 'campusfind'` | Step 3 was skipped |
| Blank page / 500 | Check `C:\xampp\apache\logs\error.log`; usually a syntax error or missing extension |
| Google / email verification disabled | Expected — copy and fill `config.local.php` (step 6) |
| Listing images rejected | Check `upload_max_filesize` / `post_max_size` in `php.ini` (needs ≥ 2 MB per image) |
| "Folder not empty" on clone | Clone to a different path or move the existing folder aside |
