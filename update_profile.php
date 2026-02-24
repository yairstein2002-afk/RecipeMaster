<?php
session_start();
if (!isset($_SESSION['user_id'])) { exit("Access Denied"); }
require_once 'db.php';
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $profile_img = trim($_POST['profile_img']);
    $new_password = $_POST['new_password'];

    try {
        // 1. עדכון שם משתמש ותמונה במסד הנתונים
        $stmt = $pdo->prepare("UPDATE users SET username = ?, profile_img = ? WHERE id = ?");
        $stmt->execute([$username, $profile_img, $userId]);
        
        // רענון הזיכרון המיידי של האתר כדי שהתמונה תשתנה מיד ב-Navbar
        $_SESSION['username'] = $username;
        $_SESSION['profile_img'] = $profile_img;

        // 2. עדכון סיסמה חזקה (רק אם הוקלדה סיסמה חדשה)
        if (!empty($new_password)) {
            $strongRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/';
            if (preg_match($strongRegex, $new_password)) {
                $hashedPass = password_hash($new_password, PASSWORD_DEFAULT);
                $stmtPass = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmtPass->execute([$hashedPass, $userId]);
            } else {
                header("Location: settings.php?error=weak_password");
                exit;
            }
        }

        header("Location: index.php?msg=success");
        exit;

    } catch (PDOException $e) {
        die("שגיאה במסד הנתונים: " . $e->getMessage());
    }
}
?>
