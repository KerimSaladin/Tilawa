<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'admin') redirect('../login.php');

// إنشاء/تعديل باقة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name_ar      = trim($_POST['name_ar'] ?? '');
        $price        = (float)($_POST['price'] ?? 0);
        $duration     = (int)($_POST['duration_days'] ?? 30);
        $features     = trim($_POST['features'] ?? '');
        if ($name_ar && $price > 0) {
            $pdo->prepare("INSERT INTO subscriptions (name_ar,name,price,duration_days,features) VALUES (?,?,?,?,?)")
                ->execute([$name_ar, $name_ar, $price, $duration, $features]);
            $_SESSION['flash_success'] = 'تمت إضافة الباقة بنجاح.';
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['pkg_id'];
        $pdo->prepare("DELETE FROM subscriptions WHERE id=?")->execute([$id]);
        $_SESSION['flash_success'] = 'تم حذف الباقة.';
    } elseif ($action === 'revoke') {
        $sub_id = (int)$_POST['sub_id'];
        $pdo->prepare("UPDATE user_subscriptions SET status='cancelled' WHERE id=?")->execute([$sub_id]);
        $_SESSION['flash_success'] = 'تم إلغاء الاشتراك.';
    }
    redirect('subscriptions.php');
}

$packages = $pdo->query("SELECT * FROM subscriptions ORDER BY price ASC")->fetchAll();
$active_subs = $pdo->query("
    SELECT us.*, u.full_name, u.email, s.name_ar
    FROM user_subscriptions us
    JOIN users u ON us.user_id=u.id
    JOIN subscriptions s ON us.subscription_id=s.id
    WHERE us.status='active' AND us.end_date >= CURRENT_DATE
    ORDER BY us.created_at DESC
")->fetchAll();

$active_page = 'subscriptions';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>الاشتراكات — رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .pkg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin-bottom:1.5rem}
        .pkg-card{background:var(--warm-cream);border:1px solid var(--sand);border-radius:var(--radius-lg);padding:1.25rem;position:relative}
        .pkg-price{font-size:1.8rem;font-weight:800;color:var(--royal-blue);margin:.25rem 0}
        .pkg-name{font-weight:700;font-size:1rem;color:var(--charcoal)}
        .pkg-period{font-size:.82rem;color:var(--slate)}
        .subs-table{width:100%;border-collapse:collapse}
        .subs-table th{background:var(--warm-cream);padding:.7rem 1rem;text-align:right;font-size:.82rem;color:var(--slate);font-weight:600}
        .subs-table td{padding:.75rem 1rem;border-bottom:1px solid var(--sand);font-size:.88rem;vertical-align:middle}
        .btn-sm{padding:.35rem .85rem;font-size:.8rem;border-radius:var(--radius-md)}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include '../includes/header.php'; ?>
<div style="padding:2rem">

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success" style="margin-bottom:1rem"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<h2 style="margin-bottom:1.5rem">إدارة الاشتراكات</h2>

<!-- الباقات الحالية -->
<div class="section-card">
    <h3>باقات الاشتراك (<?php echo count($packages); ?>)</h3>
    <div class="pkg-grid">
        <?php foreach ($packages as $p): ?>
        <div class="pkg-card">
            <div class="pkg-name"><?php echo htmlspecialchars($p['name_ar']); ?></div>
            <div class="pkg-price"><?php echo number_format($p['price'],0,'.',','); ?> دج</div>
            <div class="pkg-period"><?php echo $p['duration_days']; ?> يوم</div>
            <?php if ($p['features']): ?><div style="font-size:.8rem;color:var(--slate);margin-top:.5rem"><?php echo htmlspecialchars($p['features']); ?></div><?php endif; ?>
            <form method="POST" style="margin-top:.75rem" onsubmit="return confirm('هل تريد حذف هذه الباقة؟')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="pkg_id" value="<?php echo $p['id']; ?>">
                <button class="btn btn-outline btn-sm" type="submit" style="border-color:#dc2626;color:#dc2626;width:100%">حذف الباقة</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- إضافة باقة جديدة -->
    <div style="background:var(--warm-cream);border-radius:var(--radius-lg);padding:1.25rem;margin-top:1rem">
        <h4 style="margin-bottom:1rem;font-weight:700">+ إضافة باقة جديدة</h4>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
                <div class="form-group" style="margin:0">
                    <label class="form-label">اسم الباقة (عربي)</label>
                    <input type="text" name="name_ar" class="form-input" placeholder="مثال: شهري" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">السعر (دج)</label>
                    <input type="number" name="price" class="form-input" min="1" placeholder="0" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">المدة (يوم)</label>
                    <input type="number" name="duration_days" class="form-input" value="30" min="1" required>
                </div>
            </div>
            <div class="form-group" style="margin:0 0 1rem">
                <label class="form-label">المميزات (مفصولة بفاصلة)</label>
                <input type="text" name="features" class="form-input" placeholder="مثال: وصول كامل, خصم 15%">
            </div>
            <button type="submit" class="btn btn-primary">إضافة الباقة</button>
        </form>
    </div>
</div>

<!-- الاشتراكات النشطة -->
<div class="section-card">
    <h3>الاشتراكات النشطة (<?php echo count($active_subs); ?>)</h3>
    <?php if (count($active_subs) > 0): ?>
    <div style="overflow-x:auto">
    <table class="subs-table">
        <thead><tr><th>المستخدم</th><th>الباقة</th><th>تاريخ البداية</th><th>تاريخ الانتهاء</th><th>إجراء</th></tr></thead>
        <tbody>
            <?php foreach ($active_subs as $s): ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?php echo htmlspecialchars($s['full_name']); ?></div>
                    <div style="font-size:.8rem;color:var(--slate)"><?php echo htmlspecialchars($s['email']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($s['name_ar']); ?></td>
                <td><?php echo date('d/m/Y', strtotime($s['start_date'])); ?></td>
                <td><?php echo date('d/m/Y', strtotime($s['end_date'])); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('هل تريد إلغاء هذا الاشتراك؟')">
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="sub_id" value="<?php echo $s['id']; ?>">
                        <button class="btn btn-outline btn-sm" type="submit" style="border-color:#dc2626;color:#dc2626">إلغاء</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:2rem;color:var(--slate)">لا توجد اشتراكات نشطة حالياً.</div>
    <?php endif; ?>
</div>
</div>
</div>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>
