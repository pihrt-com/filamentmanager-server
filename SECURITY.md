# Security policy

## Supported versions

Security fixes are provided for the latest stable release. Administrators should keep PHP, MySQL or MariaDB, the web server, and FilamentManager Server updated.

## Reporting a vulnerability

Do not open a public GitHub issue for a vulnerability. Contact Martin Pihrt through [pihrt.com](https://www.pihrt.com/) and include the affected version, reproduction steps, impact, and any proposed mitigation. Do not include production credentials, access tokens, database exports, or personal data.

## Deployment requirements

Production installations must use HTTPS. The recommended document root is `public/`. The web server must deny direct access to `app/`, `config/`, `database/`, `resources/`, `routes/`, `storage/`, `tests/`, `tools/`, `.git/`, backup files, logs, and local configuration. Apache fallback rules are included, but Nginx and other servers must be configured explicitly.

Use a dedicated database account and grant only the permissions needed by the application. Keep `config/local.php` readable only by the web-server account, keep `storage/` writable only where required, disable directory listings, and remove the installer directories after a successful installation. Never commit production configuration or backups.

Web authentication uses server-side sessions, secure cookies, password hashing, login lockouts, permission checks, and CSRF tokens. REST clients use Bearer tokens and never send tokens in a URL. Mobile refresh tokens are scoped to one device and stored only as SHA-256 hashes on the server.
