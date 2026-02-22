<?php
session_start();
require_once 'db.php';

// אם המשתמש כבר מחובר, אין טעם שיירשם שוב
if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'user';

    try {
        // וודא שקיימת עמודת email בטבלת users בבסיס הנתונים
        $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->execute([$username, $password, $role]);
        header("Location: login.php?msg=registered");
        exit;
    } catch (Exception $e) {
        $error = "שם המשתמש כבר תפוס, נסה משהו מקורי יותר 😕";
    }
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הצטרפות ל-RecipeMaster</title>
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
            width: 100%; max-width: 400px; 
            text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5);
        }

        h2 { color: var(--accent); margin-bottom: 10px; font-size: 1.8rem; }
        p { opacity: 0.7; margin-bottom: 25px; font-size: 0.95rem; }

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
        .btn-login:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0, 242, 254, 0.3); }

        .divider { 
            display: flex; align-items: center; margin: 25px 0; 
            color: rgba(255,255,255,0.3); font-size: 0.8rem; 
        }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: rgba(255,255,255,0.1); margin: 0 10px; }

        .footer-links { margin-top: 25px; font-size: 0.9rem; }
        a { color: var(--accent); text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

<div class="auth-card">
    <h2>הצטרפו לקהילה 👨‍🍳</h2>
    <p>הירשמו והתחילו לשתף מתכונים</p>

    <div id="g_id_onload"
         data-client_id="910090533768-549ntk92mvijvukgj5nf13o2odp3otkp.apps.googleusercontent.com"
         data-context="signup"
         data-ux_mode="popup"
         data-callback="handleCredentialResponse"
         data-auto_prompt="false">
    </div>
    <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="filled_blue" data-text="signup_with" data-size="large" data-width="100%"></div>

    <div class="divider">או הרשמה רגילה</div>

    <?php if(isset($error)): ?>
        <p style="color: #ff7675; font-size: 0.9rem;"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="בחרו שם משתמש" required>
        <input type="password" name="password" placeholder="בחרו סיסמה חזקה" required>
        <button type="submit" class="btn-login">יצירת חשבון 🚀</button>
    </form>

    <div class="footer-links">
        כבר רשומים? <a href="login.php">התחברו כאן</a>
    </div>
</div>

<script>
   function handleCredentialResponse(response) {
    // הניתוב המדויק לפי המיקום שציינת
    window.location.href = "http://localhost:8080/demo/google_auth.php?token=" + response.credential;
}
</script>

</body>
</html>