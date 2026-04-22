<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/storage.php';

if (!is_logged_in()) { redirect('../login.php'); }
$user = get_logged_in_user($pdo);
if ($user['user_type'] !== 'teacher') { redirect('../dashboard.php'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect('../dashboard.php'); }

$recitation_id   = (int)($_POST['recitation_id'] ?? 0);
$feedback_text   = sanitize_input($_POST['feedback_text'] ?? '');
$rating          = (int)($_POST['rating'] ?? 0);
$voice_data      = $_POST['voice_feedback_data'] ?? '';

if ($recitation_id <= 0 || empty($feedback_text) || $rating < 1 || $rating > 5) {
    $_SESSION['error'] = 'جميع الحقول مطلوبة والتقييم يجب أن يكون بين 1 و 5';
    redirect('../recitation.php?id=' . $recitation_id . '&view=correction');
}

$stmt = $pdo->prepare("SELECT id FROM recitations WHERE id=? AND (teacher_id=? OR teacher_id IS NULL)");
$stmt->execute([$recitation_id, $user['id']]);
if (!$stmt->fetch()) {
    $_SESSION['error'] = 'التلاوة غير موجودة أو لا تنتمي لك';
    redirect('../recitation.php?view=review');
}

try {
    $voice_path = null;

    if (!empty($voice_data) && strpos($voice_data, 'data:audio') === 0) {
        $raw = base64_decode(str_replace([' '], ['+'], preg_replace('#^data:audio/[^;]+;base64,#', '', $voice_data)));
        $res = upload_raw($raw, 'feedback', $user['id'], 'webm');
        if ($res['success']) {
            $voice_path = USE_R2 ? $res['url'] : $res['path'];
        }
    }

    // Track file
    if ($voice_path && !USE_R2) {
        $pdo->prepare("INSERT INTO teacher_files (teacher_id,file_name,file_path,file_type,file_size,description) VALUES (?,?,?,?,?,?)")
            ->execute([$user['id'], basename($voice_path), $voice_path, 'audio/webm', strlen($raw ?? ''), "صوت تصحيح للتلاوة #$recitation_id"]);
    }

    $pdo->prepare("INSERT INTO corrections (recitation_id,teacher_id,feedback_text,voice_feedback_path,rating) VALUES (?,?,?,?,?)")
        ->execute([$recitation_id, $user['id'], $feedback_text, $voice_path, $rating]);

    $pdo->prepare("UPDATE recitations SET status='reviewed', teacher_id=? WHERE id=?")
        ->execute([$user['id'], $recitation_id]);

    log_activity($pdo, $user['id'], 'correction_submitted', "تصحيح للتلاوة #$recitation_id - تقييم: $rating", 'recitation', $recitation_id);

    if ($rating >= 4) {
        $rec = $pdo->prepare("SELECT student_id,surah_name FROM recitations WHERE id=?");
        $rec->execute([$recitation_id]);
        $r = $rec->fetch();
        if ($r) {
            $pdo->prepare("INSERT INTO progress (student_id,surah_name,memorized_verses,total_verses) VALUES (?,?,1,1)
                ON CONFLICT (student_id, surah_name) DO UPDATE SET memorized_verses=progress.memorized_verses+1, last_updated=CURRENT_TIMESTAMP")
                ->execute([$r['student_id'], $r['surah_name']]);
        }
    }

    $_SESSION['success'] = 'تم إرسال التصحيح بنجاح';
    redirect('../recitation.php?view=review');

} catch (Exception $e) {
    $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
    redirect('../recitation.php?id=' . $recitation_id . '&view=correction');
}
