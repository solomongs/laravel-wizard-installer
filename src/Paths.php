<?php

namespace SolomonGbeta\LaravelWizard;

final class Paths
{
    public readonly string $base;
    public readonly string $public;
    public readonly string $private;

    public function __construct(string $base, ?string $public = null)
    {
        $real = realpath($base);
        if (!$real || !is_file($real.'/artisan') || !is_file($real.'/bootstrap/app.php')) {
            throw new InstallerException('Run preparation from your Laravel project folder, where artisan is located.', 'not_laravel');
        }
        $this->base = $real;
        $this->public = realpath($public ?? $real.'/public') ?: ($public ?? $real.'/public');
        $this->private = $real.'/storage/app/laravel-wizard';
        if (self::within($this->private, $this->public) || self::within($this->base, $this->public)) {
            throw new InstallerException('The public folder must be separate from the private Laravel project files.', 'unsafe_layout');
        }
    }

    public static function within(string $path, string $directory): bool
    {
        $p = str_replace('\\', '/', realpath($path) ?: $path);
        $d = rtrim(str_replace('\\', '/', realpath($directory) ?: $directory), '/');
        return $p === $d || str_starts_with($p, $d.'/');
    }

    public function clearConfigCache(): void
    {
        foreach (['config.php', 'routes.php', 'routes-v7.php'] as $file) {
            $path = $this->base.'/bootstrap/cache/'.$file;
            if (is_file($path) && !unlink($path)) {
                throw new InstallerException('Remove the old config/route cache files in bootstrap/cache using File Manager, then retry.', 'cache_write');
            }
        }
    }
}
