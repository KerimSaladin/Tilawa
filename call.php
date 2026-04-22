<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

$callee_id  = isset($_GET['user'])       ? (int)$_GET['user']       : 0;
$call_id    = isset($_GET['call'])       ? (int)$_GET['call']       : 0;
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$type       = (($_GET['type'] ?? 'audio') === 'video') ? 'video' : 'audio';

if (!$callee_id && !$call_id && !$session_id) redirect('messages.php');

// جلسة مباشرة — يمكن أن تكون جماعية أو فردية
$session_title = 'مكالمة';
if ($session_id) {
    $stype = $_GET['type'] ?? 'individual';
    if ($stype === 'group') {
        $stmt = $pdo->prepare("SELECT title FROM group_sessions WHERE id=?");
        $stmt->execute([$session_id]);
        $gs = $stmt->fetch();
        if ($gs) $session_title = $gs['title'];
    } else {
        $stmt = $pdo->prepare("SELECT u.full_name FROM live_sessions ls JOIN users u ON u.id=CASE WHEN ls.teacher_id=? THEN ls.student_id ELSE ls.teacher_id END WHERE ls.id=?");
        $stmt->execute([$user['id'], $session_id]);
        $ls = $stmt->fetch();
        if ($ls) $session_title = 'جلسة مع ' . $ls['full_name'];
    }
    $type = 'video'; // الجلسات دائماً فيديو
}

// اسم المتصل
$callee_name = '';
if ($callee_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
    $stmt->execute([$callee_id]);
    $row = $stmt->fetch();
    if ($row) $callee_name = $row['full_name'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title><?php echo htmlspecialchars($session_title); ?> — رتل معي</title>
    <script src="assets/js/call-service.js"></script>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#111;color:#fff;font-family:Cairo,sans-serif;height:100vh;overflow:hidden;display:flex;flex-direction:column}
        .call-header{padding:.9rem 1.5rem;background:#1e1e1e;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid #333}
        .call-title{font-weight:700;font-size:1rem}
        .call-state{font-size:.85rem;color:#94a3b8}
        .media-grid{flex:1;display:flex;gap:.75rem;padding:1rem;justify-content:center;align-items:center;position:relative}
        .media-box{border-radius:14px;background:#222;overflow:hidden;display:flex;align-items:center;justify-content:center;position:relative}
        .media-box-remote{flex:1;max-height:100%;aspect-ratio:16/9}
        .media-box-local{position:absolute;bottom:1rem;right:1rem;width:200px;height:112px;border:2px solid #2ecc71;z-index:10}
        .media-box video,.media-box audio{width:100%;height:100%;object-fit:cover}
        .avatar-placeholder{font-size:4rem;color:#555;text-align:center}
        .controls{padding:1.25rem;background:#1e1e1e;display:flex;justify-content:center;gap:.75rem;flex-wrap:wrap;border-top:1px solid #333}
        .ctrl{width:52px;height:52px;border-radius:50%;border:none;background:#333;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:1.2rem;transition:background .2s}
        .ctrl:hover{background:#444}
        .ctrl-end{background:#e74c3c;border-radius:26px;width:auto;padding:0 1.5rem;font-weight:700;font-size:.95rem;gap:.4rem}
        .ctrl-end:hover{background:#c0392b}
        .ctrl-accept{background:#2ecc71;color:#111}
        .ctrl-accept:hover{background:#27ae60}
        .incoming-overlay{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:100;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:1.5rem}
        .incoming-overlay h2{font-size:1.5rem}
        .incoming-btns{display:flex;gap:1.25rem}
        #localMedia{background:#1a1a1a}
        #remoteMedia{background:#1a1a1a;width:100%;height:100%}
    </style>
</head>
<body>
<div class="call-header">
    <div>
        <div class="call-title"><?php echo htmlspecialchars($session_title); ?></div>
        <div class="call-state" id="callState">جاري الاتصال...</div>
    </div>
    <div style="font-size:.85rem;color:#64748b"><?php echo $type==='video'?'📹 فيديو':'🎤 صوت'; ?></div>
</div>

<div class="media-grid">
    <?php if ($type==='video'): ?>
    <div class="media-box media-box-remote">
        <video id="remoteMedia" autoplay playsinline></video>
        <div class="avatar-placeholder" id="remoteAvatarPlaceholder">👤</div>
    </div>
    <div class="media-box media-box-local">
        <video id="localMedia" autoplay playsinline muted></video>
    </div>
    <?php else: ?>
    <div style="text-align:center">
        <div style="font-size:5rem;margin-bottom:1rem">🎤</div>
        <div style="color:#94a3b8"><?php echo htmlspecialchars($callee_name ?: $session_title); ?></div>
        <audio id="remoteMedia" autoplay></audio>
    </div>
    <?php endif; ?>
</div>

<div class="controls">
    <?php if ($call_id): ?>
    <button class="ctrl ctrl-accept" id="acceptBtn" title="قبول">✓ قبول</button>
    <button class="ctrl" id="declineBtn" style="background:#e74c3c" title="رفض">✕</button>
    <?php endif; ?>
    <button class="ctrl" id="muteBtn" title="كتم الصوت">🎤</button>
    <?php if ($type==='video'): ?>
    <button class="ctrl" id="camBtn" title="إيقاف الكاميرا">📷</button>
    <?php endif; ?>
    <button class="ctrl ctrl-end" id="endBtn">📵 إنهاء</button>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
let muted = false, camOff = false;

(async () => {
    const svc = new CallService({
        type:      '<?php echo $type; ?>',
        calleeId:  <?php echo $callee_id  ?: 'null'; ?>,
        callId:    <?php echo $call_id    ?: 'null'; ?>,
        localEl:   document.getElementById('localMedia'),
        remoteEl:  document.getElementById('remoteMedia'),
        stateEl:   document.getElementById('callState'),
        endBtn:    document.getElementById('endBtn'),
        acceptBtn: document.getElementById('acceptBtn'),
        declineBtn:document.getElementById('declineBtn'),
        onConnected: () => {
            const ph = document.getElementById('remoteAvatarPlaceholder');
            if (ph) ph.style.display = 'none';
        }
    });
    try {
        await svc.init();
        window._svc = svc;
    } catch(e) {
        document.getElementById('callState').textContent = 'تعذر بدء المكالمة: ' + e.message;
    }
})();

// كتم الصوت
document.getElementById('muteBtn')?.addEventListener('click', () => {
    if (!window._svc?.localStream) return;
    muted = !muted;
    window._svc.localStream.getAudioTracks().forEach(t => t.enabled = !muted);
    document.getElementById('muteBtn').textContent = muted ? '🔇' : '🎤';
    document.getElementById('muteBtn').style.background = muted ? '#e74c3c' : '#333';
});

// إيقاف الكاميرا
document.getElementById('camBtn')?.addEventListener('click', () => {
    if (!window._svc?.localStream) return;
    camOff = !camOff;
    window._svc.localStream.getVideoTracks().forEach(t => t.enabled = !camOff);
    document.getElementById('camBtn').textContent = camOff ? '🚫' : '📷';
    document.getElementById('camBtn').style.background = camOff ? '#e74c3c' : '#333';
});
</script>
</body>
</html>
