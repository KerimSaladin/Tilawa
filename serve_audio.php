<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

// Check if user is logged in
if (!is_logged_in()) {
    http_response_code(403);
    die('Access denied');
}

$user = get_logged_in_user($pdo);
if (!$user) {
    http_response_code(403);
    die('Access denied');
}

// Get file name from request
$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    die('File name required');
}

// Sanitize filename to prevent directory traversal
$filename = basename($filename);
$filepath = UPLOAD_DIR . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    http_response_code(404);
    die('File not found: ' . htmlspecialchars($filepath));
}

// Check if user has permission to access this file
// Teachers can access any recitation, students can only access their own
// Try multiple patterns to find the file in database
// First try exact match
$stmt = $pdo->prepare("SELECT student_id, teacher_id, status, audio_file_path, video_file_path FROM recitations WHERE audio_file_path = ? OR video_file_path = ?");
$stmt->execute([$filename, $filename]);
$recitation = $stmt->fetch();

// If not found, try with path patterns
if (!$recitation) {
    $stmt = $pdo->prepare("SELECT student_id, teacher_id, status, audio_file_path, video_file_path FROM recitations WHERE audio_file_path LIKE ? OR audio_file_path LIKE ? OR audio_file_path LIKE ? OR video_file_path LIKE ? OR video_file_path LIKE ? OR video_file_path LIKE ?");
    $stmt->execute([
        '%/' . $filename,
        '%\\' . $filename,
        '%' . $filename,
        '%/' . $filename,
        '%\\' . $filename,
        '%' . $filename
    ]);
    $recitation = $stmt->fetch();
}

// If still not found, try to find by filename only (extract filename from path)
if (!$recitation) {
    $stmt = $pdo->prepare("SELECT student_id, teacher_id, status, audio_file_path, video_file_path FROM recitations WHERE audio_file_path LIKE ? OR video_file_path LIKE ?");
    $stmt->execute(['%' . $filename, '%' . $filename]);
    $results = $stmt->fetchAll();
    
    // Find exact filename match
    foreach ($results as $result) {
        $audio_filename = $result['audio_file_path'] ? basename($result['audio_file_path']) : null;
        $video_filename = $result['video_file_path'] ? basename($result['video_file_path']) : null;
        if (($audio_filename && $audio_filename === $filename) || ($video_filename && $video_filename === $filename)) {
            $recitation = $result;
            break;
        }
    }
}

if ($recitation) {
    // Check permissions
    $has_access = false;
    
    if ($user['user_type'] === 'teacher') {
        // Teachers can access:
        // 1. Recitations assigned to them
        // 2. Pending recitations (no teacher assigned) - ALL teachers can access pending
        // 3. Any recitation from students they have access to
        if ($recitation['teacher_id'] == $user['id'] || 
            $recitation['teacher_id'] === null || 
            $recitation['status'] === 'pending') {
            $has_access = true;
        } else {
            // Check if teacher has any recitations from this student
            $stmt = $pdo->prepare("SELECT id FROM recitations WHERE student_id = ? AND teacher_id = ? LIMIT 1");
            $stmt->execute([$recitation['student_id'], $user['id']]);
            if ($stmt->fetch()) {
                $has_access = true;
            }
        }
    } elseif ($user['user_type'] === 'student') {
        // Students can only access their own recitations
        $has_access = ($recitation['student_id'] == $user['id']);
    } elseif ($user['user_type'] === 'admin') {
        // Admins can access everything
        $has_access = true;
    }
    
    if (!$has_access) {
        http_response_code(403);
        die('Access denied');
    }
} else {
    // If not found in database, allow access for teachers and admins, or if user owns the file
    if ($user['user_type'] === 'teacher' || $user['user_type'] === 'admin') {
        // Allow teachers and admins to access any file
    } elseif (strpos($filename, '_' . $user['id'] . '_') !== false) {
        // Allow if user owns the file (filename contains user_id)
    } else {
        http_response_code(403);
        die('Access denied');
    }
}

// Get file extension
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

// Set content type based on extension
$content_types = [
    // Audio formats
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'webm' => 'audio/webm',
    'm4a' => 'audio/mp4',
    'aac' => 'audio/aac',
    // Video formats
    'mp4' => 'video/mp4',
    'mov' => 'video/quicktime',
    'avi' => 'video/x-msvideo'
];

// Determine if it's a video file
$video_extensions = ['mp4', 'webm', 'ogg', 'mov', 'avi'];
$is_video = in_array($extension, $video_extensions);

// Get content type, with specific handling for WebM
if ($extension === 'webm') {
    // WebM could be audio or video, default to video/webm
    $content_type = 'video/webm';
} else {
    $content_type = $content_types[$extension] ?? ($is_video ? 'video/mp4' : 'audio/mpeg');
}

// Set headers for audio/video streaming
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($filepath));
header('Accept-Ranges: bytes');
header('Cache-Control: public, max-age=3600');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: *'); // Allow CORS for audio/video playback

// Handle range requests for audio streaming
$range = isset($_SERVER['HTTP_RANGE']) ? $_SERVER['HTTP_RANGE'] : null;
if ($range) {
    $size = filesize($filepath);
    $range = str_replace('bytes=', '', $range);
    list($start, $end) = explode('-', $range);
    $start = intval($start);
    $end = $end ? intval($end) : $size - 1;
    $length = $end - $start + 1;
    
    header('HTTP/1.1 206 Partial Content');
    header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    header('Content-Length: ' . $length);
    
    $fp = fopen($filepath, 'rb');
    fseek($fp, $start);
    echo fread($fp, $length);
    fclose($fp);
} else {
    // Output entire file
    readfile($filepath);
}
exit;
