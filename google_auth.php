<?php
//היא ה"זיכרון לטווח קצר" של האתר שידע איזה משתמש הוא
session_start();
require_once 'db.php';

// קבלת הטוקן בשיטת POST
$token = $_POST['token'] ?? null;

if (!$token) {
    header("Location: login.php?error=auth_failed");
    exit;
}

// אימות מול גוגל
$url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
$response = curl_exec($ch);
$data = json_decode($response, true);
curl_close($ch);

// בדיקת תקינות המפתח (Audience)
//זה מוודא שהמשתמש קיבל אישור מגוגל במיוחד עבור RecipeMaster ולא עבור אף אתר אחר.
if (!isset($data['aud']) || $data['aud'] !== GOOGLE_CLIENT_ID) {
    header("Location: login.php?error=auth_failed");
    exit;
}
//פורק את הציוד שהגיע מגוגל לתוך משתנים שקל לעבוד איתם
$email = $data['email'];
$name = htmlspecialchars($data['name']);
$picture = $data['picture'] ?? 'user-default.png';

// בדיקה אם המשתמש קיים
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

// יצירת סשן חדש ומאובטח
//הפקודה מחליפה את "תעודת הזהות" הזמנית של הסשן (Session ID) בתעודה חדשה לגמרי, ומוחקת את הישנה
session_regenerate_id(true);

if ($user) {
    // אם המשתמש חסום
    if ($user['status'] === 'banned' || (isset($user['is_blocked']) && $user['is_blocked'] == 1)) {
        $reason = urlencode($user['block_reason'] ?? 'הפרת תקנון');
        header("Location: login.php?error=is_blocked&reason=" . $reason);
        exit;
    }
    //שורות הקוד האלו הן "צילום המצב" של המשתמש. הן לוקחות את המידע הקבוע שמצאנו בבסיס הנתונים (ה-Database) ושומרות אותו בתוך ה-Session (הזיכרון לטווח קצר של השרת).
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['status'] = $user['status'];
} else {
    // רישום משתמש חדש (סטטוס ממתין כברירת מחדל לאבטחה)
    $ins = $pdo->prepare("INSERT INTO users (username, email, role, profile_img, status) VALUES (?, ?, 'user', ?, 'pending')");
    $ins->execute([$name, $email, $picture]);
    
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['role'] = 'user';
    $_SESSION['status'] = 'pending';
}

$_SESSION['username'] = $name;
$_SESSION['profile_img'] = $picture;

header("Location: index.php");
exit;