<?php
// ── Environment helpers ────────────────────────────────────────────────────────
function env(string $key, $default = null) {
    $v = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($v !== false && $v !== null && $v !== '') ? $v : $default;
}

// ── Database ───────────────────────────────────────────────────────────────────
define('DB_HOST',    env('DB_HOST',    'localhost'));
define('DB_NAME',    env('DB_NAME',    'tilawa_platform'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_CHARSET', 'utf8mb4');

// ── Site URL ───────────────────────────────────────────────────────────────────
// Use APP_URL env var in production; auto-detect on localhost
$_appUrl = env('APP_URL');
if (!$_appUrl) {
    $proto   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $appRoot = '';
    if (preg_match('#^(/[^/]+)/#', $script, $m)) $appRoot = $m[1];
    $_appUrl = $proto . '://' . $host . $appRoot;
}
define('SITE_URL',  rtrim($_appUrl, '/'));
define('SITE_NAME', 'رتل معي');

// ── File storage ───────────────────────────────────────────────────────────────
define('UPLOAD_DIR',     __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE',  100 * 1024 * 1024); // 100 MB

// Cloudflare R2 / S3-compatible storage
define('R2_KEY',        env('R2_KEY',        ''));
define('R2_SECRET',     env('R2_SECRET',     ''));
define('R2_BUCKET',     env('R2_BUCKET',     ''));
define('R2_ENDPOINT',   env('R2_ENDPOINT',   ''));
define('R2_PUBLIC_URL', env('R2_PUBLIC_URL', ''));
define('USE_R2',        R2_KEY !== '' && R2_SECRET !== '' && R2_BUCKET !== '');

// ── Payment ────────────────────────────────────────────────────────────────────
define('CURRENCY',            'دج');
define('CURRENCY_SYMBOL',     'دج');
define('PLATFORM_FEE_PERCENT', 15);
define('PLAN_MONTHLY_PRICE',   2000);
define('PLAN_YEARLY_PRICE',    15000);
define('PLAN_LIFETIME_PRICE',  35000);
define('DEFAULT_WALLET_BALANCE', 5000);
define('TOP_UP_AMOUNTS',       [2000, 5000, 10000, 20000]);

// ── FFmpeg ─────────────────────────────────────────────────────────────────────
define('FFMPEG_PATH',             env('FFMPEG_PATH', ''));
define('FFPROBE_PATH',            env('FFPROBE_PATH', ''));
define('ENABLE_FFMPEG_CONVERSION', false);

// ── Session ────────────────────────────────────────────────────────────────────
$is_https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
// Railway runs behind a proxy — trust X-Forwarded-Proto
if (!$is_https && (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) {
    $is_https = true;
}
ini_set('session.cookie_secure',   $is_https ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', $is_https ? 'None' : 'Lax');
ini_set('session.use_only_cookies', '1');

// ── Error reporting ────────────────────────────────────────────────────────────
// Off in production (API files set this explicitly anyway)
$is_dev = env('APP_ENV', 'production') === 'development';
error_reporting($is_dev ? E_ALL : 0);
ini_set('display_errors', $is_dev ? '1' : '0');

// ── Timezone ───────────────────────────────────────────────────────────────────
date_default_timezone_set('Africa/Algiers');

// ── Database connection ────────────────────────────────────────────────────────
try {
    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    $pdo = null;
    // Only surface DB errors in dev
    if ($is_dev) { die('DB connection failed: ' . $e->getMessage()); }
}

// ── Session start ──────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Local upload dir (temp buffer before R2) ──────────────────────────────────
if (!file_exists(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
