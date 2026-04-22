<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user) redirect('login.php');

// اشتراك اختياري — لا توجد إعادة توجيه إجبارية

$chat_uid = isset($_GET['user']) ? (int)$_GET['user'] : 0;
$chat_user = null;
if ($chat_uid > 0) {
    $stmt = $pdo->prepare("SELECT id, full_name, user_type, is_online, last_seen FROM users WHERE id=? AND is_active = TRUE");
    $stmt->execute([$chat_uid]);
    $chat_user = $stmt->fetch();
    if ($chat_user) {
        $pdo->prepare("UPDATE messages SET is_read = TRUE WHERE sender_id=? AND receiver_id=?")->execute([$chat_uid, $user['id']]);
    }
}

// المحادثات
$conversations = $pdo->prepare("
    SELECT DISTINCT
        CASE WHEN sender_id=? THEN receiver_id ELSE sender_id END as other_id,
        (SELECT full_name FROM users WHERE id=other_id) as name,
        (SELECT user_type FROM users WHERE id=other_id) as utype,
        (SELECT is_online FROM users WHERE id=other_id) as is_online,
        (SELECT message_text FROM messages
            WHERE (sender_id=? AND receiver_id=other_id) OR (sender_id=other_id AND receiver_id=?)
            ORDER BY created_at DESC LIMIT 1) as last_msg,
        (SELECT created_at FROM messages
            WHERE (sender_id=? AND receiver_id=other_id) OR (sender_id=other_id AND receiver_id=?)
            ORDER BY created_at DESC LIMIT 1) as last_time,
        (SELECT COUNT(*) FROM messages WHERE sender_id=other_id AND receiver_id=? AND is_read = FALSE) as unread
    FROM messages
    WHERE sender_id=? OR receiver_id=?
    ORDER BY last_time DESC
");
$conversations->execute(array_fill(0, 8, $user['id']));
$conversations = $conversations->fetchAll();

// الرسائل مع المستخدم المحدد
$messages = [];
if ($chat_user) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.full_name as sender_name
        FROM messages m JOIN users u ON m.sender_id=u.id
        WHERE (m.sender_id=? AND m.receiver_id=?) OR (m.sender_id=? AND m.receiver_id=?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$user['id'], $chat_uid, $chat_uid, $user['id']]);
    $messages = $stmt->fetchAll();
}

// إجمالي غير مقروء
$unread_total = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE receiver_id=? AND is_read = FALSE");
$unread_total->execute([$user['id']]);
$unread_total = $unread_total->fetchColumn();

// قائمة المستخدمين للرسالة الجديدة
$contacts = $pdo->prepare("
    SELECT id, full_name, user_type FROM users
    WHERE is_active = TRUE AND id != ?
    AND (
        user_type IN ('admin')
        OR id IN (
            SELECT DISTINCT teacher_id FROM recitations WHERE student_id=? AND teacher_id IS NOT NULL
            UNION SELECT DISTINCT teacher_id FROM enrollments WHERE student_id=?
            UNION SELECT DISTINCT student_id FROM recitations WHERE teacher_id=?
            UNION SELECT DISTINCT student_id FROM enrollments WHERE teacher_id=?
        )
    )
    ORDER BY full_name
");
$contacts->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$contacts = $contacts->fetchAll();

$active_page = 'messages';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>الرسائل — رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .messages-layout{display:flex;height:calc(100vh - 70px);overflow:hidden;background:var(--warm-cream)}
        .sidebar{width:300px;flex-shrink:0;background:white;border-left:1px solid var(--sand);display:flex;flex-direction:column;overflow:hidden}
        .sidebar-head{padding:1rem 1.25rem;border-bottom:1px solid var(--sand);display:flex;justify-content:space-between;align-items:center}
        .sidebar-head h3{margin:0;font-size:1rem;font-weight:700;color:var(--charcoal)}
        .conv-list{flex:1;overflow-y:auto}
        .conv-item{display:flex;align-items:center;gap:.75rem;padding:.9rem 1rem;border-bottom:1px solid var(--sand);text-decoration:none;color:inherit;transition:background .15s;cursor:pointer}
        .conv-item:hover{background:var(--warm-cream)}
        .conv-item.active{background:#eff6ff;border-right:3px solid var(--royal-blue)}
        .conv-avatar{width:44px;height:44px;border-radius:50%;background:linear-gradient(135deg,var(--soft-blue),var(--royal-blue));display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1.1rem;flex-shrink:0;position:relative}
        .online-dot-sm{position:absolute;bottom:0;right:0;width:10px;height:10px;border-radius:50%;background:#22c55e;border:2px solid white}
        .conv-name{font-weight:600;font-size:.92rem;color:var(--charcoal)}
        .conv-last{font-size:.8rem;color:var(--slate);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:150px}
        .conv-time{font-size:.72rem;color:var(--slate);white-space:nowrap}
        .unread-badge{background:var(--royal-blue);color:#fff;border-radius:50px;font-size:.72rem;font-weight:700;padding:.1rem .45rem;min-width:18px;text-align:center}
        .chat-area{flex:1;display:flex;flex-direction:column;overflow:hidden}
        .chat-head{padding:1rem 1.5rem;background:white;border-bottom:1px solid var(--sand);display:flex;align-items:center;gap:1rem;flex-shrink:0}
        .chat-messages{flex:1;overflow-y:auto;padding:1.25rem 1.5rem;display:flex;flex-direction:column;gap:.75rem}
        .msg-bubble{max-width:70%;padding:.65rem 1rem;border-radius:16px;font-size:.92rem;line-height:1.5;word-break:break-word}
        .msg-out{align-self:flex-end;background:var(--royal-blue);color:#fff;border-bottom-right-radius:4px}
        .msg-in{align-self:flex-start;background:white;color:var(--charcoal);border:1px solid var(--sand);border-bottom-left-radius:4px}
        .msg-time{font-size:.7rem;opacity:.65;margin-top:.25rem;text-align:left}
        .msg-sender{font-size:.78rem;font-weight:600;margin-bottom:.2rem;color:var(--slate)}
        .chat-input-area{padding:1rem 1.5rem;background:white;border-top:1px solid var(--sand);flex-shrink:0}
        .chat-input-row{display:flex;gap:.75rem;align-items:flex-end}
        .chat-input-row textarea{flex:1;resize:none;border:1.5px solid var(--sand);border-radius:12px;padding:.65rem 1rem;font-family:inherit;font-size:.95rem;line-height:1.5;max-height:120px;transition:border-color .15s}
        .chat-input-row textarea:focus{border-color:var(--soft-blue);outline:none}
        .no-chat{flex:1;display:flex;align-items:center;justify-content:center;color:var(--slate);flex-direction:column;gap:1rem}
        .new-msg-btn{background:var(--royal-blue);color:#fff;border:none;border-radius:50px;width:32px;height:32px;font-size:1.1rem;cursor:pointer;display:flex;align-items:center;justify-content:center}
        .contacts-modal{position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center}
        .contacts-modal.show{display:flex}
        .contacts-box{background:white;border-radius:var(--radius-xl);padding:1.75rem;max-width:420px;width:90%;max-height:80vh;overflow-y:auto;box-shadow:var(--shadow-xl)}
        .contact-item{display:flex;align-items:center;gap:.75rem;padding:.75rem;border-radius:var(--radius-md);cursor:pointer;transition:background .15s}
        .contact-item:hover{background:var(--warm-cream)}
        @media(max-width:700px){.sidebar{width:100%;display:<?php echo $chat_user?'none':'flex'; ?>}.chat-area{display:<?php echo $chat_user?'flex':'none'; ?>}}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0;display:flex;flex-direction:column;height:100vh">
<?php include 'includes/header.php'; ?>

<div class="messages-layout">

<!-- الشريط الجانبي -->
<div class="sidebar">
    <div class="sidebar-head">
        <h3>الرسائل <?php if ($unread_total > 0): ?><span class="unread-badge"><?php echo $unread_total; ?></span><?php endif; ?></h3>
        <button class="new-msg-btn" onclick="document.getElementById('contactsModal').classList.add('show')" title="رسالة جديدة">✏</button>
    </div>
    <div class="conv-list">
        <?php if ($conversations): ?>
        <?php foreach ($conversations as $c): ?>
        <a href="?user=<?php echo $c['other_id']; ?>" class="conv-item <?php echo $chat_uid===$c['other_id']?'active':''; ?>">
            <div class="conv-avatar">
                <?php echo mb_substr($c['name']??'?',0,1,'UTF-8'); ?>
                <?php if ($c['is_online']): ?><div class="online-dot-sm"></div><?php endif; ?>
            </div>
            <div style="flex:1;min-width:0">
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div class="conv-name"><?php echo htmlspecialchars($c['name']??'مجهول'); ?></div>
                    <div class="conv-time"><?php echo $c['last_time'] ? date('H:i', strtotime($c['last_time'])) : ''; ?></div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <div class="conv-last"><?php echo htmlspecialchars(mb_substr($c['last_msg']??'',0,35,'UTF-8')); ?></div>
                    <?php if ($c['unread'] > 0): ?><div class="unread-badge"><?php echo $c['unread']; ?></div><?php endif; ?>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="padding:2rem;text-align:center;color:var(--slate);font-size:.9rem">لا توجد محادثات بعد.<br>ابدأ محادثة جديدة بالضغط على ✏</div>
        <?php endif; ?>
    </div>
</div>

<!-- منطقة الدردشة -->
<div class="chat-area">
    <?php if ($chat_user): ?>

    <!-- رأس الدردشة -->
    <div class="chat-head">
        <a href="messages.php" style="color:var(--slate);text-decoration:none;font-size:1.2rem">←</a>
        <div class="conv-avatar" style="width:40px;height:40px;font-size:1rem">
            <?php echo mb_substr($chat_user['full_name'],0,1,'UTF-8'); ?>
            <?php if ($chat_user['is_online']): ?><div class="online-dot-sm"></div><?php endif; ?>
        </div>
        <div>
            <div style="font-weight:700;font-size:.95rem"><?php echo htmlspecialchars($chat_user['full_name']); ?></div>
            <div style="font-size:.78rem;color:var(--slate)"><?php echo $chat_user['is_online']?'متصل الآن':'آخر ظهور: '.($chat_user['last_seen']?date('d/m H:i',strtotime($chat_user['last_seen'])):'—'); ?></div>
        </div>
        <div style="margin-right:auto;display:flex;gap:.5rem">
            <a href="call.php?user=<?php echo $chat_uid; ?>&type=audio" class="btn btn-outline" style="padding:.4rem .85rem;font-size:.85rem" title="مكالمة صوتية">📞</a>
            <a href="call.php?user=<?php echo $chat_uid; ?>&type=video" class="btn btn-primary"  style="padding:.4rem .85rem;font-size:.85rem" title="مكالمة فيديو">🎥</a>
        </div>
    </div>

    <!-- الرسائل -->
    <div class="chat-messages" id="chatMessages">
        <?php if ($messages): ?>
        <?php foreach ($messages as $m):
            $out = $m['sender_id'] === $user['id'];
        ?>
        <div class="msg-bubble <?php echo $out?'msg-out':'msg-in'; ?>">
            <?php if (!$out): ?><div class="msg-sender"><?php echo htmlspecialchars($m['sender_name']); ?></div><?php endif; ?>
            <?php echo nl2br(htmlspecialchars($m['message_text'])); ?>
            <div class="msg-time"><?php echo date('H:i d/m', strtotime($m['created_at'])); ?><?php if ($out && $m['is_read']): ?> ✓✓<?php elseif ($out): ?> ✓<?php endif; ?></div>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <div style="text-align:center;color:var(--slate);margin:auto;padding:3rem">ابدأ المحادثة مع <?php echo htmlspecialchars($chat_user['full_name']); ?></div>
        <?php endif; ?>
    </div>

    <!-- حقل الإدخال -->
    <div class="chat-input-area">
        <div class="chat-input-row">
            <textarea id="msgInput" placeholder="اكتب رسالتك..." rows="1" onkeydown="handleKey(event)"></textarea>
            <button onclick="sendMessage()" class="btn btn-primary" style="padding:.65rem 1.25rem;white-space:nowrap;align-self:flex-end">إرسال</button>
        </div>
    </div>

    <?php else: ?>
    <div class="no-chat">
        <div style="font-size:4rem">💬</div>
        <h3 style="color:var(--charcoal)">اختر محادثة</h3>
        <p style="color:var(--slate)">اختر محادثة من القائمة أو ابدأ واحدة جديدة</p>
        <button onclick="document.getElementById('contactsModal').classList.add('show')" class="btn btn-primary">✏ رسالة جديدة</button>
    </div>
    <?php endif; ?>
</div>

</div><!-- messages-layout -->
</div><!-- dashboard-container -->

<!-- مودال جهات الاتصال -->
<div class="contacts-modal" id="contactsModal">
    <div class="contacts-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="margin:0">رسالة جديدة</h3>
            <button onclick="document.getElementById('contactsModal').classList.remove('show')" style="background:none;border:none;font-size:1.5rem;cursor:pointer">✕</button>
        </div>
        <input type="text" id="contactSearch" placeholder="بحث عن مستخدم..." class="form-input" style="margin-bottom:1rem" oninput="filterContacts(this.value)">
        <div id="contactsList">
            <?php if ($contacts): ?>
            <?php foreach ($contacts as $c): ?>
            <div class="contact-item" onclick="window.location='?user=<?php echo $c['id']; ?>'">
                <div class="conv-avatar" style="width:38px;height:38px;font-size:.95rem"><?php echo mb_substr($c['full_name'],0,1,'UTF-8'); ?></div>
                <div>
                    <div style="font-weight:600;font-size:.9rem"><?php echo htmlspecialchars($c['full_name']); ?></div>
                    <div style="font-size:.78rem;color:var(--slate)"><?php echo $c['user_type']==='teacher'?'معلم':($c['user_type']==='admin'?'مدير':'طالب'); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div style="text-align:center;padding:2rem;color:var(--slate)">لا توجد جهات اتصال متاحة.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const SITE_URL = '<?php echo SITE_URL; ?>';
const CHAT_UID = <?php echo $chat_uid ?: 0; ?>;

// التمرير للأسفل
const cm = document.getElementById('chatMessages');
if (cm) cm.scrollTop = cm.scrollHeight;

// إرسال رسالة
async function sendMessage() {
    const inp = document.getElementById('msgInput');
    const txt = inp.value.trim();
    if (!txt || !CHAT_UID) return;
    inp.value = '';
    inp.style.height = '';
    try {
        const resp = await fetch(SITE_URL + '/api/messages.php', {
            method: 'POST', credentials: 'include',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: new URLSearchParams({action:'send', receiver_id: CHAT_UID, message_text: txt})
        });
        const d = await resp.json();
        if (d.success) {
            appendMessage(txt, true, d.time || new Date().toLocaleTimeString('ar',{hour:'2-digit',minute:'2-digit'}));
        } else {
            alert(d.message || 'فشل الإرسال');
        }
    } catch(e) { alert('فشل الاتصال'); }
}

function appendMessage(text, outgoing, time) {
    const cm = document.getElementById('chatMessages');
    if (!cm) return;
    const div = document.createElement('div');
    div.className = 'msg-bubble ' + (outgoing ? 'msg-out' : 'msg-in');
    div.innerHTML = text.replace(/\n/g,'<br>') + `<div class="msg-time">${time}</div>`;
    cm.appendChild(div);
    cm.scrollTop = cm.scrollHeight;
}

function handleKey(e) {
    const ta = e.target;
    // تمديد المساحة تلقائياً
    ta.style.height = 'auto';
    ta.style.height = Math.min(ta.scrollHeight, 120) + 'px';
    // إرسال بـ Enter (بدون Shift)
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMessage(); }
}

// استقبال رسائل جديدة كل 3 ثواني
<?php if ($chat_uid): ?>
let lastMsgId = <?php echo $messages ? end($messages)['id'] : 0; ?>;
setInterval(async () => {
    try {
        const r = await fetch(`${SITE_URL}/api/messages.php?action=poll&with=${CHAT_UID}&last_id=${lastMsgId}`, {credentials:'include'});
        const d = await r.json();
        if (d.success && d.messages?.length) {
            d.messages.forEach(m => {
                lastMsgId = m.id;
                appendMessage(m.message_text, m.sender_id == <?php echo $user['id']; ?>,
                    new Date(m.created_at).toLocaleTimeString('ar',{hour:'2-digit',minute:'2-digit'}));
            });
        }
    } catch {}
}, 3000);
<?php endif; ?>

// بحث في جهات الاتصال
function filterContacts(q) {
    document.querySelectorAll('#contactsList .contact-item').forEach(item => {
        item.style.display = item.textContent.toLowerCase().includes(q.toLowerCase()) ? '' : 'none';
    });
}

document.getElementById('contactsModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('contactsModal'))
        document.getElementById('contactsModal').classList.remove('show');
});
</script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
