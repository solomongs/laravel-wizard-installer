<?php

require dirname(__DIR__).'/bootstrap.php';

use SolomonGbeta\LaravelWizard\ConfigValidator;
use SolomonGbeta\LaravelWizard\EnvWriter;
use SolomonGbeta\LaravelWizard\InstallerException;
use SolomonGbeta\LaravelWizard\Paths;
use SolomonGbeta\LaravelWizard\SetupManager;
use SolomonGbeta\LaravelWizard\StateStore;

$passed = 0;
function verify(bool $value, string $label): void {
    global $passed;
    if (!$value) throw new RuntimeException('FAILED: '.$label);
    $passed++;
    echo 'PASS '.$label."\n";
}
function rejects(callable $call, string $reason): void {
    try { $call(); } catch (InstallerException $e) { verify($e->reason === $reason, 'reject '.$reason); return; }
    throw new RuntimeException('Expected rejection: '.$reason);
}
$base = sys_get_temp_dir().'/wizard-unit-'.bin2hex(random_bytes(6));
foreach (['public','bootstrap/cache','storage/app','config'] as $dir) mkdir($base.'/'.$dir, 0755, true);
file_put_contents($base.'/artisan', '<?php');
file_put_contents($base.'/bootstrap/app.php', '<?php');
try {
    $paths = new Paths($base);
    verify(!Paths::within($base.'/application', $base.'/app'), 'directory boundaries');
    rejects(fn () => new Paths($base, dirname($base)), 'unsafe_layout');
    verify(EnvWriter::quote('pass"$word\\# 123') === '"pass\\"\\$word\\\\# 123"', 'dotenv special characters encoded');
    rejects(fn () => EnvWriter::quote("bad\nAPP_DEBUG=true"), 'invalid_value');
    $rendered = EnvWriter::render("APP_NAME=Old\n# kept comment\nDB_PASSWORD=old\nDB_PASSWORD=duplicate\nCUSTOM=keep\n", ['APP_NAME' => 'New Site', 'DB_PASSWORD' => 'A$# "z\\9', 'NEW_KEY' => 'new']);
    verify(substr_count($rendered, 'DB_PASSWORD=') === 1, 'duplicate keys removed');
    verify(str_contains($rendered, '# kept comment') && str_contains($rendered, 'CUSTOM=keep') && str_contains($rendered, 'NEW_KEY='), 'comments unknown keys and missing keys preserved');
    rejects(fn () => EnvWriter::render('', ['APP_KEY;BAD' => 'x']), 'invalid_value');
    $token = (new SetupManager())->prepare($paths, ['allow-http' => true]);
    $store = new StateStore($paths);
    verify(strlen($token) === 48 && !str_contains(file_get_contents($paths->private.'/setup.json'), $token), 'only setup token hash persisted');
    verify(is_file($base.'/public/install.php'), 'standalone entry generated');
    exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($base.'/public/install.php'), $output, $exit);
    verify($exit === 0, 'generated entry PHP syntax');
    $generation = $store->read('setup')['generation'];
    (new SetupManager())->prepare($paths);
    verify($generation !== $store->read('setup')['generation'], 'code rotation invalidates old sessions');
    $writer = new EnvWriter($paths);
    file_put_contents($base.'/.env', "APP_KEY=\nAPP_DEBUG=true\n");
    verify($writer->existingKey() === null, 'empty key does not consume following setting');
    file_put_contents($base.'/.env', 'APP_KEY=base64:already-established-key'."\n");
    verify($writer->existingKey() === 'base64:already-established-key', 'existing application encryption key preserved');
    file_put_contents($base.'/.env', 'export APP_KEY=base64:exported-existing-key'."\n");
    verify($writer->existingKey() === 'base64:exported-existing-key', 'exported encryption key preserved');
    $writer->save(['APP_NAME' => 'New']);
    verify(is_file($paths->private.'/environment.backup'), 'private env backup created');
    $store->locked(function () use ($store): void { rejects(fn () => $store->locked(fn () => true), 'busy'); });
    $generation = $store->read('setup')['generation'];
    file_put_contents($base.'/public/install.php', '<?php // project-owned installer');
    rejects(fn () => (new SetupManager())->prepare($paths), 'file_conflict');
    verify($generation === $store->read('setup')['generation'], 'failed preparation does not rotate existing code');
    $store->write(['installed_at' => gmdate('c')], 'installed');
    rejects(fn () => (new SetupManager())->prepare($paths), 'installed');
    $input = ['name'=>'My Website','url'=>'https://example.com','timezone'=>'Africa/Lagos','db_host'=>'db.example.com','db_port'=>'3306','db_database'=>'account_app','db_username'=>'account_user','db_password'=>'a$#"\\z','confirm_empty'=>true];
    verify(ConfigValidator::validate($input)['db_password'] === $input['db_password'], 'password whitespace and specials not changed');
    rejects(fn () => ConfigValidator::validate(array_replace($input, ['url'=>'https://name:secret@example.com'])), 'validation');
    rejects(fn () => ConfigValidator::validate(array_replace($input, ['db_host'=>'localhost;dbname=other'])), 'validation');
    rejects(fn () => ConfigValidator::validate(array_replace($input, ['confirm_empty'=>false])), 'confirmation');
    echo "{$passed} standalone checks passed.\n";
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    rmdir($base);
}
