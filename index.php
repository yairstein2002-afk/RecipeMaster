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

// 1. שליפת קטגוריות לסרגל הניווט
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// פונקציית עזר לשליפת מתכונים לפי קריטריון
function getRecipesByOrder($pdo, $orderBy) {
    // השאילתה המעודכנת כוללת סדר עדיפויות משני למקרה של תיקו
    $sql = "SELECT r.*, u.username, u.profile_img, c.icon,
            (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) as likes_count,
            (SELECT COUNT(*) FROM comments WHERE recipe_id = r.id) as comments_count
            FROM recipes r 
            JOIN users u ON r.user_id = u.id 
            JOIN categories c ON r.category_id = c.id
            WHERE r.is_public = 1 AND r.is_approved = 1 
            /* הסדר: הקריטריון הראשי -> צפיות -> לייקים -> תגובות -> הכי חדש */
            ORDER BY $orderBy DESC, views DESC, likes_count DESC, comments_count DESC, r.id DESC 
            LIMIT 12"; 
    return $pdo->query($sql)->fetchAll();
}

// 2. שליפת הנתונים ל-3 האזורים
$topLiked = getRecipesByOrder($pdo, 'likes_count');
$topViews = getRecipesByOrder($pdo, 'views');
$topComments = getRecipesByOrder($pdo, 'comments_count');

$sections = [
    ['id' => 'liked', 'title' => '🔥 הכי אהובים (לייקים)', 'data' => $topLiked, 'badge' => '❤️'],
    ['id' => 'views', 'title' => '📈 הכי נצפים', 'data' => $topViews, 'badge' => '👁️'],
    ['id' => 'comments', 'title' => '💬 הכי מדוברים', 'data' => $topComments, 'badge' => '💬']
];
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecipeMaster | דף הבית</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card-bg: rgba(255,255,255,0.05); --danger: #ff4757; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 80px; scroll-behavior: smooth; }
        
        .header-container { background: rgba(15, 23, 42, 0.8); border-bottom: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(15px); position: sticky; top: 0; z-index: 1000; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 25px; }
        .main-search { width: 100%; padding: 12px 20px; border-radius: 50px; background: rgba(255,255,255,0.05); border: 1px solid var(--accent); color: white; outline: none; transition: 0.3s; }
        .main-search:focus { box-shadow: 0 0 15px rgba(0, 242, 254, 0.3); background: rgba(255,255,255,0.1); }

        .cat-bar { display: flex; gap: 12px; padding: 15px 25px; overflow-x: auto; scrollbar-width: none; }
        .cat-bar::-webkit-scrollbar { display: none; }
        .cat-link { white-space: nowrap; padding: 8px 18px; background: var(--card-bg); border-radius: 50px; color: white; text-decoration: none; font-size: 0.9rem; border: 1px solid rgba(255,255,255,0.1); transition: 0.3s; }
        .cat-link:hover { background: var(--accent); color: var(--bg); }

        .admin-tools { background: rgba(255, 118, 117, 0.1); padding: 8px 25px; display: flex; gap: 20px; align-items: center; font-size: 0.85rem; border-bottom: 1px solid rgba(255, 118, 117, 0.2); }
        .notification-badge { background: linear-gradient(45deg, #ff4757, #ff6b81); color: white; font-size: 0.75rem; padding: 2px 8px; border-radius: 50px; margin-right: 6px; animation: pulse 2s infinite; }
        
        .recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; padding: 20px; }
        .recipe-card { background: var(--card-bg); border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; text-decoration: none; color: white; position: relative; }
        .recipe-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        .img-wrapper { width: 100%; aspect-ratio: 16/10; overflow: hidden; position: relative; }
        .recipe-img { width: 100%; height: 100%; object-fit: cover; transition: 0.4s; }
        .recipe-card:hover .recipe-img { transform: scale(1.1); }
        
        .stat-badge { position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); color: white; padding: 5px 10px; border-radius: 10px; font-size: 0.8rem; backdrop-filter: blur(5px); border: 1px solid rgba(255,255,255,0.1); }

        .shopping-cart-float { position: fixed; bottom: 30px; left: 30px; background: linear-gradient(45deg, #ff4757, #ff6b81); color: white; padding: 15px 25px; border-radius: 50px; text-decoration: none; font-weight: bold; display: none; box-shadow: 0 10px 25px rgba(255, 71, 87, 0.4); z-index: 1000; transition: 0.3s; align-items: center; }
        .notebook-btn { position: fixed; bottom: 30px; right: 30px; background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; padding: 15px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; z-index: 999; box-shadow: 0 10px 20px rgba(0,0,0,0.3); }

        .user-avatar-small { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; border: 1px solid var(--accent); }
        .hidden-item { display: none; }
        .toggle-btn { display: block; width: 220px; margin: 10px auto 40px; padding: 12px; background: transparent; border: 1px solid var(--accent); color: var(--accent); border-radius: 50px; cursor: pointer; font-weight: bold; transition: 0.3s; }
        .toggle-btn:hover { background: var(--accent); color: var(--bg); }

        @keyframes pulse { 0% { transform: scale(1); } 50% { transform: scale(1.1); } 100% { transform: scale(1); } }
    </style>
</head>
<body>

<div class="header-container">
    <nav class="navbar">
        <div style="font-size: 1.6rem; font-weight: bold; color: var(--accent);">RecipeMaster 👨‍🍳</div>
        <div style="display: flex; gap: 15px; align-items: center;">
            <?php if($isLoggedIn): ?>
                <div style="display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,0.05); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(0, 242, 254, 0.3);">
                    <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?? 'user-default.png'); ?>" class="user-avatar-small">
                    <span style="font-weight: bold; font-size: 0.85rem;"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                </div>
                <a href="add_recipe.php" style="background: var(--accent); color: var(--bg); padding: 8px 15px; border-radius: 10px; text-decoration: none; font-weight: bold; font-size: 0.9rem;">+ חדש</a>
                <a href="settings.php" title="הגדרות">⚙️</a>
                <a href="logout.php" style="color: var(--danger); text-decoration: none; font-weight: bold; font-size: 0.9rem;">יציאה</a>
            <?php else: ?>
                <a href="login.php" style="background: var(--accent); color: var(--bg); padding: 8px 20px; border-radius: 50px; text-decoration: none; font-weight: bold;">התחברות 🔑</a>
            <?php endif; ?>
        </div>
    </nav>

    <div style="padding: 0 25px 15px;">
        <input type="text" id="mainSearch" class="main-search" placeholder="🔍 חפש מתכון בכל האזורים..." onkeyup="filterAllSections()">
    </div>

    <div class="cat-bar">
        <a href="index.php" class="cat-link" style="background: rgba(0, 242, 254, 0.2); border-color: var(--accent);">🏠 הכל</a>
        <?php foreach ($categories as $c): ?>
            <a href="category.php?id=<?php echo $c['id']; ?>" class="cat-link"><?php echo $c['icon']; ?> <?php echo htmlspecialchars($c['name']); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if($userRole === 'admin'): ?>
    <div class="admin-tools">
        <span style="color: #ff4757; font-weight: bold;">👑 ניהול:</span>
        <a href="manage_categories.php" style="color: white; text-decoration: none;">קטגוריות</a>
        <a href="admin_approval.php" style="color: white; text-decoration: none; display: flex; align-items: center;">
            אישור מתכונים ⚖️
            <?php if($pendingCount > 0): ?>
                <span class="notification-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<main class="container">
    <?php foreach ($sections as $sec): ?>
    <section id="sec-<?php echo $sec['id']; ?>" class="content-section">
        <div style="padding: 25px 25px 0; font-size: 1.4rem; font-weight: bold; color: var(--accent);"><?php echo $sec['title']; ?></div>
        <div class="recipe-grid" id="grid-<?php echo $sec['id']; ?>">
            <?php foreach ($sec['data'] as $index => $r): ?>
            <a href="view_recipe.php?id=<?php echo $r['id']; ?>" 
               class="recipe-card <?php echo ($index >= 4) ? 'hidden-item' : ''; ?>" 
               data-title="<?php echo htmlspecialchars(mb_strtolower($r['title'], 'UTF-8')); ?>">
                <div class="img-wrapper">
                    <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="recipe-img">
                    <div class="stat-badge">
                        <?php 
                            if($sec['id'] == 'liked') echo "❤️ " . $r['likes_count'];
                            elseif($sec['id'] == 'views') echo "👁️ " . $r['views'];
                            else echo "💬 " . $r['comments_count'];
                        ?>
                    </div>
                </div>
                <div style="padding: 15px;">
                    <h3 style="margin:0; font-size: 1.1rem;"><?php echo htmlspecialchars($r['title']); ?></h3>
                    <div style="display: flex; align-items: center; gap: 8px; font-size: 0.8rem; opacity: 0.7; margin-top: 10px;">
                        <img src="<?php echo htmlspecialchars($r['profile_img'] ?: 'user-default.png'); ?>" class="user-avatar-small">
                        <span><?php echo $r['icon']; ?> | <?php echo htmlspecialchars($r['username']); ?></span>
                    </div>
                </div>
            </a>
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

<a href="shopping_list.php" class="shopping-cart-float" id="mainCartBtn">
    <span class="cart-badge" id="mainCartCount">0</span>
    🛒 סל קניות
</a>

<script>
// פונקציית הרחבה/צמצום לכל אזור בנפרד
function toggleSection(secId, btn) {
    const grid = document.getElementById('grid-' + secId);
    const items = grid.querySelectorAll('.recipe-card');
    let isOpening = btn.innerText.includes("ראה עוד");

    items.forEach((item, index) => {
        if (index >= 4) {
            item.style.display = isOpening ? "block" : "none";
        }
    });

    btn.innerText = isOpening ? "הצג פחות 🔼" : "ראה עוד (+" + (items.length - 4) + ")";
}

// חיפוש חכם שסורק את כל האזורים
function filterAllSections() {
    let input = document.getElementById('mainSearch').value.toLowerCase();
    let cards = document.querySelectorAll('.recipe-card');
    let buttons = document.querySelectorAll('.toggle-btn');
    
    cards.forEach(card => {
        let title = card.getAttribute('data-title');
        if (title.includes(input)) {
            card.style.display = "block";
            card.style.opacity = "1";
        } else {
            card.style.display = "none";
        }
    });

    // מחביא כפתורי "ראה עוד" בזמן חיפוש כדי שלא יפריעו לתוצאות
    buttons.forEach(btn => {
        btn.style.display = (input === "") ? "block" : "none";
    });
}

// ניהול סל קניות צף
function checkShoppingList() {
    const currentUser = "<?php echo $_SESSION['username'] ?? 'guest'; ?>";
    const cartKey = 'shopping_list_' + currentUser;
    let list = JSON.parse(localStorage.getItem(cartKey)) || [];
    const btn = document.getElementById('mainCartBtn');
    const count = document.getElementById('mainCartCount');
    if (list.length > 0) {
        btn.style.display = 'flex';
        count.innerText = list.length;
    } else {
        btn.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', checkShoppingList);
</script>
</body>
</html>
