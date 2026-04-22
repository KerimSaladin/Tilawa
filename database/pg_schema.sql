-- Tilawa Platform — PostgreSQL schema
CREATE TYPE user_type_enum         AS ENUM ('teacher','student','admin');
CREATE TYPE teacher_status_enum    AS ENUM ('pending','approved','rejected');
CREATE TYPE gender_enum            AS ENUM ('male','female');
CREATE TYPE group_gender_enum      AS ENUM ('male','female','mixed');
CREATE TYPE recitation_status_enum AS ENUM ('pending','reviewed','approved','needs_revision');
CREATE TYPE sub_status_enum        AS ENUM ('active','expired','cancelled');
CREATE TYPE call_type_enum         AS ENUM ('audio','video');
CREATE TYPE call_status_enum       AS ENUM ('ringing','accepted','declined','ended');
CREATE TYPE live_status_enum       AS ENUM ('scheduled','active','completed','cancelled');
CREATE TYPE resource_type_enum     AS ENUM ('video','pdf','link');
CREATE TYPE gs_status_enum         AS ENUM ('scheduled','live','ended');
CREATE TYPE tx_type_enum           AS ENUM ('session_payment','course_payment','platform_subscription','teacher_earning','platform_fee','top_up');
CREATE TYPE tx_status_enum         AS ENUM ('completed','pending','failed');
CREATE TYPE enroll_type_enum       AS ENUM ('session','course');

CREATE TABLE users (
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
    quran_certificate     VARCHAR(500),
    availability_start    TIME,
    availability_end      TIME,
    availability_days     VARCHAR(100),
    availability_timezone VARCHAR(50) DEFAULT 'UTC',
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    trial_end_date        DATE,
    subscription_id       INT,
    is_active             BOOLEAN DEFAULT TRUE,
    is_online             BOOLEAN DEFAULT FALSE,
    last_seen             TIMESTAMP,
    has_star              BOOLEAN DEFAULT FALSE,
    wallet_balance        DECIMAL(10,2) DEFAULT 5000.00,
    session_price         DECIMAL(10,2),
    price_per_course      DECIMAL(10,2),
    payment_method_token  VARCHAR(255),
    stripe_customer_id    VARCHAR(255)
);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_type  ON users(user_type);

CREATE TABLE subscriptions (
    id           SERIAL PRIMARY KEY,
    name         VARCHAR(100) NOT NULL,
    name_ar      VARCHAR(100) NOT NULL,
    price        DECIMAL(10,2) NOT NULL,
    features     TEXT,
    duration_days INT NOT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE user_subscriptions (
    id              SERIAL PRIMARY KEY,
    user_id         INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    subscription_id INT NOT NULL REFERENCES subscriptions(id) ON DELETE CASCADE,
    start_date      DATE NOT NULL,
    end_date        DATE NOT NULL,
    status          sub_status_enum DEFAULT 'active',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE sessions (
    id            SERIAL PRIMARY KEY,
    user_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    session_token VARCHAR(255) NOT NULL UNIQUE,
    expires_at    TIMESTAMP NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sessions_token ON sessions(session_token);

CREATE TABLE recitations (
    id              SERIAL PRIMARY KEY,
    student_id      INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    teacher_id      INT REFERENCES users(id) ON DELETE SET NULL,
    surah_name      VARCHAR(100) NOT NULL,
    ayah_range      VARCHAR(50),
    audio_file_path VARCHAR(500),
    video_file_path VARCHAR(500),
    status          recitation_status_enum DEFAULT 'pending',
    uploaded_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE corrections (
    id                  SERIAL PRIMARY KEY,
    recitation_id       INT NOT NULL REFERENCES recitations(id) ON DELETE CASCADE,
    teacher_id          INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    feedback_text       TEXT,
    voice_feedback_path VARCHAR(500),
    rating              INT DEFAULT 0,
    corrected_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE progress (
    id               SERIAL PRIMARY KEY,
    student_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    teacher_id       INT REFERENCES users(id) ON DELETE SET NULL,
    surah_name       VARCHAR(100) NOT NULL,
    memorized_verses INT DEFAULT 0,
    total_verses     INT DEFAULT 0,
    completion_pct   DECIMAL(5,2) DEFAULT 0,
    last_updated     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, surah_name)
);

CREATE TABLE groups (
    id         SERIAL PRIMARY KEY,
    teacher_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    group_name VARCHAR(255) NOT NULL,
    description TEXT,
    gender     group_gender_enum DEFAULT 'mixed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE group_members (
    id         SERIAL PRIMARY KEY,
    group_id   INT NOT NULL REFERENCES groups(id) ON DELETE CASCADE,
    student_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(group_id, student_id)
);

CREATE TABLE messages (
    id                 SERIAL PRIMARY KEY,
    sender_id          INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    receiver_id        INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    message_text       TEXT,
    voice_message_path VARCHAR(500),
    is_read            BOOLEAN DEFAULT FALSE,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_messages_receiver ON messages(receiver_id);

CREATE TABLE activity_logs (
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

CREATE TABLE teacher_files (
    id          SERIAL PRIMARY KEY,
    teacher_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    file_name   VARCHAR(500) NOT NULL,
    file_path   VARCHAR(500) NOT NULL,
    file_type   VARCHAR(50),
    file_size   INT,
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE live_sessions (
    id             SERIAL PRIMARY KEY,
    teacher_id     INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    student_id     INT REFERENCES users(id) ON DELETE SET NULL,
    start_time     TIMESTAMP,
    end_time       TIMESTAMP,
    status         live_status_enum DEFAULT 'scheduled',
    report_text    TEXT,
    recording_path VARCHAR(500),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE calls (
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

CREATE TABLE resources (
    id          SERIAL PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    description TEXT,
    file_path   VARCHAR(500),
    url         VARCHAR(500),
    type        resource_type_enum NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE ratings (
    id         SERIAL PRIMARY KEY,
    student_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    teacher_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    rating     SMALLINT NOT NULL CHECK(rating BETWEEN 1 AND 5),
    comment    TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(student_id, teacher_id)
);

CREATE TABLE group_sessions (
    id               SERIAL PRIMARY KEY,
    teacher_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    title            VARCHAR(255) NOT NULL,
    description      TEXT,
    scheduled_at     TIMESTAMP NOT NULL,
    duration_minutes INT DEFAULT 60,
    max_students     INT DEFAULT 50,
    status           gs_status_enum DEFAULT 'scheduled',
    session_url      VARCHAR(500),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE group_session_participants (
    id         SERIAL PRIMARY KEY,
    session_id INT NOT NULL REFERENCES group_sessions(id) ON DELETE CASCADE,
    student_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    joined_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(session_id, student_id)
);

CREATE TABLE transactions (
    id           SERIAL PRIMARY KEY,
    from_user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    to_user_id   INT,
    type         tx_type_enum NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    description  TEXT,
    status       tx_status_enum DEFAULT 'completed',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE enrollments (
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

-- Seed data
INSERT INTO subscriptions(name,name_ar,price,features,duration_days) VALUES
('Standard','عادية',29.99,'Basic features',30),
('Gold','ذهبية',99.99,'Advanced features',90),
('Premium','مميزة',499.99,'All features',365);

-- Admin user (password: admin123)
INSERT INTO users(email,password_hash,full_name,user_type) VALUES
('admin@tilawa.com','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','Administrator','admin');
