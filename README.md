# Wayvio Public Source Release

This repository is the public source release of Wayvio.

Wayvio is based on LinkStack and was extensively rebuilt into a SaaS-oriented multi-tenant platform for the hosted Wayvio service at `wayvio.de`.

## Project Status

This repository contains the public application code published for Wayvio.

Important context:

- Wayvio started from the LinkStack codebase and was then modified heavily for the Wayvio product and SaaS operations.
- This public version still contains integration points for internal services used in the hosted Wayvio environment.
- Some of those internal APIs are not published in this repository.
- Because of that, this repository should be understood as the public application codebase, but not as a complete one-click reproduction of the production SaaS stack behind `wayvio.de`.
- The hosted Wayvio environment depends on additional internal services and operational components that are not part of this repository.

## Public Export Notes

This public export was sanitized before release:

- runtime artifacts, backups, local databases, `vendor/`, `node_modules/`, and real `.env` files are excluded
- example configuration values and legal contact placeholders were anonymized
- oversized theme previews, generated public build assets, and sample or user media were reduced or removed to keep the archive upload-friendly

## Local Development Setup

Requirements:

- PHP 8.1+
- Composer
- Node.js + npm
- MySQL or SQLite for local development

Quick start:

```bash
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run prod
php artisan serve
```

Notes:

- This setup is intended for local development and source inspection.
- If you want to use the installer flow, review `config/installer.php` and the installer routes in `routes/web.php`.
- Replace all placeholder values in `.env` before any public deployment, especially legal, mail, backup, and API settings.
- Some features in this public release reference internal APIs that are used for the hosted Wayvio SaaS environment and are not included here.
- Do not commit real secrets, backups, session files, logs, or generated caches.

## Hosted Service

The production Wayvio service at `wayvio.de` runs on a broader private operating environment than what is published here.

This repository therefore documents and releases the public source side of Wayvio, while some internal SaaS services, APIs, and infrastructure remain unpublished.

## Attribution

Wayvio is based on LinkStack:

- LinkStack repository: `https://github.com/LinkStackOrg/LinkStack`
- LinkStack project site: `https://linkstack.org`

Wayvio contains substantial modifications on top of that base, including SaaS-oriented multi-tenancy, product-specific workflows, and integrations for the hosted Wayvio platform.

This repository is therefore a derivative public source release built from the LinkStack foundation and extended for the Wayvio product.

## License

Wayvio uses the same license family as LinkStack: `AGPL-3.0-or-later` (GNU Affero General Public License v3.0 or later).

License check for this public export:

- `LICENSE` contains the GNU Affero General Public License v3 text
- `composer.json` declares `AGPL-3.0-or-later`
- this matches the current LinkStack repository license listing on GitHub (`AGPL-3.0 license`)

## Security

Report security issues privately through the appropriate deployment contact channel. Do not publish secrets or private vulnerability details in public issues.
