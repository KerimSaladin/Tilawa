<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';

require_login();
$user = get_logged_in_user($pdo);

if (!$user || $user['user_type'] !== 'admin') {
    redirect('../login.php');
}

// Handle resource operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_resource') {
        $title = sanitize_input($_POST['title'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $type = $_POST['type'] ?? '';
        $url = sanitize_input($_POST['url'] ?? '');
        
        // Validate input
        if (empty($title) || empty($type) || !in_array($type, ['video', 'pdf', 'link'])) {
            $_SESSION['error'] = 'جميع الحقول مطلوبة والنوع يجب أن يكون صحيحاً';
        } else {
            $file_path = null;
            
            // Handle file upload for video and pdf
            if ($type !== 'link' && isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                $allowed_types = [
                    'video' => ['video/mp4', 'video/webm', 'video/ogg'],
                    'pdf' => ['application/pdf']
                ];
                
                if (!in_array($_FILES['file']['type'], $allowed_types[$type])) {
                    $_SESSION['error'] = 'نوع الملف غير مدعوم';
                } elseif ($_FILES['file']['size'] > MAX_FILE_SIZE) {
                    $_SESSION['error'] = 'حجم الملف كبير جداً';
                } else {
                    $extension = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                    $filename = 'resource_' . time() . '_' . uniqid() . '.' . $extension;
                    $filepath = UPLOAD_DIR . $filename;
                    
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $filepath)) {
                        $file_path = $filename;
                    } else {
                        $_SESSION['error'] = 'فشل رفع الملف';
                    }
                }
            }
            
            // For link type, URL is required
            if ($type === 'link' && empty($url)) {
                $_SESSION['error'] = 'الرابط مطلوب لنوع الروابط';
            }
            
            if (!isset($_SESSION['error'])) {
                $stmt = $pdo->prepare("
                    INSERT INTO resources (title, description, file_path, url, type) 
                    VALUES (?, ?, ?, ?, ?)
                ");
                $success = $stmt->execute([$title, $description, $file_path, $url, $type]);
                
                if ($success) {
                    $_SESSION['success'] = 'تمت إضافة المورد بنجاح';
                } else {
                    $_SESSION['error'] = 'فشل إضافة المورد';
                }
            }
        }
    } elseif ($action === 'edit_resource') {
        $id = (int)$_POST['id'];
        $title = sanitize_input($_POST['title'] ?? '');
        $description = sanitize_input($_POST['description'] ?? '');
        $type = $_POST['type'] ?? '';
        $url = sanitize_input($_POST['url'] ?? '');
        
        if (empty($title) || empty($id)) {
            $_SESSION['error'] = 'العنوان والمعرف مطلوبان';
        } else {
            $stmt = $pdo->prepare("
                UPDATE resources 
                SET title = ?, description = ?, url = ? 
                WHERE id = ?
            ");
            $success = $stmt->execute([$title, $description, $url, $id]);
            
            if ($success) {
                $_SESSION['success'] = 'تم تحديث المورد بنجاح';
            } else {
                $_SESSION['error'] = 'فشل تحديث المورد';
            }
        }
    } elseif ($action === 'delete_resource') {
        $id = (int)$_POST['id'];
        
        if (empty($id)) {
            $_SESSION['error'] = 'معرف المورد مطلوب';
        } else {
            // Get resource info to delete file if exists
            $stmt = $pdo->prepare("SELECT file_path FROM resources WHERE id = ?");
            $stmt->execute([$id]);
            $resource = $stmt->fetch();
            
            if ($resource && $resource['file_path'] && file_exists(UPLOAD_DIR . $resource['file_path'])) {
                unlink(UPLOAD_DIR . $resource['file_path']);
            }
            
            $stmt = $pdo->prepare("DELETE FROM resources WHERE id = ?");
            $success = $stmt->execute([$id]);
            
            if ($success) {
                $_SESSION['success'] = 'تم حذف المورد بنجاح';
            } else {
                $_SESSION['error'] = 'فشل حذف المورد';
            }
        }
    }
}

// Get all resources
$stmt = $pdo->query("SELECT * FROM resources ORDER BY created_at DESC");
$resources = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة الموارد التعليمية - رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <div class="dashboard-header">
        <div class="header-left">
            <a href="index.php" class="brand">
                <img src="../assets/images/images.png" alt="رتل معي" class="brand-logo">
                <span class="brand-name">لوحة الإدارة</span>
            </a>
        </div>
        <div class="header-center">
            <nav class="header-nav">
                <a href="index.php" class="nav-link">الرئيسية</a>
                <a href="users.php" class="nav-link">المستخدمون</a>
                <a href="subscriptions.php" class="nav-link">الاشتراكات</a>
                <a href="activity_logs.php" class="nav-link">سجل الأنشطة</a>
                <a href="teacher_files.php" class="nav-link">ملفات المعلمين</a>
                <a href="resources_admin.php" class="nav-link active">الموارد التعليمية</a>
            </nav>
        </div>
        <div class="header-right">
            <a href="../includes/auth.php?logout=1" class="logout-btn" title="تسجيل الخروج" onclick="return confirm('هل أنت متأكد من تسجيل الخروج؟')">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 17l5-5-5-5"/><path d="M15 12H3"/><path d="M19 21V3"/></svg>
            </a>
        </div>
    </div>
    
    <div class="dashboard-container">
        <h1 style="margin-bottom: 2rem; color: var(--dark-blue);">إدارة الموارد التعليمية</h1>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>
        
        <!-- Add Resource Form -->
        <div class="card" style="margin-bottom: 2rem;">
            <h2 style="margin-bottom: 1rem; color: var(--dark-blue);">إضافة مورد جديد</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_resource">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">العنوان *</label>
                        <input type="text" name="title" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px;">
                    </div>
                    <div>
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">النوع *</label>
                        <select name="type" required style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px;">
                            <option value="">اختر النوع</option>
                            <option value="video">فيديو</option>
                            <option value="pdf">ملف PDF</option>
                            <option value="link">رابط خارجي</option>
                        </select>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">الوصف</label>
                    <textarea name="description" rows="3" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px;"></textarea>
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">الملف (للفيديو و PDF)</label>
                    <input type="file" name="file" accept="video/*,.pdf" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px;">
                </div>
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600;">الرابط (للروابط الخارجية)</label>
                    <input type="url" name="url" style="width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 8px;">
                </div>
                
                <button type="submit" class="btn btn-primary">إضافة مورد</button>
            </form>
        </div>
        
        <!-- Resources List -->
        <div class="card">
            <h2 style="margin-bottom: 1rem; color: var(--dark-blue);">الموارد الحالية</h2>
            <?php if (count($resources) > 0): ?>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>العنوان</th>
                            <th>النوع</th>
                            <th>الوصف</th>
                            <th>تاريخ الإضافة</th>
                            <th>الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resources as $resource): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($resource['title']); ?></td>
                                <td>
                                    <?php 
                                    $types = ['video' => 'فيديو', 'pdf' => 'PDF', 'link' => 'رابط'];
                                    echo $types[$resource['type']] ?? $resource['type'];
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars(mb_substr($resource['description'] ?? '', 0, 100)); ?></td>
                                <td><?php echo format_date($resource['created_at']); ?></td>
                                <td>
                                    <button onclick="editResource(<?php echo $resource['id']; ?>)" class="btn btn-sm" style="background: #ffc107; color: #000; margin-left: 0.5rem;">تعديل</button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('هل أنت متأكد من حذف هذا المورد؟')">
                                        <input type="hidden" name="action" value="delete_resource">
                                        <input type="hidden" name="id" value="<?php echo $resource['id']; ?>">
                                        <button type="submit" class="btn btn-sm" style="background: #dc3545; color: white;">حذف</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 2rem;">لا توجد موارد حالياً</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function editResource(id) {
            // This would open a modal or redirect to edit page
            // For now, we'll just show an alert
            alert('وظيفة التعديل ستكون متاحة قريباً');
        }
    </script>
</body>
</html>
