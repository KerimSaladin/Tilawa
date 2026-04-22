<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);
ob_start();
@ini_set('upload_max_filesize', '100M');
@ini_set('post_max_size', '120M');
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '300');

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Auth check first
if (!is_logged_in()) {
    echo json_encode(['success'=>false,'message'=>'يجب تسجيل الدخول']); exit;
}

$user = get_logged_in_user($pdo);
if (!$user) {
    echo json_encode(['success'=>false,'message'=>'غير مصرح']); exit;
}

if ($user['user_type'] !== 'student') {
    echo json_encode(['success'=>false,'message'=>'هذه الصفحة للطلاب فقط']); exit;
}

// Method check after auth
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'message'=>'POST مطلوب']); exit;
}

try {
    $surah_name = sanitize_input($_POST['surah_name'] ?? '');
    $ayah_range = sanitize_input($_POST['ayah_range'] ?? '');
    $teacher_id = !empty($_POST['teacher_id']) ? (int)$_POST['teacher_id'] : null;

    if (empty($surah_name)) {
        echo json_encode(['success'=>false,'message'=>'اسم السورة مطلوب']); exit;
    }

    $audio_path = null;
    $video_path = null;

    // ── ملف فيديو مرفوع ──
    if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['video_file'];
        if ($f['size'] > 100 * 1024 * 1024) {
            echo json_encode(['success'=>false,'message'=>'حجم الفيديو كبير جداً (الحد 100MB)']); exit;
        }
        $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) ?: 'webm';
        $name = 'recitation_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
        $dest = UPLOAD_DIR . $name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            echo json_encode(['success'=>false,'message'=>'فشل حفظ ملف الفيديو']); exit;
        }
        $video_path = $name;

    // ── فيديو مسجّل (Blob مُرسَل كملف) ──
    } elseif (!empty($_FILES['recorded_video']['name']) && $_FILES['recorded_video']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['recorded_video'];
        $name = 'recitation_' . $user['id'] . '_' . time() . '_' . uniqid() . '.webm';
        $dest = UPLOAD_DIR . $name;
        if (!move_uploaded_file($f['tmp_name'], $dest)) {
            echo json_encode(['success'=>false,'message'=>'فشل حفظ التسجيل المرئي']); exit;
        }
        $video_path = $name;
    }

    // ── ملف صوتي مرفوع ──
    if (!$video_path) {
        if (!empty($_FILES['audio_file']['name']) && $_FILES['audio_file']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['audio_file'];
            if ($f['size'] > 100 * 1024 * 1024) {
                echo json_encode(['success'=>false,'message'=>'حجم الصوت كبير جداً (الحد 100MB)']); exit;
            }
            $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) ?: 'webm';
            $name = 'recitation_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $ext;
            $dest = UPLOAD_DIR . $name;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                echo json_encode(['success'=>false,'message'=>'فشل حفظ ملف الصوت']); exit;
            }
            $audio_path = $name;

        // ── صوت مسجّل (Blob) ──
        } elseif (!empty($_FILES['recorded_audio']['name']) && $_FILES['recorded_audio']['error'] === UPLOAD_ERR_OK) {
            $f = $_FILES['recorded_audio'];
            $name = 'recitation_' . $user['id'] . '_' . time() . '_' . uniqid() . '.webm';
            $dest = UPLOAD_DIR . $name;
            if (!move_uploaded_file($f['tmp_name'], $dest)) {
                echo json_encode(['success'=>false,'message'=>'فشل حفظ التسجيل الصوتي']); exit;
            }
            $audio_path = $name;
        }
    }

    if (!$audio_path && !$video_path) {
        echo json_encode(['success'=>false,'message'=>'يجب رفع ملف صوتي أو فيديو أو تسجيل تلاوة']); exit;
    }

    // التحقق من المعلم — بدون شرط الجنس (الطالب يختار معلمه)
    if ($teacher_id) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id=? AND user_type='teacher' AND teacher_status='approved' AND is_active = TRUE");
        $stmt->execute([$teacher_id]);
        if (!$stmt->fetch()) {
            $teacher_id = null; // معلم غير موجود — حفظ بدون معلم
        }
    }

    // حفظ التلاوة
    $stmt = $pdo->prepare("
        INSERT INTO recitations (student_id, teacher_id, surah_name, ayah_range, audio_file_path, video_file_path, status)
        VALUES (?, ?, ?, ?, ?, ?, 'pending')
    ");
    $stmt->execute([
        $user['id'],
        $teacher_id,
        $surah_name,
        $ayah_range ?: null,
        $audio_path,
        $video_path
    ]);

    echo json_encode([
        'success'       => true,
        'message'       => 'تم رفع التلاوة بنجاح! سيراجعها المعلم قريباً.',
        'recitation_id' => $stmt->fetchColumn()
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>'خطأ: ' . $e->getMessage()]);
}
