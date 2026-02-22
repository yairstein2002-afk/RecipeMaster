<?php
session_start();
require_once 'db.php'; // וודא שהקובץ נמצא בתיקיית demo

// 1. חסימת גישה למשתמשים שאינם מחוברים
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg = "";
$error = "";

// 2. שליפת נתוני המשתמש העדכניים מהמסד
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// 3. זיהוי סוג המשתמש (גוגל או רגיל)
$is_google_user = ($user['password'] === 'google_account');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // --- עדכון שם משתמש ---
    if (isset($_POST['update_username'])) {
        $new_username = trim($_POST['new_username']);
        if (!empty($new_username)) {
            try {
                // ניסיון עדכון שם המשתמש בטבלה
                $stmt = $pdo->prepare("UPDATE users SET username = ? WHERE id = ?");
                $stmt->execute([$new_username, $user_id]);
                
                // עדכון השם ב-Session כדי שהשינוי יופיע מיד באתר
                $_SESSION['username'] = $new_username;
                $msg = "שם המשתמש עודכן בהצלחה! ✨";
                
                // עדכון המשתנה המקומי לתצוגה בטופס
                $user['username'] = $new_username;
            } catch (Exception $e) {
                // טיפול במקרה ששם המשתמש החדש כבר קיים במערכת
                $error = "שם המשתמש הזה כבר תפוס, בחר שם אחר 😕";
            }
        }
    }

    // --- עדכון סיסמה (חסום למשתמשי גוגל) ---
    if (isset($_POST['update_password']) && !$is_google_user) {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];

        // אימות הסיסמה הישנה לפני ביצוע השינוי
        if (password_verify($old_pass, $user['password'])) {
            $hashed_new = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_new, $user_id]);
            $msg = "הסיסמה עודכנה בהצלחה! 🔐";
        } else {
            $error = "הסיסמה הישנה שהזנת אינה נכונה ⛔";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>הגדרות פרופיל | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; }
        body { 
            background: radial-gradient(circle at top right, #1e293b, #0f172a); 
            color: white; font-family: 'Segoe UI', sans-serif; 
            display: flex; justify-content: center; min-height: 100vh; margin: 0; padding: 40px;
        }

        .settings-container { width: 100%; max-width: 500px; }
        .card { 
            background: rgba(255, 255, 255, 0.03); 
            padding: 30px; border-radius: 20px; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            backdrop-filter: blur(20px); margin-bottom: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }

        h1 { color: var(--accent); font-size: 2rem; margin-bottom: 30px; }
        h3 { margin-top: 0; opacity: 0.9; }

        input { 
            width: 100%; padding: 12px; margin: 10px 0; 
            background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); 
            color: white; border-radius: 10px; box-sizing: border-box; outline: none;
        }
        input:focus { border-color: var(--accent); }

        .btn { 
            background: linear-gradient(45deg, #4facfe, #00f2fe); 
            color: #0f172a; border: none; padding: 14px; 
            width: 100%; border-radius: 50px; font-weight: bold; 
            cursor: pointer; transition: 0.3s; margin-top: 10px;
        }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 242, 254, 0.3); }

        .google-badge { 
            background: #4285F4; color: white; padding: 6px 15px; 
            border-radius: 50px; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 5px; margin-bottom: 15px;
        }

        .alert { padding: 12px; border-radius: 10px; margin-bottom: 20px; font-weight: bold; }
        .success { background: rgba(0, 255, 136, 0.1); color: #00ff88; border: 1px solid rgba(0, 255, 136, 0.3); }
        .danger { background: rgba(255, 118, 117, 0.1); color: #ff7675; border: 1px solid rgba(255, 118, 117, 0.3); }
        
        a.back-link { color: var(--accent); text-decoration: none; display: block; margin-bottom: 10px; font-weight: bold; }
    </style>
</head>
<body>

<div class="settings-container">
    <a href="index.php" class="back-link">← חזרה לדף הבית</a>
    <h1>הגדרות פרופיל ⚙️</h1>

    <?php if($msg): ?> <div class="alert success"><?php echo $msg; ?></div> <?php endif; ?>
    <?php if($error): ?> <div class="alert danger"><?php echo $error; ?></div> <?php endif; ?>

    <div class="card">
        <h3>שם משתמש</h3>
        <?php if($is_google_user): ?>
            <div class="google-badge">מחובר דרך Google 🌐</div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="text" name="new_username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            <button type="submit" name="update_username" class="btn">עדכן שם משתמש</button>
        </form>
    </div>

    <div class="card">
        <h3>אבטחת חשבון</h3>
        <?php if(!$is_google_user): ?>
            <form method="POST">
                <input type="password" name="old_password" placeholder="סיסמה נוכחית" required>
                <input type="password" name="new_password" placeholder="סיסמה חדשה" required>
                <button type="submit" name="update_password" class="btn" style="background: #f1c40f;">עדכן סיסמה 🔐</button>
            </form>
        <?php else: ?>
            <p style="font-size: 0.9rem; opacity: 0.8;">
                מכיוון שאתה מחובר דרך גוגל, ניהול הסיסמה מתבצע בחשבון הגוגל שלך בלבד.
            </p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>