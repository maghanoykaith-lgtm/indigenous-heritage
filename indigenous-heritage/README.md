# Indigenous Heritage Management System

A PHP + MySQL archive for documenting, protecting, and respectfully sharing Indigenous
cultural heritage: traditional practices, oral traditions, languages, arts and crafts,
music and dance, artifacts, traditional knowledge, and historical records.

Records are published only with documented community consent, and every record has a
visibility level:

- **Public** – anyone can view it.
- **Restricted** – only approved community members (and staff).
- **Private** – staff only.

Sacred knowledge can never be made public, sensitive locations are hidden from everyone except
staff, and individual files can be marked confidential.

For hosting on the internet, see **[DEPLOYMENT.md](DEPLOYMENT.md)**.

## Features

**Public site** (responsive for phones, tablets, laptops and desktops)

- Home page with live counts, search, category tiles, and latest records
- Records browser with keyword search, category filter, sorting, and pagination
- Record pages with image gallery, lightbox, documents, and cultural-use notice
- Photo gallery, About and cultural protocols, contact form with spam protection
- Community member registration (accounts start as *pending* until approved)

**Admin console** (`/admin/`)

- Dashboard: totals, published, pending review, drafts, categories, restricted/private,
  media, unread messages, members awaiting approval, records per category, recent activity
- Heritage records: add, edit, view, delete, search and filter by category/status/visibility/consent
- Image and document uploads (JPG, PNG, GIF, WebP, PDF, DOCX, TXT). Images are re-encoded,
  which removes EXIF/GPS metadata, and thumbnails are generated.
- Publication workflow: draft → pending review → published → archived, with consent tracking
- Categories, contact messages, member approval/suspension, staff accounts, own account settings
- Roles: **Administrator** (everything) and **Editor** (create and edit records; publishing
  requires an administrator)

## Security

- PDO prepared statements everywhere (no SQL built from user input)
- Output escaping on every page, strict Content-Security-Policy, no inline scripts
- CSRF token on every form, POST-only logout and destructive actions
- Bcrypt password hashing, login throttling (5 attempts per account, 20 per IP, 15 minutes)
- Hardened sessions: HttpOnly, SameSite, Secure on HTTPS, ID rotation, idle timeout
- Forced password change for temporary passwords; the old default `admin123` is refused on
  the live site
- Uploads checked by real content type, size, and image dimensions; files are served by
  `media.php` only after an access check
- `includes/`, `config/`, `database/`, `docker/` and dot-files are blocked from the web
- Credentials only in environment variables or a git-ignored `.env`

## Local development (WAMP)

1. Start WAMP and open <http://localhost/indigenous-heritage/>.
2. Defaults connect to MySQL `localhost`, user `root`, empty password, database
   `indigenous_heritage`. To change them, copy `.env.example` to `.env` and edit it.
3. Create or upgrade the database schema (safe to run repeatedly; never deletes data):

   ```powershell
   C:\wamp64\bin\php\php8.3.14\php.exe database\migrate.php
   ```

4. Sign in at `/admin/login.php`. An existing account that still uses the old default
   password is asked to choose a new one at first sign-in.

## Project structure

```
index.php, records.php, record.php, gallery.php    Public pages
about.php, contact.php, register.php, user_dashboard.php
media.php                                          Access-controlled file delivery
health.php                                         Health check for hosting
crafts.php, traditions.php, materials.php, stories.php   Old URLs → redirect to records.php
admin/                                             Admin console
includes/
  bootstrap.php     Loads config, security headers, sessions, CSRF
  config.php        Environment-based settings (.env locally, env vars in production)
  db.php            PDO connection (TLS for managed databases)
  auth.php          Staff/member authentication, roles, throttling
  heritage.php      Records, visibility rules, uploads, validation
  migrations.php    Versioned, non-destructive schema migrations
  partials/         Shared page layouts
database/
  migrate.php       Run migrations (CLI only)
  export_data.php   Export local data for production import (CLI only)
  schema.sql        Reference copy of the full schema
  data/             Exported data (git-ignored: contains account details)
css/style.css, js/script.js
Dockerfile, docker/, render.yaml                  Production container and Render blueprint
```

`database/indigenous_heritage.sql` and `database/infinityfree_import.sql` are the original
schema files, kept for reference. The migration supersedes them.
