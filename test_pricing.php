<?php
require 'includes/config.php';
require 'includes/payment.php';

$teacher_id = 2; // Assuming 2 is a teacher ID
$res = update_teacher_pricing($pdo, $teacher_id, 999.00, 5000.00);
$stmt = $pdo->prepare("SELECT session_price, price_per_course FROM users WHERE id = ?");
$stmt->execute([$teacher_id]);
print_r($stmt->fetch(PDO::FETCH_ASSOC));
echo "Result of update: ";
print_r($res);
