<?php
session_start();
require_once 'db.php';

// אבטחה: רק מנהל רשאי להיכנס
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') { 
    header("Location: index.php"); 
    exit("Access Denied"); 
}

$adminId = $_SESSION['user_id'];

// שליפת רשימת המשתמשים ששלחו פנייה (מסנן מנהלים כדי שלא תראה את עצמך או את המנהל השני)
$stmt = $pdo->query("
    SELECT u.id, u.username, u.profile_img, MAX(m.created_at) as last_msg
    FROM messages m
    JOIN users u ON m.user_id = u.id
    WHERE m.type = 'admin' 
    AND u.role != 'admin' -- מציג רק לקוחות, לא מנהלים
    GROUP BY u.id
    ORDER BY last_msg DESC
");
$chats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ניהול פניות | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 30px; margin: 0; }
        .container { max-width: 600px; margin: auto; }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--accent); padding-bottom: 10px; margin-bottom: 20px; }
        h1 { margin: 0; font-size: 1.8rem; }
        
        .user-card { 
            background: var(--glass); padding: 15px; border-radius: 12px; 
            margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;
            text-decoration: none; color: white; border: 1px solid rgba(255,255,255,0.1);
            transition: 0.3s ease;
        }
        .user-card:hover { background: rgba(255,255,255,0.1); border-color: var(--accent); transform: scale(1.01); }
        
        .user-info { display: flex; align-items: center; gap: 15px; }
        .user-img { width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent); }
        
        .badge { background: var(--accent); color: #0f172a; padding: 6px 15px; border-radius: 8px; font-weight: bold; font-size: 0.9rem; }
        .back-link { color: #94a3b8; text-decoration: none; font-size: 0.9rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>הודעות ממשתמשים 📩</h1>
            <a href="index.php" class="back-link">← חזרה</a>
        </div>

        <?php if(empty($chats)): ?> 
            <p style="text-align:center; opacity:0.5; margin-top: 50px;">אין פניות בבסיס הנתונים כרגע.</p> 
        <?php endif; ?>
        
        <?php foreach($chats as $chat): ?>
            <a href="community.php?tab=contact&chat_with=<?php echo $chat['id']; ?>" class="user-card">
                <div class="user-info">
                    <img src="<?php echo htmlspecialchars($chat['profile_img'] ?: 'user-default.png'); ?>" class="user-img">
                    <div>
                        <strong><?php echo htmlspecialchars($chat['username']); ?></strong>
                        <div style="font-size: 0.75rem; opacity: 0.5;">
                            פנייה אחרונה: <?php echo date('H:i | d/m', strtotime($chat['last_msg'])); ?>
                        </div>
                    </div>
                </div>
                <div class="badge">פתח שיחה</div>
            </a>
        <?php endforeach; ?>
    </div>
</body>
</html>