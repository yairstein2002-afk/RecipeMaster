<?php
session_start();
require_once 'db.php';

/**
 * שכבת הגנה 1: בדיקת התחברות בסיסית
 */
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php");
    exit; 
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

/**
 * שכבת הגנה 2: סנכרון חסימה וסיבה בזמן אמת (סגירת קצה פתוח)
 * שליפת הסטטוס וסיבת החסימה ישירות מהמסד כדי למנוע עקיפות סשן
 */
$stmt_check = $pdo->prepare("SELECT status, is_blocked, block_reason FROM users WHERE id = ?");
$stmt_check->execute([$userId]);
$userData = $stmt_check->fetch();

// בדיקה אם המשתמש נחסם או הושעה בזמן שהיה מחובר
if (!$userData || $userData['is_blocked'] == 1 || $userData['status'] === 'banned') {
    // שליחת סיבת החסימה כפרמטר לדף הלוגין
    $reason = urlencode($userData['block_reason'] ?? 'הפרת תנאי השימוש באתר');
    session_destroy();
    header("Location: login.php?error=is_blocked&reason=" . $reason);
    exit;
}

// ניקוי קלטים למניעת רווחים מיותרים
$newUsername = trim($_POST['username']);
$deleteImageFlag = $_POST['delete_image'] ?? "0";
$imagePath = null;

/**
 * 1. בדיקת ייחודיות שם המשתמש
 * מוודא שהשם החדש לא תפוס על ידי משתמש אחר
 */
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
$stmt->execute([$newUsername, $userId]);
if ($stmt->fetch()) {
    header("Location: settings.php?error=username_taken");
    exit;
}

// שליפת נתיב התמונה הנוכחית מה-DB לצורך ניקוי פיזי של קבצים
$oldImg = $pdo->prepare("SELECT profile_img FROM users WHERE id = ?");
$oldImg->execute([$userId]);
$oldImgPath = $oldImg->fetchColumn();

/**
 * 2. טיפול במחיקת תמונה (חזרה לברירת מחדל)
 */
if ($deleteImageFlag === "1") {
    if ($oldImgPath && file_exists($oldImgPath) && !str_contains($oldImgPath, 'user-default.png')) {
        unlink($oldImgPath); // מחיקה פיזית מהתיקייה uploads
    }
    $imagePath = 'user-default.png';
} 

/**
 * 3. טיפול בהעלאת תמונה חדשה וכיווץ (GD Library)
 * סוגר קצה פתוח: אבטחת סוג קובץ ואופטימיזציה של שטח אחסון
 */
elseif (!empty($_FILES['profile_img']['name']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
    $targetDir = "uploads/profiles/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true); 
    
    $tempPath = $_FILES['profile_img']['tmp_name'];
    $file_info = getimagesize($tempPath);
    
    if ($file_info) {
        list($width, $height, $type) = $file_info;
        
        // יצירת משאב תמונה לפי סוג הקובץ המקורי
        switch ($type) {
            case IMAGETYPE_JPEG: $source = imagecreatefromjpeg($tempPath); break;
            case IMAGETYPE_PNG:  $source = imagecreatefrompng($tempPath); break;
            case IMAGETYPE_WEBP: $source = imagecreatefromwebp($tempPath); break;
            default: $source = null;
        }

        if ($source) {
            // מחיקת התמונה הישנה לפני החלפה כדי למנוע הצטברות קבצים
            if ($oldImgPath && file_exists($oldImgPath) && !str_contains($oldImgPath, 'user-default.png')) {
                unlink($oldImgPath);
            }

            // יצירת תמונה מרובעת בגודל אופטימלי (400x400)
            $newSize = 400; 
            $virtualImage = imagecreatetruecolor($newSize, $newSize);
            
            // הגדרות שקיפות (עבור PNG/WebP)
            imagealphablending($virtualImage, false);
            imagesavealpha($virtualImage, true);
            imagecopyresampled($virtualImage, $source, 0, 0, 0, 0, $newSize, $newSize, $width, $height);
            
            $newFileName = "user_" . $userId . "_" . time() . ".jpg";
            $finalPath = $targetDir . $newFileName;

            // שמירה בפורמט JPG דחוס (איכות 85) לחיסכון במקום
            if (imagejpeg($virtualImage, $finalPath, 85)) {
                $imagePath = $finalPath;
            }
            
            // שחרור זיכרון השרת (חשוב בשרתים עם משאבים מוגבלים)
            imagedestroy($source);
            imagedestroy($virtualImage);
        }
    }
}

/**
 * 4. לוגיקת סטטוס
 * מנהל נשאר מאושר, משתמש רגיל חוזר להמתנה (Pending) לאחר שינוי פרטים
 */
$newStatus = ($userRole === 'admin') ? 'approved' : 'pending';

/**
 * 5. עדכון מסד הנתונים
 */
try {
    if ($imagePath) {
        // עדכון כולל תמונה
        $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_img = ?, status = ? WHERE id = ?");
        $stmt->execute([$newUsername, $imagePath, $newStatus, $userId]);
        $_SESSION['profile_img'] = $imagePath;
    } else {
        // עדכון שם בלבד
        $stmt = $pdo->prepare("UPDATE users SET username = ?, status = ? WHERE id = ?");
        $stmt->execute([$newUsername, $newStatus, $userId]);
    }

    /**
     * 6. עדכון ה-Session לשימוש מיידי באתר
     */
    $_SESSION['username'] = $newUsername;
    $_SESSION['status'] = $newStatus;

    // הפניה לדף הבית עם הודעה מתאימה
    header("Location: index.php?msg=" . (($newStatus === 'pending') ? "pending_approval" : "profile_updated"));
    exit;

} catch (Exception $e) {
    // תיעוד שגיאה למניעת קריסה (קצה פתוח: Error Handling)
    error_log("Update Profile Error: " . $e->getMessage());
    die("שגיאה בעדכון הפרופיל. נא לנסות שוב מאוחר יותר.");
}
