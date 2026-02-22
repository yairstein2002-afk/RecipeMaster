<?php
session_start();
require_once 'db.php';

// בדיקה האם עבר ID של קטגוריה בכתובת (URL)
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$catId = $_GET['id'];

// 1. שליפת פרטי הקטגוריה (שם ואייקון)
$stmt_cat = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
$stmt_cat->execute([$catId]);
$category = $stmt_cat->fetch();

if (!$category) {
    die("הקטגוריה לא נמצאה.");
}

// 2. שליפת כל המתכונים הציבוריים ששייכים לקטגוריה הזו
$stmt_recipes = $pdo->prepare("
    SELECT r.*, u.username 
    FROM recipes r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.category_id = ? AND r.is_public = 1 
    ORDER BY r.id DESC
");
$stmt_recipes->execute([$catId]);
$recipes = $stmt_recipes->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title><?php echo $category['name']; ?> | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px; }
        
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 40px; }
        .back-link { color: var(--accent); text-decoration: none; font-weight: bold; }
        
        .cat-title { font-size: 2.5rem; display: flex; align-items: center; gap: 15px; }
        .cat-title span { color: var(--accent); }

        /* גריד המתכונים - סגנון ה-Feed */
        .recipe-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); 
            gap: 20px; 
        }
        
        .recipe-card { 
            background: #1e293b; border-radius: 20px; overflow: hidden; 
            border: 1px solid rgba(255,255,255,0.05); transition: 0.3s;
        }
        .recipe-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        
        .recipe-img { width: 100%; height: 200px; object-fit: cover; }
        .recipe-info { padding: 15px; }
        .recipe-info h3 { margin: 0 0 10px; font-size: 1.2rem; }
        .author { font-size: 0.8rem; opacity: 0.6; }

        .empty-state { text-align: center; margin-top: 100px; opacity: 0.5; }
    </style>
</head>
<body>

<div class="header">
    <a href="index.php" class="back-link">🔙 חזרה לחיפוש</a>
    <div class="cat-title">
        <?php echo $category['icon']; ?> <span><?php echo htmlspecialchars($category['name']); ?></span>
    </div>
    <div style="width: 100px;"></div> </div>

<?php if (count($recipes) > 0): ?>
    <div class="recipe-grid">
        <?php foreach ($recipes as $r): ?>
        <div class="recipe-card">
            <a href="view_recipe.php?id=<?php echo $r['id']; ?>">
                <?php if($r['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($r['image_url']); ?>" class="recipe-img">
                <?php else: ?>
                    <div style="height:200px; background:#2d3436; display:flex; align-items:center; justify-content:center;">📸</div>
                <?php endif; ?>
            </a>
            <div class="recipe-info">
                <h3><?php echo htmlspecialchars($r['title']); ?></h3>
                <div class="author">הועלה ע"י <?php echo htmlspecialchars($r['username']); ?></div>
                <a href="view_recipe.php?id=<?php echo $r['id']; ?>" style="color: var(--accent); text-decoration: none; display: block; margin-top: 10px; font-weight: bold;">למתכון המלא ←</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="empty-state">
        <h2>אופס! עדיין אין מתכונים ציבוריים בקטגוריה הזו.</h2>
        <p>אולי תהיה הראשון להוסיף אחד?</p>
    </div>
<?php endif; ?>

</body>
</html>