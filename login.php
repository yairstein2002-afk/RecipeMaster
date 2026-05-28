<?php
// חיבור לקובץ הגדרות בסיס הנתונים (מכיל את ה-PDO והגדרות גוגל)
require_once 'db.php'; 

// קבלת פרמטרים מהכתובת (URL) וניקוי תווים מסוכנים למניעת פריצות
$error = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_SPECIAL_CHARS);
$msg = filter_input(INPUT_GET, 'msg', FILTER_SANITIZE_SPECIAL_CHARS);

// אתחול משתנים להודעות שיוצגו למשתמש
$error_msg = ""; 
$success_msg = ""; 

// בדיקה אם המשתמש נחסם - הוספת הודעה עם קישור ישיר לוואטסאפ
if ($error === 'is_blocked') {
    $error_msg = "🚫 חשבונך נחסם. <a href='https://wa.me/972508265414' target='_blank' class='contact-link'>לחץ כאן ליצירת קשר עם המנהל</a>";
} 
// בדיקה אם המשתמש ממתין לאישור מנהל (למשל ברישום ראשוני)
elseif ($error === 'pending') {
    $error_msg = "⏳ חשבונך ממתין לאישור מנהל. הכניסה תתאפשר לאחר האישור.";
}
// הודעה במידה והמשתמש לא מחובר ומנסה לגשת לדפים מוגנים
elseif ($error === 'login_required') {
    $error_msg = "🔒 עליך להתחבר כדי לגשת לדף הקהילה.";
}

// הודעת הצלחה לאחר התנתקות מסודרת מהמערכת
if ($msg === 'logged_out') {
    $success_msg = "✅ התנתקת מהמערכת בהצלחה. נתראה בקרוב!";
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
        /* משתני עיצוב לשמירה על שפה עיצובית אחידה בכל האתר */
        :root { --accent: #ff4757; --bg: #0f172a; --error: #ff4757; --success: #2ed573; }
        
        /* עיצוב הרקע כגרדיאנט עמוק ומודרני */
        body { 
            background: radial-gradient(circle at top right, #1e293b, #0f172a); 
            color: white; font-family: 'Segoe UI', sans-serif; 
            display: flex; align-items: center; justify-content: center; 
            min-height: 100vh; margin: 0; flex-direction: column; 
        }

        /* כרטיס הכניסה עם אפקט זכוכית (Glassmorphism) */
        .auth-card { 
            background: rgba(255, 255, 255, 0.03); padding: 50px 40px; border-radius: 30px; 
            border: 1px solid rgba(255, 255, 255, 0.1); backdrop-filter: blur(20px); 
            text-align: center; width: 100%; max-width: 380px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }

        /* עיצוב תיבות ההודעה (שגיאות והצלחות) */
        .msg-box { padding: 12px; border-radius: 12px; margin-bottom: 25px; font-size: 0.9rem; border: 1px solid; line-height: 1.4; }
        .error-box { background: rgba(255, 71, 87, 0.15); color: var(--error); border-color: var(--error); }
        .success-box { background: rgba(46, 213, 115, 0.15); color: var(--success); border-color: var(--success); }

        /* עיצוב הקישור ליצירת קשר בתוך הודעת החסימה */
        .contact-link { color: var(--accent); text-decoration: underline; font-weight: bold; }
        
        /* כפתור כניסה כאורח */
        .btn-guest { display: block; margin-top: 25px; color: #94a3b8; text-decoration: none; font-size: 0.9rem; transition: 0.3s; }
        .btn-guest:hover { color: white; }

        /* עיצוב הפוטר בתחתית הדף */
        footer { margin-top: 30px; display: flex; gap: 20px; font-size: 0.8rem; opacity: 0.5; }
        footer a { color: white; text-decoration: none; }
    </style>
</head>
<body>

<div class="auth-card">
    <h1>RecipeMaster 👨‍🍳</h1>
    <p style="opacity: 0.7; margin-bottom: 30px;">התחבר כדי לשמור ולשתף מתכונים</p>

    <?php if ($error_msg): ?>
        <div class="msg-box error-box" role="alert"><?php echo $error_msg; ?></div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
        <div class="msg-box success-box" role="alert"><?php echo htmlspecialchars($success_msg); ?></div>
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
/**
 * פונקציה המופעלת על ידי גוגל לאחר בחירת חשבון.
 * היא יוצרת טופס "בלתי נראה" ושולחת את ה-Token לשרת לצורך אימות.
 */
function handleCredentialResponse(response) {
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