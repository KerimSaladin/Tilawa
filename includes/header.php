<?php
/**
 * Shared dashboard header — يُستخدم في جميع صفحات اللوحة
 * $user must be set before including this file
 * $active_page should be set (e.g. 'dashboard', 'recitation', etc.)
 */
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/payment.php';
$active = $active_page ?? '';
$is_admin  = ($user['user_type'] ?? '') === 'admin';
$is_teacher = ($user['user_type'] ?? '') === 'teacher';
$is_student = ($user['user_type'] ?? '') === 'student';
$in_admin_folder = (strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$base = $in_admin_folder ? '../' : '';
?>
<div class="dashboard-header">
    <div class="header-left">
        <a href="<?php echo $base; ?>index.php" class="brand">
            <img src="<?php echo $base; ?>assets/images/images.png" alt="رتل معي" class="brand-logo">
            <span class="brand-name">
                <?php
                if ($is_admin) echo 'لوحة الإدارة';
                elseif ($is_teacher) echo 'لوحة المعلم';
                else echo 'لوحة الطالب';
                ?>
            </span>
        </a>
    </div>

    <div class="header-center">
        <nav class="header-nav">
            <?php if ($is_admin): ?>
                <a href="<?php echo $base; ?>admin/index.php"        class="nav-link <?php echo $active === 'admin_home' ? 'active' : ''; ?>">الرئيسية</a>
                <a href="<?php echo $base; ?>admin/users.php"        class="nav-link <?php echo $active === 'users' ? 'active' : ''; ?>">المستخدمون</a>
                <a href="<?php echo $base; ?>admin/teachers.php"     class="nav-link <?php echo $active === 'teachers' ? 'active' : ''; ?>">المعلمون</a>
                <a href="<?php echo $base; ?>admin/subscriptions.php" class="nav-link <?php echo $active === 'subscriptions' ? 'active' : ''; ?>">الاشتراكات</a>
                <a href="<?php echo $base; ?>admin/finance.php"      class="nav-link <?php echo $active === 'finance' ? 'active' : ''; ?>">المالية</a>
                <a href="<?php echo $base; ?>admin/activity_logs.php" class="nav-link <?php echo $active === 'logs' ? 'active' : ''; ?>">السجلات</a>
            <?php elseif ($is_teacher): ?>
                <a href="<?php echo $base; ?>dashboard.php"               class="nav-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>">الرئيسية</a>
                <a href="<?php echo $base; ?>recitation.php?view=students" class="nav-link <?php echo $active === 'recitation' ? 'active' : ''; ?>">الطلبة</a>
                <a href="<?php echo $base; ?>groups.php"                  class="nav-link <?php echo $active === 'groups' ? 'active' : ''; ?>">المجموعات</a>
                <a href="<?php echo $base; ?>messages.php"                class="nav-link <?php echo $active === 'messages' ? 'active' : ''; ?>">الرسائل</a>
                <a href="<?php echo $base; ?>live_session.php"            class="nav-link <?php echo $active === 'live' ? 'active' : ''; ?>">الجلسات المباشرة</a>
            <?php else: ?>
                <a href="<?php echo $base; ?>dashboard.php"  class="nav-link <?php echo $active === 'dashboard' ? 'active' : ''; ?>">الرئيسية</a>
                <a href="<?php echo $base; ?>teachers.php"   class="nav-link <?php echo $active === 'teachers' ? 'active' : ''; ?>">المعلمون</a>
                <a href="<?php echo $base; ?>recitation.php" class="nav-link <?php echo $active === 'recitation' ? 'active' : ''; ?>">التلاوة</a>
                <a href="<?php echo $base; ?>progress.php"   class="nav-link <?php echo $active === 'progress' ? 'active' : ''; ?>">التقدم</a>
                <a href="<?php echo $base; ?>my_groups.php"  class="nav-link <?php echo $active === 'groups' ? 'active' : ''; ?>">مجموعاتي</a>
                <a href="<?php echo $base; ?>live_session.php" class="nav-link <?php echo $active === 'live' ? 'active' : ''; ?>">الجلسات</a>
                <a href="<?php echo $base; ?>messages.php"   class="nav-link <?php echo $active === 'messages' ? 'active' : ''; ?>">الرسائل</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="header-right">
        <?php if ($is_student): ?>
        <div class="wallet-chip">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="14" rx="2" ry="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
            <span><?php echo isset($pdo) ? number_format(get_wallet_balance($pdo, $user['id']), 0, '.', ',') : '0'; ?> دج</span>
        </div>
        <?php endif; ?>

        <!-- الإشعارات -->
        <div class="notifications-container" style="position:relative;z-index:1002">
            <button class="icon-btn bell-icon" id="notificationsBtn" title="الإشعارات" aria-label="الإشعارات">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0v5l2 2H4l2-2V8"/><path d="M10 21h4"/></svg>
            </button>
            <div class="notifications-dropdown" id="notificationsDropdown">
                <div class="notifications-header">الإشعارات</div>
                <div class="notifications-list" id="notificationsList">
                    <div class="notification-item">لا توجد إشعارات جديدة</div>
                </div>
            </div>
        </div>

        <!-- قائمة المستخدم -->
        <div class="user-menu-container">
            <button class="icon-btn user-icon" id="userMenuBtn" title="الملف الشخصي" aria-label="الملف الشخصي">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-4 3-7 7-7s7 3 7 7"/></svg>
            </button>
            <div class="user-menu-dropdown" id="userMenuDropdown">
                <div class="dropdown-user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                <a href="<?php echo $base; ?>dashboard.php" class="dropdown-item">لوحة التحكم</a>
                <?php if ($is_student): ?>
                <a href="<?php echo $base; ?>wallet.php" class="dropdown-item">محفظتي</a>
                <a href="<?php echo $base; ?>subscription.php" class="dropdown-item">اشتراكاتي</a>
                <a href="<?php echo $base; ?>transactions.php" class="dropdown-item">المعاملات</a>
                <?php endif; ?>
                <?php if ($is_teacher): ?>
                <a href="<?php echo $base; ?>transactions.php" class="dropdown-item">أرباحي</a>
                <?php endif; ?>
                <a href="<?php echo $base; ?>includes/auth.php?logout=1" class="dropdown-item logout-link" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">تسجيل الخروج</a>
            </div>
        </div>

        <a href="<?php echo $base; ?>includes/auth.php?logout=1" class="logout-btn" title="تسجيل الخروج" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')" aria-label="تسجيل الخروج">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M19 21V3"/></svg>
        </a>
    </div>
</div>

<!-- ═══ Incoming-call toast (injected on every dashboard page) ═══ -->
<style>
#incomingCallToast{
  position:fixed;top:1.25rem;left:50%;transform:translateX(-50%) translateY(-120px);
  z-index:9999;background:#1e1e2e;border:1px solid rgba(99,102,241,.45);
  border-radius:18px;padding:1.1rem 1.4rem;display:flex;align-items:center;gap:1rem;
  box-shadow:0 8px 32px rgba(0,0,0,.55);min-width:300px;max-width:420px;
  transition:transform .35s cubic-bezier(.34,1.56,.64,1);pointer-events:none
}
#incomingCallToast.show{transform:translateX(-50%) translateY(0);pointer-events:auto}
.ict-avatar{
  width:46px;height:46px;border-radius:50%;flex-shrink:0;
  background:linear-gradient(135deg,#6366f1,#a78bfa);
  display:flex;align-items:center;justify-content:center;
  font-size:1.2rem;font-weight:700;color:#fff;position:relative
}
.ict-pulse{
  position:absolute;inset:-6px;border-radius:50%;
  border:2px solid rgba(99,102,241,.6);animation:ictPulse 1.4s ease-out infinite
}
@keyframes ictPulse{0%{transform:scale(.85);opacity:0}50%{opacity:1}100%{transform:scale(1.25);opacity:0}}
.ict-info{flex:1;min-width:0}
.ict-name{font-weight:700;font-size:.95rem;color:#f1f5f9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ict-sub{font-size:.78rem;color:#94a3b8;margin-top:.1rem}
.ict-btns{display:flex;gap:.5rem;flex-shrink:0}
.ict-btn{
  border:none;border-radius:50px;padding:.45rem .9rem;font-size:.8rem;font-weight:700;
  cursor:pointer;font-family:Cairo,sans-serif;transition:filter .15s
}
.ict-btn:hover{filter:brightness(1.15)}
.ict-accept{background:#22c55e;color:#fff}
.ict-decline{background:#ef4444;color:#fff}
</style>

<div id="incomingCallToast" role="alertdialog" aria-live="assertive" aria-label="مكالمة واردة">
  <div class="ict-avatar" id="ictAvatar">?<div class="ict-pulse"></div></div>
  <div class="ict-info">
    <div class="ict-name" id="ictName">—</div>
    <div class="ict-sub"  id="ictSub">مكالمة واردة</div>
  </div>
  <div class="ict-btns">
    <button class="ict-btn ict-accept"  id="ictAccept">📞 قبول</button>
    <button class="ict-btn ict-decline" id="ictDecline">📵 رفض</button>
  </div>
</div>

<audio id="ringtoneAudio" loop preload="none">
  <source src="<?php echo $base; ?>assets/audio/ringtone.mp3" type="audio/mpeg">
</audio>

<script>
(function(){
  const CALLS_API = '<?php echo $base; ?>api/calls.php';
  const toast     = document.getElementById('incomingCallToast');
  const ictName   = document.getElementById('ictName');
  const ictSub    = document.getElementById('ictSub');
  const ictAvatar = document.getElementById('ictAvatar');
  const ictAccept = document.getElementById('ictAccept');
  const ictDecline= document.getElementById('ictDecline');
  const ringtone  = document.getElementById('ringtoneAudio');

  let activeCallId  = null;
  let dismissed     = new Set();
  let onCallPage    = /\/call\.php/.test(window.location.pathname);

  if (onCallPage) return; // caller/callee already on call page

  function showToast(call) {
    if (dismissed.has(call.id)) return;
    activeCallId = call.id;
    const initial = (call.caller_name || '?').charAt(0).toUpperCase();
    ictAvatar.textContent = initial;
    // re-attach pulse (textContent wipes children)
    const pulse = document.createElement('div');
    pulse.className = 'ict-pulse';
    ictAvatar.appendChild(pulse);
    ictName.textContent = call.caller_name || 'مجهول';
    ictSub.textContent  = call.type === 'video' ? '📹 مكالمة فيديو واردة' : '🎤 مكالمة صوتية واردة';
    toast.classList.add('show');
    ringtone.play().catch(()=>{});
  }

  function hideToast() {
    toast.classList.remove('show');
    ringtone.pause();
    ringtone.currentTime = 0;
  }

  ictAccept.addEventListener('click', () => {
    if (!activeCallId) return;
    hideToast();
    window.location.href = '<?php echo $base; ?>call.php?call=' + activeCallId;
  });

  ictDecline.addEventListener('click', async () => {
    if (!activeCallId) return;
    dismissed.add(activeCallId);
    hideToast();
    const fd = new FormData();
    fd.append('action', 'decline');
    fd.append('call_id', activeCallId);
    await fetch(CALLS_API, { method:'POST', body:fd, credentials:'include' }).catch(()=>{});
    activeCallId = null;
  });

  async function pollIncoming() {
    try {
      const r = await fetch(CALLS_API + '?action=check_incoming', {credentials:'include'});
      const j = await r.json();
      if (j.success && j.call && !dismissed.has(j.call.id)) {
        showToast(j.call);
      } else if (!j.call && activeCallId) {
        // caller hung up before answer
        hideToast();
        activeCallId = null;
      }
    } catch {}
  }

  pollIncoming();
  setInterval(pollIncoming, 3000);
})();
</script>
