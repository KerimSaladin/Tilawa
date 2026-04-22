<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

$has_access = has_active_access($pdo, $user['id']);
if (!$has_access) redirect('subscription.php?required=1');

if ($user['user_type'] === 'teacher' && !is_teacher_approved($pdo, $user['id'])) redirect('dashboard.php');

$flash = null;

// ══════════════════════════════════════════════════════════════
// إجراءات المعلم
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['user_type'] === 'teacher') {
    $action = $_POST['action'] ?? '';

    // إضافة موعد فردي متاح
    if ($action === 'add_slot') {
        $slot_date  = $_POST['slot_date'] ?? '';
        $slot_time  = $_POST['slot_time'] ?? '';
        $duration   = (int)($_POST['duration'] ?? 60);
        $session_type = $_POST['session_type'] ?? 'individual'; // individual | group
        $max_students = $session_type === 'group' ? (int)($_POST['max_students'] ?? 20) : 1;
        $title        = sanitize_input($_POST['title'] ?? 'جلسة تحفيظ');
        $description  = sanitize_input($_POST['description'] ?? '');

        if (!$slot_date || !$slot_time) {
            $flash = ['type'=>'error','msg'=>'يرجى تحديد التاريخ والوقت'];
        } else {
            $start_dt = $slot_date . ' ' . $slot_time . ':00';
            if ($session_type === 'group') {
                $pdo->prepare("
                    INSERT INTO group_sessions (teacher_id, title, description, scheduled_at, duration_minutes, max_students, status)
                    VALUES (?,?,?,?,?,?,'scheduled')
                ")->execute([$user['id'], $title, $description, $start_dt, $duration, $max_students]);
            } else {
                $stmt_ls = $pdo->prepare("
                    INSERT INTO live_sessions (teacher_id, student_id, status, start_time)
                    VALUES (?, NULL, 'available', ?) RETURNING id
                ");
                $stmt_ls->execute([$user['id'], $start_dt]);
                $lid = $stmt_ls->fetchColumn();
                $pdo->prepare("UPDATE live_sessions SET end_time = start_time + (? * INTERVAL '1 minute') WHERE id=?")
                    ->execute([$duration, $lid]);
            }
            $flash = ['type'=>'success','msg'=>'تم إضافة الموعد بنجاح!'];
        }
    }

    // إلغاء موعد
    if ($action === 'cancel_slot') {
        $lid = (int)$_POST['session_id'];
        $pdo->prepare("UPDATE live_sessions SET status='cancelled' WHERE id=? AND teacher_id=?")->execute([$lid, $user['id']]);
        $flash = ['type'=>'success','msg'=>'تم إلغاء الموعد.'];
    }

    // بدء/إنهاء جلسة جماعية
    if ($action === 'update_group_status') {
        $gid    = (int)$_POST['session_id'];
        $status = $_POST['status'] ?? 'live';
        $pdo->prepare("UPDATE group_sessions SET status=? WHERE id=? AND teacher_id=?")->execute([$status, $gid, $user['id']]);
        $flash = ['type'=>'success','msg'=>'تم تحديث حالة الجلسة.'];
    }
}

// ══════════════════════════════════════════════════════════════
// إجراءات الطالب
// ══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user['user_type'] === 'student') {
    $action = $_POST['action'] ?? '';

    // حجز موعد فردي
    if ($action === 'book_slot') {
        $lid = (int)$_POST['session_id'];
        // تحقق من أن الموعد متاح ولم يُحجز
        $stmt = $pdo->prepare("SELECT * FROM live_sessions WHERE id=? AND status='available' AND (student_id IS NULL OR student_id=0)");
        $stmt->execute([$lid]);
        $slot = $stmt->fetch();
        if (!$slot) {
            $flash = ['type'=>'error','msg'=>'هذا الموعد غير متاح أو تم حجزه مسبقاً'];
        } else {
            $pdo->prepare("UPDATE live_sessions SET student_id=?, status='scheduled' WHERE id=? AND (student_id IS NULL OR student_id=0)")->execute([$user['id'], $lid]);
            $flash = ['type'=>'success','msg'=>'تم حجز الموعد بنجاح! سيتواصل معك المعلم قريباً.'];
        }
    }

    // الانضمام لجلسة جماعية
    if ($action === 'join_group_session') {
        $gid = (int)$_POST['session_id'];
        $stmt = $pdo->prepare("SELECT gs.*, (SELECT COUNT(*) FROM group_session_participants WHERE session_id=gs.id) as cnt FROM group_sessions gs WHERE gs.id=? AND gs.status IN ('scheduled','live')");
        $stmt->execute([$gid]);
        $gs = $stmt->fetch();
        if (!$gs) {
            $flash = ['type'=>'error','msg'=>'الجلسة غير متاحة'];
        } elseif ($gs['cnt'] >= $gs['max_students']) {
            $flash = ['type'=>'error','msg'=>'الجلسة ممتلئة'];
        } else {
            try {
                $pdo->prepare("INSERT INTO group_session_participants (session_id, student_id) VALUES (?,?) ON CONFLICT DO NOTHING")->execute([$gid, $user['id']]);
                $flash = ['type'=>'success','msg'=>'تم التسجيل في الجلسة بنجاح!'];
            } catch (Exception $e) {
                $flash = ['type'=>'error','msg'=>'تعذّر الانضمام'];
            }
        }
    }

    // إلغاء حجز
    if ($action === 'cancel_booking') {
        $lid = (int)$_POST['session_id'];
        $pdo->prepare("UPDATE live_sessions SET student_id=NULL, status='available' WHERE id=? AND student_id=?")->execute([$lid, $user['id']]);
        $flash = ['type'=>'success','msg'=>'تم إلغاء الحجز.'];
    }
}

// ══════════════════════════════════════════════════════════════
// جلب البيانات
// ══════════════════════════════════════════════════════════════
if ($user['user_type'] === 'teacher') {

    // مواعيدي الفردية المتاحة والمحجوزة
    $my_slots = $pdo->prepare("
        SELECT ls.*, u.full_name as student_name
        FROM live_sessions ls
        LEFT JOIN users u ON ls.student_id = u.id AND ls.student_id > 0
        WHERE ls.teacher_id=? AND ls.status IN ('available','scheduled','active')
        ORDER BY ls.start_time ASC
    ");
    $my_slots->execute([$user['id']]);
    $my_slots = $my_slots->fetchAll();

    // جلساتي الجماعية
    $my_group_sessions = $pdo->prepare("
        SELECT gs.*,
               (SELECT COUNT(*) FROM group_session_participants WHERE session_id=gs.id) as registered_count
        FROM group_sessions gs
        WHERE gs.teacher_id=? AND gs.status IN ('scheduled','live')
        ORDER BY gs.scheduled_at ASC
    ");
    $my_group_sessions->execute([$user['id']]);
    $my_group_sessions = $my_group_sessions->fetchAll();

    // طلابي المسجلون معي
    $my_students = $pdo->prepare("
        SELECT DISTINCT u.id, u.full_name
        FROM users u
        WHERE u.user_type='student' AND u.is_active=1
        AND u.id IN (
            SELECT DISTINCT student_id FROM recitations WHERE teacher_id=?
            UNION SELECT DISTINCT student_id FROM enrollments WHERE teacher_id=?
        )
        ORDER BY u.full_name
    ");
    $my_students->execute([$user['id'], $user['id']]);
    $my_students = $my_students->fetchAll();

} else {
    // الطالب — مواعيد فردية متاحة من جميع المعلمين
    $available_slots = $pdo->prepare("
        SELECT ls.*, u.full_name as teacher_name, u.id as teacher_id,
               COALESCE(AVG(r.rating),0) as avg_rating
        FROM live_sessions ls
        JOIN users u ON ls.teacher_id=u.id
        LEFT JOIN ratings r ON r.teacher_id=u.id
        WHERE ls.status='available' AND (ls.student_id IS NULL OR ls.student_id = 0)
        AND ls.start_time > NOW()
        GROUP BY ls.id, u.full_name, u.id
        ORDER BY ls.start_time ASC
    ");
    $available_slots->execute([]);
    $available_slots = $available_slots->fetchAll();

    // حجوزاتي
    $my_bookings = $pdo->prepare("
        SELECT ls.*, u.full_name as teacher_name
        FROM live_sessions ls
        JOIN users u ON ls.teacher_id=u.id
        WHERE ls.student_id=? AND ls.status IN ('scheduled','active')
        ORDER BY ls.start_time ASC
    ");
    $my_bookings->execute([$user['id']]);
    $my_bookings = $my_bookings->fetchAll();

    // الجلسات الجماعية المتاحة
    $group_sessions_available = $pdo->prepare("
        SELECT gs.*, u.full_name as teacher_name,
               (SELECT COUNT(*) FROM group_session_participants WHERE session_id=gs.id) as registered_count,
               (SELECT COUNT(*) FROM group_session_participants WHERE session_id=gs.id AND student_id=?) as is_registered
        FROM group_sessions gs
        JOIN users u ON gs.teacher_id=u.id
        WHERE gs.status IN ('scheduled','live') AND gs.scheduled_at > NOW()
        ORDER BY gs.scheduled_at ASC
    ");
    $group_sessions_available->execute([$user['id']]);
    $group_sessions_available = $group_sessions_available->fetchAll();

    // جلساتي الجماعية المسجل فيها
    $my_group_registrations = $pdo->prepare("
        SELECT gs.*, u.full_name as teacher_name, gsp.joined_at
        FROM group_sessions gs
        JOIN group_session_participants gsp ON gs.id=gsp.session_id
        JOIN users u ON gs.teacher_id=u.id
        WHERE gsp.student_id=? AND gs.status IN ('scheduled','live')
        ORDER BY gs.scheduled_at ASC
    ");
    $my_group_registrations->execute([$user['id']]);
    $my_group_registrations = $my_group_registrations->fetchAll();
}

$active_page = 'live';
$status_ar = ['available'=>'متاح','scheduled'=>'محجوز','active'=>'جارٍ الآن','ended'=>'منتهي','cancelled'=>'ملغي'];
$gstatus_ar = ['scheduled'=>'مجدولة','live'=>'مباشرة الآن','ended'=>'منتهية'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>الجلسات — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .tabs{display:flex;gap:.5rem;margin-bottom:1.5rem;border-bottom:2px solid var(--sand);padding-bottom:0;flex-wrap:wrap}
        .tab-btn{padding:.7rem 1.5rem;border:none;background:none;border-bottom:3px solid transparent;margin-bottom:-2px;cursor:pointer;font-family:inherit;font-size:.95rem;font-weight:600;color:var(--slate);transition:var(--transition-base)}
        .tab-btn.active{color:var(--royal-blue);border-bottom-color:var(--royal-blue)}
        .tab-content{display:none}.tab-content.active{display:block}
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .slot-card{background:var(--warm-cream);border:1px solid var(--sand);border-radius:var(--radius-lg);padding:1.25rem;display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:.75rem;flex-wrap:wrap;transition:var(--transition-bounce)}
        .slot-card:hover{box-shadow:var(--shadow-md);transform:translateY(-2px)}
        .slot-datetime{font-weight:700;font-size:1rem;color:var(--charcoal)}
        .slot-meta{font-size:.85rem;color:var(--slate);margin-top:.3rem}
        .slot-status{padding:.25rem .75rem;border-radius:50px;font-size:.8rem;font-weight:700}
        .status-available{background:#d1fae5;color:#065f46}
        .status-scheduled{background:#dbeafe;color:#1d4ed8}
        .status-active{background:#fef3c7;color:#92400e}
        .status-cancelled{background:#f1f5f9;color:#64748b}
        .slot-actions{display:flex;gap:.5rem;flex-wrap:wrap;flex-shrink:0}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        @media(max-width:600px){.form-row{grid-template-columns:1fr}}
        .teacher-initial{width:42px;height:42px;border-radius:50%;background:linear-gradient(135deg,var(--soft-blue),var(--royal-blue));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.1rem;flex-shrink:0}
        .rating-stars{color:#f59e0b;font-size:.9rem}
        .gs-card{background:var(--warm-cream);border-radius:var(--radius-lg);padding:1.25rem;border:1px solid var(--sand);margin-bottom:.75rem}
        .gs-card h4{font-weight:700;margin-bottom:.4rem}
        .gs-meta{display:flex;flex-wrap:wrap;gap:.5rem;margin:.5rem 0}
        .gs-meta span{background:white;padding:.2rem .6rem;border-radius:50px;font-size:.8rem;border:1px solid var(--sand);color:var(--slate)}
        .session-type-toggle{display:flex;gap:.5rem;margin-bottom:1rem}
        .type-btn{padding:.5rem 1.25rem;border:2px solid var(--sand);border-radius:50px;background:white;cursor:pointer;font-family:inherit;font-size:.9rem;font-weight:600;color:var(--slate);transition:var(--transition-base)}
        .type-btn.active{border-color:var(--royal-blue);background:var(--royal-blue);color:white}
        #groupFields{display:none}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include 'includes/header.php'; ?>
<div style="padding:2rem">

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:1rem"><?php echo htmlspecialchars($flash['msg']); ?></div>
<?php endif; ?>

<?php if ($user['user_type'] === 'teacher'): ?>
<!-- ═══════════ واجهة المعلم ═══════════ -->

<div class="tabs">
    <button class="tab-btn active" onclick="showTab('add-slot')">➕ إضافة موعد</button>
    <button class="tab-btn" onclick="showTab('my-slots')">📅 مواعيدي الفردية (<?php echo count($my_slots); ?>)</button>
    <button class="tab-btn" onclick="showTab('group-sessions')">👥 الجلسات الجماعية (<?php echo count($my_group_sessions); ?>)</button>
</div>

<!-- إضافة موعد -->
<div class="tab-content active" id="tab-add-slot">
    <div class="section-card">
        <h3>إضافة موعد جديد</h3>
        <form method="POST">
            <input type="hidden" name="action" value="add_slot">

            <div style="margin-bottom:1.25rem">
                <label class="form-label">نوع الجلسة</label>
                <div class="session-type-toggle">
                    <button type="button" class="type-btn active" onclick="setType('individual',this)">فردية</button>
                    <button type="button" class="type-btn" onclick="setType('group',this)">جماعية</button>
                </div>
                <input type="hidden" name="session_type" id="session_type_input" value="individual">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">التاريخ</label>
                    <input type="date" name="slot_date" class="form-input" min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">الوقت</label>
                    <input type="time" name="slot_time" class="form-input" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">المدة (دقيقة)</label>
                    <select name="duration" class="form-select">
                        <option value="30">30 دقيقة</option>
                        <option value="45">45 دقيقة</option>
                        <option value="60" selected>60 دقيقة</option>
                        <option value="90">90 دقيقة</option>
                        <option value="120">ساعتان</option>
                    </select>
                </div>
                <div class="form-group" id="groupFields" style="display:none">
                    <label class="form-label">الحد الأقصى للطلاب</label>
                    <input type="number" name="max_students" class="form-input" value="20" min="2" max="200">
                </div>
            </div>

            <div id="groupTitleFields" style="display:none">
                <div class="form-group">
                    <label class="form-label">عنوان الجلسة</label>
                    <input type="text" name="title" class="form-input" placeholder="مثال: تعليم سورة البقرة">
                </div>
                <div class="form-group">
                    <label class="form-label">وصف الجلسة (اختياري)</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="تفاصيل الجلسة..."></textarea>
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="margin-top:.5rem">إضافة الموعد</button>
        </form>
    </div>
</div>

<!-- مواعيدي الفردية -->
<div class="tab-content" id="tab-my-slots">
    <div class="section-card">
        <h3>مواعيدي الفردية</h3>
        <?php if ($my_slots): ?>
        <?php foreach ($my_slots as $s): ?>
        <div class="slot-card">
            <div style="flex:1">
                <div class="slot-datetime">📅 <?php echo date('l d/m/Y', strtotime($s['start_time'])); ?> — <?php echo date('H:i', strtotime($s['start_time'])); ?></div>
                <div class="slot-meta">
                    <?php if ($s['end_time']): ?>⏱ حتى <?php echo date('H:i', strtotime($s['end_time'])); ?><?php endif; ?>
                    <?php if ($s['student_id'] > 0 && $s['student_name']): ?> | 👤 <?php echo htmlspecialchars($s['student_name']); ?><?php endif; ?>
                </div>
            </div>
            <div class="slot-actions">
                <span class="slot-status status-<?php echo $s['status']; ?>"><?php echo $status_ar[$s['status']]??$s['status']; ?></span>
                <?php if ($s['status']==='available' || $s['status']==='scheduled'): ?>
                <form method="POST" style="display:inline" onsubmit="return confirm('إلغاء هذا الموعد؟')">
                    <input type="hidden" name="action" value="cancel_slot">
                    <input type="hidden" name="session_id" value="<?php echo $s['id']; ?>">
                    <button class="btn btn-outline" style="border-color:#dc2626;color:#dc2626;padding:.4rem .9rem;font-size:.85rem">إلغاء</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:2rem;color:var(--slate)">لا توجد مواعيد مضافة. أضف موعداً جديداً من تبويب "إضافة موعد".</div>
        <?php endif; ?>
    </div>
</div>

<!-- الجلسات الجماعية -->
<div class="tab-content" id="tab-group-sessions">
    <div class="section-card">
        <h3>الجلسات الجماعية</h3>
        <?php if ($my_group_sessions): ?>
        <?php foreach ($my_group_sessions as $gs): ?>
        <div class="gs-card">
            <h4><?php echo htmlspecialchars($gs['title']); ?></h4>
            <?php if ($gs['description']): ?><p style="color:var(--slate);font-size:.88rem;margin:.3rem 0"><?php echo htmlspecialchars($gs['description']); ?></p><?php endif; ?>
            <div class="gs-meta">
                <span>📅 <?php echo date('d/m/Y H:i', strtotime($gs['scheduled_at'])); ?></span>
                <span>⏱ <?php echo $gs['duration_minutes']; ?> دقيقة</span>
                <span>👥 <?php echo $gs['registered_count']; ?>/<?php echo $gs['max_students']; ?> طالب</span>
                <span class="status-<?php echo $gs['status']; ?>" style="border-color:transparent!important"><?php echo $gstatus_ar[$gs['status']]??$gs['status']; ?></span>
            </div>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.75rem">
                <?php if ($gs['status']==='scheduled'): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="update_group_status">
                    <input type="hidden" name="session_id" value="<?php echo $gs['id']; ?>">
                    <input type="hidden" name="status" value="live">
                    <button class="btn btn-secondary" style="padding:.45rem 1rem;font-size:.88rem">▶ بدء الجلسة</button>
                </form>
                <?php elseif ($gs['status']==='live'): ?>
                <form method="POST" style="display:inline">
                    <input type="hidden" name="action" value="update_group_status">
                    <input type="hidden" name="session_id" value="<?php echo $gs['id']; ?>">
                    <input type="hidden" name="status" value="ended">
                    <button class="btn btn-outline" style="border-color:#dc2626;color:#dc2626;padding:.45rem 1rem;font-size:.88rem">⏹ إنهاء</button>
                </form>
                <?php endif; ?>
                <!-- رابط الجلسة الحية عبر WebRTC -->
                <a href="call.php?session_id=<?php echo $gs['id']; ?>&type=group" class="btn btn-primary" style="padding:.45rem 1rem;font-size:.88rem">🎥 فتح الجلسة</a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:2rem;color:var(--slate)">لا توجد جلسات جماعية. أضف جلسة من تبويب "إضافة موعد" بتحديد نوع "جماعية".</div>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ═══════════ واجهة الطالب ═══════════ -->

<div class="tabs">
    <button class="tab-btn active" onclick="showTab('individual')">📅 مواعيد فردية</button>
    <button class="tab-btn" onclick="showTab('group')">👥 جلسات جماعية</button>
    <button class="tab-btn" onclick="showTab('my-bookings')">✅ حجوزاتي (<?php echo count($my_bookings); ?>)</button>
</div>

<!-- المواعيد الفردية المتاحة -->
<div class="tab-content active" id="tab-individual">
    <div class="section-card">
        <h3>المواعيد الفردية المتاحة</h3>
        <?php if ($available_slots): ?>
        <?php foreach ($available_slots as $s): ?>
        <div class="slot-card">
            <div style="display:flex;align-items:center;gap:.75rem;flex:1">
                <div class="teacher-initial"><?php echo mb_substr($s['teacher_name'],0,1,'UTF-8'); ?></div>
                <div>
                    <div class="slot-datetime"><?php echo htmlspecialchars($s['teacher_name']); ?>
                        <span class="rating-stars" style="font-size:.8rem;margin-right:.4rem"><?php echo str_repeat('★',(int)round($s['avg_rating'])) . str_repeat('☆',5-(int)round($s['avg_rating'])); ?></span>
                    </div>
                    <div class="slot-meta">
                        📅 <?php echo date('l d/m/Y', strtotime($s['start_time'])); ?>
                        — ⏰ <?php echo date('H:i', strtotime($s['start_time'])); ?>
                        <?php if ($s['end_time']): ?> حتى <?php echo date('H:i', strtotime($s['end_time'])); ?><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="slot-actions">
                <form method="POST">
                    <input type="hidden" name="action" value="book_slot">
                    <input type="hidden" name="session_id" value="<?php echo $s['id']; ?>">
                    <button class="btn btn-primary" style="padding:.5rem 1.25rem;font-size:.9rem">حجز الموعد</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:3rem;color:var(--slate)">لا توجد مواعيد فردية متاحة حالياً. تحقق لاحقاً.</div>
        <?php endif; ?>
    </div>
</div>

<!-- الجلسات الجماعية -->
<div class="tab-content" id="tab-group">
    <div class="section-card">
        <h3>الجلسات الجماعية المتاحة</h3>
        <?php if ($group_sessions_available): ?>
        <?php foreach ($group_sessions_available as $gs): ?>
        <div class="gs-card">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:1rem">
                <div style="flex:1">
                    <h4><?php echo htmlspecialchars($gs['title']); ?></h4>
                    <div style="font-size:.88rem;color:var(--slate);margin:.25rem 0">👨‍🏫 <?php echo htmlspecialchars($gs['teacher_name']); ?></div>
                    <?php if ($gs['description']): ?><p style="font-size:.88rem;color:var(--slate)"><?php echo htmlspecialchars($gs['description']); ?></p><?php endif; ?>
                    <div class="gs-meta">
                        <span>📅 <?php echo date('d/m/Y H:i', strtotime($gs['scheduled_at'])); ?></span>
                        <span>⏱ <?php echo $gs['duration_minutes']; ?> دقيقة</span>
                        <span>👥 <?php echo $gs['registered_count']; ?>/<?php echo $gs['max_students']; ?></span>
                        <?php if ($gs['status']==='live'): ?><span style="background:#dcfce7;color:#166534;border-color:#4ade80">🔴 مباشر الآن</span><?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;flex-direction:column;gap:.5rem;min-width:140px">
                    <?php if ($gs['is_registered']): ?>
                    <div style="background:#d1fae5;color:#065f46;padding:.5rem 1rem;border-radius:var(--radius-md);font-weight:700;font-size:.88rem;text-align:center">✅ مسجّل</div>
                    <?php if ($gs['status']==='live'): ?>
                    <a href="call.php?session_id=<?php echo $gs['id']; ?>&type=group" class="btn btn-primary" style="font-size:.88rem;text-align:center">🎥 دخول الجلسة</a>
                    <?php endif; ?>
                    <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="join_group_session">
                        <input type="hidden" name="session_id" value="<?php echo $gs['id']; ?>">
                        <button class="btn btn-secondary" style="width:100%;font-size:.88rem">التسجيل</button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:3rem;color:var(--slate)">لا توجد جلسات جماعية متاحة حالياً.</div>
        <?php endif; ?>
    </div>
</div>

<!-- حجوزاتي -->
<div class="tab-content" id="tab-my-bookings">
    <div class="section-card">
        <h3>حجوزاتي الفردية</h3>
        <?php if ($my_bookings): ?>
        <?php foreach ($my_bookings as $b): ?>
        <div class="slot-card">
            <div style="display:flex;align-items:center;gap:.75rem;flex:1">
                <div class="teacher-initial"><?php echo mb_substr($b['teacher_name'],0,1,'UTF-8'); ?></div>
                <div>
                    <div class="slot-datetime"><?php echo htmlspecialchars($b['teacher_name']); ?></div>
                    <div class="slot-meta">
                        📅 <?php echo date('l d/m/Y', strtotime($b['start_time'])); ?>
                        — ⏰ <?php echo date('H:i', strtotime($b['start_time'])); ?>
                    </div>
                </div>
            </div>
            <div class="slot-actions">
                <span class="slot-status status-<?php echo $b['status']; ?>"><?php echo $status_ar[$b['status']]??$b['status']; ?></span>
                <?php if ($b['status']==='active'): ?>
                <a href="call.php?session_id=<?php echo $b['id']; ?>&type=individual" class="btn btn-primary" style="padding:.4rem 1rem;font-size:.88rem">🎥 دخول</a>
                <?php endif; ?>
                <?php if ($b['status']==='scheduled'): ?>
                <form method="POST" onsubmit="return confirm('إلغاء هذا الحجز؟')">
                    <input type="hidden" name="action" value="cancel_booking">
                    <input type="hidden" name="session_id" value="<?php echo $b['id']; ?>">
                    <button class="btn btn-outline" style="border-color:#dc2626;color:#dc2626;padding:.4rem .9rem;font-size:.85rem">إلغاء</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:2rem;color:var(--slate)">لا توجد حجوزات حالية.</div>
        <?php endif; ?>
    </div>

    <div class="section-card">
        <h3>الجلسات الجماعية المسجّل فيها</h3>
        <?php if ($my_group_registrations): ?>
        <?php foreach ($my_group_registrations as $gr): ?>
        <div class="gs-card">
            <h4><?php echo htmlspecialchars($gr['title']); ?></h4>
            <div style="font-size:.88rem;color:var(--slate);margin:.25rem 0">👨‍🏫 <?php echo htmlspecialchars($gr['teacher_name']); ?></div>
            <div class="gs-meta">
                <span>📅 <?php echo date('d/m/Y H:i', strtotime($gr['scheduled_at'])); ?></span>
                <span>⏱ <?php echo $gr['duration_minutes']; ?> دقيقة</span>
                <?php if ($gr['status']==='live'): ?><span style="background:#dcfce7;color:#166534;border-color:#4ade80">🔴 مباشر الآن</span><?php endif; ?>
            </div>
            <?php if ($gr['status']==='live'): ?>
            <a href="call.php?session_id=<?php echo $gr['id']; ?>&type=group" class="btn btn-primary" style="margin-top:.75rem;font-size:.88rem;display:inline-block">🎥 دخول الجلسة</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;padding:2rem;color:var(--slate)">لم تسجل في أي جلسة جماعية بعد.</div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>
</div>
</div>

<script>
function showTab(id) {
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    const el = document.getElementById('tab-' + id);
    if (el) el.classList.add('active');
    event.target.classList.add('active');
}

function setType(type, btn) {
    document.getElementById('session_type_input').value = type;
    document.querySelectorAll('.type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    const gf = document.getElementById('groupFields');
    const gtf = document.getElementById('groupTitleFields');
    if (type === 'group') { gf.style.display='block'; gtf.style.display='block'; }
    else { gf.style.display='none'; gtf.style.display='none'; }
}
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
