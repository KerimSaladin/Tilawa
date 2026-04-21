<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'email' => sanitize_input($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'full_name' => sanitize_input($_POST['full_name'] ?? ''),
        'user_type' => sanitize_input($_POST['user_type'] ?? 'student'),
        'phone' => sanitize_input($_POST['phone'] ?? ''),
        'gender' => sanitize_input($_POST['gender'] ?? ''),
        'quran_certificate' => $_FILES['quran_certificate'] ?? null
    ];
    
    $result = register_user($pdo, $data);
    
    if ($result['success']) {
        redirect('dashboard.php');
    } else {
        $error = $result['message'];
    }
}

// Redirect if already logged in
if (is_logged_in()) {
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>انشاء حساب - رتل معي</title>
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
            left: -20%;
            width: 700px;
            height: 700px;
            background: radial-gradient(circle, rgba(64, 145, 108, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            animation: floatOrb 15s ease-in-out infinite;
            pointer-events: none;
        }
        
        .auth-container::after {
            content: '';
            position: absolute;
            bottom: -30%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
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
                0 0 60px rgba(64, 145, 108, 0.06);
            border: 1px solid rgba(64, 145, 108, 0.12);
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
            background-image: url('https://images.unsplash.com/photo-1728484700528-837d6dd746f1?w=800&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxwaG90by1yZWxhdGVkfDQyfHx8ZW58MHx8fHx8');
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
            background: linear-gradient(135deg, rgba(27, 67, 50, 0.75) 0%, rgba(45, 106, 79, 0.5) 100%);
            z-index: 0;
        }
        
        .auth-illustration::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.12) 0%, transparent 60%);
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
            background: rgba(255, 255, 255, 0.98);
            position: relative;
            overflow-y: auto;
            max-height: 90vh;
        }
        
        .auth-form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #40916C 0%, #5B8BD6 50%, #D4AF37 100%);
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
        
        .form-input,
        .form-select {
            width: 100%;
            padding: 1rem 1.25rem;
            border: 2px solid rgba(64, 145, 108, 0.15);
            border-radius: 14px;
            font-size: 1rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.9);
            color: #1A2A4A;
        }
        
        .form-input:focus,
        .form-select:focus {
            outline: none;
            border-color: #40916C;
            box-shadow: 
                0 0 0 4px rgba(64, 145, 108, 0.12),
                0 4px 15px rgba(64, 145, 108, 0.15),
                0 0 30px rgba(212, 175, 55, 0.06);
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 1);
        }
        
        /* Premium Radio Buttons */
        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.75rem;
            flex-wrap: wrap;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.875rem 1.5rem;
            background: linear-gradient(135deg, rgba(64, 145, 108, 0.05) 0%, rgba(91, 139, 214, 0.05) 100%);
            border: 2px solid rgba(64, 145, 108, 0.15);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .radio-option:hover {
            background: linear-gradient(135deg, rgba(64, 145, 108, 0.1) 0%, rgba(91, 139, 214, 0.1) 100%);
            border-color: rgba(64, 145, 108, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(64, 145, 108, 0.1);
        }
        
        .radio-option input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #40916C;
        }
        
        .radio-option input[type="radio"]:checked + label {
            font-weight: 700;
            color: #2D6A4F;
        }
        
        .radio-option:has(input[type="radio"]:checked) {
            background: linear-gradient(135deg, rgba(64, 145, 108, 0.12) 0%, rgba(45, 106, 79, 0.12) 100%);
            border-color: #40916C;
            box-shadow: 0 4px 15px rgba(64, 145, 108, 0.2), 0 0 20px rgba(212, 175, 55, 0.05);
        }
        
        .radio-option label {
            margin: 0;
            color: #1A2A4A;
            font-weight: 500;
            cursor: pointer;
        }
        
        /* Premium Secondary Button */
        .btn-secondary {
            background: linear-gradient(135deg, #40916C 0%, #2D6A4F 100%);
            color: white;
            border: none;
            box-shadow: 0 8px 32px rgba(64, 145, 108, 0.35);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 700;
        }
        
        .btn-secondary:hover {
            transform: translateY(-3px);
            box-shadow: 
                0 12px 40px rgba(45, 106, 79, 0.4),
                0 0 40px rgba(212, 175, 55, 0.08);
            background: linear-gradient(135deg, #2D6A4F 0%, #1B4332 100%);
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
            color: #40916C;
            text-decoration: none;
        }
        
        .text-center a:hover {
            color: #2D6A4F;
            transform: translateX(-3px);
        }
        
        small {
            color: #64748B;
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
                max-height: none;
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
                    <p style="font-size: 1.2rem;">انضم إلينا اليوم</p>
                </div>
            </div>
            <div class="auth-form-section">
                <h1 class="form-title">انشاء حساب جديد</h1>
                <p class="form-subtitle">املأ البيانات التالية للتسجيل</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label" for="full_name">الاسم واللقب</label>
                        <input type="text" id="full_name" name="full_name" class="form-input" required 
                               placeholder="أدخل اسمك الكامل" value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="email">البريد الإلكتروني</label>
                        <input type="email" id="email" name="email" class="form-input" required 
                               placeholder="أدخل بريدك الإلكتروني" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone">رقم الهاتف</label>
                        <input type="tel" id="phone" name="phone" class="form-input" 
                               placeholder="أدخل رقم هاتفك" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password">كلمة المرور</label>
                        <input type="password" id="password" name="password" class="form-input" required 
                               placeholder="أدخل كلمة المرور (6 أحرف على الأقل)" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">تأكيد كلمة المرور</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-input" required 
                               placeholder="أعد إدخال كلمة المرور" minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">الصفة: معلم / طالب</label>
                        <div class="radio-group">
                            <div class="radio-option">
                                <input type="radio" id="user_type_student" name="user_type" value="student" 
                                       <?php echo (!isset($_POST['user_type']) || $_POST['user_type'] === 'student') ? 'checked' : ''; ?>>
                                <label for="user_type_student">طالب</label>
                            </div>
                            <div class="radio-option">
                                <input type="radio" id="user_type_teacher" name="user_type" value="teacher"
                                       <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'teacher') ? 'checked' : ''; ?>>
                                <label for="user_type_teacher">معلم</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="gender">الجنس <span style="color: #ff6b6b;">*</span></label>
                        <select id="gender" name="gender" class="form-select" required>
                            <option value="">اختر الجنس</option>
                            <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>ذكر</option>
                            <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>أنثى</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="certificate-group" style="display: none;">
                        <label class="form-label" for="quran_certificate">شهادة القرآن الكريم</label>
                        <input type="file" id="quran_certificate" name="quran_certificate" class="form-input" 
                               accept="image/*,.pdf" 
                               style="padding: 0.75rem; background-color: var(--white);">
                        <small style="display: block; margin-top: 0.5rem; color: #64748B;">
                            يُسمح برفع ملفات الصور (JPG, PNG, GIF) أو PDF. الحد الأقصى للحجم: 10 ميجابايت
                            <br><strong style="color: #ff6b6b;">ملاحظة:</strong> إذا لم ترفع شهادة، يجب اجتياز امتحان القرآن (النظري والعملي) قبل الموافقة على حسابك.
                        </small>
                    </div>

                    <!-- Payment Details Section -->
                    <div class="form-group" style="margin-top: 2rem; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                        <h3 style="font-size: 1.2rem; color: #1A2A4A; margin-bottom: 1rem;">تفاصيل الدفع (تفعيل الفترة التجريبية)</h3>
                        <p style="font-size: 0.9rem; color: #64748B; margin-bottom: 1rem;">
                            لن يتم خصم أي مبلغ الآن. ستحصل على 10 أيام تجربة مجانية.
                        </p>
                        
                        <div class="form-group">
                            <label class="form-label" for="card_number">رقم البطاقة</label>
                            <input type="text" id="card_number" name="card_number" class="form-input" required 
                                   placeholder="0000 0000 0000 0000" pattern="[0-9\s]{13,19}">
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label class="form-label" for="card_expiry">تاريخ الانتهاء</label>
                                <input type="text" id="card_expiry" name="card_expiry" class="form-input" required 
                                       placeholder="MM/YY" pattern="[0-9]{2}/[0-9]{2}">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="card_cvc">رمز الأمان (CVC)</label>
                                <input type="text" id="card_cvc" name="card_cvc" class="form-input" required 
                                       placeholder="123" pattern="[0-9]{3,4}">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-secondary btn-large" style="width: 100%;">انشاء الحساب</button>
                </form>
                
                <p class="text-center mt-3">
                    لديك حساب بالفعل؟ <a href="login.php" style="color: var(--muted-blue); text-decoration: none; font-weight: 600;">تسجيل الدخول</a>
                </p>
                
                <p class="text-center mt-2">
                    <a href="index.php" style="color: var(--text-light); text-decoration: none;">← العودة للصفحة الرئيسية</a>
                </p>
            </div>
        </div>
    </div>
    <script>
        // Show/hide certificate field based on user type
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeRadios = document.querySelectorAll('input[name="user_type"]');
            const certificateGroup = document.getElementById('certificate-group');
            const certificateInput = document.getElementById('quran_certificate');
            
            function toggleCertificateField() {
                const selectedType = document.querySelector('input[name="user_type"]:checked');
                if (selectedType && selectedType.value === 'teacher') {
                    certificateGroup.style.display = 'block';
                    // Certificate is optional - if not provided, exam will be required
                    certificateInput.removeAttribute('required');
                } else {
                    certificateGroup.style.display = 'none';
                    certificateInput.removeAttribute('required');
                }
            }
            
            // Check initial state
            toggleCertificateField();
            
            // Add event listeners
            userTypeRadios.forEach(radio => {
                radio.addEventListener('change', toggleCertificateField);
            });
        });
    </script>
</body>
</html>


