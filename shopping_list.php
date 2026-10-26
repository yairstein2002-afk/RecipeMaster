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

function parseIngredient(rawText) {
    let text = rawText.trim().replace(/\s+/g, ' ');
    
    // ניקוי מלא של כל אזכור תחליף (בסוגריים או אחרי המילה 'תחליף:' / 'או:') כדי שלא יוצג ולא יפגע בחישוב
    text = text.replace(/[\(\[](?:תחליף|או)?:?\s*[^\]\)]+[\)\]]/gi, '').trim();
    text = text.replace(/(?:תחליף|או):\s*.*$/gi, '').trim();

    // פונקציית עזר פנימית לתרגום מילים לשברים עשרוניים
    function getFrac(f) {
        if (f === 'חצי') return 0.5;
        if (f === 'רבע') return 0.25;
        if (f === 'שליש') return 0.33;
        if (f === 'שני שליש' || f === 'שני שלישים') return 0.66;
        if (f === 'שלוש רבעים' || f === 'שלושה רבעים') return 0.75;
        return 0;
    }

    const fracList = "(חצי|רבע|שליש|שני שליש|שני שלישים|שלוש רבעים|שלושה רבעים)";

    // 1. טיפול במצב של "2 כוסות ושלושה רבעים" -> "2.75 כוסות"
    let re1 = new RegExp("^(\\d+)\\s+(כפיות|כפות|כוסות|שיני|חבילות)\\s+ו" + fracList + "(\\s|$)");
    text = text.replace(re1, function(match, num, unit, frac, space) {
        return (parseFloat(num) + getFrac(frac)) + ' ' + unit + space;
    });

    // 2. טיפול במצב של "כוס ושני שליש" -> "1.66 כוס"
    let re2 = new RegExp("^(כפית|כף|כוס|שן|חבילה)\\s+ו" + fracList + "(\\s|$)");
    text = text.replace(re2, function(match, unit, frac, space) {
        return (1 + getFrac(frac)) + ' ' + unit + space;
    });

    // 3. טיפול במצב של "1 ושלוש רבעים כוסות" -> "1.75 כוסות"
    let re3 = new RegExp("^(\\d+)\\s+ו" + fracList + "(\\s|$)");
    text = text.replace(re3, function(match, num, frac, space) {
        return (parseFloat(num) + getFrac(frac)) + space;
    });

    // 4. טיפול במילה בודדת בהתחלה ("שני שליש כוס") -> "0.66 כוס"
    let re4 = new RegExp("^" + fracList + "\\s+");
    text = text.replace(re4, function(match, frac) {
        return getFrac(frac) + ' ';
    });
    
    // 5. אם כתבו רק מידה בהתחלה ללא מספר ("כפית מלח") -> "1 כפית"
    if (/^(כפית|כפיות|כף|כפות|כוס|כוסות|שן|שיני|קורט|מיכל|מכל|חבילה|חבילות)(\s|$)/.test(text)) {
        text = '1 ' + text;
    }

    // מכאן והלאה - חילוץ המספר וזיהוי המוצר הנקי
    const match = text.match(/^(\d+(\.\d+)?)\s*(.*)$/);
    
    if (match) {
        const amount = parseFloat(match[1]);
        let rest = match[3].trim();
        
        // נרמול ליחיד כדי ש-"כפיות" יחובר ל-"כפית"
        let normalizedRest = rest
            .replace(/^כפיות(\s|$)/, 'כפית$1')
            .replace(/^כפות(\s|$)/, 'כף$1')
            .replace(/^כוסות(\s|$)/, 'כוס$1')
            .replace(/^שיני(\s|$)/, 'שן$1')
            .replace(/^חבילות(\s|$)/, 'חבילה$1');

        return { 
            hasAmount: true, 
            amount: amount, 
            key: normalizedRest.toLowerCase(), 
            display: normalizedRest 
        };
    } else {
        return { 
            hasAmount: false, 
            amount: null, 
            key: text.toLowerCase(), 
            display: text 
        };
    }
}

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

    const aggregated = {};

    rawList.forEach(item => {
        const parsed = parseIngredient(item.fullText);
        
        if (parsed.hasAmount) {
            if (aggregated[parsed.key]) {
                let currentAmount = aggregated[parsed.key].amount === null ? 1 : aggregated[parsed.key].amount;
                aggregated[parsed.key].amount = currentAmount + parsed.amount;
            } else {
                aggregated[parsed.key] = parsed;
            }
        } else {
            if (!aggregated[parsed.key]) {
                aggregated[parsed.key] = parsed;
            }
        }
    });

    document.getElementById('emptyMsg').style.display = 'none';
    document.getElementById('footerActions').style.display = 'block';
    
    const itemsArray = Object.values(aggregated);

    container.innerHTML = itemsArray.map((item) => {
        const isChecked = checkedItems.includes(item.key);

        let displayName = item.display;
        if (item.amount > 1) {
            displayName = displayName
                .replace(/^כפית(\s|$)/, 'כפיות$1')
                .replace(/^כף(\s|$)/, 'כפות$1')
                .replace(/^כוס(\s|$)/, 'כוסות$1')
                .replace(/^שן(\s|$)/, 'שיני$1')
                .replace(/^חבילה(\s|$)/, 'חבילות$1');
        }

        const label = item.amount !== null 
            ? `<span class="amount-highlight">${item.amount}</span> ${displayName}` 
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
        const parsed = parseIngredient(item.fullText);
        return parsed.key !== key; 
    });
    
    localStorage.setItem(cartKey, JSON.stringify(newList));
    
    let checked = JSON.parse(localStorage.getItem(checkedKey)) || [];
    if (checked.includes(key)) {
        checked = checked.filter(i => i !== key);
        localStorage.setItem(checkedKey, JSON.stringify(checked));
    }

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
