-- Platform Settings Table for Dynamic Configuration
-- This table allows admin to control commission rates and other platform settings

CREATE TABLE IF NOT EXISTS platform_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT NOT NULL,
    setting_type ENUM('number', 'percentage', 'text', 'boolean') DEFAULT 'text',
    description TEXT,
    is_editable BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- Insert default platform settings
INSERT IGNORE INTO platform_settings (setting_key, setting_value, setting_type, description) VALUES
('platform_commission_rate', '15', 'percentage', 'Percentage commission taken from each teacher payment'),
('subscription_discount_rate', '15', 'percentage', 'Discount percentage for users with active subscriptions'),
('min_top_up_amount', '100', 'number', 'Minimum amount for wallet top-up'),
('max_top_up_amount', '50000', 'number', 'Maximum amount for wallet top-up'),
('default_wallet_balance', '5000', 'number', 'Default wallet balance for new users'),
('enable_subscriptions', 'true', 'boolean', 'Enable subscription system'),
('trial_period_days', '7', 'number', 'Trial period duration in days for new users');

-- Update existing subscriptions table to ensure proper structure
ALTER TABLE subscriptions 
ADD COLUMN IF NOT EXISTS description TEXT AFTER features,
ADD COLUMN IF NOT EXISTS is_active BOOLEAN DEFAULT TRUE AFTER duration_days,
ADD COLUMN IF NOT EXISTS sort_order INT DEFAULT 0 AFTER is_active;

-- Create index for active subscriptions
ALTER TABLE subscriptions ADD INDEX IF NOT EXISTS idx_active (is_active, sort_order);
