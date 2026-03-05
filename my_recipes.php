<?php
session_start();
require_once 'db.php';

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$userId = $_SESSION['user_id'];

// --- סנכרון סטטוס בזמן אמת מול מסד הנתונים ---
$stmt_status = $pdo->prepare("SELECT status, role FROM users WHERE id = ?");
$stmt_status->execute([$userId]);
$userData = $stmt_status->fetch();

if ($userData) {
    $_SESSION['status'] = $userData['status'];
    $_SESSION['role'] = $userData['role'];
}

$userRole = $_SESSION['role'] ?? 'user';
$userStatus = ($userRole === 'admin') ? 'approved' : ($_SESSION['status'] ?? 'pending');

// 1. שליפת קטגוריות שבהן המשתמש באמת יצר מתכונים
$stmt_cats = $pdo->prepare("
    SELECT DISTINCT c.* FROM categories c 
    JOIN recipes r ON c.id = r.category_id 
    WHERE r.user_id = ? 
    ORDER BY c.id ASC");
$stmt_cats->execute([$userId]);
$my_categories = $stmt_cats->fetchAll();

// 2. שליפת מתכונים שאהבתי (Liked Recipes)
$stmt_liked = $pdo->prepare("
    SELECT r.*, u.username as author_name 
    FROM recipes r 
    JOIN likes l ON r.id = l.recipe_id 
    JOIN users u ON r.user_id = u.id
    WHERE l.user_id = ? 
    ORDER BY l.id DESC");
$stmt_liked->execute([$userId]);
$liked_recipes = $stmt_liked->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>המחברת שלי | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); --danger: #ff4757; --success: #2ecc71; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 50px; scroll-behavior: smooth; }
        
        .header-section { padding: 40px 30px 10px; max-width: 1200px; margin: 0 auto; }
        
        .welcome-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
            padding: 40px; border-radius: 30px; display: flex; align-items: center; gap: 35px;
            border: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; position: relative;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }

        .back-link { position: absolute; top: 20px; left: 30px; color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .back-link:hover { color: var(--accent); }

        .big-avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid var(--accent); box-shadow: 0 0 25px rgba(0, 242, 254, 0.3); flex-shrink: 0; }
        
        .btn-add-recipe {
            background: linear-gradient(45deg, #00f2fe, #4facfe);
            color: #0f172a;
            padding: 12px 25px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
            box-shadow: 0 5px 15px rgba(0, 242, 254, 0.3);
        }
        .btn-add-recipe:hover { transform: scale(1.05); box-shadow: 0 8px 20px rgba(0, 242, 254, 0.5); }

        .pending-notice {
            background: rgba(255, 165, 2, 0.1);
            border: 1px solid #ffa502;
            color: #ffa502;
            padding: 12px 20px;
            border-radius: 15px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .category-nav { position: sticky; top: 0; z-index: 1000; background: rgba(15, 23, 42, 0.9); backdrop-filter: blur(15px); padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .nav-container { max-width: 1200px; margin: 0 auto; display: flex; justify-content: center; flex-wrap: wrap; gap: 12px; padding: 0 20px; }
        .cat-chip { white-space: nowrap; padding: 10px 20px; border-radius: 50px; background: var(--glass); border: 1px solid rgba(255,255,255,0.1); color: white; text-decoration: none; font-size: 0.95rem; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .cat-chip:hover { border-color: var(--accent); background: rgba(0, 242, 254, 0.1); transform: translateY(-2px); }

        .filter-group { display: flex; gap: 15px; margin-top: 20px; justify-content: flex-start; flex-wrap: wrap; }
        .filter-btn { padding: 10px 22px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.03); color: #94a3b8; cursor: pointer; transition: all 0.3s; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .filter-btn:hover { border-color: var(--accent); }
        .filter-btn.active { border-color: var(--accent); background: var(--accent); color: #0f172a; box-shadow: 0 0 15px rgba(0, 242, 254, 0.4); }
        .filter-btn.liked-btn.active { border-color: var(--danger); background: var(--danger); color: white; box-shadow: 0 0 15px rgba(255, 71, 87, 0.4); }

        .search-bar { width: 100%; padding: 18px; border-radius: 15px; background: var(--glass); border: 1px solid rgba(255,255,255,0.1); color: white; outline: none; box-sizing: border-box; font-size: 1rem; }
        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .card { background: var(--glass); border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); transition: 0.3s; display: flex; flex-direction: column; }
        .card:hover { transform: translateY(-5px); border-color: var(--accent); }
        .recipe-link { text-decoration: none; color: inherit; display: block; flex-grow: 1; }
        .img-wrapper { height: 190px; overflow: hidden; position: relative; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: 0.5s; }
        .status-tag { position: absolute; top: 12px; left: 12px; padding: 5px 10px; border-radius: 8px; font-size: 0.75rem; font-weight: bold; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 2; }
        .liked-tag { position: absolute; top: 12px; right: 12px; background: var(--danger); color: white; padding: 5px 8px; border-radius: 8px; font-size: 0.8rem; z-index: 2; font-weight: bold; }
        
        .card-actions { display: flex; justify-content: space-between; padding: 15px; background: rgba(0,0,0,0.3); border-top: 1px solid rgba(255,255,255,0.05); }
        .btn-edit { color: var(--accent); text-decoration: none; font-weight: bold; }
        .btn-delete { color: var(--danger); text-decoration: none; }
        .section-title { margin-top: 50px; margin-bottom: 25px; color: var(--accent); font-size: 1.8rem; border-right: 5px solid var(--accent); padding-right: 15px; display: flex; align-items: center; gap: 12px; }
    </style>
</head>
<body>

<div class="header-section">
    <div class="welcome-card">
        <a href="index.php" class="back-link">🔙 חזרה לפיד</a>
        <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?: 'user-default.png'); ?>" class="big-avatar">
        <div style="flex-grow: 1;">
            <h1 style="margin: 0; font-size: 2.5rem;">המחברת של <?php echo htmlspecialchars($_SESSION['username']); ?> 📖</h1>
            <p style="margin: 5px 0 15px; color: var(--accent); font-size: 1.1rem;">ניהול כל המתכונים האישיים שלך.</p>
            
            <?php if($userStatus === 'approved'): ?>
                <a href="add_recipe.php" class="btn-add-recipe">
                    <span>➕</span> הוסף מתכון למחברת
                </a>
            <?php else: ?>
                <div class="pending-notice">
                    <span>⏳</span> הפרופיל שלך ממתין לאישור מנהל. בקרוב תוכל להוסיף מתכונים.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <input type="text" id="recipeSearch" class="search-bar" placeholder="🔍 חפש מתכון במחברת..." onkeyup="applyFilters()">
    
    <div class="filter-group">
        <button class="filter-btn active" onclick="setStatusFilter('all', this)"><span>✨</span> הכל</button>
        <button class="filter-btn" onclick="setStatusFilter('public', this)"><span>🌍</span> פומבי</button>
        <button class="filter-btn" onclick="setStatusFilter('private', this)"><span>🔒</span> פרטי</button>
        <button class="filter-btn liked-btn" onclick="setStatusFilter('liked', this)"><span>❤️</span> אהבתי</button>
    </div>
</div>

<nav class="category-nav" id="catNav">
    <div class="nav-container">
        <a href="#" class="cat-chip" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;">✨ הכל</a>
        <?php foreach ($my_categories as $cat): ?>
            <a href="#cat-<?php echo $cat['id']; ?>" class="cat-chip">
                <?php echo $cat['icon'] . " " . htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<div class="container">
    <div id="myRecipesWrapper">
        <?php if(empty($my_categories)): ?>
            <div id="emptyMsg" style="text-align:center; padding:100px; opacity:0.5;">
                <h2>עדיין אין לך מתכונים במחברת... 📔</h2>
            </div>
        <?php endif; ?>

        <?php foreach ($my_categories as $cat): 
            $stmt = $pdo->prepare("SELECT * FROM recipes WHERE user_id = ? AND category_id = ? ORDER BY id DESC");
            $stmt->execute([$userId, $cat['id']]);
            $recipes = $stmt->fetchAll();
        ?>
            <div class="cat-section" id="cat-<?php echo $cat['id']; ?>">
                <h2 class="section-title"><?php echo $cat['icon'] . " " . $cat['name']; ?></h2>
                <div class="grid">
                    <?php foreach ($recipes as $r): ?>
                    <div class="card recipe-card" data-title="<?php echo htmlspecialchars(mb_strtolower($r['title'], 'UTF-8')); ?>" data-status="<?php echo $r['is_public'] ? 'public' : 'private'; ?>" data-origin="my">
                        <a href="view_recipe.php?id=<?php echo $r['id']; ?>" class="recipe-link">
                            <div class="img-wrapper">
                                <div class="status-tag"><?php echo $r['is_public'] ? '🌍 פומבי' : '🔒 פרטי'; ?></div>
                                <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="card-img">
                            </div>
                            <div style="padding: 18px;">
                                <h4 style="margin:0; font-size: 1.2rem;"><?php echo htmlspecialchars($r['title']); ?></h4>
                            </div>
                        </a>
                        <div class="card-actions">
                            <a href="edit_recipe.php?id=<?php echo $r['id']; ?>" class="btn-edit">✏️ עריכה</a>
                            <a href="delete_recipe.php?id=<?php echo $r['id']; ?>" class="btn-delete" onclick="return confirm('בטוח שרוצה למחוק?')">🗑️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="likedRecipesWrapper" style="display: none;">
        <h2 class="section-title">❤️ מתכונים ששמרת מהקהילה</h2>
        <div class="grid">
            <?php foreach ($liked_recipes as $lr): ?>
            <div class="card recipe-card" data-title="<?php echo htmlspecialchars(mb_strtolower($lr['title'], 'UTF-8')); ?>" data-status="liked" data-origin="liked">
                <a href="view_recipe.php?id=<?php echo $lr['id']; ?>" class="recipe-link">
                    <div class="img-wrapper">
                        <div class="liked-tag">אהבתי ❤️</div>
                        <img src="<?php echo htmlspecialchars($lr['image_url'] ?: 'default.jpg'); ?>" class="card-img">
                    </div>
                    <div style="padding: 18px;">
                        <h4 style="margin:0; font-size: 1.2rem;"><?php echo htmlspecialchars($lr['title']); ?></h4>
                        <p style="margin: 5px 0 0; font-size: 0.85rem; opacity: 0.7;">מאת: <?php echo htmlspecialchars($lr['author_name']); ?></p>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
let currentStatus = 'all';

function setStatusFilter(status, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentStatus = status;

    // ניהול תצוגת אזורים
    const mySection = document.getElementById('myRecipesWrapper');
    const likedSection = document.getElementById('likedRecipesWrapper');
    const catNav = document.getElementById('catNav');

    if (status === 'liked') {
        mySection.style.display = 'none';
        likedSection.style.display = 'block';
        catNav.style.display = 'none';
    } else {
        mySection.style.display = 'block';
        likedSection.style.display = 'none';
        catNav.style.display = 'block';
    }
    
    applyFilters();
}

function applyFilters() {
    let searchText = document.getElementById('recipeSearch').value.toLowerCase();
    let cards = document.querySelectorAll('.recipe-card');
    let sections = document.querySelectorAll('.cat-section');

    cards.forEach(card => {
        let title = card.getAttribute('data-title');
        let status = card.getAttribute('data-status');
        let matchesSearch = title.includes(searchText);
        
        let matchesStatus = false;
        if (currentStatus === 'all') {
            matchesStatus = (status === 'public' || status === 'private');
        } else if (currentStatus === 'liked') {
            matchesStatus = (status === 'liked');
        } else {
            matchesStatus = (status === currentStatus);
        }

        card.style.display = (matchesSearch && matchesStatus) ? "flex" : "none";
    });

    // הסתרת קטגוריות ריקות
    sections.forEach(section => {
        const visibleCards = section.querySelectorAll('.card[style="display: flex;"]');
        section.style.display = (visibleCards.length > 0) ? "block" : "none";
    });
}
</script>

</body>
</html>
