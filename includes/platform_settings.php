<?php
require_once __DIR__ . '/config.php';

/**
 * Get platform setting value
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value
 */
function get_platform_setting($pdo, $key, $default = null) {
    $stmt = $pdo->prepare("SELECT setting_value, setting_type FROM platform_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return $default;
    }
    
    $value = $result['setting_value'];
    $type = $result['setting_type'];
    
    switch ($type) {
        case 'number':
        case 'percentage':
            return is_numeric($value) ? (float)$value : $default;
        case 'boolean':
            return filter_var($value, FILTER_VALIDATE_BOOLEAN);
        default:
            return $value;
    }
}

/**
 * Update platform setting
 * @param PDO $pdo Database connection
 * @param string $key Setting key
 * @param mixed $value New value
 * @return bool Success status
 */
function update_platform_setting($pdo, $key, $value) {
    $stmt = $pdo->prepare("SELECT setting_type FROM platform_settings WHERE setting_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    
    if (!$result) {
        return false;
    }
    
    $type = $result['setting_type'];
    
    // Validate value based on type
    switch ($type) {
        case 'number':
        case 'percentage':
            if (!is_numeric($value) || $value < 0) {
                return false;
            }
            if ($type === 'percentage' && $value > 100) {
                return false;
            }
            break;
        case 'boolean':
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
            break;
    }
    
    $stmt = $pdo->prepare("UPDATE platform_settings SET setting_value = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = ?");
    return $stmt->execute([$value, $key]);
}

/**
 * Get all platform settings
 * @param PDO $pdo Database connection
 * @return array All settings
 */
function get_all_platform_settings($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM platform_settings WHERE is_editable = TRUE ORDER BY setting_key");
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get dynamic platform commission rate
 * @param PDO $pdo Database connection
 * @return float Commission percentage
 */
function get_platform_commission_rate($pdo) {
    return get_platform_setting($pdo, 'platform_commission_rate', 15.0);
}

/**
 * Get subscription discount rate
 * @param PDO $pdo Database connection
 * @return float Discount percentage
 */
function get_subscription_discount_rate($pdo) {
    return get_platform_setting($pdo, 'subscription_discount_rate', 15.0);
}

/**
 * Check if subscriptions are enabled
 * @param PDO $pdo Database connection
 * @return bool
 */
function are_subscriptions_enabled($pdo) {
    return get_platform_setting($pdo, 'enable_subscriptions', true);
}

/**
 * Get trial period days
 * @param PDO $pdo Database connection
 * @return int
 */
function get_trial_period_days($pdo) {
    return (int)get_platform_setting($pdo, 'trial_period_days', 7);
}

/**
 * Get wallet top-up limits
 * @param PDO $pdo Database connection
 * @return array [min, max]
 */
function get_wallet_limits($pdo) {
    return [
        'min' => get_platform_setting($pdo, 'min_top_up_amount', 100),
        'max' => get_platform_setting($pdo, 'max_top_up_amount', 50000)
    ];
}
?>
