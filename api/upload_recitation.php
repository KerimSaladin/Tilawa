<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

@ini_set('upload_max_filesize', '100M');
@ini_set('post_max_size', '120M');
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '300');

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/storage.php';

function send_json($d) { ob_clean(); header('Content-Type: application/json'); echo json_encode($d); exit; }

try {
    if (!is_logged_in())                         send_json(['success'=>false,'message'=>'يجب تسجيل الدخول']);
    $user = get_logged_in_user($pdo);
    if (!$user)                                  send_json(['success'=>false,'message'=>'غير مصرح']);
    if ($user['user_type'] !== 'student')        send_json(['success'=>false,'message'=>'هذه الصفحة للطلاب فقط']);
    if ($_SERVER['REQUEST_METHOD'] !== 'POST')   send_json(['success'=>false,'message'=>'POST مطلوب']);

    $surah_name = sanitize_input($_POST['surah_name'] ?? '');
    $ayah_range = sanitize_input($_POST['ayah_range'] ?? '');
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

    if (empty($surah_name)) send_json(['success'=>false,'message'=>'اسم السورة مطلوب']);

    $audio_path = $video_path = null;

    // ── Video ──
    foreach (['video_file', 'recorded_video'] as $field) {
        if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
            $res = upload_file($_FILES[$field], 'recitation', $user['id']);
            if (!$res['success']) send_json($res);
            $video_path = USE_R2 ? $res['url'] : $res['path'];
            break;
        }
    }

    // ── Audio (only if no video) ──
    if (!$video_path) {
        foreach (['audio_file', 'recorded_audio'] as $field) {
            if (!empty($_FILES[$field]['name']) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $res = upload_file($_FILES[$field], 'recitation', $user['id']);
                if (!$res['success']) send_json($res);
                $audio_path = USE_R2 ? $res['url'] : $res['path'];
                break;
            }
        }
    }

    if (!$audio_path && !$video_path) send_json(['success'=>false,'message'=>'يجب رفع ملف صوتي أو مرئي']);

    // Validate teacher
    if ($teacher_id) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND user_type='teacher' AND teacher_status='approved' AND is_active=TRUE");
        $stmt->execute([$teacher_id]);
        if (!$stmt->fetch()) $teacher_id = null;
    }

    $pdo->prepare("INSERT INTO recitations (student_id,teacher_id,surah_name,ayah_range,audio_file_path,video_file_path,status) VALUES (?,?,?,?,?,?,'pending') RETURNING id")
        ->execute([$user['id'], $teacher_id, $surah_name, $ayah_range ?: null, $audio_path, $video_path]);

    $recitation_id = $pdo->query("SELECT lastval()")->fetchColumn();
    send_json(['success'=>true,'message'=>'تم رفع التلاوة بنجاح! سيراجعها المعلم قريباً.','recitation_id'=>$recitation_id]);

} catch (Throwable $e) {
    send_json(['success'=>false,'message'=>$e->getMessage()]);
}
