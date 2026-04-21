<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
if (!is_logged_in()) { http_response_code(401); exit; }

$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'teacher') { http_response_code(403); exit; }

$success = update_teacher_online_status($pdo, $user['id']);
header('Content-Type: application/json');
echo json_encode(['success' => (bool)$success]);
