<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');
if (!is_logged_in()) { echo json_encode(['has_subscription'=>false]); exit; }
$user = get_logged_in_user($pdo);
$sub  = get_subscription_status($pdo, $user['id']);
echo json_encode(['has_subscription' => (bool)$sub, 'subscription' => $sub]);
