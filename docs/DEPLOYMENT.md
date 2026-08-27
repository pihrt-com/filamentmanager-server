# Deployment

## Requirements

Use PHP 8.4 or newer with `pdo_mysql`, `openssl`, `mbstring`, `json`, `fileinfo`, and `zip`. Use MySQL 8.0+ or MariaDB 10.6+ with InnoDB and `utf8mb4`. HTTPS is required for production.

## Recommended layout

Upload the release to a directory outside the public document root and configure the domain or subdirectory document root to its `public/` directory. The URL `https://example.com/install/` then loads `public/install/index.php`, while application code, local configuration, logs, and backups remain outside the document root.

Example filesystem layout:

```text
/home/account/apps/filamentmanager/
  app/
  config/
  public/        <- web document root
  storage/
```

## Apache shared-hosting fallback

When the hosting panel cannot change the document root, upload the complete release to a directory such as `/public_html/filamentmanager/` and visit `https://pihrt.com/filamentmanager/install/`. The root `.htaccess` denies private directories and maps assets to `public/assets/`. This fallback requires Apache with `AllowOverride` enabled. Do not use it on a server that ignores `.htaccess`.

## Installation

1. Create an empty MySQL or MariaDB database and a dedicated database user in the hosting control panel.
2. Upload the release files in binary-safe mode and preserve `.htaccess` files.
3. For shared hosting, first ensure that the uploaded project directory is `0755` and that `.htaccess` and `prepare-install.php` are `0644`; Apache needs these minimum permissions before it can run PHP. Then open `/prepare-install.php` once. It sets the remaining release directories to `0755`, files to `0644`, verifies that PHP can write `config/` and `storage/`, and provides a link to `/install/`. Delete `prepare-install.php` immediately after use. If the hosting panel already applies these modes, you may open `/install/` directly.
4. Review the environment checks, enter the public HTTPS URL and database connection, and create the first administrator with a password of at least 12 characters.
5. Sign in and open Administration > Settings to review filesystem-security diagnostics.
6. Remove `/install/` and `/public/install/` through the hosting file manager or FTP. The `storage/installed.lock` file already blocks reinstalling before these directories are removed.
7. After installation, use directory mode `0750` for private directories, `0755` for `public/`, file mode `0640` for local configuration, and the least permissive mode that still lets PHP write to `storage/`.

## Nginx

Set the document root to the absolute `public/` directory, pass only `public/index.php` to PHP-FPM, use `try_files $uri $uri/ /index.php?$query_string`, deny hidden files, and never expose the project root. The Apache `.htaccess` files have no effect under Nginx.

## Backup before changes

Download an administration backup before PHP upgrades, server moves, database changes, or FilamentManager updates. Keep an additional off-server copy and test restoration periodically.
