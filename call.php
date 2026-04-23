<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';

if (!$pdo) { http_response_code(503); die('قاعدة البيانات غير متاحة.'); }
require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

$callee_id  = isset($_GET['user'])       ? (int)$_GET['user']       : 0;
$call_id    = isset($_GET['call'])       ? (int)$_GET['call']       : 0;
$session_id = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;
$type       = (($_GET['type'] ?? 'audio') === 'video') ? 'video' : 'audio';

if (!$callee_id && !$call_id && !$session_id) redirect('messages.php');

$session_title = 'مكالمة';
$callee_name   = '';

if ($session_id) {
    $stype = $_GET['stype'] ?? 'individual';
    if ($stype === 'group') {
        $stmt = $pdo->prepare("SELECT title FROM group_sessions WHERE id=?");
        $stmt->execute([$session_id]);
        $gs = $stmt->fetch();
        if ($gs) $session_title = $gs['title'];
    } else {
        $stmt = $pdo->prepare("SELECT u.full_name FROM live_sessions ls JOIN users u ON u.id=CASE WHEN ls.teacher_id=? THEN ls.student_id ELSE ls.teacher_id END WHERE ls.id=?");
        $stmt->execute([$user['id'], $session_id]);
        $ls = $stmt->fetch();
        if ($ls) { $session_title = 'جلسة مع ' . $ls['full_name']; $callee_name = $ls['full_name']; }
    }
    $type = 'video';
}

if ($callee_id) {
    $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id=?");
    $stmt->execute([$callee_id]);
    $row = $stmt->fetch();
    if ($row) $callee_name = $row['full_name'];
}

// Incoming call — get caller name
$caller_name = '';
if ($call_id) {
    $stmt = $pdo->prepare("SELECT u.full_name, c.type FROM calls c JOIN users u ON c.caller_id=u.id WHERE c.id=? AND (c.caller_id=? OR c.callee_id=?)");
    $stmt->execute([$call_id, $user['id'], $user['id']]);
    $callRow = $stmt->fetch();
    if ($callRow) {
        $caller_name = $callRow['full_name'];
        $type        = $callRow['type'];
    }
}

$display_name = $callee_name ?: $caller_name ?: $session_title;
$is_callee    = (bool)$call_id;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?php echo htmlspecialchars($display_name); ?> — رتل معي</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:#0f0f13;color:#fff;font-family:Cairo,sans-serif;height:100vh;overflow:hidden;display:flex;flex-direction:column}

/* ─── Header ──────────────────────────────────────────── */
.call-header{
  padding:.75rem 1.25rem;background:rgba(255,255,255,.04);
  border-bottom:1px solid rgba(255,255,255,.08);
  display:flex;justify-content:space-between;align-items:center;flex-shrink:0
}
.call-header-name{font-weight:700;font-size:1rem;color:#f1f5f9}
.call-header-state{font-size:.8rem;color:#64748b;margin-top:.1rem}
.call-type-badge{
  font-size:.78rem;color:#94a3b8;
  background:rgba(255,255,255,.07);border-radius:20px;padding:.3rem .75rem
}

/* ─── Media area ──────────────────────────────────────── */
.media-area{
  flex:1;position:relative;background:#0f0f13;overflow:hidden;display:flex;align-items:center;justify-content:center
}
#remoteVideo{width:100%;height:100%;object-fit:cover;display:block}
#localVideo{
  position:absolute;bottom:1.25rem;right:1.25rem;
  width:180px;height:101px;object-fit:cover;border-radius:12px;
  border:2px solid rgba(255,255,255,.2);background:#1e1e2e;z-index:10
}
audio#remoteAudio{display:none}

/* Waiting screen (audio call / connecting) */
.waiting-screen{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:1.5rem;padding:2rem;text-align:center
}
.avatar-ring{
  position:relative;width:120px;height:120px;
}
.avatar-ring-pulse{
  position:absolute;inset:-16px;border-radius:50%;
  border:3px solid rgba(99,102,241,.5);
  animation:pulse 1.8s ease-out infinite
}
.avatar-ring-pulse2{
  position:absolute;inset:-32px;border-radius:50%;
  border:2px solid rgba(99,102,241,.25);
  animation:pulse 1.8s ease-out .6s infinite
}
@keyframes pulse{0%{transform:scale(.8);opacity:0}50%{opacity:1}100%{transform:scale(1.3);opacity:0}}
.avatar-circle{
  width:120px;height:120px;border-radius:50%;
  background:linear-gradient(135deg,#6366f1,#818cf8);
  display:flex;align-items:center;justify-content:center;
  font-size:2.8rem;font-weight:700;color:#fff;position:relative;z-index:1
}
.waiting-name{font-size:1.4rem;font-weight:700;color:#f1f5f9}
.waiting-state{font-size:.9rem;color:#64748b}

/* ─── Incoming overlay (callee) ───────────────────────── */
#incomingOverlay{
  position:absolute;inset:0;z-index:50;
  background:linear-gradient(160deg,#0f0f13 0%,#1a1a2e 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1.75rem
}
.inc-avatar{
  width:110px;height:110px;border-radius:50%;
  background:linear-gradient(135deg,#6366f1,#a78bfa);
  display:flex;align-items:center;justify-content:center;
  font-size:2.5rem;font-weight:700;color:#fff;position:relative
}
.inc-pulse{
  position:absolute;inset:-14px;border-radius:50%;
  border:3px solid rgba(99,102,241,.55);
  animation:pulse 1.6s ease-out infinite
}
.inc-pulse2{
  position:absolute;inset:-28px;border-radius:50%;
  border:2px solid rgba(99,102,241,.25);
  animation:pulse 1.6s ease-out .5s infinite
}
.inc-name{font-size:1.5rem;font-weight:800;color:#f1f5f9;margin-bottom:-.5rem}
.inc-sub{font-size:.9rem;color:#94a3b8}
.inc-actions{display:flex;gap:2rem;margin-top:.5rem}
.inc-btn{
  width:68px;height:68px;border-radius:50%;border:none;cursor:pointer;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  font-size:1.6rem;transition:transform .15s,filter .15s;gap:.2rem
}
.inc-btn:hover{transform:scale(1.08);filter:brightness(1.15)}
.inc-btn span{font-size:.65rem;color:rgba(255,255,255,.8);font-weight:600;font-family:Cairo,sans-serif}
.inc-btn-accept{background:#22c55e;box-shadow:0 0 24px rgba(34,197,94,.4)}
.inc-btn-decline{background:#ef4444;box-shadow:0 0 24px rgba(239,68,68,.35)}

/* ─── Controls ────────────────────────────────────────── */
.controls{
  padding:1rem 1.5rem;background:rgba(255,255,255,.04);
  border-top:1px solid rgba(255,255,255,.07);
  display:flex;justify-content:center;align-items:center;gap:.75rem;flex-shrink:0
}
.ctrl{
  width:50px;height:50px;border-radius:50%;border:none;
  background:rgba(255,255,255,.1);color:#fff;cursor:pointer;
  display:flex;align-items:center;justify-content:center;font-size:1.25rem;
  transition:background .2s,transform .15s
}
.ctrl:hover{background:rgba(255,255,255,.18);transform:scale(1.07)}
.ctrl.active{background:#ef4444}
.ctrl-end{
  border-radius:26px;width:auto;padding:0 1.5rem;
  background:#ef4444;font-weight:700;font-size:.9rem;
  gap:.5rem;height:50px;color:#fff
}
.ctrl-end:hover{background:#dc2626}
</style>
</head>
<body>

<!-- Header -->
<div class="call-header">
  <div>
    <div class="call-header-name" id="displayName"><?php echo htmlspecialchars($display_name); ?></div>
    <div class="call-header-state" id="callState"><?php echo $is_callee ? 'مكالمة واردة…' : 'جاري الاتصال…'; ?></div>
  </div>
  <div class="call-type-badge"><?php echo $type==='video' ? '📹 فيديو' : '🎤 صوت'; ?></div>
</div>

<!-- Media area -->
<div class="media-area" id="mediaArea">

  <?php if ($is_callee): ?>
  <!-- Callee: show incoming overlay first -->
  <div id="incomingOverlay">
    <div class="inc-avatar">
      <?php echo mb_strtoupper(mb_substr($caller_name ?: '?', 0, 1, 'UTF-8')); ?>
      <div class="inc-pulse"></div><div class="inc-pulse2"></div>
    </div>
    <div class="inc-name"><?php echo htmlspecialchars($caller_name ?: 'مجهول'); ?></div>
    <div class="inc-sub"><?php echo $type==='video' ? '📹 مكالمة فيديو واردة' : '🎤 مكالمة صوتية واردة'; ?></div>
    <div class="inc-actions">
      <button class="inc-btn inc-btn-accept" id="acceptBtn">
        📞<span>قبول</span>
      </button>
      <button class="inc-btn inc-btn-decline" id="declineBtn">
        📵<span>رفض</span>
      </button>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($type === 'video'): ?>
    <video id="remoteVideo" autoplay playsinline></video>
    <video id="localVideo"  autoplay playsinline muted></video>
  <?php else: ?>
    <audio id="remoteAudio" autoplay></audio>
    <!-- Waiting / audio call screen -->
    <div class="waiting-screen" id="waitingScreen">
      <div class="avatar-ring">
        <div class="avatar-ring-pulse"></div>
        <div class="avatar-ring-pulse2"></div>
        <div class="avatar-circle"><?php echo mb_strtoupper(mb_substr($display_name, 0, 1, 'UTF-8')); ?></div>
      </div>
      <div class="waiting-name"><?php echo htmlspecialchars($display_name); ?></div>
      <div class="waiting-state" id="waitingState"><?php echo $is_callee ? 'مكالمة واردة…' : 'جاري الاتصال…'; ?></div>
    </div>
  <?php endif; ?>
</div>

<!-- Controls -->
<div class="controls">
  <button class="ctrl" id="muteBtn" title="كتم الصوت">🎤</button>
  <?php if ($type === 'video'): ?>
  <button class="ctrl" id="camBtn" title="إيقاف الكاميرا">📷</button>
  <?php endif; ?>
  <button class="ctrl ctrl-end" id="endBtn">📵 إنهاء</button>
</div>

<script src="assets/js/call-service.js"></script>
<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
let muted = false, camOff = false;

(async () => {
    const svc = new CallService({
        siteUrl:   SITE_URL,
        type:      '<?php echo $type; ?>',
        calleeId:  <?php echo $callee_id  ?: 'null'; ?>,
        callId:    <?php echo $call_id    ?: 'null'; ?>,
        localEl:   document.getElementById('localVideo')  || null,
        remoteEl:  document.getElementById('remoteVideo') || document.getElementById('remoteAudio'),
        stateEl:   document.getElementById('callState'),
        endBtn:    document.getElementById('endBtn'),
        acceptBtn: document.getElementById('acceptBtn'),
        declineBtn:document.getElementById('declineBtn'),
        onConnected: () => {
            document.getElementById('callState').textContent = 'متصل';
            const ws = document.getElementById('waitingState');
            if (ws) ws.textContent = 'متصل';
            // Hide incoming overlay once accepted and connected
            const ov = document.getElementById('incomingOverlay');
            if (ov) ov.style.display = 'none';
        },
        onStateChange: (status) => {
            if (status === 'accepted') {
                const ov = document.getElementById('incomingOverlay');
                if (ov) ov.style.display = 'none';
                const ws = document.getElementById('waitingState');
                if (ws) ws.textContent = 'جاري الاتصال…';
            }
        }
    });

    try {
        await svc.init();
        window._svc = svc;
    } catch(e) {
        document.getElementById('callState').textContent = 'تعذّر بدء المكالمة: ' + e.message;
    }
})();

// Mute
document.getElementById('muteBtn').addEventListener('click', () => {
    if (!window._svc?.localStream) return;
    muted = !muted;
    window._svc.localStream.getAudioTracks().forEach(t => t.enabled = !muted);
    const btn = document.getElementById('muteBtn');
    btn.textContent = muted ? '🔇' : '🎤';
    btn.classList.toggle('active', muted);
});

// Camera
document.getElementById('camBtn')?.addEventListener('click', () => {
    if (!window._svc?.localStream) return;
    camOff = !camOff;
    window._svc.localStream.getVideoTracks().forEach(t => t.enabled = !camOff);
    const btn = document.getElementById('camBtn');
    btn.textContent = camOff ? '🚫' : '📷';
    btn.classList.toggle('active', camOff);
});
</script>
</body>
</html>
