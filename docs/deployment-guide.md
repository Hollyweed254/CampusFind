# Deployment guide (free PHP/MySQL hosting)

Before choosing a host, confirm: PHP 8.x, MySQL/MariaDB, `fileinfo` extension, file uploads enabled, HTTPS.

1. Create the hosting account and a MySQL database; note host, db name, user, password.
2. **Fresh install:** import `database/campusfind.sql` in phpMyAdmin.
   **Existing install:** back up, then apply
   `database/migrations/2026-11-11-marketplace-v2.sql` once. It is additive; do
   not run it twice.
3. Edit `includes/config.php` with the host's DB credentials.
4. Upload all files by FTP/File Manager to `htdocs` (or `public_html`).
5. Make `uploads/` writable (chmod 755 or 775 as the host requires). Let PHP
   create `storage/chat`, `storage/verifications` and `storage/sms` on first
   use, or create them writable in advance.
6. **Security:** log in as admin and change the password (Profile), or change the hash in the SQL before import. Delete `test-connection.php`.
7. Visit `/test-connection.php` once (before deleting) to verify DB, uploads and fileinfo.
8. Configure optional services as environment variables (`docs/marketplace-v2.md`):
   SMS provider, university domains and M-Pesa Daraja. Keep the callback token
   secret. M-Pesa needs a public HTTPS callback; the app works with it disabled.
9. Run the test cases in `docs/test-cases.md` and the v2 checks in
   `docs/marketplace-v2.md` on the live URL and keep screenshots.
10. If the host ignores `.htaccess` (Nginx hosts), block PHP execution in
    `uploads/` and deny all HTTP access to `storage/` in the host's settings.
11. Keep a local backup of the database export and code.

To remove the v2 feature set, back up and use
`database/migrations/2026-11-11-marketplace-v2-down.sql`; it is destructive.
