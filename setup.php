<?php
/**
 * setup.php — إعداد قاعدة بيانات منصة رتل معي
 * يمكن تشغيله في أي وقت — آمن للتكرار (لا يحذف بيانات موجودة)
 * بعد الإعداد احذف هذا الملف من السيرفر
 */

// ── الحماية ──────────────────────────────────────────────────────────────────
define('SETUP_TOKEN', 'tilawa_setup_2024');
if (!isset($_GET['token']) || $_GET['token'] !== SETUP_TOKEN) {
    http_response_code(403);
    die('<!DOCTYPE html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"><title>403</title>
    <style>body{font-family:Cairo,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;background:#fdf9f0;}
    .box{text-align:center;padding:3rem;background:white;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,.1);}
    h2{color:#dc2626;font-size:1.5rem;}</style></head>
    <body><div class="box"><h2>⛔ غير مصرح</h2>
    <p>أضف <code>?token=tilawa_setup_2024</code> إلى الرابط</p></div></body></html>');
}

// ── الاتصال بقاعدة البيانات ───────────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'tilawa_platform');
define('DB_CHARSET', 'utf8mb4');

$log   = [];   // سجل العمليات
$errors = 0;

function step(string $msg, string $type = 'ok'): void {
    global $log;
    $icon = match($type) { 'ok' => '✅', 'skip' => '⏭', 'err' => '❌', 'info' => 'ℹ️', default => '•' };
    $log[] = ['icon' => $icon, 'msg' => $msg, 'type' => $type];
}

// أنشئ قاعدة البيانات إن لم تكن موجودة
try {
    $dsn_root = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn_root, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    step("الاتصال بـ MySQL وإنشاء قاعدة البيانات `" . DB_NAME . "`");
} catch (PDOException $e) {
    die('<h2 style="color:red;font-family:sans-serif;text-align:center;padding:2rem">❌ فشل الاتصال: ' . htmlspecialchars($e->getMessage()) . '</h2>');
}

// ── دوال مساعدة ─────────────────────────────────────────────────────────────
function colExists(PDO $pdo, string $table, string $col): bool {
    $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
    return $stmt->rowCount() > 0;
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    return $stmt->rowCount() > 0;
}

function addCol(PDO $pdo, string $table, string $col, string $def): void {
    if (!tableExists($pdo, $table)) return;
    if (colExists($pdo, $table, $col)) {
        step("العمود `$col` موجود في `$table`", 'skip');
        return;
    }
    try {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$col` $def");
        step("أضيف العمود `$col` إلى `$table`");
    } catch (Throwable $e) {
        global $errors;
        $errors++;
        step("خطأ إضافة `$col` إلى `$table`: " . $e->getMessage(), 'err');
    }
}

function createTable(PDO $pdo, string $table, string $sql): void {
    if (tableExists($pdo, $table)) {
        step("الجدول `$table` موجود مسبقاً", 'skip');
        return;
    }
    try {
        $pdo->exec($sql);
        step("أُنشئ الجدول `$table`");
    } catch (Throwable $e) {
        global $errors;
        $errors++;
        step("خطأ إنشاء `$table`: " . $e->getMessage(), 'err');
    }
}

// ── إنشاء الجداول (بالترتيب — الآباء قبل الأبناء) ───────────────────────────

// 1. users
createTable($pdo, 'users', "
CREATE TABLE IF NOT EXISTS `users` (
    `id`                    INT AUTO_INCREMENT PRIMARY KEY,
    `email`                 VARCHAR(255) NOT NULL UNIQUE,
    `password_hash`         VARCHAR(255) NOT NULL,
    `full_name`             VARCHAR(255) NOT NULL,
    `user_type`             ENUM('student','teacher','admin') NOT NULL DEFAULT 'student',
    `teacher_status`        ENUM('pending','approved','rejected') DEFAULT NULL,
    `exam_required`         TINYINT(1) DEFAULT 0,
    `exam_passed`           TINYINT(1) DEFAULT 0,
    `gender`                ENUM('male','female') DEFAULT NULL,
    `phone`                 VARCHAR(50) DEFAULT NULL,
    `quran_certificate`     VARCHAR(500) DEFAULT NULL,
    `trial_end_date`        DATE DEFAULT NULL,
    `is_active`             TINYINT(1) DEFAULT 1,
    `is_online`             TINYINT(1) DEFAULT 0,
    `last_seen`             TIMESTAMP NULL DEFAULT NULL,
    `has_star`              TINYINT(1) DEFAULT 0,
    `wallet_balance`        DECIMAL(10,2) DEFAULT 5000.00,
    `session_price`         DECIMAL(10,2) DEFAULT NULL,
    `price_per_course`      DECIMAL(10,2) DEFAULT NULL,
    `payment_method_token`  VARCHAR(255) DEFAULT NULL,
    `availability_start`    TIME DEFAULT NULL,
    `availability_end`      TIME DEFAULT NULL,
    `availability_days`     VARCHAR(100) DEFAULT NULL,
    `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_email`         (`email`),
    INDEX `idx_user_type`     (`user_type`),
    INDEX `idx_teacher_status`(`teacher_status`),
    INDEX `idx_gender`        (`gender`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 2. sessions (رموز الجلسات)
createTable($pdo, 'sessions', "
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT NOT NULL,
    `session_token` VARCHAR(255) NOT NULL UNIQUE,
    `expires_at`    TIMESTAMP NOT NULL,
    `created_at`    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_token`   (`session_token`),
    INDEX `idx_user`    (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 3. subscriptions (خطط الاشتراك — الكتالوج)
createTable($pdo, 'subscriptions', "
CREATE TABLE IF NOT EXISTS `subscriptions` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `name`         VARCHAR(100) NOT NULL,
    `name_ar`      VARCHAR(100) NOT NULL,
    `price`        DECIMAL(10,2) NOT NULL,
    `duration_days` INT NOT NULL,
    `features`     TEXT DEFAULT NULL,
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 4. user_subscriptions
createTable($pdo, 'user_subscriptions', "
CREATE TABLE IF NOT EXISTS `user_subscriptions` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT NOT NULL,
    `subscription_id` INT NOT NULL,
    `start_date`      DATE NOT NULL,
    `end_date`        DATE NOT NULL,
    `status`          ENUM('active','expired','cancelled') DEFAULT 'active',
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`)         REFERENCES `users`(`id`)         ON DELETE CASCADE,
    FOREIGN KEY (`subscription_id`) REFERENCES `subscriptions`(`id`) ON DELETE CASCADE,
    INDEX `idx_user_status` (`user_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 5. recitations
createTable($pdo, 'recitations', "
CREATE TABLE IF NOT EXISTS `recitations` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`      INT NOT NULL,
    `teacher_id`      INT DEFAULT NULL,
    `surah_name`      VARCHAR(100) NOT NULL,
    `ayah_range`      VARCHAR(50) DEFAULT NULL,
    `audio_file_path` VARCHAR(500) DEFAULT NULL,
    `video_file_path` VARCHAR(500) DEFAULT NULL,
    `status`          ENUM('pending','reviewed','approved','needs_revision') DEFAULT 'pending',
    `uploaded_at`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_student`  (`student_id`),
    INDEX `idx_teacher`  (`teacher_id`),
    INDEX `idx_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 6. corrections
createTable($pdo, 'corrections', "
CREATE TABLE IF NOT EXISTS `corrections` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `recitation_id`      INT NOT NULL,
    `teacher_id`         INT NOT NULL,
    `feedback_text`      TEXT DEFAULT NULL,
    `voice_feedback_path` VARCHAR(500) DEFAULT NULL,
    `rating`             INT DEFAULT 0,
    `corrected_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`recitation_id`) REFERENCES `recitations`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`)    REFERENCES `users`(`id`)        ON DELETE CASCADE,
    INDEX `idx_recitation` (`recitation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 7. progress
createTable($pdo, 'progress', "
CREATE TABLE IF NOT EXISTS `progress` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`       INT NOT NULL,
    `teacher_id`       INT DEFAULT NULL,
    `surah_name`       VARCHAR(100) NOT NULL,
    `memorized_verses` INT DEFAULT 0,
    `total_verses`     INT DEFAULT 0,
    `completion_pct`   DECIMAL(5,2) DEFAULT 0,
    `last_updated`     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 8. messages
createTable($pdo, 'messages', "
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

// 9. groups
createTable($pdo, 'groups', "
CREATE TABLE IF NOT EXISTS `groups` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id`  INT NOT NULL,
    `group_name`  VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `gender`      ENUM('male','female','mixed') DEFAULT 'mixed',
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 10. group_members
createTable($pdo, 'group_members', "
CREATE TABLE IF NOT EXISTS `group_members` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `group_id`   INT NOT NULL,
    `student_id` INT NOT NULL,
    `joined_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_group_student` (`group_id`, `student_id`),
    FOREIGN KEY (`group_id`)   REFERENCES `groups`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 11. calls (WebRTC signaling)
createTable($pdo, 'calls', "
CREATE TABLE IF NOT EXISTS `calls` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `caller_id`  INT NOT NULL,
    `callee_id`  INT NOT NULL,
    `status`     ENUM('pending','active','ended','rejected') DEFAULT 'pending',
    `offer`      TEXT DEFAULT NULL,
    `answer`     TEXT DEFAULT NULL,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `ended_at`   TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`caller_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`callee_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_callee_status` (`callee_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 12. call_candidates (ICE candidates)
createTable($pdo, 'call_candidates', "
CREATE TABLE IF NOT EXISTS `call_candidates` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `call_id`    INT NOT NULL,
    `user_id`    INT NOT NULL,
    `candidate`  TEXT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`call_id`) REFERENCES `calls`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_call` (`call_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 13. live_sessions
createTable($pdo, 'live_sessions', "
CREATE TABLE IF NOT EXISTS `live_sessions` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id`  INT NOT NULL,
    `student_id`  INT DEFAULT NULL,
    `call_id`     INT DEFAULT NULL,
    `status`      ENUM('available','scheduled','active','ended','cancelled') DEFAULT 'available',
    `start_time`  TIMESTAMP NULL DEFAULT NULL,
    `end_time`    TIMESTAMP NULL DEFAULT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_teacher` (`teacher_id`),
    INDEX `idx_student` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 14. resources (موارد تعليمية مجانية)
createTable($pdo, 'resources', "
CREATE TABLE IF NOT EXISTS `resources` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `title`       VARCHAR(255) NOT NULL,
    `description` TEXT DEFAULT NULL,
    `file_path`   VARCHAR(500) DEFAULT NULL,
    `url`         VARCHAR(500) DEFAULT NULL,
    `type`        ENUM('video','pdf','link') NOT NULL,
    `created_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 15. ratings (تقييمات الطلاب للمعلمين)
createTable($pdo, 'ratings', "
CREATE TABLE IF NOT EXISTS `ratings` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` INT NOT NULL,
    `teacher_id` INT NOT NULL,
    `rating`     TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
    `comment`    TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_student_teacher` (`student_id`, `teacher_id`),
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 16. group_sessions (جلسات جماعية)
createTable($pdo, 'group_sessions', "
CREATE TABLE IF NOT EXISTS `group_sessions` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id`       INT NOT NULL,
    `title`            VARCHAR(255) NOT NULL,
    `description`      TEXT DEFAULT NULL,
    `scheduled_at`     TIMESTAMP NOT NULL,
    `duration_minutes` INT DEFAULT 60,
    `max_students`     INT DEFAULT 50,
    `status`           ENUM('scheduled','live','ended') DEFAULT 'scheduled',
    `session_url`      VARCHAR(500) DEFAULT NULL,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher` (`teacher_id`),
    INDEX `idx_status`  (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 17. group_session_participants
createTable($pdo, 'group_session_participants', "
CREATE TABLE IF NOT EXISTS `group_session_participants` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `student_id` INT NOT NULL,
    `joined_at`  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_session_student` (`session_id`, `student_id`),
    FOREIGN KEY (`session_id`) REFERENCES `group_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 18. transactions
createTable($pdo, 'transactions', "
CREATE TABLE IF NOT EXISTS `transactions` (
    `id`           INT AUTO_INCREMENT PRIMARY KEY,
    `from_user_id` INT NOT NULL,
    `to_user_id`   INT DEFAULT NULL,
    `type`         ENUM('session_payment','course_payment','platform_subscription','teacher_earning','platform_fee','top_up') NOT NULL,
    `amount`       DECIMAL(10,2) NOT NULL,
    `description`  TEXT DEFAULT NULL,
    `status`       ENUM('completed','pending','failed') DEFAULT 'completed',
    `created_at`   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`from_user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_from_user` (`from_user_id`),
    INDEX `idx_type`      (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 19. enrollments
createTable($pdo, 'enrollments', "
CREATE TABLE IF NOT EXISTS `enrollments` (
    `id`               INT AUTO_INCREMENT PRIMARY KEY,
    `student_id`       INT NOT NULL,
    `teacher_id`       INT NOT NULL,
    `type`             ENUM('session','course') NOT NULL,
    `sessions_paid`    INT DEFAULT 0,
    `sessions_used`    INT DEFAULT 0,
    `amount_paid`      DECIMAL(10,2) NOT NULL,
    `discount_applied` TINYINT(1) DEFAULT 0,
    `created_at`       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_student` (`student_id`),
    INDEX `idx_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 20. teacher_files
createTable($pdo, 'teacher_files', "
CREATE TABLE IF NOT EXISTS `teacher_files` (
    `id`          INT AUTO_INCREMENT PRIMARY KEY,
    `teacher_id`  INT NOT NULL,
    `file_name`   VARCHAR(255) NOT NULL,
    `file_path`   VARCHAR(500) NOT NULL,
    `file_type`   VARCHAR(100) DEFAULT NULL,
    `file_size`   INT DEFAULT NULL,
    `description` TEXT DEFAULT NULL,
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    INDEX `idx_teacher` (`teacher_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 21. activity_logs
createTable($pdo, 'activity_logs', "
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `user_id`            INT DEFAULT NULL,
    `action_type`        VARCHAR(100) NOT NULL,
    `action_description` TEXT DEFAULT NULL,
    `resource_type`      VARCHAR(100) DEFAULT NULL,
    `resource_id`        INT DEFAULT NULL,
    `ip_address`         VARCHAR(45) DEFAULT NULL,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user`        (`user_id`),
    INDEX `idx_action_type` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

step("── إنشاء الجداول اكتمل ──", 'info');

// ── إضافة الأعمدة الناقصة إلى users ─────────────────────────────────────────
$userCols = [
    'teacher_status'       => "ENUM('pending','approved','rejected') DEFAULT NULL",
    'exam_required'        => "TINYINT(1) DEFAULT 0",
    'exam_passed'          => "TINYINT(1) DEFAULT 0",
    'gender'               => "ENUM('male','female') DEFAULT NULL",
    'phone'                => "VARCHAR(50) DEFAULT NULL",
    'quran_certificate'    => "VARCHAR(500) DEFAULT NULL",
    'trial_end_date'       => "DATE DEFAULT NULL",
    'is_active'            => "TINYINT(1) DEFAULT 1",
    'is_online'            => "TINYINT(1) DEFAULT 0",
    'last_seen'            => "TIMESTAMP NULL DEFAULT NULL",
    'has_star'             => "TINYINT(1) DEFAULT 0",
    'wallet_balance'       => "DECIMAL(10,2) DEFAULT 5000.00",
    'session_price'        => "DECIMAL(10,2) DEFAULT NULL",
    'price_per_course'     => "DECIMAL(10,2) DEFAULT NULL",
    'payment_method_token' => "VARCHAR(255) DEFAULT NULL",
    'availability_start'   => "TIME DEFAULT NULL",
    'availability_end'     => "TIME DEFAULT NULL",
    'availability_days'    => "VARCHAR(100) DEFAULT NULL",
];
foreach ($userCols as $col => $def) {
    addCol($pdo, 'users', $col, $def);
}

// أعمدة إضافية في جداول أخرى (إن كانت موجودة ولكن ناقصة)
addCol($pdo, 'recitations', 'audio_file_path', "VARCHAR(500) DEFAULT NULL");
addCol($pdo, 'recitations', 'video_file_path', "VARCHAR(500) DEFAULT NULL");
addCol($pdo, 'corrections', 'voice_feedback_path', "VARCHAR(500) DEFAULT NULL");
addCol($pdo, 'corrections', 'feedback_text', "TEXT DEFAULT NULL");
addCol($pdo, 'progress',    'memorized_verses', "INT DEFAULT 0");
addCol($pdo, 'progress',    'total_verses',     "INT DEFAULT 0");
addCol($pdo, 'progress',    'completion_pct',   "DECIMAL(5,2) DEFAULT 0");
addCol($pdo, 'groups',      'gender',           "ENUM('male','female','mixed') DEFAULT 'mixed'");

// ── إصلاح الجداول الموجودة بهيكل خاطئ ─────────────────────────────────────────

// groups: إعادة تسمية name → group_name إن وُجدت القديمة
try {
    $cols = $pdo->query("SHOW COLUMNS FROM `groups` LIKE 'name'")->fetch();
    $hasGroupName = $pdo->query("SHOW COLUMNS FROM `groups` LIKE 'group_name'")->fetch();
    if ($cols && !$hasGroupName) {
        $pdo->exec("ALTER TABLE `groups` CHANGE `name` `group_name` VARCHAR(255) NOT NULL");
        step("groups: عمود 'name' أُعيدت تسميته إلى 'group_name'");
    }
} catch (Throwable $e) { step("groups rename: " . $e->getMessage(), 'err'); $errors++; }

// live_sessions: student_id يجب أن يكون NULL-able (لا NOT NULL DEFAULT 0)
try {
    $col = $pdo->query("SHOW COLUMNS FROM `live_sessions` LIKE 'student_id'")->fetch();
    if ($col && stripos($col['Null'], 'NO') !== false) {
        // Drop existing FK on student_id first
        $fk = $pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='live_sessions'
            AND COLUMN_NAME='student_id' AND REFERENCED_TABLE_NAME='users'")->fetch();
        if ($fk) {
            $pdo->exec("ALTER TABLE `live_sessions` DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
        }
        // Make column nullable
        $pdo->exec("ALTER TABLE `live_sessions` MODIFY `student_id` INT DEFAULT NULL");
        // Fix any 0 values left over
        $pdo->exec("UPDATE `live_sessions` SET student_id=NULL WHERE student_id=0");
        // Re-add FK with ON DELETE SET NULL
        $pdo->exec("ALTER TABLE `live_sessions` ADD CONSTRAINT `fk_ls_student`
            FOREIGN KEY (`student_id`) REFERENCES `users`(`id`) ON DELETE SET NULL");
        step("live_sessions: student_id أصبح nullable + FK أُصلح");
    }
} catch (Throwable $e) { step("live_sessions fix: " . $e->getMessage(), 'err'); $errors++; }

// live_sessions: إعادة تسمية started_at → start_time و ended_at → end_time
try {
    if ($pdo->query("SHOW COLUMNS FROM `live_sessions` LIKE 'started_at'")->fetch()) {
        $pdo->exec("ALTER TABLE `live_sessions` CHANGE `started_at` `start_time` TIMESTAMP NULL DEFAULT NULL");
        step("live_sessions: started_at → start_time");
    }
    if ($pdo->query("SHOW COLUMNS FROM `live_sessions` LIKE 'ended_at'")->fetch()) {
        $pdo->exec("ALTER TABLE `live_sessions` CHANGE `ended_at` `end_time` TIMESTAMP NULL DEFAULT NULL");
        step("live_sessions: ended_at → end_time");
    }
} catch (Throwable $e) { step("live_sessions rename: " . $e->getMessage(), 'err'); $errors++; }

step("── إصلاح الهيكل اكتمل ──", 'info');


// ── بيانات أساسية: حساب الأدمن ───────────────────────────────────────────────
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `user_type` = 'admin'");
    if ((int)$stmt->fetchColumn() === 0) {
        $hash  = password_hash('admin123', PASSWORD_BCRYPT);
        $trial = date('Y-m-d', strtotime('+36500 days'));
        $pdo->prepare("
            INSERT INTO `users`
                (`email`, `password_hash`, `full_name`, `user_type`, `is_active`, `wallet_balance`, `trial_end_date`, `has_star`)
            VALUES (?, ?, 'المدير', 'admin', 1, 99999.00, ?, 1)
        ")->execute(['admin@tilawa.com', $hash, $trial]);
        step("أُنشئ حساب الأدمن: admin@tilawa.com / admin123");
    } else {
        step("حساب الأدمن موجود مسبقاً", 'skip');
    }
} catch (Throwable $e) {
    $errors++;
    step("خطأ إنشاء الأدمن: " . $e->getMessage(), 'err');
}

// ── بيانات أساسية: خطط الاشتراك ──────────────────────────────────────────────
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM `subscriptions`");
    if ((int)$stmt->fetchColumn() === 0) {
        $plans = [
            ['شهري',      'Monthly',  2000,  30,    'وصول كامل لمدة شهر واحد'],
            ['سنوي',      'Yearly',   15000, 365,   'وصول كامل لمدة سنة كاملة + توفير 72%'],
            ['مدى الحياة','Lifetime', 35000, 36500, 'وصول دائم مدى الحياة — أفضل قيمة'],
        ];
        $ins = $pdo->prepare("INSERT INTO `subscriptions` (`name_ar`,`name`,`price`,`duration_days`,`features`) VALUES (?,?,?,?,?)");
        foreach ($plans as $p) {
            $ins->execute($p);
        }
        step("أُضيفت خطط الاشتراك الثلاث (شهري، سنوي، مدى الحياة)");
    } else {
        step("خطط الاشتراك موجودة مسبقاً", 'skip');
    }
} catch (Throwable $e) {
    $errors++;
    step("خطأ إضافة خطط الاشتراك: " . $e->getMessage(), 'err');
}

// ── إنشاء مجلد الرفع ──────────────────────────────────────────────────────────
$uploadDir = __DIR__ . '/uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
    step("أُنشئ مجلد uploads/");
} else {
    step("مجلد uploads/ موجود", 'skip');
}

// ── تشخيص شامل ───────────────────────────────────────────────────────────────
$requiredTables = [
    'users','sessions','subscriptions','user_subscriptions',
    'recitations','corrections','progress','messages',
    'groups','group_members','calls','call_candidates',
    'live_sessions','resources','ratings',
    'group_sessions','group_session_participants',
    'transactions','enrollments','teacher_files','activity_logs',
];

$diagPass = 0;
$diagFail = 0;
$diagResults = [];

foreach ($requiredTables as $t) {
    $ok = tableExists($pdo, $t);
    $diagResults[] = ['label' => "جدول `$t`", 'ok' => $ok];
    $ok ? $diagPass++ : $diagFail++;
}

// تحقق من عمود users المهمة
$criticalCols = ['password_hash','user_type','has_star','wallet_balance','trial_end_date','is_online','last_seen'];
foreach ($criticalCols as $c) {
    $ok = colExists($pdo, 'users', $c);
    $diagResults[] = ['label' => "users.`$c`", 'ok' => $ok];
    $ok ? $diagPass++ : $diagFail++;
}

// تحقق من الأدمن
$stmt  = $pdo->query("SELECT COUNT(*) FROM `users` WHERE `user_type`='admin'");
$admin = (int)$stmt->fetchColumn();
$diagResults[] = ['label' => "حساب الأدمن ($admin مدير)", 'ok' => $admin > 0];
$admin > 0 ? $diagPass++ : $diagFail++;

// تحقق من الخطط
$stmt  = $pdo->query("SELECT COUNT(*) FROM `subscriptions`");
$plans = (int)$stmt->fetchColumn();
$diagResults[] = ['label' => "خطط الاشتراك ($plans خطة)", 'ok' => $plans >= 3];
$plans >= 3 ? $diagPass++ : $diagFail++;

$allOk = $diagFail === 0 && $errors === 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعداد قاعدة البيانات — رتل معي</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Cairo', sans-serif;
            direction: rtl;
            background: #f5f3ef;
            color: #2d3e50;
            min-height: 100vh;
            padding: 2rem 1rem;
        }
        .page { max-width: 860px; margin: 0 auto; }

        /* Warning Banner */
        .warning-banner {
            background: linear-gradient(135deg, #fff3cd, #ffe8a1);
            border: 2px solid #f59e0b;
            border-radius: 14px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            font-weight: 700;
            color: #92400e;
            font-size: 1rem;
        }
        .warning-banner .warn-icon { font-size: 1.75rem; flex-shrink: 0; }

        /* Header */
        .setup-header {
            background: linear-gradient(135deg, #1A2A4A 0%, #2D4A8A 100%);
            border-radius: 20px;
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            color: white;
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        .setup-header .logo-text {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #D4AF37, #F5E6C3);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .setup-header h1 { font-size: 1.4rem; font-weight: 700; color: white; margin-bottom: .25rem; }
        .setup-header p  { font-size: .9rem; color: rgba(255,255,255,.7); }

        /* Log section */
        .section-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 16px rgba(0,0,0,.07);
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        .section-title {
            padding: 1rem 1.5rem;
            background: #f8f7f4;
            border-bottom: 1px solid #e8e4d9;
            font-weight: 700;
            font-size: 1rem;
            color: #1A2A4A;
            display: flex;
            align-items: center;
            gap: .5rem;
        }
        .log-list { padding: .75rem 1.5rem; }
        .log-item {
            display: flex;
            align-items: flex-start;
            gap: .75rem;
            padding: .45rem 0;
            border-bottom: 1px solid #f0ede6;
            font-size: .92rem;
            line-height: 1.5;
        }
        .log-item:last-child { border-bottom: none; }
        .log-item.ok   { color: #166534; }
        .log-item.skip { color: #64748b; }
        .log-item.err  { color: #dc2626; font-weight: 600; }
        .log-item.info { color: #1e40af; font-weight: 700; border-bottom: 2px solid #dbeafe; }
        .log-icon { flex-shrink: 0; font-size: 1rem; margin-top: .1rem; }

        /* Diagnostic grid */
        .diag-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: .6rem;
            padding: 1.25rem 1.5rem;
        }
        .diag-item {
            display: flex;
            align-items: center;
            gap: .5rem;
            padding: .5rem .75rem;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
        }
        .diag-item.pass { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .diag-item.fail { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }

        /* Result banner */
        .result-banner {
            border-radius: 16px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }
        .result-banner.success {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border: 2px solid #4ade80;
        }
        .result-banner.has-errors {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 2px solid #f87171;
        }
        .result-banner .result-icon { font-size: 3.5rem; margin-bottom: .75rem; }
        .result-banner h2 { font-size: 1.5rem; font-weight: 900; margin-bottom: .5rem; }
        .result-banner.success h2 { color: #166534; }
        .result-banner.has-errors h2 { color: #991b1b; }
        .result-banner p { font-size: .95rem; color: #64748b; }

        /* Credentials box */
        .creds-box {
            background: #fffbeb;
            border: 1.5px solid #fcd34d;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin: 1.25rem 0 0;
            text-align: right;
        }
        .creds-box h4 { color: #92400e; margin-bottom: .75rem; font-size: 1rem; }
        .cred-row { display: flex; align-items: center; gap: .75rem; margin-bottom: .4rem; font-size: .9rem; }
        .cred-label { color: #78716c; min-width: 100px; }
        .cred-value { font-weight: 700; color: #1c1917; font-family: monospace; background: #fef3c7; padding: .15rem .5rem; border-radius: 6px; }

        /* Action buttons */
        .actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 1.5rem;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .85rem 2rem;
            border-radius: 50px;
            font-family: 'Cairo', sans-serif;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            transition: all .2s;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #5B8BD6, #2D4A8A);
            color: white;
            box-shadow: 0 4px 16px rgba(45,74,138,.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(45,74,138,.4); }
        .btn-secondary {
            background: linear-gradient(135deg, #40916C, #2D6A4F);
            color: white;
            box-shadow: 0 4px 16px rgba(45,106,79,.3);
        }
        .btn-secondary:hover { transform: translateY(-2px); }

        /* Score badge */
        .score-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: rgba(255,255,255,.9);
            border-radius: 50px;
            padding: .4rem 1rem;
            font-weight: 700;
            font-size: .9rem;
            margin-top: .75rem;
        }
        .score-badge.all-ok { color: #166534; }
        .score-badge.has-fail { color: #dc2626; }
    </style>
</head>
<body>
<div class="page">

    <!-- تحذير -->
    <div class="warning-banner">
        <span class="warn-icon">⚠️</span>
        <span>تحذير أمني: احذف هذا الملف <code>setup.php</code> فور انتهاء الإعداد — لا تتركه على السيرفر</span>
    </div>

    <!-- الرأس -->
    <div class="setup-header">
        <div>
            <div class="logo-text">رتل معي</div>
        </div>
        <div>
            <h1>🔧 إعداد قاعدة البيانات</h1>
            <p>يُنشئ هذا الملف جميع الجداول والأعمدة اللازمة تلقائياً</p>
        </div>
    </div>

    <!-- سجل العمليات -->
    <div class="section-card">
        <div class="section-title">📋 سجل العمليات</div>
        <div class="log-list">
            <?php foreach ($log as $entry): ?>
            <div class="log-item <?php echo $entry['type']; ?>">
                <span class="log-icon"><?php echo $entry['icon']; ?></span>
                <span><?php echo htmlspecialchars($entry['msg']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- التشخيص -->
    <div class="section-card">
        <div class="section-title">🔍 تقرير التشخيص</div>
        <div class="diag-grid">
            <?php foreach ($diagResults as $d): ?>
            <div class="diag-item <?php echo $d['ok'] ? 'pass' : 'fail'; ?>">
                <span><?php echo $d['ok'] ? '✅' : '❌'; ?></span>
                <span><?php echo htmlspecialchars($d['label']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- النتيجة -->
    <div class="result-banner <?php echo $allOk ? 'success' : 'has-errors'; ?>">
        <div class="result-icon"><?php echo $allOk ? '🎉' : '⚠️'; ?></div>
        <h2><?php echo $allOk ? 'الإعداد مكتمل — المنصة جاهزة' : 'بعض العناصر تحتاج انتباهاً'; ?></h2>
        <div class="score-badge <?php echo $diagFail === 0 ? 'all-ok' : 'has-fail'; ?>">
            <?php echo $diagPass; ?> نجح · <?php echo $diagFail; ?> فشل
            <?php if ($errors > 0): ?> · <?php echo $errors; ?> خطأ<?php endif; ?>
        </div>

        <?php if ($allOk): ?>
        <div class="creds-box">
            <h4>🔑 بيانات دخول المدير (إن كانت جديدة)</h4>
            <div class="cred-row">
                <span class="cred-label">البريد:</span>
                <span class="cred-value">admin@tilawa.com</span>
            </div>
            <div class="cred-row">
                <span class="cred-label">كلمة المرور:</span>
                <span class="cred-value">admin123</span>
            </div>
            <p style="font-size:.8rem;color:#78716c;margin-top:.75rem">⚠️ غيّر كلمة المرور فور تسجيل الدخول</p>
        </div>
        <?php endif; ?>

        <div class="actions">
            <a href="login.php" class="btn btn-primary">🔐 تسجيل الدخول</a>
            <a href="dashboard.php" class="btn btn-secondary">🏠 لوحة التحكم</a>
        </div>
    </div>

</div>
</body>
</html>
