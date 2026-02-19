<?php
// 1. קריאה לקובץ החיבור לבסיס הנתונים
// הפונקציה require מבטיחה שאם חסר הקובץ db.php, הדף יעצור ולא ינסה להמשיך לרוץ סתם
require 'db.php';

// משתנה ריק שישמור את ההודעות (הצלחה/שגיאה) שנציג למשתמש על המסך
$message = "";

// 2. בדיקה האם המשתמש לחץ על כפתור "הירשם" (כלומר, האם הטופס נשלח בשיטת POST)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // 3. קליטת הנתונים מהטופס וניקוי רווחים מיותרים בהתחלה ובסוף (פונקציית trim)
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);

    // 4. וולידציה (Validation) בסיסית בצד השרת: מוודאים שהמשתמש לא עקף את הדפדפן ושלח שדות ריקים
    if (!empty($username) && !empty($email) && !empty($password)) {
        
        // 5. אבטחת מידע - הצפנת סיסמה! (הדגש ההנדסי שלך)
        // הפונקציה password_hash לוקחת את הסיסמה הרגילה והופכת אותה לגיבוב (Hash) מסוג BCRYPT באופן אוטומטי
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        try {
            // 6. כתיבת שאילתת ההכנסה (INSERT) למסד הנתונים
            // שים לב: אנחנו לא מכניסים את המשתנים ישירות לשאילתה! במקום זה אנחנו משתמשים ב"שומרי מקום" (כמו :username)
            // שיטה זו נקראת Prepared Statements, והיא מונעת לחלוטין פריצות מסוג SQL Injection
            $sql = "INSERT INTO users (username, email, password) VALUES (:username, :email, :password)";
            
            // השרת מכין את התבנית של השאילתה מראש
            $stmt = $pdo->prepare($sql);
            
            // 7. ביצוע (Execute) של השאילתה - כאן אנחנו משדכים בין "שומרי המקום" לנתונים האמיתיים מהטופס
            $stmt->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hashed_password // קריטי: אנחנו שומרים ב-DB רק את הסיסמה המוצפנת!
            ]);

            // אם הגענו לשורה הזו מבלי לקרוס, סימן שההכנסה ל-DB הצליחה
            $message = "<div style='color: green; font-weight: bold;'>ההרשמה בוצעה בהצלחה! הרשומה נוצרה. <a href='login.php'>התחבר כאן</a></div>";
            
        } catch (PDOException $e) {
            // 8. טיפול בשגיאות (Exception Handling)
            // אם מישהו מנסה להירשם עם אימייל או שם משתמש שכבר קיימים, בסיס הנתונים יזרוק שגיאה
            // הקוד 23000 ב-MySQL אומר "הפרת חוק ה-UNIQUE שהגדרנו בטבלה"
            if ($e->getCode() == 23000) {
                $message = "<div style='color: red;'>שגיאה: שם המשתמש או האימייל כבר קיימים במערכת.</div>";
            } else {
                // שגיאות אחרות (למשל, השרת נפל פתאום)
                $message = "<div style='color: red;'>שגיאה כללית: " . $e->getMessage() . "</div>";
            }
        }
    } else {
        $message = "<div style='color: red;'>נא למלא את כל השדות.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>הרשמה - RecipeMaster</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="form-container">
        <h2>הרשמה למערכת</h2>
        
        <?php echo $message; ?>
        
        <form method="POST" action="">
            <input type="text" name="username" placeholder="שם משתמש" required>
            <input type="email" name="email" placeholder="אימייל" required>
            <input type="password" name="password" placeholder="סיסמה" required>
            <button type="submit">הירשם עכשיו</button>
        </form>
    </div>

</body>
</html>