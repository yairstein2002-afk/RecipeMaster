<?php
session_start();
require_once 'db.php';

// אבטחה: רק מחוברים יכולים להוסיף מתכון
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $categoryId = $_POST['category_id'];
    $imageUrl = $_POST['image_url'];
    $videoUrl = $_POST['video_url'];
    $isPublic = isset($_POST['is_public']) ? 1 : 0;

    // --- לוגיקת אישור אוטומטי לאדמין בלבד ---
    $isApproved = ($userRole === 'admin') ? 1 : 0;

    try {
        $pdo->beginTransaction();

        // 1. הכנסת המתכון
        $stmt = $pdo->prepare("INSERT INTO recipes (user_id, category_id, title, image_url, video_url, is_public, is_approved) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $categoryId, $title, $imageUrl, $videoUrl, $isPublic, $isApproved]);
        $recipeId = $pdo->lastInsertId();

        // 2. הכנסת מצרכים (כולל שדה התיאור הנוסף)
        if (!empty($_POST['ing_names'])) {
            $insIng = $pdo->prepare("INSERT INTO ingredients (recipe_id, amount, ingredient_name, ingredient_description) VALUES (?, ?, ?, ?)");
            foreach ($_POST['ing_names'] as $i => $name) {
                if (!empty(trim($name))) {
                    $insIng->execute([
                        $recipeId, 
                        $_POST['ing_amounts'][$i], 
                        $name, 
                        $_POST['ing_descs'][$i] // התיאור של המצרך
                    ]);
                }
            }
        }

        // 3. הכנסת שלבי הכנה
        if (!empty($_POST['steps'])) {
            $insStep = $pdo->prepare("INSERT INTO instructions (recipe_id, instruction_text) VALUES (?, ?)");
            foreach ($_POST['steps'] as $step) {
                if (!empty(trim($step))) {
                    $insStep->execute([$recipeId, $step]);
                }
            }
        }

        $pdo->commit();
        // אם אתה אדמין - הולך לדף הבית לראות את המתכון. אם לא - למחברת האישית.
        header("Location: " . ($isApproved ? "index.php" : "my_recipes.php?status=pending"));
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה בשמירת המתכון: " . $e->getMessage());
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>הוספת מתכון | RecipeMaster</title>
    <style>
        /* עיצוב פרימיום Glassmorphism */
        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background: rgba(255,255,255,0.05); padding: 30px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); }
        h2 { text-align: center; color: #00f2fe; margin-bottom: 30px; }
        
        input, select, textarea { width: 100%; padding: 12px; margin: 10px 0; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 12px; box-sizing: border-box; }
        
        .row { display: flex; gap: 8px; margin-bottom: 10px; }
        .btn-add { background: none; color: #00f2fe; border: 1px dashed #00f2fe; padding: 10px; border-radius: 10px; cursor: pointer; width: 100%; margin-bottom: 20px; transition: 0.3s; }
        .btn-add:hover { background: rgba(0, 242, 254, 0.1); }
        
        .btn-submit { background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; border: none; padding: 18px; width: 100%; border-radius: 50px; font-weight: bold; font-size: 1.1rem; cursor: pointer; margin-top: 20px; box-shadow: 0 10px 20px rgba(0, 242, 254, 0.2); }
        
        .section-header { display: flex; align-items: center; gap: 10px; margin-top: 25px; color: #00f2fe; font-weight: bold; font-size: 1.1rem; }
        .checkbox-container { background: rgba(255,255,255,0.03); padding: 15px; border-radius: 12px; display: flex; align-items: center; gap: 10px; cursor: pointer; margin: 15px 0; border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body>

<div class="container">
    <h2>🍳 הוספת מתכון חדש למערכת</h2>
    
    <form method="POST">
        <input type="text" name="title" placeholder="כותרת המתכון..." required>
        
        <select name="category_id" required>
            <option value="" disabled selected>בחר קטגוריה...</option>
            <?php foreach($categories as $c): ?>
                <option value="<?php echo $c['id']; ?>"><?php echo $c['icon'] . " " . $c['name']; ?></option>
            <?php endforeach; ?>
        </select>

        <div class="row">
            <input type="text" name="image_url" placeholder="📸 קישור לתמונה">
            <input type="text" name="video_url" placeholder="🎥 קישור ליוטיוב">
        </div>

        <label class="checkbox-container">
            <input type="checkbox" name="is_public" checked style="width: auto;">
            <span>🌍 פרסם לקהילה (בכפוף לאישור)</span>
        </label>

        <div class="section-header">🍎 מצרכים</div>
        <div id="ingredients-area">
            <div class="row">
                <input type="text" name="ing_amounts[]" placeholder="כמות" style="flex: 1;">
                <input type="text" name="ing_names[]" placeholder="שם המצרך" style="flex: 2;">
                <input type="text" name="ing_descs[]" placeholder="תיאור (קצוץ/חתוך...)" style="flex: 2;">
            </div>
        </div>
        <button type="button" class="btn-add" onclick="addIngredient()">+ הוסף מצרך</button>

        <div class="section-header">📝 אופן ההכנה</div>
        <div id="steps-area">
            <textarea name="steps[]" placeholder="שלב 1..." rows="2"></textarea>
        </div>
        <button type="button" class="btn-add" onclick="addStep()">+ הוסף שלב</button>

        <button type="submit" class="btn-submit">שמור מתכון ✨</button>
    </form>
</div>

<script>
// הוספת שורת מצרך חדשה עם 3 שדות
function addIngredient() {
    const div = document.createElement('div');
    div.className = 'row';
    div.innerHTML = `
        <input type="text" name="ing_amounts[]" placeholder="כמות" style="flex: 1;">
        <input type="text" name="ing_names[]" placeholder="שם המצרך" style="flex: 2;">
        <input type="text" name="ing_descs[]" placeholder="תיאור" style="flex: 2;">
    `;
    document.getElementById('ingredients-area').appendChild(div);
}

// הוספת שלב הכנה חדש
function addStep() {
    const area = document.getElementById('steps-area');
    const stepNum = area.getElementsByTagName('textarea').length + 1;
    const txt = document.createElement('textarea');
    txt.name = "steps[]";
    txt.placeholder = "שלב " + stepNum + "...";
    txt.rows = 2;
    area.appendChild(txt);
}
</script>

</body>
</html>