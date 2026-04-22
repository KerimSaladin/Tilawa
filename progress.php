<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

// المعلمون يصلون مجاناً دائماً — الطلاب يحتاجون اشتراكاً
// اشتراك اختياري للطلاب
if ($user['user_type'] === 'teacher' && !is_teacher_approved($pdo, $user['id'])) redirect('dashboard.php');

// Default values used by teacher-only template sections to avoid undefined variable notices.
$reports = [];
$student_detail_id = 0;
$student_progress = [];
$student_info = null;
$progress_has_teacher_id = column_exists($pdo, 'progress', 'teacher_id');
$progress_has_completion_pct = column_exists($pdo, 'progress', 'completion_pct');

// ── بيانات الطالب ─────────────────────────────────────────────
if ($user['user_type'] === 'student') {
    $progress_rows_sql = "
        SELECT p.*, " . ($progress_has_teacher_id ? "u.full_name as teacher_name" : "NULL as teacher_name") . "
        FROM progress p
        " . ($progress_has_teacher_id ? "LEFT JOIN users u ON p.teacher_id = u.id" : "") . "
        WHERE p.student_id = ?
        ORDER BY p.last_updated DESC
    ";
    $progress_rows = $pdo->prepare($progress_rows_sql);
    $progress_rows->execute([$user['id']]);
    $progress_rows = $progress_rows->fetchAll();

    $stats = $pdo->prepare("
        SELECT
            COALESCE(SUM(memorized_verses),0) as total_memorized,
            COUNT(*) as total_surahs
        FROM progress WHERE student_id=?
    ");
    $stats->execute([$user['id']]);
    $stats = $stats->fetch();

    $rec_stats = $pdo->prepare("
        SELECT
            COUNT(*) as total,
            SUM(status='reviewed' OR status='approved') as reviewed,
            COALESCE(AVG(c.rating),0) as avg_rating
        FROM recitations r
        LEFT JOIN corrections c ON r.id=c.recitation_id
        WHERE r.student_id=?
    ");
    $rec_stats->execute([$user['id']]);
    $rec_stats = $rec_stats->fetch();

    // أضف سورة للتتبع يدوياً
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['add_surah'])) {
        $surah  = sanitize_input($_POST['surah_name'] ?? '');
        $mem    = (int)($_POST['memorized_verses'] ?? 0);
        $total  = (int)($_POST['total_verses'] ?? 0);
        $tid    = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;
        if ($surah && $total > 0) {
            $pct = $total > 0 ? round($mem / $total * 100, 1) : 0;
            if ($progress_has_teacher_id && $progress_has_completion_pct) {
                $pdo->prepare("
                    INSERT INTO progress (student_id, teacher_id, surah_name, memorized_verses, total_verses, completion_pct)
                    VALUES (?,?,?,?,?,?)
                    ON CONFLICT DO UPDATE SET memorized_verses=?, total_verses=?, completion_pct=?, last_updated = CURRENT_TIMESTAMP
                ")->execute([
                    $user['id'], $tid, $surah, $mem, $total, $pct,
                    $mem, $total, $pct
                ]);
            } elseif ($progress_has_teacher_id) {
                $pdo->prepare("
                    INSERT INTO progress (student_id, teacher_id, surah_name, memorized_verses, total_verses)
                    VALUES (?,?,?,?,?)
                    ON CONFLICT (student_id, surah_name) DO UPDATE SET memorized_verses=EXCLUDED.memorized_verses, total_verses=EXCLUDED.total_verses, last_updated=CURRENT_TIMESTAMP
                ")->execute([
                    $user['id'], $tid, $surah, $mem, $total,
                    $mem, $total
                ]);
            } elseif ($progress_has_completion_pct) {
                $pdo->prepare("
                    INSERT INTO progress (student_id, surah_name, memorized_verses, total_verses, completion_pct)
                    VALUES (?,?,?,?,?)
                    ON CONFLICT DO UPDATE SET memorized_verses=?, total_verses=?, completion_pct=?, last_updated = CURRENT_TIMESTAMP
                ")->execute([
                    $user['id'], $surah, $mem, $total, $pct,
                    $mem, $total, $pct
                ]);
            } else {
                $pdo->prepare("
                    INSERT INTO progress (student_id, surah_name, memorized_verses, total_verses)
                    VALUES (?,?,?,?)
                    ON CONFLICT (student_id, surah_name) DO UPDATE SET memorized_verses=EXCLUDED.memorized_verses, total_verses=EXCLUDED.total_verses, last_updated=CURRENT_TIMESTAMP
                ")->execute([
                    $user['id'], $surah, $mem, $total,
                    $mem, $total
                ]);
            }
            header('Location: progress.php'); exit;
        }
    }
    // تحديث آيات
    if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['update_verses'])) {
        $pid = (int)$_POST['progress_id'];
        $mem = (int)$_POST['memorized_verses'];
        $tot = (int)$_POST['total_verses'];
        $pct = $tot > 0 ? round($mem/$tot*100,1) : 0;
        if ($progress_has_completion_pct) {
            $pdo->prepare("UPDATE progress SET memorized_verses=?, total_verses=?, completion_pct=?, last_updated = CURRENT_TIMESTAMP WHERE id=? AND student_id=?")
                ->execute([$mem, $tot, $pct, $pid, $user['id']]);
        } else {
            $pdo->prepare("UPDATE progress SET memorized_verses=?, total_verses=?, last_updated = CURRENT_TIMESTAMP WHERE id=? AND student_id=?")
                ->execute([$mem, $tot, $pid, $user['id']]);
        }
        header('Location: progress.php'); exit;
    }

    // جلب معلمي الطالب لقائمة الاختيار
    $my_teachers = $pdo->prepare("
        SELECT DISTINCT u.id, u.full_name FROM users u
        WHERE u.user_type='teacher' AND u.id IN (
            SELECT DISTINCT teacher_id FROM recitations WHERE student_id=? AND teacher_id IS NOT NULL
            UNION SELECT DISTINCT teacher_id FROM enrollments WHERE student_id=?
        )
    ");
    $my_teachers->execute([$user['id'], $user['id']]);
    $my_teachers = $my_teachers->fetchAll();

    $surah_list = get_surah_list();
}

// ── بيانات المعلم ──────────────────────────────────────────────
if ($user['user_type'] === 'teacher') {
    $reports = $pdo->prepare("
        SELECT
            u.id as student_id, u.full_name as student_name,
            COUNT(r.id) as total_recitations,
            SUM(r.status IN ('reviewed','approved')) as reviewed_count,
            COALESCE(AVG(c.rating),0) as avg_rating,
            MAX(r.uploaded_at) as last_activity
        FROM users u
        LEFT JOIN recitations r ON u.id=r.student_id AND r.teacher_id=?
        LEFT JOIN corrections c ON r.id=c.recitation_id
        WHERE u.user_type='student' AND u.id IN (
            SELECT DISTINCT student_id FROM recitations WHERE teacher_id=?
            UNION SELECT DISTINCT student_id FROM enrollments WHERE teacher_id=?
        )
        GROUP BY u.id, u.full_name
        ORDER BY last_activity DESC
    ");
    $reports->execute([$user['id'], $user['id'], $user['id']]);
    $reports = $reports->fetchAll();

    // تفاصيل طالب محدد
    $student_detail_id = (int)($_GET['student_id'] ?? 0);
    $student_progress = [];
    $student_info = null;
    if ($student_detail_id) {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
        $stmt->execute([$student_detail_id]);
        $student_info = $stmt->fetch();
        $student_progress_sql = "
            SELECT p.* FROM progress p WHERE p.student_id=? " . ($progress_has_teacher_id ? "AND p.teacher_id=?" : "") . "
        ";
        $student_progress = $pdo->prepare($student_progress_sql);
        $student_progress->execute($progress_has_teacher_id ? [$student_detail_id, $user['id']] : [$student_detail_id]);
        $student_progress = $student_progress->fetchAll();
    }
}

$active_page = 'progress';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>التقدم — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem}
        .stats-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:1.25rem;margin-bottom:2rem}
        .stat-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-lg);padding:1.5rem;text-align:center;box-shadow:var(--shadow-md)}
        .stat-value{font-size:2rem;font-weight:800;background:linear-gradient(135deg,var(--royal-blue),var(--soft-blue));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .stat-label{font-size:.85rem;color:var(--slate);margin-top:.25rem}
        .progress-row{display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--warm-cream);border-radius:var(--radius-md);margin-bottom:.6rem}
        .progress-row:hover{background:#f0ede6}
        .pr-name{font-weight:700;min-width:120px;font-size:.95rem}
        .pr-bar-wrap{flex:1;background:#e2ddd5;border-radius:50px;height:12px;overflow:hidden}
        .pr-bar-fill{height:100%;background:linear-gradient(90deg,var(--secondary-green),var(--accent-green));border-radius:50px;transition:width .4s}
        .pr-pct{font-weight:700;font-size:.9rem;min-width:50px;text-align:left;color:var(--secondary-green)}
        .pr-details{font-size:.8rem;color:var(--slate);min-width:80px;text-align:center}
        .pr-actions{display:flex;gap:.4rem}
        .report-table{width:100%;border-collapse:collapse}
        .report-table th{background:var(--warm-cream);padding:.7rem 1rem;text-align:right;font-size:.82rem;color:var(--slate);font-weight:600}
        .report-table td{padding:.75rem 1rem;border-bottom:1px solid var(--sand);font-size:.9rem;vertical-align:middle}
        .report-table tr:hover td{background:var(--warm-cream)}
        .modal-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center}
        .modal-overlay.show{display:flex}
        .modal-box{background:white;border-radius:var(--radius-xl);padding:2rem;max-width:480px;width:90%;box-shadow:var(--shadow-xl)}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include 'includes/header.php'; ?>
<div style="padding:2rem">

<?php if ($user['user_type']==='student'): ?>

<!-- ═══ إحصاء الطالب ═══ -->
<div class="stats-grid">
    <div class="stat-card"><div class="stat-value"><?php echo $stats['total_memorized']; ?></div><div class="stat-label">آيات محفوظة</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $stats['total_surahs']; ?></div><div class="stat-label">سور مُتابَعة</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $rec_stats['total']; ?></div><div class="stat-label">إجمالي التلاوات</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo $rec_stats['reviewed']; ?></div><div class="stat-label">مُراجَعة</div></div>
    <div class="stat-card"><div class="stat-value"><?php echo number_format($rec_stats['avg_rating'],1); ?>/5</div><div class="stat-label">متوسط التقييم</div></div>
</div>

<!-- مخطط التقدم -->
<?php if (count($progress_rows) > 0): ?>
<div class="section-card">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem">
        <h3 style="margin:0;border:none;padding:0">جدول الحفظ</h3>
        <button onclick="document.getElementById('addSurahModal').classList.add('show')" class="btn btn-primary" style="padding:.5rem 1.2rem;font-size:.9rem">+ سورة جديدة</button>
    </div>
    <?php foreach ($progress_rows as $p):
        $pct = $p['total_verses'] > 0 ? round($p['memorized_verses']/$p['total_verses']*100) : 0;
    ?>
    <div class="progress-row">
        <div class="pr-name"><?php echo htmlspecialchars($p['surah_name']); ?></div>
        <div class="pr-bar-wrap"><div class="pr-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
        <div class="pr-pct"><?php echo $pct; ?>%</div>
        <div class="pr-details"><?php echo $p['memorized_verses']; ?>/<?php echo $p['total_verses']; ?> آية</div>
        <?php if ($p['teacher_name']): ?><div style="font-size:.78rem;color:var(--slate)">👨‍🏫 <?php echo htmlspecialchars($p['teacher_name']); ?></div><?php endif; ?>
        <div class="pr-actions">
            <button onclick="openEdit(<?php echo $p['id']; ?>,<?php echo $p['memorized_verses']; ?>,<?php echo $p['total_verses']; ?>,'<?php echo addslashes($p['surah_name']); ?>')" class="btn btn-outline" style="padding:.3rem .7rem;font-size:.78rem">تعديل</button>
        </div>
    </div>
    <?php endforeach; ?>
    <div style="margin-top:1.5rem">
        <canvas id="progressChart" style="max-height:300px"></canvas>
    </div>
</div>

<?php else: ?>
<div class="section-card" style="text-align:center;padding:3rem">
    <div style="font-size:4rem;margin-bottom:1rem">📖</div>
    <h3>لم تبدأ في تتبع الحفظ بعد</h3>
    <p style="color:var(--slate);margin:.75rem 0 1.5rem">أضف سورة لتبدأ في متابعة تقدمك</p>
    <button onclick="document.getElementById('addSurahModal').classList.add('show')" class="btn btn-primary">+ إضافة سورة</button>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ═══ تقارير المعلم ═══ -->

<?php if ($student_detail_id && $student_info): ?>
<div class="section-card">
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem">
        <a href="progress.php" class="btn btn-outline" style="padding:.4rem .9rem;font-size:.85rem">← رجوع</a>
        <h3 style="margin:0;border:none;padding:0">تقدم: <?php echo htmlspecialchars($student_info['full_name']); ?></h3>
    </div>
    <?php if ($student_progress): ?>
    <?php foreach ($student_progress as $p):
        $pct = $p['total_verses'] > 0 ? round($p['memorized_verses']/$p['total_verses']*100) : 0;
    ?>
    <div class="progress-row">
        <div class="pr-name"><?php echo htmlspecialchars($p['surah_name']); ?></div>
        <div class="pr-bar-wrap"><div class="pr-bar-fill" style="width:<?php echo $pct; ?>%"></div></div>
        <div class="pr-pct"><?php echo $pct; ?>%</div>
        <div class="pr-details"><?php echo $p['memorized_verses']; ?>/<?php echo $p['total_verses']; ?> آية</div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <p style="color:var(--slate);text-align:center;padding:2rem">لا توجد بيانات تقدم لهذا الطالب بعد.</p>
    <?php endif; ?>
</div>

<?php else: ?>

<div class="section-card">
    <h3>تقارير أداء الطلاب</h3>
    <?php if ($reports): ?>
    <div style="overflow-x:auto">
    <table class="report-table">
        <thead><tr><th>الطالب</th><th>إجمالي التلاوات</th><th>مُراجَعة</th><th>متوسط التقييم</th><th>آخر نشاط</th><th>تفاصيل</th></tr></thead>
        <tbody>
            <?php foreach ($reports as $r): ?>
            <tr>
                <td><span style="font-weight:700"><?php echo htmlspecialchars($r['student_name']); ?></span></td>
                <td><?php echo $r['total_recitations']; ?></td>
                <td><?php echo $r['reviewed_count']; ?></td>
                <td>
                    <?php $avg = round($r['avg_rating'],1); ?>
                    <span style="color:#f59e0b"><?php echo str_repeat('★',(int)$avg).str_repeat('☆',5-(int)$avg); ?></span>
                    <span style="font-size:.82rem;color:var(--slate)">(<?php echo $avg; ?>)</span>
                </td>
                <td style="font-size:.85rem;color:var(--slate)"><?php echo $r['last_activity'] ? date('d/m/Y',strtotime($r['last_activity'])) : '—'; ?></td>
                <td><a href="?student_id=<?php echo $r['student_id']; ?>" class="btn btn-outline" style="padding:.3rem .8rem;font-size:.82rem">عرض</a></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div style="margin-top:1.5rem"><canvas id="performanceChart" style="max-height:300px"></canvas></div>
    <?php else: ?>
    <div style="text-align:center;padding:3rem;color:var(--slate)">لا توجد بيانات بعد. ستظهر التقارير بعد مراجعة تلاوات الطلاب.</div>
    <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

</div>
</div>

<!-- مودال إضافة سورة -->
<div class="modal-overlay" id="addSurahModal">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="margin:0">إضافة / تحديث سورة</h3>
            <button onclick="document.getElementById('addSurahModal').classList.remove('show')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--slate)">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="add_surah" value="1">
            <div class="form-group">
                <label class="form-label">السورة</label>
                <select name="surah_name" class="form-select" required>
                    <option value="">اختر السورة</option>
                    <?php foreach (get_surah_list() as $s): ?>
                    <option value="<?php echo htmlspecialchars($s); ?>"><?php echo htmlspecialchars($s); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">آيات محفوظة</label>
                    <input type="number" name="memorized_verses" class="form-input" value="0" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">إجمالي الآيات</label>
                    <input type="number" name="total_verses" class="form-input" value="0" min="1" required>
                </div>
            </div>
            <?php if (!empty($my_teachers)): ?>
            <div class="form-group">
                <label class="form-label">المعلم المرتبط (اختياري)</label>
                <select name="teacher_id" class="form-select">
                    <option value="">بدون معلم</option>
                    <?php foreach ($my_teachers as $t): ?>
                    <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div style="display:flex;gap:.75rem;margin-top:1rem">
                <button type="submit" class="btn btn-primary" style="flex:1">حفظ</button>
                <button type="button" onclick="document.getElementById('addSurahModal').classList.remove('show')" class="btn btn-outline" style="flex:1">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<!-- مودال تعديل آيات -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="margin:0" id="editSurahName">تعديل التقدم</h3>
            <button onclick="document.getElementById('editModal').classList.remove('show')" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:var(--slate)">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="update_verses" value="1">
            <input type="hidden" name="progress_id" id="edit_pid">
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">آيات محفوظة</label>
                    <input type="number" name="memorized_verses" id="edit_mem" class="form-input" min="0">
                </div>
                <div class="form-group">
                    <label class="form-label">إجمالي الآيات</label>
                    <input type="number" name="total_verses" id="edit_tot" class="form-input" min="1">
                </div>
            </div>
            <div style="display:flex;gap:.75rem;margin-top:1rem">
                <button type="submit" class="btn btn-primary" style="flex:1">تحديث</button>
                <button type="button" onclick="document.getElementById('editModal').classList.remove('show')" class="btn btn-outline" style="flex:1">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEdit(id, mem, tot, name) {
    document.getElementById('edit_pid').value = id;
    document.getElementById('edit_mem').value = mem;
    document.getElementById('edit_tot').value = tot;
    document.getElementById('editSurahName').textContent = 'تعديل: ' + name;
    document.getElementById('editModal').classList.add('show');
}
document.querySelectorAll('.modal-overlay').forEach(m => m.addEventListener('click', e => { if(e.target===m) m.classList.remove('show'); }));

<?php if ($user['user_type']==='student' && !empty($progress_rows)): ?>
const ctx = document.getElementById('progressChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($progress_rows,'surah_name')); ?>,
            datasets: [{
                label: 'آيات محفوظة',
                data: <?php echo json_encode(array_column($progress_rows,'memorized_verses')); ?>,
                backgroundColor: 'rgba(64,145,108,.75)',
                borderColor: 'rgba(45,106,79,1)',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,ticks:{stepSize:1}}} }
    });
}
<?php endif; ?>

<?php if ($user['user_type']==='teacher' && !empty($reports)): ?>
const pctx = document.getElementById('performanceChart');
if (pctx) {
    new Chart(pctx, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode(array_column($reports,'student_name')); ?>,
            datasets: [{
                label: 'متوسط التقييم',
                data: <?php echo json_encode(array_map(fn($r)=>round($r['avg_rating'],1),$reports)); ?>,
                backgroundColor: 'rgba(91,139,214,.75)',
                borderColor: 'rgba(45,74,138,1)',
                borderWidth: 2,
                borderRadius: 6
            }]
        },
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true,max:5,ticks:{stepSize:1}}} }
    });
}
<?php endif; ?>
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
