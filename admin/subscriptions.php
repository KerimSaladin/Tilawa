<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/payment.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'admin') redirect('../login.php');

// ── POST handlers ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // A. إنشاء باقة جديدة
    if ($action === 'create') {
        $name_ar     = trim($_POST['name_ar']     ?? '');
        $price       = (float)($_POST['price']    ?? 0);
        $description = trim($_POST['description'] ?? '');
        $features    = trim($_POST['features']    ?? '');
        $is_lifetime = !empty($_POST['is_lifetime']);
        $duration    = $is_lifetime ? 0 : max(1, (int)($_POST['duration_days'] ?? 30));

        if ($name_ar && $price > 0) {
            try {
                $pdo->prepare("
                    INSERT INTO subscriptions (name_ar, name, price, duration_days, description, features)
                    VALUES (?, ?, ?, ?, ?, ?)
                ")->execute([$name_ar, $name_ar, $price, $duration, $description ?: null, $features ?: null]);
                $_SESSION['flash_success'] = 'تمت إضافة الباقة بنجاح.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'خطأ أثناء الحفظ: ' . $e->getMessage();
            }
        } else {
            $_SESSION['flash_error'] = 'يرجى ملء الاسم والسعر.';
        }

    // B. تعديل باقة قائمة
    } elseif ($action === 'edit') {
        $id          = (int)($_POST['pkg_id']     ?? 0);
        $name_ar     = trim($_POST['name_ar']     ?? '');
        $price       = (float)($_POST['price']    ?? 0);
        $description = trim($_POST['description'] ?? '');
        $features    = trim($_POST['features']    ?? '');
        $is_lifetime = !empty($_POST['is_lifetime']);
        $duration    = $is_lifetime ? 0 : max(1, (int)($_POST['duration_days'] ?? 30));

        if ($id && $name_ar && $price > 0) {
            try {
                $pdo->prepare("
                    UPDATE subscriptions
                    SET name_ar=?, name=?, price=?, duration_days=?, description=?, features=?
                    WHERE id=?
                ")->execute([$name_ar, $name_ar, $price, $duration, $description ?: null, $features ?: null, $id]);
                $_SESSION['flash_success'] = 'تم تحديث الباقة بنجاح.';
            } catch (Throwable $e) {
                $_SESSION['flash_error'] = 'خطأ أثناء التحديث: ' . $e->getMessage();
            }
        }

    // C. حذف باقة
    } elseif ($action === 'delete') {
        $id = (int)($_POST['pkg_id'] ?? 0);
        if ($id) {
            $pdo->prepare("DELETE FROM subscriptions WHERE id=?")->execute([$id]);
            $_SESSION['flash_success'] = 'تم حذف الباقة.';
        }

    // D. إلغاء اشتراك مستخدم
    } elseif ($action === 'revoke') {
        $sub_id = (int)($_POST['sub_id'] ?? 0);
        if ($sub_id) {
            $pdo->prepare("UPDATE user_subscriptions SET status='cancelled' WHERE id=?")->execute([$sub_id]);
            $_SESSION['flash_success'] = 'تم إلغاء الاشتراك.';
        }

    // E. تحديث إعدادات المنصة (عمولة، حدود الشحن/السحب)
    } elseif ($action === 'update_settings') {
        $settings = [
            'platform_fee_percent' => max(0, min(100, (float)($_POST['platform_fee_percent'] ?? 15))),
            'min_topup'            => max(1,   (int)($_POST['min_topup']   ?? 100)),
            'max_topup'            => max(100, (int)($_POST['max_topup']   ?? 100000)),
            'min_payout'           => max(1,   (int)($_POST['min_payout']  ?? 500)),
        ];
        try {
            $st = $pdo->prepare("
                INSERT INTO platform_settings (key, value, updated_at)
                VALUES (?, ?, NOW())
                ON CONFLICT (key) DO UPDATE SET value=EXCLUDED.value, updated_at=NOW()
            ");
            foreach ($settings as $k => $v) {
                $st->execute([$k, (string)$v]);
            }
            $_SESSION['flash_success'] = 'تم تحديث إعدادات المنصة بنجاح.';
        } catch (Throwable $e) {
            $_SESSION['flash_error'] = 'خطأ: ' . $e->getMessage();
        }
    }

    redirect('subscriptions.php');
}

// ── Fetch data ────────────────────────────────────────────────────────────────
try {
    $packages = $pdo->query("SELECT * FROM subscriptions ORDER BY price ASC")->fetchAll();
} catch (Throwable $e) {
    $packages = [];
}

try {
    $active_subs = $pdo->query("
        SELECT us.*, u.full_name, u.email, s.name_ar, s.duration_days
        FROM user_subscriptions us
        JOIN users u ON us.user_id = u.id
        JOIN subscriptions s ON us.subscription_id = s.id
        WHERE us.status = 'active'
          AND (s.duration_days = 0 OR us.end_date >= CURRENT_DATE)
        ORDER BY us.created_at DESC
    ")->fetchAll();
} catch (Throwable $e) {
    $active_subs = [];
}

// Current platform settings
$fee_pct    = (float) get_platform_setting($pdo, 'platform_fee_percent', 15);
$min_topup  = (int)   get_platform_setting($pdo, 'min_topup',  100);
$max_topup  = (int)   get_platform_setting($pdo, 'max_topup',  100000);
$min_payout = (int)   get_platform_setting($pdo, 'min_payout', 500);

$active_page = 'subscriptions';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>الاشتراكات — رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
        .section-card h3{font-weight:700;margin-bottom:1.25rem;border-bottom:1px solid var(--sand);padding-bottom:.75rem;display:flex;align-items:center;gap:.5rem}
        .pkg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:1rem;margin-bottom:1.5rem}
        .pkg-card{background:var(--warm-cream);border:1px solid var(--sand);border-radius:var(--radius-lg);padding:1.25rem;position:relative;transition:box-shadow .2s}
        .pkg-card:hover{box-shadow:var(--shadow-md)}
        .pkg-price{font-size:1.8rem;font-weight:800;color:var(--royal-blue);margin:.25rem 0}
        .pkg-name{font-weight:700;font-size:1rem;color:var(--charcoal)}
        .pkg-period{font-size:.82rem;color:var(--slate);margin-bottom:.5rem}
        .pkg-desc{font-size:.85rem;color:var(--charcoal);margin:.5rem 0;line-height:1.5}
        .pkg-features{font-size:.78rem;color:var(--slate);margin-top:.4rem;border-top:1px solid var(--sand);padding-top:.5rem}
        .subs-table{width:100%;border-collapse:collapse}
        .subs-table th{background:var(--warm-cream);padding:.7rem 1rem;text-align:right;font-size:.82rem;color:var(--slate);font-weight:600}
        .subs-table td{padding:.75rem 1rem;border-bottom:1px solid var(--sand);font-size:.88rem;vertical-align:middle}
        .btn-sm{padding:.35rem .85rem;font-size:.8rem;border-radius:var(--radius-md)}
        .settings-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:1rem}
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center}
        .modal-box{background:#fff;border-radius:20px;padding:2rem;max-width:500px;width:90%;box-shadow:0 25px 80px rgba(0,0,0,.2);max-height:90vh;overflow-y:auto}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        @media(max-width:600px){.form-row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include '../includes/header.php'; ?>
<div style="padding:2rem">

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success" style="margin-bottom:1rem"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['flash_error'])): ?>
<div class="alert alert-error" style="margin-bottom:1rem"><?php echo htmlspecialchars($_SESSION['flash_error']); unset($_SESSION['flash_error']); ?></div>
<?php endif; ?>

<h2 style="margin-bottom:1.5rem">⚙️ إدارة الاشتراكات والإعدادات</h2>

<!-- ═══ A. إعدادات المنصة (عمولة، حدود) ═══ -->
<div class="section-card">
    <h3>🏦 إعدادات المنصة</h3>
    <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        <div class="settings-grid">
            <div class="form-group" style="margin:0">
                <label class="form-label">نسبة عمولة المنصة (%)</label>
                <input type="number" name="platform_fee_percent" class="form-input"
                       min="0" max="100" step="0.5"
                       value="<?php echo $fee_pct; ?>" required>
                <small style="color:#94a3b8">تُطبَّق على كل دفعة بين طالب ومعلم</small>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">الحد الأدنى لشحن المحفظة (دج)</label>
                <input type="number" name="min_topup" class="form-input"
                       min="1" value="<?php echo $min_topup; ?>" required>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">الحد الأقصى لشحن المحفظة (دج)</label>
                <input type="number" name="max_topup" class="form-input"
                       min="100" value="<?php echo $max_topup; ?>" required>
            </div>
            <div class="form-group" style="margin:0">
                <label class="form-label">الحد الأدنى لسحب الأرباح (دج)</label>
                <input type="number" name="min_payout" class="form-input"
                       min="1" value="<?php echo $min_payout; ?>" required>
            </div>
        </div>
        <button type="submit" class="btn btn-primary" style="margin-top:1.25rem">حفظ الإعدادات</button>
    </form>
</div>

<!-- ═══ B. باقات الاشتراك ═══ -->
<div class="section-card">
    <h3>📦 باقات الاشتراك (<?php echo count($packages); ?>)</h3>
    <?php if ($packages): ?>
    <div class="pkg-grid">
        <?php foreach ($packages as $p): ?>
        <div class="pkg-card">
            <div class="pkg-name">
                <?php echo htmlspecialchars($p['name_ar']); ?>
                <?php if ((int)$p['duration_days'] === 0): ?>
                <span style="background:#fef3c7;color:#92400e;font-size:.72rem;padding:.2rem .5rem;border-radius:50px;margin-right:.4rem">♾️ مدى الحياة</span>
                <?php endif; ?>
            </div>
            <div class="pkg-price"><?php echo number_format($p['price'],0,'.',','); ?> دج</div>
            <div class="pkg-period"><?php echo (int)$p['duration_days'] === 0 ? 'مدى الحياة' : $p['duration_days'] . ' يوم'; ?></div>
            <?php if (!empty($p['description'])): ?>
            <div class="pkg-desc"><?php echo nl2br(htmlspecialchars($p['description'])); ?></div>
            <?php endif; ?>
            <?php if (!empty($p['features'])): ?>
            <div class="pkg-features">✔ <?php echo htmlspecialchars($p['features']); ?></div>
            <?php endif; ?>
            <div style="display:flex;gap:.5rem;margin-top:.85rem">
                <button class="btn btn-outline btn-sm" style="flex:1"
                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($p)); ?>)">تعديل</button>
                <form method="POST" style="flex:1" onsubmit="return confirm('هل تريد حذف هذه الباقة؟')">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="pkg_id" value="<?php echo $p['id']; ?>">
                    <button class="btn btn-outline btn-sm" type="submit"
                            style="border-color:#dc2626;color:#dc2626;width:100%">حذف</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p style="color:var(--slate);font-size:.9rem;margin-bottom:1.25rem">لا توجد باقات بعد. أضف أول باقة أدناه.</p>
    <?php endif; ?>

    <!-- إضافة باقة جديدة -->
    <div style="background:var(--warm-cream);border-radius:var(--radius-lg);padding:1.25rem">
        <h4 style="margin-bottom:1rem;font-weight:700">+ إضافة باقة جديدة</h4>
        <form method="POST">
            <input type="hidden" name="action" value="create">
            <div class="form-row" style="margin-bottom:1rem">
                <div class="form-group" style="margin:0">
                    <label class="form-label">اسم الباقة (عربي) <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name_ar" class="form-input" placeholder="مثال: شهري" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">السعر (دج) <span style="color:#dc2626">*</span></label>
                    <input type="number" name="price" class="form-input" min="1" step="1" placeholder="0" required>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label class="form-label">الوصف (يظهر للمستخدمين)</label>
                <textarea name="description" class="form-input" rows="2"
                          placeholder="مثال: احصل على خصم 15% على جميع الجلسات واستمتع بالوصول الكامل..."></textarea>
            </div>
            <div class="form-row" style="margin-bottom:1rem">
                <div class="form-group" style="margin:0">
                    <label class="form-label">المدة (يوم)</label>
                    <input type="number" name="duration_days" id="new_duration" class="form-input" value="30" min="1">
                </div>
                <div class="form-group" style="margin:0;display:flex;align-items:flex-end">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:600">
                        <input type="checkbox" id="new_lifetime" name="is_lifetime" value="1"
                               onchange="document.getElementById('new_duration').disabled=this.checked"
                               style="width:18px;height:18px;accent-color:#40916C">
                        ♾️ مدى الحياة
                    </label>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label class="form-label">المميزات (مفصولة بفاصلة)</label>
                <input type="text" name="features" class="form-input" placeholder="مثال: وصول كامل, خصم 15%, دعم أولوية">
            </div>
            <button type="submit" class="btn btn-primary">إضافة الباقة</button>
        </form>
    </div>
</div>

<!-- ═══ C. الاشتراكات النشطة ═══ -->
<div class="section-card">
    <h3>✅ الاشتراكات النشطة (<?php echo count($active_subs); ?>)</h3>
    <?php if ($active_subs): ?>
    <div style="overflow-x:auto">
    <table class="subs-table">
        <thead>
            <tr><th>المستخدم</th><th>الباقة</th><th>البداية</th><th>الانتهاء</th><th>إجراء</th></tr>
        </thead>
        <tbody>
            <?php foreach ($active_subs as $s): ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?php echo htmlspecialchars($s['full_name']); ?></div>
                    <div style="font-size:.8rem;color:var(--slate)"><?php echo htmlspecialchars($s['email']); ?></div>
                </td>
                <td><?php echo htmlspecialchars($s['name_ar']); ?></td>
                <td><?php echo date('d/m/Y', strtotime($s['start_date'])); ?></td>
                <td><?php echo (int)$s['duration_days'] === 0
                        ? '<span style="color:#065f46;font-weight:700">♾️ مدى الحياة</span>'
                        : date('d/m/Y', strtotime($s['end_date'])); ?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('هل تريد إلغاء هذا الاشتراك؟')">
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="sub_id" value="<?php echo $s['id']; ?>">
                        <button class="btn btn-outline btn-sm" type="submit"
                                style="border-color:#dc2626;color:#dc2626">إلغاء</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:2rem;color:var(--slate)">لا توجد اشتراكات نشطة حالياً.</div>
    <?php endif; ?>
</div>

</div><!-- /padding:2rem -->
</div><!-- /dashboard-container -->

<!-- ═══ مودال تعديل الباقة ═══ -->
<div id="editModal" class="modal-overlay">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem">
            <h3 style="margin:0">✏️ تعديل الباقة</h3>
            <button onclick="closeEditModal()" style="background:none;border:none;font-size:1.5rem;cursor:pointer;color:#94a3b8">✕</button>
        </div>
        <form method="POST" id="editForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="pkg_id" id="edit_id">
            <div class="form-row" style="margin-bottom:1rem">
                <div class="form-group" style="margin:0">
                    <label class="form-label">اسم الباقة <span style="color:#dc2626">*</span></label>
                    <input type="text" name="name_ar" id="edit_name" class="form-input" required>
                </div>
                <div class="form-group" style="margin:0">
                    <label class="form-label">السعر (دج) <span style="color:#dc2626">*</span></label>
                    <input type="number" name="price" id="edit_price" class="form-input" min="1" required>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label class="form-label">الوصف</label>
                <textarea name="description" id="edit_description" class="form-input" rows="2"></textarea>
            </div>
            <div class="form-row" style="margin-bottom:1rem">
                <div class="form-group" style="margin:0">
                    <label class="form-label">المدة (يوم)</label>
                    <input type="number" name="duration_days" id="edit_duration" class="form-input" min="1">
                </div>
                <div class="form-group" style="margin:0;display:flex;align-items:flex-end">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-weight:600">
                        <input type="checkbox" id="edit_lifetime" name="is_lifetime" value="1"
                               onchange="document.getElementById('edit_duration').disabled=this.checked"
                               style="width:18px;height:18px;accent-color:#40916C">
                        ♾️ مدى الحياة
                    </label>
                </div>
            </div>
            <div class="form-group" style="margin-bottom:1rem">
                <label class="form-label">المميزات</label>
                <input type="text" name="features" id="edit_features" class="form-input">
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end">
                <button type="button" class="btn btn-outline" onclick="closeEditModal()">إلغاء</button>
                <button type="submit" class="btn btn-primary">حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<script src="../assets/js/dashboard.js"></script>
<script>
function openEditModal(pkg) {
    document.getElementById('edit_id').value          = pkg.id;
    document.getElementById('edit_name').value        = pkg.name_ar    || '';
    document.getElementById('edit_price').value       = pkg.price      || '';
    document.getElementById('edit_description').value = pkg.description || '';
    document.getElementById('edit_features').value    = pkg.features   || '';
    const isLifetime = parseInt(pkg.duration_days) === 0;
    document.getElementById('edit_lifetime').checked  = isLifetime;
    document.getElementById('edit_duration').value    = isLifetime ? 30 : (pkg.duration_days || 30);
    document.getElementById('edit_duration').disabled = isLifetime;
    document.getElementById('editModal').style.display = 'flex';
}
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}
document.getElementById('editModal').addEventListener('click', e => {
    if (e.target === document.getElementById('editModal')) closeEditModal();
});
</script>
</body>
</html>
