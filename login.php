<?php
session_start();
require_once 'db.php';

// אם המשתמש כבר מחובר - שלח לדף הבית
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // חיפוש המשתמש במסד הנתונים
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // אימות סיסמה ובדיקה שהמשתמש אינו משתמש גוגל בלבד (שאין לו סיסמה מקומית)
    if ($user && $user['password'] !== 'google_account' && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        // וודא שבטבלה שלך יש עמודת role, אחרת השורה הבאה תזרוק שגיאה
        if(isset($user['role'])) {
            $_SESSION['role'] = $user['role'];
        }
        header("Location: index.php");
        exit;
    } else {
        $error = "שם משתמש או סיסמה שגויים ⛔";
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>כניסה | RecipeMaster</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { 
            background: radial-gradient(circle at top right, #1e293b, #0f172a); 
            color: white; font-family: 'Segoe UI', sans-serif; 
            display: flex; align-items: center; justify-content: center; 
            min-height: 100vh; margin: 0;
        }
        .auth-card { 
            background: rgba(255, 255, 255, 0.03); 
            padding: 40px; border-radius: 30px; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            backdrop-filter: blur(20px); 
            width: 100%; max-width: 380px; 
            text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }
        h2 { color: var(--accent); margin-bottom: 25px; }
        input { 
            width: 100%; padding: 15px; margin: 10px 0; 
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
            color: white; border-radius: 12px; box-sizing: border-box; outline: none; transition: 0.3s;
        }
        input:focus { border-color: var(--accent); background: rgba(255,255,255,0.1); }
        .btn-login { 
            background: linear-gradient(45deg, #4facfe, #00f2fe); 
            color: #0f172a; border: none; padding: 16px; 
            width: 100%; border-radius: 50px; font-weight: bold; 
            cursor: pointer; margin-top: 15px; font-size: 1rem; transition: 0.3s;
        }
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 242, 254, 0.4); }
        .divider { 
            display: flex; align-items: center; margin: 25px 0; 
            color: rgba(255,255,255,0.3); font-size: 0.8rem; 
        }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: rgba(255,255,255,0.1); margin: 0 10px; }
        a { color: var(--accent); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="auth-card">
    <h2>התחברות 🔑</h2>

    <div id="g_id_onload"
         data-client_id="910090533768-549ntk92mvijvukgj5nf13o2odp3otkp.apps.googleusercontent.com"
         data-context="signin"
         data-ux_mode="popup"
         data-callback="handleCredentialResponse"
         data-auto_prompt="false">
    </div>
    <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="filled_blue" data-text="signin_with" data-size="large" data-width="100%"></div>

    <div class="divider">או באמצעות סיסמה</div>

    <?php if(isset($error)) echo "<p style='color:#ff7675; font-size:0.9rem;'>$error</p>"; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="שם משתמש" required>
        <input type="password" name="password" placeholder="סיסמה" required>
        <button type="submit" class="btn-login">כניסה למערכת ✨</button>
    </form>

    <div style="margin-top: 25px; font-size: 0.9rem; opacity: 0.8;">
        חדשים כאן? <a href="register.php">צרו חשבון 🚀</a>
    </div>
</div>

<script>
function handleCredentialResponse(response) {
    // הניתוב המדויק לתיקיית demo בפורט 8080
    window.location.href = "http://localhost:8080/demo/google_auth.php?token=" + response.credential;
}
</script>

</body>
</html>