<?php
session_start();
require_once 'db.php';

// --- שכבת הגנה 1: בדיקת התחברות בסיסית ---
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// --- שכבת הגנה 2: סנכרון חסימה בזמן אמת (סגירת קצה פתוח) ---
$stmt_check = $pdo->prepare("SELECT status, is_blocked FROM users WHERE id = ?");
$stmt_check->execute([$userId]);
$userData = $stmt_check->fetch();

if (!$userData || $userData['is_blocked'] == 1 || $userData['status'] === 'banned') {
    session_destroy();
    header("Location: login.php?error=is_blocked");
    exit;
}

$recipeId = $_GET['id'] ?? null;

if ($recipeId) {
    try {
        $pdo->beginTransaction();

        // 1. שליפת נתוני המתכון כולל נתיב התמונה לפני המחיקה
        $stmt_file = $pdo->prepare("SELECT image_url FROM recipes WHERE id = ? AND (user_id = ? OR ? = 'admin')");
        $stmt_file->execute([$recipeId, $userId, $userRole]);
        $recipe = $stmt_file->fetch();

        if ($recipe) {
            $imageUrl = $recipe['image_url'];

            // 2. מחיקת נתונים מקושרים מה-Database
            // הערה: אם הגדרת ON DELETE CASCADE במסד הנתונים, השורות האלו בונוס לביטחון
            $pdo->prepare("DELETE FROM ingredients WHERE recipe_id = ?")->execute([$recipeId]);
            $pdo->prepare("DELETE FROM instructions WHERE recipe_id = ?")->execute([$recipeId]);
            $pdo->prepare("DELETE FROM comments WHERE recipe_id = ?")->execute([$recipeId]);
            $pdo->prepare("DELETE FROM likes WHERE recipe_id = ?")->execute([$recipeId]);
            
            // 3. מחיקת המתכון עצמו מה-Database
            $pdo->prepare("DELETE FROM recipes WHERE id = ?")->execute([$recipeId]);

            // 4. ביצוע ה-Commit למסד הנתונים
            $pdo->commit();

            // 5. סגירת קצה פתוח: מחיקה פיזית של הקובץ מהשרת (רק אחרי שה-DB הצליח)
            if ($imageUrl && $imageUrl !== 'default.jpg' && file_exists($imageUrl)) {
                unlink($imageUrl);
            }

            header("Location: my_recipes.php?status=deleted");
            exit;
        } else {
            die("שגיאה: המתכון לא נמצא או שאין לך הרשאה למחוק אותו.");
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        die("שגיאה בתהליך המחיקה: " . $e->getMessage());
    }
} else {
    header("Location: my_recipes.php");
    exit;
}
