<?php
/**
 * api.php — Расширенный API My Cryndel (10x endpoints)
 *
 * CONTENT ENDPOINTS:
 *   upload_music, upload_sticker, upload_coding, upload_theme
 *   get_items, get_item, get_user_items
 *   edit_item, delete_item
 *   like_item, unlike_item
 *   add_comment, get_comments, delete_comment, like_comment
 *   increment_download
 *
 * FAVORITES / BOOKMARKS:
 *   toggle_favorite, get_favorites
 *
 * DRAFTS:
 *   save_draft, get_drafts, delete_draft
 *
 * PROFILE:
 *   get_profile, update_profile_settings, get_user_stats
 *
 * NOTIFICATIONS:
 *   get_notifications, mark_read, mark_all_read, get_unread_count
 *
 * SEARCH:
 *   search (users + items combined)
 *
 * TRENDING:
 *   get_trending
 *
 * POSTS:
 *   like_post, get_post_comments, add_post_comment
 *
 * MISC:
 *   ping
 */
 
require_once __DIR__ . '/functions.php';
 
$user = fn_getCurrentUser();
$action = $_POST['action'] ?? $_GET['action'] ?? '';
 
switch ($action) {
 
// ============================================
// CONTENT UPLOADS
// ============================================
case 'upload_music':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $title = fn_sanitize($_POST['title'] ?? '');
    $use = fn_sanitize($_POST['use'] ?? '');
    $description = fn_sanitize($_POST['description'] ?? '');
    $tags = fn_sanitize($_POST['tags'] ?? '');
    $is_author = !empty($_POST['is_author']);
    $is_ai = !empty($_POST['is_ai']);
    $is_nsfw = !empty($_POST['is_nsfw']);
    if (empty($title) || empty($use)) fn_jsonResponse(['error' => 'Заполните обязательные поля'], 400);
    $iconPath = fn_handleUpload('icon_file', ['jpg','jpeg','png','gif','webp'], MAX_AVATAR_SIZE);
    if (!$iconPath && !empty($_POST['icon_url'])) $iconPath = filter_var($_POST['icon_url'], FILTER_VALIDATE_URL) ?: null;
    // Try uploaded file first
    $musicPath = fn_handleUpload('music_file', ['mp3','ogg','wav','flac','m4a','aac','webm']);
    // If user attempted to upload but it failed, return clearer error
    if (isset($_FILES['music_file']) && $_FILES['music_file']['error'] !== UPLOAD_ERR_NO_FILE && $musicPath === null) {
        fn_jsonResponse(['error' => 'Ошибка загрузки файла музыки. Проверьте формат и размер.'], 400);
    }
    if (!$musicPath && !empty($_POST['music_url'])) $musicPath = filter_var($_POST['music_url'], FILTER_VALIDATE_URL) ?: null;
    if (empty($musicPath)) fn_response(['error' => 'Укажите ссылку или загрузите файл музыки'], null, 400);
    $id = fn_generateId();
    $item = [
        'id' => $id, 'type' => 'music', 'title' => $title, 'use' => $use,
        'slug' => fn_generateSlug($title), 'icon' => $iconPath, 'file' => $musicPath,
        'description' => $description, 'tags' => $tags,
        'is_author' => $is_author, 'is_ai' => $is_ai, 'is_nsfw' => $is_nsfw,
        'user_id' => $user['id'], 'author' => $user['username'],
        'likes' => 0, 'liked_by' => [], 'plays' => 0, 'downloads' => 0,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents(MUSIC_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_response(['success' => true, 'message' => 'Музыка успешно загружена', 'item' => $item], '/music.php?view=detail&id=' . $id);
    break;
 
case 'upload_sticker':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $title = fn_sanitize($_POST['title'] ?? '');
    $use = fn_sanitize($_POST['use'] ?? '');
    $description = fn_sanitize($_POST['description'] ?? '');
    $tags = fn_sanitize($_POST['tags'] ?? '');
    $pack_name = fn_sanitize($_POST['pack_name'] ?? '');
    $is_author = !empty($_POST['is_author']);
    $is_nsfw = !empty($_POST['is_nsfw']);
    $is_ai = !empty($_POST['is_ai']);
    if (empty($title)) fn_jsonResponse(['error' => 'Заполните обязательные поля'], 400);
    $stickerPath = fn_handleUpload('sticker_file', ['png','gif','webp','webm','jpg','jpeg','tgs','svg']);
    if (isset($_FILES['sticker_file']) && $_FILES['sticker_file']['error'] !== UPLOAD_ERR_NO_FILE && $stickerPath === null) {
        fn_jsonResponse(['error' => 'Ошибка загрузки файла стикера. Проверьте формат и размер.'], 400);
    }
    if (!$stickerPath && !empty($_POST['sticker_url'])) $stickerPath = filter_var($_POST['sticker_url'], FILTER_VALIDATE_URL) ?: null;
    if (empty($stickerPath)) fn_response(['error' => 'Загрузите стикер или укажите ссылку'], null, 400);
    $id = fn_generateId();
    $item = [
        'id' => $id, 'type' => 'sticker', 'title' => $title, 'use' => $use ?: $title,
        'slug' => fn_generateSlug($title), 'file' => $stickerPath,
        'description' => $description, 'tags' => $tags, 'pack_name' => $pack_name,
        'is_author' => $is_author, 'is_nsfw' => $is_nsfw, 'is_ai' => $is_ai,
        'user_id' => $user['id'], 'author' => $user['username'],
        'likes' => 0, 'liked_by' => [], 'added_by' => [],
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents(STICKERS_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_response(['success' => true, 'message' => 'Стикер добавлен', 'item' => $item], '/stickers.php?view=detail&id=' . $id);
    break;
 
case 'upload_coding':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $title = fn_sanitize($_POST['title'] ?? '');
    $use = fn_sanitize($_POST['use'] ?? '');
    $description = fn_sanitize($_POST['description'] ?? '');
    $tags = fn_sanitize($_POST['tags'] ?? '');
    $category = fn_sanitize($_POST['category'] ?? 'mod');
    $version = fn_sanitize($_POST['version'] ?? '1.0');
    $mc_version = fn_sanitize($_POST['mc_version'] ?? '');
    $is_author = !empty($_POST['is_author']);
    $is_ai = !empty($_POST['is_ai']);
    if (empty($title)) fn_jsonResponse(['error' => 'Заполните обязательные поля'], 400);
    $codePath = fn_handleUpload('code_file', ['zip','rar','7z','tar','gz','jar','py','js','php','java','txt','json','xml','yml','yaml','toml','cfg','ini','properties','mcpack','mcworld','mcaddon','lua']);
    if (isset($_FILES['code_file']) && $_FILES['code_file']['error'] !== UPLOAD_ERR_NO_FILE && $codePath === null) {
        fn_jsonResponse(['error' => 'Ошибка загрузки файла сборки. Проверьте формат и размер.'], 400);
    }
    if (!$codePath && !empty($_POST['file_url'])) $codePath = filter_var($_POST['file_url'], FILTER_VALIDATE_URL) ?: null;
    if (empty($codePath)) fn_response(['error' => 'Загрузите файл или укажите ссылку'], null, 400);
    $iconPath = fn_handleUpload('icon_file', ['jpg','jpeg','png','gif','webp'], MAX_AVATAR_SIZE);
    if (!$iconPath && !empty($_POST['icon_url'])) $iconPath = filter_var($_POST['icon_url'], FILTER_VALIDATE_URL) ?: null;
    $id = fn_generateId();
    $item = [
        'id' => $id, 'type' => 'coding', 'title' => $title, 'use' => $use ?: $title,
        'slug' => fn_generateSlug($title), 'file' => $codePath, 'icon' => $iconPath,
        'description' => $description, 'tags' => $tags,
        'category' => $category, 'version' => $version, 'mc_version' => $mc_version,
        'is_author' => $is_author, 'is_ai' => $is_ai,
        'user_id' => $user['id'], 'author' => $user['username'],
        'likes' => 0, 'liked_by' => [], 'downloads' => 0,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents(CODING_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_response(['success' => true, 'message' => 'Сборка загружена', 'item' => $item], '/coding.php?view=detail&id=' . $id);
    break;
 
case 'upload_theme':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $title = fn_sanitize($_POST['title'] ?? '');
    $use = fn_sanitize($_POST['use'] ?? '');
    $css_code = $_POST['css_code'] ?? '';
    $description = fn_sanitize($_POST['description'] ?? '');
    $theme_type = fn_sanitize($_POST['theme_type'] ?? 'profile');
    $tags = fn_sanitize($_POST['tags'] ?? '');
    $is_author = !empty($_POST['is_author']);
    $is_ai = !empty($_POST['is_ai']);
    if (empty($title) || empty($css_code)) fn_jsonResponse(['error' => 'Заполните обязательные поля'], 400);
    if (strlen($css_code) > 50000) fn_jsonResponse(['error' => 'CSS код слишком большой'], 400);
    $previewPath = fn_handleUpload('preview_file', ['jpg','jpeg','png','gif','webp'], MAX_AVATAR_SIZE);
    if (isset($_FILES['preview_file']) && $_FILES['preview_file']['error'] !== UPLOAD_ERR_NO_FILE && $previewPath === null) {
        fn_jsonResponse(['error' => 'Ошибка загрузки превью. Проверьте формат и размер.'], 400);
    }
    if (!$previewPath && !empty($_POST['preview_url'])) $previewPath = filter_var($_POST['preview_url'], FILTER_VALIDATE_URL) ?: null;
    $id = fn_generateId();
    $item = [
        'id' => $id, 'type' => 'theme', 'title' => $title, 'use' => $use ?: $title,
        'slug' => fn_generateSlug($title), 'css_code' => $css_code, 'preview' => $previewPath,
        'description' => $description, 'tags' => $tags, 'theme_type' => $theme_type,
        'is_author' => $is_author, 'is_ai' => $is_ai,
        'user_id' => $user['id'], 'author' => $user['username'],
        'likes' => 0, 'liked_by' => [], 'applied_by' => [],
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents(THEMES_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_response(['success' => true, 'message' => 'Тема сохранена', 'item' => $item], '/themes.php?view=detail&id=' . $id);
    break;
 
// ============================================
// GET CONTENT
// ============================================
case 'get_items':
    $itemType = $_GET['type'] ?? '';
    $search = $_GET['q'] ?? $_GET['search'] ?? '';
    $sort = $_GET['sort'] ?? 'likes';
    $limit = min(100, max(1, intval($_GET['limit'] ?? 50)));
    $offset = max(0, intval($_GET['offset'] ?? 0));
    $items = fn_getItems($itemType, $search, $limit, $offset, $sort);
    fn_jsonResponse(['items' => $items, 'total' => count($items)]);
    break;
 
case 'get_item':
    $itemType = $_GET['type'] ?? '';
    $itemId = $_GET['id'] ?? '';
    if (empty($itemType) || empty($itemId)) fn_jsonResponse(['error' => 'Укажите type и id'], 400);
    $item = fn_getItemById($itemType, $itemId);
    if (!$item) fn_jsonResponse(['error' => 'Не найдено'], 404);
    $item['comments_count'] = count(fn_getItemComments($itemType, $itemId));
    fn_jsonResponse(['item' => $item]);
    break;
 
case 'get_user_items':
    $itemType = $_GET['type'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    if (empty($itemType) || empty($userId)) fn_jsonResponse(['error' => 'Укажите type и user_id'], 400);
    $items = array_values(fn_getUserItems($itemType, $userId));
    fn_jsonResponse(['items' => $items]);
    break;
 
// ============================================
// EDIT / DELETE CONTENT
// ============================================
case 'edit_item':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $item = fn_getItemById($itemType, $itemId);
    if (!$item) fn_jsonResponse(['error' => 'Не найдено'], 404);
    if ($item['user_id'] !== $user['id'] && ($user['role'] ?? '') !== 'admin') fn_jsonResponse(['error' => 'Нет прав'], 403);
    $editable = ['title', 'description', 'tags', 'category', 'version', 'mc_version', 'theme_type', 'pack_name', 'css_code'];
    foreach ($editable as $field) {
        if (isset($_POST[$field])) {
            $item[$field] = ($field === 'css_code') ? $_POST[$field] : fn_sanitize($_POST[$field]);
        }
    }
    $item['updated_at'] = date('Y-m-d H:i:s');
    $dirMap = ['music' => MUSIC_DIR, 'sticker' => STICKERS_DIR, 'coding' => CODING_DIR, 'theme' => THEMES_DIR];
    file_put_contents(($dirMap[$itemType] ?? MUSIC_DIR) . $itemId . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_jsonResponse(['success' => true, 'item' => $item]);
    break;
 
case 'delete_item':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $item = fn_getItemById($itemType, $itemId);
    if (!$item) fn_jsonResponse(['error' => 'Не найдено'], 404);
    if ($item['user_id'] !== $user['id'] && ($user['role'] ?? '') !== 'admin') fn_jsonResponse(['error' => 'Нет прав'], 403);
    $dirMap = ['music' => MUSIC_DIR, 'sticker' => STICKERS_DIR, 'coding' => CODING_DIR, 'theme' => THEMES_DIR];
    $file = ($dirMap[$itemType] ?? MUSIC_DIR) . $itemId . '.json';
    if (file_exists($file)) unlink($file);
    fn_jsonResponse(['success' => true]);
    break;
 
// ============================================
// LIKES
// ============================================
case 'like_item':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $result = fn_toggleItemLike($itemType, $itemId, $user['id']);
    if ($result === null) fn_jsonResponse(['error' => 'Не найдено'], 404);
    fn_jsonResponse($result);
    break;
 
case 'unlike_item':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $item = fn_getItemById($itemType, $itemId);
    if (!$item) fn_jsonResponse(['error' => 'Не найдено'], 404);
    if (!isset($item['liked_by'])) $item['liked_by'] = [];
    $idx = array_search($user['id'], $item['liked_by']);
    if ($idx !== false) {
        array_splice($item['liked_by'], $idx, 1);
        $item['likes'] = max(0, ($item['likes'] ?? 1) - 1);
    }
    $dirMap = ['music' => MUSIC_DIR, 'sticker' => STICKERS_DIR, 'coding' => CODING_DIR, 'theme' => THEMES_DIR];
    file_put_contents(($dirMap[$itemType] ?? '') . $itemId . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_jsonResponse(['likes' => $item['likes'], 'liked' => false]);
    break;
 
// ============================================
// COMMENTS ON ITEMS
// ============================================
case 'add_comment':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $content = trim($_POST['content'] ?? $_POST['comment_text'] ?? '');
    if (empty($content)) fn_jsonResponse(['error' => 'Пустой комментарий'], 400);
    if (strlen($content) > 2000) fn_jsonResponse(['error' => 'Комментарий слишком длинный'], 400);
    $comment = fn_addItemComment($itemType, $itemId, $user['id'], $content);
    fn_jsonResponse(['success' => true, 'comment' => $comment]);
    break;
 
case 'get_comments':
    $itemType = $_GET['type'] ?? '';
    $itemId = $_GET['id'] ?? '';
    if (empty($itemType) || empty($itemId)) fn_jsonResponse(['error' => 'Укажите type и id'], 400);
    $comments = fn_getItemComments($itemType, $itemId);
    fn_jsonResponse(['comments' => $comments, 'total' => count($comments)]);
    break;
 
case 'delete_comment':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $commentId = $_POST['comment_id'] ?? '';
    $dir = DATA_DIR . 'item_comments/' . $itemType . '/' . $itemId . '/';
    $file = $dir . $commentId . '.json';
    if (!file_exists($file)) fn_jsonResponse(['error' => 'Не найдено'], 404);
    $comment = json_decode(file_get_contents($file), true);
    if ($comment['user_id'] !== $user['id'] && ($user['role'] ?? '') !== 'admin') fn_jsonResponse(['error' => 'Нет прав'], 403);
    unlink($file);
    fn_jsonResponse(['success' => true]);
    break;
 
case 'like_comment':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $commentId = $_POST['comment_id'] ?? '';
    $dir = DATA_DIR . 'item_comments/' . $itemType . '/' . $itemId . '/';
    $file = $dir . $commentId . '.json';
    if (!file_exists($file)) fn_jsonResponse(['error' => 'Не найдено'], 404);
    $comment = json_decode(file_get_contents($file), true);
    if (!isset($comment['liked_by'])) $comment['liked_by'] = [];
    $idx = array_search($user['id'], $comment['liked_by']);
    if ($idx !== false) {
        array_splice($comment['liked_by'], $idx, 1);
        $comment['likes'] = max(0, ($comment['likes'] ?? 1) - 1);
        $liked = false;
    } else {
        $comment['liked_by'][] = $user['id'];
        $comment['likes'] = ($comment['likes'] ?? 0) + 1;
        $liked = true;
    }
    file_put_contents($file, json_encode($comment, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_jsonResponse(['likes' => $comment['likes'], 'liked' => $liked]);
    break;
 
// ============================================
// DOWNLOADS
// ============================================
case 'increment_download':
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $item = fn_getItemById($itemType, $itemId);
    if (!$item) fn_jsonResponse(['error' => 'Не найдено'], 404);
    $item['downloads'] = ($item['downloads'] ?? 0) + 1;
    $dirMap = ['music' => MUSIC_DIR, 'sticker' => STICKERS_DIR, 'coding' => CODING_DIR, 'theme' => THEMES_DIR];
    file_put_contents(($dirMap[$itemType] ?? '') . $itemId . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    fn_jsonResponse(['downloads' => $item['downloads']]);
    break;
 
// ============================================
// FAVORITES / BOOKMARKS
// ============================================
case 'toggle_favorite':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemType = $_POST['item_type'] ?? '';
    $itemId = $_POST['item_id'] ?? '';
    $added = fn_toggleFavorite($user['id'], $itemType, $itemId);
    fn_jsonResponse(['success' => true, 'added' => $added]);
    break;
 
case 'get_favorites':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $userId = $_GET['user_id'] ?? $user['id'];
    $favorites = fn_getUserFavorites($userId);
    $items = [];
    foreach ($favorites as $fav) {
        $parts = explode(':', $fav, 2);
        if (count($parts) === 2) {
            $item = fn_getItemById($parts[0], $parts[1]);
            if ($item) { $item['fav_type'] = $parts[0]; $items[] = $item; }
        }
    }
    fn_jsonResponse(['favorites' => $items]);
    break;
 
// ============================================
// DRAFTS
// ============================================
case 'save_draft':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $draftData = [
        'id' => $_POST['draft_id'] ?? null,
        'title' => fn_sanitize($_POST['title'] ?? ''),
        'content' => $_POST['content'] ?? '',
        'tags' => fn_sanitize($_POST['tags'] ?? ''),
        'type' => fn_sanitize($_POST['draft_type'] ?? 'post')
    ];
    $draft = fn_saveDraft($user['id'], $draftData);
    fn_jsonResponse(['success' => true, 'draft' => $draft]);
    break;
 
case 'get_drafts':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $drafts = fn_getUserDrafts($user['id']);
    fn_jsonResponse(['drafts' => $drafts]);
    break;
 
case 'delete_draft':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $draftId = $_POST['draft_id'] ?? '';
    if (empty($draftId)) fn_jsonResponse(['error' => 'Укажите draft_id'], 400);
    $deleted = fn_deleteDraft($user['id'], $draftId);
    fn_jsonResponse(['success' => $deleted]);
    break;
 
// ============================================
// PROFILE
// ============================================
case 'get_profile':
    $username = $_GET['username'] ?? '';
    if (empty($username)) fn_jsonResponse(['error' => 'Укажите username'], 400);
    $profileUser = fn_getUserByUsername($username);
    if (!$profileUser) fn_jsonResponse(['error' => 'Пользователь не найден'], 404);
    $settings = fn_getUserProfileSettings($profileUser['id']);
    $stats = [
        'posts' => count(glob(POSTS_DIR . $profileUser['username'] . '/*.json') ?: []),
        'music' => count(array_values(fn_getUserItems('music', $profileUser['id']))),
        'stickers' => count(array_values(fn_getUserItems('sticker', $profileUser['id']))),
        'coding' => count(array_values(fn_getUserItems('coding', $profileUser['id']))),
        'themes' => count(array_values(fn_getUserItems('theme', $profileUser['id']))),
    ];
    fn_jsonResponse([
        'user' => [
            'id' => $profileUser['id'],
            'username' => $profileUser['username'],
            'role' => $profileUser['role'] ?? '',
            'avatar' => fn_getAvatarUrl($profileUser),
            'created_at' => $profileUser['created_at'] ?? ''
        ],
        'settings' => $settings,
        'stats' => $stats
    ]);
    break;
 
case 'update_profile_settings':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $settings = fn_getUserProfileSettings($user['id']);
    $allowed = ['status', 'cover_css', 'custom_css', 'bio', 'creator_link', 'widgets', 'sticker_border', 'favorite_sticker_pack'];
    foreach ($allowed as $field) {
        if (isset($_POST[$field])) {
            $settings[$field] = ($field === 'custom_css' || $field === 'cover_css') ? $_POST[$field] : fn_sanitize($_POST[$field]);
        }
    }
    fn_saveUserProfileSettings($user['id'], $settings);
    fn_jsonResponse(['success' => true, 'settings' => $settings]);
    break;
 
case 'get_user_stats':
    $userId = $_GET['user_id'] ?? ($user ? $user['id'] : '');
    if (empty($userId)) fn_jsonResponse(['error' => 'Укажите user_id'], 400);
    $u = fn_getUserById($userId);
    if (!$u) fn_jsonResponse(['error' => 'Не найден'], 404);
 
    $postFiles = glob(POSTS_DIR . $u['username'] . '/*.json') ?: [];
    $totalLikes = 0;
    foreach ($postFiles as $pf) {
        $p = json_decode(file_get_contents($pf), true);
        if ($p) $totalLikes += ($p['likes'] ?? 0);
    }
    foreach (['music', 'sticker', 'coding', 'theme'] as $t) {
        foreach (fn_getUserItems($t, $userId) as $item) {
            $totalLikes += ($item['likes'] ?? 0);
        }
    }
    fn_jsonResponse([
        'user_id' => $userId,
        'username' => $u['username'],
        'posts' => count($postFiles),
        'music' => count(array_values(fn_getUserItems('music', $userId))),
        'stickers' => count(array_values(fn_getUserItems('sticker', $userId))),
        'coding' => count(array_values(fn_getUserItems('coding', $userId))),
        'themes' => count(array_values(fn_getUserItems('theme', $userId))),
        'total_likes' => $totalLikes,
        'favorites' => count(fn_getUserFavorites($userId)),
    ]);
    break;
 
// ============================================
// NOTIFICATIONS
// ============================================
case 'get_notifications':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $unreadOnly = !empty($_GET['unread']);
    $limit = min(100, max(1, intval($_GET['limit'] ?? 100)));
    $notifications = fn_getUserNotifications($user['id'], $unreadOnly, $limit);
    fn_jsonResponse(['notifications' => $notifications, 'total' => count($notifications)]);
    break;
 
case 'mark_read':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $notifId = $_POST['notification_id'] ?? '';
    if (empty($notifId)) fn_jsonResponse(['error' => 'Укажите notification_id'], 400);
    $file = NOTIFICATIONS_DIR . $user['id'] . '/' . $notifId . '.json';
    if (file_exists($file)) {
        $n = json_decode(file_get_contents($file), true);
        if ($n) { $n['read'] = true; file_put_contents($file, json_encode($n, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); }
    }
    fn_jsonResponse(['success' => true]);
    break;
 
case 'mark_all_read':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $dir = NOTIFICATIONS_DIR . $user['id'] . '/';
    if (file_exists($dir)) {
        foreach (glob($dir . '*.json') ?: [] as $f) {
            $n = json_decode(file_get_contents($f), true);
            if ($n) { $n['read'] = true; file_put_contents($f, json_encode($n, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)); }
        }
    }
    fn_jsonResponse(['success' => true]);
    break;
 
case 'get_unread_count':
    if (!$user) fn_jsonResponse(['count' => 0]);
    fn_jsonResponse(['count' => fn_getUnreadCount($user['id'])]);
    break;
 
// ============================================
// SEARCH
// ============================================
case 'search':
    $query = $_GET['q'] ?? $_POST['query'] ?? '';
    $type = $_GET['search_type'] ?? $_POST['type'] ?? 'all';
    $results = ['users' => [], 'music' => [], 'stickers' => [], 'coding' => [], 'themes' => []];
 
    if ($type === 'all' || $type === 'users') {
        $users = fn_getUsers();
        foreach ($users as $u) {
            if (mb_stripos($u['username'], $query) !== false) {
                $results['users'][] = [
                    'username' => $u['username'],
                    'avatar' => fn_getAvatarUrl($u),
                    'role' => $u['role'] ?? ''
                ];
            }
        }
    }
    foreach (['music', 'sticker', 'coding', 'theme'] as $t) {
        $key = $t === 'sticker' ? 'stickers' : ($t === 'theme' ? 'themes' : $t);
        if ($type === 'all' || $type === $t || $type === $key) {
            $results[$key] = fn_getItems($t, $query, 20);
        }
    }
    fn_jsonResponse($results);
    break;
 
// ============================================
// TRENDING
// ============================================
case 'get_trending':
    $trending = [];
    foreach (['music', 'sticker', 'coding', 'theme'] as $t) {
        $items = fn_getItems($t, '', 5, 0, 'likes');
        foreach ($items as &$item) { $item['content_type'] = $t; }
        $trending = array_merge($trending, $items);
    }
    usort($trending, function($a, $b) { return ($b['likes'] ?? 0) - ($a['likes'] ?? 0); });
    fn_jsonResponse(['trending' => array_slice($trending, 0, 20)]);
    break;
 
// ============================================
// POSTS (for existing post system)
// ============================================
case 'like_post':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $slug = $_POST['slug'] ?? '';
    if (empty($slug)) fn_jsonResponse(['error' => 'Укажите slug'], 400);
    // Use index.php's function through include or manual implementation
    $postDirs = glob(POSTS_DIR . '*', GLOB_ONLYDIR) ?: [];
    foreach ($postDirs as $dir) {
        $file = $dir . '/' . $slug . '.json';
        if (file_exists($file)) {
            $post = json_decode(file_get_contents($file), true);
            if (!$post) continue;
            $likesFile = $dir . '/' . $slug . '_likes.json';
            $likes = file_exists($likesFile) ? (json_decode(file_get_contents($likesFile), true) ?: []) : [];
            $idx = array_search($user['id'], $likes);
            if ($idx !== false) {
                array_splice($likes, $idx, 1);
                $liked = false;
            } else {
                $likes[] = $user['id'];
                $liked = true;
                if (($post['user_id'] ?? '') !== $user['id']) {
                    fn_addNotification($post['user_id'], 'like', [
                        'from_user_id' => $user['id'],
                        'from_username' => $user['username'],
                        'post_slug' => $slug,
                        'post_title' => $post['title'] ?? ''
                    ]);
                }
            }
            file_put_contents($likesFile, json_encode(array_values($likes)));
            $post['likes'] = count($likes);
            file_put_contents($file, json_encode($post, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            fn_jsonResponse(['likes' => count($likes), 'liked' => $liked]);
        }
    }
    fn_jsonResponse(['error' => 'Пост не найден'], 404);
    break;
 
case 'get_post_comments':
    $slug = $_GET['slug'] ?? '';
    if (empty($slug)) fn_jsonResponse(['error' => 'Укажите slug'], 400);
    $dir = COMMENTS_DIR . $slug . '/';
    $comments = [];
    if (file_exists($dir)) {
        foreach (glob($dir . '*.json') ?: [] as $f) {
            $c = json_decode(file_get_contents($f), true);
            if ($c) {
                $u = fn_getUserById($c['user_id']);
                if ($u) { $c['username'] = $u['username']; $c['avatar'] = fn_getAvatarUrl($u); }
                $comments[] = $c;
            }
        }
    }
    usort($comments, function($a, $b) { return strtotime($a['created_at'] ?? '0') - strtotime($b['created_at'] ?? '0'); });
    fn_jsonResponse(['comments' => $comments, 'total' => count($comments)]);
    break;
 
case 'add_post_comment':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $slug = $_POST['slug'] ?? '';
    $content = trim($_POST['content'] ?? '');
    $parentId = $_POST['parent_id'] ?? null;
    if (empty($slug) || empty($content)) fn_jsonResponse(['error' => 'Укажите slug и content'], 400);
    if (strlen($content) > 2000) fn_jsonResponse(['error' => 'Слишком длинный комментарий'], 400);
    $dir = COMMENTS_DIR . $slug . '/';
    if (!file_exists($dir)) @mkdir($dir, 0755, true);
    $id = uniqid();
    $comment = [
        'id' => $id, 'post_slug' => $slug, 'user_id' => $user['id'],
        'content' => $content, 'parent_id' => $parentId,
        'reactions' => [], 'created_at' => date('Y-m-d H:i:s')
    ];
    file_put_contents($dir . $id . '.json', json_encode($comment, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    // Notify post author
    $postDirs = glob(POSTS_DIR . '*', GLOB_ONLYDIR) ?: [];
    foreach ($postDirs as $pdir) {
        $pf = $pdir . '/' . $slug . '.json';
        if (file_exists($pf)) {
            $post = json_decode(file_get_contents($pf), true);
            if ($post && ($post['user_id'] ?? '') !== $user['id']) {
                fn_addNotification($post['user_id'], 'comment', [
                    'from_user_id' => $user['id'],
                    'from_username' => $user['username'],
                    'post_slug' => $slug,
                    'post_title' => $post['title'] ?? '',
                    'content' => mb_substr($content, 0, 100)
                ]);
            }
            break;
        }
    }
    $comment['username'] = $user['username'];
    $comment['avatar'] = fn_getAvatarUrl($user);
    fn_jsonResponse(['success' => true, 'comment' => $comment]);
    break;
 
// ============================================
// APPLY THEME
// ============================================
case 'apply_theme':
    if (!$user) fn_jsonResponse(['error' => 'Требуется авторизация'], 401);
    $itemId = $_POST['item_id'] ?? '';
    $item = fn_getItemById('theme', $itemId);
    if (!$item || empty($item['css_code'])) fn_jsonResponse(['error' => 'Тема не найдена'], 404);
    $settings = fn_getUserProfileSettings($user['id']);
    $settings['custom_css'] = $item['css_code'];
    $settings['applied_theme'] = $item['title'];
    $settings['applied_theme_id'] = $item['id'];
    fn_saveUserProfileSettings($user['id'], $settings);
    if (!isset($item['applied_by'])) $item['applied_by'] = [];
    if (!in_array($user['id'], $item['applied_by'])) {
        $item['applied_by'][] = $user['id'];
        file_put_contents(THEMES_DIR . $itemId . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    fn_jsonResponse(['success' => true]);
    break;
 
// ============================================
// MISC
// ============================================
case 'ping':
    fn_jsonResponse(['status' => 'ok', 'time' => date('Y-m-d H:i:s'), 'user' => $user ? $user['username'] : null]);
    break;
 
default:
    fn_jsonResponse(['error' => 'Неизвестное действие: ' . $action, 'available_actions' => [
        'upload_music', 'upload_sticker', 'upload_coding', 'upload_theme',
        'get_items', 'get_item', 'get_user_items',
        'edit_item', 'delete_item',
        'like_item', 'unlike_item',
        'add_comment', 'get_comments', 'delete_comment', 'like_comment',
        'increment_download',
        'toggle_favorite', 'get_favorites',
        'save_draft', 'get_drafts', 'delete_draft',
        'get_profile', 'update_profile_settings', 'get_user_stats',
        'get_notifications', 'mark_read', 'mark_all_read', 'get_unread_count',
        'search', 'get_trending',
        'like_post', 'get_post_comments', 'add_post_comment',
        'apply_theme', 'ping'
    ]], 400);
}