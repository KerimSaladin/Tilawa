<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول']);
    exit;
}

$user = get_logged_in_user($pdo);
$recitation_id = (int)($_GET['recitation_id'] ?? 0);

if ($recitation_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرف التلاوة غير صحيح']);
    exit;
}

try {
    // Check if user has access to this recitation
    if ($user['user_type'] === 'student') {
        $stmt = $pdo->prepare("SELECT id FROM recitations WHERE id = ? AND student_id = ?");
        $stmt->execute([$recitation_id, $user['id']]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM recitations WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$recitation_id, $user['id']]);
    }
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'لا يوجد لديك صلاحية للوصول لهذه التلاوة']);
        exit;
    }
    
    // Get corrections
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as teacher_name
        FROM corrections c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.recitation_id = ?
        ORDER BY c.corrected_at DESC
    ");
    $stmt->execute([$recitation_id]);
    $corrections = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'corrections' => $corrections
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>

