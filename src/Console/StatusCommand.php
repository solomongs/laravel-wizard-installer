<?php

namespace SolomonGbeta\LaravelWizard\Console;

use Illuminate\Console\Command;
use SolomonGbeta\LaravelWizard\Paths;
use SolomonGbeta\LaravelWizard\StateStore;

final class StatusCommand extends Command
{
    protected $signature = 'wizard:status';
    protected $description = 'Show installation phase and step statuses without disclosing credentials';

    public function handle(): int
    {
        $store = new StateStore(new Paths(base_path(), public_path()));
        if (!is_file($store->paths->private.'/setup.json')) { $this->info('Not prepared.'); return self::SUCCESS; }
        $state = $store->read();
        $this->info($store->installed() ? 'Installed (setup locked).' : 'Phase: '.($state['phase'] ?? 'prepared'));
        $this->line($state['message'] ?? '');
        foreach ($state['steps'] ?? [] as $step) $this->line($step['status'].' · '.$step['label']);
        return ($state['phase'] ?? '') === 'failed' ? self::FAILURE : self::SUCCESS;
    }
}
