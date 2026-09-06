<?php
/**
 * Global app bootstrap. Include this at the top of every entry-point page
 * (require_once __DIR__ . '/../config/app.php';)
 */

// --- Error visibility (turn display_errors OFF before deploying live) ---
error_reporting(E_ALL);
ini_set('display_errors', '1');

// --- Session (secure cookie params) ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// --- Base paths / URLs ---
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', '/yvolution'); // adjust to your local folder name under htdocs
define(
    'APP_URL',
    rtrim(
        getenv('YVOLUTION_APP_URL') ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE_URL),
        '/'
    )
);

// --- Timezone ---
date_default_timezone_set('Asia/Manila');

// --- Simple request rate limiter (protect against aggressive bursts) ---
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX_REQUESTS', 200);
define('RATE_LIMIT_WINDOW_SECONDS', 60);

function enforce_rate_limit(string $scope = 'global', int $maxRequests = 200, int $windowSeconds = 60): void
{
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';

    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    $storageDir = BASE_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate_limits';
    if (!is_dir($storageDir) && !mkdir($storageDir, 0777, true) && !is_dir($storageDir)) {
        return;
    }

    $key = hash('sha256', $ip . '|' . $scope);
    $filePath = $storageDir . DIRECTORY_SEPARATOR . $key . '.json';
    $now = time();

    $handle = fopen($filePath, 'c+');
    if ($handle === false) {
        return;
    }

    flock($handle, LOCK_EX);

    $data = ['requests' => []];
    clearstatcache(true, $filePath);
    if (filesize($filePath) > 0) {
        if (fseek($handle, 0) === 0) {
            $contents = stream_get_contents($handle);
            if ($contents !== false) {
                $decoded = json_decode($contents, true);
                if (is_array($decoded) && isset($decoded['requests']) && is_array($decoded['requests'])) {
                    $data = $decoded;
                }
            }
        }
    }

    $data['requests'] = array_values(array_filter(
        $data['requests'] ?? [],
        static function ($timestamp) use ($now, $windowSeconds): bool {
            return is_int($timestamp) && $timestamp > $now - $windowSeconds;
        }
    ));

    if (count($data['requests']) >= $maxRequests) {
        http_response_code(429);
        header('Retry-After: ' . $windowSeconds);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Too many requests. Please try again shortly.';
        flock($handle, LOCK_UN);
        fclose($handle);
        exit;
    }

    $data['requests'][] = $now;
    rewind($handle);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($data));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
}

// --- Core includes always available ---
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';

if (RATE_LIMIT_ENABLED) {
    $requestPath = strtolower($_SERVER['REQUEST_URI'] ?? '/');
    $isAuthRequest = preg_match('#^/(auth/(login|register)|public/contact_submit\.php)#i', $requestPath) === 1;
    enforce_rate_limit($isAuthRequest ? 'auth' : 'global', $isAuthRequest ? 20 : RATE_LIMIT_MAX_REQUESTS, RATE_LIMIT_WINDOW_SECONDS);
}

require_once BASE_PATH . '/includes/auth_guard.php';
