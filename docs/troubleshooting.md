# Installation status and troubleshooting

## Understand the status

| Status | Meaning | Next step |
|---|---|---|
| Ready / passed | This check met its requirement | Continue |
| Needs attention / failed | A required condition has not been met | Follow the check's tip, then check again |
| Warning | A condition deserves review | Read the explanation and your project's guide |
| Installing | A migration or final setup step is running | Keep the tab open |
| Complete / locked | Setup finished and the installer is disabled | Open the site and remove `install.php` |

The installer reports conditions it can observe. It cannot prove DNS propagation, browser-visible privacy of every file, SMTP delivery, or the suitability of an application's external services. Check those using the project's release guide.

## The wizard will not open

Confirm that you uploaded a prepared **website** release, not just this package's source ZIP. Ensure `public/install.php` exists, the domain points at that public directory, and the web PHP version meets the project's requirement.

A missing `vendor/autoload.php` means dependencies were omitted. Ask the developer for the complete release; do not rename folders or download random dependency ZIPs.

A `403` often means the selected folder is empty or has no index file. A `404` for `install.php` often means the wrong public folder or domain root was selected.

## The setup code expired or was rejected

Check that you copied the code without spaces and are using the correct website release. The expiry starts on the developer's preparation machine. The developer can rotate preparation for an unfinished installation and issue a fresh code.

Do not publish the code in an issue. Do not remove a completion lock to make an installed website accept a new code.

## The database test failed

Check the actual hosting-panel hostname, port, complete database name, and username. On cPanel, account prefixes are part of the names. On 20i, the hostname may not be `localhost`.

Confirm the database user is assigned to the database with the privileges required for migrations. Enter special characters in the password exactly as they appear; the installer handles environment quoting.

If tables already exist, the wizard should refuse an unrelated database. Create a new empty database for the website. The tool never deletes those tables for you.

## A migration failed or timed out

Stop and record the displayed migration/status information without secrets. Ask the developer to inspect the private Laravel and installer logs and compare the database's recorded migrations with its tables.

MySQL schema changes are not always transactional. A process can time out after creating a table but before recording success. An automatic retry can then fail with “table already exists”. Do not repeatedly retry or drop tables blindly.

After the developer confirms it is safe, resume the same prepared installation. The intended progress flow skips migrations Laravel has already recorded. An interrupted or partially applied migration may need a targeted correction before resuming.

Use `wizard:status` and `wizard:doctor` from the application root if terminal access is available:

```bash
php artisan wizard:status
php artisan wizard:doctor
```

These are developer tools. Browser installation itself does not require terminal access.

## Website error after completion

Keep `APP_DEBUG=false`. Use private logs rather than enabling public error details. Confirm that the application's actual requirements are present: mail provider, storage paths, upload links, scheduled tasks, third-party credentials, and any project-specific service.

The wizard is a fresh-install workflow. It does not replace the application's deployment, monitoring, or update process.

## What to send your developer

Send the hosting product, domain, selected PHP version, chosen public root, the failed check name, and a screenshot with credentials hidden. Send only relevant redacted log lines.

Do not send `.env`, the setup code, database passwords, private keys, or full backup archives through public issue trackers.
