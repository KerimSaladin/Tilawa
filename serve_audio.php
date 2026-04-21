<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/storage.php';

if (!is_logged_in()) { http_response_code(403); exit('Access denied'); }
$user = get_logged_in_user($pdo);
if (!$user)          { http_response_code(403); exit('Access denied'); }

$filename = basename($_GET['file'] ?? '');
if (!$filename) { http_response_code(400); exit('File required'); }

// ── If using R2, redirect to public URL ───────────────────────────────────────
if (USE_R2) {
    ob_clean();
    header('Location: ' . rtrim(R2_PUBLIC_URL, '/') . '/' . urlencode($filename));
    exit;
}

// ── Local serving ─────────────────────────────────────────────────────────────
$filepath = UPLOAD_DIR . $filename;
if (!file_exists($filepath)) { http_response_code(404); exit('File not found'); }

// Permission check
$stmt = $pdo->prepare("SELECT student_id,teacher_id,status FROM recitations WHERE audio_file_path LIKE ? OR video_file_path LIKE ?");
$stmt->execute(['%'.$filename, '%'.$filename]);
$rec = $stmt->fetch();

if ($rec) {
    $ok = false;
    if     ($user['user_type'] === 'admin')   $ok = true;
    elseif ($user['user_type'] === 'teacher') $ok = ($rec['teacher_id'] == $user['id'] || !$rec['teacher_id'] || $rec['status'] === 'pending');
    elseif ($user['user_type'] === 'student') $ok = ($rec['student_id'] == $user['id']);
    if (!$ok) { http_response_code(403); exit('Access denied'); }
} else {
    // voice feedback files — only owner or teacher
    if ($user['user_type'] === 'student' && strpos($filename, '_'.$user['id'].'_') === false) {
        http_response_code(403); exit('Access denied');
    }
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mime_map = [
    'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','webm'=>'video/webm',
    'm4a'=>'audio/mp4','aac'=>'audio/aac','mp4'=>'video/mp4','mov'=>'video/quicktime','avi'=>'video/x-msvideo'
];
$content_type = $mime_map[$ext] ?? 'application/octet-stream';
$size = filesize($filepath);

ob_clean();
header('Content-Type: ' . $content_type);
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');

if (isset($_SERVER['HTTP_RANGE'])) {
    [$start, $end] = explode('-', str_replace('bytes=', '', $_SERVER['HTTP_RANGE']));
    $start = (int)$start;
    $end   = $end !== '' ? (int)$end : $size - 1;
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes '.$start.'-'.$end.'/'.$size);
    header('Content-Length: '.($end - $start + 1));
    $fp = fopen($filepath, 'rb'); fseek($fp, $start); echo fread($fp, $end - $start + 1); fclose($fp);
} else {
    header('Content-Length: '.$size);
    readfile($filepath);
}
