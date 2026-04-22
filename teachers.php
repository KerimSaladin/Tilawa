<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/payment.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'student') redirect('dashboard.php');

// انضمام لمجموعة مباشرة من صفحة المعلمين
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_group'])) {
    $gid = (int)$_POST['group_id'];
    try {
        $pdo->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?,?) ON CONFLICT DO NOTHING")->execute([$gid, $user['id']]);
        $_SESSION['flash'] = ['type'=>'success','msg'=>'تم الانضمام للمجموعة بنجاح!'];
    } catch (Exception $e) {
        $_SESSION['flash'] = ['type'=>'error','msg'=>'حدث خطأ'];
    }
    header('Location: teachers.php'); exit;
}

// جلب المعلمين مع تفاصيلهم
$teachers = $pdo->prepare("
    SELECT u.*,
           COALESCE(AVG(r.rating), 0) as avg_rating,
           COUNT(DISTINCT r.id) as rating_count,
           (SELECT COUNT(*) FROM recitations rec WHERE rec.teacher_id=u.id AND rec.status IN ('reviewed','approved')) as total_sessions,
           (SELECT COUNT(DISTINCT student_id) FROM recitations rec2 WHERE rec2.teacher_id=u.id) as total_students
    FROM users u
    LEFT JOIN ratings r ON u.id = r.teacher_id
    WHERE u.user_type='teacher' AND u.teacher_status='approved' AND u.is_active = TRUE
    GROUP BY u.id
    ORDER BY avg_rating DESC, total_sessions DESC
");
$teachers->execute();
$teachers = $teachers->fetchAll();

// المعلمون الذين سجّل معهم الطالب
$my_teachers = $pdo->prepare("
    SELECT DISTINCT teacher_id FROM recitations WHERE student_id=? AND teacher_id IS NOT NULL
    UNION SELECT DISTINCT teacher_id FROM enrollments WHERE student_id=?
");
$my_teachers->execute([$user['id'], $user['id']]);
$my_teacher_ids = array_column($my_teachers->fetchAll(), 'teacher_id');

$has_discount = has_subscription_discount($pdo, $user['id']);
$active_page = 'teachers';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>المعلمون — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .page-header{margin-bottom:2rem}
        .page-header h1{font-size:1.6rem;font-weight:800;color:var(--charcoal);margin-bottom:.4rem}
        .page-header p{color:var(--slate)}
        .filter-bar{display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap;align-items:center}
        .filter-btn{padding:.45rem 1.1rem;border:2px solid var(--sand);border-radius:50px;background:white;cursor:pointer;font-family:inherit;font-size:.88rem;font-weight:600;color:var(--slate);transition:var(--transition-base)}
        .filter-btn.active,.filter-btn:hover{background:var(--royal-blue);color:#fff;border-color:var(--royal-blue)}
        .teachers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:1.5rem}
        .teacher-card{background:var(--glass-white);border:1.5px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;transition:var(--transition-bounce);box-shadow:var(--shadow-md)}
        .teacher-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-xl);border-color:var(--primary-gold)}
        .teacher-card.my-teacher{border-color:rgba(64,145,108,.4);background:linear-gradient(135deg,rgba(64,145,108,.03),rgba(149,213,178,.05))}
        .tc-head{display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem}
        .tc-avatar{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--soft-blue),var(--royal-blue));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;font-weight:800;flex-shrink:0;position:relative}
        .online-indicator{position:absolute;bottom:1px;right:1px;width:13px;height:13px;border-radius:50%;background:#22c55e;border:2px solid white}
        .offline-indicator{position:absolute;bottom:1px;right:1px;width:13px;height:13px;border-radius:50%;background:#cbd5e1;border:2px solid white}
        .tc-name{font-weight:700;font-size:1.05rem;color:var(--charcoal);margin-bottom:.2rem}
        .tc-stars{color:#f59e0b;font-size:1rem;letter-spacing:.05rem}
        .tc-rating-count{font-size:.78rem;color:var(--slate);margin-right:.3rem}
        .tc-stats{display:flex;gap:1.5rem;margin-bottom:1.25rem;padding:.85rem;background:var(--warm-cream);border-radius:var(--radius-md)}
        .tc-stat{text-align:center;flex:1}
        .tc-stat-val{font-size:1.2rem;font-weight:800;color:var(--royal-blue)}
        .tc-stat-lbl{font-size:.72rem;color:var(--slate);margin-top:.1rem}
        .tc-pricing{margin-bottom:1.25rem}
        .tc-price-row{display:flex;justify-content:space-between;align-items:center;padding:.4rem 0;border-bottom:1px dashed var(--sand);font-size:.9rem}
        .tc-price-row:last-child{border:none}
        .tc-price-label{color:var(--slate)}
        .tc-price-val{font-weight:700;color:var(--secondary-green)}
        .tc-price-disc{font-size:.78rem;color:var(--accent-green);text-decoration:line-through;opacity:.6}
        .tc-actions{display:flex;flex-direction:column;gap:.5rem}
        .enrolled-badge{background:#d1fae5;color:#065f46;border-radius:50px;padding:.3rem .85rem;font-size:.8rem;font-weight:700;text-align:center}
        .no-price-note{font-size:.85rem;color:var(--slate);text-align:center;padding:.75rem;background:var(--warm-cream);border-radius:var(--radius-md)}
        .search-input{flex:1;max-width:320px}
        .discount-info{background:linear-gradient(135deg,rgba(64,145,108,.08),rgba(149,213,178,.1));border:1px solid rgba(64,145,108,.25);border-radius:var(--radius-md);padding:.85rem 1.25rem;margin-bottom:1.5rem;font-size:.9rem;color:var(--secondary-green);font-weight:600}
        /* مودال التقييم */
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center}
        .modal-overlay.show{display:flex}
        .modal-box{background:white;border-radius:var(--radius-xl);padding:2rem;max-width:440px;width:90%;box-shadow:var(--shadow-xl)}
        .stars-input{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:.25rem;margin:1rem 0}
        .stars-input input{display:none}
        .stars-input label{font-size:2.2rem;color:#d1d5db;cursor:pointer;transition:color .15s}
        .stars-input input:checked~label,.stars-input label:hover,.stars-input label:hover~label{color:#f59e0b}
        /* مودال الدفع */
        .pay-row{display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--sand);font-size:.9rem}
        .pay-row:last-child{border:none}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include 'includes/header.php'; ?>
<div style="padding:2rem">

<?php if (isset($_SESSION['flash'])): ?>
<div class="alert alert-<?php echo $_SESSION['flash']['type']; ?>" style="margin-bottom:1rem"><?php echo htmlspecialchars($_SESSION['flash']['msg']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<div class="page-header">
    <h1>🎓 المعلمون المتاحون</h1>
    <p>اختر معلمك وابدأ رحلة الحفظ</p>
</div>

<?php if ($has_discount): ?>
<div class="discount-info">🎉 لديك اشتراك نشط — تستمتع بخصم 15% على جميع الجلسات</div>
<?php else: ?>
<div style="background:#fffbeb;border:1px solid #fcd34d;border-radius:var(--radius-md);padding:.85rem 1.25rem;margin-bottom:1.25rem;font-size:.88rem;color:#92400e">
    💡 <a href="subscription.php" style="color:#92400e;font-weight:700">اشترك الآن</a> للحصول على خصم 15% على جميع الجلسات
</div>
<?php endif; ?>

<div class="filter-bar">
    <input type="text" id="searchInput" class="form-input search-input" placeholder="🔍 بحث عن معلم..." oninput="filterTeachers()">
    <button class="filter-btn active" onclick="filterByType('all',this)">الكل (<?php echo count($teachers); ?>)</button>
    <button class="filter-btn" onclick="filterByType('enrolled',this)">معلموي (<?php echo count($my_teacher_ids); ?>)</button>
    <button class="filter-btn" onclick="filterByType('online',this)">متصل الآن</button>
    <button class="filter-btn" onclick="filterByType('has_price',this)">لديه أسعار</button>
</div>

<?php if ($teachers): ?>
<div class="teachers-grid" id="teachersGrid">
    <?php foreach ($teachers as $t):
        $tp = get_teacher_pricing($pdo, $t['id']);
        $is_enrolled = in_array($t['id'], $my_teacher_ids);
        $online = is_teacher_online($pdo, $t['id']);
        $avg = round($t['avg_rating'], 1);
        $stars_full = str_repeat('★', (int)$avg);
        $stars_empty = str_repeat('☆', 5 - (int)$avg);
        $initial = mb_substr($t['full_name'], 0, 1, 'UTF-8');
        $has_prices = $tp['session_price'] || $tp['price_per_course'];
        $disc_session = $has_discount && $tp['session_price'] ? round($tp['session_price'] * 0.85) : null;
        $disc_course  = $has_discount && $tp['price_per_course'] ? round($tp['price_per_course'] * 0.85) : null;
    ?>
    <div class="teacher-card <?php echo $is_enrolled?'my-teacher':''; ?>"
         data-name="<?php echo htmlspecialchars($t['full_name']); ?>"
         data-online="<?php echo $online?'1':'0'; ?>"
         data-enrolled="<?php echo $is_enrolled?'1':'0'; ?>"
         data-has-price="<?php echo $has_prices?'1':'0'; ?>">

        <div class="tc-head">
            <div class="tc-avatar">
                <?php echo $initial; ?>
                <?php if ($online): ?><div class="online-indicator"></div>
                <?php else: ?><div class="offline-indicator"></div><?php endif; ?>
            </div>
            <div style="flex:1;min-width:0">
                <div class="tc-name">
                    <?php echo htmlspecialchars($t['full_name']); ?>
                    <?php if ($is_enrolled): ?> <span style="font-size:.72rem;background:#d1fae5;color:#065f46;padding:.1rem .5rem;border-radius:50px">معلمي</span><?php endif; ?>
                </div>
                <div>
                    <span class="tc-stars"><?php echo $stars_full . $stars_empty; ?></span>
                    <span class="tc-rating-count">(<?php echo $avg; ?> • <?php echo $t['rating_count']; ?> تقييم)</span>
                </div>
                <div style="font-size:.78rem;color:<?php echo $online?'#22c55e':'#94a3b8'; ?>;margin-top:.15rem">
                    <?php echo $online ? '🟢 متصل الآن' : '⚫ غير متصل'; ?>
                </div>
            </div>
        </div>

        <div class="tc-stats">
            <div class="tc-stat">
                <div class="tc-stat-val"><?php echo $t['total_sessions']; ?></div>
                <div class="tc-stat-lbl">جلسة مراجَعة</div>
            </div>
            <div class="tc-stat">
                <div class="tc-stat-val"><?php echo $t['total_students']; ?></div>
                <div class="tc-stat-lbl">طالب</div>
            </div>
            <div class="tc-stat">
                <div class="tc-stat-val"><?php echo $avg ?: '—'; ?></div>
                <div class="tc-stat-lbl">التقييم</div>
            </div>
        </div>

        <?php if ($has_prices): ?>
        <div class="tc-pricing">
            <?php if ($tp['session_price']): ?>
            <div class="tc-price-row">
                <span class="tc-price-label">جلسة واحدة</span>
                <span>
                    <?php if ($disc_session): ?>
                    <span class="tc-price-disc"><?php echo number_format($tp['session_price'],0,'.',','); ?></span>
                    <span class="tc-price-val"> <?php echo number_format($disc_session,0,'.',','); ?> دج</span>
                    <?php else: ?>
                    <span class="tc-price-val"><?php echo number_format($tp['session_price'],0,'.',','); ?> دج</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
            <?php if ($tp['price_per_course']): ?>
            <div class="tc-price-row">
                <span class="tc-price-label">الدورة كاملة</span>
                <span>
                    <?php if ($disc_course): ?>
                    <span class="tc-price-disc"><?php echo number_format($tp['price_per_course'],0,'.',','); ?></span>
                    <span class="tc-price-val"> <?php echo number_format($disc_course,0,'.',','); ?> دج</span>
                    <?php else: ?>
                    <span class="tc-price-val"><?php echo number_format($tp['price_per_course'],0,'.',','); ?> دج</span>
                    <?php endif; ?>
                </span>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="no-price-note">لم يحدد المعلم أسعاره بعد</div>
        <?php endif; ?>

        <div class="tc-actions">
            <?php if ($tp['session_price']): ?>
            <button onclick="openPay(<?php echo $t['id']; ?>,'session',<?php echo $tp['session_price']; ?>,<?php echo $disc_session??$tp['session_price']; ?>,'<?php echo addslashes($t['full_name']); ?>')"
                    class="btn btn-primary" style="font-size:.9rem;padding:.6rem">
                💳 حجز جلسة <?php echo $has_discount?'<span style="font-size:.75rem;opacity:.8">(خصم 15%)</span>':''; ?>
            </button>
            <?php endif; ?>
            <?php if ($tp['price_per_course']): ?>
            <button onclick="openPay(<?php echo $t['id']; ?>,'course',<?php echo $tp['price_per_course']; ?>,<?php echo $disc_course??$tp['price_per_course']; ?>,'<?php echo addslashes($t['full_name']); ?>')"
                    class="btn btn-secondary" style="font-size:.9rem;padding:.6rem">
                📚 التسجيل في الدورة
            </button>
            <?php endif; ?>
            <div style="display:flex;gap:.5rem">
                <a href="messages.php?user=<?php echo $t['id']; ?>" class="btn btn-outline" style="flex:1;font-size:.85rem;padding:.5rem;text-align:center">💬 رسالة</a>
                <button onclick="openRate(<?php echo $t['id']; ?>,'<?php echo addslashes($t['full_name']); ?>')"
                        class="btn btn-outline" style="flex:1;font-size:.85rem;padding:.5rem">⭐ تقييم</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div style="text-align:center;padding:4rem;color:var(--slate)">
    <div style="font-size:4rem;margin-bottom:1rem">👨‍🏫</div>
    <h3>لا يوجد معلمون متاحون حالياً</h3>
</div>
<?php endif; ?>
</div>
</div>

<!-- مودال الدفع -->
<div class="modal-overlay" id="payModal">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 id="pay_title" style="margin:0">تأكيد الدفع</h3>
            <button onclick="document.getElementById('payModal').classList.remove('show')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--slate)">✕</button>
        </div>
        <div style="background:var(--warm-cream);border-radius:var(--radius-md);padding:1.25rem;margin-bottom:1rem">
            <div class="pay-row"><span style="color:var(--slate)">المعلم</span><span id="pay_teacher" style="font-weight:700"></span></div>
            <div class="pay-row"><span style="color:var(--slate)">النوع</span><span id="pay_type" style="font-weight:700"></span></div>
            <div class="pay-row" id="pay_orig_row"><span style="color:var(--slate)">السعر الأصلي</span><span id="pay_orig" style="font-weight:700;text-decoration:line-through;color:#94a3b8"></span></div>
            <div class="pay-row" style="border-top:2px solid var(--sand);padding-top:.75rem;margin-top:.25rem">
                <span style="font-weight:700">المبلغ المستحق</span>
                <span id="pay_final" style="font-size:1.2rem;font-weight:800;color:var(--royal-blue)"></span>
            </div>
            <div class="pay-row"><span style="color:var(--slate)">رصيد محفظتك</span><span id="pay_balance" style="font-weight:700"></span></div>
        </div>
        <div id="pay_msg" style="margin-bottom:1rem;font-size:.9rem;text-align:center"></div>
        <div style="display:flex;gap:.75rem">
            <button id="pay_btn" onclick="confirmPay()" class="btn btn-primary" style="flex:1">تأكيد الدفع</button>
            <button onclick="document.getElementById('payModal').classList.remove('show')" class="btn btn-outline" style="flex:1">إلغاء</button>
        </div>
    </div>
</div>

<!-- مودال التقييم -->
<div class="modal-overlay" id="rateModal">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem">
            <h3 style="margin:0">تقييم: <span id="rate_name"></span></h3>
            <button onclick="document.getElementById('rateModal').classList.remove('show')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--slate)">✕</button>
        </div>
        <div class="form-group">
            <label class="form-label">التقييم</label>
            <div class="stars-input">
                <input type="radio" name="rstar" id="rs5" value="5"><label for="rs5">★</label>
                <input type="radio" name="rstar" id="rs4" value="4"><label for="rs4">★</label>
                <input type="radio" name="rstar" id="rs3" value="3"><label for="rs3">★</label>
                <input type="radio" name="rstar" id="rs2" value="2"><label for="rs2">★</label>
                <input type="radio" name="rstar" id="rs1" value="1"><label for="rs1">★</label>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">تعليق (اختياري)</label>
            <textarea id="rate_comment" class="form-input" rows="3" placeholder="أضف تعليقك..."></textarea>
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button onclick="submitRate()" class="btn btn-primary" style="flex:1">إرسال التقييم</button>
            <button onclick="document.getElementById('rateModal').classList.remove('show')" class="btn btn-outline" style="flex:1">إلغاء</button>
        </div>
    </div>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
let _pay = {}, _rate = {};

// ── فلترة المعلمين ──
let currentFilter = 'all';
function filterTeachers() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    document.querySelectorAll('.teacher-card').forEach(card => {
        const name = card.dataset.name.toLowerCase();
        const matchSearch = !q || name.includes(q);
        const matchFilter =
            currentFilter === 'all' ||
            (currentFilter === 'enrolled' && card.dataset.enrolled === '1') ||
            (currentFilter === 'online'   && card.dataset.online   === '1') ||
            (currentFilter === 'has_price'&& card.dataset.hasPrice  === '1');
        card.style.display = (matchSearch && matchFilter) ? '' : 'none';
    });
}
function filterByType(type, btn) {
    currentFilter = type;
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    filterTeachers();
}

// ── مودال الدفع ──
async function openPay(tid, type, orig, final, name) {
    _pay = {tid, type, orig, final, name};
    document.getElementById('pay_title').textContent = type === 'session' ? 'حجز جلسة' : 'التسجيل في الدورة';
    document.getElementById('pay_teacher').textContent = name;
    document.getElementById('pay_type').textContent = type === 'session' ? 'جلسة واحدة' : 'دورة كاملة';
    document.getElementById('pay_final').textContent = final.toLocaleString('ar') + ' دج';
    const origRow = document.getElementById('pay_orig_row');
    if (orig !== final) {
        origRow.style.display = 'flex';
        document.getElementById('pay_orig').textContent = orig.toLocaleString('ar') + ' دج';
    } else {
        origRow.style.display = 'none';
    }
    document.getElementById('pay_msg').textContent = '...جارٍ التحقق من الرصيد';
    document.getElementById('payModal').classList.add('show');
    try {
        const r = await fetch(SITE_URL + '/api/wallet_balance.php', {credentials:'include'});
        const d = await r.json();
        const bal = d.balance || 0;
        document.getElementById('pay_balance').textContent = bal.toLocaleString('ar') + ' دج';
        const ok = bal >= final;
        document.getElementById('pay_msg').innerHTML = ok
            ? '<span style="color:var(--secondary-green)">✅ رصيدك كافٍ للدفع</span>'
            : '<span style="color:#dc2626">❌ رصيدك غير كافٍ — <a href="wallet.php">شحن المحفظة</a></span>';
        document.getElementById('pay_btn').disabled = !ok;
    } catch(e) { document.getElementById('pay_msg').textContent = 'فشل التحقق من الرصيد'; }
}

async function confirmPay() {
    const btn = document.getElementById('pay_btn');
    btn.disabled = true; btn.textContent = '...جارٍ الدفع';
    try {
        const r = await fetch(SITE_URL + '/api/payment.php', {
            method:'POST', credentials:'include',
            body: new URLSearchParams({action:'pay_teacher', teacher_id:_pay.tid, payment_type:_pay.type})
        });
        const d = await r.json();
        if (d.success) {
            alert('✅ تم الدفع بنجاح! يمكنك الآن إرسال تلاواتك للمعلم.');
            document.getElementById('payModal').classList.remove('show');
            location.reload();
        } else {
            alert('❌ ' + (d.message || 'فشل الدفع'));
            btn.disabled = false; btn.textContent = 'تأكيد الدفع';
        }
    } catch(e) { alert('فشل الاتصال'); btn.disabled = false; btn.textContent = 'تأكيد الدفع'; }
}

// ── مودال التقييم ──
function openRate(tid, name) {
    _rate = {tid};
    document.getElementById('rate_name').textContent = name;
    document.querySelectorAll('input[name=rstar]').forEach(i => i.checked = false);
    document.getElementById('rate_comment').value = '';
    document.getElementById('rateModal').classList.add('show');
}

async function submitRate() {
    const star = document.querySelector('input[name=rstar]:checked');
    if (!star) { alert('يرجى اختيار عدد النجوم'); return; }
    const btn = event.target;
    btn.disabled = true; btn.textContent = '...إرسال';
    try {
        const r = await fetch(SITE_URL + '/api/rate_teacher.php', {
            method:'POST', credentials:'include',
            body: new URLSearchParams({teacher_id:_rate.tid, rating:star.value, comment:document.getElementById('rate_comment').value})
        });
        const d = await r.json();
        alert(d.success ? '✅ ' + d.message : '❌ ' + (d.message || 'فشل التقييم'));
        if (d.success) { document.getElementById('rateModal').classList.remove('show'); location.reload(); }
    } catch(e) { alert('فشل الاتصال'); }
    finally { btn.disabled = false; btn.textContent = 'إرسال التقييم'; }
}

document.querySelectorAll('.modal-overlay').forEach(m =>
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); })
);
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
