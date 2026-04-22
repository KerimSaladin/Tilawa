<?php
require_once 'includes/config.php';

// Create resources table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS resources (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            file_path VARCHAR(500) DEFAULT NULL,
            url VARCHAR(500) DEFAULT NULL,
            type ENUM('video', 'pdf', 'link') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_type (type),
            INDEX idx_created_at (created_at)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
    ");
    echo "Resources table created successfully\n";
} catch (PDOException $e) {
    echo "Error creating resources table: " . $e->getMessage() . "\n";
}

// Create ratings table (for Feature 3)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ratings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            teacher_id INT NOT NULL,
            rating TINYINT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            comment TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_student_teacher (student_id, teacher_id),
            INDEX idx_teacher_id (teacher_id),
            INDEX idx_rating (rating)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
    ");
    echo "Ratings table created successfully\n";
} catch (PDOException $e) {
    echo "Error creating ratings table: " . $e->getMessage() . "\n";
}

// Create group_sessions table (for Feature 4)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_sessions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            teacher_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            scheduled_at TIMESTAMP NOT NULL,
            duration_minutes INT NOT NULL DEFAULT 60,
            max_students INT NOT NULL DEFAULT 50,
            status ENUM('scheduled', 'live', 'ended') NOT NULL DEFAULT 'scheduled',
            session_url VARCHAR(500) DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX idx_teacher_id (teacher_id),
            INDEX idx_status (status),
            INDEX idx_scheduled_at (scheduled_at)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
    ");
    echo "Group sessions table created successfully\n";
} catch (PDOException $e) {
    echo "Error creating group_sessions table: " . $e->getMessage() . "\n";
}

// Create group_session_participants table (for Feature 4)
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS group_session_participants (
            id INT AUTO_INCREMENT PRIMARY KEY,
            session_id INT NOT NULL,
            student_id INT NOT NULL,
            joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (session_id) REFERENCES group_sessions(id) ON DELETE CASCADE,
            FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_session_student (session_id, student_id),
            INDEX idx_session_id (session_id),
            INDEX idx_student_id (student_id)
        ) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci
    ");
    echo "Group session participants table created successfully\n";
} catch (PDOException $e) {
    echo "Error creating group_session_participants table: " . $e->getMessage() . "\n";
}

echo "All feature tables created successfully!\n";
?>
