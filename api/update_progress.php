<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول']);
    exit;
}

$user = get_logged_in_user($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير صحيحة']);
    exit;
}

$student_id = (int)($_POST['student_id'] ?? 0);
$surah_name = sanitize_input($_POST['surah_name'] ?? '');
$memorized_verses = (int)($_POST['memorized_verses'] ?? 0);
$total_verses = (int)($_POST['total_verses'] ?? 0);

if ($student_id <= 0 || empty($surah_name)) {
    echo json_encode(['success' => false, 'message' => 'بيانات غير كافية']);
    exit;
}

// Verify access (teacher updating student progress or student updating own)
if ($user['user_type'] === 'student' && $user['id'] !== $student_id) {
    echo json_encode(['success' => false, 'message' => 'لا يوجد لديك صلاحية']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO progress (student_id, surah_name, memorized_verses, total_verses)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (student_id, surah_name) DO UPDATE SET memorized_verses=EXCLUDED.memorized_verses, total_verses=EXCLUDED.total_verses, last_updated=CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        $student_id,
        $surah_name,
        $memorized_verses,
        $total_verses,
        $memorized_verses,
        $total_verses
    ]);
    
    // Log direct memorization update
    log_activity($pdo, $user['id'], 'direct_memorization_update', 
        "تحديث مباشر للحفظ: $surah_name ($memorized_verses/$total_verses آية)", 
        'progress', $student_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'تم تحديث التقدم بنجاح'
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>

