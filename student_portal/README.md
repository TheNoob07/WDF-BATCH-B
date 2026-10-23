# StudentHub Portal

StudentHub is a responsive student portal built for the ITUE203 Web Development Frameworks practicals. It brings together the student-facing portal, JSON data explorer, PHP registration and authentication, and administrator tools while keeping the existing blue, white, and light-background theme.

## Run locally

1. Install XAMPP. In the XAMPP Control Panel, start **MySQL**. Apache is only needed if you use the `htdocs` option below.
2. Import [`mysql/database-schema.sql`](./mysql/database-schema.sql) through phpMyAdmin. For an existing database, follow the one-time migration instructions in [`php/DATABASE-SETUP.md`](./php/DATABASE-SETUP.md).
3. Start the web app using one of these methods:
   - **This project folder:** double-click `start-portal.bat`, keep its window open, and open `http://127.0.0.1:8000/`. The built-in PHP router protects the portal pages in the same way as `.htaccess`.
   - **XAMPP Apache:** copy this folder into `C:\xampp\htdocs\student_portal`, start Apache in the XAMPP Control Panel, and open `http://localhost/student_portal/`.
4. Do not open pages with `file://` or VS Code Live Server. PHP, sessions, login, and registration require a PHP web server and MySQL.
5. Create a student account. To promote a trusted account to administrator in phpMyAdmin:

   ```sql
   UPDATE users SET role = 'admin' WHERE username = 'your-username';
   ```

   Sign out and back in to load the administrator role. Admins can open **Manage records** from the admin dashboard.

Apache must allow `.htaccess` overrides and have `mod_rewrite` enabled. Sessions expire after 15 minutes of inactivity or 8 hours total. New event posters must be JPEG, PNG, or WebP files no larger than 2 MB.

If login or registration displays **Database setup required**, start MySQL in XAMPP, import `mysql/database-schema.sql` through phpMyAdmin, and reload the page. The default local connection is host `127.0.0.1`, database `studenthub`, user `root`, and an empty password.

## GitHub workflow

Create an empty repository on GitHub, then from this project directory run:

```text
git init
git add .
git commit -m "Start StudentHub portal"
git branch -M main
git remote add origin https://github.com/<your-account>/<your-repository>.git
git push -u origin main
```

Commit after each practical so the project history documents its incremental development. Do not commit real credentials or uploaded/private data.

## Project structure

```text
student_portal/
|-- CSS Files/       Shared page stylesheets
|-- Java Script Files/ Page scripts
|-- data/            JSON collections and file-based practical data
|-- images/          Campus images and illustrations
|-- mysql/           MySQL schema and practical migration scripts
|-- php/             PHP pages, authentication, and JSON APIs
|-- uploads/events/  Validated event posters
|-- *.html           Student-facing portal pages
```

## Practical coverage

| Practical | Portal implementation |
| --- | --- |
| 1 | Project scope, this README, [sitemap](./SITEMAP.md), and [wireframe](./WIREFRAME.md) |
| 2–4 | Semantic portal pages, responsive CSS, accessible navigation, JavaScript interactions, notifications, and sliders |
| 5 | Student registration form with field validation, password confirmation/strength feedback, and terms acceptance |
| 6 | JSON event, student, and FAQ explorer with fetch, search, filters, sorting, pagination, and local cache |
| 7 | PHP contact form with server-side validation, CSRF protection, and CSV storage |
| 8 | Normalized MySQL schema, seed data, PDO connection, and prepared statements |
| 9–10 | MySQLi registration, password hashing, sessions, role checks, logout, and session timeout |
| 11–12 | Admin-only student/event CRUD, search/filter, validated poster uploads, and confirmations |
| 13–14 | Fetch-based MySQL JSON API, dashboard statistics, role-gated admin tools, and audit log |
| 15 | Local XAMPP setup, documentation, migration guidance, and manual integration checklist |

The CSV/JSON practical endpoints and the MySQL management API are separate demonstrations; the administrator API does not modify the public JSON collections.
