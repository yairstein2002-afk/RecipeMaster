<?php
session_start();
// אבטחה: רק משתמש מחובר יכול לגשת לאיזור האישי
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

require_once 'db.php';

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// 1. לוגיקת מחיקה (רק למתכונים שלך או אם אתה אדמין)
if (isset($_GET['delete_id'])) {
    $stmt = ($userRole == 'admin') ? 
        $pdo->prepare("DELETE FROM recipes WHERE id = ?") : 
        $pdo->prepare("DELETE FROM recipes WHERE id = ? AND user_id = ?");
    
    $params = ($userRole == 'admin') ? [$_GET['delete_id']] : [$_GET['delete_id'], $userId];
    $stmt->execute($params);
    header("Location: my_recipes.php"); exit;
}

// 2. שינוי פרטיות מהיר (Toggle)
if (isset($_GET['toggle_id'])) {
    $stmt = $pdo->prepare("UPDATE recipes SET is_public = 1 - is_public WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['toggle_id'], $userId]);
    header("Location: my_recipes.php"); exit;
}

// 3. שליפת הקטגוריות והמתכונים האישיים בלבד
$stmt_cats = $pdo->query("SELECT * FROM categories ORDER BY id ASC");
$categories = $stmt_cats->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>המחברת שלי | RecipeMaster</title>
    <style>
        /* עיצוב פרימיום - Glassmorphism על רקע כהה */
        body { 
            background: linear-gradient(135deg, #0f172a, #1e293b, #0f172a); 
            color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        .header { 
            background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(15px);
            padding: 30px; border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1);
            display: flex; justify-content: space-between; align-items: center; margin-bottom: 40px;
        }

        .btn-action { 
            padding: 10px 20px; border-radius: 50px; text-decoration: none; font-weight: bold; transition: 0.3s;
        }
        .btn-back { color: #94a3b8; border: 1px solid rgba(255, 255, 255, 0.1); }
        .btn-add { background: #00f2fe; color: #0f172a; }

        .cat-title { font-size: 1.8em; margin: 40px 0 20px; color: #00f2fe; border-right: 4px solid #00f2fe; padding-right: 15px; }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }

        .card { 
            background: rgba(255, 255, 255, 0.03); backdrop-filter: blur(10px);
            border-radius: 20px; border: 1px solid rgba(255, 255, 255, 0.1); overflow: hidden; position: relative; transition: 0.3s;
        }
        .card:hover { transform: translateY(-10px); border-color: #00f2fe; box-shadow: 0 10px 30px rgba(0, 242, 254, 0.1); }

        .card-img { width: 100%; height: 180px; object-fit: cover; }
        
        .badge { 
            position: absolute; top: 12px; right: 12px; padding: 5px 12px; border-radius: 20px; 
            font-size: 0.75em; font-weight: bold; text-decoration: none; z-index: 5;
        }
        .badge-pub { background: rgba(0, 242, 254, 0.2); color: #00f2fe; border: 1px solid #00f2fe; }
        .badge-priv { background: rgba(253, 203, 110, 0.2); color: #fdcb6e; border: 1px solid #fdcb6e; }

        .card-body { padding: 20px; }
        .card-title { font-size: 1.2rem; color: white; text-decoration: none; display: block; margin-bottom: 15px; }
        .card-footer { display: flex; justify-content: space-between; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; align-items: center; }
        
        .action-link { text-decoration: none; font-size: 0.85em; transition: 0.2s; }
        .action-link:hover { opacity: 0.8; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div>
            <a href="index.php" class="btn-action btn-back">← חזרה לקהילה</a>
            <h1 style="margin: 15px 0 0;">המחברת של <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        </div>
        <a href="add_recipe.php" class="btn-action btn-add">+ מתכון חדש</a>
    </div>

    <?php foreach ($categories as $cat): 
        // שליפת מתכונים אישיים בלבד לכל קטגוריה
        $sql = ($userRole == 'admin') ? 
               "SELECT * FROM recipes WHERE category_id = ?" : 
               "SELECT * FROM recipes WHERE user_id = ? AND category_id = ?";
        
        $stmt = $pdo->prepare($sql);
        $params = ($userRole == 'admin') ? [$cat['id']] : [$userId, $cat['id']];
        $stmt->execute($params);
        $recipes = $stmt->fetchAll();
        
        if (count($recipes) > 0): ?>
            <h2 class="cat-title"><?php echo $cat['icon'] . " " . $cat['name']; ?></h2>
            <div class="grid">
                <?php foreach ($recipes as $r): ?>
                <div class="card">
                    <a href="view_recipe.php?id=<?php echo $r['id']; ?>">
                        <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="card-img">
                    </a>

                    <a href="?toggle_id=<?php echo $r['id']; ?>" class="badge <?php echo $r['is_public'] ? 'badge-pub' : 'badge-priv'; ?>">
                        <?php echo $r['is_public'] ? '🌍 פומבי' : '🔒 פרטי'; ?>
                    </a>

                    <div class="card-body">
                        <a href="view_recipe.php?id=<?php echo $r['id']; ?>" class="card-title"><?php echo htmlspecialchars($r['title']); ?></a>
                        
                        <div class="card-footer">
                            <a href="view_recipe.php?id=<?php echo $r['id']; ?>" class="action-link" style="color: #94a3b8;">צפייה 👁️</a>
                            
                            <a href="edit_recipe.php?id=<?php echo $r['id']; ?>" class="action-link" style="color: #00f2fe;">עריכה ✏️</a>
                            
                            <a href="?delete_id=<?php echo $r['id']; ?>" class="action-link" style="color: #ff7675;" onclick="return confirm('למחוק את המתכון לצמיתות?')">מחיקה 🗑️</a>
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