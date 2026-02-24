<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';
$userId = $_SESSION['user_id'];

// שליפת הנתונים הקיימים כדי להציג אותם בטופס
$stmt = $pdo->prepare("SELECT username, profile_img FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>הגדרות חשבון | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .settings-card { background: var(--glass); backdrop-filter: blur(15px); padding: 40px; border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); width: 100%; max-width: 450px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
        h2 { color: var(--accent); text-align: center; margin-bottom: 30px; }
        .profile-preview { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); display: block; margin: 0 auto 20px; }
        label { display: block; margin-bottom: 8px; font-size: 0.9rem; opacity: 0.8; }
        input { width: 100%; padding: 12px; margin-bottom: 20px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 12px; outline: none; box-sizing: border-box; }
        .password-container { position: relative; width: 100%; }
        .password-container input { padding-left: 45px; }
        .toggle-password { position: absolute; left: 15px; top: 12px; cursor: pointer; opacity: 0.6; transition: 0.3s; user-select: none; }
        .btn-save { background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; border: none; padding: 15px; width: 100%; border-radius: 50px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 242, 254, 0.3); }
        .msg { font-size: 0.8rem; margin-top: -15px; margin-bottom: 15px; min-height: 1.2em; }
    </style>
</head>
<body>

<div class="settings-card">
    <h2>הגדרות חשבון ⚙️</h2>
    
    <img src="<?php echo htmlspecialchars($user['profile_img'] ?: 'user-default.png'); ?>" class="profile-preview" id="avatar-preview">

    <form action="update_profile.php" method="POST" onsubmit="return validateForm()">
        <label>שם משתמש:</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label>קישור לתמונת פרופיל:</label>
        <input type="text" name="profile_img" id="img_input" value="<?php echo htmlspecialchars($user['profile_img']); ?>" oninput="updatePreview(this.value)">

        <label>סיסמה חדשה (חזקה):</label>
        <div class="password-container">
            <input type="password" name="new_password" id="pass_input" placeholder="השאר ריק אם אין שינוי" onkeyup="checkStrength(this.value)">
            <span class="toggle-password" onclick="togglePasswordVisibility()">👁️</span>
        </div>
        <div id="pass-msg" class="msg"></div>

        <button type="submit" class="btn-save">שמור שינויים ✨</button>
        <a href="index.php" style="display: block; text-align: center; margin-top: 20px; color: #94a3b8; text-decoration: none; font-size: 0.9rem;">חזרה לדף הבית</a>
    </form>
</div>

<script>
function togglePasswordVisibility() {
    const passInput = document.getElementById('pass_input');
    const toggleIcon = document.querySelector('.toggle-password');
    if (passInput.type === "password") {
        passInput.type = "text";
        toggleIcon.innerText = "🔒";
    } else {
        passInput.type = "password";
        toggleIcon.innerText = "👁️";
    }
}

function updatePreview(url) {
    document.getElementById('avatar-preview').src = url || 'user-default.png';
}

function checkStrength(pass) {
    const msg = document.getElementById('pass-msg');
    if (pass.length === 0) { msg.innerText = ""; return; }
    const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
    if (strongRegex.test(pass)) {
        msg.innerText = "סיסמה חזקה ✅";
        msg.style.color = "#55efc4";
    } else {
        msg.innerText = "נדרש: 8+ תווים, אות גדולה, קטנה, מספר ותו מיוחד.";
        msg.style.color = "#ff7675";
    }
}

function validateForm() {
    const pass = document.getElementById('pass_input').value;
    if (pass.length > 0) {
        const strongRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;
        if (!strongRegex.test(pass)) {
            alert("הסיסמה חייבת להיות חזקה לפני השמירה.");
            return false;
        }
    }
    return true;
}
</script>
</body>
</html>
