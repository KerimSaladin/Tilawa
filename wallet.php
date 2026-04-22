<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/payment.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

// المعلمون لا يحتاجون المحفظة للاشتراك — لكن يرون أرباحهم
// شحن المحفظة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['top_up'])) {
    $amount = (float)$_POST['amount'];
    if (!in_array($amount, TOP_UP_AMOUNTS)) {
        $_SESSION['flash_error'] = 'المبلغ غير صحيح';
    } else {
        // شحن مباشر — لا نطرح ثم نضيف بل نضيف مباشرة
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$amount, $user['id']]);
        $pdo->prepare("INSERT INTO transactions (from_user_id, type, amount, description, status) VALUES (?,?,?,?,'completed')")
            ->execute([$user['id'], 'top_up', $amount, 'شحن المحفظة: ' . number_format($amount, 0, '.', ',') . ' دج']);
        $_SESSION['flash_success'] = 'تم شحن المحفظة بنجاح!';
    }
    redirect('wallet.php');
}

$wallet_balance = get_wallet_balance($pdo, $user['id']);
$transactions   = get_transaction_history($pdo, $user['id'], $user['user_type'], 15);
$active_page = 'wallet';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المحفظة — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .wallet-hero{background:linear-gradient(135deg,var(--deep-blue),var(--royal-blue));border-radius:var(--radius-xl);padding:2.5rem;color:#fff;text-align:center;margin-bottom:2rem;box-shadow:var(--shadow-xl)}
        .wallet-hero h2{color:#fff;font-size:1.1rem;opacity:.8;margin-bottom:.5rem}
        .wallet-amount{font-size:3rem;font-weight:900;margin:.5rem 0}
        .wallet-currency{font-size:1.2rem;opacity:.7}
        .topup-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-top:1.5rem}
        .topup-btn{background:white;border:2px solid var(--sand);border-radius:var(--radius-lg);padding:1.5rem;cursor:pointer;text-align:center;transition:var(--transition-bounce);font-family:inherit}
        .topup-btn:hover{border-color:var(--soft-blue);transform:translateY(-4px);box-shadow:var(--shadow-md)}
        .topup-btn .amount{display:block;font-size:1.5rem;font-weight:800;color:var(--royal-blue)}
        .topup-btn .label{font-size:.85rem;color:var(--slate);margin-top:.2rem}
        .tx-table{width:100%;border-collapse:collapse}
        .tx-table th{background:var(--warm-cream);padding:.75rem 1rem;text-align:right;font-size:.85rem;color:var(--slate);font-weight:600}
        .tx-table td{padding:.75rem 1rem;border-bottom:1px solid var(--sand);font-size:.9rem}
        .tx-table tr:last-child td{border-bottom:none}
        .tx-pos{color:#16a34a;font-weight:700}
        .tx-neg{color:#dc2626;font-weight:700}
        .tx-badge{padding:.2rem .65rem;border-radius:50px;font-size:.78rem;font-weight:600}
        .type-top_up{background:#d1fae5;color:#065f46}
        .type-session_payment,.type-course_payment{background:#dbeafe;color:#1e40af}
        .type-teacher_earning{background:#fef3c7;color:#92400e}
        .type-platform_fee{background:#fee2e2;color:#991b1b}
        .type-platform_subscription{background:#ede9fe;color:#5b21b6}
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-size:1.1rem;font-weight:700;color:var(--charcoal);margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include 'includes/header.php'; ?>
<div style="padding:2rem">

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success"><?php echo $_SESSION['flash_success']; unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
<div class="alert alert-error"><?php echo $_SESSION['flash_error']; unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<!-- رصيد المحفظة -->
<div class="wallet-hero">
    <h2>رصيد المحفظة</h2>
    <div class="wallet-amount"><?php echo number_format($wallet_balance, 0, '.', ','); ?></div>
    <div class="wallet-currency">دج (دينار جزائري)</div>
    <?php if ($user['user_type'] === 'student'): ?>
    <p style="margin-top:.75rem;opacity:.7;font-size:.9rem">استخدم رصيدك لدفع رسوم المعلمين والجلسات</p>
    <?php else: ?>
    <p style="margin-top:.75rem;opacity:.7;font-size:.9rem">أرباحك من الجلسات والدورات تُضاف هنا مباشرة</p>
    <?php endif; ?>
</div>

<?php if ($user['user_type'] === 'student'): ?>
<!-- شحن المحفظة -->
<div class="section-card">
    <h3>💳 شحن المحفظة</h3>
    <p style="color:var(--slate);font-size:.9rem;margin-bottom:1rem">اختر المبلغ الذي تريد إضافته (نظام دفع تجريبي)</p>
    <form method="POST">
        <input type="hidden" name="top_up" value="1">
        <div class="topup-grid">
            <?php foreach (TOP_UP_AMOUNTS as $amount): ?>
            <button type="submit" name="amount" value="<?php echo $amount; ?>" class="topup-btn">
                <span class="amount">+<?php echo number_format($amount, 0, '.', ','); ?></span>
                <span class="label">دج</span>
            </button>
            <?php endforeach; ?>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- سجل المعاملات -->
<div class="section-card">
    <h3>📋 آخر المعاملات</h3>
    <?php
    $type_labels = [
        'top_up' => 'شحن',
        'session_payment' => 'دفع جلسة',
        'course_payment' => 'دفع دورة',
        'teacher_earning' => 'أرباح معلم',
        'platform_fee' => 'عمولة المنصة',
        'platform_subscription' => 'اشتراك',
    ];
    ?>
    <?php if (count($transactions) > 0): ?>
    <div style="overflow-x:auto">
    <table class="tx-table">
        <thead>
            <tr>
                <th>التاريخ</th>
                <th>النوع</th>
                <th>البيان</th>
                <th>المبلغ</th>
                <th>الحالة</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $t): ?>
            <tr>
                <td style="white-space:nowrap"><?php echo date('d/m/Y H:i', strtotime($t['created_at'])); ?></td>
                <td><span class="tx-badge type-<?php echo $t['type']; ?>"><?php echo $type_labels[$t['type']] ?? $t['type']; ?></span></td>
                <td><?php echo htmlspecialchars($t['description'] ?? '—'); ?></td>
                <td>
                    <?php
                    $out = ($t['from_user_id'] == $user['id'] && $t['type'] !== 'top_up');
                    echo $out
                        ? '<span class="tx-neg">-' . number_format($t['amount'],0,'.',',') . ' دج</span>'
                        : '<span class="tx-pos">+' . number_format($t['amount'],0,'.',',') . ' دج</span>';
                    ?>
                </td>
                <td><?php
                    $st = ['completed'=>'✅ مكتمل','pending'=>'⏳ معلق','failed'=>'❌ فاشل'];
                    echo $st[$t['status']] ?? $t['status'];
                ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div style="text-align:center;margin-top:1rem">
        <a href="transactions.php" class="btn btn-outline">عرض جميع المعاملات</a>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:3rem;color:var(--slate)">لا توجد معاملات بعد.</div>
    <?php endif; ?>
</div>
</div>
</div>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
