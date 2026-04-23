<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/platform_settings.php';

/**
 * Format amount with thousands separator for Algerian Dinar
 * @param float $amount Amount to format
 * @return string Formatted amount with DZD
 */
function format_currency($amount) {
    return number_format($amount, 0, '.', ',') . ' دج';
}

/**
 * Get subscription plans available
 * @return array Available subscription plans
 */
function get_subscription_plans() {
    return [
        'monthly' => [
            'name' => 'Monthly',
            'name_ar' => 'DZD',
            'price' => PLAN_MONTHLY_PRICE,
            'duration' => 30,
            'description' => 'Full access to all features',
            'badge' => null
        ],
        'yearly' => [
            'name' => 'Yearly',
            'name_ar' => 'DZD',
            'price' => PLAN_YEARLY_PRICE,
            'duration' => 365,
            'description' => 'Full access to all features + 72% savings',
            'badge' => 'Save 72%'
        ],
        'lifetime' => [
            'name' => 'Lifetime',
            'name_ar' => 'DZD',
            'price' => PLAN_LIFETIME_PRICE,
            'duration' => 36500,
            'description' => 'Lifetime access + best value',
            'badge' => 'Best Value'
        ]
    ];
}

/**
 * Process payment with proper transaction handling
 * @param PDO $pdo Database connection
 * @param int $from_user_id User making payment
 * @param int|null $to_user_id User receiving payment (null for platform)
 * @param string $type Payment type
 * @param float $amount Payment amount
 * @param string $description Payment description
 * @param bool $apply_subscription_discount Whether to apply subscription discount
 * @return array Result with success status and message
 */
function process_payment($pdo, $from_user_id, $to_user_id, $type, $amount, $description, $apply_subscription_discount = false) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get user info
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$from_user_id]);
        $from_user = $stmt->fetch();
        
        if (!$from_user) {
            throw new Exception('المستخدم غير موجود');
        }
        
        // Check if user has star (exempt from payment)
        if ($from_user['has_star']) {
            // Still log the transaction but as 0 amount
            $stmt = $pdo->prepare("
                INSERT INTO transactions (from_user_id, to_user_id, type, amount, description, status)
                VALUES (?, ?, ?, ?, ?, 'completed')
            ");
            $stmt->execute([$from_user_id, $to_user_id, $type, 0, $description . ' (Star User - Free)']);
            
            $pdo->commit();
            return ['success' => true, 'message' => 'تم الدفع (مستخدم مميز)', 'amount_paid' => 0];
        }
        
        // Apply subscription discount if applicable
        $final_amount = $amount;
        $discount_amount = 0;
        
        if ($apply_subscription_discount && $to_user_id) {
            if (has_active_platform_subscription($pdo, $from_user_id)) {
                $discount_rate = get_subscription_discount_rate($pdo);
                $discount_amount = $amount * ($discount_rate / 100);
                $final_amount = $amount - $discount_amount;
            }
        }
        
        // Check wallet balance
        if ($from_user['wallet_balance'] < $final_amount) {
            throw new Exception('رصيد المحفظة غير كافٍ');
        }
        
        // Deduct from user wallet
        $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?");
        $stmt->execute([$final_amount, $from_user_id]);
        
        // Process payment based on type
        if ($to_user_id && in_array($type, ['session_payment', 'course_payment'])) {
            // Teacher payment - split between teacher and platform
            $commission_rate = get_platform_commission_rate($pdo);
            $platform_fee = $final_amount * ($commission_rate / 100);
            $teacher_earning = $final_amount - $platform_fee;
            
            // Add to teacher wallet
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$teacher_earning, $to_user_id]);
            
            // Log teacher earning
            $stmt = $pdo->prepare("
                INSERT INTO transactions (from_user_id, to_user_id, type, amount, description, status)
                VALUES (?, ?, 'teacher_earning', ?, ?, 'completed')
            ");
            $stmt->execute([$from_user_id, $to_user_id, $teacher_earning, $description]);
            
            // Log platform fee
            $stmt = $pdo->prepare("
                INSERT INTO transactions (from_user_id, to_user_id, type, amount, description, status)
                VALUES (?, NULL, 'platform_fee', ?, ?, 'completed')
            ");
            $stmt->execute([$from_user_id, $platform_fee, "Platform fee from: " . $description]);
            
        } elseif ($type === 'platform_subscription') {
            // Platform subscription - no recipient
            // Log subscription payment
            $stmt = $pdo->prepare("
                INSERT INTO transactions (from_user_id, to_user_id, type, amount, description, status)
                VALUES (?, NULL, ?, ?, ?, 'completed')
            ");
            $stmt->execute([$from_user_id, $type, $final_amount, $description]);
            
        } elseif ($type === 'top_up') {
            // Top up - add to user wallet
            $stmt = $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            $stmt->execute([$final_amount, $from_user_id]);
            
            // Log top up
            $stmt = $pdo->prepare("
                INSERT INTO transactions (from_user_id, to_user_id, type, amount, description, status)
                VALUES (?, NULL, ?, ?, ?, 'completed')
            ");
            $stmt->execute([$from_user_id, $type, $final_amount, $description]);
        }
        
        // Log the main payment transaction
        $stmt = $pdo->prepare("
            INSERT INTO transactions (from_user_id, to_user_id, type, amount, description, status)
            VALUES (?, ?, ?, ?, ?, 'completed')
        ");
        $stmt->execute([$from_user_id, $to_user_id, $type, $final_amount, $description]);
        
        $pdo->commit();
        
        return [
            'success' => true, 
            'message' => 'تم الدفع بنجاح',
            'amount_paid' => $final_amount,
            'discount_applied' => $discount_amount,
            'platform_fee' => isset($platform_fee) ? $platform_fee : 0
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Check if user has active platform subscription
 */
function has_active_platform_subscription($pdo, $user_id) {
    // Check star users bypass all payment requirements
    $stmt = $pdo->prepare("SELECT has_star FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();
    if ($u && $u['has_star']) return true;

    $stmt = $pdo->prepare("
        SELECT us.id FROM user_subscriptions us
        WHERE us.user_id=? AND us.status='active' AND us.end_date >= CURRENT_DATE
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch() !== false;
}

/**
 * Get user's active subscription
 */
function get_user_subscription($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT us.*, s.name_ar, s.name FROM user_subscriptions us
        JOIN subscriptions s ON us.subscription_id=s.id
        WHERE us.user_id=? AND us.status='active' AND us.end_date >= CURRENT_DATE
        ORDER BY us.end_date DESC LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Create platform subscription for user
 */
function create_platform_subscription($pdo, $user_id, $plan_type = 'monthly', $amount = null) {
    // Legacy function kept for compatibility — new code uses api/subscribe.php
    return true;
}

/**
 * Get user wallet balance
 */
function get_wallet_balance($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT wallet_balance FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $result = $stmt->fetch();
    return $result ? $result['wallet_balance'] : 0;
}

/**
 * Top up user wallet
 */
function top_up_wallet($pdo, $user_id, $amount) {
    $description = "Wallet top-up: " . format_currency($amount);
    
    return process_payment($pdo, $user_id, null, 'top_up', $amount, $description, false);
}

/**
 * Get teacher pricing
 */
function get_teacher_pricing($pdo, $teacher_id) {
    $stmt = $pdo->prepare("SELECT session_price, price_per_course FROM users WHERE id = ? AND user_type = 'teacher'");
    $stmt->execute([$teacher_id]);
    return $stmt->fetch();
}

/**
 * Update teacher pricing
 */
function update_teacher_pricing($pdo, $teacher_id, $session_price, $course_price) {
    // Validate prices
    if (($session_price && $session_price < 100) ||
        ($course_price && $course_price < 100)) {
        return ['success' => false, 'message' => 'السعر يجب أن يكون 100 دج على الأقل'];
    }
    
    $stmt = $pdo->prepare("
        UPDATE users 
        SET session_price = ?, price_per_course = ? 
        WHERE id = ? AND user_type = 'teacher'
    ");
    
    if ($stmt->execute([$session_price, $course_price, $teacher_id])) {
        return ['success' => true, 'message' => 'تم تحديث الأسعار بنجاح'];
    }
    
    return ['success' => false, 'message' => 'فشل تحديث الأسعار'];
}

/**
 * Create enrollment
 */
function create_enrollment($pdo, $student_id, $teacher_id, $type, $sessions_paid, $amount_paid, $discount_applied) {
    $stmt = $pdo->prepare("
        INSERT INTO enrollments (student_id, teacher_id, type, sessions_paid, amount_paid, discount_applied)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    return $stmt->execute([$student_id, $teacher_id, $type, $sessions_paid, $amount_paid, $discount_applied]);
}

/**
 * Get user enrollments
 */
function get_user_enrollments($pdo, $user_id, $user_type) {
    if ($user_type === 'student') {
        $stmt = $pdo->prepare("
            SELECT e.*, u.full_name as teacher_name 
            FROM enrollments e
            JOIN users u ON e.teacher_id = u.id
            WHERE e.student_id = ?
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$user_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT e.*, u.full_name as student_name 
            FROM enrollments e
            JOIN users u ON e.student_id = u.id
            WHERE e.teacher_id = ?
            ORDER BY e.created_at DESC
        ");
        $stmt->execute([$user_id]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Get transaction history
 */
function get_transaction_history($pdo, $user_id, $user_type, $limit = 50, $offset = 0) {
    if ($user_type === 'admin') {
        // Admin sees all transactions
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u_from.full_name as from_user_name,
                   u_to.full_name as to_user_name
            FROM transactions t
            LEFT JOIN users u_from ON t.from_user_id = u_from.id
            LEFT JOIN users u_to ON t.to_user_id = u_to.id
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
    } else {
        // Students and teachers see their own transactions
        $stmt = $pdo->prepare("
            SELECT t.*, 
                   u_from.full_name as from_user_name,
                   u_to.full_name as to_user_name
            FROM transactions t
            LEFT JOIN users u_from ON t.from_user_id = u_from.id
            LEFT JOIN users u_to ON t.to_user_id = u_to.id
            WHERE t.from_user_id = ? OR t.to_user_id = ?
            ORDER BY t.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $user_id, $limit, $offset]);
    }
    
    return $stmt->fetchAll();
}

/**
 * Get platform revenue statistics
 */
function get_platform_stats($pdo) {
    $stats = [];
    
    // Total platform revenue
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_revenue 
        FROM transactions 
        WHERE type = 'platform_fee' AND status = 'completed'
    ");
    $stmt->execute();
    $stats['total_revenue'] = $stmt->fetch()['total_revenue'] ?: 0;
    
    // Total transactions
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions WHERE status = 'completed'");
    $stmt->execute();
    $stats['total_transactions'] = $stmt->fetch()['total'];
    
    // Top earning teachers
    $stmt = $pdo->prepare("
        SELECT u.full_name, SUM(t.amount) as total_earned
        FROM transactions t
        JOIN users u ON t.to_user_id = u.id
        WHERE t.type = 'teacher_earning' AND t.status = 'completed'
        GROUP BY u.id, u.full_name
        ORDER BY total_earned DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['top_teachers'] = $stmt->fetchAll();
    
    // Most active students
    $stmt = $pdo->prepare("
        SELECT u.full_name, COUNT(t.id) as transaction_count, SUM(t.amount) as total_spent
        FROM transactions t
        JOIN users u ON t.from_user_id = u.id
        WHERE t.type IN ('session_payment', 'course_payment') AND t.status = 'completed'
        GROUP BY u.id, u.full_name
        ORDER BY transaction_count DESC
        LIMIT 10
    ");
    $stmt->execute();
    $stats['active_students'] = $stmt->fetchAll();
    
    return $stats;
}
?>
