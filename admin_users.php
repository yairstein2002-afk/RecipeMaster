<?php
session_start();
require_once 'db.php';

// אבטחה: רק מנהל מחובר יכול לגשת לדף זה
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("שגיאה: אין לך הרשאות לגשת לדף זה.");
}

$currentAdminId = $_SESSION['user_id'];

// פונקציית עזר לבדיקה אם המטרה היא מנהל
function isTargetAdmin($pdo, $id) {
    $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetchColumn() === 'admin';
}

// --- לוגיקת פעולות ניהול ---

// 1. אישור משתמש
if (isset($_GET['approve_user'])) {
    $userIdToApprove = (int)$_GET['approve_user'];
    $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([$userIdToApprove]);
    if ($userIdToApprove == $_SESSION['user_id']) { $_SESSION['status'] = 'approved'; }
    header("Location: admin_users.php?status=user_approved"); exit;
}

// 2. חסימת משתמש (Ban)
if (isset($_GET['ban_user'])) {
    $targetId = (int)$_GET['ban_user'];
    if (!isTargetAdmin($pdo, $targetId)) {
        $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$targetId]);
        header("Location: admin_users.php?status=user_banned"); exit;
    } else {
        die("שגיאה: לא ניתן לחסום מנהל.");
    }
}

// 3. ביטול חסימה (Unban)
if (isset($_GET['unban_user'])) {
    $pdo->prepare("UPDATE users SET status = 'approved' WHERE id = ?")->execute([(int)$_GET['unban_user']]);
    header("Location: admin_users.php?status=user_unbanned"); exit;
}

// 4. שינוי תפקיד
if (isset($_GET['toggle_role'])) {
    $targetId = (int)$_GET['toggle_role'];
    if ($targetId != $currentAdminId && !isTargetAdmin($pdo, $targetId)) { 
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
        header("Location: admin_users.php?status=role_updated"); exit;
    }
}

// 5. מחיקת כל התוכן של משתמש
if (isset($_GET['clear_content'])) {
    $targetId = (int)$_GET['clear_content'];
    if (!isTargetAdmin($pdo, $targetId)) {
        $pdo->prepare("DELETE FROM ingredients WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM instructions WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM comments WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM likes WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM recipe_views WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM recipes WHERE user_id = ?")->execute([$targetId]);
        header("Location: admin_users.php?status=content_cleared"); exit;
    }
}

// --- שליפת נתונים ---
$pendingUsers = $pdo->query("SELECT * FROM users WHERE status = 'pending' AND role != 'admin' ORDER BY id DESC")->fetchAll();
$users = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM recipes WHERE user_id = u.id) as recipe_count, (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count FROM users u WHERE u.status = 'approved' OR u.role = 'admin' ORDER BY u.role DESC, u.id DESC")->fetchAll();
$bannedUsers = $pdo->query("SELECT * FROM users WHERE status = 'banned' ORDER BY id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול מערכת | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card: rgba(255,255,255,0.05); --danger: #ff4757; --success: #2ecc71; --warning: #ffa502; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 40px 20px; margin: 0; }
        .container { max-width: 1100px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px; }
        
        /* עיצוב שורת החיפוש */
        .search-container { margin-bottom: 25px; position: relative; }
        .admin-search { 
            width: 100%; padding: 15px 45px 15px 15px; border-radius: 15px; 
            background: var(--card); border: 1px solid rgba(255,255,255,0.1); 
            color: white; font-size: 1rem; outline: none; transition: 0.3s;
        }
        .admin-search:focus { border-color: var(--accent); box-shadow: 0 0 15px rgba(0, 242, 254, 0.2); }
        .search-icon { position: absolute; right: 15px; top: 50%; transform: translateY(-50%); opacity: 0.5; }

        .section-title { margin: 30px 0 15px; font-size: 1.4rem; display: flex; align-items: center; gap: 10px; }
        table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 40px; }
        th, td { padding: 15px 20px; text-align: right; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { background: rgba(0, 242, 254, 0.1); color: var(--accent); }
        
        .btn { padding: 6px 12px; border-radius: 8px; text-decoration: none; font-size: 0.8rem; font-weight: bold; transition: 0.3s; cursor: pointer; display: inline-block; border: none; }
        .btn-approve { background: var(--success); color: white; }
        .btn-ban { background: var(--danger); color: white; }
        .btn-role { background: rgba(0, 242, 254, 0.1); color: var(--accent); border: 1px solid var(--accent); }
        .status-msg { background: var(--success); color: white; padding: 10px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
        
        .no-results { display: none; text-align: center; padding: 20px; opacity: 0.6; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>מרכז בקרה וניהול 🛡️</h1>
        <a href="index.php" style="color: var(--accent); text-decoration: none;">🏠 חזרה לבית</a>
    </div>

    <div class="search-container">
        <span class="search-icon">🔍</span>
        <input type="text" id="userSearch" class="admin-search" placeholder="חפש משתמש לפי שם..." onkeyup="filterUsers()">
    </div>

    <?php if(isset($_GET['status'])): ?>
        <div class="status-msg">בוצע בהצלחה! המערכת עודכנה. ✅</div>
    <?php endif; ?>

    <?php if(!empty($pendingUsers)): ?>
        <h2 class="section-title" style="color: var(--warning);">⏳ ממתינים לאישור</h2>
        <table>
            <tbody>
                <?php foreach($pendingUsers as $pu): ?>
                <tr>
                    <td width="40%"><b><?php echo htmlspecialchars($pu['username']); ?></b></td>
                    <td><?php echo htmlspecialchars($pu['email']); ?></td>
                    <td>
                        <a href="?approve_user=<?php echo $pu['id']; ?>" class="btn btn-approve">אשר ✅</a>
                        <a href="?ban_user=<?php echo $pu['id']; ?>" class="btn btn-ban">חסום 🚫</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 class="section-title">👥 ניהול משתמשים ותפקידים</h2>
    <table id="usersTable">
        <thead>
            <tr>
                <th>משתמש</th>
                <th>סטטיסטיקה</th>
                <th>תפקיד</th>
                <th>ניהול וגישה</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
            <tr class="user-row">
                <td class="username-cell"><b><?php echo htmlspecialchars($u['username']); ?></b></td>
                <td>🍳 <?php echo $u['recipe_count']; ?> | 💬 <?php echo $u['comment_count']; ?></td>
                <td>
                    <span style="color: <?php echo ($u['role'] === 'admin') ? 'var(--accent)' : 'white'; ?>;">
                        <?php echo ($u['role'] === 'admin') ? '👑 מנהל' : '👤 משתמש'; ?>
                    </span>
                </td>
                <td>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <a href="?toggle_role=<?php echo $u['id']; ?>" class="btn btn-role" onclick="return confirm('למנות למנהל?')">👑 מנה למנהל</a>
                        <a href="?clear_content=<?php echo $u['id']; ?>" class="btn" style="color: var(--warning);" onclick="return confirm('למחוק את כל התוכן של המשתמש?')">נקה תוכן</a>
                        <a href="?ban_user=<?php echo $u['id']; ?>" class="btn btn-ban">חסום</a>
                    <?php elseif ($u['id'] == $currentAdminId): ?>
                        <small style="opacity:0.5;">(אתה המנהל)</small>
                    <?php else: ?>
                        <small style="opacity:0.5; color: var(--accent);">🛡️ מנהל מוגן</small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <div id="noResults" class="no-results">לא נמצאו משתמשים התואמים לחיפוש.</div>

    <?php if(!empty($bannedUsers)): ?>
        <h2 class="section-title" style="color: var(--danger);">🚫 משתמשים חסומים</h2>
        <table>
            <tbody>
                <?php foreach($bannedUsers as $bu): ?>
                <tr>
                    <td width="80%"><?php echo htmlspecialchars($bu['username']); ?></td>
                    <td><a href="?unban_user=<?php echo $bu['id']; ?>" class="btn btn-approve">שחרר חסימה 🔓</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
/**
 * פונקציה לסינון משתמשים בזמן אמת בתוך הטבלה
 */
function filterUsers() {
    const input = document.getElementById('userSearch');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    const noResults = document.getElementById('noResults');
    let hasVisibleRows = false;

    rows.forEach(row => {
        const username = row.querySelector('.username-cell').textContent.toLowerCase();
        if (username.includes(filter)) {
            row.style.display = "";
            hasVisibleRows = true;
        } else {
            row.style.display = "none";
        }
    });

    noResults.style.display = hasVisibleRows ? "none" : "block";
}
</script>

</body>
</html>