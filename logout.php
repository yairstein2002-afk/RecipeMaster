<?php
session_start();
session_unset(); // ניקוי כל המשתנים בסשן
session_destroy(); // השמדת הסשן לחלוטין
header("Location: login.php");
exit;
?>