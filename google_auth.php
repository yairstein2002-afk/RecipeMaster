<?php
session_start();
require_once 'db.php'; // כאן נמצא הפורט 3307 המעודכן שלך

// מוודאים שבאמת קיבלנו טוקן מגוגל
if (!isset($_GET['token'])) {
    header("Location: login.php?error=auth_failed");
    exit;
}

$token = $_GET['token'];

// 1. פנייה מאובטחת לשרתים של גוגל לפענוח המידע
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
$response = @file_get_contents($url);
if (!$response) {
    header("Location: login.php?error=auth_failed");
    exit;
}
$payload = json_decode($response, true);

// 2. אם גוגל אישרה שהכל תקין
if (isset($payload['email'])) {
    $email = $payload['email'];
    $name = $payload['name'] ?? explode('@', $email)[0]; 
    $picture = $payload['picture'] ?? 'user-default.png'; 

    // 3. בדיקה מול מסד הנתונים: האם המשתמש כבר קיים?
    // ה-PDO כאן משתמש בפורט 3307 שהגדרת ב-db.php
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user) {
        // --- בדיקת אבטחה: האם המשתמש חסום? ---
        if ($user['status'] === 'banned') {
            header("Location: login.php?error=banned");
            exit;
        }

        // --- תסריט א': משתמש קיים ---
        if (empty($user['profile_img'])) {
            $update = $pdo->prepare("UPDATE users SET profile_img = ? WHERE id = ?");
            $update->execute([$picture, $user['id']]);
            $user['profile_img'] = $picture;
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['status'] = $user['status']; 
        $_SESSION['profile_img'] = $user['profile_img'];
        
    } else {
        // --- תסריט ב': משתמש חדש לגמרי ---
        $insert = $pdo->prepare("INSERT INTO users (username, email, password, role, profile_img, status) VALUES (?, ?, 'google_account', 'user', ?, 'pending')");
        $insert->execute([$name, $email, $picture]);
        
        $newId = $pdo->lastInsertId();
        
        $_SESSION['user_id'] = $newId;
        $_SESSION['username'] = $name;
        $_SESSION['role'] = 'user';
        $_SESSION['status'] = 'pending'; 
        $_SESSION['profile_img'] = $picture;
    }

    header("Location: index.php");
    exit;
} else {
    header("Location: login.php?error=auth_failed");
    exit;
}
?>
