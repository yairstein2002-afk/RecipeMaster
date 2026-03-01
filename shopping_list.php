<?php
session_start();
require_once 'db.php';
?>
<!DOCTYPE html>
<html lang="he" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>רשימת הקניות שלי | RecipeMaster</title>
    <style>
        :root { --accent: #00f2fe; --bg: #0f172a; --card-bg: rgba(255,255,255,0.05); --danger: #ff4757; --wa: #25D366; --success: #2ecc71; }
        body { background: var(--bg); color: white; font-family: 'Segoe UI', sans-serif; padding: 20px; margin: 0; }
        .container { max-width: 600px; margin: 0 auto; }
        .header-box { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 15px; }
        
        .shopping-item { background: var(--card-bg); padding: 15px; border-radius: 15px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border: 1px solid rgba(255,255,255,0.1); transition: 0.3s; }
        .item-info { flex: 1; font-size: 1.05rem; }
        .item-info.checked { text-decoration: line-through; opacity: 0.4; color: #94a3b8; }
        .amount-highlight { color: var(--accent); font-weight: bold; margin-left: 5px; font-size: 1.2rem; }

        .btn { border: none; padding: 10px; border-radius: 12px; cursor: pointer; font-weight: bold; transition: 0.2s; display: flex; align-items: center; gap: 5px; }
        .btn-check { background: rgba(46, 204, 113, 0.1); color: var(--success); }
        .btn-check.active { background: var(--success); color: white; }
        .btn-remove { background: rgba(255, 71, 87, 0.1); color: var(--danger); }
        
        .actions-group { margin-top: 40px; display: flex; flex-direction: column; gap: 15px; }
        .btn-wa { background: var(--wa); color: white; width: 100%; justify-content: center; font-size: 1.1rem; border-radius: 50px; padding: 15px; text-decoration: none; box-shadow: 0 4px 15px rgba(37, 211, 102, 0.2); }
        .btn-delete-all { background: rgba(255, 71, 87, 0.1); color: var(--danger); border: 1px solid var(--danger); width: 100%; padding: 15px; border-radius: 50px; justify-content: center; transition: 0.3s; }
        .btn-delete-all:hover { background: var(--danger); color: white; }

        #emptyMsg { text-align: center; margin-top: 80px; opacity: 0.6; }
    </style>
</head>
<body>

<div class="container">
    <div class="header-box">
        <h1 style="margin:0;">הסל שלי 🛒</h1>
        <a href="index.php" style="color: var(--accent); text-decoration: none; font-weight: bold;">🏠 חזרה</a>
    </div>

    <div id="listContainer"></div>
    
    <div id="footerActions" style="display: none;" class="actions-group">
        <button onclick="shareList()" class="btn btn-wa">שיתוף בוואטסאפ 📱</button>
        <button onclick="clearAll()" class="btn btn-delete-all">🗑️ מחיקת כל הרשימה</button>
    </div>
    
    <div id="emptyMsg" style="display:none;">
        <div style="font-size: 4rem; margin-bottom: 20px;">🧺</div>
        <h2>הסל ריק...</h2>
        <p>חזרו למתכונים והוסיפו מצרכים.</p>
    </div>
</div>

<script>
const currentUser = "<?php echo $_SESSION['username'] ?? 'guest'; ?>";
const cartKey = 'shopping_list_' + currentUser;
const checkedKey = 'checked_items_' + currentUser;

function loadList() {
    const rawList = JSON.parse(localStorage.getItem(cartKey)) || [];
    const checkedItems = JSON.parse(localStorage.getItem(checkedKey)) || [];
    const container = document.getElementById('listContainer');
    
    if (rawList.length === 0) {
        container.innerHTML = '';
        document.getElementById('footerActions').style.display = 'none';
        document.getElementById('emptyMsg').style.display = 'block';
        return;
    }

    // איחוד חכם: רק אם הטקסט המלא (כולל התיאור) זהה לחלוטין
    const aggregated = {};
    rawList.forEach(item => {
        const text = item.fullText.trim().replace(/\s+/g, ' ');
        const match = text.match(/^(\d+(\.\d+)?)\s*(.*)$/);
        
        if (match) {
            const amount = parseFloat(match[1]);
            const rest = match[3].trim();
            const key = rest.toLowerCase(); // המפתח הוא התיאור ללא המספר

            if (aggregated[key]) {
                aggregated[key].amount += amount;
            } else {
                aggregated[key] = { amount: amount, display: rest, key: key };
            }
        } else {
            const key = text.toLowerCase();
            if (!aggregated[key]) {
                aggregated[key] = { amount: null, display: text, key: key };
            }
        }
    });

    document.getElementById('emptyMsg').style.display = 'none';
    document.getElementById('footerActions').style.display = 'block';
    
    const itemsArray = Object.values(aggregated);
    container.innerHTML = itemsArray.map((item) => {
        const isChecked = checkedItems.includes(item.key);
        const label = item.amount !== null 
            ? `<span class="amount-highlight">${item.amount}</span> ${item.display}` 
            : item.display;

        return `
            <div class="shopping-item">
                <div class="item-info ${isChecked ? 'checked' : ''}">${label}</div>
                <div style="display:flex; gap:8px;">
                    <button class="btn btn-check ${isChecked ? 'active' : ''}" onclick="toggleCheck('${item.key}')">
                        ${isChecked ? '✓' : '☐'}
                    </button>
                    <button class="btn btn-remove" onclick="removeSpecific('${item.key}')">✕</button>
                </div>
            </div>
        `;
    }).join('');

    window.currentAggregatedData = itemsArray;
}

function shareList() {
    const data = window.currentAggregatedData;
    if (!data) return;
    let text = `🛒 *רשימת הקניות שלי מ-RecipeMaster* \n\n`;
    data.forEach((item, i) => {
        text += `${i + 1}. ${item.amount !== null ? item.amount + ' ' : ''}${item.display}\n`;
    });
    window.open(`https://api.whatsapp.com/send?text=${encodeURIComponent(text)}`, '_blank');
}

function toggleCheck(key) {
    let checked = JSON.parse(localStorage.getItem(checkedKey)) || [];
    if (checked.includes(key)) { checked = checked.filter(i => i !== key); } 
    else { checked.push(key); }
    localStorage.setItem(checkedKey, JSON.stringify(checked));
    loadList();
}

function removeSpecific(key) {
    let list = JSON.parse(localStorage.getItem(cartKey)) || [];
    let newList = list.filter(item => {
        const text = item.fullText.trim().replace(/\s+/g, ' ');
        const match = text.match(/^(\d+(\.\d+)?)\s*(.*)$/);
        const itemKey = match ? match[3].trim().toLowerCase() : text.toLowerCase();
        return itemKey !== key;
    });
    localStorage.setItem(cartKey, JSON.stringify(newList));
    loadList();
}

function clearAll() {
    if(confirm('למחוק את כל הרשימה?')) {
        localStorage.removeItem(cartKey);
        localStorage.removeItem(checkedKey);
        loadList();
    }
}

document.addEventListener('DOMContentLoaded', loadList);
</script>
</body>
</html>