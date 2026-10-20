# MySQL setup

1. Start **Apache** and **MySQL** in XAMPP.
2. Open phpMyAdmin, select **Import**, and import `database-schema.sql`. This creates the `studenthub` database, its tables, and sample student, event, and registration records.
3. Configure the connection in `db.php`. By default, it expects MySQL at `127.0.0.1`, database `studenthub`, user `root`, and no password (the usual local XAMPP defaults). For another setup, set `STUDENT_PORTAL_DB_HOST`, `STUDENT_PORTAL_DB_NAME`, `STUDENT_PORTAL_DB_USER`, and `STUDENT_PORTAL_DB_PASSWORD` in the PHP server environment.
4. Put `student_portal` under the web server's document root (usually `C:\xampp\htdocs`) or configure an Apache virtual host to serve the project.
5. Open `http://localhost/student_portal/` and sign in. Do not open PHP pages directly from the filesystem or with VS Code Live Server; the portal must run through PHP and Apache.

## Schema relationships

- `students` stores student details and has a unique email.
- `events` stores event information.
- `registrations` joins students to events, with foreign keys and a unique student/event pair to prevent duplicate registrations.

The page reads all three tables, writes new students/events/registrations using PDO prepared statements, validates submitted fields on the server, and protects forms with a CSRF token. The existing JSON and CSV features remain separate.

## User registration

The same schema file also creates the `users` table (unique username/email, a password hash, role and last-login columns), `remember_tokens` table, and `registration_audit` table. Re-import the schema file to create additional tables in an existing `studenthub` database; its `CREATE TABLE IF NOT EXISTS` statements keep existing tables and records.

Open `http://localhost/student_portal/php/registration.php` to test secure user registration. The page uses the same database environment settings, but connects with MySQLi and records successful registrations in the audit table in the same transaction as the user insert. The account form checks username/email uniqueness, validates and confirms passwords on both the browser and server, hashes passwords with `password_hash()`, and uses CSRF protection. New accounts use the `student` role.

## Credential login

Open `http://localhost/student_portal/php/login.php` (the old `login.html` address redirects there) to sign in using the username or email and password used at registration. The page verifies passwords with `password_verify()`, stores the user ID and username in a regenerated PHP session, and redirects to the appropriate role dashboard. Use its POST logout button to end the session.

## Login, sessions, roles, and timeout

For an existing database, run `practical10-migration.sql` **once** in phpMyAdmin to add roles, last-login tracking, and remember-token storage. For a new database, importing `database-schema.sql` creates the same objects; do not run the migration after that.

Open `http://localhost/student_portal/` to sign in. The login creates a temporary PHP session and redirects students to `dashboard.php`; the username is shown on that page. Remember-me sign-in is disabled, so closing the browser ends the sign-in session. Sessions also expire after 15 minutes of inactivity or 8 hours total. Logout ends the session. Database record management is restricted to admin accounts.

Apache must allow `.htaccess` overrides and have `mod_rewrite` enabled. The root `.htaccess` routes every portal `.html` page through a session check, so users cannot bypass login by opening a page URL directly. `login.html` remains a public redirect to the login form. Opening the site root displays login when signed out.

New accounts are students. To promote a trusted account to admin, update that account in phpMyAdmin, for example: `UPDATE users SET role = 'admin' WHERE username = 'your-username';` Admins are redirected to `admin-dashboard.php`; student and admin dashboard routes check the session role.
