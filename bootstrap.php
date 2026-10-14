<?php

// Deliberately avoid vendor/autoload.php until the user has configured the site.
// This keeps the checks usable when Composer's PHP requirements are not yet met.
spl_autoload_register(static function (string $class): void {
    $prefix = 'SolomonGbeta\\LaravelWizard\\';
    if (str_starts_with($class, $prefix)) {
        $path = __DIR__.'/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
        if (is_file($path)) require_once $path;
    }
});
