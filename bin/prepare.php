#!/usr/bin/env php
<?php

// Can run before Laravel boots, e.g. when application providers need a database.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (PHP_VERSION_ID < 80100) { fwrite(STDERR, "Use PHP 8.1 or newer to prepare the installer.\n"); exit(1); }
require dirname(__DIR__).'/bootstrap.php';
$options = getopt('', ['ttl::','brand::','public::','allow-http']);
try {
    $paths = new \SolomonGbeta\LaravelWizard\Paths(getcwd(), $options['public'] ?? null);
    $config = $paths->base.'/config/laravel-wizard.php';
    if (!is_file($config)) copy(dirname(__DIR__).'/config/laravel-wizard.php', $config);
    // getopt returns false for a flag without a value.
    if (array_key_exists('allow-http', $options)) $options['allow-http'] = true;
    $token = (new \SolomonGbeta\LaravelWizard\SetupManager())->prepare($paths, $options);
    fwrite(STDOUT, "Installer prepared. Open https://your-domain/install.php after uploading.\nPrivate setup code (give only to the owner): {$token}\n");
    fwrite(STDOUT, "Include vendor, composer.lock and compiled assets. Exclude installed.json, sessions, attempts and environment.backup.\nDeveloped by Solomon Gbeta.\n");
} catch (\Throwable $error) {
    fwrite(STDERR, $error instanceof \SolomonGbeta\LaravelWizard\InstallerException ? $error->getMessage()."\n" : "Preparation failed. Check project folders and permissions.\n");
    exit(1);
}
