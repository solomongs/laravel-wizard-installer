<?php

namespace SolomonGbeta\LaravelWizard\Tests\Feature;

use Orchestra\Testbench\TestCase;
use SolomonGbeta\LaravelWizard\Contracts\AfterInstall;
use SolomonGbeta\LaravelWizard\EnvWriter;
use SolomonGbeta\LaravelWizard\InstallerEngine;
use SolomonGbeta\LaravelWizard\InstallerException;
use SolomonGbeta\LaravelWizard\LaravelBridge;
use SolomonGbeta\LaravelWizard\Paths;
use SolomonGbeta\LaravelWizard\SetupManager;
use SolomonGbeta\LaravelWizard\StateStore;
use SolomonGbeta\LaravelWizard\WizardServiceProvider;

class PackageTest extends TestCase
{
    protected function getPackageProviders($app): array { return [WizardServiceProvider::class]; }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver'=>'sqlite','database'=>':memory:','prefix'=>'']);
    }

    public function test_provider_registers_commands(): void
    {
        $commands = $this->app->make(\Illuminate\Contracts\Console\Kernel::class)->all();
        $this->assertArrayHasKey('wizard:prepare', $commands);
        $this->assertArrayHasKey('wizard:doctor', $commands);
        $this->assertArrayHasKey('wizard:status', $commands);
    }

    public function test_dotenv_round_trips_special_passwords_and_adds_absent_keys(): void
    {
        $password = 'p a#ss$word"\\value';
        $encoded = EnvWriter::render("# keep\n", ['DB_PASSWORD'=>$password, 'APP_DEBUG'=>'false']);
        $parsed = \Dotenv\Dotenv::parse($encoded);
        $this->assertSame($password, $parsed['DB_PASSWORD']);
        $this->assertSame('false', $parsed['APP_DEBUG']);
    }

    public function test_real_migration_runs_then_completion_locks_installer(): void
    {
        $base = $this->temporaryProject();
        $paths = new Paths($base);
        try {
            (new SetupManager())->prepare($paths, ['allow-http'=>true]);
            $store = new StateStore($paths);
            $store->write(['phase'=>'configured','profile'=>'cpanel','steps'=>[], 'website_url'=>'https://example.com']);
            $migration = $base.'/database/migrations/2026_01_01_000001_create_wizard_records.php';
            $this->app->useDatabasePath($base.'/database');
            $bridge = new TrustedTestBridge($paths, $this->app);
            $options = require dirname(__DIR__, 2).'/config/laravel-wizard.php';
            $engine = new InstallerEngine($paths, $options, null, $bridge);
            $status = $engine->run();
            $this->assertSame('migrating', $status['phase']);
            $this->assertSame(2, $status['progress']['total']);
            $status = $engine->run();
            $this->assertTrue($this->app['db']->connection()->getSchemaBuilder()->hasTable('wizard_records'));
            $this->assertSame(50, $status['progress']['percent']);
            $status = $engine->run();
            $this->assertSame('complete', $status['phase']);
            $this->assertSame(100, $status['progress']['percent']);
            $this->assertTrue($store->installed());
            $this->assertSame('https://example.com/', $status['success_url']);
            try { $engine->run(); $this->fail('Completed installer reopened'); } catch (InstallerException $e) { $this->assertSame('installed', $e->reason); }
        } finally { $this->removeProject($base); }
    }

    public function test_interrupted_unrecorded_migration_is_not_repeated(): void
    {
        $base = $this->temporaryProject();
        $paths = new Paths($base);
        try {
            (new SetupManager())->prepare($paths);
            $this->app->useDatabasePath($base.'/database');
            $bridge = new TrustedTestBridge($paths, $this->app);
            $steps = $bridge->plan();
            $steps[0]['status'] = 'running';
            $store = new StateStore($paths);
            $store->write(['phase'=>'migrating','steps'=>$steps]);
            $engine = new InstallerEngine($paths, require dirname(__DIR__,2).'/config/laravel-wizard.php', null, $bridge);
            try { $engine->run(); $this->fail('Partial migration repeated'); } catch (InstallerException $e) { $this->assertSame('migration_repair', $e->reason); }
            $this->assertFalse($this->app['db']->connection()->getSchemaBuilder()->hasTable('wizard_records'));
            $this->assertSame('failed', $engine->status()['phase']);
            $this->assertFalse($store->installed());
        } finally { $this->removeProject($base); }
    }

    public function test_completed_interrupted_request_is_recovered_from_migration_repository(): void
    {
        $base = $this->temporaryProject();
        $paths = new Paths($base);
        try {
            (new SetupManager())->prepare($paths);
            $this->app->useDatabasePath($base.'/database');
            $bridge = new TrustedTestBridge($paths, $this->app);
            $steps = $bridge->plan();
            $bridge->migrate($steps[0]['file']);
            $steps[0]['status'] = 'running';
            (new StateStore($paths))->write(['phase'=>'migrating','steps'=>$steps]);
            $engine = new InstallerEngine($paths, require dirname(__DIR__,2).'/config/laravel-wizard.php', null, $bridge);
            $this->assertSame('done', $engine->run()['steps'][0]['status']);
        } finally { $this->removeProject($base); }
    }

    public function test_guard_returns_503_for_api_while_installing(): void
    {
        $directory = storage_path('app/laravel-wizard');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        file_put_contents($directory.'/setup.json', json_encode(['options'=>['protect_application'=>true]]));
        try {
            $request = \Illuminate\Http\Request::create('/api/test','GET');
            $response = (new \SolomonGbeta\LaravelWizard\Http\RequireInstalled())->handle($request, fn () => response('Unexpected success'));
            $this->assertSame(503, $response->getStatusCode());
        } finally { unlink($directory.'/setup.json'); }
    }

    public function test_effective_database_mismatch_is_rejected_before_repository_creation(): void
    {
        $base = $this->temporaryProject();
        try {
            $bridge = new LaravelBridge(new Paths($base), $this->app);
            try {
                $bridge->assertTarget(['signature'=>'checked-mysql-signature','salt'=>'random-proof-salt']);
                $this->fail('Mismatched database accepted');
            } catch (InstallerException $e) { $this->assertSame('database_identity', $e->reason); }
            $this->assertFalse($this->app['db']->connection()->getSchemaBuilder()->hasTable('migrations'));
        } finally { $this->removeProject($base); }
    }

    public function test_corrupt_setup_guard_fails_closed(): void
    {
        $directory = storage_path('app/laravel-wizard');
        if (!is_dir($directory)) mkdir($directory, 0700, true);
        file_put_contents($directory.'/setup.json', '{broken');
        try {
            $response = (new \SolomonGbeta\LaravelWizard\Http\RequireInstalled())->handle(\Illuminate\Http\Request::create('/'), fn () => response('Unexpected success'));
            $this->assertSame(503, $response->getStatusCode());
        } finally { unlink($directory.'/setup.json'); }
    }

    public function test_project_skipped_migration_can_complete_without_being_repeated(): void
    {
        if (!method_exists(\Illuminate\Database\Migrations\Migration::class, 'shouldRun')) $this->markTestSkipped('This framework does not support intentional migration skipping.');
        $base = $this->temporaryProject();
        $paths = new Paths($base);
        try {
            file_put_contents($base.'/database/migrations/2026_01_01_000001_create_wizard_records.php', <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
 public function shouldRun(): bool { return false; }
 public function up(): void { throw new \RuntimeException('A skipped migration must not run'); }
};
PHP);
            (new SetupManager())->prepare($paths);
            $this->app->useDatabasePath($base.'/database');
            (new StateStore($paths))->write(['phase'=>'configured','steps'=>[]]);
            $bridge = new TrustedTestBridge($paths, $this->app);
            $engine = new InstallerEngine($paths, require dirname(__DIR__,2).'/config/laravel-wizard.php', null, $bridge);
            $engine->run();
            $status = $engine->run();
            $this->assertSame('done', $status['steps'][0]['status']);
            $this->assertStringContainsString('skipped by project', $status['steps'][0]['label']);
            $this->assertSame('complete', $engine->run()['phase']);
        } finally { $this->removeProject($base); }
    }

    private function temporaryProject(): string
    {
        $base = sys_get_temp_dir().'/wizard-integration-'.bin2hex(random_bytes(6));
        foreach (['public','bootstrap/cache','storage/app','config','database/migrations'] as $dir) mkdir($base.'/'.$dir, 0755, true);
        file_put_contents($base.'/artisan','<?php');
        file_put_contents($base.'/bootstrap/app.php','<?php');
        file_put_contents($base.'/database/migrations/2026_01_01_000001_create_wizard_records.php', <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
 public function up(): void { \Illuminate\Support\Facades\Schema::create('wizard_records', fn (\Illuminate\Database\Schema\Blueprint $t) => $t->id()); }
 public function down(): void { \Illuminate\Support\Facades\Schema::dropIfExists('wizard_records'); }
};
PHP);
        return $base;
    }

    private function removeProject(string $base): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($base);
    }
}

// Only SQLite migration integration uses this adapter; HTTP tests exercise real MySQL identity checks.
class TrustedTestBridge extends LaravelBridge
{
    public function assertTarget(array $proof): void {}
}
