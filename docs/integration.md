# Integrating the installer into a Laravel project

This workflow is for the developer preparing a Laravel website for a customer. The customer runs the prepared website in the browser; build work stays on your development machine or CI runner.

## Inspect the project first

Read `composer.json`, `composer.lock`, the bootstrap and provider files, public entry point, migrations, frontend build scripts, and `.env.example`. Identify the project's real PHP/extension requirements and any app boot code that accesses the database before setup.

Choose a safe deployment layout and an empty database. An installer cannot make a WebSocket server, Redis dependency, daemon worker, missing PHP extension, or oversized migration compatible with a restrictive hosting account.

## Install through Composer

From the application root:

```bash
composer config repositories.solomongs-wizard vcs https://github.com/solomongs/laravel-wizard-installer.git
composer require solomongs/laravel-wizard-installer:^1.0
php artisan vendor:publish --tag=laravel-wizard-config
```

The VCS repository setting allows installation directly from GitHub; Packagist publication is not required. Keep the package in production dependencies. A release tag matching `^1.0` must exist in the package repository.

Laravel package discovery registers `SolomonGbeta\LaravelWizard\WizardServiceProvider`. If you disable discovery, add that provider to your application's provider list, using `config/app.php` on Laravel 10 or `bootstrap/providers.php` on the later application structure. Do not add a second registration if discovery is already active.

## Configure the wizard

The published file is `config/laravel-wizard.php`. This file is read directly by the standalone installer as well as by Laravel; return a plain PHP array. Do not use `env()`, `config()`, facades, service-container calls, or closures in this file.

An example configuration:

```php
<?php

return [
    'brand' => 'My Laravel Website',
    'support_url' => 'https://example.com/help',
    'required_extensions' => [],
    'minimum_php' => null,
    'require_https' => true,
    'protect_application' => true,
    'success_path' => '/',
    'after_install' => null,
];
```

Set `minimum_php` to a version string if the project has a higher runtime requirement. Add only extensions your project actually needs to `required_extensions`; baseline checks are built into the installer. Keep `success_path` as a same-site relative path. Use your actual support page or omit it.

`protect_application` controls the package's pre-install application protection. Do not turn this off for a public unfinished website without an alternative access boundary.

## Project-specific initial data

Set `after_install` to a class implementing `SolomonGbeta\LaravelWizard\Contracts\AfterInstall`. Its `run()` method receives the Laravel application after all migrations finish. This is the extension point for your project's production defaults or administrator provisioning.

The following complete example writes a single marker into Laravel's private local storage. It is safe to retry because it replaces the same file rather than creating duplicate records. Replace the marker with your application's idempotent setup after reviewing its schema and user model.

Create `app/Installation/MarkWebsiteReady.php`:

```php
<?php

namespace App\Installation;

use Illuminate\Contracts\Foundation\Application;
use SolomonGbeta\LaravelWizard\Contracts\AfterInstall;

final class MarkWebsiteReady implements AfterInstall
{
    public function run(Application $app): void
    {
        $app->make('filesystem')->disk('local')->put(
            'installation/ready.txt',
            'Website installation completed.' . PHP_EOL
        );
    }
}
```

Then set the configuration value:

```php
<?php

return [
    'brand' => 'My Laravel Website',
    'support_url' => 'https://example.com/help',
    'required_extensions' => [],
    'minimum_php' => null,
    'require_https' => true,
    'protect_application' => true,
    'success_path' => '/',
    'after_install' => App\Installation\MarkWebsiteReady::class,
];
```

For a real administrator flow, use the project's authorization rules and password hashing. Collect or generate credentials through your own secure provisioning mechanism; do not ship a universal default administrator password. If the hook invokes a seeder, keep it narrowly scoped and safe to retry. Do not run a development seeder or one that deletes application data.

## Prepare a clean release copy

Use an isolated release copy rather than packaging your active development directory. Exclude development databases, private credentials, user uploads, logs, tests that are not needed at runtime, `.git/`, and `node_modules/`.

Install production dependencies using the lock file:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
```

If the project uses a frontend build tool, run its documented build command in the release process and include the resulting public assets. The wizard has its own local assets and needs no frontend build on the hosting account.

Do not distribute production configuration caches made using development credentials. Remove those caches deliberately in the release copy before preparation. Keep `.env.example` as a nonsecret template, and inspect it for hardcoded credentials.

## Prepare the standalone entry point

Run from the release copy's Laravel root:

```bash
php vendor/solomongs/laravel-wizard-installer/bin/prepare.php --ttl=72 --brand="My Laravel Website"
```

This entry point does not need the normal Laravel application to boot. It is preferred when service providers or database-backed sessions would block setup. Preparation records the release root, creates the setup state, and writes `public/install.php`.

When the application boots, the Artisan equivalent is:

```bash
php artisan wizard:prepare --ttl=72 --brand="My Laravel Website"
```

The returned setup code is secret. Record it privately. The default lifetime starts immediately and expires after 72 hours; allowed windows are 1–168 hours. Prepare near handover; an unfinished release can be prepared again with a fresh code. Completed installations cannot be reopened by this command.

Use `--allow-http` only in local testing or staging with deliberate access controls. Do not use it in the customer production release.

## Package and hand over

### When the host's public root is fixed

For a cPanel main domain with fixed `public_html`, prepare a split layout on your development machine: a private sibling `laravel-app/` holding the application, and `public_html/` holding only its public files. Copy the contents of the original `public/` into that target and update the application's ordinary `index.php` bootstrap paths to the private sibling. Configure Laravel's public path for all normal entry points and filesystem links. Inspect the project's actual framework bootstrap instead of applying an index file from a different version.

After that layout is complete, prepare with the explicit public path:

```bash
php vendor/solomongs/laravel-wizard-installer/bin/prepare.php --public=/build/release/public_html --ttl=72 --brand="My Laravel Website"
```

The preparation entry point generates references for the selected public directory; it does not copy the website's public assets or rewrite its ordinary entry point. `/build/release/public_html` is the example local release folder, not a path to enter blindly on a customer's hosting account. Keep the same relative application/public directory layout when uploading. Test both the wizard and the ordinary website entry point in this layout.

### Release delivery

Include the Laravel application, `vendor/`, `composer.lock`, built public assets, `public/install.php`, and fresh private `storage/app/laravel-wizard/setup.json` and `state.json`. A package source ZIP from this repository is not enough. Exclude `installed.json`, `sessions/`, `attempts.json`, and `environment.backup` from a fresh customer release. Exclude the entire private wizard directory from Git.

Deliver a short customer guide containing:

1. Intended hosting product and minimum PHP version.
2. Where to extract the application and exactly which public root to select.
3. How to create an empty MySQL/MariaDB database and obtain its actual hostname.
4. The browser URL for setup.
5. The setup-code expiry and support contact.
6. Project-specific tasks after the wizard: mail, storage links, scheduler, and external credentials.

Send the code separately from the archive. Never commit customer preparation state or credentials. Test a disposable clone of the prepared release against the same framework version and a fresh database, then deliver the original clean archive. Do not package the test clone after it has written database credentials or completion state.

## Developer verification

Where an application terminal is available:

```bash
php artisan wizard:doctor
php artisan wizard:status
```

Doctor reports CLI environmental requirements and exits unsuccessfully when blocking checks fail; browser checks must still verify web PHP. Use `wizard:doctor --json` for structured results. Status reports installer state without exposing credentials and exits unsuccessfully when the recorded phase is failed. Test an unauthorized visit, wrong code, expired code, missing required extension, unwritable storage, connection failure, populated database rejection, migration failure, successful completion, and post-completion lock. Check the site's home page and project-specific flows after the installation.

After success, remove `public/install.php` while preserving the completion lock. Upgrade the website through its normal deployment process; do not reset the wizard.
