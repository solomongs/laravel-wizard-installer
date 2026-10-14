<?php

namespace SolomonGbeta\LaravelWizard;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;
use SolomonGbeta\LaravelWizard\Console\DoctorCommand;
use SolomonGbeta\LaravelWizard\Console\PrepareCommand;
use SolomonGbeta\LaravelWizard\Console\StatusCommand;
use SolomonGbeta\LaravelWizard\Http\RequireInstalled;

final class WizardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/laravel-wizard.php', 'laravel-wizard');
    }

    public function boot(): void
    {
        $this->publishes([dirname(__DIR__).'/config/laravel-wizard.php' => config_path('laravel-wizard.php')], 'laravel-wizard-config');
        if ($this->app->runningInConsole()) $this->commands([PrepareCommand::class, StatusCommand::class, DoctorCommand::class]);
        // Install guard before session/database-dependent middleware. It has no redirect when not prepared.
        $this->callAfterResolving(Kernel::class, static function ($kernel): void {
            $kernel->prependMiddleware(RequireInstalled::class);
        });
    }
}
