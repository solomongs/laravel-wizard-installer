<?php

namespace SolomonGbeta\LaravelWizard;

final class StateStore
{
    public function __construct(public readonly Paths $paths) {}

    public function read(string $name = 'state'): array
    {
        $path = $this->paths->private.'/'.$name.'.json';
        if (!is_file($path)) return [];
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new InstallerException('Setup status is damaged. Ask your developer to check the private setup folder.', 'state_corrupt');
        return $data;
    }

    public function write(array $state, string $name = 'state'): void
    {
        AtomicFile::json($this->paths->private.'/'.$name.'.json', $state);
    }

    public function installed(): bool
    {
        // Existence fails closed, even if a marker is damaged.
        return is_file($this->paths->private.'/installed.json');
    }

    public function locked(callable $callback): mixed
    {
        $handle = fopen($this->paths->private.'/operation.lock', 'c');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) fclose($handle);
            throw new InstallerException('Another setup request is running. Wait for its status before trying again.', 'busy', 409);
        }
        chmod($this->paths->private.'/operation.lock', 0600);
        try { return $callback(); }
        finally { flock($handle, LOCK_UN); fclose($handle); }
    }
}
