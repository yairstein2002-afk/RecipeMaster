<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';

$recipeId = $_GET['id'];
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// מנהל רואה הכל, משתמש רק את שלו או ציבורי
$sql = ($userRole == 'admin') ? "SELECT * FROM recipes WHERE id = ?" : "SELECT * FROM recipes WHERE id = ? AND (user_id = ? OR is_public = 1)";
$stmt = $pdo->prepare($sql);
($userRole == 'admin') ? $stmt->execute([$recipeId]) : $stmt->execute([$recipeId, $userId]);
$recipe = $stmt->fetch();

if (!$recipe) { die("המתכון לא נמצא."); }

// שליפת מרכיבים כולל התיאור החדש
$stmt_ing = $pdo->prepare("SELECT amount, ingredient_name, ingredient_description FROM ingredients WHERE recipe_id = ?");
$stmt_ing->execute([$recipeId]);
$ingredients = $stmt_ing->fetchAll();

$stmt_ins = $pdo->prepare("SELECT instruction_text FROM instructions WHERE recipe_id = ?");
$stmt_ins->execute([$recipeId]);
$instructions = $stmt_ins->fetchAll();

function getEmbed($url) {
    if (preg_match('/(?:v=|shorts\/|be\/)([^&?\/]+)/', $url, $match)) return "https://www.youtube.com/embed/" . $match[1];
    return null;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($recipe['title']); ?></title>
    <style>
        body { background: #2d3436; color: white; font-family: sans-serif; line-height: 1.6; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; background: #34495e; padding: 30px; border-radius: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 10px; text-align: right; border-bottom: 1px solid #4b6584; }
        .amount { color: #fab1a0; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" style="color: #a29bfe;">🔙 חזרה</a>
        <h1><?php echo htmlspecialchars($recipe['title']); ?></h1>
        <?php if($recipe['image_url']): ?> <img src="<?php echo $recipe['image_url']; ?>" style="width:100%; border-radius:10px;"> <?php endif; ?>

        <h2>🛒 מה צריך?</h2>
<table>
    <tr style="background: #4b6584;">
        <th>כמות</th>
        <th>מוצר</th>
        <th>תיאור</th>
    </tr>
    <?php foreach ($ingredients as $ing): ?>
    <tr>
        <td class="amount"><?php echo htmlspecialchars($ing['amount']); ?></td>
        <td style="font-weight: bold;"><?php echo htmlspecialchars($ing['ingredient_name']); ?></td>
        <td style="font-style: italic; color: #b2bec3;"><?php echo htmlspecialchars($ing['ingredient_description']); ?></td>
    </tr>
    <?php endforeach; ?>
</table>

        <h2>👨‍🍳 אופן ההכנה</h2>
        <ol><?php foreach($instructions as $ins): ?><li><?php echo htmlspecialchars($ins['instruction_text']); ?></li><?php endforeach; ?></ol>

        <?php $yt = getEmbed($recipe['video_url']); if($yt): ?>
            <h2>🎥 מדריך וידאו</h2>
            <iframe width="100%" height="350" src="<?php echo $yt; ?>" frameborder="0" allowfullscreen></iframe>
        <?php endif; ?>
    </div>
</body>
</html>