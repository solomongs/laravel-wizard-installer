<?php

namespace SolomonGbeta\LaravelWizard;

class InstallerEngine
{
    private readonly StateStore $store;
    private readonly Doctor $doctor;

    public function __construct(private readonly Paths $paths, private readonly array $options, private readonly ?DatabaseProbe $probe = null, private readonly ?LaravelBridge $bridge = null)
    {
        $this->store = new StateStore($paths);
        $this->doctor = new Doctor($paths, $options);
    }

    public function status(): array
    {
        $state = $this->store->read();
        $steps = array_map(static fn (array $step): array => array_intersect_key($step, array_flip(['id','label','status'])), $state['steps'] ?? []);
        $done = count(array_filter($steps, static fn (array $step): bool => $step['status'] === 'done'));
        $total = count($steps);
        $complete = $this->store->installed();
        return [
            'authenticated' => true,
            'profile' => $state['profile'] ?? 'other',
            'phase' => $complete ? 'complete' : ($state['phase'] ?? 'prepared'),
            'checks' => $state['checks'] ?? [],
            'ready' => $state['ready'] ?? false,
            'progress' => ['done' => $done, 'total' => $total, 'percent' => $complete ? 100 : ($total ? (int) floor(100 * $done / $total) : 0)],
            'steps' => $steps,
            'message' => $state['message'] ?? 'Ready to check your hosting.',
            'success_url' => $complete ? ($state['website_url'] ?? '').($this->options['success_path'] ?? '/') : null,
        ];
    }

    public function profile(string $profile): array
    {
        if (!in_array($profile, ['20i','cpanel','other'], true)) throw new InstallerException('Choose Multi8 Hosting, cPanel or another shared host.', 'profile');
        return $this->store->locked(function () use ($profile): array {
            $this->ensureOpen();
            $state = $this->store->read();
            $state['profile'] = $profile;
            $this->store->write($state);
            return $this->status();
        });
    }

    public function check(array $server): array
    {
        return $this->store->locked(function () use ($server): array {
            $this->ensureOpen();
            $state = $this->store->read();
            $state['checks'] = $this->doctor->checks($server);
            $state['ready'] = $this->doctor->ready($state['checks']);
            $this->store->write($state);
            return $this->status();
        });
    }

    public function configure(array $input, array $server): array
    {
        return $this->store->locked(function () use ($input, $server): array {
            $this->ensureOpen();
            $state = $this->store->read();
            if (!in_array($state['phase'] ?? '', ['prepared','configured'], true)) throw new InstallerException('Database setup is already running. The saved connection cannot be changed.', 'phase', 409);
            $checks = $this->doctor->checks($server);
            if (!$this->doctor->ready($checks)) throw new InstallerException('Resolve the failed hosting checks before configuring the website.', 'requirements');
            $values = ConfigValidator::validate($input, !empty($this->options['require_https']));
            ($this->probe ?? new DatabaseProbe())->verifyEmpty($values);
            $writer = new EnvWriter($this->paths);
            $env = [
                'APP_NAME' => $values['name'], 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
                'APP_URL' => $values['url'], 'APP_TIMEZONE' => $values['timezone'],
                'DB_CONNECTION' => 'mysql', 'DB_HOST' => $values['db_host'], 'DB_PORT' => $values['db_port'],
                'DB_URL' => '', 'DATABASE_URL' => '', 'DB_SOCKET' => '',
                'DB_DATABASE' => $values['db_database'], 'DB_USERNAME' => $values['db_username'], 'DB_PASSWORD' => $values['db_password'],
                'SESSION_DRIVER' => 'file', 'CACHE_STORE' => 'file', 'CACHE_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'sync',
            ];
            // Never rotate the encryption key on an existing application.
            if (!$writer->existingKey()) $env['APP_KEY'] = 'base64:'.base64_encode(random_bytes(32));
            $writer->save($env);
            $setup = $this->store->read('setup');
            $state['target'] = ['signature' => DatabaseProbe::signature($values, $setup['generation']), 'salt' => $setup['generation']];
            $state['phase'] = 'configured';
            $state['checks'] = $checks;
            $state['ready'] = true;
            $state['website_url'] = $values['url'];
            $state['message'] = 'Database connection passed. Website settings saved. Ready to create the database tables.';
            $this->store->write($state);
            return $this->status();
        });
    }

    public function run(): array
    {
        return $this->store->locked(function (): array {
            $this->ensureOpen();
            $state = $this->store->read();
            if (!in_array($state['phase'] ?? '', ['configured','migrating','finalizing','failed'], true)) throw new InstallerException('Save and test your website settings before starting installation.', 'phase', 409);
            $bridge = $this->bridge ?? new LaravelBridge($this->paths);
            try {
                $bridge->assertTarget($state['target'] ?? []);
                if (!($state['steps'] ?? [])) {
                    $state['steps'] = $bridge->plan();
                    $state['phase'] = 'migrating';
                    $this->store->write($state);
                    return $this->status();
                }
                foreach ($state['steps'] as $index => $step) {
                    if ($step['status'] === 'done') continue;
                    if ($step['kind'] === 'migration' && in_array($step['status'], ['running','failed'], true)) {
                        if ($bridge->hasRun($step['id'])) {
                            $state['steps'][$index]['status'] = 'done';
                            $state['phase'] = 'migrating';
                            $state['message'] = 'Recovered the completed database step. Continue installation.';
                            $this->store->write($state);
                            return $this->status();
                        }
                        throw new InstallerException('A database step stopped before completion. Ask your developer to inspect and repair this migration. Setup will not repeat potentially partial SQL or erase tables.', 'migration_repair', 409);
                    }
                    $state['steps'][$index]['status'] = 'running';
                    $state['phase'] = $step['kind'] === 'finalize' ? 'finalizing' : 'migrating';
                    $state['message'] = 'Running '.$step['label'].'.';
                    $this->store->write($state);
                    if ($step['kind'] === 'migration') {
                        $executed = $bridge->migrate($step['file']);
                        if (!$executed) $state['steps'][$index]['label'] .= ' (skipped by project)';
                        if ($executed && !$bridge->hasRun($step['id'])) throw new InstallerException('The database step did not report completion. Ask your developer to inspect it.', 'migration_repair');
                    } else {
                        $bridge->finish($this->options);
                    }
                    $state['steps'][$index]['status'] = 'done';
                    $state['message'] = $step['label'].' completed.';
                    if ($step['kind'] === 'finalize') {
                        // Write the fail-closed marker BEFORE any success response or cleanup.
                        $this->store->write(['installed_at' => gmdate('c'), 'version' => '1.0.1'], 'installed');
                        $state['phase'] = 'complete';
                        $state['message'] = 'Your website is installed. Setup is permanently locked.';
                    }
                    $this->store->write($state);
                    return $this->status();
                }
                throw new InstallerException('No pending setup steps were found. Ask your developer to check the installation status.', 'state_corrupt');
            } catch (\Throwable $error) {
                // Do not put raw SQL, credentials, file paths or traces into browser responses or logs.
                $reference = bin2hex(random_bytes(4));
                $message = $error instanceof InstallerException ? $error->getMessage() : 'This setup step could not finish. Check your hosting PHP/error logs with your developer.';
                $state['phase'] = 'failed';
                foreach ($state['steps'] ?? [] as $i => $step) if ($step['status'] === 'running') $state['steps'][$i]['status'] = 'failed';
                $state['message'] = $message.' Support reference: '.$reference.'.';
                $state['last_error'] = ['reference' => $reference, 'class' => get_class($error), 'time' => gmdate('c')];
                $this->store->write($state);
                throw new InstallerException($state['message'], $error instanceof InstallerException ? $error->reason : 'installation_failed', $error instanceof InstallerException ? $error->httpStatus : 422);
            }
        });
    }

    private function ensureOpen(): void
    {
        if ($this->store->installed()) throw new InstallerException('Installation is complete. Setup is locked.', 'installed', 410);
    }
}
