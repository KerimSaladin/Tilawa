<?php
require 'includes/config.php';
$stmt = $pdo->query("SHOW COLUMNS FROM users");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "COLUMNS:\n";
print_r($cols);

$stmt = $pdo->prepare("UPDATE users SET session_price=300 WHERE id=1");
$res = $stmt->execute();
echo "\nUpdate res: " . ($res ? "true" : "false");

$stmt = $pdo->query("SELECT session_price FROM users WHERE id=1");
print_r($stmt->fetch());
