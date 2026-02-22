<?php
session_start();
// אבטחה: רק משתמש מחובר יכול לצפות במתכונים
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';

$recipeId = $_GET['id'];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// 1. פונקציה להמרת קישור יוטיוב לנגן וידאו פעיל (Embed)
function getYouTubeEmbed($url) {
    if (preg_match('/(?:v=|shorts\/|be\/)([^&?\/]+)/', $url, $match)) {
        return "https://www.youtube.com/embed/" . $match[1];
    }
    return null;
}

// 2. לוגיקת אדמין: הפיכת מתכון של משתמש אחר לפרטי (הסרה מהפיד)
if (isset($_GET['make_private']) && $userRole === 'admin') {
    $stmt = $pdo->prepare("UPDATE recipes SET is_public = 0, is_approved = 0 WHERE id = ?");
    $stmt->execute([$recipeId]);
    header("Location: index.php?msg=removed_from_feed");
    exit;
}

// 3. שליפת המתכון כולל שם המשתמש שהעלה אותו (JOIN)
$sql = "SELECT r.*, u.username FROM recipes r 
        JOIN users u ON r.user_id = u.id 
        WHERE r.id = ? AND (r.user_id = ? OR r.is_public = 1 OR ? = 'admin')";
$stmt = $pdo->prepare($sql);
$stmt->execute([$recipeId, $userId, $userRole]);
$recipe = $stmt->fetch();

if (!$recipe) { die("המתכון לא נמצא או הוסר מהקהילה."); }

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
        .container { max-width: 800px; margin: auto; background: rgba(255,255,255,0.05); padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); }
        
        .author-info { color: var(--accent); margin: 10px 0; font-size: 1rem; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }
        
        .recipe-img { width: 100%; border-radius: 15px; margin: 20px 0; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        
        .video-container { margin-top: 30px; border-radius: 15px; overflow: hidden; border: 2px solid var(--accent); box-shadow: 0 0 20px rgba(0, 242, 254, 0.2); }
        
        .admin-box { margin-top: 40px; padding: 20px; background: rgba(255,118,117,0.1); border: 1px dashed #ff7675; border-radius: 15px; text-align: center; }
        .btn-hide { background: #ff7675; color: white; padding: 12px 25px; border-radius: 10px; text-decoration: none; font-weight: bold; display: inline-block; transition: 0.3s; }
        .btn-hide:hover { background: white; color: #ff7675; }

        ul, ol { padding-right: 20px; }
        li { margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" style="color: #94a3b8; text-decoration: none; font-weight: bold;">← חזרה לפיד הקהילה</a>
        
        <h1 style="margin-top: 20px;"><?php echo htmlspecialchars($recipe['title']); ?></h1>
        
        <div class="author-info">👨‍🍳 הועלה על ידי: <?php echo htmlspecialchars($recipe['username']); ?></div>

        <?php if($recipe['image_url']): ?>
            <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" class="recipe-img">
        <?php endif; ?>

        <h3>🛒 מצרכים:</h3>
        <ul>
            <?php foreach ($ingredients as $ing): ?>
                <li>
                    <b><?php echo htmlspecialchars($ing['amount']); ?></b> 
                    <?php echo htmlspecialchars($ing['ingredient_name']); ?> 
                    <span style="opacity: 0.7; font-style: italic;">(<?php echo htmlspecialchars($ing['ingredient_description']); ?>)</span>
                </li>
            <?php endforeach; ?>
        </ul>

        <h3>📝 אופן ההכנה:</h3>
        <ol>
            <?php foreach($instructions as $ins): ?>
                <li><?php echo htmlspecialchars($ins['instruction_text']); ?></li>
            <?php endforeach; ?>
        </ol>

        <?php 
        $embedUrl = getYouTubeEmbed($recipe['video_url']); 
        if ($embedUrl): 
        ?>
            <h3 style="margin-top: 40px;">🎥 מדריך וידאו למתכון</h3>
            <div class="video-container">
                <iframe width="100%" height="450" src="<?php echo $embedUrl; ?>" frameborder="0" allowfullscreen></iframe>
            </div>
        <?php endif; ?>

        <?php if ($userRole === 'admin' && $recipe['user_id'] != $userId): ?>
            <div class="admin-box">
                <h4 style="margin-top: 0; color: #ff7675;">ניהול אדמין</h4>
                <p>המתכון הזה מוצג כרגע בקהילה. ניתן להסירו ולהפוך אותו לפרטי עבור היוצר שלו.</p>
                <a href="?id=<?php echo $recipeId; ?>&make_private=1" class="btn-hide" onclick="return confirm('האם להפוך את המתכון לפרטי ולהסירו מהפיד?')">🔒 הפוך לפרטי (הסרה מהקהילה)</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>