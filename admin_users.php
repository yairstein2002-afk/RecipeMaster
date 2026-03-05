<?php
session_start();
require_once 'db.php';

// אבטחה: רק מנהל מחובר יכול לגשת לדף זה
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("שגיאה: אין לך הרשאות לגשת לדף זה.");
}

$currentAdminId = $_SESSION['user_id'];

/**
 * פונקציה לשליחת התראה למשתמש על פעולת ניהול
 */
function sendAdminNotification($pdo, $userId, $actorId) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, actor_id, recipe_id, is_read) VALUES (?, ?, 0, 0)");
        $stmt->execute([$userId, $actorId]);
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
    }
}

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
    $pdo->prepare("UPDATE users SET status = 'approved', is_blocked = 0 WHERE id = ?")->execute([$userIdToApprove]);
    sendAdminNotification($pdo, $userIdToApprove, $currentAdminId);
    header("Location: admin_users.php?status=user_approved"); exit;
}

// 2. חסימת משתמש (Ban)
if (isset($_GET['ban_user'])) {
    $targetId = (int)$_GET['ban_user'];
    if (!isTargetAdmin($pdo, $targetId)) {
        $pdo->prepare("UPDATE users SET status = 'banned', is_blocked = 1 WHERE id = ?")->execute([$targetId]);
        header("Location: admin_users.php?status=user_banned"); exit;
    } else {
        die("שגיאה: לא ניתן לחסום מנהל.");
    }
}

// 3. ביטול חסימה (Unban)
if (isset($_GET['unban_user'])) {
    $targetId = (int)$_GET['unban_user'];
    $pdo->prepare("UPDATE users SET status = 'approved', is_blocked = 0 WHERE id = ?")->execute([$targetId]);
    sendAdminNotification($pdo, $targetId, $currentAdminId);
    header("Location: admin_users.php?status=user_unbanned"); exit;
}

// 4. שינוי תפקיד למנהל
if (isset($_GET['toggle_role'])) {
    $targetId = (int)$_GET['toggle_role'];
    if ($targetId != $currentAdminId && !isTargetAdmin($pdo, $targetId)) { 
        $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$targetId]);
        sendAdminNotification($pdo, $targetId, $currentAdminId);
        header("Location: admin_users.php?status=role_updated"); exit;
    }
}

// 5. מחיקת מתכונים ציבוריים בלבד
if (isset($_GET['clear_public'])) {
    $targetId = (int)$_GET['clear_public'];
    if (!isTargetAdmin($pdo, $targetId)) {
        $pdo->prepare("DELETE FROM ingredients WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ? AND is_public = 1)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM instructions WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ? AND is_public = 1)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM recipes WHERE user_id = ? AND is_public = 1")->execute([$targetId]);
        header("Location: admin_users.php?status=public_cleared"); exit;
    }
}

// 6. מחיקת כל התוכן (כולל פרטי)
if (isset($_GET['clear_all_content'])) {
    $targetId = (int)$_GET['clear_all_content'];
    if (!isTargetAdmin($pdo, $targetId)) {
        $pdo->prepare("DELETE FROM ingredients WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM instructions WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM comments WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM likes WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)")->execute([$targetId]);
        $pdo->prepare("DELETE FROM recipes WHERE user_id = ?")->execute([$targetId]);
        header("Location: admin_users.php?status=all_cleared"); exit;
    }
}

// --- שליפת נתונים ---
$pendingUsers = $pdo->query("SELECT * FROM users WHERE status = 'pending' AND role != 'admin' ORDER BY id DESC")->fetchAll();

$users = $pdo->query("SELECT u.*, 
    (SELECT COUNT(*) FROM recipes WHERE user_id = u.id AND is_approved = 1 AND is_public = 1) as public_count,
    (SELECT COUNT(*) FROM comments WHERE user_id = u.id) as comment_count 
    FROM users u WHERE (u.status = 'approved' OR u.role = 'admin') AND (u.is_blocked = 0 OR u.is_blocked IS NULL)
    ORDER BY u.role DESC, u.id DESC")->fetchAll();

$bannedUsers = $pdo->query("SELECT * FROM users WHERE status = 'banned' OR is_blocked = 1 ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול משתמשים | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card: rgba(255,255,255,0.05); --danger: #ff4757; --success: #2ecc71; --warning: #ffa502; --info: #60a5fa; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 30px; margin: 0; }
        .container { max-width: 1300px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .admin-search { width: 100%; padding: 15px; border-radius: 12px; background: var(--card); border: 1px solid rgba(255,255,255,0.1); color: white; margin-bottom: 20px; outline: none; }
        table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 15px; overflow: hidden; margin-bottom: 30px; }
        th, td { padding: 15px; text-align: right; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        th { background: rgba(0, 242, 254, 0.1); color: var(--accent); }
        .btn { padding: 8px 12px; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: bold; transition: 0.2s; display: inline-block; cursor: pointer; border: none; margin-left: 5px; }
        .btn-approve { background: var(--success); color: white; }
        .btn-ban { background: var(--danger); color: white; }
        .btn-clear-pub { background: transparent; border: 1px solid var(--info); color: var(--info); }
        .btn-clear-all { background: transparent; border: 1px solid var(--danger); color: var(--danger); }
        .status-msg { background: var(--success); color: white; padding: 12px; border-radius: 10px; margin-bottom: 20px; text-align: center; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>ניהול משתמשים 🛡️</h1>
        <a href="index.php" style="color: var(--accent); text-decoration: none;">🏠 חזרה לאתר</a>
    </div>

    <?php if(isset($_GET['status'])): ?>
        <div class="status-msg">✅ הפעולה בוצעה בהצלחה! המערכת עודכנה.</div>
    <?php endif; ?>

    <input type="text" id="userSearch" class="admin-search" placeholder="🔍 חפש לפי שם או אימייל..." onkeyup="filterUsers()">

    <?php if(!empty($pendingUsers)): ?>
        <h3 style="color: var(--warning);">⏳ ממתינים לאישור</h3>
        <table>
            <tbody>
                <?php foreach($pendingUsers as $pu): ?>
                <tr class="user-row">
                    <td class="name-cell"><b><?php echo htmlspecialchars($pu['username']); ?></b></td>
                    <td class="email-cell"><?php echo htmlspecialchars($pu['email']); ?></td>
                    <td width="20%">
                        <a href="?approve_user=<?php echo $pu['id']; ?>" class="btn btn-approve">אשר</a>
                        <a href="?ban_user=<?php echo $pu['id']; ?>" class="btn btn-ban">חסום</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h3>👥 משתמשים פעילים ומנהלים</h3>
    <table>
        <thead>
            <tr>
                <th>שם משתמש</th>
                <th>אימייל</th>
                <th>תוכן ציבורי</th>
                <th>תפקיד</th>
                <th>פעולות ניהול</th>
            </tr>
        </thead>
        <tbody id="usersTable">
            <?php foreach ($users as $u): ?>
            <tr class="user-row">
                <td class="name-cell"><b><?php echo htmlspecialchars($u['username']); ?></b></td>
                <td class="email-cell" style="opacity: 0.7;"><?php echo htmlspecialchars($u['email']); ?></td>
                <td>🍳 <?php echo $u['public_count']; ?> מתכונים | 💬 <?php echo $u['comment_count']; ?></td>
                <td>
                    <span style="color: <?php echo ($u['role'] === 'admin') ? 'var(--accent)' : 'white'; ?>;">
                        <?php echo ($u['role'] === 'admin') ? '👑 מנהל' : '👤 משתמש'; ?>
                    </span>
                </td>
                <td>
                    <?php if ($u['role'] !== 'admin'): ?>
                        <a href="?clear_public=<?php echo $u['id']; ?>" class="btn btn-clear-pub" onclick="return confirm('למחוק מתכונים ציבוריים בלבד?')">מחק ציבורי</a>
                        <a href="?clear_all_content=<?php echo $u['id']; ?>" class="btn btn-clear-all" onclick="return confirm('אזהרה: זה ימחוק את כל המתכונים כולל הפרטיים!')">מחק הכל</a>
                        <a href="?ban_user=<?php echo $u['id']; ?>" class="btn btn-ban">חסום</a>
                        <a href="?toggle_role=<?php echo $u['id']; ?>" class="btn" style="border: 1px solid var(--accent); color: var(--accent);">מנה למנהל</a>
                    <?php else: ?>
                        <small style="opacity:0.4;"><?php echo ($u['id'] == $currentAdminId) ? '(זה אתה)' : '🛡️ מנהל מוגן'; ?></small>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if(!empty($bannedUsers)): ?>
        <h3 style="color: var(--danger);">🚫 רשימת חסומים</h3>
        <table>
            <tbody>
                <?php foreach($bannedUsers as $bu): ?>
                <tr>
                    <td><b><?php echo htmlspecialchars($bu['username']); ?></b></td>
                    <td style="opacity: 0.6;"><?php echo htmlspecialchars($bu['email']); ?></td>
                    <td><a href="?unban_user=<?php echo $bu['id']; ?>" class="btn btn-approve">שחרר חסימה 🔓</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
function filterUsers() {
    const input = document.getElementById('userSearch').value.toLowerCase();
    const rows = document.querySelectorAll('.user-row');
    rows.forEach(row => {
        const name = row.querySelector('.name-cell').textContent.toLowerCase();
        const email = row.querySelector('.email-cell').textContent.toLowerCase();
        row.style.display = (name.includes(input) || email.includes(input)) ? "" : "none";
    });
}
</script>
</body>
</html>
