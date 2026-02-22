<?php
// הגדרות התחברות לשרת ולמסד הנתונים
$host = 'localhost';
$dbname = 'recipemaster';
$username = 'root'; // ברירת מחדל ב-XAMPP
$password = '';     // ב-XAMPP הסיסמה ריקה בברירת מחדל

try {
    // יצירת החיבור בפועל (DSN) עם תמיכה בעברית (utf8mb4)
    $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";
    
    // הגדרות אבטחה והתנהגות (Options)
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // זרוק שגיאה אם משהו לא עובד
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // החזר תוצאות כמערך אסוציאטיבי נוח
        PDO::ATTR_EMULATE_PREPARES   => false,                  // אבטחה: השתמש ב-Prepared Statements אמיתיים
    ];

    // יצירת אובייקט החיבור ($pdo)
    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    // אם החיבור נכשל - עצור הכל והצג שגיאה
    die("שגיאה בחיבור לבסיס הנתונים: " . $e->getMessage());
}
?>