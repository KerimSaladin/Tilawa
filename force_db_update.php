<?php
require 'includes/config.php';

// Fix messages table completely
$pdo->exec("DROP TABLE IF EXISTS messages");
$pdo->exec("
CREATE TABLE IF NOT EXISTS `messages` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `sender_id`    INT NOT NULL,
    `receiver_id`  INT NOT NULL,
    `message_text` TEXT NOT NULL,
    `is_read`      TINYINT(1) DEFAULT 0,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sender_id`)   REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`receiver_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_receiver` (`receiver_id`),
    INDEX `idx_is_read` (`is_read`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");
echo "Messages table recreated.\n";

// Add gender to groups table safely
try {
    $pdo->exec("ALTER TABLE `groups` ADD COLUMN gender ENUM('male','female','mixed') DEFAULT 'mixed'");
    echo "Added gender column to groups.\n";
} catch(Exception $e) {
    echo "Groups gender column may already exist.\n";
}

// Add columns safely to users
$cols = [
    'session_price' => 'DECIMAL(10,2) DEFAULT NULL',
    'price_per_course' => 'DECIMAL(10,2) DEFAULT NULL',
    'wallet_balance' => 'DECIMAL(10,2) DEFAULT 5000',
    'is_online' => 'TINYINT DEFAULT 0',
    'has_star' => 'TINYINT DEFAULT 0',
    'last_seen' => 'TIMESTAMP NULL DEFAULT NULL'
];
foreach ($cols as $col => $def) {
    try {
        $pdo->exec("ALTER TABLE users ADD COLUMN $col $def");
        echo "Added $col to users.\n";
    } catch(Exception $e) {}
}

echo "Live database updated successfully.\n";
