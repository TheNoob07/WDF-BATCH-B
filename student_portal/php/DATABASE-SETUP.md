# MySQL setup

1. Start **MySQL** in the XAMPP Control Panel. Apache is only required when using the XAMPP web server; the project also includes `start-portal.bat` to run PHP directly from the project folder.
2. Open phpMyAdmin at `http://localhost/phpmyadmin`, select **Import**, and import `../mysql/database-schema.sql`. This creates the `studenthub` database, its tables, and sample student, event, and registration records.
3. Configure the connection in `db.php`. By default, it expects MySQL at `127.0.0.1`, database `studenthub`, user `root`, and no password (the usual local XAMPP defaults). For another setup, set `STUDENT_PORTAL_DB_HOST`, `STUDENT_PORTAL_DB_NAME`, `STUDENT_PORTAL_DB_USER`, and `STUDENT_PORTAL_DB_PASSWORD` in the PHP server environment.
4. To run from the current project folder, double-click `start-portal.bat` and open `http://127.0.0.1:8000/`. Keep the command window open. Alternatively, put `student_portal` under `C:\xampp\htdocs`, start Apache, and open `http://localhost/student_portal/`.
5. Register at `http://127.0.0.1:8000/php/registration.php`, then sign in at `http://127.0.0.1:8000/php/login.php`. Do not open PHP pages directly from the filesystem or with VS Code Live Server.

## Schema relationships

- `students` stores student details and has a unique email.
- `events` stores event information.
- `registrations` joins students to events, with foreign keys and a unique student/event pair to prevent duplicate registrations.

The page reads all three tables, writes new students/events/registrations using PDO prepared statements, validates submitted fields on the server, and protects forms with a CSRF token. The existing JSON and CSV features remain separate.

## User registration

The same schema file also creates the `users` table (unique username/email, a password hash, role and last-login columns), `remember_tokens` table, and `registration_audit` table. Re-import the schema file to create additional tables in an existing `studenthub` database; its `CREATE TABLE IF NOT EXISTS` statements keep existing tables and records.

Open `http://127.0.0.1:8000/php/registration.php` to test secure user registration when using `start-portal.bat` (or the corresponding `/student_portal/php/registration.php` URL under Apache). The page uses the same database environment settings, but connects with MySQLi and records successful registrations in the audit table in the same transaction as the user insert. The account form checks username/email uniqueness, validates and confirms passwords on both the browser and server, hashes passwords with `password_hash()`, and uses CSRF protection. New accounts use the `student` role.

## Credential login

Open `http://127.0.0.1:8000/php/login.php` (the old `login.html` address redirects there) to sign in using the username or email and password used at registration. The page verifies passwords with `password_verify()`, stores the user ID and username in a regenerated PHP session, and redirects to the appropriate role dashboard. Use its POST logout button to end the session.

## Login, sessions, roles, and timeout

For a database created before Practical 10, run `../mysql/practical10-migration.sql` once. To add the management and audit features to an existing portal database, run `../mysql/practical11-14-migration.sql` once after any required Practical 10 migration. For a new database, importing `../mysql/database-schema.sql` creates all tables and columns; do not run either migration afterward.

Open `http://localhost/student_portal/` to sign in. The login creates a temporary PHP session and redirects students to `dashboard.php`; the username is shown on that page. Remember-me sign-in is disabled, so closing the browser ends the sign-in session. Sessions also expire after 15 minutes of inactivity or 8 hours total. Logout ends the session. Database record management is restricted to admin accounts.

Apache must allow `.htaccess` overrides and have `mod_rewrite` enabled. The root `.htaccess` routes every portal `.html` page through a session check, so users cannot bypass login by opening a page URL directly. `login.html` remains a public redirect to the login form. Opening the site root displays login when signed out.

New accounts are students. To promote a trusted account to admin, update that account in phpMyAdmin, for example: `UPDATE users SET role = 'admin' WHERE username = 'your-username';` Admins are redirected to `admin-dashboard.php`; student and admin dashboard routes check the session role. Admins can manage student and event records from `php/manage-records.php`; record mutations are audited in `audit_logs`.

Event posters are restricted to JPEG, PNG, and WebP images up to 2 MB and are stored under `uploads/events/`. The management page uses the signed-in administrator session and a CSRF token for its JSON API.
