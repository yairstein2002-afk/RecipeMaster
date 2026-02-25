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
    <title>הגדרות פרופיל | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        
        .settings-card { 
            background: var(--glass); backdrop-filter: blur(15px); padding: 40px; 
            border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); 
            width: 100%; max-width: 450px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); 
        }
        
        h2 { color: var(--accent); text-align: center; margin-bottom: 30px; font-size: 2rem; }
        
        /* עיצוב התצוגה המקדימה של האווטאר */
        .profile-preview { 
            width: 130px; height: 130px; border-radius: 50%; object-fit: cover; 
            border: 3px solid var(--accent); display: block; margin: 0 auto 25px; 
            box-shadow: 0 0 20px rgba(0, 242, 254, 0.2);
        }
        
        label { display: block; margin-bottom: 8px; font-size: 0.95rem; font-weight: bold; color: rgba(255,255,255,0.9); }
        
        input[type="text"] { 
            width: 100%; padding: 14px; margin-bottom: 25px; background: rgba(0,0,0,0.2); 
            border: 1px solid rgba(255,255,255,0.1); color: white; border-radius: 12px; 
            outline: none; box-sizing: border-box; font-size: 1rem; transition: 0.3s;
        }
        input[type="text"]:focus { border-color: var(--accent); }
        
        input[type="file"] { 
            width: 100%; padding: 12px; margin-bottom: 30px; background: rgba(0, 242, 254, 0.05); 
            border: 1px dashed var(--accent); cursor: pointer; border-radius: 12px; color: white; 
            box-sizing: border-box;
        }
        
        .btn-save { 
            background: linear-gradient(45deg, #4facfe, #00f2fe); color: #0f172a; 
            border: none; padding: 16px; width: 100%; border-radius: 50px; font-weight: bold; 
            cursor: pointer; transition: 0.3s; font-size: 1.1rem; 
        }
        .btn-save:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 242, 254, 0.3); }
    </style>
</head>
<body>

<div class="settings-card">
    <h2>הפרופיל שלי 👤</h2>
    
    <img src="<?php echo htmlspecialchars($user['profile_img'] ?: 'user-default.png'); ?>" class="profile-preview" id="avatar-preview">

    <form action="update_profile.php" method="POST" enctype="multipart/form-data">
        <label>שם תצוגה בקהילה:</label>
        <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>

        <label>החלפת תמונת פרופיל (עד 5MB):</label>
        <input type="file" name="profile_img" accept="image/*" onchange="previewUpload(event)">

        <button type="submit" class="btn-save">שמור שינויים ✨</button>
        
        <a href="index.php" style="display: block; text-align: center; margin-top: 25px; color: #94a3b8; text-decoration: none; font-size: 0.95rem; transition: 0.3s;">← ביטול וחזרה הביתה</a>
    </form>
</div>

<script>
// מציג את התמונה החדשה מיד עם בחירתה מהמחשב/טלפון
function previewUpload(event) {
    const file = event.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar-preview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}
</script>
</body>
</html>
