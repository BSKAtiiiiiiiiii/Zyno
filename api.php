<?php
session_start();

define('DATA_DIR', __DIR__ . '/VP/');
define('USERS_FILE', DATA_DIR . 'users.json');
define('MUSIC_DIR', DATA_DIR . 'music/');
define('STICKERS_DIR', DATA_DIR . 'stickers/');
define('CODING_DIR', DATA_DIR . 'coding/');
define('THEMES_DIR', DATA_DIR . 'themes/');
define('UPLOADS_DIR', DATA_DIR . 'uploads/');
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024);

$dirs = [MUSIC_DIR, STICKERS_DIR, CODING_DIR, THEMES_DIR, UPLOADS_DIR];
foreach ($dirs as $dir) {
    if (!file_exists($dir)) mkdir($dir, 0755, true);
}

function getCurrentUser() {
    if (!isset($_SESSION['user_id'])) return null;
    $users = json_decode(file_get_contents(USERS_FILE), true) ?: [];
    foreach ($users as $u) {
        if ($u['id'] === $_SESSION['user_id']) return $u;
    }
    return null;
}

function generateId() {
    return substr(md5(uniqid(mt_rand(), true)), 0, 12);
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function generateSlug($title) {
    $slug = mb_strtolower($title);
    $slug = preg_replace('/[^a-zа-яё0-9\s-]/u', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    if (empty($slug)) $slug = 'item';
    return $slug . '-' . substr(md5(uniqid()), 0, 6);
}

function handleUpload($fileKey, $allowedTypes, $maxSize = null) {
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $file = $_FILES[$fileKey];
    $maxSize = $maxSize ?: MAX_UPLOAD_SIZE;
    if ($file['size'] > $maxSize) return null;

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) return null;

    $newName = generateId() . '.' . $ext;
    $dest = UPLOADS_DIR . $newName;
    if (move_uploaded_file($file['tmp_name'], $dest)) {
        return 'VP/uploads/' . $newName;
    }
    return null;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$user = getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'upload_music':
        if (!$user) jsonResponse(['error' => 'Требуется авторизация'], 401);

        $title = sanitize($_POST['title'] ?? '');
        $use = sanitize($_POST['use'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $tags = sanitize($_POST['tags'] ?? '');
        $is_author = !empty($_POST['is_author']);
        $is_ai = !empty($_POST['is_ai']);
        $is_nsfw = !empty($_POST['is_nsfw']);
        $agreed = !empty($_POST['agreed']);

        if (empty($title) || empty($use) || !$agreed) {
            jsonResponse(['error' => 'Заполните обязательные поля и примите правила'], 400);
        }

        $iconPath = handleUpload('icon_file', ['jpg','jpeg','png','gif','webp'], 2*1024*1024);
        if (!$iconPath && !empty($_POST['icon_url'])) {
            $iconPath = filter_var($_POST['icon_url'], FILTER_VALIDATE_URL) ? $_POST['icon_url'] : null;
        }

        $musicPath = handleUpload('music_file', ['mp3','ogg','wav','flac','m4a']);
        if (!$musicPath && !empty($_POST['music_url'])) {
            $musicPath = filter_var($_POST['music_url'], FILTER_VALIDATE_URL) ? $_POST['music_url'] : null;
        }

        $id = generateId();
        $item = [
            'id' => $id,
            'type' => 'music',
            'title' => $title,
            'use' => $use,
            'slug' => generateSlug($title),
            'icon' => $iconPath,
            'file' => $musicPath,
            'description' => $description,
            'tags' => $tags,
            'is_author' => $is_author,
            'is_ai' => $is_ai,
            'is_nsfw' => $is_nsfw,
            'user_id' => $user['id'],
            'author' => $user['username'],
            'likes' => 0,
            'comments' => [],
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents(MUSIC_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        header('Location: /?action=music');
        exit;
        break;

    case 'upload_sticker':
        if (!$user) jsonResponse(['error' => 'Требуется авторизация'], 401);

        $title = sanitize($_POST['title'] ?? '');
        $use = sanitize($_POST['use'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $tags = sanitize($_POST['tags'] ?? '');
        $is_author = !empty($_POST['is_author']);
        $is_nsfw = !empty($_POST['is_nsfw']);
        $agreed = !empty($_POST['agreed']);

        if (empty($title) || !$agreed) {
            jsonResponse(['error' => 'Заполните обязательные поля'], 400);
        }

        $stickerPath = handleUpload('sticker_file', ['png','gif','webp','webm','jpg','jpeg','tgs']);
        if (!$stickerPath && !empty($_POST['sticker_url'])) {
            $stickerPath = filter_var($_POST['sticker_url'], FILTER_VALIDATE_URL) ? $_POST['sticker_url'] : null;
        }

        $id = generateId();
        $item = [
            'id' => $id,
            'type' => 'sticker',
            'title' => $title,
            'use' => $use ?: $title,
            'slug' => generateSlug($title),
            'file' => $stickerPath,
            'description' => $description,
            'tags' => $tags,
            'is_author' => $is_author,
            'is_nsfw' => $is_nsfw,
            'user_id' => $user['id'],
            'author' => $user['username'],
            'likes' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents(STICKERS_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        header('Location: /?action=stickers');
        exit;
        break;

    case 'upload_coding':
        if (!$user) jsonResponse(['error' => 'Требуется авторизация'], 401);

        $title = sanitize($_POST['title'] ?? '');
        $use = sanitize($_POST['use'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $tags = sanitize($_POST['tags'] ?? '');
        $is_author = !empty($_POST['is_author']);
        $agreed = !empty($_POST['agreed']);

        if (empty($title) || !$agreed) {
            jsonResponse(['error' => 'Заполните обязательные поля'], 400);
        }

        $codePath = handleUpload('code_file', ['zip','rar','7z','tar','gz','jar','py','js','php','java','txt','json','xml','yml','yaml','toml','cfg','ini','properties']);

        $id = generateId();
        $item = [
            'id' => $id,
            'type' => 'coding',
            'title' => $title,
            'use' => $use ?: $title,
            'slug' => generateSlug($title),
            'file' => $codePath,
            'description' => $description,
            'tags' => $tags,
            'is_author' => $is_author,
            'user_id' => $user['id'],
            'author' => $user['username'],
            'likes' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents(CODING_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        header('Location: /?action=coding');
        exit;
        break;

    case 'upload_theme':
        if (!$user) jsonResponse(['error' => 'Требуется авторизация'], 401);

        $title = sanitize($_POST['title'] ?? '');
        $use = sanitize($_POST['use'] ?? '');
        $css_code = $_POST['css_code'] ?? '';
        $description = sanitize($_POST['description'] ?? '');
        $agreed = !empty($_POST['agreed']);

        if (empty($title) || empty($css_code) || !$agreed) {
            jsonResponse(['error' => 'Заполните обязательные поля'], 400);
        }

        $id = generateId();
        $item = [
            'id' => $id,
            'type' => 'theme',
            'title' => $title,
            'use' => $use ?: $title,
            'slug' => generateSlug($title),
            'css_code' => $css_code,
            'preview_css' => 'background:' . (substr($css_code, 0, 60)),
            'description' => $description,
            'user_id' => $user['id'],
            'author' => $user['username'],
            'likes' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ];

        file_put_contents(THEMES_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        header('Location: /?action=themes');
        exit;
        break;

    case 'like_item':
        if (!$user) jsonResponse(['error' => 'Требуется авторизация'], 401);

        $itemId = $_POST['item_id'] ?? '';
        $itemType = $_POST['item_type'] ?? '';

        $dirMap = [
            'music' => MUSIC_DIR,
            'sticker' => STICKERS_DIR,
            'coding' => CODING_DIR,
            'theme' => THEMES_DIR
        ];

        if (!isset($dirMap[$itemType])) jsonResponse(['error' => 'Неверный тип'], 400);

        $filePath = $dirMap[$itemType] . $itemId . '.json';
        if (!file_exists($filePath)) jsonResponse(['error' => 'Не найдено'], 404);

        $item = json_decode(file_get_contents($filePath), true);
        $likesKey = 'liked_by';
        if (!isset($item[$likesKey])) $item[$likesKey] = [];

        $idx = array_search($user['id'], $item[$likesKey]);
        if ($idx !== false) {
            array_splice($item[$likesKey], $idx, 1);
            $item['likes'] = max(0, ($item['likes'] ?? 1) - 1);
            $liked = false;
        } else {
            $item[$likesKey][] = $user['id'];
            $item['likes'] = ($item['likes'] ?? 0) + 1;
            $liked = true;
        }

        file_put_contents($filePath, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        jsonResponse(['likes' => $item['likes'], 'liked' => $liked]);
        break;

    case 'get_items':
        $itemType = $_GET['type'] ?? '';
        $search = mb_strtolower($_GET['q'] ?? '');

        $dirMap = [
            'music' => MUSIC_DIR,
            'sticker' => STICKERS_DIR,
            'coding' => CODING_DIR,
            'theme' => THEMES_DIR
        ];

        if (!isset($dirMap[$itemType])) jsonResponse(['error' => 'Неверный тип'], 400);

        $files = glob($dirMap[$itemType] . '*.json');
        $items = [];
        foreach ($files as $f) {
            $item = json_decode(file_get_contents($f), true);
            if (!$item) continue;
            if ($search && mb_strpos(mb_strtolower($item['title'] ?? ''), $search) === false &&
                mb_strpos(mb_strtolower($item['tags'] ?? ''), $search) === false) {
                continue;
            }
            $items[] = $item;
        }
        usort($items, function($a, $b) { return ($b['likes'] ?? 0) - ($a['likes'] ?? 0); });
        jsonResponse($items);
        break;

    default:
        jsonResponse(['error' => 'Неизвестное действие'], 400);
}
