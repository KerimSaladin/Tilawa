<?php
require_once 'includes/config.php';

// Get all resources ordered by creation date
$stmt = $pdo->query("SELECT * FROM resources ORDER BY created_at DESC");
$resources = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الموارد التعليمية - رتل معي</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        .resources-page {
            background: var(--cream-bg);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .resources-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        
        .resources-header h1 {
            color: var(--dark-blue);
            font-size: 2.5rem;
            margin-bottom: 1rem;
            font-family: 'Amiri', serif;
        }
        
        .resources-header p {
            color: #666;
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .resources-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 2rem;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
        }
        
        .resource-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            direction: rtl;
        }
        
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .resource-type-header {
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            position: relative;
        }
        
        .resource-type-header.video {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .resource-type-header.pdf {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .resource-type-header.link {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .resource-content {
            padding: 1.5rem;
        }
        
        .resource-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--dark-blue);
            margin-bottom: 0.75rem;
            font-family: 'Amiri', serif;
        }
        
        .resource-description {
            color: #666;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            min-height: 3rem;
        }
        
        .resource-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: #999;
        }
        
        .resource-type-badge {
            background: var(--muted-blue);
            color: white;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .resource-action {
            width: 100%;
            padding: 0.8rem;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .resource-action.primary {
            background: linear-gradient(135deg, var(--dark-blue), var(--muted-blue));
            color: white;
        }
        
        .resource-action.primary:hover {
            background: linear-gradient(135deg, var(--muted-blue), var(--dark-blue));
            transform: translateY(-2px);
        }
        
        .resource-action.secondary {
            background: #f8f9fa;
            color: var(--dark-blue);
            border: 2px solid var(--dark-blue);
        }
        
        .resource-action.secondary:hover {
            background: var(--dark-blue);
            color: white;
        }
        
        .no-resources {
            text-align: center;
            padding: 4rem 2rem;
            color: #666;
        }
        
        .no-resources-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .back-to-home {
            position: absolute;
            top: 2rem;
            right: 2rem;
            background: white;
            color: var(--dark-blue);
            padding: 0.8rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }
        
        .back-to-home:hover {
            background: var(--dark-blue);
            color: white;
            transform: translateY(-2px);
        }
        
        @media (max-width: 768px) {
            .resources-grid {
                grid-template-columns: 1fr;
                padding: 0 1rem;
            }
            
            .resources-header h1 {
                font-size: 2rem;
            }
            
            .back-to-home {
                position: relative;
                top: auto;
                right: auto;
                display: block;
                width: fit-content;
                margin: 0 auto 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="resources-page">
        <a href="index.php" class="back-to-home">🏠 العودة للرئيسية</a>
        
        <div class="resources-header">
            <h1>📚 الموارد التعليمية</h1>
            <p>مجموعة مختارة من الموارد التعليمية المجانية لمساعدتك في رحلة تعلم القرآن الكريم</p>
        </div>
        
        <?php if (count($resources) > 0): ?>
            <div class="resources-grid">
                <?php foreach ($resources as $resource): ?>
                    <div class="resource-card">
                        <div class="resource-type-header <?php echo $resource['type']; ?>">
                            <?php 
                            $icons = ['video' => '🎥', 'pdf' => '📄', 'link' => '🔗'];
                            echo $icons[$resource['type']] ?? '📄';
                            ?>
                        </div>
                        <div class="resource-content">
                            <h3 class="resource-title"><?php echo htmlspecialchars($resource['title']); ?></h3>
                            <p class="resource-description">
                                <?php echo htmlspecialchars($resource['description'] ?? 'لا يوجد وصف متاح'); ?>
                            </p>
                            <div class="resource-meta">
                                <span class="resource-type-badge">
                                    <?php 
                                    $types = ['video' => 'فيديو', 'pdf' => 'ملف PDF', 'link' => 'رابط'];
                                    echo $types[$resource['type']] ?? $resource['type'];
                                    ?>
                                </span>
                                <span><?php echo date('Y/m/d', strtotime($resource['created_at'])); ?></span>
                            </div>
                            
                            <?php if ($resource['type'] === 'link' && $resource['url']): ?>
                                <a href="<?php echo htmlspecialchars($resource['url']); ?>" 
                                   target="_blank" 
                                   class="resource-action primary">
                                    🔗 زيارة الرابط
                                </a>
                            <?php elseif ($resource['file_path']): ?>
                                <a href="serve_resource.php?id=<?php echo $resource['id']; ?>" 
                                   target="_blank" 
                                   class="resource-action primary">
                                    <?php 
                                    if ($resource['type'] === 'video') {
                                        echo '▶️ مشاهدة الفيديو';
                                    } else {
                                        echo '📄 تحميل الملف';
                                    }
                                    ?>
                                </a>
                            <?php else: ?>
                                <button class="resource-action secondary" disabled>
                                    غير متاح حالياً
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-resources">
                <div class="no-resources-icon">📚</div>
                <h2>لا توجد موارد حالياً</h2>
                <p>سيتم إضافة موارد تعليمية قريباً. تفضل بزيارتنا لاحقاً.</p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
