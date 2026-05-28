<?php
//זה הכפתור שמפעיל את הזיכרון של האתר לגבי המשתמש הנוכחי.
session_start();
require_once 'db.php';
//השרת יידע מי המשתמש הספציפי שהוא צריך לסמן כ"לא מחובר".
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    
    // הגנה: איפוס זמן הפעילות לעבר הרחוק (למשל 10 דקות אחורה) 
    // זה מבטיח שהמשתמש ייגרע מהמונה באופן מיידי
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() - INTERVAL 10 MINUTE WHERE id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        // גם אם העדכון נכשל, אנחנו ממשיכים בהתנתקות
        error_log("Logout activity update failed: " . $e->getMessage());
    }
}

// השורה הזו היא "איפוס הזיכרון" של השרת לגביך
$_SESSION = array();

// מחיקת עוגיית הסשן מהדפדפן (אבטחה נוספת)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// הריסת הסשן לחלוטין
session_destroy();

// הפניה לדף הלוגין עם הודעת פרידה (אופציונלי)
header("Location: login.php?msg=logged_out");
exit;