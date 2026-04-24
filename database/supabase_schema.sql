-- =====================================================================
-- Tilawa Platform — PostgreSQL Schema (converted from MySQL)
-- Run this in: Supabase Dashboard → SQL Editor → New Query → Run
-- =====================================================================

-- ENUM types
CREATE TYPE user_type_enum         AS ENUM ('teacher', 'student', 'admin');
CREATE TYPE teacher_status_enum    AS ENUM ('pending', 'approved', 'rejected');
CREATE TYPE gender_enum            AS ENUM ('male', 'female');
CREATE TYPE group_gender_enum      AS ENUM ('male', 'female', 'mixed');
CREATE TYPE recitation_status_enum AS ENUM ('pending', 'reviewed', 'approved', 'needs_revision');
CREATE TYPE sub_status_enum        AS ENUM ('active', 'expired', 'cancelled');
CREATE TYPE call_type_enum         AS ENUM ('audio', 'video');
CREATE TYPE call_status_enum       AS ENUM ('ringing', 'accepted', 'declined', 'ended');
CREATE TYPE live_status_enum       AS ENUM ('scheduled', 'active', 'completed', 'cancelled');
CREATE TYPE resource_type_enum     AS ENUM ('video', 'pdf', 'link');
CREATE TYPE gs_status_enum         AS ENUM ('scheduled', 'live', 'ended');
CREATE TYPE tx_type_enum           AS ENUM ('session_payment', 'course_payment', 'platform_subscription', 'teacher_earning', 'platform_fee', 'top_up');
CREATE TYPE tx_status_enum         AS ENUM ('completed', 'pending', 'failed');
CREATE TYPE enroll_type_enum       AS ENUM ('session', 'course');

-- Users
CREATE TABLE IF NOT EXISTS users (
    id                    SERIAL PRIMARY KEY,
    email                 VARCHAR(255) NOT NULL UNIQUE,
    password_hash         VARCHAR(255) NOT NULL,
    full_name             VARCHAR(255) NOT NULL,
    user_type             user_type_enum NOT NULL DEFAULT 'student',
    teacher_status        teacher_status_enum DEFAULT 'pending',
    exam_required         BOOLEAN DEFAULT FALSE,
    exam_passed           BOOLEAN DEFAULT FALSE,
    gender                gender_enum DEFAULT NULL,
    phone                 VARCHAR(20),
    quran_certificate     VARCHAR(500) DEFAULT NULL,
    availability_start    TIME DEFAULT NULL,
    availability_end      TIME DEFAULT NULL,
    availability_days     VARCHAR(100) DEFAULT NULL,
    availability_timezone VARCHAR(50) DEFAULT 'UTC',
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trial_end_date        DATE DEFAULT NULL,
    subscription_id       INT DEFAULT NULL,
    is_active             BOOLEAN DEFAULT TRUE,
    is_online             BOOLEAN DEFAULT FALSE,
    last_seen             TIMESTAMP NULL DEFAULT NULL,
    has_star              BOOLEAN DEFAULT FALSE,
    wallet_balance        DECIMAL(10,2) DEFAULT 5000.00,
    session_price         DECIMAL(10,2) DEFAULT NULL,
    price_per_course      DECIMAL(10,2) DEFAULT NULL,
    payment_method_token  VARCHAR(255) DEFAULT NULL,
    stripe_customer_id    VARCHAR(255) DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS idx_users_email          ON users (email);
CREATE INDEX IF NOT EXISTS idx_users_type           ON users (user_type);
CREATE INDEX IF NOT EXISTS idx_users_teacher_status ON users (teacher_status);
CREATE INDEX IF NOT EXISTS idx_users_gender         ON users (gender);
CREATE INDEX IF NOT EXISTS idx_users_trial_end      ON users (trial_end_date);

-- Subscriptions
CREATE TABLE IF NOT EXISTS subscriptions (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    name_ar      VARCHAR(100) NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    features     TEXT,
    duration_days INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- User subscriptions
CREATE TABLE IF NOT EXISTS user_subscriptions (
    id              SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    subscription_id INT NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    status          sub_status_enum DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_usub_user_id ON user_subscriptions (user_id);
CREATE INDEX IF NOT EXISTS idx_usub_status  ON user_subscriptions (status);

-- Sessions (auth tokens)
CREATE TABLE IF NOT EXISTS sessions (
    id            SERIAL PRIMARY KEY,
    user_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at    TIMESTAMP NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_sessions_token   ON sessions (session_token);
CREATE INDEX IF NOT EXISTS idx_sessions_expires ON sessions (expires_at);

-- Recitations
CREATE TABLE IF NOT EXISTS recitations (
    id              SERIAL PRIMARY KEY,
    student_id      INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    teacher_id      INT REFERENCES users(id) ON DELETE SET NULL,
    surah_name      VARCHAR(100) NOT NULL,
    ayah_range      VARCHAR(50),
    audio_file_path VARCHAR(500) DEFAULT NULL,
    video_file_path VARCHAR(500) DEFAULT NULL,
    status          recitation_status_enum DEFAULT 'pending',
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_recitations_student ON recitations (student_id);
CREATE INDEX IF NOT EXISTS idx_recitations_teacher ON recitations (teacher_id);
CREATE INDEX IF NOT EXISTS idx_recitations_status  ON recitations (status);

-- Corrections
CREATE TABLE IF NOT EXISTS corrections (
    id                 SERIAL PRIMARY KEY,
    recitation_id      INT NOT NULL REFERENCES recitations(id) ON DELETE CASCADE,
    teacher_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    feedback_text      TEXT,
    voice_feedback_path VARCHAR(500) DEFAULT NULL,
    rating             INT DEFAULT 0,
    corrected_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_corrections_recitation ON corrections (recitation_id);

-- Progress
CREATE TABLE IF NOT EXISTS progress (
    id               SERIAL PRIMARY KEY,
    student_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    surah_name       VARCHAR(100) NOT NULL,
    memorized_verses INT DEFAULT 0,
    total_verses     INT DEFAULT 0,
    last_updated     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, surah_name)
);
CREATE INDEX IF NOT EXISTS idx_progress_student ON progress (student_id);

-- Trigger: update last_updated on progress
CREATE OR REPLACE FUNCTION update_progress_timestamp()
RETURNS TRIGGER AS $$
BEGIN NEW.last_updated = CURRENT_TIMESTAMP; RETURN NEW; END;
$$ LANGUAGE plpgsql;
CREATE OR REPLACE TRIGGER trg_progress_updated
    BEFORE UPDATE ON progress
    FOR EACH ROW EXECUTE FUNCTION update_progress_timestamp();

-- Groups
CREATE TABLE IF NOT EXISTS groups (
    id         SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    group_name VARCHAR(255) NOT NULL,
    description TEXT,
    gender     group_gender_enum DEFAULT 'mixed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_groups_teacher ON groups (teacher_id);

-- Group members
CREATE TABLE IF NOT EXISTS group_members (
    id         SERIAL PRIMARY KEY,
    group_id   INT NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    student_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (group_id, student_id)
);
CREATE INDEX IF NOT EXISTS idx_gm_group   ON group_members (group_id);
CREATE INDEX IF NOT EXISTS idx_gm_student ON group_members (student_id);

-- Messages
CREATE TABLE IF NOT EXISTS messages (
    id                 SERIAL PRIMARY KEY,
    sender_id          INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message_text       TEXT,
    voice_message_path VARCHAR(500) DEFAULT NULL,
    is_read            BOOLEAN DEFAULT FALSE,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_messages_sender   ON messages (sender_id);
CREATE INDEX IF NOT EXISTS idx_messages_receiver ON messages (receiver_id);
CREATE INDEX IF NOT EXISTS idx_messages_read     ON messages (is_read);
CREATE INDEX IF NOT EXISTS idx_messages_created  ON messages (created_at);

-- Activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
    id                 SERIAL PRIMARY KEY,
    user_id            INT REFERENCES users(id) ON DELETE SET NULL,
    action_type        VARCHAR(100) NOT NULL,
    action_description TEXT,
    resource_type      VARCHAR(50),
    resource_id        INT,
    ip_address         VARCHAR(45),
    user_agent         TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_logs_user     ON activity_logs (user_id);
CREATE INDEX IF NOT EXISTS idx_logs_action   ON activity_logs (action_type);
CREATE INDEX IF NOT EXISTS idx_logs_created  ON activity_logs (created_at);
CREATE INDEX IF NOT EXISTS idx_logs_resource ON activity_logs (resource_type, resource_id);

-- Teacher files
CREATE TABLE IF NOT EXISTS teacher_files (
    id          SERIAL PRIMARY KEY,
    teacher_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    file_name   VARCHAR(500) NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    file_type   VARCHAR(50),
    file_size   INT,
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_tf_teacher  ON teacher_files (teacher_id);
CREATE INDEX IF NOT EXISTS idx_tf_uploaded ON teacher_files (uploaded_at);

-- Live sessions
CREATE TABLE IF NOT EXISTS live_sessions (
    id             SERIAL PRIMARY KEY,
    teacher_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    start_time     TIMESTAMP NULL DEFAULT NULL,
    end_time       TIMESTAMP NULL DEFAULT NULL,
    status         live_status_enum DEFAULT 'scheduled',
    report_text    TEXT,
    recording_path VARCHAR(500),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_ls_teacher ON live_sessions (teacher_id);
CREATE INDEX IF NOT EXISTS idx_ls_student ON live_sessions (student_id);
CREATE INDEX IF NOT EXISTS idx_ls_status  ON live_sessions (status);

-- Calls (WebRTC signaling)
CREATE TABLE IF NOT EXISTS calls (
    id         SERIAL PRIMARY KEY,
    caller_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    callee_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type       call_type_enum NOT NULL,
    status     call_status_enum DEFAULT 'ringing',
    offer_sdp  TEXT,
    answer_sdp TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_calls_caller ON calls (caller_id);
CREATE INDEX IF NOT EXISTS idx_calls_callee ON calls (callee_id);
CREATE INDEX IF NOT EXISTS idx_calls_status ON calls (status);

-- Trigger: update updated_at on calls
CREATE OR REPLACE FUNCTION update_calls_timestamp()
RETURNS TRIGGER AS $$
BEGIN NEW.updated_at = CURRENT_TIMESTAMP; RETURN NEW; END;
$$ LANGUAGE plpgsql;
CREATE OR REPLACE TRIGGER trg_calls_updated
    BEFORE UPDATE ON calls
    FOR EACH ROW EXECUTE FUNCTION update_calls_timestamp();

-- Resources
CREATE TABLE IF NOT EXISTS resources (
    id          SERIAL PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path   VARCHAR(500) DEFAULT NULL,
    url         VARCHAR(500) DEFAULT NULL,
    type        resource_type_enum NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_resources_type ON resources (type);

-- Ratings
CREATE TABLE IF NOT EXISTS ratings (
    id         SERIAL PRIMARY KEY,
    student_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    teacher_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating     SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment    TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (student_id, teacher_id)
);
CREATE INDEX IF NOT EXISTS idx_ratings_teacher ON ratings (teacher_id);

-- Group sessions
CREATE TABLE IF NOT EXISTS group_sessions (
    id               SERIAL PRIMARY KEY,
    teacher_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title            VARCHAR(255) NOT NULL,
    description      TEXT DEFAULT NULL,
    scheduled_at     TIMESTAMP NOT NULL,
    duration_minutes INT DEFAULT 60,
    max_students     INT DEFAULT 50,
    status           gs_status_enum DEFAULT 'scheduled',
    session_url      VARCHAR(500) DEFAULT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_gs_teacher ON group_sessions (teacher_id);
CREATE INDEX IF NOT EXISTS idx_gs_status  ON group_sessions (status);

-- Group session participants
CREATE TABLE IF NOT EXISTS group_session_participants (
    id         SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES group_sessions(id) ON DELETE CASCADE,
    student_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (session_id, student_id)
);

-- Transactions
CREATE TABLE IF NOT EXISTS transactions (
    id           SERIAL PRIMARY KEY,
    from_user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id   INT DEFAULT NULL,
    type         tx_type_enum NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    description  TEXT DEFAULT NULL,
    status       tx_status_enum DEFAULT 'completed',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_tx_from ON transactions (from_user_id);
CREATE INDEX IF NOT EXISTS idx_tx_type ON transactions (type);

-- Enrollments
CREATE TABLE IF NOT EXISTS enrollments (
    id               SERIAL PRIMARY KEY,
    student_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    teacher_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    type             enroll_type_enum NOT NULL,
    sessions_paid    INT DEFAULT 0,
    sessions_used    INT DEFAULT 0,
    amount_paid      DECIMAL(10,2) NOT NULL,
    discount_applied BOOLEAN DEFAULT FALSE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_enroll_student ON enrollments (student_id);
CREATE INDEX IF NOT EXISTS idx_enroll_teacher ON enrollments (teacher_id);

-- ── Seed data ─────────────────────────────────────────────────────────────────
INSERT INTO subscriptions (name, name_ar, price, features, duration_days) VALUES
    ('Standard', 'عادية',  29.99,  'Basic features, limited corrections per month', 30),
    ('Gold',     'ذهبية',  99.99,  'Advanced features, unlimited corrections, priority support', 90),
    ('Premium',  'مميزة',  499.99, 'All features, personal teacher, advanced analytics', 365)
ON CONFLICT DO NOTHING;

-- Default admin (password: admin123)
INSERT INTO users (email, password_hash, full_name, user_type) VALUES
    ('admin@rattil.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin')
ON CONFLICT (email) DO NOTHING;

-- ── Migration: dynamic platform settings ──────────────────────────────────────
CREATE TABLE IF NOT EXISTS platform_settings (
    key   VARCHAR(100) PRIMARY KEY,
    value TEXT        NOT NULL,
    label VARCHAR(200),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO platform_settings (key, value, label)
VALUES
    ('platform_fee_percent', '15',    'نسبة عمولة المنصة (%)'),
    ('min_topup',            '100',   'الحد الأدنى للشحن (دج)'),
    ('max_topup',            '100000','الحد الأقصى للشحن (دج)'),
    ('min_payout',           '500',   'الحد الأدنى للسحب (دج)')
ON CONFLICT (key) DO NOTHING;

-- ── Migration: description column on subscriptions ────────────────────────────
ALTER TABLE subscriptions ADD COLUMN IF NOT EXISTS description TEXT;
