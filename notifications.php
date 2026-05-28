<?php
// הפעלת סשן כדי לגשת לנתוני המשתמש המחובר
session_start();
// חיבור למסד הנתונים
require_once 'db.php';

// בדיקה אם המשתמש מחובר. אם לא - הפניה לדף התחברות
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

// שמירת נתוני המשתמש מהסשן למשתנים נוחים
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'guest';

// --- 1. לוגיקת פעולות ניהול והתראות ---
if (isset($_GET['action'])) {
    
    // פעולה למשתמש רגיל: מחיקת כל ההתראות שלו (ניקוי הלוח)
    if ($_GET['action'] === 'clear_all') {
        $pdo->prepare("DELETE FROM notifications WHERE user_id = ?")->execute([$userId]);
        header("Location: notifications.php?status=cleared"); 
        exit;
    }

    // פעולות המיועדות למנהלים בלבד
    if ($userRole === 'admin' && isset($_GET['target_id'])) {
        $targetId = (int)$_GET['target_id'];

        // אפשרות א': מחיקת תגובה שדווחה
        if ($_GET['action'] === 'delete_comment') {
            // מוחק את ההתראה/דיווח עצמו מהלוח של כל המנהלים
            $pdo->prepare("DELETE FROM notifications WHERE comment_id = ?")->execute([$targetId]);
            // מוחק את כל התגובות שהיו תשובה לתגובה הזו (שורשרת)
            $pdo->prepare("DELETE FROM comments WHERE parent_id = ?")->execute([$targetId]);
            // מוחק את התגובה הבעייתית עצמה מהמתכון
            $pdo->prepare("DELETE FROM comments WHERE id = ?")->execute([$targetId]);
            
        } 
        // אפשרות ב': חסימת המשתמש שכתב את התגובה
        elseif ($_GET['action'] === 'ban') {
            // שליפת המידע על המשתמש המיועד לחסימה
            $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
            $stmt->execute([$targetId]);
            $target = $stmt->fetch();
            
            // הגנה: מוודא שלא חוסמים מנהל אחר ושלא חוסמים את עצמנו בטעות
            if ($target && $target['role'] !== 'admin' && $target['id'] !== $userId) {
                // עדכון סטטוס המשתמש לחסום
                $pdo->prepare("UPDATE users SET status = 'banned' WHERE id = ?")->execute([$targetId]);
                // ניקוי כל ההתראות שהמשתמש הזה יצר אי פעם (ניקוי ה"רעש" שלו)
                $pdo->prepare("DELETE FROM notifications WHERE actor_id = ?")->execute([$targetId]);
            } else {
                // אם המנהל ניסה לחסום אדמין - נחזיר שגיאה
                header("Location: notifications.php?error=forbidden"); 
                exit;
            }
        }
        // סיום פעולת מנהל והפניה חזרה עם הודעת הצלחה
        header("Location: notifications.php?status=success"); 
        exit;
    }
}

// --- 2. שאילתה לשליפת התראות (מחיבור של 5 טבלאות) ---
// --- שאילתה מעודכנת הכוללת זיהוי לייקים ---
$sql = "
    SELECT 
        n.id, n.recipe_id, n.created_at, n.actor_id, n.report_reason, n.comment_id, n.is_read,
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
    AND (n.recipe_id = 0 OR r.id IS NOT NULL OR n.comment_id = 0)
    ORDER BY n.created_at DESC 
    LIMIT 50
";

// הרצת השאילתה ושמירת התוצאות במערך $notifications
$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

// סימון אוטומטי של כל ההתראות כ"נקראו" ברגע שנכנסים לדף
$pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")->execute([$userId]);
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>מרכז התראות | RecipeMaster</title>
    <style>
        /* הוסף את זה בתוך ה-style הקיים שלך */
    .status-like { border-right: 5px solid #ff4757; background: rgba(255, 71, 87, 0.03); }
        /* הגדרת משתני עיצוב וצבעים */
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); --danger: #ff4757; --success: #2ecc71; }
        
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; line-height: 1.5; margin: 0; }
        .container { max-width: 700px; margin: 40px auto; }
        
        /* כותרת ופעולות עליונות */
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }
        .btn-main { background: var(--accent); color: var(--bg); padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; }
        .btn-clear { color: #94a3b8; text-decoration: none; font-size: 0.85rem; border: 1px solid rgba(255,255,255,0.1); padding: 8px 15px; border-radius: 50px; }

        /* עיצוב כרטיס התראה */
        .notif-card { background: var(--glass); padding: 20px; border-radius: 15px; margin-bottom: 15px; border: 1px solid rgba(255,255,255,0.05); position: relative; }
        .status-report { border-right: 5px solid var(--danger); background: rgba(255, 71, 87, 0.05); }
        .status-system { border-right: 5px solid var(--success); background: rgba(46, 204, 113, 0.05); }
        
        /* תיבות טקסט בתוך ההתראה */
        .comment-box { background: rgba(0, 0, 0, 0.2); border-right: 3px solid var(--accent); padding: 10px; border-radius: 8px; margin-top: 10px; font-style: italic; }
        .context-box { background: rgba(255,255,255,0.03); border-right: 2px solid #64748b; padding: 8px; font-size: 0.85rem; color: #94a3b8; margin: 10px 0; }

        /* כפתורי ניהול למנהל בלבד */
        .admin-actions { margin-top: 15px; display: flex; gap: 10px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; }
        .btn-admin { padding: 6px 12px; border-radius: 6px; font-size: 0.8rem; text-decoration: none; font-weight: bold; border: 1px solid transparent; }
        .btn-del { border-color: var(--danger); color: var(--danger); }
        .btn-ban { border-color: #f1c40f; color: #f1c40f; }
        
        .time { font-size: 0.75rem; opacity: 0.4; margin-top: 10px; display: block; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>התראות 🔔</h1>
        <div class="nav-actions">
            <?php if(!empty($notifications)): ?>
                <a href="?action=clear_all" class="btn-clear" onclick="return confirm('למחוק הכל?')">🧹 נקה הכל</a>
            <?php endif; ?>
            <a href="index.php" class="btn-main">🏠 בית</a>
        </div>
    </div>
    
    <?php if (empty($notifications)): ?>
        <div style="text-align: center; padding: 80px; opacity: 0.4;"><h2>הכל שקט כאן...</h2></div>
    <?php endif; ?>

   <?php foreach ($notifications as $n): 
    // זיהוי סוג ההתראה
    $isReport = !empty($n['report_reason']);
    $isReply = ($n['parent_id'] > 0);
    $isSystem = ($n['recipe_id'] == 0 && $n['comment_id'] == 0 && !$isReport);
    
    // זיהוי לייק: אם יש מתכון אבל אין תגובה ואין דיווח
    $isLike = ($n['recipe_id'] > 0 && empty($n['comment_id']) && !$isReport);
?>
    <div class="notif-card <?php 
        echo $isReport ? 'status-report' : 
            ($isSystem ? 'status-system' : 
            ($isLike ? 'status-like' : '')); 
    ?>">
        
        <?php if ($isSystem): ?>
            <strong style="color: var(--success);">🎉 הפרופיל שלך אושר!</strong><br>
            <span style="font-size: 0.9rem;">עכשיו אתה יכול לשתף מתכונים וליהנות מכל אפשרויות האתר.</span>

        <?php elseif ($isReport): ?>
            <small style="color: var(--danger);">🚩 דיווח על תוכן</small><br>
            <strong><?php echo htmlspecialchars($n['actor_name']); ?></strong> דיווח על תגובה במתכון <strong><?php echo htmlspecialchars($n['recipe_title']); ?></strong>
            <div class="context-box"><b>סיבת הדיווח:</b> <?php echo htmlspecialchars($n['report_reason']); ?></div>
            <div class="comment-box">"<?php echo htmlspecialchars($n['comment_text']); ?>"</div>

            <?php if ($userRole === 'admin'): ?>
                <div class="admin-actions">
                    <a href="?action=delete_comment&target_id=<?php echo $n['comment_id']; ?>" class="btn-admin btn-del" onclick="return confirm('למחוק?')">🗑️ מחק תגובה</a>
                    <?php if ($n['author_role'] !== 'admin'): ?>
                        <a href="?action=ban&target_id=<?php echo $n['author_id']; ?>" class="btn-admin btn-ban" onclick="return confirm('לחסום?')">🚫 חסום</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

        <?php elseif ($isLike): ?>
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 1.4rem;">❤️</span>
                <div>
                    <strong><?php echo htmlspecialchars($n['actor_name']); ?></strong>
                    <span>אהב/ה את המתכון שלך: </span>
                    <strong style="color: var(--accent);"><?php echo htmlspecialchars($n['recipe_title']); ?></strong>
                </div>
            </div>

        <?php else: ?>
            <strong><?php echo htmlspecialchars($n['actor_name']); ?></strong>
            <span> <?php echo $isReply ? 'השיב/ה לתגובה שלך ב:' : 'הגיב/ה למתכון שלך:'; ?> </span>
            <strong><?php echo htmlspecialchars($n['recipe_title']); ?></strong>

            <?php if ($isReply && $n['parent_comment_text']): ?>
                <div class="context-box">"<?php echo htmlspecialchars(mb_strimwidth($n['parent_comment_text'], 0, 60, "...")); ?>"</div>
            <?php endif; ?>

            <div class="comment-box"><?php echo htmlspecialchars($n['comment_text']); ?></div>
        <?php endif; ?>

        <span class="time"><?php echo date('H:i | d/m/Y', strtotime($n['created_at'])); ?></span>
    </div>
<?php endforeach; ?>
</div>

</body>
</html>