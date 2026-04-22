<?php
require_once 'includes/config.php';
$stmt = $pdo->query("SELECT * FROM users WHERE user_type = 'admin' LIMIT 1");
$admin = $stmt->fetch(PDO::FETCH_ASSOC);
print_r($admin);
?>
