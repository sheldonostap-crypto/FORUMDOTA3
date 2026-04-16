<?php
session_start();
header('Content-Type: text/html; charset=utf-8');

// --- НАЛАШТУВАННЯ БАЗИ ---
$host = 'localhost';
$db_name = 'mysite14hjsc_DBDOTA2UKRFORUM';
$db_user = 'mysite14hjsc_DBDOTA2UKRFORUM';
$db_pass = 'qwe123asdfqwe8457y36#&$@#';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Створення таблиць
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, nick VARCHAR(50) UNIQUE, password VARCHAR(255), mmr INT DEFAULT 0, hours INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS real_clubs (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100), creator VARCHAR(50)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    $pdo->exec("CREATE TABLE IF NOT EXISTS club_posts (id INT AUTO_INCREMENT PRIMARY KEY, club_id INT, author VARCHAR(50), text TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    // ТАБЛИЦЯ КОМЕНТАРІВ
    $pdo->exec("CREATE TABLE IF NOT EXISTS post_comments (id INT AUTO_INCREMENT PRIMARY KEY, post_id INT, author VARCHAR(50), text TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

} catch (Exception $e) { die("Помилка підключення до БД"); }

// --- API ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ob_clean();
    $a = $_POST['action'];

    if ($a === 'login') {
        $st = $pdo->prepare("SELECT * FROM users WHERE nick = ? AND password = ?");
        $st->execute([$_POST['nick'], $_POST['password']]);
        $u = $st->fetch();
        if ($u) { $_SESSION['user'] = $u; echo json_encode(['status' => 'ok']); } 
        else { http_response_code(401); }
        exit;
    }

    if ($a === 'register') {
        $st = $pdo->prepare("INSERT INTO users (nick, password) VALUES (?, ?)");
        try { $st->execute([$_POST['nick'], $_POST['password']]); echo json_encode(['ok'=>1]); } 
        catch (Exception $e) { http_response_code(400); }
        exit;
    }

    if ($a === 'create_club' && isset($_SESSION['user'])) {
        $pdo->prepare("INSERT INTO real_clubs (name, creator) VALUES (?, ?)")->execute([$_POST['name'], $_SESSION['user']['nick']]);
        echo json_encode(['ok'=>1]); exit;
    }

    if ($a === 'add_post' && isset($_SESSION['user'])) {
        $pdo->prepare("INSERT INTO club_posts (club_id, author, text) VALUES (?, ?, ?)")->execute([$_POST['club_id'], $_SESSION['user']['nick'], $_POST['text']]);
        echo json_encode(['ok'=>1]); exit;
    }

    if ($a === 'get_posts') {
        $st = $pdo->prepare("SELECT * FROM club_posts WHERE club_id = ? ORDER BY id DESC");
        $st->execute([$_POST['club_id']]);
        $posts = $st->fetchAll();
        
        // Для кожного поста підтягуємо коментарі
        foreach($posts as &$p) {
            $cst = $pdo->prepare("SELECT * FROM post_comments WHERE post_id = ? ORDER BY id ASC");
            $cst->execute([$p['id']]);
            $p['comments'] = $cst->fetchAll();
        }
        echo json_encode($posts); exit;
    }

    // НОВЕ: ДОДАВАННЯ КОМЕНТАРЯ
    if ($a === 'add_comment' && isset($_SESSION['user'])) {
        $pdo->prepare("INSERT INTO post_comments (post_id, author, text) VALUES (?, ?, ?)")
            ->execute([$_POST['post_id'], $_SESSION['user']['nick'], $_POST['text']]);
        echo json_encode(['ok'=>1]); exit;
    }
}

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
?>
<!DOCTYPE html>
<html lang="uk">
<head>
    <meta charset="UTF-8">
    <title>УКРАЇНСЬКА ДОТА 2 ФОРУМ</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div id="scr-login" class="screen <?= isset($_SESSION['user']) ? 'hidden' : '' ?>">
        <div class="login-box">
            <h1 style="color:var(--neon); margin:0;">DOTA 2</h1>
            <p style="color:var(--gold); font-weight:bold; margin-bottom:30px;">УКРАЇНСЬКА ДОТА 2 ФОРУМ</p>
            <input type="text" id="l-nick" placeholder="НІКНЕЙМ">
            <input type="password" id="l-pass" placeholder="ПАРОЛЬ">
            <button class="btn" style="width:100%; margin-top:15px;" onclick="login()">УВІЙТИ</button>
            <button class="btn btn-pink" style="width:100%; margin-top:10px;" onclick="register()">РЕЄСТРАЦІЯ</button>
        </div>
    </div>

    <div id="scr-main" class="screen <?= !isset($_SESSION['user']) ? 'hidden' : '' ?>">
        <div class="header">
            <button class="btn" onclick="createClub()">+ НОВИЙ КЛУБ</button>
            <div style="display:flex; align-items:center; gap:20px;">
                <span style="color:var(--gold); font-weight:bold;"><?= $_SESSION['user']['nick'] ?? '' ?></span>
                <a href="?logout=1" class="btn btn-pink" style="font-size:11px;">ВИХІД</a>
            </div>
        </div>
        <h2 style="color:var(--neon); border-bottom: 1px solid var(--neon); padding-bottom:10px; margin-bottom:30px;">ДОСТУПНІ КЛУБИ</h2>
        <div style="display:flex; flex-wrap:wrap; justify-content:center; width:100%; max-width:1200px;">
            <?php 
            $clubs = $pdo->query("SELECT * FROM real_clubs ORDER BY id DESC")->fetchAll();
            foreach($clubs as $c) {
                echo "<div class='club-card' onclick='openClub({$c['id']}, \"".addslashes($c['name'])."\")'>
                        <h3 style='color:var(--neon); margin:0;'>{$c['name']}</h3>
                        <small style='color:gray'>Автор: {$c['creator']}</small>
                      </div>";
            }
            ?>
        </div>
    </div>

    <div id="scr-club" class="screen hidden">
        <div class="header">
            <button class="btn" onclick="show('scr-main')">НАЗАД</button>
            <h2 id="club-title" style="color:var(--pink); margin:0;"></h2>
            <div></div>
        </div>
        <div style="width:100%; max-width:650px;">
            <textarea id="post-text" placeholder="Напишіть щось у цей клуб..." rows="3"></textarea>
            <button class="btn" style="width:100%" onclick="addPost()">ОПУБЛІКУВАТИ</button>
            <div id="post-list" style="margin-top:40px;"></div>
        </div>
    </div>

    <script>
        let curClubId = null;

        function show(id) {
            document.querySelectorAll('.screen').forEach(s => s.classList.add('hidden'));
            document.getElementById(id).classList.remove('hidden');
        }

        function login() {
            let fd = new FormData();
            fd.append('action', 'login');
            fd.append('nick', document.getElementById('l-nick').value);
            fd.append('password', document.getElementById('l-pass').value);
            fetch(window.location.href, {method:'POST', body:fd}).then(r => r.ok ? location.reload() : alert("ПОМИЛКА: Невірні дані"));
        }

        function register() {
            let n = document.getElementById('l-nick').value;
            let p = document.getElementById('l-pass').value;
            if(n.length < 2) return alert("Нік занадто короткий");
            let fd = new FormData();
            fd.append('action', 'register');
            fd.append('nick', n); fd.append('password', p);
            fetch(window.location.href, {method:'POST', body:fd}).then(() => alert("Реєстрація успішна! Тепер увійдіть."));
        }

        function createClub() {
            let name = prompt("ВВЕДІТЬ НАЗВУ КЛУБУ:");
            if(!name) return;
            let fd = new FormData();
            fd.append('action', 'create_club');
            fd.append('name', name);
            fetch(window.location.href, {method:'POST', body:fd}).then(() => location.reload());
        }

        function openClub(id, name) {
            curClubId = id;
            document.getElementById('club-title').innerText = name;
            loadPosts();
            show('scr-club');
        }

        function loadPosts() {
            let fd = new FormData();
            fd.append('action', 'get_posts');
            fd.append('club_id', curClubId);
            fetch(window.location.href, {method:'POST', body:fd}).then(r => r.json()).then(data => {
                let h = '';
                data.forEach(p => {
                    let commentsHtml = '';
                    p.comments.forEach(c => {
                        commentsHtml += `<div class="comment"><b style="color:var(--gold)">${c.author}:</b> ${c.text}</div>`;
                    });

                    h += `
                    <div class="post">
                        <strong style="color:var(--neon)">${p.author}</strong>
                        <p style="margin:10px 0;">${p.text}</p>
                        <div class="comment-section">
                            <div id="comments-${p.id}">${commentsHtml}</div>
                            <div class="comment-input-group">
                                <input type="text" id="ci-${p.id}" placeholder="Ваш коментар...">
                                <button class="btn" style="padding:5px 10px; font-size:10px;" onclick="addComment(${p.id})">OK</button>
                            </div>
                        </div>
                    </div>`;
                });
                document.getElementById('post-list').innerHTML = h || '<p style="color:gray">Тут поки порожньо...</p>';
            });
        }

        function addPost() {
            let txt = document.getElementById('post-text').value;
            if(!txt) return;
            let fd = new FormData();
            fd.append('action', 'add_post');
            fd.append('club_id', curClubId);
            fd.append('text', txt);
            fetch(window.location.href, {method:'POST', body:fd}).then(() => {
                document.getElementById('post-text').value = '';
                loadPosts();
            });
        }

        function addComment(postId) {
            let txt = document.getElementById('ci-' + postId).value;
            if(!txt) return;
            let fd = new FormData();
            fd.append('action', 'add_comment');
            fd.append('post_id', postId);
            fd.append('text', txt);
            fetch(window.location.href, {method:'POST', body:fd}).then(() => {
                loadPosts(); // Перезавантажуємо, щоб побачити новий комент
            });
        }
    </script>
</body>
</html>
