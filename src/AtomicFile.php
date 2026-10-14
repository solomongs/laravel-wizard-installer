<?php

namespace SolomonGbeta\LaravelWizard;

final class AtomicFile
{
    public static function write(string $path, string $contents, int $mode = 0600): void
    {
        $directory = dirname($path);
        if (is_link($path) || !is_dir($directory)) {
            throw new InstallerException('A private setup file cannot be written. Check folder permissions and symbolic links.', 'file_write');
        }
        $temporary = tempnam($directory, '.wizard-');
        if ($temporary === false) {
            throw new InstallerException('The hosting account cannot create setup files. Check disk space and folder permissions.', 'file_write');
        }
        try {
            if (!chmod($temporary, $mode) || file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents) || !rename($temporary, $path)) {
                throw new InstallerException('The hosting account cannot save setup files. Check disk space and folder ownership.', 'file_write');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    public static function json(string $path, array $data): void
    {
        self::write($path, json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
    }
}
