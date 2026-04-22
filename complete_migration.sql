-- =====================================================
-- Tilawa Quran Platform - Complete Migration Script
-- =====================================================
-- Database: tilawa_platform
-- This file contains ALL SQL commands for complete platform setup
-- =====================================================

-- Create database if not exists
CREATE DATABASE IF NOT EXISTS tilawa_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tilawa_platform;

-- =====================================================
-- CORE TABLES (from original schema)
-- =====================================================

-- Users table with all feature additions
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    user_type ENUM('teacher', 'student', 'admin') NOT NULL DEFAULT 'student',
    teacher_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    exam_required BOOLEAN DEFAULT FALSE,
    exam_passed BOOLEAN DEFAULT FALSE,
    gender ENUM('male', 'female') DEFAULT NULL,
    phone VARCHAR(20),
    quran_certificate VARCHAR(500) DEFAULT NULL,
    availability_start TIME DEFAULT NULL,
    availability_end TIME DEFAULT NULL,
    availability_days VARCHAR(100) DEFAULT NULL,
    availability_timezone VARCHAR(50) DEFAULT 'UTC',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trial_end_date DATE DEFAULT NULL,
    subscription_id INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    payment_method_token VARCHAR(255) DEFAULT NULL,
    stripe_customer_id VARCHAR(255) DEFAULT NULL,
    
    -- Feature 1: Teacher Online Status
    is_online TINYINT DEFAULT 0,
    last_seen TIMESTAMP NULL DEFAULT NULL,
    
    -- Feature 7: Admin Star Award System
    has_star TINYINT DEFAULT 0,
    
    -- Payment System Features
    session_price DECIMAL(10,2) DEFAULT NULL,
    price_per_course DECIMAL(10,2) DEFAULT NULL,
    wallet_balance DECIMAL(10,2) DEFAULT 100.00,
    
    INDEX idx_email (email),
    INDEX idx_user_type (user_type),
    INDEX idx_teacher_status (teacher_status),
    INDEX idx_gender (gender),
    INDEX idx_trial_end (trial_end_date),
    INDEX idx_is_online (is_online),
    INDEX idx_last_seen (last_seen),
    INDEX idx_has_star (has_star)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Subscriptions table (original)
CREATE TABLE IF NOT EXISTS subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_ar VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    features TEXT,
    duration_days INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- User subscriptions table (original)
CREATE TABLE IF NOT EXISTS user_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subscription_id INT NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Recitations table (original)
CREATE TABLE IF NOT EXISTS recitations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT,
    surah_name VARCHAR(100) NOT NULL,
    ayah_range VARCHAR(50),
    audio_file_path VARCHAR(500) DEFAULT NULL,
    video_file_path VARCHAR(500) DEFAULT NULL,
    status ENUM(
        'pending',
        'reviewed',
        'approved',
        'needs_revision'
    ) DEFAULT 'pending',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE
    SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_status (status)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Corrections table (original)
CREATE TABLE IF NOT EXISTS corrections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recitation_id INT NOT NULL,
    teacher_id INT NOT NULL,
    feedback_text TEXT,
    voice_feedback_path VARCHAR(500) DEFAULT NULL,
    rating INT DEFAULT 0,
    corrected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recitation_id) REFERENCES recitations(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_recitation_id (recitation_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Progress table (original)
CREATE TABLE IF NOT EXISTS progress (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    surah_name VARCHAR(100) NOT NULL,
    memorized_verses INT DEFAULT 0,
    total_verses INT DEFAULT 0,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Sessions table (original)
CREATE TABLE IF NOT EXISTS sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    teacher_id INT NOT NULL,
    session_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    session_type ENUM('recitation', 'correction') DEFAULT 'recitation',
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_teacher_id (teacher_id),
    INDEX idx_session_date (session_date)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================
-- FEATURE 2: Free Learning Resources
-- =====================================================

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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================
-- FEATURE 3: Student Rating for Teachers
-- =====================================================

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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================
-- FEATURE 4 & 5: Live Group Sessions
-- =====================================================

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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================
-- PAYMENT SYSTEM TABLES
-- =====================================================

-- Platform subscription for students (Feature 6)
CREATE TABLE IF NOT EXISTS subscriptions_platform (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    plan_name VARCHAR(100) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- All transactions log (Payment System)
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Student-Teacher enrollments (Payment System)
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
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================
-- ADDITIONAL SUPPORTING TABLES (from original schema)
-- =====================================================

-- Messages table (original)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    subject VARCHAR(255),
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_sender_id (sender_id),
    INDEX idx_receiver_id (receiver_id),
    INDEX idx_created_at (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Groups table (original)
CREATE TABLE IF NOT EXISTS groups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(255) NOT NULL,
    description TEXT,
    teacher_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_teacher_id (teacher_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Group members table (original)
CREATE TABLE IF NOT EXISTS group_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT NOT NULL,
    student_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_group_student (group_id, student_id),
    INDEX idx_group_id (group_id),
    INDEX idx_student_id (student_id)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Activity logs table (original)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    details TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE DATA INSERTIONS
-- =====================================================

-- Insert sample subscription plans
INSERT IGNORE INTO subscriptions (name, name_ar, price, features, duration_days) VALUES
('Basic', 'أساسي', 0.00, 'Access to basic features', 30),
('Premium', 'مميز', 29.99, 'Full access to all features', 30),
('Professional', 'احترافي', 49.99, 'All features + priority support', 30);

-- =====================================================
-- MIGRATION COMPLETION MESSAGE
-- =====================================================

SELECT 'Complete Tilawa Platform Migration - All tables and features created successfully!' as message;

-- =====================================================
-- USAGE INSTRUCTIONS
-- =====================================================
/*
To run this complete migration:

1. Via MySQL Command Line:
   mysql -u root -p tilawa_platform < complete_migration.sql

2. Via PHPMyAdmin:
   - Select tilawa_platform database
   - Click "Import" tab
   - Choose complete_migration.sql file
   - Click "Go"

3. Via Web Browser:
   http://localhost/Tilawa/complete_migration.php

After migration, your Tilawa platform will have:
- All 7 implemented features
- Complete payment system
- Teacher online status
- Student rating system
- Group sessions
- Free resources
- Platform subscriptions
- Star award system
- Transaction logging
- Wallet management

All tables are created with proper indexes, foreign keys, and UTF-8 support.
*/
