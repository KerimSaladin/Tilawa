<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

/**
 * Login user
 */
function login_user($pdo, $email, $password) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_type'] = $user['user_type'];
        $_SESSION['user_name'] = $user['full_name'];
        
        // Create session token
        $token = generate_token();
        $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
        $stmt = $pdo->prepare("INSERT INTO sessions (user_id, session_token, expires_at) VALUES (?, ?, ?) RETURNING id");
        $stmt->execute([$user['id'], $token, $expires]);
        
        return ['success' => true, 'user' => $user];
    }
    
    return ['success' => false, 'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'];
}

/**
 * Register new user
 */
function register_user($pdo, $data) {
    // Validate input
    if (empty($data['email']) || empty($data['password']) || empty($data['full_name'])) {
        return ['success' => false, 'message' => 'جميع الحقول مطلوبة'];
    }
    
    if (!is_valid_email($data['email'])) {
        return ['success' => false, 'message' => 'البريد الإلكتروني غير صحيح'];
    }
    
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل'];
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'message' => 'كلمات المرور غير متطابقة'];
    }
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$data['email']]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'البريد الإلكتروني مستخدم بالفعل'];
    }
    
    // Validate gender
    if (empty($data['gender']) || !in_array($data['gender'], ['male', 'female'])) {
        return ['success' => false, 'message' => 'يجب اختيار الجنس'];
    }
    
    // Validate certificate for teachers
    $user_type = in_array($data['user_type'], ['teacher', 'student']) ? $data['user_type'] : 'student';
    $certificate_path = null;
    $exam_required = false;
    
    if ($user_type === 'teacher') {
        // Check if certificate is uploaded
        $has_certificate = !empty($data['quran_certificate']) && isset($data['quran_certificate']['error']) && $data['quran_certificate']['error'] === UPLOAD_ERR_OK;
        
        if ($has_certificate) {
            // Validate certificate file
            require_once __DIR__ . '/functions.php';
            
            if (!is_valid_certificate_file($data['quran_certificate'])) {
                return ['success' => false, 'message' => 'صيغة الملف غير مدعومة. يُسمح فقط بملفات الصور (JPG, PNG, GIF) أو PDF'];
            }
            
            if ($data['quran_certificate']['size'] > MAX_FILE_SIZE) {
                return ['success' => false, 'message' => 'حجم الملف كبير جداً'];
            }
        } else {
            // No certificate uploaded - teacher must pass exam
            $exam_required = true;
        }
    }
    
    // Create user first to get user_id
    $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
    
    // Set trial end date (30 days from now)
    $trial_end_date = date('Y-m-d', strtotime('+30 days'));
    
    // Set teacher status to pending for teachers
    $teacher_status = ($user_type === 'teacher') ? 'pending' : null;

    // Simulate Payment Token Generation (Pre-authorization)
    $payment_token = 'tok_' . bin2hex(random_bytes(10));
    
    $hasPaymentCol = column_exists($pdo, 'users', 'payment_method_token');
    $cols = ['email','password_hash','full_name','user_type','teacher_status','exam_required','gender','phone','quran_certificate','trial_end_date'];
    $place = array_fill(0, count($cols), '?');
    $params = [
        $data['email'],
        $password_hash,
        $data['full_name'],
        $user_type,
        $teacher_status,
        $exam_required ? true : false,
        $data['gender'],
        $data['phone'] ?? null,
        null,
        $trial_end_date
    ];
    if ($hasPaymentCol) {
        $cols[] = 'payment_method_token';
        $place[] = '?';
        $params[] = $payment_token;
    }
    $sql = "INSERT INTO users (" . implode(',', $cols) . ") VALUES (" . implode(',', $place) . ")";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute($params);
        
        $user_id = $stmt->fetchColumn();
        
        // Upload certificate if teacher and certificate provided
        if ($user_type === 'teacher' && $has_certificate) {
            $upload_result = upload_certificate_file($data['quran_certificate'], $user_id);
            
            if (!$upload_result['success']) {
                // Delete user if certificate upload failed
                $delete_stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $delete_stmt->execute([$user_id]);
                return ['success' => false, 'message' => $upload_result['message']];
            }
            
            $certificate_path = $upload_result['filename'];
            
            // Update user with certificate path
            $update_stmt = $pdo->prepare("UPDATE users SET quran_certificate = ? WHERE id = ?");
            $update_stmt->execute([$certificate_path, $user_id]);
            
            // Track teacher certificate file
            $file_path = UPLOAD_DIR . $certificate_path;
            if (file_exists($file_path)) {
                $file_size = filesize($file_path);
                $file_extension = strtolower(pathinfo($certificate_path, PATHINFO_EXTENSION));
                $file_type = in_array($file_extension, ['pdf']) ? 'application/pdf' : 'image/' . $file_extension;
                
                $stmt = $pdo->prepare("
                    INSERT INTO teacher_files (teacher_id, file_name, file_path, file_type, file_size, description)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $user_id,
                    basename($certificate_path),
                    $certificate_path,
                    $file_type,
                    $file_size,
                    'شهادة القرآن الكريم'
                ]);
            }
        }
        
        // Log teacher registration
        require_once __DIR__ . '/functions.php';
        $log_message = $exam_required 
            ? 'تسجيل معلم جديد - يحتاج اجتياز امتحان' 
            : 'تسجيل معلم جديد - مع شهادة';
        log_activity($pdo, $user_id, 'teacher_registration', $log_message, 'user', $user_id);
        
        // Auto login
        $_SESSION['user_id'] = $user_id;
        $_SESSION['user_type'] = $user_type;
        $_SESSION['user_name'] = $data['full_name'];
        
        return ['success' => true, 'user_id' => $user_id];
    } catch (PDOException $e) {
        // Delete uploaded certificate if user creation failed
        if ($certificate_path && file_exists(UPLOAD_DIR . $certificate_path)) {
            @unlink(UPLOAD_DIR . $certificate_path);
        }
        return ['success' => false, 'message' => 'حدث خطأ أثناء التسجيل: ' . $e->getMessage()];
    }
}

/**
 * Logout user
 */
function logout_user($pdo) {
    if (isset($_SESSION['user_id'])) {
        // Delete session token
        if (isset($_SESSION['session_token'])) {
            $stmt = $pdo->prepare("DELETE FROM sessions WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        }
    }
    
    session_unset();
    session_destroy();
    
    // Start new session for logout message
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Require login
 */
function require_login() {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

/**
 * Require specific role
 */
function require_role($role) {
    require_login();
    $user = get_logged_in_user($GLOBALS['pdo']);
    if (!has_role($user, $role)) {
        redirect('dashboard.php');
    }
}

/**
 * Check if user has active subscription
 */
function has_active_subscription($pdo, $user_id) {
    $stmt = $pdo->prepare("SELECT has_star, user_type FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (!$user) return false;

    // Teachers always have free access
    if ($user['user_type'] === 'teacher') return true;

    // Star users bypass all payment requirements
    if ($user['has_star']) return true;

    $subscription = get_subscription_status($pdo, $user_id);
    return $subscription !== false;
}

// Handle logout request
if (isset($_GET['logout'])) {
    logout_user($pdo);
    $_SESSION['logout_message'] = 'تم تسجيل الخروج بنجاح';
    
    // Determine correct path to login.php based on current location
    $script_path = $_SERVER['SCRIPT_NAME'];
    if (strpos($script_path, '/includes/') !== false || strpos($script_path, '\\includes\\') !== false) {
        // If we're in includes directory, go up one level
        redirect('../login.php');
    } else {
        // If we're in root, use direct path
        redirect('login.php');
    }
}
