<?php
session_start();
require_once 'db.php';

if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    // עדכון זמן פעילות ל-10 דקות אחורה כדי שייעלם מהמונה מיד
    $pdo->prepare("UPDATE users SET last_activity = NOW() - INTERVAL 10 MINUTE WHERE id = ?")->execute([$userId]);
}

session_unset();
session_destroy();
header("Location: login.php");
exit;
?>
