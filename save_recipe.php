$videoUrl = null;
$localVideoUrl = null;

if ($_POST['video_type'] === 'youtube') {
    $videoUrl = $_POST['video_url'];
} else {
    // טיפול בהעלאת קובץ
    if (isset($_FILES['recipe_video']) && $_FILES['recipe_video']['error'] === 0) {
        $maxSize = 20 * 1024 * 1024; // 20MB
        if ($_FILES['recipe_video']['size'] <= $maxSize) {
            $videoName = time() . '_' . $_FILES['recipe_video']['name'];
            $targetPath = 'uploads/videos/' . $videoName;
            if (move_uploaded_file($_FILES['recipe_video']['tmp_name'], $targetPath)) {
                $localVideoUrl = $targetPath;
            }
        }
    }
}

// ב-INSERT ל-DB נכניס את $videoUrl לעמודה video_url 
// ואת $localVideoUrl לעמודה local_video_url
