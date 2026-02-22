<?php
session_start();
require_once 'db.php'; // וודא שקובץ זה נמצא בתיקיית demo ומחובר למסד הנכון

if (isset($_GET['token'])) {
    $id_token = $_GET['token'];

    // 1. אימות הטוקן מול גוגל
    $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $id_token;
    $response = @file_get_contents($url);
    $payload = json_decode($response, true);

    if ($payload && isset($payload['email'])) {
        $email = $payload['email'];
        
        // 2. בדיקה אם המשתמש קיים (לפי אימייל)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user) {
            // 3. יצירת משתמש חדש אם לא קיים
            $username = explode('@', $email)[0];
            
            // שימוש בברירת המחדל של הטבלה הקיימת שלך
            $stmt = $pdo->prepare("INSERT INTO users (username, email, role, password) VALUES (?, ?, 'user', 'google_account')");
            $stmt->execute([$username, $email]);

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
        }

        // 4. רישום ב-Session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // ניתוב לדף הבית בתוך תיקיית ה-demo
        header("Location: index.php?msg=connected");
        exit;
    } else {
        die("שגיאה: אימות החשבון נכשל. וודא שה-Client ID מוגדר נכון.");
    }
} else {
    header("Location: login.php");
    exit;
}