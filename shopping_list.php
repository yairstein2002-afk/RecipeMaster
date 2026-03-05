<?php
session_start();
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
require_once 'db.php';

$userId = $_SESSION['user_id'];

// שליפת הנתונים הנוכחיים של המשתמש
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) { die("משתמש לא נמצא."); }

// בדיקה אם למשתמש יש תמונה שהיא לא ברירת המחדל
$currentImg = $user['profile_img'] ?: 'user-default.png';
$isDefault = (strpos($currentImg, 'user-default.png') !== false);
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>הגדרות פרופיל | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); --danger: #ff4757; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .settings-card { background: var(--glass); backdrop-filter: blur(15px); padding: 40px; border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); width: 100%; max-width: 450px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
        h2 { color: var(--accent); text-align: center; margin-bottom: 10px; font-size: 2rem; }
        .status-info { text-align: center; font-size: 0.85rem; margin-bottom: 25px; color: #94a3b8; }
        
        /* מיכל לתמונת הפרופיל עם כפתור מחיקה */
        .profile-container { position: relative; width: 130px; height: 130px; margin: 0 auto 25px; }
        .profile-preview { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; border: 3px solid var(--accent); box-shadow: 0 0 20px rgba(0, 242, 254, 0.2); }
        
        .btn-delete-img { 
            position: absolute; top: 0; left: 0; 
            background: var(--danger); color: white; 
            border: none; border-radius: 50%; 
            width: 32px; height: 32px; 
            cursor: pointer; display: flex; 
            align-items: center; justify-content: center; 
            font-weight: bold; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            transition: 0.2s;
            z-index: 10;
        }
        .btn-delete-img:hover { transform: scale(1.1); background: #ff6b81; }

        label { display: block; margin-bottom: 8px; font-size: 0.95rem; font-weight: bold; color: rgba(255,255,255,0.9); }
        input[type="text"] { width: 100%; padding: 14px; margin-bottom: 25px; background: rgba(0,0,0,0.2); border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 12px; outline: none; box-sizing: border-box; font-size: 1rem; }
        input[type="file"] { width: 100%; padding: 12px; margin-bottom: 30px; background: rgba(0, 242, 254, 0.05); border: 1px dashed var(--accent); cursor: pointer; border-radius: 12px; color: white; box-sizing: border-box; }
        .btn-save { background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; border: none; padding: 16px; width: 100%; border-radius: 50px; font-weight: bold; cursor: pointer; transition: 0.3s; font-size: 1.1rem; }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 242, 254, 0.3); }
        .alert { background: rgba(255, 165, 2, 0.2); color: #ffa502; padding: 10px; border-radius: 10px; font-size: 0.9rem; text-align: center; margin-bottom: 20px; border: 1px solid #ffa502; }
    </style>
</head>
<body>

<div class="settings-card">
    <h2>הפרופיל שלי 👤</h2>
    <p class="status-info">סטטוס נוכחי: <b><?php echo ($user['status'] == 'approved') ? '✅ מאושר' : '⏳ ממתין לאישור'; ?></b></p>
    
    <?php if(isset($_SESSION['pending_msg'])): ?>
        <div class="alert"><?php echo $_SESSION['pending_msg']; unset($_SESSION['pending_msg']); ?></div>
    <?php endif; ?>

    <div class="profile-container">
        <img src="<?php echo htmlspecialchars($currentImg); ?>" class="profile-preview" id="avatar-preview">
        <button type="button" class="btn-delete-img" id="remove-btn" 
                onclick="removePhoto()" 
                style="<?php echo $isDefault ? 'display:none;' : ''; ?>">✕</button>
    </div>

    <form action="update_profile.php" method="POST" enctype="multipart/form-data">
        <input type="hidden" name="delete_image" id="delete-image-flag" value="0">

        <label>שם תצוגה בקהילה:</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label>החלפת תמונת פרופיל:</label>
        <input type="file" name="profile_img" id="file-input" accept="image/*" onchange="previewUpload(event)">

        <button type="submit" class="btn-save">שמור שינויים ✨</button>
        <p style="font-size: 0.75rem; text-align: center; margin-top: 10px; opacity: 0.6;">* כל שינוי בפרטים יחייב אישור מנהל מחדש.</p>
        
        <a href="index.php" style="display: block; text-align: center; margin-top: 25px; color: #94a3b8; text-decoration: none;">← ביטול וחזרה הביתה</a>
    </form>
</div>

<script>
function previewUpload(event) {
    const file = event.target.files[0];
    const preview = document.getElementById('avatar-preview');
    const removeBtn = document.getElementById('remove-btn');
    const deleteFlag = document.getElementById('delete-image-flag');

    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) { 
            preview.src = e.target.result; 
            removeBtn.style.display = 'flex'; // מציג את ה-X כי נבחרה תמונה חדשה
            deleteFlag.value = "0"; // מבטל סימון מחיקה אם היה
        }
        reader.readAsDataURL(file);
    }
}

function removePhoto() {
    if(confirm("האם למחוק את תמונת הפרופיל ולחזור לברירת מחדל?")) {
        const preview = document.getElementById('avatar-preview');
        const fileInput = document.getElementById('file-input');
        const removeBtn = document.getElementById('remove-btn');
        const deleteFlag = document.getElementById('delete-image-flag');

        preview.src = 'user-default.png'; // חוזר לתצוגת ברירת מחדל
        fileInput.value = ""; // מאפס את בחירת הקובץ אם הייתה
        removeBtn.style.display = 'none'; // מחביא את ה-X
        deleteFlag.value = "1"; // מסמן ל-PHP שצריך למחוק את התמונה מה-DB
    }
}
</script>
</body>
</html>
