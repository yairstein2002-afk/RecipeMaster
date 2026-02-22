<?php
session_start();
// אבטחה: רק משתמש מחובר יכול לגשת לאיזור האישי
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

require_once 'db.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// 1. לוגיקת מחיקה - המשתמש יכול למחוק רק את המתכונים שלו
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM recipes WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['delete_id'], $userId]);
    header("Location: my_recipes.php?msg=deleted"); 
    exit;
}

// 2. שינוי פרטיות מהיר (Toggle) - רק למתכונים שלך
if (isset($_GET['toggle_id'])) {
    $stmt = $pdo->prepare("UPDATE recipes SET is_public = 1 - is_public WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['toggle_id'], $userId]);
    header("Location: my_recipes.php"); 
    exit;
}

// 3. שליפת הקטגוריות
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>המחברת שלי | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { 
            background: linear-gradient(135deg, #0f172a, #1e293b); 
            color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { 
            background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(15px);
            padding: 30px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;
        }
        .btn-action { padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .btn-back { color: #94a3b8; border: 1px solid rgba(255, 255, 255, 0.1); }
        .btn-add { background: var(--accent); color: #0f172a; }
        
        .cat-title { font-size: 1.8em; margin: 40px 0 20px; color: var(--accent); border-right: 4px solid var(--accent); padding-right: 15px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        
        .card { 
            background: rgba(255, 255, 255, 0.03); border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); 
            overflow: hidden; position: relative; transition: 0.3s;
        }
        .card:hover { transform: translateY(-10px); border-color: var(--accent); }
        .img-wrapper { width: 100%; height: 180px; position: relative; overflow: hidden; }
        .card-img { width: 100%; height: 100%; object-fit: cover; }
        
        /* אייקון וידאו */
        .video-badge { position: absolute; bottom: 8px; left: 8px; background: rgba(0,0,0,0.6); color: white; padding: 4px 8px; border-radius: 8px; font-size: 0.7rem; backdrop-filter: blur(5px); }

        .badge { position: absolute; top: 12px; right: 12px; padding: 5px 12px; border-radius: 20px; font-size: 0.75em; font-weight: bold; text-decoration: none; z-index: 5; }
        .badge-pub { background: rgba(0, 242, 254, 0.2); color: var(--accent); border: 1px solid var(--accent); }
        .badge-priv { background: rgba(253, 203, 110, 0.2); color: #fdcb6e; border: 1px solid #fdcb6e; }
        
        .card-footer { display: flex; justify-content: space-between; padding: 15px; border-top: 1px solid rgba(255,255,255,0.05); }
        .action-link { text-decoration: none; font-size: 0.85em; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <a href="index.php" class="btn-action btn-back">← חזרה לקהילה</a>
            <h1 style="margin: 15px 0 0;">המחברת האישית שלי 📖</h1>
        </div>
        <a href="add_recipe.php" class="btn-action btn-add">+ מתכון חדש</a>
    </div>

    <?php foreach ($categories as $cat): 
        // שליפת מתכונים ששייכים ליוזר המחובר בלבד
        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE user_id = ? AND category_id = ?");
        $stmt->execute([$userId, $cat['id']]);
        $recipes = $stmt->fetchAll();
        
        if (count($recipes) > 0): ?>
            <h2 class="cat-title"><?php echo $cat['icon'] . " " . $cat['name']; ?></h2>
            <div class="grid">
                <?php foreach ($recipes as $r): ?>
                <div class="card">
                    <div class="img-wrapper">
                        <a href="view_recipe.php?id=<?php echo $r['id']; ?>">
                            <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="card-img">
                        </a>
                        <?php if(!empty($r['video_url'])): ?>
                            <div class="video-badge">🎥 וידאו</div>
                        <?php endif; ?>
                    </div>

                    <a href="?toggle_id=<?php echo $r['id']; ?>" class="badge <?php echo $r['is_public'] ? 'badge-pub' : 'badge-priv'; ?>">
                        <?php echo $r['is_public'] ? '🌍 פומבי' : '🔒 פרטי'; ?>
                    </a>

                    <div style="padding: 20px;">
                        <a href="view_recipe.php?id=<?php echo $r['id']; ?>" style="color: white; text-decoration: none; font-weight: bold; display: block; margin-bottom: 10px;">
                            <?php echo htmlspecialchars($r['title']); ?>
                        </a>
                        
                        <div class="card-footer">
                            <a href="edit_recipe.php?id=<?php echo $r['id']; ?>" class="action-link" style="color: var(--accent);">עריכה ✏️</a>
                            <a href="?delete_id=<?php echo $r['id']; ?>" class="action-link" style="color: #ff7675;" onclick="return confirm('למחוק?')">מחיקה 🗑️</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

</body>
</html>