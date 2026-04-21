<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/payment.php';
require_once 'includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

// المعلمون لا يحتاجون اشتراكاً
if ($user['user_type'] === 'teacher') redirect('dashboard.php');

$packages = $pdo->query("SELECT * FROM subscriptions ORDER BY price ASC")->fetchAll();
$current_sub = get_subscription_status($pdo, $user['id']);
$trial_days  = get_trial_days_remaining($pdo, $user['id']);
$wallet_balance = get_wallet_balance($pdo, $user['id']);

$flash = '';
if (isset($_SESSION['flash_success'])) { $flash = ['type'=>'success','msg'=>$_SESSION['flash_success']]; unset($_SESSION['flash_success']); }
if (isset($_SESSION['flash_error']))   { $flash = ['type'=>'error','msg'=>$_SESSION['flash_error']];   unset($_SESSION['flash_error']); }

$sub_required = false; // الاشتراك اختياري

$active_page = 'subscription';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الاشتراكات — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .plans-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.5rem;margin-top:2rem}
        .plan-card{background:var(--glass-white);border:2px solid var(--glass-border);border-radius:var(--radius-xl);padding:2rem;text-align:center;transition:var(--transition-bounce);position:relative;overflow:hidden}
        .plan-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-xl)}
        .plan-card.popular{border-color:var(--accent-green);transform:scale(1.03)}
        .plan-card.popular:hover{transform:scale(1.03) translateY(-6px)}
        .popular-badge{position:absolute;top:0;left:0;right:0;background:linear-gradient(135deg,var(--accent-green),var(--secondary-green));color:#fff;padding:.5rem;font-size:.8rem;font-weight:700}
        .plan-name{font-size:1.3rem;font-weight:700;color:var(--charcoal);margin:1rem 0 .5rem}
        .plan-price{font-size:2.8rem;font-weight:900;background:linear-gradient(135deg,var(--royal-blue),var(--soft-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .plan-period{font-size:.9rem;color:var(--slate)}
        .plan-features{text-align:right;margin:1.5rem 0;list-style:none;padding:0;display:flex;flex-direction:column;gap:.6rem}
        .plan-features li{display:flex;align-items:center;gap:.5rem;font-size:.95rem;color:var(--charcoal)}
        .plan-features li::before{content:'✓';color:var(--accent-green);font-weight:800}
        .plan-btn{width:100%;margin-top:1rem}
        .current-badge{position:absolute;top:1rem;left:1rem;background:var(--accent-green);color:#fff;padding:.3rem .8rem;border-radius:50px;font-size:.8rem;font-weight:700}
        .sub-active-card{background:linear-gradient(135deg,rgba(64,145,108,.08),rgba(149,213,178,.15));border:2px solid rgba(64,145,108,.3);border-radius:var(--radius-xl);padding:2rem;margin-bottom:2rem;display:grid;grid-template-columns:1fr auto;align-items:center;gap:1rem}
        @media(max-width:600px){.sub-active-card{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include 'includes/header.php'; ?>
<div style="padding:2rem">

<?php if ($sub_required): ?>
<div class="alert alert-warning" style="margin-bottom:1.5rem">
    💡 اشترك للحصول على خصم 15% على جميع الجلسات مع المعلمين.
</div>
<?php endif; ?>

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:1.5rem"><?php echo htmlspecialchars($flash['msg']); ?></div>
<?php endif; ?>

<!-- الاشتراك النشط -->
<?php if ($current_sub): ?>
<div class="sub-active-card">
    <div>
        <h3 style="margin:0 0 .5rem;color:var(--secondary-green)">✅ لديك اشتراك نشط</h3>
        <p style="margin:0;color:var(--slate)">
            الباقة: <strong><?php echo htmlspecialchars($current_sub['name_ar']); ?></strong> —
            تنتهي في <?php echo date('d/m/Y', strtotime($current_sub['end_date'])); ?>
        </p>
        <p style="margin:.25rem 0 0;font-size:.9rem;color:var(--accent-green)">🎉 تستمتع بخصم 15% على جميع مدفوعات المعلمين</p>
    </div>
    <div>
        <div style="font-weight:700;font-size:1.1rem;color:var(--royal-blue)"><?php echo number_format($wallet_balance,0,'.',','); ?> دج</div>
        <div style="font-size:.8rem;color:var(--slate)">رصيد المحفظة</div>
    </div>
</div>
<?php elseif ($trial_days > 0): ?>
<div class="alert alert-warning" style="margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
    <span>⏳ فترة التجربة المجانية — <?php echo $trial_days; ?> أيام متبقية. اشترك الآن للاستمرار.</span>
    <span style="font-weight:700">رصيدك: <?php echo number_format($wallet_balance,0,'.',','); ?> دج</span>
</div>
<?php endif; ?>

<!-- عنوان -->
<div style="text-align:center;margin-bottom:2rem">
    <h1>باقات الاشتراك</h1>
    <p style="color:var(--slate)">الاشتراك اختياري تماماً — يمنحك <strong>خصم 15%</strong> على جميع الجلسات مع المعلمين</p>
    <p style="color:var(--accent-green);font-size:.9rem;margin-top:.4rem">✅ يمكنك استخدام المنصة بالكامل بدون اشتراك</p>
    <p style="color:var(--slate);font-size:.9rem">رصيدك الحالي: <strong><?php echo number_format($wallet_balance,0,'.',','); ?> دج</strong> — <a href="wallet.php">شحن المحفظة</a></p>
</div>

<?php if (count($packages) > 0): ?>
<div class="plans-grid">
    <?php
    $plan_features = [
        'شهري'      => ['وصول كامل لمدة شهر', 'خصم 15% على جميع المعلمين', 'رفع تلاوات غير محدودة', 'الانضمام للجلسات الجماعية'],
        'سنوي'      => ['وصول كامل لسنة كاملة', 'خصم 15% على جميع المعلمين', 'رفع تلاوات غير محدودة', 'الانضمام للجلسات الجماعية', 'توفير 72% مقارنة بالشهري'],
        'مدى الحياة'=> ['وصول دائم مدى الحياة', 'خصم 15% على جميع المعلمين', 'رفع تلاوات غير محدودة', 'الانضمام للجلسات الجماعية', 'أفضل قيمة'],
    ];
    foreach ($packages as $i => $p):
        $is_current = $current_sub && $current_sub['subscription_id'] == $p['id'];
        $is_popular = $i === 1;
        $feats = $plan_features[$p['name_ar']] ?? ['وصول كامل', 'خصم 15% على المعلمين'];
        $period = $p['duration_days'] <= 31 ? '/شهر' : ($p['duration_days'] <= 366 ? '/سنة' : '');
    ?>
    <div class="plan-card <?php echo $is_popular?'popular':''; ?>">
        <?php if ($is_popular): ?><div class="popular-badge">⭐ الأكثر شعبية</div><?php endif; ?>
        <?php if ($is_current): ?><div class="current-badge">اشتراكك الحالي</div><?php endif; ?>
        <div class="plan-name" style="margin-top:<?php echo $is_popular?'2rem':'0'; ?>"><?php echo htmlspecialchars($p['name_ar']); ?></div>
        <div class="plan-price"><?php echo number_format($p['price'],0,'.',','); ?></div>
        <div class="plan-period">دج <?php echo $period; ?></div>
        <ul class="plan-features">
            <?php foreach ($feats as $f): ?><li><?php echo $f; ?></li><?php endforeach; ?>
        </ul>
        <hr style="border:none;border-top:1px solid var(--sand);margin:1rem 0">
        <?php if (!$is_current): ?>
        <button onclick="subscribe(<?php echo $p['id']; ?>,'<?php echo addslashes($p['name_ar']); ?>',<?php echo $p['price']; ?>)"
                class="btn <?php echo $is_popular?'btn-secondary':'btn-primary'; ?> plan-btn">
            اشترك الآن
        </button>
        <?php else: ?>
        <button class="btn btn-outline plan-btn" disabled style="opacity:.6;cursor:not-allowed">مشترك حالياً</button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="alert alert-info" style="text-align:center">لا توجد باقات متاحة حالياً. تواصل مع الإدارة.</div>
<?php endif; ?>

<div style="text-align:center;margin-top:3rem;padding:1.5rem;background:var(--warm-cream);border-radius:var(--radius-lg)">
    <p style="color:var(--slate)">الدفع يتم من رصيد محفظتك — <a href="wallet.php">شحن المحفظة</a></p>
</div>
</div>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
function subscribe(pkgId, name, price) {
    if (!confirm('اشتراك في باقة "' + name + '" مقابل ' + price.toLocaleString('ar') + ' دج من محفظتك؟')) return;
    fetch(SITE_URL + '/api/subscribe.php', {
        method:'POST', credentials:'include',
        body: new URLSearchParams({subscription_id: pkgId, payment_method:'wallet'})
    }).then(r => r.json()).then(d => {
        if (d.success) { alert('✅ ' + (d.message||'تم الاشتراك بنجاح!')); location.reload(); }
        else alert('❌ ' + (d.message||'فشل الاشتراك'));
    }).catch(() => alert('حدث خطأ في الاتصال'));
}
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
