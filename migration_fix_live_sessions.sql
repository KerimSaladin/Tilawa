-- ══════════════════════════════════════════════════════════════════════════
-- Migration: Fix live_sessions for available-slot support
-- Run this once on your Supabase SQL editor
-- ══════════════════════════════════════════════════════════════════════════

-- 1. Add 'available' to live_status_enum
--    (required by live_session.php: INSERT ... status='available')
ALTER TYPE live_status_enum ADD VALUE IF NOT EXISTS 'available' BEFORE 'scheduled';

-- 2. Allow student_id = 0 for unbooked slots (NOT NULL kept, sentinel 0 used)
--    The FK reference to users(id) must be dropped first since 0 is not a valid user.
--    Easiest fix: drop the FK and allow any integer.
ALTER TABLE live_sessions DROP CONSTRAINT IF EXISTS live_sessions_student_id_fkey;

-- 3. Set existing NULL student_ids to 0 (if any were inserted before this migration)
UPDATE live_sessions SET student_id = 0 WHERE student_id IS NULL;

-- 4. Re-enforce NOT NULL (it was already NOT NULL; this is a no-op but explicit)
ALTER TABLE live_sessions ALTER COLUMN student_id SET NOT NULL;
ALTER TABLE live_sessions ALTER COLUMN student_id SET DEFAULT 0;
