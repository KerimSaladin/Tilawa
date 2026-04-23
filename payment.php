<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/payment.php';

function send_json($data) {
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

try {

if ($_SERVER['REQUEST_METHOD'] !== 'POST') send_json(['success'=>false,'message'=>'POST مطلوب']);
if (!is_logged_in())                        send_json(['success'=>false,'message'=>'يجب تسجيل الدخول']);

$user = get_logged_in_user($pdo);
if (!$user) send_json(['success'=>false,'message'=>'غير مصرح']);

$action = $_POST['action'] ?? '';

switch ($action) {

    case 'update_pricing':
        if ($user['user_type'] !== 'teacher') send_json(['success'=>false,'message'=>'المعلمون فقط']);
        $session_price = (isset($_POST['session_price']) && $_POST['session_price'] !== '') ? (float)$_POST['session_price'] : null;
        $course_price  = (isset($_POST['course_price'])  && $_POST['course_price']  !== '') ? (float)$_POST['course_price']  : null;
        send_json(update_teacher_pricing($pdo, $user['id'], $session_price, $course_price));

    case 'pay_teacher':
        if ($user['user_type'] !== 'student') send_json(['success'=>false,'message'=>'الطلاب فقط']);
        $teacher_id   = (int)($_POST['teacher_id'] ?? 0);
        $payment_type = $_POST['payment_type'] ?? '';
        if (!in_array($payment_type, ['session','course'])) send_json(['success'=>false,'message'=>'نوع دفع غير صالح']);
        $pricing = get_teacher_pricing($pdo, $teacher_id);
        if (!$pricing) send_json(['success'=>false,'message'=>'المعلم غير موجود']);
        $price_field = $payment_type === 'session' ? 'session_price' : 'price_per_course';
        $base_price  = $pricing[$price_field] ?? 0;
        if (!$base_price || $base_price <= 0) send_json(['success'=>false,'message'=>'لم يحدد المعلم أسعاره بعد']);
        $description = ($payment_type === 'session' ? 'دفع جلسة' : 'دفع دورة') . ' مع المعلم #' . $teacher_id;
        $result = process_payment($pdo, $user['id'], $teacher_id, $payment_type . '_payment', $base_price, $description, true);
        if ($result['success']) {
            $pdo->prepare("INSERT INTO enrollments (student_id,teacher_id,type,sessions_paid,amount_paid,discount_applied) VALUES (?,?,?,?,?,?)")
                ->execute([$user['id'], $teacher_id, $payment_type, $payment_type==='session'?1:0,
                           $result['final_amount'] ?? $base_price,
                           isset($result['discount_applied']) && $result['discount_applied'] > 0 ? true : false]);
        }
        send_json($result);

    case 'top_up':
        if ($user['user_type'] !== 'student') send_json(['success'=>false,'message'=>'الطلاب فقط']);
        $amount = (float)($_POST['amount'] ?? 0);
        if (!in_array($amount, TOP_UP_AMOUNTS)) send_json(['success'=>false,'message'=>'مبلغ غير صالح']);
        $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?")->execute([$amount, $user['id']]);
        $pdo->prepare("INSERT INTO transactions (from_user_id,type,amount,description,status) VALUES (?,'top_up',?,?,'completed')")
            ->execute([$user['id'], $amount, 'شحن المحفظة: ' . number_format($amount,0,'.',',') . ' دج']);
        send_json(['success'=>true,'message'=>'تم شحن المحفظة بنجاح']);

    default:
        send_json(['success'=>false,'message'=>'إجراء غير معروف: ' . htmlspecialchars($action)]);
}

} catch (Throwable $e) {
    send_json(['success'=>false,'message'=>$e->getMessage()]);
}
