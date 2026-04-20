<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/payment.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'admin') redirect('../login.php');

$total_students = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='student'")->fetchColumn();
$total_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='teacher'")->fetchColumn();
$total_recitations = $pdo->query("SELECT COUNT(*) FROM recitations")->fetchColumn();
$active_subs = $pdo->query("SELECT COUNT(*) FROM user_subscriptions WHERE status='active'")->fetchColumn();
$pending_teachers = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='teacher' AND teacher_status='pending'")->fetchColumn();
$total_revenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='platform_fee' AND status='completed'")->fetchColumn();

$recent_users = $pdo->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10")->fetchAll();
$recent_txns  = $pdo->query("
    SELECT t.*, u.full_name as from_name FROM transactions t
    LEFT JOIN users u ON t.from_user_id=u.id
    ORDER BY t.created_at DESC LIMIT 8
")->fetchAll();

$active_page = 'admin_home';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة الإدارة — رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1.25rem;margin-bottom:2rem}
        .stat-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;box-shadow:var(--shadow-md);transition:var(--transition-bounce)}
        .stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-xl)}
        .stat-value{font-size:2rem;font-weight:800;background:linear-gradient(135deg,var(--royal-blue),var(--soft-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .stat-label{font-size:.85rem;color:var(--slate);margin-top:.25rem}
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .user-row{display:flex;justify-content:space-between;align-items:center;padding:.7rem .75rem;border-radius:var(--radius-md);background:var(--warm-cream);margin-bottom:.5rem;gap:.75rem;flex-wrap:wrap}
        .role-badge{padding:.2rem .65rem;border-radius:50px;font-size:.78rem;font-weight:600}
        .role-student{background:#dbeafe;color:#1d4ed8}
        .role-teacher{background:#d1fae5;color:#065f46}
        .role-admin{background:#fef3c7;color:#92400e}
        .status-pending{background:#fee2e2;color:#991b1b}
        .status-approved{background:#d1fae5;color:#065f46}
        .admin-nav-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-bottom:2rem}
        .admin-nav-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;text-decoration:none;color:var(--charcoal);transition:var(--transition-bounce);display:flex;flex-direction:column;align-items:center;gap:.5rem}
        .admin-nav-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-xl);border-color:var(--primary-gold)}
        .admin-nav-icon{font-size:2rem}
        .admin-nav-label{font-weight:700;font-size:.9rem}
        .pending-alert{background:linear-gradient(135deg,rgba(239,68,68,.1),rgba(220,38,38,.05));border:1px solid rgba(239,68,68,.3);border-radius:var(--radius-md);padding:1rem 1.25rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include '../includes/header.php'; ?>
<div style="padding:2rem">

<?php if ($pending_teachers > 0): ?>
<div class="pending-alert">
    <span style="font-weight:700;color:#dc2626">⚠️ <?php echo $pending_teachers; ?> معلم ينتظر الموافقة</span>
    <a href="teachers.php?filter=pending" class="btn btn-primary" style="padding:.5rem 1.2rem;font-size:.9rem">مراجعة الطلبات</a>
</div>
<?php endif; ?>

<!-- إحصاء -->
<div class="stats-grid">
    <div class="stat-card"><div class="stat-value"><?php echo $total_students; ?></div><div class="stat-label">الطلاب</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $total_teachers; ?></div><div class="stat-label">المعلمون</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $total_recitations; ?></div><div class="stat-label">التلاوات</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $active_subs; ?></div><div class="stat-label">اشتراكات نشطة</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($total_revenue,0,'.',','); ?> دج</div><div class="stat-label">إيرادات المنصة</div></div>
</div>

<!-- روابط إدارية -->
<div class="admin-nav-cards">
    <a href="users.php" class="admin-nav-card"><div class="admin-nav-icon">👥</div><div class="admin-nav-label">إدارة المستخدمين</div></a>
    <a href="teachers.php" class="admin-nav-card"><div class="admin-nav-icon">🎓</div><div class="admin-nav-label">إدارة المعلمين</div></a>
    <a href="subscriptions.php" class="admin-nav-card"><div class="admin-nav-icon">💳</div><div class="admin-nav-label">الاشتراكات</div></a>
    <a href="finance.php" class="admin-nav-card"><div class="admin-nav-icon">💰</div><div class="admin-nav-label">المالية</div></a>
    <a href="activity_logs.php" class="admin-nav-card"><div class="admin-nav-icon">📋</div><div class="admin-nav-label">سجل الأنشطة</div></a>
    <a href="teacher_files.php" class="admin-nav-card"><div class="admin-nav-icon">📁</div><div class="admin-nav-label">ملفات المعلمين</div></a>
    <a href="resources_admin.php" class="admin-nav-card"><div class="admin-nav-icon">📚</div><div class="admin-nav-label">الموارد التعليمية</div></a>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
<!-- أحدث المستخدمين -->
<div class="section-card">
    <h3>أحدث المستخدمين</h3>
    <?php foreach ($recent_users as $u): ?>
    <div class="user-row">
        <div>
            <div style="font-weight:700;font-size:.95rem"><?php echo htmlspecialchars($u['full_name']); ?></div>
            <div style="font-size:.82rem;color:var(--slate)"><?php echo htmlspecialchars($u['email']); ?></div>
        </div>
        <div style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap">
            <span class="role-badge role-<?php echo $u['user_type']; ?>"><?php echo $u['user_type']==='student'?'طالب':($u['user_type']==='teacher'?'معلم':'أدمن'); ?></span>
            <?php if ($u['user_type']==='teacher'): ?>
            <span class="role-badge status-<?php echo $u['teacher_status']??'pending'; ?>"><?php echo $u['teacher_status']==='approved'?'موافق عليه':'قيد المراجعة'; ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <div style="text-align:center;margin-top:.75rem"><a href="users.php" class="btn btn-outline" style="font-size:.9rem;padding:.5rem 1.25rem">عرض الكل</a></div>
</div>

<!-- آخر المعاملات -->
<div class="section-card">
    <h3>آخر المعاملات</h3>
    <?php
    $type_ar = ['top_up'=>'شحن','session_payment'=>'جلسة','course_payment'=>'دورة','teacher_earning'=>'أرباح','platform_fee'=>'عمولة','platform_subscription'=>'اشتراك'];
    foreach ($recent_txns as $t): ?>
    <div class="user-row">
        <div>
            <div style="font-weight:700;font-size:.9rem"><?php echo htmlspecialchars($t['from_name']??'النظام'); ?></div>
            <div style="font-size:.8rem;color:var(--slate)"><?php echo date('d/m/Y H:i',strtotime($t['created_at'])); ?></div>
        </div>
        <div style="text-align:left">
            <div style="font-weight:700;color:var(--royal-blue)"><?php echo number_format($t['amount'],0,'.',','); ?> دج</div>
            <div style="font-size:.78rem;color:var(--slate)"><?php echo $type_ar[$t['type']]??$t['type']; ?></div>
        </div>
    </div>
    <?php endforeach; ?>
    <div style="text-align:center;margin-top:.75rem"><a href="../transactions.php" class="btn btn-outline" style="font-size:.9rem;padding:.5rem 1.25rem">عرض الكل</a></div>
</div>
</div>

</div>
</div>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>
