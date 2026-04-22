<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

if (!is_logged_in()) {
    redirect('../login.php');
}

$user = get_logged_in_user($pdo);
if ($user['user_type'] !== 'teacher') {
    redirect('../dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recitation_id = (int)($_POST['recitation_id'] ?? 0);
    $feedback_text = sanitize_input($_POST['feedback_text'] ?? '');
    $rating = (int)($_POST['rating'] ?? 0);
    $voice_feedback_data = $_POST['voice_feedback_data'] ?? '';
    
    if ($recitation_id <= 0 || empty($feedback_text) || $rating < 1 || $rating > 5) {
        $_SESSION['error'] = 'جميع الحقول مطلوبة والتقييم يجب أن يكون بين 1 و 5';
        redirect('../recitation.php?id=' . $recitation_id . '&view=correction');
    }
    
    // Verify recitation belongs to this teacher or is pending
    $stmt = $pdo->prepare("SELECT id FROM recitations WHERE id = ? AND (teacher_id = ? OR teacher_id IS NULL)");
    $stmt->execute([$recitation_id, $user['id']]);
    if (!$stmt->fetch()) {
        $_SESSION['error'] = 'التلاوة غير موجودة أو لا تنتمي لك';
        redirect('../recitation.php?view=review');
    }
    
    try {
        // Handle voice feedback upload
        $voice_feedback_path = null;
        if (!empty($voice_feedback_data) && strpos($voice_feedback_data, 'data:audio') === 0) {
            // Decode base64 audio
            $voice_feedback_data = str_replace('data:audio/webm;base64,', '', $voice_feedback_data);
            $voice_feedback_data = str_replace(' ', '+', $voice_feedback_data);
            $voice_binary = base64_decode($voice_feedback_data);
            
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/voice_feedback/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Save voice file
            $filename = 'feedback_' . $user['id'] . '_' . $recitation_id . '_' . time() . '.webm';
            $filepath = $upload_dir . $filename;
            file_put_contents($filepath, $voice_binary);
            $voice_feedback_path = 'uploads/voice_feedback/' . $filename;
            
            // Track teacher file upload
            require_once '../includes/functions.php';
            $file_size = filesize($filepath);
            $stmt = $pdo->prepare("
                INSERT INTO teacher_files (teacher_id, file_name, file_path, file_type, file_size, description)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $user['id'],
                $filename,
                $voice_feedback_path,
                'audio/webm',
                $file_size,
                "صوت تصحيح للتلاوة #$recitation_id"
            ]);
        }
        
        require_once '../includes/functions.php';
        
        // Insert correction with voice feedback
        $stmt = $pdo->prepare("
            INSERT INTO corrections (recitation_id, teacher_id, feedback_text, voice_feedback_path, rating)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$recitation_id, $user['id'], $feedback_text, $voice_feedback_path, $rating]);
        
        // Update recitation status and assign teacher if not assigned
        $stmt = $pdo->prepare("UPDATE recitations SET status = 'reviewed', teacher_id = ? WHERE id = ?");
        $stmt->execute([$user['id'], $recitation_id]);
        
        // Log correction submission
        log_activity($pdo, $user['id'], 'correction_submitted', 
            "إرسال تصحيح للتلاوة #$recitation_id - تقييم: $rating", 
            'recitation', $recitation_id);
        
        // Update student progress if approved
        if ($rating >= 4) {
            $stmt = $pdo->prepare("
                SELECT student_id, surah_name FROM recitations WHERE id = ?
            ");
            $stmt->execute([$recitation_id]);
            $recitation = $stmt->fetch();
            
            if ($recitation) {
                // Update or insert progress
                $stmt = $pdo->prepare("
                    INSERT INTO progress (student_id, surah_name, memorized_verses, total_verses)
                    VALUES (?, ?, 1, 1)
                    ON CONFLICT (student_id,surah_name) DO UPDATE SET memorized_verses=progress.memorized_verses+1, last_updated=CURRENT_TIMESTAMP
                ");
                $stmt->execute([$recitation['student_id'], $recitation['surah_name']]);
            }
        }
        
        $_SESSION['success'] = 'تم إرسال التصحيح بنجاح';
        redirect('../recitation.php?view=review');
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'حدث خطأ: ' . $e->getMessage();
        redirect('../recitation.php?id=' . $recitation_id . '&view=correction');
    }
} else {
    redirect('../dashboard.php');
}
?>

