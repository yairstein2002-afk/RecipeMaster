<?php
session_start();
require_once 'db.php';

// בדיקת התחברות בסיסית
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit; 
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'משתמש';
$userRole = $_SESSION['role'] ?? 'user';
$currentTab = $_GET['tab'] ?? 'community';

// --- סנכרון סטטוס וחסימה בזמן אמת מה-DB ---
$stmt_user = $pdo->prepare("SELECT status, `role`, is_blocked FROM users WHERE id = ?");
$stmt_user->execute([$userId]);
$dbUser = $stmt_user->fetch();

if (!$dbUser) { session_destroy(); header("Location: login.php"); exit; }

// עדכון משתני עזר
$userStatus = $dbUser['status'];
$userRole = $dbUser['role'];
$isBlocked = ($dbUser['is_blocked'] == 1 || $userStatus === 'banned');

// --- אבטחה: חסימת גישה לקהילה למשתמשים חסומים ---
if ($currentTab == 'community' && $isBlocked) {
    // אם המשתמש חסום ומנסה להיכנס לצ'אט - נשלח אותו לטאב צור קשר
    header("Location: community.php?tab=contact&error=blocked");
    exit;
}

// עדכון זמן פעילות
$pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?")->execute([$userId]);

// איפוס התראות צ'אט
if ($currentTab == 'community') {
    $pdo->prepare("UPDATE users SET last_chat_read = NOW() WHERE id = ?")->execute([$userId]);
}

// --- לוגיקת ניהול (מחיקת צ'אט) ---
if ($userRole === 'admin' && $currentTab == 'community') {
    if (isset($_GET['clear_all'])) {
        $pdo->query("DELETE FROM messages WHERE type = 'community'");
        header("Location: community.php?tab=community"); exit;
    }
}

// מחיקת הודעה בודדת
if (isset($_GET['delete_id']) && $currentTab == 'community') {
    $delId = (int)$_GET['delete_id'];
    if ($userRole === 'admin') {
        $pdo->prepare("DELETE FROM messages WHERE id = ?")->execute([$delId]);
    } else {
        $pdo->prepare("DELETE FROM messages WHERE id = ? AND user_id = ?")->execute([$delId, $userId]);
    }
    header("Location: community.php?tab=community"); exit;
}

// --- שליחת הודעה חדשה ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !empty(trim($_POST['message'])) && $currentTab == 'community') {
    if ($isBlocked) {
        die("פעולה חסומה: אינך מורשה לשלוח הודעות.");
    }
    $msgText = trim($_POST['message']);
    $pdo->prepare("INSERT INTO messages (user_id, message_text, type) VALUES (?, ?, 'community')")->execute([$userId, $msgText]);
    header("Location: community.php?tab=community"); exit;
}

// --- שליפת הודעות קהילה ---
$messages = [];
if ($currentTab == 'community') {
    $stmt = $pdo->prepare("SELECT m.*, u.username, u.id as author_id FROM messages m JOIN users u ON m.user_id = u.id WHERE m.type = 'community' ORDER BY m.created_at ASC LIMIT 150");
    $stmt->execute();
    $messages = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>קהילה וצור קשר | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --glass: rgba(255, 255, 255, 0.05); --danger: #ff4b2b; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; margin: 0; padding: 15px; }
        
        .top-bar { max-width: 600px; margin: 0 auto 10px; display: flex; justify-content: space-between; align-items: center; }
        .user-tag { color: var(--accent); font-weight: bold; background: var(--glass); padding: 5px 12px; border-radius: 20px; border: 1px solid rgba(0,242,254,0.2); }
        
        .chat-wrapper { max-width: 600px; margin: auto; height: 80vh; display: flex; flex-direction: column; background: var(--glass); backdrop-filter: blur(15px); border-radius: 25px; border: 1px solid rgba(255,255,255,0.1); overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.3); }
        .tabs { display: flex; background: rgba(0,0,0,0.2); }
        .tab { flex: 1; padding: 15px; text-align: center; color: #94a3b8; text-decoration: none; font-weight: bold; transition: 0.3s; }
        .tab.active { color: var(--accent); border-bottom: 3px solid var(--accent); background: rgba(0, 242, 254, 0.05); }
        
        .content { flex: 1; overflow-y: auto; padding: 20px; display: flex; flex-direction: column; gap: 12px; scroll-behavior: smooth; }
        .msg { background: rgba(255,255,255,0.03); padding: 12px; border-radius: 15px; border-right: 3px solid var(--accent); position: relative; }
        
        .contact-container { display: flex; flex-direction: column; gap: 20px; justify-content: center; height: 100%; padding: 10px; }
        .contact-option { background: var(--glass); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 30px; text-align: center; text-decoration: none; color: white; transition: 0.3s ease; }
        .contact-option:hover { transform: translateY(-5px); border-color: var(--accent); background: rgba(255,255,255,0.08); }
        .contact-option h3 { margin: 10px 0 5px; color: var(--accent); font-size: 1.4rem; }
        .contact-icon { font-size: 3rem; display: block; }

        .input-box { padding: 15px; display: flex; gap: 8px; background: rgba(0,0,0,0.3); }
        input { flex: 1; background: #1e293b; border: 1px solid #334155; padding: 12px; color: white; border-radius: 10px; outline: none; }
        button { background: var(--accent); border: none; padding: 0 25px; border-radius: 10px; cursor: pointer; font-weight: bold; color: #0f172a; transition: 0.2s; }
        
        .blocked-msg { padding: 15px; text-align: center; background: rgba(255, 75, 43, 0.1); color: #ff4b2b; font-weight: bold; font-size: 0.9rem; }
    </style>
</head>
<body>

<div class="top-bar">
    <a href="index.php" style="color: #94a3b8; text-decoration: none;">🏠 חזרה לבית</a>
    <div class="user-tag">👤 <?php echo htmlspecialchars($username); ?></div>
    <?php if ($userRole === 'admin' && $currentTab == 'community'): ?>
        <a href="?clear_all=1" style="color:var(--danger); font-size:0.8rem; text-decoration:none;" onclick="return confirm('לנקות את כל הצ\'אט?')">🗑️ ניקוי</a>
    <?php endif; ?>
</div>

<div class="chat-wrapper">
    <div class="tabs">
        <?php if (!$isBlocked): ?>
            <a href="?tab=community" class="tab <?php echo $currentTab=='community'?'active':''; ?>">👥 קהילה</a>
        <?php else: ?>
            <div class="tab" style="opacity:0.5; cursor:not-allowed;">👥 קהילה 🔒</div>
        <?php endif; ?>
        
        <a href="?tab=contact" class="tab <?php echo $currentTab=='contact'?'active':''; ?>">✉️ צור קשר</a>
    </div>

    <div class="content" id="box">
        <?php if ($currentTab == 'community'): ?>
            <?php foreach($messages as $m): ?>
                <div class="msg">
                    <div style="display:flex; justify-content:space-between; font-size:0.75rem; margin-bottom:5px;">
                        <span style="color:var(--accent); font-weight:bold;"><?php echo htmlspecialchars($m['username']); ?></span>
                        <span style="opacity:0.4;"><?php echo date('H:i', strtotime($m['created_at'])); ?></span>
                    </div>
                    <div><?php echo htmlspecialchars($m['message_text']); ?></div>
                    <?php if ($userRole === 'admin' || $m['author_id'] == $userId): ?>
                        <a href="?tab=community&delete_id=<?php echo $m['id']; ?>" style="color:var(--danger); font-size:0.7rem; text-decoration:none; display:inline-block; margin-top:8px;">מחק</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="contact-container">
                <?php if (isset($_GET['error']) && $_GET['error'] === 'blocked'): ?>
                    <div class="blocked-msg">🚫 גישתך לקהילה נחסמה. ניתן ליצור קשר עם המנהל כאן:</div>
                <?php endif; ?>

                <a href="https://wa.me/972508265414" target="_blank" class="contact-option">
                    <span class="contact-icon">💬</span>
                    <h3>וואטסאפ</h3>
                    <p>מענה מהיר לשאלות וערעורים</p>
                </a>

                <a href="mailto:yairstein2002@gmail.com?subject=פנייה בנושא חסימה" class="contact-option">
                    <span class="contact-icon">📧</span>
                    <h3>אימייל</h3>
                    <p>לפניות רשמיות ותמיכה טכנית</p>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($currentTab == 'community'): ?>
        <form class="input-box" method="POST">
            <input type="text" name="message" placeholder="הקלד הודעה לקהילה..." required autocomplete="off">
            <button type="submit">שלח</button>
        </form>
    <?php endif; ?>
</div>

<script>
    const box = document.getElementById('box');
    if ("<?php echo $currentTab; ?>" === "community") {
        box.scrollTop = box.scrollHeight;
    }
</script>
</body>
</html>
