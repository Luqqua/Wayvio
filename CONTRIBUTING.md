# Contributing

## Scope

This repository is published as the AGPL-licensed public source release of Wayvio.

Wayvio is derived from LinkStack and includes product-specific changes for the hosted Wayvio platform. Some internal SaaS components referenced by the application are not published in this repository.

Before opening a pull request:

1. Install dependencies with `composer install` and `npm install`.
2. Copy `.env.example` to `.env` and configure local values.
3. Run `php artisan key:generate`.
4. Run `php artisan migrate` and `php artisan db:seed`.
5. Run `php artisan test`.

## Rules

- Do not commit real secrets, `.env` files, backups, logs, session files, or generated caches.
- Keep legal and support placeholders generic in example configuration.
- Do not introduce documentation that implies this repository contains the complete private production stack behind `wayvio.de`.
- Document behavior changes that affect setup, deployment, or licensing.

## License

By contributing, you agree that your contributions are licensed under `AGPL-3.0-or-later`.
