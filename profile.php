<?php
// התחלת סשן ובדיקת חיבור למסד הנתונים
session_start();
require_once 'db.php';

// קבלת ה-ID מהכתובת בצורה מאובטחת
$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($profileId === 0) { header("Location: index.php"); exit; }

// 1. שליפת פרטי המשתמש בעל הפרופיל
$stmt_user = $pdo->prepare("SELECT username, profile_img, role FROM users WHERE id = ?");
$stmt_user->execute([$profileId]);
$profileUser = $stmt_user->fetch();

if (!$profileUser) { die("שגיאה: משתמש לא נמצא."); }

// 2. שליפת סטטיסטיקות מצטברות (צפיות, לייקים ותגובות)
$stmt_stats = $pdo->prepare("
    SELECT 
        SUM(r.views) as total_views,
        (SELECT COUNT(*) FROM likes WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = r.user_id)) as total_likes,
        (SELECT COUNT(*) FROM comments WHERE recipe_id IN (SELECT id FROM recipes WHERE user_id = r.user_id)) as total_comments
    FROM recipes r
    WHERE r.user_id = ?
");
$stmt_stats->execute([$profileId]);
$stats = $stmt_stats->fetch();

$totalViews    = $stats['total_views'] ?? 0;
$totalLikes    = $stats['total_likes'] ?? 0;
$totalComments = $stats['total_comments'] ?? 0;

// 3. שליפת המתכונים כולל נתוני קטגוריה וספירה לכל מתכון בנפרד
$stmt_recipes = $pdo->prepare("
    SELECT r.*, c.icon, c.name as cat_name,
    (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) as likes_count,
    (SELECT COUNT(*) FROM comments WHERE recipe_id = r.id) as comments_count
    FROM recipes r 
    JOIN categories c ON r.category_id = c.id 
    WHERE r.user_id = ? AND r.is_public = 1 AND r.is_approved = 1 
    ORDER BY r.id DESC
");
$stmt_recipes->execute([$profileId]);
$userRecipes = $stmt_recipes->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>הפרופיל של <?php echo htmlspecialchars($profileUser['username']); ?></title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 50px; }
        
        /* עיצוב הראשייה */
        .profile-header { 
            background: linear-gradient(to bottom, rgba(0, 242, 254, 0.15), transparent); 
            padding: 60px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.05); 
            position: relative;
        }
        .back-link { position: absolute; top: 20px; right: 20px; color: var(--accent); text-decoration: none; font-weight: bold; }
        .profile-avatar { width: 130px; height: 130px; border-radius: 50%; border: 4px solid var(--accent); object-fit: cover; margin-bottom: 15px; }

        /* סטטיסטיקות */
        .chef-stats { display: flex; justify-content: center; gap: 15px; margin-top: 20px; flex-wrap: wrap; }
        .stat-box { background: var(--glass); padding: 10px 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.1); min-width: 90px; }
        .stat-num { display: block; font-size: 1.1rem; font-weight: bold; color: var(--accent); }

        .container { max-width: 1100px; margin: 40px auto; padding: 0 20px; }

        /* --- עיצוב חדש לסרגל החיפוש והקטגוריות --- */
        .filter-area { margin-bottom: 40px; display: flex; flex-direction: column; gap: 20px; }
        
        .search-wrapper { position: relative; width: 100%; }
        .search-input { 
            width: 100%; background: var(--glass); border: 1px solid rgba(255,255,255,0.1); 
            padding: 15px 25px; border-radius: 15px; color: white; font-size: 1rem; outline: none; 
            transition: 0.3s; box-sizing: border-box;
        }
        .search-input:focus { border-color: var(--accent); background: rgba(255,255,255,0.1); }

        /* סרגל קטגוריות נגלל אופקית */
        .category-scroll { 
            display: flex; gap: 10px; overflow-x: auto; padding-bottom: 10px; 
            white-space: nowrap; scroll-behavior: smooth;
            -ms-overflow-style: none; scrollbar-width: none; /* הסתרת סרגל גלילה */
        }
        .category-scroll::-webkit-scrollbar { display: none; } /* הסתרה לכרום/ספארי */

        .cat-btn { 
            background: var(--glass); border: 1px solid rgba(255,255,255,0.1); color: white; 
            padding: 10px 20px; border-radius: 50px; cursor: pointer; transition: 0.3s; flex-shrink: 0;
        }
        .cat-btn.active, .cat-btn:hover { background: var(--accent); color: #0f172a; font-weight: bold; }

        /* גריד המתכונים */
        .recipe-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 25px; }
        .recipe-card { background: var(--glass); border-radius: 20px; overflow: hidden; border: 1px solid rgba(255,255,255,0.05); transition: 0.3s; text-decoration: none; color: white; }
        .recipe-card:hover { transform: translateY(-5px); border-color: var(--accent); }
        .img-wrapper { width: 100%; aspect-ratio: 16/10; overflow: hidden; position: relative; }
        .recipe-img { width: 100%; height: 100%; object-fit: cover; }
    </style>
</head>
<body>

    <div class="profile-header">
        <a href="index.php" class="back-link">🏠 חזרה</a>
        <img src="<?php echo htmlspecialchars($profileUser['profile_img'] ?: 'user-default.png'); ?>" class="profile-avatar">
        <h1><?php echo htmlspecialchars($profileUser['username']); ?> <?php echo ($profileUser['role'] === 'admin') ? '👑' : ''; ?></h1>
        
        <div class="chef-stats">
            <div class="stat-box"><span class="stat-num">📔 <?php echo count($userRecipes); ?></span><span style="font-size: 0.7rem; opacity: 0.6;">מתכונים</span></div>
            <div class="stat-box"><span class="stat-num">❤️ <?php echo number_format($totalLikes); ?></span><span style="font-size: 0.7rem; opacity: 0.6;">לייקים</span></div>
            <div class="stat-box"><span class="stat-num">💬 <?php echo number_format($totalComments); ?></span><span style="font-size: 0.7rem; opacity: 0.6;">תגובות</span></div>
            <div class="stat-box"><span class="stat-num">👁️ <?php echo number_format($totalViews); ?></span><span style="font-size: 0.7rem; opacity: 0.6;">צפיות</span></div>
        </div>
    </div>

    <div class="container">
        <div class="filter-area">
            <div class="search-wrapper">
                <input type="text" id="recipeSearch" class="search-input" placeholder="חפש במתכונים של המשתמש...">
            </div>

            <div class="category-scroll">
                <button class="cat-btn active" data-category="all">הכל ✨</button>
                <?php 
                // שליפת רשימת קטגוריות ייחודית רק מהמתכונים שקיימים בדף
                $uniqueCats = [];
                foreach ($userRecipes as $r) { $uniqueCats[$r['category_id']] = ['name'=>$r['cat_name'], 'icon'=>$r['icon']]; }
                foreach ($uniqueCats as $id => $cat): ?>
                    <button class="cat-btn" data-category="<?php echo $id; ?>">
                        <?php echo $cat['icon'] . " " . $cat['name']; ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="recipe-grid" id="recipeGrid">
            <?php foreach ($userRecipes as $r): ?>
                <a href="view_recipe.php?id=<?php echo $r['id']; ?>" 
                   class="recipe-card" 
                   data-title="<?php echo htmlspecialchars(mb_strtolower($r['title'], 'UTF-8')); ?>" 
                   data-cat="<?php echo $r['category_id']; ?>">
                    
                    <div class="img-wrapper">
                        <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="recipe-img" loading="lazy">
                        <div style="position: absolute; top: 10px; right: 10px; background: rgba(0,0,0,0.6); padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; backdrop-filter: blur(5px);">
                            <?php echo $r['icon']; ?> <?php echo htmlspecialchars($r['cat_name']); ?>
                        </div>
                    </div>
                    
                    <div style="padding: 15px;">
                        <h4 style="margin: 0;"><?php echo htmlspecialchars($r['title']); ?></h4>
                        <div style="margin-top: 12px; display: flex; gap: 12px; font-size: 0.8rem; opacity: 0.7;">
                            <span>❤️ <?php echo $r['likes_count']; ?></span>
                            <span>💬 <?php echo $r['comments_count']; ?></span>
                            <span>👁️ <?php echo number_format($r['views'] ?? 0); ?></span>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // אלמנטים של חיפוש וסינון
        const searchInput = document.getElementById('recipeSearch');
        const catBtns = document.querySelectorAll('.cat-btn');
        const cards = document.querySelectorAll('.recipe-card');

        // פונקציית הסינון שמשלבת חיפוש + קטגוריה
        function runFilter() {
            const query = searchInput.value.toLowerCase(); // מה שהוקלד
            const activeCat = document.querySelector('.cat-btn.active').dataset.category; // הקטגוריה שנבחרה

            cards.forEach(card => {
                const title = card.dataset.title;
                const catId = card.dataset.cat;
                
                // בדיקה אם המתכון מתאים גם לחיפוש וגם לקטגוריה
                const matchesSearch = title.includes(query);
                const matchesCat = (activeCat === 'all' || catId === activeCat);

                // הצגה או הסתרה
                card.style.display = (matchesSearch && matchesCat) ? 'block' : 'none';
            });
        }

        // האזנה להקלדה
        searchInput.addEventListener('input', runFilter);

        // האזנה ללחיצה על כפתור קטגוריה
        catBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                catBtns.forEach(b => b.classList.remove('active')); // הסרת סימון מהקודם
                btn.classList.add('active'); // סימון הנוכחי
                runFilter(); // הרצת הסינון
            });
        });
    </script>
</body>
</html>