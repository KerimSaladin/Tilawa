<?php
require_once 'includes/config.php';

// Add is_online column to users table
try {
    $stmt = $pdo->query("SELECT is_online FROM users LIMIT 1");
    echo "is_online column already exists\n";
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_online TINYINT DEFAULT 0 AFTER last_seen");
    echo "Added is_online column\n";
}

// Add last_seen column to users table  
try {
    $stmt = $pdo->query("SELECT last_seen FROM users LIMIT 1");
    echo "last_seen column already exists\n";
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_seen TIMESTAMP NULL DEFAULT NULL AFTER is_online");
    echo "Added last_seen column\n";
}

// Add has_star column to users table (for Feature 7)
try {
    $stmt = $pdo->query("SELECT has_star FROM users LIMIT 1");
    echo "has_star column already exists\n";
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE users ADD COLUMN has_star TINYINT DEFAULT 0 AFTER payment_method_token");
    echo "Added has_star column\n";
}

echo "Database migration completed successfully!\n";
?>
