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
