<?php
/**
 * migrate_live_sessions.php
 * Run once to fix the live_sessions table for slot-based booking
 */
require_once 'includes/config.php';

echo "<pre style='font-family:monospace;padding:1rem'>\n";

try {
    // Add 'available' and 'cancelled' to status enum
    $pdo->exec("ALTER TABLE live_sessions MODIFY COLUMN status ENUM('available','scheduled','active','completed','cancelled') DEFAULT 'available'");
    echo "✅ live_sessions.status enum updated\n";
} catch(PDOException $e) {
    echo "⏭ status enum: " . $e->getMessage() . "\n";
}

try {
    // Fix student_id to allow 0 (unbooked slot)
    $pdo->exec("ALTER TABLE live_sessions MODIFY COLUMN student_id INT NOT NULL DEFAULT 0");
    echo "✅ live_sessions.student_id default 0\n";
} catch(PDOException $e) {
    echo "⏭ student_id: " . $e->getMessage() . "\n";
}

try {
    // Remove foreign key constraint on student_id so 0 is allowed
    // First find the constraint name
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_NAME='live_sessions' AND TABLE_SCHEMA=DATABASE() AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'");
    $fk = $stmt->fetch();
    if ($fk) {
        $pdo->exec("ALTER TABLE live_sessions DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
        echo "✅ Removed FK on student_id\n";
    }
} catch(PDOException $e) {
    echo "⏭ FK removal: " . $e->getMessage() . "\n";
}

try {
    // Rename start_time columns if they exist under old names
    $stmt = $pdo->query("SHOW COLUMNS FROM live_sessions LIKE 'started_at'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE live_sessions CHANGE started_at start_time TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Renamed started_at → start_time\n";
    }
} catch(PDOException $e) {
    echo "⏭ rename started_at: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM live_sessions LIKE 'ended_at'");
    if ($stmt->rowCount() > 0) {
        $pdo->exec("ALTER TABLE live_sessions CHANGE ended_at end_time TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Renamed ended_at → end_time\n";
    }
} catch(PDOException $e) {
    echo "⏭ rename ended_at: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM live_sessions LIKE 'start_time'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE live_sessions ADD COLUMN start_time TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Added start_time column\n";
    }
} catch(PDOException $e) {
    echo "⏭ add start_time: " . $e->getMessage() . "\n";
}

try {
    $stmt = $pdo->query("SHOW COLUMNS FROM live_sessions LIKE 'end_time'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE live_sessions ADD COLUMN end_time TIMESTAMP NULL DEFAULT NULL");
        echo "✅ Added end_time column\n";
    }
} catch(PDOException $e) {
    echo "⏭ add end_time: " . $e->getMessage() . "\n";
}

try {
    // Add 'available' status and gender to groups table
    $stmt = $pdo->query("SHOW COLUMNS FROM `groups` LIKE 'gender'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE `groups` ADD COLUMN gender ENUM('male','female','mixed') DEFAULT 'mixed'");
        echo "✅ Added groups.gender column\n";
    } else {
        echo "⏭ groups.gender already exists\n";
    }
} catch(PDOException $e) {
    echo "⏭ groups.gender: " . $e->getMessage() . "\n";
}

try {
    // Add ratings table if missing
    $pdo->exec("CREATE TABLE IF NOT EXISTS ratings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        teacher_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_student_teacher (student_id, teacher_id),
        INDEX idx_teacher (teacher_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ ratings table OK\n";
} catch(PDOException $e) {
    echo "⏭ ratings: " . $e->getMessage() . "\n";
}

try {
    // Ensure group_sessions has all needed columns
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        teacher_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        description TEXT DEFAULT NULL,
        scheduled_at TIMESTAMP NOT NULL,
        duration_minutes INT DEFAULT 60,
        max_students INT DEFAULT 50,
        status ENUM('scheduled','live','ended') DEFAULT 'scheduled',
        session_url VARCHAR(500) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_teacher (teacher_id),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ group_sessions table OK\n";
} catch(PDOException $e) {
    echo "⏭ group_sessions: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_session_participants (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        student_id INT NOT NULL,
        joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_session_student (session_id, student_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "✅ group_session_participants table OK\n";
} catch(PDOException $e) {
    echo "⏭ group_session_participants: " . $e->getMessage() . "\n";
}


try {
    // Allow NULL student_id for available slots (drop FK constraint)
    $stmt = $pdo->query("
        SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_NAME='live_sessions' AND TABLE_SCHEMA=DATABASE()
        AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'
    ");
    $fk = $stmt->fetch();
    if ($fk) {
        $pdo->exec("ALTER TABLE live_sessions DROP FOREIGN KEY {$fk['CONSTRAINT_NAME']}");
        echo "✅ Removed student_id FK (allows NULL for available slots)\n";
    } else {
        echo "⏭ student_id FK already removed\n";
    }
} catch(PDOException $e) {
    echo "⏭ FK: " . $e->getMessage() . "\n";
}

try {
    $pdo->exec("ALTER TABLE live_sessions MODIFY COLUMN student_id INT DEFAULT NULL");
    echo "✅ live_sessions.student_id allows NULL\n";
} catch(PDOException $e) {
    echo "⏭ student_id nullable: " . $e->getMessage() . "\n";
}

// Verify final structure
echo "\n--- التحقق النهائي ---\n";
$cols = $pdo->query("SHOW COLUMNS FROM live_sessions")->fetchAll();
foreach ($cols as $col) {
    echo "  live_sessions." . $col['Field'] . " [" . $col['Type'] . "] default=" . ($col['Default']??'NULL') . "\n";
}

echo "\n✅ الترحيل اكتمل — يمكنك حذف هذا الملف\n</pre>";
