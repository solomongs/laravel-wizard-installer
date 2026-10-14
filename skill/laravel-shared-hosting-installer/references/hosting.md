# Hosting profiles

Verify current official sources for platform-dependent settings. Read the `20i-hosting-ops` skill when the user names it or the task performs Multi8 Hosting platform operations. Use Multi8 Hosting as the customer-facing brand; retain upstream documentation URLs and the technical skill name.

## Multi8 Hosting / StackCP

Target standard Linux shared/reseller hosting. Current official platform documentation allows the primary domain/subdomain root to point at an existing directory within the package. Managed WordPress roots cannot be changed through that tool.

- PHP: choose the project's runtime through PHP Version; verify actual web PHP and modules.
- Settings: Manage Hosting → Options → Manage → Web Tools → PHP Configuration. Configuration propagation may take about five minutes.
- Database: create in MySQL Databases; use exact assigned hostname and user/database values rather than assuming localhost.
- Root: Manage Domains → Domains, or Domains → Subdomains → Modify Subdomains; choose the application's `public/` directory.
- Do not put mod_php `php_value`/`php_flag` settings in `.htaccess`; use panel/per-directory PHP configuration.
- Keep HTTPS redirect rules consistent with the panel and trusted proxy configuration. Do not trust arbitrary forwarded headers.
- Scheduled Tasks can run suitable short commands; do not assume shared hosting supports persistent workers, WebSockets, Horizon, Reverb, or Octane.

Sources checked October 2026:

- <https://docs.20i.com/web-hosting/change-document-root>
- <https://docs.20i.com/web-hosting-site-management/php-configuration>
- <https://docs.20i.com/stackcp/change-php-version>
- <https://docs.20i.com/web-hosting/php-modules-available-on-shared-hosting>

## cPanel

The provider sets account capabilities. Verify PHP/extension availability, database privileges, document-root controls, and limits rather than assuming all cPanel hosts are the same.

- PHP: Software → MultiPHP Manager or the provider's CloudLinux selector. Settings: MultiPHP INI Editor where enabled.
- Database: Database Wizard / MySQL Database Wizard; assign the created user to the database. Preserve account prefixes in both names.
- Root: Domains → Manage for supported domains. The normal customer interface cannot change the Main Domain root or roots sharing it. Providers can restrict other root choices as well.
- Safe choices: own-root addon/subdomain; provider-configured public root; or developer-prepared split layout using private app files outside public_html and the explicit preparation public path.
- Never upload the full application into a publicly served project root as a workaround.
- Cron Jobs availability does not imply support for always-on services.

Sources:

- <https://docs.cpanel.net/cpanel/domains/domains/manage-the-domain/>
- <https://docs.cpanel.net/cpanel/software/multiphp-manager-for-cpanel/>
- <https://docs.cpanel.net/cpanel/software/multiphp-ini-editor-for-cpanel/>

## Other compatible hosts

Require PHP meeting the application's lock file, required modules, MySQL/MariaDB, writable private runtime paths, safe public-root separation, HTTPS, and adequate per-request time/memory. Use provider-neutral tips and the host's own control-panel names. Nginx routing requires provider configuration; a Laravel `.htaccess` cannot configure Nginx.

Check web-server and ownership behavior without changing global services or using `chmod 777`. Keep configuration and diagnostics private.
