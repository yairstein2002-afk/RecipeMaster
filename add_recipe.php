<?php
session_start();
require_once 'db.php';

// אבטחה: רק משתמשים מחוברים יכולים לגשת
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// סנכרון סטטוס
$stmt_check = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt_check->execute([$userId]);
$currentStatus = $stmt_check->fetchColumn();
$_SESSION['status'] = $currentStatus;
$userStatus = ($userRole === 'admin') ? 'approved' : $currentStatus;

if ($userStatus !== 'approved') {
    header("Location: index.php?error=not_approved");
    exit;
}

// לוגיקת שמירת הנתונים
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $categoryId = $_POST['category_id'];
    $videoUrl = $_POST['video_url'];
    $isPublic = isset($_POST['is_public']) ? 1 : 0;
    $isApproved = ($userRole === 'admin') ? 1 : 0;

    // --- טיפול חכם בתמונה (כיווץ וחיסכון במקום) ---
    $imageUrl = 'default.jpg'; 
    if (isset($_FILES['recipe_img']) && $_FILES['recipe_img']['error'] === UPLOAD_ERR_OK) {
        $tempPath = $_FILES['recipe_img']['tmp_name'];
        $file_info = getimagesize($tempPath);
        
        if ($file_info) {
            $width = $file_info[0];
            $height = $file_info[1];
            $type = $file_info[2];
            
            // יצירת משאב תמונה
            switch ($type) {
                case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($tempPath); break;
                case IMAGETYPE_PNG:  $source = imagecreatefrompng($tempPath); break;
                case IMAGETYPE_WEBP: $source = imagecreatefromwebp($tempPath); break;
                default: $source = null;
            }

            if ($source) {
                $newWidth = 800; // מקסימום רוחב לאתר
                $newHeight = floor($height * ($newWidth / $width));
                $virtualImage = imagecreatetruecolor($newWidth, $newHeight);
                
                // שמירה על שקיפות ל-PNG/WEBP
                imagealphablending($virtualImage, false);
                imagesavealpha($virtualImage, true);
                
                imagecopyresampled($virtualImage, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                
                $upload_dir = 'uploads/recipes/';
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                $new_file_name = uniqid('recipe_', true) . '.jpg'; // נשמור כ-JPG דחוס
                $destination = $upload_dir . $new_file_name;
                
                // שמירה באיכות 75% לחיסכון במקום
                if (imagejpeg($virtualImage, $destination, 75)) {
                    $imageUrl = $destination;
                }
                
                imagedestroy($source);
                imagedestroy($virtualImage);
            }
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO recipes (user_id, category_id, title, image_url, video_url, is_public, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $categoryId, $title, $imageUrl, $videoUrl, $isPublic, $isApproved]);
        $recipeId = $pdo->lastInsertId();

        if (!empty($_POST['ing_names'])) {
            $insIng = $pdo->prepare("INSERT INTO ingredients (recipe_id, amount, ingredient_name, ingredient_description) VALUES (?, ?, ?, ?)");
            foreach ($_POST['ing_names'] as $i => $name) {
                if (!empty(trim($name))) {
                    $insIng->execute([$recipeId, $_POST['ing_amounts'][$i] ?? '', $name, $_POST['ing_descs'][$i] ?? '']);
                }
            }
        }

        if (!empty($_POST['steps'])) {
            $insStep = $pdo->prepare("INSERT INTO instructions (recipe_id, instruction_text) VALUES (?, ?)");
            foreach ($_POST['steps'] as $step) {
                if (!empty(trim($step))) { $insStep->execute([$recipeId, $step]); }
            }
        }

        $pdo->commit();
        header("Location: index.php?msg=recipe_submitted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה: " . $e->getMessage());
    }
}
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הוספת מתכון | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); --danger: #ff4757; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 700px; margin: 0 auto; background: var(--glass); padding: 30px; border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); }
        h2 { text-align: center; color: var(--accent); }
        input, select, textarea { width: 100%; padding: 12px; margin: 10px 0; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 12px; box-sizing: border-box; }
        .row { display: flex; gap: 10px; margin-bottom: 10px; align-items: center; }
        .btn-add { background: none; color: var(--accent); border: 1px dashed var(--accent); padding: 10px; border-radius: 10px; cursor: pointer; width: 100%; margin: 10px 0 20px; }
        .btn-remove { color: #ff7675; background: none; border: none; cursor: pointer; font-size: 1.2rem; }
        .upload-area { border: 2px dashed var(--accent); border-radius: 12px; padding: 20px; text-align: center; background: rgba(0, 242, 254, 0.05); cursor: pointer; position: relative; }
        .upload-area input[type="file"] { position: absolute; top: 0; left: 0; width: 100%; height: 100%; opacity: 0; cursor: pointer; }
        #preview { width: 100%; max-height: 250px; object-fit: cover; border-radius: 10px; display: none; margin-top: 15px; }
        .btn-submit { background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; border: none; padding: 18px; width: 100%; border-radius: 50px; font-weight: bold; font-size: 1.1rem; cursor: pointer; margin-top: 30px; }
        
        /* עיצוב בועת העזרה ליוטיוב */
        .help-box { display:none; background: rgba(0, 242, 254, 0.1); padding: 12px; border-radius: 12px; border: 1px solid var(--accent); margin-top: 5px; font-size: 0.85rem; line-height: 1.4; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" style="color: #94a3b8; text-decoration: none;">← חזרה לדף הבית</a>
    <h2>🍳 הוספת מתכון חדש</h2>
    
    <form method="POST" enctype="multipart/form-data">
        <input type="text" name="title" placeholder="כותרת המתכון (למשל: פוקצ'ה ביתית)" required>
        
        <select name="category_id" required>
            <option value="" disabled selected>בחר קטגוריה...</option>
            <?php foreach($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo $c['icon'] . " " . htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <div class="upload-area" style="position: relative;">
            <div id="upload-text">📸 לחץ כאן להעלאת תמונה</div>
            <input type="file" name="recipe_img" id="recipe_img_input" accept="image/*" onchange="previewImg(event)">
            <img id="preview">
            <button type="button" id="remove-img-btn" onclick="removeSelectedImg(event)" 
                    style="display:none; position: absolute; top: 10px; left: 10px; background: var(--danger); color: white; border: none; border-radius: 50%; width: 30px; height: 30px; cursor: pointer; z-index: 10;">✕</button>
        </div>

        <div style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <label style="font-size: 0.9rem; color: var(--accent);">🎥 קישור ליוטיוב (אופציונלי)</label>
                <span onclick="toggleHelp()" style="cursor:pointer; font-size: 0.75rem; text-decoration: underline; opacity: 0.8;">איך להעלות בלי ערוץ?</span>
            </div>
            <input type="url" name="video_url" placeholder="הדבק כאן קישור לסרטון...">
            <div id="video-help" class="help-box">
                <b>טיפ לפרטיות:</b><br>
                ניתן להעלות סרטון ליוטיוב ולהגדיר אותו כ-<b>"לא רשום" (Unlisted)</b>.<br>
                ככה הסרטון יעבוד רק כאן באתר, ואף אחד לא יוכל למצוא אותו בחיפוש ביוטיוב! 🤫
            </div>
        </div>

        <label style="display: flex; align-items: center; gap: 10px; margin: 20px 0;">
            <input type="checkbox" name="is_public" checked style="width: auto;">
            <span>פרסם לקהילה (בכפוף לאישור מנהל)</span>
        </label>

        <h3 style="color: var(--accent);">🍎 מצרכים</h3>
        <div id="ing-area">
            <div class="row">
                <input type="text" name="ing_amounts[]" placeholder="כמות" style="flex: 1;">
                <input type="text" name="ing_names[]" placeholder="שם המצרך" style="flex: 2;">
                <input type="text" name="ing_descs[]" placeholder="תיאור (אופציונלי)" style="flex: 2;">
            </div>
        </div>
        <button type="button" class="btn-add" onclick="addIng()">+ הוסף מצרך</button>

        <h3 style="color: var(--accent);">📝 אופן ההכנה</h3>
        <div id="step-area">
            <textarea name="steps[]" placeholder="שלב 1..." rows="2" required></textarea>
        </div>
        <button type="button" class="btn-add" onclick="addStep()">+ הוסף שלב</button>

        <button type="submit" class="btn-submit">פרסם מתכון ✨</button>
    </form>
</div>

<script>
function toggleHelp() {
    const help = document.getElementById('video-help');
    help.style.display = (help.style.display === 'block') ? 'none' : 'block';
}

function previewImg(e) {
    const file = e.target.files[0];
    const preview = document.getElementById('preview');
    const uploadText = document.getElementById('upload-text');
    const removeBtn = document.getElementById('remove-img-btn');

    if (file) {
        const reader = new FileReader();
        reader.onload = () => {
            preview.src = reader.result;
            preview.style.display = 'block';
            uploadText.style.display = 'none';
            removeBtn.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
}

function removeSelectedImg(e) {
    e.stopPropagation(); e.preventDefault();
    const input = document.getElementById('recipe_img_input');
    const preview = document.getElementById('preview');
    const uploadText = document.getElementById('upload-text');
    const removeBtn = document.getElementById('remove-img-btn');
    input.value = "";
    preview.src = "";
    preview.style.display = 'none';
    uploadText.style.display = 'block';
    removeBtn.style.display = 'none';
}

function addIng() {
    const div = document.createElement('div');
    div.className = 'row';
    div.innerHTML = `
        <input type="text" name="ing_amounts[]" placeholder="כמות" style="flex: 1;">
        <input type="text" name="ing_names[]" placeholder="שם המצרך" style="flex: 2;">
        <input type="text" name="ing_descs[]" placeholder="תיאור" style="flex: 2;">
        <button type="button" class="btn-remove" onclick="this.parentElement.remove()">✕</button>
    `;
    document.getElementById('ing-area').appendChild(div);
}

function addStep() {
    const area = document.getElementById('step-area');
    const stepNum = area.querySelectorAll('textarea').length + 1;
    const div = document.createElement('div');
    div.className = 'row';
    div.innerHTML = `<textarea name="steps[]" placeholder="שלב ${stepNum}..." rows="2" style="flex: 1;"></textarea>
                     <button type="button" class="btn-remove" onclick="this.parentElement.remove()">✕</button>`;
    area.appendChild(div);
}
</script>
</body>
</html>
