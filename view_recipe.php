<?php
session_start();
require_once 'db.php';

$recipeId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$userId = $_SESSION['user_id'] ?? null; 
$userRole = $_SESSION['role'] ?? 'guest'; 

if (!$recipeId) { header("Location: index.php"); exit; }



// --- 1. סנכרון סטטוס מול ה-DB בזמן אמת ---
if ($userId) {
    $stmt_check = $pdo->prepare("SELECT status, role FROM users WHERE id = ?");
    $stmt_check->execute([$userId]);
    $dbUser = $stmt_check->fetch();
    if ($dbUser) {
        $_SESSION['status'] = $dbUser['status'];
        $_SESSION['role'] = $dbUser['role'];
    }
}
$userStatus = ($userRole === 'admin') ? 'approved' : ($_SESSION['status'] ?? 'pending');

// שליפת מזהה בעל המתכון
$check_owner = $pdo->prepare("SELECT user_id FROM recipes WHERE id = ?");
$check_owner->execute([$recipeId]);
$recipeOwnerId = $check_owner->fetchColumn();

// --- 2. לוגיקת דיווח על תגובה ---
if (isset($_POST['report_comment']) && $userId && $userStatus === 'approved') {
    $commentId = (int)$_POST['report_comment_id'];
    $reason = $_POST['report_reason'] ?? 'לא צוינה סיבה';
    
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        $pdo->prepare("INSERT INTO notifications (user_id, actor_id, recipe_id, comment_id, report_reason, is_read) VALUES (?, ?, ?, ?, ?, 0)")
            ->execute([$adminId, $userId, $recipeId, $commentId, $reason]);
    }
    header("Location: view_recipe.php?id=$recipeId&reported=1"); exit;
}

// --- 3. מונה צפיות חכם ---
if ($userId) {
    try {
        $stmt_view_log = $pdo->prepare("INSERT IGNORE INTO recipe_views (recipe_id, user_id) VALUES (?, ?)");
        $stmt_view_log->execute([$recipeId, $userId]);
        if ($stmt_view_log->rowCount() > 0) {
            $pdo->prepare("UPDATE recipes SET views = views + 1 WHERE id = ?")->execute([$recipeId]);
        }
    } catch (PDOException $e) { }
}

// --- 4. לוגיקת אדמין (פרטי/ציבורי) ---
if (isset($_POST['toggle_privacy']) && $userRole === 'admin') {
    $stmt_status = $pdo->prepare("SELECT is_public FROM recipes WHERE id = ?");
    $stmt_status->execute([$recipeId]);
    $newStatus = ($stmt_status->fetchColumn() == 1) ? 0 : 1;
    $pdo->prepare("UPDATE recipes SET is_public = ?, is_approved = ? WHERE id = ?")->execute([$newStatus, $newStatus, $recipeId]);
    header("Location: view_recipe.php?id=$recipeId"); exit;
}

// --- 5. לייקים ---
if (isset($_POST['toggle_like']) && $userId && $userStatus === 'approved') {
    $check = $pdo->prepare("SELECT id FROM likes WHERE user_id = ? AND recipe_id = ?");
    $check->execute([$userId, $recipeId]);
    
    if ($check->fetch()) {
        $pdo->prepare("DELETE FROM likes WHERE user_id = ? AND recipe_id = ?")->execute([$userId, $recipeId]);
    } else {
        $pdo->prepare("INSERT INTO likes (user_id, recipe_id) VALUES (?, ?)")->execute([$userId, $recipeId]);
        if ($recipeOwnerId && $recipeOwnerId != $userId) {
            $pdo->prepare("INSERT INTO notifications (user_id, actor_id, recipe_id, is_read) VALUES (?, ?, ?, 0)")
                ->execute([$recipeOwnerId, $userId, $recipeId]);
        }
    }
    header("Location: view_recipe.php?id=$recipeId"); exit;
}

// --- 6. תגובות (ניתוב התראות חכם) ---
if (isset($_POST['submit_comment']) && $userId && !empty(trim($_POST['comment_text']))) {
    if ($userStatus !== 'approved') die("גישה חסומה.");
    
    // בדיקה ב-PHP לפני ה-INSERT: האם המשתמש הגיב בדקה האחרונה?
$stmt_check_spam = $pdo->prepare("
    SELECT COUNT(*) FROM comments 
    WHERE user_id = ? AND created_at > NOW() - INTERVAL 1 MINUTE
");
$stmt_check_spam->execute([$userId]);

if ($stmt_check_spam->fetchColumn() > 0) {
    die("נא להמתין דקה בין תגובה לתגובה. ! 😉");
}

// בדיקה נוספת: האם למשתמש הזה כבר יש יותר מ-5 תגובות במתכון הזה?
$stmt_user_limit = $pdo->prepare("
    SELECT COUNT(*) FROM comments WHERE user_id = ? AND recipe_id = ?
");
$stmt_user_limit->execute([$userId, $recipeId]);

if ($stmt_user_limit->fetchColumn() >= 5) {
    die("כתבת כבר 5 תגובות למתכון זה. בוא ניתן הזדמנות לאחרים!");
}

    $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $commentText = $_POST['comment_text'];
    
    $stmt_comm = $pdo->prepare("INSERT INTO comments (recipe_id, user_id, comment_text, parent_id) VALUES (?, ?, ?, ?)");
    $stmt_comm->execute([$recipeId, $userId, $commentText, $parentId]);
    $lastCommentId = $pdo->lastInsertId(); 

    $targetUserId = null;
    if ($parentId) {
        $stmt_p = $pdo->prepare("SELECT user_id FROM comments WHERE id = ?");
        $stmt_p->execute([$parentId]);
        $targetUserId = $stmt_p->fetchColumn();
    } else {
        $targetUserId = $recipeOwnerId;
    }

    if ($targetUserId && $targetUserId != $userId) {
        $pdo->prepare("INSERT INTO notifications (user_id, actor_id, recipe_id, comment_id, is_read) VALUES (?, ?, ?, ?, 0)")
            ->execute([$targetUserId, $userId, $recipeId, $lastCommentId]);
    }
    header("Location: view_recipe.php?id=$recipeId#comments"); exit;
}

// --- שליפת נתונים לתצוגה ---
$recipe_stmt = $pdo->prepare("SELECT r.*, u.username, u.profile_img, 
                (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id) as likes_count, 
                (SELECT COUNT(*) FROM likes WHERE recipe_id = r.id AND user_id = ?) as user_liked,
                (SELECT COUNT(*) FROM recipe_views WHERE recipe_id = r.id) as real_views
                FROM recipes r 
                JOIN users u ON r.user_id = u.id 
                WHERE r.id = ?");
$recipe_stmt->execute([$userId, $recipeId]);
$recipe = $recipe_stmt->fetch();

if (!$recipe) die("המתכון לא נמצא.");

$ingredients = $pdo->prepare("SELECT * FROM ingredients WHERE recipe_id = ?");
$ingredients->execute([$recipeId]); $ingredients = $ingredients->fetchAll();

$instructions = $pdo->prepare("SELECT * FROM instructions WHERE recipe_id = ? ORDER BY id ASC");
$instructions->execute([$recipeId]); $instructions = $instructions->fetchAll();

$allComments = $pdo->prepare("SELECT c.*, u.username, u.profile_img, u.role FROM comments c JOIN users u ON c.user_id = u.id WHERE c.recipe_id = ? ORDER BY c.created_at DESC");
$allComments->execute([$recipeId]);
$commentsByParent = [];
foreach ($allComments->fetchAll() as $c) { $commentsByParent[$c['parent_id'] ?? 0][] = $c; }

function getYouTubeEmbed($url) {
    if (preg_match('/(?:v=|shorts\/|be\/)([^&?\/]+)/', $url, $match)) return "https://www.youtube.com/embed/" . $match[1];
    return null;
}

function renderComments($parentId, $commentsByParent, $userId, $userStatus, $isReply = false) {
    if (!isset($commentsByParent[$parentId])) return;
    foreach ($commentsByParent[$parentId] as $c) { 
        $isAdmin = ($c['role'] === 'admin');
        $childCount = isset($commentsByParent[$c['id']]) ? count($commentsByParent[$c['id']]) : 0;
        ?>
        <div class="comment-node" style="margin-bottom: 20px;">
            <div class="comment-main" style="background: rgba(255,255,255,0.03); padding: 15px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05);">
                <div style="display:flex; justify-content: space-between; align-items:center;">
                    <div style="display:flex; align-items:center; gap:8px;">
                        <img src="<?php echo $c['profile_img'] ?: 'user-default.png'; ?>" style="width:30px; height:30px; border-radius:50%;">
                        <div>
                            <b><?php echo htmlspecialchars($c['username']); ?> <?php echo $isAdmin ? '👑' : ''; ?></b><br>
                            <small style="opacity: 0.5; font-size: 0.75rem;"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></small>
                        </div>
                    </div>
                    <?php if($userId && $userStatus === 'approved' && $userId != $c['user_id']): ?>
                        <button onclick="toggleDiv('report-f-<?php echo $c['id']; ?>')" class="comment-action-btn" style="color: var(--danger); font-size: 0.8rem; border: 1px solid var(--danger); padding: 2px 8px; border-radius: 5px;">🚩 דווח על תגובה</button>
                    <?php endif; ?>
                </div>

                <p style="margin: 12px 0; font-size: 0.95rem; line-height: 1.5;"><?php echo nl2br(htmlspecialchars($c['comment_text'])); ?></p>
                
                <div style="display:flex; gap:15px; align-items: center;">
                    <?php if($userId && $userStatus === 'approved' && !$isReply): ?>
                        <button onclick="toggleDiv('reply-f-<?php echo $c['id']; ?>')" class="comment-action-btn">השב ↩️</button>
                    <?php endif; ?>
                    <?php if($childCount > 0): ?>
                        <button onclick="toggleComments(<?php echo $c['id']; ?>, this)" class="comment-action-btn" data-count="<?php echo $childCount; ?>">הצג <?php echo $childCount; ?> תגובות ▼</button>
                    <?php endif; ?>
                </div>

                <div id="report-f-<?php echo $c['id']; ?>" style="display:none; margin-top:15px; background: rgba(255, 71, 87, 0.15); padding: 15px; border-radius: 12px; border: 1px solid var(--danger);">
                    <h4 style="margin: 0 0 10px 0; font-size: 0.9rem; color: #ff6b81;">דיווח על תגובה פוגענית</h4>
                    <form method="POST">
                        <input type="hidden" name="report_comment_id" value="<?php echo $c['id']; ?>">
                        <textarea name="report_reason" placeholder="פרט כאן את סיבת הדיווח..." 
                                  style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.2); background: #1e293b; color: white; margin-bottom: 10px; font-size: 0.85rem;" required></textarea>
                        <div style="display:flex; gap:10px;">
                            <button type="submit" name="report_comment" class="btn-submit" style="background:var(--danger); font-size:0.8rem; padding: 6px 15px;">שלח דיווח</button>
                            <button type="button" onclick="toggleDiv('report-f-<?php echo $c['id']; ?>')" class="btn-submit" style="background:rgba(255,255,255,0.1); font-size:0.8rem; padding: 6px 15px;">ביטול</button>
                        </div>
                    </form>
                </div>

                <div id="reply-f-<?php echo $c['id']; ?>" style="display:none; margin-top:10px;">
                    <form method="POST">
                        <input type="hidden" name="parent_id" value="<?php echo $c['id']; ?>">
                        <textarea name="comment_text" rows="1" placeholder="כתבו תגובה..."></textarea>
                        <button type="submit" name="submit_comment" class="btn-submit" style="padding:4px 10px; font-size:0.8rem; margin-top:5px;">שלח</button>
                    </form>
                </div>
            </div>
            <?php if (!$isReply && $childCount > 0): ?>
            <div id="replies-<?php echo $c['id']; ?>" class="replies-container" style="display:none; margin-right: 30px; border-right: 2px solid var(--accent); padding-right: 15px; margin-top:10px;">
                <?php renderComments($c['id'], $commentsByParent, $userId, $userStatus, true); ?>
            </div>
            <?php endif; ?>
        </div>
    <?php }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($recipe['title']); ?> | RecipeMaster</title>
<style>
    .load-more-btn {
    width: 100%;
    padding: 12px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--accent);
    color: var(--accent);
    border-radius: 50px;
    cursor: pointer;
    font-weight: bold;
    margin-top: 20px;
    transition: 0.3s;
    backdrop-filter: blur(10px);
}

.load-more-btn:hover {
    background: var(--accent);
    color: var(--bg);
    box-shadow: 0 0 20px rgba(0, 242, 254, 0.4);
}
    :root { 
        --accent: #00f2fe; 
        --bg: #0f172a; 
        --glass: rgba(255, 255, 255, 0.05); 
        --danger: #ff4757; 
        --wa: #25D366; 
        --success: #27ae60;
    }
    
    body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding-bottom: 50px; }
    
    .hero { width: 100%; height: 350px; background: url('<?php echo htmlspecialchars($recipe['image_url']); ?>') center/cover; position: relative; }
    
    .hero-overlay { 
        position: absolute; 
        inset: 0; 
        background: linear-gradient(to bottom, rgba(15, 23, 42, 0), var(--bg)); 
        display: flex; 
        align-items: flex-end; 
        justify-content: center; 
        padding-bottom: 50px; 
    }
    
    .container { max-width: 900px; margin: 0 auto; padding: 0 20px; position: relative; }

    /* עיצוב הודעת הדיווח הירוקה - מתוקן למניעת החלק השחור */
    .success-banner { 
        background: var(--success); 
        color: white; 
        padding: 15px; 
        border-radius: 12px; 
        margin-bottom: 25px; 
        text-align: center; 
        font-weight: bold; 
        position: relative; 
        z-index: 50;
        margin-top: -20px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        border: 1px solid rgba(255,255,255,0.2);
    }

    .meta-bar { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        background: var(--glass); 
        backdrop-filter: blur(15px); 
        padding: 15px 25px; 
        border-radius: 50px; 
        border: 1px solid rgba(255,255,255,0.1); 
        margin-bottom: 30px; 
        gap: 10px; 
        flex-wrap: wrap; 
        position: relative;
        z-index: 5;
    }

    .btn-action { background: none; border: 1px solid rgba(255,255,255,0.2); color: white; padding: 8px 15px; border-radius: 50px; cursor: pointer; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 5px; transition: 0.3s; }
    .btn-action.active { border-color: var(--danger); color: var(--danger); }
    .comment-action-btn { background:none; border:none; color:var(--accent); cursor:pointer; font-size:0.85rem; padding: 5px 0; font-weight: bold; }
    .grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 20px; }
    
    @media (max-width: 768px) { .grid { grid-template-columns: 1fr; } }
    
    .card { background: var(--glass); padding: 25px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); }
    
    textarea { 
        width: 100%; 
        background: rgba(255, 255, 255, 0.05); 
        border: 1px solid rgba(255,255,255,0.1); 
        color: white; 
        border-radius: 15px; 
        padding: 12px; 
        resize: none; 
        box-sizing: border-box; 
    }
    
    .btn-submit { background: var(--accent); color: var(--bg); border: none; padding: 8px 20px; border-radius: 50px; font-weight: bold; cursor: pointer; }
</style>
</head>
<body>

<div class="hero"><div class="hero-overlay"><h1><?php echo htmlspecialchars($recipe['title']); ?></h1></div></div>

<div class="container">
    <?php if(isset($_GET['reported'])): ?>
        <div class="success-banner">✅ הדיווח התקבל ויועבר לטיפול המנהלים. תודה!</div>
    <?php endif; ?>

    <div class="meta-bar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <a href="profile.php?id=<?php echo $recipe['user_id']; ?>" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 10px;">
                <img src="<?php echo $recipe['profile_img'] ?: 'user-default.png'; ?>" style="width:45px; height:45px; border-radius:50%; border: 2px solid var(--accent);">
                <div><b><?php echo htmlspecialchars($recipe['username']); ?></b><br><small>👁️ <?php echo number_format($recipe['real_views']); ?> צפיות</small></div>
            </a>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; justify-content: center;">
            <a href="index.php" class="btn-action">🏠 הביתה</a>
            <?php if($userId): ?>
                <a href="my_recipes.php" class="btn-action" style="background: rgba(0, 242, 254, 0.1); border-color: var(--accent); color: var(--accent);">📖 המחברת שלי</a>
                <form method="POST" style="margin:0;"><button type="submit" name="toggle_like" class="btn-action <?php echo $recipe['user_liked'] ? 'active' : ''; ?>">❤️ <?php echo $recipe['likes_count']; ?></button></form>
            <?php endif; ?>
            <a href="shopping_list.php" class="btn-action">🛒 הסל שלי</a>
            <a href="#" class="btn-action" style="color: var(--wa); border-color: var(--wa);" onclick="shareWA(event)">שיתוף 📱</a>
            <?php if($userRole === 'admin'): ?>
                <form method="POST" style="margin:0;"><button type="submit" name="toggle_privacy" class="btn-action" style="color: #f1c40f; border-color: #f1c40f;"><?php echo ($recipe['is_public'] == 1) ? '🔒 לפרטי' : '🌍 לציבורי'; ?></button></form>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <h3>🛒 מצרכים</h3>
            <?php foreach ($ingredients as $ing): 
                $line = trim($ing['amount'] . " " . $ing['ingredient_name'] . " " . ($ing['ingredient_description'] ? "({$ing['ingredient_description']})" : ""));
            ?>
                <label style="display:flex; gap:10px; margin-bottom:12px; cursor: pointer; align-items: center;">
                    <input type="checkbox" class="ing-checkbox" 
                           data-name="<?php echo htmlspecialchars($ing['ingredient_name']); ?>" 
                           data-line="<?php echo htmlspecialchars($line); ?>"
                           onchange="updateList(this)"
                           style="width:18px; height:18px; accent-color: var(--accent);">
                    <span><?php echo htmlspecialchars($line); ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <div class="card">
            <h3>👨‍🍳 הכנה</h3>
            <?php foreach ($instructions as $i => $ins): ?>
                <p><b><?php echo $i+1; ?>.</b> <?php echo nl2br(htmlspecialchars($ins['instruction_text'])); ?></p>
            <?php endforeach; ?>
            
            <?php if($embed = getYouTubeEmbed($recipe['video_url'])): ?>
                <div style="margin-top:20px;">
                    <iframe src="<?php echo $embed; ?>" style="width:100%; aspect-ratio:16/9; border-radius:15px; border:none;" allowfullscreen></iframe>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="comments-area" id="comments" style="margin-top: 40px;">
        <h3>💬 תגובות</h3>
        <?php if($userId && $userStatus === 'approved'): ?>
            <form method="POST" style="margin-bottom: 25px;">
                <textarea name="comment_text" rows="2" placeholder="כתבו תגובה..." required></textarea>
                <button type="submit" name="submit_comment" class="btn-submit" style="margin-top: 10px;">פרסם ✨</button>
            </form>
        <?php endif; ?>
        <?php renderComments(0, $commentsByParent, $userId, $userStatus); ?>
    </div>
</div>

<script>
function shareWA(e) { e.preventDefault(); window.open("https://api.whatsapp.com/send?text=" + encodeURIComponent("תראו את המתכון הזה: " + window.location.href), "_blank"); }
function toggleDiv(id) { const el = document.getElementById(id); el.style.display = (el.style.display === 'block') ? 'none' : 'block'; }
function toggleComments(id, btn) {
    const container = document.getElementById('replies-' + id);
    const count = btn.getAttribute('data-count');
    if (container.style.display === 'none' || container.style.display === '') {
        container.style.display = 'block'; btn.innerHTML = 'הצג פחות ▲';
    } else {
        container.style.display = 'none'; btn.innerHTML = 'הצג ' + count + ' תגובות ▼';
    }
}

const cartKey = 'shopping_list_<?php echo $_SESSION['username'] ?? 'guest'; ?>';
function updateList(cb) {
    let list = JSON.parse(localStorage.getItem(cartKey)) || [];
    const name = cb.dataset.name; const line = cb.dataset.line;
    const recipeId = "<?php echo $recipeId; ?>";
    const id = recipeId + "_" + name;
    if (cb.checked) { list.push({ id: id, fullText: line }); } 
    else { list = list.filter(i => i.id !== id); }
    localStorage.setItem(cartKey, JSON.stringify(list));
}

document.addEventListener('DOMContentLoaded', () => {
    const list = JSON.parse(localStorage.getItem(cartKey)) || [];
    document.querySelectorAll('.ing-checkbox').forEach(cb => {
        const id = "<?php echo $recipeId; ?>_" + cb.dataset.name;
        if (list.some(i => i.id === id)) cb.checked = true;
    });
});
</script>
</body>
</html>
