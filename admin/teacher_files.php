<?php
require_once '../includes/config.php';
require_once '../includes/storage.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);

if (!$user || $user['user_type'] !== 'admin') {
    redirect('../login.php');
}

// Get teacher filter
$teacher_id = !empty($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : null;

// Get teacher files
$files = get_teacher_files($pdo, $teacher_id);

// Get all teachers for filter
$stmt = $pdo->query("SELECT id, full_name, email FROM users WHERE user_type = 'teacher' ORDER BY full_name");
$teachers = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ملفات المعلمين - رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .filters-container {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .file-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 8px;
            margin: 0.5rem 0;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="header-left">
            <a href="index.php" class="brand">
                <img src="../assets/images/images.png" alt="رتل معي" class="brand-logo">
                <span class="brand-name">ملفات المعلمين</span>
            </a>
        </div>
        <div class="header-center">
            <nav class="header-nav">
                <a href="index.php" class="nav-link">الرئيسية</a>
                <a href="users.php" class="nav-link">المستخدمون</a>
                <a href="subscriptions.php" class="nav-link">الاشتراكات</a>
                <a href="activity_logs.php" class="nav-link">سجل الأنشطة</a>
                <a href="teacher_files.php" class="nav-link active">ملفات المعلمين</a>
            </nav>
        </div>
        <div class="header-right">
            <div class="user-menu-container">
                <button class="icon-btn user-icon" id="userMenuBtn" title="الملف الشخصي"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-4 3-7 7-7s7 3 7 7"/></svg></button>
                <div class="user-menu-dropdown" id="userMenuDropdown">
                    <a href="../dashboard.php" class="dropdown-item">العودة للوحة المستخدم</a>
                    <a href="../includes/auth.php?logout=1" class="dropdown-item logout-link">تسجيل الخروج</a>
                </div>
            </div>
            <a href="../includes/auth.php?logout=1" class="logout-btn" title="تسجيل الخروج" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M19 21V3"/></svg></a>
        </div>
    </div>
    
    <div class="dashboard-container">
        <h1 style="margin-bottom: 2rem; color: var(--dark-blue);">ملفات المعلمين</h1>
        
        <div class="filters-container">
            <form method="GET" action="">
                <div style="display: flex; gap: 1rem; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label class="form-label">المعلم</label>
                        <select name="teacher_id" class="form-select">
                            <option value="">الكل</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>" <?php echo $teacher_id == $teacher['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['full_name'] . ' (' . $teacher['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">تصفية</button>
                    <a href="teacher_files.php" class="btn btn-outline">إعادة تعيين</a>
                </div>
            </form>
        </div>
        
        <div class="card">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>المعلم</th>
                        <th>اسم الملف</th>
                        <th>نوع الملف</th>
                        <th>الحجم</th>
                        <th>تاريخ الرفع</th>
                        <th>الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($files)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 2rem;">لا توجد ملفات</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($files as $file): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($file['full_name']); ?>
                                    <br><small style="color: var(--text-light);"><?php echo htmlspecialchars($file['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                                <td><?php echo htmlspecialchars($file['file_type'] ?? '-'); ?></td>
                                <td><?php echo $file['file_size'] ? number_format($file['file_size'] / 1024, 2) . ' KB' : '-'; ?></td>
                                <td><?php echo format_date($file['uploaded_at']); ?></td>
                                <td>
                                    <a href="<?php
                                        $fp = $file['file_path'];
                                        if (str_starts_with($fp, 'http')) {
                                            echo htmlspecialchars($fp);
                                        } else {
                                            echo '../serve_resource.php?file=' . urlencode(basename($fp));
                                        }
                                    ?>" target="_blank" class="btn btn-outline" style="padding: 0.5rem 1rem; font-size: 0.85rem;">
                                        عرض
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script src="../assets/js/dashboard.js"></script>
</body>
</html>

