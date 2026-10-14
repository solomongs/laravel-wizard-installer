<?php

namespace SolomonGbeta\LaravelWizard;

final class Doctor
{
    public function __construct(private readonly Paths $paths, private readonly array $options) {}

    public function checks(array $server = []): array
    {
        $checks = [];
        $add = static function (string $id, string $label, string $status, string $detail, string $help) use (&$checks): void {
            $checks[] = compact('id', 'label', 'status', 'detail', 'help');
        };
        $min = $this->minimumPhp();
        $add('php', 'PHP version', version_compare(PHP_VERSION, $min, '>=') ? 'pass' : 'fail', 'Current '.PHP_VERSION.'; required '.$min.' or newer.', 'Choose this PHP version in Multi8 Hosting Change PHP Version or cPanel MultiPHP Manager. Your project dependencies may need a newer version.');
        $vendor = is_file($this->paths->base.'/vendor/autoload.php');
        $add('vendor', 'Application dependencies', $vendor ? 'pass' : 'fail', $vendor ? 'The vendor folder is present.' : 'The vendor folder is missing.', 'Ask your developer for a prepared application ZIP including vendor. A GitHub source ZIP alone is not ready to install.');
        $framework = $this->frameworkVersion();
        $add('framework', 'Laravel framework', $framework && preg_match('/^(?:v)?(10|11|12|13)\./', $framework) ? 'pass' : 'fail', $framework ? 'Detected '.$framework.'.' : 'Laravel framework was not found in composer.lock.', 'Install Composer dependencies and include composer.lock. This release targets Laravel 10 through 13.');
        $extensions = array_unique(array_merge(['ctype','curl','dom','fileinfo','filter','hash','mbstring','openssl','pcre','pdo','pdo_mysql','session','tokenizer','xml'], $this->options['required_extensions'] ?? [], $this->composerExtensions()));
        foreach ($extensions as $extension) {
            $loaded = extension_loaded($extension);
            $add('ext_'.$extension, 'PHP extension: '.$extension, $loaded ? 'pass' : 'fail', $loaded ? 'Available.' : 'Missing or disabled.', 'Enable this extension in the hosting PHP settings or ask your hosting provider.');
        }
        foreach (['storage', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'bootstrap/cache', 'storage/app/laravel-wizard'] as $directory) {
            $path = $this->paths->base.'/'.$directory;
            $writable = is_dir($path) && is_writable($path);
            $add('write_'.str_replace('/', '_', $directory), 'Writable: '.$directory, $writable ? 'pass' : 'fail', $writable ? 'The web account can write here.' : 'Missing or not writable.', 'Use File Manager to check ownership and permissions. Usually 755 directories and 644 files work; private setup files use 600. Ask your host if ownership is wrong. Never use 777.');
        }
        $env = $this->paths->base.'/.env';
        $add('environment', 'Website settings file', (!is_link($env) && (is_file($env) ? is_writable($env) : is_writable($this->paths->base))) ? 'pass' : 'fail', '.env must be writable and outside the public folder.', 'Grant your hosting web account write access to .env during setup. Keep this file private.');
        if ($server) {
            $root = realpath((string) ($server['DOCUMENT_ROOT'] ?? ''));
            $safe = $root && Paths::within($this->paths->public, $root) && !Paths::within($this->paths->base, $root) && !Paths::within($this->paths->private, $root);
            $add('document_root', 'Public website folder', $safe ? 'pass' : 'fail', $safe ? 'The domain serves only the public folder.' : 'The domain is not pointed at the correct public folder.', 'Multi8 Hosting: Manage Domains > Domains > Document Root. cPanel: Domains > Manage for an editable domain. For a fixed main domain use a developer-prepared split-public layout. Do not expose the entire Laravel folder.');
            $https = self::https($server);
            $add('https', 'Secure HTTPS connection', $https ? 'pass' : (!empty($this->options['require_https']) ? 'fail' : 'warn'), $https ? 'Your setup connection is encrypted.' : 'The connection is HTTP.', 'Enable SSL in your hosting panel and open the https:// address. A reverse proxy may need the host to pass the HTTPS flag correctly.');
        }
        $cache = is_file($this->paths->base.'/bootstrap/cache/config.php');
        $add('config_cache', 'Fresh settings', $cache ? 'fail' : 'pass', $cache ? 'An old configuration cache is present.' : 'No stale configuration cache.', 'Remove bootstrap/cache/config.php using File Manager before setup. Prepare normally removes this file.');
        $add('execution_time', 'Time available per setup request', (int) ini_get('max_execution_time') === 0 || (int) ini_get('max_execution_time') >= 30 ? 'pass' : 'warn', ((int) ini_get('max_execution_time') ?: 'Unlimited').' seconds.', 'Each migration runs in a separate request. A single large migration may still need a longer PHP limit from your host.');
        return $checks;
    }

    public function ready(array $checks): bool
    {
        foreach ($checks as $check) if ($check['status'] === 'fail') return false;
        return true;
    }

    public function frameworkVersion(): ?string
    {
        foreach ($this->packages() as $package) if (($package['name'] ?? '') === 'laravel/framework') return $package['version'] ?? null;
        return null;
    }

    public function minimumPhp(): string
    {
        $version = $this->frameworkVersion() ?? '';
        $min = preg_match('/^v?13\./', $version) ? '8.3.0' : (preg_match('/^v?(11|12)\./', $version) ? '8.2.0' : '8.1.0');
        if (is_string($this->options['minimum_php'] ?? null) && version_compare($this->options['minimum_php'], $min, '>')) $min = $this->options['minimum_php'];
        // Conservative lower bound from all runtime dependencies; Composer still verifies full constraints at bootstrap.
        foreach (array_merge($this->packages(), [$this->projectManifest()]) as $package) {
            $constraint = $package['require']['php'] ?? '';
            if (preg_match('/^(?:\^|~|>=)?\s*(8\.\d+(?:\.\d+)?)/', $constraint, $m) && version_compare($m[1], $min, '>')) $min = $m[1];
        }
        return $min;
    }

    private function projectManifest(): array
    {
        $file = $this->paths->base.'/composer.json';
        return is_file($file) ? (json_decode((string) file_get_contents($file), true) ?: []) : [];
    }

    private function composerExtensions(): array
    {
        $extensions = [];
        foreach (array_merge($this->packages(), [$this->projectManifest()]) as $package) {
            foreach (array_keys($package['require'] ?? []) as $name) {
                if (str_starts_with($name, 'ext-')) $extensions[] = substr($name, 4);
            }
        }
        return $extensions;
    }

    private function packages(): array
    {
        $lock = $this->paths->base.'/composer.lock';
        if (!is_file($lock)) return [];
        return (json_decode((string) file_get_contents($lock), true)['packages'] ?? []);
    }

    public static function https(array $server): bool
    {
        return (!empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off') || (int) ($server['SERVER_PORT'] ?? 0) === 443;
    }
}
