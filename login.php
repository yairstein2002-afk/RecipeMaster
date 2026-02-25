<?php
session_start();
// אם המשתמש כבר מחובר, אין סיבה שיישאר פה
if (isset($_SESSION['user_id'])) { 
    header("Location: index.php"); 
    exit; 
}
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>כניסה | RecipeMaster</title>
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { background: radial-gradient(circle at top right, #1e293b, #0f172a); color: white; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        
        .auth-card { 
            background: rgba(255, 255, 255, 0.03); padding: 50px 40px; 
            border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.1); 
            backdrop-filter: blur(20px); width: 100%; max-width: 380px; 
            text-align: center; box-shadow: 0 25px 50px rgba(0,0,0,0.5); 
        }
        
        h1 { color: var(--accent); margin-bottom: 10px; font-size: 2.2rem; }
        p { color: rgba(255,255,255,0.7); margin-bottom: 40px; font-size: 1.1rem; }

        /* מפריד ויזואלי */
        .divider { display: flex; align-items: center; margin: 30px 0 20px; color: rgba(255,255,255,0.4); font-size: 0.85rem; }
        .divider::before, .divider::after { content: ""; flex: 1; height: 1px; background: rgba(255,255,255,0.1); margin: 0 10px; }

        /* עיצוב כפתור האורח */
        .btn-guest {
            display: block;
            background: transparent;
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 12px;
            width: 100%;
            border-radius: 50px;
            font-weight: bold;
            text-decoration: none;
            font-size: 1rem;
            transition: 0.3s;
            box-sizing: border-box;
        }
        .btn-guest:hover {
            background: rgba(255,255,255,0.05);
            border-color: var(--accent);
            color: var(--accent);
        }
    </style>
</head>
<body>

<div class="auth-card">
    <h1>RecipeMaster 👨‍🍳</h1>
    <p>התחבר כדי לשמור ולשתף מתכונים</p>

    <div id="g_id_onload"
         data-client_id="910090533768-549ntk92mvijvukgj5nf13o2odp3otkp.apps.googleusercontent.com"
         data-context="use"
         data-ux_mode="popup"
         data-callback="handleCredentialResponse"
         data-auto_prompt="false">
    </div>
    <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="filled_blue" data-text="continue_with" data-size="large" data-width="100%"></div>

    <div class="divider">או</div>

    <a href="index.php" class="btn-guest">המשך כאורח 👤</a>
</div>

<script>
// הפונקציה שמופעלת מיד אחרי שהמשתמש בוחר את החשבון שלו בגוגל
function handleCredentialResponse(response) {
    window.location.href = "google_auth.php?token=" + response.credential;
}
</script>

</body>
</html>
