<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);

if (!$user || $user['user_type'] !== 'admin') {
    redirect('../login.php');
}

// Get filter
$type_filter = $_GET['type'] ?? 'all';

// Aggregate Uploads
$uploads = [];

// 1. Teacher Files
if ($type_filter === 'all' || $type_filter === 'files') {
    $stmt = $pdo->query("
        SELECT tf.id, tf.file_name, tf.file_path, tf.file_type, tf.uploaded_at, u.full_name, u.email, 'teacher_file' as source
        FROM teacher_files tf
        JOIN users u ON tf.teacher_id = u.id
        ORDER BY tf.uploaded_at DESC
    ");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $row['display_name'] = 'ملف: ' . $row['file_name'];
        $uploads[] = $row;
    }
}

// 2. Certificates
if ($type_filter === 'all' || $type_filter === 'certificates') {
    $stmt = $pdo->query("
        SELECT id, quran_certificate as file_path, 'certificate' as file_type, created_at as uploaded_at, full_name, email, 'certificate' as source
        FROM users 
        WHERE quran_certificate IS NOT NULL
        ORDER BY created_at DESC
    ");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $row['file_name'] = basename($row['file_path']);
        $row['display_name'] = 'شهادة: ' . $row['full_name'];
        $uploads[] = $row;
    }
}

// 3. Voice Feedback (Corrections)
if ($type_filter === 'all' || $type_filter === 'feedback') {
    $stmt = $pdo->query("
        SELECT c.id, c.voice_feedback_path as file_path, 'audio/mpeg' as file_type, c.corrected_at as uploaded_at, u.full_name, u.email, 'feedback' as source
        FROM corrections c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.voice_feedback_path IS NOT NULL
        ORDER BY c.corrected_at DESC
    ");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($result as $row) {
        $row['file_name'] = basename($row['file_path']);
        $row['display_name'] = 'تصحيح صوتي';
        $uploads[] = $row;
    }
}

// Sort by date desc
usort($uploads, function($a, $b) {
    return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
});

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>كل المرفقات - رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-header">
        <div class="header-left">
            <a href="index.php" class="brand">
                <img src="../assets/images/images.png" alt="رتل معي" class="brand-logo">
                <span class="brand-name">كل المرفقات</span>
            </a>
        </div>
        <div class="header-center">
            <nav class="header-nav">
                <a href="index.php" class="nav-link">الرئيسية</a>
                <a href="users.php" class="nav-link">المستخدمون</a>
                <a href="subscriptions.php" class="nav-link">الاشتراكات</a>
                <a href="activity_logs.php" class="nav-link">سجل الأنشطة</a>
                <a href="uploads.php" class="nav-link active">المرفقات</a>
                <a href="teacher_files.php" class="nav-link">ملفات المعلمين</a>
            </nav>
        </div>
        <div class="header-right">
            <a href="../includes/auth.php?logout=1" class="logout-btn">تسجيل الخروج</a>
        </div>
    </div>
    
    <div class="dashboard-container">
        <h1 style="margin-bottom: 2rem; color: var(--dark-blue);">كل الملفات والمحتوى</h1>
        
        <div class="filters-container" style="background: white; padding: 1rem; border-radius: 8px; margin-bottom: 2rem;">
            <a href="?type=all" class="btn <?php echo $type_filter == 'all' ? 'btn-primary' : 'btn-outline'; ?>">الكل</a>
            <a href="?type=files" class="btn <?php echo $type_filter == 'files' ? 'btn-primary' : 'btn-outline'; ?>">ملفات عامة</a>
            <a href="?type=certificates" class="btn <?php echo $type_filter == 'certificates' ? 'btn-primary' : 'btn-outline'; ?>">شهادات</a>
            <a href="?type=feedback" class="btn <?php echo $type_filter == 'feedback' ? 'btn-primary' : 'btn-outline'; ?>">تصحيحات صوتية</a>
        </div>

        <div class="card">
            <table class="report-table">
                <thead>
                    <tr>
                        <th>المصدر</th>
                        <th>المعلم</th>
                        <th>الوصف / الاسم</th>
                        <th>تاريخ الرفع</th>
                        <th>معاينة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($uploads)): ?>
                        <tr><td colspan="5" style="text-align: center;">لا توجد ملفات</td></tr>
                    <?php else: ?>
                        <?php foreach ($uploads as $file): ?>
                            <tr>
                                <td>
                                    <?php 
                                    $labels = ['teacher_file' => 'ملف عام', 'certificate' => 'شهادة', 'feedback' => 'تصحيح'];
                                    echo $labels[$file['source']] ?? $file['source']; 
                                    ?>
                                </td>
                                <td>
                                    <?php echo htmlspecialchars($file['full_name']); ?>
                                    <br><small><?php echo htmlspecialchars($file['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($file['display_name']); ?></td>
                                <td><?php echo format_date($file['uploaded_at']); ?></td>
                                <td>
                                    <a href="../uploads/<?php echo htmlspecialchars($file['file_path']); ?>" target="_blank" class="btn btn-outline" style="padding: 0.2rem 0.5rem; font-size: 0.8rem;">تحميل/عرض</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
