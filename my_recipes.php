<?php
session_start();
// אבטחה: רק משתמש מחובר יכול לגשת למחברת האישית
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

require_once 'db.php';
$userId = $_SESSION['user_id'];

// 1. שליפת קטגוריות שקיימים בהן מתכונים של המשתמש בלבד
// השאילתה מבטיחה שיוצגו רק קטגוריות שבהן העלית לפחות מתכון אחד
$stmt_cats = $pdo->prepare("
    SELECT DISTINCT c.* FROM categories c
    JOIN recipes r ON c.id = r.category_id
    WHERE r.user_id = ?
    ORDER BY c.id ASC
");
$stmt_cats->execute([$userId]);
$my_categories = $stmt_cats->fetchAll();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>המחברת שלי | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { 
            background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; 
            margin: 0; padding: 20px; scroll-behavior: smooth; 
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
        /* סרגל קטגוריות צף - מציג רק מה שיש בו תוכן */
        .cat-nav { 
            display: flex; gap: 10px; overflow-x: auto; padding: 15px 0; 
            position: sticky; top: 0; background: #0f172a; z-index: 100; 
            margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .cat-chip { 
            padding: 8px 18px; background: rgba(255,255,255,0.05); border-radius: 50px; 
            white-space: nowrap; color: white; text-decoration: none; 
            border: 1px solid rgba(255,255,255,0.1); transition: 0.3s;
        }
        .cat-chip:hover { background: var(--accent); color: #0f172a; }

        .search-bar { 
            width: 100%; padding: 15px; border-radius: 15px; 
            background: rgba(255,255,255,0.05); border: 1px solid var(--accent); 
            color: white; margin-bottom: 30px; outline: none; 
        }

        /* גריד ואפקט ה-Zoom שביקשת */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 20px; }
        .card { 
            background: rgba(255,255,255,0.03); border-radius: 15px; overflow: hidden; 
            border: 1px solid rgba(255,255,255,0.1); transition: 0.3s; position: relative; 
        }
        .card:hover { border-color: var(--accent); transform: translateY(-3px); }
        
        .img-wrapper { height: 160px; overflow: hidden; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: 0.4s; }
        .card:hover .card-img { transform: scale(1.1); } /* הגדלת תמונה במעבר עכבר */

        /* כפתור "ראה עוד/פחות" */
        .toggle-btn { 
            display: block; width: 100%; padding: 12px; margin-top: 20px; 
            background: rgba(0, 242, 254, 0.05); border: 1px dashed var(--accent); 
            color: var(--accent); cursor: pointer; border-radius: 12px; 
            text-align: center; font-weight: bold; transition: 0.3s;
        }
        .toggle-btn:hover { background: rgba(0, 242, 254, 0.1); }
        
        .hidden-recipe { display: none !important; }
        .cat-section { margin-bottom: 60px; }
    </style>
</head>
<body>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>המחברת שלי 📖</h1>
        <a href="index.php" style="color: #94a3b8; text-decoration: none;">🔙 חזרה לפיד</a>
    </div>
    
    <input type="text" id="recipeSearch" class="search-bar" placeholder="🔍 חפש מתכון במחברת שלך..." onkeyup="filterRecipes()">

    <div class="cat-nav">
        <?php foreach ($my_categories as $cat): ?>
            <a href="#cat-<?php echo $cat['id']; ?>" class="cat-chip"><?php echo $cat['icon'] . " " . $cat['name']; ?></a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($my_categories)): ?>
        <div style="text-align: center; padding: 50px; opacity: 0.5;">
            <h3>המחברת עדיין ריקה...</h3>
            <a href="add_recipe.php" style="color: var(--accent);">הוסף את המתכון הראשון שלך!</a>
        </div>
    <?php endif; ?>

    <?php foreach ($my_categories as $cat): 
        // שליפת המתכונים עבור כל קטגוריה
        $stmt = $pdo->prepare("SELECT * FROM recipes WHERE user_id = ? AND category_id = ? ORDER BY id DESC");
        $stmt->execute([$userId, $cat['id']]);
        $recipes = $stmt->fetchAll();
        ?>
        <div class="cat-section" id="cat-<?php echo $cat['id']; ?>">
            <h2 style="color: var(--accent); border-right: 4px solid var(--accent); padding-right: 15px;">
                <?php echo $cat['icon'] . " " . $cat['name']; ?>
            </h2>
            
            <div class="grid">
                <?php foreach ($recipes as $index => $r): 
                    // הסתרת מתכונים מהחמישי והלאה כברירת מחדל
                    $hiddenClass = ($index >= 4) ? 'hidden-recipe' : ''; 
                ?>
                <div class="card <?php echo $hiddenClass; ?>" data-title="<?php echo htmlspecialchars(mb_strtolower($r['title'], 'UTF-8')); ?>">
                    <div class="img-wrapper">
                        <img src="<?php echo htmlspecialchars($r['image_url'] ?: 'default.jpg'); ?>" class="card-img">
                    </div>
                    <div style="padding: 15px;">
                        <h4 style="margin:0; font-size: 1.1rem;"><?php echo htmlspecialchars($r['title']); ?></h4>
                        <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center;">
                            <a href="view_recipe.php?id=<?php echo $r['id']; ?>" style="color: var(--accent); text-decoration: none; font-size: 0.9rem;">צפייה ←</a>
                            <span style="font-size: 0.8rem; opacity: 0.5;"><?php echo $r['is_public'] ? '🌍' : '🔒'; ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (count($recipes) > 4): ?>
                <button class="toggle-btn" onclick="toggleSection(this)">
                    <span class="btn-text">ראה עוד ב<?php echo $cat['name']; ?> (+<?php echo count($recipes)-4; ?>)</span>
                </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<script>
// פונקציית החלפה בין "ראה עוד" ל"ראה פחות"
function toggleSection(btn) {
    const section = btn.parentElement;
    const cards = section.querySelectorAll('.card');
    const btnText = btn.querySelector('.btn-text');
    let isOpening = btnText.innerText.includes("ראה עוד");

    cards.forEach((card, index) => {
        if (index >= 4) { // משפיע רק על המתכונים שמעבר ל-4 הראשונים
            if (isOpening) {
                card.classList.remove('hidden-recipe');
                card.style.display = 'block';
            } else {
                card.classList.add('hidden-recipe');
                card.style.display = 'none';
            }
        }
    });

    if (isOpening) {
        btnText.innerText = "ראה פחות 🔼";
    } else {
        const remaining = cards.length - 4;
        btnText.innerText = "ראה עוד ב" + section.querySelector('h2').innerText + " (+" + remaining + ")";
        section.scrollIntoView({ behavior: 'smooth' }); // קפיצה חלקה חזרה לראש הקטגוריה
    }
}

// פונקציית חיפוש חיה
function filterRecipes() {
    let input = document.getElementById('recipeSearch').value.toLowerCase();
    let cards = document.querySelectorAll('.card');
    let sections = document.querySelectorAll('.cat-section');
    
    cards.forEach(card => {
        let title = card.getAttribute('data-title');
        if (title.includes(input)) {
            card.style.display = "block";
            card.classList.remove('hidden-recipe'); // בחיפוש מראים הכל ללא הסתרה
        } else {
            card.style.display = "none";
        }
    });

    // הסתרת קטגוריה שלמה אם אין בה תוצאות בחיפוש
    sections.forEach(sec => {
        const visibleInSec = sec.querySelectorAll('.card[style="display: block;"]').length;
        sec.style.display = (visibleInSec > 0 || input === "") ? "block" : "none";
    });
}
</script>
</body>
</html>
