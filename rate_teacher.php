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
if (!$user || $user['user_type'] !== 'student') {
    echo json_encode(['success'=>false,'message'=>'هذه الخدمة للطلاب فقط']); exit;
}

$teacher_id = (int)($_POST['teacher_id'] ?? 0);
$rating     = (int)($_POST['rating']     ?? 0);
$comment    = sanitize_input($_POST['comment'] ?? '');

if ($teacher_id <= 0 || $rating < 1 || $rating > 5) {
    echo json_encode(['success'=>false,'message'=>'يرجى اختيار تقييم صحيح (1-5)']); exit;
}

// تحقق من وجود المعلم
$stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND user_type='teacher' AND is_active=1");
$stmt->execute([$teacher_id]);
if (!$stmt->fetch()) {
    echo json_encode(['success'=>false,'message'=>'المعلم غير موجود']); exit;
}

// السماح بالتقييم لأي طالب — لا يشترط وجود جلسة سابقة
try {
    $stmt = $pdo->prepare("
        INSERT INTO ratings (student_id, teacher_id, rating, comment)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (student_id, teacher_id) DO UPDATE SET rating=EXCLUDED.rating, comment=EXCLUDED.comment
    ");
    $stmt->execute([$user['id'], $teacher_id, $rating, $comment ?: null]);
    echo json_encode(['success'=>true,'message'=>'تم إرسال تقييمك بنجاح!']);
} catch (PDOException $e) {
    echo json_encode(['success'=>false,'message'=>'حدث خطأ أثناء الحفظ']);
}
