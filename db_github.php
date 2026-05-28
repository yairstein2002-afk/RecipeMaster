<?php
/**
 * RecipeMaster - Database Configuration & Core Security
 * * @author Yair Stein
 * @version 1.0
 */

// Environment check: Determine if running on localhost or production server
$isLocal = in_array($_SERVER['REMOTE_ADDR'], ['127.0.0.1', '::1']);

// Dynamic connection settings
if ($isLocal) {
    // Development Environment (XAMPP/Local)
    $host     = '127.0.0.1';
    $dbname   = 'recipemaster';
    $username = 'root';
    $password = ''; // Local password is empty by default
    $port     = 3307;
} else {
    // Production Environment (Remote Server)
    // NOTE: In a production environment, these should ideally be stored in environment variables
    $host     = 'your_production_host'; 
    $dbname   = 'your_production_db';
    $username = 'your_production_user';
    $password = '********'; // Never push real passwords to GitHub!
    $port     = 3306;
}

// Google Authentication Key
define('GOOGLE_CLIENT_ID', 'your_google_client_id_here');

try {
    $dsn = "mysql:host=$host;dbname=$dbname;port=$port;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Throw exceptions on errors
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Return data as associative arrays
        PDO::ATTR_EMULATE_PREPARES   => false,                  // Use real prepared statements for security
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"     // Ensure full UTF-8 support (Hebrew, Emojis)
    ];

    $pdo = new PDO($dsn, $username, $password, $options);

} catch (PDOException $e) {
    // Log error internally and show a user-friendly message
    error_log("Database Connection Error: " . $e->getMessage());
    die("Internal Server Error. Please try again later.");
}

/**
 * Clean strings to prevent XSS (Cross-Site Scripting) attacks
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}