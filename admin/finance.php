<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/payment.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'admin') redirect('../login.php');

$stats = get_platform_stats($pdo);

// إحصاءات إضافية
$total_wallets   = $pdo->query("SELECT COALESCE(SUM(wallet_balance),0) FROM users")->fetchColumn();
$total_students  = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='student'")->fetchColumn();
$total_teachers  = $pdo->query("SELECT COUNT(*) FROM users WHERE user_type='teacher' AND teacher_status='approved'")->fetchColumn();
$active_subs     = $pdo->query("SELECT COUNT(*) FROM user_subscriptions WHERE status='active'")->fetchColumn();
$sub_revenue     = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='platform_subscription' AND status='completed'")->fetchColumn();
$session_revenue = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='platform_fee' AND status='completed'")->fetchColumn();

// أحدث 20 معاملة
$recent = $pdo->query("
    SELECT t.*, u1.full_name as from_name, u2.full_name as to_name
    FROM transactions t
    LEFT JOIN users u1 ON t.from_user_id=u1.id
    LEFT JOIN users u2 ON t.to_user_id=u2.id
    ORDER BY t.created_at DESC LIMIT 20
")->fetchAll();

$type_ar = ['top_up'=>'شحن','session_payment'=>'جلسة','course_payment'=>'دورة','teacher_earning'=>'أرباح معلم','platform_fee'=>'عمولة المنصة','platform_subscription'=>'اشتراك'];
$active_page = 'finance';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>المالية — رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1.25rem;margin-bottom:2rem}
        .stat-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;box-shadow:var(--shadow-md)}
        .stat-value{font-size:1.8rem;font-weight:800;background:linear-gradient(135deg,var(--royal-blue),var(--soft-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .stat-label{font-size:.85rem;color:var(--slate);margin-top:.25rem}
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .tx-table{width:100%;border-collapse:collapse}
        .tx-table th{background:var(--warm-cream);padding:.7rem 1rem;text-align:right;font-size:.82rem;color:var(--slate);font-weight:600}
        .tx-table td{padding:.75rem 1rem;border-bottom:1px solid var(--sand);font-size:.88rem}
        .tx-badge{padding:.2rem .65rem;border-radius:50px;font-size:.75rem;font-weight:600}
        .type-platform_fee{background:#fee2e2;color:#991b1b}
        .type-teacher_earning{background:#fef3c7;color:#92400e}
        .type-top_up{background:#d1fae5;color:#065f46}
        .type-platform_subscription{background:#ede9fe;color:#5b21b6}
        .type-session_payment,.type-course_payment{background:#dbeafe;color:#1e40af}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include '../includes/header.php'; ?>
<div style="padding:2rem">

<h2 style="margin-bottom:1.5rem">التقرير المالي</h2>

<div class="stats-grid">
    <div class="stat-card"><div class="stat-value"><?php echo number_format($session_revenue,0,'.',','); ?> دج</div><div class="stat-label">عمولات الجلسات</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($sub_revenue,0,'.',','); ?> دج</div><div class="stat-label">إيرادات الاشتراكات</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($session_revenue+$sub_revenue,0,'.',','); ?> دج</div><div class="stat-label">إجمالي الإيرادات</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($total_wallets,0,'.',','); ?> دج</div><div class="stat-label">إجمالي المحافظ</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $active_subs; ?></div><div class="stat-label">اشتراكات نشطة</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $stats['total_transactions']; ?></div><div class="stat-label">إجمالي المعاملات</div></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
    <?php if (!empty($stats['top_teachers'])): ?>
    <div class="section-card">
        <h3>🏆 أعلى المعلمين أرباحاً</h3>
        <?php foreach ($stats['top_teachers'] as $i => $t): ?>
        <div style="display:flex;justify-content:space-between;padding:.6rem .75rem;background:var(--warm-cream);border-radius:var(--radius-md);margin-bottom:.5rem">
            <span><?php echo ($i+1) . '. ' . htmlspecialchars($t['full_name']); ?></span>
            <span style="font-weight:700;color:var(--royal-blue)"><?php echo number_format($t['total_earned'],0,'.',','); ?> دج</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php if (!empty($stats['active_students'])): ?>
    <div class="section-card">
        <h3>📊 أكثر الطلاب نشاطاً</h3>
        <?php foreach ($stats['active_students'] as $i => $s): ?>
        <div style="display:flex;justify-content:space-between;padding:.6rem .75rem;background:var(--warm-cream);border-radius:var(--radius-md);margin-bottom:.5rem">
            <span><?php echo ($i+1) . '. ' . htmlspecialchars($s['full_name']); ?></span>
            <span style="font-weight:700;color:var(--accent-green)"><?php echo $s['transaction_count']; ?> معاملة</span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<div class="section-card">
    <h3>آخر المعاملات</h3>
    <div style="overflow-x:auto">
    <table class="tx-table">
        <thead><tr><th>التاريخ</th><th>النوع</th><th>من</th><th>إلى</th><th>المبلغ</th><th>الحالة</th></tr></thead>
        <tbody>
            <?php foreach ($recent as $t): ?>
            <tr>
                <td style="white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                <td><span class="tx-badge type-<?php echo $t['type']; ?>"><?php echo $type_ar[$t['type']]??$t['type']; ?></span></td>
                <td><?php echo htmlspecialchars($t['from_name']??'النظام'); ?></td>
                <td><?php echo htmlspecialchars($t['to_name']??'المنصة'); ?></td>
                <td style="font-weight:700"><?php echo number_format($t['amount'],0,'.',','); ?> دج</td>
                <td><?php echo $t['status']==='completed'?'✅ مكتمل':($t['status']==='pending'?'⏳ معلق':'❌ فاشل'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div style="text-align:center;margin-top:1rem">
        <a href="../transactions.php" class="btn btn-outline">عرض جميع المعاملات</a>
    </div>
</div>
</div>
</div>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>
