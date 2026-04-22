<?php
function env($k, $d=null){ $v=$_ENV[$k]??$_SERVER[$k]??getenv($k); return ($v!==false&&$v!==null&&$v!=='') ? $v : $d; }

$host = env('DB_HOST');
$port = env('DB_PORT', '5432');
$name = env('DB_NAME');
$user = env('DB_USER');
$pass = env('DB_PASS');

echo "Host: $host\n";
echo "Port: $port\n";
echo "Name: $name\n";
echo "User: $user\n";
echo "pdo_pgsql loaded: " . (extension_loaded('pdo_pgsql') ? 'YES' : 'NO') . "\n\n";

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$name;sslmode=require";
    $pdo = new PDO($dsn, $user, $pass);
    echo "Connected OK\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
