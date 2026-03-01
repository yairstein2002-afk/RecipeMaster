<?php
session_start();
require_once 'db.php';

// אבטחה: רק משתמש מחובר יכול לערוך
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$recipeId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

/**
 * מניעת בעיית סשן: בדיקת הסטטוס העדכני מה-DB
 */
$stmt_check = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt_check->execute([$userId]);
$currentStatus = $stmt_check->fetchColumn();
$_SESSION['status'] = $currentStatus;

$userStatus = ($userRole === 'admin') ? 'approved' : $currentStatus;

// חסימת אבטחה: משתמש שלא אושר לא יכול לערוך תוכן
if ($userStatus !== 'approved') {
    header("Location: index.php?error=not_approved");
    exit;
}

if (!$recipeId) { header("Location: my_recipes.php"); exit; }

// 1. שליפת נתוני המתכון (וידוא בעלות או הרשאת אדמין)
$stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND (user_id = ? OR ? = 'admin')");
$stmt->execute([$recipeId, $userId, $userRole]);
$recipe = $stmt->fetch();

if (!$recipe) { die("מתכון לא נמצא או שאין לך הרשאה לערוך אותו."); }

// שליפת מצרכים והוראות קיימים
$ingredients = $pdo->prepare("SELECT * FROM ingredients WHERE recipe_id = ?");
$ingredients->execute([$recipeId]);
$ing_list = $ingredients->fetchAll();

$instructions = $pdo->prepare("SELECT * FROM instructions WHERE recipe_id = ? ORDER BY id ASC");
$instructions->execute([$recipeId]);
$ins_list = $instructions->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// 2. לוגיקת שמירה
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $imageUrl = $recipe['image_url']; 
    
    /**
     * לוגיקת אישור חכמה:
     * 1. אדמין תמיד נשאר מאושר.
     * 2. אם המשתמש הפך את המתכון לפרטי (is_public = 0) - הוא מאושר אוטומטית (כי הוא לא בקהילה).
     * 3. אם המתכון ציבורי וזה משתמש רגיל - הוא חוזר להמתנה לאישור.
     */
    if ($userRole === 'admin') {
        $newApprovedStatus = 1;
    } elseif ($isPublic == 0) {
        $newApprovedStatus = 1;
    } else {
        $newApprovedStatus = 0;
    }

    // טיפול בתמונה
    if (isset($_FILES['recipe_img']) && $_FILES['recipe_img']['error'] === UPLOAD_ERR_OK) {
        $file_ext = strtolower(pathinfo($_FILES['recipe_img']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, ['jpg', 'jpeg', 'png', 'webp']) && $_FILES['recipe_img']['size'] <= 5000000) {
            $new_file_name = uniqid('recipe_', true) . '.' . $file_ext;
            $upload_dir = 'uploads/recipes/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
            $destination = $upload_dir . $new_file_name;
            if (move_uploaded_file($_FILES['recipe_img']['tmp_name'], $destination)) {
                $imageUrl = $destination; 
            }
        }
    }

    try {
        $pdo->beginTransaction();

        $updateRecipe = $pdo->prepare("UPDATE recipes SET 
            title = ?, category_id = ?, image_url = ?, video_url = ?, is_public = ?, is_approved = ? 
            WHERE id = ?");
            
        $updateRecipe->execute([
            $_POST['title'], $_POST['category_id'], $imageUrl, $_POST['video_url'], 
            $isPublic, $newApprovedStatus, $recipeId
        ]);

        // עדכון מצרכים: מחיקה והכנסה מחדש
        $pdo->prepare("DELETE FROM ingredients WHERE recipe_id = ?")->execute([$recipeId]);
        $insIng = $pdo->prepare("INSERT INTO ingredients (recipe_id, amount, ingredient_name, ingredient_description) VALUES (?, ?, ?, ?)");
        foreach ($_POST['ing_names'] as $i => $name) {
            if (!empty(trim($name))) {
                $insIng->execute([$recipeId, $_POST['ing_amounts'][$i], $name, $_POST['ing_desc'][$i]]);
            }
        }

        // עדכון הוראות: מחיקה והכנסה מחדש
        $pdo->prepare("DELETE FROM instructions WHERE recipe_id = ?")->execute([$recipeId]);
        $insStep = $pdo->prepare("INSERT INTO instructions (recipe_id, instruction_text) VALUES (?, ?)");
        foreach ($_POST['steps'] as $step) {
            if (!empty(trim($step))) { $insStep->execute([$recipeId, $step]); }
        }

        $pdo->commit();
        
        // קביעת הודעת הסטטוס לפי התוצאה
        $statusMsg = ($newApprovedStatus === 1) ? "updated" : "pending";
        header("Location: my_recipes.php?status=" . $statusMsg); 
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה בעדכון: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>עריכת מתכון | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 40px 20px; }
        .glass-card { background: rgba(255,255,255,0.03); backdrop-filter: blur(15px); border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); padding: 40px; max-width: 850px; margin: auto; }
        h2 { color: var(--accent); text-align: center; }
        .section { background: rgba(255,255,255,0.02); padding: 20px; border-radius: 15px; margin-bottom: 20px; }
        input, select, textarea { width: 100%; padding: 12px; margin: 10px 0; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 10px; box-sizing: border-box; }
        .btn-save { background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; border: none; padding: 15px; width: 100%; border-radius: 50px; font-weight: bold; cursor: pointer; font-size: 1.1rem; }
        .btn-add { background: none; border: 1px dashed var(--accent); color: var(--accent); padding: 10px; width: 100%; cursor: pointer; border-radius: 10px; margin-bottom: 10px; }
        .row { display: flex; gap: 10px; }
        .upload-area { border: 2px dashed #00f2fe; border-radius: 12px; padding: 15px; text-align: center; background: rgba(0, 242, 254, 0.05); position: relative; overflow: hidden; }
        .upload-area input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        #recipe-preview { width: 100%; height: 200px; object-fit: cover; border-radius: 10px; margin-top: 15px; border: 1px solid #00f2fe; }
    </style>
</head>
<body>

<div class="glass-card">
    <h2>עריכת מתכון ✨</h2>
    
    <?php if ($userRole !== 'admin'): ?>
        <p style="text-align: center; opacity: 0.7; font-size: 0.9rem;">
            * שינוי המתכון למצב ציבורי ידרוש אישור מנהל מחדש.<br>
            * מתכונים פרטיים מאושרים מיידית.
        </p>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div class="section">
            <label>שם המנה:</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required>
            
            <label>קטגוריה:</label>
            <select name="category_id">
                <?php foreach($categories as $c): ?>
                    <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $recipe['category_id']) ? 'selected' : ''; ?>>
                        <?php echo $c['icon']." ".$c['name']; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="section">
            <h3>🖼️ מדיה ותמונה</h3>
            <div class="upload-area">
                <input type="file" name="recipe_img" accept="image/*" onchange="previewImage(event)">
                <img id="recipe-preview" src="<?php echo htmlspecialchars($recipe['image_url'] ?: 'default.jpg'); ?>">
                <p style="margin-top: 10px; font-size: 0.85rem; color: var(--accent);">לחץ להחלפת תמונה</p>
            </div>
            <input type="text" name="video_url" value="<?php echo htmlspecialchars($recipe['video_url']); ?>" placeholder="🎥 קישור ליוטיוב">
        </div>

        <div class="section">
            <h3>🛒 מצרכים</h3>
            <div id="ing-container">
                <?php foreach($ing_list as $ing): ?>
                    <div class="row">
                        <input type="text" name="ing_amounts[]" value="<?php echo htmlspecialchars($ing['amount']); ?>" placeholder="כמות" style="flex:1">
                        <input type="text" name="ing_names[]" value="<?php echo htmlspecialchars($ing['ingredient_name']); ?>" placeholder="מצרך" style="flex:2">
                        <input type="text" name="ing_desc[]" value="<?php echo htmlspecialchars($ing['ingredient_description']); ?>" placeholder="תיאור" style="flex:2">
                        <button type="button" style="background:none; border:none; color:#ff4757; cursor:pointer;" onclick="this.parentElement.remove()">✕</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-add" onclick="addIng()">+ הוסף מצרך</button>
        </div>

        <div class="section">
            <h3>👨‍🍳 שלבי הכנה</h3>
            <div id="steps-container">
                <?php foreach($ins_list as $ins): ?>
                    <div class="row" style="margin-bottom:10px;">
                        <textarea name="steps[]" rows="2" style="flex:1"><?php echo htmlspecialchars($ins['instruction_text']); ?></textarea>
                        <button type="button" style="background:none; border:none; color:#ff4757; cursor:pointer;" onclick="this.parentElement.remove()">✕</button>
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
        <a href="my_recipes.php" style="display: block; text-align: center; margin-top: 15px; color: #94a3b8; text-decoration: none;">ביטול</a>
    </form>
</div>

<script>
function previewImage(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('recipe-preview');
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) { preview.src = e.target.result; }
        reader.readAsDataURL(file);
    }
}
function addIng() {
    const div = document.createElement('div'); div.className = 'row';
    div.innerHTML = `<input type="text" name="ing_amounts[]" placeholder="כמות" style="flex:1">
                     <input type="text" name="ing_names[]" placeholder="מצרך" style="flex:2">
                     <input type="text" name="ing_desc[]" placeholder="תיאור" style="flex:2">
                     <button type="button" style="background:none; border:none; color:#ff4757; cursor:pointer;" onclick="this.parentElement.remove()">✕</button>`;
    document.getElementById('ing-container').appendChild(div);
}
function addStep() {
    const div = document.createElement('div'); div.className = 'row'; div.style.marginBottom = '10px';
    div.innerHTML = `<textarea name="steps[]" rows="2" style="flex:1" placeholder="שלב חדש..."></textarea>
                     <button type="button" style="background:none; border:none; color:#ff4757; cursor:pointer;" onclick="this.parentElement.remove()">✕</button>`;
    document.getElementById('steps-container').appendChild(div);
}
</script>
</body>
</html>