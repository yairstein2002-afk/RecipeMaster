<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';

$recipeId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// 1. שליפת נתוני הבסיס של המתכון (וידוא בעלות או הרשאת אדמין)
$stmt = $pdo->prepare("SELECT * FROM recipes WHERE id = ? AND (user_id = ? OR ? = 'admin')");
$stmt->execute([$recipeId, $userId, $userRole]);
$recipe = $stmt->fetch();

if (!$recipe) { die("מתכון לא נמצא או שאין לך הרשאה לערוך אותו."); }

// 2. שליפת מצרכים והוראות קיימים
$current_ingredients = $pdo->prepare("SELECT * FROM ingredients WHERE recipe_id = ?");
$current_ingredients->execute([$recipeId]);
$ingredients = $current_ingredients->fetchAll();

$current_instructions = $pdo->prepare("SELECT * FROM instructions WHERE recipe_id = ? ORDER BY id ASC");
$current_instructions->execute([$recipeId]);
$instructions = $current_instructions->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();

// 3. לוגיקת שמירה (Update)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $pdo->beginTransaction();

        // עדכון פרטים כלליים כולל תמונה וסרטון
        $updateRecipe = $pdo->prepare("UPDATE recipes SET title = ?, category_id = ?, image_url = ?, video_url = ?, is_public = ? WHERE id = ?");
        $updateRecipe->execute([
            $_POST['title'], $_POST['category_id'], $_POST['image_url'], 
            $_POST['video_url'], isset($_POST['is_public']) ? 1 : 0, $recipeId
        ]);

        // עדכון מצרכים: מחיקה והכנסה מחדש (השיטה היעילה ביותר למערכים משתנים)
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
        header("Location: my_recipes.php?status=updated"); exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה בעדכון המידע: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>עריכה מלאה | RecipeMaster</title>
    <style>
        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; line-height: 1.6; }
        .edit-container { max-width: 900px; margin: 0 auto; background: rgba(255,255,255,0.05); padding: 40px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px); }
        h2, h3 { color: #00f2fe; border-bottom: 1px solid rgba(0,242,254,0.2); padding-bottom: 10px; }
        input, select, textarea { width: 100%; padding: 12px; margin: 10px 0; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 10px; font-size: 1rem; }
        .row { display: flex; gap: 15px; margin-bottom: 10px; align-items: flex-end; }
        .btn-add { background: rgba(0, 184, 148, 0.2); color: #55efc4; border: 1px solid #00b894; padding: 8px 20px; border-radius: 50px; cursor: pointer; margin: 10px 0; transition: 0.3s; }
        .btn-add:hover { background: #00b894; color: #0f172a; }
        .btn-save { background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; border: none; padding: 18px; width: 100%; border-radius: 50px; font-weight: bold; font-size: 1.2rem; cursor: pointer; margin-top: 40px; box-shadow: 0 10px 20px rgba(0,242,254,0.2); }
        .btn-cancel { display: block; text-align: center; margin-top: 20px; color: #94a3b8; text-decoration: none; }
        .preview-img { width: 120px; height: 80px; object-fit: cover; border-radius: 10px; border: 1px solid #00f2fe; }
    </style>
</head>
<body>

<div class="edit-container">
    <h2>עריכת מתכון: <?php echo htmlspecialchars($recipe['title']); ?> ✍️</h2>
    
    <form method="POST">
        <div class="row">
            <div style="flex:2">
                <label>שם המתכון:</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($recipe['title']); ?>" required>
            </div>
            <div style="flex:1">
                <label>קטגוריה:</label>
                <select name="category_id">
                    <?php foreach($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($c['id'] == $recipe['category_id']) ? 'selected' : ''; ?>>
                            <?php echo $c['icon']." ".$c['name']; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <h3>🖼️ תמונה וסרטון</h3>
        <div class="row">
            <div style="flex:1">
                <label>קישור לתמונה:</label>
                <input type="text" name="image_url" value="<?php echo htmlspecialchars($recipe['image_url']); ?>">
            </div>
            <div style="flex:1">
                <label>קישור ל-YouTube:</label>
                <input type="text" name="video_url" value="<?php echo htmlspecialchars($recipe['video_url']); ?>">
            </div>
            <?php if($recipe['image_url']): ?>
                <img src="<?php echo htmlspecialchars($recipe['image_url']); ?>" class="preview-img">
            <?php endif; ?>
        </div>

        <h3>🛒 מצרכים</h3>
        <div id="ingredients-list">
            <?php foreach($ingredients as $ing): ?>
                <div class="row">
                    <input type="text" name="ing_amounts[]" value="<?php echo htmlspecialchars($ing['amount']); ?>" placeholder="כמות" style="flex:1">
                    <input type="text" name="ing_names[]" value="<?php echo htmlspecialchars($ing['ingredient_name']); ?>" placeholder="שם המצרך" style="flex:2">
                    <input type="text" name="ing_desc[]" value="<?php echo htmlspecialchars($ing['ingredient_description']); ?>" placeholder="תיאור" style="flex:2">
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-add" onclick="addIng()">+ הוסף שורה למצרכים</button>

        <h3>👨‍🍳 שלבי הכנה</h3>
        <div id="steps-list">
            <?php foreach($instructions as $ins): ?>
                <textarea name="steps[]" rows="2" placeholder="כתוב כאן שלב בהכנה..."><?php echo htmlspecialchars($ins['instruction_text']); ?></textarea>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn-add" onclick="addStep()">+ הוסף שלב</button>

        <div style="margin-top: 20px; background: rgba(255,255,255,0.05); padding: 15px; border-radius: 10px;">
            <label style="cursor: pointer; display: flex; align-items: center; gap: 10px;">
                <input type="checkbox" name="is_public" <?php echo $recipe['is_public'] ? 'checked' : ''; ?> style="width:auto;"> 
                <span>הפוך מתכון זה לפומבי (יופיע בפיד הקהילתי)</span>
            </label>
        </div>

        <button type="submit" class="btn-save">שמור שינויים ועדכן הכל</button>
        <a href="my_recipes.php" class="btn-cancel">ביטול וחזרה למחברת</a>
    </form>
</div>

<script>
// פונקציות להוספת שורות דינמיות
function addIng() {
    const container = document.getElementById('ingredients-list');
    const div = document.createElement('div');
    div.className = 'row';
    div.innerHTML = `
        <input type="text" name="ing_amounts[]" placeholder="כמות" style="flex:1">
        <input type="text" name="ing_names[]" placeholder="שם המצרך" style="flex:2">
        <input type="text" name="ing_desc[]" placeholder="תיאור" style="flex:2">
    `;
    container.appendChild(div);
}

function addStep() {
    const container = document.getElementById('steps-list');
    const area = document.createElement('textarea');
    area.name = 'steps[]';
    area.rows = 2;
    area.placeholder = "כתוב כאן שלב בהכנה...";
    container.appendChild(area);
}
</script>

</body>
</html>