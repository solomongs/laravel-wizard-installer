<?php

namespace SolomonGbeta\LaravelWizard\Contracts;

use Illuminate\Contracts\Foundation\Application;

interface AfterInstall
{
    /** Provision app-specific settings/admin roles. A retry can call this again: make it idempotent. */
    public function run(Application $app): void;
}
