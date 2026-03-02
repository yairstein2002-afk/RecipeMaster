<?php
session_start();
require_once 'db.php';

// קבלת ה-ID של בעל הפרופיל מהכתובת
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($profileId === 0) {
    header("Location: index.php");
    exit;
}

// 1. שליפת פרטי המשתמש (בעל הפרופיל)
$stmt_user = $pdo->prepare("SELECT username, profile_img, role FROM users WHERE id = ?");
$stmt_user->execute([$profileId]);
$profileUser = $stmt_user->fetch();

if (!$profileUser) {
    die("שגיאה: משתמש לא נמצא.");
}

// 2. חישוב סך הלייקים הכללי שהמשתמש קיבל בכל המתכונים שלו יחד
$stmt_total_likes = $pdo->prepare("
    SELECT COUNT(*) 
    FROM likes 
    WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = ?)
");
$stmt_total_likes->execute([$profileId]);
$totalLikes = $stmt_total_likes->fetchColumn();

// 3. שליפת המתכונים הציבוריים והמאושרים של המשתמש
$stmt_recipes = $pdo->prepare("
    SELECT r.*, c.icon, 
    (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) as likes_count
    FROM recipes r 
    JOIN categories c ON r.category_id = c.id 
    WHERE r.user_id = ? AND r.is_public = 1 AND r.is_approved = 1 
    ORDER BY r.id DESC
");
$stmt_recipes->execute([$profileId]);
$userRecipes = $stmt_recipes->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הפרופיל של <?php echo htmlspecialchars($profileUser['username']); ?> | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 50px; }
        
        .profile-header { 
            background: linear-gradient(to bottom, rgba(0, 242, 254, 0.15), transparent); 
            padding: 60px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); 
            position: relative;
        }
        
        .back-link { position: absolute; top: 20px; right: 20px; color: var(--accent); text-decoration: none; font-weight: bold; font-size: 0.9rem; }
        
        .profile-avatar { 
            width: 130px; height: 130px; border-radius: 50%; border: 4px solid var(--accent); 
            object-fit: cover; box-shadow: 0 0 25px rgba(0,242,254,0.3); margin-bottom: 15px;
        }

        /* עיצוב מוני הסטטיסטיקה */
        .chef-stats { display: flex; justify-content: center; gap: 20px; margin-top: 20px; }
        .stat-box { background: var(--glass); padding: 10px 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); min-width: 100px; }
        .stat-num { display: block; font-size: 1.2rem; font-weight: bold; color: var(--accent); }
        .stat-label { font-size: 0.8rem; opacity: 0.6; }

        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }
        .recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        
        .recipe-card { 
            background: var(--glass); border-radius: 20px; overflow: hidden; 
            border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; 
            text-decoration: none; color: white; display: flex; flex-direction: column;
        }
        .recipe-card:hover { transform: translateY(-5px); border-color: var(--accent); box-shadow: 0 10px 20px rgba(0,0,0,0.3); }
        
        .img-wrapper { width: 100%; aspect-ratio: 16/10; overflow: hidden; position: relative; }
        .recipe-img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .recipe-card:hover .recipe-img { scale: 1.05; }
        
        .card-body { padding: 18px; flex-grow: 1; }
        .recipe-title { margin: 0; font-size: 1.15rem; color: white; }
        .card-footer { margin-top: 12px; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; opacity: 0.7; }
    </style>
</head>
<body>

<div class="profile-header">
    <a href="index.php" class="back-link">🏠 חזרה לפיד</a>
    <img src="<?php echo htmlspecialchars($profileUser['profile_img'] ?: 'user-default.png'); ?>" class="profile-avatar">
    <h1 style="margin: 0; font-size: 2rem;">
        <?php echo htmlspecialchars($profileUser['username']); ?> 
        <?php echo ($profileUser['role'] === 'admin') ? '👑' : ''; ?>
    </h1>
    <p style="opacity: 0.7; margin: 5px 0 0;">שף בקהילת RecipeMaster</p>

    <div class="chef-stats">
        <div class="stat-box">
            <span class="stat-num"><?php echo count($userRecipes); ?></span>
            <span class="stat-label">מתכונים</span>
        </div>
        <div class="stat-box">
            <span class="stat-num">❤️ <?php echo number_format($totalLikes); ?></span>
            <span class="stat-label">לייקים כולל</span>
        </div>
    </div>
</div>

<div class="container">
    <h2 style="margin-bottom: 25px; border-right: 4px solid var(--accent); padding-right: 15px;">הספר של <?php echo htmlspecialchars($profileUser['username']); ?> 📔</h2>
    
    <?php if (empty($userRecipes)): ?>
        <div style="text-align: center; padding: 100px; opacity: 0.5;">
            <h3>הבשלן עדיין לא פרסם מתכונים... 🧺</h3>
        </div>
    <?php else: ?>
        <div class="recipe-grid">
            <?php foreach ($userRecipes as $r): ?>
                <a href="view_recipe.php?id=<?php echo $r['id']; ?>" class="recipe-card">
                    <div class="img-wrapper">
                        <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="recipe-img">
                    </div>
                    <div class="card-body">
                        <h4 class="recipe-title"><?php echo htmlspecialchars($r['title']); ?></h4>
                        <div class="card-footer">
                            <span><?php echo $r['icon']; ?> לצפייה במתכון</span>
                            <span>❤️ <?php echo $r['likes_count']; ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>