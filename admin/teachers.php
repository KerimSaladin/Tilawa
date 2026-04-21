<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'admin') redirect('../login.php');

// معالجة الإجراءات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = $_POST['action'] ?? '';
    $teacher_id = (int)($_POST['teacher_id'] ?? 0);
    if ($teacher_id) {
        if ($action === 'approve') {
            $pdo->prepare("UPDATE users SET teacher_status='approved' WHERE id=? AND user_type='teacher'")->execute([$teacher_id]);
            log_activity($pdo, $user['id'], 'teacher_approved', 'تمت الموافقة على معلم #' . $teacher_id, 'user', $teacher_id);
            $_SESSION['flash_success'] = 'تمت الموافقة على المعلم بنجاح.';
        } elseif ($action === 'reject') {
            $pdo->prepare("UPDATE users SET teacher_status='rejected' WHERE id=? AND user_type='teacher'")->execute([$teacher_id]);
            log_activity($pdo, $user['id'], 'teacher_rejected', 'تم رفض معلم #' . $teacher_id, 'user', $teacher_id);
            $_SESSION['flash_success'] = 'تم رفض طلب المعلم.';
        } elseif ($action === 'toggle_star') {
            $pdo->prepare("UPDATE users SET has_star = 1 - has_star WHERE id=?")->execute([$teacher_id]);
            $_SESSION['flash_success'] = 'تم تحديث نجمة المعلم.';
        } elseif ($action === 'toggle_exam') {
            $pdo->prepare("UPDATE users SET exam_passed = 1 - exam_passed WHERE id=? AND user_type='teacher'")->execute([$teacher_id]);
            $_SESSION['flash_success'] = 'تم تحديث حالة الامتحان.';
        }
    }
    redirect('teachers.php' . (isset($_GET['filter']) ? '?filter=' . urlencode($_GET['filter']) : ''));
}

$filter = $_GET['filter'] ?? 'all';
$where  = "user_type='teacher'";
if ($filter === 'pending')  $where .= " AND teacher_status='pending'";
if ($filter === 'approved') $where .= " AND teacher_status='approved'";
if ($filter === 'rejected') $where .= " AND teacher_status='rejected'";

$teachers = $pdo->query("
    SELECT u.*,
           (SELECT COUNT(*) FROM recitations WHERE teacher_id=u.id) as total_recitations,
           (SELECT COALESCE(SUM(amount),0) FROM transactions WHERE to_user_id=u.id AND type='teacher_earning') as total_earnings,
           (SELECT COALESCE(AVG(rating),0) FROM ratings WHERE teacher_id=u.id) as avg_rating
    FROM users u WHERE $where ORDER BY u.created_at DESC
")->fetchAll();

$counts = $pdo->query("SELECT teacher_status, COUNT(*) as c FROM users WHERE user_type='teacher' GROUP BY teacher_status")->fetchAll(PDO::FETCH_KEY_PAIR);
$total_t = array_sum($counts);
$active_page = 'teachers';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>إدارة المعلمين — رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .filter-tabs{display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .filter-tab{padding:.5rem 1.25rem;border-radius:50px;text-decoration:none;font-size:.9rem;font-weight:600;border:2px solid var(--sand);color:var(--slate);transition:var(--transition-base)}
        .filter-tab:hover,.filter-tab.active{background:var(--royal-blue);color:#fff;border-color:var(--royal-blue)}
        .teacher-table{width:100%;border-collapse:collapse}
        .teacher-table th{background:var(--warm-cream);padding:.75rem 1rem;text-align:right;font-size:.85rem;color:var(--slate);font-weight:600}
        .teacher-table td{padding:.85rem 1rem;border-bottom:1px solid var(--sand);vertical-align:middle;font-size:.9rem}
        .teacher-table tr:hover td{background:var(--warm-cream)}
        .badge{padding:.2rem .7rem;border-radius:50px;font-size:.78rem;font-weight:700}
        .badge-pending{background:#fee2e2;color:#991b1b}
        .badge-approved{background:#d1fae5;color:#065f46}
        .badge-rejected{background:#f1f5f9;color:#64748b}
        .badge-star{background:#fef3c7;color:#92400e}
        .action-btns{display:flex;gap:.4rem;flex-wrap:wrap}
        .btn-sm{padding:.35rem .85rem;font-size:.8rem;border-radius:var(--radius-md)}
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include '../includes/header.php'; ?>
<div style="padding:2rem">

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
    <h2 style="margin:0">إدارة المعلمين (<?php echo $total_t; ?>)</h2>
</div>

<div class="filter-tabs">
    <a href="?filter=all"      class="filter-tab <?php echo $filter==='all'?'active':''; ?>">الكل (<?php echo $total_t; ?>)</a>
    <a href="?filter=pending"  class="filter-tab <?php echo $filter==='pending'?'active':''; ?>">قيد المراجعة (<?php echo $counts['pending']??0; ?>)</a>
    <a href="?filter=approved" class="filter-tab <?php echo $filter==='approved'?'active':''; ?>">موافق عليهم (<?php echo $counts['approved']??0; ?>)</a>
    <a href="?filter=rejected" class="filter-tab <?php echo $filter==='rejected'?'active':''; ?>">مرفوضون (<?php echo $counts['rejected']??0; ?>)</a>
</div>

<div class="section-card">
    <?php if (count($teachers) > 0): ?>
    <div style="overflow-x:auto">
    <table class="teacher-table">
        <thead>
            <tr>
                <th>المعلم</th>
                <th>الجنس</th>
                <th>الحالة</th>
                <th>الامتحان</th>
                <th>التلاوات</th>
                <th>الأرباح</th>
                <th>التقييم</th>
                <th>نجمة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($teachers as $t):
                $stars = str_repeat('★',(int)round($t['avg_rating'])) . str_repeat('☆',5-(int)round($t['avg_rating']));
            ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?php echo htmlspecialchars($t['full_name']); ?></div>
                    <div style="font-size:.8rem;color:var(--slate)"><?php echo htmlspecialchars($t['email']); ?></div>
                    <div style="font-size:.75rem;color:var(--slate)"><?php echo date('d/m/Y', strtotime($t['created_at'])); ?></div>
                </td>
                <td><?php echo $t['gender']==='male'?'ذكر':($t['gender']==='female'?'أنثى':'—'); ?></td>
                <td><span class="badge badge-<?php echo $t['teacher_status']??'pending'; ?>"><?php
                    echo ($t['teacher_status']==='approved'?'موافق عليه':($t['teacher_status']==='rejected'?'مرفوض':'قيد المراجعة'));
                ?></span></td>
                <td>
                    <?php if ($t['exam_required']): ?>
                    <span class="badge <?php echo $t['exam_passed']?'badge-approved':'badge-pending'; ?>">
                        <?php echo $t['exam_passed']?'اجتاز':'لم يجتز'; ?>
                    </span>
                    <?php else: ?>
                    <span style="color:var(--slate);font-size:.85rem">غير مطلوب</span>
                    <?php endif; ?>
                </td>
                <td><?php echo $t['total_recitations']; ?></td>
                <td><?php echo number_format($t['total_earnings'],0,'.',','); ?> دج</td>
                <td><span style="color:#f59e0b"><?php echo $stars; ?></span></td>
                <td><?php echo $t['has_star'] ? '<span class="badge badge-star">⭐ نجمة</span>' : '—'; ?></td>
                <td>
                    <div class="action-btns">
                        <?php if ($t['teacher_status'] === 'pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-secondary btn-sm" type="submit">✅ موافقة</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn-outline btn-sm" type="submit" style="border-color:#dc2626;color:#dc2626">❌ رفض</button>
                        </form>
                        <?php elseif ($t['teacher_status'] === 'approved'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button class="btn btn-outline btn-sm" type="submit" style="border-color:#dc2626;color:#dc2626">إلغاء الموافقة</button>
                        </form>
                        <?php elseif ($t['teacher_status'] === 'rejected'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button class="btn btn-secondary btn-sm" type="submit">إعادة الموافقة</button>
                        </form>
                        <?php endif; ?>
                        <?php if ($t['exam_required']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                            <input type="hidden" name="action" value="toggle_exam">
                            <button class="btn btn-primary btn-sm" type="submit"><?php echo $t['exam_passed']?'إلغاء الامتحان':'تأكيد الامتحان'; ?></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="teacher_id" value="<?php echo $t['id']; ?>">
                            <input type="hidden" name="action" value="toggle_star">
                            <button class="btn btn-gold btn-sm" type="submit" style="padding:.35rem .85rem;font-size:.8rem"><?php echo $t['has_star']?'إزالة النجمة':'منح نجمة ⭐'; ?></button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:3rem;color:var(--slate)">لا يوجد معلمون في هذه الفئة.</div>
    <?php endif; ?>
</div>
</div>
</div>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>
