# Installing on cPanel shared hosting

cPanel is a control panel used by many hosting providers. Available PHP versions, limits, extensions, SSH access, and directory restrictions are set by your provider. The wizard checks your actual account.

## What you need

- A prepared Laravel website ZIP, including `vendor/` and compiled frontend assets.
- A private setup code that has not expired.
- A domain with HTTPS and a safe public-directory layout.
- A new, empty MySQL/MariaDB database with an assigned database user.

## 1. Set the PHP version

Open **Software → MultiPHP Manager**, select the domain, and choose the PHP version required by the website. Some hosts use CloudLinux **Select PHP Version** instead; follow that provider's instructions. The web PHP version and a terminal's `php` command can differ.

Use **Software → MultiPHP INI Editor** for supported PHP settings. If a required version or extension is missing, ask your provider to enable it. The installer cannot install server extensions.

## 2. Create the database

Open **Databases → Database Wizard** or **MySQL Database Wizard**, depending on the panel version. Create a database and user, then assign the user to the database with the required table-creation and migration privileges.

Use the complete names, including the account prefix. For example, the panel may turn `website` into `account_website`. Use the database hostname supplied by the host. Keep the password out of public support tickets and screenshots.

## 3. Upload the prepared release

Open **Files → File Manager**, enable viewing hidden files, upload the ZIP, and extract it into the intended application directory. Check for `vendor/autoload.php`, `bootstrap/app.php`, and `public/index.php`.

The application directory contains private files. Only its `public/` directory should be served to visitors.

## 4. Choose a supported document root

For a domain that has its own configurable document root, open **Domains → Manage**, set that root to the application's `public/` directory, and save. File movement is separate: changing the setting does not move your upload.

**The cPanel customer interface cannot change the Main Domain's document root, or the roots of domains sharing it.** Providers may also restrict roots to `public_html`. Use one of these supported options:

1. Use an addon domain or subdomain with its own suitable root.
2. Ask your provider to configure the main domain's root for Laravel.
3. Have your developer prepare a split layout with private application files outside `public_html`, only Laravel's public files in `public_html`, and correctly adjusted application paths. The developer can use the installer's explicit `--public` preparation option after creating that layout. Follow [the developer guide](integration.md#when-the-hosts-public-root-is-fixed); do not improvise the split layout after preparation.

Never serve the full project from `public_html` and rely only on a redirect into `public/`. Do not move the application's front controller into the project root.

## 5. Run the wizard

Enable a valid domain certificate using the provider's SSL/AutoSSL facilities. Open `https://your-domain.example/install.php`, enter the setup code, and choose **cPanel**.

Fix blocking checks, enter the website and database settings, test the connection, then review and install. Each progress update represents part of your project's migration sequence. Keep the tab open until completion.

## After completion

Open the site, verify sign-in and important pages, and remove `public/install.php`. Keep the private completion lock. Set up mail, storage links, scheduler tasks, and any other services from your developer's release instructions.

If the application needs cron and the provider enables **Advanced → Cron Jobs**, use the correct absolute PHP path and application path supplied by the provider. Persistent workers and WebSockets are not implied by having Cron Jobs.

## Troubleshooting

| Symptom | Likely next action |
|---|---|
| Domain root setting is missing | Ask the provider; Main Domain changes are not available in the regular UI |
| “Access denied” from MySQL | Assign the user to the database; verify complete prefixed names and the password |
| “Could not find driver” | Enable the PHP MySQL PDO driver for the domain |
| 404 on ordinary Laravel routes | Include the original `public/.htaccess` and check rewrite support with the provider |
| Permissions check fails | Fix owner-compatible write access, not blanket `777` |
| Migration request times out | Developer should inspect the failed migration and host limits before retrying |

Sources checked 14 October 2026: [cPanel domain management](https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/), [MultiPHP Manager](https://docs.cpanel.net/cpanel/software/multiphp-manager-for-cpanel/), and [MultiPHP INI Editor](https://docs.cpanel.net/cpanel/software/multiphp-ini-editor-for-cpanel/).
