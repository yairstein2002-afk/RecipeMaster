<?php
session_start();
require_once 'db.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'guest';

// --- לוגיקת ספירת מתכונים לאישור (לאדמינים בלבד) ---
$pendingCount = 0;
if ($userRole === 'admin') {
    $stmt_pending = $pdo->query("SELECT COUNT(*) FROM recipes WHERE is_approved = 0");
    $pendingCount = $stmt_pending->fetchColumn();
}
// ----------------------------------------------------

// 1. שליפת קטגוריות לסרגל הניווט
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// 2. טרנדינג - 4 מתכונים מאושרים וציבוריים, כולל תמונת פרופיל
$trending = $pdo->query("SELECT r.*, u.username, u.profile_img FROM recipes r 
                         JOIN users u ON r.user_id = u.id 
                         WHERE r.is_public = 1 AND r.is_approved = 1 
                         ORDER BY r.id DESC LIMIT 4")->fetchAll();

$trendingIds = array_column($trending, 'id');

// 3. השראה מהקהילה - שליפת כל המתכונים המאושרים, כולל תמונת פרופיל
$feed = [];
if (!empty($trendingIds)) {
    $placeholders = implode(',', array_fill(0, count($trendingIds), '?'));
    $sql_feed = "SELECT r.*, u.username, u.profile_img, c.icon FROM recipes r 
                 JOIN users u ON r.user_id = u.id 
                 JOIN categories c ON r.category_id = c.id 
                 WHERE r.is_public = 1 AND r.is_approved = 1 AND r.id NOT IN ($placeholders) 
                 ORDER BY r.id DESC";
    $stmt_feed = $pdo->prepare($sql_feed);
    $stmt_feed->execute($trendingIds);
    $feed = $stmt_feed->fetchAll();
} else {
    // שליפת כל המתכונים המאושרים והציבוריים כולל תמונת פרופיל מהקהילה
    $feed = $pdo->query("SELECT r.*, u.username, u.profile_img, c.icon FROM recipes r 
                         JOIN users u ON r.user_id = u.id 
                         JOIN categories c ON r.category_id = c.id 
                         WHERE r.is_public = 1 AND r.is_approved = 1 
                         ORDER BY r.id DESC")->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>RecipeMaster | דף הבית</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card-bg: rgba(255,255,255,0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 80px; scroll-behavior: smooth; }
        
        /* תפריט עליון קבוע */
        .header-container { background: rgba(255,255,255,0.03); border-bottom: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); position: sticky; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 25px; }
        
        /* חיפוש חכם */
        .search-wrapper { padding: 0 25px 15px; }
        .main-search { width: 100%; padding: 12px 20px; border-radius: 50px; background: rgba(255,255,255,0.05); border: 1px solid var(--accent); color: white; outline: none; transition: 0.3s; }
        .main-search:focus { box-shadow: 0 0 15px rgba(0, 242, 254, 0.3); background: rgba(255,255,255,0.1); }

        /* סרגל קטגוריות */
        .cat-bar { display: flex; gap: 12px; padding: 15px 25px; overflow-x: auto; scrollbar-width: none; border-bottom: 1px solid rgba(255, 255, 255, 0.05); }
        .cat-bar::-webkit-scrollbar { display: none; }
        .cat-link { white-space: nowrap; padding: 8px 18px; background: var(--card-bg); border-radius: 50px; color: white; text-decoration: none; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.1); transition: 0.3s; }
        .cat-link:hover { background: var(--accent); color: var(--bg); }

        /* סרגל אדמין */
        .admin-tools { background: rgba(255, 118, 117, 0.1); padding: 8px 25px; display: flex; gap: 20px; align-items: center; font-size: 0.85rem; border-bottom: 1px solid rgba(255, 118, 117, 0.2); }
        
        /* עיצוב בועת ההתראות לאדמין */
        .notification-badge {
            background: linear-gradient(45deg, #ff4757, #ff6b81);
            color: white; font-size: 0.75rem; font-weight: bold;
            padding: 2px 8px; border-radius: 50px; margin-right: 6px;
            box-shadow: 0 0 10px rgba(255, 71, 87, 0.5); display: inline-block;
            animation: pulse 2s infinite; 
        }
        @keyframes pulse {
            0% { transform: scale(1); box-shadow: 0 0 5px rgba(255, 71, 87, 0.5); }
            50% { transform: scale(1.15); box-shadow: 0 0 15px rgba(255, 71, 87, 0.8); }
            100% { transform: scale(1); box-shadow: 0 0 5px rgba(255, 71, 87, 0.5); }
        }

        /* גריד ואפקט Zoom */
        .recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; padding: 20px; }
        .recipe-card { background: var(--card-bg); border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; text-decoration: none; color: white; }
        .recipe-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        
        .img-wrapper { width: 100%; aspect-ratio: 16/10; overflow: hidden; position: relative; }
        .recipe-img { width: 100%; height: 100%; object-fit: cover; transition: 0.4s; }
        .recipe-card:hover .recipe-img { transform: scale(1.1); } /* אפקט ההגדלה */
        
        .video-badge { position: absolute; bottom: 10px; left: 10px; background: rgba(0,0,0,0.6); color: white; padding: 5px 10px; border-radius: 10px; font-size: 0.8rem; backdrop-filter: blur(5px); }
        .btn-add-nav { background: var(--accent); color: var(--bg); padding: 7px 15px; border-radius: 8px; text-decoration: none; font-weight: bold; }
        
        /* עיצוב תמונת משתמש */
        .user-avatar-small { width: 25px; height: 25px; border-radius: 50%; object-fit: cover; border: 1px solid var(--accent); }
        .nav-user-badge { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(0, 242, 254, 0.3); }
        
        .hidden-item { display: none; }
        .toggle-btn { display: block; width: 200px; margin: 20px auto; padding: 12px; background: transparent; border: 1px solid var(--accent); color: var(--accent); border-radius: 50px; cursor: pointer; font-weight: bold; }
    </style>
</head>
<body>

<div class="header-container">
    <nav class="navbar">
        <div style="font-size: 1.6rem; font-weight: bold; color: var(--accent);">RecipeMaster 👨‍🍳</div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <?php if($isLoggedIn): ?>
                <div class="nav-user-badge">
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'user-default.png'); ?>" class="user-avatar-small" style="width: 30px; height: 30px;">
                    <span style="font-weight: bold; font-size: 0.9rem;">שלום, <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <a href="add_recipe.php" class="btn-add-nav">+ מתכון חדש</a>
                <a href="settings.php" style="text-decoration: none; font-size: 1.2rem;">⚙️</a>
                <a href="logout.php" style="color: #ff7675; text-decoration: none; font-weight: bold;">יציאה</a>
            <?php else: ?>
                <a href="login.php" class="btn-add-nav">התחברות 🔑</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="search-wrapper">
        <input type="text" id="mainSearch" class="main-search" placeholder="🔍 חפש מתכון בקהילה..." onkeyup="filterFeed()">
    </div>

    <div class="cat-bar">
        <a href="index.php" class="cat-link" style="background: rgba(0, 242, 254, 0.2);">🏠 הכל</a>
        <?php foreach ($categories as $c): ?>
            <a href="category.php?id=<?php echo $c['id']; ?>" class="cat-link"><?php echo $c['icon']; ?> <?php echo htmlspecialchars($c['name']); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if($userRole === 'admin'): ?>
    <div class="admin-tools">
        <span style="color: #ff4757; font-weight: bold;">👑 מנהל:</span>
        <a href="manage_categories.php" style="color: #ff7675; text-decoration: none; font-weight: bold;">ניהול קטגוריות</a>
        <a href="admin_approval.php" style="color: white; text-decoration: none; font-weight: bold; display: flex; align-items: center;">
            אישור מתכונים ⚖️
            <?php if($pendingCount > 0): ?>
                <span class="notification-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<div id="trending-section">
    <div style="padding: 20px 25px 0; font-size: 1.5rem; font-weight: bold; color: var(--accent);">🔥 טרנדינג</div>
    <div class="recipe-grid">
        <?php foreach ($trending as $t): ?>
        <a href="view_recipe.php?id=<?php echo $t['id']; ?>" class="recipe-card" data-title="<?php echo htmlspecialchars(mb_strtolower($t['title'], 'UTF-8')); ?>">
            <div class="img-wrapper">
                <img src="<?php echo htmlspecialchars($t['image_url'] ?: 'default.jpg'); ?>" class="recipe-img">
                <?php if(!empty($t['video_url'])): ?><div class="video-badge">🎥 וידאו</div><?php endif; ?>
            </div>
            <div style="padding: 15px;">
                <h3 style="margin:0;"><?php echo htmlspecialchars($t['title']); ?></h3>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; opacity: 0.8; margin-top: 10px;">
                    <img src="<?php echo htmlspecialchars($t['profile_img'] ?: 'user-default.png'); ?>" class="user-avatar-small">
                    <span><?php echo htmlspecialchars($t['username']); ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<div id="community-section">
    <div style="padding: 20px 25px 0; font-size: 1.5rem; font-weight: bold; color: var(--accent);">✨ השראה מהקהילה (<?php echo count($feed); ?>)</div>
    <div class="recipe-grid" id="main-feed">
        <?php foreach ($feed as $index => $f): ?>
        <a href="view_recipe.php?id=<?php echo $f['id']; ?>" class="recipe-card <?php echo ($index >= 4) ? 'hidden-item' : ''; ?>" data-title="<?php echo htmlspecialchars(mb_strtolower($f['title'], 'UTF-8')); ?>">
            <div class="img-wrapper">
                <img src="<?php echo htmlspecialchars($f['image_url'] ?: 'default.jpg'); ?>" class="recipe-img">
                <?php if(!empty($f['video_url'])): ?><div class="video-badge">🎥 וידאו</div><?php endif; ?>
            </div>
            <div style="padding: 15px;">
                <h3 style="margin:0;"><?php echo htmlspecialchars($f['title']); ?></h3>
                <div style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; opacity: 0.8; margin-top: 10px;">
                    <img src="<?php echo htmlspecialchars($f['profile_img'] ?: 'user-default.png'); ?>" class="user-avatar-small">
                    <span><?php echo $f['icon']; ?> | <?php echo htmlspecialchars($f['username']); ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if (count($feed) > 4): ?>
        <button class="toggle-btn" id="toggleBtn" onclick="toggleCommunityFeed()">ראה עוד מתכונים (+<?php echo count($feed)-4; ?>)</button>
    <?php endif; ?>
</div>

<?php if($isLoggedIn): ?>
    <a href="my_recipes.php" style="position: fixed; bottom: 30px; left: 30px; background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; padding: 15px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; z-index: 1000; box-shadow: 0 10px 20px rgba(0,0,0,0.3);">המחברת שלי 📖</a>
<?php endif; ?>

<script>
function toggleCommunityFeed() {
    const btn = document.getElementById('toggleBtn');
    const items = document.querySelectorAll('#main-feed .recipe-card');
    let isOpening = btn.innerText.includes("ראה עוד");

    items.forEach((item, index) => {
        if (index >= 4) {
            item.style.display = isOpening ? "block" : "none";
            if (isOpening) item.classList.remove('hidden-item');
        }
    });

    btn.innerText = isOpening ? "הצג פחות 🔼" : "ראה עוד מתכונים (+" + (items.length - 4) + ")";
    if (!isOpening) window.scrollTo({ top: document.getElementById('community-section').offsetTop - 100, behavior: 'smooth' });
}

function filterFeed() {
    let input = document.getElementById('mainSearch').value.toLowerCase();
    let cards = document.querySelectorAll('.recipe-grid .recipe-card');
    let btn = document.getElementById('toggleBtn');
    
    cards.forEach(card => {
        let title = card.getAttribute('data-title');
        if (title.includes(input)) {
            card.style.display = "block";
            card.classList.remove('hidden-item'); 
        } else {
            card.style.display = "none";
        }
    });

    if (btn) btn.style.display = (input === "") ? "block" : "none";
}
</script>
</body>
</html>
