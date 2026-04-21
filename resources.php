<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';

require_login();
$user = get_logged_in_user($pdo);
$active_page = 'resources';

if (!$user) {
    redirect('login.php');
}

$resource = $_GET['resource'] ?? '';

$resources = [
    'virtues' => [
        'title' => 'فضل حفظ القرآن',
        'content' => '
        <h2>فضل حفظ القرآن الكريم</h2>
        <p>حفظ القرآن الكريم له فضائل عظيمة وثواب كبير عند الله تعالى:</p>
        <ul style="line-height: 2; margin: 1rem 0;">
            <li>حافظ القرآن يرفعه الله درجات في الجنة</li>
            <li>حافظ القرآن يكون مع السفرة الكرام البررة</li>
            <li>حافظ القرآن يلبس تاج الكرامة يوم القيامة</li>
            <li>حافظ القرآن يشفع له القرآن يوم القيامة</li>
            <li>حافظ القرآن يقرأ القرآن في كل وقت وحين</li>
        </ul>
        <p>قال رسول الله صلى الله عليه وسلم: "مثل الذي يقرأ القرآن وهو حافظ له مع السفرة الكرام البررة، ومثل الذي يقرأ القرآن وهو يتعاهده وهو عليه شديد فله أجران".</p>
        '
    ],
    'etiquette' => [
        'title' => 'آداب حامل القرآن',
        'content' => '
        <h2>آداب حامل القرآن الكريم</h2>
        <p>يجب على حامل القرآن أن يتحلى بآداب عظيمة:</p>
        <ul style="line-height: 2; margin: 1rem 0;">
            <li>الإخلاص لله تعالى في حفظه وتلاوته</li>
            <li>الطهارة عند قراءة القرآن</li>
            <li>استقبال القبلة عند القراءة</li>
            <li>التدبر والتفكر في معاني الآيات</li>
            <li>التعاهد والمتابعة في المراجعة</li>
            <li>التواضع وعدم الكبر</li>
            <li>العمل بما تعلم من القرآن</li>
            <li>تعليم القرآن للآخرين</li>
        </ul>
        <p>قال رسول الله صلى الله عليه وسلم: "خيركم من تعلم القرآن وعلمه".</p>
        '
    ],
    'adhkar' => [
        'title' => 'أذكار الصباح والمساء',
        'content' => '
        <h2>أذكار الصباح والمساء</h2>
        <h3>أذكار الصباح:</h3>
        <p style="line-height: 2;">
            <strong>أعوذ بالله من الشيطان الرجيم:</strong><br>
            "اللهم بك أصبحنا وبك أمسينا وبك نحيا وبك نموت وإليك النشور"<br><br>
            "أصبحنا وأصبح الملك لله، والحمد لله، لا إله إلا الله وحده لا شريك له"<br><br>
            "اللهم إني أسألك العافية في الدنيا والآخرة"<br><br>
            "اللهم عافني في بدني، اللهم عافني في سمعي، اللهم عافني في بصري"
        </p>
        <h3>أذكار المساء:</h3>
        <p style="line-height: 2;">
            <strong>أعوذ بالله من الشيطان الرجيم:</strong><br>
            "اللهم بك أمسينا وبك أصبحنا وبك نحيا وبك نموت وإليك المصير"<br><br>
            "أمسينا وأمسى الملك لله، والحمد لله، لا إله إلا الله وحده لا شريك له"<br><br>
            "اللهم إني أعوذ بك من الهم والحزن، وأعوذ بك من العجز والكسل"<br><br>
            "اللهم إني أعوذ بك من شر ما عملت ومن شر ما لم أعمل"
        </p>
        '
    ]
];

$current_resource = $resources[$resource] ?? null;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الموارد التعليمية - رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <style>
        .resources-container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .resources-header {
            text-align: right;
            margin-bottom: 2rem;
        }
        
        .resources-title {
            color: #5C80B2;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 2rem;
        }
        
        .resources-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .resource-item {
            background-color: #8DAFE2;
            color: #F8F7F2;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            text-decoration: none;
            font-size: 1.1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-align: right;
        }
        
        .resource-item:hover {
            background-color: #5C80B2;
            transform: translateX(-5px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .resource-content {
            background-color: var(--white);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 2px 8px var(--shadow);
            margin-top: 2rem;
        }
        
        .resource-content h2 {
            color: var(--dark-blue);
            margin-bottom: 1.5rem;
        }
        
        .resource-content h3 {
            color: var(--muted-blue);
            margin-top: 2rem;
            margin-bottom: 1rem;
        }
        
        .resource-content ul {
            list-style: none;
            padding-right: 0;
        }
        
        .resource-content li {
            padding-right: 1.5rem;
            position: relative;
        }
        
        .resource-content li:before {
            content: "•";
            position: absolute;
            right: 0;
            color: var(--muted-blue);
            font-weight: bold;
        }
        
        .back-btn {
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <div class="dashboard-header">
        <div class="header-left">
            <a href="dashboard.php" class="brand">
                <img src="assets/images/images.png" alt="رتل معي" class="brand-logo">
                <span class="brand-name"><?php echo $user['user_type'] === 'teacher' ? 'لوحة المعلم' : 'لوحة الطالب'; ?></span>
            </a>
        </div>
        <div class="header-center">
            <nav class="header-nav">
                <a href="dashboard.php" class="nav-link">الرئيسية</a>
                <?php if ($user['user_type'] === 'teacher'): ?>
                    <a href="recitation.php?view=students" class="nav-link">الطلبة</a>
                    <a href="groups.php" class="nav-link">المجموعات</a>
                    <a href="progress.php?view=reports" class="nav-link">التقارير</a>
                <?php else: ?>
                    <a href="recitation.php" class="nav-link">التلاوة</a>
                    <a href="progress.php" class="nav-link">التقدم</a>
                    <a href="my_groups.php" class="nav-link">مجموعاتي</a>
                    <a href="resources.php" class="nav-link">الموارد</a>
                <?php endif; ?>
            </nav>
        </div>
        <div class="header-right">
            <div class="notifications-container" style="position: relative; z-index: 1002;">
                <button class="icon-btn bell-icon" id="notificationsBtn" title="الإشعارات" aria-label="الإشعارات"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0v5l2 2H4l2-2V8"/><path d="M10 21h4"/></svg></button>
                <div class="notifications-dropdown" id="notificationsDropdown">
                    <div class="notifications-header">الإشعارات</div>
                    <div class="notifications-list" id="notificationsList">
                        <div class="notification-item">لا توجد إشعارات جديدة</div>
                    </div>
                </div>
            </div>
            <div class="user-menu-container">
                <button class="icon-btn user-icon" id="userMenuBtn" title="الملف الشخصي" aria-label="الملف الشخصي"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M5 20c0-4 3-7 7-7s7 3 7 7"/></svg></button>
                <div class="user-menu-dropdown" id="userMenuDropdown">
                    <a href="dashboard.php" class="dropdown-item">الملف الشخصي</a>
                    <a href="subscription.php" class="dropdown-item">الاشتراكات</a>
                    <a href="includes/auth.php?logout=1" class="dropdown-item logout-link">تسجيل الخروج</a>
                </div>
            </div>
            <a href="includes/auth.php?logout=1" class="logout-btn" title="تسجيل الخروج" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')" aria-label="تسجيل الخروج"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M19 21V3"/></svg></a>
        </div>
    </div>
    
    <div class="resources-container">
        <div class="resources-header">
            <h1 class="resources-title">موارد تعليمية</h1>
            
            <?php if ($current_resource): ?>
                <a href="resources.php" class="btn btn-outline back-btn">← العودة إلى الموارد</a>
            <?php endif; ?>
        </div>
        
        <?php if ($current_resource): ?>
            <!-- Show Resource Content -->
            <div class="resource-content">
                <?php echo $current_resource['content']; ?>
            </div>
        <?php else: ?>
            <!-- Show Resources List -->
            <div class="resources-list">
                <a href="resources.php?resource=virtues" class="resource-item">فضل حفظ القرآن</a>
                <a href="resources.php?resource=etiquette" class="resource-item">آداب حامل القرآن</a>
                <a href="resources.php?resource=adhkar" class="resource-item">أذكار الصباح والمساء</a>
            </div>
        <?php endif; ?>
    </div>
    <script src="assets/js/dashboard.js"></script>
</body>
</html>

