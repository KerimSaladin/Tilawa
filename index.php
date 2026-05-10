<?php
// Use DIRECTORY_SEPARATOR for cross-platform compatibility
$config_path = __DIR__ . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'config.php';

if (!file_exists($config_path)) {
    die('Error: Configuration file not found at: ' . $config_path . '<br>Please ensure the includes folder exists in the project root.');
}

require_once $config_path;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رتل معي - منصة تعليم القرآن الكريم</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/animations.css">
    <link rel="stylesheet" href="assets/css/quote-card.css">
    <style>
        /* Premium Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--cream-bg) 0%, rgba(232, 244, 253, 0.5) 50%, var(--cream-bg) 100%);
            padding: 5rem 2rem;
            min-height: 90vh;
            display: flex;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background Orbs */
        .hero-section::before {
            content: '';
            position: absolute;
            top: -20%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.08) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(60px);
            animation: floatOrb 15s ease-in-out infinite;
            pointer-events: none;
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            bottom: -20%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: radial-gradient(circle, rgba(91, 139, 214, 0.1) 0%, transparent 70%);
            border-radius: 50%;
            filter: blur(50px);
            animation: floatOrb 12s ease-in-out infinite reverse;
            pointer-events: none;
        }
        
        @keyframes floatOrb {
            0%, 100% { transform: translate(0, 0) scale(1); }
            33% { transform: translate(30px, -30px) scale(1.05); }
            66% { transform: translate(-20px, 20px) scale(0.95); }
        }
        
        .hero-content {
            max-width: 1280px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1.1fr 0.9fr;
            gap: 5rem;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .hero-text {
            z-index: 2;
        }
        
        /* Premium Badge */
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.12) 0%, rgba(245, 230, 195, 0.2) 100%);
            border: 1px solid rgba(212, 175, 55, 0.25);
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #B8860B;
            margin-bottom: 1.5rem;
            animation: fadeInUp 0.8s ease-out;
            backdrop-filter: blur(10px);
        }
        
        .hero-badge svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }
        
        .hero-title-main {
            font-size: clamp(2.5rem, 5vw, 4rem);
            margin-bottom: 1.5rem;
            line-height: 1.25;
            font-weight: 900;
            font-family: 'Cairo', 'Tajawal', 'Amiri', serif;
            background: linear-gradient(135deg, #1A2A4A 0%, #2D4A8A 40%, #5B8BD6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.03em;
            position: relative;
            animation: fadeInUp 1s ease-out;
        }
        
        .gooey-text-wrapper {
            display: inline-block;
            min-width: 280px;
            text-align: right;
            position: relative;
            vertical-align: baseline;
            margin-top: 0.3em;
        }
        
        .hero-title-gooey {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 900;
            font-family: 'Cairo', 'Tajawal', 'Amiri', serif;
            background: linear-gradient(135deg, #D4AF37 0%, #B8860B 50%, #D4AF37 100%);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.03em;
            white-space: nowrap;
            animation: goldShimmer 4s ease infinite;
        }
        
        @keyframes goldShimmer {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @media (max-width: 968px) {
            .hero-title-gooey {
                font-size: 2.2rem;
            }
            
            .gooey-text-wrapper {
                min-width: 200px;
            }
        }
        
        .hero-title-main::after {
            content: '';
            position: absolute;
            bottom: -15px;
            right: 0;
            width: 120px;
            height: 4px;
            background: linear-gradient(90deg, transparent 0%, #D4AF37 50%, transparent 100%);
            border-radius: 2px;
            animation: fadeInUp 1.2s ease-out 0.3s both;
        }
        
        .hero-subtitle-main {
            font-size: clamp(1.2rem, 2.5vw, 1.5rem);
            color: var(--slate);
            margin-bottom: 2.5rem;
            line-height: 2;
            font-family: 'Amiri', 'Scheherazade New', 'Cairo', serif;
            font-weight: 400;
            letter-spacing: 0.02em;
            animation: fadeInUp 1.2s ease-out 0.2s both;
            position: relative;
            padding-right: 1.5rem;
            transition: all 0.4s ease;
        }
        
        .hero-subtitle-main:hover {
            color: var(--charcoal);
            transform: translateX(-5px);
        }
        
        .hero-subtitle-main::before {
            content: '';
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(180deg, transparent 0%, #D4AF37 30%, #D4AF37 70%, transparent 100%);
            border-radius: 2px;
            transition: all 0.3s ease;
        }
        
        .hero-buttons-main {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            animation: fadeInUp 1.4s ease-out 0.4s both;
        }
        
        .hero-illustration {
            position: relative;
            z-index: 1;
            transform-style: preserve-3d;
            transition: transform 0.2s ease-out;
        }
        
        /* Decorative Frame */
        .hero-illustration::before {
            content: '';
            position: absolute;
            inset: -15px;
            border: 2px solid rgba(212, 175, 55, 0.2);
            border-radius: 28px;
            transform: rotate(-3deg);
            transition: all 0.4s ease;
        }
        
        .hero-illustration:hover::before {
            transform: rotate(0deg);
            border-color: rgba(212, 175, 55, 0.4);
        }
        
        .hero-photo {
            width: 100%;
            max-height: 480px;
            border-radius: 24px;
            object-fit: cover;
            box-shadow: 
                0 25px 50px rgba(0, 0, 0, 0.15),
                0 0 0 1px rgba(255, 255, 255, 0.5),
                0 0 80px rgba(212, 175, 55, 0.1);
            display: block;
            transition: all 0.5s ease;
        }
        
        .hero-illustration:hover .hero-photo {
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.2),
                0 0 0 1px rgba(255, 255, 255, 0.5),
                0 0 100px rgba(212, 175, 55, 0.15);
            transform: scale(1.02);
        }
        
        /* Premium Features Section */
        .features-section {
            padding: 6rem 2rem;
            background: linear-gradient(180deg, white 0%, var(--cream-bg) 100%);
            position: relative;
            overflow: hidden;
        }
        
        .features-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent 0%, rgba(212, 175, 55, 0.3) 50%, transparent 100%);
        }
        
        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }
        
        .section-title {
            font-size: clamp(2rem, 4vw, 2.8rem);
            font-weight: 800;
            font-family: 'Cairo', 'Tajawal', serif;
            background: linear-gradient(135deg, #1A2A4A 0%, #2D4A8A 50%, #5B8BD6 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, transparent 0%, #D4AF37 50%, transparent 100%);
            border-radius: 2px;
        }
        
        .section-subtitle {
            font-size: 1.15rem;
            color: var(--slate);
            max-width: 600px;
            margin: 1.5rem auto 0;
            line-height: 1.8;
        }
        
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2.5rem;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .feature-card {
            text-align: center;
            padding: 2.5rem 2rem;
            background: var(--glass-white);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: var(--shadow-md);
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: visible;
            z-index: 1;
        }
        
        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-gold) 0%, var(--soft-blue) 50%, var(--accent-green) 100%);
            transform: scaleX(0);
            transition: transform 0.4s ease;
            border-radius: 24px 24px 0 0;
            z-index: 2;
        }
        
        .feature-card:hover {
            transform: translateY(-12px);
            box-shadow: var(--shadow-xl), 0 0 60px rgba(212, 175, 55, 0.08);
        }
        
        .feature-card:hover::before {
            transform: scaleX(1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            background: linear-gradient(135deg, rgba(91, 139, 214, 0.1) 0%, rgba(212, 175, 55, 0.08) 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.4s ease;
        }
        
        .feature-card:hover .feature-icon {
            background: linear-gradient(135deg, var(--soft-blue) 0%, var(--royal-blue) 100%);
            transform: scale(1.1) rotate(5deg);
            box-shadow: 0 10px 30px rgba(91, 139, 214, 0.3);
        }
        
        .feature-icon img {
            width: 40px;
            height: 40px;
            display: inline-block;
            transition: filter 0.3s ease;
        }
        
        .feature-card:hover .feature-icon img {
            filter: brightness(0) invert(1);
        }
        
        .feature-title {
            font-size: 1.4rem;
            font-weight: 700;
            font-family: 'Cairo', 'Tajawal', serif;
            color: var(--charcoal);
            margin-bottom: 0.75rem;
            letter-spacing: -0.01em;
            transition: all 0.3s ease;
        }
        
        .feature-card:hover .feature-title {
            background: linear-gradient(135deg, var(--deep-blue) 0%, var(--royal-blue) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .feature-description {
            color: var(--slate);
            font-size: 1rem;
            line-height: 1.8;
            font-family: 'Cairo', 'Tajawal', sans-serif;
        }
        
        /* Stats Section */
        .stats-bar {
            display: flex;
            justify-content: center;
            gap: 4rem;
            padding: 2rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #D4AF37 0%, #B8860B 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-family: 'Cairo', sans-serif;
        }
        
        .stat-label {
            font-size: 0.95rem;
            color: var(--slate);
            margin-top: 0.25rem;
        }
        
        @media (max-width: 968px) {
            .hero-content {
                grid-template-columns: 1fr;
                gap: 3rem;
            }
            
            .hero-title-main {
                font-size: 2.2rem;
            }
            
            .features-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .stats-bar {
                gap: 2rem;
            }
            
            .hero-illustration::before {
                display: none;
            }
        }

        /* Resources Section Styles */
        .resources-section {
            padding: 6rem 2rem;
            background: linear-gradient(180deg, var(--cream-bg) 0%, white 100%);
            position: relative;
        }
        
        .resources-preview {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 2rem;
            margin-top: 3rem;
        }
        
        .resource-preview-card {
            background: white;
            border-radius: 20px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
            position: relative;
            overflow: hidden;
        }
        
        .resource-preview-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--dark-blue), var(--muted-blue));
        }
        
        .resource-preview-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border-color: var(--dark-blue);
        }
        
        .resource-icon {
            font-size: 3.5rem;
            margin-bottom: 1.5rem;
            display: block;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.1));
        }
        
        .resource-preview-card h3 {
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--dark-blue);
            margin-bottom: 1rem;
            font-family: 'Amiri', serif;
        }
        
        .resource-preview-card p {
            color: #666;
            line-height: 1.7;
            font-size: 1rem;
        }
        
        @media (max-width: 768px) {
            .resources-preview {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            
            .resource-preview-card {
                padding: 2rem 1.5rem;
            }
            
            .resource-icon {
                font-size: 3rem;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <img src="assets/images/images.png" alt="رتل معي" class="site-logo" style="height = 100px, height = 100px">
                </div>
                <div class="nav-buttons">
                    <a href="register.php" class="btn btn-outline">تجربة مجانية</a>
                    <a href="login.php" class="btn btn-primary">تسجيل الدخول</a>
                </div>
            </div>
        </div>
    </header>

    <main>
        <section class="hero-section">
            <div class="hero-content">
                <div class="hero-text">
                    <div class="hero-badge">
                        <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z"/></svg>
                        <span>منصة تعليمية متميزة</span>
                    </div>
                    <h1 class="hero-title-main">
                        رتل معي... لأن الجمال كله في 
                        <span class="gooey-text-wrapper" 
                              data-gooey-text="تلاوة القرآن|حفظ القرآن|تلاوة القرآن|حفظ القرآن" 
                              data-morph-time="1" 
                              data-cooldown-time="0.25"
                              data-text-class-name="hero-title-gooey"></span>
                    </h1>
                    <p class="hero-subtitle-main">
                        هنا يبدأ نور القرآن يلامس قلبك، وتهمس الآيات بسكينة تفيض على روحك.
                    </p>
                    <div class="hero-buttons-main">
                        <a href="register.php" class="btn btn-primary btn-large">انشاء حساب</a>
                        <a href="login.php" class="btn btn-secondary btn-large">تسجيل الدخول</a>
                    </div>
                </div>
                <div class="hero-illustration" data-tilt data-tilt-rotation="15">
                    <img src="https://images.unsplash.com/photo-1596125160970-6f02eeba00d3?w=1200&auto=format&fit=crop&q=60&ixlib=rb-4.1.0&ixid=M3wxMjA3fDB8MHxzZWFyY2h8MTB8fHF1cmFofGVufDB8fDB8fHww" alt="صورة مصحف بلمسة فنية تعزز الطابع التعليمي والروحاني" class="hero-photo" loading="lazy" decoding="async" referrerpolicy="no-referrer">
                </div>
            </div>
        </section>

        <!-- Quote Section -->
        <section class="quote-section" id="quote-app">
            <div class="container quote-container">
                <div class="layout-controller">
                    <button class="layout-btn active" data-layout="stack" aria-label="عرض متراكم">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"></polygon><polyline points="2 17 12 22 22 17"></polyline><polyline points="2 12 12 17 22 12"></polyline></svg>
                    </button>
                    <button class="layout-btn" data-layout="grid" aria-label="عرض شبكي">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    </button>
                    <button class="layout-btn" data-layout="list" aria-label="عرض قائمة">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"></line><line x1="8" y1="12" x2="21" y2="12"></line><line x1="8" y1="18" x2="21" y2="18"></line><line x1="3" y1="6" x2="3.01" y2="6"></line><line x1="3" y1="12" x2="3.01" y2="12"></line><line x1="3" y1="18" x2="3.01" y2="18"></line></svg>
                    </button>
                </div>

                <div class="cards-wrapper stack">
                    <!-- Cards will be injected here -->
                </div>
                
                <div class="stack-nav">
                    <!-- Nav dots -->
                </div>
            </div>
            <div class="overlay"></div>
        </section>

        <section class="features-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">مميزات المنصة</h2>
                    <p class="section-subtitle">نقدم لك أدوات متكاملة لرحلة حفظ القرآن الكريم وتلاوته بإتقان</p>
                </div>
                <div class="features-grid">
                    <div class="feature-card">
                        <div class="feature-icon" aria-hidden="true"><img src="assets/images/target.svg" alt=""></div>
                        <h3 class="feature-title">تسهيل حفظ القرآن</h3>
                        <p class="feature-description">منصة متكاملة لتسهيل حفظ القرآن الكريم وضبطه عن بعد</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon" aria-hidden="true"><img src="assets/images/chart.svg" alt=""></div>
                        <h3 class="feature-title">لوحة متابعة التقدم</h3>
                        <p class="feature-description">تتبع تقدمك في الحفظ مع إحصائيات مفصلة وسهلة الفهم</p>
                    </div>
                    <div class="feature-card">
                        <div class="feature-icon" aria-hidden="true"><img src="assets/images/check.svg" alt=""></div>
                        <h3 class="feature-title">تصحيح التلاوة</h3>
                        <p class="feature-description">احصل على تصحيح احترافي لتلاوتك من معلمين مؤهلين</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Free Resources Section -->
        <section class="resources-section">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">📚 موارد تعليمية مجانية</h2>
                    <p class="section-subtitle">استفد من مجموعتنا المختارة من الموارد التعليمية لتعزيز رحلتك في تعلم القرآن</p>
                </div>
                <div class="resources-preview">
                    <div class="resource-preview-card">
                        <div class="resource-icon">🎥</div>
                        <h3>فيديوهات تعليمية</h3>
                        <p>دروس فيديو مصورة لتعليم أحكام التجويد والتلاوة الصحيحة</p>
                    </div>
                    <div class="resource-preview-card">
                        <div class="resource-icon">📄</div>
                        <h3>ملفات PDF</h3>
                        <p>كتب ومواد مطبوعة في علوم القرآن والتجويد</p>
                    </div>
                    <div class="resource-preview-card">
                        <div class="resource-icon">🔗</div>
                        <h3>روابط مفيدة</h3>
                        <p>مواقع ومصادر موثوقة لتعلم القرآن الكريم</p>
                    </div>
                </div>
                <div style="text-align: center; margin-top: 2rem;">
                    <a href="resources_public.php" class="btn btn-primary" style="background: linear-gradient(135deg, var(--dark-blue), var(--muted-blue)); padding: 1rem 2rem; font-size: 1.1rem; border-radius: 25px; text-decoration: none; color: white; font-weight: 600; display: inline-block;">
                        استكشف جميع الموارد 📖
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer>
        <div class="container">
            <p>&copy; 2024 رتل معي. جميع الحقوق محفوظة.</p>
        </div>
    </footer>
    <script src="assets/js/tilt-effect.js"></script>
    <script src="assets/js/gooey-text.js"></script>
    <script src="assets/js/quote-card.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
             const cards = [
                {
                    id: '1',
                    title: 'فضل تلاوة القرآن',
                    description: 'قال رسول الله ﷺ: "من قرأ حرفاً من كتاب الله فله به حسنة، والحسنة بعشر أمثالها، لا أقول الم حرف، ولكن ألف حرف ولام حرف وميم حرف".',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>',
                    color: '#fff'
                },
                {
                    id: '2',
                    title: 'الحفظ والمراجعة',
                    description: 'خيركم من تعلم القرآن وعلمه. الحفظ يثبت الإيمان في القلب وينير الدرب.',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/></svg>',
                    color: '#fff'
                },
                {
                    id: '3',
                    title: 'السكينة والطمأنينة',
                    description: 'ألا بذكر الله تطمئن القلوب. تلاوة القرآن تجلب السكينة وتطرد الهموم.',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.42 4.58a5.4 5.4 0 0 0-7.65 0l-.77.78-.77-.78a5.4 5.4 0 0 0-7.65 0C1.46 6.7 1.33 10.28 4 13l8 8 8-8c2.67-2.72 2.54-6.3.42-8.42z"/></svg>',
                    color: '#fff'
                },
                 {
                    id: '4',
                    title: 'شفاء ورحمة',
                    description: 'وننزل من القرآن ما هو شفاء ورحمة للمؤمنين.',
                    icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
                    color: '#fff'
                }
            ];
            
            new QuoteStack('quote-app', cards);
        });
    </script>
</body>
</html>

