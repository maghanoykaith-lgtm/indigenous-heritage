# Deploying the Indigenous Heritage Management System

Target architecture:

```
Phone / tablet / laptop ──HTTPS──> Render (Docker: PHP 8.3 + Apache) ──TLS──> Aiven for MySQL (free)
```

Nothing in production depends on WAMP, `localhost`, or the local database. All
settings come from environment variables set in the hosting dashboards.

## Why Render + Aiven

| Need | Render (web) | Aiven (database) |
| --- | --- | --- |
| Cost | Free web service | Free MySQL plan, no card required |
| PHP + MySQL | Runs this repo's `Dockerfile` (PHP 8.3, Apache, GD, PDO MySQL) | MySQL 8, TLS enforced |
| HTTPS | Automatic certificate on `*.onrender.com` and custom domains | Encrypted connections and disk |
| Secrets | Environment variables (never in git) | Service URI + CA certificate |
| Deploys | Auto-deploys on every `git push` | Automated backups |

Files and images are stored inside MySQL, so the web server needs no persistent
disk (Render's free plan has none).

### Free-tier limits (checked October 2026)

- **Render free:** 0.1 CPU / 512 MB RAM; sleeps after 15 minutes without traffic and
  takes about a minute to wake; 750 instance-hours per month (enough for one service).
  Sessions live in the container, so visitors are signed out when it sleeps or redeploys.
- **Aiven free MySQL:** 1 CPU / 1 GB RAM / **1 GB storage**, 76 connections, single node,
  documentation-only support. Aiven may power off a free service after a long period of
  inactivity (it emails first; you can power it back on).
- 1 GB is roughly 1,500–3,000 resized photos plus thumbnails. Images are re-encoded to at
  most 2000 px; documents count at their full size (max 5 MB each).

### Paid options (only with your approval)

- Aiven **Developer** MySQL: **$5/month**, 8 GB storage.
- Render **Starter** web service: about **$7/month**, no sleeping (confirm on Render's pricing page).
- A custom domain: about **$10–15/year** from a registrar; Render's HTTPS for it is free.

---

## What you need to do (accounts and secrets)

These steps need your own accounts, so they cannot be automated from this machine.
Never paste passwords or the database URI into project files; enter them only in
the dashboards or in a terminal session as shown.

### Step 1 – Create the free database on Aiven

1. Sign up at <https://console.aiven.io/signup> (no credit card).
2. **Create service → MySQL → Free plan.** Choose the region closest to your users
   (for the Philippines, a Singapore region if offered). Name it `ihms-db`.
3. Wait until the status is **Running**.
4. On the service **Overview** page:
   - copy the **Service URI** (`mysql://avnadmin:…@….aivencloud.com:PORT/defaultdb?ssl-mode=REQUIRED`);
   - click **CA certificate → Download** and save `ca.pem` somewhere outside the project folder.

### Step 2 – Copy your data into the new database (from this PC)

Run this in PowerShell from the project folder. The values exist only in this
terminal session and are not saved anywhere:

```powershell
cd C:\wamp64\www\indigenous-heritage
$env:APP_ENV = 'production'
$env:DATABASE_URL = Read-Host 'Paste the Aiven Service URI'
$env:DB_SSL_CA = 'C:\path\to\ca.pem'
$env:ADMIN_INITIAL_PASSWORD = Read-Host 'Choose the admin password for the live site'
C:\wamp64\bin\php\php8.3.14\php.exe database\migrate.php
```

Expected output ends with:

```
[migrate]   Imported initial data (NN statements).
[migrate]   Replaced the default password for 'admin' with ADMIN_INITIAL_PASSWORD.
[migrate] Database is up to date.
```

This creates every table and imports `database/data/initial_data.sql`: all 16 heritage
records, 8 categories, the original crafts/traditions/materials/stories/gallery rows,
accounts, and messages. That file holds account e-mails and password hashes, so it is
git-ignored and never uploaded to GitHub. To refresh it from your local WAMP data first:
`C:\wamp64\bin\php\php8.3.14\php.exe database\export_data.php`.

### Step 3 – Put the code on GitHub (private repository)

1. Create a **private** repository at <https://github.com/new> named `indigenous-heritage`
   (no README, no .gitignore).
2. In the project folder (the repository is already initialised and staged):

```powershell
git config user.name  "Your Name"
git config user.email "you@example.com"
git commit -m "Indigenous Heritage Management System"
git remote add origin https://github.com/YOUR-USERNAME/indigenous-heritage.git
git push -u origin main
```

Git for Windows opens a browser window to sign in to GitHub on the first push.

### Step 4 – Deploy the web service on Render

1. Sign up at <https://dashboard.render.com/register> using **GitHub** and allow access to the repository.
2. **New → Blueprint**, select the `indigenous-heritage` repository. Render reads `render.yaml`
   (free plan, Singapore region, Docker, health check `/health.php`).
3. Render asks for the secret values:

   | Variable | Value |
   | --- | --- |
   | `DATABASE_URL` | the Aiven Service URI from step 1 |
   | `DB_SSL_CA_PEM` | the full text of `ca.pem` (open it in Notepad, copy everything) |
   | `ADMIN_INITIAL_PASSWORD` | the same password as step 2 |
   | `APP_URL` | `https://indigenous-heritage.onrender.com` (or the address Render shows) |
   | `CONTACT_EMAIL` | the public contact address for the archive |

4. Click **Apply**. The first build takes about 5 minutes. In **Logs** you should see
   `[migrate] Database is up to date.` followed by Apache starting.
5. If Render gives the service a different address, update `APP_URL` to match
   (**Environment → Edit**; Render redeploys automatically).

### Step 5 – Verify the live site

- `https://YOUR-SERVICE.onrender.com/health.php` shows `ok`.
- The homepage shows 16 records and 8 categories.
- Sign in at `/admin/login.php` as `admin` with the password from step 2. You are asked to
  choose a new one, then reach the dashboard.
- Add a test record with a photo, then open it from a private/incognito window and from a phone.
- Mark it **Restricted**: the private window must now show "not found".

---

## Day-to-day operation

- **Update the site:** commit and `git push`; Render rebuilds and runs migrations automatically.
- **Database changes:** add a new step in `includes/migrations.php`. Steps run once, in order,
  and never drop data.
- **Backups:** Aiven keeps automatic backups. For your own copy, run `database/export_data.php`
  with the production environment variables from step 2 set.
- **Custom domain:** buy a domain (cost needs approval), then add it under the Render service's
  **Settings → Custom Domains** and follow the DNS instructions; HTTPS is issued automatically.

## Configuration reference

See `.env.example`. Important production settings:

| Variable | Purpose |
| --- | --- |
| `APP_ENV=production` | Hides errors, forces HTTPS, blocks the old default password |
| `DATABASE_URL` or `DB_HOST`/`DB_PORT`/`DB_NAME`/`DB_USER`/`DB_PASSWORD` | Database connection |
| `DB_SSL_CA_PEM` or `DB_SSL_CA` | CA certificate for verified TLS to the database |
| `ADMIN_INITIAL_PASSWORD` | First admin password (forced change at first sign-in) |
| `APP_URL` | Public address, used for HTTPS redirects |
| `TRUSTED_PROXY_HOPS` | Proxies in front of PHP (Render: `1`), for correct client IPs |
| `SESSION_TIMEOUT_MINUTES`, `UPLOAD_MAX_MB`, `APP_TIMEZONE`, `CONTACT_EMAIL` | Optional tuning |

## Other hosts

The app is plain PHP 8.1+ with PDO MySQL and GD, so it also runs on any PHP host or Docker
platform (Railway, Fly.io, a VPS, cPanel hosting). Set the same environment variables, point the
web root at the project folder, and run `php database/migrate.php` once.
