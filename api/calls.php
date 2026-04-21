<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'يجب تسجيل الدخول']);
    exit;
}

$user = get_logged_in_user($pdo);
$method = $_SERVER['REQUEST_METHOD'];
$action = $method === 'POST' ? ($_POST['action'] ?? '') : ($_GET['action'] ?? '');

header('Content-Type: application/json');

try {
    if ($action === 'create' && $method === 'POST') {
        $callee_id = (int)($_POST['callee_id'] ?? 0);
        $type = $_POST['type'] === 'video' ? 'video' : 'audio';
        if ($callee_id <= 0 || $callee_id === (int)$user['id']) {
            echo json_encode(['success' => false, 'message' => 'مستلم غير صحيح']);
            exit;
        }
        $stmt = $pdo->prepare("INSERT INTO calls (caller_id, callee_id, type, status) VALUES (?, ?, ?, 'ringing')");
        $stmt->execute([$user['id'], $callee_id, $type]);
        $call_id = $pdo->lastInsertId();
        echo json_encode(['success' => true, 'call_id' => (int)$call_id]);
        exit;
    }

    if ($action === 'check_incoming' && $method === 'GET') {
        $stmt = $pdo->prepare("SELECT c.*, u.full_name AS caller_name FROM calls c JOIN users u ON c.caller_id = u.id WHERE c.callee_id = ? AND c.status = 'ringing' AND c.created_at > NOW() - INTERVAL '30 seconds' ORDER BY c.created_at DESC LIMIT 1");
        $stmt->execute([$user['id']]);
        $call = $stmt->fetch();
        echo json_encode(['success' => true, 'call' => $call ?: null]);
        exit;
    }

    if ($action === 'accept' && $method === 'POST') {
        $call_id = (int)($_POST['call_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE calls SET status = 'accepted' WHERE id = ? AND callee_id = ?");
        $stmt->execute([$call_id, $user['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'decline' && $method === 'POST') {
        $call_id = (int)($_POST['call_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE calls SET status = 'declined' WHERE id = ? AND callee_id = ?");
        $stmt->execute([$call_id, $user['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'end' && $method === 'POST') {
        $call_id = (int)($_POST['call_id'] ?? 0);
        $stmt = $pdo->prepare("UPDATE calls SET status = 'ended' WHERE id = ? AND (caller_id = ? OR callee_id = ?)");
        $stmt->execute([$call_id, $user['id'], $user['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'set_offer' && $method === 'POST') {
        $call_id = (int)($_POST['call_id'] ?? 0);
        $sdp = $_POST['sdp'] ?? '';
        $stmt = $pdo->prepare("UPDATE calls SET offer_sdp = ? WHERE id = ? AND caller_id = ?");
        $stmt->execute([$sdp, $call_id, $user['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'set_answer' && $method === 'POST') {
        $call_id = (int)($_POST['call_id'] ?? 0);
        $sdp = $_POST['sdp'] ?? '';
        $stmt = $pdo->prepare("UPDATE calls SET answer_sdp = ? WHERE id = ? AND callee_id = ?");
        $stmt->execute([$sdp, $call_id, $user['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'add_candidate' && $method === 'POST') {
        $call_id = (int)($_POST['call_id'] ?? 0);
        $candidate = $_POST['candidate'] ?? '';
        $stmt = $pdo->prepare("INSERT INTO call_candidates (call_id, sender_id, candidate) VALUES (?, ?, ?)");
        $stmt->execute([$call_id, $user['id'], $candidate]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'get_candidates' && $method === 'GET') {
        $call_id = (int)($_GET['call_id'] ?? 0);
        $last_id = (int)($_GET['last_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM call_candidates WHERE call_id = ? AND id > ? AND sender_id != ? ORDER BY id ASC");
        $stmt->execute([$call_id, $last_id, $user['id']]);
        $rows = $stmt->fetchAll();
        echo json_encode(['success' => true, 'candidates' => $rows]);
        exit;
    }

    if ($action === 'get_call' && $method === 'GET') {
        $call_id = (int)($_GET['call_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM calls WHERE id = ? AND (caller_id = ? OR callee_id = ?)");
        $stmt->execute([$call_id, $user['id'], $user['id']]);
        $call = $stmt->fetch();
        echo json_encode(['success' => true, 'call' => $call ?: null]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'إجراء غير معروف']);
    exit;
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'خطأ قاعدة البيانات']);
    exit;
}
