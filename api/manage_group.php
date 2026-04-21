<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول']);
    exit;
}

$user = get_logged_in_user($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'add_student') {
        // Only teachers can add students
        if ($user['user_type'] !== 'teacher') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'غير مصرح']);
            exit;
        }
        
        $group_id = (int)($_POST['group_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        
        if (!$group_id || !$student_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
            exit;
        }
        
        // Verify group belongs to teacher
        $stmt = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$group_id, $user['id']]);
        if (!$stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'المجموعة غير موجودة أو غير مصرح لك']);
            exit;
        }
        
        // Verify student exists, is active, and has same gender as teacher
        $stmt = $pdo->prepare("SELECT id, gender FROM users WHERE id = ? AND user_type = 'student' AND is_active = 1");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();
        
        if (!$student) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'الطالب غير موجود']);
            exit;
        }
        
        // Check gender match only when both teacher and student have gender set
        if (!empty($user['gender']) && !empty($student['gender']) && $student['gender'] !== $user['gender']) {
            header('Content-Type: application/json');
            $gender_text = $user['gender'] === 'male' ? 'ذكر' : 'أنثى';
            echo json_encode(['success' => false, 'message' => "لا يمكن إضافة طالب من جنس مختلف. المعلم/المعلمة ({$gender_text}) يمكنه/ها تدريس {$gender_text} فقط"]);
            exit;
        }
        
        // Check if student is already in group
        $stmt = $pdo->prepare("SELECT id FROM group_members WHERE group_id = ? AND student_id = ?");
        $stmt->execute([$group_id, $student_id]);
        if ($stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'الطالب موجود بالفعل في هذه المجموعة']);
            exit;
        }
        
        // Add student to group
        $stmt = $pdo->prepare("INSERT INTO group_members (group_id, student_id) VALUES (?, ?)");
        $stmt->execute([$group_id, $student_id]);
        
        // Redirect back to groups page
        header('Location: ../groups.php?success=تم إضافة الطالب بنجاح');
        exit;
        
    } elseif ($action === 'remove_student') {
        // Only teachers can remove students
        if ($user['user_type'] !== 'teacher') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'غير مصرح']);
            exit;
        }
        
        $group_id = (int)($_POST['group_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        
        if (!$group_id || !$student_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
            exit;
        }
        
        // Verify group belongs to teacher
        $stmt = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$group_id, $user['id']]);
        if (!$stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'المجموعة غير موجودة أو غير مصرح لك']);
            exit;
        }
        
        // Remove student from group
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ? AND student_id = ?");
        $stmt->execute([$group_id, $student_id]);
        
        // Redirect back to groups page
        header('Location: ../groups.php?success=تم إزالة الطالب بنجاح');
        exit;
        
    } elseif ($action === 'update_group') {
        // Only teachers can update groups
        if ($user['user_type'] !== 'teacher') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'غير مصرح']);
            exit;
        }
        
        $group_id = (int)($_POST['group_id'] ?? 0);
        $description = sanitize_input($_POST['description'] ?? '');
        
        if (!$group_id) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'بيانات غير كاملة']);
            exit;
        }
        
        // Verify group belongs to teacher
        $stmt = $pdo->prepare("SELECT id FROM groups WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$group_id, $user['id']]);
        if (!$stmt->fetch()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'المجموعة غير موجودة أو غير مصرح لك']);
            exit;
        }
        
        // Update group description
        $stmt = $pdo->prepare("UPDATE groups SET description = ? WHERE id = ?");
        $stmt->execute([$description, $group_id]);
        
        // Redirect back to groups page
        header('Location: ../groups.php?success=تم تحديث وصف المجموعة بنجاح');
        exit;

    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
        exit;
    }
    
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
    exit;
}
?>

