<?php

namespace SolomonGbeta\LaravelWizard\Console;

use Illuminate\Console\Command;
use SolomonGbeta\LaravelWizard\InstallerException;
use SolomonGbeta\LaravelWizard\Paths;
use SolomonGbeta\LaravelWizard\SetupManager;

final class PrepareCommand extends Command
{
    protected $signature = 'wizard:prepare {--ttl=72 : Setup window in hours (1–168)} {--brand= : Website name} {--public= : Explicit public folder for a split layout} {--allow-http : Only for local testing or securely restricted staging}';
    protected $description = 'Prepare an application for guided shared-hosting installation';

    public function handle(): int
    {
        try {
            $options = array_filter(['ttl' => $this->option('ttl'), 'brand' => $this->option('brand'), 'allow-http' => $this->option('allow-http')], static fn ($value): bool => $value !== null);
            $paths = new Paths(base_path(), $this->option('public') ?: public_path());
            $token = (new SetupManager())->prepare($paths, $options);
            $this->info('Installer prepared. Open https://your-domain/install.php after uploading the application.');
            $this->line('Private setup code (give only to the site owner): '.$token);
            $this->warn('Include vendor and compiled application assets. Exclude installed.json, sessions, attempts and environment.backup from distribution.');
            return self::SUCCESS;
        } catch (InstallerException $error) { $this->error($error->getMessage()); return self::FAILURE; }
    }
}
