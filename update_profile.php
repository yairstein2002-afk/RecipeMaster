<?php
session_start();
if (!isset($_SESSION['user_id'])) { exit("Access Denied"); }
require_once 'db.php';
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    
    // מושכים את התמונה הנוכחית כברירת מחדל (למקרה שהוא רצה לשנות רק את השם)
    $profile_img_path = $_SESSION['profile_img'] ?? 'user-default.png';

    try {
        // --- 1. טיפול בהעלאת התמונה החדשה (אם נבחרה) ---
        if (isset($_FILES['profile_img']) && $_FILES['profile_img']['error'] === UPLOAD_ERR_OK) {
            $tmp_name = $_FILES['profile_img']['tmp_name'];
            $file_name = $_FILES['profile_img']['name'];
            $file_size = $_FILES['profile_img']['size'];
            
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (in_array($file_ext, $allowed_exts) && $file_size <= 5000000) { // עד 5MB
                // יצירת שם קובץ ייחודי כדי למנוע דריסת תמונות של משתמשים אחרים
                $new_file_name = uniqid('profile_', true) . '.' . $file_ext;
                $upload_dir = 'uploads/profiles/';
                
                if (!is_dir($upload_dir)) { mkdir($upload_dir, 0777, true); }
                
                $destination = $upload_dir . $new_file_name;
                
                // העברת הקובץ ושמירת הנתיב החדש
                if (move_uploaded_file($tmp_name, $destination)) {
                    $profile_img_path = $destination;
                }
            } else {
                die("שגיאה: הקובץ חייב להיות תמונה וגודלו עד 5MB.");
            }
        }

        // --- 2. עדכון מסד הנתונים ---
        $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_img = ? WHERE id = ?");
        $stmt->execute([$username, $profile_img_path, $userId]);
        
        // --- 3. עדכון הזיכרון המיידי (Session) ---
        // חשוב כדי שהתמונה והשם יתעדכנו מיד בסרגל הניווט בכל האתר
        $_SESSION['username'] = $username;
        $_SESSION['profile_img'] = $profile_img_path;

        // חזרה לדף הבית בהצלחה
        header("Location: index.php?msg=profile_updated");
        exit;

    } catch (Exception $e) {
        die("שגיאה בעדכון הנתונים: " . $e->getMessage());
    }
} else {
    // מניעת גישה ישירה לקובץ ללא שליחת טופס
    header("Location: settings.php");
    exit;
}
?>
