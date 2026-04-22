<?php
require_once 'includes/config.php';

// Get resource ID
$resource_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($resource_id <= 0) {
    http_response_code(400);
    exit('Invalid resource ID');
}

// Get resource from database
$stmt = $pdo->prepare("SELECT * FROM resources WHERE id = ?");
$stmt->execute([$resource_id]);
$resource = $stmt->fetch();

if (!$resource || !$resource['file_path']) {
    http_response_code(404);
    exit('Resource not found');
}

$file_path = UPLOAD_DIR . $resource['file_path'];

if (!file_exists($file_path)) {
    http_response_code(404);
    exit('File not found');
}

// Get file info
$file_size = filesize($file_path);
$file_extension = strtolower(pathinfo($resource['file_path'], PATHINFO_EXTENSION));

// Set appropriate content type
$content_types = [
    'pdf' => 'application/pdf',
    'mp4' => 'video/mp4',
    'webm' => 'video/webm',
    'ogg' => 'video/ogg',
    'mov' => 'video/quicktime',
    'avi' => 'video/x-msvideo'
];

$content_type = $content_types[$file_extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . $file_size);
header('Cache-Control: public, max-age=86400'); // Cache for 1 day

if ($resource['type'] === 'pdf') {
    // For PDFs, force download
    header('Content-Disposition: attachment; filename="' . basename($resource['file_path']) . '"');
} else {
    // For videos, allow inline viewing
    header('Content-Disposition: inline; filename="' . basename($resource['file_path']) . '"');
}

// Output file
readfile($file_path);
exit;
?>
