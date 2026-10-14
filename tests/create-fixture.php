<?php

require dirname(__DIR__).'/vendor/autoload.php';

use SolomonGbeta\LaravelWizard\Paths;
use SolomonGbeta\LaravelWizard\SetupManager;

$base = $argv[1] ?? '';
if (!$base || file_exists($base)) { fwrite(STDERR, "Supply a new temporary fixture path.\n"); exit(1); }
foreach (['public','bootstrap/cache','storage/app','storage/framework/cache/data','storage/framework/sessions','storage/framework/views','storage/logs','config','database/migrations','routes','resources/views'] as $dir) mkdir($base.'/'.$dir, 0755, true);
$vendor = dirname(__DIR__).'/vendor';
symlink($vendor, $base.'/vendor');
copy(dirname(__DIR__).'/composer.lock', $base.'/composer.lock');
file_put_contents($base.'/artisan', '<?php');
file_put_contents($base.'/.env', "APP_KEY=\nAPP_DEBUG=true\nAPP_ENV=local\nDB_URL=mysql://unused.invalid/production\nDATABASE_URL=mysql://unused.invalid/production\nDB_SOCKET=/invalid.sock\n");
file_put_contents($base.'/bootstrap/app.php', <<<'PHP'
<?php
$app = new \Illuminate\Foundation\Application(dirname(__DIR__));
$app->singleton(\Illuminate\Contracts\Console\Kernel::class, \Illuminate\Foundation\Console\Kernel::class);
$app->singleton(\Illuminate\Contracts\Http\Kernel::class, \Illuminate\Foundation\Http\Kernel::class);
$app->singleton(\Illuminate\Contracts\Debug\ExceptionHandler::class, \Illuminate\Foundation\Exceptions\Handler::class);
return $app;
PHP);
file_put_contents($base.'/config/app.php', <<<'PHP'
<?php
return [
 'name'=>env('APP_NAME','Fixture'), 'env'=>env('APP_ENV','production'), 'debug'=>env('APP_DEBUG',false),
 'url'=>env('APP_URL','http://localhost'), 'timezone'=>env('APP_TIMEZONE','UTC'), 'locale'=>'en',
 'fallback_locale'=>'en', 'key'=>env('APP_KEY'), 'cipher'=>'AES-256-CBC',
 'providers'=>\Illuminate\Support\ServiceProvider::defaultProviders()->merge([\SolomonGbeta\LaravelWizard\WizardServiceProvider::class])->toArray(),
];
PHP);
file_put_contents($base.'/config/view.php', <<<'PHP'
<?php
return ['paths'=>[resource_path('views')], 'compiled'=>storage_path('framework/views')];
PHP);
file_put_contents($base.'/config/session.php', <<<'PHP'
<?php
return ['driver'=>env('SESSION_DRIVER','file'),'files'=>storage_path('framework/sessions'),'lifetime'=>120,'cookie'=>'wizard_fixture','path'=>'/','http_only'=>true,'same_site'=>'lax','lottery'=>[2,100]];
PHP);
file_put_contents($base.'/config/database.php', <<<'PHP'
<?php
return [ 'default'=>'mysql', 'connections'=>['mysql'=>[
 'driver'=>'mysql', 'url'=>env('DB_URL'), 'unix_socket'=>env('DB_SOCKET',''), 'host'=>env('DB_HOST'),'port'=>env('DB_PORT'), 'database'=>env('DB_DATABASE'),
 'username'=>env('DB_USERNAME'), 'password'=>env('DB_PASSWORD'),'charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'', 'strict'=>true,
]], 'migrations'=>version_compare(\Illuminate\Foundation\Application::VERSION, '11.0', '>=') ? ['table'=>'migrations','update_date_on_publish'=>true] : 'migrations' ];
PHP);
file_put_contents($base.'/database/migrations/2026_01_01_000001_create_wizard_test_table.php', <<<'PHP'
<?php
return new class extends \Illuminate\Database\Migrations\Migration {
 public function up(): void { \Illuminate\Support\Facades\Schema::create('wizard_test_records', function (\Illuminate\Database\Schema\Blueprint $t): void { $t->id(); $t->string('label'); }); }
 public function down(): void { \Illuminate\Support\Facades\Schema::dropIfExists('wizard_test_records'); }
};
PHP);
file_put_contents($base.'/public/index.php', <<<'PHP'
<?php
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$app['router']->get('/', fn () => response('fixture running'));
$app['router']->get('/api/probe', fn () => response()->json(['running'=>true]));
$request = \Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
PHP);
$token = (new SetupManager())->prepare(new Paths($base), ['allow-http'=>true, 'brand'=>'Wizard Test Website']);
echo json_encode(['token'=>$token], JSON_THROW_ON_ERROR);
