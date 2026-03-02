<?php
// ... (קוד ה-PHP של ההתחברות והשליפה נשאר זהה) ...
?>
<?php foreach ($notifications as $n): ?>
    <div class="notif-card" style="background: var(--glass); padding: 15px; border-radius: 12px; margin-bottom: 10px; border: 1px solid rgba(255,255,255,0.1);">
        <?php if ($n['recipe_id'] == 0): ?>
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 1.5rem;">🛡️</span>
                <div>
                    <strong style="color: var(--accent);">הודעת מערכת:</strong> 
                    חל שינוי בסטטוס החשבון שלך (אישור/קידום). בדוק את הרשאותיך.
                </div>
            </div>
        <?php else: ?>
            <strong><?php echo htmlspecialchars($n['actor_name']); ?></strong> 
            הגיב למתכון שלך: <span style="color: var(--accent);"><?php echo htmlspecialchars($n['recipe_title']); ?></span>
        <?php endif; ?>
        
        <div style="font-size: 0.75rem; opacity: 0.5; margin-top: 8px;">
            <?php echo date('H:i, d/m', strtotime($n['created_at'])); ?>
        </div>
    </div>
<?php endforeach; ?>