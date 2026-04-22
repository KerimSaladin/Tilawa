<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once '../includes/config.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!is_logged_in()) { echo json_encode(['success'=>false,'message'=>'غير مصرح']); exit; }
$user = get_logged_in_user($pdo);
if (!$user) { echo json_encode(['success'=>false,'message'=>'غير مصرح']); exit; }

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'send') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success'=>false,'message'=>'POST مطلوب']); exit; }
    $receiver_id  = (int)($_POST['receiver_id'] ?? 0);
    $message_text = trim($_POST['message_text'] ?? '');
    if (!$receiver_id || !$message_text) { echo json_encode(['success'=>false,'message'=>'بيانات ناقصة']); exit; }
    $stmt = $pdo->prepare("INSERT INTO messages (sender_id, receiver_id, message_text) VALUES (?,?,?) RETURNING id");
    $stmt->execute([$user['id'], $receiver_id, $message_text]);
    $id = $stmt->fetchColumn();
    echo json_encode(['success'=>true,'id'=>$id,'time'=>date('H:i')]);

} elseif ($action === 'poll') {
    $with    = (int)($_GET['with'] ?? 0);
    $last_id = (int)($_GET['last_id'] ?? 0);
    if (!$with) { echo json_encode(['success'=>false,'message'=>'with مطلوب']); exit; }
    $stmt = $pdo->prepare("
        SELECT id, sender_id, message_text, created_at, is_read
        FROM messages
        WHERE id > ?
        AND ((sender_id=? AND receiver_id=?) OR (sender_id=? AND receiver_id=?))
        ORDER BY created_at ASC
    ");
    $stmt->execute([$last_id, $with, $user['id'], $user['id'], $with]);
    $msgs = $stmt->fetchAll();
    // تحديد كمقروء
    if ($msgs) {
        $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id=? AND receiver_id=? AND is_read = FALSE")->execute([$with, $user['id']]);
    }
    echo json_encode(['success'=>true,'messages'=>$msgs]);

} elseif ($action === 'unread_count') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read = FALSE");
    $stmt->execute([$user['id']]);
    echo json_encode(['success'=>true,'count'=>(int)$stmt->fetchColumn()]);

} else {
    echo json_encode(['success'=>false,'message'=>'إجراء غير صالح']);
}
