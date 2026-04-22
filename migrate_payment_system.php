<?php
require_once 'includes/config.php';

echo "<h2>Payment System Database Migration</h2>";

// Teacher pricing columns
echo "<h3>Adding teacher pricing columns...</h3>";
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN session_price DECIMAL(10,2) DEFAULT NULL");
    echo "Added session_price column<br>";
} catch (Exception $e) {
    echo "session_price column already exists<br>";
}

try {
    $pdo->exec("ALTER TABLE users ADD COLUMN price_per_course DECIMAL(10,2) DEFAULT NULL");
    echo "Added price_per_course column<br>";
} catch (Exception $e) {
    echo "price_per_course column already exists<br>";
}

// Platform subscription for students
echo "<h3>Creating subscriptions table...</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS subscriptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            plan_name VARCHAR(100) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NOT NULL,
            status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created subscriptions table<br>";
} catch (Exception $e) {
    echo "Subscriptions table already exists<br>";
}

// Fake wallet/balance for all users
echo "<h3>Adding wallet balance column...</h3>";
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10,2) DEFAULT 100.00");
    echo "Added wallet_balance column<br>";
    
    // Set initial balance for existing users
    $pdo->exec("UPDATE users SET wallet_balance = 100.00 WHERE wallet_balance IS NULL");
    echo "Set initial wallet balance for existing users<br>";
} catch (Exception $e) {
    echo "wallet_balance column already exists<br>";
}

// All transactions log
echo "<h3>Creating transactions table...</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            from_user_id INT NOT NULL,
            to_user_id INT NULL,
            type ENUM('session_payment', 'course_payment', 'platform_subscription', 'teacher_earning', 'platform_fee', 'top_up') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            status ENUM('completed', 'pending', 'failed') DEFAULT 'completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (from_user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created transactions table<br>";
} catch (Exception $e) {
    echo "Transactions table already exists<br>";
}

// Student-Teacher enrollments
echo "<h3>Creating enrollments table...</h3>";
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS enrollments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            type ENUM('session', 'course') NOT NULL,
            sessions_paid INT DEFAULT 0,
            sessions_used INT DEFAULT 0,
            amount_paid DECIMAL(10,2) NOT NULL,
            discount_applied TINYINT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "Created enrollments table<br>";
} catch (Exception $e) {
    echo "Enrollments table already exists<br>";
}

echo "<h3>Migration completed successfully!</h3>";
echo "<a href='dashboard.php'>Go to Dashboard</a>";
?>
