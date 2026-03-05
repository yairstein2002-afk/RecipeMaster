<?php
session_start();
require_once 'db.php';

if (($_SESSION['role'] ?? '') !== 'admin') {
    die("<div style='color:white; background:#0f172a; height:100vh; display:flex; align-items:center; justify-content:center; font-family:sans-serif;'><h2>⛔ גישה חסומה למנהלים בלבד</h2></div>");
}

if (isset($_GET['action'])) {
    $commentId = isset($_GET['c_id']) ? (int)$_GET['c_id'] : null;
    $badUserId = isset($_GET['u_id']) ? (int)$_GET['u_id'] : null;
    $notificationId = isset($_GET['n_id']) ? (int)$_GET['n_id'] : null;
    $action = $_GET['action'];

    if ($action === 'delete_comment' && $commentId) {
        $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$commentId]);
        $pdo->prepare("DELETE FROM notifications WHERE comment_id = ?")->execute([$commentId]);
    } elseif ($action === 'ban_user' && $badUserId) {
        $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$badUserId]);
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE comment_id IN (SELECT id FROM comments WHERE user_id = ?)")->execute([$badUserId]);
    } elseif ($action === 'dismiss' && $notificationId) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notificationId]);
    }
    header("Location: admin_reports.php?status=success");
    exit;
}

// שליפת דיווחים עם סיבה ותוכן תגובה
$sql = "SELECT n.id as n_id, n.report_reason, n.created_at as report_time,
               u_reporter.username as reporter_name, 
               u_bad.username as bad_user_name, u_bad.id as bad_user_id,
               c.id as comment_id, c.comment_text as original_comment, 
               r.id as recipe_id, r.title as recipe_title
        FROM notifications n
        JOIN users u_reporter ON n.actor_id = u_reporter.id
        JOIN comments c ON n.comment_id = c.id
        JOIN users u_bad ON c.user_id = u_bad.id
        JOIN recipes r ON n.recipe_id = r.id
        WHERE n.report_reason IS NOT NULL AND n.is_read = 0
        ORDER BY n.created_at DESC";

$reports = $pdo->query($sql)->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול דיווחים | RecipeMaster Admin</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --danger: #ff4757; --warning: #f1c40f; --glass: rgba(255, 255, 255, 0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 40px 20px; }
        .container { max-width: 900px; margin: 0 auto; }
        .report-card { background: var(--glass); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 25px; margin-bottom: 25px; border-right: 5px solid var(--danger); }
        .info-box { background: rgba(0,0,0,0.25); padding: 15px; border-radius: 12px; margin: 15px 0; }
        .btn { padding: 10px 20px; border-radius: 50px; border: none; font-weight: bold; cursor: pointer; text-decoration: none; font-size: 0.9rem; margin-left: 10px; display: inline-block; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-ban { background: var(--warning); color: black; }
        .btn-dismiss { background: rgba(255,255,255,0.1); color: white; }
    </style>
</head>
<body>
<div class="container">
    <h1>🚩 דיווחים ממתינים לטיפול</h1>
    <?php if (empty($reports)): ?>
        <p>אין דיווחים חדשים כרגע.</p>
    <?php else: ?>
        <?php foreach ($reports as $r): ?>
            <div class="report-card">
                <div style="display:flex; justify-content: space-between; font-size: 0.85rem; color: #94a3b8;">
                    <span>מדווח ע"י: <b><?php echo htmlspecialchars($r['reporter_name']); ?></b></span>
                    <span>🕒 <?php echo date('H:i | d/m/Y', strtotime($r['report_time'])); ?></span>
                </div>

                <div class="info-box" style="border: 1px solid rgba(255, 71, 87, 0.3);">
                    <strong style="color: var(--danger);">⚠️ סיבת הדיווח שהתקבלה:</strong><br>
                    <?php echo nl2br(htmlspecialchars($r['report_reason'])); ?>
                </div>

                <div class="info-box" style="border: 1px solid rgba(0, 242, 254, 0.3);">
                    <strong style="color: var(--accent);">💬 התגובה המקורית של <?php echo htmlspecialchars($r['bad_user_name']); ?>:</strong><br>
                    "<?php echo nl2br(htmlspecialchars($r['original_comment'])); ?>"
                </div>

                <p style="font-size: 0.85rem;">במתכון: <a href="view_recipe.php?id=<?php echo $r['recipe_id']; ?>" target="_blank" style="color: var(--accent);"><?php echo htmlspecialchars($r['recipe_title']); ?></a></p>

                <div class="actions">
                    <a href="?action=delete_comment&c_id=<?php echo $r['comment_id']; ?>" class="btn btn-delete" onclick="return confirm('למחוק את התגובה?')">מחק תגובה 🗑️</a>
                    <a href="?action=ban_user&u_id=<?php echo $r['bad_user_id']; ?>" class="btn btn-ban" onclick="return confirm('לחסום את המשתמש?')">חסום משתמש 🚫</a>
                    <a href="?action=dismiss&n_id=<?php echo $r['n_id']; ?>" class="btn btn-dismiss">התעלם ✔️</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
</body>
</html>