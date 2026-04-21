<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/payment.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$transactions = get_transaction_history($pdo, $user['id'], $user['user_type'], $limit, $offset);

if ($user['user_type'] === 'admin') {
    $stmt = $pdo->query("SELECT COUNT(*) FROM transactions");
} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE from_user_id=? OR to_user_id=?");
    $stmt->execute([$user['id'], $user['id']]);
}
$total = $stmt->fetchColumn();
$total_pages = ceil($total / $limit);

$platform_stats = ($user['user_type'] === 'admin') ? get_platform_stats($pdo) : null;

$type_labels = [
    'top_up' => 'شحن المحفظة',
    'session_payment' => 'دفع جلسة',
    'course_payment' => 'دفع دورة',
    'teacher_earning' => 'أرباح معلم',
    'platform_fee' => 'عمولة المنصة',
    'platform_subscription' => 'اشتراك المنصة',
];
$active_page = 'transactions';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المعاملات المالية — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-size:1.1rem;font-weight:700;color:var(--charcoal);margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .tx-table{width:100%;border-collapse:collapse}
        .tx-table th{background:var(--warm-cream);padding:.75rem 1rem;text-align:right;font-size:.85rem;color:var(--slate);font-weight:600}
        .tx-table td{padding:.75rem 1rem;border-bottom:1px solid var(--sand);font-size:.9rem;vertical-align:middle}
        .tx-table tr:hover td{background:var(--warm-cream)}
        .tx-pos{color:#16a34a;font-weight:700}
        .tx-neg{color:#dc2626;font-weight:700}
        .tx-badge{padding:.2rem .65rem;border-radius:50px;font-size:.78rem;font-weight:600;white-space:nowrap}
        .type-top_up{background:#d1fae5;color:#065f46}
        .type-session_payment,.type-course_payment{background:#dbeafe;color:#1e40af}
        .type-teacher_earning{background:#fef3c7;color:#92400e}
        .type-platform_fee{background:#fee2e2;color:#991b1b}
        .type-platform_subscription{background:#ede9fe;color:#5b21b6}
        .pagination{display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap}
        .pag-btn{padding:.5rem 1rem;border:1px solid var(--sand);border-radius:var(--radius-md);text-decoration:none;color:var(--charcoal);font-size:.9rem;transition:var(--transition-base)}
        .pag-btn:hover,.pag-btn.active{background:var(--royal-blue);color:#fff;border-color:var(--royal-blue)}
        .stats-mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.25rem;margin-bottom:2rem}
        .stat-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;box-shadow:var(--shadow-md)}
        .stat-value{font-size:1.8rem;font-weight:800;background:linear-gradient(135deg,var(--royal-blue),var(--soft-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .stat-label{font-size:.85rem;color:var(--slate);margin-top:.25rem}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include 'includes/header.php'; ?>
<div style="padding:2rem">

<!-- إحصاء الأدمن -->
<?php if ($platform_stats): ?>
<div class="stats-mini">
    <div class="stat-card">
        <div class="stat-value"><?php echo number_format($platform_stats['total_revenue'],0,'.',','); ?> دج</div>
        <div class="stat-label">إجمالي إيرادات المنصة</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?php echo $platform_stats['total_transactions']; ?></div>
        <div class="stat-label">إجمالي المعاملات</div>
    </div>
</div>

<?php if (!empty($platform_stats['top_teachers'])): ?>
<div class="section-card">
    <h3>🏆 أعلى المعلمين أرباحاً</h3>
    <div style="display:flex;flex-direction:column;gap:.5rem">
    <?php foreach ($platform_stats['top_teachers'] as $i => $t): ?>
    <div style="display:flex;justify-content:space-between;padding:.6rem .75rem;background:var(--warm-cream);border-radius:var(--radius-md)">
        <span><?php echo ($i+1) . '. ' . htmlspecialchars($t['full_name']); ?></span>
        <span style="font-weight:700;color:var(--royal-blue)"><?php echo number_format($t['total_earned'],0,'.',','); ?> دج</span>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- جدول المعاملات -->
<div class="section-card">
    <h3>📋 سجل المعاملات (<?php echo $total; ?> معاملة)</h3>
    <?php if (count($transactions) > 0): ?>
    <div style="overflow-x:auto">
    <table class="tx-table">
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>النوع</th>
                <th>البيان</th>
                <?php if ($user['user_type']==='admin'): ?><th>من</th><th>إلى</th><?php endif; ?>
                <th>المبلغ</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
            <tr>
                <td style="white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                <td><span class="tx-badge type-<?php echo $t['type']; ?>"><?php echo $type_labels[$t['type']] ?? $t['type']; ?></span></td>
                <td style="max-width:220px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?php echo htmlspecialchars($t['description'] ?? '—'); ?></td>
                <?php if ($user['user_type']==='admin'): ?>
                <td><?php echo htmlspecialchars($t['from_user_name'] ?? 'النظام'); ?></td>
                <td><?php echo htmlspecialchars($t['to_user_name'] ?? 'المنصة'); ?></td>
                <?php endif; ?>
                <td>
                    <?php
                    $out = ($t['from_user_id'] == $user['id'] && $t['type'] !== 'top_up' && $t['type'] !== 'teacher_earning');
                    if ($user['user_type'] === 'admin') {
                        echo '<span>' . number_format($t['amount'],0,'.',',') . ' دج</span>';
                    } elseif ($out) {
                        echo '<span class="tx-neg">-' . number_format($t['amount'],0,'.',',') . ' دج</span>';
                    } else {
                        echo '<span class="tx-pos">+' . number_format($t['amount'],0,'.',',') . ' دج</span>';
                    }
                    ?>
                </td>
                <td><?php
                    $st = ['completed'=>'✅','pending'=>'⏳','failed'=>'❌'];
                    echo ($st[$t['status']] ?? '') . ' ' . ($t['status']==='completed'?'مكتمل':($t['status']==='pending'?'معلق':'فاشل'));
                ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <!-- صفحات -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?>" class="pag-btn">← السابق</a><?php endif; ?>
        <?php for ($i=1; $i<=$total_pages; $i++): ?>
        <a href="?page=<?php echo $i; ?>" class="pag-btn <?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?>" class="pag-btn">التالي →</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div style="text-align:center;padding:3rem;color:var(--slate)">لا توجد معاملات بعد.</div>
    <?php endif; ?>
</div>
</div>
</div>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
