<?php
session_start();
require_once 'db.php';

// הגנה בסיסית: אם המשתמש לא מחובר, הקובץ לא מחזיר כלום
if (!isset($_SESSION['user_id'])) exit;

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'user';

// קבלת ה-ID של ההודעה האחרונה שיש לדפדפן כרגע
$lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

if ($lastId > 0) {
    // שליפת הודעות חדשות בלבד - יעיל מאוד לשרתים חינמיים
    $stmt = $pdo->prepare("SELECT m.*, u.username, u.email, u.profile_img, u.id as author_id 
                           FROM messages m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.type = 'community' AND m.id > ? 
                           ORDER BY m.created_at ASC");
    $stmt->execute([$lastId]);
} else {
    // טעינה ראשונית בלבד - שליפת 50 הודעות אחרונות כדי למלא את המסך
    $stmt = $pdo->prepare("SELECT m.*, u.username, u.email, u.profile_img, u.id as author_id 
                           FROM messages m 
                           JOIN users u ON m.user_id = u.id 
                           WHERE m.type = 'community' 
                           ORDER BY m.created_at ASC LIMIT 50");
    $stmt->execute();
}

$messages = $stmt->fetchAll();

foreach($messages as $m): 
    $isMine = ($m['author_id'] == $userId);
    
    // יצירת Gravatar למקרה שאין תמונת פרופיל
    $emailHash = md5(strtolower(trim($m['email'] ?? '0')));
    $fallbackImg = "https://www.gravatar.com/avatar/$emailHash?d=mp";
    
    // בדיקת קיום קובץ תמונה (שים לב: בשרתים מסוימים file_exists דורש נתיב מלא)
    $chatImg = (!empty($m['profile_img']) && file_exists($m['profile_img'])) 
               ? $m['profile_img'] 
               : $fallbackImg;
?>
    <div class="msg-wrapper" data-id="<?php echo $m['id']; ?>">
        <div class="msg <?php echo $isMine ? 'msg-me' : 'msg-others'; ?>">
            <div class="msg-info">
                <?php if (!$isMine): ?>
                    <img src="<?php echo htmlspecialchars($chatImg); ?>" 
                         class="chat-avatar" 
                         onerror="this.src='<?php echo $fallbackImg; ?>';">
                <?php endif; ?>
                <span style="color:var(--accent); font-weight:bold; font-size:0.8rem;">
                    <?php echo $isMine ? 'אתה' : htmlspecialchars($m['username']); ?>
                </span>
            </div>
            
            <div style="word-break: break-word;">
                <?php echo htmlspecialchars($m['message_text']); ?>
            </div>
            
            <div style="text-align: left; margin-top: 4px;">
                <span class="msg-time">
                    <?php echo date('d/m/Y | H:i', strtotime($m['created_at'])); ?>
                </span>
                
                <?php if ($userRole === 'admin' || $isMine): ?>
                    <a href="?tab=community&delete_id=<?php echo $m['id']; ?>" 
                       style="color:#ff4b2b; font-size:0.65rem; text-decoration:none; margin-right:8px;"
                       onclick="return confirm('למחוק?')">מחק</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>