<?php

namespace SolomonGbeta\LaravelWizard\Http;

use Closure;
use Illuminate\Http\Request;

final class RequireInstalled
{
    public function handle(Request $request, Closure $next): mixed
    {
        $private = storage_path('app/laravel-wizard');
        if (!is_file($private.'/setup.json') || is_file($private.'/installed.json')) return $next($request);
        $setup = json_decode((string) file_get_contents($private.'/setup.json'), true);
        if (!is_array($setup) || !is_array($setup['options'] ?? null)) {
            return response()->json(['message'=>'Installation status cannot be read. Ask the developer to repair the private setup file.'], 503);
        }
        if (($setup['options']['protect_application'] ?? true) === false) return $next($request);
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Website installation is not complete.'], 503)->header('Retry-After', '60');
        }
        return redirect(rtrim($request->getBaseUrl(), '/').'/install.php');
    }
}
