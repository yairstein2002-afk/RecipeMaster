<?php
session_start();
require_once 'db.php';

$isLoggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'] ?? 'guest';
$userId = $_SESSION['user_id'] ?? null; // הוספנו את ה-ID של המשתמש המחובר

// לוגיקה: מנהל מאושר תמיד, משתמשים לפי סשן
$userStatus = ($userRole === 'admin') ? 'approved' : ($_SESSION['status'] ?? 'pending');

// --- לוגיקת ספירת התראות למנהל וספירת פעמון אישי ---
$pendingRecipesCount = 0;
$pendingUsersCount = 0;
$unreadNotifications = 0; // מונה חדש להתראות אישיות

if ($isLoggedIn) {
    // שליפת מונה ההתראות האישיות (הפעמון)
    $stmt_notif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt_notif->execute([$userId]);
    $unreadNotifications = $stmt_notif->fetchColumn();

    if ($userRole === 'admin') {
        // ספירת מתכונים לאישור
        $stmt_pending_r = $pdo->query("SELECT COUNT(*) FROM recipes WHERE is_approved = 0");
        $pendingRecipesCount = $stmt_pending_r->fetchColumn();

        // ספירת משתמשים הממתינים לאישור
        $stmt_pending_u = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'pending'");
        $pendingUsersCount = $stmt_pending_u->fetchColumn();
    }
}

// 1. שליפת קטגוריות לסרגל הניווט
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// פונקציית עזר לשליפת מתכונים לפי קריטריון
function getRecipesByOrder($pdo, $orderBy) {
    $sql = "SELECT r.*, u.username, u.profile_img, c.icon,
            (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) as likes_count,
            (SELECT COUNT(*) FROM comments WHERE recipe_id = r.id) as comments_count,
            (SELECT COUNT(*) FROM recipe_views WHERE recipe_id = r.id) as views
            FROM recipes r 
            JOIN users u ON r.user_id = u.id 
            JOIN categories c ON r.category_id = c.id
            WHERE r.is_public = 1 AND r.is_approved = 1 
            ORDER BY $orderBy DESC, views DESC, likes_count DESC, r.id DESC 
            LIMIT 12"; 
    return $pdo->query($sql)->fetchAll();
}

// 2. שליפת הנתונים ל-3 האזורים
$sections = [
    ['id' => 'liked', 'title' => '🔥 הכי אהובים (לייקים)', 'data' => getRecipesByOrder($pdo, 'likes_count')],
    ['id' => 'views', 'title' => '📈 הכי נצפים', 'data' => getRecipesByOrder($pdo, 'views')],
    ['id' => 'comments', 'title' => '💬 הכי מדוברים', 'data' => getRecipesByOrder($pdo, 'comments_count')]
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecipeMaster | דף הבית</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card-bg: rgba(255,255,255,0.05); --danger: #ff4757; --warning: #ffa502; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 100px; scroll-behavior: smooth; }
        
        .status-banner { background: var(--warning); color: #0f172a; padding: 10px; text-align: center; font-weight: bold; font-size: 0.9rem; border-bottom: 2px solid rgba(0,0,0,0.1); }

        .header-container { background: rgba(15, 23, 42, 0.8); border-bottom: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(15px); position: sticky; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 25px; }
        .main-search { width: 100%; padding: 12px 20px; border-radius: 50px; background: rgba(255,255,255,0.05); border: 1px solid var(--accent); color: white; outline: none; transition: 0.3s; box-sizing: border-box; }

        /* סגנון לפעמון ההתראות */
        .notif-bell { position: relative; text-decoration: none; font-size: 1.4rem; margin-left: 10px; cursor: pointer; }
        .bell-badge { position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; font-size: 0.7rem; min-width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 2px solid var(--bg); font-weight: bold; }

        .cat-bar { display: flex; gap: 12px; padding: 15px 25px; overflow-x: auto; scrollbar-width: none; }
        .cat-link { white-space: nowrap; padding: 8px 18px; background: var(--card-bg); border-radius: 50px; color: white; text-decoration: none; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.1); transition: 0.3s; }
        .cat-link:hover { background: var(--accent); color: var(--bg); }

        .admin-tools { background: rgba(0, 242, 254, 0.1); padding: 10px 25px; display: flex; gap: 20px; align-items: center; font-size: 0.85rem; border-bottom: 1px solid rgba(0, 242, 254, 0.2); }
        .notification-badge { background: var(--danger); color: white; font-size: 0.75rem; padding: 2px 8px; border-radius: 50px; margin-right: 6px; animation: pulse 2s infinite; font-weight: bold; }
        
        .recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; padding: 20px; }
        .recipe-card { background: var(--card-bg); border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; text-decoration: none; color: white; position: relative; display: flex; flex-direction: column; }
        .recipe-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        .img-wrapper { width: 100%; aspect-ratio: 16/10; overflow: hidden; position: relative; }
        .recipe-img { width: 100%; height: 100%; object-fit: cover; }
        
        .stat-badge { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 6px 12px; border-radius: 12px; font-size: 0.75rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1); display: flex; gap: 10px; align-items: center; }

        .notebook-btn { position: fixed; bottom: 30px; right: 30px; background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; padding: 15px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; z-index: 999; box-shadow: 0 10px 20px rgba(0,0,0,0.3); transition: 0.3s; }
        .shopping-btn { position: fixed; bottom: 30px; left: 30px; background: white; color: #0f172a; padding: 15px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; z-index: 999; box-shadow: 0 10px 20px rgba(0,0,0,0.3); transition: 0.3s; display: flex; align-items: center; gap: 8px; }

        .user-avatar-small { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--accent); }
        .author-link { color: var(--accent); text-decoration: none; font-weight: bold; transition: 0.2s; display: flex; align-items: center; gap: 8px; }
        .author-link:hover { opacity: 0.8; text-decoration: underline; }

        .hidden-item { display: none; }
        .toggle-btn { display: block; width: 220px; margin: 10px auto 40px; padding: 12px; background: transparent; border: 1px solid var(--accent); color: var(--accent); border-radius: 50px; cursor: pointer; font-weight: bold; transition: 0.3s; }

        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

<?php if($isLoggedIn && $userStatus === 'pending'): ?>
    <div class="status-banner">⏳ הפרופיל שלך ממתין לאישור מנהל. זמנית לא ניתן להעלות תוכן או להגיב.</div>
<?php endif; ?>

<div class="header-container">
    <nav class="navbar">
        <div style="font-size: 1.6rem; font-weight: bold; color: var(--accent);">RecipeMaster 👨‍🍳</div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <?php if($isLoggedIn): ?>
                
                <a href="notifications.php" class="notif-bell">
                    🔔
                    <?php if($unreadNotifications > 0): ?>
                        <span class="bell-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>

                <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(0, 242, 254, 0.3);">
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'user-default.png'); ?>" class="user-avatar-small">
                    <span style="font-weight: bold; font-size: 0.85rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                
                <?php if($userStatus === 'approved'): ?>
                    <a href="add_recipe.php" style="background: var(--accent); color: var(--bg); padding: 8px 15px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 0.9rem;">+ חדש</a>
                <?php endif; ?>

                <a href="settings.php" title="הגדרות">⚙️</a>
                <a href="logout.php" style="color: var(--danger); text-decoration: none; font-weight: bold; font-size: 0.9rem;">יציאה</a>
            <?php else: ?>
                <a href="login.php" style="background: var(--accent); color: var(--bg); padding: 8px 20px; border-radius: 50px; text-decoration: none; font-weight: bold;">התחברות 🔑</a>
            <?php endif; ?>
        </div>
    </nav>

    <div style="padding: 0 25px 15px;">
        <input type="text" id="mainSearch" class="main-search" placeholder="🔍 חפש מתכון או בשלן (למשל: עוגה, משה...)" onkeyup="filterAllSections()">
    </div>

    <div class="cat-bar">
        <a href="index.php" class="cat-link" style="background: rgba(0, 242, 254, 0.2); border-color: var(--accent);">🏠 הכל</a>
        <?php foreach ($categories as $c): ?>
            <a href="category.php?id=<?php echo $c['id']; ?>" class="cat-link"><?php echo $c['icon']; ?> <?php echo htmlspecialchars($c['name']); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if($userRole === 'admin'): ?>
    <div class="admin-tools">
        <span style="color: var(--accent); font-weight: bold;">🛠️ כלי מנהל:</span>
        <a href="admin_approval.php" style="color: white; text-decoration: none; display: flex; align-items: center;">
            אישור מתכונים
            <?php if($pendingRecipesCount > 0): ?>
                <span class="notification-badge"><?php echo $pendingRecipesCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="admin_users.php" style="color: white; text-decoration: none; display: flex; align-items: center;">
            ניהול משתמשים 👥
            <?php if($pendingUsersCount > 0): ?>
                <span class="notification-badge" style="background: var(--warning);"><?php echo $pendingUsersCount; ?></span>
            <?php endif; ?>
        </a>
        <a href="manage_categories.php" style="color: white; text-decoration: none;">עריכת קטגוריות 📁</a>
    </div>
    <?php endif; ?>
</div>

<main class="container">
    <?php foreach ($sections as $sec): ?>
    <section id="sec-<?php echo $sec['id']; ?>" class="content-section">
        <div style="padding: 25px 25px 0; font-size: 1.4rem; font-weight: bold; color: var(--accent);"><?php echo $sec['title']; ?></div>
        <div class="recipe-grid" id="grid-<?php echo $sec['id']; ?>">
            <?php foreach ($sec['data'] as $index => $r): ?>
            <div class="recipe-card <?php echo ($index >= 4) ? 'hidden-item' : ''; ?>" 
                 data-title="<?php echo htmlspecialchars(mb_strtolower($r['title'], 'UTF-8')); ?>"
                 data-author="<?php echo htmlspecialchars(mb_strtolower($r['username'], 'UTF-8')); ?>">
                
                <a href="view_recipe.php?id=<?php echo $r['id']; ?>" style="text-decoration: none; color: inherit;">
                    <div class="img-wrapper">
                        <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="recipe-img">
                        <div class="stat-badge">
                            <span>👁️ <?php echo number_format($r['views']); ?></span>
                            <span style="opacity: 0.5;">|</span>
                            <span>❤️ <?php echo $r['likes_count']; ?></span>
                        </div>
                    </div>
                </a>
                
                <div style="padding: 15px;">
                    <a href="view_recipe.php?id=<?php echo $r['id']; ?>" style="text-decoration: none; color: inherit;">
                        <h3 style="margin:0; font-size: 1.1rem;"><?php echo htmlspecialchars($r['title']); ?></h3>
                    </a>
                    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 10px;">
                        <a href="profile.php?id=<?php echo $r['user_id']; ?>" class="author-link" title="לפרופיל של <?php echo htmlspecialchars($r['username']); ?>">
                            <img src="<?php echo htmlspecialchars($r['profile_img'] ?: 'user-default.png'); ?>" class="user-avatar-small">
                            <span><?php echo htmlspecialchars($r['username']); ?></span>
                        </a>
                        <span style="font-size: 0.8rem; opacity: 0.5;"><?php echo $r['icon']; ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php if (count($sec['data']) > 4): ?>
            <button class="toggle-btn" onclick="toggleSection('<?php echo $sec['id']; ?>', this)">ראה עוד (+<?php echo count($sec['data'])-4; ?>)</button>
        <?php endif; ?>
    </section>
    <?php endforeach; ?>
</main>

<?php if($isLoggedIn): ?>
    <a href="my_recipes.php" class="notebook-btn">המחברת שלי 📖</a>
<?php endif; ?>

<a href="shopping_list.php" class="shopping-btn">🛒 רשימת קניות</a>

<script>
function toggleSection(secId, btn) {
    const grid = document.getElementById('grid-' + secId);
    const items = grid.querySelectorAll('.recipe-card');
    let isOpening = btn.innerText.includes("ראה עוד");
    items.forEach((item, index) => { if (index >= 4) item.style.display = isOpening ? "flex" : "none"; });
    btn.innerText = isOpening ? "הצג פחות 🔼" : "ראה עוד (+" + (items.length - 4) + ")";
}

function filterAllSections() {
    let input = document.getElementById('mainSearch').value.toLowerCase();
    let cards = document.querySelectorAll('.recipe-card');
    
    cards.forEach(card => {
        let title = card.getAttribute('data-title');
        let author = card.getAttribute('data-author');
        
        if (title.includes(input) || author.includes(input)) {
            card.style.display = "flex";
        } else {
            card.style.display = "none";
        }
    });
    
    document.querySelectorAll('.toggle-btn').forEach(btn => btn.style.display = (input === "") ? "block" : "none");
}
</script>
</body>
</html>
