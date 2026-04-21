<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);

if (!$user) {
    redirect('login.php');
}

$session_id = $_GET['session_id'] ?? null;
if (!$session_id) {
    redirect('live_session.php');
}

// Get Session
$stmt = $pdo->prepare("
    SELECT ls.*, t.full_name as teacher_name, s.full_name as student_name 
    FROM live_sessions ls
    JOIN users t ON ls.teacher_id = t.id
    JOIN users s ON ls.student_id = s.id
    WHERE ls.id = ?
");
$stmt->execute([$session_id]);
$session = $stmt->fetch();

// Security Check: User must be part of the session
if (!$session || ($session['teacher_id'] != $user['id'] && $session['student_id'] != $user['id'])) {
    die("Access Denied");
}

$is_teacher = ($user['id'] == $session['teacher_id']);

// Handle End Session (Teacher)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $is_teacher && isset($_POST['end_session'])) {
    $report = $_POST['report_text'];
    $stmt = $pdo->prepare("
        UPDATE live_sessions 
        SET status = 'completed', end_time = NOW(), report_text = ? 
        WHERE id = ?
    ");
    $stmt->execute([$report, $session_id]);
    redirect('live_session.php');
}

// Update status to active if teacher joins and it's scheduled
if ($is_teacher && $session['status'] === 'scheduled') {
    $stmt = $pdo->prepare("UPDATE live_sessions SET status = 'active', start_time = NOW() WHERE id = ?");
    $stmt->execute([$session_id]);
    $session['status'] = 'active'; // Update local var
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>غرفة التسميع - <?php echo htmlspecialchars($session['teacher_name']); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .room-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
            background-color: #1a1a1a;
            color: white;
        }
        .room-header {
            padding: 1rem;
            background-color: #2d2d2d;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .video-grid {
            flex: 1;
            display: flex;
            gap: 1rem;
            padding: 1rem;
            justify-content: center;
            align-items: center;
        }
        .video-box {
            width: 45%;
            aspect-ratio: 16/9;
            background-color: #333;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
        }
        .avatar-placeholder {
            width: 100px;
            height: 100px;
            background: #555;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 2rem;
            margin-bottom: 1rem;
        }
        .user-label {
            position: absolute;
            bottom: 1rem;
            left: 1rem;
            background: rgba(0,0,0,0.6);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
        }
        .controls {
            padding: 1.5rem;
            background-color: #2d2d2d;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }
        .control-btn {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: none;
            background: #444;
            color: white;
            cursor: pointer;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 1.2rem;
            transition: background 0.2s;
        }
        .control-btn.active {
            background: var(--primary-green);
        }
        .control-btn.end-call {
            background: #e74c3c;
            width: auto;
            border-radius: 25px;
            padding: 0 1.5rem;
            font-weight: bold;
        }
        .report-modal {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .report-form {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            color: black;
            width: 90%;
            max-width: 500px;
        }
    </style>
</head>
<body>
    <div class="room-header">
        <div style="font-weight: bold; font-size: 1.2rem;">
            جلسة تسميع: <?php echo htmlspecialchars($session['student_name']); ?>
        </div>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <div style="color: #aaa;">المدة: <span id="timer">00:00</span></div>
            <div style="width: 10px; height: 10px; background: #2ecc71; border-radius: 50%;"></div>
        </div>
    </div>

    <div class="video-grid">
        <!-- Remote Video -->
        <div class="video-box">
            <div class="avatar-placeholder">👤</div>
            <div style="color: #aaa;">في انتظار الكاميرا...</div>
            <div class="user-label"><?php echo $is_teacher ? $session['student_name'] : $session['teacher_name']; ?></div>
        </div>
        
        <!-- Local Video -->
        <div class="video-box" style="border: 2px solid var(--primary-green);">
            <div class="avatar-placeholder">👤</div>
            <div style="color: #var(--primary-green);">صوتك متصل</div>
            <div class="user-label">أنت</div>
        </div>
    </div>

    <div class="controls">
        <button class="control-btn" onclick="this.classList.toggle('active')">🎤</button>
        <button class="control-btn" onclick="this.classList.toggle('active')">📹</button>
        
        <?php if ($is_teacher): ?>
            <button class="control-btn end-call" onclick="showReportModal()">انهاء الجلسة</button>
        <?php else: ?>
            <a href="live_session.php" class="control-btn end-call" style="text-decoration: none; display: flex; align-items: center;">مغادرة</a>
        <?php endif; ?>
    </div>

    <!-- Report Modal for Teacher -->
    <div class="report-modal" id="reportModal">
        <div class="report-form">
            <h2 style="margin-bottom: 1rem; color: var(--dark-blue);">تقرير الجلسة</h2>
            <form method="POST">
                <input type="hidden" name="end_session" value="1">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: bold;">ملاحظات وتقييم الطالب</label>
                    <textarea name="report_text" class="form-input" rows="5" required style="width: 100%; padding: 0.5rem; border: 1px solid #ccc; border-radius: 8px;" placeholder="اكتب ملاحظاتك هنا..."></textarea>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">حفظ وانهاء</button>
                    <button type="button" class="btn btn-outline" onclick="hideReportModal()" style="flex: 1;">إلغاء</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Simple Timer
        let seconds = 0;
        setInterval(() => {
            seconds++;
            const mins = Math.floor(seconds / 60).toString().padStart(2, '0');
            const secs = (seconds % 60).toString().padStart(2, '0');
            document.getElementById('timer').innerText = `${mins}:${secs}`;
        }, 1000);

        function showReportModal() {
            document.getElementById('reportModal').style.display = 'flex';
        }

        function hideReportModal() {
            document.getElementById('reportModal').style.display = 'none';
        }
    </script>
</body>
</html>
