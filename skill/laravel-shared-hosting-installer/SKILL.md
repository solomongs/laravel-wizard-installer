---
name: laravel-shared-hosting-installer
description: Integrate Solomon Gbeta's Laravel Wizard Installer into an existing Laravel project, prepare a secure first-install release for 20i, cPanel, or compatible PHP shared hosting, and supply a plain-language customer guide. Use for adding a browser installation wizard, packaging Laravel for upload without customer SSH or Composer, diagnosing installer status, or preparing a fixed-public-root Laravel deployment. Preserve the project's chosen stack and starter kit.
---

# Laravel Shared Hosting Installer

Apply the reusable package from `https://github.com/solomongs/laravel-wizard-installer`. Treat the task as integration into an existing Laravel application, not installation of an unrelated starter kit. Attribute the installer to Solomon Gbeta in customer-facing package information.

## Inspect and choose a layout

1. Identify the project root and whether the target is a fresh installation or a live application. Read `composer.json`, `composer.lock`, bootstrap/provider files, `public/index.php`, `.env.example`, migrations, and the frontend build scripts. Avoid displaying secrets.
2. Run `scripts/inspect_laravel.py <absolute-project-root>` for a nonsecret structural report. Read its output and inspect any missing requirements directly.
3. Determine the hosting product, actual PHP version/extensions, public root controls, MySQL/MariaDB availability, and project service requirements. Infer from evidence; ask only for missing facts that change the deployment plan. Read [hosting profiles](references/hosting.md).
4. Prefer the intact application's `public/` as the document root. For fixed cPanel `public_html`, prepare an explicit split layout with a private application sibling; update the actual framework's ordinary entry point and public-path configuration before using `--public`. Never make the application root public and rely on a redirect.
5. Recognize unsupported requirements before packaging: missing PHP extensions, database engines outside MySQL/MariaDB, daemon workers, Reverb, Horizon, Octane, Redis dependencies, and individual migrations exceeding host limits. Adapt only within the authorized scope; do not promise support from the host brand alone.

## Integrate and prepare

Read [the integration contract](references/integration.md) before editing.

1. Add `solomongs/laravel-wizard-installer` as a production Composer dependency using its GitHub VCS repository and an available stable tag. Use the project's existing lock file and compatible dependency versions. Do not set `--ignore-platform-reqs` or replace the application's framework to make installation appear successful.
2. Publish `config/laravel-wizard.php`, or copy the package default if the application cannot yet boot. Keep the file as a plain PHP array without framework helper calls. Configure project branding, support, required extensions, higher PHP requirements, relative success path, and protection.
3. Add an `AfterInstall` class only if the project needs production defaults or initial administrator provisioning. Make it idempotent, use the project's roles and hashing, and keep credentials private. Do not invent a default administrator account or run destructive/development seeders.
4. Create an isolated clean release copy. Build production Composer dependencies and the application's actual frontend assets on the development machine/CI. Exclude development credentials, database files, user uploads, logs, `.git/`, and `node_modules/`; retain all dependencies and hidden runtime files needed on the host.
5. Prepare near handover with the standalone `bin/prepare.php` from the application's root. Include generated `public/install.php`, `composer.lock`, and fresh private preparation state in the customer archive. Exclude the whole `storage/app/laravel-wizard/` directory from Git. Exclude runtime sessions, attempts, backups, and installed markers from a first-install archive. Store the displayed setup code privately and deliver it separately. Default expiry is 72 hours from preparation.
6. Use `--allow-http` only for local testing or deliberately access-controlled staging. Keep HTTPS mandatory for a real installation.

## Verify and deliver

Test a disposable clone of the prepared release with the project's exact framework version and a fresh MySQL/MariaDB database, then deliver the original clean archive. Keep test credentials and completion state out of the customer release. Check unauthorized access, the setup code, requirement failures, database rejection, migration progress, the optional hook, normal website startup, and the completion lock. Test the split public layout separately if used. Avoid claiming a framework/host version was tested merely because Composer constraints allow it.

Give the customer the release archive and a short guide: selected PHP, extraction folder, exact public root, database setup, HTTPS, setup URL, expiry, status meanings, support, post-install mail/storage/scheduler tasks, and removal of `install.php`. Read [customer diagnostics](references/diagnostics.md) for troubleshooting. Preserve the private completion lock after success.

Do not run migrations against a populated live database as a “first install”, erase tables, rotate an existing `APP_KEY`, or delete the completion lock for updates. Use the project's ordinary upgrade and backup workflow. Continue authorized reversible integration work without redundant permission prompts; confirm only genuinely destructive changes not already authorized.

Report what changed, which checks actually passed, the package version, artifact/repository links, and material host limits. Do not claim the hosting panel, GitHub publication, or deployment was changed without a returned successful operation.
