<?php
session_start();
require_once 'db.php';

// אבטחה: רק משתמש מחובר יכול לעדכן
if (!isset($_SESSION['user_id'])) { exit("Access Denied"); }

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';
$newUsername = trim($_POST['username']);
$deleteImageFlag = $_POST['delete_image'] ?? "0"; // מקבל את הסימון מהטופס
$imagePath = null;

// 1. בדיקת ייחודיות שם המשתמש
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->execute([$newUsername, $userId]);
if ($stmt->fetch()) {
    die("שגיאה: שם המשתמש כבר תפוס. בחר שם אחר.");
}

// שליפת נתוני התמונה הנוכחית מה-DB
$oldImgStmt = $pdo->prepare("SELECT profile_img FROM users WHERE id = ?");
$oldImgStmt->execute([$userId]);
$oldImg = $oldImgStmt->fetchColumn();

// 2. טיפול במחיקת תמונה (אם המשתמש לחץ על ה-X)
if ($deleteImageFlag === "1") {
    if ($oldImg && file_exists($oldImg) && !str_contains($oldImg, 'user-default.png')) {
        unlink($oldImg); // מחיקה פיזית מהשרת
    }
    $imagePath = 'user-default.png'; // עדכון לברירת מחדל
} 
// 3. טיפול בהעלאת תמונה חדשה (אם הועלתה כזו)
elseif (!empty($_FILES['profile_img']['name']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/profiles/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true); 
    
    $tempPath = $_FILES['profile_img']['tmp_name'];
    $fileExt = strtolower(pathinfo($_FILES["profile_img"]["name"], PATHINFO_EXTENSION));
    $newFileName = "user_" . $userId . "_" . time();

    // מחיקת תמונה ישנה לפני החלפה
    if ($oldImg && file_exists($oldImg) && !str_contains($oldImg, 'user-default.png')) {
        unlink($oldImg);
    }

    // --- כיווץ חכם (GD) ---
    if (function_exists('imagecreatefromjpeg')) {
        $file_info = getimagesize($tempPath);
        if ($file_info) {
            list($width, $height, $type) = $file_info;
            switch ($type) {
                case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($tempPath); break;
                case IMAGETYPE_PNG:  $source = imagecreatefrompng($tempPath); break;
                case IMAGETYPE_WEBP: $source = imagecreatefromwebp($tempPath); break;
                default: $source = null;
            }

            if ($source) {
                $newSize = 400;
                $virtualImage = imagecreatetruecolor($newSize, $newSize);
                imagealphablending($virtualImage, false);
                imagesavealpha($virtualImage, true);
                imagecopyresampled($virtualImage, $source, 0, 0, 0, 0, $newSize, $newSize, $width, $height);
                
                $finalPath = $targetDir . $newFileName . ".jpg";
                if (imagejpeg($virtualImage, $finalPath, 80)) {
                    $imagePath = $finalPath;
                }
                imagedestroy($source);
                imagedestroy($virtualImage);
            }
        }
    }

    if (!$imagePath) {
        $finalPath = $targetDir . $newFileName . "." . $fileExt;
        if (move_uploaded_file($tempPath, $finalPath)) {
            $imagePath = $finalPath;
        }
    }
}

// 4. לוגיקה לסטטוס (אדמין נשאר מאושר, משתמש חוזר להמתנה)
$newStatus = ($userRole === 'admin') ? 'approved' : 'pending';

// 5. עדכון מסד הנתונים
if ($imagePath) {
    // אם בוצעה מחיקה או החלפה
    $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_img = ?, status = ? WHERE id = ?");
    $stmt->execute([$newUsername, $imagePath, $newStatus, $userId]);
    $_SESSION['profile_img'] = $imagePath;
} else {
    // אם רק השם השתנה
    $stmt = $pdo->prepare("UPDATE users SET username = ?, status = ? WHERE id = ?");
    $stmt->execute([$newUsername, $newStatus, $userId]);
}

// 6. עדכון ה-Session
$_SESSION['username'] = $newUsername;
$_SESSION['status'] = $newStatus;

header("Location: index.php?msg=" . (($newStatus === 'pending') ? "pending_approval" : "profile_updated"));
exit;
