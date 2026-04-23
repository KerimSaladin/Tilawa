<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/payment.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!is_logged_in()) { echo json_encode(['success'=>false,'message'=>'غير مصرح']); exit; }

$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'student') {
    echo json_encode(['success'=>false,'message'=>'هذه الخدمة للطلاب فقط']); exit;
}

$pkg_id = (int)($_POST['subscription_id'] ?? 0);
if (!$pkg_id) { echo json_encode(['success'=>false,'message'=>'باقة غير صالحة']); exit; }

$stmt = $pdo->prepare("SELECT * FROM subscriptions WHERE id=?");
$stmt->execute([$pkg_id]);
$pkg = $stmt->fetch();
if (!$pkg) { echo json_encode(['success'=>false,'message'=>'الباقة غير موجودة']); exit; }

// التحقق من الرصيد
$balance = get_wallet_balance($pdo, $user['id']);
if ($balance < $pkg['price']) {
    echo json_encode(['success'=>false,'message'=>'رصيد المحفظة غير كافٍ. يرجى شحن المحفظة أولاً.']); exit;
}

// التحقق من عدم وجود اشتراك نشط
$existing = get_subscription_status($pdo, $user['id']);
if ($existing) {
    echo json_encode(['success'=>false,'message'=>'لديك اشتراك نشط بالفعل']); exit;
}

try {
    $pdo->beginTransaction();
    // خصم من المحفظة
    $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id=?")->execute([$pkg['price'], $user['id']]);
    // إنشاء الاشتراك — duration_days=0 يعني مدى الحياة
    $start = date('Y-m-d');
    $end   = ($pkg['duration_days'] == 0)
        ? '2099-12-31'  // تاريخ بعيد جداً يمثل مدى الحياة
        : date('Y-m-d', strtotime("+{$pkg['duration_days']} days"));
    $pdo->prepare("INSERT INTO user_subscriptions (user_id,subscription_id,start_date,end_date,status) VALUES (?,?,?,?,'active')")
        ->execute([$user['id'], $pkg['id'], $start, $end]);
    // تسجيل المعاملة
    $pdo->prepare("INSERT INTO transactions (from_user_id,type,amount,description,status) VALUES (?,'platform_subscription',?,?,'completed')")
        ->execute([$user['id'], $pkg['price'], 'اشتراك: ' . $pkg['name_ar']]);
    $pdo->commit();
    echo json_encode(['success'=>true,'message'=>'تم الاشتراك بنجاح في باقة ' . $pkg['name_ar']]);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success'=>false,'message'=>'حدث خطأ: ' . $e->getMessage()]);
}
