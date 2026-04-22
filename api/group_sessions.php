<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'POST مطلوب']); exit;
}
if (!is_logged_in()) {
    echo json_encode(['success'=>false,'message'=>'يجب تسجيل الدخول']); exit;
}
$user = get_logged_in_user($pdo);
if (!$user) {
    echo json_encode(['success'=>false,'message'=>'غير مصرح']); exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'create_session' && $user['user_type'] === 'teacher') {
    // Create new group session
    $title = sanitize_input($_POST['title'] ?? '');
    $description = sanitize_input($_POST['description'] ?? '');
    $scheduled_at = $_POST['scheduled_at'] ?? '';
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 60);
    $max_students = (int)($_POST['max_students'] ?? 50);
    $session_url = sanitize_input($_POST['session_url'] ?? '');
    
    // Validate input
    if (empty($title) || empty($scheduled_at)) {
        echo json_encode(['success' => false, 'message' => 'العنوان وتاريخ الجلسة مطلوبان']); exit;
    }
    
    // Use default ngrok URL if none provided
    if (empty($session_url)) {
        $session_url = 'https://unexhumed-histomorphological-bee.ngrok-free.dev';
    }
    
    $success = create_group_session($pdo, $user['id'], $title, $description, $scheduled_at, $duration_minutes, $max_students, $session_url);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'تم إنشاء الجلسة بنجاح']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل إنشاء الجلسة']);
    }
    
} elseif ($action === 'register_session' && $user['user_type'] === 'student') {
    // Register student for session
    $session_id = (int)$_POST['session_id'];
    
    if ($session_id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'معرف الجلسة غير صحيح']); exit;
    }
    
    $result = register_student_for_session($pdo, $user['id'], $session_id);
    echo json_encode($result);
    
} elseif ($action === 'update_status' && $user['user_type'] === 'teacher') {
    // Update session status (go live, end session)
    $session_id = (int)$_POST['session_id'];
    $status = $_POST['status'] ?? '';
    
    if ($session_id <= 0 || !in_array($status, ['live', 'ended'])) {
        echo json_encode(['success' => false, 'message' => 'بيانات غير صحيحة']); exit;
    }
    
    // Verify teacher owns this session
    $stmt = $pdo->prepare("SELECT id FROM group_sessions WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$session_id, $user['id']]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'غير مصرح لك بتعديل هذه الجلسة']); exit;
    }
    
    $success = update_group_session_status($pdo, $session_id, $status);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'تم تحديث حالة الجلسة']);
    } else {
        echo json_encode(['success' => false, 'message' => 'فشل تحديث حالة الجلسة']);
    }
    
} else {
    echo json_encode(['success' => false, 'message' => 'إجراء غير صحيح']); exit;
}
?>
