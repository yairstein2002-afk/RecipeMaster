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

/**
 * פתרון לבעיית האישור: 
 * שליפת הסטטוס העדכני ביותר ממסד הנתונים כדי למנוע מצב שבו המשתמש 
 * אושר ב-DB אבל הסשן שלו עדיין מראה 'pending'.
 */
$stmt_check = $pdo->prepare("SELECT status FROM users WHERE id = ?");
$stmt_check->execute([$userId]);
$currentStatus = $stmt_check->fetchColumn();

// עדכון הסשן בערך העדכני ביותר מה-DB
$_SESSION['status'] = $currentStatus;

// קביעת הסטטוס הסופי (מנהל תמיד מאושר)
$userStatus = ($userRole === 'admin') ? 'approved' : $currentStatus;

// חסימת צד-שרת: אם המשתמש לא מאושר, הוא מועבר לדף הבית
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
    
    // אדמין מקבל אישור אוטומטי למתכון, משתמש רגיל ממתין לאישור מנהל
    $isApproved = ($userRole === 'admin') ? 1 : 0;

    // טיפול בהעלאת תמונה
    $imageUrl = 'default.jpg'; 
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

        // 1. הכנסת המתכון הראשי
        $stmt = $pdo->prepare("INSERT INTO recipes (user_id, category_id, title, image_url, video_url, is_public, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $categoryId, $title, $imageUrl, $videoUrl, $isPublic, $isApproved]);
        $recipeId = $pdo->lastInsertId();

        // 2. הכנסת מצרכים (בלופ)
        if (!empty($_POST['ing_names'])) {
            $insIng = $pdo->prepare("INSERT INTO ingredients (recipe_id, amount, ingredient_name, ingredient_description) VALUES (?, ?, ?, ?)");
            foreach ($_POST['ing_names'] as $i => $name) {
                if (!empty(trim($name))) {
                    $amount = $_POST['ing_amounts'][$i] ?? '';
                    $desc = $_POST['ing_descs'][$i] ?? '';
                    $insIng->execute([$recipeId, $amount, $name, $desc]);
                }
            }
        }

        // 3. הכנסת שלבי הכנה (בלופ)
        if (!empty($_POST['steps'])) {
            $insStep = $pdo->prepare("INSERT INTO instructions (recipe_id, instruction_text) VALUES (?, ?)");
            foreach ($_POST['steps'] as $step) {
                if (!empty(trim($step))) {
                    $insStep->execute([$recipeId, $step]);
                }
            }
        }

        $pdo->commit();
        header("Location: index.php?msg=recipe_submitted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה בשמירה: " . $e->getMessage());
    }
}

// שליפת קטגוריות לטופס
$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הוספת מתכון | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); }
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

        <div class="upload-area">
            <div id="upload-text">📸 לחץ כאן להעלאת תמונה</div>
            <input type="file" name="recipe_img" accept="image/*" onchange="previewImg(event)">
            <img id="preview">
        </div>

        <input type="url" name="video_url" placeholder="🎥 קישור ליוטיוב (אופציונלי)">

        <label style="display: flex; align-items: center; gap: 10px; margin: 20px 0;">
            <input type="checkbox" name="is_public" checked style="width: auto;">
            <span>פרסם לקהילה (בכפוף לאישור מנהל)</span>
        </label>

        <h3 style="color: var(--accent);">🍎 מצרכים</h3>
        <div id="ing-area">
            <div class="row">
                <input type="text" name="ing_amounts[]" placeholder="כמות" style="flex: 1;">
                <input type="text" name="ing_names[]" placeholder="שם המצרך" style="flex: 2;">
                <input type="text" name="ing_descs[]" placeholder="תיאור" style="flex: 2;">
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
function previewImg(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = () => {
            const img = document.getElementById('preview');
            img.src = reader.result;
            img.style.display = 'block';
            document.getElementById('upload-text').style.display = 'none';
        }
        reader.readAsDataURL(file);
    }
}

function addIng() {
    const div = document.createElement('div');
    div.className = 'row';
    div.innerHTML = `
        <input type="text" name="ing_amounts[]" placeholder=\"כמות\" style=\"flex: 1;\">
        <input type=\"text\" name=\"ing_names[]\" placeholder=\"שם המצרך\" style=\"flex: 2;\">
        <input type=\"text\" name=\"ing_descs[]\" placeholder=\"תיאור\" style=\"flex: 2;\">
        <button type=\"button\" class=\"btn-remove\" onclick=\"this.parentElement.remove()\">✕</button>
    `;
    document.getElementById('ing-area').appendChild(div);
}

function addStep() {
    const area = document.getElementById('step-area');
    const stepNum = area.querySelectorAll('textarea').length + 1;
    const div = document.createElement('div');
    div.className = 'row';
    div.innerHTML = `<textarea name=\"steps[]\" placeholder=\"שלב ${stepNum}...\" rows=\"2\" style=\"flex: 1;\"></textarea>
                     <button type=\"button\" class=\"btn-remove\" onclick=\"this.parentElement.remove()\">✕</button>`;
    area.appendChild(div);
}
</script>
</body>
</html>