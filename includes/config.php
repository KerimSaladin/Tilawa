<?php
function env($k, $d=null){ $v=$_ENV[$k]??$_SERVER[$k]??getenv($k); return ($v!==false&&$v!==null&&$v!=='') ? $v : $d; }

// Database
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '5432'));
define('DB_NAME', env('DB_NAME', 'tilawa'));
define('DB_USER', env('DB_USER', 'tilawa'));
define('DB_PASS', env('DB_PASS', 'tilawa123'));

// Site
$_proto   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https') ? 'https' : 'http';
$_host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('SITE_URL',  env('APP_URL', $_proto . '://' . $_host));
define('SITE_NAME', 'رتل معي');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 100 * 1024 * 1024);

// Storage (Supabase/S3 compatible)
define('R2_KEY',        env('R2_KEY', ''));
define('R2_SECRET',     env('R2_SECRET', ''));
define('R2_BUCKET',     env('R2_BUCKET', ''));
define('R2_ENDPOINT',   env('R2_ENDPOINT', ''));
define('R2_PUBLIC_URL', env('R2_PUBLIC_URL', ''));
define('USE_R2',        R2_KEY !== '' && R2_SECRET !== '' && R2_BUCKET !== '');

// Payment
define('CURRENCY',             'دج');
define('CURRENCY_SYMBOL',      'دج');
define('CURRENCY_AR',          'دج');
define('PLATFORM_FEE_PERCENT',  15);
define('PLAN_MONTHLY_PRICE',   2000);
define('PLAN_YEARLY_PRICE',   15000);
define('PLAN_LIFETIME_PRICE', 35000);
define('DEFAULT_WALLET_BALANCE', 5000);
define('TOP_UP_AMOUNTS', [2000, 5000, 10000, 20000]);

// FFmpeg
define('FFMPEG_PATH',  env('FFMPEG_PATH', ''));
define('FFPROBE_PATH', env('FFPROBE_PATH', ''));
define('ENABLE_FFMPEG_CONVERSION', false);

// Session
$_https = $_proto === 'https';
ini_set('session.cookie_secure',   $_https ? '1' : '0');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', $_https ? 'None' : 'Lax');
ini_set('session.use_only_cookies','1');

// Timezone
date_default_timezone_set('Africa/Algiers');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// DB connection
try {
    $ssl  = env('DB_SSL', 'false') === 'true' ? ';sslmode=require' : '';
    $dsn  = 'pgsql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . $ssl;
    $pdo  = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    error_log('DB connection failed: ' . $e->getMessage());
    $pdo = null;
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (!file_exists(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
