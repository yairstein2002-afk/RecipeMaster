<?php
session_start();
require_once 'db.php';

// אבטחה: רק אדמין יכול לנהל קטגוריות
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("גישה חסומה.");
}

$edit_cat = null;

// 1. הוספה או עדכון קטגוריה
if (isset($_POST['save_category'])) {
    $name = $_POST['cat_name'];
    $icon = $_POST['cat_icon'];
    $id = $_POST['cat_id'] ?? null;
    
    if (!empty($name) && !empty($icon)) {
        if ($id) {
            // עדכון קטגוריה קיימת
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, icon = ? WHERE id = ?");
            $stmt->execute([$name, $icon, $id]);
            $msg = "updated";
        } else {
            // הוספת חדשה
            $stmt = $pdo->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
            $stmt->execute([$name, $icon]);
            $msg = "added";
        }
        header("Location: manage_categories.php?msg=$msg");
        exit;
    }
}

// 2. שליפת קטגוריה לעריכה
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_cat = $stmt->fetch();
}

// 3. מחיקת קטגוריה
if (isset($_GET['delete_id'])) {
    $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$_GET['delete_id']]);
    header("Location: manage_categories.php?msg=deleted");
    exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id ASC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול קטגוריות | RecipeMaster</title>
    <style>
        body { background: #0f172a; color: white; font-family: 'Segoe UI', sans-serif; padding: 30px; }
        .admin-container { max-width: 600px; margin: 0 auto; }
        .card { background: rgba(255,255,255,0.05); padding: 25px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); margin-bottom: 25px; }
        input { width: 100%; padding: 12px; margin: 10px 0; background: #1e293b; border: 1px solid #334155; color: white; border-radius: 12px; box-sizing: border-box; }
        .btn-save { background: var(--color, #00f2fe); color: #0f172a; border: none; padding: 12px 25px; border-radius: 50px; font-weight: bold; cursor: pointer; width: 100%; }
        
        .cat-list-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .actions { display: flex; gap: 10px; }
        .btn-action { text-decoration: none; font-size: 0.85rem; padding: 6px 14px; border-radius: 8px; transition: 0.3s; }
        .btn-edit { border: 1px solid #00f2fe; color: #00f2fe; }
        .btn-edit:hover { background: #00f2fe; color: #0f172a; }
        .btn-delete { border: 1px solid #ff7675; color: #ff7675; }
        .btn-delete:hover { background: #ff7675; color: white; }
    </style>
</head>
<body>

<div class="admin-container">
    <a href="index.php" style="color: #94a3b8; text-decoration: none;">← חזרה לדף הבית</a>
    
    <h1 style="color: #00f2fe; margin-top: 20px;">
        <?php echo $edit_cat ? "📝 עריכת קטגוריה" : "📂 ניהול קטגוריות"; ?>
    </h1>

    <div class="card">
        <h3><?php echo $edit_cat ? "ערוך את: " . htmlspecialchars($edit_cat['name']) : "+ הוסף קטגוריה חדשה"; ?></h3>
        <form method="POST">
            <?php if ($edit_cat): ?>
                <input type="hidden" name="cat_id" value="<?php echo $edit_cat['id']; ?>">
            <?php endif; ?>
            
            <input type="text" name="cat_name" placeholder="שם הקטגוריה" value="<?php echo $edit_cat ? htmlspecialchars($edit_cat['name']) : ''; ?>" required>
            <input type="text" name="cat_icon" placeholder="אייקון (אמוג'י)" value="<?php echo $edit_cat ? htmlspecialchars($edit_cat['icon']) : ''; ?>" required>
            
            <button type="submit" name="save_category" class="btn-save" style="--color: <?php echo $edit_cat ? '#f1c40f' : '#00f2fe'; ?>">
                <?php echo $edit_cat ? "עדכן קטגוריה ✅" : "צור קטגוריה ✨"; ?>
            </button>
            
            <?php if ($edit_cat): ?>
                <a href="manage_categories.php" style="display: block; text-align: center; margin-top: 10px; color: #94a3b8; text-decoration: none; font-size: 0.9rem;">ביטול עריכה</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card">
        <h3>קטגוריות קיימות</h3>
        <?php foreach ($categories as $cat): ?>
            <div class="cat-list-item">
                <span><?php echo $cat['icon'] . " " . htmlspecialchars($cat['name']); ?></span>
                <div class="actions">
                    <a href="?edit_id=<?php echo $cat['id']; ?>" class="btn-action btn-edit">ערוך ✏️</a>
                    <a href="?delete_id=<?php echo $cat['id']; ?>" class="btn-action btn-delete" onclick="return confirm('למחוק?')">מחק 🗑️</a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

</body>
</html>
