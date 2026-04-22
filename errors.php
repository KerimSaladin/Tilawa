<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/payment.php';

echo "<pre style='font-family:monospace;font-size:14px;padding:20px'>";
echo "=== DB Connection ===\n";
echo "PDO: " . ($pdo ? "OK" : "NULL - CONNECTION FAILED") . "\n\n";

if ($pdo) {
    echo "=== Tables in public schema ===\n";
    $tbls = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
    echo implode(', ', $tbls) . "\n\n";

    echo "=== users table columns ===\n";
    $cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='users' ORDER BY ordinal_position")->fetchAll();
    foreach ($cols as $c) echo $c['column_name'] . ' (' . $c['data_type'] . ")\n";
    echo "\n";

    echo "=== progress table columns ===\n";
    $cols = $pdo->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_schema='public' AND table_name='progress' ORDER BY ordinal_position")->fetchAll();
    foreach ($cols as $c) echo $c['column_name'] . ' (' . $c['data_type'] . ")\n";
    echo "\n";

    echo "=== User count ===\n";
    echo "users: " . $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn() . "\n";

    echo "\n=== Session ===\n";
    echo "session_id: " . session_id() . "\n";
    echo "user_id: " . ($_SESSION['user_id'] ?? 'not set') . "\n";

    echo "\n=== ENV check ===\n";
    foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','APP_URL','APP_ENV','R2_KEY','R2_BUCKET','R2_ENDPOINT'] as $k) {
        $v = $_ENV[$k] ?? $_SERVER[$k] ?? getenv($k);
        echo "$k: " . ($v ? substr($v, 0, 30) . (strlen($v) > 30 ? '...' : '') : 'NOT SET') . "\n";
    }

    echo "\n=== Load functions test ===\n";
    try {
        $u = get_logged_in_user($pdo);
        echo "get_logged_in_user: " . ($u ? "user #{$u['id']}" : "null (not logged in)") . "\n";
    } catch (Throwable $e) {
        echo "get_logged_in_user ERROR: " . $e->getMessage() . "\n";
    }
    try {
        $p = get_teacher_pricing($pdo, 1);
        echo "get_teacher_pricing: OK\n";
    } catch (Throwable $e) {
        echo "get_teacher_pricing ERROR: " . $e->getMessage() . "\n";
    }
}
echo "</pre>";
