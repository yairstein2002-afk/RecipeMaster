<?php
session_start();
require_once 'db.php';

// הסרנו את החסימה הכפויה (ה-header ל-login.php) כדי לאפשר לאורחים לצפות
$recipeId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'] ?? null; // null אם זה אורח
$userRole = $_SESSION['role'] ?? 'guest'; // 'guest' כברירת מחדל לאורח

// 1. פונקציה להמרת קישור יוטיוב
function getYouTubeEmbed($url) {
    if (preg_match('/(?:v=|shorts\/|be\/)([^&?\/]+)/', $url, $match)) {
        return "https://www.youtube.com/embed/" . $match[1];
    }
    return null;
}

// 2. לוגיקת אדמין (רק אם המשתמש מחובר והוא אדמין)
if (isset($_GET['make_private']) && $userRole === 'admin') {
    $stmt = $pdo->prepare("UPDATE recipes SET is_public = 0, is_approved = 0 WHERE id = ?");
    $stmt->execute([$recipeId]);
    header("Location: index.php?msg=removed_from_feed");
    exit;
}

// 3. שליפת המתכון - מאפשרים צפייה אם המתכון ציבורי או שייך למשתמש המחובר
$sql = "SELECT r.*, u.username, u.profile_img FROM recipes r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ? AND (r.is_public = 1 OR r.user_id = ? OR ? = 'admin')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$recipeId, $userId, $userRole]);
$recipe = $stmt->fetch();

if (!$recipe) { die("המתכון לא נמצא או שהגישה אליו מוגבלת."); }

// 4. שליפת מצרכים והוראות
$ingredients = $pdo->prepare("SELECT amount, ingredient_name, ingredient_description FROM ingredients WHERE recipe_id = ?");
$ingredients->execute([$recipeId]);
$ingredients = $ingredients->fetchAll();

$instructions = $pdo->prepare("SELECT instruction_text FROM instructions WHERE recipe_id = ? ORDER BY id ASC");
$instructions->execute([$recipeId]);
$instructions = $instructions->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($recipe['title']); ?> | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; line-height: 1.6; }
        .container { max-width: 850px; margin: auto; background: rgba(255,255,255,0.05); padding: 30px; border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); }
        .author-info { display: flex; align-items: center; gap: 10px; color: var(--accent); margin: 20px 0; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; }
        .user-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        .recipe-img { width: 100%; border-radius: 20px; margin: 20px 0; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .video-container { margin-top: 30px; border-radius: 20px; overflow: hidden; border: 2px solid var(--accent); }
        .back-link { color: #94a3b8; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .back-link:hover { color: var(--accent); }
        ul, ol { padding-right: 25px; }
        li { margin-bottom: 12px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← חזרה לדף הבית</a>
        
        <h1 style="margin-top: 25px; color: white;"><?php echo htmlspecialchars($recipe['title']); ?></h1>
        
        <div class="author-info">
            <img src="<?php echo htmlspecialchars($recipe['profile_img'] ?: 'user-default.png'); ?>" class="user-avatar">
            <span>מאת: <?php echo htmlspecialchars($recipe['username']); ?></span>
        </div>

        <?php if($recipe['image_url']): ?>
            <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" class="recipe-img">
        <?php endif; ?>

        <h3 style="color: var(--accent);">🛒 מצרכים:</h3>
        <ul>
            <?php foreach ($ingredients as $ing): ?>
                <li>
                    <b><?php echo htmlspecialchars($ing['amount']); ?></b> 
                    <?php echo htmlspecialchars($ing['ingredient_name']); ?> 
                    <?php if($ing['ingredient_description']): ?>
                        <span style="opacity: 0.7; font-style: italic;">(<?php echo htmlspecialchars($ing['ingredient_description']); ?>)</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>

        <h3 style="color: var(--accent);">📝 אופן ההכנה:</h3>
        <ol>
            <?php foreach($instructions as $ins): ?>
                <li><?php echo htmlspecialchars($ins['instruction_text']); ?></li>
            <?php endforeach; ?>
        </ol>

        <?php 
        $embedUrl = getYouTubeEmbed($recipe['video_url']); 
        if ($embedUrl): 
        ?>
            <h3 style="margin-top: 40px; color: var(--accent);">🎥 מדריך וידאו</h3>
            <div class="video-container">
                <iframe width="100%" height="480" src="<?php echo $embedUrl; ?>" frameborder="0" allowfullscreen></iframe>
            </div>
        <?php endif; ?>

        <?php if ($userRole === 'admin' && $recipe['user_id'] != $userId): ?>
            <div style="margin-top: 50px; padding: 20px; background: rgba(255,118,117,0.1); border: 1px dashed #ff7675; border-radius: 15px; text-align: center;">
                <h4 style="margin: 0 0 10px; color: #ff7675;">ניהול מערכת</h4>
                <a href="?id=<?php echo $recipeId; ?>&make_private=1" style="background: #ff7675; color: white; padding: 10px 20px; border-radius: 10px; text-decoration: none; font-weight: bold;" onclick="return confirm('להסיר מהקהילה?')">🔒 הפוך לפרטי</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
