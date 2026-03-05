<?php
session_start();
// אבטחה: רק משתמש מחובר יכול לבצע מחיקה
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

require_once 'db.php';

$recipeId = $_GET['id'] ?? null;
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

if ($recipeId) {
    try {
        $pdo->beginTransaction();

        // 1. אבטחה קריטית: מוודאים שהמתכון שייך למשתמש או שמדובר באדמין
        $checkStmt = $pdo->prepare("SELECT id FROM recipes WHERE id = ? AND (user_id = ? OR ? = 'admin')");
        $checkStmt->execute([$recipeId, $userId, $userRole]);
        
        if ($checkStmt->fetch()) {
            // 2. מחיקת נתונים מקושרים (מצרכים והוראות) כדי למנוע שגיאות זבל במסד
            $pdo->prepare("DELETE FROM ingredients WHERE recipe_id = ?")->execute([$recipeId]);
            $pdo->prepare("DELETE FROM instructions WHERE recipe_id = ?")->execute([$recipeId]);
            
            // 3. מחיקת המתכון עצמו
            $pdo->prepare("DELETE FROM recipes WHERE id = ?")->execute([$recipeId]);
            
            $pdo->commit();
            header("Location: my_recipes.php?msg=deleted");
            exit;
        } else {
            die("אין לך הרשאה למחוק מתכון זה.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה בתהליך המחיקה: " . $e->getMessage());
    }
} else {
    header("Location: my_recipes.php");
    exit;
}
?>
