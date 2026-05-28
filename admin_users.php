<?php
session_start();
require_once 'db.php';

// אבטחה: רק מנהל מחובר יכול לגשת
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die("שגיאה: אין לך הרשאות לגשת לדף זה.");
}

$currentAdminId = $_SESSION['user_id'];

// --- לוגיקת פעולות (נשארת זהה למה שעבד לך) ---
//  מאשר משתמש אחרי שיוני שביצע
if (isset($_GET['approve_user'])) {
    $id = (int)$_GET['approve_user'];
    $pdo->prepare("UPDATE users SET status = 'approved', is_blocked = 0 WHERE id = ?")->execute([$id]);
    header("Location: admin_users.php?status=success"); exit;
}
//חוסם משתמש
if (isset($_GET['ban_user'])) {
    $id = (int)$_GET['ban_user'];
    $pdo->prepare("UPDATE users SET status = 'banned', is_blocked = 1 WHERE id = ?")->execute([$id]);
    header("Location: admin_users.php?status=banned"); exit;
}
//שחרר חסימה
if (isset($_GET['unban_user'])) {
    $id = (int)$_GET['unban_user'];
    $pdo->prepare("UPDATE users SET status = 'approved', is_blocked = 0 WHERE id = ?")->execute([$id]);
    header("Location: admin_users.php?status=unbanned"); exit;
}
//מחק משתמש
if (isset($_GET['delete_user'])) {
    $id = (int)$_GET['delete_user'];
    $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'")->execute([$id]);
    header("Location: admin_users.php?status=deleted"); exit;
}

// שליפת נתונים
//שולפת משתמשים שהם ממתינים
$pending = $pdo->query("SELECT * FROM users WHERE status = 'pending' ORDER BY id DESC")->fetchAll();
//שולפת רק משתמשים שהם גם מאושרים ( וגם לא חסומים
$actives = $pdo->query("SELECT u.*, (SELECT COUNT(*) FROM recipes WHERE user_id = u.id) as r_count FROM users u WHERE status = 'approved' AND is_blocked = 0 ORDER BY role DESC, id DESC")->fetchAll();
//שולפת משתמשים שהם חסומים
$banned  = $pdo->query("SELECT * FROM users WHERE status = 'banned' OR is_blocked = 1 ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ניהול משתמשים | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card: rgba(255,255,255,0.05); --danger: #ff4757; --success: #2ed573; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; }
        
        .search-bar { width: 100%; padding: 12px 20px; border-radius: 50px; background: var(--card); border: 1px solid var(--accent); color: white; margin-bottom: 25px; outline: none; box-sizing: border-box; }

        .table-wrapper { background: var(--card); border-radius: 15px; overflow-x: auto; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: right; border-bottom: 1px solid rgba(255,255,255,0.05); }
        th { background: rgba(0, 242, 254, 0.1); color: var(--accent); white-space: nowrap; }
        
        /* יישור עמודת הפעולות */
        .actions-cell {
            display: flex;
            gap: 8px;
            justify-content: flex-start;
            align-items: center;
            min-width: 180px; /* מבטיח שהעמודה לא תתכווץ יותר מדי */
        }

        .btn { padding: 6px 12px; border-radius: 50px; text-decoration: none; font-size: 0.8rem; font-weight: bold; transition: 0.3s; border: none; cursor: pointer; white-space: nowrap; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: rgba(255, 71, 87, 0.15); color: var(--danger); border: 1px solid var(--danger); }
        .btn-danger:hover { background: var(--danger); color: white; }
        .btn-outline { border: 1px solid #555; color: #aaa; }

        .badge { padding: 3px 10px; border-radius: 10px; font-size: 0.75rem; }
        .badge-admin { background: var(--accent); color: var(--bg); font-weight: bold; }

        @media (max-width: 768px) {
            .actions-cell { flex-direction: column; align-items: stretch; min-width: auto; }
            .btn { text-align: center; }
        }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h1>ניהול קהילה 🛡️</h1>
        <a href="index.php" style="color: var(--accent); text-decoration: none;">🏠 חזרה</a>
    </div>

    <input type="text" id="search" class="search-bar" placeholder="🔍 חפש משתמש..." onkeyup="filter()">


<?php if($pending): ?>
    <h3 style="color: #ffa502;">⏳ ממתינים לאישור</h3>
    <div class="table-wrapper">
        <table style="width: 100%; border-collapse: collapse;">
            <?php foreach($pending as $u): ?>
    <?php 
        // 1. יצירת קישור למייל (Gravatar) - זה הגיבוי למקרה שאין תמונה או שהיא שבורה
        $emailHash = md5(strtolower(trim($u['email'] ?? '0')));
        $gravatarImg = "https://www.gravatar.com/avatar/$emailHash?d=mp&s=100";
        
        // 2. בדיקה בשרת: האם יש נתיב תמונה במסד והאם הקובץ באמת קיים בתיקייה?
        // אם אתה בתיקיית admin, ייתכן שצריך להוסיף "../" לפני ה-profile_img
        $displayImg = (!empty($u['profile_img']) && file_exists($u['profile_img'])) 
                      ? $u['profile_img'] 
                      : $gravatarImg;
    ?>
    <tr class="row" style="border-bottom: 1px solid rgba(255,255,255,0.05);">
        <td style="padding: 15px; width: 60px;">
            <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; border: 2px solid var(--accent); background: #1e293b;">
                <img src="<?php echo htmlspecialchars($displayImg); ?>" 
                     style="width: 100%; height: 100%; object-fit: cover;"
                     alt="פרופיל">
            </div>
        </td>

        <td style="padding: 15px;">
            <div style="font-weight: bold; font-size: 1rem;">
                <?php echo htmlspecialchars($u['username']); ?>
                <?php if($u['role'] === 'admin'): ?> <span title="מנהל">👑</span> <?php endif; ?>
            </div>
            <div style="font-size: 0.85rem; opacity: 0.6; margin-top: 4px;">
                <?php echo htmlspecialchars($u['email']); ?>
            </div>
        </td>

        <td style="padding: 15px; text-align: left;">
            <div class="actions-cell" style="display: flex; gap: 10px; justify-content: flex-end;">
                <a href="?approve_user=<?php echo $u['id']; ?>" class="btn btn-success" style="padding: 8px 16px; border-radius: 8px; text-decoration: none; background: #2ed573; color: white; font-weight: bold; font-size: 0.9rem;">אשר</a>
                <a href="?delete_user=<?php echo $u['id']; ?>" class="btn btn-outline" 
                   style="padding: 8px 16px; border-radius: 8px; text-decoration: none; border: 1px solid #ff4757; color: #ff4757; font-size: 0.9rem;"
                   onclick="return confirm('למחוק את המשתמש?')">מחק</a>
            </div>
        </td>
    </tr>
<?php endforeach; ?>
        </table>
    </div>
<?php endif; ?>


    <h3>👥 משתמשים ומנהלים</h3>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>משתמש</th>
                    <th>תפקיד</th>
                    <th>מתכונים</th>
                    <th>פעולות</th>
                </tr>
            </thead>
            <tbody id="userTable">
                <?php foreach($actives as $u): ?>
                <tr class="row">
                    <td><b><?php echo htmlspecialchars($u['username']); ?></b><br><small style="opacity:0.5;"><?php echo $u['email']; ?></small></td>
                    <td>
                        <?php if($u['role'] === 'admin'): ?>
                            <span class="badge badge-admin">מנהל</span>
                        <?php else: ?>
                            <span class="badge" style="border: 1px solid #555;">משתמש</span>
                        <?php endif; ?>
                    </td>
                    <td>🍳 <?php echo $u['r_count']; ?></td>
                    <td>
                        <div class="actions-cell">
                            <?php if($u['id'] != $currentAdminId && $u['role'] !== 'admin'): ?>
                                <a href="?ban_user=<?php echo $u['id']; ?>" class="btn btn-danger">חסום</a>
                                <a href="?delete_user=<?php echo $u['id']; ?>" class="btn btn-outline" onclick="return confirm('מחיקה לצמיתות?')">מחק</a>
                            <?php else: ?>
                                <small style="opacity:0.4;">(מוגן)</small>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if($banned): ?>
    <h3 style="color: var(--danger);">🚫 חסומים</h3>
    <div class="table-wrapper">
        <table>
            <?php foreach($banned as $u): ?>
            <tr class="row">
                <td><del><?php echo htmlspecialchars($u['username']); ?></del></td>
                <td>
                    <div class="actions-cell">
                        <a href="?unban_user=<?php echo $u['id']; ?>" class="btn btn-success">שחרר חסימה</a>
                        <a href="?delete_user=<?php echo $u['id']; ?>" class="btn btn-outline">מחק</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function filter() {
    let val = document.getElementById('search').value.toLowerCase();
    document.querySelectorAll('.row').forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(val) ? "" : "none";
    });
}
</script>

</body>
</html>