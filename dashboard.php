<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
ob_start();

try {
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'includes/payment.php';

if (!$pdo) { http_response_code(500); die('DB connection failed'); }

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

// الاشتراك اختياري — يمنح خصم 15% فقط، لا يمنع الوصول

$active_page = 'dashboard';

if ($user['user_type'] === 'teacher') {
    update_teacher_online_status($pdo, $user['id']);
    $is_approved = is_teacher_approved($pdo, $user['id']);

    if (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM recitations r WHERE r.teacher_id=? AND r.status='pending'");
        $stmt->execute([$user['id']]);
        $pending_recitations = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT s.id) as total FROM users s
            WHERE s.user_type='student' AND s.id IN (
                SELECT DISTINCT student_id FROM recitations WHERE teacher_id=?
                UNION SELECT DISTINCT student_id FROM enrollments WHERE teacher_id=?
            )
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $active_students = $stmt->fetch()['total'];

        $stmt = $pdo->prepare("
            SELECT r.*, u.full_name as student_name FROM recitations r
            JOIN users u ON r.student_id=u.id
            WHERE (r.teacher_id=? OR (r.teacher_id IS NULL AND u.id IN (
                SELECT DISTINCT student_id FROM enrollments WHERE teacher_id=?
            )))
            AND r.status='pending'
            ORDER BY r.uploaded_at DESC LIMIT 5
        ");
        $stmt->execute([$user['id'], $user['id']]);
        $recent_recitations = $stmt->fetchAll();
    } else {
        $pending_recitations = 0; $active_students = 0; $recent_recitations = [];
    }

    $stmt = $pdo->prepare("SELECT * FROM groups WHERE teacher_id=? ORDER BY created_at DESC");
    $stmt->execute([$user['id']]);
    $groups = $stmt->fetchAll();

    $teacher_sessions = get_teacher_group_sessions($pdo, $user['id']);
    $teacher_pricing  = get_teacher_pricing($pdo, $user['id']);

    // إجمالي الأرباح
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE to_user_id=? AND type='teacher_earning' AND status='completed'");
    $stmt->execute([$user['id']]);
    $total_earnings = $stmt->fetch()['total'];

} else {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM recitations WHERE student_id=?");
    $stmt->execute([$user['id']]); $total_recitations = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM recitations WHERE student_id=? AND status='reviewed'");
    $stmt->execute([$user['id']]); $reviewed_recitations = $stmt->fetch()['total'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(memorized_verses),0) as v FROM progress WHERE student_id=?");
    $stmt->execute([$user['id']]); $total_memorized = $stmt->fetch()['v'];

    $stmt = $pdo->prepare("
        SELECT c.*, r.surah_name, r.ayah_range, u.full_name as teacher_name
        FROM corrections c JOIN recitations r ON c.recitation_id=r.id JOIN users u ON c.teacher_id=u.id
        WHERE r.student_id=? ORDER BY c.corrected_at DESC LIMIT 5");
    $stmt->execute([$user['id']]); $recent_corrections = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT g.* FROM groups g JOIN group_members gm ON g.id=gm.group_id WHERE gm.student_id=? ORDER BY g.created_at DESC");
    $stmt->execute([$user['id']]); $groups = $stmt->fetchAll();

    // المعلمون المتاحون
    $stmt = $pdo->prepare("
        SELECT u.*, COALESCE(AVG(r.rating),0) as avg_rating, COUNT(r.id) as rating_count
        FROM users u LEFT JOIN ratings r ON u.id=r.teacher_id
        WHERE u.user_type='teacher' AND u.teacher_status='approved' AND u.is_active = TRUE
        GROUP BY u.id ORDER BY avg_rating DESC, u.created_at ASC LIMIT 12");
    $stmt->execute(); $teachers = $stmt->fetchAll();

    $trial_days = get_trial_days_remaining($pdo, $user['id']);
    $subscription = get_subscription_status($pdo, $user['id']);
    $wallet_balance = get_wallet_balance($pdo, $user['id']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        /* ── إصلاحات تناسق عامة ── */
        .wallet-chip{display:flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,var(--accent-green),var(--secondary-green));color:#fff;border-radius:50px;padding:.4rem 1rem;font-weight:700;font-size:.9rem}
        .wallet-chip svg{opacity:.85}
        .dropdown-user-name{padding:.75rem 1rem;font-weight:700;color:var(--charcoal);border-bottom:1px solid var(--sand);margin-bottom:.25rem}

        /* ── شبكة الإحصاء ── */
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1.25rem;margin-bottom:2rem}
        .stat-card{background:var(--glass-white);backdrop-filter:blur(20px);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;border:1px solid var(--glass-border);box-shadow:var(--shadow-md);transition:var(--transition-bounce)}
        .stat-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-xl)}
        .stat-value{font-size:2.2rem;font-weight:800;background:linear-gradient(135deg,var(--royal-blue),var(--soft-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .stat-label{font-size:.9rem;color:var(--slate);margin-top:.25rem;font-weight:500}

        /* ── بطاقات الإجراءات ── */
        .action-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:1rem;margin-bottom:2rem}
        .action-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:1.5rem 1rem;text-align:center;text-decoration:none;color:var(--charcoal);transition:var(--transition-bounce);display:flex;flex-direction:column;align-items:center;gap:.75rem}
        .action-card:hover{transform:translateY(-8px);box-shadow:var(--shadow-xl),var(--shadow-glow);border-color:var(--primary-gold)}
        .action-icon img{width:40px;height:40px}
        .action-title{font-weight:600;font-size:.95rem}

        /* ── قسم التلاوات المعلقة ── */
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-size:1.15rem;font-weight:700;color:var(--charcoal);margin-bottom:1.25rem;padding-bottom:.75rem;border-bottom:1px solid var(--sand)}
        .recitation-item{display:flex;justify-content:space-between;align-items:center;padding:.9rem;border-radius:var(--radius-md);background:var(--warm-cream);margin-bottom:.6rem;gap:1rem}
        .recitation-student{font-weight:700;color:var(--charcoal);margin-bottom:.2rem}
        .recitation-details{font-size:.85rem;color:var(--slate)}

        /* ── قسم الأسعار (معلم) ── */
        .pricing-section{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem}
        .pricing-section h3{font-weight:700;color:var(--charcoal);margin-bottom:.5rem}
        .pricing-note{color:var(--slate);font-size:.9rem;margin-bottom:1.25rem}
        .pricing-grid{display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem}
        .pricing-field label{display:block;font-weight:600;font-size:.9rem;margin-bottom:.4rem;color:var(--charcoal)}
        .pricing-field{position:relative}
        .pricing-field input{width:100%;padding:.75rem 3.5rem .75rem 1rem;border:2px solid var(--sand);border-radius:var(--radius-md);font-family:inherit;font-size:1rem;transition:var(--transition-base)}
        .pricing-field input:focus{border-color:var(--soft-blue);outline:none;box-shadow:0 0 0 3px rgba(91,139,214,.12)}
        .pricing-field .currency{position:absolute;left:1rem;bottom:.8rem;color:var(--slate);font-size:.85rem;font-weight:600}
        .pricing-actions{display:flex;gap:.75rem}
        .earnings-row{display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--sand);font-size:.95rem}
        .earnings-row:last-child{border-bottom:none}
        .earnings-row .label{color:var(--slate)}
        .earnings-row .value{font-weight:700;color:var(--charcoal)}

        /* ── المعلمون (طالب) ── */
        .teachers-section{margin-bottom:2rem}
        .teachers-section h3{font-size:1.2rem;font-weight:700;color:var(--charcoal);margin-bottom:1rem}
        .teachers-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.25rem}
        .teacher-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.5rem;transition:var(--transition-bounce);box-shadow:var(--shadow-md)}
        .teacher-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-xl)}
        .teacher-header{display:flex;align-items:center;gap:1rem;margin-bottom:1rem}
        .teacher-avatar{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--soft-blue),var(--royal-blue));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.3rem;font-weight:800;flex-shrink:0}
        .teacher-name{font-weight:700;font-size:1rem;color:var(--charcoal)}
        .teacher-rating{display:flex;align-items:center;gap:.3rem;font-size:.8rem;color:var(--slate);margin-top:.2rem}
        .stars{color:#F59E0B;font-size:.95rem}
        .online-dot{width:9px;height:9px;border-radius:50%;background:#22c55e;display:inline-block;margin-left:.4rem;box-shadow:0 0 0 2px rgba(34,197,94,.25)}
        .offline-dot{width:9px;height:9px;border-radius:50%;background:#94a3b8;display:inline-block;margin-left:.4rem}
        .teacher-pricing-row{display:flex;justify-content:space-between;font-size:.9rem;padding:.35rem 0;border-bottom:1px dashed var(--sand)}
        .teacher-pricing-row:last-child{border:none}
        .price-label{color:var(--slate)}
        .price-value{font-weight:700;color:var(--primary-green)}
        .teacher-actions{margin-top:1rem;display:flex;flex-direction:column;gap:.5rem}

        /* ── الجلسات الجماعية ── */
        .sessions-list{display:flex;flex-direction:column;gap:.75rem}
        .session-card{background:var(--warm-cream);border-radius:var(--radius-md);padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;flex-wrap:wrap}
        .session-card h4{font-weight:700;color:var(--charcoal);margin-bottom:.3rem}
        .session-meta{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:.5rem}
        .session-meta span{font-size:.82rem;background:white;border-radius:50px;padding:.2rem .7rem;color:var(--slate);border:1px solid var(--sand)}
        .session-actions{display:flex;gap:.5rem;flex-shrink:0;flex-wrap:wrap}
        .status-scheduled{background:#dbeafe!important;color:#1d4ed8!important;border-color:#93c5fd!important}
        .status-live{background:#dcfce7!important;color:#166534!important;border-color:#86efac!important}
        .status-ended{background:#f1f5f9!important;color:#64748b!important;border-color:#cbd5e1!important}

        /* ── مودال إنشاء جلسة ── */
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center;padding:1rem}
        .modal-overlay.show{display:flex}
        .modal-box{background:white;border-radius:var(--radius-xl);padding:2rem;max-width:520px;width:100%;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-xl)}
        .modal-box h3{font-weight:700;font-size:1.2rem;margin-bottom:1.5rem;color:var(--charcoal)}
        .modal-close{float:left;background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--slate);line-height:1}

        /* ── مودال التقييم ── */
        .stars-input{display:flex;flex-direction:row-reverse;justify-content:flex-end;gap:.25rem}
        .stars-input input{display:none}
        .stars-input label{font-size:2rem;color:#d1d5db;cursor:pointer;transition:color .15s}
        .stars-input input:checked~label,.stars-input label:hover,.stars-input label:hover~label{color:#f59e0b}

        /* ── نظام الدفع (مودال) ── */
        .payment-detail-row{display:flex;justify-content:space-between;padding:.6rem 0;border-bottom:1px solid var(--sand);font-size:.95rem}
        .payment-detail-row:last-child{border-none}
        .payment-detail-row .lbl{color:var(--slate)}
        .payment-detail-row .val{font-weight:700}

        /* ── شريط الاشتراك (طالب) ── */
        .trial-banner{background:linear-gradient(135deg,rgba(212,175,55,.1),rgba(245,230,195,.2));border:1px solid rgba(212,175,55,.3);border-radius:var(--radius-md);padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap}
        .trial-banner .trial-text{font-weight:600;color:#92400e}
        .sub-banner{background:linear-gradient(135deg,rgba(64,145,108,.1),rgba(149,213,178,.15));border:1px solid rgba(64,145,108,.25);border-radius:var(--radius-md);padding:1rem 1.25rem;display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;gap:1rem;flex-wrap:wrap}
        .sub-banner .sub-text{font-weight:600;color:var(--secondary-green)}

        @media(max-width:768px){
            .pricing-grid{grid-template-columns:1fr}
            .session-card{flex-direction:column}
        }
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">

<?php include 'includes/header.php'; ?>

<div style="padding:2rem">

    <!-- ترحيب -->
    <div class="section-card" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem">
        <div>
            <h2 style="margin:0;font-size:1.4rem">مرحباً، <?php echo htmlspecialchars($user['full_name']); ?> 👋</h2>
            <p style="margin:.25rem 0 0;color:var(--slate)">
                <?php if ($user['user_type']==='teacher') echo 'مرحباً بمن أكرمه الله بأن يكون لسانًا للقرآن ومرشدًا للقلوب.';
                else echo 'مرحباً بالساعي إلى الجنة من باب التلاوة.'; ?>
            </p>
        </div>
        <div style="font-size:.85rem;color:var(--slate)"><?php echo date('d/m/Y'); ?></div>
    </div>

    <?php if ($user['user_type']==='teacher'): ?>
    <!-- ═══════════════ لوحة المعلم ═══════════════ -->

    <?php if (!$is_approved): ?>
        <div class="section-card" style="text-align:center;padding:3rem">
            <div style="font-size:4rem;margin-bottom:1rem">⏳</div>
            <h2>الحساب قيد المراجعة</h2>
            <p style="color:var(--slate);max-width:500px;margin:.75rem auto 1.5rem;line-height:1.8">
                شكراً لتسجيلك معلماً في منصة رتل معي. يتم حالياً مراجعة طلبك وشهاداتك.
                سيتم تفعيل حسابك فور موافقة الإدارة.
            </p>
            <?php if (!empty($user['exam_required']) && empty($user['exam_passed'])): ?>
            <div class="alert alert-warning">تنبيه: يجب عليك اجتياز اختبار القرآن. سيتواصل معك أحد المشرفين قريباً.</div>
            <?php endif; ?>
            <a href="includes/auth.php?logout=1" class="btn btn-outline" style="margin-top:1rem">تسجيل الخروج</a>
        </div>

    <?php else: ?>

        <!-- إحصاء المعلم -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $pending_recitations; ?></div>
                <div class="stat-label">تلاوات قيد المراجعة</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $active_students; ?></div>
                <div class="stat-label">طلاب نشطون</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo count($groups); ?></div>
                <div class="stat-label">المجموعات</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($total_earnings, 0, '.', ','); ?> دج</div>
                <div class="stat-label">إجمالي الأرباح</div>
            </div>
        </div>

        <!-- روابط سريعة -->
        <div class="action-grid">
            <a href="recitation.php?view=students" class="action-card">
                <div class="action-icon"><img src="assets/images/group.svg" alt=""></div>
                <div class="action-title">قائمة الطلبة</div>
            </a>
            <a href="recitation.php?view=review" class="action-card">
                <div class="action-icon"><img src="assets/images/calendar.svg" alt=""></div>
                <div class="action-title">مراجعة التلاوات</div>
            </a>
            <a href="progress.php?view=reports" class="action-card">
                <div class="action-icon"><img src="assets/images/chart.svg" alt=""></div>
                <div class="action-title">تقارير الأداء</div>
            </a>
            <a href="live_session.php" class="action-card">
                <div class="action-icon"><img src="assets/images/mic.svg" alt=""></div>
                <div class="action-title">الجلسات المباشرة</div>
            </a>
            <a href="groups.php" class="action-card">
                <div class="action-icon"><img src="assets/images/group.svg" alt=""></div>
                <div class="action-title">إدارة المجموعات</div>
            </a>
            <a href="messages.php" class="action-card">
                <div class="action-icon"><img src="assets/images/book.svg" alt=""></div>
                <div class="action-title">الرسائل</div>
            </a>
        </div>

        <!-- ضبط الأسعار -->
        <div class="pricing-section">
            <h3>💰 تحديد الأسعار</h3>

            <!-- الأسعار الحالية المحفوظة -->
            <?php if ($teacher_pricing['session_price'] || $teacher_pricing['price_per_course']): ?>
            <div style="background:linear-gradient(135deg,rgba(64,145,108,.08),rgba(149,213,178,.1));border:1.5px solid rgba(64,145,108,.25);border-radius:var(--radius-md);padding:1rem 1.25rem;margin-bottom:1.25rem;display:flex;gap:2rem;flex-wrap:wrap">
                <?php if ($teacher_pricing['session_price']): ?>
                <div>
                    <div style="font-size:.8rem;color:var(--slate);margin-bottom:.2rem">سعر الجلسة الحالي</div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--secondary-green)"><?php echo number_format($teacher_pricing['session_price'],0,'.',','); ?> دج</div>
                </div>
                <?php endif; ?>
                <?php if ($teacher_pricing['price_per_course']): ?>
                <div>
                    <div style="font-size:.8rem;color:var(--slate);margin-bottom:.2rem">سعر الدورة الحالي</div>
                    <div style="font-size:1.3rem;font-weight:800;color:var(--secondary-green)"><?php echo number_format($teacher_pricing['price_per_course'],0,'.',','); ?> دج</div>
                </div>
                <?php endif; ?>
                <div style="align-self:center;font-size:.82rem;color:var(--accent-green)">✅ هذه هي أسعارك الظاهرة للطلاب</div>
            </div>
            <?php else: ?>
            <div style="background:#fff3cd;border:1px solid #fcd34d;border-radius:var(--radius-md);padding:.85rem 1rem;margin-bottom:1rem;font-size:.9rem;color:#92400e">
                ⚠️ لم تحدد أسعارك بعد — لن يتمكن الطلاب من حجز جلساتك حتى تضع سعراً
            </div>
            <?php endif; ?>

            <p class="pricing-note">المنصة تأخذ 15% عمولة — الأرباح الصافية تُضاف لمحفظتك مباشرة.</p>
            <form id="pricingForm">
                <div class="pricing-grid">
                    <div class="pricing-field">
                        <label for="session_price">سعر الجلسة الواحدة</label>
                        <input type="number" id="session_price" name="session_price"
                               value="<?php echo $teacher_pricing['session_price'] ?? ''; ?>"
                               min="100" step="50" placeholder="مثال: 500">
                        <span class="currency">دج</span>
                    </div>
                    <div class="pricing-field">
                        <label for="course_price">سعر الدورة الكاملة</label>
                        <input type="number" id="course_price" name="course_price"
                               value="<?php echo $teacher_pricing['price_per_course'] ?? ''; ?>"
                               min="100" step="50" placeholder="مثال: 3000">
                        <span class="currency">دج</span>
                    </div>
                </div>
                <div class="pricing-actions">
                    <button type="submit" class="btn btn-primary">💾 حفظ الأسعار</button>
                </div>
            </form>
            <div style="margin-top:1.25rem;border-top:1px solid var(--sand);padding-top:1rem">
                <div class="earnings-row"><span class="label">إجمالي الأرباح</span><span class="value"><?php echo number_format($total_earnings,0,'.',','); ?> دج</span></div>
                <div class="earnings-row"><span class="label">عمولة المنصة</span><span class="value">15% من كل معاملة</span></div>
                <div class="earnings-row"><span class="label">رصيد المحفظة</span><span class="value"><?php echo number_format($user['wallet_balance']??0,0,'.',','); ?> دج</span></div>
            </div>
        </div>

        <!-- التلاوات المعلقة -->
        <?php if (count($recent_recitations) > 0): ?>
        <div class="section-card">
            <h3>تلاوات تنتظر المراجعة</h3>
            <?php foreach ($recent_recitations as $r): ?>
            <div class="recitation-item">
                <div>
                    <div class="recitation-student"><?php echo htmlspecialchars($r['student_name']); ?></div>
                    <div class="recitation-details"><?php echo htmlspecialchars($r['surah_name']); ?><?php if ($r['ayah_range']) echo ' — ' . htmlspecialchars($r['ayah_range']); ?></div>
                </div>
                <a href="recitation.php?id=<?php echo $r['id']; ?>&view=correction" class="btn btn-primary" style="white-space:nowrap">مراجعة</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- الجلسات الجماعية -->
        <div class="section-card">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem">
                <h3 style="margin:0;border:none;padding:0">الجلسات الجماعية</h3>
                <button onclick="document.getElementById('createSessionModal').classList.add('show')" class="btn btn-secondary" style="padding:.6rem 1.2rem">＋ جلسة جديدة</button>
            </div>
            <?php if (count($teacher_sessions) > 0): ?>
            <div class="sessions-list">
                <?php foreach ($teacher_sessions as $s):
                    $stmap = ['scheduled'=>'مجدولة','live'=>'مباشرة الآن','ended'=>'منتهية'];
                ?>
                <div class="session-card">
                    <div style="flex:1">
                        <h4><?php echo htmlspecialchars($s['title']); ?></h4>
                        <p style="color:var(--slate);font-size:.9rem;margin:.2rem 0"><?php echo htmlspecialchars($s['description']??''); ?></p>
                        <div class="session-meta">
                            <span>📅 <?php echo date('Y/m/d H:i', strtotime($s['scheduled_at'])); ?></span>
                            <span>⏱ <?php echo $s['duration_minutes']; ?> دقيقة</span>
                            <span>👥 <?php echo $s['registered_count']; ?>/<?php echo $s['max_students']; ?></span>
                            <span class="status-<?php echo $s['status']; ?>"><?php echo $stmap[$s['status']] ?? $s['status']; ?></span>
                        </div>
                    </div>
                    <div class="session-actions">
                        <?php if ($s['status']==='scheduled'): ?>
                        <button onclick="updateSession(<?php echo $s['id']; ?>,'live')" class="btn btn-secondary">بدء</button>
                        <?php elseif ($s['status']==='live'): ?>
                        <?php if ($s['session_url']): ?><a href="<?php echo htmlspecialchars($s['session_url']); ?>" target="_blank" class="btn btn-primary">دخول</a><?php endif; ?>
                        <button onclick="updateSession(<?php echo $s['id']; ?>,'ended')" class="btn btn-outline" style="border-color:#dc2626;color:#dc2626">إنهاء</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p style="text-align:center;color:var(--slate);padding:2rem">لم تقم بإنشاء أي جلسات جماعية بعد.</p>
            <?php endif; ?>
        </div>

    <?php endif; // is_approved ?>

    <?php else: ?>
    <!-- ═══════════════ لوحة الطالب ═══════════════ -->

    <!-- شريط الاشتراك — اختياري للحصول على خصم 15% -->
    <?php if ($subscription): ?>
    <div class="sub-banner">
        <span class="sub-text">✅ اشتراك نشط — <?php echo htmlspecialchars($subscription['name_ar']); ?> (حتى <?php echo date('d/m/Y', strtotime($subscription['end_date'])); ?>)</span>
        <span style="font-weight:700;color:var(--secondary-green)">🎉 خصم 15% على جميع الجلسات</span>
    </div>
    <?php else: ?>
    <div style="background:linear-gradient(135deg,rgba(91,139,214,.08),rgba(45,74,138,.05));border:1px solid rgba(91,139,214,.2);border-radius:var(--radius-md);padding:.9rem 1.25rem;margin-bottom:1.25rem;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:.75rem">
        <span style="color:var(--slate);font-size:.9rem">💡 اشترك للحصول على <strong>خصم 15%</strong> على جميع الجلسات — الاشتراك اختياري</span>
        <a href="subscription.php" class="btn btn-outline" style="padding:.4rem 1rem;font-size:.85rem">عرض الباقات</a>
    </div>
    <?php endif; ?>

    <!-- إحصاء الطالب -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_recitations; ?></div>
            <div class="stat-label">إجمالي التلاوات</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $reviewed_recitations; ?></div>
            <div class="stat-label">تلاوات مراجعة</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $total_memorized; ?></div>
            <div class="stat-label">آيات محفوظة</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($wallet_balance,0,'.',','); ?> دج</div>
            <div class="stat-label">رصيد المحفظة</div>
        </div>
    </div>

    <!-- روابط سريعة -->
    <div class="action-grid">
        <a href="teachers.php" class="action-card"><div class="action-icon"><img src="assets/images/group.svg" alt=""></div><div class="action-title">🎓 المعلمون</div></a>
        <a href="recitation.php" class="action-card"><div class="action-icon"><img src="assets/images/play.svg" alt=""></div><div class="action-title">رفع تلاوة</div></a>
        <a href="progress.php" class="action-card"><div class="action-icon"><img src="assets/images/book.svg" alt=""></div><div class="action-title">جدول الحفظ</div></a>
        <a href="my_groups.php" class="action-card"><div class="action-icon"><img src="assets/images/group.svg" alt=""></div><div class="action-title">مجموعاتي</div></a>
        <a href="live_session.php" class="action-card"><div class="action-icon"><img src="assets/images/mic.svg" alt=""></div><div class="action-title">الجلسات</div></a>
        <a href="wallet.php" class="action-card"><div class="action-icon"><img src="assets/images/chart.svg" alt=""></div><div class="action-title">المحفظة</div></a>
    </div>

    <!-- المعلمون المتاحون -->
    <div class="teachers-section">
        <h3>المعلمون المتاحون</h3>
        <?php if (count($teachers) > 0): ?>
        <div class="teachers-grid">
            <?php foreach ($teachers as $t):
                $tp  = get_teacher_pricing($pdo, $t['id']);
                $avg = round($t['avg_rating'], 1);
                $stars = str_repeat('★', (int)$avg) . str_repeat('☆', 5-(int)$avg);
                $online = is_teacher_online($pdo, $t['id']);
                $initial = mb_substr($t['full_name'], 0, 1, 'UTF-8');
            ?>
            <div class="teacher-card">
                <div class="teacher-header">
                    <div class="teacher-avatar"><?php echo $initial; ?></div>
                    <div>
                        <div class="teacher-name">
                            <?php echo htmlspecialchars($t['full_name']); ?>
                            <?php if ($online): ?><span class="online-dot" title="متصل الآن"></span><?php else: ?><span class="offline-dot" title="غير متصل"></span><?php endif; ?>
                        </div>
                        <div class="teacher-rating">
                            <span class="stars"><?php echo $stars; ?></span>
                            <span>(<?php echo $avg; ?> / <?php echo $t['rating_count']; ?> تقييم)</span>
                        </div>
                    </div>
                </div>
                <?php if ($tp['session_price']): ?>
                <div class="teacher-pricing-row"><span class="price-label">جلسة واحدة</span><span class="price-value"><?php echo number_format($tp['session_price'],0,'.',','); ?> دج</span></div>
                <?php endif; ?>
                <?php if ($tp['price_per_course']): ?>
                <div class="teacher-pricing-row"><span class="price-label">الدورة كاملة</span><span class="price-value"><?php echo number_format($tp['price_per_course'],0,'.',','); ?> دج</span></div>
                <?php endif; ?>
                <?php if (!$tp['session_price'] && !$tp['price_per_course']): ?>
                <p style="color:var(--slate);font-size:.85rem;margin:.5rem 0">لم يحدد المعلم أسعاره بعد</p>
                <?php endif; ?>
                <div class="teacher-actions" style="margin-top:1rem;display:flex;flex-direction:column;gap:.5rem">
                    <?php if ($tp['session_price']): ?>
                    <button onclick="openPaymentModal(<?php echo $t['id']; ?>,'session',<?php echo $tp['session_price']; ?>,'<?php echo addslashes($t['full_name']); ?>')" class="btn btn-primary" style="font-size:.9rem;padding:.6rem">حجز جلسة</button>
                    <?php endif; ?>
                    <?php if ($tp['price_per_course']): ?>
                    <button onclick="openPaymentModal(<?php echo $t['id']; ?>,'course',<?php echo $tp['price_per_course']; ?>,'<?php echo addslashes($t['full_name']); ?>')" class="btn btn-secondary" style="font-size:.9rem;padding:.6rem">التسجيل في الدورة</button>
                    <?php endif; ?>
                    <button onclick="openRatingModal(<?php echo $t['id']; ?>,'<?php echo addslashes($t['full_name']); ?>')" class="btn btn-outline" style="font-size:.85rem;padding:.5rem">تقييم المعلم</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;padding:3rem;color:var(--slate)">لا يوجد معلمون متاحون حالياً.</div>
        <?php endif; ?>
    </div>

    <!-- آخر التصحيحات -->
    <?php if (count($recent_corrections) > 0): ?>
    <div class="section-card">
        <h3>آخر التصحيحات</h3>
        <?php foreach ($recent_corrections as $c): ?>
        <div class="recitation-item">
            <div>
                <div class="recitation-student"><?php echo htmlspecialchars($c['teacher_name']); ?></div>
                <div class="recitation-details"><?php echo htmlspecialchars($c['surah_name']??''); ?><?php if (!empty($c['ayah_range'])) echo ' — ' . htmlspecialchars($c['ayah_range']); ?></div>
            </div>
            <span style="font-size:.8rem;color:var(--slate)"><?php echo date('d/m/Y', strtotime($c['corrected_at'])); ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php endif; // student/teacher ?>
</div><!-- end padding -->

<!-- ═══════════ المودالات ═══════════ -->

<!-- مودال إنشاء جلسة (معلم) -->
<div class="modal-overlay" id="createSessionModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('createSessionModal').classList.remove('show')">✕</button>
        <h3>إنشاء جلسة جماعية جديدة</h3>
        <div class="form-group"><label class="form-label">عنوان الجلسة</label><input type="text" id="cs_title" class="form-input" placeholder="مثال: تعليم سورة البقرة"></div>
        <div class="form-group"><label class="form-label">الوصف</label><textarea id="cs_desc" class="form-input" rows="3"></textarea></div>
        <div class="form-group"><label class="form-label">التاريخ والوقت</label><input type="datetime-local" id="cs_date" class="form-input"></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
            <div class="form-group"><label class="form-label">المدة (دقيقة)</label><input type="number" id="cs_duration" class="form-input" value="60" min="15" max="240"></div>
            <div class="form-group"><label class="form-label">الحد الأقصى للطلاب</label><input type="number" id="cs_max" class="form-input" value="30" min="1" max="100"></div>
        </div>
        <div class="form-group"><label class="form-label">رابط الجلسة (اختياري)</label><input type="url" id="cs_url" class="form-input" placeholder="https://meet.google.com/..."></div>
        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button onclick="createSession()" class="btn btn-primary" style="flex:1">إنشاء الجلسة</button>
            <button onclick="document.getElementById('createSessionModal').classList.remove('show')" class="btn btn-outline" style="flex:1">إلغاء</button>
        </div>
    </div>
</div>

<!-- مودال الدفع (طالب) -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('paymentModal').classList.remove('show')">✕</button>
        <h3 id="pm_title">تأكيد الدفع</h3>
        <div style="background:var(--warm-cream);border-radius:var(--radius-md);padding:1.25rem;margin-bottom:1.25rem">
            <div class="payment-detail-row"><span class="lbl">المعلم</span><span class="val" id="pm_teacher">—</span></div>
            <div class="payment-detail-row"><span class="lbl">النوع</span><span class="val" id="pm_type">—</span></div>
            <div class="payment-detail-row"><span class="lbl">السعر الأساسي</span><span class="val" id="pm_base">—</span></div>
            <div class="payment-detail-row" id="pm_discount_row" style="display:none"><span class="lbl" style="color:var(--secondary-green)">خصم الاشتراك (15%)</span><span class="val" style="color:var(--secondary-green)" id="pm_discount">—</span></div>
            <div class="payment-detail-row" style="border-top:2px solid var(--sand);margin-top:.5rem;padding-top:.75rem"><span class="lbl" style="font-weight:700">المبلغ النهائي</span><span class="val" style="font-size:1.1rem;color:var(--royal-blue)" id="pm_final">—</span></div>
            <div class="payment-detail-row"><span class="lbl">رصيد محفظتك</span><span class="val" id="pm_wallet">—</span></div>
        </div>
        <div id="pm_msg" style="margin-bottom:1rem;font-size:.9rem;text-align:center"></div>
        <div style="display:flex;gap:.75rem">
            <button id="pm_confirm" onclick="confirmPayment()" class="btn btn-primary" style="flex:1">تأكيد الدفع</button>
            <button onclick="document.getElementById('paymentModal').classList.remove('show')" class="btn btn-outline" style="flex:1">إلغاء</button>
        </div>
    </div>
</div>

<!-- مودال التقييم (طالب) -->
<div class="modal-overlay" id="ratingModal">
    <div class="modal-box">
        <button class="modal-close" onclick="document.getElementById('ratingModal').classList.remove('show')">✕</button>
        <h3>تقييم المعلم: <span id="rm_name"></span></h3>
        <div class="form-group">
            <label class="form-label">التقييم</label>
            <div class="stars-input">
                <input type="radio" name="star" id="s5" value="5"><label for="s5">★</label>
                <input type="radio" name="star" id="s4" value="4"><label for="s4">★</label>
                <input type="radio" name="star" id="s3" value="3"><label for="s3">★</label>
                <input type="radio" name="star" id="s2" value="2"><label for="s2">★</label>
                <input type="radio" name="star" id="s1" value="1"><label for="s1">★</label>
            </div>
        </div>
        <div class="form-group"><label class="form-label">تعليق (اختياري)</label><textarea id="rm_comment" class="form-input" rows="3" placeholder="أضف تعليقك هنا..."></textarea></div>
        <div style="display:flex;gap:.75rem;margin-top:1rem">
            <button onclick="submitRating()" class="btn btn-primary" style="flex:1">إرسال التقييم</button>
            <button onclick="document.getElementById('ratingModal').classList.remove('show')" class="btn btn-outline" style="flex:1">إلغاء</button>
        </div>
    </div>
</div>

</div><!-- dashboard-container -->

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';

// ── ping للمعلمين كل دقيقة ──
<?php if ($user['user_type']==='teacher'): ?>
setInterval(() => {
    fetch(SITE_URL + '/api/ping.php', {method:'POST', credentials:'include'});
}, 60000);
<?php endif; ?>

// ── إنشاء جلسة ──
async function createSession() {
    const title = document.getElementById('cs_title').value.trim();
    const date  = document.getElementById('cs_date').value;
    if (!title) { alert('يرجى إدخال عنوان الجلسة'); return; }
    if (!date)  { alert('يرجى تحديد تاريخ ووقت الجلسة'); return; }

    const btn = document.querySelector('#createSessionModal .btn-primary');
    if (btn) { btn.disabled = true; btn.textContent = '...جارٍ الإنشاء'; }

    try {
        const data = new URLSearchParams({
            action: 'create_session',
            title,
            description: document.getElementById('cs_desc').value,
            scheduled_at: date,
            duration_minutes: document.getElementById('cs_duration').value,
            max_students: document.getElementById('cs_max').value,
            session_url: document.getElementById('cs_url').value
        });
        const resp = await fetch(SITE_URL + '/api/group_sessions.php', {
            method:'POST', body:data, credentials:'include'
        });
        const text = await resp.text();
        let d;
        try { d = JSON.parse(text); }
        catch(e) { alert('خطأ في الاستجابة: ' + text.substring(0,200)); return; }

        if (d.success) {
            document.getElementById('createSessionModal').classList.remove('show');
            alert('✅ تم إنشاء الجلسة بنجاح!');
            location.reload();
        } else {
            alert('❌ ' + (d.message || 'حدث خطأ غير معروف'));
        }
    } catch(err) {
        alert('❌ فشل الاتصال: ' + err.message);
    } finally {
        if (btn) { btn.disabled = false; btn.textContent = 'إنشاء الجلسة'; }
    }
}

// ── تحديث حالة جلسة ──
function updateSession(id, status) {
    const labels = {live:'بدء', ended:'إنهاء'};
    if (!confirm('هل أنت متأكد من ' + (labels[status]||status) + ' الجلسة؟')) return;
    fetch(SITE_URL + '/api/group_sessions.php', {
        method:'POST', credentials:'include',
        body: new URLSearchParams({action:'update_status', session_id:id, status})
    }).then(r => r.json()).then(d => {
        if (d.success) location.reload();
        else alert(d.message || 'حدث خطأ');
    });
}

// ── مودال الدفع ──
let _pm = {};
function openPaymentModal(teacherId, type, basePrice, teacherName) {
    _pm = {teacherId, type, basePrice, teacherName};
    document.getElementById('pm_teacher').textContent = teacherName;
    document.getElementById('pm_type').textContent = type === 'session' ? 'جلسة واحدة' : 'دورة كاملة';
    document.getElementById('pm_base').textContent = basePrice.toLocaleString('ar') + ' دج';
    document.getElementById('pm_discount_row').style.display = 'none';
    document.getElementById('pm_msg').textContent = '...جارٍ التحميل';

    fetch(SITE_URL + '/api/wallet_balance.php', {credentials:'include'})
        .then(r => r.json()).then(w => {
            const bal = w.balance || 0;
            let final = basePrice, disc = 0;
            <?php if (has_subscription_discount($pdo, $user['id'])): ?>
            disc = basePrice * 0.15; final = basePrice - disc;
            document.getElementById('pm_discount_row').style.display = 'flex';
            document.getElementById('pm_discount').textContent = '-' + disc.toLocaleString('ar') + ' دج';
            <?php endif; ?>
            _pm.final = final;
            document.getElementById('pm_final').textContent = final.toLocaleString('ar') + ' دج';
            document.getElementById('pm_wallet').textContent = bal.toLocaleString('ar') + ' دج';
            const ok = bal >= final;
            document.getElementById('pm_msg').innerHTML = ok
                ? '<span style="color:var(--secondary-green)">✅ رصيدك كافٍ</span>'
                : '<span style="color:#dc2626">❌ رصيدك غير كافٍ — <a href="wallet.php">شحن المحفظة</a></span>';
            document.getElementById('pm_confirm').disabled = !ok;
            document.getElementById('paymentModal').classList.add('show');
        });
}

function confirmPayment() {
    fetch(SITE_URL + '/api/payment.php', {
        method:'POST', credentials:'include',
        body: new URLSearchParams({action:'pay_teacher', teacher_id:_pm.teacherId, payment_type:_pm.type})
    }).then(r => r.json()).then(d => {
        if (d.success) { alert('تم الدفع بنجاح! يمكنك الآن حجز جلسة مع ' + _pm.teacherName); document.getElementById('paymentModal').classList.remove('show'); location.reload(); }
        else alert(d.message || 'فشل الدفع');
    });
}

// ── مودال التقييم ──
let _rm = {};
function openRatingModal(teacherId, name) {
    _rm = {teacherId};
    document.getElementById('rm_name').textContent = name;
    document.querySelectorAll('input[name=star]').forEach(i => i.checked = false);
    document.getElementById('rm_comment').value = '';
    document.getElementById('ratingModal').classList.add('show');
}

function submitRating() {
    const star = document.querySelector('input[name=star]:checked');
    if (!star) { alert('يرجى اختيار عدد النجوم'); return; }
    fetch(SITE_URL + '/api/rate_teacher.php', {
        method:'POST', credentials:'include',
        body: new URLSearchParams({teacher_id:_rm.teacherId, rating:star.value, comment:document.getElementById('rm_comment').value})
    }).then(r => r.json()).then(d => {
        alert(d.message || (d.success ? 'تم التقييم!' : 'فشل التقييم'));
        if (d.success) { document.getElementById('ratingModal').classList.remove('show'); location.reload(); }
    });
}

// ── ضبط الأسعار ──
document.getElementById('pricingForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'update_pricing',
        session_price: document.getElementById('session_price')?.value || '',
        course_price:  document.getElementById('course_price')?.value  || ''
    });
    const resp = await fetch(SITE_URL + '/api/payment.php', {
        method:'POST', credentials:'include',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body
    });
    const d = await resp.json();
    alert(d.message || (d.success ? 'تم حفظ الأسعار بنجاح!' : 'فشل الحفظ'));
    if (d.success) location.reload();
});

// إغلاق المودالات بالنقر خارجها
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); });
});
</script>
<script src="assets/js/dashboard.js"></script>
<?php
} catch (Throwable $e) {
    ob_end_clean();
    error_log('dashboard.php fatal: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    echo '<pre style="color:red;padding:20px">Dashboard Error: ' . htmlspecialchars($e->getMessage()) . "
" . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</pre>';
    exit;
}
ob_end_flush();
?>
</body>
</html>
