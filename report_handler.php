<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    echo json_encode(['success' => false]); exit;
}

$commentId = (int)$_GET['id'];
$stmt = $pdo->prepare("UPDATE comments SET report_count = report_count + 1 WHERE id = ?");
$success = $stmt->execute([$commentId]);

echo json_encode(['success' => $success]);