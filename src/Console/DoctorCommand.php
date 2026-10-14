<?php

namespace SolomonGbeta\LaravelWizard\Console;

use Illuminate\Console\Command;
use SolomonGbeta\LaravelWizard\Doctor;
use SolomonGbeta\LaravelWizard\Paths;

final class DoctorCommand extends Command
{
    protected $signature = 'wizard:doctor {--json : Print machine-readable results}';
    protected $description = 'Check CLI PHP, project files and permissions (verify web PHP in the wizard too)';

    public function handle(): int
    {
        $doctor = new Doctor(new Paths(base_path(), public_path()), config('laravel-wizard', []));
        $checks = $doctor->checks();
        if ($this->option('json')) $this->line(json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        else foreach ($checks as $check) $this->line(strtoupper($check['status']).' · '.$check['label'].' · '.$check['detail']);
        return $doctor->ready($checks) ? self::SUCCESS : self::FAILURE;
    }
}
