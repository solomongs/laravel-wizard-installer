# Package integration contract

Repository: <https://github.com/solomongs/laravel-wizard-installer>

Package: `solomongs/laravel-wizard-installer`; namespace: `SolomonGbeta\LaravelWizard`; developer: Solomon Gbeta.

Installer code supports PHP 8.1+; the application determines the actual PHP requirement. Integration targets Laravel 10–13. Current framework minima: Laravel 10 8.1, Laravel 11/12 8.2, Laravel 13 8.3. Check `composer.lock` and official documentation for later changes and extra extension requirements.

## Install and configure

Run from the Laravel application root:

```bash
composer config repositories.solomongs-wizard vcs https://github.com/solomongs/laravel-wizard-installer.git
composer require solomongs/laravel-wizard-installer:^1.0
php artisan vendor:publish --tag=laravel-wizard-config
```

Use an existing stable tag. If a new tag is not yet present, inspect repository tags before choosing a development constraint; do not silently claim a stable release exists. Package discovery registers `SolomonGbeta\LaravelWizard\WizardServiceProvider`. For projects explicitly disabling discovery, register it using the project's existing provider mechanism.

The config file `config/laravel-wizard.php` returns a plain array and is consumed directly by the standalone runtime. Do not use `env()`, `config()`, facades, closures, or runtime framework calls in it.

Supported keys:

| Key | Purpose |
|---|---|
| `brand` | Site/product name shown by the wizard |
| `support_url` | Actual HTTPS help/support URL, when available |
| `required_extensions` | Additional PHP extension names |
| `minimum_php` | Optional higher required PHP version string |
| `require_https` | Mandatory real-install transport; default true |
| `protect_application` | Keep the unfinished ordinary application protected |
| `success_path` | Relative same-site destination, normally `/` |
| `after_install` | Optional application class name |

Optional hook contract:

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

Set `after_install` to `App\Installation\MarkWebsiteReady::class` for this example. It is intentionally an idempotent private marker. Implement the project's actual defaults/admin flow rather than treating this marker as account creation. The hook runs after all migrations and must tolerate finalization retries.

## Prepare a release

Build production dependencies and the project's compiled assets in a clean release copy:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
php vendor/solomongs/laravel-wizard-installer/bin/prepare.php --ttl=72 --brand="My Laravel Website"
```

Standalone preparation works without booting normal Laravel routes. Prefer it if existing service providers/database sessions need an unavailable database. When the app boots, the equivalent is:

```bash
php artisan wizard:prepare --ttl=72 --brand="My Laravel Website"
php artisan wizard:doctor
php artisan wizard:status
```

`--ttl=72` means 72 hours from preparation; supported windows are 1–168 hours. Preparation refuses completed installations and can rotate unfinished preparation. `--allow-http` is only for local or access-controlled staging tests.

For fixed public roots, create a portable split layout first: private `laravel-app/` and sibling `public_html/`. Copy only original public contents into the public target; adapt the normal `index.php` bootstrap paths and Laravel public path using the actual framework version. Then use:

```bash
php vendor/solomongs/laravel-wizard-installer/bin/prepare.php --public=/build/release/public_html --ttl=72 --brand="My Laravel Website"
```

The absolute example path is local to release preparation; preserve relative sibling layout when uploading. This option prepares the installer entry point; it does not move assets or rewrite the normal application entry point for you.

Customer archive: complete Laravel project, production `vendor/`, `composer.lock`, compiled public assets, generated installer entry point, and fresh private `storage/app/laravel-wizard/setup.json` and `state.json`. Exclude dev/live credentials and data; exclude `installed.json`, `sessions/`, `attempts.json`, and `environment.backup`. Exclude the whole private wizard directory from Git. Test a disposable archive clone and deliver the original clean release, not the clone after installation. Transmit the setup code separately and keep the archive private. Preserve an existing application key; generate only when absent.

The customer opens `https://domain.example/install.php`; selects a hosting profile; fixes requirements; configures website and database; reviews; runs sequential migrations; completes and removes the public installer. No shell execution is needed on the customer host. The source package itself is not a ready website ZIP.
