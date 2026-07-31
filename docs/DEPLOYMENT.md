# Hostinger Deployment Notes

1. Upload the site files to the Hostinger public web root for `newpathchaplaincy.com`.
2. Create a MySQL database and database user in Hostinger.
3. Copy `.env.example` to `.env` on the server and fill in `DB_HOST`, `DB_NAME`, `DB_USER`, and `DB_PASS`.
4. Open phpMyAdmin, select the new database, use Import, and import `database/schema.sql`.
5. Import `database/seed.sql` into the same database.
6. Visit `/admin/login.php`.

Initial Super Admin:

- Email: `admin@newpathchaplaincy.com`
- Temporary password: `TempAdmin@2026!`
- 2FA code: `202626`

The account is forced to change its password on first login. Use a strong unique password and then remove the temporary password from any shared deployment notes.
