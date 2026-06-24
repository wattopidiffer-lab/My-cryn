<?php
session_start();

// Подключаем общие функции (fn_*) - константы定义там
require_once __DIR__ . '/functions.php';

// Включение логирования ошибок
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// ============================================
// ИНИЦИАЛИЗАЦИЯ
// ============================================

// ============================================
// ФУНКЦИИ РАБОТЫ С ПОЛЬЗОВАТЕЛЯМИ
//=============================================
function getUsers() {
    if (!file_exists(USERS_FILE)) {
        error_log("getUsers: файл users.json не существует");
        return [];
    }
    $content = file_get_contents(USERS_FILE);
    if ($content === false) {
        error_log("getUsers: не удалось прочитать users.json");
        return [];
    }
    $users = json_decode($content, true);
    if ($users === null) {
        error_log("getUsers: ошибка декодирования JSON");
        return [];
    }
    return $users;
}

function saveUsers($users) {
    if (file_put_contents(USERS_FILE, json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        error_log("saveUsers: не удалось записать users.json");
        return false;
    }
    return true;
}

function getUserByUsername($username) {
    $users = getUsers();
    foreach ($users as $user) {
        if (strtolower($user['username']) === strtolower($username)) {
            return $user;
        }
    }
    return null;
}

function getRoleStyle($role) {
    $styles = [
        'content_creator' => [
            'badge' => '<span class="role-badge creator"><i class="fas fa-video"></i> Создатель контента</span>',
            'color' => '#3b82f6',
            'border' => '3px solid #3b82f6',
            'gradient' => 'linear-gradient(135deg, #3b82f6, #2563eb)'
        ],
        'tech_admin' => [
            'badge' => '<span class="role-badge tech"><i class="fas fa-cog"></i> Тех админ</span>',
            'color' => '#8b5cf6',
            'border' => '3px solid #8b5cf6',
            'gradient' => 'linear-gradient(135deg, #8b5cf6, #7c3aed)'
        ],
        'legendary' => [
            'badge' => '<span class="role-badge legendary"><i class="fas fa-star"></i> Легендарный игрок</span>',
            'color' => '#ec4899',
            'border' => '3px solid #ec4899',
            'gradient' => 'linear-gradient(135deg, #ec4899, #db2777)'
        ],
        'mythic' => [
            'badge' => '<span class="role-badge mythic"><i class="fas fa-dragon"></i> Мифический игрок</span>',
            'color' => '#14b8a6',
            'border' => '3px solid #14b8a6',
            'gradient' => 'linear-gradient(135deg, #14b8a6, #0d9488)'
        ],
        'golden' => [
            'badge' => '<span class="role-badge golden"><i class="fas fa-medal"></i> Золотой игрок</span>',
            'color' => '#fbbf24',
            'border' => '3px solid #fbbf24',
            'gradient' => 'linear-gradient(135deg, #fbbf24, #f59e0b)'
        ]
    ];

    return $styles[$role] ?? [
        'badge' => '',
        'color' => '#10b981',
        'border' => '3px solid #10b981',
        'gradient' => 'linear-gradient(135deg, #10b981, #059669)'
    ];
}

// ============================================
// ФУНКЦИИ ДЛЯ АВАТАРОК
//=============================================
function uploadAvatar($userId, $file) {
    $user = fn_getUserById($userId);
    if (!$user) {
        error_log("uploadAvatar: пользователь $userId не найден");
        return false;
    }
    
    $avatarDir = AVATARS_DIR . $user['username'] . '/';
    if (!file_exists($avatarDir)) {
        if (!mkdir($avatarDir, 0755, true)) {
            error_log("uploadAvatar: не удалось создать директорию $avatarDir");
            return false;
        }
    }
    
    if (!empty($user['avatar']) && file_exists($user['avatar'])) {
        if (!unlink($user['avatar'])) {
            error_log("uploadAvatar: не удалось удалить старый аватар {$user['avatar']}");
        }
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'avatar_' . uniqid() . '.' . $ext;
    $filepath = $avatarDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Убираем VP/ из пути - сохраняем только avatars/username/...
        $relativePath = 'avatars/' . $user['username'] . '/' . $filename;
        
        $users = getUsers();
        $updated = false;
        foreach ($users as &$u) {
            if ($u['id'] === $userId) {
                $u['avatar'] = $relativePath;
                $updated = true;
                break;
            }
        }
        
        if ($updated && saveUsers($users)) {
            return $relativePath;
        } else {
            error_log("uploadAvatar: не удалось обновить users.json");
            return false;
        }
    }
    
    error_log("uploadAvatar: ошибка при перемещении файла");
    return false;
}

function getAvatarUrl($user) {
    if (!empty($user['avatar'])) {
        // Добавляем VP/ к пути для проверки
        $fullPath = __DIR__ . '/VP/' . $user['avatar'];
        if (file_exists($fullPath)) {
            return '/VP/' . ltrim($user['avatar'], '/');
        }
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($user['username']) . '&background=10b981&color=fff&size=128';
}

// ============================================
// ФУНКЦИИ ДЛЯ ПОСТОВ
//=============================================
function generateSlug($title) {
    $slug = preg_replace('/[^a-z0-9]+/', '-', strtolower($title));
    $slug = trim($slug, '-');
    return $slug ?: 'post-' . uniqid();
}

function getAllPosts() {
    $allPosts = [];
    $userFolders = glob(POSTS_DIR . '*', GLOB_ONLYDIR);
    
    if ($userFolders === false) {
        error_log("getAllPosts: ошибка при поиске папок пользователей");
        return [];
    }
    
    foreach ($userFolders as $folder) {
        $jsonFiles = glob($folder . '/*.json');
        if ($jsonFiles === false) continue;
        
        foreach ($jsonFiles as $file) {
            if (basename($file) !== 'settings.json' && !strpos($file, '_likes.json')) {
                $content = file_get_contents($file);
                if ($content === false) {
                    error_log("getAllPosts: не удалось прочитать $file");
                    continue;
                }
                $post = json_decode($content, true);
                if ($post) {
                    $user = getUserByUsername($post['username']);
                    if ($user) {
                        $post['avatar'] = getAvatarUrl($user);
                    }
                    $post['comments_count'] = count(getPostComments($post['slug']));
                    $allPosts[] = $post;
                } else {
                    error_log("getAllPosts: ошибка декодирования JSON в $file");
                }
            }
        }
    }
    
    usort($allPosts, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $allPosts;
}

function getUserPosts($userId) {
    $user = fn_getUserById($userId);
    if (!$user) {
        error_log("getUserPosts: пользователь $userId не найден");
        return [];
    }
    
    $userDir = POSTS_DIR . $user['username'] . '/';
    if (!file_exists($userDir)) return [];
    
    $posts = [];
    $jsonFiles = glob($userDir . '*.json');
    
    if ($jsonFiles === false) return [];
    
    foreach ($jsonFiles as $file) {
        if (basename($file) !== 'settings.json' && !strpos($file, '_likes.json')) {
            $content = file_get_contents($file);
            if ($content === false) {
                error_log("getUserPosts: не удалось прочитать $file");
                continue;
            }
            $post = json_decode($content, true);
            if ($post) {
                $post['avatar'] = getAvatarUrl($user);
                $post['comments_count'] = count(getPostComments($post['slug']));
                $posts[] = $post;
            }
        }
    }
    
    usort($posts, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $posts;
}

function getPostBySlug($slug) {
    $userFolders = glob(POSTS_DIR . '*', GLOB_ONLYDIR);
    
    if ($userFolders === false) {
        error_log("getPostBySlug: ошибка при поиске папок");
        return null;
    }
    
    foreach ($userFolders as $folder) {
        $jsonFile = $folder . '/' . $slug . '.json';
        if (file_exists($jsonFile)) {
            $content = file_get_contents($jsonFile);
            if ($content === false) {
                error_log("getPostBySlug: не удалось прочитать $jsonFile");
                continue;
            }
            $post = json_decode($content, true);
            if (!$post) {
                error_log("getPostBySlug: ошибка JSON в $jsonFile");
                continue;
            }
            if (!isset($post['username'])) {
                error_log("getPostBySlug: пост без username в $jsonFile");
                continue;
            }
            $user = getUserByUsername($post['username']);
            if ($user) {
                $post['avatar'] = getAvatarUrl($user);
            }
            $post['comments_count'] = count(getPostComments($slug));
            return $post;
        }
    }
    
    error_log("getPostBySlug: пост с slug '$slug' не найден");
    return null;
}

function savePost($post) {
    $userDir = POSTS_DIR . $post['username'] . '/';
    if (!file_exists($userDir)) {
        if (!mkdir($userDir, 0755, true)) {
            error_log("savePost: не удалось создать директорию $userDir");
            return false;
        }
    }
    
    $jsonFile = $userDir . $post['slug'] . '.json';
    
    if (file_exists($jsonFile)) {
        $content = file_get_contents($jsonFile);
        if ($content !== false) {
            $oldPost = json_decode($content, true);
            if ($oldPost) {
                $post['created_at'] = $oldPost['created_at'];
                $post['views'] = $oldPost['views'] ?? 0;
                $post['likes'] = $oldPost['likes'] ?? 0;
            }
        }
    }
    
    $post['updated_at'] = date('Y-m-d H:i:s');
    
    if (file_put_contents($jsonFile, json_encode($post, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        error_log("savePost: не удалось записать JSON файл $jsonFile");
        return false;
    }
    
    $htmlFile = $userDir . $post['slug'] . '.html';
    $roleStyle = getRoleStyle($post['role'] ?? '');
    $sanitizedCss = str_replace(['</style>', '<script', 'javascript:', 'expression('], '', $post['custom_css'] ?? '');
    
    $htmlContent = <<<HTML
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$post['title']} | My Cryndel</title>
    <meta name="description" content="{$post['meta_description']}">
    <meta name="keywords" content="{$post['meta_keywords']}">
    <meta property="og:title" content="{$post['title']}">
    <meta property="og:description" content="{$post['meta_description']}">
    <meta property="og:image" content="https://hi.cryndel.ru{$post['avatar']}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', sans-serif;
            background: #f3f4f6;
            color: #111827;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 0 20px;
        }
        .post-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            overflow: hidden;
            border-top: {$roleStyle['border']};
        }
        .post-header {
            padding: 30px;
            border-bottom: 1px solid #e5e7eb;
        }
        .post-meta {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }
        .post-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid {$roleStyle['color']};
        }
        .post-author {
            flex: 1;
        }
        .post-author-name {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
        }
        .role-badge.admin { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .role-badge.creator { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .role-badge.tech { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .role-badge.legendary { background: linear-gradient(135deg, #ec4899, #db2777); }
        .role-badge.mythic { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .role-badge.golden { background: linear-gradient(135deg, #fbbf24, #f59e0b); }
        .post-date {
            font-size: 14px;
            color: #6b7280;
        }
        .post-title {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 20px;
            color: #111827;
        }
        .post-content {
            padding: 30px;
            font-size: 16px;
        }
        .post-content img {
            max-width: 100%;
            border-radius: 16px;
            margin: 20px 0;
        }
        .post-footer {
            padding: 20px 30px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tag {
            background: #f3f4f6;
            color: #6b7280;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            text-decoration: none;
        }
        .post-stats {
            display: flex;
            gap: 20px;
            color: #6b7280;
        }
        .stat {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        @media (max-width: 768px) {
            .container { padding: 0 15px; }
            .post-title { font-size: 24px; }
            .post-header { padding: 20px; }
            .post-content { padding: 20px; }
        }
        {$sanitizedCss}
    </style>
</head>
<body>
    <div class="container">
        <article class="post-card">
            <header class="post-header">
                <div class="post-meta">
                    <img src="https://hi.cryndel.ru{$post['avatar']}" alt="" class="post-avatar">
                    <div class="post-author">
                        <div class="post-author-name">
                            @{$post['username']}
                            {$roleStyle['badge']}
                        </div>
                        <div class="post-date">{$post['created_at']}</div>
                    </div>
                </div>
                <h1 class="post-title">{$post['title']}</h1>
            </header>
            
            <div class="post-content">
                {$post['content']}
            </div>
            
            <footer class="post-footer">
                <div class="post-tags">
                    {$post['tags_html']}
                </div>
                <div class="post-stats">
                    <span class="stat"><i class="far fa-eye"></i> {$post['views']}</span>
                    <span class="stat"><i class="far fa-heart"></i> {$post['likes']}</span>
                </div>
            </footer>
        </article>
    </div>
</body>
</html>
HTML;
    
    if (file_put_contents($htmlFile, $htmlContent) === false) {
        error_log("savePost: не удалось записать HTML файл $htmlFile");
        return false;
    }
    
    return true;
}

// 🔴 КРИТИЧЕСКАЯ ИСПРАВЛЕННАЯ ФУНКЦИЯ УДАЛЕНИЯ ПОСТА
function deletePost($slug) {
    $post = getPostBySlug($slug);
    if (!$post) {
        error_log("deletePost: пост с slug '$slug' не найден");
        return false;
    }
    
    $username = $post['username'];
    $userDir = POSTS_DIR . $username . '/';
    $commentsDir = COMMENTS_DIR . $slug . '/';
    
    $deleted = true;
    
    // Удаляем JSON файл поста
    $jsonFile = $userDir . $slug . '.json';
    if (file_exists($jsonFile)) {
        if (!unlink($jsonFile)) {
            error_log("deletePost: не удалось удалить JSON файл: $jsonFile");
            $deleted = false;
        }
    }
    
    // Удаляем HTML файл поста
    $htmlFile = $userDir . $slug . '.html';
    if (file_exists($htmlFile)) {
        if (!unlink($htmlFile)) {
            error_log("deletePost: не удалось удалить HTML файл: $htmlFile");
            $deleted = false;
        }
    }
    
    // Удаляем файл с лайками
    $likesFile = $userDir . $slug . '_likes.json';
    if (file_exists($likesFile)) {
        if (!unlink($likesFile)) {
            error_log("deletePost: не удалось удалить файл лайков: $likesFile");
            $deleted = false;
        }
    }
    
    // Удаляем папку с комментариями и все файлы в ней
    if (file_exists($commentsDir)) {
        $commentFiles = glob($commentsDir . '*.json');
        if ($commentFiles !== false) {
            foreach ($commentFiles as $file) {
                if (!unlink($file)) {
                    error_log("deletePost: не удалось удалить файл комментария: $file");
                    $deleted = false;
                }
            }
        }
        if (!rmdir($commentsDir)) {
            error_log("deletePost: не удалось удалить папку комментариев: $commentsDir");
            $deleted = false;
        }
    }
    
    return $deleted;
}

function incrementPostViews($slug) {
    if (isset($_SESSION['viewed_posts'][$slug])) {
        return;
    }
    
    $post = getPostBySlug($slug);
    if (!$post) {
        error_log("incrementPostViews: пост $slug не найден");
        return;
    }
    
    $jsonFile = POSTS_DIR . $post['username'] . '/' . $slug . '.json';
    if (!file_exists($jsonFile)) {
        error_log("incrementPostViews: файл $jsonFile не существует");
        return;
    }
    
    $content = file_get_contents($jsonFile);
    if ($content === false) {
        error_log("incrementPostViews: не удалось прочитать $jsonFile");
        return;
    }
    
    $postData = json_decode($content, true);
    if (!$postData) {
        error_log("incrementPostViews: ошибка декодирования JSON");
        return;
    }
    
    $postData['views'] = ($postData['views'] ?? 0) + 1;
    
    if (file_put_contents($jsonFile, json_encode($postData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        error_log("incrementPostViews: не удалось записать $jsonFile");
        return;
    }
    
    $_SESSION['viewed_posts'][$slug] = true;
}

function togglePostLike($slug, $userId) {
    $post = getPostBySlug($slug);
    if (!$post) {
        error_log("togglePostLike: пост $slug не найден");
        return ['likes' => 0, 'hasLiked' => false];
    }
    
    $userDir = POSTS_DIR . $post['username'] . '/';
    if (!file_exists($userDir)) {
        if (!mkdir($userDir, 0755, true)) {
            error_log("togglePostLike: не удалось создать папку $userDir");
            return ['likes' => $post['likes'] ?? 0, 'hasLiked' => false];
        }
    }
    
    $likesFile = $userDir . $slug . '_likes.json';
    $jsonFile = $userDir . $slug . '.json';
    
    $likes = [];
    if (file_exists($likesFile)) {
        $content = file_get_contents($likesFile);
        if ($content !== false) {
            $likes = json_decode($content, true) ?: [];
        }
    }
    
    $key = array_search($userId, $likes);
    if ($key !== false) {
        unset($likes[$key]);
        $hasLiked = false;
    } else {
        $likes[] = $userId;
        $hasLiked = true;
    }
    
    if (file_put_contents($likesFile, json_encode(array_values($likes), JSON_PRETTY_PRINT)) === false) {
        error_log("togglePostLike: не удалось записать $likesFile");
    }
    
    $postContent = file_get_contents($jsonFile);
    if ($postContent !== false) {
        $postData = json_decode($postContent, true);
        if ($postData) {
            $postData['likes'] = count($likes);
            $postData['updated_at'] = date('Y-m-d H:i:s');
            if (file_put_contents($jsonFile, json_encode($postData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
                error_log("togglePostLike: не удалось записать $jsonFile");
            }
        }
    }
    
    // Send notification on like
    if ($hasLiked && $post['user_id'] !== $userId) {
        $likerUser = fn_getUserById($userId);
        addNotification($post['user_id'], 'like', [
            'from_user_id' => $userId,
            'from_username' => $likerUser ? $likerUser['username'] : '',
            'post_slug' => $slug,
            'post_title' => $post['title'] ?? ''
        ]);
    }

    return ['likes' => count($likes), 'hasLiked' => $hasLiked];
}

function hasUserLikedPost($slug, $userId) {
    $post = getPostBySlug($slug);
    if (!$post) return false;
    
    $likesFile = POSTS_DIR . $post['username'] . '/' . $slug . '_likes.json';
    if (!file_exists($likesFile)) return false;
    
    $content = file_get_contents($likesFile);
    if ($content === false) return false;
    
    $likes = json_decode($content, true) ?: [];
    return in_array($userId, $likes);
}

// ============================================
// ФУНКЦИИ ДЛЯ КОММЕНТАРИЕВ (С РЕАКЦИЯМИ И ОТВЕТАМИ)
//=============================================
function getPostComments($slug) {
    $commentsDir = COMMENTS_DIR . $slug . '/';
    if (!file_exists($commentsDir)) return [];
    
    $comments = [];
    $commentFiles = glob($commentsDir . '*.json');
    
    if ($commentFiles === false) return [];
    
    foreach ($commentFiles as $file) {
        $content = file_get_contents($file);
        if ($content === false) {
            error_log("getPostComments: не удалось прочитать $file");
            continue;
        }
        $comment = json_decode($content, true);
        if ($comment) {
            $user = fn_getUserById($comment['user_id']);
            if ($user) {
                $comment['avatar'] = getAvatarUrl($user);
                $comment['username'] = $user['username'];
            }
            $comments[] = $comment;
        }
    }
    
    usort($comments, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    return $comments;
}

function addComment($slug, $userId, $content, $parentId = null) {
    $post = getPostBySlug($slug);
    if (!$post) {
        error_log("addComment: пост $slug не найден");
        return false;
    }
    
    $commentsDir = COMMENTS_DIR . $slug . '/';
    if (!file_exists($commentsDir)) {
        if (!mkdir($commentsDir, 0755, true)) {
            error_log("addComment: не удалось создать папку $commentsDir");
            return false;
        }
    }
    
    $commentId = uniqid();
    $comment = [
        'id' => $commentId,
        'post_slug' => $slug,
        'user_id' => $userId,
        'content' => trim($content),
        'parent_id' => $parentId,
        'reactions' => [],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $commentFile = $commentsDir . $commentId . '.json';
    if (file_put_contents($commentFile, json_encode($comment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        error_log("addComment: не удалось записать $commentFile");
        return false;
    }
    
    // Send comment notification to post author
    if ($post['user_id'] !== $userId) {
        $commenterUser = fn_getUserById($userId);
        addNotification($post['user_id'], 'comment', [
            'from_user_id' => $userId,
            'from_username' => $commenterUser ? $commenterUser['username'] : '',
            'post_slug' => $slug,
            'post_title' => $post['title'] ?? '',
            'comment_id' => $commentId,
            'content' => mb_substr($content, 0, 100)
        ]);
    }

    // Обработка упоминаний (@username)
    preg_match_all('/@([a-zA-Z0-9_]+)/', $content, $matches);
    if (!empty($matches[1])) {
        foreach ($matches[1] as $mentionedUsername) {
            $mentionedUser = getUserByUsername($mentionedUsername);
            if ($mentionedUser && $mentionedUser['id'] != $userId) {
                addNotification($mentionedUser['id'], 'mention', [
                    'from_user_id' => $userId,
                    'from_username' => fn_getUserById($userId) ? fn_getUserById($userId)['username'] : '',
                    'post_slug' => $slug,
                    'comment_id' => $commentId,
                    'content' => mb_substr($content, 0, 100)
                ]);
            }
        }
    }
    
    return $comment;
}

function deleteComment($commentId, $slug, $userId) {
    $user = fn_getUserById($userId);
    if (!$user) return false;
    
    $commentFile = COMMENTS_DIR . $slug . '/' . $commentId . '.json';
    if (!file_exists($commentFile)) return false;
    
    $content = file_get_contents($commentFile);
    if ($content === false) return false;
    
    $comment = json_decode($content, true);
    if (!$comment) return false;
    
    if ($comment['user_id'] !== $userId && $user['role'] !== 'admin') {
        return false;
    }
    
    // Удаляем все ответы на этот комментарий
    $commentsDir = COMMENTS_DIR . $slug . '/';
    $allComments = glob($commentsDir . '*.json');
    if ($allComments !== false) {
        foreach ($allComments as $file) {
            $cContent = file_get_contents($file);
            if ($cContent !== false) {
                $c = json_decode($cContent, true);
                if ($c && isset($c['parent_id']) && $c['parent_id'] === $commentId) {
                    unlink($file);
                }
            }
        }
    }
    
    return unlink($commentFile);
}

function toggleCommentReaction($commentId, $slug, $userId, $reaction = '❤️') {
    $commentFile = COMMENTS_DIR . $slug . '/' . $commentId . '.json';
    if (!file_exists($commentFile)) return false;
    
    $content = file_get_contents($commentFile);
    if ($content === false) return false;
    
    $comment = json_decode($content, true);
    if (!$comment) return false;
    
    if (!isset($comment['reactions'])) {
        $comment['reactions'] = [];
    }
    
    if (!isset($comment['reactions'][$reaction])) {
        $comment['reactions'][$reaction] = [];
    }
    
    $key = array_search($userId, $comment['reactions'][$reaction]);
    if ($key !== false) {
        array_splice($comment['reactions'][$reaction], $key, 1);
    } else {
        $comment['reactions'][$reaction][] = $userId;
    }
    
    if (empty($comment['reactions'][$reaction])) {
        unset($comment['reactions'][$reaction]);
    }
    
    return file_put_contents($commentFile, json_encode($comment, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function buildCommentTree($comments, $parentId = null) {
    $tree = [];
    foreach ($comments as $comment) {
        if (($comment['parent_id'] ?? null) === $parentId) {
            $comment['replies'] = buildCommentTree($comments, $comment['id']);
            $tree[] = $comment;
        }
    }
    return $tree;
}

// ============================================
// ФУНКЦИИ ДЛЯ УВЕДОМЛЕНИЙ
//=============================================
function addNotification($userId, $type, $data) {
    $notificationsDir = NOTIFICATIONS_DIR . $userId . '/';
    if (!file_exists($notificationsDir)) {
        if (!mkdir($notificationsDir, 0755, true)) {
            error_log("addNotification: не удалось создать папку $notificationsDir");
            return false;
        }
    }
    
    $notificationId = uniqid();
    $notification = [
        'id' => $notificationId,
        'user_id' => $userId,
        'type' => $type,
        'data' => $data,
        'read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $notificationFile = $notificationsDir . $notificationId . '.json';
    return file_put_contents($notificationFile, json_encode($notification, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function getUserNotifications($userId, $unreadOnly = false) {
    $notificationsDir = NOTIFICATIONS_DIR . $userId . '/';
    if (!file_exists($notificationsDir)) return [];
    
    $notifications = [];
    $files = glob($notificationsDir . '*.json');
    
    if ($files === false) return [];
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content === false) continue;
        
        $notification = json_decode($content, true);
        if ($notification) {
            if ($unreadOnly && $notification['read']) continue;
            $notifications[] = $notification;
        }
    }
    
    usort($notifications, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    return $notifications;
}

function markNotificationAsRead($notificationId, $userId) {
    $notificationFile = NOTIFICATIONS_DIR . $userId . '/' . $notificationId . '.json';
    if (!file_exists($notificationFile)) return false;
    
    $content = file_get_contents($notificationFile);
    if ($content === false) return false;
    
    $notification = json_decode($content, true);
    if (!$notification) return false;
    
    $notification['read'] = true;
    
    return file_put_contents($notificationFile, json_encode($notification, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
}

function markAllNotificationsAsRead($userId) {
    $notificationsDir = NOTIFICATIONS_DIR . $userId . '/';
    if (!file_exists($notificationsDir)) return false;
    
    $files = glob($notificationsDir . '*.json');
    if ($files === false) return false;
    
    $updated = true;
    foreach ($files as $file) {
        $content = file_get_contents($file);
        if ($content === false) continue;
        
        $notification = json_decode($content, true);
        if ($notification) {
            $notification['read'] = true;
            if (file_put_contents($file, json_encode($notification, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
                $updated = false;
            }
        }
    }
    
    return $updated;
}

// ============================================
// ФУНКЦИИ ДЛЯ НАСТРОЕК ПРОФИЛЯ
//=============================================
function getUserProfileSettings($userId) {
    $user = fn_getUserById($userId);
    if (!$user) {
        error_log("getUserProfileSettings: пользователь $userId не найден");
        return null;
    }
    
    $settingsFile = PROFILES_DIR . $user['username'] . '/settings.json';
    
    if (file_exists($settingsFile)) {
        $content = file_get_contents($settingsFile);
        if ($content === false) {
            error_log("getUserProfileSettings: не удалось прочитать $settingsFile");
            return ['custom_css' => '', 'medals' => []];
        }
        $settings = json_decode($content, true);
        if ($settings === null) {
            error_log("getUserProfileSettings: ошибка декодирования JSON");
            return ['custom_css' => '', 'medals' => []];
        }
        return $settings;
    }
    
    return ['custom_css' => '', 'medals' => []];
}

function saveUserProfileSettings($userId, $settings) {
    $user = fn_getUserById($userId);
    if (!$user) {
        error_log("saveUserProfileSettings: пользователь $userId не найден");
        return false;
    }
    
    $profileDir = PROFILES_DIR . $user['username'] . '/';
    if (!file_exists($profileDir)) {
        if (!mkdir($profileDir, 0755, true)) {
            error_log("saveUserProfileSettings: не удалось создать $profileDir");
            return false;
        }
    }
    
    $settingsFile = $profileDir . 'settings.json';
    if (file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
        error_log("saveUserProfileSettings: не удалось записать $settingsFile");
        return false;
    }
    
    if (!empty($settings['custom_css'])) {
        $cssFile = $profileDir . 'custom.css';
        if (file_put_contents($cssFile, $settings['custom_css']) === false) {
            error_log("saveUserProfileSettings: не удалось записать CSS");
        }
    }
    
    return true;
}

function addUserMedal($username, $medal) {
    $user = getUserByUsername($username);
    if (!$user) {
        error_log("addUserMedal: пользователь $username не найден");
        return false;
    }
    
    $settings = getUserProfileSettings($user['id']);
    if (!isset($settings['medals'])) $settings['medals'] = [];
    
    $settings['medals'][] = [
        'id' => uniqid(),
        'name' => $medal['name'],
        'description' => $medal['description'],
        'icon' => $medal['icon'],
        'color' => $medal['color'],
        'awarded_at' => date('Y-m-d H:i:s')
    ];
    
    return saveUserProfileSettings($user['id'], $settings);
}

// ============================================
// ФУНКЦИИ ДЛЯ СООБЩЕНИЙ
//=============================================
function addError($message) {
    if (!isset($_SESSION['errors'])) $_SESSION['errors'] = [];
    $_SESSION['errors'][] = $message;
}

function getErrors() {
    $errors = $_SESSION['errors'] ?? [];
    unset($_SESSION['errors']);
    return $errors;
}

function addMessage($message) {
    if (!isset($_SESSION['messages'])) $_SESSION['messages'] = [];
    $_SESSION['messages'][] = $message;
}

function getMessages() {
    $messages = $_SESSION['messages'] ?? [];
    unset($_SESSION['messages']);
    return $messages;
}

// ============================================
// ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ
//=============================================
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizePostContent($content) {
    if (empty($content)) {
        return '';
    }

    $content = str_replace('&nbsp;', ' ', $content);
    $content = preg_replace('/<hr[^>]*>/i', '<hr>', $content);
    $content = preg_replace('/<div([^>]*)>/i', '<p$1>', $content);
    $content = preg_replace('/<\/div>/i', '</p>', $content);
    $content = preg_replace('/<(script|style|iframe|object|embed|button)[^>]*>.*?<\/\1>/is', '', $content);
    $content = preg_replace('/\s*(id|class|style|contenteditable|data-[a-zA-Z0-9_-]+|on[a-zA-Z]+)=(["\"][^"\"]*["\"]|[^\s>]+)/i', '', $content);
    $allowedTags = '<a><b><strong><i><em><u><s><strike><br><p><ul><ol><li><img><h1><h2><h3><h4><h5><h6><hr><pre><code><blockquote>';
    $clean = strip_tags($content, $allowedTags);

    // Preserve only safe href/src values
    $clean = preg_replace_callback('/<(a|img)([^>]*)>/i', function($matches) {
        $tag = strtolower($matches[1]);
        $attrs = $matches[2];
        $keep = '';

        if ($tag === 'a') {
            if (preg_match('/href\s*=\s*(["\"])(.*?)\1/i', $attrs, $m)) {
                $href = filter_var($m[2], FILTER_VALIDATE_URL);
                if ($href) {
                    $keep = ' href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer"';
                }
            }
        } elseif ($tag === 'img') {
            if (preg_match('/src\s*=\s*(["\"])(.*?)\1/i', $attrs, $m)) {
                $src = filter_var($m[2], FILTER_VALIDATE_URL) ?: htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
                $keep = ' src="' . htmlspecialchars($src, ENT_QUOTES, 'UTF-8') . '"';
            }
            if (preg_match('/alt\s*=\s*(["\"])(.*?)\1/i', $attrs, $m)) {
                $keep .= ' alt="' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '"';
            }
        }

        return '<' . $tag . $keep . '>';
    }, $clean);

    return $clean;
}

function validatePassword($password) {
    return strlen($password) >= 6;
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function redirect($url) {
    if (strpos($url, 'http') === 0) {
        header("Location: $url");
    } else {
        $base = rtrim(SITE_URL, '/');
        $url = ltrim($url, '/');
        header("Location: $base/$url");
    }
    exit;
}

function truncateText($text, $length = 300) {
    if (mb_strlen($text) <= $length) return $text;
    
    $truncated = mb_substr($text, 0, $length);
    $lastSpace = mb_strrpos($truncated, ' ');
    
    if ($lastSpace !== false) {
        $truncated = mb_substr($truncated, 0, $lastSpace);
    }
    
    return $truncated . '...';
}

function searchPosts($query) {
    $allPosts = getAllPosts();
    $results = [];
    
    foreach ($allPosts as $post) {
        if (stripos($post['title'], $query) !== false || 
            stripos($post['content'], $query) !== false ||
            stripos($post['username'], $query) !== false) {
            $results[] = $post;
        }
    }
    
    return $results;
}

// ============================================
// ОБРАБОТКА POST ЗАПРОСОВ
//=============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'register':
            $username = sanitizeInput($_POST['username']);
            $email = sanitizeInput($_POST['email']);
            $password = $_POST['password'];
            $confirm = $_POST['confirm_password'];
            
            $errors = [];
            
            if (empty($username)) $errors[] = "Имя пользователя обязательно";
            elseif (strlen($username) < 3) $errors[] = "Минимум 3 символа";
            elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) $errors[] = "Только буквы, цифры и _";
            elseif (getUserByUsername($username)) $errors[] = "Имя уже занято";
            
            if (empty($email)) $errors[] = "Email обязателен";
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Неверный формат email";
            elseif (getUserByEmail($email)) $errors[] = "Email уже зарегистрирован";
            
            if (empty($password)) $errors[] = "Пароль обязателен";
            elseif (!validatePassword($password)) $errors[] = "Минимум 6 символов";
            elseif ($password !== $confirm) $errors[] = "Пароли не совпадают";
            
            if (empty($errors)) {
                $users = getUsers();
                $newUser = [
                    'id' => uniqid(),
                    'username' => $username,
                    'email' => $email,
                    'password' => hashPassword($password),
                    'role' => '',
                    'verified' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'bio' => 'Ой наш любимый игрок, нашего сервера Cryndel SMP',
                    'avatar' => '',
                    'social' => ['website' => '', 'twitter' => '', 'instagram' => '', 'vk' => '', 'telegram' => '']
                ];
                
                $users[] = $newUser;
                if (saveUsers($users)) {
                    $_SESSION['user_id'] = $newUser['id'];
                    addMessage("Регистрация успешна! Напишите @Cryndel для подтверждения.");
                } else {
                    addError("Ошибка сохранения пользователя");
                }
            } else {
                foreach ($errors as $error) addError($error);
            }
            
            redirect('/');
            break;
        
        case 'login':
            $username = sanitizeInput($_POST['username']);
            $password = $_POST['password'];
            
            $user = getUserByUsername($username);
            
            if (!$user || !verifyPassword($password, $user['password'])) {
                addError("Неверное имя или пароль");
            } else {
                $_SESSION['user_id'] = $user['id'];
                if (!$user['verified']) {
                    addMessage("Аккаунт ждет подтверждения. Напишите @Cryndel.");
                } else {
                    addMessage("С возвращением, {$user['username']}!");
                }
            }
            
            redirect('/');
            break;
        
        case 'logout':
            session_destroy();
            redirect('/');
            break;
        
        case 'save_post':
            $user = fn_getCurrentUser();
            if (!$user) {
                addError("Требуется авторизация");
                redirect('/?action=login');
            }
            
            if (!$user['verified']) {
                addError("Аккаунт не подтвержден");
                redirect('/');
            }
            
            $title = sanitizeInput($_POST['title']);
            $content = sanitizePostContent($_POST['content'] ?? '');
            $meta_title = sanitizeInput($_POST['meta_title'] ?? '');
            $meta_description = sanitizeInput($_POST['meta_description'] ?? '');
            $meta_keywords = sanitizeInput($_POST['meta_keywords'] ?? '');
            $custom_css = $_POST['custom_css'] ?? '';
            $slug = !empty($_POST['slug']) ? sanitizeInput($_POST['slug']) : generateSlug($title);
            $tags = isset($_POST['tags']) ? array_map('trim', explode(',', sanitizeInput($_POST['tags']))) : [];
            $is_editing = isset($_POST['is_editing']) && $_POST['is_editing'] === 'true';
            $original_slug = $_POST['original_slug'] ?? '';
            
            $errors = [];
            if (empty($title)) $errors[] = "Заголовок обязателен";
            if (empty($content)) $errors[] = "Содержание обязательно";
            if (mb_strlen($content) > MAX_POST_LENGTH) $errors[] = "Превышен лимит (" . MAX_POST_LENGTH . " символов)";
            
            if (!empty($errors)) {
                foreach ($errors as $error) addError($error);
                if ($is_editing) {
                    redirect('/?action=create_post&edit=' . $original_slug);
                } else {
                    redirect('/?action=create_post');
                }
            }
            
            if (!$is_editing) {
                $existingPost = getPostBySlug($slug);
                if ($existingPost && $existingPost['user_id'] !== $user['id']) {
                    $slug = $slug . '-' . uniqid();
                }
            }
            
            $tagsHtml = '';
            foreach ($tags as $tag) {
                if (!empty($tag)) {
                    $tagsHtml .= '<a href="/?tag=' . urlencode($tag) . '" class="tag">#' . htmlspecialchars($tag) . '</a>';
                }
            }
            
            $post = [
                'user_id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role'] ?? '',
                'avatar' => getAvatarUrl($user),
                'title' => $title,
                'content' => $content,
                'slug' => $slug,
                'meta_title' => $meta_title ?: $title,
                'meta_description' => $meta_description ?: mb_substr(strip_tags($content), 0, 160),
                'meta_keywords' => $meta_keywords,
                'custom_css' => $custom_css,
                'tags' => $tags,
                'tags_html' => $tagsHtml,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'views' => 0,
                'likes' => 0
            ];
            
            if (savePost($post)) {
                if ($is_editing && $original_slug && $original_slug !== $slug) {
                    deletePost($original_slug);
                }
                addMessage($is_editing ? "Пост обновлен!" : "Пост опубликован!");
                redirect("/post/{$slug}");
            } else {
                addError("Ошибка сохранения");
                if ($is_editing) {
                    redirect('/?action=create_post&edit=' . $original_slug);
                } else {
                    redirect('/?action=create_post');
                }
            }
            break;
        
        case 'delete_post':
            $user = fn_getCurrentUser();
            if (!$user) {
                addError("Требуется авторизация");
                redirect('/?action=login');
            }
            
            $slug = sanitizeInput($_POST['slug']);
            $post = getPostBySlug($slug);
            
            if (!$post) {
                addError("Пост не найден");
            } elseif ($post['user_id'] !== $user['id'] && $user['role'] !== 'admin') {
                addError("Нет прав");
            } elseif (deletePost($slug)) {
                addMessage("Пост удален");
            } else {
                addError("Ошибка удаления");
            }
            
            redirect('/');
            break;
        
        case 'like_post':
            header('Content-Type: application/json');
            
            $user = fn_getCurrentUser();
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'auth_required']);
                exit;
            }
            
            $slug = sanitizeInput($_POST['slug'] ?? '');
            if (empty($slug)) {
                echo json_encode(['success' => false, 'message' => 'no_slug']);
                exit;
            }
            
            $result = togglePostLike($slug, $user['id']);
            
            echo json_encode([
                'success' => true,
                'likes' => $result['likes'],
                'hasLiked' => $result['hasLiked']
            ]);
            exit;
            break;
        
        case 'add_comment':
            header('Content-Type: application/json');
            
            $user = fn_getCurrentUser();
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'auth_required']);
                exit;
            }
            
            $slug = sanitizeInput($_POST['slug'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $parentId = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
            
            if (empty($slug)) {
                echo json_encode(['success' => false, 'message' => 'no_slug']);
                exit;
            }
            
            if (empty($content)) {
                echo json_encode(['success' => false, 'message' => 'Комментарий не может быть пустым']);
                exit;
            }
            
            $comment = addComment($slug, $user['id'], $content, $parentId);
            
            if ($comment) {
                $comment['avatar'] = getAvatarUrl($user);
                $comment['username'] = $user['username'];
                echo json_encode(['success' => true, 'comment' => $comment]);
            } else {
                error_log("add_comment: ошибка добавления комментария");
                echo json_encode(['success' => false, 'message' => 'Ошибка добавления комментария']);
            }
            exit;
            break;
        
        case 'delete_comment':
            header('Content-Type: application/json');
            
            $user = fn_getCurrentUser();
            if (!$user) {
                echo json_encode(['success' => false]);
                exit;
            }
            
            $commentId = $_POST['comment_id'] ?? '';
            $slug = $_POST['slug'] ?? '';
            
            if (empty($commentId) || empty($slug)) {
                echo json_encode(['success' => false]);
                exit;
            }
            
            if (deleteComment($commentId, $slug, $user['id'])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
            break;
        
        case 'react_comment':
            header('Content-Type: application/json');
            
            $user = fn_getCurrentUser();
            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'auth_required']);
                exit;
            }
            
            $commentId = $_POST['comment_id'] ?? '';
            $slug = $_POST['slug'] ?? '';
            $reaction = $_POST['reaction'] ?? '❤️';
            
            if (empty($commentId) || empty($slug)) {
                echo json_encode(['success' => false, 'message' => 'invalid_data']);
                exit;
            }
            
            $success = toggleCommentReaction($commentId, $slug, $user['id'], $reaction);
            
            if ($success) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'error']);
            }
            exit;
            break;
        
        case 'upload_avatar':
            $user = fn_getCurrentUser();
            if (!$user) {
                addError("Требуется авторизация");
                redirect('/?action=login');
            }
            
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                addError("Ошибка загрузки");
                redirect("/{$user['username']}");
            }
            
            $file = $_FILES['avatar'];
            
            if ($file['size'] > MAX_AVATAR_SIZE) {
                addError("Файл слишком большой (макс 1MB)");
                redirect("/{$user['username']}");
            }
            
            $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if (!in_array($mime, $allowed)) {
                addError("Допустимы: JPG, PNG, GIF, WEBP");
                redirect("/{$user['username']}");
            }
            
            $result = uploadAvatar($user['id'], $file);
            if ($result) {
                addMessage("Аватар обновлен!");
            } else {
                addError("Ошибка загрузки");
            }
            
            redirect("/{$user['username']}");
            break;
        
        case 'update_profile':
            $user = fn_getCurrentUser();
            if (!$user) {
                addError("Требуется авторизация");
                redirect('/?action=login');
            }
            
            $bio = sanitizeInput($_POST['bio'] ?? '');
            $website = sanitizeInput($_POST['website'] ?? '');
            $twitter = sanitizeInput($_POST['twitter'] ?? '');
            $instagram = sanitizeInput($_POST['instagram'] ?? '');
            $vk = sanitizeInput($_POST['vk'] ?? '');
            $telegram = sanitizeInput($_POST['telegram'] ?? '');
            
            $users = getUsers();
            $updated = false;
            foreach ($users as &$u) {
                if ($u['id'] === $user['id']) {
                    $u['bio'] = $bio;
                    $u['social'] = compact('website', 'twitter', 'instagram', 'vk', 'telegram');
                    $updated = true;
                    break;
                }
            }
            
            if ($updated && saveUsers($users)) {
                addMessage("Профиль обновлен");
            } else {
                addError("Ошибка сохранения");
            }

            // Also save status, cover, widgets into profile settings
            if (isset($_POST['status']) || isset($_POST['cover_css'])) {
                $settings = getUserProfileSettings($user['id']);
                $settings['status'] = mb_substr(sanitizeInput($_POST['status'] ?? ''), 0, 100);
                $settings['cover_css'] = sanitizeInput($_POST['cover_css'] ?? '');
                $settings['widget_music'] = !empty($_POST['widget_music']);
                $settings['widget_sticker'] = !empty($_POST['widget_sticker']);
                $settings['widget_build'] = !empty($_POST['widget_build']);
                saveUserProfileSettings($user['id'], $settings);
            }
            
            redirect("/{$user['username']}");
            break;
        
        case 'update_profile_settings':
            $user = fn_getCurrentUser();
            if (!$user) {
                addError("Требуется авторизация");
                redirect('/?action=login');
            }
            
            $custom_css = $_POST['custom_css'] ?? '';
            $settings = getUserProfileSettings($user['id']);
            $settings['custom_css'] = $custom_css;
            $settings['status'] = mb_substr($_POST['status'] ?? '', 0, 100);
            $settings['cover_css'] = $_POST['cover_css'] ?? '';
            $settings['widget_music'] = !empty($_POST['widget_music']);
            $settings['widget_sticker'] = !empty($_POST['widget_sticker']);
            $settings['widget_build'] = !empty($_POST['widget_build']);
            
            if (saveUserProfileSettings($user['id'], $settings)) {
                addMessage("Настройки сохранены");
            } else {
                addError("Ошибка");
            }
            
            redirect("/{$user['username']}");
            break;
        
        case 'search':
            header('Content-Type: application/json');
            
            $query = sanitizeInput($_POST['query'] ?? '');
            $type = $_POST['type'] ?? 'all';
            
            $results = ['users' => [], 'posts' => []];
            
            if ($query && strlen($query) >= 2) {
                if ($type === 'all' || $type === 'users') {
                    $users = getUsers();
                    foreach ($users as $user) {
                        if (stripos($user['username'], $query) !== false) {
                            $results['users'][] = [
                                'username' => $user['username'],
                                'avatar' => getAvatarUrl($user),
                                'role' => $user['role'] ?? '',
                                'verified' => $user['verified'] ?? false
                            ];
                        }
                    }
                }
                
                if ($type === 'all' || $type === 'posts') {
                    $results['posts'] = searchPosts($query);
                }
            }
            
            echo json_encode($results);
            exit;
            break;
        
        case 'mark_notification_read':
            header('Content-Type: application/json');
            
            $user = fn_getCurrentUser();
            if (!$user) {
                echo json_encode(['success' => false]);
                exit;
            }
            
            $notificationId = $_POST['notification_id'] ?? '';
            
            if (empty($notificationId)) {
                echo json_encode(['success' => false]);
                exit;
            }
            
            $success = markNotificationAsRead($notificationId, $user['id']);
            echo json_encode(['success' => $success]);
            exit;
            break;
        
        case 'mark_all_notifications_read':
            header('Content-Type: application/json');
            
            $user = fn_getCurrentUser();
            if (!$user) {
                echo json_encode(['success' => false]);
                exit;
            }
            
            $success = markAllNotificationsAsRead($user['id']);
            echo json_encode(['success' => $success]);
            exit;
            break;
        
        case 'dismiss_onboarding':
            $_SESSION['onboarding_dismissed'] = true;
            echo json_encode(['success' => true]);
            exit;
            break;
    }
}

// ============================================
// ОБРАБОТКА AJAX GET ЗАПРОСОВ
//=============================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['ajax']) {
        case 'increment_views':
            if (isset($_GET['slug'])) {
                incrementPostViews($_GET['slug']);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false]);
            }
            exit;
            break;
            
        case 'check_like':
            $user = fn_getCurrentUser();
            $slug = $_GET['slug'] ?? '';
            if ($user && $slug) {
                echo json_encode(['hasLiked' => hasUserLikedPost($slug, $user['id'])]);
            } else {
                echo json_encode(['hasLiked' => false]);
            }
            exit;
            break;
            
        case 'get_comments':
            $slug = $_GET['slug'] ?? '';
            $comments = getPostComments($slug);
            $tree = buildCommentTree($comments);
            echo json_encode(['success' => true, 'comments' => $tree]);
            exit;
            break;
            
        case 'get_notifications':
            $user = fn_getCurrentUser();
            if (!$user) {
                echo json_encode(['success' => false]);
                exit;
            }
            
            $notifications = getUserNotifications($user['id']);
            $unreadCount = count(getUserNotifications($user['id'], true));
            
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'unread_count' => $unreadCount
            ]);
            exit;
            break;
            
        case 'search_users':
            $query = $_GET['q'] ?? '';
            if (strlen($query) < 2) {
                echo json_encode([]);
                exit;
            }
            
            $users = getUsers();
            $results = [];
            foreach ($users as $user) {
                if (stripos($user['username'], $query) !== false) {
                    $results[] = [
                        'username' => $user['username'],
                        'avatar' => getAvatarUrl($user)
                    ];
                }
            }
            
            echo json_encode($results);
            exit;
            break;
    }
}

// ============================================
// ОПРЕДЕЛЕНИЕ ТЕКУЩЕГО ВЬЮ
//=============================================
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = trim($request_uri, '/');
$view = 'feed';
$post = null;
$profileUser = null;
$editPost = null;

if (empty($path) || $path === 'index.php') {
    $action = $_GET['action'] ?? '';
    switch ($action) {
        case 'login': $view = 'login'; break;
        case 'register': $view = 'register'; break;
        case 'create_post':
            if (fn_getCurrentUser()) {
                if (isset($_GET['edit'])) {
                    $editPost = getPostBySlug($_GET['edit']);
                    if ($editPost && $editPost['user_id'] === fn_getCurrentUser()['id']) {
                        $view = 'create_post';
                    } else {
                        addError("Пост не найден или нет прав");
                        redirect('/');
                    }
                } else {
                    $view = 'create_post';
                }
            } else {
                redirect('/?action=login');
            }
            break;
        case 'my_posts': $view = fn_getCurrentUser() ? 'my_posts' : 'login'; break;
        case 'templates': $view = 'templates'; break;
        case 'music': $view = 'music'; break;
        case 'stickers': $view = 'stickers'; break;
        case 'coding': $view = 'coding'; break;
        case 'themes': $view = 'themes'; break;
        case 'notifications': $view = fn_getCurrentUser() ? 'notifications_page' : 'login'; break;
        default: $view = 'feed'; break;
    }
} elseif (preg_match('/^post\/([a-z0-9-]+)$/', $path, $matches)) {
    $slug = $matches[1];
    $post = getPostBySlug($slug);
    if ($post) {
        $view = 'post_view';
    } else {
        http_response_code(404);
        $view = '404';
    }
} elseif (preg_match('/^([a-zA-Z0-9_]+)$/', $path, $matches)) {
    $profileUser = getUserByUsername($matches[1]);
    $view = $profileUser ? 'profile' : '404';
} elseif (preg_match('/^VP\/posts\/([a-z0-9_-]+)\/([a-z0-9_-]+)\.html$/', $path, $matches)) {
    $username = $matches[1];
    $slug = $matches[2];
    $post = getPostBySlug($slug);
    if ($post && $post['username'] === $username) {
        $htmlFile = POSTS_DIR . $username . '/' . $slug . '.html';
        if (file_exists($htmlFile)) {
            readfile($htmlFile);
            exit;
        }
    }
    http_response_code(404);
    $view = '404';
} else {
    http_response_code(404);
    $view = '404';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    
    <title><?php
    if ($view === 'post_view' && $post) echo htmlspecialchars($post['meta_title']) . ' | My Cryndel';
    elseif ($view === 'profile' && $profileUser) echo htmlspecialchars($profileUser['username']) . ' | Профиль | My Cryndel';
    elseif ($view === 'create_post') echo (isset($_GET['edit']) ? 'Редактировать пост' : 'Создать пост') . ' | My Cryndel';
    elseif ($view === 'my_posts') echo 'Мои посты | My Cryndel';
    elseif ($view === 'login') echo 'Вход | My Cryndel';
    elseif ($view === 'register') echo 'Регистрация | My Cryndel';
    elseif ($view === 'templates') echo 'Шаблоны | My Cryndel';
    elseif ($view === 'music') echo 'Музыка | My Cryndel';
    elseif ($view === 'stickers') echo 'Стикеры | My Cryndel';
    elseif ($view === 'coding') echo 'Кодинг | My Cryndel';
    elseif ($view === 'themes') echo 'Оформление | My Cryndel';
    elseif ($view === 'notifications_page') echo 'Уведомления | My Cryndel';
    else echo 'My Cryndel - Сообщество игроков Minecraft';
    ?></title>
    
    <meta name="description" content="My Cryndel - сообщество игроков Minecraft. IP: cryndel.ru:25919">
    <meta name="keywords" content="Minecraft, SMP, Cryndel, сервер, майнкрафт">
    <meta property="og:site_name" content="My Cryndel">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="https://hi.cryndel.ru/<?php echo $path; ?>">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <meta name="theme-color" content="#10b981">
    
    <style>
        /* ============================================
           ОСНОВНЫЕ ПЕРЕМЕННЫЕ И СБРОСЫ
        ============================================ */
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --primary-light: #d1fae5;
            --text: #111827;
            --text-light: #6b7280;
            --text-muted: #9ca3af;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --background: #f9fafb;
            --card-bg: #ffffff;
            --shadow: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
            --radius: 12px;
            --radius-lg: 16px;
            --radius-full: 9999px;
            --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            transition: background-color 0.3s ease, font-size 0.2s ease;
        }

        body.custom-bg {
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
        }

        /* ============================================
           КОНТЕЙНЕРЫ И СЕТКА
        ============================================ */
        .container {
            max-width: 680px;
            margin: 0 auto;
            padding: 0 16px;
            width: 100%;
        }

        .container-wide {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px;
            width: 100%;
        }

        /* ============================================
           ШАПКА САЙТА (ОБНОВЛЕННАЯ)
        ============================================ */
        .header {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            border-radius: 0 0 16px 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 64px;
            padding: 0 8px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            font-size: 20px;
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }

        .logo:hover {
            transform: scale(1.02);
        }

        .logo img {
            width: 32px;
            height: 32px;
            border-radius: 8px;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .nav-link {
            color: var(--text-light);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: var(--radius-full);
        }

        .nav-link:hover {
            color: var(--primary);
            background: var(--primary-light);
        }

        .nav-link.active {
            color: var(--primary);
            background: var(--primary-light);
        }

        .nav-link i {
            font-size: 16px;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* ============================================
           УВЕДОМЛЕНИЯ (КОЛОКОЛЬЧИК)
        ============================================ */
        .notification-bell {
            position: relative;
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            color: var(--text-light);
        }

        .notification-bell:hover {
            background: var(--border-light);
            color: var(--primary);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 600;
            min-width: 16px;
            height: 16px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid white;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 320px;
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            margin-top: 8px;
            display: none;
            z-index: 101;
            overflow: hidden;
        }

        .notification-dropdown.show {
            display: block;
            animation: fadeInDown 0.2s ease;
        }

        .notification-header {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-header h3 {
            font-size: 16px;
            font-weight: 600;
        }

        .notification-header button {
            background: none;
            border: none;
            color: var(--primary);
            font-size: 12px;
            cursor: pointer;
        }

        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border-light);
            transition: var(--transition);
            cursor: pointer;
        }

        .notification-item:hover {
            background: var(--border-light);
        }

        .notification-item.unread {
            background: var(--primary-light);
        }

        .notification-item.unread:hover {
            background: #bee3db;
        }

        .notification-content {
            display: flex;
            gap: 10px;
        }

        .notification-icon {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--primary-light);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-text {
            flex: 1;
        }

        .notification-text p {
            font-size: 13px;
            margin-bottom: 4px;
        }

        .notification-time {
            font-size: 11px;
            color: var(--text-light);
        }

        .notification-empty {
            padding: 30px;
            text-align: center;
            color: var(--text-light);
        }

        /* ============================================
           МОБИЛЬНОЕ МЕНЮ
        ============================================ */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            color: var(--text);
            cursor: pointer;
            z-index: 101;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: var(--transition);
        }

        .mobile-menu-btn:hover {
            background: var(--border-light);
        }

        @media (max-width: 768px) {
            .nav { display: none; }
            .mobile-menu-btn { display: flex; align-items: center; justify-content: center; }
        }

        .mobile-menu {
            position: fixed;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100vh;
            background: var(--card-bg);
            z-index: 200;
            transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            padding: 20px;
        }

        .mobile-menu.show {
            left: 0;
        }

        .mobile-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }

        .mobile-menu-close {
            background: none;
            border: none;
            font-size: 28px;
            color: var(--text);
            cursor: pointer;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }

        .mobile-menu-close:hover {
            background: var(--border-light);
        }

        .mobile-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .mobile-nav-link {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            border-radius: var(--radius);
            transition: var(--transition);
            font-size: 16px;
        }

        .mobile-nav-link i {
            width: 24px;
            font-size: 20px;
            color: var(--primary);
        }

        .mobile-nav-link:hover {
            background: var(--border-light);
        }

        .mobile-nav-link.active {
            background: var(--primary-light);
            color: var(--primary);
        }

        .mobile-nav-link.active i {
            color: var(--primary);
        }

        .mobile-user-info {
            padding: 15px;
            margin-top: 20px;
            background: var(--border-light);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .mobile-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
        }

        .mobile-username {
            font-weight: 600;
            font-size: 16px;
        }

        .mobile-role {
            font-size: 12px;
            color: var(--text-light);
        }

        /* ============================================
           ОСНОВНОЙ КОНТЕНТ
        ============================================ */
        .main {
            margin-top: 64px;
            flex: 1;
            padding: 20px 0 40px;
        }

        /* ============================================
           КАРТОЧКИ ПОСТОВ
        ============================================ */
        .post-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            margin-bottom: 20px;
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            opacity: 0;
            transform: translateY(10px);
            animation: fadeInUp 0.4s ease forwards;
        }

        .post-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .post-card.role-admin { border-left: 4px solid #f59e0b; }
        .post-card.role-content_creator { border-left: 4px solid #3b82f6; }
        .post-card.role-tech_admin { border-left: 4px solid #8b5cf6; }
        .post-card.role-legendary { border-left: 4px solid #ec4899; }
        .post-card.role-mythic { border-left: 4px solid #14b8a6; }
        .post-card.role-golden { border-left: 4px solid #fbbf24; }

        .post-card.no-border {
            border-left: none;
        }

        .post-header {
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 1px solid var(--border-light);
        }

        .post-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            object-fit: cover;
            transition: var(--transition);
        }

        .post-avatar:hover {
            transform: scale(1.05);
        }

        .post-author-info { 
            flex: 1; 
        }

        .post-author {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .post-author-name {
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            transition: var(--transition);
        }

        .post-author-name:hover {
            color: var(--primary);
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            color: white;
            transition: var(--transition);
        }
        .role-badge.admin { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .role-badge.creator { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .role-badge.tech { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .role-badge.legendary { background: linear-gradient(135deg, #ec4899, #db2777); }
        .role-badge.mythic { background: linear-gradient(135deg, #14b8a6, #0d9488); }
        .role-badge.golden { background: linear-gradient(135deg, #fbbf24, #f59e0b); }

        .post-date {
            font-size: 12px;
            color: var(--text-light);
        }

        .post-content {
            padding: 16px;
        }

        .post-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 12px;
            color: var(--text);
            text-decoration: none;
            display: block;
            transition: var(--transition);
        }

        .post-title:hover {
            color: var(--primary);
        }

        .post-text {
            color: var(--text);
            line-height: 1.6;
            margin-bottom: 16px;
            word-wrap: break-word;
        }

        .post-text img {
            max-width: 100%;
            border-radius: var(--radius);
            margin: 10px 0;
            transition: var(--transition);
        }

        .post-text img:hover {
            transform: scale(1.02);
        }

        .read-more {
            color: var(--primary);
            font-weight: 500;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 8px;
            transition: var(--transition);
        }

        .read-more:hover {
            gap: 10px;
        }

        .post-footer {
            padding: 12px 16px;
            border-top: 1px solid var(--border-light);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .post-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .tag {
            background: var(--border-light);
            color: var(--text-light);
            font-size: 12px;
            padding: 4px 10px;
            border-radius: 20px;
            text-decoration: none;
            transition: var(--transition);
        }

        .tag:hover {
            background: var(--primary-light);
            color: var(--primary);
            transform: scale(1.05);
        }

        .post-stats {
            display: flex;
            gap: 16px;
        }

        .stat {
            display: flex;
            align-items: center;
            gap: 6px;
            color: var(--text-light);
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
        }

        .stat:hover {
            color: var(--primary);
        }

        .like-btn {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 14px;
            transition: var(--transition);
        }

        .like-btn:hover { 
            color: #ef4444;
            transform: scale(1.1);
        }
        
        .like-btn.active { 
            color: #ef4444; 
        }
        
        .like-btn.active i { 
            font-weight: 900;
            animation: likePop 0.3s ease;
        }

        @keyframes likePop {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        /* ============================================
           КОММЕНТАРИИ (СОВРЕМЕННЫЙ ДИЗАЙН)
        ============================================ */
        .comments-section {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 24px;
            margin-top: 20px;
        }

        .comments-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .comment-form {
            margin-bottom: 30px;
        }

        .comment-input-wrapper {
            position: relative;
            margin-bottom: 10px;
        }

        .comment-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
            transition: var(--transition);
        }

        .comment-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .mention-suggestions {
            position: absolute;
            bottom: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            max-height: 200px;
            overflow-y: auto;
            display: none;
            z-index: 10;
            margin-bottom: 4px;
        }

        .mention-suggestions.show {
            display: block;
        }

        .mention-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 12px;
            cursor: pointer;
            transition: var(--transition);
        }

        .mention-item:hover {
            background: var(--border-light);
        }

        .mention-item img {
            width: 24px;
            height: 24px;
            border-radius: 50%;
        }

        .comment-submit {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .comment-item {
            display: flex;
            gap: 12px;
            position: relative;
            animation: fadeIn 0.3s ease;
        }

        .comment-item.level-1 { margin-left: 40px; }
        .comment-item.level-2 { margin-left: 80px; }
        .comment-item.level-3 { margin-left: 120px; }

        .comment-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }

        .comment-bubble {
            flex: 1;
            background: var(--border-light);
            border-radius: 18px 18px 18px 4px;
            padding: 12px 16px;
            position: relative;
        }

        .comment-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }

        .comment-author {
            font-weight: 600;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
        }

        .comment-author:hover {
            color: var(--primary);
        }

        .comment-date {
            font-size: 11px;
            color: var(--text-light);
        }

        .comment-text {
            font-size: 14px;
            line-height: 1.5;
            color: var(--text);
            word-wrap: break-word;
        }

        .comment-text a {
            color: var(--primary);
            text-decoration: none;
        }

        .comment-text a:hover {
            text-decoration: underline;
        }

        .comment-actions {
            display: flex;
            gap: 12px;
            margin-top: 8px;
        }

        .comment-action {
            background: none;
            border: none;
            color: var(--text-light);
            font-size: 12px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 4px;
            transition: var(--transition);
            padding: 2px 6px;
            border-radius: 12px;
        }

        .comment-action:hover {
            color: var(--primary);
            background: white;
        }

        .comment-reactions {
            display: flex;
            gap: 4px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .reaction-badge {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            cursor: pointer;
            transition: var(--transition);
        }

        .reaction-badge:hover {
            border-color: var(--primary);
            transform: scale(1.05);
        }

        .reaction-badge.active {
            background: var(--primary-light);
            border-color: var(--primary);
        }

        .reply-form {
            margin-top: 10px;
            margin-left: 52px;
            display: none;
        }

        .reply-form.show {
            display: block;
        }

        .comment-delete {
            position: absolute;
            top: 8px;
            right: 8px;
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 14px;
            padding: 4px 8px;
            border-radius: 12px;
            transition: var(--transition);
        }

        .comment-delete:hover {
            color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .no-comments {
            text-align: center;
            color: var(--text-light);
            padding: 40px;
        }

        /* ============================================
           ПОИСК (МИНИМАЛИСТИЧНЫЙ)
        ============================================ */
        .search-minimal {
            margin-bottom: 20px;
            position: relative;
        }

        .search-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .search-icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text-light);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .search-icon-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .settings-icon-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--card-bg);
            border: 1px solid var(--border);
            color: var(--text-light);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .settings-icon-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: rotate(90deg);
        }

        .search-expanded {
            display: none;
            margin-top: 10px;
            animation: fadeInDown 0.3s ease;
        }

        .search-expanded.show {
            display: block;
        }

        .search-input-group {
            display: flex;
            gap: 10px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-full);
            padding: 4px 4px 4px 16px;
        }

        .search-input-group input {
            flex: 1;
            border: none;
            padding: 10px 0;
            font-size: 14px;
            background: transparent;
        }

        .search-input-group input:focus {
            outline: none;
        }

        .search-input-group button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            color: white;
            border: none;
            cursor: pointer;
            transition: var(--transition);
        }

        .search-input-group button:hover {
            background: var(--primary-dark);
            transform: scale(1.05);
        }

        .search-type {
            display: flex;
            gap: 15px;
            margin-top: 10px;
            padding: 0 10px;
        }

        .search-type label {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-light);
            cursor: pointer;
        }

        /* ============================================
           МОДАЛЬНОЕ ОКНО НАСТРОЕК ЛЕНТЫ
        ============================================ */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.active {
            display: flex;
            opacity: 1;
        }

        .modal-content {
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            transform: scale(0.9);
            transition: transform 0.3s ease;
            box-shadow: var(--shadow-lg);
        }

        .modal.active .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 700;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: var(--border-light);
        }

        .modal-body {
            padding: 20px;
        }

        /* Настройки ленты */
        .settings-group {
            margin-bottom: 20px;
        }

        .settings-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }

        .settings-row {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .color-picker {
            width: 50px;
            height: 40px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            padding: 2px;
        }

        .gradient-presets {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }

        .gradient-preset {
            width: 40px;
            height: 40px;
            border-radius: var(--radius);
            cursor: pointer;
            border: 2px solid transparent;
            transition: var(--transition);
        }

        .gradient-preset:hover {
            transform: scale(1.1);
        }

        .gradient-preset.active {
            border-color: var(--primary);
        }

        .bg-image-input {
            display: flex;
            gap: 10px;
        }

        .bg-image-input input {
            flex: 1;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .file-upload {
            position: relative;
            display: inline-block;
        }

        .file-upload-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: var(--primary);
            color: white;
            border-radius: var(--radius);
            cursor: pointer;
            transition: var(--transition);
        }

        .file-upload-btn:hover {
            background: var(--primary-dark);
        }

        .file-upload input[type="file"] {
    position: absolute;
    opacity: 0;
    width: 100%;
    height: 100%;
    top: 0;
    left: 0;
    cursor: pointer;
}

        .settings-slider {
            width: 100%;
            height: 6px;
            background: var(--border);
            border-radius: 3px;
            outline: none;
        }

        .settings-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 18px;
            height: 18px;
            background: var(--primary);
            border-radius: 50%;
            cursor: pointer;
            transition: var(--transition);
        }

        .settings-slider::-webkit-slider-thumb:hover {
            transform: scale(1.2);
        }

        .font-select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
        }

        .sound-player {
            margin-top: 10px;
            padding: 15px;
            background: var(--border-light);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .sound-player button {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: white;
            color: var(--primary);
            cursor: pointer;
            transition: var(--transition);
        }

        .sound-player button:hover {
            background: var(--primary);
            color: white;
        }

        .sound-player input[type="range"] {
            flex: 1;
            min-width: 100px;
        }

        .sound-player.hidden {
            display: none;
        }

        .save-settings-btn {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            margin-top: 20px;
        }

        .save-settings-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        /* ============================================
           ОНБОРДИНГ (ПЕРВЫЙ ЗАПУСК)
        ============================================ */
        .onboarding-modal {
            text-align: center;
        }

        .onboarding-icon {
            font-size: 48px;
            color: var(--primary);
            margin-bottom: 20px;
        }

        .onboarding-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 15px;
        }

        .onboarding-text {
            color: var(--text-light);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .onboarding-btn {
            padding: 12px 30px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .onboarding-btn:hover {
            background: var(--primary-dark);
        }

        /* ============================================
           ФОРМЫ И КНОПКИ
        ============================================ */
        .form-container {
            max-width: 800px;
            margin: 40px auto;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 30px;
            box-shadow: var(--shadow-md);
        }

        .form-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            transition: var(--transition);
        }

        .form-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        textarea.form-input {
            min-height: 120px;
            resize: vertical;
        }

        .form-help {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 6px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: var(--radius);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        .btn-block {
            width: 100%;
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }

        .alert {
            padding: 16px;
            border-radius: var(--radius);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s ease;
        }

        .alert-success {
            background: var(--primary-light);
            color: var(--primary-dark);
        }

        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }

        /* ============================================
           ПРОФИЛЬ
        ============================================ */
        .profile-header {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 30px;
            text-align: center;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--border-light);
            margin-bottom: 20px;
            transition: var(--transition);
        }

        .profile-avatar:hover {
            transform: scale(1.05);
        }

        .profile-name {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .profile-bio {
            color: var(--text-light);
            margin-bottom: 20px;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 20px;
        }

        .stat-item {
            text-align: center;
            animation: fadeInUp 0.5s ease;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
        }

        .stat-label {
            font-size: 12px;
            color: var(--text-light);
        }

        .social-links {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .social-link {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-light);
            text-decoration: none;
            transition: var(--transition);
        }

        .social-link:hover {
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-3px);
        }

        .medals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .medal-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 15px;
            text-align: center;
            transition: var(--transition);
        }

        .medal-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .medal-icon {
            font-size: 32px;
            margin-bottom: 10px;
            color: var(--primary);
        }

        .medal-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .medal-description {
            font-size: 12px;
            color: var(--text-light);
        }

        .tabs {
            display: flex;
            border-bottom: 1px solid var(--border);
            margin-bottom: 20px;
            gap: 4px;
            overflow-x: auto;
        }

        .tab {
            padding: 12px 24px;
            background: none;
            border: none;
            color: var(--text-light);
            font-weight: 500;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: var(--transition);
            white-space: nowrap;
        }

        .tab:hover {
            color: var(--primary);
        }

        .tab.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-content.active {
            display: block;
        }

        /* ============================================
           РЕДАКТОР ПОСТОВ
        ============================================ */
        .editor-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            padding: 10px;
            background: var(--border-light);
            border: 1px solid var(--border);
            border-bottom: none;
            border-radius: var(--radius) var(--radius) 0 0;
        }

        .editor-btn {
            padding: 8px 12px;
            background: white;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            cursor: pointer;
            font-size: 14px;
            transition: var(--transition);
        }

        .editor-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .editor-content {
            min-height: 300px;
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 0 0 var(--radius) var(--radius);
            background: white;
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            overflow-y: auto;
        }

        .editor-content:focus {
            outline: none;
            border-color: var(--primary);
        }

        /* ============================================
           АНИМАЦИИ
        ============================================ */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .animate-fade {
            animation: fadeIn 0.3s ease;
        }

        .animate-fade-up {
            animation: fadeInUp 0.4s ease;
        }

        .animate-scale {
            animation: scaleIn 0.3s ease;
        }

        /* ============================================
           УТИЛИТЫ
        ============================================ */
        .text-center { text-align: center; }
        .text-primary { color: var(--primary); }
        .text-light { color: var(--text-light); }
        .mb-4 { margin-bottom: 16px; }
        .mb-6 { margin-bottom: 24px; }
        .mt-4 { margin-top: 16px; }
        .mt-6 { margin-top: 24px; }
        .flex { display: flex; }
        .items-center { align-items: center; }
        .justify-between { justify-content: space-between; }
        .justify-center { justify-content: center; }
        .gap-2 { gap: 8px; }
        .gap-4 { gap: 16px; }
        .w-full { width: 100%; }
        .hidden { display: none; }

        /* ============================================
           МЕДИА-ЗАПРОСЫ
        ============================================ */
        @media (max-width: 768px) {
            .profile-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .tabs {
                padding-bottom: 4px;
            }
            
            .form-container {
                margin: 20px 12px;
                padding: 20px;
            }
            
            .post-footer {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .editor-toolbar {
                overflow-x: auto;
                flex-wrap: nowrap;
            }

            .notification-dropdown {
                width: 280px;
                right: -60px;
            }
        }

        @media (max-width: 480px) {
            .profile-name {
                font-size: 20px;
            }
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .comment-item.level-1 { margin-left: 15px; }
            .comment-item.level-2 { margin-left: 30px; }
            .comment-item.level-3 { margin-left: 45px; }

            .post-card { padding: 12px; }
            .post-title { font-size: 16px; }
            .post-text { font-size: 13px; line-height: 1.5; }
            .post-author-name { font-size: 13px; }
            .post-date { font-size: 11px; }
            .post-footer { font-size: 12px; }
            .stat { font-size: 12px; }
            .profile-bio { font-size: 13px; }
            .profile-stats .stat-value { font-size: 18px; }
            .profile-stats .stat-label { font-size: 11px; }
            .content-card { padding: 10px; }
            .content-card-title { font-size: 14px; }
            .content-card-meta { font-size: 11px; }
            h1 { font-size: 20px !important; }
            .btn-sm { font-size: 12px; padding: 4px 8px; }
        }

        @media (max-width: 768px) {
            .post-text { font-size: 14px; }
            .post-title { font-size: 17px; }
            body { font-size: 14px; }
            .nav-link span { display: none; }
            .content-grid { grid-template-columns: 1fr; }
            .sticker-grid { grid-template-columns: repeat(3, 1fr); }
        }

        /* Floating Action Button */
        .fab-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: var(--primary);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 16px rgba(0,0,0,0.25);
            z-index: 999;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .fab-button:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.35);
            color: #fff;
        }
        @media (max-width: 768px) {
            .fab-button { bottom: 80px; right: 16px; width: 48px; height: 48px; font-size: 20px; }
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
        }
        .content-card {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            position: relative;
            transition: box-shadow 0.2s;
        }
        .content-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }
        .content-card-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            background: var(--bg-secondary, #f3f4f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: var(--primary);
            overflow: hidden;
            flex-shrink: 0;
        }
        .content-card-icon img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 12px;
        }
        .content-card-info {
            flex: 1;
            min-width: 0;
        }
        .content-card-title {
            font-weight: 600;
            font-size: 15px;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .content-card-meta {
            font-size: 12px;
            color: var(--text-light);
        }
        .content-card-desc {
            font-size: 12px;
            color: var(--text-light);
            margin-top: 4px;
        }
        .content-card-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 4px;
            margin-top: 4px;
        }
        .content-card-tags .tag {
            font-size: 11px;
            padding: 2px 6px;
            background: var(--bg-secondary, #f3f4f6);
            border-radius: 4px;
        }
        .content-card-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-shrink: 0;
        }
        .content-likes {
            font-size: 13px;
            color: var(--text-light);
        }
        .badge-ai, .badge-nsfw {
            position: absolute;
            top: 8px;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
            font-weight: 600;
        }
        .badge-ai { right: 8px; background: #6366f1; color: #fff; }
        .badge-nsfw { right: 40px; background: #ef4444; color: #fff; }
        .download-warning {
            width: 100%;
            font-size: 11px;
            color: #f59e0b;
            margin-top: 4px;
        }

        /* Sticker Grid */
        .sticker-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
        }
        .sticker-card {
            position: relative;
            aspect-ratio: 1;
            border-radius: 12px;
            background: var(--card-bg, #fff);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .sticker-card:hover {
            transform: scale(1.05);
        }
        .sticker-card img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        .sticker-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.5);
            color: #fff;
            text-align: center;
            padding: 4px;
            font-size: 12px;
            opacity: 0;
            transition: opacity 0.2s;
        }
        .sticker-card:hover .sticker-overlay {
            opacity: 1;
        }

        /* Notifications Page */
        .notification-item-page {
            display: flex;
            gap: 12px;
            padding: 16px;
            background: var(--card-bg, #fff);
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 8px;
            transition: background 0.2s;
        }
        .notification-item-page.unread {
            background: rgba(16, 185, 129, 0.05);
            border-color: var(--primary);
        }
        .notif-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--bg-secondary, #f3f4f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
        }
        .notif-body { flex: 1; min-width: 0; }
        .notif-text { font-size: 14px; line-height: 1.4; }
        .notif-text strong { color: var(--primary); }
        .notif-time { font-size: 12px; color: var(--text-light); margin-top: 4px; }

        /* Profile Cover */
        .profile-cover {
            width: 100%;
            height: 180px;
            border-radius: 16px 16px 0 0;
            background: linear-gradient(135deg, var(--primary), #6366f1);
            margin-bottom: -40px;
            position: relative;
        }
        .profile-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 16px 16px 0 0;
        }

        /* Profile Status */
        .profile-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            color: var(--text-light);
            margin-top: 4px;
        }
        .profile-status .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }

        /* Profile Stats Summary (for author) */
        .profile-stats-summary {
            background: var(--bg-secondary, #f3f4f6);
            border-radius: 10px;
            padding: 12px 16px;
            margin-bottom: 16px;
            font-size: 14px;
            color: var(--primary);
            font-weight: 500;
        }

        /* Profile Icon Tabs */
        .tabs-icons {
            display: flex;
            gap: 0;
            border-bottom: 2px solid var(--border);
            margin-bottom: 20px;
        }
        .tab-icon {
            flex: 1;
            text-align: center;
            padding: 10px 0;
            cursor: pointer;
            color: var(--text-light);
            font-size: 18px;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: color 0.2s, border-color 0.2s;
            background: none;
            border-top: none;
            border-left: none;
            border-right: none;
        }
        .tab-icon:hover, .tab-icon.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        .tab-icon .tab-tooltip {
            display: none;
            position: absolute;
            background: #1f2937;
            color: #fff;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            top: -30px;
            left: 50%;
            transform: translateX(-50%);
        }
        .tab-icon:hover .tab-tooltip { display: block; }

        /* Widgets */
        .widget-card {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: box-shadow 0.2s;
        }
        .widget-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        .widget-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            background: var(--bg-secondary, #f3f4f6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: var(--primary);
            flex-shrink: 0;
        }
        .widget-info { flex: 1; min-width: 0; }
        .widget-title { font-weight: 600; font-size: 14px; }
        .widget-meta { font-size: 12px; color: var(--text-light); }

        /* Role tooltip */
        .role-icon-wrap {
            display: inline-flex;
            position: relative;
            cursor: pointer;
        }
        .role-icon-wrap .role-tooltip {
            display: none;
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1f2937;
            color: #fff;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            white-space: nowrap;
            z-index: 100;
            margin-bottom: 4px;
        }
        .role-icon-wrap:hover .role-tooltip { display: block; }

        /* Special command menus */
        .special-menu-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            display: flex;
            align-items: flex-end;
            justify-content: center;
        }
        .special-menu {
            background: var(--card-bg, #fff);
            border-radius: 20px 20px 0 0;
            max-height: 70vh;
            width: 100%;
            max-width: 500px;
            overflow-y: auto;
            padding: 20px;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }
        .special-menu-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }
        .special-menu-header h3 { font-size: 18px; font-weight: 700; }
        .special-menu-close {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        .special-menu-search {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            margin-bottom: 12px;
            font-size: 14px;
        }
        .special-menu-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border-radius: 10px;
            cursor: pointer;
            transition: background 0.15s;
        }
        .special-menu-item:hover { background: var(--bg-secondary, #f3f4f6); }

        /* Embed cards (in posts/comments) */
        .embed-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: var(--bg-secondary, #f3f4f6);
            margin: 8px 0;
        }
        .embed-card-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .embed-card-icon.music { background: #6366f1; color: #fff; }
        .embed-card-icon.sticker { background: #f59e0b; color: #fff; }
        .embed-card-icon.theme { background: #8b5cf6; color: #fff; }
        .embed-card-icon.code { background: #10b981; color: #fff; }
        .embed-card-icon.user { background: var(--primary); color: #fff; }
        .embed-card-info { flex: 1; min-width: 0; }
        .embed-card-title { font-weight: 600; font-size: 14px; }
        .embed-card-meta { font-size: 12px; color: var(--text-light); }
        .embed-card-actions { display: flex; gap: 6px; }

        /* Image carousel for post images */
        .image-carousel { position: relative; overflow: hidden; border-radius: 12px; }
        .image-carousel-track { display: flex; transition: transform 0.35s ease; }
        .image-carousel-slide { min-width: 100%; display: flex; align-items: center; justify-content: center; }
        .image-carousel-slide img { width: 100%; height: auto; max-height: 420px; object-fit: cover; display:block; }
        .carousel-control { position: absolute; top: 50%; transform: translateY(-50%); background: rgba(0,0,0,0.45); color: #fff; border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; }
        .carousel-prev { left: 10px; }
        .carousel-next { right: 10px; }
        .carousel-dots { position:absolute; left:50%; transform:translateX(-50%); bottom:8px; display:flex; gap:6px; }
        .carousel-dot { width:8px;height:8px;border-radius:50%;background:rgba(255,255,255,0.6);cursor:pointer; }
        .carousel-dot.active { background:var(--primary); }

        /* User mention card */
        .mention-card {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 2px 8px;
            background: var(--bg-secondary, #f3f4f6);
            border-radius: 8px;
            font-size: 13px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
        .mention-card img {
            width: 20px;
            height: 20px;
            border-radius: 50%;
        }
        .mention-card:hover {
            background: var(--primary);
            color: #fff;
        }

        /* Sticker modal */
        .sticker-modal-content {
            text-align: center;
            padding: 20px;
        }
        .sticker-modal-content img {
            max-width: 200px;
            max-height: 200px;
            margin-bottom: 16px;
        }

        /* Upload forms */
        .upload-form {
            background: var(--card-bg, #fff);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
        }
        .upload-form h3 {
            margin-bottom: 16px;
            font-size: 18px;
        }
        .checkbox-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin: 12px 0;
        }
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            cursor: pointer;
        }

        /* Settings button in profile corner */
        .profile-settings-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0,0,0,0.3);
            color: #fff;
            border: none;
            border-radius: 50%;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.2s;
        }
        .profile-settings-btn:hover { background: rgba(0,0,0,0.5); }

        /* Btn sm */
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
            border-radius: 8px;
        }

        /* ============================================
           УЛУЧШЕННЫЙ ДИЗАЙН — Профессиональный UI
        ============================================ */

        /* Smooth scrolling */
        html { scroll-behavior: smooth; }

        /* Better selection */
        ::selection {
            background: var(--primary);
            color: #fff;
        }

        /* Scrollbar styling */
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
        ::-webkit-scrollbar-thumb:hover { background: var(--text-light); }

        /* Better header glass effect */
        .header {
            background: rgba(255,255,255,0.85) !important;
            backdrop-filter: blur(20px) saturate(180%) !important;
            -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
        }

        /* Profile cover gradient enhancement */
        .profile-cover {
            height: 200px;
            background: linear-gradient(135deg, #10b981 0%, #3b82f6 50%, #8b5cf6 100%);
            position: relative;
            overflow: hidden;
        }
        .profile-cover::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 60px;
            background: linear-gradient(to top, var(--card-bg), transparent);
        }

        /* Avatar ring glow */
        .profile-avatar {
            border: 4px solid var(--card-bg) !important;
            box-shadow: 0 0 0 3px var(--primary), 0 4px 20px rgba(16,185,129,0.25);
            position: relative;
            z-index: 2;
        }

        /* Post card hover enhancement */
        .post-card {
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
        }
        .post-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            border-color: rgba(16,185,129,0.2);
        }

        /* Better buttons with gradient */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            box-shadow: 0 2px 8px rgba(16,185,129,0.3);
        }
        .btn-primary:hover {
            box-shadow: 0 4px 16px rgba(16,185,129,0.4);
        }

        /* FAB button glow */
        .fab-button {
            background: linear-gradient(135deg, var(--primary), #059669);
            box-shadow: 0 4px 20px rgba(16,185,129,0.4);
        }
        .fab-button:hover {
            box-shadow: 0 6px 30px rgba(16,185,129,0.5);
        }

        /* Content card polish */
        .content-card {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .content-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: rgba(16,185,129,0.15);
        }

        /* Widget card subtle gradient bg */
        .widget-card {
            background: linear-gradient(135deg, var(--card-bg), var(--border-light));
        }
        .widget-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0,0,0,0.08);
        }

        /* Input focus glow */
        input:focus, textarea:focus, select:focus {
            outline: none;
            border-color: var(--primary) !important;
            box-shadow: 0 0 0 3px rgba(16,185,129,0.15) !important;
        }

        /* Role badge shine */
        .role-badge {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Tag hover effect */
        .tag {
            transition: all 0.2s ease;
        }
        .tag:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        /* Like button pulse on active */
        .like-btn.active i {
            text-shadow: 0 0 8px rgba(239,68,68,0.4);
        }

        /* Modal overlay improved */
        .modal {
            backdrop-filter: blur(8px);
        }
        .modal-content {
            box-shadow: 0 25px 60px rgba(0,0,0,0.15);
        }

        /* Notification bell animation */
        .notification-bell:has(.notification-badge:not(.hidden)) {
            animation: bellShake 0.5s ease-in-out;
        }
        @keyframes bellShake {
            0%, 100% { transform: rotate(0); }
            25% { transform: rotate(10deg); }
            75% { transform: rotate(-10deg); }
        }

        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, var(--border-light) 25%, var(--border) 50%, var(--border-light) 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
            border-radius: 8px;
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        .empty-state i {
            font-size: 48px;
            color: var(--border);
            margin-bottom: 16px;
        }
        .empty-state h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }
        .empty-state p {
            font-size: 14px;
            max-width: 300px;
            margin: 0 auto;
        }

        /* Badge indicators */
        .badge-new {
            position: absolute;
            top: -4px;
            right: -4px;
            width: 10px;
            height: 10px;
            background: var(--primary);
            border-radius: 50%;
            border: 2px solid var(--card-bg);
        }

        /* Improved mobile bottom spacing for FAB */
        @media (max-width: 768px) {
            .main { padding-bottom: 100px; }
            body { -webkit-tap-highlight-color: transparent; }
            .header { border-radius: 0; }
            .post-header { padding: 12px; }
            .post-content { padding: 12px; }
            .post-footer { padding: 10px 12px; }
        }

        /* Gradient text utility */
        .gradient-text {
            background: linear-gradient(135deg, var(--primary), #3b82f6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Page transition */
        .main {
            animation: pageEnter 0.3s ease;
        }
        @keyframes pageEnter {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Sticker card improvements */
        .sticker-card {
            border: 1px solid var(--border);
            background: linear-gradient(145deg, #fff, #f9fafb);
        }
        .sticker-card:hover {
            transform: scale(1.08);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
            border-color: var(--primary);
        }

        /* Notification item transition */
        .notification-item {
            transition: all 0.2s ease;
        }
        .notification-item-page {
            transition: all 0.2s ease;
        }
        .notification-item-page:hover {
            transform: translateX(4px);
        }

        /* Smooth image load */
        img { transition: opacity 0.3s ease; }
        img[src=""] { opacity: 0; }

        /* Print styles */
        @media print {
            .header, .fab-button, .mobile-menu, .notification-bell { display: none !important; }
            .main { margin-top: 0; }
        }
    </style>
    
    <?php if ($view === 'profile' && $profileUser): ?>
        <?php $settings = getUserProfileSettings($profileUser['id']); ?>
        <?php if (!empty($settings['custom_css'])): ?>
            <style><?php echo str_replace(['</style>', '<script', 'javascript:', 'expression('], '', $settings['custom_css']); ?></style>
        <?php endif; ?>
    <?php endif; ?>
</head>
<body>
    <!-- ============================================
         ШАПКА САЙТА Cryndel Как обычно моладец :)
    ============================================ -->
    <header class="header">
        <div class="container-wide">
            <div class="header-content">
                <a href="/" class="logo">
                    <img src="dder.png" alt="My Cryndel">
                    <span>My Cryndel</span>
                </a>
                
                <nav class="nav">
                    <a href="/" class="nav-link <?php echo $view === 'feed' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Лента</span>
                    </a>
                    <a href="/music.php" class="nav-link <?php echo $view === 'music' ? 'active' : ''; ?>">
                        <i class="fas fa-music"></i>
                        <span>Музыка</span>
                    </a>
                    <a href="/stickers.php" class="nav-link <?php echo $view === 'stickers' ? 'active' : ''; ?>">
                        <i class="fas fa-sticky-note"></i>
                        <span>Стикеры</span>
                    </a>
                    <a href="/coding.php" class="nav-link <?php echo $view === 'coding' ? 'active' : ''; ?>">
                        <i class="fas fa-code"></i>
                        <span>Кодинг</span>
                    </a>
                    <a href="/themes.php" class="nav-link <?php echo $view === 'themes' ? 'active' : ''; ?>">
                        <i class="fas fa-palette"></i>
                        <span>Оформление</span>
                    </a>
                    
                    <?php if ($user = fn_getCurrentUser()): ?>
                        <a href="/<?php echo $user['username']; ?>" class="nav-link <?php echo $view === 'profile' && $profileUser && $profileUser['id'] === $user['id'] ? 'active' : ''; ?>">
                            <img src="<?php echo getAvatarUrl($user); ?>" alt="" style="width: 24px; height: 24px; border-radius: 50%;">
                            <span>Профиль</span>
                        </a>
                    <?php else: ?>
                        <a href="/?action=login" class="nav-link <?php echo $view === 'login' ? 'active' : ''; ?>">
                            <i class="fas fa-sign-in-alt"></i>
                            <span>Вход</span>
                        </a>
                        <a href="/?action=register" class="nav-link <?php echo $view === 'register' ? 'active' : ''; ?>">
                            <i class="fas fa-user-plus"></i>
                            <span>Регистрация</span>
                        </a>
                    <?php endif; ?>
                </nav>
                
                <div class="nav-right">
                    <?php if ($user = fn_getCurrentUser()): ?>
                        <div class="notification-bell" id="notificationBell">
                            <i class="far fa-bell"></i>
                            <span class="notification-badge hidden" id="notificationBadge">0</span>
                            
                            <div class="notification-dropdown" id="notificationDropdown">
                                <div class="notification-header">
                                    <h3>Уведомления</h3>
                                    <button id="markAllRead">Прочитать все</button>
                                </div>
                                <div class="notification-list" id="notificationList">
                                    <div class="notification-empty">Загрузка...</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <button class="mobile-menu-btn" id="mobileMenuBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                </div>
            </div>
        </div>
    </header>
    
    <!-- Мобильное меню -->
    <div class="mobile-menu" id="mobileMenu">
        <div class="mobile-menu-header">
            <a href="/" class="logo">
                <img src="dder.png" alt="My Cryndel">
                <span>My Cryndel</span>
            </a>
            <button class="mobile-menu-close" id="mobileMenuClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <nav class="mobile-nav">
            <a href="/" class="mobile-nav-link <?php echo $view === 'feed' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                Лента
            </a>
            <a href="/music.php" class="mobile-nav-link <?php echo $view === 'music' ? 'active' : ''; ?>">
                <i class="fas fa-music"></i>
                Музыка
            </a>
            <a href="/stickers.php" class="mobile-nav-link <?php echo $view === 'stickers' ? 'active' : ''; ?>">
                <i class="fas fa-sticky-note"></i>
                Стикеры
            </a>
            <a href="/coding.php" class="mobile-nav-link <?php echo $view === 'coding' ? 'active' : ''; ?>">
                <i class="fas fa-code"></i>
                Кодинг
            </a>
            <a href="/themes.php" class="mobile-nav-link <?php echo $view === 'themes' ? 'active' : ''; ?>">
                <i class="fas fa-palette"></i>
                Оформление
            </a>
            
            <?php if ($user = fn_getCurrentUser()): ?>
                <a href="/?action=notifications" class="mobile-nav-link <?php echo $view === 'notifications_page' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    Уведомления
                </a>
                
                <a href="/?action=my_posts" class="mobile-nav-link <?php echo $view === 'my_posts' ? 'active' : ''; ?>">
                    <i class="fas fa-file-alt"></i>
                    Мои посты
                </a>
                
                <a href="/<?php echo $user['username']; ?>" class="mobile-nav-link <?php echo $view === 'profile' && $profileUser && $profileUser['id'] === $user['id'] ? 'active' : ''; ?>">
                    <img src="<?php echo getAvatarUrl($user); ?>" alt="" style="width: 24px; height: 24px; border-radius: 50%;">
                    Профиль
                </a>
                
                <div class="mobile-user-info">
                    <img src="<?php echo getAvatarUrl($user); ?>" alt="" class="mobile-avatar">
                    <div>
                        <div class="mobile-username">@<?php echo $user['username']; ?></div>
                        <div class="mobile-role">
                            <?php
                            if (!empty($user['role'])) {
                                $roleNames = [
                                    'admin' => 'Админ',
                                    'content_creator' => 'Создатель контента',
                                    'tech_admin' => 'Тех админ',
                                    'legendary' => 'Легендарный игрок',
                                    'mythic' => 'Мифический игрок',
                                    'golden' => 'Золотой игрок'
                                ];
                                echo $roleNames[$user['role']] ?? '';
                            }
                            ?>
                        </div>
                    </div>
                </div>
                
                <form method="post" action="/" style="margin-top: 20px;">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn-outline btn-block">
                        <i class="fas fa-sign-out-alt"></i>
                        Выйти
                    </button>
                </form>
            <?php else: ?>
                <a href="/?action=login" class="mobile-nav-link <?php echo $view === 'login' ? 'active' : ''; ?>">
                    <i class="fas fa-sign-in-alt"></i>
                    Вход
                </a>
                <a href="/?action=register" class="mobile-nav-link <?php echo $view === 'register' ? 'active' : ''; ?>">
                    <i class="fas fa-user-plus"></i>
                    Регистрация
                </a>
            <?php endif; ?>
        </nav>
    </div>
    
    <!-- Основной контент -->
    <main class="main">
        <div class="container">
            <?php $messages = getMessages(); if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="alert alert-success animate-fade">
                        <i class="fas fa-check-circle"></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php $errors = getErrors(); if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-error animate-fade">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <?php
            if ($view === 'login'):
            ?>
                <div class="form-container animate-fade">
                    <h1 class="form-title">Вход в аккаунт</h1>
                    
                    <form method="post" action="/">
                        <input type="hidden" name="action" value="login">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Имя пользователя</label>
                            <input type="text" id="username" name="username" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-sign-in-alt"></i>
                            Войти
                        </button>
                        
                        <div class="text-center mt-4">
                            <span class="text-light">Нет аккаунта?</span>
                            <a href="/?action=register" class="text-primary">Зарегаться ^_~</a>
                        </div>
                    </form>
                </div>
            
            <?php
            elseif ($view === 'register'):
            ?>
                <div class="form-container animate-fade">
                    <h1 class="form-title">Регистрация</h1>
                    
                    <form method="post" action="/">
                        <input type="hidden" name="action" value="register">
                        
                        <div class="form-group">
                            <label for="username" class="form-label">Имя пользователя</label>
                            <input type="text" id="username" name="username" class="form-input" required>
                            <div class="form-help">Только буквы, цифры и подчеркивания :]</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password" class="form-label">Пароль</label>
                            <input type="password" id="password" name="password" class="form-input" required>
                            <div class="form-help">Минимум 6 символов, НЕ используйте свой пароль от акаунта на сервер!</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Подтвердите пароль</label>
                            <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                        </div>
                        
                        <div class="alert alert-success mb-4">
                            <i class="fas fa-info-circle"></i>
                            <span>Не забудьте ваш пароль :)</span>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-block">
                            <i class="fas fa-user-plus"></i>
                            Зарегистрироваться
                        </button>
                        
                        <div class="text-center mt-4">
                            <span class="text-light">Уже есть аккаунт?</span>
                            <a href="/?action=login" class="text-primary">Войти</a>
                        </div>
                    </form>
                </div>
            
            <?php
            elseif ($view === 'create_post'):
                $user = fn_getCurrentUser();
                if (!$user['verified']):
            ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>Ваш аккаунт еще не подтвержден. Напишите @Cryndel для активации.</span>
                </div>
            <?php else: ?>
                <div class="form-container" style="max-width: 800px;">
                    <h1 class="form-title"><?php echo $editPost ? 'Редактировать пост' : 'Создать пост'; ?></h1>
                    
                    <form method="post" action="/" id="postForm" onsubmit="updateContent()">
                        <input type="hidden" name="action" value="save_post">
                        <?php if ($editPost): ?>
                            <input type="hidden" name="is_editing" value="true">
                            <input type="hidden" name="original_slug" value="<?php echo $editPost['slug']; ?>">
                        <?php endif; ?>
                        
                        <div style="margin-bottom: 30px;">
                            <h3 style="margin-bottom: 20px;">Основное</h3>
                            
                            <div class="form-group">
                                <label for="title" class="form-label">Заголовок</label>
                                <input type="text" id="title" name="title" class="form-input" required maxlength="200" value="<?php echo $editPost ? htmlspecialchars($editPost['title']) : ''; ?>">
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Текст поста</label>
                                <div class="editor-toolbar">
                                    <button type="button" class="editor-btn" onclick="formatText('bold')" title="Жирный"><i class="fas fa-bold"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('italic')" title="Курсив"><i class="fas fa-italic"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('underline')" title="Подчеркнутый"><i class="fas fa-underline"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('strikeThrough')" title="Зачеркнутый"><i class="fas fa-strikethrough"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('justifyLeft')" title="По левому краю"><i class="fas fa-align-left"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('justifyCenter')" title="По центру"><i class="fas fa-align-center"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('justifyRight')" title="По правому краю"><i class="fas fa-align-right"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('insertUnorderedList')" title="Маркированный список"><i class="fas fa-list-ul"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('insertOrderedList')" title="Нумерованный список"><i class="fas fa-list-ol"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('formatBlock', '<h1>')" title="Заголовок 1">H1</button>
                                    <button type="button" class="editor-btn" onclick="formatText('formatBlock', '<h2>')" title="Заголовок 2">H2</button>
                                    <button type="button" class="editor-btn" onclick="formatText('formatBlock', '<h3>')" title="Заголовок 3">H3</button>
                                    <button type="button" class="editor-btn" onclick="insertLink()" title="Вставить ссылку"><i class="fas fa-link"></i></button>
                                    <button type="button" class="editor-btn" onclick="insertImage()" title="Вставить изображение"><i class="fas fa-image"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('insertHorizontalRule')" title="Линия"><i class="fas fa-minus"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('undo')" title="Отменить"><i class="fas fa-undo"></i></button>
                                    <button type="button" class="editor-btn" onclick="formatText('redo')" title="Повторить"><i class="fas fa-redo"></i></button>
                                </div>
                                <div id="editor" class="editor-content" contenteditable="true"><?php echo $editPost ? $editPost['content'] : ''; ?></div>
                                <textarea id="content" name="content" class="hidden"><?php echo $editPost ? htmlspecialchars($editPost['content']) : ''; ?></textarea>
                                <div class="form-help">
                                    <span id="content-counter">0</span>/<?php echo MAX_POST_LENGTH; ?> символов
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="tags" class="form-label">Теги (через запятую)</label>
                                <input type="text" id="tags" name="tags" class="form-input" placeholder="minecraft, smp, новости" value="<?php echo $editPost ? htmlspecialchars(implode(', ', $editPost['tags'] ?? [])) : ''; ?>">
                            </div>
                        </div>
                        
                        <details style="margin-bottom: 30px;">
                            <summary style="cursor: pointer; color: var(--primary); font-weight: 500; margin-bottom: 15px;">
                                Расширенные настройки
                            </summary>
                            
                            <div style="padding: 20px; background: var(--border-light); border-radius: var(--radius);">
                                <div class="form-group">
                                    <label for="slug" class="form-label">URL поста</label>
                                    <input type="text" id="slug" name="slug" class="form-input" placeholder="оставьте пустым для автогенерации" value="<?php echo $editPost ? htmlspecialchars($editPost['slug']) : ''; ?>">
                                    <div class="form-help">Только латиница, дефисы и цифры</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="meta_title" class="form-label">SEO заголовок</label>
                                    <input type="text" id="meta_title" name="meta_title" class="form-input" value="<?php echo $editPost ? htmlspecialchars($editPost['meta_title'] ?? '') : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="meta_description" class="form-label">SEO описание</label>
                                    <textarea id="meta_description" name="meta_description" class="form-input" rows="3"><?php echo $editPost ? htmlspecialchars($editPost['meta_description'] ?? '') : ''; ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="meta_keywords" class="form-label">SEO ключевые слова</label>
                                    <input type="text" id="meta_keywords" name="meta_keywords" class="form-input" value="<?php echo $editPost ? htmlspecialchars($editPost['meta_keywords'] ?? '') : ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="custom_css" class="form-label">Свой CSS</label>
                                    <textarea id="custom_css" name="custom_css" class="form-input" rows="5" style="font-family: monospace;"><?php echo $editPost ? htmlspecialchars($editPost['custom_css'] ?? '') : ''; ?></textarea>
                                </div>
                            </div>
                        </details>
                        
                        <div class="flex gap-4">
                            <button type="submit" class="btn btn-primary flex-1">
                                <i class="fas fa-paper-plane"></i>
                                <?php echo $editPost ? 'Обновить' : 'Запостить'; ?>
                            </button>
                            <a href="/" class="btn btn-outline flex-1">
                                Отмена
                            </a>
                        </div>
                    </form>
                </div>
                
                <script>
                    function formatText(command, value = null) {
                        document.getElementById('editor').focus();
                        document.execCommand(command, false, value);
                        updateContent();
                    }
                    
                    function insertLink() {
                        const url = prompt('Введите URL:');
                        if (url) {
                            formatText('createLink', url);
                        }
                    }
                    
                    function insertImage() {
                        const url = prompt('Введите URL изображения:');
                        if (url) {
                            formatText('insertImage', url);
                        }
                    }
                    
                    function updateContent() {
                        const editor = document.getElementById('editor');
                        const content = document.getElementById('content');
                        const counter = document.getElementById('content-counter');
                        
                        content.value = editor.innerHTML;
                        counter.textContent = editor.innerText.length;
                    }
                    
                    document.getElementById('title').addEventListener('input', function() {
                        const slugInput = document.getElementById('slug');
                        if (!slugInput.value && !<?php echo $editPost ? 'true' : 'false'; ?>) {
                            let slug = this.value.toLowerCase()
                                .replace(/[^a-z0-9]+/g, '-')
                                .replace(/^-|-$/g, '');
                            slugInput.value = slug || '';
                        }
                    });
                    
                    document.getElementById('editor').addEventListener('input', updateContent);
                    document.getElementById('editor').addEventListener('keyup', updateContent);

                    // Command suggestion menus for triggers: @@, @, §, ;, ~, &
                    function initCommandMenus() {
                        const editor = document.getElementById('editor');
                        if (!editor) return;
                        let box = null;
                        const triggers = {
                            '@@': ['embed:video', 'embed:playlist', 'embed:track'],
                            '@': ['@alice', '@bob', '@charlie'],
                            '§': ['sticker:happy', 'sticker:sad', 'sticker:funny'],
                            ';': [';code:js', ';code:py', ';code:html'],
                            '~': ['~topic:minecraft', '~topic:servers', '~topic:mods'],
                            '&': ['&all', '&mods', '&admins']
                        };

                        function hideBox() { if (box && box.parentNode) box.parentNode.removeChild(box); box = null; }

                        editor.addEventListener('keyup', function(e) {
                            const sel = window.getSelection();
                            if (!sel.rangeCount) { hideBox(); return; }
                            const range = sel.getRangeAt(0).cloneRange();
                            range.collapse(true);
                            // get up to 40 chars before caret
                            range.setStart(range.startContainer, Math.max(0, range.startOffset - 40));
                            const text = range.toString();
                            const m = text.match(/(@@|@|§|;|~|&)([\w-]*)$/);
                            if (!m) { hideBox(); return; }
                            const trigger = m[1];
                            const q = m[2] || '';
                            const list = triggers[trigger] || [];
                            const matches = list.filter(i => i.toLowerCase().includes(q.toLowerCase())).slice(0,8);
                            if (!matches.length) { hideBox(); return; }

                            if (!box) {
                                box = document.createElement('div'); box.className = 'cmd-suggestions';
                                const ul = document.createElement('ul'); box.appendChild(ul);
                                document.body.appendChild(box);
                            }
                            const ul = box.querySelector('ul'); ul.innerHTML = '';
                            matches.forEach((it, idx) => {
                                const li = document.createElement('li'); li.textContent = it; li.dataset.value = it; li.tabIndex = 0;
                                li.addEventListener('click', () => select(it, trigger, q));
                                ul.appendChild(li);
                            });

                            // position near caret
                            const rect = sel.getRangeAt(0).getBoundingClientRect();
                            const top = (rect.bottom || rect.top) + window.scrollY + 6;
                            const left = (rect.left || rect.right) + window.scrollX;
                            box.style.top = top + 'px'; box.style.left = Math.max(8, left) + 'px';
                        });

                        editor.addEventListener('keydown', function(e) {
                            if (!box) return;
                            if (e.key === 'Escape') { hideBox(); return; }
                            if (e.key === 'Enter') {
                                e.preventDefault(); const first = box.querySelector('li'); if (first) first.click();
                            }
                        });

                        document.addEventListener('click', function(ev) {
                            if (box && !box.contains(ev.target) && ev.target.id !== 'editor') hideBox();
                        });

                        function select(value, trigger, query) {
                            const sel = window.getSelection(); if (!sel.rangeCount) return;
                            const r = sel.getRangeAt(0);
                            // replace trigger+query before caret
                            const start = r.startOffset - (trigger.length + (query ? query.length : 0));
                            try {
                                const node = r.startContainer;
                                if (node.nodeType === Node.TEXT_NODE) {
                                    const text = node.textContent;
                                    const before = text.slice(0, start);
                                    const after = text.slice(r.startOffset);
                                    const newText = before + value + ' ' + after;
                                    node.textContent = newText;
                                    // set caret after inserted value
                                    const newPos = before.length + value.length + 1;
                                    const newRange = document.createRange();
                                    newRange.setStart(node, newPos); newRange.collapse(true);
                                    sel.removeAllRanges(); sel.addRange(newRange);
                                    updateContent();
                                }
                            } catch (err) { console.error(err); }
                            hideBox();
                        }
                    }

                    initCommandMenus();
                    document.getElementById('editor').addEventListener('paste', function() {
                        setTimeout(updateContent, 10);
                    });
                    
                    updateContent();
                </script>
            <?php endif; ?>
            
            <?php
            elseif ($view === 'my_posts'):
                $user = getCurrentUser();
                $posts = getUserPosts($user['id']);
            ?>
                <div style="margin-bottom: 20px;">
                    <div class="flex justify-between items-center">
                        <h1 style="font-size: 24px; font-weight: 700;">Мои посты</h1>
                        <?php if ($user['verified']): ?>
                            <a href="/?action=create_post" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Новый пост
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($posts)): ?>
                    <div class="post-card" style="padding: 40px; text-align: center;">
                        <i class="fas fa-file-alt" style="font-size: 48px; color: var(--text-light); margin-bottom: 20px;"></i>
                        <h3 style="margin-bottom: 10px;">У тебя пока нед постов O.O</h3>
                        <p style="color: var(--text-light); margin-bottom: 20px;">Напиши свой первый пост :D</p>
                        <?php if ($user['verified']): ?>
                            <a href="/?action=create_post" class="btn btn-primary">
                                Создать пост
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <?php $roleStyle = getRoleStyle($post['role'] ?? ''); ?>
                        <div class="post-card role-<?php echo $post['role'] ?? ''; ?>">
                            <div class="post-header">
                                <img src="<?php echo $post['avatar']; ?>" alt="" class="post-avatar">
                                <div class="post-author-info">
                                    <div class="post-author">
                                        <a href="/<?php echo $post['username']; ?>" class="post-author-name">
                                            @<?php echo $post['username']; ?>
                                        </a>
                                        <?php if (!empty($post['role'])): ?>
                                            <?php echo $roleStyle['badge']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-date"><?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="post-content">
                                <a href="/post/<?php echo $post['slug']; ?>" class="post-title">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                                
                                <div class="post-text">
                                    <?php 
                                    $text = strip_tags($post['content']);
                                    if (mb_strlen($text) > 300) {
                                        echo nl2br(htmlspecialchars(mb_substr($text, 0, 300))) . '...';
                                        echo '<a href="/post/' . $post['slug'] . '" class="read-more">Развернуть <i class="fas fa-arrow-right"></i></a>';
                                    } else {
                                        echo $post['content'];
                                    }
                                    ?>
                                </div>
                            </div>
                            
                            <div class="post-footer">
                                <div class="post-tags">
                                    <?php echo $post['tags_html']; ?>
                                </div>
                                
                                <div class="flex gap-2">
                                    <a href="/?action=create_post&edit=<?php echo $post['slug']; ?>" class="btn btn-outline" style="padding: 8px 16px;">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <form method="post" action="/" onsubmit="return confirm('Ты хочешь удалить свой постик ~_~?')">
                                        <input type="hidden" name="action" value="delete_post">
                                        <input type="hidden" name="slug" value="<?php echo $post['slug']; ?>">
                                        <button type="submit" class="btn btn-outline" style="padding: 8px 16px;">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            
            <?php
            elseif ($view === 'profile' && $profileUser):
                $userPosts = getUserPosts($profileUser['id']);
                $totalViews = array_sum(array_column($userPosts, 'views'));
                $totalLikes = array_sum(array_column($userPosts, 'likes'));
                $roleStyle = getRoleStyle($profileUser['role'] ?? '');
                $settings = getUserProfileSettings($profileUser['id']);
                $medals = $settings['medals'] ?? [];
            ?>
                <!-- Cover -->
                <div class="profile-cover" style="<?php echo !empty($settings['cover_css']) ? htmlspecialchars($settings['cover_css']) : ''; ?>">
                    <?php if (!empty($settings['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($settings['cover_image']); ?>" alt="">
                    <?php endif; ?>
                    <?php if (getCurrentUser() && getCurrentUser()['id'] === $profileUser['id']): ?>
                        <button class="profile-settings-btn" onclick="switchTab('profile-settings')" title="Настройки">
                            <i class="fas fa-cog"></i>
                        </button>
                    <?php endif; ?>
                </div>
                
                <div class="profile-header">
                    <img src="<?php echo getAvatarUrl($profileUser); ?>" alt="" class="profile-avatar">
                    
                    <div class="profile-name">
                        @<?php echo $profileUser['username']; ?>
                        <?php if (!empty($profileUser['role'])): ?>
                            <span class="role-icon-wrap">
                                <?php echo $roleStyle['badge']; ?>
                                <span class="role-tooltip"><?php echo htmlspecialchars($profileUser['role']); ?></span>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($settings['server_nick'])): ?>
                            <span class="role-icon-wrap" title="Ник на сервере">
                                <i class="fas fa-gamepad" style="color:var(--primary);font-size:14px;"></i>
                                <span class="role-tooltip"><?php echo htmlspecialchars($settings['server_nick']); ?></span>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($settings['status'])): ?>
                        <div class="profile-status">
                            <span class="status-dot"></span>
                            <?php echo htmlspecialchars($settings['status']); ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="profile-bio">
                        <?php echo htmlspecialchars($profileUser['bio']); ?>
                    </div>
                    
                    <?php if (!empty(array_filter($profileUser['social']))): ?>
                        <div class="social-links">
                            <?php if (!empty($profileUser['social']['website'])): ?>
                                <a href="<?php echo $profileUser['social']['website']; ?>" class="social-link" target="_blank">
                                    <i class="fas fa-globe"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($profileUser['social']['twitter'])): ?>
                                <a href="https://twitter.com/<?php echo $profileUser['social']['twitter']; ?>" class="social-link" target="_blank">
                                    <i class="fab fa-twitter"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($profileUser['social']['instagram'])): ?>
                                <a href="https://instagram.com/<?php echo $profileUser['social']['instagram']; ?>" class="social-link" target="_blank">
                                    <i class="fab fa-instagram"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($profileUser['social']['vk'])): ?>
                                <a href="https://vk.com/<?php echo $profileUser['social']['vk']; ?>" class="social-link" target="_blank">
                                    <i class="fab fa-vk"></i>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($profileUser['social']['telegram'])): ?>
                                <a href="https://t.me/<?php echo $profileUser['social']['telegram']; ?>" class="social-link" target="_blank">
                                    <i class="fab fa-telegram"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (getCurrentUser() && getCurrentUser()['id'] === $profileUser['id']): ?>
                        <div style="margin-top: 20px;">
                            <button onclick="toggleModal('edit-profile-modal')" class="btn btn-outline">
                                <i class="fas fa-edit"></i>
                                Редактировать профиль
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?php echo count($userPosts); ?></div>
                        <div class="stat-label">Постов</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $totalViews; ?></div>
                        <div class="stat-label">Просмотров</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo $totalLikes; ?></div>
                        <div class="stat-label">Лайков</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?php echo date('d.m.Y', strtotime($profileUser['created_at'])); ?></div>
                        <div class="stat-label">На сервере</div>
                    </div>
                </div>
                
                <?php
                // Stats summary for post author (only visible to owner)
                $isOwner = getCurrentUser() && getCurrentUser()['id'] === $profileUser['id'];
                $totalComments = 0;
                foreach ($userPosts as $p) { $totalComments += $p['comments_count'] ?? 0; }
                if ($isOwner && ($totalLikes > 0 || $totalComments > 0)):
                ?>
                    <div class="profile-stats-summary">
                        +<?php echo $totalLikes; ?> лайков и <?php echo $totalComments; ?> комментариев на ваши посты
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($medals)): ?>
                    <div style="margin-bottom: 20px;">
                        <h3 style="margin-bottom: 15px;">Медали</h3>
                        <div class="medals-grid">
                            <?php foreach ($medals as $medal): ?>
                                <div class="medal-card">
                                    <div class="medal-icon">
                                        <i class="<?php echo $medal['icon']; ?>"></i>
                                    </div>
                                    <div class="medal-name"><?php echo htmlspecialchars($medal['name']); ?></div>
                                    <div class="medal-description"><?php echo htmlspecialchars($medal['description']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Widgets -->
                <?php
                $userMusicFiles = glob(MUSIC_DIR . '*.json');
                $userMusic = [];
                foreach ($userMusicFiles as $mf) {
                    $md = json_decode(file_get_contents($mf), true);
                    if ($md && ($md['user_id'] ?? '') === $profileUser['id']) $userMusic[] = $md;
                }
                $userStickerFiles = glob(STICKERS_DIR . '*.json');
                $userStickers = [];
                foreach ($userStickerFiles as $sf) {
                    $sd = json_decode(file_get_contents($sf), true);
                    if ($sd && ($sd['user_id'] ?? '') === $profileUser['id']) $userStickers[] = $sd;
                }
                $userCodingFiles = glob(CODING_DIR . '*.json');
                $userCoding = [];
                foreach ($userCodingFiles as $cf) {
                    $cd = json_decode(file_get_contents($cf), true);
                    if ($cd && ($cd['user_id'] ?? '') === $profileUser['id']) $userCoding[] = $cd;
                }
                ?>
                
                <?php if (!empty($settings['widget_music']) && !empty($userMusic)): ?>
                    <div class="widget-card" onclick="playMusic('<?php echo htmlspecialchars($userMusic[0]['file'] ?? ''); ?>')">
                        <div class="widget-icon"><i class="fas fa-music"></i></div>
                        <div class="widget-info">
                            <div class="widget-title"><?php echo htmlspecialchars($userMusic[0]['title'] ?? 'Музыка'); ?></div>
                            <div class="widget-meta">Виджет музыки</div>
                        </div>
                        <i class="fas fa-play" style="color:var(--primary);"></i>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($settings['widget_sticker']) && count($userStickers) >= 20): ?>
                    <div class="widget-card">
                        <div class="widget-icon"><i class="fas fa-sticky-note"></i></div>
                        <div class="widget-info">
                            <div class="widget-title">Любимый стикерпак</div>
                            <div class="widget-meta"><?php echo count($userStickers); ?> стикеров</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($settings['widget_build']) && count($userCoding) >= 3): ?>
                    <div class="widget-card">
                        <div class="widget-icon"><i class="fas fa-cubes"></i></div>
                        <div class="widget-info">
                            <div class="widget-title">Моя сборка</div>
                            <div class="widget-meta"><?php echo count($userCoding); ?> загрузок</div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="tabs-icons">
                    <?php if (!empty($userPosts)): ?>
                        <button class="tab-icon active" onclick="switchTab('profile-posts')" style="position:relative;">
                            <i class="fas fa-file-alt"></i>
                            <span class="tab-tooltip">Посты</span>
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($userMusic)): ?>
                        <button class="tab-icon" onclick="switchTab('profile-music')" style="position:relative;">
                            <i class="fas fa-music"></i>
                            <span class="tab-tooltip">Музыка</span>
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($userStickers)): ?>
                        <button class="tab-icon" onclick="switchTab('profile-stickers')" style="position:relative;">
                            <i class="fas fa-sticky-note"></i>
                            <span class="tab-tooltip">Стикеры</span>
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($userCoding)): ?>
                        <button class="tab-icon" onclick="switchTab('profile-coding')" style="position:relative;">
                            <i class="fas fa-code"></i>
                            <span class="tab-tooltip">Кодинг</span>
                        </button>
                    <?php endif; ?>
                    <button class="tab-icon" onclick="switchTab('profile-likes')" style="position:relative;">
                        <i class="fas fa-heart"></i>
                        <span class="tab-tooltip">Нравится</span>
                    </button>
                    <?php if ($isOwner): ?>
                        <button class="tab-icon" onclick="switchTab('profile-settings')" style="position:relative;">
                            <i class="fas fa-cog"></i>
                            <span class="tab-tooltip">Настройки</span>
                        </button>
                    <?php endif; ?>
                </div>
                
                <div id="profile-posts" class="tab-content <?php echo !empty($userPosts) || empty($userMusic) ? 'active' : ''; ?>">
                    <?php if (empty($userPosts)): ?>
                        <div class="post-card" style="padding: 40px; text-align: center;">
                            <i class="fas fa-file-alt" style="font-size: 48px; color: var(--text-light); margin-bottom: 20px;"></i>
                            <h3>Нет постов</h3>
                            <p style="color: var(--text-light);">Пользователь пока ничего не написал..</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($userPosts as $post): ?>
                            <?php $postRoleStyle = getRoleStyle($post['role'] ?? ''); ?>
                            <div class="post-card role-<?php echo $post['role'] ?? ''; ?>">
                                <div class="post-header">
                                    <img src="<?php echo $post['avatar']; ?>" alt="" class="post-avatar">
                                    <div class="post-author-info">
                                        <div class="post-author">
                                            <a href="/<?php echo $post['username']; ?>" class="post-author-name">
                                                @<?php echo $post['username']; ?>
                                            </a>
                                            <?php if (!empty($post['role'])): ?>
                                                <?php echo $postRoleStyle['badge']; ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="post-date"><?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></div>
                                    </div>
                                </div>
                                
                                <div class="post-content">
                                    <a href="/post/<?php echo $post['slug']; ?>" class="post-title">
                                        <?php echo htmlspecialchars($post['title']); ?>
                                    </a>
                                    
                                    <div class="post-text">
                                        <?php 
                                        $text = strip_tags($post['content']);
                                        if (mb_strlen($text) > 300) {
                                            echo nl2br(htmlspecialchars(mb_substr($text, 0, 300))) . '...';
                                            echo '<a href="/post/' . $post['slug'] . '" class="read-more">Читать далее <i class="fas fa-arrow-right"></i></a>';
                                        } else {
                                            echo $post['content'];
                                        }
                                        ?>
                                    </div>
                                </div>
                                
                                <div class="post-footer">
                                    <div class="post-tags">
                                        <?php echo $post['tags_html']; ?>
                                    </div>
                                    
                                    <div class="post-stats">
                                        <span class="stat"><i class="far fa-eye"></i> <?php echo $post['views']; ?></span>
                                        <span class="stat"><i class="far fa-heart"></i> <?php echo $post['likes']; ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Music Tab -->
                <div id="profile-music" class="tab-content">
                    <?php if (empty($userMusic)): ?>
                        <div class="post-card" style="padding:30px;text-align:center;">
                            <i class="fas fa-music" style="font-size:48px;color:var(--text-light);margin-bottom:15px;"></i>
                            <p style="color:var(--text-light);">Тут пока пусто</p>
                        </div>
                    <?php else: ?>
                        <div class="content-grid">
                        <?php foreach ($userMusic as $m): ?>
                            <div class="content-card music-card">
                                <div class="content-card-icon">
                                    <?php if (!empty($m['icon'])): ?><img src="<?php echo htmlspecialchars($m['icon']); ?>" alt=""><?php else: ?><i class="fas fa-music"></i><?php endif; ?>
                                </div>
                                <div class="content-card-info">
                                    <div class="content-card-title"><?php echo htmlspecialchars($m['title'] ?? ''); ?></div>
                                </div>
                                <div class="content-card-actions">
                                    <?php if (!empty($m['file'])): ?>
                                        <button class="btn btn-sm btn-outline" onclick="playMusic('<?php echo htmlspecialchars($m['file']); ?>')"><i class="fas fa-play"></i></button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Stickers Tab -->
                <div id="profile-stickers" class="tab-content">
                    <?php if (empty($userStickers)): ?>
                        <div class="post-card" style="padding:30px;text-align:center;">
                            <i class="fas fa-sticky-note" style="font-size:48px;color:var(--text-light);margin-bottom:15px;"></i>
                            <p style="color:var(--text-light);">Тут пока пусто</p>
                        </div>
                    <?php else: ?>
                        <div class="sticker-grid">
                        <?php foreach ($userStickers as $s): ?>
                            <div class="sticker-card" onclick="openStickerModal(this)" data-info='<?php echo htmlspecialchars(json_encode($s)); ?>'>
                                <?php if (!empty($s['file'])): ?>
                                    <img src="<?php echo htmlspecialchars($s['file']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-sticky-note" style="font-size:48px;"></i>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Coding Tab -->
                <div id="profile-coding" class="tab-content">
                    <?php if (empty($userCoding)): ?>
                        <div class="post-card" style="padding:30px;text-align:center;">
                            <i class="fas fa-code" style="font-size:48px;color:var(--text-light);margin-bottom:15px;"></i>
                            <p style="color:var(--text-light);">Тут пока пусто</p>
                        </div>
                    <?php else: ?>
                        <div class="content-grid">
                        <?php foreach ($userCoding as $c): ?>
                            <div class="content-card coding-card">
                                <div class="content-card-icon"><i class="fas fa-file-code"></i></div>
                                <div class="content-card-info">
                                    <div class="content-card-title"><?php echo htmlspecialchars($c['title'] ?? ''); ?></div>
                                </div>
                                <div class="content-card-actions">
                                    <?php if (!empty($c['file'])): ?>
                                        <a href="<?php echo htmlspecialchars($c['file']); ?>" class="btn btn-sm btn-primary" download><i class="fas fa-download"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Profile Likes Tab -->
                <div id="profile-likes" class="tab-content">
                    <div class="post-card" style="padding:30px;text-align:center;">
                        <i class="fas fa-heart" style="font-size:48px;color:var(--text-light);margin-bottom:15px;"></i>
                        <p style="color:var(--text-light);">Посты которые понравились</p>
                    </div>
                </div>
                
                <?php if (getCurrentUser() && getCurrentUser()['id'] === $profileUser['id']): ?>
                    <div id="profile-settings" class="tab-content">
                        <div class="post-card" style="padding: 30px;">
                            <h3 style="margin-bottom: 20px;">Настройки профиля</h3>
                            
                            <form method="post" action="/" enctype="multipart/form-data" style="margin-bottom: 30px;">
                                <input type="hidden" name="action" value="upload_avatar">
                                
                                <div class="form-group">
                                    <label class="form-label">Аватар</label>
                                    <div class="file-upload">
                                        <span class="file-upload-btn">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            Выберите файл
                                        </span>
                                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" id="avatarInput">
                                    </div>
                                    <div class="form-help">Максимальный размер: 1MB. Поддерживаются GIF анимации.</div>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-upload"></i>
                                    Загрузить аватар
                                </button>
                            </form>
                            
                            <form method="post" action="/" style="margin-bottom: 30px;">
                                <input type="hidden" name="action" value="update_profile">
                                
                                <div class="form-group">
                                    <label for="bio" class="form-label">О себе</label>
                                    <textarea id="bio" name="bio" class="form-input"><?php echo htmlspecialchars($profileUser['bio']); ?></textarea>
                                </div>
                                
                                <h4 style="margin: 20px 0 10px;">Социальные сети</h4>
                                
                                <div class="form-group">
                                    <label for="website" class="form-label">Веб-сайт</label>
                                    <input type="url" id="website" name="website" class="form-input" value="<?php echo $profileUser['social']['website'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="twitter" class="form-label">Twitter</label>
                                    <input type="text" id="twitter" name="twitter" class="form-input" value="<?php echo $profileUser['social']['twitter'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="instagram" class="form-label">Instagram</label>
                                    <input type="text" id="instagram" name="instagram" class="form-input" value="<?php echo $profileUser['social']['instagram'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="vk" class="form-label">VK</label>
                                    <input type="text" id="vk" name="vk" class="form-input" value="<?php echo $profileUser['social']['vk'] ?? ''; ?>">
                                </div>
                                
                                <div class="form-group">
                                    <label for="telegram" class="form-label">Telegram</label>
                                    <input type="text" id="telegram" name="telegram" class="form-input" value="<?php echo $profileUser['social']['telegram'] ?? ''; ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Сохранить
                                </button>
                            </form>
                            
                            <form method="post" action="/">
                                <input type="hidden" name="action" value="update_profile_settings">
                                
                                <h4 style="margin: 20px 0 10px;">Статус</h4>
                                <div class="form-group">
                                    <label for="status" class="form-label">Статус (как в Discord)</label>
                                    <input type="text" id="status" name="status" class="form-input" value="<?php echo htmlspecialchars($settings['status'] ?? ''); ?>" maxlength="100" placeholder="Играю в Minecraft...">
                                </div>
                                
                                <h4 style="margin: 20px 0 10px;">Обложка профиля</h4>
                                <div class="form-group">
                                    <label for="cover_css" class="form-label">CSS для обложки (градиент, цвет)</label>
                                    <input type="text" id="cover_css" name="cover_css" class="form-input" value="<?php echo htmlspecialchars($settings['cover_css'] ?? ''); ?>" placeholder="background: linear-gradient(135deg, #667eea, #764ba2)">
                                    <div class="gradient-presets" style="margin-top:8px;">
                                        <div class="gradient-preset" style="background: linear-gradient(135deg, #667eea, #764ba2)" onclick="document.getElementById('cover_css').value='background:linear-gradient(135deg,#667eea,#764ba2)'"></div>
                                        <div class="gradient-preset" style="background: linear-gradient(135deg, #f093fb, #f5576c)" onclick="document.getElementById('cover_css').value='background:linear-gradient(135deg,#f093fb,#f5576c)'"></div>
                                        <div class="gradient-preset" style="background: linear-gradient(135deg, #4facfe, #00f2fe)" onclick="document.getElementById('cover_css').value='background:linear-gradient(135deg,#4facfe,#00f2fe)'"></div>
                                        <div class="gradient-preset" style="background: linear-gradient(135deg, #43e97b, #38f9d7)" onclick="document.getElementById('cover_css').value='background:linear-gradient(135deg,#43e97b,#38f9d7)'"></div>
                                        <div class="gradient-preset" style="background: linear-gradient(135deg, #fa709a, #fee140)" onclick="document.getElementById('cover_css').value='background:linear-gradient(135deg,#fa709a,#fee140)'"></div>
                                    </div>
                                </div>
                                
                                <h4 style="margin: 20px 0 10px;">Виджеты</h4>
                                <div class="checkbox-group">
                                    <label>
                                        <input type="checkbox" name="widget_music" value="1" <?php echo !empty($settings['widget_music']) ? 'checked' : ''; ?>>
                                        Виджет музыки
                                    </label>
                                    <label>
                                        <input type="checkbox" name="widget_sticker" value="1" <?php echo !empty($settings['widget_sticker']) ? 'checked' : ''; ?>>
                                        Виджет стикеров
                                    </label>
                                    <label>
                                        <input type="checkbox" name="widget_build" value="1" <?php echo !empty($settings['widget_build']) ? 'checked' : ''; ?>>
                                        Виджет сборки
                                    </label>
                                </div>
                                
                                <h4 style="margin: 20px 0 10px;">Свой CSS</h4>
                                <div class="form-group">
                                    <label for="custom_css" class="form-label">CSS код для профиля</label>
                                    <textarea id="custom_css" name="custom_css" class="form-input" rows="8" style="font-family: monospace;"><?php 
                                        echo htmlspecialchars($settings['custom_css'] ?? '');
                                    ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Сохранить настройки
                                </button>
                            </form>
                            
                            <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border);">
                                <h4 style="margin-bottom: 10px;">Статистика на сервере</h4>
                                <a href="/sta.php" class="btn btn-outline">
                                    <i class="fas fa-chart-line"></i>
                                    Перейти к статистике
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            
            <?php
            elseif ($view === 'post_view' && $post):
                $roleStyle = getRoleStyle($post['role'] ?? '');
                $currentUser = getCurrentUser();
            ?>
                <div class="post-card role-<?php echo $post['role'] ?? ''; ?>" style="margin-bottom: 30px;">
                    <div class="post-header">
                        <img src="<?php echo $post['avatar']; ?>" alt="" class="post-avatar">
                        <div class="post-author-info">
                            <div class="post-author">
                                <a href="/<?php echo $post['username']; ?>" class="post-author-name">
                                    @<?php echo $post['username']; ?>
                                </a>
                                <?php if (!empty($post['role'])): ?>
                                    <?php echo $roleStyle['badge']; ?>
                                <?php endif; ?>
                            </div>
                            <div class="post-date"><?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <div class="post-content">
                        <h1 class="post-title"><?php echo htmlspecialchars($post['title']); ?></h1>
                        
                        <div class="post-text" style="font-size: 16px;">
                            <?php echo $post['content']; ?>
                        </div>
                    </div>
                    
                    <div class="post-footer">
                        <div class="post-tags">
                            <?php echo $post['tags_html']; ?>
                        </div>
                        
                        <div class="post-stats">
                            <span class="stat" id="views-stat"><i class="far fa-eye"></i> <span id="viewCount"><?php echo $post['views']; ?></span></span>
                            <button class="like-btn <?php echo ($currentUser && hasUserLikedPost($post['slug'], $currentUser['id'])) ? 'active' : ''; ?>" 
                                    data-slug="<?php echo $post['slug']; ?>">
                                <i class="<?php echo ($currentUser && hasUserLikedPost($post['slug'], $currentUser['id'])) ? 'fas' : 'far'; ?> fa-heart"></i>
                                <span class="like-count"><?php echo $post['likes']; ?></span>
                            </button>
                            <span class="stat comments-toggle-btn" onclick="toggleComments()">
                                <i class="far fa-comment"></i>
                                <span id="comments-count"><?php echo $post['comments_count']; ?></span>
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php if ($currentUser && ($post['user_id'] === $currentUser['id'] || $currentUser['role'] === 'admin')): ?>
                    <div class="flex gap-4" style="margin-bottom: 20px;">
                        <a href="/?action=create_post&edit=<?php echo $post['slug']; ?>" class="btn btn-outline flex-1">
                            <i class="fas fa-edit"></i>
                            Редактировать
                        </a>
                        <form method="post" action="/" class="flex-1" onsubmit="return confirm('Удалить пост?')">
                            <input type="hidden" name="action" value="delete_post">
                            <input type="hidden" name="slug" value="<?php echo $post['slug']; ?>">
                            <button type="submit" class="btn btn-outline w-full">
                                <i class="fas fa-trash"></i>
                                Удалить
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="comments-section">
                    <div class="comments-title">
                        <i class="far fa-comments"></i>
                        Комментарии
                    </div>
                    
                    <?php if ($currentUser): ?>
                        <div class="comment-form">
                            <div class="comment-input-wrapper">
                                <textarea id="comment-content" class="comment-input" placeholder="Напишите комментарий... Используйте @ для упоминания"></textarea>
                                <div class="mention-suggestions" id="mentionSuggestions"></div>
                            </div>
                            <div class="comment-submit">
                                <button class="btn btn-primary btn-small" onclick="addComment()">
                                    <i class="fas fa-paper-plane"></i>
                                    Отправить
                                </button>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info mb-4">
                            <a href="/?action=login">Войдите</a>, чтобы оставить комментарий ^_~
                        </div>
                    <?php endif; ?>
                    
                    <div id="comments-list" class="comments-list">
                        <div class="no-comments">Загрузка комментариев... </div>
                    </div>
                </div>
                
                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    let currentSlug = '<?php echo $post['slug']; ?>';
                    let currentUser = <?php echo $currentUser ? json_encode(['id' => $currentUser['id'], 'username' => $currentUser['username']]) : 'null'; ?>;
                    
                    window.toggleComments = function() {
                        loadComments();
                    };
                    
                    function loadComments() {
                        fetch('/?ajax=get_comments&slug=' + encodeURIComponent(currentSlug))
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    displayComments(data.comments);
                                    document.getElementById('comments-count').textContent = countComments(data.comments);
                                }
                            })
                            .catch(error => {
                                console.error('Error loading comments:', error);
                            });
                    }
                    
                    function countComments(comments) {
                        let count = comments.length;
                        comments.forEach(comment => {
                            if (comment.replies && comment.replies.length) {
                                count += countComments(comment.replies);
                            }
                        });
                        return count;
                    }
                    
                    function displayComments(comments, level = 0) {
                        const list = document.getElementById('comments-list');
                        
                        if (comments.length === 0 && level === 0) {
                            list.innerHTML = '<div class="no-comments">Пока нет комментариев. Будьте первым >"<</div>';
                            return;
                        }
                        
                        let html = '';
                        comments.forEach(comment => {
                            html += renderComment(comment, level);
                        });
                        
                        if (level === 0) {
                            list.innerHTML = html;
                        }
                        return html;
                    }
                    
                    function renderComment(comment, level) {
                        const hasReplies = comment.replies && comment.replies.length > 0;
                        const reactionsHtml = comment.reactions ? Object.entries(comment.reactions).map(([reaction, users]) => {
                            const active = currentUser && users.includes(currentUser.id);
                            return `<span class="reaction-badge ${active ? 'active' : ''}" onclick="reactToComment('${comment.id}', '${reaction}')">
                                ${reaction} ${users.length}
                            </span>`;
                        }).join('') : '';
                        
                        return `
                            <div class="comment-item level-${level}" data-id="${comment.id}">
                                <img src="${comment.avatar}" alt="" class="comment-avatar">
                                <div class="comment-bubble">
                                    <div class="comment-header">
                                        <a href="/${comment.username}" class="comment-author">@${comment.username}</a>
                                        <span class="comment-date">${comment.created_at}</span>
                                    </div>
                                    <div class="comment-text">${linkifyMentions(escapeHtml(comment.content))}</div>
                                    
                                    <div class="comment-actions">
                                        <button class="comment-action" onclick="toggleReplyForm('${comment.id}')">
                                             Ответить
                                        </button>
                                        <button class="comment-action" onclick="reactToComment('${comment.id}', '❤️')">
                                            <i class="far fa-heart"></i>
                                        </button>
                                    </div>
                                    
                                    ${reactionsHtml ? `<div class="comment-reactions">${reactionsHtml}</div>` : ''}
                                    
                                    ${currentUser && (currentUser.id === comment.user_id || currentUser.role === 'admin') ? `
                                        <button class="comment-delete" onclick="deleteComment('${comment.id}')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    ` : ''}
                                </div>
                            </div>
                            
                            <div id="reply-form-${comment.id}" class="reply-form">
                                <div class="comment-input-wrapper">
                                    <textarea id="reply-content-${comment.id}" class="comment-input" placeholder="Напишите ответ..."></textarea>
                                </div>
                                <div class="comment-submit">
                                    <button class="btn btn-primary btn-small" onclick="addReply('${comment.id}')">
                                        <i class="fas fa-paper-plane"></i>
                                        Ответить
                                    </button>
                                    <button class="btn btn-outline btn-small" onclick="toggleReplyForm('${comment.id}')">
                                        Отмена
                                    </button>
                                </div>
                            </div>
                            
                            ${hasReplies ? displayComments(comment.replies, level + 1) : ''}
                        `;
                    }
                    
                    window.linkifyMentions = function(text) {
                        return text.replace(/@([a-zA-Z0-9_]+)/g, '<a href="/$1">@$1</a>');
                    };
                    
                    window.escapeHtml = function(text) {
                        const div = document.createElement('div');
                        div.textContent = text;
                        return div.innerHTML;
                    };
                    
                    window.toggleReplyForm = function(commentId) {
                        const form = document.getElementById('reply-form-' + commentId);
                        form.classList.toggle('show');
                    };
                    
                    window.addComment = function(parentId = null) {
                        const contentInput = parentId ? 
                            document.getElementById('reply-content-' + parentId) : 
                            document.getElementById('comment-content');
                        
                        const content = contentInput.value.trim();
                        
                        if (!content) {
                            alert('Введите комментарий');
                            return;
                        }
                        
                        const formData = new FormData();
                        formData.append('action', 'add_comment');
                        formData.append('slug', currentSlug);
                        formData.append('content', content);
                        if (parentId) {
                            formData.append('parent_id', parentId);
                        }
                        
                        fetch('/', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                contentInput.value = '';
                                if (parentId) {
                                    document.getElementById('reply-form-' + parentId).classList.remove('show');
                                }
                                loadComments();
                            } else {
                                if (data.message === 'auth_required') {
                                    window.location.href = '/?action=login';
                                } else {
                                    alert(data.message || 'Ошибка');
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error adding comment:', error);
                            alert('Ошибка сети');
                        });
                    };
                    
                    window.addReply = function(parentId) {
                        addComment(parentId);
                    };
                    
                    window.deleteComment = function(commentId) {
                        if (!confirm('Удалить комментарий?')) return;
                        
                        const formData = new FormData();
                        formData.append('action', 'delete_comment');
                        formData.append('comment_id', commentId);
                        formData.append('slug', currentSlug);
                        
                        fetch('/', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                loadComments();
                            }
                        })
                        .catch(error => {
                            console.error('Error deleting comment:', error);
                            alert('Ошибка сети');
                        });
                    };
                    
                    window.reactToComment = function(commentId, reaction) {
                        <?php if (!$currentUser): ?>
                            window.location.href = '/?action=login';
                            return;
                        <?php endif; ?>
                        
                        const formData = new FormData();
                        formData.append('action', 'react_comment');
                        formData.append('comment_id', commentId);
                        formData.append('slug', currentSlug);
                        formData.append('reaction', reaction);
                        
                        fetch('/', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                loadComments();
                            }
                        })
                        .catch(error => {
                            console.error('Error reacting to comment:', error);
                        });
                    };
                    
                    // Упоминания (@)
                    const commentInput = document.getElementById('comment-content');
                    const suggestions = document.getElementById('mentionSuggestions');
                    
                    if (commentInput) {
                        let timeout;
                        commentInput.addEventListener('input', function() {
                            clearTimeout(timeout);
                            const text = this.value;
                            const lastAt = text.lastIndexOf('@');
                            
                            if (lastAt !== -1 && lastAt === text.length - 1) {
                                suggestions.classList.add('show');
                            } else if (lastAt !== -1 && lastAt < text.length - 1) {
                                const query = text.substring(lastAt + 1).split(' ')[0];
                                if (query.length >= 1) {
                                    timeout = setTimeout(() => {
                                        fetch('/?ajax=search_users&q=' + encodeURIComponent(query))
                                            .then(response => response.json())
                                            .then(users => {
                                                if (users.length) {
                                                    suggestions.innerHTML = users.map(user => `
                                                        <div class="mention-item" onclick="insertMention('${user.username}')">
                                                            <img src="${user.avatar}" alt="">
                                                            <span>@${user.username}</span>
                                                        </div>
                                                    `).join('');
                                                    suggestions.classList.add('show');
                                                } else {
                                                    suggestions.classList.remove('show');
                                                }
                                            });
                                    }, 300);
                                }
                            } else {
                                suggestions.classList.remove('show');
                            }
                        });
                    }
                    
                    window.insertMention = function(username) {
                        const text = commentInput.value;
                        const lastAt = text.lastIndexOf('@');
                        commentInput.value = text.substring(0, lastAt + 1) + username + ' ';
                        suggestions.classList.remove('show');
                        commentInput.focus();
                    };
                    
                    loadComments();
                    
                    <?php if (!isset($_SESSION['viewed_posts'][$post['slug']])): ?>
                    fetch('/?ajax=increment_views&slug=<?php echo $post['slug']; ?>')
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                const viewCount = document.getElementById('viewCount');
                                if (viewCount) {
                                    viewCount.textContent = parseInt(viewCount.textContent) + 1;
                                }
                            }
                        })
                        .catch(error => {
                            console.error('Error incrementing views:', error);
                        });
                    <?php endif; ?>
                    
                    fetch('/?ajax=check_like&slug=' + encodeURIComponent(currentSlug))
                        .then(response => response.json())
                        .then(data => {
                            const likeBtn = document.querySelector('.like-btn');
                            if (likeBtn && data.hasLiked) {
                                const icon = likeBtn.querySelector('i');
                                icon.classList.remove('far');
                                icon.classList.add('fas');
                                likeBtn.classList.add('active');
                            }
                        })
                        .catch(error => {
                            console.error('Error checking like:', error);
                        });
                });
                </script>
            
            <?php
            elseif ($view === 'feed'):
                $allPosts = getAllPosts();
            ?>
                <!-- Минималистичный поиск -->
                <div class="search-minimal">
                    <div class="search-trigger">
                        <button class="search-icon-btn" id="searchToggleBtn">
                            <i class="fas fa-search"></i>
                        </button>
                        <button class="settings-icon-btn" id="settingsToggleBtn">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                    
                    <div class="search-expanded" id="searchExpanded">
                        <div class="search-input-group">
                            <input type="text" id="searchQuery" placeholder="Поиск...">
                            <button onclick="performSearch()">
                                <i class="fas fa-arrow-right"></i>
                            </button>
                        </div>
                        
                        <div class="search-type">
                            <label>
                                <input type="radio" name="searchType" value="all" checked> Всё
                            </label>
                            <label>
                                <input type="radio" name="searchType" value="users"> Пользователи
                            </label>
                            <label>
                                <input type="radio" name="searchType" value="posts"> Посты
                            </label>
                        </div>
                        
                        <div id="searchResults" class="search-results"></div>
                    </div>
                </div>
                
                <!-- Модальное окно настроек ленты -->
                <div class="modal" id="settingsModal">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3 class="modal-title">Настройки ленты</h3>
                            <button class="modal-close" onclick="toggleSettingsModal()">&times;</button>
                        </div>
                        <div class="modal-body">
                            <div class="settings-group">
                                <label>Фон</label>
                                <div class="settings-row">
                                    <input type="color" id="bgColor" class="color-picker" value="#f9fafb">
                                    <span>или</span>
                                    <input type="text" id="bgImage" class="form-input" placeholder="URL изображения" style="flex:1;">
                                </div>
                                
                                <div class="gradient-presets">
                                    <div class="gradient-preset" style="background: linear-gradient(135deg, #667eea, #764ba2)" onclick="setGradient('135deg, #667eea, #764ba2')"></div>
                                    <div class="gradient-preset" style="background: linear-gradient(135deg, #f093fb, #f5576c)" onclick="setGradient('135deg, #f093fb, #f5576c')"></div>
                                    <div class="gradient-preset" style="background: linear-gradient(135deg, #4facfe, #00f2fe)" onclick="setGradient('135deg, #4facfe, #00f2fe')"></div>
                                    <div class="gradient-preset" style="background: linear-gradient(135deg, #43e97b, #38f9d7)" onclick="setGradient('135deg, #43e97b, #38f9d7')"></div>
                                    <div class="gradient-preset" style="background: linear-gradient(135deg, #fa709a, #fee140)" onclick="setGradient('135deg, #fa709a, #fee140')"></div>
                                </div>
                                
                                <div class="file-upload" style="margin-top: 10px;">
                                    <span class="file-upload-btn">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        Загрузить фото с ПК
                                    </span>
                                    <input type="file" id="bgFileUpload" accept="image/*">
                                </div>
                            </div>
                            
                            <div class="settings-group">
                                <label>Цвет интерфейса</label>
                                <input type="color" id="interfaceColor" class="color-picker" value="#10b981">
                            </div>
                            
                            <div class="settings-group">
                                <label>Размер текста в карточках: <span id="fontSizeValue">16px</span></label>
                                <input type="range" id="fontSize" class="settings-slider" min="12" max="24" value="16">
                            </div>
                            
                            <div class="settings-group">
                                <label>Шрифт</label>
                                <select id="fontFamily" class="font-select">
                                    <option value="Inter, sans-serif">Inter</option>
                                    <option value="Arial, sans-serif">Arial</option>
                                    <option value="'Times New Roman', serif">Times New Roman</option>
                                    <option value="'Courier New', monospace">Courier New</option>
                                    <option value="Georgia, serif">Georgia</option>
                                    <option value="Verdana, sans-serif">Verdana</option>
                                </select>
                                <div class="file-upload" style="margin-top: 8px;">
                                    <span class="file-upload-btn">
                                        <i class="fas fa-font"></i>
                                        Загрузить свой шрифт
                                    </span>
                                    <input type="file" id="fontFileUpload" accept=".ttf,.otf,.woff,.woff2">
                                </div>
                            </div>
                            
                            <div class="settings-group">
                                <label>
                                    <input type="checkbox" id="noBorderPosts"> Убрать рамку у постов
                                </label>
                            </div>
                            
                            <div class="settings-group">
                                <label>
                                    <input type="checkbox" id="darkMode"> Темная тема
                                </label>
                            </div>
                            
                            <div class="settings-group">
                                <label>Звук на фон</label>
                                <div class="file-upload">
                                    <span class="file-upload-btn">
                                        <i class="fas fa-music"></i>
                                        Загрузить аудиофайл
                                    </span>
                                    <input type="file" id="soundFileUpload" accept="audio/*">
                                </div>
                                
                                <div class="sound-player hidden" id="soundPlayer">
                                    <button id="playPauseBtn"><i class="fas fa-play"></i></button>
                                    <button id="stopBtn"><i class="fas fa-stop"></i></button>
                                    <button id="restartBtn"><i class="fas fa-undo"></i></button>
                                    <input type="range" id="volumeSlider" min="0" max="1" step="0.1" value="0.5">
                                    <button id="loopBtn"><i class="fas fa-repeat"></i></button>
                                    <button id="hidePlayerBtn"><i class="fas fa-times"></i></button>
                                </div>
                                
                                <label style="margin-top: 10px;">
                                    <input type="checkbox" id="enableSound"> Включить фоновую музыку
                                </label>
                            </div>
                            
                            <button class="save-settings-btn" onclick="saveFeedSettings()">
                                <i class="fas fa-save"></i>
                                Точно сохранить OwO?
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Лента постов -->
                <div style="margin-bottom: 20px;">
                    <h1 style="font-size: 24px; font-weight: 700;">Лента постов</h1>
                </div>
                
                <?php if (empty($allPosts)): ?>
                    <div class="post-card" style="padding: 40px; text-align: center;">
                        <i class="fas fa-newspaper" style="font-size: 48px; color: var(--text-light); margin-bottom: 20px;"></i>
                        <h3 style="margin-bottom: 10px;">Пока нет постов</h3>
                        <p style="color: var(--text-light); margin-bottom: 20px;">Будьте первым!</p>
                        <?php if (getCurrentUser() && getCurrentUser()['verified']): ?>
                            <a href="/?action=create_post" class="btn btn-primary">
                                Создать пост
                            </a>
                        <?php elseif (!getCurrentUser()): ?>
                            <a href="/?action=register" class="btn btn-primary">
                                Зарегистрироваться
                            </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($allPosts as $post): ?>
                        <?php $roleStyle = getRoleStyle($post['role'] ?? ''); ?>
                        <div class="post-card role-<?php echo $post['role'] ?? ''; ?>">
                            <div class="post-header">
                                <img src="<?php echo $post['avatar']; ?>" alt="" class="post-avatar">
                                <div class="post-author-info">
                                    <div class="post-author">
                                        <a href="/<?php echo $post['username']; ?>" class="post-author-name">
                                            @<?php echo $post['username']; ?>
                                        </a>
                                        <?php if (!empty($post['role'])): ?>
                                            <?php echo $roleStyle['badge']; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="post-date"><?php echo date('d.m.Y H:i', strtotime($post['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="post-content">
                                <a href="/post/<?php echo $post['slug']; ?>" class="post-title">
                                    <?php echo htmlspecialchars($post['title']); ?>
                                </a>
                                
                                <div class="post-text">
    <?php
    // Заменяем &nbsp; на обычные пробелы (без объявления новой функции)
    $fullContent = str_replace('&nbsp;', ' ', $post['content']);

    // Получаем чистый текст без HTML для подсчёта длины
    $plainText = strip_tags($fullContent);
    $plainText = preg_replace('/\s+/', ' ', $plainText); // убираем лишние пробелы
    $plainText = trim($plainText);

    if (mb_strlen($plainText) > 300) {
        // Обрезаем до 300 символов, стараясь не разрывать слова
        $short = mb_substr($plainText, 0, 300);
        $lastSpace = mb_strrpos($short, ' ');
        if ($lastSpace !== false) {
            $short = mb_substr($short, 0, $lastSpace);
        }
        // Выводим обрезанный текст безопасно, с переносами строк
        echo nl2br(htmlspecialchars($short)) . '...';
        echo '<a href="/post/' . $post['slug'] . '" class="read-more">Читать далее <i class="fas fa-arrow-right"></i></a>';
    } else {
        // Если текст короткий, выводим как есть (с HTML) и с заменёнными &nbsp;
        echo $fullContent;
    }
    ?>
</div>
                            </div>
                            
                            <div class="post-footer">
                                <div class="post-tags">
                                    <?php echo $post['tags_html']; ?>
                                </div>
                                
                                <div class="post-stats">
                                    <span class="stat"><i class="far fa-eye"></i> <?php echo $post['views']; ?></span>
                                    <button class="like-btn <?php echo (getCurrentUser() && hasUserLikedPost($post['slug'], getCurrentUser()['id'])) ? 'active' : ''; ?>" 
                                            data-slug="<?php echo $post['slug']; ?>">
                                        <i class="<?php echo (getCurrentUser() && hasUserLikedPost($post['slug'], getCurrentUser()['id'])) ? 'fas' : 'far'; ?> fa-heart"></i>
                                        <span class="like-count"><?php echo $post['likes']; ?></span>
                                    </button>
                                    <span class="stat"><i class="far fa-comment"></i> <?php echo $post['comments_count']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (getCurrentUser() && getCurrentUser()['verified']): ?>
                    <a href="/?action=create_post" class="fab-button" title="Создать пост">
                        <i class="fas fa-plus"></i>
                    </a>
                <?php endif; ?>
            
            <?php
            elseif ($view === 'music'):
            ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1 style="font-size:24px;font-weight:700;"><i class="fas fa-music"></i> Музыка</h1>
                    <?php if (getCurrentUser()): ?>
                        <a href="/?action=music&upload=1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Загрузить</a>
                    <?php endif; ?>
                </div>
                <?php if (getCurrentUser() && isset($_GET['upload'])): ?>
                <div class="upload-form">
                    <h3><i class="fas fa-upload"></i> Загрузить музыку</h3>
                    <form method="post" action="/api.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_music">
                        <div class="form-group">
                            <label class="form-label">Название *</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Юз (уникальный) *</label>
                            <input type="text" name="use" class="form-input" required placeholder="my_cool_song">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Иконка (загрузить)</label>
                            <input type="file" name="icon_file" accept="image/*">
                            <label class="form-label" style="margin-top:6px;">или ссылка</label>
                            <input type="url" name="icon_url" class="form-input" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Музыка (загрузить)</label>
                            <input type="file" name="music_file" accept="audio/*">
                            <label class="form-label" style="margin-top:6px;">или ссылка</label>
                            <input type="url" name="music_url" class="form-input" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-input" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Теги (через запятую)</label>
                            <input type="text" name="tags" class="form-input" placeholder="minecraft, ambient, chill">
                        </div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="is_author"> Я являюсь автором</label>
                            <label><input type="checkbox" name="is_ai"> Создано с использованием ИИ</label>
                            <label><input type="checkbox" name="is_nsfw"> 18+</label>
                            <label><input type="checkbox" name="agreed" required> Я знаком с правилами и согласен *</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="search-minimal" style="margin-bottom:20px;">
                    <input type="text" id="musicSearch" class="form-input" placeholder="Поиск музыки..." oninput="filterCards('music')">
                </div>
                <?php
                    $musicFiles = glob(MUSIC_DIR . '*.json');
                    $musicItems = [];
                    foreach ($musicFiles as $f) {
                        $item = json_decode(file_get_contents($f), true);
                        if ($item) $musicItems[] = $item;
                    }
                    usort($musicItems, function($a, $b) { return ($b['likes'] ?? 0) - ($a['likes'] ?? 0); });
                ?>
                <?php if (empty($musicItems)): ?>
                    <div class="post-card" style="padding:40px;text-align:center;">
                        <i class="fas fa-music" style="font-size:48px;color:var(--text-light);margin-bottom:20px;"></i>
                        <h3>Пока нет музыки</h3>
                        <p style="color:var(--text-light);">Будьте первым кто загрузит!</p>
                    </div>
                <?php else: ?>
                    <div class="content-grid" id="musicGrid">
                    <?php foreach ($musicItems as $item): ?>
                        <div class="content-card music-card" data-title="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                            <div class="content-card-icon">
                                <?php if (!empty($item['icon'])): ?>
                                    <img src="<?php echo htmlspecialchars($item['icon']); ?>" alt="">
                                <?php else: ?>
                                    <i class="fas fa-music"></i>
                                <?php endif; ?>
                            </div>
                            <div class="content-card-info">
                                <div class="content-card-title"><?php echo htmlspecialchars($item['title'] ?? 'Без названия'); ?></div>
                                <div class="content-card-meta">@<?php echo htmlspecialchars($item['author'] ?? ''); ?></div>
                                <?php if (!empty($item['tags'])): ?>
                                    <div class="content-card-tags">
                                        <?php foreach (explode(',', $item['tags']) as $tag): ?>
                                            <span class="tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="content-card-actions">
                                <?php if (!empty($item['file'])): ?>
                                    <button class="btn btn-sm btn-outline" onclick="playMusic('<?php echo htmlspecialchars($item['file']); ?>')"><i class="fas fa-play"></i></button>
                                <?php endif; ?>
                                <span class="content-likes"><i class="far fa-heart"></i> <?php echo $item['likes'] ?? 0; ?></span>
                            </div>
                            <?php if (!empty($item['is_ai'])): ?><span class="badge-ai">AI</span><?php endif; ?>
                            <?php if (!empty($item['is_nsfw'])): ?><span class="badge-nsfw">18+</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php
            elseif ($view === 'stickers'):
            ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1 style="font-size:24px;font-weight:700;"><i class="fas fa-sticky-note"></i> Стикеры</h1>
                    <?php if (getCurrentUser()): ?>
                        <a href="/?action=stickers&upload=1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Загрузить</a>
                    <?php endif; ?>
                </div>
                <?php if (getCurrentUser() && isset($_GET['upload'])): ?>
                <div class="upload-form">
                    <h3><i class="fas fa-upload"></i> Загрузить стикер</h3>
                    <form method="post" action="/api.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_sticker">
                        <div class="form-group">
                            <label class="form-label">Название *</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Юз (уникальный)</label>
                            <input type="text" name="use" class="form-input" placeholder="cute_cat">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Стикер (PNG, GIF, WebP, WebM)</label>
                            <input type="file" name="sticker_file" accept=".png,.gif,.webp,.webm,.jpg,.jpeg">
                            <label class="form-label" style="margin-top:6px;">или ссылка</label>
                            <input type="url" name="sticker_url" class="form-input" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-input" rows="2"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Теги</label>
                            <input type="text" name="tags" class="form-input" placeholder="кот, смешно">
                        </div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="is_author"> Я являюсь автором</label>
                            <label><input type="checkbox" name="is_nsfw"> 18+</label>
                            <label><input type="checkbox" name="agreed" required> Я знаком с правилами *</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="search-minimal" style="margin-bottom:20px;">
                    <input type="text" id="stickerSearch" class="form-input" placeholder="Поиск стикеров..." oninput="filterCards('sticker')">
                </div>
                <?php
                    $stickerFiles = glob(STICKERS_DIR . '*.json');
                    $stickerItems = [];
                    foreach ($stickerFiles as $f) {
                        $item = json_decode(file_get_contents($f), true);
                        if ($item) $stickerItems[] = $item;
                    }
                    usort($stickerItems, function($a, $b) { return ($b['likes'] ?? 0) - ($a['likes'] ?? 0); });
                ?>
                <?php if (empty($stickerItems)): ?>
                    <div class="post-card" style="padding:40px;text-align:center;">
                        <i class="fas fa-sticky-note" style="font-size:48px;color:var(--text-light);margin-bottom:20px;"></i>
                        <h3>Пока нет стикеров</h3>
                        <p style="color:var(--text-light);">Загрузите первый стикер!</p>
                    </div>
                <?php else: ?>
                    <div class="sticker-grid" id="stickerGrid">
                    <?php foreach ($stickerItems as $item): ?>
                        <div class="sticker-card" data-title="<?php echo htmlspecialchars($item['title'] ?? ''); ?>" onclick="openStickerModal(this)" data-info='<?php echo htmlspecialchars(json_encode($item)); ?>'>
                            <?php if (!empty($item['file'])): ?>
                                <img src="<?php echo htmlspecialchars($item['file']); ?>" alt="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                            <?php else: ?>
                                <i class="fas fa-sticky-note" style="font-size:48px;"></i>
                            <?php endif; ?>
                            <div class="sticker-overlay">
                                <span class="content-likes"><i class="far fa-heart"></i> <?php echo $item['likes'] ?? 0; ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php
            elseif ($view === 'coding'):
            ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1 style="font-size:24px;font-weight:700;"><i class="fas fa-code"></i> Кодинг</h1>
                    <?php if (getCurrentUser()): ?>
                        <a href="/?action=coding&upload=1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Загрузить</a>
                    <?php endif; ?>
                </div>
                <?php if (getCurrentUser() && isset($_GET['upload'])): ?>
                <div class="upload-form">
                    <h3><i class="fas fa-upload"></i> Загрузить код / сборку</h3>
                    <form method="post" action="/api.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_coding">
                        <div class="form-group">
                            <label class="form-label">Название *</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Юз (уникальный)</label>
                            <input type="text" name="use" class="form-input" placeholder="my_mod_v2">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Файл (ZIP, RAR, JAR и др.)</label>
                            <input type="file" name="code_file">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-input" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Теги</label>
                            <input type="text" name="tags" class="form-input" placeholder="minecraft, мод, сборка">
                        </div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="is_author"> Я являюсь автором</label>
                            <label><input type="checkbox" name="agreed" required> Я знаком с правилами *</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="search-minimal" style="margin-bottom:20px;">
                    <input type="text" id="codingSearch" class="form-input" placeholder="Поиск кода, сборок..." oninput="filterCards('coding')">
                </div>
                <?php
                    $codingFiles = glob(CODING_DIR . '*.json');
                    $codingItems = [];
                    foreach ($codingFiles as $f) {
                        $item = json_decode(file_get_contents($f), true);
                        if ($item) $codingItems[] = $item;
                    }
                    usort($codingItems, function($a, $b) { return ($b['likes'] ?? 0) - ($a['likes'] ?? 0); });
                ?>
                <?php if (empty($codingItems)): ?>
                    <div class="post-card" style="padding:40px;text-align:center;">
                        <i class="fas fa-code" style="font-size:48px;color:var(--text-light);margin-bottom:20px;"></i>
                        <h3>Пока нет загрузок</h3>
                        <p style="color:var(--text-light);">Загрузите свой код или сборку!</p>
                    </div>
                <?php else: ?>
                    <div class="content-grid" id="codingGrid">
                    <?php foreach ($codingItems as $item): ?>
                        <div class="content-card coding-card" data-title="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                            <div class="content-card-icon"><i class="fas fa-file-code"></i></div>
                            <div class="content-card-info">
                                <div class="content-card-title"><?php echo htmlspecialchars($item['title'] ?? 'Без названия'); ?></div>
                                <div class="content-card-meta">@<?php echo htmlspecialchars($item['author'] ?? ''); ?></div>
                                <?php if (!empty($item['description'])): ?>
                                    <div class="content-card-desc"><?php echo htmlspecialchars(mb_substr($item['description'], 0, 80)); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="content-card-actions">
                                <?php if (!empty($item['file'])): ?>
                                    <a href="<?php echo htmlspecialchars($item['file']); ?>" class="btn btn-sm btn-primary" download><i class="fas fa-download"></i></a>
                                <?php endif; ?>
                                <span class="content-likes"><i class="far fa-heart"></i> <?php echo $item['likes'] ?? 0; ?></span>
                            </div>
                            <div class="download-warning"><i class="fas fa-exclamation-triangle"></i> Будьте осторожны с загрузками</div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php
            elseif ($view === 'themes'):
            ?>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
                    <h1 style="font-size:24px;font-weight:700;"><i class="fas fa-palette"></i> Оформление</h1>
                    <?php if (getCurrentUser()): ?>
                        <a href="/?action=themes&upload=1" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Создать</a>
                    <?php endif; ?>
                </div>
                <?php if (getCurrentUser() && isset($_GET['upload'])): ?>
                <div class="upload-form">
                    <h3><i class="fas fa-plus"></i> Создать оформление</h3>
                    <form method="post" action="/api.php" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_theme">
                        <div class="form-group">
                            <label class="form-label">Название *</label>
                            <input type="text" name="title" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Юз (уникальный)</label>
                            <input type="text" name="use" class="form-input" placeholder="dark_neon">
                        </div>
                        <div class="form-group">
                            <label class="form-label">CSS код *</label>
                            <textarea name="css_code" class="form-input" rows="8" style="font-family:monospace;" required placeholder=".profile-header { background: #1a1a2e; color: #eee; }"></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Превью (опционально)</label>
                            <input type="file" name="preview_file" accept="image/*" class="form-input">
                            <label class="form-label" style="margin-top:6px;">или ссылка</label>
                            <input type="url" name="preview_url" class="form-input" placeholder="https://...">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Описание</label>
                            <textarea name="description" class="form-input" rows="2"></textarea>
                        </div>
                        <div class="checkbox-group">
                            <label><input type="checkbox" name="agreed" required> Я знаком с правилами *</label>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Создать</button>
                    </form>
                </div>
                <?php endif; ?>
                
                <div class="search-minimal" style="margin-bottom:20px;">
                    <input type="text" id="themeSearch" class="form-input" placeholder="Поиск оформлений..." oninput="filterCards('theme')">
                </div>
                <?php
                    $themeFiles = glob(THEMES_DIR . '*.json');
                    $themeItems = [];
                    foreach ($themeFiles as $f) {
                        $item = json_decode(file_get_contents($f), true);
                        if ($item) $themeItems[] = $item;
                    }
                    usort($themeItems, function($a, $b) { return ($b['likes'] ?? 0) - ($a['likes'] ?? 0); });
                ?>
                <?php if (empty($themeItems)): ?>
                    <div class="post-card" style="padding:40px;text-align:center;">
                        <i class="fas fa-palette" style="font-size:48px;color:var(--text-light);margin-bottom:20px;"></i>
                        <h3>Пока нет оформлений</h3>
                        <p style="color:var(--text-light);">Создайте первое CSS оформление!</p>
                    </div>
                <?php else: ?>
                    <div class="content-grid" id="themeGrid">
                    <?php foreach ($themeItems as $item): ?>
                        <div class="content-card theme-card" data-title="<?php echo htmlspecialchars($item['title'] ?? ''); ?>">
                            <div class="content-card-icon" style="<?php echo !empty($item['preview_css']) ? htmlspecialchars($item['preview_css']) : ''; ?>">
                                <i class="fas fa-palette"></i>
                            </div>
                            <div class="content-card-info">
                                <div class="content-card-title"><?php echo htmlspecialchars($item['title'] ?? 'Без названия'); ?></div>
                                <div class="content-card-meta">@<?php echo htmlspecialchars($item['author'] ?? ''); ?></div>
                            </div>
                            <div class="content-card-actions">
                                <button class="btn btn-sm btn-outline" onclick="copyCssCode('<?php echo htmlspecialchars($item['id'] ?? ''); ?>')"><i class="fas fa-copy"></i> Копировать</button>
                                <span class="content-likes"><i class="far fa-heart"></i> <?php echo $item['likes'] ?? 0; ?></span>
                            </div>
                            <?php if (!empty($item['css_code'])): ?>
                                <textarea class="hidden" id="css-<?php echo htmlspecialchars($item['id'] ?? ''); ?>"><?php echo htmlspecialchars($item['css_code']); ?></textarea>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            <?php
            elseif ($view === 'notifications_page'):
                $currentUser = getCurrentUser();
                $allNotifs = $currentUser ? getUserNotifications($currentUser['id']) : [];
                $allNotifs = array_slice($allNotifs, 0, 100);
            ?>
                <div style="margin-bottom:20px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap;">
                    <h1 style="font-size:24px;font-weight:700;"><i class="fas fa-bell"></i> Уведомления</h1>
                    <?php if (!empty($allNotifs)): ?>
                        <button id="notificationsPageMarkAllRead" type="button" class="btn btn-sm btn-outline">Отметить всё как прочитанное</button>
                    <?php endif; ?>
                </div>
                <?php if (empty($allNotifs)): ?>
                    <div class="post-card" style="padding:40px;text-align:center;">
                        <i class="fas fa-bell-slash" style="font-size:48px;color:var(--text-light);margin-bottom:20px;"></i>
                        <h3>Нет уведомлений</h3>
                    </div>
                <?php else: ?>
                    <?php foreach ($allNotifs as $notif): ?>
                        <div class="notification-item-page <?php echo empty($notif['read']) ? 'unread' : ''; ?>">
                            <div class="notif-icon">
                                <?php if (($notif['type'] ?? '') === 'like'): ?>
                                    <i class="fas fa-heart" style="color:#ef4444;"></i>
                                <?php elseif (($notif['type'] ?? '') === 'comment'): ?>
                                    <i class="fas fa-comment" style="color:#3b82f6;"></i>
                                <?php elseif (($notif['type'] ?? '') === 'mention'): ?>
                                    <i class="fas fa-at" style="color:#8b5cf6;"></i>
                                <?php else: ?>
                                    <i class="fas fa-bell" style="color:var(--primary);"></i>
                                <?php endif; ?>
                            </div>
                            <div class="notif-body">
                                <div class="notif-text">
                                    <?php
                                    $data = $notif['data'] ?? [];
                                    $fromUser = $data['from_username'] ?? '';
                                    $postTitle = $data['post_title'] ?? '';
                                    $type = $notif['type'] ?? '';
                                    if ($type === 'like') {
                                        echo '<strong>@' . htmlspecialchars($fromUser) . '</strong> поставил(а) лайк на ваш пост <em>' . htmlspecialchars($postTitle) . '</em>';
                                    } elseif ($type === 'comment') {
                                        echo '<strong>@' . htmlspecialchars($fromUser) . '</strong> написал(а) комментарий: <em>"' . htmlspecialchars($data['content'] ?? '') . '"</em>';
                                    } elseif ($type === 'mention') {
                                        echo '<strong>@' . htmlspecialchars($fromUser) . '</strong> упомянул(а) вас: <em>"' . htmlspecialchars($data['content'] ?? '') . '"</em>';
                                    } else {
                                        echo htmlspecialchars($notif['message'] ?? 'Уведомление');
                                    }
                                    ?>
                                </div>
                                <div class="notif-time"><?php echo date('d.m.Y H:i', strtotime($notif['created_at'] ?? 'now')); ?></div>
                                <?php if (!empty($data['post_slug'])): ?>
                                    <a href="/post/<?php echo htmlspecialchars($data['post_slug']); ?>" class="btn btn-sm btn-outline" style="margin-top:5px;">Подробнее</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

            <?php
            elseif ($view === 'templates'):
            ?>
                <div class="post-card" style="padding: 40px; text-align: center;">
                    <i class="fas fa-paint-brush" style="font-size: 64px; color: var(--primary); margin-bottom: 20px;"></i>
                    <h1 style="font-size: 32px; margin-bottom: 10px;">Шаблоны</h1>
                    <p style="color: var(--text-light); margin-bottom: 30px;">Скоро здесь появятся шаблоны для постов</p>
                    <a href="/" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        На главную
                    </a>
                </div>
            
            <?php
            elseif ($view === '404'):
            ?>
                <div class="post-card" style="padding: 60px 30px; text-align: center;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 64px; color: #f59e0b; margin-bottom: 20px;"></i>
                    <h1 style="font-size: 32px; margin-bottom: 10px;">404</h1>
                    <p style="color: var(--text-light); margin-bottom: 30px;">Страница потерялось ):</p>
                    <a href="/" class="btn btn-primary">
                        <i class="fas fa-home"></i>
                        На главную
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <!-- Модальное окно редактирования профиля -->
    <?php if ($view === 'profile' && $profileUser && getCurrentUser() && getCurrentUser()['id'] === $profileUser['id']): ?>
        <div id="edit-profile-modal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Редактировать профиль</h3>
                    <button class="modal-close" onclick="toggleModal('edit-profile-modal')">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="post" action="/">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="form-group">
                            <label for="modal-bio" class="form-label">О себе</label>
                            <textarea id="modal-bio" name="bio" class="form-input"><?php echo htmlspecialchars($profileUser['bio']); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label for="modal-status" class="form-label">Статус</label>
                            <input type="text" id="modal-status" name="status" class="form-input" value="<?php echo htmlspecialchars($settings['status'] ?? ''); ?>" maxlength="100" placeholder="Играю в Minecraft...">
                        </div>
                        
                        <div class="form-group">
                            <label for="modal-website" class="form-label">Веб-сайт</label>
                            <input type="url" id="modal-website" name="website" class="form-input" value="<?php echo $profileUser['social']['website'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="modal-twitter" class="form-label">Twitter</label>
                            <input type="text" id="modal-twitter" name="twitter" class="form-input" value="<?php echo $profileUser['social']['twitter'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="modal-instagram" class="form-label">Instagram</label>
                            <input type="text" id="modal-instagram" name="instagram" class="form-input" value="<?php echo $profileUser['social']['instagram'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="modal-vk" class="form-label">VK</label>
                            <input type="text" id="modal-vk" name="vk" class="form-input" value="<?php echo $profileUser['social']['vk'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="modal-telegram" class="form-label">Telegram</label>
                            <input type="text" id="modal-telegram" name="telegram" class="form-input" value="<?php echo $profileUser['social']['telegram'] ?? ''; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Обложка профиля (CSS)</label>
                            <input type="text" name="cover_css" class="form-input" value="<?php echo htmlspecialchars($settings['cover_css'] ?? ''); ?>" placeholder="linear-gradient(135deg, #667eea 0%, #764ba2 100%)">
                            <small style="color:var(--text-light);">Пример: linear-gradient(135deg, #667eea, #764ba2) или цвет #1a1a2e</small>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Виджеты</label>
                            <label style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                <input type="checkbox" name="widget_music" <?php echo !empty($settings['widget_music']) ? 'checked' : ''; ?>>
                                <span>Виджет музыки</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                <input type="checkbox" name="widget_sticker" <?php echo !empty($settings['widget_sticker']) ? 'checked' : ''; ?>>
                                <span>Любимый стикер-пак</span>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                                <input type="checkbox" name="widget_build" <?php echo !empty($settings['widget_build']) ? 'checked' : ''; ?>>
                                <span>Виджет сборки</span>
                            </label>
                        </div>

                        <div class="flex gap-4 mt-4">
                            <button type="submit" class="btn btn-primary flex-1">Сохранить</button>
                            <button type="button" class="btn btn-outline flex-1" onclick="toggleModal('edit-profile-modal')">Отмена</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Онбординг (первый запуск) -->
    <div class="modal" id="onboardingModal">
        <div class="modal-content onboarding-modal">
            <div class="onboarding-icon">
               
            </div>
            <h2 class="onboarding-title">Добро пожаловать в My Cryndel!</h2>
            <div class="onboarding-text">
                <p>Краткая инструкция:</p>
                <p>📝 Создавайте посты и делитес</p>
                <p>💬 Комментируйте и общайтесь с другими </p>
                <p>🏆 Получайте медали за активность</p>
                <p>🎨 Настраивайте внешний вид под себя</p>
                <p>😘 только обойдемся без пх :)</p>
                <p>🔔 Следите за уведомлениями</p><br>
                <p style="margin-top: 15px;">Пользуясь сайтом, вы соглашаетесь с<br> правилами платформы.</p>
            </div>
            <br>
            <button class="onboarding-btn" onclick="dismissOnboarding()">
                Пон ;)
            </button>
             <div class="onboarding-icon">
               
            </div>
        </div>
    </div>
    
   <script>

document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // ГЛОБАЛЬНЫЕ ПЕРЕМЕННЫЕ ДЛЯ ЗВУКА
    // ============================================
    let audio = null;
    let isPlaying = false;
    let currentAudioFile = null;
    
    // ============================================
    // ИНИЦИАЛИЗАЦИЯ ВСЕГО
    // ============================================
    initMobileMenu();
    initModals();
    initLikes();
    initSearch();
    initFeedSettings();
    initUploadForms();
    initSoundPlayer();
    initNotifications();
    initTabs();
    loadFeedSettings(); // Загружаем сохраненные настройки
    checkOnboarding();
    
    // ============================================
    // МОБИЛЬНОЕ МЕНЮ
    // ============================================
    function initMobileMenu() {
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const mobileMenu = document.getElementById('mobileMenu');
        const mobileMenuClose = document.getElementById('mobileMenuClose');
        
        if (mobileMenuBtn && mobileMenu && mobileMenuClose) {
            mobileMenuBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                mobileMenu.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
            
            mobileMenuClose.addEventListener('click', () => {
                mobileMenu.classList.remove('show');
                document.body.style.overflow = '';
            });
            
            document.addEventListener('click', (e) => {
                if (!mobileMenu.contains(e.target) && e.target !== mobileMenuBtn && !mobileMenuBtn.contains(e.target)) {
                    mobileMenu.classList.remove('show');
                    document.body.style.overflow = '';
                }
            });
        }
    }
    
    // ============================================
    // МОДАЛЬНЫЕ ОКНА
    // ============================================
    function initModals() {
        window.toggleModal = function(id) {
            const modal = document.getElementById(id);
            if (modal) {
                modal.classList.toggle('active');
                document.body.style.overflow = modal.classList.contains('active') ? 'hidden' : '';
            }
        };
        
        window.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal')) {
                e.target.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
    }
    
    // ============================================
    // ТАБЫ
    // ============================================
    function initTabs() {
        window.switchTab = function(tabId) {
            const tabs = document.querySelectorAll('.tab');
            const contents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(tab => tab.classList.remove('active'));
            contents.forEach(content => content.classList.remove('active'));
            
            const activeTab = document.querySelector(`[onclick="switchTab('${tabId}')"]`);
            if (activeTab) activeTab.classList.add('active');
            
            const activeContent = document.getElementById(tabId);
            if (activeContent) activeContent.classList.add('active');
        };
    }
    
    // ============================================
    // ЛАЙКИ
    // ============================================
    function initLikes() {
        document.querySelectorAll('.like-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const slug = this.dataset.slug;
                
                <?php if (!getCurrentUser()): ?>
                    window.location.href = '/?action=login';
                    return;
                <?php endif; ?>
                
                // Анимация
                const icon = this.querySelector('i');
                if (icon) {
                    icon.style.transform = 'scale(1.3)';
                    setTimeout(() => icon.style.transform = 'scale(1)', 200);
                }
                
                // Отправка запроса
                fetch('/', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=like_post&slug=' + encodeURIComponent(slug)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const icon = this.querySelector('i');
                        const count = this.querySelector('.like-count');
                        
                        if (data.hasLiked) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            this.classList.add('active');
                        } else {
                            icon.classList.remove('fas');
                            icon.classList.add('far');
                            this.classList.remove('active');
                        }
                        
                        if (count) count.textContent = data.likes;
                    }
                })
                .catch(error => console.error('Error:', error));
            });
            
            // Проверка статуса
            const slug = btn.dataset.slug;
            if (slug) {
                fetch('/?ajax=check_like&slug=' + encodeURIComponent(slug))
                .then(response => response.json())
                .then(data => {
                    if (data.hasLiked) {
                        const icon = btn.querySelector('i');
                        if (icon) {
                            icon.classList.remove('far');
                            icon.classList.add('fas');
                            btn.classList.add('active');
                        }
                    }
                })
                .catch(error => console.error('Error checking like:', error));
            }
        });
    }
    
    // ============================================
    // ПОИСК
    // ============================================
    function initSearch() {
        const searchToggle = document.getElementById('searchToggleBtn');
        const searchExpanded = document.getElementById('searchExpanded');
        
        if (searchToggle && searchExpanded) {
            searchToggle.addEventListener('click', () => {
                searchExpanded.classList.toggle('show');
            });
        }
        
        window.performSearch = function() {
            const query = document.getElementById('searchQuery')?.value;
            const type = document.querySelector('input[name="searchType"]:checked')?.value;
            const resultsDiv = document.getElementById('searchResults');
            
            if (!query || query.length < 2 || !resultsDiv) return;
            
            fetch('/', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=search&query=' + encodeURIComponent(query) + '&type=' + encodeURIComponent(type)
            })
            .then(response => response.json())
            .then(data => {
                resultsDiv.innerHTML = '';
                
                if (data.users && data.users.length > 0) {
                    let usersHtml = '<div class="search-users"><h3>Пользователи</h3>';
                    data.users.forEach(user => {
                        usersHtml += `
                            <a href="/${user.username}" class="search-user-item" style="display: flex; align-items: center; gap: 10px; padding: 8px; border-bottom: 1px solid var(--border);">
                                <img src="${user.avatar}" alt="" style="width: 32px; height: 32px; border-radius: 50%;">
                                <div>
                                    <div style="font-weight: 600;">@${user.username}</div>
                                </div>
                            </a>
                        `;
                    });
                    usersHtml += '</div>';
                    resultsDiv.innerHTML += usersHtml;
                }
                
                if (data.posts && data.posts.length > 0) {
                    let postsHtml = '<div class="search-posts"><h3>Посты</h3>';
                    data.posts.forEach(post => {
                        postsHtml += `
                            <a href="/post/${post.slug}" class="search-post-item" style="display: block; padding: 8px; border-bottom: 1px solid var(--border);">
                                <div style="font-weight: 600;">${escapeHtml(post.title)}</div>
                                <div style="font-size: 12px; color: var(--text-light);">@${post.username}</div>
                            </a>
                        `;
                    });
                    postsHtml += '</div>';
                    resultsDiv.innerHTML += postsHtml;
                }
                
                if ((!data.users || data.users.length === 0) && (!data.posts || data.posts.length === 0)) {
                    resultsDiv.innerHTML = '<p style="text-align: center; color: var(--text-light); padding: 20px;">Ничегошеньки не найдено =/ </p>';
                }
                
                resultsDiv.classList.add('show');
            })
            .catch(error => {
                console.error('Search error:', error);
                resultsDiv.innerHTML = '<p style="text-align: center; color: var(--text-light);">Ошибочка ..</p>';
                resultsDiv.classList.add('show');
            });
        };
        
        const searchInput = document.getElementById('searchQuery');
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') performSearch();
            });
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ============================================
    // AJAX ЗАГРУЗКИ: перехват форм upload (music, sticker, coding, theme)
    // ============================================
    function initUploadForms() {
        document.querySelectorAll('.upload-form form').forEach(form => {
            if (form.dataset.ajaxInit) return;
            form.dataset.ajaxInit = '1';
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                if (form.dataset.busy) return;
                const submitBtn = form.querySelector('button[type="submit"]');
                const origHtml = submitBtn ? submitBtn.innerHTML : null;
                let status = form.querySelector('.upload-status');
                if (!status) { status = document.createElement('div'); status.className = 'upload-status'; form.appendChild(status); }
                status.classList.remove('error','success');
                status.textContent = '';

                const xhr = new XMLHttpRequest();
                const fd = new FormData(form);
                xhr.open('POST', form.action);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

                xhr.upload.addEventListener('progress', function(ev) {
                    if (ev.lengthComputable) {
                        const pct = Math.round(ev.loaded / ev.total * 100);
                        status.textContent = 'Загрузка: ' + pct + '%';
                    }
                });

                xhr.addEventListener('load', function() {
                    form.dataset.busy = '';
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; }
                    let res = null;
                    try { res = JSON.parse(xhr.responseText); } catch (err) { status.classList.add('error'); status.textContent = 'Сервер вернул некорректный ответ'; return; }
                    if (xhr.status >= 200 && xhr.status < 300 && (res.success || res.success === true)) {
                        status.classList.add('success');
                        status.textContent = res.message || 'Готово';
                        // поддержка fn_response redirect через поле redirect (если есть)
                        if (res.redirect) { window.location = res.redirect; return; }
                        if (res.item && res.item.id) {
                            // перенаправление по типу
                            const t = res.item.type || '';
                            if (t === 'music') window.location = '/music.php?view=detail&id=' + res.item.id;
                            else if (t === 'sticker') window.location = '/stickers.php?view=detail&id=' + res.item.id;
                            else if (t === 'coding') window.location = '/coding.php?view=detail&id=' + res.item.id;
                            else if (t === 'theme') window.location = '/themes.php?view=detail&id=' + res.item.id;
                            else setTimeout(()=>location.reload(), 700);
                            return;
                        }
                        // Auto-dismiss success
                        setTimeout(()=>{
                            status.style.opacity = '0';
                            setTimeout(()=>status.remove(), 300);
                        }, 1200);
                    } else {
                        status.classList.add('error');
                        status.textContent = res.error || res.message || JSON.stringify(res);
                        // Auto-dismiss error after a longer delay
                        setTimeout(()=>{
                            try { status.style.opacity = '0'; setTimeout(()=>status.remove(), 300); } catch(e){}
                        }, 6000);
                    }
                });

                xhr.addEventListener('error', function() {
                    form.dataset.busy = '';
                    if (submitBtn) { submitBtn.disabled = false; submitBtn.innerHTML = origHtml; }
                    status.classList.add('error');
                    status.textContent = 'Ошибка сети при загрузке';
                });

                if (submitBtn) { submitBtn.disabled = true; submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Загрузка...'; }
                form.dataset.busy = '1';
                xhr.send(fd);
            });
        });
    }
    
    // ============================================
    // НАСТРОЙКИ ЛЕНТЫ
    // ============================================
    function initFeedSettings() {
        const settingsToggle = document.getElementById('settingsToggleBtn');
        const settingsModal = document.getElementById('settingsModal');
        
        window.toggleSettingsModal = function() {
            if (settingsModal) {
                settingsModal.classList.toggle('active');
                if (settingsModal.classList.contains('active')) {
                    loadSettingsToModal();
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        };
        
        if (settingsToggle && settingsModal) {
            settingsToggle.addEventListener('click', toggleSettingsModal);
        }
        
        // Загрузка фото с ПК
        const bgFileUpload = document.getElementById('bgFileUpload');
        if (bgFileUpload) {
            bgFileUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        document.body.style.backgroundImage = `url('${e.target.result}')`;
                        document.body.style.backgroundSize = 'cover';
                        document.body.style.backgroundPosition = 'center';
                        document.body.style.backgroundAttachment = 'fixed';
                        document.body.classList.add('custom-bg');
                        
                        // Сохраняем в localStorage
                        const settings = JSON.parse(localStorage.getItem('feedSettings') || '{}');
                        settings.bgImage = e.target.result;
                        localStorage.setItem('feedSettings', JSON.stringify(settings));
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Загрузка своего шрифта
        const fontFileUpload = document.getElementById('fontFileUpload');
        if (fontFileUpload) {
            fontFileUpload.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const fontFace = new FontFace('CustomFont', `url(${e.target.result})`);
                        fontFace.load().then(function(loadedFace) {
                            document.fonts.add(loadedFace);
                            document.body.style.fontFamily = 'CustomFont, sans-serif';
                            
                            // Сохраняем
                            const settings = JSON.parse(localStorage.getItem('feedSettings') || '{}');
                            settings.fontFamily = 'CustomFont, sans-serif';
                            localStorage.setItem('feedSettings', JSON.stringify(settings));
                        });
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
        
        // Слайдер размера шрифта
        const fontSizeInput = document.getElementById('fontSize');
        const fontSizeValue = document.getElementById('fontSizeValue');
        
        if (fontSizeInput && fontSizeValue) {
            fontSizeInput.addEventListener('input', function() {
                fontSizeValue.textContent = this.value + 'px';
            });
        }
        
        // Градиенты
        window.setGradient = function(gradient) {
            document.body.style.background = `linear-gradient(${gradient})`;
            document.body.style.backgroundAttachment = 'fixed';
            document.body.classList.add('custom-bg');
            
            // Сохраняем
            const settings = JSON.parse(localStorage.getItem('feedSettings') || '{}');
            settings.bgImage = '';
            settings.bgColor = '';
            localStorage.setItem('feedSettings', JSON.stringify(settings));
        };
    }
    
    // ============================================
    // ЗАГРУЗКА НАСТРОЕК В МОДАЛКУ
    // ============================================
    function loadSettingsToModal() {
        const settings = JSON.parse(localStorage.getItem('feedSettings') || '{}');
        
        // Цвет фона
        const bgColorInput = document.getElementById('bgColor');
        if (bgColorInput) {
            bgColorInput.value = settings.bgColor || '#f9fafb';
        }
        
        // Фоновое изображение
        const bgImageInput = document.getElementById('bgImage');
        if (bgImageInput) {
            bgImageInput.value = settings.bgImage || '';
        }
        
        // Цвет интерфейса
        const interfaceColorInput = document.getElementById('interfaceColor');
        if (interfaceColorInput) {
            interfaceColorInput.value = settings.interfaceColor || '#10b981';
        }
        
        // Размер шрифта
        const fontSizeInput = document.getElementById('fontSize');
        const fontSizeValue = document.getElementById('fontSizeValue');
        if (fontSizeInput) {
            fontSizeInput.value = settings.fontSize || '16';
            if (fontSizeValue) fontSizeValue.textContent = (settings.fontSize || '16') + 'px';
        }
        
        // Семейство шрифта
        const fontFamilySelect = document.getElementById('fontFamily');
        if (fontFamilySelect) {
            fontFamilySelect.value = settings.fontFamily || 'Inter, sans-serif';
        }
        
        // Без рамок
        const noBorderCheckbox = document.getElementById('noBorderPosts');
        if (noBorderCheckbox) {
            noBorderCheckbox.checked = settings.noBorderPosts || false;
        }
        
        // Темная тема
        const darkModeCheckbox = document.getElementById('darkMode');
        if (darkModeCheckbox) {
            darkModeCheckbox.checked = settings.darkMode || false;
        }
        
        // Звук
        const soundCheckbox = document.getElementById('enableSound');
        if (soundCheckbox) {
            soundCheckbox.checked = settings.enableSound || false;
        }
    }
    
    // ============================================
    // СОХРАНЕНИЕ НАСТРОЕК
    // ============================================
    window.saveFeedSettings = function() {
        const settings = {
            bgColor: document.getElementById('bgColor')?.value || '#f9fafb',
            bgImage: document.getElementById('bgImage')?.value || '',
            interfaceColor: document.getElementById('interfaceColor')?.value || '#10b981',
            fontSize: document.getElementById('fontSize')?.value || '16',
            fontFamily: document.getElementById('fontFamily')?.value || 'Inter, sans-serif',
            noBorderPosts: document.getElementById('noBorderPosts')?.checked || false,
            darkMode: document.getElementById('darkMode')?.checked || false,
            enableSound: document.getElementById('enableSound')?.checked || false
        };
        
        localStorage.setItem('feedSettings', JSON.stringify(settings));
        applyAllSettings(settings);
        toggleSettingsModal();
        showNotification('Настройки сохранены!');
    };
    
    // ============================================
    // ПРИМЕНЕНИЕ ВСЕХ НАСТРОЕК
    // ============================================
    function applyAllSettings(settings) {
        // ФОН
        if (settings.bgImage && settings.bgImage.trim() !== '') {
            document.body.style.backgroundImage = `url('${settings.bgImage}')`;
            document.body.style.backgroundSize = 'cover';
            document.body.style.backgroundPosition = 'center';
            document.body.style.backgroundAttachment = 'fixed';
            document.body.classList.add('custom-bg');
            document.body.style.backgroundColor = '';
        } 
        else if (settings.bgColor && settings.bgColor !== '#f9fafb') {
            document.body.style.backgroundColor = settings.bgColor;
            document.body.style.backgroundImage = '';
            document.body.classList.remove('custom-bg');
        }
        else {
            document.body.style.backgroundColor = '#f9fafb';
            document.body.style.backgroundImage = '';
            document.body.classList.remove('custom-bg');
        }
        
        // ЦВЕТ ИНТЕРФЕЙСА
        if (settings.interfaceColor) {
            document.documentElement.style.setProperty('--primary', settings.interfaceColor);
            
            // Затемняем для hover
            const darker = adjustColor(settings.interfaceColor, -20);
            document.documentElement.style.setProperty('--primary-dark', darker);
            
            // Осветляем для фона
            const lighter = adjustColor(settings.interfaceColor, 40);
            document.documentElement.style.setProperty('--primary-light', lighter + '20');
        }
        
        // РАЗМЕР ТЕКСТА
        if (settings.fontSize) {
            document.body.style.fontSize = settings.fontSize + 'px';
        }
        
        // ШРИФТ
        if (settings.fontFamily) {
            document.body.style.fontFamily = settings.fontFamily;
        }
        
        // РАМКИ ПОСТОВ
        document.querySelectorAll('.post-card').forEach(card => {
            if (settings.noBorderPosts) {
                card.classList.add('no-border');
                card.style.border = 'none';
            } else {
                card.classList.remove('no-border');
                card.style.border = '';
            }
        });
        
        // ТЕМНАЯ ТЕМА
        if (settings.darkMode) {
            applyDarkMode(true);
        } else {
            applyDarkMode(false);
        }
        
        // ЗВУК
        if (settings.enableSound && audio && !isPlaying) {
            audio.play();
            isPlaying = true;
            const playBtn = document.getElementById('playPauseBtn');
            if (playBtn) playBtn.innerHTML = '<i class="fas fa-pause"></i>';
        }
    }
    
    // ============================================
    // ЗАГРУЗКА НАСТРОЕК ПРИ СТАРТЕ
    // ============================================
    function loadFeedSettings() {
        const settings = JSON.parse(localStorage.getItem('feedSettings') || '{}');
        if (Object.keys(settings).length > 0) {
            applyAllSettings(settings);
        }
    }
    
    // ============================================
    // ФУНКЦИЯ ДЛЯ ИЗМЕНЕНИЯ ЦВЕТА
    // ============================================
    function adjustColor(hex, percent) {
        if (!hex) return '#10b981';
        hex = hex.replace('#', '');
        
        let r = parseInt(hex.substring(0, 2), 16);
        let g = parseInt(hex.substring(2, 4), 16);
        let b = parseInt(hex.substring(4, 6), 16);
        
        r = Math.min(255, Math.max(0, r + percent));
        g = Math.min(255, Math.max(0, g + percent));
        b = Math.min(255, Math.max(0, b + percent));
        
        return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
    }
    
    // ============================================
    // ТЕМНАЯ ТЕМА
    // ============================================
    function applyDarkMode(enabled) {
        if (enabled) {
            document.documentElement.style.setProperty('--background', '#1a1a1a');
            document.documentElement.style.setProperty('--card-bg', '#2d2d2d');
            document.documentElement.style.setProperty('--text', '#ffffff');
            document.documentElement.style.setProperty('--text-light', '#a0a0a0');
            document.documentElement.style.setProperty('--border', '#404040');
            document.documentElement.style.setProperty('--border-light', '#333333');
        } else {
            document.documentElement.style.setProperty('--background', '#f9fafb');
            document.documentElement.style.setProperty('--card-bg', '#ffffff');
            document.documentElement.style.setProperty('--text', '#111827');
            document.documentElement.style.setProperty('--text-light', '#6b7280');
            document.documentElement.style.setProperty('--border', '#e5e7eb');
            document.documentElement.style.setProperty('--border-light', '#f3f4f6');
        }
    }
    
    // ============================================
    // ЗВУКОВОЙ ПЛЕЕР (ПОЛНОСТЬЮ РАБОЧИЙ)
    // ============================================
    function initSoundPlayer() {
        const soundFileUpload = document.getElementById('soundFileUpload');
        const soundPlayer = document.getElementById('soundPlayer');
        const playPauseBtn = document.getElementById('playPauseBtn');
        const stopBtn = document.getElementById('stopBtn');
        const restartBtn = document.getElementById('restartBtn');
        const volumeSlider = document.getElementById('volumeSlider');
        const loopBtn = document.getElementById('loopBtn');
        const hidePlayerBtn = document.getElementById('hidePlayerBtn');
        
        if (!soundFileUpload || !soundPlayer) return;
        
        // Загрузка аудиофайла
        soundFileUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Останавливаем предыдущее аудио
            if (audio) {
                audio.pause();
                audio = null;
            }
            
            const url = URL.createObjectURL(file);
            audio = new Audio(url);
            audio.volume = volumeSlider ? volumeSlider.value : 0.5;
            
            // Показываем плеер
            soundPlayer.classList.remove('hidden');
            
            // Обновляем название файла
            const fileName = file.name;
            const soundLabel = document.querySelector('.sound-player label');
            if (soundLabel) soundLabel.textContent = 'Файл: ' + fileName;
            
            // Обработчик окончания
            audio.addEventListener('ended', function() {
                if (loopBtn && loopBtn.classList.contains('active')) {
                    audio.currentTime = 0;
                    audio.play();
                } else {
                    isPlaying = false;
                    if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            });
            
            // Сохраняем в настройки
            const settings = JSON.parse(localStorage.getItem('feedSettings') || '{}');
            settings.enableSound = true;
            localStorage.setItem('feedSettings', JSON.stringify(settings));
        });
        
        // Play/Pause
        if (playPauseBtn) {
            playPauseBtn.addEventListener('click', function() {
                if (!audio) {
                    alert('Сначала загрузите аудиофайл!');
                    return;
                }
                
                if (isPlaying) {
                    audio.pause();
                    this.innerHTML = '<i class="fas fa-play"></i>';
                } else {
                    audio.play();
                    this.innerHTML = '<i class="fas fa-pause"></i>';
                }
                isPlaying = !isPlaying;
            });
        }
        
        // Stop
        if (stopBtn) {
            stopBtn.addEventListener('click', function() {
                if (!audio) return;
                
                audio.pause();
                audio.currentTime = 0;
                isPlaying = false;
                if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
            });
        }
        
        // Restart
        if (restartBtn) {
            restartBtn.addEventListener('click', function() {
                if (!audio) return;
                
                audio.currentTime = 0;
                if (!isPlaying) {
                    audio.play();
                    isPlaying = true;
                    if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                }
            });
        }
        
        // Громкость
        if (volumeSlider) {
            volumeSlider.addEventListener('input', function() {
                if (audio) {
                    audio.volume = this.value;
                }
            });
        }
        
        // Зацикливание
        if (loopBtn) {
            loopBtn.addEventListener('click', function() {
                this.classList.toggle('active');
                this.style.color = this.classList.contains('active') ? 'var(--primary)' : '';
            });
        }
        
        // Скрыть плеер
        if (hidePlayerBtn) {
            hidePlayerBtn.addEventListener('click', function() {
                soundPlayer.classList.add('hidden');
                if (audio && isPlaying) {
                    audio.pause();
                    isPlaying = false;
                    if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            });
        }
        
        // Обработка чекбокса "Включить фоновую музыку"
        const enableSoundCheckbox = document.getElementById('enableSound');
        if (enableSoundCheckbox) {
            enableSoundCheckbox.addEventListener('change', function(e) {
                if (!audio && e.target.checked) {
                    alert('Сначала загрузите аудиофайл!');
                    e.target.checked = false;
                    return;
                }
                
                if (e.target.checked && audio) {
                    audio.play();
                    isPlaying = true;
                    if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                    soundPlayer.classList.remove('hidden');
                } else if (!e.target.checked && audio) {
                    audio.pause();
                    isPlaying = false;
                    if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
                
                // Сохраняем
                const settings = JSON.parse(localStorage.getItem('feedSettings') || '{}');
                settings.enableSound = e.target.checked;
                localStorage.setItem('feedSettings', JSON.stringify(settings));
            });
        }
    }
    
    // ============================================
    // УВЕДОМЛЕНИЯ
    // ============================================
    function initNotifications() {
        const notificationBell = document.getElementById('notificationBell');
        const notificationDropdown = document.getElementById('notificationDropdown');
        const notificationList = document.getElementById('notificationList');
        const notificationBadge = document.getElementById('notificationBadge');
        const markAllRead = document.getElementById('markAllRead');
        
        if (notificationBell && notificationDropdown) {
            notificationBell.addEventListener('click', function(e) {
                e.stopPropagation();
                notificationDropdown.classList.toggle('show');
                if (notificationDropdown.classList.contains('show')) {
                    loadNotifications();
                }
            });
            
            document.addEventListener('click', function(e) {
                if (!notificationBell.contains(e.target) && !notificationDropdown.contains(e.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });
        }
        
        function loadNotifications() {
            fetch('/api.php?action=get_notifications')
                .then(response => response.json())
                .then(data => {
                    const nots = data.notifications || [];
                    displayNotifications(nots);
                    const unread = nots.filter(n => !n.read).length;
                    if (notificationBadge) {
                        if (unread > 0) { notificationBadge.textContent = unread; notificationBadge.classList.remove('hidden'); }
                        else notificationBadge.classList.add('hidden');
                    }
                })
                .catch(error => console.error('Error loading notifications:', error));
        }
        
        function displayNotifications(notifications) {
            if (!notificationList) return;
            
            if (notifications.length === 0) {
                notificationList.innerHTML = '<div class="notification-empty">Нет уведомлений</div>';
                return;
            }
            
            let html = '';
            notifications.forEach(notification => {
                let content = '';
                let icon = 'fas fa-bell';
                
                if (notification.type === 'mention') {
                    const fromUser = notification.data?.from_username || '';
                    content = '<p><strong>@' + escapeHtml(fromUser) + '</strong> упомянул(а) вас в комментарии</p>';
                    icon = 'fas fa-at';
                } else if (notification.type === 'like') {
                    const fromUser = notification.data?.from_username || 'Кто-то';
                    const postTitle = notification.data?.post_title || 'ваш пост';
                    content = '<p><strong>@' + escapeHtml(fromUser) + '</strong> поставил(а) ❤️ на «' + escapeHtml(postTitle) + '»</p>';
                    icon = 'fas fa-heart';
                } else if (notification.type === 'comment') {
                    const fromUser = notification.data?.from_username || 'Кто-то';
                    const commentText = notification.data?.comment_preview || '';
                    content = '<p><strong>@' + escapeHtml(fromUser) + '</strong> написал(а): ' + escapeHtml(commentText) + '</p>';
                    icon = 'fas fa-comment';
                }
                
                html += `
                    <div class="notification-item ${notification.read ? '' : 'unread'}" data-id="${notification.id}" onclick="markNotificationRead('${notification.id}')">
                        <div class="notification-content">
                            <div class="notification-icon">
                                <i class="${icon}"></i>
                            </div>
                            <div class="notification-text">
                                ${content}
                                <div class="notification-time">${notification.created_at || ''}</div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            notificationList.innerHTML = html;
        }
        
        window.markNotificationRead = function(notificationId) {
            const formData = new FormData();
            formData.append('action', 'mark_read');
            formData.append('notification_id', notificationId);
            fetch('/api.php', { method: 'POST', body: formData })
                .then(response => response.json())
                .then(data => { loadNotifications(); })
                .catch(error => console.error('Error marking notification as read:', error));
        };
        
        if (markAllRead) {
            markAllRead.addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'mark_all_notifications_read');
                
                fetch('/api.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => { loadNotifications(); })
                    .catch(error => console.error('Error marking all notifications as read:', error));
            });
        }

        const notificationsPageMarkAllRead = document.getElementById('notificationsPageMarkAllRead');
        if (notificationsPageMarkAllRead) {
            notificationsPageMarkAllRead.addEventListener('click', function() {
                const formData = new FormData();
                formData.append('action', 'mark_all_notifications_read');

                fetch('/api.php', { method: 'POST', body: formData })
                    .then(response => response.json())
                    .then(data => {
                        window.location.reload();
                    })
                    .catch(error => console.error('Error marking all notifications as read:', error));
            });
        }
        
        // Загружаем уведомления каждые 30 секунд
        <?php if (getCurrentUser()): ?>
        setInterval(loadNotifications, 30000);
        loadNotifications();
        <?php endif; ?>
    }
    
    // ============================================
    // УВЕДОМЛЕНИЯ ДЛЯ ПОЛЬЗОВАТЕЛЯ
    // ============================================
    function showNotification(message, type = 'success') {
        const notification = document.createElement('div');
        notification.className = `alert alert-${type} animate-fade`;
        notification.innerHTML = `
            <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-info-circle'}"></i>
            <span>${message}</span>
        `;
        notification.style.position = 'fixed';
        notification.style.top = '80px';
        notification.style.right = '20px';
        notification.style.zIndex = '1000';
        notification.style.maxWidth = '300px';
        notification.style.animation = 'fadeIn 0.3s ease';
        
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 3000);
    }
    
    // ============================================
    // ОНБОРДИНГ
    // ============================================
    function checkOnboarding() {
        const dismissed = localStorage.getItem('onboardingDismissed');
        const sessionOnboarding = <?php echo isset($_SESSION['onboarding_dismissed']) ? 'true' : 'false'; ?>;
        
        if (!dismissed && !sessionOnboarding) {
            const modal = document.getElementById('onboardingModal');
            if (modal) setTimeout(() => modal.classList.add('active'), 500);
        }
    }
    
    window.dismissOnboarding = function() {
        localStorage.setItem('onboardingDismissed', 'true');
        
        const formData = new FormData();
        formData.append('action', 'dismiss_onboarding');
        fetch('/', { method: 'POST', body: formData });
        
        const modal = document.getElementById('onboardingModal');
        if (modal) modal.classList.remove('active');
    };

    // ============================================
    // ФИЛЬТРАЦИЯ КАРТОЧЕК (музыка, стикеры, код, оформление)
    // ============================================
    window.filterCards = function(type) {
        const input = document.getElementById(type + 'Search') || document.getElementById(type === 'sticker' ? 'stickerSearch' : type + 'Search');
        if (!input) return;
        const query = input.value.toLowerCase();
        const container = input.closest('.content-section') || input.parentElement?.parentElement;
        if (!container) return;
        const cards = container.querySelectorAll('.content-card, .sticker-item');
        cards.forEach(card => {
            const title = (card.querySelector('.card-title')?.textContent || card.dataset.title || '').toLowerCase();
            const tags = (card.querySelector('.card-tags')?.textContent || card.dataset.tags || '').toLowerCase();
            const author = (card.querySelector('.card-author')?.textContent || '').toLowerCase();
            card.style.display = (title.includes(query) || tags.includes(query) || author.includes(query)) ? '' : 'none';
        });
    };

    // ============================================
    // ВОСПРОИЗВЕДЕНИЕ МУЗЫКИ ИЗ КАРТОЧЕК
    // ============================================
    window.playMusic = function(url, title) {
        if (audio) { audio.pause(); }
        audio = new Audio(url);
        audio.play();
        isPlaying = true;

        const soundPlayer = document.getElementById('soundPlayer');
        if (soundPlayer) {
            soundPlayer.classList.remove('hidden');
            const soundLabel = soundPlayer.querySelector('label');
            if (soundLabel) soundLabel.textContent = '🎵 ' + (title || 'Музыка');
        }
        const playPauseBtn = document.getElementById('playPauseBtn');
        if (playPauseBtn) playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
        showNotification('Воспроизведение: ' + (title || ''));
    };

    // ============================================
    // МОДАЛЬНОЕ ОКНО СТИКЕРА
    // ============================================
    window.openStickerModal = function(stickerData) {
        let modal = document.getElementById('stickerInfoModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'stickerInfoModal';
            modal.className = 'modal';
            modal.innerHTML = '<div class="modal-content" style="max-width:400px;text-align:center;"><div class="modal-header"><h3 class="modal-title" id="stickerModalTitle"></h3><button class="modal-close" onclick="toggleModal(\'stickerInfoModal\')">&times;</button></div><div class="modal-body"><div id="stickerModalImage" style="margin-bottom:16px;"></div><p id="stickerModalAuthor" style="color:var(--text-light);"></p><p id="stickerModalDesc"></p><div id="stickerModalTags" style="margin-top:8px;"></div><div style="margin-top:16px;"><button class="btn btn-primary btn-sm" id="stickerLikeBtn" onclick="likeItem(this.dataset.slug,\'sticker\',this)"><i class="far fa-heart"></i> <span class="like-count">0</span></button></div></div></div>';
            document.body.appendChild(modal);
        }
        const data = typeof stickerData === 'string' ? JSON.parse(stickerData) : stickerData;
        document.getElementById('stickerModalTitle').textContent = data.title || 'Стикер';
        document.getElementById('stickerModalAuthor').textContent = '@' + (data.author || '');
        document.getElementById('stickerModalDesc').textContent = data.description || '';
        const imgDiv = document.getElementById('stickerModalImage');
        if (data.file && (data.file.endsWith('.webm') || data.file.endsWith('.mp4'))) {
            imgDiv.innerHTML = '<video src="' + data.file + '" autoplay loop muted style="max-width:100%;max-height:250px;border-radius:12px;"></video>';
        } else {
            imgDiv.innerHTML = '<img src="' + (data.file || '') + '" style="max-width:100%;max-height:250px;border-radius:12px;">';
        }
        const tagsDiv = document.getElementById('stickerModalTags');
        if (data.tags) {
            tagsDiv.innerHTML = data.tags.split(',').map(t => '<span class="tag">' + escapeHtml(t.trim()) + '</span>').join(' ');
        } else { tagsDiv.innerHTML = ''; }
        const likeBtn = document.getElementById('stickerLikeBtn');
        if (likeBtn) {
            likeBtn.dataset.slug = data.slug || '';
            likeBtn.querySelector('.like-count').textContent = data.likes || 0;
        }
        toggleModal('stickerInfoModal');
    };

    // ============================================
    // КОПИРОВАНИЕ CSS КОДА (оформление)
    // ============================================
    window.copyCssCode = function(cssCode) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(cssCode).then(() => showNotification('CSS код скопирован!')).catch(() => fallbackCopy(cssCode));
        } else { fallbackCopy(cssCode); }
    };
    function fallbackCopy(text) {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
        showNotification('CSS код скопирован!');
    }

    // ============================================
    // ЛАЙКИ ДЛЯ КАРТОЧЕК (music/sticker/coding/theme)
    // ============================================
    window.likeItem = function(type, itemId, btn) {
        <?php if (!getCurrentUser()): ?>
            window.location.href = '/?action=login';
            return;
        <?php endif; ?>

        fetch('/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=like_item&item_id=' + encodeURIComponent(itemId) + '&item_type=' + encodeURIComponent(type)
        })
        .then(r => r.json())
        .then(data => {
            if (btn) {
                const icon = btn.querySelector('i');
                const count = btn.querySelector('.like-count');
                if (data.liked) {
                    if (icon) { icon.classList.remove('far'); icon.classList.add('fas'); }
                    btn.classList.add('active');
                } else {
                    if (icon) { icon.classList.remove('fas'); icon.classList.add('far'); }
                    btn.classList.remove('active');
                }
                if (count) count.textContent = data.likes;
            }
        })
        .catch(e => console.error('Like error:', e));
    };

    // API call helper
    window.apiCall = function(url, data) {
        const body = new URLSearchParams(data).toString();
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(r => r.json());
    };

    // Toast notification
    window.showToast = function(msg, type) {
        const toast = document.createElement('div');
        toast.className = 'toast ' + (type || 'success');
        toast.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : 'check-circle') + '"></i> ' + msg;
        toast.style.cssText = 'position:fixed;bottom:20px;right:20px;padding:12px 20px;background:#10b981;color:#fff;border-radius:12px;z-index:99999;font-size:14px;font-weight:500;box-shadow:0 4px 12px rgba(0,0,0,0.15);animation:fadeInUp 0.3s ease;display:flex;align-items:center;gap:8px;';
        if (type === 'error') toast.style.background = '#ef4444';
        document.body.appendChild(toast);
        setTimeout(() => { toast.style.opacity = '0'; setTimeout(() => toast.remove(), 300); }, 3000);
    };

    // ============================================
    // СПЕЦИАЛЬНЫЕ СИМВОЛЫ: §, ;, ~, &, @@
    // Менюшки для контента в постах/комментариях
    // ============================================
    function initSpecialCommands() {
        // Support both textarea and contenteditable elements
        const textInputs = document.querySelectorAll('textarea[name="content"], textarea[name="comment"], [contenteditable="true"]');
        textInputs.forEach(input => {
            const isContentEditable = input.hasAttribute('contenteditable');
            input.addEventListener('input', function(e) {
                let textBefore = '';
                if (isContentEditable) {
                    const sel = window.getSelection();
                    if (sel.rangeCount) {
                        const range = sel.getRangeAt(0);
                        const preRange = range.cloneRange();
                        preRange.selectNodeContents(this);
                        preRange.setEnd(range.startContainer, range.startOffset);
                        textBefore = preRange.toString();
                    }
                } else {
                    const val = this.value;
                    const cursorPos = this.selectionStart;
                    textBefore = val.substring(0, cursorPos);
                }

                const lastWord = textBefore.split(/\s/).pop();

                if (lastWord === '§') {
                    showCommandMenu('music', this);
                } else if (lastWord === ';') {
                    showCommandMenu('sticker', this);
                } else if (lastWord === '~') {
                    showCommandMenu('theme', this);
                } else if (lastWord === '&') {
                    showCommandMenu('coding', this);
                } else if (lastWord.startsWith('@@')) {
                    showUserMentionMenu(lastWord.substring(2), this);
                } else {
                    hideCommandMenu();
                }
            });
        });
    }
    initSpecialCommands();

    let activeCommandMenu = null;

    function showCommandMenu(type, inputEl) {
        hideCommandMenu();
        const menu = document.createElement('div');
        menu.className = 'command-menu';
        menu.id = 'commandMenu';
        const typeNames = { music: 'Музыка', sticker: 'Стикеры', theme: 'Оформление', coding: 'Кодинг' };
        const typeIcons = { music: 'fa-music', sticker: 'fa-sticky-note', theme: 'fa-palette', coding: 'fa-code' };
        menu.innerHTML = `<div class="command-menu-header"><i class="fas ${typeIcons[type]}"></i> ${typeNames[type]}</div><div class="command-menu-search"><input type="text" placeholder="Поиск по юз..." class="form-input" id="cmdSearchInput"></div><div class="command-menu-list" id="cmdList"><div style="padding:12px;color:var(--text-light);text-align:center;">Загрузка...</div></div>`;

        // Position near the textarea
        const rect = inputEl.getBoundingClientRect();
        menu.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.bottom+4}px;z-index:9999;width:320px;max-height:300px;background:var(--card-bg);border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.15);overflow:hidden;`;

        document.body.appendChild(menu);
        activeCommandMenu = menu;

        // Fetch items
        fetch('/api.php?action=get_items&type=' + type)
            .then(r => r.json())
            .then(data => {
                if (!data.items || data.items.length === 0) {
                    document.getElementById('cmdList').innerHTML = '<div style="padding:12px;color:var(--text-light);text-align:center;">Пусто</div>';
                    return;
                }
                renderCommandItems(data.items, type, inputEl);
            })
            .catch(() => {
                document.getElementById('cmdList').innerHTML = '<div style="padding:12px;color:var(--text-light);">Ошибка</div>';
            });

        // Search
        setTimeout(() => {
            const searchInput = document.getElementById('cmdSearchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const q = this.value.toLowerCase();
                    document.querySelectorAll('.command-item').forEach(item => {
                        const text = (item.dataset.title + ' ' + item.dataset.use).toLowerCase();
                        item.style.display = text.includes(q) ? '' : 'none';
                    });
                });
                searchInput.focus();
            }
        }, 100);
    }

    function renderCommandItems(items, type, inputEl) {
        const list = document.getElementById('cmdList');
        if (!list) return;
        // Sort by likes desc
        items.sort((a, b) => (b.likes || 0) - (a.likes || 0));
        let html = '';
        items.forEach(item => {
            html += `<div class="command-item" data-title="${escapeHtml(item.title)}" data-use="${escapeHtml(item.use || '')}" data-type="${type}" data-slug="${item.slug || ''}" style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;border-bottom:1px solid var(--border-light);" onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background=''">`;
            if (type === 'music' && item.icon) {
                html += `<img src="${item.icon}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">`;
            } else if (type === 'sticker' && item.file) {
                html += `<img src="${item.file}" style="width:32px;height:32px;border-radius:6px;object-fit:cover;">`;
            } else {
                html += `<div style="width:32px;height:32px;border-radius:6px;background:var(--primary-light);display:flex;align-items:center;justify-content:center;"><i class="fas fa-${type === 'music' ? 'music' : type === 'sticker' ? 'sticky-note' : type === 'theme' ? 'palette' : 'code'}" style="color:var(--primary);font-size:14px;"></i></div>`;
            }
            html += `<div style="flex:1;min-width:0;"><div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${escapeHtml(item.title)}</div><div style="font-size:11px;color:var(--text-light);">@${escapeHtml(item.author || '')} · ❤️ ${item.likes || 0}</div></div></div>`;
        });
        list.innerHTML = html;

        list.querySelectorAll('.command-item').forEach(el => {
            el.addEventListener('click', function() {
                const useVal = this.dataset.use || this.dataset.title;
                const typeVal = this.dataset.type;
                const symbols = { music: '§', sticker: ';', theme: '~', coding: '&' };
                const symbol = symbols[typeVal] || '';
                const isContentEditable = inputEl.hasAttribute('contenteditable');
                if (isContentEditable) {
                    // For contenteditable, replace the trigger symbol in text content
                    let text = inputEl.innerText || inputEl.textContent;
                    const lastSymbolIdx = text.lastIndexOf(symbol);
                    if (lastSymbolIdx !== -1) {
                        inputEl.innerText = text.substring(0, lastSymbolIdx) + symbol + useVal + ' ' + text.substring(lastSymbolIdx + 1);
                    }
                } else {
                    const val = inputEl.value;
                    const cursorPos = inputEl.selectionStart;
                    const textBefore = val.substring(0, cursorPos);
                    const textAfter = val.substring(cursorPos);
                    const lastSymbolIdx = textBefore.lastIndexOf(symbol);
                    if (lastSymbolIdx !== -1) {
                        inputEl.value = textBefore.substring(0, lastSymbolIdx) + symbol + useVal + ' ' + textAfter;
                    }
                }
                hideCommandMenu();
                inputEl.focus();
            });
        });
    }

    function showUserMentionMenu(query, inputEl) {
        if (query.length < 1) { hideCommandMenu(); return; }
        hideCommandMenu();

        fetch('/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=search&query=' + encodeURIComponent(query)
        })
        .then(r => r.json())
        .then(data => {
            if (!data.users || data.users.length === 0) return;
            const menu = document.createElement('div');
            menu.className = 'command-menu';
            menu.id = 'commandMenu';
            const rect = inputEl.getBoundingClientRect();
            menu.style.cssText = `position:fixed;left:${rect.left}px;top:${rect.bottom+4}px;z-index:9999;width:260px;max-height:200px;background:var(--card-bg);border:1px solid var(--border);border-radius:12px;box-shadow:0 8px 30px rgba(0,0,0,0.15);overflow:auto;`;
            let html = '';
            data.users.forEach(user => {
                html += `<div class="command-item" data-username="${escapeHtml(user.username)}" style="display:flex;align-items:center;gap:10px;padding:8px 12px;cursor:pointer;" onmouseover="this.style.background='var(--primary-light)'" onmouseout="this.style.background=''"><img src="${user.avatar}" style="width:28px;height:28px;border-radius:50%;"><span style="font-weight:600;font-size:13px;">@${escapeHtml(user.username)}</span></div>`;
            });
            menu.innerHTML = html;
            document.body.appendChild(menu);
            activeCommandMenu = menu;
            menu.querySelectorAll('.command-item').forEach(el => {
                el.addEventListener('click', function() {
                    const username = this.dataset.username;
                    const isContentEditable = inputEl.hasAttribute('contenteditable');
                    if (isContentEditable) {
                        let text = inputEl.innerText || inputEl.textContent;
                        const lastAtIdx = text.lastIndexOf('@@');
                        if (lastAtIdx !== -1) {
                            inputEl.innerText = text.substring(0, lastAtIdx) + '@@' + username + ' ' + text.substring(lastAtIdx + 2 + (text.substring(lastAtIdx + 2).split(/\s/)[0] || '').length);
                        }
                    } else {
                        const val = inputEl.value;
                        const cursorPos = inputEl.selectionStart;
                        const textBefore = val.substring(0, cursorPos);
                        const textAfter = val.substring(cursorPos);
                        const lastAtIdx = textBefore.lastIndexOf('@@');
                        if (lastAtIdx !== -1) {
                            inputEl.value = textBefore.substring(0, lastAtIdx) + '@@' + username + ' ' + textAfter;
                        }
                    }
                    hideCommandMenu();
                    inputEl.focus();
                });
            });
        });
    }

    function hideCommandMenu() {
        if (activeCommandMenu) { activeCommandMenu.remove(); activeCommandMenu = null; }
        const existing = document.getElementById('commandMenu');
        if (existing) existing.remove();
    }

    document.addEventListener('click', function(e) {
        if (activeCommandMenu && !activeCommandMenu.contains(e.target)) {
            hideCommandMenu();
        }
    });

    // ============================================
    // РЕНДЕРИНГ EMBED-КАРТОЧЕК В КОНТЕНТЕ (безопасно через текстовые ноды)
    // Преобразует §юз, ;юз, ~юз, &юз, @@юзер в карточки
    // ============================================
    function replaceInTextNodes(root, regex, replacer) {
        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, null, false);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(textNode => {
            const parent = textNode.parentNode;
            const text = textNode.nodeValue;
            if (!regex.test(text)) return;
            let frag = document.createDocumentFragment();
            let lastIndex = 0;
            text.replace(regex, function() {
                const args = arguments;
                const match = args[0];
                const index = args[args.length - 2];
                if (index > lastIndex) {
                    frag.appendChild(document.createTextNode(text.substring(lastIndex, index)));
                }
                const replacementHtml = replacer.apply(null, arguments);
                const wrapper = document.createElement('span');
                wrapper.innerHTML = replacementHtml;
                while (wrapper.firstChild) frag.appendChild(wrapper.firstChild);
                lastIndex = index + match.length;
            });
            if (lastIndex < text.length) frag.appendChild(document.createTextNode(text.substring(lastIndex)));
            parent.replaceChild(frag, textNode);
        });
    }

    function renderEmbedCards() {
        document.querySelectorAll('.post-text, .comment-text').forEach(el => {
            if (el.dataset.rendered) return;
            el.dataset.rendered = '1';

            // @@username -> profile card
            replaceInTextNodes(el, /@@(\w+)/g, function(match, username) {
                return `<span class="mention-card" onclick="window.location='/${username}'"><i class="fas fa-user"></i> @${escapeHtml(username)}</span>`;
            });

            // @username -> normal mention (avoid @@)
            replaceInTextNodes(el, /@(\w+)(?!@)/g, function(match, username) {
                return `<a href="/${username}" class="mention-link">@${escapeHtml(username)}</a>`;
            });

            // §use -> music
            replaceInTextNodes(el, /§(\S+)/g, function(match, use) {
                return `<span class="embed-card embed-music" data-type="music" data-use="${escapeHtml(use)}" onclick="loadEmbedCard(this,'music','${escapeHtml(use)}')"><span class="embed-card-icon music"><i class="fas fa-music"></i></span><div class="embed-card-info"><div class="embed-card-title">${escapeHtml(use)}</div></div></span>`;
            });

            // ;use -> sticker
            replaceInTextNodes(el, /;(\S+)/g, function(match, use) {
                return `<span class="embed-card embed-sticker" data-type="sticker" data-use="${escapeHtml(use)}" onclick="loadEmbedCard(this,'sticker','${escapeHtml(use)}')"><span class="embed-card-icon sticker"><i class="fas fa-sticky-note"></i></span><div class="embed-card-info"><div class="embed-card-title">${escapeHtml(use)}</div></div></span>`;
            });

            // ~use -> theme
            replaceInTextNodes(el, /~(\S+)/g, function(match, use) {
                return `<span class="embed-card embed-theme" data-type="theme" data-use="${escapeHtml(use)}" onclick="loadEmbedCard(this,'theme','${escapeHtml(use)}')"><span class="embed-card-icon theme"><i class="fas fa-palette"></i></span><div class="embed-card-info"><div class="embed-card-title">${escapeHtml(use)}</div></div></span>`;
            });

            // &use -> coding
            replaceInTextNodes(el, /&(?!#?\w+;)(\S+)/g, function(match, use) {
                return `<span class="embed-card embed-coding" data-type="coding" data-use="${escapeHtml(use)}" onclick="loadEmbedCard(this,'coding','${escapeHtml(use)}')"><span class="embed-card-icon code"><i class="fas fa-code"></i></span><div class="embed-card-info"><div class="embed-card-title">${escapeHtml(use)}</div></div></span>`;
            });
        });
    }
    renderEmbedCards();

    // Image carousel for posts: detect multiple images inside .post-text and wrap them
    function initImageCarousels() {
        document.querySelectorAll('.post-text').forEach(el => {
            if (el.dataset.carouselInit) return;
            const imgs = Array.from(el.querySelectorAll('img'));
            if (imgs.length <= 1) return;
            el.dataset.carouselInit = '1';
            const carousel = document.createElement('div');
            carousel.className = 'image-carousel';
            const track = document.createElement('div');
            track.className = 'image-carousel-track';
            imgs.forEach(img => {
                const slide = document.createElement('div');
                slide.className = 'image-carousel-slide';
                slide.appendChild(img.cloneNode(true));
                track.appendChild(slide);
                img.remove();
            });
            carousel.appendChild(track);
            const prev = document.createElement('button'); prev.className = 'carousel-control carousel-prev'; prev.innerHTML = '&lt;';
            const next = document.createElement('button'); next.className = 'carousel-control carousel-next'; next.innerHTML = '&gt;';
            carousel.appendChild(prev); carousel.appendChild(next);
            const dots = document.createElement('div'); dots.className = 'carousel-dots';
            for (let i=0;i<track.children.length;i++){
                const d = document.createElement('div'); d.className='carousel-dot'; if(i===0) d.classList.add('active'); d.dataset.index=i; dots.appendChild(d);
            }
            carousel.appendChild(dots);
            el.insertBefore(carousel, el.firstChild);

            let idx = 0;
            function update() {
                track.style.transform = 'translateX(' + (-idx*100) + '%)';
                dots.querySelectorAll('.carousel-dot').forEach((dot,i)=>dot.classList.toggle('active', i===idx));
            }
            prev.addEventListener('click', ()=>{ idx = (idx-1+track.children.length)%track.children.length; update(); });
            next.addEventListener('click', ()=>{ idx = (idx+1)%track.children.length; update(); });
            dots.querySelectorAll('.carousel-dot').forEach(d=>d.addEventListener('click', e=>{ idx = parseInt(e.target.dataset.index); update(); }));
        });
    }
    initImageCarousels();

    // Load full embed card data on click
    window.loadEmbedCard = function(el, type, use) {
        fetch('/api.php?action=get_items&type=' + type + '&search=' + encodeURIComponent(use))
            .then(r => r.json())
            .then(data => {
                if (!data.items || data.items.length === 0) {
                    showNotification('Не найдено: ' + use, 'info');
                    return;
                }
                const item = data.items.find(i => i.use === use) || data.items[0];
                if (type === 'music' && item.file) {
                    playMusic(item.file, item.title);
                } else if (type === 'sticker') {
                    openStickerModal(item);
                } else if (type === 'theme' && item.css_code) {
                    copyCssCode(item.css_code);
                } else if (type === 'coding') {
                    if (item.file) {
                        window.open(item.file, '_blank');
                    } else {
                        showNotification('Файл не найден', 'info');
                    }
                }
            })
            .catch(e => console.error('Embed load error:', e));
    };
});
</script>
</body>
</html>