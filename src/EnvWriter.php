<?php

namespace SolomonGbeta\LaravelWizard;

final class EnvWriter
{
    public function __construct(private readonly Paths $paths) {}

    public static function quote(string $value): string
    {
        if (strpbrk($value, "\r\n\0") !== false) {
            throw new InstallerException('Use a single-line value in each setup field.', 'invalid_value');
        }
        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }

    public static function render(string $original, array $values): string
    {
        $lines = preg_split('/\r?\n/', rtrim($original, "\r\n")) ?: [];
        $seen = [];
        $result = [];
        foreach ($values as $key => $value) {
            if (!preg_match('/^[A-Z][A-Z0-9_]*$/D', $key) || !is_string($value)) {
                throw new InstallerException('Invalid environment setting.', 'invalid_value');
            }
            self::quote($value);
        }
        foreach ($lines as $line) {
            if (preg_match('/^\s*(?:export\s+)?([A-Z][A-Z0-9_]*)\s*=/', $line, $matches) && array_key_exists($matches[1], $values)) {
                $key = $matches[1];
                if (!isset($seen[$key])) $result[] = $key.'='.self::quote($values[$key]);
                $seen[$key] = true;
            } else {
                $result[] = $line;
            }
        }
        foreach ($values as $key => $value) {
            if (!isset($seen[$key])) $result[] = $key.'='.self::quote($value);
        }
        return implode("\n", $result)."\n";
    }

    public function save(array $values): void
    {
        $path = $this->paths->base.'/.env';
        $original = is_file($path) ? (string) file_get_contents($path) : '';
        $backup = $this->paths->private.'/environment.backup';
        if (!is_file($backup)) AtomicFile::write($backup, $original);
        AtomicFile::write($path, self::render($original, $values));
        $this->paths->clearConfigCache();
    }

    public function existingKey(): ?string
    {
        $path = $this->paths->base.'/.env';
        if (!is_file($path)) return null;
        if (!preg_match_all('/^[ \t]*(?:export[ \t]+)?APP_KEY[ \t]*=[ \t]*([^\r\n]*)/m', (string) file_get_contents($path), $m)) return null;
        if (count($m[1]) > 1) throw new InstallerException('The settings file contains more than one APP_KEY. Ask your developer to preserve the correct existing key and remove duplicates.', 'duplicate_key');
        $raw = trim($m[1][0]);
        if ($raw === '' || str_starts_with($raw, '#')) return null;
        $key = trim($raw, "\"'");
        return $key !== '' ? $key : null;
    }
}
