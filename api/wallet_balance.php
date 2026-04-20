<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/payment.php';
header('Content-Type: application/json');
if (!is_logged_in()) { echo json_encode(['balance'=>0]); exit; }
$user = get_logged_in_user($pdo);
echo json_encode(['balance' => get_wallet_balance($pdo, $user['id'])]);
