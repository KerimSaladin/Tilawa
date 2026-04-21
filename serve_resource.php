<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/config.php';
require_once 'includes/storage.php';

$resource_id = (int)($_GET['id'] ?? 0);
if ($resource_id <= 0) { http_response_code(400); exit('Invalid ID'); }

$stmt = $pdo->prepare("SELECT * FROM resources WHERE id=?");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch();

if (!$resource || !$resource['file_path']) { http_response_code(404); exit('Not found'); }

if (USE_R2) {
    $url = str_starts_with($resource['file_path'], 'http')
         ? $resource['file_path']
         : rtrim(R2_PUBLIC_URL, '/') . '/' . basename($resource['file_path']);
    ob_clean();
    header('Location: ' . $url);
    exit;
}

$filepath = UPLOAD_DIR . $resource['file_path'];
if (!file_exists($filepath)) { http_response_code(404); exit('File not found'); }

$ext = strtolower(pathinfo($resource['file_path'], PATHINFO_EXTENSION));
$mime = ['pdf'=>'application/pdf','mp4'=>'video/mp4','webm'=>'video/webm','ogg'=>'video/ogg','mov'=>'video/quicktime'][$ext] ?? 'application/octet-stream';

ob_clean();
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($filepath));
header('Content-Disposition: '.($resource['type']==='pdf'?'attachment':'inline').'; filename="'.basename($resource['file_path']).'"');
readfile($filepath);
