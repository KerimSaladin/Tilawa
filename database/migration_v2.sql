-- ============================================================
-- Migration v2 — Tilawa Platform
-- ============================================================

-- 1. Ensure gender column exists on users
ALTER TABLE users ADD COLUMN IF NOT EXISTS gender VARCHAR(10) DEFAULT NULL;

-- 2. Lifetime subscriptions: allow duration_days = 0
-- (0 = no expiration / lifetime)
-- No schema change needed, it's already an INT column.
-- Just ensure existing data is valid:
UPDATE subscriptions SET duration_days = 36500 WHERE duration_days IS NULL;

-- 3. get_subscription_status fix: handle lifetime subs (duration_days=0)
-- handled in PHP, no SQL needed.

-- 4. Ensure enrollments table has sessions_paid column
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS sessions_paid INT DEFAULT 0;
ALTER TABLE enrollments ADD COLUMN IF NOT EXISTS discount_applied SMALLINT DEFAULT 0;

-- 5. Index for gender-based teacher filtering
CREATE INDEX IF NOT EXISTS idx_users_gender ON users(gender);
CREATE INDEX IF NOT EXISTS idx_users_type_status ON users(user_type, teacher_status, is_active);

-- 6. Ensure wallet_balance has default
ALTER TABLE users ALTER COLUMN wallet_balance SET DEFAULT 0;

-- ============================================================
-- Run this once on your Supabase / PostgreSQL database.
-- ============================================================
