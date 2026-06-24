<?php
/**
 * functions.php — общие функции для всех PHP файлов My Cryndel
 * Подключается через require_once 'functions.php';
 */
 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
 
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
 
// ============================================
// КОНФИГУРАЦИЯ
// ============================================
if (!defined('DATA_DIR')) define('DATA_DIR', __DIR__ . '/VP/');
if (!defined('USERS_FILE')) define('USERS_FILE', DATA_DIR . 'users.json');
if (!defined('POSTS_DIR')) define('POSTS_DIR', DATA_DIR . 'posts/');
if (!defined('PROFILES_DIR')) define('PROFILES_DIR', DATA_DIR . 'profiles/');
if (!defined('AVATARS_DIR')) define('AVATARS_DIR', DATA_DIR . 'avatars/');
if (!defined('COMMENTS_DIR')) define('COMMENTS_DIR', DATA_DIR . 'comments/');
if (!defined('NOTIFICATIONS_DIR')) define('NOTIFICATIONS_DIR', DATA_DIR . 'notifications/');
if (!defined('MUSIC_DIR')) define('MUSIC_DIR', DATA_DIR . 'music/');
if (!defined('STICKERS_DIR')) define('STICKERS_DIR', DATA_DIR . 'stickers/');
if (!defined('CODING_DIR')) define('CODING_DIR', DATA_DIR . 'coding/');
if (!defined('THEMES_DIR')) define('THEMES_DIR', DATA_DIR . 'themes/');
if (!defined('DRAFTS_DIR')) define('DRAFTS_DIR', DATA_DIR . 'drafts/');
if (!defined('FAVORITES_DIR')) define('FAVORITES_DIR', DATA_DIR . 'favorites/');
if (!defined('UPLOADS_DIR')) define('UPLOADS_DIR', DATA_DIR . 'uploads/');
if (!defined('MAX_FILE_SIZE')) define('MAX_FILE_SIZE', 10 * 1024 * 1024);
if (!defined('MAX_AVATAR_SIZE')) define('MAX_AVATAR_SIZE', 1 * 1024 * 1024);
if (!defined('MAX_POST_LENGTH')) define('MAX_POST_LENGTH', 25000);
if (!defined('SITE_NAME')) define('SITE_NAME', 'My Cryndel');
if (!defined('SITE_URL')) define('SITE_URL', 'http://localhost:8080');
 
$allDirs = [DATA_DIR, POSTS_DIR, PROFILES_DIR, AVATARS_DIR, COMMENTS_DIR,
    NOTIFICATIONS_DIR, MUSIC_DIR, STICKERS_DIR, CODING_DIR, THEMES_DIR,
    DRAFTS_DIR, FAVORITES_DIR, UPLOADS_DIR];
foreach ($allDirs as $dir) {
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
}
if (!file_exists(USERS_FILE)) file_put_contents(USERS_FILE, json_encode([]));
 
// ============================================
// CSRF ЗАЩИТА
// ============================================
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
 
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}
 
function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}
 
// ============================================
// ФУНКЦИИ ПОЛЬЗОВАТЕЛЕЙ
// ============================================
function fn_getUsers() {
    if (!file_exists(USERS_FILE)) return [];
    $c = file_get_contents(USERS_FILE);
    return ($c !== false) ? (json_decode($c, true) ?: []) : [];
}
 
function fn_saveUsers($users) {
    return file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}
 
function fn_getUserByUsername($username) {
    foreach (fn_getUsers() as $u) {
        if (strtolower($u['username']) === strtolower($username)) return $u;
    }
    return null;
}
 
function fn_getUserById($id) {
    foreach (fn_getUsers() as $u) {
        if ($u['id'] === $id) return $u;
    }
    return null;
}
 
function fn_getCurrentUser() {
    return isset($_SESSION['user_id']) ? fn_getUserById($_SESSION['user_id']) : null;
}
 
function fn_getAvatarUrl($user) {
    if (!empty($user['avatar'])) {
        $fullPath = __DIR__ . '/VP/' . $user['avatar'];
        if (file_exists($fullPath)) return '/VP/' . ltrim($user['avatar'], '/');
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=10b981&color=fff&size=128';
}
 
function fn_getRoleStyle($role) {
    $styles = [
        'admin' => ['badge' => '<span class="role-badge admin"><i class="fas fa-crown"></i></span>', 'color' => '#f59e0b', 'name' => 'Администрация'],
        'content_creator' => ['badge' => '<span class="role-badge creator"><i class="fas fa-video"></i></span>', 'color' => '#3b82f6', 'name' => 'Создатель контента'],
        'tech_admin' => ['badge' => '<span class="role-badge tech"><i class="fas fa-cog"></i></span>', 'color' => '#8b5cf6', 'name' => 'Тех админ'],
        'legendary' => ['badge' => '<span class="role-badge legendary"><i class="fas fa-star"></i></span>', 'color' => '#ec4899', 'name' => 'Легендарный игрок'],
        'mythic' => ['badge' => '<span class="role-badge mythic"><i class="fas fa-dragon"></i></span>', 'color' => '#14b8a6', 'name' => 'Мифический игрок'],
        'golden' => ['badge' => '<span class="role-badge golden"><i class="fas fa-medal"></i></span>', 'color' => '#fbbf24', 'name' => 'Золотой игрок'],
    ];
    return $styles[$role] ?? ['badge' => '', 'color' => '#10b981', 'name' => 'Игрок'];
}
 
// ============================================
// CONTENT ITEMS (music, stickers, coding, themes)
// ============================================
function fn_getItems($type, $search = '', $limit = 50, $offset = 0, $sortBy = 'likes') {
    $dirMap = ['music' => MUSIC_DIR, 'sticker' => STICKERS_DIR, 'coding' => CODING_DIR, 'theme' => THEMES_DIR];
    if (!isset($dirMap[$type])) return [];
 
    $files = glob($dirMap[$type] . '*.json') ?: [];
    $items = [];
    foreach ($files as $f) {
        $item = json_decode(file_get_contents($f), true);
        if (!$item) continue;
        if ($search) {
            $s = mb_strtolower($search);
            $match = (mb_strpos(mb_strtolower($item['title'] ?? ''), $s) !== false)
                || (mb_strpos(mb_strtolower($item['tags'] ?? ''), $s) !== false)
                || (mb_strpos(mb_strtolower($item['use'] ?? ''), $s) !== false)
                || (mb_strpos(mb_strtolower($item['author'] ?? ''), $s) !== false);
            if (!$match) continue;
        }
        $items[] = $item;
    }
 
    if ($sortBy === 'likes') {
        usort($items, function($a, $b) { return ($b['likes'] ?? 0) - ($a['likes'] ?? 0); });
    } elseif ($sortBy === 'date') {
        usort($items, function($a, $b) { return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'); });
    } elseif ($sortBy === 'title') {
        usort($items, function($a, $b) { return strcmp($a['title'] ?? '', $b['title'] ?? ''); });
    }
 
    return array_slice($items, $offset, $limit);
}
 
function fn_getItemById($type, $id) {
    $dirMap = ['music' => MUSIC_DIR, 'sticker' => STICKERS_DIR, 'coding' => CODING_DIR, 'theme' => THEMES_DIR];
    if (!isset($dirMap[$type])) return null;
    $file = $dirMap[$type] . $id . '.json';
    if (!file_exists($file)) return null;
    return json_decode(file_get_contents($file), true);
}
 
function fn_getItemBySlug($type, $slug) {
    $items = fn_getItems($type);
    foreach ($items as $item) {
        if (($item['slug'] ?? '') === $slug) return $item;
    }
    return null;
}
 
function fn_getUserItems($type, $userId) {
    $items = fn_getItems($type, '', 999);
    return array_filter($items, function($i) use ($userId) {
        return ($i['user_id'] ?? '') === $userId;
    });
}
 
function fn_getItemComments($type, $itemId) {
    $dir = DATA_DIR . 'item_comments/' . $type . '/' . $itemId . '/';
    if (!file_exists($dir)) return [];
    $comments = [];
    foreach (glob($dir . '*.json') ?: [] as $f) {
        $c = json_decode(file_get_contents($f), true);
        if ($c) {
            $u = fn_getUserById($c['user_id']);
            if ($u) {
                $c['username'] = $u['username'];
                $c['avatar'] = fn_getAvatarUrl($u);
                $c['role'] = $u['role'] ?? '';
            }
            $comments[] = $c;
        }
    }
    usort($comments, function($a, $b) { return strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'); });
    return $comments;
}
 
function fn_addItemComment($type, $itemId, $userId, $content) {
    $dir = DATA_DIR . 'item_comments/' . $type . '/' . $itemId . '/';
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
    $id = uniqid();
    $comment = [
        'id' => $id,
        'item_id' => $itemId,
        'item_type' => $type,
        'user_id' => $userId,
        'content' => trim($content),
        'likes' => 0,
        'liked_by' => [],
        'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($dir . $id . '.json', json_encode($comment, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
 
    // Notify item author
    $item = fn_getItemById($type, $itemId);
    if ($item && ($item['user_id'] ?? '') !== $userId) {
        fn_addNotification($item['user_id'], 'item_comment', [
            'from_user_id' => $userId,
            'from_username' => fn_getUserById($userId)['username'] ?? '',
            'item_type' => $type,
            'item_id' => $itemId,
            'item_title' => $item['title'] ?? '',
            'content' => mb_substr($content, 0, 100)
        ]);
    }
    return $comment;
}
 
function fn_toggleItemLike($type, $itemId, $userId) {
    $dirMap = ['music' => MUSIC_DIR, 'sticker' => STICKERS_DIR, 'coding' => CODING_DIR, 'theme' => THEMES_DIR];
    if (!isset($dirMap[$type])) return null;
    $file = $dirMap[$type] . $itemId . '.json';
    if (!file_exists($file)) return null;
    $item = json_decode(file_get_contents($file), true);
    if (!$item) return null;
 
    if (!isset($item['liked_by'])) $item['liked_by'] = [];
    $idx = array_search($userId, $item['liked_by']);
    if ($idx !== false) {
        array_splice($item['liked_by'], $idx, 1);
        $item['likes'] = max(0, ($item['likes'] ?? 1) - 1);
        $liked = false;
    } else {
        $item['liked_by'][] = $userId;
        $item['likes'] = ($item['likes'] ?? 0) + 1;
        $liked = true;
        // Notify
        if (($item['user_id'] ?? '') !== $userId) {
            fn_addNotification($item['user_id'], 'item_like', [
                'from_user_id' => $userId,
                'from_username' => fn_getUserById($userId)['username'] ?? '',
                'item_type' => $type,
                'item_id' => $itemId,
                'item_title' => $item['title'] ?? ''
            ]);
        }
    }
    file_put_contents($file, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return ['likes' => $item['likes'], 'liked' => $liked];
}
 
// ============================================
// УВЕДОМЛЕНИЯ
// ============================================
function fn_addNotification($userId, $type, $data) {
    $dir = NOTIFICATIONS_DIR . $userId . '/';
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
    $id = uniqid();
    $n = ['id' => $id, 'user_id' => $userId, 'type' => $type, 'data' => $data, 'read' => false, 'created_at' => date('Y-m-d H:i:s')];
    return file_put_contents($dir . $id . '.json', json_encode($n, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}
 
function fn_getUserNotifications($userId, $unreadOnly = false, $limit = 100) {
    $dir = NOTIFICATIONS_DIR . $userId . '/';
    if (!file_exists($dir)) return [];
    $notifs = [];
    foreach (glob($dir . '*.json') ?: [] as $f) {
        $n = json_decode(file_get_contents($f), true);
        if ($n) {
            if ($unreadOnly && $n['read']) continue;
            $notifs[] = $n;
        }
    }
    usort($notifs, function($a, $b) { return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0'); });
    return array_slice($notifs, 0, $limit);
}
 
function fn_getUnreadCount($userId) {
    return count(fn_getUserNotifications($userId, true));
}
 
// ============================================
// ПРОФИЛЬ
// ============================================
function fn_getUserProfileSettings($userId) {
    $user = fn_getUserById($userId);
    if (!$user) return ['custom_css' => '', 'medals' => [], 'status' => '', 'cover_css' => ''];
    $file = PROFILES_DIR . $user['username'] . '/settings.json';
    if (file_exists($file)) {
        $s = json_decode(file_get_contents($file), true);
        if ($s) return $s;
    }
    return ['custom_css' => '', 'medals' => [], 'status' => '', 'cover_css' => ''];
}
 
function fn_saveUserProfileSettings($userId, $settings) {
    $user = fn_getUserById($userId);
    if (!$user) return false;
    $dir = PROFILES_DIR . $user['username'] . '/';
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
    return file_put_contents($dir . 'settings.json', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) !== false;
}
 
// ============================================
// ЧЕРНОВИКИ
// ============================================
function fn_saveDraft($userId, $data) {
    $dir = DRAFTS_DIR . $userId . '/';
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
    $id = $data['id'] ?? uniqid();
    $draft = array_merge($data, ['id' => $id, 'user_id' => $userId, 'updated_at' => date('Y-m-d H:i:s')]);
    if (empty($draft['created_at'])) $draft['created_at'] = date('Y-m-d H:i:s');
    file_put_contents($dir . $id . '.json', json_encode($draft, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $draft;
}
 
function fn_getUserDrafts($userId) {
    $dir = DRAFTS_DIR . $userId . '/';
    if (!file_exists($dir)) return [];
    $drafts = [];
    foreach (glob($dir . '*.json') ?: [] as $f) {
        $d = json_decode(file_get_contents($f), true);
        if ($d) $drafts[] = $d;
    }
    usort($drafts, function($a, $b) { return strtotime($b['updated_at'] ?? '0') - strtotime($a['updated_at'] ?? '0'); });
    return $drafts;
}
 
function fn_deleteDraft($userId, $draftId) {
    $file = DRAFTS_DIR . $userId . '/' . $draftId . '.json';
    if (file_exists($file)) return unlink($file);
    return false;
}
 
// ============================================
// ИЗБРАННОЕ
// ============================================
function fn_toggleFavorite($userId, $type, $itemId) {
    $dir = FAVORITES_DIR . $userId . '/';
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
    $file = $dir . 'favorites.json';
    $favorites = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
 
    $key = $type . ':' . $itemId;
    $idx = array_search($key, $favorites);
    if ($idx !== false) {
        array_splice($favorites, $idx, 1);
        $added = false;
    } else {
        $favorites[] = $key;
        $added = true;
    }
    file_put_contents($file, json_encode($favorites, JSON_UNESCAPED_UNICODE));
    return $added;
}
 
function fn_getUserFavorites($userId) {
    $file = FAVORITES_DIR . $userId . '/favorites.json';
    if (!file_exists($file)) return [];
    return json_decode(file_get_contents($file), true) ?: [];
}
 
function fn_isFavorite($userId, $type, $itemId) {
    $favs = fn_getUserFavorites($userId);
    return in_array($type . ':' . $itemId, $favs);
}
 
// ============================================
// УТИЛИТЫ
// ============================================
function fn_sanitize($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}
 
function fn_generateId() {
    return substr(md5(uniqid(mt_rand(), true)), 0, 12);
}
 
function fn_generateSlug($title) {
    $slug = mb_strtolower($title);
    $slug = preg_replace('/[^a-zа-яё0-9\s-]/u', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    if (empty($slug)) $slug = 'item';
    return $slug . '-' . substr(md5(uniqid()), 0, 6);
}
 
function fn_handleUpload($fileKey, $allowedTypes, $maxSize = null) {
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] !== UPLOAD_ERR_OK) return null;
    $file = $_FILES[$fileKey];
    $maxSize = $maxSize ?: MAX_FILE_SIZE;
    if ($file['size'] > $maxSize) return null;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedTypes)) return null;
    $newName = fn_generateId() . '.' . $ext;
    $dest = UPLOADS_DIR . $newName;
    if (move_uploaded_file($file['tmp_name'], $dest)) return 'VP/uploads/' . $newName;
    return null;
}

function fn_isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function fn_response($data, $redirectUrl = null, $code = 200) {
    if (fn_isAjaxRequest()) {
        if (!empty($redirectUrl) && is_array($data)) {
            $data['redirect'] = $redirectUrl;
        }
        fn_jsonResponse($data, $code);
    }

    if (!empty($data['success']) && !empty($redirectUrl)) {
        header('Location: ' . $redirectUrl);
        exit;
    }

    if ($code !== 200) {
        http_response_code($code);
    }
    header('Content-Type: text/plain; charset=utf-8');
    if (is_string($data)) {
        echo $data;
    } elseif (!empty($data['error'])) {
        echo $data['error'];
    } else {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function fn_jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
 
function fn_timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    if ($diff < 60) return 'только что';
    if ($diff < 3600) return floor($diff / 60) . ' мин. назад';
    if ($diff < 86400) return floor($diff / 3600) . ' ч. назад';
    if ($diff < 604800) return floor($diff / 86400) . ' дн. назад';
    return date('d.m.Y', $time);
}
 
function fn_formatNumber($n) {
    if ($n >= 1000000) return round($n / 1000000, 1) . 'M';
    if ($n >= 1000) return round($n / 1000, 1) . 'K';
    return $n;
}
 
// ============================================
// ОБЩИЙ HTML: шапка/подвал
// ============================================
function fn_renderHead($title, $extraCss = '') {
    $user = fn_getCurrentUser();
    $csrfToken = generateCsrfToken();
    ?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo fn_sanitize($title); ?> | <?php echo SITE_NAME; ?></title>
    <meta name="description" content="My Cryndel - сообщество игроков Minecraft">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="theme-color" content="#10b981">
    <style>
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #d1fae5;
            --primary-lighter: #ecfdf5;
            --accent: #8b5cf6;
            --accent-light: #ede9fe;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --info: #3b82f6;
            --info-light: #dbeafe;
            --text: #111827;
            --text-secondary: #374151;
            --text-light: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --background: #f0f2f5;
            --card-bg: #ffffff;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
            --radius: 12px;
            --radius-lg: 16px;
            --radius-xl: 24px;
            --radius-full: 9999px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.6;
            min-height: 100vh;
        }
        a { color: var(--primary); text-decoration: none; }
        a:hover { color: var(--primary-dark); }
 
        /* Layout */
        .page-container { max-width: 1200px; margin: 0 auto; padding: 0 16px; }
        .content-container { max-width: 900px; margin: 0 auto; padding: 80px 16px 40px; }
 
        /* Header */
        .site-header {
            background: rgba(255,255,255,0.97);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--border);
            position: fixed; top: 0; left: 0; right: 0; z-index: 1000;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .header-inner { max-width: 1200px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; height: 60px; padding: 0 20px; }
        .site-logo { display: flex; align-items: center; gap: 10px; font-weight: 800; font-size: 18px; color: var(--primary); text-decoration: none; }
        .site-logo img { width: 28px; height: 28px; border-radius: 8px; }
        .main-nav { display: flex; align-items: center; gap: 4px; }
        .nav-item {
            display: flex; align-items: center; gap: 6px; padding: 8px 14px;
            color: var(--text-light); font-size: 13px; font-weight: 500;
            border-radius: var(--radius-full); transition: var(--transition); text-decoration: none;
            white-space: nowrap;
        }
        .nav-item:hover, .nav-item.active { color: var(--primary); background: var(--primary-light); }
        .nav-item i { font-size: 15px; }
        .nav-item img { width: 22px; height: 22px; border-radius: 50%; }
        .header-right { display: flex; align-items: center; gap: 8px; }
 
        /* Notification bell */
        .notif-bell { position: relative; cursor: pointer; padding: 8px; border-radius: 50%; color: var(--text-light); transition: var(--transition); }
        .notif-bell:hover { background: var(--border-light); color: var(--primary); }
        .notif-badge {
            position: absolute; top: 2px; right: 2px; background: var(--danger);
            color: #fff; font-size: 9px; font-weight: 700; min-width: 16px; height: 16px;
            border-radius: 8px; display: flex; align-items: center; justify-content: center;
            padding: 0 4px; border: 2px solid #fff;
        }
        .notif-badge.hidden { display: none; }
 
        /* Mobile menu */
        .mobile-toggle { display: none; background: none; border: none; font-size: 20px; color: var(--text); cursor: pointer; padding: 8px; border-radius: 8px; }
        .mobile-nav {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 2000; opacity: 0;
            transition: opacity 0.3s;
        }
        .mobile-nav.open { display: flex; opacity: 1; }
        .mobile-nav-inner {
            width: 280px; background: #fff; height: 100%; padding: 0;
            box-shadow: var(--shadow-xl); overflow-y: auto;
            transform: translateX(-100%); transition: transform 0.3s;
        }
        .mobile-nav.open .mobile-nav-inner { transform: translateX(0); }
        .mobile-nav-header {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 20px; border-bottom: 1px solid var(--border);
        }
        .mobile-nav-close { background: none; border: none; font-size: 20px; cursor: pointer; color: var(--text-light); padding: 4px; }
        .mobile-nav-links { padding: 8px 0; }
        .mobile-nav-link {
            display: flex; align-items: center; gap: 12px; padding: 12px 20px;
            color: var(--text); font-size: 15px; font-weight: 500; text-decoration: none;
            transition: var(--transition);
        }
        .mobile-nav-link:hover, .mobile-nav-link.active { color: var(--primary); background: var(--primary-lighter); }
        .mobile-nav-link i { width: 20px; text-align: center; font-size: 16px; }
        .mobile-nav-divider { height: 1px; background: var(--border); margin: 8px 0; }
        .mobile-user-section { padding: 16px 20px; border-top: 1px solid var(--border); }
        .mobile-user-info { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
        .mobile-user-info img { width: 36px; height: 36px; border-radius: 50%; }
        .mobile-user-name { font-weight: 600; font-size: 14px; }
 
        /* Cards */
        .card {
            background: var(--card-bg); border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm); border: 1px solid var(--border-light);
            overflow: hidden; transition: var(--transition);
        }
        .card:hover { box-shadow: var(--shadow-md); transform: translateY(-1px); }
        .card-body { padding: 20px; }
        .card-footer { padding: 12px 20px; border-top: 1px solid var(--border-light); display: flex; align-items: center; justify-content: space-between; }
 
        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px;
            font-size: 14px; font-weight: 600; border: none; border-radius: var(--radius);
            cursor: pointer; transition: var(--transition); text-decoration: none;
            font-family: inherit; line-height: 1.4;
        }
        .btn-primary { background: var(--primary); color: #fff; }
        .btn-primary:hover { background: var(--primary-dark); color: #fff; transform: translateY(-1px); box-shadow: var(--shadow-md); }
        .btn-secondary { background: var(--border-light); color: var(--text); }
        .btn-secondary:hover { background: var(--border); }
        .btn-outline { background: transparent; color: var(--text); border: 1px solid var(--border); }
        .btn-outline:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-lighter); }
        .btn-danger { background: var(--danger); color: #fff; }
        .btn-danger:hover { background: #dc2626; color: #fff; }
        .btn-ghost { background: transparent; color: var(--text-light); padding: 8px; }
        .btn-ghost:hover { color: var(--primary); background: var(--primary-lighter); }
        .btn-sm { padding: 6px 14px; font-size: 13px; border-radius: 8px; }
        .btn-lg { padding: 14px 28px; font-size: 16px; border-radius: var(--radius-lg); }
        .btn-icon { padding: 8px; border-radius: 8px; }
        .btn-round { border-radius: var(--radius-full); }
 
        /* Forms */
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px; }
        .form-input {
            width: 100%; padding: 10px 14px; font-size: 14px; border: 1.5px solid var(--border);
            border-radius: var(--radius); background: #fff; color: var(--text);
            transition: var(--transition); font-family: inherit; outline: none;
        }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .form-input::placeholder { color: var(--text-muted); }
        textarea.form-input { resize: vertical; min-height: 80px; }
        .form-hint { font-size: 12px; color: var(--text-muted); margin-top: 4px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .checkbox-label { display: flex; align-items: center; gap: 8px; font-size: 14px; cursor: pointer; padding: 4px 0; }
        .checkbox-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); }
 
        /* Section header */
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 24px; flex-wrap: wrap; gap: 12px;
        }
        .section-title { font-size: 24px; font-weight: 800; display: flex; align-items: center; gap: 10px; }
        .section-title i { color: var(--primary); }
 
        /* Content grid */
        .items-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .stickers-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
 
        /* Item card */
        .item-card {
            background: var(--card-bg); border-radius: var(--radius-lg);
            border: 1px solid var(--border-light); overflow: hidden;
            transition: var(--transition); cursor: pointer; position: relative;
        }
        .item-card:hover { box-shadow: var(--shadow-md); transform: translateY(-2px); border-color: var(--primary-light); }
        .item-card-image {
            width: 100%; height: 160px; object-fit: cover; background: var(--border-light);
            display: flex; align-items: center; justify-content: center;
        }
        .item-card-image i { font-size: 48px; color: var(--text-muted); }
        .item-card-image img { width: 100%; height: 100%; object-fit: cover; }
        .item-card-body { padding: 14px; }
        .item-card-title { font-size: 15px; font-weight: 700; margin-bottom: 4px; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--text); }
        .item-card-author { font-size: 12px; color: var(--text-light); margin-bottom: 8px; }
        .item-card-meta { display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--text-muted); }
        .item-card-meta span { display: flex; align-items: center; gap: 4px; }
        .item-card-tags { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px; }
        .item-card-badges { position: absolute; top: 8px; right: 8px; display: flex; gap: 4px; }
 
        /* Tags */
        .tag { display: inline-block; padding: 3px 10px; font-size: 11px; font-weight: 500; border-radius: var(--radius-full); background: var(--border-light); color: var(--text-light); }
        .tag-primary { background: var(--primary-light); color: var(--primary-dark); }
 
        /* Badges */
        .badge { display: inline-flex; align-items: center; gap: 3px; padding: 2px 8px; font-size: 10px; font-weight: 700; border-radius: var(--radius-full); }
        .badge-ai { background: var(--accent-light); color: var(--accent); }
        .badge-nsfw { background: var(--danger-light); color: var(--danger); }
        .badge-author { background: var(--primary-light); color: var(--primary-dark); }
        .badge-new { background: var(--info-light); color: var(--info); }
 
        /* Like button */
        .like-btn {
            display: inline-flex; align-items: center; gap: 4px; padding: 6px 12px;
            border: 1px solid var(--border); border-radius: var(--radius-full);
            background: #fff; color: var(--text-light); cursor: pointer;
            font-size: 13px; font-weight: 500; transition: var(--transition);
        }
        .like-btn:hover { border-color: var(--danger); color: var(--danger); background: var(--danger-light); }
        .like-btn.active { border-color: var(--danger); color: var(--danger); background: var(--danger-light); }
        .like-btn.active i { font-weight: 900; }
 
        /* Search bar */
        .search-bar { position: relative; margin-bottom: 20px; }
        .search-bar input {
            width: 100%; padding: 12px 16px 12px 44px; font-size: 14px;
            border: 1.5px solid var(--border); border-radius: var(--radius-lg);
            background: #fff; outline: none; transition: var(--transition);
        }
        .search-bar input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-light); }
        .search-bar i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); }
        .search-filters { display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-chip {
            padding: 6px 14px; font-size: 13px; font-weight: 500;
            border-radius: var(--radius-full); border: 1px solid var(--border);
            background: #fff; color: var(--text-light); cursor: pointer; transition: var(--transition);
        }
        .filter-chip:hover, .filter-chip.active { background: var(--primary); color: #fff; border-color: var(--primary); }
 
        /* Empty state */
        .empty-state { text-align: center; padding: 60px 20px; }
        .empty-state i { font-size: 64px; color: var(--border); margin-bottom: 16px; }
        .empty-state h3 { font-size: 20px; font-weight: 700; margin-bottom: 8px; color: var(--text); }
        .empty-state p { color: var(--text-light); margin-bottom: 20px; font-size: 15px; }
 
        /* Upload form */
        .upload-section {
            background: var(--card-bg); border-radius: var(--radius-xl); padding: 32px;
            border: 1px solid var(--border-light); margin-bottom: 24px; box-shadow: var(--shadow-sm);
        }
        .upload-section h2 { font-size: 20px; font-weight: 700; margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
        .upload-section h2 i { color: var(--primary); }
        .file-upload-area {
            border: 2px dashed var(--border); border-radius: var(--radius); padding: 30px;
            text-align: center; cursor: pointer; transition: var(--transition); margin-bottom: 8px;
        }
        .file-upload-area:hover { border-color: var(--primary); background: var(--primary-lighter); }
        .file-upload-area i { font-size: 32px; color: var(--text-muted); margin-bottom: 8px; }
        .file-upload-area p { color: var(--text-light); font-size: 13px; }
        .upload-status {
            margin-top: 10px; padding: 10px 14px; border-radius: 10px; font-weight: 600;
            display: inline-block; transition: opacity 0.25s ease, transform 0.25s ease;
            background: rgba(0,0,0,0.03); color: var(--text-secondary);
        }
        .upload-status.success { background: linear-gradient(90deg, rgba(16,185,129,0.12), rgba(59,130,246,0.06)); color: var(--primary-dark); }
        .upload-status.error { background: linear-gradient(90deg, rgba(239,68,68,0.08), rgba(250,204,21,0.02)); color: var(--danger); }
        .cmd-suggestions {
            position: absolute; z-index: 3000; min-width: 220px; max-width: 360px;
            background: var(--card-bg); border: 1px solid var(--border); box-shadow: var(--shadow-lg);
            border-radius: 8px; overflow: hidden; font-size: 14px;
        }
        .cmd-suggestions ul { list-style: none; margin: 0; padding: 6px 0; }
        .cmd-suggestions li { padding: 8px 12px; cursor: pointer; color: var(--text); }
        .cmd-suggestions li:hover, .cmd-suggestions li.active { background: var(--primary-lighter); color: var(--primary); }
 
        /* Detail page */
        .detail-header {
            background: var(--card-bg); border-radius: var(--radius-xl); overflow: hidden;
            border: 1px solid var(--border-light); margin-bottom: 20px; box-shadow: var(--shadow-sm);
        }
        .detail-cover { height: 200px; background: linear-gradient(135deg, var(--primary), var(--accent)); position: relative; display: flex; align-items: center; justify-content: center; }
        .detail-cover img { width: 100%; height: 100%; object-fit: cover; }
        .detail-cover i { font-size: 64px; color: rgba(255,255,255,0.5); }
        /* Profile cover and header */
        .profile-cover { height: 260px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 16px; position: relative; overflow: hidden; }
        .profile-cover img { width: 100%; height: 100%; object-fit: cover; filter: saturate(1.03) contrast(1.02); }
        .profile-header { display:flex; align-items:flex-end; gap:16px; padding: 16px; position: absolute; left: 16px; bottom: 16px; }
        .profile-avatar { width:96px; height:96px; border-radius:16px; border:4px solid #fff; box-shadow: var(--shadow-lg); overflow:hidden; background:#fff; }
        .profile-info { color:#fff; text-shadow: 0 2px 8px rgba(0,0,0,0.25); }
        .profile-info h2 { margin:0; font-size:20px; font-weight:800; }
        .profile-info p { margin:0; font-size:13px; opacity:0.95; }
        .detail-info { padding: 24px; }
        .detail-title { font-size: 28px; font-weight: 800; margin-bottom: 8px; line-height: 1.2; }
        .detail-author { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; }
        .detail-author img { width: 28px; height: 28px; border-radius: 50%; }
        .detail-author span { font-size: 14px; color: var(--text-light); font-weight: 500; }
        .detail-description { color: var(--text-secondary); font-size: 15px; line-height: 1.7; margin-bottom: 16px; }
        .detail-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .detail-stats { display: flex; gap: 20px; margin-bottom: 16px; }
        .detail-stat { display: flex; align-items: center; gap: 6px; font-size: 14px; color: var(--text-light); }
        .detail-stat i { color: var(--text-muted); }
 
        /* Comments section */
        .comments-section { background: var(--card-bg); border-radius: var(--radius-xl); padding: 24px; border: 1px solid var(--border-light); margin-top: 20px; }
        .comments-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        .comment-form { display: flex; gap: 10px; margin-bottom: 20px; }
        .comment-form input, .comment-form textarea {
            flex: 1; padding: 10px 14px; border: 1.5px solid var(--border);
            border-radius: var(--radius); font-size: 14px; font-family: inherit; outline: none;
        }
        .comment-form input:focus, .comment-form textarea:focus { border-color: var(--primary); }
        .comment-item { display: flex; gap: 10px; padding: 12px 0; border-bottom: 1px solid var(--border-light); }
        .comment-item:last-child { border-bottom: none; }
        .comment-avatar { width: 36px; height: 36px; border-radius: 50%; flex-shrink: 0; }
        .comment-body { flex: 1; min-width: 0; }
        .comment-author { font-weight: 600; font-size: 13px; color: var(--text); }
        .comment-text { font-size: 14px; color: var(--text-secondary); margin-top: 2px; word-wrap: break-word; }
        .comment-time { font-size: 11px; color: var(--text-muted); margin-top: 4px; }
        .comment-actions { display: flex; gap: 12px; margin-top: 4px; }
        .comment-action { font-size: 12px; color: var(--text-muted); cursor: pointer; display: flex; align-items: center; gap: 4px; }
        .comment-action:hover { color: var(--primary); }
 
        /* Player */
        .music-player {
            position: fixed; bottom: 0; left: 0; right: 0; z-index: 999;
            background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);
            border-top: 1px solid var(--border); box-shadow: 0 -4px 20px rgba(0,0,0,0.08);
            padding: 12px 20px; display: none; align-items: center; gap: 16px;
        }
        .music-player.active { display: flex; }
        .player-info { flex: 1; min-width: 0; }
        .player-title { font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .player-author { font-size: 12px; color: var(--text-light); }
        .player-controls { display: flex; align-items: center; gap: 8px; }
        .player-btn { background: none; border: none; font-size: 18px; cursor: pointer; padding: 8px; color: var(--text); border-radius: 50%; transition: var(--transition); }
        .player-btn:hover { background: var(--border-light); color: var(--primary); }
        .player-btn.main { font-size: 24px; background: var(--primary); color: #fff; padding: 10px; }
        .player-btn.main:hover { background: var(--primary-dark); }
        .player-progress { flex: 2; height: 4px; background: var(--border); border-radius: 2px; cursor: pointer; position: relative; }
        .player-progress-bar { height: 100%; background: var(--primary); border-radius: 2px; width: 0; transition: width 0.1s linear; }
 
        /* Sticker card */
        .sticker-card {
            background: var(--card-bg); border-radius: var(--radius); border: 1px solid var(--border-light);
            overflow: hidden; cursor: pointer; transition: var(--transition); position: relative; aspect-ratio: 1;
            display: flex; align-items: center; justify-content: center;
        }
        .sticker-card:hover { transform: scale(1.05); box-shadow: var(--shadow-md); border-color: var(--primary-light); }
        .sticker-card img { max-width: 100%; max-height: 100%; object-fit: contain; padding: 8px; }
        .sticker-card .sticker-likes {
            position: absolute; bottom: 4px; right: 4px; font-size: 10px;
            background: rgba(0,0,0,0.6); color: #fff; padding: 2px 6px;
            border-radius: 8px; display: flex; align-items: center; gap: 3px;
        }
 
        /* Alerts */
        .alert { padding: 12px 16px; border-radius: var(--radius); font-size: 14px; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
        .alert-success { background: var(--primary-light); color: var(--primary-dark); }
        .alert-error { background: var(--danger-light); color: var(--danger); }
        .alert-info { background: var(--info-light); color: var(--info); }
        .alert-warning { background: var(--warning-light); color: var(--warning); }
 
        /* Download warning */
        .download-warning {
            background: var(--warning-light); color: #92400e; padding: 8px 12px;
            font-size: 12px; border-radius: 8px; display: flex; align-items: center; gap: 6px; margin-top: 8px;
        }
 
        /* Tabs */
        .tabs { display: flex; gap: 2px; background: var(--border-light); border-radius: var(--radius); padding: 3px; margin-bottom: 20px; overflow-x: auto; }
        .tab-btn {
            padding: 8px 16px; font-size: 13px; font-weight: 600; border: none; background: none;
            cursor: pointer; border-radius: 8px; color: var(--text-light); transition: var(--transition);
            white-space: nowrap;
        }
        .tab-btn:hover { color: var(--text); }
        .tab-btn.active { background: #fff; color: var(--primary); box-shadow: var(--shadow-sm); }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
 
        /* Role badges */
        .role-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: var(--radius-full); font-size: 11px; font-weight: 600; color: #fff; }
        .role-badge.admin { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .role-badge.creator { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .role-badge.tech { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .role-badge.legendary { background: linear-gradient(135deg, #ec4899, #db2777); }
        .role-badge.mythic { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .role-badge.golden { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
 
        /* Toast notifications */
        .toast-container { position: fixed; top: 76px; right: 20px; z-index: 9999; display: flex; flex-direction: column; gap: 8px; }
        .toast {
            padding: 12px 20px; border-radius: var(--radius); font-size: 14px; font-weight: 500;
            box-shadow: var(--shadow-lg); animation: slideIn 0.3s ease; max-width: 340px;
            display: flex; align-items: center; gap: 8px;
        }
        .toast-success { background: var(--primary); color: #fff; }
        .toast-error { background: var(--danger); color: #fff; }
        .toast-info { background: var(--info); color: #fff; }
 
        /* Modal */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5); z-index: 5000; display: none;
            align-items: center; justify-content: center; padding: 20px;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active { display: flex; }
        .modal-box {
            background: #fff; border-radius: var(--radius-xl); max-width: 560px; width: 100%;
            max-height: 90vh; overflow-y: auto; box-shadow: var(--shadow-xl);
        }
        .modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
        .modal-title { font-size: 18px; font-weight: 700; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: var(--text-light); padding: 4px 8px; border-radius: 8px; }
        .modal-close:hover { background: var(--border-light); }
        .modal-body { padding: 24px; }
        .modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 8px; }
 
        /* Pagination */
        .pagination { display: flex; justify-content: center; gap: 4px; margin-top: 24px; }
        .page-btn { padding: 8px 14px; border-radius: 8px; border: 1px solid var(--border); background: #fff; cursor: pointer; font-size: 13px; font-weight: 500; transition: var(--transition); }
        .page-btn:hover, .page-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); }
 
        /* FAB */
        .fab { position: fixed; bottom: 24px; right: 24px; z-index: 500; }
        .fab-btn {
            width: 56px; height: 56px; border-radius: 50%; background: var(--primary); color: #fff;
            display: flex; align-items: center; justify-content: center; font-size: 24px;
            box-shadow: var(--shadow-lg); border: none; cursor: pointer; transition: var(--transition);
            text-decoration: none;
        }
        .fab-btn:hover { background: var(--primary-dark); transform: scale(1.1); color: #fff; }
 
        /* Animations */
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
 
        /* Responsive */
        @media (max-width: 768px) {
            .main-nav { display: none; }
            .mobile-toggle { display: block; }
            .content-container { padding: 72px 12px 32px; }
            .section-header { flex-direction: column; align-items: flex-start; }
            .section-title { font-size: 20px; }
            .items-grid { grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); gap: 10px; }
            .stickers-grid { grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 8px; }
            .form-row { grid-template-columns: 1fr; }
            .upload-section { padding: 20px; }
            .detail-title { font-size: 22px; }
            .detail-cover { height: 150px; }
            .music-player { padding: 8px 12px; }
            .header-inner { padding: 0 12px; }
            .fab { bottom: 16px; right: 16px; }
            .fab-btn { width: 48px; height: 48px; font-size: 20px; }
            .btn { padding: 8px 16px; font-size: 13px; }
            .tabs { gap: 0; }
            .tab-btn { padding: 8px 10px; font-size: 12px; }
        }
 
        @media (max-width: 480px) {
            .items-grid { grid-template-columns: 1fr; }
            .detail-info { padding: 16px; }
        }
 
        /* Scrollbar */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }
 
        <?php echo $extraCss; ?>
    </style>
</head>
<body>
    <?php
}
 
function fn_renderHeader($activeSection = '') {
    $user = fn_getCurrentUser();
    $unreadCount = $user ? fn_getUnreadCount($user['id']) : 0;
    ?>
    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="site-logo">
                <img src="/dder.png" alt="MC">
                <span>My Cryndel</span>
            </a>
 
            <nav class="main-nav">
                <a href="/" class="nav-item <?php echo $activeSection === 'feed' ? 'active' : ''; ?>"><i class="fas fa-home"></i><span>Лента</span></a>
                <a href="/music.php" class="nav-item <?php echo $activeSection === 'music' ? 'active' : ''; ?>"><i class="fas fa-music"></i><span>Музыка</span></a>
                <a href="/stickers.php" class="nav-item <?php echo $activeSection === 'stickers' ? 'active' : ''; ?>"><i class="fas fa-sticky-note"></i><span>Стикеры</span></a>
                <a href="/coding.php" class="nav-item <?php echo $activeSection === 'coding' ? 'active' : ''; ?>"><i class="fas fa-code"></i><span>Кодинг</span></a>
                <a href="/themes.php" class="nav-item <?php echo $activeSection === 'themes' ? 'active' : ''; ?>"><i class="fas fa-palette"></i><span>Оформление</span></a>
                <?php if ($user): ?>
                    <a href="/<?php echo $user['username']; ?>" class="nav-item <?php echo $activeSection === 'profile' ? 'active' : ''; ?>">
                        <img src="<?php echo fn_getAvatarUrl($user); ?>" alt="">
                        <span>Профиль</span>
                    </a>
                <?php else: ?>
                    <a href="/?action=login" class="nav-item"><i class="fas fa-sign-in-alt"></i><span>Вход</span></a>
                    <a href="/?action=register" class="nav-item"><i class="fas fa-user-plus"></i><span>Регистрация</span></a>
                <?php endif; ?>
            </nav>
 
            <div class="header-right">
                <?php if ($user): ?>
                    <div class="notif-bell" id="notifBell" onclick="toggleNotifDropdown()">
                        <i class="far fa-bell"></i>
                        <span class="notif-badge <?php echo $unreadCount === 0 ? 'hidden' : ''; ?>" id="notifBadge"><?php echo $unreadCount; ?></span>
                    </div>
                <?php endif; ?>
                <button class="mobile-toggle" onclick="openMobileNav()"><i class="fas fa-bars"></i></button>
            </div>
        </div>
    </header>
 
    <!-- Mobile nav -->
    <div class="mobile-nav" id="mobileNav" onclick="if(event.target===this)closeMobileNav()">
        <div class="mobile-nav-inner">
            <div class="mobile-nav-header">
                <a href="/" class="site-logo"><img src="/dder.png" alt=""><span>My Cryndel</span></a>
                <button class="mobile-nav-close" onclick="closeMobileNav()"><i class="fas fa-times"></i></button>
            </div>
            <div class="mobile-nav-links">
                <a href="/" class="mobile-nav-link <?php echo $activeSection === 'feed' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Лента</a>
                <a href="/music.php" class="mobile-nav-link <?php echo $activeSection === 'music' ? 'active' : ''; ?>"><i class="fas fa-music"></i> Музыка</a>
                <a href="/stickers.php" class="mobile-nav-link <?php echo $activeSection === 'stickers' ? 'active' : ''; ?>"><i class="fas fa-sticky-note"></i> Стикеры</a>
                <a href="/coding.php" class="mobile-nav-link <?php echo $activeSection === 'coding' ? 'active' : ''; ?>"><i class="fas fa-code"></i> Кодинг</a>
                <a href="/themes.php" class="mobile-nav-link <?php echo $activeSection === 'themes' ? 'active' : ''; ?>"><i class="fas fa-palette"></i> Оформление</a>
                <div class="mobile-nav-divider"></div>
                <a href="/?action=notifications" class="mobile-nav-link"><i class="fas fa-bell"></i> Уведомления <?php if ($unreadCount > 0): ?><span class="badge badge-ai"><?php echo $unreadCount; ?></span><?php endif; ?></a>
                <?php if ($user): ?>
                    <a href="/<?php echo $user['username']; ?>" class="mobile-nav-link"><i class="fas fa-user"></i> Профиль</a>
                <?php endif; ?>
            </div>
            <?php if ($user): ?>
                <div class="mobile-user-section">
                    <div class="mobile-user-info">
                        <img src="<?php echo fn_getAvatarUrl($user); ?>" alt="">
                        <div>
                            <div class="mobile-user-name">@<?php echo fn_sanitize($user['username']); ?></div>
                        </div>
                    </div>
                    <form method="post" action="/">
                        <input type="hidden" name="action" value="logout">
                        <button type="submit" class="btn btn-outline btn-sm" style="width:100%;"><i class="fas fa-sign-out-alt"></i> Выйти</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
 
    <div class="toast-container" id="toastContainer"></div>
    <?php
}
 
function fn_renderFooter($extraJs = '') {
    ?>
 
    <!-- Global music player -->
    <div class="music-player" id="globalPlayer">
        <div class="player-info">
            <div class="player-title" id="playerTitle">-</div>
            <div class="player-author" id="playerAuthor">-</div>
        </div>
        <div class="player-progress" id="playerProgress" onclick="seekPlayer(event)">
            <div class="player-progress-bar" id="playerProgressBar"></div>
        </div>
        <div class="player-controls">
            <button class="player-btn" onclick="playerAction('prev')"><i class="fas fa-step-backward"></i></button>
            <button class="player-btn main" id="playerPlayBtn" onclick="playerAction('toggle')"><i class="fas fa-play"></i></button>
            <button class="player-btn" onclick="playerAction('next')"><i class="fas fa-step-forward"></i></button>
            <button class="player-btn" onclick="playerAction('close')"><i class="fas fa-times"></i></button>
        </div>
    </div>
 
    <script>
    // Mobile nav
    function openMobileNav() {
        const nav = document.getElementById('mobileNav');
        nav.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeMobileNav() {
        const nav = document.getElementById('mobileNav');
        nav.classList.remove('open');
        document.body.style.overflow = '';
    }
 
    // Toast
    function showToast(msg, type = 'success') {
        const container = document.getElementById('toastContainer');
        if (!container) return;
        const toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle') + '"></i> ' + msg;
        container.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; setTimeout(() => toast.remove(), 300); }, 3000);
    }
 
    // Global music player
    let currentAudio = null;
    let isPlaying = false;
    let currentTrack = null;
 
    function playTrack(url, title, author) {
        if (currentAudio) currentAudio.pause();
        currentAudio = new Audio(url);
        currentTrack = { url, title, author };
        document.getElementById('playerTitle').textContent = title || 'Музыка';
        document.getElementById('playerAuthor').textContent = author ? '@' + author : '';
        document.getElementById('globalPlayer').classList.add('active');
        document.getElementById('playerPlayBtn').innerHTML = '<i class="fas fa-pause"></i>';
        currentAudio.play();
        isPlaying = true;
        currentAudio.addEventListener('timeupdate', updateProgress);
        currentAudio.addEventListener('ended', () => {
            isPlaying = false;
            document.getElementById('playerPlayBtn').innerHTML = '<i class="fas fa-play"></i>';
        });
    }
 
    function updateProgress() {
        if (!currentAudio || !currentAudio.duration) return;
        const pct = (currentAudio.currentTime / currentAudio.duration) * 100;
        document.getElementById('playerProgressBar').style.width = pct + '%';
    }
 
    function seekPlayer(e) {
        if (!currentAudio || !currentAudio.duration) return;
        const rect = e.currentTarget.getBoundingClientRect();
        const pct = (e.clientX - rect.left) / rect.width;
        currentAudio.currentTime = pct * currentAudio.duration;
    }
 
    function playerAction(action) {
        if (action === 'toggle') {
            if (!currentAudio) return;
            if (isPlaying) { currentAudio.pause(); document.getElementById('playerPlayBtn').innerHTML = '<i class="fas fa-play"></i>'; }
            else { currentAudio.play(); document.getElementById('playerPlayBtn').innerHTML = '<i class="fas fa-pause"></i>'; }
            isPlaying = !isPlaying;
        } else if (action === 'close') {
            if (currentAudio) { currentAudio.pause(); currentAudio = null; }
            document.getElementById('globalPlayer').classList.remove('active');
            isPlaying = false;
        }
    }
 
    // Notifications dropdown (placeholder)
    function toggleNotifDropdown() {
        window.location.href = '/?action=notifications';
    }
 
    // AJAX helper
    async function apiCall(url, data = null) {
        const opts = data ? {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: typeof data === 'string' ? data : new URLSearchParams(data).toString()
        } : {};
        const resp = await fetch(url, opts);
        return resp.json();
    }
 
    // Like item
    async function likeItem(type, itemId, btn) {
        const data = await apiCall('/api.php', { action: 'like_item', item_type: type, item_id: itemId });
        if (data.likes !== undefined) {
            const icon = btn.querySelector('i');
            const count = btn.querySelector('.like-count');
            if (data.liked) {
                icon.className = 'fas fa-heart';
                btn.classList.add('active');
            } else {
                icon.className = 'far fa-heart';
                btn.classList.remove('active');
            }
            if (count) count.textContent = data.likes;
        }
    }
 
    // Escape HTML
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }
 
    <?php echo $extraJs; ?>
    </script>
</body>
</html>
    <?php
}