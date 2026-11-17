# CampusFind - Your campus. Your marketplace.

Campus-only marketplace for products and student services. Buyers can message
sellers inside the app, record a transaction, and optionally pay through
M-Pesa (STK Push) when Daraja is configured. Direct payments are recorded as
reported, never as verified by CampusFind.
Stack: PHP 8, MySQL, PDO, Bootstrap 5, vanilla JS.

## Run locally (XAMPP)
Collaborators setting up the database from a fresh clone: follow
[docs/collaborator-setup.md](docs/collaborator-setup.md) for the exact
commands (create DB, import schema, seed demo data, verify).

1. Copy this folder to `C:\xampp\htdocs\CampusFind` (or run `setup.ps1`).
2. Start Apache + MySQL in XAMPP.
3. **Fresh install:** create database `campusfind` and import
   `database/campusfind.sql` (it drops/recreates tables).
   **Existing install:** back up, then apply
   `database/migrations/2026-11-11-marketplace-v2.sql` once. It is additive and
   keeps existing data.
4. Edit `includes/config.php` if your MySQL user/password differ.
5. Visit `http://localhost/CampusFind/` and `/test-connection.php`.

Optional integrations (SMS provider, Google login, M-Pesa) are configured with
environment variables; see `docs/marketplace-v2.md`. The app runs without them:
SMS is simulated and clearly labelled, and M-Pesa stays disabled.

Admin login: `admin@campusfind.local` / `admin123`. **Change it and delete `test-connection.php` before deploying.**

## Design
UI follows the soko campus Figma reference design (brand name is CampusFind): Forest #173D2C, Lime #C9F27A, Warm-white #F7F8F5, Inter font, KSh pricing. The brand name and campus label are set in `includes/config.php` (`APP_NAME`, `CAMPUS_NAME`). Light is the default; a cookie-based dark mode toggle is in the navbar.

## Features
- Register / login / logout, email verification, Google login, profile + password change, student/admin roles, deactivation and suspension
- Listings CRUD (products and services share one table), up to 3 images, mark sold/closed/reopen, admin inspection before approval
- Search by keyword, type, category, price range, sort, pagination
- Contact seller via WhatsApp/phone/email link (login required to see it)
- Phone OTP, university email OTP and admin-reviewed student verification
- Public badges for verified phone (real SMS only), email, student status and earned Trusted Seller; public seller profiles with reviews
- Internal messaging per listing: polling, unread counts, read status, image attachments, blocking, reporting
- Transactions: inspection, direct payment reporting, M-Pesa STK Push (Daraja), two-sided completion, disputes, reviews
- Report listing or user; admin moderation queue (approve/reject/remove/restore, with moderation log) and abuse reports
- Admin: dashboard, users, verification, abuse, transactions, payments, audit log, reports with date filter + print
- Cookies: theme preference (`cf_theme`). Sessions: auth, role, flash messages, CSRF token

## Rules built in
- New and edited listings go to `pending` and need admin approval
- Only the owner can edit/delete/close a listing (checked server-side on every request)
- All writes are POST + CSRF token; SQL uses PDO prepared statements; output is escaped
- Uploads: JPG/PNG/WebP only, content-checked, 2MB max, random filenames, scripts blocked in `uploads/`
- Chat attachments and verification evidence are stored in `storage/` (denied over HTTP) and served through authenticated PHP endpoints
- Payments are never marked paid by a browser action; only a validated Daraja callback or status query can verify one
- Admin actions require the admin role, POST + CSRF, and are recorded in `admin_audit_logs`

## Structure
`includes/` shared code (config, db, auth/session/CSRF, helpers, header/footer, otp, chat, transactions, mpesa, trust) | `admin/` admin pages | `api/` JSON endpoints | `database/` schema and migrations | `docs/`
