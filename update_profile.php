<?php
session_start(); // אתחול הסשן כדי לגשת לנתוני המשתמש המחובר
require_once 'db.php'; // חיבור למסד הנתונים (PDO)

/**
 * שכבת הגנה 1: בדיקת התחברות
 */
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); // אם לא מחובר, הפניה לדף התחברות
    exit; 
}

$userId = $_SESSION['user_id']; // מזהה המשתמש מהסשן
$userEmail = $_SESSION['email'] ?? ''; // המייל של המשתמש (קריטי לתמונת הדיפולט)
$userRole = $_SESSION['role'] ?? 'user'; // תפקיד המשתמש (אדמין או משתמש רגיל)

/**
 * הכנת תמונת ברירת המחדל מהאימייל (Gravatar)
 * יוצרת תמונה אוטומטית לפי המייל של המשתמש אם אין לו תמונה בשרת
 */
$emailHash = md5(strtolower(trim($userEmail))); // יצירת קוד ייחודי מהמייל
$gravatarUrl = "https://www.gravatar.com/avatar/" . $emailHash . "?d=mp&s=400"; // קישור לתמונת המייל

/**
 * שכבת הגנה 2: שליפת נתונים עדכניים מהמסד
 */
$stmt_check = $pdo->prepare("SELECT profile_img, status, is_blocked FROM users WHERE id = ?");
$stmt_check->execute([$userId]);
$userData = $stmt_check->fetch(); // שליפת הנתונים הנוכחיים

// אם המשתמש חסום - ננתק אותו מיד
if (!$userData || $userData['is_blocked'] == 1 || $userData['status'] === 'banned') {
    session_destroy();
    header("Location: login.php?error=is_blocked");
    exit;
}

$oldImgPath = $userData['profile_img']; // נתיב התמונה שקיים כרגע במסד
$newUsername = trim($_POST['username']); // שם המשתמש החדש מהטופס
$deleteImageFlag = $_POST['delete_image'] ?? "0"; // האם המשתמש סימן "מחיקה"
$finalImagePath = $oldImgPath; // כברירת מחדל, נשארים עם מה שיש

/**
 * 1. בדיקה אם השם החדש תפוס
 */
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->execute([$newUsername, $userId]);
if ($stmt->fetch()) {
    header("Location: settings.php?error=username_taken");
    exit;
}

/**
 * 2. לוגיקת טיפול בתמונה - מניעת תמונות שבורות
 */

// מקרה א': המשתמש ביקש למחוק את התמונה שהעלה (חזרה לתמונת המייל)
if ($deleteImageFlag === "1") {
    if ($oldImgPath && file_exists($oldImgPath)) {
        unlink($oldImgPath); // מחיקה פיזית של הקובץ מהתיקייה
    }
    $finalImagePath = ""; // ננקה את השדה במסד כדי להשתמש במייל כברירת מחדל
} 

// מקרה ב': המשתמש העלה תמונה חדשה
elseif (!empty($_FILES['profile_img']['name']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/profiles/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true); // יצירת תיקייה אם חסרה
    
    $tempPath = $_FILES['profile_img']['tmp_name'];
    $file_info = getimagesize($tempPath); // אימות שהקובץ הוא באמת תמונה
    
    if ($file_info) {
        $type = $file_info[2];
        switch ($type) { // יצירת משאב תמונה לפי הפורמט (JPG/PNG/WEBP)
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($tempPath); break;
            case IMAGETYPE_PNG:  $source = imagecreatefrompng($tempPath); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($tempPath); break;
            default: $source = null;
        }

        if ($source) {
            // מחיקת התמונה הישנה לפני שמירת החדשה
            if ($oldImgPath && file_exists($oldImgPath)) { unlink($oldImgPath); }

            // עיבוד התמונה לגודל מרובע אחיד 400x400
            $newSize = 400;
            $virtualImage = imagecreatetruecolor($newSize, $newSize);
            imagealphablending($virtualImage, false); // שמירה על שקיפות
            imagesavealpha($virtualImage, true);
            imagecopyresampled($virtualImage, $source, 0, 0, 0, 0, $newSize, $newSize, $file_info[0], $file_info[1]);
            
            $newFileName = "user_" . $userId . "_" . time() . ".jpg"; // שם קובץ ייחודי
            $savePath = $targetDir . $newFileName; // נתיב סופי

            if (imagejpeg($virtualImage, $savePath, 85)) { // שמירה בפורמט JPG איכותי
                $finalImagePath = $savePath;
            }
            imagedestroy($source); // ניקוי זיכרון השרת
            imagedestroy($virtualImage);
        }
    }
}

/**
 * 3. עדכון מסד הנתונים וסנכרון הסשן (החלק שמונע שבירה)
 */
try {
    // מנהל נשאר מאושר, משתמש רגיל חוזר להמתנה לאחר שינוי פרטים
    $newStatus = ($userRole === 'admin') ? 'approved' : 'pending';
    
    // עדכון ה-DB
    $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_img = ?, status = ? WHERE id = ?");
    $stmt->execute([$newUsername, $finalImagePath, $newStatus, $userId]);

    // סנכרון הסשן - כדי שהאתר יתעדכן מיד ללא לוגין מחדש
    $_SESSION['username'] = $newUsername;
    $_SESSION['status'] = $newStatus;
    
    // בדיקה חכמה לסשן: אם השדה במסד ריק, נשים בסשן את תמונת המייל
    $_SESSION['profile_img'] = (!empty($finalImagePath)) ? $finalImagePath : $gravatarUrl;

    // הפניה חזרה לדף הבית
    header("Location: index.php?msg=success");
    exit;

} catch (Exception $e) {
    error_log("Update Error: " . $e->getMessage()); // רישום שגיאה בשרת
    die("חלה שגיאה בעיבוד הנתונים.");
}