<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

// Check for logout message
if (isset($_SESSION['logout_message'])) {
    $success = $_SESSION['logout_message'];
    unset($_SESSION['logout_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $result = login_user($pdo, $email, $password);
    
    if ($result['success']) {
        $user = $result['user'];
        if ($user['user_type'] === 'admin') {
            redirect('admin/index.php');
        } else {
            redirect('dashboard.php');
        }
    } else {
        $error = $result['message'];
    }
}

// Redirect if already logged in
if (is_logged_in()) {
    $user = get_logged_in_user($pdo);
    if ($user['user_type'] === 'admin') {
        redirect('admin/index.php');
    } else {
        redirect('dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Premium Auth Container */
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: linear-gradient(135deg, #FDFCF9 0%, rgba(232, 244, 253, 0.5) 50%, #FDFCF9 100%);
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Gradient Orbs */
        .auth-container::before {
            content: '';
            position: absolute;
            top: -30%;
            right: -20%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            animation: floatOrb 15s ease-in-out infinite;
            pointer-events: none;
        }
        
        .auth-container::after {
            content: '';
            position: absolute;
            bottom: -30%;
            left: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(91, 139, 214, 0.12) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(50px);
            animation: floatOrb 12s ease-in-out infinite reverse;
            pointer-events: none;
        }
        
        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }
        
        /* Premium Auth Card */
        .auth-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 28px;
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.12),
                0 10px 30px rgba(0, 0, 0, 0.08),
                0 0 0 1px rgba(255, 255, 255, 0.5),
                0 0 60px rgba(212, 175, 55, 0.08);
            border: 1px solid rgba(212, 175, 55, 0.15);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Premium Illustration Side */
        .auth-illustration {
            background-image: url('https://images.unsplash.com/photo-1710367446064-0815d9a9f9c6?w=800&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDN8fHxlbnwwfHx8fHw%3D');
            background-size: cover;
            background-position: center;
            padding: 3rem;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-illustration::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, rgba(26, 42, 74, 0.7) 0%, rgba(45, 74, 138, 0.5) 100%);
            z-index: 0;
        }
        
        .auth-illustration::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 60%);
            animation: rotate 20s linear infinite;
            pointer-events: none;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .illustration-content {
            text-align: center;
            color: white;
            position: relative;
            z-index: 1;
        }
        
        .illustration-content h2 {
            color: white;
            font-size: 2.8rem;
            font-weight: 900;
            margin-bottom: 1rem;
            text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            letter-spacing: -0.02em;
            background: linear-gradient(135deg, #fff 0%, #F5E6C3 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .illustration-content p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.3rem;
            text-shadow: 0 2px 12px rgba(0, 0, 0, 0.3);
            font-weight: 500;
        }
        
        /* Premium Form Section */
        .auth-form-section {
            padding: 3.5rem 3rem;
            position: relative;
            background: rgba(255, 255, 255, 0.98);
        }
        
        .auth-form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #D4AF37 0%, #5B8BD6 50%, #40916C 100%);
        }
        
        .form-title {
            font-size: 2.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, #1A2A4A 0%, #2D4A8A 50%, #5B8BD6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.75rem;
            text-align: center;
            letter-spacing: -0.02em;
        }
        
        .form-subtitle {
            text-align: center;
            color: #64748B;
            margin-bottom: 2.5rem;
            font-size: 1.1rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: #1A2A4A;
            font-size: 0.95rem;
        }
        
        .form-input {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(212, 175, 55, 0.15);
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
        }
        
        .form-input:focus {
            outline: none;
            border-color: #5B8BD6;
            box-shadow: 
                0 0 0 4px rgba(91, 139, 214, 0.12),
                0 4px 15px rgba(91, 139, 214, 0.15),
                0 0 30px rgba(212, 175, 55, 0.08);
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #5B8BD6 0%, #2D4A8A 100%);
            border: none;
            box-shadow: 0 8px 32px rgba(91, 139, 214, 0.35);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 12px 40px rgba(45, 74, 138, 0.4),
                0 0 40px rgba(212, 175, 55, 0.1);
            background: linear-gradient(135deg, #2D4A8A 0%, #1A2A4A 100%);
        }
        
        /* Premium Alerts */
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 14px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            animation: slideDown 0.3s ease-out;
            backdrop-filter: blur(10px);
        }
        
        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(220, 38, 38, 0.1) 100%);
            border: 1px solid rgba(220, 38, 38, 0.2);
            color: #DC2626;
        }
        
        .alert-success {
            background: linear-gradient(135deg, rgba(64, 145, 108, 0.1) 0%, rgba(45, 106, 79, 0.1) 100%);
            border: 1px solid rgba(64, 145, 108, 0.2);
            color: #2D6A4F;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Premium Links */
        .text-center a {
            transition: all 0.3s ease;
            font-weight: 600;
            color: #5B8BD6;
            text-decoration: none;
        }
        
        .text-center a:hover {
            color: #2D4A8A;
            transform: translateX(-3px);
        }
        
        @media (max-width: 768px) {
            .auth-card {
                grid-template-columns: 1fr;
            }
            
            .auth-illustration {
                display: none;
            }
            
            .auth-form-section {
                padding: 2.5rem 2rem;
            }
            
            .form-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-illustration">
                <div class="illustration-content">
                    <h2>رتل معي</h2>
                    <p style="font-size: 1.2rem;">مرحباً بعودتك</p>
                </div>
            </div>
            <div class="auth-form-section">
                <h1 class="form-title">تسجيل الدخول</h1>
                <p class="form-subtitle">سجل دخولك للوصول إلى حسابك</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label" for="email">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email" class="form-input" required 
                               placeholder="أدخل بريدك الإلكتروني" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">كلمة المرور</label>
                        <input type="password" id="password" name="password" class="form-input" required 
                               placeholder="أدخل كلمة المرور">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-large" style="width: 100%;">تسجيل الدخول</button>
                </form>
                
                <p class="text-center mt-3">
                    ليس لديك حساب؟ <a href="register.php" style="color: var(--muted-blue); text-decoration: none;">انشاء حساب جديد</a>
                </p>
                
                <p class="text-center mt-2">
                    <a href="index.php" style="color: var(--text-light); text-decoration: none;">← العودة للصفحة الرئيسية</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>

