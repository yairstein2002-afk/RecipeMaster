<?php
session_start();
require_once 'db.php';

// אבטחה: רק אדמין נכנס
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { die("אין גישה."); }

// לוגיקת אישור/דחייה
if (isset($_GET['approve_id'])) {
    $pdo->prepare("UPDATE recipes SET is_approved = 1 WHERE id = ?")->execute([$_GET['approve_id']]);
    header("Location: admin_approval.php"); exit;
}
if (isset($_GET['reject_id'])) {
    $pdo->prepare("UPDATE recipes SET is_public = 0 WHERE id = ?")->execute([$_GET['reject_id']]);
    header("Location: admin_approval.php"); exit;
}

$pending = $pdo->query("SELECT r.*, u.username, c.name as cat_name FROM recipes r 
                        JOIN users u ON r.user_id = u.id 
                        JOIN categories c ON r.category_id = c.id 
                        WHERE r.is_public = 1 AND r.is_approved = 0")->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>מרכז בקרה | RecipeMaster</title>
    <style>
        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; padding: 30px; }
        .approval-grid { max-width: 900px; margin: 0 auto; }
        .item { 
            background: rgba(255,255,255,0.03); padding: 20px; border-radius: 20px; 
            border: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .btn { padding: 8px 18px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 0.9em; }
        .btn-ok { background: #00b894; color: white; margin-right: 10px; }
        .btn-no { background: rgba(255,118,117,0.2); color: #ff7675; border: 1px solid #ff7675; }
    </style>
</head>
<body>
    <div class="approval-grid">
        <a href="index.php" style="color: #94a3b8; text-decoration: none;">← חזרה לדף הבית</a>
        <h1 style="color: #00f2fe; margin: 20px 0;">📋 בקרת איכות מתכונים</h1>
        
        <?php if (empty($pending)): ?>
            <p style="opacity: 0.5;">אין מתכונים שמחכים לאישור. הכל נקי! ✨</p>
        <?php else: ?>
            <?php foreach ($pending as $p): ?>
                <div class="item">
                    <div>
                        <strong style="font-size: 1.2rem;"><?php echo htmlspecialchars($p['title']); ?></strong>
                        <div style="font-size: 0.85rem; opacity: 0.6;">מאת: <?php echo htmlspecialchars($p['username']); ?> | קטגוריה: <?php echo $p['cat_name']; ?></div>
                    </div>
                    <div>
                        <a href="view_recipe.php?id=<?php echo $p['id']; ?>" target="_blank" style="color: #00f2fe; margin-left: 20px;">צפייה 👁️</a>
                        <a href="?reject_id=<?php echo $p['id']; ?>" class="btn btn-no" onclick="return confirm('להחזיר לפרטי?')">דחה ❌</a>
                        <a href="?approve_id=<?php echo $p['id']; ?>" class="btn btn-ok">אשר פרסום ✅</a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>