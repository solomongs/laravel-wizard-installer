# Laravel Wizard Installer

A guided browser installer for Laravel applications on compatible 20i, cPanel, and other PHP shared hosting. **Developed by Solomon Gbeta.**

Give a customer a prepared Laravel website ZIP, a private setup code, and a clear installation path. The wizard checks the hosting environment, explains problems in plain English, saves production configuration, tests an empty MySQL/MariaDB database, runs the application's migrations in small steps, and locks itself when finished.

**This repository is a reusable installer package, not a complete Laravel website.** Add it to your Laravel project before preparing that project's upload. Dependencies and frontend assets must already be built. Customers do not need Composer, Node.js, SSH, Livewire, or a license verification server to run the browser installer.

[Website and interactive demo](https://solomongs.github.io/laravel-wizard-installer/) · [20i guide](docs/20i.md) · [cPanel guide](docs/cpanel.md) · [Troubleshooting](docs/troubleshooting.md) · [Project integration](docs/integration.md) · [Verification record](docs/testing.md)

## Features

- A standalone `public/install.php` entry point that can diagnose a Laravel app before its usual database-backed session boots.
- Private, expiring setup codes; PHP sessions and CSRF protection; installation state outside the public folder.
- Hosting profiles for 20i, cPanel, and other compatible hosts, with field hints and actionable status messages.
- PHP, extension, deployment-file, document-root, and writable-folder checks.
- Production `.env` updates that quote values safely and keep unrelated application settings.
- An existing `APP_KEY` is preserved; a new key is generated only when absent.
- MySQL/MariaDB connection testing and a fresh-install database guard.
- One pending migration per browser request, with progress and a permanent completion lock.
- An optional application hook for project-specific initial data or administrator setup.
- Developer commands: `wizard:prepare`, `wizard:doctor`, and `wizard:status`.
- A reusable ChatGPT/Codex skill and a GitHub Pages documentation site with an interactive walkthrough.

## Compatibility

The installer code requires PHP 8.1+. The Laravel project and its locked dependencies may require a newer version:

| Laravel project | Framework minimum PHP | Additional condition |
|---|---:|---|
| Laravel 10 | 8.1 | Existing projects; review framework support lifecycle |
| Laravel 11 | 8.2 | Use the project lock file as the final requirement |
| Laravel 12 | 8.2 | Use the project lock file as the final requirement |
| Laravel 13 | 8.3 | Dependencies may raise the minimum |

Laravel 10 and 11 are legacy compatibility targets, not recommendations for new production projects. Use maintained Laravel 12 or 13 for new builds. Laravel 10–13 are the package integration targets. See the repository's CI results for the versions actually tested. Hosting compatibility means the specific account passes the checks; the name “cPanel” alone does not guarantee available PHP versions, extensions, database privileges, or resource limits.

Version 1 supports fresh MySQL/MariaDB installations. Redis, PostgreSQL, SQLite, persistent queue workers, WebSockets, and project-specific services are outside its automatic setup. Large individual migrations still need enough host execution time; splitting the migration list does not split a single migration.

## Add it to your Laravel project

Run these commands on your development machine, from the Laravel project root:

```bash
composer config repositories.solomongs-wizard vcs https://github.com/solomongs/laravel-wizard-installer.git
composer require solomongs/laravel-wizard-installer:^1.0
php artisan vendor:publish --tag=laravel-wizard-config
```

Keep the installer as a production dependency, not a `require-dev` dependency. Laravel package discovery registers the Artisan commands. If your application disables discovery, register the service provider as described in [the integration guide](docs/integration.md).

Prepare your release copy:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php vendor/solomongs/laravel-wizard-installer/bin/prepare.php --ttl=72 --brand="My Laravel Website"
```

The preparation command writes `public/install.php` and private preparation state in `storage/app/laravel-wizard/`, then displays a setup code. Store that code privately and send it to the site's owner separately from the upload archive. The default expiry is **72 hours from preparation**, not from upload; supported windows are 1–168 hours.

If your project already boots successfully, the equivalent Artisan entry point is:

```bash
php artisan wizard:prepare --ttl=72 --brand="My Laravel Website"
php artisan wizard:doctor
php artisan wizard:status
```

If your project uses Vite or another frontend build tool, build those assets on the development machine before packaging. Include `vendor/`, `composer.lock`, the built frontend assets, the generated entry point, and the fresh private `setup.json` and `state.json` in your customer release. Exclude development credentials, populated database files, logs, user uploads, `.git/`, `node_modules/`, and private runtime files `installed.json`, `sessions/`, `attempts.json`, and `environment.backup`. Do not commit `storage/app/laravel-wizard/` or the setup code. Test a disposable clone of the release so test credentials and completion state cannot contaminate the customer archive.

## Install the website in your browser

1. Create a new MySQL/MariaDB database and database user in your hosting panel. Assign the user's database privileges. Copy the exact database name, username, hostname, and password privately.
2. Select the PHP version required by the project and enable its required extensions.
3. Upload and extract the **prepared website release**, including hidden files and `vendor/`.
4. Point the domain's document root at that website's `public/` directory. Use the relevant hosting guide if the panel restricts this setting.
5. Enable a valid HTTPS certificate and open `https://your-domain.example/install.php`.
6. Enter the setup code. Choose your hosting profile and work through the checks, website settings, database test, review, and installation.
7. Wait for “Installation complete”. Open the website, confirm its main pages work, and remove `public/install.php` through File Manager. Keep the private completion lock in place.

The wizard does not create a hosting account, configure DNS, issue SSL certificates, install missing PHP extensions, or create a database through your hosting provider. Its tips show where you can perform those tasks.

## Add your application's initial setup

Use the optional `after_install` class in `config/laravel-wizard.php` to provision project-specific data. Your class implements `SolomonGbeta\LaravelWizard\Contracts\AfterInstall` and receives the booted Laravel application. See [the complete example](docs/integration.md#project-specific-initial-data).

No administrator account or default password is created automatically. Define the account flow your own project needs. Make your hook safe to retry and never use destructive seeders.

## Security and recovery

Use a new database for a first installation. The wizard rejects an unrelated populated database and never runs `migrate:fresh`, `db:wipe`, or automatic rollbacks. Migrations are your application's code; review them before packaging.

The completion lock at `storage/app/laravel-wizard/installed.json` disables setup after success. Preparation refuses to reopen a completed installation. Do not delete the lock to upgrade a live site. Deploy upgrades with the application's normal migration and backup workflow.

Use HTTPS for any real installation. `--allow-http` exists only for local testing or staging with access controls. Never share your database password, `.env`, setup code, or preparation archive in a public issue.

See [the security model](docs/security.md) and [recovery guide](docs/troubleshooting.md) for interruption, expiry, and failure behavior.

## ChatGPT/Codex skill

The skill is named **`laravel-shared-hosting-installer`**. Invoke it with a request such as:

```text
Use $laravel-shared-hosting-installer to add the wizard to this Laravel project and prepare a 20i upload with a customer installation guide.
```

The portable source is in [`skill/laravel-shared-hosting-installer`](skill/laravel-shared-hosting-installer). Copy that folder into your Codex skills directory when installing elsewhere. The skill audits the project first, integrates the Composer package, checks the hosting layout, and prepares a release with project-specific instructions. It does not assume a particular Laravel starter kit.

## Development and documentation

The documentation site is a static GitHub Pages site. Its walkthrough is an interactive demonstration; it never receives a real setup code or database credentials. Use the website’s **Edit site content** page to export edited `content.json`, then commit it to `website/content.json` in GitHub to publish. Edit `website/` for layout changes, and edit `docs/` for the hosting guides. GitHub Actions tests the package and publishes the static site.

Run the dependency-free core checks and the Laravel integration suite:

```bash
php tests/run.php
composer install
vendor/bin/phpunit
```

The tests also cover real HTTP sessions, API access guards, MySQL credentials, existing database rejection, and completion locks through `tests/http-e2e.py` with a disposable database. See the repository's workflows for the current CI matrix. Host-panel settings remain manual and should be verified on the intended account before a customer release.

## Acknowledgements

The requested reference projects were [qbifyhq/laravel-installer-wizard](https://github.com/qbifyhq/laravel-installer-wizard) and [relayercore/laravel-installer](https://packagist.org/packages/relayercore/laravel-installer). This implementation uses an original standalone shared-hosting flow rather than copying their code. The uploaded `20i.zip` hosting notes informed the 20i pitfalls and customer guidance.

Official references: [Laravel deployment](https://laravel.com/docs/13.x/deployment), [20i document roots](https://docs.20i.com/web-hosting/change-document-root), [20i PHP configuration](https://docs.20i.com/web-hosting-site-management/php-configuration), [cPanel domain management](https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/), and [cPanel MultiPHP Manager](https://docs.cpanel.net/cpanel/software/multiphp-manager-for-cpanel/).

## License and author

MIT license. **Developed by Solomon Gbeta.**
