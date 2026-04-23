<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'admin') redirect('../login.php');

$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 50;
$offset = ($page - 1) * $limit;

$filters = [
    'action_type' => trim($_GET['action_type'] ?? ''),
    'user_id'     => trim($_GET['user_id'] ?? ''),
    'date_from'   => trim($_GET['date_from'] ?? ''),
    'date_to'     => trim($_GET['date_to'] ?? ''),
];
$active_filters = array_filter($filters);

// Build WHERE clause shared by count and fetch queries
$where  = '1=1';
$params = [];
if (!empty($active_filters['action_type'])) {
    $where .= ' AND action_type = ?';
    $params[] = $active_filters['action_type'];
}
if (!empty($active_filters['user_id'])) {
    $where .= ' AND user_id = ?';
    $params[] = (int)$active_filters['user_id'];
}
if (!empty($active_filters['date_from'])) {
    $where .= ' AND created_at >= ?';
    $params[] = $active_filters['date_from'] . ' 00:00:00';
}
if (!empty($active_filters['date_to'])) {
    $where .= ' AND created_at <= ?';
    $params[] = $active_filters['date_to'] . ' 23:59:59';
}

// Filtered total (fixes wrong pagination when filtering)
try {
    $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs WHERE $where");
    $count_stmt->execute($params);
    $total = (int)$count_stmt->fetchColumn();
} catch (Throwable $e) {
    $total = 0;
}
$total_pages = $total > 0 ? (int)ceil($total / $limit) : 1;
$page = min($page, $total_pages);

// Fetch logs — inject LIMIT/OFFSET as integers directly to avoid PostgreSQL type error
try {
    $log_sql = "
        SELECT al.*, u.full_name, u.email, u.user_type
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE $where
        ORDER BY al.created_at DESC
        LIMIT " . (int)$limit . " OFFSET " . (int)(($page - 1) * $limit);
    $log_stmt = $pdo->prepare($log_sql);
    $log_stmt->execute($params);
    $logs = $log_stmt->fetchAll();
} catch (Throwable $e) {
    $logs = [];
    $log_error = $e->getMessage();
}

try {
    $action_types = $pdo->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $action_types = [];
}
$active_page = 'logs';

$action_labels = [
    'teacher_registration' => 'تسجيل معلم',
    'teacher_approved'     => 'موافقة على معلم',
    'teacher_rejected'     => 'رفض معلم',
    'teacher_rated'        => 'تقييم معلم (مع تعليق)',
    'login'                => 'تسجيل دخول',
    'logout'               => 'تسجيل خروج',
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>سجل الأنشطة — رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .logs-table{width:100%;border-collapse:collapse}
        .logs-table th{background:var(--warm-cream);padding:.7rem 1rem;text-align:right;font-size:.82rem;color:var(--slate);font-weight:600}
        .logs-table td{padding:.7rem 1rem;border-bottom:1px solid var(--sand);font-size:.88rem}
        .filter-bar{display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:flex-end}
        .filter-bar .form-group{margin:0;flex:1;min-width:140px}
        .pagination{display:flex;gap:.5rem;justify-content:center;margin-top:1.5rem;flex-wrap:wrap}
        .pag-btn{padding:.5rem 1rem;border:1px solid var(--sand);border-radius:var(--radius-md);text-decoration:none;color:var(--charcoal);font-size:.88rem;transition:var(--transition-base)}
        .pag-btn:hover,.pag-btn.active{background:var(--royal-blue);color:#fff;border-color:var(--royal-blue)}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include '../includes/header.php'; ?>
<div style="padding:2rem">

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
    <h2 style="margin:0">سجل الأنشطة (<?php echo $total; ?> سجل)</h2>
</div>

<form method="GET" class="filter-bar">
    <div class="form-group">
        <label class="form-label">نوع النشاط</label>
        <select name="action_type" class="form-select">
            <option value="">الكل</option>
            <?php foreach ($action_types as $at): ?>
            <option value="<?php echo $at; ?>" <?php echo ($filters['action_type']===$at)?'selected':''; ?>><?php echo $action_labels[$at]??$at; ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label class="form-label">من تاريخ</label>
        <input type="date" name="date_from" class="form-input" value="<?php echo $filters['date_from']; ?>">
    </div>
    <div class="form-group">
        <label class="form-label">إلى تاريخ</label>
        <input type="date" name="date_to" class="form-input" value="<?php echo $filters['date_to']; ?>">
    </div>
    <div style="display:flex;gap:.5rem;align-self:flex-end">
        <button type="submit" class="btn btn-primary">فلترة</button>
        <a href="activity_logs.php" class="btn btn-outline">إعادة</a>
    </div>
</form>

<div class="section-card">
    <?php if (isset($log_error)): ?>
    <div class="alert alert-error" style="margin-bottom:1rem;background:#fee2e2;color:#991b1b;padding:1rem;border-radius:var(--radius-md)">
        خطأ في قاعدة البيانات: <?php echo htmlspecialchars($log_error); ?>
    </div>
    <?php endif; ?>
    <?php if (count($logs) > 0): ?>
    <div style="overflow-x:auto">
    <table class="logs-table">
        <thead><tr><th>التاريخ</th><th>المستخدم</th><th>النشاط</th><th>التفاصيل</th><th>عنوان IP</th></tr></thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
                <td style="white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                <td>
                    <?php if ($log['full_name']): ?>
                    <div style="font-weight:600"><?php echo htmlspecialchars($log['full_name']); ?></div>
                    <div style="font-size:.78rem;color:var(--slate)"><?php echo htmlspecialchars($log['email']??''); ?></div>
                    <?php else: ?>
                    <span style="color:var(--slate)">النظام</span>
                    <?php endif; ?>
                </td>
                <td><span style="font-weight:600;color:var(--royal-blue)"><?php echo $action_labels[$log['action_type']]??htmlspecialchars($log['action_type']); ?></span></td>
                <td><?php echo htmlspecialchars($log['action_description']??'—'); ?></td>
                <td style="font-size:.78rem;color:var(--slate)"><?php echo htmlspecialchars($log['ip_address']??'—'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php if ($total_pages > 1): ?>
    <?php
    // Build query string preserving filters
    $pag_params = array_filter($filters);
    $pag_qs = $pag_params ? '&' . http_build_query($pag_params) : '';
    ?>
    <div class="pagination">
        <?php if ($page > 1): ?><a href="?page=<?php echo $page-1; ?><?php echo $pag_qs; ?>" class="pag-btn">← السابق</a><?php endif; ?>
        <?php for ($i=1;$i<=$total_pages;$i++): ?><a href="?page=<?php echo $i; ?><?php echo $pag_qs; ?>" class="pag-btn <?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a><?php endfor; ?>
        <?php if ($page < $total_pages): ?><a href="?page=<?php echo $page+1; ?><?php echo $pag_qs; ?>" class="pag-btn">التالي →</a><?php endif; ?>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <div style="text-align:center;padding:3rem;color:var(--slate)">لا توجد سجلات.</div>
    <?php endif; ?>
</div>
</div>
</div>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>
