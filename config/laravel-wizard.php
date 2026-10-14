<?php

// Read directly before Laravel boots. Use literals, never env(), closures or secrets here.
return [
    'brand' => 'Laravel Wizard',
    'support_url' => 'https://github.com/solomongs/laravel-wizard-installer/issues',
    'minimum_php' => null, // null detects the installed framework and Composer lock.
    'required_extensions' => [], // Add only extensions your own application needs.
    'require_https' => true,
    'protect_application' => true,
    'success_path' => '/', // A local path, e.g. /login. No external redirects.
    'after_install' => null, // Class implementing Contracts\AfterInstall; must be idempotent.
];
