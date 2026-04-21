<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/payment.php';

require_login();
$user = get_logged_in_user($pdo);
if (!$user || $user['user_type'] !== 'admin') redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action  = $_POST['action'] ?? '';
    $uid     = (int)($_POST['user_id'] ?? 0);
    if ($uid && $uid !== $user['id']) {
        if ($action === 'toggle_active') {
            $pdo->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=?")->execute([$uid]);
            $_SESSION['flash_success'] = 'تم تحديث حالة المستخدم.';
        } elseif ($action === 'toggle_star') {
            $pdo->prepare("UPDATE users SET has_star = 1 - has_star WHERE id=?")->execute([$uid]);
            $_SESSION['flash_success'] = 'تم تحديث نجمة المستخدم.';
        } elseif ($action === 'add_wallet') {
            $amount = (float)($_POST['amount'] ?? 0);
            if ($amount > 0) {
                $pdo->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?")->execute([$amount, $uid]);
                $pdo->prepare("INSERT INTO transactions (from_user_id,type,amount,description,status) VALUES (?,'top_up',?,?,'completed')")->execute([$uid, $amount, 'إضافة رصيد من الأدمن']);
                $_SESSION['flash_success'] = 'تمت إضافة ' . number_format($amount,0,'.',',') . ' دج للمستخدم.';
            }
        }
    }
    redirect('users.php');
}

$search = $_GET['q'] ?? '';
$type   = $_GET['type'] ?? 'all';

$params = [];
$where  = "1=1";
if ($type !== 'all') { $where .= " AND user_type=?"; $params[] = $type; }
if ($search) { $where .= " AND (full_name LIKE ? OR email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$stmt = $pdo->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$users = $stmt->fetchAll();

$active_page = 'users';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
    <title>إدارة المستخدمين — رتل معي</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <style>
        .users-table{width:100%;border-collapse:collapse}
        .users-table th{background:var(--warm-cream);padding:.75rem 1rem;text-align:right;font-size:.85rem;color:var(--slate);font-weight:600}
        .users-table td{padding:.85rem 1rem;border-bottom:1px solid var(--sand);vertical-align:middle;font-size:.9rem}
        .users-table tr:hover td{background:var(--warm-cream)}
        .badge{padding:.2rem .7rem;border-radius:50px;font-size:.78rem;font-weight:700}
        .badge-student{background:#dbeafe;color:#1d4ed8}
        .badge-teacher{background:#d1fae5;color:#065f46}
        .badge-admin{background:#fef3c7;color:#92400e}
        .badge-active{background:#d1fae5;color:#065f46}
        .badge-inactive{background:#fee2e2;color:#991b1b}
        .badge-star{background:#fef3c7;color:#92400e}
        .action-btns{display:flex;gap:.4rem;flex-wrap:wrap}
        .btn-sm{padding:.35rem .85rem;font-size:.8rem;border-radius:var(--radius-md)}
        .search-bar{display:flex;gap:.75rem;margin-bottom:1.5rem;flex-wrap:wrap}
        .search-bar input{flex:1;min-width:200px}
        .search-bar select{min-width:140px}
        .section-card{background:var(--glass-white);border:1px solid var(--glass-border);border-radius:var(--radius-xl);padding:1.75rem;margin-bottom:1.5rem;box-shadow:var(--shadow-md)}
    </style>
</head>
<body>
<div class="dashboard-container" style="padding-top:0">
<?php include '../includes/header.php'; ?>
<div style="padding:2rem">

<?php if (isset($_SESSION['flash_success'])): ?>
<div class="alert alert-success" style="margin-bottom:1rem"><?php echo htmlspecialchars($_SESSION['flash_success']); unset($_SESSION['flash_success']); ?></div>
<?php endif; ?>

<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem">
    <h2 style="margin:0">إدارة المستخدمين (<?php echo count($users); ?>)</h2>
</div>

<form method="GET" class="search-bar">
    <input type="text" name="q" class="form-input" placeholder="بحث بالاسم أو البريد..." value="<?php echo htmlspecialchars($search); ?>">
    <select name="type" class="form-select">
        <option value="all" <?php echo $type==='all'?'selected':''; ?>>جميع الأنواع</option>
        <option value="student" <?php echo $type==='student'?'selected':''; ?>>الطلاب</option>
        <option value="teacher" <?php echo $type==='teacher'?'selected':''; ?>>المعلمون</option>
        <option value="admin" <?php echo $type==='admin'?'selected':''; ?>>المدراء</option>
    </select>
    <button type="submit" class="btn btn-primary">بحث</button>
    <a href="users.php" class="btn btn-outline">إعادة تعيين</a>
</form>

<div class="section-card">
    <div style="overflow-x:auto">
    <table class="users-table">
        <thead>
            <tr>
                <th>المستخدم</th>
                <th>النوع</th>
                <th>الحالة</th>
                <th>الرصيد</th>
                <th>تاريخ التسجيل</th>
                <th>نجمة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
                <td>
                    <div style="font-weight:700"><?php echo htmlspecialchars($u['full_name']); ?></div>
                    <div style="font-size:.8rem;color:var(--slate)"><?php echo htmlspecialchars($u['email']); ?></div>
                    <?php if (!empty($u['phone'])): ?><div style="font-size:.78rem;color:var(--slate)"><?php echo htmlspecialchars($u['phone']); ?></div><?php endif; ?>
                </td>
                <td><span class="badge badge-<?php echo $u['user_type']; ?>"><?php echo $u['user_type']==='student'?'طالب':($u['user_type']==='teacher'?'معلم':'أدمن'); ?></span></td>
                <td><span class="badge <?php echo $u['is_active']?'badge-active':'badge-inactive'; ?>"><?php echo $u['is_active']?'نشط':'معطل'; ?></span></td>
                <td><?php echo number_format($u['wallet_balance']??0,0,'.',','); ?> دج</td>
                <td style="white-space:nowrap"><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                <td><?php echo !empty($u['has_star'])?'⭐':'—'; ?></td>
                <td>
                    <div class="action-btns">
                        <?php if ($u['id'] !== $user['id']): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <input type="hidden" name="action" value="toggle_active">
                            <button class="btn btn-outline btn-sm" type="submit"><?php echo $u['is_active']?'تعطيل':'تفعيل'; ?></button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                            <input type="hidden" name="action" value="toggle_star">
                            <button class="btn btn-gold btn-sm" type="submit" style="font-size:.8rem;padding:.35rem .85rem"><?php echo !empty($u['has_star'])?'إزالة ⭐':'منح ⭐'; ?></button>
                        </form>
                        <button onclick="showAddWallet(<?php echo $u['id']; ?>,'<?php echo addslashes($u['full_name']); ?>')" class="btn btn-primary btn-sm">+ رصيد</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>
</div>
</div>

<!-- مودال إضافة رصيد -->
<div style="position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(4px);z-index:9999;display:none;align-items:center;justify-content:center" id="walletModal">
    <div style="background:white;border-radius:var(--radius-xl);padding:2rem;max-width:400px;width:90%;box-shadow:var(--shadow-xl)">
        <h3 style="margin-bottom:1.25rem">إضافة رصيد للمستخدم</h3>
        <p id="wm_name" style="color:var(--slate);margin-bottom:1rem"></p>
        <form method="POST">
            <input type="hidden" name="action" value="add_wallet">
            <input type="hidden" name="user_id" id="wm_uid" value="">
            <div class="form-group">
                <label class="form-label">المبلغ (دج)</label>
                <input type="number" name="amount" class="form-input" min="1" step="100" placeholder="0" required>
            </div>
            <div style="display:flex;gap:.75rem">
                <button type="submit" class="btn btn-primary" style="flex:1">إضافة</button>
                <button type="button" onclick="document.getElementById('walletModal').style.display='none'" class="btn btn-outline" style="flex:1">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddWallet(uid, name) {
    document.getElementById('wm_uid').value = uid;
    document.getElementById('wm_name').textContent = 'المستخدم: ' + name;
    document.getElementById('walletModal').style.display = 'flex';
}
document.getElementById('walletModal').addEventListener('click', function(e) {
    if (e.target === this) this.style.display = 'none';
});
</script>
<script src="../assets/js/dashboard.js"></script>
</body>
</html>
