<?php
// 1. ניהול סשן ואבטחה
session_start();
// אם המשתמש לא מחובר (אין user_id בסשן), שלח אותו לדף הלוגין
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// 2. חיבור למסד הנתונים MySQL
require_once 'db_config.php'; 
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// 3. שאילתה לשליפת מתכונים מה-MySQL
// אנחנו שולפים רק את המתכונים של המשתמש המחובר ומסדרים מהחדש לישן
$stmt = $conn->prepare("SELECT * FROM recipes WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RecipeMaster - לוח בקרה</title>
    
    <link rel="stylesheet" href="assets/css/style.css">

    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/10.7.1/firebase-database-compat.js"></script>

    <script>
        // פרטי החיבור האישיים שלך (מה-Console)
        const firebaseConfig = {
            apiKey: "AIzaSyDNeBkG47FgukK2AKnipDd0I3BKKDs-G54",
            authDomain: "recipemaster-db.firebaseapp.com",
            projectId: "recipemaster-db",
            storageBucket: "recipemaster-db.firebasestorage.app",
            messagingSenderId: "171514516397",
            appId: "1:171514516397:web:7768d4a780cca41c908724",
            databaseURL: "https://recipemaster-db-default-rtdb.europe-west1.firebasedatabase.app"
        };
        firebase.initializeApp(firebaseConfig);
        const database = firebase.database();
    </script>
</head>
<body>

    <div class="form-container" style="max-width: 1100px; width: 95%; margin: 20px auto;">
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
            <h2 style="margin: 0;">שלום, <?php echo htmlspecialchars($username); ?>! 👨‍🍳</h2>
            <a href="logout.php" style="color: #ff7675; text-decoration: none; font-weight: bold;">התנתקות</a>
        </div>

        <div style="margin-bottom: 30px; text-align: left;">
            <button onclick="location.href='add_recipe.php'" style="width: auto; padding: 12px 25px; background: #00b894; border-radius: 10px;">
                + הוסף מתכון חדש
            </button>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px;">
            
            <?php if ($result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <div class="recipe-card" style="background: rgba(255,255,255,0.08); padding: 20px; border-radius: 20px; border: 1px solid rgba(255,255,255,0.1); backdrop-filter: blur(10px);">
                        <h3 style="margin-top: 0; color: #fff;"><?php echo htmlspecialchars($row['title']); ?></h3>
                        <p style="color: rgba(255,255,255,0.8); font-size: 0.95em; line-height: 1.6; min-height: 50px;">
                            <?php echo nl2br(htmlspecialchars($row['description'])); ?>
                        </p>

                        <?php if (!empty($row['video_url'])): ?>
                            <?php 
                                // פונקציה קטנה שמחלצת את ה-ID של הסרטון (11 תווים) מכל סוג של לינק יוטיוב
                                $video_id = "";
                                if (preg_match('/(v=|v\/|embed\/|youtu.be\/)([^"&?\/\s]{11})/', $row['video_url'], $match)) {
                                    $video_id = $match[2];
                                }
                            ?>
                            <?php if ($video_id): ?>
                                <div style="margin-top: 15px; border-radius: 15px; overflow: hidden;">
                                    <iframe width="100%" height="200" 
                                        src="https://www.youtube.com/embed/<?php echo $video_id; ?>" 
                                        frameborder="0" allowfullscreen>
                                    </iframe>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div style="margin-top: 15px; text-align: left;">
                            <small style="color: rgba(255,255,255,0.4);">הועלה ב: <?php echo date('d/m/Y', strtotime($row['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div style="grid-column: 1/-1; text-align: center; padding: 50px; background: rgba(255,255,255,0.05); border-radius: 20px;">
                    <p style="color: rgba(255,255,255,0.5);">אין עדיין מתכונים להצגה.</p>
                </div>
            <?php endif; ?>

        </div>
    </div>

</body>
</html>