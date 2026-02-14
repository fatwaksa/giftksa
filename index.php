<?php
// 1. إعداد المجلدات
$dir = 'gifts';
if(!is_dir($dir)) mkdir($dir, 0777, true);

// وظيفة لجلب الرابط الحقيقي من الرابط المختصر (لحل مشكلة ساوند كلاود)
function get_real_url($url) {
    if (strpos($url, 'on.soundcloud.com') !== false) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_exec($ch);
        $real_url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        return explode('?', $real_url)[0]; // إرجاع الرابط النظيف
    }
    return $url;
}

// 2. منطق إنشاء الهدية (API)
if($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') == 'create'){
    header('Content-Type: application/json');
    
    $sender  = trim($_POST['sender'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $type    = trim($_POST['type'] ?? 'card');
    $music   = trim($_POST['music'] ?? '');

    if(!$sender || !$message){
        echo json_encode(['error' => 'الاسم والرسالة مطلوبان']); 
        exit;
    }

    // معالجة الرابط إذا كان ساوند كلاود مختصر
    $final_music_url = get_real_url($music);

    $id = uniqid();
    $data = ['s' => $sender, 'm' => $message, 'g' => $type, 'u' => $final_music_url];
    
    if(file_put_contents($dir . "/$id.json", json_encode($data, JSON_UNESCAPED_UNICODE))){
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $link = $protocol . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'])[0] . "?id=$id";
        echo json_encode(['link' => $link]);
    } else {
        echo json_encode(['error' => 'فشل في حفظ البيانات']);
    }
    exit;
}

// 3. جلب بيانات الهدية
$gift_data = null;
if(isset($_GET['id'])){
    $file = $dir . "/" . basename($_GET['id']) . ".json";
    if(file_exists($file)){
        $gift_data = json_decode(file_get_contents($file), true);
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gift For Friends | مفاجأة خاصة</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root { --primary: #ff3e6c; --dark: #2d3436; --glass: rgba(255,255,255,0.95); }
        body { font-family: 'Cairo', sans-serif; background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); margin: 0; min-height: 100vh; display: flex; justify-content: center; align-items: center; overflow-x: hidden; }
        .container { width: 90%; max-width: 500px; background: var(--glass); backdrop-filter: blur(10px); border-radius: 30px; padding: 40px 20px; box-shadow: 0 20px 60px rgba(0,0,0,0.1); text-align: center; border: 1px solid rgba(255,255,255,0.3); position: relative; z-index: 2; }
        .options-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 25px; }
        .opt-card { background: #fff; padding: 15px 5px; border-radius: 15px; cursor: pointer; border: 2px solid #f0f0f0; transition: 0.3s; }
        .opt-card i { display: block; font-size: 1.5rem; margin-bottom: 8px; color: #888; }
        .opt-card.active { border-color: var(--primary); background: #fff0f3; }
        .opt-card.active i { color: var(--primary); }
        input, textarea { width: 100%; padding: 15px; margin-top: 10px; border-radius: 12px; border: 1px solid #ddd; font-family: 'Cairo'; box-sizing: border-box; background: #fafafa; outline: none; }
        .btn-send { background: linear-gradient(45deg, var(--primary), #ff7675); color: white; border: none; padding: 18px; border-radius: 50px; width: 100%; font-size: 1.2rem; font-weight: bold; cursor: pointer; margin-top: 20px; box-shadow: 0 10px 20px rgba(255,62,108,0.2); }
        #mystery-gift { font-size: 100px; color: var(--primary); cursor: pointer; animation: bounce 2s infinite; margin: 30px 0; display: inline-block; }
        @keyframes bounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.1); } }
        #timer-overlay { position: fixed; top:0; left:0; width:100%; height:100%; background: rgba(255,62,108,0.95); color: white; display: flex; justify-content: center; align-items: center; font-size: 150px; font-weight: 900; z-index: 1000; }
        .msg-display-card { background: white; padding: 30px 20px; border-radius: 20px; box-shadow: inset 0 0 15px rgba(0,0,0,0.02); margin: 20px 0; border: 1px solid #eee; }
        .main-text { font-size: 1.6rem; font-weight: 700; color: #222; line-height: 1.5; }
        .sender-name { font-size: 1.1rem; color: var(--primary); font-weight: 900; border-top: 1px solid #eee; display: inline-block; padding-top: 10px; margin-top: 15px; }
        .petal { position: absolute; z-index: 1; pointer-events: none; animation: fall linear forwards; }
        @keyframes fall { to { transform: translateY(100vh) rotate(360deg); opacity: 0; } }
        .hidden { display: none !important; }
        iframe { border-radius: 15px; margin-top: 15px; border: none; }
    </style>
</head>
<body>

<div id="timer-overlay" class="hidden">3</div>

<div class="container">
    <?php if(!$gift_data): ?>
    <h1 style="color:var(--primary); margin:0;">Gift For Friends</h1>
    <p style="color:#777; margin-bottom:30px;">أرسل هدية رقمية مميزة بضغطة زر</p>

    <div class="options-grid">
        <div class="opt-card active" onclick="pick(this, 'card')"><i class="fa fa-envelope-open-text"></i>بطاقة</div>
        <div class="opt-card" onclick="pick(this, 'love')"><i class="fa fa-heart"></i>حب</div>
        <div class="opt-card" onclick="pick(this, 'bday')"><i class="fa fa-cake-candles"></i>ميلاد</div>
        <div class="opt-card" onclick="pick(this, 'heart')"><i class="fa fa-heart-pulse"></i>قلبك</div>
        <div class="opt-card" onclick="pick(this, 'egg')"><i class="fa fa-dragon"></i>بيضة</div>
        <div class="opt-card" onclick="pick(this, 'scratch')"><i class="fa fa-wand-magic-sparkles"></i>خادش</div>
    </div>

    <input type="text" id="senderName" placeholder="اسمك (المُهدي)">
    <textarea id="message" rows="4" placeholder="اكتب محتوى الرسالة هنا..."></textarea>
    <input type="text" id="musicUrl" placeholder="رابط SoundCloud أو YouTube">

    <button class="btn-send" id="genBtn" onclick="generate()">إنشاء رابط الهدية <i class="fa fa-magic"></i></button>

    <div id="result-box" class="hidden">
        <input type="text" id="linkOutput" readonly style="text-align:center; border:1px solid var(--primary); margin-top: 20px;">
        <button class="btn-send" style="margin-top:10px; padding:10px;" onclick="copyLink()">نسخ الرابط</button>
    </div>

    <?php else: ?>
    <div id="receiver-view">
        <h2 id="gift-title">وصلتك هدية من <span><?= htmlspecialchars($gift_data['s']) ?></span></h2>

        <div id="closed-state">
            <div id="mystery-gift" onclick="unlock()"><i class="fa fa-box-archive"></i></div>
            <p>انقر لفتح هديتك</p>
        </div>

        <div id="opened-state" class="hidden">
            <div class="msg-display-card">
                <div id="visual-icon" style="font-size:60px; color:var(--primary); margin-bottom:15px;">
                    <?php
                        $icons = ['card'=>'fa-envelope-open-text','love'=>'fa-heart','bday'=>'fa-cake-candles','heart'=>'fa-heart-pulse','egg'=>'fa-dragon','scratch'=>'fa-wand-magic-sparkles'];
                        $icon = $icons[$gift_data['g']] ?? 'fa-gift';
                        echo "<i class='fa $icon'></i>";
                    ?>
                </div>
                <div class="main-text"><?= nl2br(htmlspecialchars($gift_data['m'])) ?></div>
                <div class="sender-name">من: <?= htmlspecialchars($gift_data['s']) ?></div>
            </div>

            <div id="player-container">
                </div>
        </div>
    </div>
    <script>
        const musicUrl = "<?= $gift_data['u'] ?>";
        function loadMusic() {
            const container = document.getElementById('player-container');
            if(!musicUrl) return;

            if(musicUrl.includes('youtube.com') || musicUrl.includes('youtu.be')) {
                let vid = musicUrl.split('v=')[1] || musicUrl.split('/').pop();
                vid = vid.split('&')[0];
                container.innerHTML = `<iframe width="100%" height="200" src="https://www.youtube.com/embed/${vid}?autoplay=1" allow="autoplay"></iframe>`;
            } else {
                // SoundCloud Player
                container.innerHTML = `<iframe width="100%" height="166" scrolling="no" frameborder="no" allow="autoplay" 
                src="https://w.soundcloud.com/player/?url=${encodeURIComponent(musicUrl)}&auto_play=true&hide_related=false&show_comments=true&show_user=true&show_reposts=false&show_teaser=true"></iframe>`;
            }
        }
    </script>
    <?php endif; ?>
</div>

<script>
let selectedType = 'card';
function pick(el, type) {
    selectedType = type;
    document.querySelectorAll('.opt-card').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
}

function generate() {
    const s = document.getElementById('senderName').value.trim();
    const m = document.getElementById('message').value.trim();
    const u = document.getElementById('musicUrl').value.trim();
    if(!s || !m) return alert("يرجى إدخال البيانات");

    const btn = document.getElementById('genBtn');
    btn.disabled = true;

    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('sender', s);
    formData.append('message', m);
    formData.append('type', selectedType);
    formData.append('music', u);

    fetch(window.location.href, { method: 'POST', body: formData })
    .then(r => r.json())
    .then(res => {
        document.getElementById('linkOutput').value = res.link;
        document.getElementById('result-box').classList.remove('hidden');
        btn.classList.add('hidden');
    });
}

function copyLink() {
    const input = document.getElementById('linkOutput');
    input.select();
    navigator.clipboard.writeText(input.value);
    alert("تم النسخ!");
}

function unlock() {
    const overlay = document.getElementById('timer-overlay');
    overlay.classList.remove('hidden');
    let count = 3;
    overlay.innerText = count;
    
    const interval = setInterval(() => {
        count--;
        if(count > 0) overlay.innerText = count;
        else {
            clearInterval(interval);
            overlay.classList.add('hidden');
            document.getElementById('closed-state').classList.add('hidden');
            document.getElementById('opened-state').classList.remove('hidden');
            if(typeof loadMusic === 'function') loadMusic(); // تشغيل الموسيقى فور الفتح
            spawnPetals();
        }
    }, 1000);
}

function spawnPetals() {
    for(let i=0; i<50; i++) {
        const p = document.createElement('i');
        p.className = 'fa fa-heart petal';
        p.style.left = Math.random() * 100 + 'vw';
        p.style.color = '#ff3e6c';
        p.style.fontSize = Math.random() * 20 + 10 + 'px';
        p.style.animationDuration = Math.random() * 3 + 2 + 's';
        document.body.appendChild(p);
    }
}
</script>
</body>
</html>
