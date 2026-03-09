<?php
session_start();
require_once 'db.php';

// אבטחה: רק משתמש מחובר יכול לערוך
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$recipeId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

/**
 * שכבת הגנה: סנכרון סטטוס וחסימה בזמן אמת (סגירת קצה פתוח)
 */
$stmt_check = $pdo->prepare("SELECT status, is_blocked, block_reason FROM users WHERE id = ?");
$stmt_check->execute([$userId]);
$userLock = $stmt_check->fetch();

// אם המשתמש נחסם - ננתק אותו מיד
if (!$userLock || $userLock['is_blocked'] == 1 || $userLock['status'] === 'banned') {
    $reason = urlencode($userLock['block_reason'] ?? 'הפרת תנאי השימוש');
    session_destroy();
    header("Location: login.php?error=is_blocked&reason=" . $reason);
    exit;
}

$userStatus = ($userRole === 'admin') ? 'approved' : $userLock['status'];

if ($userStatus !== 'approved') {
    header("Location: index.php?error=not_approved");
    exit;
}

if (!$recipeId) { 
    header("Location: my_recipes.php"); 
    exit; 
}

// 1. שליפת נתוני המתכון (וידוא בעלות או הרשאת אדמין)
$stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND (user_id = ? OR ? = 'admin')");
$stmt->execute([$recipeId, $userId, $userRole]);
$recipe = $stmt->fetch();

if (!$recipe) { 
    header("Location: my_recipes.php?error=not_found");
    exit;
}

// שליפת מצרכים והוראות קיימים
$ing_list = $pdo->prepare("SELECT * FROM ingredients WHERE recipe_id = ?");
$ing_list->execute([$recipeId]);
$ing_list = $ing_list->fetchAll();

$ins_list = $pdo->prepare("SELECT * FROM instructions WHERE recipe_id = ? ORDER BY id ASC");
$ins_list->execute([$recipeId]);
$ins_list = $ins_list->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// 2. לוגיקת שמירה (POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $categoryId = $_POST['category_id'];
    $videoUrl = trim($_POST['video_url']);
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $oldImageUrl = $recipe['image_url']; 
    $imageUrl = $oldImageUrl;
    $fileWasUploaded = false;

    // ניקוי מזהה יוטיוב (סגירת קצה פתוח: UX)
    if (!empty($videoUrl)) {
        preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/ ]{11})%i', $videoUrl, $match);
        $videoUrl = $match[1] ?? $videoUrl;
    }

    // פונקציית עזר לניקוי קבצים
    function deletePhysicalFile($path) {
        if ($path && $path !== 'default.jpg' && file_exists($path)) {
            unlink($path);
        }
    }

    // בדיקה אם המשתמש בחר למחוק את התמונה
    if (isset($_POST['delete_current_img']) && $_POST['delete_current_img'] == '1') {
        deletePhysicalFile($oldImageUrl);
        $imageUrl = 'default.jpg';
    }

    // לוגיקת אישור חכמה
    $newApprovedStatus = ($userRole === 'admin' || $isPublic == 0) ? 1 : 0;

    // טיפול בתמונה חדשה
    if (isset($_FILES['recipe_img']) && $_FILES['recipe_img']['error'] === UPLOAD_ERR_OK) {
        $tempPath = $_FILES['recipe_img']['tmp_name'];
        $file_info = getimagesize($tempPath);
        
        if ($file_info) {
            $type = $file_info[2];
            switch ($type) {
                case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($tempPath); break;
                case IMAGETYPE_PNG:  $source = imagecreatefrompng($tempPath); break;
                case IMAGETYPE_WEBP: $source = imagecreatefromwebp($tempPath); break;
                default: $source = null;
            }

            if ($source) {
                $newWidth = 800;
                $newHeight = floor($file_info[1] * ($newWidth / $file_info[0]));
                $virtualImage = imagecreatetruecolor($newWidth, $newHeight);
                
                imagealphablending($virtualImage, false);
                imagesavealpha($virtualImage, true);
                imagecopyresampled($virtualImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $file_info[0], $file_info[1]);
                
                $upload_dir = 'uploads/recipes/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                
                $new_file_name = uniqid('recipe_', true) . '.jpg';
                $destination = $upload_dir . $new_file_name;
                
                if (imagejpeg($virtualImage, $destination, 75)) {
                    // מחיקת התמונה הישנה מהשרת כי יש חדשה
                    deletePhysicalFile($oldImageUrl);
                    $imageUrl = $destination;
                    $fileWasUploaded = true;
                }
                imagedestroy($source);
                imagedestroy($virtualImage);
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $updateRecipe = $pdo->prepare("UPDATE recipes SET 
            title = ?, category_id = ?, image_url = ?, video_url = ?, is_public = ?, is_approved = ? 
            WHERE id = ?");
            
        $updateRecipe->execute([
            $title, $categoryId, $imageUrl, $videoUrl, 
            $isPublic, $newApprovedStatus, $recipeId
        ]);

        // עדכון מצרכים
        $pdo->prepare("DELETE FROM ingredients WHERE recipe_id = ?")->execute([$recipeId]);
        $insIng = $pdo->prepare("INSERT INTO ingredients (recipe_id, amount, ingredient_name, ingredient_description) VALUES (?, ?, ?, ?)");
        if (isset($_POST['ing_names'])) {
            foreach ($_POST['ing_names'] as $i => $name) {
                $trimmedName = trim($name);
                if (!empty($trimmedName)) {
                    $insIng->execute([$recipeId, $_POST['ing_amounts'][$i] ?? '', $trimmedName, $_POST['ing_descs'][$i] ?? '']);
                }
            }
        }

        // עדכון הוראות
        $pdo->prepare("DELETE FROM instructions WHERE recipe_id = ?")->execute([$recipeId]);
        $insStep = $pdo->prepare("INSERT INTO instructions (recipe_id, instruction_text) VALUES (?, ?)");
        if (isset($_POST['steps'])) {
            foreach ($_POST['steps'] as $step) {
                $trimmedStep = trim($step);
                if (!empty($trimmedStep)) { $insStep->execute([$recipeId, $trimmedStep]); }
            }
        }

        $pdo->commit();
        header("Location: my_recipes.php?status=" . (($newApprovedStatus === 1) ? "updated" : "pending")); 
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        // אם נכשלנו והספקנו להעלות קובץ - ננקה אותו
        if ($fileWasUploaded && $imageUrl !== 'default.jpg') { unlink($imageUrl); }
        die("שגיאה בעדכון: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>עריכת מתכון | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --danger: #ff4757; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .glass-card { background: rgba(255,255,255,0.03); backdrop-filter: blur(15px); border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); padding: 30px; max-width: 800px; margin: auto; }
        h2 { color: var(--accent); text-align: center; }
        .section { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 15px; margin-bottom: 20px; position: relative; }
        input, select, textarea { width: 100%; padding: 12px; margin: 10px 0; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 10px; box-sizing: border-box; outline: none; }
        .row { display: flex; gap: 10px; align-items: center; margin-bottom: 10px; }
        .btn-save { background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; border: none; padding: 15px; width: 100%; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 1.1rem; }
        .btn-add { background: none; border: 1px dashed var(--accent); color: var(--accent); padding: 10px; width: 100%; cursor: pointer; border-radius: 10px; }
        .upload-area { border: 2px dashed var(--accent); border-radius: 12px; padding: 20px; text-align: center; background: rgba(0, 242, 254, 0.05); position: relative; cursor: pointer; }
        .upload-area input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        #recipe-preview { width: 100%; max-height: 250px; object-fit: cover; border-radius: 10px; margin-top: 15px; }
        .remove-x { position: absolute; top: 10px; left: 10px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; z-index: 5; }
    </style>
</head>
<body>

<div class="glass-card">
    <a href="my_recipes.php" style="color: #94a3b8; text-decoration: none;">← ביטול וחזרה למחברת</a>
    <h2>עריכת מתכון ✨</h2>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="section">
            <label>כותרת המתכון</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required>
            
            <label>קטגוריה</label>
            <select name="category_id">
                <?php foreach($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $recipe['category_id']) ? 'selected' : ''; ?>>
                        <?php echo $c['icon']." ".htmlspecialchars($c['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="section">
            <h3>🖼️ תמונת המתכון</h3>
            <div class="upload-area">
                <input type="hidden" name="delete_current_img" id="deleteImgFlag" value="0">
                <input type="file" id="recipe_img_input" name="recipe_img" accept="image/*" onchange="previewImage(event)">
                <img id="recipe-preview" src="<?php echo htmlspecialchars($recipe['image_url'] ?: 'default.jpg'); ?>">
                <button type="button" id="remove-img-btn" class="remove-x" onclick="removeEditImage(event)" 
                        style="<?php echo ($recipe['image_url'] == 'default.jpg') ? 'display:none;' : ''; ?>">✕</button>
                <p style="margin-top: 10px; font-size: 0.85rem; color: var(--accent);">לחץ להחלפת תמונה</p>
            </div>
            <label style="margin-top:15px; display:block;">🎥 קישור ליוטיוב</label>
            <input type="text" name="video_url" value="<?php echo htmlspecialchars($recipe['video_url']); ?>" placeholder="הדבק קישור כאן...">
        </div>

        <div class="section">
            <h3>🛒 מצרכים</h3>
            <div id="ing-container">
                <?php foreach($ing_list as $ing): ?>
                    <div class="row">
                        <input type="text" name="ing_amounts[]" value="<?php echo htmlspecialchars($ing['amount']); ?>" placeholder="כמות ברבים" style="flex:1">
                        <input type="text" name="ing_names[]" value="<?php echo htmlspecialchars($ing['ingredient_name']); ?>" placeholder="מצרך" style="flex:2">
                        <input type="text" name="ing_descs[]" value="<?php echo htmlspecialchars($ing['ingredient_description']); ?>" placeholder="תחליף אם יש" style="flex:2">
                        <button type="button" style="background:none; border:none; color:var(--danger); cursor:pointer;" onclick="this.parentElement.remove()">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addIng()">+ הוסף מצרך</button>
        </div>

        <div class="section">
            <h3>👨‍🍳 שלבי הכנה</h3>
            <div id="steps-container">
                <?php foreach($ins_list as $ins): ?>
                    <div class="row">
                        <textarea name="steps[]" rows="2" style="flex:1" required><?php echo htmlspecialchars($ins['instruction_text']); ?></textarea>
                        <button type="button" style="background:none; border:none; color:var(--danger); cursor:pointer;" onclick="this.parentElement.remove()">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addStep()">+ הוסף שלב</button>
        </div>

        <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 20px; cursor: pointer;">
            <input type="checkbox" name="is_public" <?php echo $recipe['is_public'] ? 'checked' : ''; ?> style="width: auto;">
            <span>פרסם בקהילה (מתכון ציבורי)</span>
        </label>

        <button type="submit" class="btn-save">שמור שינויים ✅</button>
    </form>
</div>

<script>
function previewImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('recipe-preview');
    const removeBtn = document.getElementById('remove-img-btn');
    const flag = document.getElementById('deleteImgFlag');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) { 
            preview.src = e.target.result; 
            removeBtn.style.display = 'block';
            flag.value = '0';
        }
        reader.readAsDataURL(file);
    }
}

function removeEditImage(e) {
    e.stopPropagation(); e.preventDefault();
    if(confirm("האם למחוק את התמונה ולחזור לברירת מחדל?")) {
        document.getElementById('recipe_img_input').value = "";
        document.getElementById('recipe-preview').src = "default.jpg";
        document.getElementById('deleteImgFlag').value = "1";
        document.getElementById('remove-img-btn').style.display = 'none';
    }
}

function addIng() {
    const div = document.createElement('div'); div.className = 'row';
    div.innerHTML = `<input type="text" name="ing_amounts[]" placeholder="כמות ברבים" style="flex:1">
                     <input type="text" name="ing_names[]" placeholder="מצרך" style="flex:2">
                     <input type="text" name="ing_descs[]" placeholder="תחליף אם יש" style="flex:2">
                     <button type="button" style="background:none; border:none; color:#ff4757; cursor:pointer;" onclick="this.parentElement.remove()">✕</button>`;
    document.getElementById('ing-container').appendChild(div);
}

function addStep() {
    const div = document.createElement('div'); div.className = 'row';
    div.innerHTML = `<textarea name="steps[]" rows="2" style="flex:1" placeholder="שלב חדש..." required></textarea>
                     <button type="button" style="background:none; border:none; color:#ff4757; cursor:pointer;" onclick="this.parentElement.remove()">✕</button>`;
    document.getElementById('steps-container').appendChild(div);
}
</script>
</body>
</html>
