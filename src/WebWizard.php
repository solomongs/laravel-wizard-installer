<?php

namespace SolomonGbeta\LaravelWizard;

final class WebWizard
{
    public static function serve(string $base, string $public): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        header('Cache-Control: no-store, private');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow');
        $isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
        $nonce = base64_encode(random_bytes(18));
        header("Content-Security-Policy: default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'nonce-{$nonce}'; connect-src 'self'; img-src data:; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        try {
            $paths = new Paths($base, $public);
            $store = new StateStore($paths);
            if ($store->installed()) throw new InstallerException('Your website has already been installed. This setup page is permanently locked.', 'installed', 410);
            $setup = $store->read('setup');
            if (!$setup || !isset($setup['token_hash'], $setup['options'])) throw new InstallerException('This installer has not been prepared. Ask your developer for a complete installation package.', 'not_prepared', 503);
            if (($setup['expires_at'] ?? 0) < time()) throw new InstallerException('The setup window expired. Ask your developer to run wizard:prepare again to issue a new setup code. Saved progress is retained.', 'expired', 403);
            $options = $setup['options'];
            if (!empty($options['require_https']) && !Doctor::https($_SERVER)) throw new InstallerException('Open this installer using https:// after activating SSL in your hosting panel. Database passwords must be sent over a secure connection.', 'https_required', 403);
            $root = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
            if ($root && Paths::within($paths->private, $root)) throw new InstallerException('Your domain exposes private application files. Correct the public document root before opening setup.', 'unsafe_document_root', 403);
            self::session($paths);
            if (($_SESSION['generation'] ?? null) !== $setup['generation']) {
                $_SESSION = ['generation' => $setup['generation']];
                session_regenerate_id(true);
            }
            $_SESSION['csrf'] ??= bin2hex(random_bytes(24));
            $authenticated = ($_SESSION['authenticated_until'] ?? 0) > time();
            $engine = new InstallerEngine($paths, $options);
            if (!$isPost) {
                if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') throw new InstallerException('Use GET or POST for setup.', 'method', 405);
                $brand = $options['brand'];
                $csrf = $_SESSION['csrf'];
                header('Content-Type: text/html; charset=UTF-8');
                require dirname(__DIR__).'/resources/wizard.php';
                return;
            }
            if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 16384) throw new InstallerException('Setup request is too large.', 'request', 413);
            $raw = file_get_contents('php://input', false, null, 0, 16385);
            if (!is_string($raw) || strlen($raw) > 16384) throw new InstallerException('Setup request is too large.', 'request', 413);
            $input = json_decode($raw, true);
            if (!is_array($input)) throw new InstallerException('Send a valid setup request.', 'request', 400);
            if (!is_string($input['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $input['csrf'])) throw new InstallerException('This page expired. Reload the setup page and try again.', 'csrf', 419);
            $action = $input['action'] ?? '';
            if ($action === 'status' && !$authenticated) { self::json(['ok' => true, 'data' => ['authenticated' => false, 'phase' => 'prepared']]); return; }
            if ($action === 'login') {
                self::authenticate($store, $setup, $input);
                $_SESSION['authenticated_until'] = time() + 7200;
                session_regenerate_id(true);
                self::json(['ok' => true, 'data' => $engine->status()]);
                return;
            }
            if (!$authenticated) throw new InstallerException('Enter your private setup code to continue.', 'unauthorized', 401);
            if ($action === 'logout') {
                unset($_SESSION['authenticated_until']);
                session_regenerate_id(true);
                self::json(['ok' => true, 'data' => ['authenticated' => false, 'phase' => 'prepared']]);
                return;
            }
            $data = match ($action) {
                'status' => $engine->status(),
                'profile' => $engine->profile(is_string($input['profile'] ?? null) ? $input['profile'] : ''),
                'check' => $engine->check($_SERVER),
                'configure' => $engine->configure($input, $_SERVER),
                'run' => $engine->run(),
                default => throw new InstallerException('Unknown setup action.', 'action', 400),
            };
            self::json(['ok' => true, 'data' => $data]);
        } catch (InstallerException $error) {
            http_response_code($error->httpStatus);
            if ($isPost) {
                $payload = ['ok' => false, 'error' => $error->getMessage(), 'code' => $error->reason];
                if (isset($engine) && !empty($authenticated)) $payload['data'] = $engine->status();
                self::json($payload);
            } else {
                header('Content-Type: text/html; charset=UTF-8');
                $message = htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8');
                echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Laravel Wizard · Setup status</title><style nonce="'.$nonce.'">body{font:18px system-ui;background:#f3f5ed;color:#16251e;margin:0;padding:8vh 7vw}main{max-width:660px;margin:auto}h1{font-size:clamp(2rem,6vw,3.5rem)}p{line-height:1.7}small{color:#456353}</style><main><small>LARAVEL WIZARD</small><h1>Setup status</h1><p>'.$message.'</p><p>Developed by Solomon Gbeta</p></main></html>';
            }
        } catch (\Throwable) {
            http_response_code(500);
            if ($isPost) self::json(['ok' => false, 'error' => 'Setup could not start. Ask your developer to check private folder permissions and hosting PHP logs.', 'code' => 'internal']);
            else { header('Content-Type: text/plain; charset=UTF-8'); echo 'Setup could not start. Check hosting PHP logs and private folder permissions with your developer.'; }
        }
    }

    private static function session(Paths $paths): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) throw new InstallerException('A server auto-prepend script started a conflicting session. Ask your hosting provider to exclude install.php.', 'session_conflict');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.save_path', $paths->private.'/sessions');
        session_name('sgwizard_'.substr(hash('sha256', $paths->base), 0, 12));
        session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => Doctor::https($_SERVER), 'httponly' => true, 'samesite' => 'Strict']);
        if (!session_start()) throw new InstallerException('The hosting account cannot save setup sessions. Check the private setup sessions folder.', 'session');
    }

    private static function authenticate(StateStore $store, array $setup, array $input): void
    {
        $store->locked(function () use ($store, $setup, $input): void {
            $id = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
            $attempts = $store->read('attempts');
            $attempts = array_filter($attempts, static fn (array $value): bool => ($value['start'] ?? 0) > time() - 600);
            $attempt = $attempts[$id] ?? ['start' => time(), 'count' => 0];
            if ($attempt['count'] >= 10) throw new InstallerException('Too many incorrect setup codes. Wait ten minutes before trying again.', 'rate_limit', 429);
            $token = is_string($input['token'] ?? null) ? $input['token'] : '';
            if (!hash_equals($setup['token_hash'], hash('sha256', $token))) {
                $attempt['count']++;
                $attempts[$id] = $attempt;
                $store->write($attempts, 'attempts');
                throw new InstallerException('That setup code is incorrect. Use the private code supplied with your application package.', 'invalid_token', 401);
            }
            unset($attempts[$id]);
            $store->write($attempts, 'attempts');
        });
    }

    private static function json(array $payload): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
