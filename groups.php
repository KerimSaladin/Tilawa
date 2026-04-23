<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

require_login();
$user = get_logged_in_user($pdo);

if (!$user || $user['user_type'] !== 'teacher') {
    redirect('dashboard.php');
}

// Check if teacher is approved
require_once 'includes/functions.php';
$active_page = 'groups';
if (!is_teacher_approved($pdo, $user['id'])) {
    $_SESSION['teacher_pending_message'] = true;
    $_SESSION['teacher_message'] = 'يجب موافقة الإدارة قبل البدء في العمل.';
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle success message from query string
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}

// Handle create group
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    $group_name = sanitize_input($_POST['name'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    
    if (empty($group_name)) {
        $error = 'اسم المجموعة مطلوب';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO groups (teacher_id, group_name, description) VALUES (?, ?, ?)");
            $stmt->execute([$user['id'], $group_name, $description]);
            $success = 'تم إنشاء المجموعة بنجاح';
        } catch (PDOException $e) {
            $error = 'حدث خطأ أثناء إنشاء المجموعة';
        }
    }
}

// Handle update availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_availability') {
    $availability_start = sanitize_input($_POST['availability_start'] ?? '');
    $availability_end = sanitize_input($_POST['availability_end'] ?? '');
    $availability_days = isset($_POST['availability_days']) ? implode(',', $_POST['availability_days']) : '';
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET availability_start = ?, availability_end = ?, availability_days = ? WHERE id = ?");
        $stmt->execute([$availability_start ?: null, $availability_end ?: null, $availability_days ?: null, $user['id']]);
        $success = 'تم تحديث أوقات التوفر بنجاح';
        // Refresh user data
        $user = get_logged_in_user($pdo);
    } catch (PDOException $e) {
        $error = 'حدث خطأ أثناء تحديث أوقات التوفر';
    }
}

// Get all groups for this teacher
$stmt = $pdo->prepare("SELECT id, teacher_id, group_name as name, description, gender, created_at FROM groups WHERE teacher_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$groups = $stmt->fetchAll();

// Get students for each group
// Get students for each group with stats
foreach ($groups as &$group) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.email, gm.joined_at,
               (SELECT COUNT(*) FROM recitations r WHERE r.student_id = u.id) as total_recitations,
               (SELECT COALESCE(SUM(memorized_verses), 0) FROM progress p WHERE p.student_id = u.id) as total_memorized,
               (SELECT MAX(uploaded_at) FROM recitations r WHERE r.student_id = u.id) as last_recitation_date
        FROM group_members gm
        JOIN users u ON gm.student_id = u.id
        WHERE gm.group_id = ?
        ORDER BY total_memorized DESC, gm.joined_at DESC
    ");
    $stmt->execute([$group['id']]);
    $group['students'] = $stmt->fetchAll();
    
    // Calculate group stats
    $group['total_recitations'] = 0;
    $group['total_memorized'] = 0;
    foreach ($group['students'] as $student) {
        $group['total_recitations'] += $student['total_recitations'];
        $group['total_memorized'] += $student['total_memorized'];
    }
    
    // Get top 3 students for leaderboard
    $group['leaderboard'] = array_slice($group['students'], 0, 3);
}

// Get all students (filter by gender only if teacher has gender set)
if (!empty($user['gender'])) {
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE user_type = 'student' AND is_active = TRUE AND (gender = ? OR gender IS NULL) ORDER BY full_name");
    $stmt->execute([$user['gender']]);
} else {
    $stmt = $pdo->prepare("SELECT id, full_name, email FROM users WHERE user_type = 'student' AND is_active = TRUE ORDER BY full_name");
    $stmt->execute();
}
$all_students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المجموعات - رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .groups-page {
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .create-group-form {
            background: var(--white);
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .groups-list {
            display: grid;
            gap: 1.5rem;
        }
        
        .group-card {
            background: var(--white);
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .group-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--light-blue);
        }
        
        .group-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark-blue);
        }
        
        .group-description {
            color: #666;
            margin-bottom: 1rem;
        }
        
        .students-list {
            margin-top: 1rem;
        }
        
        .student-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            background: var(--cream-bg);
            border-radius: 8px;
            margin-bottom: 0.5rem;
        }
        
        .add-student-form {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #ddd;
        }
        
        .btn-remove {
            background: #ff6b6b;
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-remove:hover {
            background: #ff5252;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
        
        .group-stats {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
            background: var(--cream-bg);
            padding: 1rem;
            border-radius: 10px;
        }
        
        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
        }
        
        .stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark-blue);
        }
        
        .stat-label {
            font-size: 0.85rem;
            color: #666;
        }
        
        .leaderboard {
            margin-bottom: 1.5rem;
            border: 1px solid #eee;
            border-radius: 10px;
            padding: 1rem;
        }
        
        .leaderboard-title {
            font-size: 1.1rem;
            color: var(--dark-blue);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .leaderboard-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem;
            border-bottom: 1px solid #eee;
        }
        
        .leaderboard-item:last-child {
            border-bottom: none;
        }
        
        .rank-badge {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 0.8rem;
            color: white;
            margin-left: 0.5rem;
        }
        
        .rank-1 { background-color: #ffd700; }
        .rank-2 { background-color: #c0c0c0; }
        .rank-3 { background-color: #cd7f32; }
        
        .edit-btn {
            background: none;
            border: none;
            color: var(--muted-blue);
            cursor: pointer;
            font-size: 0.9rem;
            padding: 0.2rem 0.5rem;
        }
        
        .edit-btn:hover {
            color: var(--dark-blue);
            text-decoration: underline;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close-modal {
            position: absolute;
            left: 1rem;
            top: 1rem;
            font-size: 1.5rem;
            cursor: pointer;
            color: #999;
        }
        
        .availability-form {
            background: linear-gradient(135deg, var(--light-blue) 0%, #e8f4f8 100%);
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid rgba(45, 62, 80, 0.1);
        }
        
        .availability-form h3 {
            color: var(--dark-blue);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .availability-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .days-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .day-checkbox {
            display: none;
        }
        
        .day-label {
            padding: 0.5rem 1rem;
            background: white;
            border: 2px solid #ddd;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .day-checkbox:checked + .day-label {
            background: var(--dark-blue);
            color: white;
            border-color: var(--dark-blue);
        }
        
        .day-label:hover {
            border-color: var(--muted-blue);
        }
        
        .current-availability {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-top: 1rem;
            border: 1px solid rgba(45, 62, 80, 0.1);
        }
        
        .availability-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--cream-bg);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.9rem;
            color: var(--dark-blue);
        }
        
        .message-btn {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .message-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <?php include 'includes/header.php'; ?>

        
        <div class="groups-page">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <!-- Teacher Availability Settings -->
            <div class="availability-form">
                <h3>🕐 أوقات التوفر للتدريس</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_availability">
                    <div class="availability-grid">
                        <div class="form-group">
                            <label class="form-label">من الساعة</label>
                            <input type="time" name="availability_start" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['availability_start'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">إلى الساعة</label>
                            <input type="time" name="availability_end" class="form-input" 
                                   value="<?php echo htmlspecialchars($user['availability_end'] ?? ''); ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">أيام التوفر</label>
                        <?php
                        $days = [
                            'sunday' => 'الأحد',
                            'monday' => 'الإثنين',
                            'tuesday' => 'الثلاثاء',
                            'wednesday' => 'الأربعاء',
                            'thursday' => 'الخميس',
                            'friday' => 'الجمعة',
                            'saturday' => 'السبت'
                        ];
                        $selected_days = explode(',', $user['availability_days'] ?? '');
                        ?>
                        <div class="days-selector">
                            <?php foreach ($days as $day_key => $day_name): ?>
                                <input type="checkbox" name="availability_days[]" value="<?php echo $day_key; ?>" 
                                       id="day_<?php echo $day_key; ?>" class="day-checkbox"
                                       <?php echo in_array($day_key, $selected_days) ? 'checked' : ''; ?>>
                                <label for="day_<?php echo $day_key; ?>" class="day-label"><?php echo $day_name; ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">حفظ أوقات التوفر</button>
                </form>
                
                <?php if (!empty($user['availability_days']) && !empty($user['availability_start'])): ?>
                <div class="current-availability">
                    <strong>أوقات التوفر الحالية:</strong>
                    <div style="margin-top: 0.5rem;">
                        <span class="availability-badge">
                            🕐 <?php echo date('g:i A', strtotime($user['availability_start'])); ?> - <?php echo date('g:i A', strtotime($user['availability_end'])); ?>
                        </span>
                        <span class="availability-badge" style="margin-right: 0.5rem;">
                            📅 <?php 
                            $selected = explode(',', $user['availability_days']);
                            $day_names = array_map(function($d) use ($days) { return $days[$d] ?? $d; }, $selected);
                            echo implode('، ', $day_names);
                            ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Create Group Form -->
            <div class="create-group-form">
                <h2 style="margin-bottom: 1rem; color: var(--dark-blue);">إنشاء مجموعة جديدة</h2>
                <?php if (!empty($user['gender'])): ?>
                    <p style="font-size: 0.95rem; color: var(--muted-blue); margin-bottom: 1rem; padding: 0.75rem; background: var(--light-blue); border-radius: 8px;">
                        <strong>ملاحظة:</strong> كمعلم <?php echo $user['gender'] === 'male' ? 'ذكر' : 'أنثى'; ?>، يمكنك إضافة <?php echo $user['gender'] === 'male' ? 'الطلاب الذكور فقط' : 'الطالبات الإناث فقط'; ?> إلى مجموعاتك.
                    </p>
                <?php endif; ?>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">
                    <div class="form-group">
                        <label class="form-label" for="group_name">اسم المجموعة *</label>
                        <input type="text" id="group_name" name="group_name" class="form-input" required 
                               placeholder="أدخل اسم المجموعة">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="description">الوصف</label>
                        <textarea id="description" name="description" class="form-input" rows="3" 
                                  placeholder="وصف المجموعة (اختياري)"></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">إنشاء المجموعة</button>
                </form>
            </div>
            
            <!-- Groups List -->
            <div class="groups-list">
                <?php if (count($groups) > 0): ?>
                    <?php foreach ($groups as $group): ?>
                    <div class="group-card">
                        <div class="group-header">
                            <div>
                                <h3 class="group-title">
                                    <?php echo htmlspecialchars($group['name']); ?>
                                    <button class="edit-btn" onclick="openEditModal(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['description'])); ?>')">
                                        (تعديل الوصف)
                                    </button>
                                </h3>
                                <?php if ($group['description']): ?>
                                    <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                                <?php endif; ?>
                            </div>
                            <span style="color: #999; font-size: 0.9rem;">
                                <?php echo count($group['students']); ?> طالب
                            </span>
                        </div>
                        
                        <!-- Group Stats -->
                        <div class="group-stats">
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $group['total_recitations']; ?></span>
                                <span class="stat-label">إجمالي التلاوات</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-value"><?php echo $group['total_memorized']; ?></span>
                                <span class="stat-label">آية محفوظة</span>
                            </div>
                        </div>
                        
                        <!-- Leaderboard -->
                        <?php if (count($group['leaderboard']) > 0 && $group['leaderboard'][0]['total_memorized'] > 0): ?>
                        <div class="leaderboard">
                            <div class="leaderboard-title">🏆 المتصدرون</div>
                            <?php foreach ($group['leaderboard'] as $index => $student): ?>
                                <?php if ($student['total_memorized'] > 0): ?>
                                <div class="leaderboard-item">
                                    <div style="display: flex; align-items: center;">
                                        <span class="rank-badge rank-<?php echo $index + 1; ?>"><?php echo $index + 1; ?></span>
                                        <span><?php echo htmlspecialchars($student['full_name']); ?></span>
                                    </div>
                                    <span style="font-weight: bold; color: var(--dark-blue);"><?php echo $student['total_memorized']; ?> آية</span>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Students in this group -->
                        <div class="students-list">
                            <h4 style="margin-bottom: 0.75rem; color: var(--muted-blue);">الطلاب في هذه المجموعة:</h4>
                            <?php if (count($group['students']) > 0): ?>
                                <?php foreach ($group['students'] as $student): ?>
                                <div class="student-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                                        <div style="font-size: 0.85rem; color: #666; margin-top: 0.25rem;">
                                            <span>حفظ: <?php echo $student['total_memorized']; ?> آية</span>
                                            <span style="margin: 0 0.5rem;">|</span>
                                            <span>تلاوات: <?php echo $student['total_recitations']; ?></span>
                                        </div>
                                    </div>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <a href="messages.php?user=<?php echo $student['id']; ?>" class="message-btn">
                                            💬 مراسلة
                                        </a>
                                        <button class="btn-remove" onclick="removeStudent(<?php echo $group['id']; ?>, <?php echo $student['id']; ?>)">
                                            إزالة
                                        </button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #999; text-align: center; padding: 1rem;">لا يوجد طلاب في هذه المجموعة</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Add Student Form -->
                        <div class="add-student-form">
                            <h4 style="margin-bottom: 0.75rem; color: var(--muted-blue);">إضافة طالب:</h4>
                            <?php if (!empty($user['gender'])): ?>
                                <p style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">
                                    <strong>ملاحظة:</strong> يمكنك إضافة <?php echo $user['gender'] === 'male' ? 'الطلاب الذكور فقط' : 'الطالبات الإناث فقط'; ?>
                                </p>
                            <?php endif; ?>
                            <form method="POST" action="api/manage_group.php" style="display: flex; gap: 0.5rem; align-items: flex-end;">
                                <input type="hidden" name="action" value="add_student">
                                <input type="hidden" name="group_id" value="<?php echo $group['id']; ?>">
                                <select name="student_id" class="form-input" required style="flex: 1;">
                                    <option value="">اختر طالب...</option>
                                    <?php foreach ($all_students as $student): ?>
                                        <?php 
                                        $is_in_group = false;
                                        foreach ($group['students'] as $group_student) {
                                            if ($group_student['id'] == $student['id']) {
                                                $is_in_group = true;
                                                break;
                                            }
                                        }
                                        if (!$is_in_group):
                                        ?>
                                        <option value="<?php echo $student['id']; ?>">
                                            <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['email'] . ')'); ?>
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-primary">إضافة</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <p>لا توجد مجموعات بعد. قم بإنشاء مجموعة جديدة أعلاه.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        function removeStudent(groupId, studentId) {
            if (!confirm('هل أنت متأكد من إزالة هذا الطالب من المجموعة؟')) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'api/manage_group.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'remove_student';
            form.appendChild(actionInput);
            
            const groupInput = document.createElement('input');
            groupInput.type = 'hidden';
            groupInput.name = 'group_id';
            groupInput.value = groupId;
            form.appendChild(groupInput);
            
            const studentInput = document.createElement('input');
            studentInput.type = 'hidden';
            studentInput.name = 'student_id';
            studentInput.value = studentId;
            form.appendChild(studentInput);
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Edit Modal Logic
        const modal = document.getElementById('editModal');
        const editGroupId = document.getElementById('edit_group_id');
        const editDescription = document.getElementById('edit_description');
        
        function openEditModal(groupId, description) {
            editGroupId.value = groupId;
            editDescription.value = description;
            modal.style.display = 'block';
        }
        
        function closeEditModal() {
            modal.style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
    
    <!-- Edit Description Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h3 style="margin-bottom: 1rem; color: var(--dark-blue);">تعديل وصف المجموعة</h3>
            <form method="POST" action="api/manage_group.php">
                <input type="hidden" name="action" value="update_group">
                <input type="hidden" id="edit_group_id" name="group_id">
                <div class="form-group">
                    <label class="form-label" for="edit_description">الوصف</label>
                    <textarea id="edit_description" name="description" class="form-input" rows="4" placeholder="أدخل الوصف الجديد"></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">حفظ التغييرات</button>
            </form>
        </div>
    </div>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>
