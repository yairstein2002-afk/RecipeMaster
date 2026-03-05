<?php
session_start();
require_once 'db.php';

// 1. אבטחה: מוודאים שרק אדמין מחובר יכול לגשת לדף הזה
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { 
    die("גישה נדחתה. דף זה מיועד למנהלים בלבד."); 
}

// 2. לוגיקת אישור מתכון
if (isset($_GET['approve_id'])) {
    $id = $_GET['approve_id'];
    // עדכון הסטטוס ל-1 (מאושר)
    $pdo->prepare("UPDATE recipes SET is_approved = 1 WHERE id = ?")->execute([$id]);
    header("Location: admin_approval.php?msg=approved"); 
    exit;
}

// 3. לוגיקת דחיית מתכון
if (isset($_GET['reject_id'])) {
    $id = $_GET['reject_id'];
    // דחייה הופכת את המתכון לפרטי (is_public = 0) ומאשרת אותו טכנית כדי שייעלם מהרשימה לבדיקה
    $pdo->prepare("UPDATE recipes SET is_public = 0, is_approved = 1 WHERE id = ?")->execute([$id]);
    header("Location: admin_approval.php?msg=rejected"); 
    exit;
}

// 4. שליפת המתכונים שמחכים לאישור
// השאילתה הזו מסונכרנת עם הבועה בדף הבית - היא מציגה הכל איפה ש is_approved = 0
$pending = $pdo->query("SELECT r.*, u.username, c.name as cat_name FROM recipes r 
                        JOIN users u ON r.user_id = u.id 
                        JOIN categories c ON r.category_id = c.id 
                        WHERE r.is_approved = 0 
                        ORDER BY r.id DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול אישורים | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 40px 20px; margin: 0; }
        
        .container { max-width: 900px; margin: 0 auto; }
        
        h1 { color: var(--accent); margin-bottom: 30px; }
        
        .approval-card { 
            background: rgba(255,255,255,0.03); padding: 20px; border-radius: 20px; 
            border: 1px solid rgba(255,255,255,0.1); margin-bottom: 15px;
            display: flex; justify-content: space-between; align-items: center;
            transition: 0.3s;
        }
        .approval-card:hover { background: rgba(255,255,255,0.05); }

        .recipe-info { display: flex; align-items: center; gap: 20px; }
        .recipe-thumb { width: 70px; height: 70px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(255,255,255,0.1); }
        
        .actions { display: flex; gap: 10px; }
        
        .btn { padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 0.9rem; transition: 0.3s; text-align: center; }
        .btn-approve { background: #00b894; color: white; }
        .btn-reject { background: rgba(255,118,117,0.15); color: #ff7675; border: 1px solid #ff7675; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        
        .back-link { color: #94a3b8; text-decoration: none; margin-bottom: 20px; display: inline-block; }
        .empty-state { text-align: center; padding: 50px; opacity: 0.5; font-size: 1.2rem; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-link">← חזרה לדף הבית</a>
    <h1>📋 אישור מתכונים חדשים</h1>

    <?php if (empty($pending)): ?>
        <div class="empty-state">
            <p>אין מתכונים שמחכים לאישור. הכל מעודכן! ✨</p>
        </div>
    <?php else: ?>
        <?php foreach ($pending as $p): ?>
            <div class="approval-card">
                <div class="recipe-info">
                    <img src="<?php echo htmlspecialchars($p['image_url'] ?: 'default.jpg'); ?>" class="recipe-thumb">
                    <div>
                        <strong style="font-size: 1.2rem; display: block;"><?php echo htmlspecialchars($p['title']); ?></strong>
                        <span style="font-size: 0.85rem; opacity: 0.7;">
                            מאת: <b><?php echo htmlspecialchars($p['username']); ?></b> | 
                            קטגוריה: <b><?php echo htmlspecialchars($p['cat_name']); ?></b>
                        </span>
                    </div>
                </div>

                <div class="actions">
                    <a href="view_recipe.php?id=<?php echo $p['id']; ?>" target="_blank" class="btn" style="color: var(--accent);">צפייה 👁️</a>
                    <a href="?reject_id=<?php echo $p['id']; ?>" class="btn btn-reject" onclick="return confirm('האם אתה בטוח שברצונך לדחות את המתכון? הוא יועבר למצב פרטי.')">דחה ❌</a>
                    <a href="?approve_id=<?php echo $p['id']; ?>" class="btn btn-approve">אשר פרסום ✅</a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

</body>
</html>
