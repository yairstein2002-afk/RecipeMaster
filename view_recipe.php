<?php
session_start();
require_once 'db.php';

$recipeId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'] ?? null; 
$userRole = $_SESSION['role'] ?? 'guest'; 

// --- 1. מונה צפיות חכם ---
if ($recipeId && !isset($_SESSION['viewed_recipes'][$recipeId])) {
    $pdo->prepare("UPDATE recipes SET views = views + 1 WHERE id = ?")->execute([$recipeId]);
    $_SESSION['viewed_recipes'][$recipeId] = true;
}

// --- 2. לוגיקת אדמין (פרטי/ציבורי) ---
if (isset($_POST['toggle_privacy']) && $userRole === 'admin') {
    $stmt_status = $pdo->prepare("SELECT is_public FROM recipes WHERE id = ?");
    $stmt_status->execute([$recipeId]);
    $newStatus = ($stmt_status->fetchColumn() == 1) ? 0 : 1;
    $pdo->prepare("UPDATE recipes SET is_public = ?, is_approved = ? WHERE id = ?")->execute([$newStatus, $newStatus, $recipeId]);
    header("Location: view_recipe.php?id=$recipeId"); exit;
}

// --- 3. לייקים ---
if (isset($_POST['toggle_like']) && $userId) {
    $check = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND recipe_id = ?");
    $check->execute([$userId, $recipeId]);
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND recipe_id = ?")->execute([$userId, $recipeId]);
    } else {
        $pdo->prepare("INSERT INTO likes (user_id, recipe_id) VALUES (?, ?)")->execute([$userId, $recipeId]);
    }
    header("Location: view_recipe.php?id=$recipeId"); exit;
}

// --- 4. תגובות ---
if (isset($_POST['submit_comment']) && $userId && !empty(trim($_POST['comment_text']))) {
    $parentId = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $pdo->prepare("INSERT INTO comments (recipe_id, user_id, comment_text, parent_id) VALUES (?, ?, ?, ?)")
        ->execute([$recipeId, $userId, $_POST['comment_text'], $parentId]);
    header("Location: view_recipe.php?id=$recipeId#comments"); exit;
}

// שליפת נתונים
$recipe = $pdo->prepare("SELECT r.*, u.username, u.profile_img, (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) as likes_count, (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id AND user_id = ?) as user_liked FROM recipes r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$recipe->execute([$userId, $recipeId]);
$recipe = $recipe->fetch();

if (!$recipe) die("המתכון לא נמצא.");

$ingredients = $pdo->prepare("SELECT * FROM ingredients WHERE recipe_id = ?");
$ingredients->execute([$recipeId]); $ingredients = $ingredients->fetchAll();

$instructions = $pdo->prepare("SELECT * FROM instructions WHERE recipe_id = ? ORDER BY id ASC");
$instructions->execute([$recipeId]); $instructions = $instructions->fetchAll();

$allComments = $pdo->prepare("SELECT c.*, u.username, u.profile_img FROM comments c JOIN users u ON c.user_id = u.id WHERE c.recipe_id = ? ORDER BY c.created_at ASC");
$allComments->execute([$recipeId]);
$commentsByParent = [];
foreach ($allComments->fetchAll() as $c) { $commentsByParent[$c['parent_id'] ?? 0][] = $c; }

function getYouTubeEmbed($url) {
    if (preg_match('/(?:v=|shorts\/|be\/)([^&?\/]+)/', $url, $match)) return "https://www.youtube.com/embed/" . $match[1];
    return null;
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['title']); ?> | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); --danger: #ff4757; --wa: #25D366; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 50px; }
        .hero { width: 100%; height: 350px; background: url('<?php echo htmlspecialchars($recipe['image_url']); ?>') center/cover; position: relative; }
        .hero-overlay { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, var(--bg)); padding: 30px; text-align: center; }
        .container { max-width: 900px; margin: -30px auto 0; padding: 0 20px; }
        .meta-bar { display: flex; justify-content: space-between; align-items: center; background: var(--glass); backdrop-filter: blur(15px); padding: 15px 25px; border-radius: 50px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 30px; gap: 10px; }
        .btn-action { background: none; border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 15px; border-radius: 50px; cursor: pointer; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; }
        .btn-action.active { border-color: var(--danger); color: var(--danger); }
        .grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; }
        .card { background: var(--glass); padding: 25px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); }
        .replies-container { display: none; margin-right: 40px; margin-top: 10px; border-right: 2px solid var(--accent); padding-right: 15px; }
        textarea { width: 100%; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 15px; padding: 12px; resize: none; box-sizing: border-box; }
        .btn-submit { background: var(--accent); color: var(--bg); border: none; padding: 8px 20px; border-radius: 50px; font-weight: bold; cursor: pointer; }
        @media (max-width: 800px) { .grid { grid-template-columns: 1fr; } .meta-bar { flex-direction: column; border-radius: 20px; padding: 20px; } }
    </style>
</head>
<body>

<div class="hero"><div class="hero-overlay"><h1><?php echo htmlspecialchars($recipe['title']); ?></h1></div></div>

<div class="container">
    <div class="meta-bar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <img src="<?php echo $recipe['profile_img'] ?: 'user-default.png'; ?>" style="width:45px; height:45px; border-radius:50%; border: 2px solid var(--accent);">
            <div><b><?php echo htmlspecialchars($recipe['username']); ?></b><br><small>👁️ <?php echo $recipe['views']; ?> צפיות</small></div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
            <?php if($userId): ?>
                <a href="my_recipes.php" class="btn-action" style="background: rgba(0, 242, 254, 0.1); border-color: var(--accent); color: var(--accent);">📖 המחברת שלי</a>
            <?php endif; ?>

            <a href="shopping_list.php" class="btn-action">🛒 הסל שלי</a>
            <a href="#" class="btn-action" style="color: var(--wa); border-color: var(--wa);" onclick="shareWA(event)">שיתוף 📱</a>
            
            <?php if($userRole === 'admin'): ?>
                <form method="POST" style="margin:0;"><button type="submit" name="toggle_privacy" class="btn-action" style="color: var(--danger); border-color: var(--danger);">
                    <?php echo ($recipe['is_public'] == 1) ? '🔒 לפרטי' : '🌍 לציבורי'; ?>
                </button></form>
            <?php endif; ?>

            <?php if($userId): ?>
                <form method="POST" style="margin:0;"><button type="submit" name="toggle_like" class="btn-action <?php echo $recipe['user_liked'] ? 'active' : ''; ?>">
                    <?php echo $recipe['user_liked'] ? '❤️' : '🤍'; ?> <?php echo $recipe['likes_count']; ?>
                </button></form>
            <?php endif; ?>
            <a href="index.php" class="btn-action">🏠 הביתה</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>🛒 מצרכים</h3>
            <?php foreach ($ingredients as $ing): 
                $line = trim($ing['amount'] . " " . $ing['ingredient_name'] . " " . ($ing['ingredient_description'] ? "({$ing['ingredient_description']})" : ""));
            ?>
                <label style="display:flex; gap:10px; margin-bottom:8px; cursor: pointer;">
                    <input type="checkbox" class="ing-checkbox" data-name="<?php echo htmlspecialchars($ing['ingredient_name']); ?>" onchange="updateList(this, '<?php echo $ing['amount']; ?>', '<?php echo addslashes($ing['ingredient_name']); ?>', '<?php echo addslashes($line); ?>')">
                    <?php echo htmlspecialchars($line); ?>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h3>👨‍🍳 הכנה</h3>
            <?php foreach ($instructions as $i => $ins): ?>
                <p><b><?php echo $i+1; ?>.</b> <?php echo nl2br(htmlspecialchars($ins['instruction_text'])); ?></p>
            <?php endforeach; ?>
            <?php if($embed = getYouTubeEmbed($recipe['video_url'])): ?>
                <iframe src="<?php echo $embed; ?>" style="width:100%; aspect-ratio:16/9; border-radius:15px; border:none; margin-top:20px;"></iframe>
            <?php endif; ?>
        </div>
    </div>

    <div class="comments-area" id="comments">
        <h3>💬 תגובות</h3>
        <?php if($userId): ?>
            <form method="POST" style="margin-bottom: 25px;">
                <textarea name="comment_text" rows="2" placeholder="כתבו תגובה..." required></textarea>
                <button type="submit" name="submit_comment" class="btn-submit">פרסם</button>
            </form>
        <?php endif; ?>

        <?php 
        function renderComments($parentId, $commentsByParent, $userId) {
            if (!isset($commentsByParent[$parentId])) return;
            foreach ($commentsByParent[$parentId] as $c) { ?>
                <div class="comment-node">
                    <div class="comment-main">
                        <div style="display:flex; align-items:center; gap:8px; margin-bottom:5px;">
                            <img src="<?php echo $c['profile_img']; ?>" style="width:30px; height:30px; border-radius:50%;">
                            <b style="font-size:0.9rem;"><?php echo htmlspecialchars($c['username']); ?></b>
                        </div>
                        <div style="font-size:0.95rem;"><?php echo nl2br(htmlspecialchars($c['comment_text'])); ?></div>
                        <div style="display:flex; gap:15px; align-items:center;">
                            <?php if($userId): ?>
                                <button class="btn-submit" style="background:none; color:var(--accent); font-size:0.8rem; padding:0;" onclick="toggleDiv('reply-f-<?php echo $c['id']; ?>')">השב ↩️</button>
                            <?php endif; ?>
                            <?php if(isset($commentsByParent[$c['id']])): ?>
                                <button class="btn-submit" style="background:none; color:var(--accent); font-size:0.8rem; padding:0;" onclick="toggleReplies(<?php echo $c['id']; ?>)">ראה תגובות ▼</button>
                            <?php endif; ?>
                        </div>
                        <div id="reply-f-<?php echo $c['id']; ?>" style="display:none; margin-top:10px;">
                            <form method="POST"><input type="hidden" name="parent_id" value="<?php echo $c['id']; ?>"><textarea name="comment_text" rows="1" placeholder="תשובה..."></textarea><button type="submit" name="submit_comment" class="btn-submit" style="margin-top:5px; padding:4px 10px;">שלח</button></form>
                        </div>
                    </div>
                    <div id="replies-<?php echo $c['id']; ?>" class="replies-container">
                        <?php renderComments($c['id'], $commentsByParent, $userId); ?>
                    </div>
                </div>
            <?php }
        }
        renderComments(0, $commentsByParent, $userId);
        ?>
    </div>
</div>

<script>
function shareWA(e) { e.preventDefault(); window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent("תראו איזה מתכון מעולה: " + window.location.href), "_blank"); }
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'block') ? 'none' : 'block'; }
function toggleReplies(id) { const el = document.getElementById('replies-' + id); el.style.display = (el.style.display === 'block') ? 'none' : 'block'; }

const currentUser = "<?php echo $_SESSION['username'] ?? 'guest'; ?>";
const cartKey = 'shopping_list_' + currentUser;

function updateList(cb, amount, name, fullDescription) {
    let list = JSON.parse(localStorage.getItem(cartKey)) || [];
    const recipeId = "<?php echo $recipeId; ?>";
    // מזהה ייחודי שכולל את המתכון והשם כדי למנוע כפילויות מאותו מתכון
    const itemIdentifier = recipeId + "_" + name; 

    if (cb.checked) {
        // מוסיפים אובייקט עם כמות מספרית
        list.push({ 
            id: itemIdentifier, 
            amount: parseFloat(amount) || 0, 
            name: name, 
            unit: fullDescription.replace(/[0-9.]/g, '').trim() // מנקה מספרים מהתיאור כדי להישאר עם היחידה (למשל "כוס חלב")
        });
    } else {
        list = list.filter(i => i.id !== itemIdentifier);
    }
    localStorage.setItem(cartKey, JSON.stringify(list));
}

document.addEventListener('DOMContentLoaded', () => {
    const list = JSON.parse(localStorage.getItem(cartKey)) || [];
    document.querySelectorAll('.ing-checkbox').forEach(cb => {
        const name = cb.getAttribute('data-name');
        if (list.some(i => i.id === "<?php echo $recipeId; ?>_" + name)) cb.checked = true;
    });
});
</script>
</body>
</html>
