<?php
session_start();
require_once 'db.php';

// אבטחה: רק משתמש מחובר יכול לעדכן
if (!isset($_SESSION['user_id'])) { exit("Access Denied"); }

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';
$newUsername = trim($_POST['username']);
$imagePath = null;

// 1. בדיקת ייחודיות שם המשתמש (מניעת כפילויות)
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->execute([$newUsername, $userId]);
if ($stmt->fetch()) {
    die("שגיאה: שם המשתמש כבר תפוס. בחר שם אחר.");
}

// 2. טיפול בהעלאת התמונה
if (!empty($_FILES['profile_img']['name'])) {
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true); 
    
    $fileExt = strtolower(pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION));
    $newFileName = "user_" . $userId . "_" . time() . "." . $fileExt;
    $targetFile = $targetDir . $newFileName;

    $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($fileExt, $allowedTypes)) {
        die("שגיאה: ניתן להעלות רק תמונות מסוג JPG, PNG או WEBP.");
    }

    if ($_FILES["profile_img"]["size"] > 5 * 1024 * 1024) {
        die("שגיאה: התמונה גדולה מדי (מקסימום 5MB).");
    }

    // בונוס: מחיקת התמונה הישנה מהתיקייה לפני שמירת החדשה
    $oldImgStmt = $pdo->prepare("SELECT profile_img FROM users WHERE id = ?");
    $oldImgStmt->execute([$userId]);
    $oldImg = $oldImgStmt->fetchColumn();
    if ($oldImg && file_exists($oldImg) && $oldImg != 'user-default.png') {
        unlink($oldImg);
    }

    if (move_uploaded_file($_FILES["profile_img"]["tmp_name"], $targetFile)) {
        $imagePath = $targetFile;
    }
}

// 3. לוגיקה הרמטית לסטטוס
// מנהל תמיד נשאר approved. משתמש רגיל חוזר ל-pending לאישור מחדש.
$newStatus = ($userRole === 'admin') ? 'approved' : 'pending';

// 4. עדכון מסד הנתונים
if ($imagePath) {
    $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_img = ?, status = ? WHERE id = ?");
    $stmt->execute([$newUsername, $imagePath, $newStatus, $userId]);
    $_SESSION['profile_img'] = $imagePath;
} else {
    $stmt = $pdo->prepare("UPDATE users SET username = ?, status = ? WHERE id = ?");
    $stmt->execute([$newUsername, $newStatus, $userId]);
}

// 5. עדכון ה-Session בזמן אמת (קריטי להמשך גלישה חלקה)
$_SESSION['username'] = $newUsername;
$_SESSION['status'] = $newStatus;

// 6. הפניה בהתאם לסטטוס
if ($newStatus === 'pending') {
    header("Location: index.php?msg=pending_approval");
} else {
    header("Location: index.php?msg=profile_updated");
}
exit;
?>
