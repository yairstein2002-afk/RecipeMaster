<?php
session_start();
require_once 'db.php';

$userRole = $_SESSION['role'] ?? 'user';

// 1. שליפת הקטגוריות
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// 2. שליפת הטרנדינג - רק מאושרים ופומביים
$trending = $pdo->query("SELECT r.*, u.username FROM recipes r 
                         JOIN users u ON r.user_id = u.id 
                         WHERE r.is_public = 1 AND r.is_approved = 1 
                         ORDER BY r.id DESC LIMIT 5")->fetchAll();

$trendingIds = array_column($trending, 'id');

if (!empty($trendingIds)) {
    $placeholders = implode(',', array_fill(0, count($trendingIds), '?'));
    $sql_feed = "SELECT r.*, u.username, c.icon FROM recipes r 
                 JOIN users u ON r.user_id = u.id 
                 JOIN categories c ON r.category_id = c.id 
                 WHERE r.is_public = 1 AND r.is_approved = 1 AND r.id NOT IN ($placeholders) 
                 ORDER BY RAND() LIMIT 12";
    $stmt_feed = $pdo->prepare($sql_feed);
    $stmt_feed->execute($trendingIds);
    $feed = $stmt_feed->fetchAll();
} else {
    $feed = $pdo->query("SELECT r.*, u.username, c.icon FROM recipes r 
                         JOIN users u ON r.user_id = u.id 
                         JOIN categories c ON r.category_id = c.id 
                         WHERE r.is_public = 1 AND r.is_approved = 1 
                         ORDER BY RAND() LIMIT 12")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecipeMaster | השראה קולינרית</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card-bg: rgba(255,255,255,0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 80px; }
        
        /* Navbar קטגוריות */
        .cat-bar { 
            display: flex; gap: 15px; padding: 20px; overflow-x: auto; 
            background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(15px);
            position: sticky; top: 0; z-index: 1000; border-bottom: 1px solid rgba(255,255,255,0.05);
            scrollbar-width: none;
        }
        .cat-bar::-webkit-scrollbar { display: none; }
        .cat-item { 
            background: var(--card-bg); padding: 10px 22px; border-radius: 50px; 
            border: 1px solid rgba(255,255,255,0.1); text-decoration: none; color: white; transition: 0.3s;
            white-space: nowrap;
        }
        .cat-item:hover { background: var(--accent); color: var(--bg); font-weight: bold; transform: translateY(-2px); }

        /* סרגל התראות אדמין */
        .admin-alert { 
            background: rgba(0, 242, 254, 0.1); border: 1px solid var(--accent); 
            padding: 15px; border-radius: 15px; margin: 20px auto; max-width: 1100px; 
            display: flex; justify-content: space-between; align-items: center; backdrop-filter: blur(10px);
        }
        .admin-links a { color: var(--accent); text-decoration: none; margin-right: 15px; font-weight: bold; font-size: 0.9rem; }

        .section-title { padding: 40px 20px 20px; font-size: 1.8rem; font-weight: bold; color: var(--accent); }

        /* טרנדינג */
        .trending-container { display: flex; gap: 20px; padding: 0 20px; overflow-x: auto; scrollbar-width: none; }
        .trending-card { 
            min-width: 320px; height: 220px; border-radius: 25px; position: relative; 
            overflow: hidden; border: 1px solid rgba(255,255,255,0.1); text-decoration: none; color: white;
        }
        .trending-card img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .trending-card:hover img { transform: scale(1.1); }
        .trending-overlay { 
            position: absolute; inset: 0; background: linear-gradient(transparent, rgba(0,0,0,0.9)); 
            display: flex; flex-direction: column; justify-content: flex-end; padding: 20px; z-index: 2;
        }

        /* גריד קהילה */
        .feed-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 25px; padding: 20px; }
        .feed-item { 
            background: var(--card-bg); border-radius: 20px; overflow: hidden; 
            border: 1px solid rgba(255,255,255,0.05); transition: 0.3s ease;
            display: flex; flex-direction: column;
        }
        .img-wrapper { overflow: hidden; width: 100%; aspect-ratio: 16/10; }
        .feed-img { 
            width: 100%; height: 100%; object-fit: cover; display: block;
            transition: transform 0.6s cubic-bezier(0.33, 1, 0.68, 1); 
        }
        .feed-item:hover { transform: translateY(-8px); border-color: var(--accent); box-shadow: 0 15px 30px rgba(0, 242, 254, 0.15); }
        .feed-item:hover .feed-img { transform: scale(1.15); }
        .feed-content { padding: 18px; background: rgba(15, 23, 42, 0.9); z-index: 2; }

        .btn-float { 
            position: fixed; bottom: 30px; left: 30px; 
            background: linear-gradient(45deg, #4facfe, #00f2fe); 
            color: #0f172a; padding: 16px 30px; border-radius: 50px; 
            text-decoration: none; font-weight: bold; box-shadow: 0 10px 30px rgba(0,242,254,0.4); 
            z-index: 2000; transition: 0.3s;
        }
        .btn-float:hover { transform: scale(1.05); }
    </style>
</head>
<body>

<?php if ($userRole == 'admin'): 
    $stmt_p = $pdo->query("SELECT COUNT(*) FROM recipes WHERE is_public = 1 AND is_approved = 0");
    $p_count = $stmt_p->fetchColumn();
    ?>
    <div class="admin-alert">
        <span style="font-weight: bold; color: var(--accent);">
            <?php echo ($p_count > 0) ? "🔔 יאבלולו! מחכים לך $p_count מתכונים לאישור." : "🛠️ פאנל ניהול אדמין"; ?>
        </span>
        <div class="admin-links">
            <a href="manage_categories.php">📂 ניהול קטגוריות</a>
            <?php if ($p_count > 0): ?>
                <a href="admin_approval.php" style="background: var(--accent); color: var(--bg); padding: 5px 15px; border-radius: 50px;">אשר מתכונים ←</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="cat-bar">
    <a href="index.php" class="cat-item">🏠 הכל</a>
    <?php foreach ($categories as $c): ?>
        <a href="category.php?id=<?php echo $c['id']; ?>" class="cat-item">
            <?php echo $c['icon'] . " " . htmlspecialchars($c['name']); ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="section-title">🔥 עכשיו בטרנדינג</div>
<div class="trending-container">
    <?php if (empty($trending)): ?>
        <p style="padding: 20px; opacity: 0.5;">עוד רגע יהיו כאן טרנדים חדשים...</p>
    <?php else: ?>
        <?php foreach ($trending as $t): ?>
        <a href="view_recipe.php?id=<?php echo $t['id']; ?>" class="trending-card">
            <img src="<?php echo htmlspecialchars($t['image_url'] ?: 'default.jpg'); ?>">
            <div class="trending-overlay">
                <div style="font-size: 0.8rem; opacity: 0.7;">מאת: <?php echo htmlspecialchars($t['username']); ?></div>
                <div style="font-size: 1.3rem; font-weight: bold;"><?php echo htmlspecialchars($t['title']); ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="section-title">✨ השראה מהקהילה</div>
<div class="feed-grid">
    <?php if (empty($feed)): ?>
        <p style="padding: 20px; opacity: 0.5;">הקהילה עדיין מבשלת...</p>
    <?php else: ?>
        <?php foreach ($feed as $f): ?>
        <div class="feed-item">
            <a href="view_recipe.php?id=<?php echo $f['id']; ?>" class="img-wrapper">
                <img src="<?php echo htmlspecialchars($f['image_url'] ?: 'default.jpg'); ?>" class="feed-img">
            </a>
            <div class="feed-content">
                <div style="font-size: 1.1rem; font-weight: bold; margin-bottom: 8px;"><?php echo htmlspecialchars($f['title']); ?></div>
                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; opacity: 0.7;">
                    <span><?php echo $f['icon']; ?> | <?php echo htmlspecialchars($f['username']); ?></span>
                    <a href="view_recipe.php?id=<?php echo $f['id']; ?>" style="color: var(--accent); text-decoration: none;">למתכון ←</a>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if(isset($_SESSION['user_id'])): ?>
    <a href="my_recipes.php" class="btn-float">המחברת שלי 📖</a>
<?php else: ?>
    <a href="login.php" class="btn-float">התחברות 🔑</a>
<?php endif; ?>

</body>
</html>