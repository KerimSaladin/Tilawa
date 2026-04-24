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
    $amount = (float)($_POST['amount'] ?? 0);
    $min    = (float) get_platform_setting($pdo, 'min_topup', 100);
    $max    = (float) get_platform_setting($pdo, 'max_topup', 100000);

    if ($amount < $min) {
        $_SESSION['flash_error'] = "الحد الأدنى للشحن هو " . number_format($min, 0, '.', ',') . " دج";
    } elseif ($amount > $max) {
        $_SESSION['flash_error'] = "الحد الأقصى للشحن هو " . number_format($max, 0, '.', ',') . " دج";
    } else {
        $amount = round($amount, 2);
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")->execute([$amount, $user['id']]);
        $pdo->prepare("INSERT INTO transactions (from_user_id, type, amount, description, status) VALUES (?,?,?,?,'completed')")
            ->execute([$user['id'], 'top_up', $amount, 'شحن المحفظة: ' . number_format($amount, 0, '.', ',') . ' دج']);
        $_SESSION['flash_success'] = 'تم شحن المحفظة بنجاح بمبلغ ' . number_format($amount, 0, '.', ',') . ' دج!';
    }
    redirect('wallet.php');
}

// طلب سحب الأرباح للمعلم
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_payout']) && $user['user_type'] === 'teacher') {
    $payout_amount = (float)($_POST['payout_amount'] ?? 0);
    $bank_holder   = trim($_POST['bank_holder'] ?? '');
    $bank_rib      = trim($_POST['bank_rib'] ?? '');
    $bank_name     = trim($_POST['bank_name'] ?? '');
    $current_bal   = get_wallet_balance($pdo, $user['id']);
    $payout_min_v  = (float) get_platform_setting($pdo, 'min_payout', 500);
    if ($payout_amount < $payout_min_v) {
        $_SESSION['flash_error'] = 'الحد الأدنى للسحب ' . number_format($payout_min_v,0,'.',',') . ' دج';
    } elseif ($payout_amount > $current_bal) {
        $_SESSION['flash_error'] = 'المبلغ أكبر من رصيدك المتاح';
    } elseif (!$bank_holder || !$bank_rib || !$bank_name) {
        $_SESSION['flash_error'] = 'يرجى ملء جميع بيانات الحساب البنكي';
    } else {
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")->execute([$payout_amount, $user['id']]);
        $desc = "طلب سحب أرباح — {$bank_name} | {$bank_holder} | RIB: {$bank_rib}";
        $pdo->prepare("INSERT INTO transactions (from_user_id, type, amount, description, status) VALUES (?,?,?,?,'pending')")
            ->execute([$user['id'], 'teacher_earning', $payout_amount, $desc]);
        $_SESSION['flash_success'] = 'تم تقديم طلب السحب! ستتم المعالجة خلال 2-5 أيام عمل.';
    }
    redirect('wallet.php');
}

$wallet_balance = get_wallet_balance($pdo, $user['id']);
$transactions   = get_transaction_history($pdo, $user['id'], $user['user_type'], 15);
$active_page    = 'wallet';

// Limits from DB (admin-configurable)
$topup_min = (int) get_platform_setting($pdo, 'min_topup', 100);
$topup_max = (int) get_platform_setting($pdo, 'max_topup', 100000);
$payout_min = (int) get_platform_setting($pdo, 'min_payout', 500);
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
    <p style="color:var(--slate);font-size:.9rem;margin-bottom:1.25rem">
        أدخل المبلغ الذي تريد شحنه أو اختر من المبالغ السريعة أدناه
        <span style="color:#94a3b8"> (الحد الأدنى: <?php echo number_format($topup_min,0,'.',','); ?> دج — الأقصى: <?php echo number_format($topup_max,0,'.',','); ?> دج)</span>
    </p>

    <!-- حقل المبلغ الحر -->
    <div style="display:flex;gap:.75rem;align-items:flex-end;flex-wrap:wrap;margin-bottom:1.25rem">
        <div style="flex:1;min-width:180px">
            <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">المبلغ (دج)</label>
            <input type="number" id="topupAmount"
                   min="<?php echo $topup_min; ?>" max="<?php echo $topup_max; ?>"
                   step="100" placeholder="أدخل المبلغ..."
                   oninput="syncTopupAmount(this.value)"
                   style="padding:.75rem 1rem;border:2px solid var(--sand);border-radius:var(--radius-lg);font-size:1.1rem;font-weight:700;width:100%;color:var(--royal-blue);transition:border-color .2s"
                   onfocus="this.style.borderColor='var(--royal-blue)'"
                   onblur="this.style.borderColor='var(--sand)'">
        </div>
        <button type="button" class="btn btn-primary" onclick="openCardModal()"
                style="padding:.75rem 2rem;font-size:1rem;white-space:nowrap">
            شحن الآن ←
        </button>
    </div>

    <!-- أزرار المبالغ السريعة (تملأ الحقل فقط، لا تُرسل) -->
    <div style="display:flex;flex-wrap:wrap;gap:.6rem;margin-bottom:.5rem">
        <span style="font-size:.85rem;color:var(--slate);align-self:center;font-weight:600">مبالغ سريعة:</span>
        <?php foreach ([500,1000,2000,5000,10000,20000] as $preset): ?>
        <?php if ($preset >= $topup_min && $preset <= $topup_max): ?>
        <button type="button"
                onclick="setTopupAmount(<?php echo $preset; ?>)"
                style="background:white;border:2px solid var(--sand);border-radius:50px;padding:.35rem .9rem;cursor:pointer;font-size:.88rem;font-weight:700;color:var(--royal-blue);transition:all .15s"
                onmouseover="this.style.borderColor='var(--royal-blue)';this.style.background='#eff6ff'"
                onmouseout="this.style.borderColor='var(--sand)';this.style.background='white'">
            +<?php echo number_format($preset,0,'.',','); ?> دج
        </button>
        <?php endif; ?>
        <?php endforeach; ?>
    </div>
</div>

<!-- مودال إدخال البطاقة -->
<div id="cardModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:2rem;max-width:420px;width:90%;box-shadow:0 25px 80px rgba(0,0,0,.2)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="margin:0;font-size:1.1rem">💳 إدخال بيانات البطاقة</h3>
            <button onclick="closeCardModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#94a3b8">✕</button>
        </div>
        <div style="background:linear-gradient(135deg,var(--deep-blue),var(--royal-blue));border-radius:12px;padding:1rem;color:#fff;text-align:center;margin-bottom:1.25rem">
            <div style="font-size:.85rem;opacity:.8">المبلغ المراد شحنه</div>
            <div id="card_amount_val" style="font-size:2rem;font-weight:900">0 دج</div>
        </div>
        <form method="POST" id="cardTopupForm" onsubmit="return validateCard()">
            <input type="hidden" name="top_up" value="1">
            <input type="hidden" name="amount" id="card_hidden_amount" value="">
            <div class="form-group" style="margin-bottom:1rem">
                <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">رقم البطاقة</label>
                <input type="text" id="card_number" class="form-input" placeholder="0000 0000 0000 0000"
                       maxlength="19" oninput="formatCardNum(this)" required style="letter-spacing:.05em">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem">
                <div class="form-group" style="margin:0">
                    <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">تاريخ الانتهاء</label>
                    <input type="text" id="card_expiry" class="form-input" placeholder="MM/YY"
                           maxlength="5" oninput="formatExpiry(this)" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">رمز الأمان</label>
                    <input type="text" id="card_cvc" class="form-input" placeholder="123"
                           maxlength="4" inputmode="numeric" pattern="[0-9]{3,4}" required>
                </div>
            </div>
            <p style="font-size:.78rem;color:#94a3b8;text-align:center;margin-bottom:1rem">🔒 بياناتك محمية ومشفرة — لن يتم حفظ بيانات البطاقة</p>
            <button type="submit" class="btn btn-primary" style="width:100%">تأكيد الشحن</button>
        </form>
    </div>
</div>
<script>
const TOPUP_MIN = <?php echo $topup_min; ?>;
const TOPUP_MAX = <?php echo $topup_max; ?>;

function setTopupAmount(val) {
    document.getElementById('topupAmount').value = val;
    syncTopupAmount(val);
}
function syncTopupAmount(val) {
    // Live preview in modal header if open
    const v = parseFloat(val) || 0;
    document.getElementById('card_amount_val').textContent = v.toLocaleString('ar-DZ') + ' دج';
    document.getElementById('card_hidden_amount').value = v;
}
function openCardModal() {
    const inp = document.getElementById('topupAmount');
    const val = parseFloat(inp.value) || 0;
    if (!val || val < TOPUP_MIN) {
        inp.focus();
        inp.style.borderColor = '#dc2626';
        setTimeout(() => inp.style.borderColor = 'var(--sand)', 2000);
        alert('الرجاء إدخال مبلغ صحيح (الحد الأدنى: ' + TOPUP_MIN.toLocaleString('ar-DZ') + ' دج)');
        return;
    }
    if (val > TOPUP_MAX) {
        inp.focus();
        alert('المبلغ يتجاوز الحد الأقصى: ' + TOPUP_MAX.toLocaleString('ar-DZ') + ' دج');
        return;
    }
    document.getElementById('card_amount_val').textContent = val.toLocaleString('ar-DZ') + ' دج';
    document.getElementById('card_hidden_amount').value = val;
    document.getElementById('cardModal').style.display = 'flex';
}
function closeCardModal() { document.getElementById('cardModal').style.display = 'none'; }
function formatCardNum(el) {
    let v = el.value.replace(/\D/g,'').substring(0,16);
    el.value = v.replace(/(.{4})/g,'$1 ').trim();
}
function formatExpiry(el) {
    let v = el.value.replace(/\D/g,'').substring(0,4);
    if (v.length > 2) v = v.substring(0,2) + '/' + v.substring(2);
    el.value = v;
}
function validateCard() {
    const num = document.getElementById('card_number').value.replace(/\s/g,'');
    const exp = document.getElementById('card_expiry').value;
    const cvc = document.getElementById('card_cvc').value;
    if (num.length < 13) { alert('رقم البطاقة غير صحيح'); return false; }
    if (!/^\d{2}\/\d{2}$/.test(exp)) { alert('تاريخ الانتهاء غير صحيح'); return false; }
    if (cvc.length < 3) { alert('رمز الأمان غير صحيح'); return false; }
    return true;
}
document.getElementById('cardModal').addEventListener('click', e => {
    if (e.target === document.getElementById('cardModal')) closeCardModal();
});
</script>
<?php endif; ?>

<?php if ($user['user_type'] === 'teacher'): ?>
<!-- قسم سحب الأرباح للمعلم -->
<div class="section-card">
    <h3>💰 استلام الأرباح</h3>
    <p style="color:var(--slate);font-size:.9rem;margin-bottom:1.25rem">أدخل بيانات حسابك البنكي لاستلام أرباحك</p>
    <?php
    $pending_payout = $wallet_balance; // teacher's full balance is their earnings
    ?>
    <div style="background:linear-gradient(135deg,rgba(64,145,108,.08),rgba(149,213,178,.12));border:1px solid rgba(64,145,108,.25);border-radius:12px;padding:1.25rem;margin-bottom:1.5rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
        <div>
            <div style="font-size:.88rem;color:var(--slate)">رصيد متاح للسحب</div>
            <div style="font-size:2rem;font-weight:900;color:var(--secondary-green)"><?php echo number_format($wallet_balance,0,'.',','); ?> دج</div>
        </div>
        <?php if ($wallet_balance >= $payout_min): ?>
        <button onclick="document.getElementById('payoutModal').style.display='flex'" class="btn btn-primary">طلب سحب الأرباح</button>
        <?php else: ?>
        <span style="font-size:.85rem;color:#94a3b8">الحد الأدنى للسحب: <?php echo number_format($payout_min,0,'.',','); ?> دج</span>
        <?php endif; ?>
    </div>
    <div style="font-size:.85rem;color:var(--slate)">
        <p>📌 تتم معالجة طلبات السحب خلال 2-5 أيام عمل.</p>
        <p>📌 يُحتجز <?php echo number_format(get_platform_fee_percent($pdo),1); ?>% عمولة المنصة من كل جلسة.</p>
    </div>
</div>

<!-- مودال طلب السحب -->
<div id="payoutModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center">
    <div style="background:#fff;border-radius:20px;padding:2rem;max-width:440px;width:90%;box-shadow:0 25px 80px rgba(0,0,0,.2)">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="margin:0;font-size:1.1rem">🏦 بيانات السحب</h3>
            <button onclick="document.getElementById('payoutModal').style.display='none'" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#94a3b8">✕</button>
        </div>
        <form method="POST" action="wallet.php">
            <input type="hidden" name="request_payout" value="1">
            <div class="form-group" style="margin-bottom:1rem">
                <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">اسم صاحب الحساب</label>
                <input type="text" name="bank_holder" class="form-input" placeholder="الاسم الكامل" required>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">رقم الحساب البنكي (RIB)</label>
                <input type="text" name="bank_rib" class="form-input" placeholder="00000 00000 00000000000 00" required>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">اسم البنك</label>
                <input type="text" name="bank_name" class="form-input" placeholder="مثال: BNA, BADR, CPA..." required>
            </div>
            <div class="form-group" style="margin-bottom:1.25rem">
                <label style="font-size:.88rem;font-weight:600;display:block;margin-bottom:.4rem">المبلغ المراد سحبه (دج)</label>
                <input type="number" name="payout_amount" class="form-input"
                       min="<?php echo $payout_min; ?>" max="<?php echo $wallet_balance; ?>"
                       value="<?php echo $wallet_balance; ?>" required>
                <small style="color:#94a3b8">الحد الأدنى: <?php echo number_format($payout_min,0,'.',','); ?> دج / الحد الأقصى: <?php echo number_format($wallet_balance,0,'.',','); ?> دج</small>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">تقديم طلب السحب</button>
        </form>
    </div>
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
