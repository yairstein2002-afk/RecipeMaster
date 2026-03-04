<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'guest';

// --- 1. לוגיקת פעולות (מחיקה גורפת / ניהול אדמין) ---
if (isset($_GET['action'])) {
    
    // כפתור נקה הכל - מוחק את כל ההתראות של המשתמש מהמסד
    if ($_GET['action'] === 'clear_all') {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);
        header("Location: notifications.php?status=cleared"); exit;
    }

    // פעולות מנהל (מחיקת תגובה / חסימה)
    if ($userRole === 'admin' && isset($_GET['target_id'])) {
        $targetId = (int)$_GET['target_id'];
        if ($_GET['action'] === 'delete_comment') {
            $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$targetId]);
            $pdo->prepare("DELETE FROM notifications WHERE comment_id = ?")->execute([$targetId]);
        } elseif ($_GET['action'] === 'ban') {
            $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            if ($stmt->fetchColumn() !== 'admin') {
                $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$targetId]);
                $pdo->prepare("DELETE FROM notifications WHERE comment_id IN (SELECT id FROM comments WHERE user_id = ?)")->execute([$targetId]);
            }
        }
        header("Location: notifications.php?status=done"); exit;
    }
}

// --- 2. שאילתה חכמה (הכי חדש למעלה + סינון תוכן שנמחק) ---
$sql = "
    SELECT 
        n.id, n.recipe_id, n.created_at, n.actor_id, n.report_reason, n.comment_id,
        u.username as actor_name,
        r.title as recipe_title,
        c.comment_text,
        c.parent_id,
        p.comment_text as parent_comment_text,
        u_author.role as author_role,
        u_author.id as author_id
    FROM notifications n
    JOIN users u ON n.actor_id = u.id
    LEFT JOIN recipes r ON n.recipe_id = r.id
    LEFT JOIN comments c ON n.comment_id = c.id
    LEFT JOIN comments p ON c.parent_id = p.id
    LEFT JOIN users u_author ON c.user_id = u_author.id
    WHERE n.user_id = ? 
    AND (n.recipe_id = 0 OR r.id IS NOT NULL) -- הסתרת התראות על מתכונים שנמחקו
    AND (n.report_reason IS NULL OR c.id IS NOT NULL) -- הסתרת דיווחים על תגובות שנמחקו
    ORDER BY n.created_at DESC -- הכי חדש למעלה
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// סימון הכל כנקרא ברגע הכניסה
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>מרכז התראות | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); --danger: #ff4757; --warning: #f1c40f; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; line-height: 1.5; }
        .container { max-width: 700px; margin: 0 auto; }
        
        /* עיצוב ה-Header עם הכפתורים שביקשת */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }
        .nav-actions { display: flex; gap: 12px; align-items: center; }
        
        .btn-main { background: var(--accent); color: var(--bg); padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; transition: 0.3s; font-size: 0.9rem; }
        .btn-main:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 242, 254, 0.3); }
        
        .btn-clear { background: rgba(255, 71, 87, 0.1); color: var(--danger); border: 1px solid var(--danger); padding: 8px 16px; border-radius: 50px; text-decoration: none; font-size: 0.85rem; transition: 0.2s; }
        .btn-clear:hover { background: var(--danger); color: white; }

        /* כרטיסיות */
        .notif-card { background: var(--glass); padding: 20px; border-radius: 15px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.05); }
        .status-report { border-right: 5px solid var(--danger); background: rgba(255, 71, 87, 0.08); }
        
        .comment-box { background: rgba(0, 0, 0, 0.3); border-right: 3px solid var(--accent); padding: 12px; border-radius: 8px; margin-top: 10px; color: #cbd5e1; font-style: italic; font-size: 0.95rem; }
        .context-box { background: rgba(255,255,255,0.03); border-right: 2px solid #64748b; padding: 10px; font-size: 0.85rem; color: #94a3b8; margin: 10px 0; border-radius: 5px; }

        .admin-actions { margin-top: 15px; display: flex; gap: 10px; }
        .btn-admin { padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; text-decoration: none; font-weight: bold; border: none; cursor: pointer; }
        .btn-del { background: var(--danger); color: white; }
        .btn-ban { background: var(--warning); color: black; }

        .actor-link { color: var(--accent); text-decoration: none; font-weight: bold; }
        .time { font-size: 0.75rem; opacity: 0.5; margin-top: 10px; display: block; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>התראות 🔔</h1>
        <div class="nav-actions">
            <?php if(!empty($notifications)): ?>
                <a href="?action=clear_all" class="btn-clear" onclick="return confirm('האם אתה בטוח שברצונך למחוק את כל ההתראות?')">🧹 נקה הכל</a>
            <?php endif; ?>
            <a href="index.php" class="btn-main">🏠 חזור לדף הראשי</a>
        </div>
    </div>
    
    <?php if (empty($notifications)): ?>
        <div style="text-align: center; padding: 100px 0; opacity: 0.5;">
            <div style="font-size: 4rem; margin-bottom: 20px;">✨</div>
            <h2>אין לך התראות חדשות</h2>
            <p>כאן יופיעו תגובות, לייקים ודיווחים.</p>
        </div>
    <?php endif; ?>

    <?php foreach ($notifications as $n): 
        $isReport = !empty($n['report_reason']);
        $isReply = ($n['parent_id'] > 0);
        $isAuthorAdmin = ($n['author_role'] === 'admin');
    ?>
        <div class="notif-card <?php echo $isReport ? 'status-report' : ''; ?>">
            
            <?php if ($isReport): ?>
                <small style="color: var(--danger); font-weight: bold;">🚩 דיווח על תוכן</small><br>
                <a href="profile.php?id=<?php echo $n['actor_id']; ?>" class="actor-link"><?php echo htmlspecialchars($n['actor_name']); ?></a>
                דיווח על תגובה ב: <strong><?php echo htmlspecialchars($n['recipe_title']); ?></strong>
                
                <div style="background: rgba(255, 71, 87, 0.1); padding: 10px; border-radius: 8px; margin: 10px 0; border: 1px dashed var(--danger);">
                    <b>סיבת המדווח:</b> <?php echo htmlspecialchars($n['report_reason']); ?>
                </div>
                <div class="comment-box">"<?php echo htmlspecialchars($n['comment_text']); ?>"</div>

                <?php if ($userRole === 'admin'): ?>
                    <div class="admin-actions">
                        <a href="?action=delete_comment&target_id=<?php echo $n['comment_id']; ?>" class="btn-admin btn-del">מחק תגובה 🗑️</a>
                        <?php if (!$isAuthorAdmin): ?>
                            <a href="?action=ban&target_id=<?php echo $n['author_id']; ?>" class="btn-admin btn-ban" onclick="return confirm('לחסום את המשתמש?')">חסום משתמש 🚫</a>
                        <?php else: ?>
                            <span style="color: var(--warning); font-size: 0.8rem; font-weight: bold;">🛡️ מנהל (מוגן)</span>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div>
                    <a href="profile.php?id=<?php echo $n['actor_id']; ?>" class="actor-link"><?php echo htmlspecialchars($n['actor_name']); ?></a>
                    <span> <?php echo $isReply ? 'השיב/ה לתגובה שלך ב:' : 'הגיב/ה למתכון שלך:'; ?> </span>
                    <strong><?php echo htmlspecialchars($n['recipe_title']); ?></strong>

                    <?php if ($isReply && $n['parent_comment_text']): ?>
                        <div class="context-box">
                            <small>על מה שכתבת:</small><br>
                            "<?php echo htmlspecialchars(mb_strimwidth($n['parent_comment_text'], 0, 80, "...")); ?>"
                        </div>
                    <?php endif; ?>

                    <div class="comment-box"><?php echo htmlspecialchars($n['comment_text']); ?></div>
                </div>
            <?php endif; ?>

            <span class="time"><?php echo date('H:i | d/m/Y', strtotime($n['created_at'])); ?></span>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>
