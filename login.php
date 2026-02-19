<?php
// 1. התחלת סשן (Session) - השרת מקצה זיכרון כדי לזכור את המשתמש בין דפים
session_start();

// 2. חיבור למסד הנתונים
require 'db.php';

$message = "";

// 3. בדיקה אם המשתמש שלח את טופס ההתחברות
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // קליטת נתונים וניקוי רווחים
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (!empty($username) && !empty($password)) {
        try {
            // 4. שליפת המשתמש מה-DB לפי שם המשתמש שלו
            $sql = "SELECT id, username, password, is_admin FROM users WHERE username = :username";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            // 5. אימות הסיסמה: password_verify לוקחת את מה שהוקלד ומשווה ל-Hash המוצפן ב-DB
            if ($user && password_verify($password, $user['password'])) {
                
                // 6. הצלחה! שומרים נתונים ב-Session (זה נשמר על השרת ולא בדפדפן)
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['is_admin'] = $user['is_admin'];

                // 7. הפניה אוטומטית לדף הבית
                header("Location: index.php");
                exit; 
                
            } else {
                // אבטחה: לא מגלים אם השם טועה או הסיסמה, כדי להקשות על האקרים
                $message = "<div class='alert alert-danger'>שם משתמש או סיסמה אינם נכונים.</div>";
            }
        } catch (PDOException $e) {
            $message = "<div class='alert alert-danger'>שגיאת מערכת: " . $e->getMessage() . "</div>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>התחברות - RecipeMaster</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="form-container">
        <h2>כניסה למערכת</h2>
        <?php echo $message; ?>
        
        <form method="POST" action="login.php">
            <input type="text" name="username" placeholder="שם משתמש" required>
            <input type="password" name="password" placeholder="סיסמה" required>
            <button type="submit">היכנס</button>
        </form>
        
        <p style="margin-top: 20px;">
            עוד לא רשום? <a href="register.php">צור חשבון חדש</a>
        </p>
    </div>
</body>
</html>