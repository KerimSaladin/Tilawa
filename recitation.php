<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

require_login();
$user = get_logged_in_user($pdo);

if (!$user) {
    redirect('login.php');
}

// Check if teacher is approved
if ($user['user_type'] === 'teacher') {
    require_once 'includes/functions.php';
    if (!is_teacher_approved($pdo, $user['id'])) {
        $_SESSION['teacher_pending_message'] = true;
        $_SESSION['teacher_message'] = 'يجب موافقة الإدارة قبل البدء في العمل.';
        redirect('dashboard.php');
    }
}

// Check if user has active subscription or trial
if ($user['user_type'] !== 'admin') {
    $has_active_access = has_active_access($pdo, $user['id']);
    if (!$has_active_access) {
        $_SESSION['subscription_required'] = true;
        $_SESSION['subscription_message'] = 'انتهت فترة التجربة. يجب الاشتراك في إحدى الباقات للمتابعة.';
        redirect('subscription.php?required=1');
    }
}

$view = $_GET['view'] ?? 'upload';
$active_page = 'recitation';
$recitation_id = $_GET['id'] ?? null;

// Get teachers list for students (all approved teachers)
$teachers = [];
if ($user['user_type'] === 'student') {
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE user_type = 'teacher' AND is_active = TRUE AND teacher_status = 'approved'");
    $stmt->execute();
    $teachers = $stmt->fetchAll();
}

// Get recitation details if viewing
$recitation = null;
$correction = null;
if ($recitation_id) {
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as student_name, u2.full_name as teacher_name
        FROM recitations r
        LEFT JOIN users u ON r.student_id = u.id
        LEFT JOIN users u2 ON r.teacher_id = u2.id
        WHERE r.id = ?
    ");
    $stmt->execute([$recitation_id]);
    $recitation = $stmt->fetch();
    
    if ($recitation) {
        $stmt = $pdo->prepare("SELECT * FROM corrections WHERE recitation_id = ?");
        $stmt->execute([$recitation_id]);
        $correction = $stmt->fetch();
    }
}

// Get students list for teacher
$students_list = [];
if ($user['user_type'] === 'teacher' && $view === 'students') {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.full_name, u.email,
               (SELECT COUNT(*) FROM recitations r2 WHERE r2.student_id=u.id AND r2.teacher_id=?) as total_recitations,
               (SELECT COUNT(*) FROM recitations r3 WHERE r3.student_id=u.id AND r3.teacher_id=? AND r3.status='pending') as pending_count
        FROM users u
        WHERE u.user_type='student' AND u.is_active = TRUE
        AND u.id IN (
            SELECT DISTINCT student_id FROM recitations WHERE teacher_id=?
            UNION
            SELECT DISTINCT student_id FROM enrollments WHERE teacher_id=?
        )
        ORDER BY u.full_name
    ");
    $stmt->execute([$user['id'], $user['id'], $user['id'], $user['id']]);
    $students_list = $stmt->fetchAll();
}

// Get pending recitations for teacher
$pending_recitations = [];
if ($user['user_type'] === 'teacher' && $view === 'review') {
    // Get recitations assigned to this teacher OR pending recitations (no teacher assigned)
    // Only show recitations from students with same gender as teacher
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name as student_name
        FROM recitations r
        JOIN users u ON r.student_id = u.id
        WHERE r.teacher_id = ? AND r.status = 'pending'
        ORDER BY r.uploaded_at DESC
    ");
    $stmt->execute([$user['id']]);
    $pending_recitations = $stmt->fetchAll();
}

// Get student's recitations
$my_recitations = [];
if ($user['user_type'] === 'student') {
    $stmt = $pdo->prepare("
        SELECT r.*, c.feedback_text, c.voice_feedback_path, c.rating as correction_rating,
               c.corrected_at, u.full_name as teacher_name
        FROM recitations r
        LEFT JOIN corrections c ON r.id = c.recitation_id
        LEFT JOIN users u ON r.teacher_id = u.id
        WHERE r.student_id = ?
        ORDER BY r.uploaded_at DESC
    ");
    $stmt->execute([$user['id']]);
    $my_recitations = $stmt->fetchAll();
}

$surah_list = get_surah_list();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التلاوة - رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/video-player.css">
    <style>
        .recitation-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .upload-section {
            background-color: var(--white);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .audio-recorder, .video-recorder {
            background-color: var(--cream-bg);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .recorder-controls {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .recorder-btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .recorder-btn.record {
            background-color: #dc3545;
            color: white;
        }
        
        .recorder-btn.stop {
            background-color: #6c757d;
            color: white;
        }
        
        .recorder-btn:hover {
            transform: scale(1.05);
        }
        
        .audio-preview, .video-preview {
            margin-top: 1rem;
            display: none;
        }
        
        .audio-preview.active, .video-preview.active {
            display: block;
        }
        
        .video-preview video {
            width: 100%;
            max-width: 600px;
            border-radius: 10px;
            background-color: #000;
        }
        
        .recording-type-tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--cream-bg);
        }
        
        .recording-type-tab {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 1rem;
            color: var(--text-light);
            transition: all 0.3s ease;
        }
        
        .recording-type-tab.active {
            color: var(--dark-blue);
            border-bottom-color: var(--dark-blue);
            font-weight: 600;
        }
        
        .recitation-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .recitation-card {
            background-color: var(--white);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px var(--shadow);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .status-pending {
            background-color: #ffc107;
            color: #856404;
        }
        
        .status-reviewed {
            background-color: #28a745;
            color: white;
        }
        
        .status-approved {
            background-color: #17a2b8;
            color: white;
        }
        
        /* Voice Recorder UI Styles */
        .voice-recorder-ui {
            width: 100%;
            padding: 2rem 0;
        }
        
        .voice-recorder-container {
            position: relative;
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }
        
        .voice-recorder-btn {
            width: 64px;
            height: 64px;
            border-radius: 12px;
            border: none;
            background: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .voice-recorder-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        
        .voice-recorder-btn.recording {
            background: none;
        }
        
        .mic-icon {
            width: 24px;
            height: 24px;
            color: rgba(45, 62, 80, 0.7);
            transition: opacity 0.3s ease;
        }
        
        .voice-recorder-btn.recording .mic-icon {
            opacity: 0;
        }
        
        .recording-spinner {
            width: 24px;
            height: 24px;
            border-radius: 3px;
            background-color: var(--dark-blue);
            animation: spin 3s linear infinite;
            position: absolute;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .recording-timer {
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            color: rgba(45, 62, 80, 0.7);
            transition: opacity 0.3s ease;
            min-height: 1.5rem;
        }
        
        .recording-timer.recording {
            color: rgba(45, 62, 80, 0.7);
        }
        
        .visualizer-container {
            height: 16px;
            width: 256px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
        }
        
        .visualizer-bars {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 2px;
            width: 100%;
            height: 100%;
        }
        
        .visualizer-bar {
            width: 2px;
            border-radius: 2px;
            transition: all 0.3s ease;
            background-color: rgba(45, 62, 80, 0.1);
            height: 4px;
        }
        
        .visualizer-bar.active {
            background-color: rgba(45, 62, 80, 0.5);
            animation: pulse 0.3s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 0.5;
            }
            50% {
                opacity: 1;
            }
        }
        
        .recording-status {
            height: 1rem;
            font-size: 0.75rem;
            color: rgba(45, 62, 80, 0.7);
            margin: 0;
            min-height: 1rem;
        }
        
        .recording-status.recording {
            color: rgba(45, 62, 80, 0.7);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>

    
    <div class="recitation-container">
        <?php if ($user['user_type'] === 'student' && $view === 'upload'): ?>
            <!-- Student Upload View -->
            <div class="upload-section">
                <h2 style="margin-bottom: 1.5rem; color: var(--dark-blue);">رفع تلاوة جديدة</h2>
                
                <div class="recording-type-tabs">
                    <button type="button" class="recording-type-tab active" data-type="audio">تسجيل صوتي</button>
                    <button type="button" class="recording-type-tab" data-type="video">تسجيل فيديو</button>
                </div>
                
                <div class="audio-recorder" id="audioRecorderSection">
                    <h3>تسجيل التلاوة الصوتية</h3>
                    <p style="color: var(--text-light); margin-bottom: 1rem;">يمكنك تسجيل تلاوتك مباشرة أو رفع ملف صوتي</p>
                    
                    <div class="voice-recorder-ui">
                        <div class="voice-recorder-container">
                            <button
                                id="recordBtn"
                                class="voice-recorder-btn"
                                type="button"
                            >
                                <svg id="micIcon" class="mic-icon" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"></path>
                                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"></path>
                                    <line x1="12" y1="19" x2="12" y2="23"></line>
                                    <line x1="8" y1="23" x2="16" y2="23"></line>
                                </svg>
                                <div id="recordingSpinner" class="recording-spinner" style="display: none;"></div>
                            </button>
                            
                            <span id="recordingTimer" class="recording-timer">00:00</span>
                            
                            <div class="visualizer-container">
                                <div id="visualizerBars" class="visualizer-bars"></div>
                            </div>
                            
                            <p id="recordingStatus" class="recording-status">انقر للتسجيل</p>
                        </div>
                    </div>
                    
                    <div id="audioPreview" class="audio-preview">
                        <audio id="audioPlayback" controls style="width: 100%; margin-top: 1rem;"></audio>
                    </div>
                </div>
                
                <div class="video-recorder" id="videoRecorderSection" style="display: none;">
                    <h3>تسجيل التلاوة بالفيديو</h3>
                    <p style="color: var(--text-light); margin-bottom: 1rem;">يمكنك تسجيل تلاوتك بالفيديو مباشرة أو رفع ملف فيديو</p>
                    
                    <div class="recorder-controls">
                        <button id="videoRecordBtn" class="recorder-btn record">📹 بدء التسجيل</button>
                        <button id="videoStopBtn" class="recorder-btn stop" style="display: none;">⏹️ إيقاف</button>
                    </div>
                    
                    <div id="videoPreview" class="video-preview">
                        <video id="videoPlayback" controls playsinline style="width: 100%; max-width: 600px; margin: 1rem auto; display: none; border-radius: 10px; background-color: #000;"></video>
                    </div>
                </div>
                
                <form id="uploadForm" method="POST" action="<?php echo SITE_URL; ?>/api/upload_recitation.php" enctype="multipart/form-data">
                    <div class="form-group" id="audioFileGroup">
                        <label class="form-label" for="audio_file">أو اختر ملف صوتي</label>
                        <input type="file" id="audio_file" name="audio_file" class="form-input" accept="audio/*">
                    </div>
                    
                    <div class="form-group" id="videoFileGroup" style="display: none;">
                        <label class="form-label" for="video_file">أو اختر ملف فيديو</label>
                        <input type="file" id="video_file" name="video_file" class="form-input" accept="video/*">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="surah_name">اسم السورة</label>
                        <select id="surah_name" name="surah_name" class="form-select" required>
                            <option value="">اختر السورة</option>
                            <?php foreach ($surah_list as $surah): ?>
                                <option value="<?php echo htmlspecialchars($surah); ?>"><?php echo htmlspecialchars($surah); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="ayah_range">نطاق الآيات (اختياري)</label>
                        <input type="text" id="ayah_range" name="ayah_range" class="form-input" 
                               placeholder="مثال: 1-10 أو 15-20">
                    </div>
                    
                    <?php if (count($teachers) > 0): ?>
                    <div class="form-group">
                        <label class="form-label" for="teacher_id">اختر المعلم (اختياري)</label>
                        <select id="teacher_id" name="teacher_id" class="form-select">
                            <option value="">اختر معلم</option>
                            <?php foreach ($teachers as $teacher): ?>
                                <option value="<?php echo $teacher['id']; ?>"><?php echo htmlspecialchars($teacher['full_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <input type="hidden" id="recorded_audio" name="recorded_audio">
                    <input type="hidden" id="recorded_video" name="recorded_video">
                    
                    <button type="submit" class="btn btn-primary btn-large" style="width: 100%;">رفع التلاوة</button>
                </form>
                
                <script>
                    // Handle recording type tabs
                    document.querySelectorAll('.recording-type-tab').forEach(tab => {
                        tab.addEventListener('click', function() {
                            const type = this.dataset.type;
                            
                            // Update active tab
                            document.querySelectorAll('.recording-type-tab').forEach(t => t.classList.remove('active'));
                            this.classList.add('active');
                            
                            // Clear previous recordings when switching
                            if (type === 'audio') {
                                document.getElementById('audioRecorderSection').style.display = 'block';
                                document.getElementById('videoRecorderSection').style.display = 'none';
                                document.getElementById('audioFileGroup').style.display = 'block';
                                document.getElementById('videoFileGroup').style.display = 'none';
                                
                                // Clear video data
                                const videoInput = document.getElementById('recorded_video');
                                const videoFileInput = document.getElementById('video_file');
                                if (videoInput) videoInput.value = '';
                                if (videoFileInput) videoFileInput.value = '';
                                
                                // Reset video preview
                                const videoPreview = document.getElementById('videoPreview');
                                const videoPlayback = document.getElementById('videoPlayback');
                                if (videoPreview) videoPreview.classList.remove('active');
                                if (videoPlayback) {
                                    videoPlayback.src = '';
                                    videoPlayback.srcObject = null;
                                    videoPlayback.style.display = 'none';
                                }
                            } else {
                                document.getElementById('audioRecorderSection').style.display = 'none';
                                document.getElementById('videoRecorderSection').style.display = 'block';
                                document.getElementById('audioFileGroup').style.display = 'none';
                                document.getElementById('videoFileGroup').style.display = 'block';
                                
                                // Clear audio data
                                const audioInput = document.getElementById('recorded_audio');
                                const audioFileInput = document.getElementById('audio_file');
                                if (audioInput) audioInput.value = '';
                                if (audioFileInput) audioFileInput.value = '';
                                
                                // Reset audio preview
                                const audioPreview = document.getElementById('audioPreview');
                                const audioPlayback = document.getElementById('audioPlayback');
                                if (audioPreview) audioPreview.classList.remove('active');
                                if (audioPlayback) {
                                    audioPlayback.src = '';
                                }
                            }
                        });
                    });
                </script>
            </div>
            
            <!-- My Recitations List -->
            <?php if (count($my_recitations) > 0): ?>
            <div class="upload-section">
                <h2 style="margin-bottom: 1.5rem; color: var(--dark-blue);">تلاواتي</h2>
                <div class="recitation-list">
                    <?php foreach ($my_recitations as $rec): ?>
                    <div class="recitation-card">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                            <div>
                                <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($rec['surah_name']); ?></h3>
                                <?php if ($rec['ayah_range']): ?>
                                    <p style="color: var(--text-light);">الآيات: <?php echo htmlspecialchars($rec['ayah_range']); ?></p>
                                <?php endif; ?>
                                <p style="color: var(--text-light); font-size: 0.9rem;">
                                    <?php echo format_date($rec['uploaded_at']); ?>
                                </p>
                            </div>
                            <span class="status-badge status-<?php echo $rec['status']; ?>">
                                <?php
                                $status_text = [
                                    'pending' => 'قيد المراجعة',
                                    'reviewed' => 'تمت المراجعة',
                                    'approved' => 'مقبولة'
                                ];
                                echo $status_text[$rec['status']] ?? $rec['status'];
                                ?>
                            </span>
                        </div>
                        
                        <?php if ($rec['video_file_path']): ?>
                        <?php 
                        $video_ext = strtolower(pathinfo($rec['video_file_path'], PATHINFO_EXTENSION));
                        $video_mime = 'video/mp4';
                        if ($video_ext === 'webm') $video_mime = 'video/webm';
                        elseif ($video_ext === 'ogg') $video_mime = 'video/ogg';
                        ?>
                        <video data-custom-player preload="metadata" style="width: 100%; max-width: 600px; margin-bottom: 1rem;">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['video_file_path'])); ?>" type="<?php echo $video_mime; ?>">
                            المتصفح الخاص بك لا يدعم تشغيل الفيديو.
                        </video>
                        <?php elseif ($rec['audio_file_path']): ?>
                        <audio controls preload="metadata" style="width: 100%; margin-bottom: 1rem;">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/mpeg">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/webm">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/ogg">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/wav">
                            المتصفح الخاص بك لا يدعم تشغيل الصوت.
                        </audio>
                        <?php endif; ?>
                        
                        <?php if ($rec['status'] !== 'pending' || $rec['feedback_text'] || $rec['rating']): ?>
                        <div style="background-color: var(--cream-bg); padding: 1rem; border-radius: 10px; margin-top: 1rem;">
                            <strong>التعليقات من <?php echo htmlspecialchars($rec['teacher_name'] ?? 'المعلم'); ?>:</strong>
                            <p style="margin-top: 0.5rem;"><?php echo $rec['feedback_text'] ? nl2br(htmlspecialchars($rec['feedback_text'])) : '<em style="color:#94a3b8">لا توجد ملاحظات نصية</em>'; ?></p>
                            <?php if (!empty($rec['voice_feedback_path'])): ?>
                            <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px dashed #ddd;">
                                <strong>🎤 تعليق صوتي:</strong>
                                <audio controls style="width: 100%; margin-top: 0.5rem; max-width: 400px;">
                                    <source src="<?php echo htmlspecialchars($rec['voice_feedback_path']); ?>" type="audio/webm">
                                    المتصفح الخاص بك لا يدعم تشغيل الصوت.
                                </audio>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($rec['correction_rating']) && $rec['correction_rating'] > 0): ?>
                                <p style="margin-top: 0.5rem;"><strong>تقييم المعلم لتلاوتك:</strong>
                                <?php echo str_repeat('★',(int)$rec['correction_rating']).str_repeat('☆',5-(int)$rec['correction_rating']); ?>
                                (<?php echo $rec['correction_rating']; ?>/5)</p>
                            <?php endif; ?>
                            <?php if (!empty($rec['student_rating'])): ?>
                                <p style="margin-top:0.5rem;color:var(--secondary-green)"><strong>تقييمك للمعلم:</strong>
                                <?php echo str_repeat('★',(int)$rec['student_rating']).str_repeat('☆',5-(int)$rec['student_rating']); ?>
                                (<?php echo $rec['student_rating']; ?>/5)
                                <?php if ($rec['student_comment']): ?> — <?php echo htmlspecialchars($rec['student_comment']); ?><?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
        <?php elseif ($user['user_type'] === 'teacher' && $view === 'students'): ?>
            <!-- Teacher Students List View -->
            <div class="upload-section">
                <h2 style="margin-bottom: 1.5rem; color: var(--dark-blue);">قائمة الطلبة</h2>
                
                <?php if (count($students_list) > 0): ?>
                <div class="students-list">
                    <?php foreach ($students_list as $student): ?>
                    <div class="student-card">
                        <div class="student-name"><?php echo htmlspecialchars($student['full_name']); ?></div>
                        <div class="student-progress">
                            <p><strong>البريد:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                            <p><strong>إجمالي التلاوات:</strong> <?php echo $student['total_recitations']; ?></p>
                            <?php if ($student['pending_count'] > 0): ?>
                                <p style="color: #ffc107;"><strong>قيد المراجعة:</strong> <?php echo $student['pending_count']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div style="margin-top: 1rem;">
                            <a href="recitation.php?view=review&student_id=<?php echo $student['id']; ?>" class="btn btn-primary">عرض التلاوات</a>
                            <a href="progress.php?student_id=<?php echo $student['id']; ?>" class="btn btn-outline" style="margin-right: 0.5rem;">متابعة التقدم</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-light); padding: 2rem;">لا يوجد طلاب مسجلين بعد</p>
                <?php endif; ?>
            </div>
            
        <?php elseif ($user['user_type'] === 'teacher' && $view === 'review'): ?>
            <!-- Teacher Review View -->
            <div class="upload-section">
                <h2 style="margin-bottom: 1.5rem; color: var(--dark-blue);">تلاوات قيد المراجعة</h2>
                
                <?php if (count($pending_recitations) > 0): ?>
                <div class="recitation-list">
                    <?php foreach ($pending_recitations as $rec): ?>
                    <div class="recitation-card">
                        <div style="margin-bottom: 1rem;">
                            <h3 style="margin-bottom: 0.5rem;"><?php echo htmlspecialchars($rec['surah_name']); ?></h3>
                            <p><strong>الطالب:</strong> <?php echo htmlspecialchars($rec['student_name']); ?></p>
                            <?php if ($rec['ayah_range']): ?>
                                <p><strong>الآيات:</strong> <?php echo htmlspecialchars($rec['ayah_range']); ?></p>
                            <?php endif; ?>
                            <p style="color: var(--text-light); font-size: 0.9rem;">
                                <?php echo format_date($rec['uploaded_at']); ?>
                            </p>
                        </div>
                        
                        <?php if ($rec['video_file_path']): ?>
                        <?php 
                        $video_ext = strtolower(pathinfo($rec['video_file_path'], PATHINFO_EXTENSION));
                        $video_mime = 'video/mp4';
                        if ($video_ext === 'webm') $video_mime = 'video/webm';
                        elseif ($video_ext === 'ogg') $video_mime = 'video/ogg';
                        ?>
                        <video data-custom-player preload="metadata" style="width: 100%; max-width: 600px; margin-bottom: 1rem;">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['video_file_path'])); ?>" type="<?php echo $video_mime; ?>">
                            المتصفح الخاص بك لا يدعم تشغيل الفيديو.
                        </video>
                        <?php elseif ($rec['audio_file_path']): ?>
                        <audio controls preload="metadata" style="width: 100%; margin-bottom: 1rem;">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/mpeg">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/webm">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/ogg">
                            <source src="serve_audio.php?file=<?php echo urlencode(basename($rec['audio_file_path'])); ?>" type="audio/wav">
                            المتصفح الخاص بك لا يدعم تشغيل الصوت.
                        </audio>
                        <?php endif; ?>
                        
                        <a href="recitation.php?id=<?php echo $rec['id']; ?>&view=correction" class="btn btn-primary">مراجعة وتصحيح</a>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p style="text-align: center; color: var(--text-light); padding: 2rem;">لا توجد تلاوات قيد المراجعة</p>
                <?php endif; ?>
            </div>
            
        <?php elseif ($recitation && $view === 'correction'): ?>
            <!-- Correction View -->
            <div class="upload-section">
                <h2 style="margin-bottom: 1.5rem; color: var(--dark-blue);">مراجعة التلاوة</h2>
                
                <div class="recitation-card" style="margin-bottom: 2rem;">
                    <h3><?php echo htmlspecialchars($recitation['surah_name']); ?></h3>
                    <p><strong>الطالب:</strong> <?php echo htmlspecialchars($recitation['student_name']); ?></p>
                    <?php if ($recitation['ayah_range']): ?>
                        <p><strong>الآيات:</strong> <?php echo htmlspecialchars($recitation['ayah_range']); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($recitation['video_file_path']): ?>
                    <?php 
                    $video_ext = strtolower(pathinfo($recitation['video_file_path'], PATHINFO_EXTENSION));
                    $video_mime = 'video/mp4';
                    if ($video_ext === 'webm') $video_mime = 'video/webm';
                    elseif ($video_ext === 'ogg') $video_mime = 'video/ogg';
                    ?>
                    <video data-custom-player preload="metadata" style="width: 100%; max-width: 600px; margin: 1rem 0;">
                        <source src="serve_audio.php?file=<?php echo urlencode(basename($recitation['video_file_path'])); ?>" type="<?php echo $video_mime; ?>">
                        المتصفح الخاص بك لا يدعم تشغيل الفيديو.
                    </video>
                    <?php elseif ($recitation['audio_file_path']): ?>
                    <audio controls preload="metadata" style="width: 100%; margin: 1rem 0;">
                        <source src="serve_audio.php?file=<?php echo urlencode(basename($recitation['audio_file_path'])); ?>" type="audio/mpeg">
                        <source src="serve_audio.php?file=<?php echo urlencode(basename($recitation['audio_file_path'])); ?>" type="audio/webm">
                        <source src="serve_audio.php?file=<?php echo urlencode(basename($recitation['audio_file_path'])); ?>" type="audio/ogg">
                        <source src="serve_audio.php?file=<?php echo urlencode(basename($recitation['audio_file_path'])); ?>" type="audio/wav">
                        المتصفح الخاص بك لا يدعم تشغيل الصوت.
                    </audio>
                    <?php endif; ?>
                </div>
                
                <?php if (!$correction): ?>
                <form method="POST" action="<?php echo SITE_URL; ?>/api/submit_correction.php" id="correctionForm" enctype="multipart/form-data">
                    <input type="hidden" name="recitation_id" value="<?php echo $recitation['id']; ?>">
                    <input type="hidden" name="voice_feedback_data" id="voiceFeedbackData">
                    
                    <div class="form-group">
                        <label class="form-label" for="feedback_text">التعليقات والملاحظات</label>
                        <textarea id="feedback_text" name="feedback_text" class="form-input" rows="6" required></textarea>
                    </div>
                    
                    <!-- Voice Feedback Recorder -->
                    <div class="form-group">
                        <label class="form-label">تسجيل تعليق صوتي (اختياري)</label>
                        <div style="background: var(--cream-bg); padding: 1rem; border-radius: 10px; text-align: center;">
                            <button type="button" id="voiceFeedbackBtn" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 0.5rem;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 1a3 3 0 0 0-3 3v8a3 3 0 0 0 6 0V4a3 3 0 0 0-3-3z"/>
                                    <path d="M19 10v2a7 7 0 0 1-14 0v-2"/>
                                    <line x1="12" y1="19" x2="12" y2="23"/>
                                    <line x1="8" y1="23" x2="16" y2="23"/>
                                </svg>
                                <span id="voiceFeedbackStatus">🎤 سجل تعليق صوتي</span>
                            </button>
                            <div id="voiceFeedbackPreview" style="margin-top: 1rem; display: none;">
                                <audio id="voiceFeedbackAudio" controls style="width: 100%; max-width: 400px;"></audio>
                                <button type="button" id="removeVoiceFeedback" class="btn btn-outline" style="margin-top: 0.5rem; font-size: 0.85rem;">❌ حذف التسجيل</button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="rating">التقييم (من 1 إلى 5)</label>
                        <select id="rating" name="rating" class="form-select" required>
                            <option value="">اختر التقييم</option>
                            <option value="1">1 - يحتاج تحسين</option>
                            <option value="2">2 - مقبول</option>
                            <option value="3">3 - جيد</option>
                            <option value="4">4 - جيد جداً</option>
                            <option value="5">5 - ممتاز</option>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-large" style="width: 100%;">إرسال التصحيح</button>
                </form>
                
                <script>
                    // Voice feedback recording
                    let voiceFeedbackRecorder;
                    let voiceFeedbackChunks = [];
                    let isVoiceFeedbackRecording = false;
                    const voiceFeedbackBtn = document.getElementById('voiceFeedbackBtn');
                    const voiceFeedbackStatus = document.getElementById('voiceFeedbackStatus');
                    const voiceFeedbackData = document.getElementById('voiceFeedbackData');
                    const voiceFeedbackPreview = document.getElementById('voiceFeedbackPreview');
                    const voiceFeedbackAudio = document.getElementById('voiceFeedbackAudio');
                    const removeVoiceFeedback = document.getElementById('removeVoiceFeedback');
                    
                    voiceFeedbackBtn.addEventListener('click', async () => {
                        if (!isVoiceFeedbackRecording) {
                            try {
                                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                                voiceFeedbackRecorder = new MediaRecorder(stream);
                                voiceFeedbackChunks = [];
                                
                                voiceFeedbackRecorder.ondataavailable = (e) => {
                                    voiceFeedbackChunks.push(e.data);
                                };
                                
                                voiceFeedbackRecorder.onstop = () => {
                                    const audioBlob = new Blob(voiceFeedbackChunks, { type: 'audio/webm' });
                                    const audioUrl = URL.createObjectURL(audioBlob);
                                    voiceFeedbackAudio.src = audioUrl;
                                    voiceFeedbackPreview.style.display = 'block';
                                    
                                    // Convert to base64
                                    const reader = new FileReader();
                                    reader.onload = () => {
                                        voiceFeedbackData.value = reader.result;
                                    };
                                    reader.readAsDataURL(audioBlob);
                                    
                                    stream.getTracks().forEach(track => track.stop());
                                };
                                
                                voiceFeedbackRecorder.start();
                                isVoiceFeedbackRecording = true;
                                voiceFeedbackBtn.style.background = 'linear-gradient(135deg, #dc3545, #ff6b6b)';
                                voiceFeedbackBtn.style.color = 'white';
                                voiceFeedbackStatus.textContent = '⏹️ إيقاف التسجيل';
                            } catch (err) {
                                alert('يرجى السماح بالوصول إلى الميكروفون');
                            }
                        } else {
                            voiceFeedbackRecorder.stop();
                            isVoiceFeedbackRecording = false;
                            voiceFeedbackBtn.style.background = '';
                            voiceFeedbackBtn.style.color = '';
                            voiceFeedbackStatus.textContent = '🎤 تسجيل مرة أخرى';
                        }
                    });
                    
                    removeVoiceFeedback.addEventListener('click', () => {
                        voiceFeedbackData.value = '';
                        voiceFeedbackPreview.style.display = 'none';
                        voiceFeedbackAudio.src = '';
                        voiceFeedbackStatus.textContent = '🎤 سجل تعليق صوتي';
                    });
                </script>
                <?php else: ?>
                <div class="recitation-card">
                    <h3>التصحيح المقدم</h3>
                    <p><strong>التعليقات:</strong></p>
                    <p><?php echo nl2br(htmlspecialchars($correction['feedback_text'])); ?></p>
                    <?php if (!empty($correction['voice_feedback_path'])): ?>
                    <div style="margin-top: 1rem; padding: 1rem; background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border-radius: 10px; border: 1px solid #64b5f6;">
                        <strong style="display: flex; align-items: center; gap: 0.5rem; color: #1565c0; margin-bottom: 0.5rem;">
                            🎤 تعليق صوتي من المعلم:
                        </strong>
                        <audio controls style="width: 100%; max-width: 400px;">
                            <source src="<?php echo htmlspecialchars($correction['voice_feedback_path']); ?>" type="audio/webm">
                            المتصفح الخاص بك لا يدعم تشغيل الصوت.
                        </audio>
                    </div>
                    <?php endif; ?>
                    <p><strong>التقييم:</strong> <?php echo $correction['rating']; ?>/5</p>
                    <p style="color: var(--text-light); font-size: 0.9rem;">
                        <?php echo format_date($correction['corrected_at']); ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/recorder.js"></script>
    <script src="assets/js/dashboard.js"></script>
    <script src="assets/js/video-player.js"></script>
</body>
</html>

