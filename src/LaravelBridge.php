<?php

namespace SolomonGbeta\LaravelWizard;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use SolomonGbeta\LaravelWizard\Contracts\AfterInstall;

class LaravelBridge
{
    private ?Application $application;
    private ?array $proof = null;

    public function __construct(private readonly Paths $paths, ?Application $application = null)
    {
        $this->application = $application;
    }

    public function boot(): Application
    {
        if ($this->application) return $this->application;
        require_once $this->paths->base.'/vendor/autoload.php';
        $app = require $this->paths->base.'/bootstrap/app.php';
        if (!$app instanceof Application) throw new InstallerException('Laravel did not start. Ask your developer to check bootstrap/app.php.', 'bootstrap');
        $app->usePublicPath($this->paths->public);
        $app->make(Kernel::class)->bootstrap();
        $this->application = $app;
        return $app;
    }

    public function assertTarget(array $proof): void
    {
        if (!isset($proof['signature'], $proof['salt'])) throw new InstallerException('The checked database identity is missing. Ask your developer to inspect setup state.', 'database_identity');
        // DatabaseManager resolves DB_URL and other application overrides here, before any DDL.
        $app = $this->boot();
        $this->assertConnection($app->make('db')->connection(), $proof);
        $migrator = $app->make('migrator');
        $this->assertConnection($migrator->resolveConnection($migrator->getConnection()), $proof);
        $this->assertConnection($migrator->getRepository()->getConnection(), $proof);
        $this->proof = $proof;
    }

    private function assertConnection($connection, array $proof): void
    {
        $config = $connection->getConfig();
        if (($config['driver'] ?? '') !== 'mysql' || !empty($config['unix_socket']) || !empty($config['read']) || !empty($config['write']) || !empty($config['direct']) || !is_string($config['host'] ?? null)) {
            throw new InstallerException('Your application overrides the checked database connection. Ask your developer to use a single MySQL connection matching setup before proceeding.', 'database_identity');
        }
        $values = ['db_host'=>$config['host'], 'db_port'=>(string) ($config['port'] ?? '3306'), 'db_database'=>(string) ($config['database'] ?? ''), 'db_username'=>(string) ($config['username'] ?? ''), 'db_password'=>(string) ($config['password'] ?? '')];
        if (!hash_equals($proof['signature'], DatabaseProbe::signature($values, $proof['salt']))) {
            throw new InstallerException('Laravel is using a different database from the empty database you checked. Ask your developer to remove database URL, socket or server environment overrides. No migrations were run.', 'database_identity');
        }
    }

    public function plan(): array
    {
        $app = $this->boot();
        $migrator = $app->make('migrator');
        $repository = $migrator->getRepository();
        if (!$repository->repositoryExists()) $repository->createRepository();
        $files = $migrator->getMigrationFiles(array_unique(array_merge([$app->databasePath('migrations')], $migrator->paths())));
        $ran = $repository->getRan();
        $steps = [];
        foreach ($files as $name => $file) {
            $steps[] = ['id' => $name, 'label' => 'Database: '.$name, 'file' => $file, 'status' => in_array($name, $ran, true) ? 'done' : 'pending', 'kind' => 'migration'];
        }
        $steps[] = ['id' => 'project_setup', 'label' => 'Project setup and installation lock', 'status' => 'pending', 'kind' => 'finalize'];
        return $steps;
    }

    public function hasRun(string $name): bool
    {
        $repository = $this->boot()->make('migrator')->getRepository();
        return $repository->repositoryExists() && in_array($name, $repository->getRan(), true);
    }

    public function migrate(string $file): bool
    {
        $migrator = $this->boot()->make('migrator');
        $skipped = false;
        $events = $this->boot()->make('events');
        // Supported framework events fire before the migration's SQL method.
        if ($this->proof) {
            $proof = $this->proof;
            $events->listen(\Illuminate\Database\Events\MigrationStarted::class, function ($event) use ($migrator, $proof): void {
                $this->assertConnection($migrator->resolveConnection($event->migration->getConnection()), $proof);
            });
        }
        $events->listen(\Illuminate\Database\Events\MigrationSkipped::class, function () use (&$skipped): void { $skipped = true; });
        $pending = $migrator->run([$file], ['step' => true]);
        return !$skipped && count($pending) > 0;
    }

    public function finish(array $options): void
    {
        $app = $this->boot();
        if ($options['after_install'] ?? null) {
            $hook = $app->make($options['after_install']);
            if (!$hook instanceof AfterInstall) throw new InstallerException('The configured project setup class must implement AfterInstall.', 'hook');
            $hook->run($app);
        }
        // Clear only config/routes/views; never flush the application's data cache.
        $this->paths->clearConfigCache();
        $exit = $app->make(Kernel::class)->call('view:clear');
        if ($exit !== 0) throw new InstallerException('The view cache could not be cleared. Check storage/framework/views permissions.', 'view_cache');
    }
}
