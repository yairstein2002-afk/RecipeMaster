<?php
session_start();
require_once 'db.php';

// --- 1. שכבת אבטחה: אימות מנהל בלבד ---
if (($_SESSION['role'] ?? '') !== 'admin') {
    die("<div style='color:white; background:#0f172a; height:100vh; display:flex; align-items:center; justify-content:center; font-family:sans-serif;'><h2>⛔ גישה חסומה למנהלים בלבד</h2></div>");
}

// --- 2. לוגיקת פעולות מנהל (Actions) ---
if (isset($_GET['action'])) {
    $commentId = isset($_GET['c_id']) ? (int)$_GET['c_id'] : null;
    $badUserId = isset($_GET['u_id']) ? (int)$_GET['u_id'] : null;
    $notificationId = isset($_GET['n_id']) ? (int)$_GET['n_id'] : null;
    $action = $_GET['action'];
    $statusMsg = "success";

    try {
        $pdo->beginTransaction();

        if ($action === 'delete_comment' && $commentId) {
            $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$commentId]);
            $pdo->prepare("DELETE FROM notifications WHERE comment_id = ?")->execute([$commentId]);
            $statusMsg = "comment_deleted";
        } 
        elseif ($action === 'ban_user' && $badUserId) {
            // --- הגנה: בדיקה שהיעד אינו מנהל ---
            $stmt_check_admin = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt_check_admin->execute([$badUserId]);
            $targetRole = $stmt_check_admin->fetchColumn();

            if ($targetRole === 'admin') {
                $pdo->rollBack();
                header("Location: admin_reports.php?error=cannot_ban_admin");
                exit;
            }

            $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$badUserId]);
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE comment_id IN (SELECT id FROM comments WHERE user_id = ?)")->execute([$badUserId]);
            $statusMsg = "user_banned";
        } 
        elseif ($action === 'dismiss' && $notificationId) {
            $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$notificationId]);
            $statusMsg = "report_dismissed";
        }

        $pdo->commit();
        header("Location: admin_reports.php?status=" . $statusMsg);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה בביצוע הפעולה: " . htmlspecialchars($e->getMessage()));
    }
}

// --- 3. שליפת דיווחים ממתינים ---
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
    <link rel="stylesheet" href="style.css">
    <style>
        /* הוספת אנימציה להודעות הקופצות */
        .alert {
            animation: slideDown 0.5s ease forwards;
        }
        @keyframes slideDown {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
    </style>
</head>
<body>
<div class="container">
    <header style="margin-bottom: 30px;">
        <a href="index.php" style="color: var(--text-dim); text-decoration: none;">← חזרה לדף הבית</a>
        <h1 style="color: var(--accent); margin-top: 20px;">🚩 דיווחים ממתינים לטיפול</h1>
    </header>

    <?php if (isset($_GET['error']) && $_GET['error'] === 'cannot_ban_admin'): ?>
        <div class="alert alert-danger" style="background: rgba(255, 71, 87, 0.2); border: 1px solid var(--danger); color: var(--danger); padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            ❌ פעולה נדחתה: אין אפשרות לחסום משתמש בדרגת מנהל.
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-success" style="background: rgba(46, 204, 113, 0.2); border: 1px solid var(--success); color: var(--success); padding: 15px; border-radius: 12px; margin-bottom: 20px;">
            <?php 
                $msgs = [
                    'comment_deleted' => 'התגובה נמחקה בהצלחה! 🗑️',
                    'user_banned' => 'המשתמש נחסם והדיווחים נסגרו! 🚫',
                    'report_dismissed' => 'הדיווח בוטל בהצלחה. ✔️',
                    'success' => 'הפעולה בוצעה בהצלחה! ✅'
                ];
                echo $msgs[$_GET['status']] ?? $msgs['success'];
            ?>
        </div>
    <?php endif; ?>

    <?php if (empty($reports)): ?>
        <div class="card" style="text-align: center; opacity: 0.6; padding: 50px;">
            <p style="font-size: 1.2rem;">אין דיווחים חדשים כרגע. הקהילה נקייה! ✨</p>
        </div>
    <?php else: ?>
        <?php foreach ($reports as $r): ?>
            <div class="report-card" style="border-right: 5px solid var(--danger);">
                <div class="card-header" style="display:flex; justify-content: space-between; font-size: 0.85rem; color: var(--text-dim); margin-bottom: 15px;">
                    <span>👤 מדווח: <b><?php echo htmlspecialchars($r['reporter_name']); ?></b></span>
                    <span>🕒 <?php echo date('H:i | d/m/Y', strtotime($r['report_time'])); ?></span>
                </div>

                <div class="info-box" style="background: rgba(255, 71, 87, 0.1); padding: 15px; border-radius: 12px; border: 1px solid var(--danger); margin-bottom: 15px;">
                    <strong style="color: var(--danger);">⚠️ סיבת הדיווח:</strong><br>
                    <?php echo nl2br(htmlspecialchars($r['report_reason'])); ?>
                </div>

                <div class="info-box" style="background: rgba(255, 255, 255, 0.05); padding: 15px; border-radius: 12px; border: 1px solid var(--glass-border); margin-bottom: 15px;">
                    <strong style="color: var(--accent);">💬 התגובה של <?php echo htmlspecialchars($r['bad_user_name']); ?>:</strong><br>
                    <p style="font-style: italic; margin-top: 5px;">"<?php echo nl2br(htmlspecialchars($r['original_comment'])); ?>"</p>
                </div>

                <p style="font-size: 0.9rem; margin-bottom: 20px;">
                    📍 במתכון: <a href="view_recipe.php?id=<?php echo $r['recipe_id']; ?>" target="_blank" style="color: var(--accent);"><?php echo htmlspecialchars($r['recipe_title']); ?></a>
                </p>

                <div class="actions" style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="?action=delete_comment&c_id=<?php echo $r['comment_id']; ?>" class="btn btn-danger" onclick="return confirm('למחוק את התגובה לצמיתות?')">מחק תגובה 🗑️</a>
                    <a href="?action=ban_user&u_id=<?php echo $r['bad_user_id']; ?>" class="btn btn-warning" onclick="return confirm('האם לחסום את המשתמש?')">חסום משתמש 🚫</a>
                    <a href="?action=dismiss&n_id=<?php echo $r['n_id']; ?>" class="btn btn-submit" style="background: var(--glass); color: white;">התעלם ✔️</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
    // העלמת הודעות הצלחה אחרי 5 שניות
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.transition = "opacity 0.5s ease";
            alert.style.opacity = "0";
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);
</script>

</body>
</html>
