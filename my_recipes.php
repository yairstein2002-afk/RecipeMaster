<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';
$userId = $_SESSION['user_id'];

// שליפת קטגוריות שבהן המשתמש באמת יצר מתכונים
$stmt_cats = $pdo->prepare("
    SELECT DISTINCT c.* FROM categories c 
    JOIN recipes r ON c.id = r.category_id 
    WHERE r.user_id = ? 
    ORDER BY c.id ASC");
$stmt_cats->execute([$userId]);
$my_categories = $stmt_cats->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>המחברת שלי | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 50px; scroll-behavior: smooth; }
        
        /* ראש הדף המעודכן עם הברכה והתמונה */
        .header-section { padding: 40px 30px 10px; max-width: 1200px; margin: 0 auto; }
        
        .welcome-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.01) 100%);
            padding: 40px;
            border-radius: 30px;
            display: flex;
            align-items: center;
            gap: 35px;
            border: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
            position: relative;
        }

        .back-link {
            position: absolute;
            top: 20px;
            left: 30px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 0.9rem;
            transition: 0.3s;
        }
        .back-link:hover { color: var(--accent); }

        .big-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--accent);
            box-shadow: 0 0 30px rgba(0, 242, 254, 0.4);
            flex-shrink: 0; /* מונע הקטנה במסכים קטנים */
        }
        
        /* סרגל ניווט דביק */
        .category-nav { 
            position: sticky; top: 0; z-index: 1000; 
            background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(15px);
            padding: 15px 0; border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 30px;
        }
        .nav-container { 
            max-width: 1200px; margin: 0 auto; display: flex; 
            gap: 10px; overflow-x: auto; padding: 0 20px; 
            scrollbar-width: none;
        }
        .nav-container::-webkit-scrollbar { display: none; }
        
        .cat-chip { 
            white-space: nowrap; padding: 8px 18px; border-radius: 50px; 
            background: var(--glass); border: 1px solid rgba(255,255,255,0.1);
            color: white; text-decoration: none; font-size: 0.9rem; transition: 0.3s;
        }
        .cat-chip:hover, .cat-chip.active { border-color: var(--accent); background: rgba(0, 242, 254, 0.1); }

        /* כפתורי סינון סטטוס וחיפוש */
        .filter-group { display: flex; gap: 10px; margin-top: 15px; }
        .filter-btn { padding: 6px 15px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: transparent; color: white; cursor: pointer; transition: 0.2s; font-size: 0.85rem; }
        .filter-btn.active { border-color: var(--accent); color: var(--accent); background: rgba(0, 242, 254, 0.05); }

        .search-bar { width: 100%; padding: 15px; border-radius: 15px; background: var(--glass); border: 1px solid rgba(255,255,255,0.1); color: white; margin-bottom: 10px; outline: none; box-sizing: border-box; }

        .container { max-width: 1200px; margin: 0 auto; padding: 0 20px; }
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        
        /* כרטיס מתכון */
        .card { 
            background: var(--glass); border-radius: 20px; overflow: hidden; 
            border: 1px solid rgba(255,255,255,0.1); transition: 0.3s; position: relative;
        }
        .card:hover { transform: translateY(-5px); border-color: var(--accent); }
        .img-wrapper { height: 180px; overflow: hidden; position: relative; }
        .card-img { width: 100%; height: 100%; object-fit: cover; }
        
        .status-tag { position: absolute; top: 10px; left: 10px; padding: 4px 8px; border-radius: 6px; font-size: 0.7rem; font-weight: bold; background: rgba(0,0,0,0.6); backdrop-filter: blur(4px); }

        .card-actions { 
            display: flex; justify-content: space-between; padding: 15px; 
            background: rgba(0,0,0,0.2); border-top: 1px solid rgba(255,255,255,0.05);
            align-items: center;
        }
        .btn-edit { color: var(--accent); text-decoration: none; font-weight: bold; font-size: 0.9rem; }
        .btn-delete { color: #ff7675; text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .btn-delete:hover { color: #d63031; text-shadow: 0 0 5px rgba(255, 118, 117, 0.3); }

        .section-title { margin-top: 40px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: var(--accent); }
    </style>
</head>
<body>

<div class="header-section">
    <div class="welcome-card">
        <a href="index.php" class="back-link">🔙 חזרה לפיד</a>
        
        <img src="<?php echo htmlspecialchars($_SESSION['profile_img'] ?: 'user-default.png'); ?>" class="big-avatar">
        
        <div>
            <h1 style="margin: 0; font-size: 2.5rem;">המחברת של <?php echo htmlspecialchars($_SESSION['username']); ?> 📖</h1>
            <p style="margin: 5px 0 0; font-size: 1.1rem; color: var(--accent); opacity: 0.9;">כאן נשמרות כל היצירות, הסודות והמתכונים שלך.</p>
        </div>
    </div>
    
    <input type="text" id="recipeSearch" class="search-bar" placeholder="🔍 חפש מתכון במחברת..." onkeyup="applyFilters()">
    
    <div class="filter-group">
        <button class="filter-btn active" onclick="setStatusFilter('all', this)">הכל</button>
        <button class="filter-btn" onclick="setStatusFilter('public', this)">🌍 פומבי</button>
        <button class="filter-btn" onclick="setStatusFilter('private', this)">🔒 פרטי</button>
    </div>
</div>

<nav class="category-nav">
    <div class="nav-container">
        <a href="#" class="cat-chip" onclick="window.scrollTo({top: 0, behavior: 'smooth'}); return false;">הכל ✨</a>
        
        <?php foreach ($my_categories as $cat): ?>
            <a href="#cat-<?php echo $cat['id']; ?>" class="cat-chip">
                <?php echo $cat['icon'] . " " . htmlspecialchars($cat['name']); ?>
            </a>
        <?php endforeach; ?>
    </div>
</nav>

<div class="container">
    <?php foreach ($my_categories as $cat): 
        // שליפת המתכונים לקטגוריה ספציפית
        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE user_id = ? AND category_id = ? ORDER BY id DESC");
        $stmt->execute([$userId, $cat['id']]);
        $recipes = $stmt->fetchAll();
    ?>
        <div class="cat-section" id="cat-<?php echo $cat['id']; ?>">
            <h2 class="section-title"><?php echo $cat['icon'] . " " . $cat['name']; ?></h2>
            <div class="grid">
                <?php foreach ($recipes as $r): ?>
                <div class="card" 
                     data-title="<?php echo htmlspecialchars(mb_strtolower($r['title'], 'UTF-8')); ?>"
                     data-status="<?php echo $r['is_public'] ? 'public' : 'private'; ?>">
                    
                    <div class="img-wrapper">
                        <div class="status-tag">
                            <?php echo $r['is_public'] ? '🌍 פומבי' : '🔒 פרטי'; ?>
                        </div>
                        <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="card-img">
                    </div>
                    
                    <div style="padding: 15px;">
                        <h4 style="margin:0;"><?php echo htmlspecialchars($r['title']); ?></h4>
                    </div>
                    
                    <div class="card-actions">
                        <a href="edit_recipe.php?id=<?php echo $r['id']; ?>" class="btn-edit">עריכה ✏️</a>
                        <a href="view_recipe.php?id=<?php echo $r['id']; ?>" style="color:white; text-decoration:none; font-size:0.8rem;">צפייה</a>
                        <a href="delete_recipe.php?id=<?php echo $r['id']; ?>" class="btn-delete" 
                           onclick="return confirm('בטוח שרוצה למחוק?')">מחיקה 🗑️</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
// לוגיקת הסינון והחיפוש המקורית שלך נשארה ללא שינוי
let currentStatus = 'all';

function setStatusFilter(status, btn) {
    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    currentStatus = status;
    applyFilters();
}

function applyFilters() {
    let searchText = document.getElementById('recipeSearch').value.toLowerCase();
    let cards = document.querySelectorAll('.card');
    let sections = document.querySelectorAll('.cat-section');

    cards.forEach(card => {
        let title = card.getAttribute('data-title');
        let status = card.getAttribute('data-status');
        
        let matchesSearch = title.includes(searchText);
        let matchesStatus = (currentStatus === 'all' || status === currentStatus);
        
        if (matchesSearch && matchesStatus) {
            card.style.display = "block";
        } else {
            card.style.display = "none";
        }
    });

    // הסתרת קטגוריות ריקות אחרי הסינון
    sections.forEach(section => {
        const visibleCards = section.querySelectorAll('.card[style="display: block;"]');
        section.style.display = (visibleCards.length > 0) ? "block" : "none";
    });
}
</script>

</body>
</html>
