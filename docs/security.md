# Security model

This package prepares a first installation of a Laravel application. Its setup entry point is separate from the application's normal routes so it can operate before database-backed application sessions are ready.

## Authorization and state

- Preparation creates a random setup code and stores verification state outside the public directory.
- Codes expire; the default preparation lifetime is 72 hours.
- Browser operations use PHP sessions and CSRF protection.
- A completion lock prevents repeating the installation after success.
- Preparation refuses to reopen a completed installation.

Keep preparation state at `storage/app/laravel-wizard/`, `.env`, backups, and logs out of the web-served directory and out of Git. Treat a prepared customer archive as private material. The source repository should contain no live setup code or credentials. Preserve `storage/app/laravel-wizard/installed.json` after completion.

## Hosting and transport

Require HTTPS for a real installation. The `--allow-http` preparation option is for local testing or staging that has deliberate access controls. It is not a workaround for a missing production certificate.

Serve only Laravel's public directory. A rewrite to `public/` from a publicly accessible application root does not make `.env`, dependency files, or backups private. The host/provider must support a safe document-root or developer-prepared split layout.

Do not trust arbitrary client-supplied forwarded HTTPS headers. If a reverse proxy terminates HTTPS, follow the host's documented trusted-proxy configuration.

## Configuration and database

The package preserves an existing application key and unrelated configuration values. It writes production settings without showing secrets in the public interface.

Version 1 handles MySQL/MariaDB only and expects a fresh database. It does not run destructive resets or downgrade migrations. Review project migrations and optional hooks: they are trusted PHP code from the application, with the privileges of its database user.

A migration-per-request workflow reduces the total work in one request; it cannot make a single long migration fit within every shared host's execution limit. MySQL DDL can leave partial changes after interruption. Recovery should be deliberate and based on the actual database state.

## Application boundary

The optional after-install hook runs project-provided code. Make it idempotent so a finalization retry cannot duplicate data or accounts. Do not embed default administrator credentials or development data.

Completion means the wizard finished its setup tasks. Application-specific authorization, file uploads, payment setup, mail delivery, external APIs, queue behavior, and supported dependency lifecycle remain the project's responsibility.

## Report a problem

Report implementation vulnerabilities privately to the repository owner rather than posting a working setup code or credentials in an issue. Include affected versions and minimal reproduction steps without live secrets.

## Effective database identity

The installer clears stale DB_URL, DATABASE_URL and DB_SOCKET settings and signs the checked MySQL connection. Before creating the migration repository, and before each migration’s SQL method, it compares the application’s effective connection with that signature. Connection URL overrides, named migration connections, read/write splits, and direct variants that do not match are blocked. Project providers and hooks are trusted application code and must not run destructive database actions during boot.
