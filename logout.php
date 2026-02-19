<?php
// 1. גישה לסשן הקיים
session_start();

// 2. מחיקת כל הנתונים שנשמרו בסשן (מנקה את הזיכרון של השרת)
session_unset();
session_destroy();

// 3. הפניה חזרה לדף ההתחברות
header("Location: login.php");
exit;