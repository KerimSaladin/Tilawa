<?php
// Helper functions

/**
 * Sanitize input data
 */
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current logged in user data
 */
function get_logged_in_user($pdo) {
    if (!is_logged_in()) {
        return null;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Check if user has specific role
 */
function has_role($user, $role) {
    return $user && $user['user_type'] === $role;
}

/**
 * Redirect to a page
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Format date in Arabic-friendly format
 */
function format_date($date) {
    return date('Y-m-d H:i', strtotime($date));
}

/**
 * Get Arabic month name
 */
function get_arabic_month($month) {
    $months = [
        1 => 'يناير', 2 => 'فبراير', 3 => 'مارس', 4 => 'أبريل',
        5 => 'مايو', 6 => 'يونيو', 7 => 'يوليو', 8 => 'أغسطس',
        9 => 'سبتمبر', 10 => 'أكتوبر', 11 => 'نوفمبر', 12 => 'ديسمبر'
    ];
    return $months[(int)$month] ?? $month;
}

/**
 * Validate email
 */
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Generate random token
 */
function generate_token($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Get subscription status
 */
function get_subscription_status($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT us.*, s.name_ar, s.name 
        FROM user_subscriptions us
        JOIN subscriptions s ON us.subscription_id = s.id
        WHERE us.user_id = ? AND us.status = 'active' AND us.end_date >= CURRENT_DATE
        ORDER BY us.end_date DESC
        LIMIT 1
    ");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

/**
 * Check if user is in trial period
 */
function is_trial_active($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT trial_end_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['trial_end_date']) {
        return false;
    }
    
    $trial_end = strtotime($user['trial_end_date']);
    $today = strtotime(date('Y-m-d'));
    
    return $trial_end >= $today;
}

/**
 * Check if user has active subscription or trial
 */
function has_active_access($pdo, $user_id) {
    // الاشتراك اختياري — يمنح خصم 15% فقط
    // جميع المستخدمين المسجلين لديهم وصول كامل
    $stmt = $pdo->prepare("SELECT user_type FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    return (bool)$user; // أي مستخدم نشط لديه وصول
}

/**
 * التحقق من وجود اشتراك نشط — للحصول على خصم 15% فقط
 */
function has_subscription_discount($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT has_star FROM users WHERE id=?");
    $stmt->execute([$user_id]);
    $u = $stmt->fetch();
    if ($u && $u['has_star']) return true;
    $sub = get_subscription_status($pdo, $user_id);
    return (bool)$sub;
}

/**
 * Get trial days remaining
 */
function get_trial_days_remaining($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT trial_end_date FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    if (!$user || !$user['trial_end_date']) {
        return 0;
    }
    
    $trial_end = strtotime($user['trial_end_date']);
    $today = strtotime(date('Y-m-d'));
    $days_remaining = floor(($trial_end - $today) / (60 * 60 * 24));
    
    return max(0, $days_remaining);
}

/**
 * Check if audio file is valid
 */
function is_valid_audio_file($file) {
    $allowed_types = ['audio/mpeg', 'audio/mp3', 'audio/wav', 'audio/ogg', 'audio/webm'];
    $allowed_extensions = ['mp3', 'wav', 'ogg', 'webm'];
    
    $file_type = $file['type'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    return in_array($file_type, $allowed_types) && in_array($file_extension, $allowed_extensions);
}

/**
 * Upload audio file
 */
function upload_audio_file($file, $user_id) {
    if (!is_valid_audio_file($file)) {
        return ['success' => false, 'message' => 'صيغة الملف غير مدعومة'];
    }
    
    if ($file['size'] > 100 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 100MB)'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'recitation_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'message' => 'فشل رفع الملف'];
}

/**
 * Check if certificate file is valid
 */
function is_valid_certificate_file($file) {
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];
    
    $file_type = $file['type'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    return in_array($file_type, $allowed_types) && in_array($file_extension, $allowed_extensions);
}

/**
 * Upload certificate file
 */
function upload_certificate_file($file, $user_id) {
    if (!is_valid_certificate_file($file)) {
        return ['success' => false, 'message' => 'صيغة الملف غير مدعومة. يُسمح فقط بملفات الصور (JPG, PNG, GIF) أو PDF'];
    }
    
    if ($file['size'] > 100 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 100MB)'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'certificate_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'message' => 'فشل رفع الملف'];
}

/**
 * Check if video file is valid
 */
function is_valid_video_file($file) {
    $allowed_types = ['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo'];
    $allowed_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
    
    $file_type = $file['type'];
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    return in_array($file_type, $allowed_types) && in_array($file_extension, $allowed_extensions);
}

/**
 * Upload video file
 */
function upload_video_file($file, $user_id) {
    if (!is_valid_video_file($file)) {
        return ['success' => false, 'message' => 'صيغة الملف غير مدعومة'];
    }
    
    if ($file['size'] > 100 * 1024 * 1024) {
        return ['success' => false, 'message' => 'حجم الملف كبير جداً (الحد الأقصى 100MB)'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'recitation_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $extension;
    $filepath = UPLOAD_DIR . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename, 'filepath' => $filepath];
    }
    
    return ['success' => false, 'message' => 'فشل رفع الملف'];
}

/**
 * Get Surah list (common Surahs)
 */
function get_surah_list() {
    return [
        'الفاتحة' => 'الفاتحة',
        'البقرة' => 'البقرة',
        'آل عمران' => 'آل عمران',
        'النساء' => 'النساء',
        'المائدة' => 'المائدة',
        'الأنعام' => 'الأنعام',
        'الأعراف' => 'الأعراف',
        'الأنفال' => 'الأنفال',
        'التوبة' => 'التوبة',
        'يونس' => 'يونس',
        'هود' => 'هود',
        'يوسف' => 'يوسف',
        'الرعد' => 'الرعد',
        'إبراهيم' => 'إبراهيم',
        'الحجر' => 'الحجر',
        'النحل' => 'النحل',
        'الإسراء' => 'الإسراء',
        'الكهف' => 'الكهف',
        'مريم' => 'مريم',
        'طه' => 'طه',
        'الأنبياء' => 'الأنبياء',
        'الحج' => 'الحج',
        'المؤمنون' => 'المؤمنون',
        'النور' => 'النور',
        'الفرقان' => 'الفرقان',
        'يس' => 'يس',
        'الصافات' => 'الصافات',
        'ص' => 'ص',
        'الزمر' => 'الزمر',
        'غافر' => 'غافر',
        'فصلت' => 'فصلت',
        'الشورى' => 'الشورى',
        'الزخرف' => 'الزخرف',
        'الدخان' => 'الدخان',
        'الجاثية' => 'الجاثية',
        'الأحقاف' => 'الأحقاف',
        'محمد' => 'محمد',
        'الفتح' => 'الفتح',
        'الحجرات' => 'الحجرات',
        'ق' => 'ق',
        'الذاريات' => 'الذاريات',
        'الطور' => 'الطور',
        'النجم' => 'النجم',
        'القمر' => 'القمر',
        'الرحمن' => 'الرحمن',
        'الواقعة' => 'الواقعة',
        'الحديد' => 'الحديد',
        'المجادلة' => 'المجادلة',
        'الحشر' => 'الحشر',
        'الممتحنة' => 'الممتحنة',
        'الصف' => 'الصف',
        'الجمعة' => 'الجمعة',
        'المنافقون' => 'المنافقون',
        'التغابن' => 'التغابن',
        'الطلاق' => 'الطلاق',
        'التحريم' => 'التحريم',
        'الملك' => 'الملك',
        'القلم' => 'القلم',
        'الحاقة' => 'الحاقة',
        'المعارج' => 'المعارج',
        'نوح' => 'نوح',
        'الجن' => 'الجن',
        'المزمل' => 'المزمل',
        'المدثر' => 'المدثر',
        'القيامة' => 'القيامة',
        'الإنسان' => 'الإنسان',
        'المرسلات' => 'المرسلات',
        'النبأ' => 'النبأ',
        'النازعات' => 'النازعات',
        'عبس' => 'عبس',
        'التكوير' => 'التكوير',
        'الانفطار' => 'الانفطار',
        'المطففين' => 'المطففين',
        'الانشقاق' => 'الانشقاق',
        'البروج' => 'البروج',
        'الطارق' => 'الطارق',
        'الأعلى' => 'الأعلى',
        'الغاشية' => 'الغاشية',
        'الفجر' => 'الفجر',
        'البلد' => 'البلد',
        'الشمس' => 'الشمس',
        'الليل' => 'الليل',
        'الضحى' => 'الضحى',
        'الشرح' => 'الشرح',
        'التين' => 'التين',
        'العلق' => 'العلق',
        'القدر' => 'القدر',
        'البينة' => 'البينة',
        'الزلزلة' => 'الزلزلة',
        'العاديات' => 'العاديات',
        'القارعة' => 'القارعة',
        'التكاثر' => 'التكاثر',
        'العصر' => 'العصر',
        'الهمزة' => 'الهمزة',
        'الفيل' => 'الفيل',
        'قريش' => 'قريش',
        'الماعون' => 'الماعون',
        'الكوثر' => 'الكوثر',
        'الكافرون' => 'الكافرون',
        'النصر' => 'النصر',
        'المسد' => 'المسد',
        'الإخلاص' => 'الإخلاص',
        'الفلق' => 'الفلق',
        'الناس' => 'الناس'
    ];
}

/**
 * Log platform activity
 */
function log_activity($pdo, $user_id, $action_type, $action_description, $resource_type = null, $resource_id = null) {
    try {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action_type, action_description, resource_type, resource_id, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $action_type,
            $action_description,
            $resource_type,
            $resource_id,
            $ip_address
        ]);
    } catch (Exception $e) {
        // Silently fail logging to not break main functionality
        error_log('Failed to log activity: ' . $e->getMessage());
    }
}

/**
 * Check if teacher is approved and can work
 */
function is_teacher_approved($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT teacher_status, exam_required, exam_passed FROM users WHERE id = ? AND user_type = 'teacher'");
    $stmt->execute([$user_id]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        return false;
    }
    
    // Teacher must be approved
    if ($teacher['teacher_status'] !== 'approved') {
        return false;
    }
    
    // If exam is required, it must be passed
    if ($teacher['exam_required'] && !$teacher['exam_passed']) {
        return false;
    }
    
    return true;
}

/**
 * Get activity logs
 */
function get_activity_logs($pdo, $limit = 100, $offset = 0, $filters = []) {
    $where = "1=1";
    $params = [];
    
    if (!empty($filters['action_type'])) {
        $where .= " AND action_type = ?";
        $params[] = $filters['action_type'];
    }
    
    if (!empty($filters['user_id'])) {
        $where .= " AND user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (!empty($filters['date_from'])) {
        $where .= " AND created_at >= ?";
        $params[] = $filters['date_from'];
    }
    
    if (!empty($filters['date_to'])) {
        $where .= " AND created_at <= ?";
        $params[] = $filters['date_to'];
    }
    
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare("
        SELECT al.*, u.full_name, u.email, u.user_type
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE $where
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get teacher files
 */
function get_teacher_files($pdo, $teacher_id = null) {
    if ($teacher_id) {
        $stmt = $pdo->prepare("
            SELECT tf.*, u.full_name, u.email
            FROM teacher_files tf
            JOIN users u ON tf.teacher_id = u.id
            WHERE tf.teacher_id = ?
            ORDER BY tf.uploaded_at DESC
        ");
        $stmt->execute([$teacher_id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT tf.*, u.full_name, u.email
            FROM teacher_files tf
            JOIN users u ON tf.teacher_id = u.id
            ORDER BY tf.uploaded_at DESC
        ");
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

function column_exists($pdo, $table, $column) {
    $stmt = $pdo->prepare("SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = 'public' AND table_name = ? AND column_name = ?");
    $stmt->execute([$table, $column]);
    $row = $stmt->fetch();
    return isset($row['c']) && (int)$row['c'] > 0;
}

/**
 * Update teacher online status
 */
function update_teacher_online_status($pdo, $teacher_id) {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_online = TRUE, last_seen = CURRENT_TIMESTAMP 
        WHERE id = ? AND user_type = 'teacher'
    ");
    return $stmt->execute([$teacher_id]);
}

/**
 * Check if teacher is online
 */
function is_teacher_online($pdo, $teacher_id) {
    $stmt = $pdo->prepare("
        SELECT is_online, last_seen 
        FROM users 
        WHERE id = ? AND user_type = 'teacher'
    ");
    $stmt->execute([$teacher_id]);
    $teacher = $stmt->fetch();
    
    if (!$teacher) {
        return false;
    }
    
    // Check if last_seen is within the last 2 minutes
    if ($teacher['last_seen']) {
        $last_seen = strtotime($teacher['last_seen']);
        $two_minutes_ago = time() - 120; // 2 minutes ago
        return $last_seen >= $two_minutes_ago;
    }
    
    return $teacher['is_online'] == 1;
}

/**
 * Mark teacher as offline
 */
function mark_teacher_offline($pdo, $teacher_id) {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_online = 0 
        WHERE id = ? AND user_type = 'teacher'
    ");
    return $stmt->execute([$teacher_id]);
}

/**
 * Clean up offline teachers (mark as offline if last_seen > 2 minutes)
 */
function cleanup_offline_teachers($pdo) {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET is_online = 0 
        WHERE user_type = 'teacher' 
        AND is_online = 1 
        AND last_seen < NOW() - INTERVAL '2 minutes'
    ");
    return $stmt->execute();
}

/**
 * Add or update teacher rating
 */
function add_teacher_rating($pdo, $student_id, $teacher_id, $rating, $comment = null) {
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        return ['success' => false, 'message' => 'التقييم يجب أن يكون بين 1 و 5'];
    }
    
    // Check if student has had at least one session with this teacher
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as session_count 
        FROM recitations 
        WHERE student_id = ? AND teacher_id = ? AND status IN ('reviewed', 'approved')
    ");
    $stmt->execute([$student_id, $teacher_id]);
    $session_count = $stmt->fetch()['session_count'];
    
    if ($session_count == 0) {
        return ['success' => false, 'message' => 'يمكن فقط تقييم المعلمين الذين أتممت معهم جلسة واحدة على الأقل'];
    }
    
    // Check if rating already exists and update or insert
    $stmt = $pdo->prepare("
        SELECT id FROM ratings 
        WHERE student_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$student_id, $teacher_id]);
    $existing = $stmt->fetch();
    
    if ($existing) {
        // Update existing rating
        $stmt = $pdo->prepare("
            UPDATE ratings 
            SET rating = ?, comment = ?, created_at = CURRENT_TIMESTAMP 
            WHERE student_id = ? AND teacher_id = ?
        ");
        $success = $stmt->execute([$rating, $comment, $student_id, $teacher_id]);
        $action = 'updated';
    } else {
        // Insert new rating
        $stmt = $pdo->prepare("
            INSERT INTO ratings (student_id, teacher_id, rating, comment) 
            VALUES (?, ?, ?, ?)
        ");
        $success = $stmt->execute([$student_id, $teacher_id, $rating, $comment]);
        $action = 'added';
    }
    
    if ($success) {
        return ['success' => true, 'message' => "تم $action التقييم بنجاح"];
    } else {
        return ['success' => false, 'message' => 'فشل حفظ التقييم'];
    }
}

/**
 * Get teacher average rating
 */
function get_teacher_average_rating($pdo, $teacher_id) {
    $stmt = $pdo->prepare("
        SELECT AVG(rating) as avg_rating, COUNT(*) as total_ratings 
        FROM ratings 
        WHERE teacher_id = ?
    ");
    $stmt->execute([$teacher_id]);
    $result = $stmt->fetch();
    
    if (!$result || !$result['total_ratings']) {
        return ['avg_rating' => 0, 'total_ratings' => 0];
    }
    
    return [
        'avg_rating' => round($result['avg_rating'], 1),
        'total_ratings' => $result['total_ratings']
    ];
}

/**
 * Get student's rating for a specific teacher
 */
function get_student_teacher_rating($pdo, $student_id, $teacher_id) {
    $stmt = $pdo->prepare("
        SELECT rating, comment, created_at 
        FROM ratings 
        WHERE student_id = ? AND teacher_id = ?
    ");
    $stmt->execute([$student_id, $teacher_id]);
    return $stmt->fetch();
}

/**
 * Get all ratings for a teacher
 */
function get_teacher_ratings($pdo, $teacher_id, $limit = 20, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as student_name 
        FROM ratings r
        JOIN users u ON r.student_id = u.id
        WHERE r.teacher_id = ?
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$teacher_id, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Check if student can rate teacher
 */
function can_student_rate_teacher($pdo, $student_id, $teacher_id) {
    // Check if student has had completed sessions with teacher
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as session_count 
        FROM recitations 
        WHERE student_id = ? AND teacher_id = ? AND status IN ('reviewed', 'approved')
    ");
    $stmt->execute([$student_id, $teacher_id]);
    $session_count = $stmt->fetch()['session_count'];
    
    return $session_count > 0;
}

/**
 * Create group session
 */
function create_group_session($pdo, $teacher_id, $title, $description, $scheduled_at, $duration_minutes = 60, $max_students = 50, $session_url = null) {
    $stmt = $pdo->prepare("
        INSERT INTO group_sessions (teacher_id, title, description, scheduled_at, duration_minutes, max_students, session_url) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    return $stmt->execute([$teacher_id, $title, $description, $scheduled_at, $duration_minutes, $max_students, $session_url]);
}

/**
 * Get teacher's group sessions
 */
function get_teacher_group_sessions($pdo, $teacher_id, $limit = 20, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT gs.*, 
               (SELECT COUNT(*) FROM group_session_participants gsp WHERE gsp.session_id = gs.id) as registered_count
        FROM group_sessions gs
        WHERE gs.teacher_id = ?
        ORDER BY gs.scheduled_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$teacher_id, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Get all available group sessions for students
 */
function get_available_group_sessions($pdo, $limit = 20, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT gs.*, u.full_name as teacher_name,
               (SELECT COUNT(*) FROM group_session_participants gsp WHERE gsp.session_id = gs.id) as registered_count
        FROM group_sessions gs
        JOIN users u ON gs.teacher_id = u.id
        WHERE gs.status IN ('scheduled', 'live')
        AND gs.scheduled_at >= NOW() - INTERVAL '2 hours'
        ORDER BY gs.scheduled_at ASC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Register student for group session
 */
function register_student_for_session($pdo, $student_id, $session_id) {
    // Check if session exists and is available
    $stmt = $pdo->prepare("
        SELECT gs.*, 
               (SELECT COUNT(*) FROM group_session_participants gsp WHERE gsp.session_id = gs.id) as registered_count
        FROM group_sessions gs
        WHERE gs.id = ? AND gs.status = 'scheduled'
    ");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    
    if (!$session) {
        return ['success' => false, 'message' => 'الجلسة غير متاحة للتسجيل'];
    }
    
    if ($session['registered_count'] >= $session['max_students']) {
        return ['success' => false, 'message' => 'الجلسة ممتلئة'];
    }
    
    // Check if student is already registered
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM group_session_participants 
        WHERE student_id = ? AND session_id = ?
    ");
    $stmt->execute([$student_id, $session_id]);
    $already_registered = $stmt->fetch()['count'] > 0;
    
    if ($already_registered) {
        return ['success' => false, 'message' => 'أنت مسجل بالفعل في هذه الجلسة'];
    }
    
    // Register student
    $stmt = $pdo->prepare("
        INSERT INTO group_session_participants (session_id, student_id) 
        VALUES (?, ?)
    ");
    $success = $stmt->execute([$session_id, $student_id]);
    
    if ($success) {
        return ['success' => true, 'message' => 'تم التسجيل في الجلسة بنجاح'];
    } else {
        return ['success' => false, 'message' => 'فشل التسجيل في الجلسة'];
    }
}

/**
 * Check if student is registered for session
 */
function is_student_registered_for_session($pdo, $student_id, $session_id) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM group_session_participants 
        WHERE student_id = ? AND session_id = ?
    ");
    $stmt->execute([$student_id, $session_id]);
    return $stmt->fetch()['count'] > 0;
}

/**
 * Update group session status
 */
function update_group_session_status($pdo, $session_id, $status) {
    if (!in_array($status, ['scheduled', 'live', 'ended'])) {
        return false;
    }
    
    $stmt = $pdo->prepare("
        UPDATE group_sessions 
        SET status = ? 
        WHERE id = ?
    ");
    return $stmt->execute([$status, $session_id]);
}

/**
 * Get session participants
 */
function get_session_participants($pdo, $session_id) {
    $stmt = $pdo->prepare("
        SELECT gsp.*, u.full_name, u.email
        FROM group_session_participants gsp
        JOIN users u ON gsp.student_id = u.id
        WHERE gsp.session_id = ?
        ORDER BY gsp.joined_at ASC
    ");
    $stmt->execute([$session_id]);
    return $stmt->fetchAll();
}
