<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'student') redirect('dashboard.php');

// اشتراك اختياري — لا توجد إعادة توجيه إجبارية
$has_active_access = true;

$flash = '';
// انضمام لمجموعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_group'])) {
    $group_id = (int)$_POST['group_id'];
    try {
        $pdo->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?,?) ON CONFLICT DO NOTHING")->execute([$group_id, $user['id']]);
        $flash = ['type'=>'success','msg'=>'تم الانضمام للمجموعة بنجاح!'];
    } catch (Exception $e) {
        $flash = ['type'=>'error','msg'=>'حدث خطأ أثناء الانضمام'];
    }
}
// مغادرة مجموعة
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_group'])) {
    $group_id = (int)$_POST['group_id'];
    $pdo->prepare("DELETE FROM group_members WHERE group_id=? AND student_id=?")->execute([$group_id, $user['id']]);
    $flash = ['type'=>'success','msg'=>'تم مغادرة المجموعة.'];
}

// مجموعاتي
$my_groups = $pdo->prepare("
    SELECT g.id, g.teacher_id, g.group_name as name, g.description, gm.joined_at,
           (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id=g.id) as member_count
    FROM groups g
    JOIN group_members gm ON g.id=gm.group_id
    JOIN users u ON g.teacher_id=u.id
    WHERE gm.student_id=? ORDER BY gm.joined_at DESC
");
$my_groups->execute([$user['id']]);
$my_groups = $my_groups->fetchAll();

// جميع المجموعات المتاحة (نفس الجنس)
$available = $pdo->prepare("
    SELECT g.id, g.teacher_id, g.group_name as name, g.description,
           (SELECT COUNT(*) FROM group_members gm2 WHERE gm2.group_id=g.id) as member_count,
           (SELECT COUNT(*) FROM group_members gm3 WHERE gm3.group_id=g.id AND gm3.student_id=?) as is_member
    FROM groups g
    JOIN users u ON g.teacher_id=u.id
    WHERE u.teacher_status='approved' AND u.is_active = TRUE
    ORDER BY g.created_at DESC
");
$available->execute([$user['id']]);
$available = $available->fetchAll();

$active_page = 'groups';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>مجموعاتي — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .groups-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:1.25rem}
        .group-card{background:var(--warm-cream);border:1px solid var(--sand);border-radius:var(--radius-lg);padding:1.5rem;transition:var(--transition-bounce)}
        .group-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md)}
        .group-name{font-weight:700;font-size:1.05rem;color:var(--charcoal);margin-bottom:.4rem}
        .group-teacher{color:var(--slate);font-size:.9rem;margin-bottom:.4rem}
        .group-meta{display:flex;gap:.75rem;font-size:.82rem;color:var(--slate);margin-bottom:1rem;flex-wrap:wrap}
        .group-meta span{background:white;padding:.2rem .65rem;border-radius:50px;border:1px solid var(--sand)}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include 'includes/header.php'; ?>
<div style="padding:2rem">

<?php if ($flash): ?>
<div class="alert alert-<?php echo $flash['type']; ?>" style="margin-bottom:1rem"><?php echo htmlspecialchars($flash['msg']); ?></div>
<?php endif; ?>

<!-- مجموعاتي -->
<div class="section-card">
    <h3>مجموعاتي (<?php echo count($my_groups); ?>)</h3>
    <?php if ($my_groups): ?>
    <div class="groups-grid">
        <?php foreach ($my_groups as $g): ?>
        <div class="group-card">
            <div class="group-name"><?php echo htmlspecialchars($g['name']); ?></div>
            <div class="group-teacher">👨‍🏫 <?php echo htmlspecialchars($g['teacher_name']); ?></div>
            <?php if ($g['description']): ?><p style="font-size:.88rem;color:var(--slate);margin-bottom:.75rem"><?php echo htmlspecialchars($g['description']); ?></p><?php endif; ?>
            <div class="group-meta">
                <span>👥 <?php echo $g['member_count']; ?> عضو</span>
                <span>📅 انضمام <?php echo date('d/m/Y', strtotime($g['joined_at'])); ?></span>
            </div>
            <form method="POST">
                <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                <button name="leave_group" class="btn btn-outline" style="border-color:#dc2626;color:#dc2626;width:100%;padding:.55rem"
                    onclick="return confirm('هل تريد مغادرة هذه المجموعة؟')">مغادرة المجموعة</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:2rem;color:var(--slate)">لم تنضم لأي مجموعة بعد. انضم من القائمة أدناه.</div>
    <?php endif; ?>
</div>

<!-- المجموعات المتاحة -->
<div class="section-card">
    <h3>المجموعات المتاحة</h3>
    <?php if ($available): ?>
    <div class="groups-grid">
        <?php foreach ($available as $g): ?>
        <div class="group-card">
            <div class="group-name"><?php echo htmlspecialchars($g['name']); ?></div>
            <div class="group-teacher">👨‍🏫 <?php echo htmlspecialchars($g['teacher_name']); ?></div>
            <?php if ($g['description']): ?><p style="font-size:.88rem;color:var(--slate);margin-bottom:.75rem"><?php echo htmlspecialchars($g['description']); ?></p><?php endif; ?>
            <div class="group-meta">
                <span>👥 <?php echo $g['member_count']; ?> عضو</span>
            </div>
            <?php if ($g['is_member']): ?>
            <div class="btn btn-outline" style="width:100%;text-align:center;padding:.55rem;background:#d1fae5;border-color:#4ade80;color:#166534">✅ أنت عضو</div>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                <button name="join_group" class="btn btn-primary" style="width:100%;padding:.55rem">انضمام للمجموعة</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:2rem;color:var(--slate)">لا توجد مجموعات متاحة حالياً.</div>
    <?php endif; ?>
</div>
</div>
</div>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
