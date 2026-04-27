<?php
require_once 'db.php';

// ניקוי והגנה על הודעות שגיאה
$error = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);
$error_msg = "";

if ($error === 'is_blocked') {
    $reason = filter_input(INPUT_GET, 'reason', FILTER_SANITIZE_SPECIAL_CHARS) ?: "הפרת תקנון";
    $error_msg = "🚫 חשבונך נחסם: " . $reason;
} elseif ($error === 'pending') {
    $error_msg = "⏳ חשבונך ממתין לאישור מנהל. הכניסה תתאפשר לאחר האישור.";
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
        body { background: radial-gradient(circle at top right, #1e293b, #0f172a); color: white; font-family: 'Segoe UI', sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; flex-direction: column; }
        .auth-card { background: rgba(255, 255, 255, 0.03); padding: 50px 40px; border-radius: 30px; border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); text-align: center; width: 100%; max-width: 380px; box-shadow: 0 25px 50px rgba(0,0,0,0.5); }
        .error-box { background: rgba(255, 71, 87, 0.2); color: #ff4757; padding: 12px; border-radius: 12px; margin-bottom: 25px; border: 1px solid #ff4757; font-size: 0.9rem; }
        .btn-guest { display: block; margin-top: 20px; color: white; text-decoration: none; opacity: 0.7; font-size: 0.9rem; transition: 0.3s; }
        .btn-guest:hover { opacity: 1; color: var(--accent); }
        footer { margin-top: 30px; font-size: 0.8rem; opacity: 0.5; }
        footer a { color: white; margin: 0 10px; text-decoration: none; border-bottom: 1px dashed; }
    </style>
</head>
<body>

<div class="auth-card">
    <h1>RecipeMaster 👨‍🍳</h1>
    <p>התחבר כדי לשמור ולשתף מתכונים</p>

    <?php if ($error_msg): ?>
        <div class="error-box" role="alert"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <div id="g_id_onload"
         data-client_id="<?php echo GOOGLE_CLIENT_ID; ?>"
         data-callback="handleCredentialResponse">
    </div>
    <div class="g_id_signin" data-type="standard" data-shape="pill" data-theme="filled_blue" data-width="100%"></div>

    <a href="index.php" class="btn-guest">המשך כאורח 👤</a>
</div>

<footer>
    <a href="accessibility.html">נגישות</a>
    <a href="privacy.html">פרטיות</a>
    <a href="terms.html">תנאים</a>
</footer>

<script>
function handleCredentialResponse(response) {
    // שליחה ב-POST מאובטח כדי שהטוקן לא ייחשף ב-URL
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'google_auth.php';
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'token';
    input.value = response.credential;
    form.appendChild(input);
    document.body.appendChild(form);
    form.submit();
}
</script>
</body>
</html>