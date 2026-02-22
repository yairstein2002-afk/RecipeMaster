<?php
session_start();
require_once 'db.php';

// אם המשתמש כבר מחובר, שלח אותו לדף הבית
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    // שליפת המשתמש לפי השם
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    // אימות סיסמה מוצפנת
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role']; // קריטי: כאן נשמר אם הוא admin או user
        
        header("Location: index.php");
        exit;
    } else {
        $error = "שם משתמש או סיסמה שגויים. נסה שוב! ⛔";
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>כניסה למערכת | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .login-card { 
            background: rgba(255,255,255,0.05); padding: 40px; border-radius: 25px; 
            border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(15px); 
            width: 100%; max-width: 380px; text-align: center; box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        h2 { color: var(--accent); margin-bottom: 10px; }
        input { 
            width: 100%; padding: 15px; margin: 10px 0; background: #1e293b; 
            border: 1px solid #334155; color: white; border-radius: 12px; box-sizing: border-box; 
            font-size: 1rem; outline: none; transition: 0.3s;
        }
        input:focus { border-color: var(--accent); box-shadow: 0 0 10px rgba(0, 242, 254, 0.2); }
        .btn-login { 
            background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; 
            border: none; padding: 15px; width: 100%; border-radius: 50px; 
            font-weight: bold; cursor: pointer; margin-top: 15px; font-size: 1.1rem; transition: 0.3s;
        }
        .btn-login:hover { transform: scale(1.02); box-shadow: 0 10px 20px rgba(0, 242, 254, 0.3); }
        .footer-links { margin-top: 25px; font-size: 0.9rem; opacity: 0.8; }
        a { color: var(--accent); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="login-card">
        <h2>ברוכים הבאים 👋</h2>
        <p>התחברו כדי לנהל את המתכונים שלכם</p>
        
        <?php if(isset($error)): ?>
            <div style="background: rgba(255,118,117,0.2); color: #ff7675; padding: 10px; border-radius: 10px; margin-bottom: 15px;">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="text" name="username" placeholder="שם משתמש" required>
            <input type="password" name="password" placeholder="סיסמה" required>
            <button type="submit" class="btn-login">כניסה ✨</button>
        </form>

        <div class="footer-links">
            עוד לא הצטרפתם? <a href="register.php">צרו חשבון עכשיו</a>
        </div>
    </div>
</body>
</html>