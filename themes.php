<?php
/**
 * themes.php — Полноценная страница оформления/тем My Cryndel
 * CSS-темы, превью, применение к профилю, комментарии, лайки
 */
require_once __DIR__ . '/functions.php';

$user = fn_getCurrentUser();
$view = $_GET['view'] ?? 'list';
$itemId = $_GET['id'] ?? '';
$message = '';
$messageType = '';

// ============================================
// ОБРАБОТКА ФОРМ
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user) {
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_theme') {
        $title = trim($_POST['title'] ?? '');
        $use = trim($_POST['use'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $cssCode = trim($_POST['css_code'] ?? '');
        $previewUrl = trim($_POST['preview_url'] ?? '');
        $themeType = trim($_POST['theme_type'] ?? 'profile');
        $isAuthor = !empty($_POST['is_author']);
        $isAI = !empty($_POST['is_ai']);
        $agreeRules = !empty($_POST['agree_rules']);

        if (empty($title) || empty($use) || empty($cssCode)) {
            $message = 'Заполните обязательные поля: название, юз и CSS код';
            $messageType = 'error';
        } elseif (!$agreeRules) {
            $message = 'Необходимо согласиться с правилами';
            $messageType = 'error';
        } elseif (strlen($cssCode) > 50000) {
            $message = 'CSS код не должен превышать 50 000 символов';
            $messageType = 'error';
        } else {
            $uploadedPreview = fn_handleUpload('preview_file', ['jpg', 'jpeg', 'png', 'gif', 'webp'], MAX_AVATAR_SIZE);
            $previewFile = $uploadedPreview ?: $previewUrl;

            $id = fn_generateId();
            $slug = fn_generateSlug($title);
            $item = [
                'id' => $id,
                'slug' => $slug,
                'title' => $title,
                'use' => $use,
                'description' => $description,
                'tags' => $tags,
                'css_code' => $cssCode,
                'preview' => $previewFile,
                'theme_type' => $themeType,
                'author' => $user['username'],
                'user_id' => $user['id'],
                'is_author' => $isAuthor,
                'is_ai' => $isAI,
                'likes' => 0,
                'liked_by' => [],
                'applied_by' => [],
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
            file_put_contents(THEMES_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = 'Оформление загружено!';
            $messageType = 'success';
            $view = 'detail';
            $itemId = $id;
        }
    }

    if ($action === 'apply_theme' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('theme', $_POST['item_id']);
        if ($item && !empty($item['css_code'])) {
            $settings = fn_getUserProfileSettings($user['id']);
            $settings['custom_css'] = $item['css_code'];
            $settings['applied_theme'] = $item['title'];
            $settings['applied_theme_id'] = $item['id'];
            fn_saveUserProfileSettings($user['id'], $settings);
            // Track application
            if (!isset($item['applied_by'])) $item['applied_by'] = [];
            if (!in_array($user['id'], $item['applied_by'])) {
                $item['applied_by'][] = $user['id'];
                file_put_contents(THEMES_DIR . $_POST['item_id'] . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            $message = 'Оформление «' . fn_sanitize($item['title']) . '» применено к вашему профилю!';
            $messageType = 'success';
            $view = 'detail';
            $itemId = $_POST['item_id'];
        }
    }

    if ($action === 'add_comment' && !empty($_POST['item_id']) && !empty(trim($_POST['comment_text'] ?? ''))) {
        fn_addItemComment('theme', $_POST['item_id'], $user['id'], trim($_POST['comment_text']));
        $message = 'Комментарий добавлен';
        $messageType = 'success';
        $view = 'detail';
        $itemId = $_POST['item_id'];
    }

    if ($action === 'delete_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('theme', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            @unlink(THEMES_DIR . $_POST['item_id'] . '.json');
            $message = 'Оформление удалено';
            $messageType = 'success';
            $view = 'list';
        }
    }

    if ($action === 'edit_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('theme', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            $item['title'] = trim($_POST['title'] ?? $item['title']);
            $item['description'] = trim($_POST['description'] ?? $item['description']);
            $item['tags'] = trim($_POST['tags'] ?? $item['tags']);
            $item['css_code'] = trim($_POST['css_code'] ?? $item['css_code']);
            $item['theme_type'] = trim($_POST['theme_type'] ?? $item['theme_type']);
            $item['updated_at'] = date('Y-m-d H:i:s');
            $newPreview = fn_handleUpload('preview_file', ['jpg', 'jpeg', 'png', 'gif', 'webp'], MAX_AVATAR_SIZE);
            if ($newPreview) $item['preview'] = $newPreview;
            file_put_contents(THEMES_DIR . $_POST['item_id'] . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = 'Обновлено';
            $messageType = 'success';
            $view = 'detail';
            $itemId = $_POST['item_id'];
        }
    }
}

// ============================================
// ДАННЫЕ
// ============================================
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'likes';
$typeFilter = $_GET['type'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;

$allItems = fn_getItems('theme', $search, 999, 0, $sort);
if ($typeFilter) {
    $allItems = array_filter($allItems, function($i) use ($typeFilter) { return ($i['theme_type'] ?? '') === $typeFilter; });
    $allItems = array_values($allItems);
}
$totalItems = count($allItems);
$totalPages = max(1, ceil($totalItems / $perPage));
$items = array_slice($allItems, ($page - 1) * $perPage, $perPage);

$themeTypes = [
    'profile' => ['name' => 'Профиль', 'icon' => 'user', 'color' => '#8b5cf6'],
    'background' => ['name' => 'Фон', 'icon' => 'image', 'color' => '#06b6d4'],
    'posts' => ['name' => 'Посты', 'icon' => 'file-alt', 'color' => '#10b981'],
    'header' => ['name' => 'Шапка', 'icon' => 'heading', 'color' => '#f59e0b'],
    'full' => ['name' => 'Полная тема', 'icon' => 'palette', 'color' => '#ec4899'],
    'animation' => ['name' => 'Анимации', 'icon' => 'magic', 'color' => '#3b82f6'],
    'other' => ['name' => 'Другое', 'icon' => 'brush', 'color' => '#6b7280'],
];

$detailItem = null;
$detailComments = [];
if ($view === 'detail' && $itemId) {
    $detailItem = fn_getItemById('theme', $itemId);
    if ($detailItem) $detailComments = fn_getItemComments('theme', $itemId);
}

// ============================================
// РЕНДЕРИНГ
// ============================================
$extraCss = '
.themes-hero { background: linear-gradient(135deg, #8b5cf6 0%, #ec4899 50%, #f97316 100%); padding: 40px 20px; text-align: center; color: #fff; margin-bottom: 24px; border-radius: var(--radius-xl); }
.themes-hero h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
.themes-hero p { font-size: 16px; opacity: 0.9; }
.css-preview-box {
    background: #1e1e1e; color: #d4d4d4; padding: 16px; border-radius: var(--radius);
    font-family: "Fira Code", "JetBrains Mono", monospace; font-size: 13px; line-height: 1.6;
    overflow-x: auto; max-height: 400px; white-space: pre-wrap; word-break: break-word;
    border: 1px solid #333; position: relative;
}
.css-preview-box .copy-btn {
    position: absolute; top: 8px; right: 8px; background: #333; color: #fff;
    border: none; padding: 4px 10px; border-radius: 6px; cursor: pointer; font-size: 12px;
    transition: var(--transition);
}
.css-preview-box .copy-btn:hover { background: var(--primary); }
.live-preview-frame { border: 1px solid var(--border); border-radius: var(--radius); overflow: hidden; margin-bottom: 16px; position: relative; background: #fff; min-height: 200px; }
.live-preview-frame iframe { width: 100%; height: 300px; border: none; }
.theme-card-preview { height: 120px; overflow: hidden; position: relative; }
.theme-card-preview-bg { width: 100%; height: 100%; }
.theme-type-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; margin-bottom: 20px; }
.theme-type-card { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: var(--radius); border: 1px solid var(--border-light); background: var(--card-bg); cursor: pointer; transition: var(--transition); text-decoration: none; color: var(--text); font-size: 13px; font-weight: 500; }
.theme-type-card:hover, .theme-type-card.active { border-color: var(--primary); background: var(--primary-lighter); color: var(--primary); }
.css-editor { font-family: "Fira Code", "JetBrains Mono", monospace; tab-size: 2; min-height: 200px; }
@media (max-width: 768px) { .themes-hero { padding: 24px 16px; } .themes-hero h1 { font-size: 24px; } }
';

fn_renderHead($view === 'detail' && $detailItem ? $detailItem['title'] . ' — Оформление' : 'Оформление', $extraCss);
fn_renderHeader('themes');
?>

<div class="content-container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo fn_sanitize($message); ?></div>
    <?php endif; ?>

    <?php if ($view === 'detail' && $detailItem): ?>
        <!-- ===================== DETAIL ===================== -->
        <a href="/themes.php" class="btn btn-ghost btn-sm" style="margin-bottom:16px;"><i class="fas fa-arrow-left"></i> Назад</a>

        <div class="detail-header">
            <div class="detail-cover" style="background: linear-gradient(135deg, <?php echo $themeTypes[$detailItem['theme_type'] ?? 'other']['color'] ?? '#8b5cf6'; ?>, #1e293b);">
                <?php if (!empty($detailItem['preview'])): ?>
                    <img src="/<?php echo fn_sanitize($detailItem['preview']); ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-palette"></i>
                <?php endif; ?>
            </div>
            <div class="detail-info">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:8px;flex-wrap:wrap;">
                    <?php $tt = $themeTypes[$detailItem['theme_type'] ?? 'other'] ?? $themeTypes['other']; ?>
                    <span class="cat-badge" style="background:<?php echo $tt['color']; ?>20;color:<?php echo $tt['color']; ?>;display:inline-flex;align-items:center;gap:4px;padding:4px 10px;border-radius:var(--radius-full);font-size:11px;font-weight:600;"><i class="fas fa-<?php echo $tt['icon']; ?>"></i> <?php echo $tt['name']; ?></span>
                    <?php if (!empty($detailItem['is_author'])): ?><span class="badge badge-author"><i class="fas fa-check"></i> Автор</span><?php endif; ?>
                    <?php if (!empty($detailItem['is_ai'])): ?><span class="badge badge-ai"><i class="fas fa-robot"></i> AI</span><?php endif; ?>
                </div>
                <div class="detail-title"><?php echo fn_sanitize($detailItem['title']); ?></div>
                <div class="detail-author">
                    <?php $authorUser = fn_getUserByUsername($detailItem['author'] ?? ''); if ($authorUser): ?>
                        <img src="<?php echo fn_getAvatarUrl($authorUser); ?>" alt="">
                    <?php endif; ?>
                    <span>@<?php echo fn_sanitize($detailItem['author'] ?? ''); ?></span>
                </div>
                <?php if (!empty($detailItem['description'])): ?>
                    <div class="detail-description"><?php echo nl2br(fn_sanitize($detailItem['description'])); ?></div>
                <?php endif; ?>
                <div class="detail-stats">
                    <div class="detail-stat"><i class="fas fa-heart"></i> <?php echo fn_formatNumber($detailItem['likes'] ?? 0); ?></div>
                    <div class="detail-stat"><i class="fas fa-check-circle"></i> <?php echo fn_formatNumber(count($detailItem['applied_by'] ?? [])); ?> применили</div>
                    <div class="detail-stat"><i class="fas fa-comment"></i> <?php echo count($detailComments); ?></div>
                    <div class="detail-stat"><i class="fas fa-clock"></i> <?php echo fn_timeAgo($detailItem['created_at']); ?></div>
                </div>
                <div class="detail-actions" style="margin-top:16px;">
                    <?php if ($user): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="apply_theme"><input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>">
                            <button type="submit" class="btn btn-primary btn-lg"><i class="fas fa-magic"></i> Применить к профилю</button>
                        </form>
                    <?php endif; ?>
                    <button class="btn btn-outline" onclick="copyCssCode()"><i class="fas fa-copy"></i> Копировать CSS</button>
                    <button class="like-btn <?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'active' : ''; ?>"
                            onclick="likeItem('theme','<?php echo $detailItem['id']; ?>',this)">
                        <i class="<?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'fas' : 'far'; ?> fa-heart"></i>
                        <span class="like-count"><?php echo $detailItem['likes'] ?? 0; ?></span>
                    </button>
                    <?php if ($user): ?>
                        <button class="btn btn-outline btn-sm" onclick="toggleFavorite('theme','<?php echo $detailItem['id']; ?>')"><i class="far fa-bookmark"></i></button>
                    <?php endif; ?>
                </div>
                <?php if (!empty($detailItem['tags'])): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:12px;">
                        <?php foreach (explode(',', $detailItem['tags']) as $tag): ?>
                            <a href="/themes.php?q=<?php echo urlencode(trim($tag)); ?>" class="tag">#<?php echo fn_sanitize(trim($tag)); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CSS Code -->
        <?php if (!empty($detailItem['css_code'])): ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px;"><i class="fas fa-code" style="color:var(--accent);"></i> CSS код</h3>
                    <div class="css-preview-box" id="cssCode">
                        <button class="copy-btn" onclick="copyCssCode()"><i class="fas fa-copy"></i> Копировать</button>
                        <?php echo fn_sanitize($detailItem['css_code']); ?>
                    </div>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body">
                    <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;display:flex;align-items:center;gap:8px;"><i class="fas fa-eye" style="color:var(--primary);"></i> Предпросмотр</h3>
                    <div class="live-preview-frame">
                        <div id="livePreview" style="padding:20px;min-height:200px;">
                            <div style="background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.1);">
                                <div style="height:80px;background:linear-gradient(135deg,#10b981,#059669);"></div>
                                <div style="padding:16px;text-align:center;">
                                    <div style="width:64px;height:64px;border-radius:50%;background:#e5e7eb;margin:-40px auto 8px;border:3px solid #fff;display:flex;align-items:center;justify-content:center;"><i class="fas fa-user" style="font-size:24px;color:#9ca3af;"></i></div>
                                    <div style="font-weight:700;font-size:16px;">@player</div>
                                    <div style="color:#6b7280;font-size:13px;margin-top:4px;">Пример профиля</div>
                                    <div style="display:flex;justify-content:center;gap:16px;margin-top:12px;font-size:13px;color:#6b7280;">
                                        <span><b>42</b> поста</span><span><b>128</b> лайков</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($user && ($detailItem['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')): ?>
            <div class="card" style="margin-bottom:20px;">
                <div class="card-body" style="display:flex;gap:8px;">
                    <button class="btn btn-outline btn-sm" onclick="document.getElementById('editForm').style.display=document.getElementById('editForm').style.display==='none'?'block':'none'"><i class="fas fa-edit"></i> Редактировать</button>
                    <form method="post" onsubmit="return confirm('Удалить?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Удалить</button></form>
                </div>
                <div id="editForm" style="display:none;padding:20px;">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_item"><input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>">
                        <div class="form-group"><label class="form-label">Название</label><input type="text" name="title" value="<?php echo fn_sanitize($detailItem['title']); ?>" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Описание</label><textarea name="description" class="form-input" rows="3"><?php echo fn_sanitize($detailItem['description'] ?? ''); ?></textarea></div>
                        <div class="form-group"><label class="form-label">CSS код</label><textarea name="css_code" class="form-input css-editor" rows="10"><?php echo fn_sanitize($detailItem['css_code'] ?? ''); ?></textarea></div>
                        <div class="form-group">
                            <label class="form-label">Тип</label>
                            <select name="theme_type" class="form-input">
                                <?php foreach ($themeTypes as $key => $tt): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($detailItem['theme_type'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $tt['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Теги</label><input type="text" name="tags" value="<?php echo fn_sanitize($detailItem['tags'] ?? ''); ?>" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Новый скриншот</label><input type="file" name="preview_file" accept="image/*" class="form-input"></div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Comments -->
        <div class="comments-section">
            <div class="comments-title"><i class="fas fa-comments"></i> Комментарии (<?php echo count($detailComments); ?>)</div>
            <?php if ($user): ?>
                <form method="post" class="comment-form">
                    <input type="hidden" name="action" value="add_comment"><input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>">
                    <img src="<?php echo fn_getAvatarUrl($user); ?>" alt="" class="comment-avatar" style="width:36px;height:36px;">
                    <input type="text" name="comment_text" placeholder="Написать комментарий..." required class="form-input" style="flex:1;">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i></button>
                </form>
            <?php else: ?>
                <div class="alert alert-info"><a href="/?action=login">Войдите</a>, чтобы комментировать</div>
            <?php endif; ?>
            <?php if (empty($detailComments)): ?>
                <div style="text-align:center;padding:20px;color:var(--text-muted);">Нет комментариев</div>
            <?php else: ?>
                <?php foreach ($detailComments as $c): ?>
                    <div class="comment-item">
                        <img src="<?php echo fn_sanitize($c['avatar'] ?? ''); ?>" alt="" class="comment-avatar">
                        <div class="comment-body">
                            <a href="/<?php echo fn_sanitize($c['username'] ?? ''); ?>" class="comment-author">@<?php echo fn_sanitize($c['username'] ?? ''); ?></a>
                            <div class="comment-text"><?php echo nl2br(fn_sanitize($c['content'])); ?></div>
                            <div class="comment-time"><?php echo fn_timeAgo($c['created_at']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($view === 'upload'): ?>
        <!-- ===================== UPLOAD ===================== -->
        <?php if (!$user): ?>
            <div class="alert alert-info"><a href="/?action=login">Войдите</a>, чтобы загрузить оформление</div>
        <?php else: ?>
            <a href="/themes.php" class="btn btn-ghost btn-sm" style="margin-bottom:16px;"><i class="fas fa-arrow-left"></i> Назад</a>
            <div class="upload-section">
                <h2><i class="fas fa-palette"></i> Загрузить оформление</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_theme">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Название *</label><input type="text" name="title" class="form-input" placeholder="Крутая тема" required maxlength="200"></div>
                        <div class="form-group"><label class="form-label">Юз (уникальный) *</label><input type="text" name="use" class="form-input" placeholder="dark-neon" required maxlength="100" pattern="[a-zA-Z0-9_\-]+"><div class="form-hint">Латиница, цифры, дефис</div></div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Тип оформления</label>
                        <select name="theme_type" class="form-input">
                            <?php foreach ($themeTypes as $key => $tt): ?>
                                <option value="<?php echo $key; ?>"><?php echo $tt['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">CSS код *</label>
                        <textarea name="css_code" class="form-input css-editor" rows="12" placeholder="/* Ваш CSS код */&#10;.profile-cover {&#10;  background: linear-gradient(135deg, #ff6b6b, #feca57);&#10;}" required maxlength="50000"></textarea>
                        <div class="form-hint">Макс. 50 000 символов. Поддерживаются CSS переменные, градиенты, анимации.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Скриншот/Превью</label>
                        <div class="form-row">
                            <div>
                                <div class="file-upload-area" onclick="document.getElementById('previewFile').click()"><i class="fas fa-image"></i><p>Загрузить скриншот</p></div>
                                <input type="file" name="preview_file" id="previewFile" accept="image/*" style="display:none;">
                            </div>
                            <div><label class="form-label">Или ссылка</label><input type="url" name="preview_url" class="form-input" placeholder="https://..."></div>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Описание</label><textarea name="description" class="form-input" rows="3" placeholder="Описание оформления..." maxlength="2000"></textarea></div>
                    <div class="form-group"><label class="form-label">Теги</label><input type="text" name="tags" class="form-input" placeholder="темная, неон, градиент"></div>
                    <div class="form-group" style="background:var(--border-light);padding:16px;border-radius:var(--radius);">
                        <label class="checkbox-label"><input type="checkbox" name="is_author"> Я являюсь автором</label>
                        <label class="checkbox-label"><input type="checkbox" name="is_ai"> Создано с ИИ</label>
                        <label class="checkbox-label"><input type="checkbox" name="agree_rules" required> <span style="font-weight:600;">Согласен с правилами *</span></label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;"><i class="fas fa-palette"></i> Загрузить оформление</button>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ===================== LIST ===================== -->
        <div class="themes-hero">
            <h1><i class="fas fa-palette"></i> Оформление</h1>
            <p>CSS темы для профиля — измени свой вид в один клик</p>
        </div>

        <div class="section-header">
            <div class="search-bar" style="flex:1;margin-bottom:0;">
                <i class="fas fa-search"></i>
                <form method="get" action="/themes.php" style="display:contents;"><input type="text" name="q" value="<?php echo fn_sanitize($search); ?>" placeholder="Поиск оформлений..."></form>
            </div>
            <?php if ($user): ?>
                <a href="/themes.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</a>
            <?php endif; ?>
        </div>

        <div class="theme-type-grid">
            <a href="/themes.php" class="theme-type-card <?php echo !$typeFilter ? 'active' : ''; ?>"><i class="fas fa-th"></i> Все</a>
            <?php foreach ($themeTypes as $key => $tt): ?>
                <a href="/themes.php?type=<?php echo $key; ?>" class="theme-type-card <?php echo $typeFilter === $key ? 'active' : ''; ?>"><i class="fas fa-<?php echo $tt['icon']; ?>" style="color:<?php echo $tt['color']; ?>"></i> <?php echo $tt['name']; ?></a>
            <?php endforeach; ?>
        </div>

        <div class="search-filters">
            <a href="/themes.php?sort=likes<?php echo $typeFilter ? '&type=' . urlencode($typeFilter) : ''; ?>" class="filter-chip <?php echo $sort === 'likes' ? 'active' : ''; ?>"><i class="fas fa-fire"></i> Популярные</a>
            <a href="/themes.php?sort=date<?php echo $typeFilter ? '&type=' . urlencode($typeFilter) : ''; ?>" class="filter-chip <?php echo $sort === 'date' ? 'active' : ''; ?>"><i class="fas fa-clock"></i> Новые</a>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <i class="fas fa-palette"></i>
                <h3>Оформлений пока нет</h3>
                <p>Создайте первую CSS-тему!</p>
                <?php if ($user): ?><a href="/themes.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Создать оформление</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <?php $tt = $themeTypes[$item['theme_type'] ?? 'other'] ?? $themeTypes['other']; ?>
                    <div class="item-card" onclick="window.location='/themes.php?view=detail&id=<?php echo $item['id']; ?>'">
                        <div class="theme-card-preview">
                            <?php if (!empty($item['preview'])): ?>
                                <img src="/<?php echo fn_sanitize($item['preview']); ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                            <?php elseif (!empty($item['css_code'])): ?>
                                <div style="width:100%;height:100%;background:linear-gradient(135deg, <?php echo $tt['color']; ?>, #1e293b);display:flex;align-items:center;justify-content:center;">
                                    <i class="fas fa-<?php echo $tt['icon']; ?>" style="font-size:32px;color:rgba(255,255,255,0.5);"></i>
                                </div>
                            <?php else: ?>
                                <div style="width:100%;height:100%;background:var(--border-light);display:flex;align-items:center;justify-content:center;"><i class="fas fa-palette" style="font-size:32px;color:var(--text-muted);"></i></div>
                            <?php endif; ?>
                        </div>
                        <div class="item-card-badges">
                            <span class="badge" style="background:<?php echo $tt['color']; ?>20;color:<?php echo $tt['color']; ?>;"><i class="fas fa-<?php echo $tt['icon']; ?>"></i></span>
                        </div>
                        <div class="item-card-body">
                            <div class="item-card-title"><?php echo fn_sanitize($item['title']); ?></div>
                            <div class="item-card-author">@<?php echo fn_sanitize($item['author'] ?? ''); ?></div>
                            <div class="item-card-meta">
                                <span><i class="fas fa-heart"></i> <?php echo fn_formatNumber($item['likes'] ?? 0); ?></span>
                                <span><i class="fas fa-check-circle"></i> <?php echo fn_formatNumber(count($item['applied_by'] ?? [])); ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo count(fn_getItemComments('theme', $item['id'])); ?></span>
                            </div>
                            <?php if (!empty($item['tags'])): ?>
                                <div class="item-card-tags">
                                    <?php foreach (array_slice(explode(',', $item['tags']), 0, 3) as $tag): ?>
                                        <span class="tag">#<?php echo fn_sanitize(trim($tag)); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="/themes.php?page=<?php echo $i; ?>&sort=<?php echo urlencode($sort); ?><?php echo $typeFilter ? '&type=' . urlencode($typeFilter) : ''; ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="fab"><a href="/themes.php?view=upload" class="fab-btn"><i class="fas fa-plus"></i></a></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$extraJs = "
function copyCssCode() {
    const code = document.getElementById('cssCode');
    if (!code) return;
    const text = code.innerText || code.textContent;
    navigator.clipboard.writeText(text.replace('Копировать', '').trim()).then(() => showToast('CSS скопирован!')).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
        showToast('CSS скопирован!');
    });
}
// Apply live preview CSS
(function() {
    const preview = document.getElementById('livePreview');
    const cssCode = document.getElementById('cssCode');
    if (preview && cssCode) {
        const style = document.createElement('style');
        style.textContent = cssCode.innerText.replace('Копировать', '').trim();
        preview.appendChild(style);
    }
})();
async function toggleFavorite(type, itemId) {
    const data = await apiCall('/api.php', { action: 'toggle_favorite', item_type: type, item_id: itemId });
    if (data.success) showToast(data.added ? 'Добавлено в избранное' : 'Удалено из избранного');
}
";
fn_renderFooter($extraJs);
?>