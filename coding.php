<?php
/**
 * coding.php — Полноценная страница кодинга/сборок My Cryndel
 * Загрузка модов, сборок, ресурспаков, скачивание, комментарии, лайки
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
 
    if ($action === 'upload_coding') {
        $title = trim($_POST['title'] ?? '');
        $use = trim($_POST['use'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $fileUrl = trim($_POST['file_url'] ?? '');
        $iconUrl = trim($_POST['icon_url'] ?? '');
        $category = trim($_POST['category'] ?? 'mod');
        $version = trim($_POST['version'] ?? '1.0');
        $mcVersion = trim($_POST['mc_version'] ?? '');
        $isAuthor = !empty($_POST['is_author']);
        $isAI = !empty($_POST['is_ai']);
        $isNsfw = !empty($_POST['is_nsfw']);
        $agreeRules = !empty($_POST['agree_rules']);
 
        if (empty($title) || empty($use)) {
            $message = 'Заполните обязательные поля';
            $messageType = 'error';
        } elseif (!$agreeRules) {
            $message = 'Необходимо согласиться с правилами';
            $messageType = 'error';
        } else {
            $uploadedFile = fn_handleUpload('coding_file', ['zip', 'jar', 'rar', '7z', 'tar', 'gz', 'mcpack', 'mcworld', 'mcaddon', 'json', 'py', 'js', 'lua', 'txt']);
            $uploadedIcon = fn_handleUpload('icon_file', ['jpg', 'jpeg', 'png', 'gif', 'webp'], MAX_AVATAR_SIZE);
            $codingFile = $uploadedFile ?: $fileUrl;
            $iconFile = $uploadedIcon ?: $iconUrl;
 
            if (empty($codingFile)) {
                $message = 'Загрузите файл или укажите ссылку';
                $messageType = 'error';
            } else {
                $id = fn_generateId();
                $slug = fn_generateSlug($title);
                $item = [
                    'id' => $id,
                    'slug' => $slug,
                    'title' => $title,
                    'use' => $use,
                    'description' => $description,
                    'tags' => $tags,
                    'file' => $codingFile,
                    'icon' => $iconFile,
                    'category' => $category,
                    'version' => $version,
                    'mc_version' => $mcVersion,
                    'author' => $user['username'],
                    'user_id' => $user['id'],
                    'is_author' => $isAuthor,
                    'is_ai' => $isAI,
                    'is_nsfw' => $isNsfw,
                    'likes' => 0,
                    'liked_by' => [],
                    'downloads' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                file_put_contents(CODING_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $message = 'Сборка загружена!';
                $messageType = 'success';
                $view = 'detail';
                $itemId = $id;
            }
        }
    }
 
    if ($action === 'add_comment' && !empty($_POST['item_id']) && !empty(trim($_POST['comment_text'] ?? ''))) {
        fn_addItemComment('coding', $_POST['item_id'], $user['id'], trim($_POST['comment_text']));
        $message = 'Комментарий добавлен';
        $messageType = 'success';
        $view = 'detail';
        $itemId = $_POST['item_id'];
    }
 
    if ($action === 'delete_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('coding', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            @unlink(CODING_DIR . $_POST['item_id'] . '.json');
            $message = 'Сборка удалена';
            $messageType = 'success';
            $view = 'list';
        }
    }
 
    if ($action === 'edit_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('coding', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            $item['title'] = trim($_POST['title'] ?? $item['title']);
            $item['description'] = trim($_POST['description'] ?? $item['description']);
            $item['tags'] = trim($_POST['tags'] ?? $item['tags']);
            $item['version'] = trim($_POST['version'] ?? $item['version']);
            $item['mc_version'] = trim($_POST['mc_version'] ?? $item['mc_version'] ?? '');
            $item['category'] = trim($_POST['category'] ?? $item['category']);
            $item['updated_at'] = date('Y-m-d H:i:s');
            $newIcon = fn_handleUpload('icon_file', ['jpg', 'jpeg', 'png', 'gif', 'webp'], MAX_AVATAR_SIZE);
            if ($newIcon) $item['icon'] = $newIcon;
            $newFile = fn_handleUpload('coding_file', ['zip', 'jar', 'rar', '7z', 'tar', 'gz', 'mcpack', 'mcworld', 'mcaddon', 'json', 'py', 'js', 'lua', 'txt']);
            if ($newFile) $item['file'] = $newFile;
            file_put_contents(CODING_DIR . $_POST['item_id'] . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
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
$catFilter = $_GET['cat'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;
 
$allItems = fn_getItems('coding', $search, 999, 0, $sort);
if ($catFilter) {
    $allItems = array_filter($allItems, function($i) use ($catFilter) { return ($i['category'] ?? '') === $catFilter; });
    $allItems = array_values($allItems);
}
$totalItems = count($allItems);
$totalPages = max(1, ceil($totalItems / $perPage));
$items = array_slice($allItems, ($page - 1) * $perPage, $perPage);
 
$categories = [
    'mod' => ['name' => 'Моды', 'icon' => 'puzzle-piece', 'color' => '#8b5cf6'],
    'build' => ['name' => 'Сборки', 'icon' => 'cubes', 'color' => '#06b6d4'],
    'resourcepack' => ['name' => 'Ресурспаки', 'icon' => 'paint-brush', 'color' => '#f59e0b'],
    'datapack' => ['name' => 'Датапаки', 'icon' => 'database', 'color' => '#10b981'],
    'shader' => ['name' => 'Шейдеры', 'icon' => 'sun', 'color' => '#ec4899'],
    'plugin' => ['name' => 'Плагины', 'icon' => 'plug', 'color' => '#3b82f6'],
    'map' => ['name' => 'Карты', 'icon' => 'map', 'color' => '#14b8a6'],
    'skin' => ['name' => 'Скины', 'icon' => 'user', 'color' => '#f97316'],
    'script' => ['name' => 'Скрипты', 'icon' => 'terminal', 'color' => '#6366f1'],
    'other' => ['name' => 'Другое', 'icon' => 'folder', 'color' => '#6b7280'],
];
 
$detailItem = null;
$detailComments = [];
if ($view === 'detail' && $itemId) {
    $detailItem = fn_getItemById('coding', $itemId);
    if ($detailItem) $detailComments = fn_getItemComments('coding', $itemId);
}
 
// ============================================
// РЕНДЕРИНГ
// ============================================
$extraCss = '
.coding-hero { background: linear-gradient(135deg, #06b6d4 0%, #3b82f6 50%, #8b5cf6 100%); padding: 40px 20px; text-align: center; color: #fff; margin-bottom: 24px; border-radius: var(--radius-xl); }
.coding-hero h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
.coding-hero p { font-size: 16px; opacity: 0.9; }
.cat-badge { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: var(--radius-full); font-size: 11px; font-weight: 600; }
.version-badge { background: var(--border-light); color: var(--text-light); padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; }
.cat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 8px; margin-bottom: 20px; }
.cat-card { display: flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: var(--radius); border: 1px solid var(--border-light); background: var(--card-bg); cursor: pointer; transition: var(--transition); text-decoration: none; color: var(--text); font-size: 13px; font-weight: 500; }
.cat-card:hover, .cat-card.active { border-color: var(--primary); background: var(--primary-lighter); color: var(--primary); }
.cat-card i { font-size: 16px; }
@media (max-width: 768px) { .coding-hero { padding: 24px 16px; } .coding-hero h1 { font-size: 24px; } .cat-grid { grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); } }
';
 
fn_renderHead($view === 'detail' && $detailItem ? $detailItem['title'] . ' — Кодинг' : 'Кодинг', $extraCss);
fn_renderHeader('coding');
?>
 
<div class="content-container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo fn_sanitize($message); ?></div>
    <?php endif; ?>
 
    <?php if ($view === 'detail' && $detailItem): ?>
        <!-- ===================== DETAIL ===================== -->
        <a href="/coding.php" class="btn btn-ghost btn-sm" style="margin-bottom:16px;"><i class="fas fa-arrow-left"></i> Назад</a>
 
        <div class="detail-header">
            <div class="detail-cover" style="background: linear-gradient(135deg, <?php echo $categories[$detailItem['category'] ?? 'other']['color'] ?? '#6b7280'; ?>, #1e293b);">
                <?php if (!empty($detailItem['icon'])): ?>
                    <img src="/<?php echo fn_sanitize($detailItem['icon']); ?>" alt="">
                <?php else: ?>
                    <i class="fas fa-<?php echo $categories[$detailItem['category'] ?? 'other']['icon'] ?? 'code'; ?>"></i>
                <?php endif; ?>
            </div>
            <div class="detail-info">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
                    <?php $cat = $categories[$detailItem['category'] ?? 'other'] ?? $categories['other']; ?>
                    <span class="cat-badge" style="background: <?php echo $cat['color']; ?>20; color: <?php echo $cat['color']; ?>"><i class="fas fa-<?php echo $cat['icon']; ?>"></i> <?php echo $cat['name']; ?></span>
                    <?php if (!empty($detailItem['version'])): ?><span class="version-badge">v<?php echo fn_sanitize($detailItem['version']); ?></span><?php endif; ?>
                    <?php if (!empty($detailItem['mc_version'])): ?><span class="version-badge">MC <?php echo fn_sanitize($detailItem['mc_version']); ?></span><?php endif; ?>
                    <?php if (!empty($detailItem['is_author'])): ?><span class="badge badge-author"><i class="fas fa-check"></i> Автор</span><?php endif; ?>
                    <?php if (!empty($detailItem['is_ai'])): ?><span class="badge badge-ai"><i class="fas fa-robot"></i> AI</span><?php endif; ?>
                    <?php if (!empty($detailItem['is_nsfw'])): ?><span class="badge badge-nsfw">18+</span><?php endif; ?>
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
                    <div class="detail-stat"><i class="fas fa-download"></i> <?php echo fn_formatNumber($detailItem['downloads'] ?? 0); ?></div>
                    <div class="detail-stat"><i class="fas fa-comment"></i> <?php echo count($detailComments); ?></div>
                    <div class="detail-stat"><i class="fas fa-clock"></i> <?php echo fn_timeAgo($detailItem['created_at']); ?></div>
                </div>
 
                <div class="download-warning"><i class="fas fa-shield-alt"></i> Будьте осторожны при скачивании файлов. Проверяйте файлы антивирусом. Администрация не несет ответственности за содержимое.</div>
 
                <div class="detail-actions" style="margin-top:16px;">
                    <?php if (!empty($detailItem['file'])): ?>
                        <a href="/<?php echo fn_sanitize($detailItem['file']); ?>" class="btn btn-primary btn-lg" download onclick="incrementDownload('<?php echo $detailItem['id']; ?>')">
                            <i class="fas fa-download"></i> Скачать
                        </a>
                    <?php endif; ?>
                    <button class="like-btn <?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'active' : ''; ?>"
                            onclick="likeItem('coding','<?php echo $detailItem['id']; ?>',this)">
                        <i class="<?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'fas' : 'far'; ?> fa-heart"></i>
                        <span class="like-count"><?php echo $detailItem['likes'] ?? 0; ?></span>
                    </button>
                    <?php if ($user): ?>
                        <button class="btn btn-outline btn-sm" onclick="toggleFavorite('coding','<?php echo $detailItem['id']; ?>')"><i class="far fa-bookmark"></i></button>
                    <?php endif; ?>
                </div>
 
                <?php if (!empty($detailItem['tags'])): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:12px;">
                        <?php foreach (explode(',', $detailItem['tags']) as $tag): ?>
                            <a href="/coding.php?q=<?php echo urlencode(trim($tag)); ?>" class="tag">#<?php echo fn_sanitize(trim($tag)); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
 
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
                        <div class="form-group"><label class="form-label">Описание</label><textarea name="description" class="form-input" rows="4"><?php echo fn_sanitize($detailItem['description'] ?? ''); ?></textarea></div>
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">Версия</label><input type="text" name="version" value="<?php echo fn_sanitize($detailItem['version'] ?? ''); ?>" class="form-input"></div>
                            <div class="form-group"><label class="form-label">Minecraft версия</label><input type="text" name="mc_version" value="<?php echo fn_sanitize($detailItem['mc_version'] ?? ''); ?>" class="form-input"></div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Категория</label>
                            <select name="category" class="form-input">
                                <?php foreach ($categories as $key => $cat): ?>
                                    <option value="<?php echo $key; ?>" <?php echo ($detailItem['category'] ?? '') === $key ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Теги</label><input type="text" name="tags" value="<?php echo fn_sanitize($detailItem['tags'] ?? ''); ?>" class="form-input"></div>
                        <div class="form-row">
                            <div class="form-group"><label class="form-label">Новая иконка</label><input type="file" name="icon_file" accept="image/*" class="form-input"></div>
                            <div class="form-group"><label class="form-label">Новый файл</label><input type="file" name="coding_file" class="form-input"></div>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
 
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
            <div class="alert alert-info"><a href="/?action=login">Войдите</a>, чтобы загрузить</div>
        <?php else: ?>
            <a href="/coding.php" class="btn btn-ghost btn-sm" style="margin-bottom:16px;"><i class="fas fa-arrow-left"></i> Назад</a>
            <div class="upload-section">
                <h2><i class="fas fa-cloud-upload-alt"></i> Загрузить сборку / мод</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_coding">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Название *</label><input type="text" name="title" class="form-input" placeholder="Крутой мод" required maxlength="200"></div>
                        <div class="form-group"><label class="form-label">Юз (уникальный) *</label><input type="text" name="use" class="form-input" placeholder="cool-mod" required maxlength="100" pattern="[a-zA-Z0-9_\-]+"><div class="form-hint">Латиница, цифры, дефис</div></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Категория</label>
                            <select name="category" class="form-input">
                                <?php foreach ($categories as $key => $cat): ?>
                                    <option value="<?php echo $key; ?>"><?php echo $cat['name']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group"><label class="form-label">Версия</label><input type="text" name="version" class="form-input" placeholder="1.0" value="1.0"></div>
                    </div>
                    <div class="form-group"><label class="form-label">Версия Minecraft</label><input type="text" name="mc_version" class="form-input" placeholder="1.20.4"></div>
                    <div class="form-group">
                        <label class="form-label">Иконка</label>
                        <div class="form-row">
                            <div>
                                <div class="file-upload-area" onclick="document.getElementById('iconFile').click()"><i class="fas fa-image"></i><p>Загрузить иконку</p></div>
                                <input type="file" name="icon_file" id="iconFile" accept="image/*" style="display:none;">
                            </div>
                            <div><label class="form-label">Или ссылка</label><input type="url" name="icon_url" class="form-input" placeholder="https://..."></div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Файл сборки *</label>
                        <div class="form-row">
                            <div>
                                <div class="file-upload-area" onclick="document.getElementById('codingFile').click()"><i class="fas fa-file-archive"></i><p>Загрузить файл<br><small>ZIP, JAR, RAR, MCPACK и др. до 10 МБ</small></p></div>
                                <input type="file" name="coding_file" id="codingFile" style="display:none;">
                            </div>
                            <div><label class="form-label">Или ссылка</label><input type="url" name="file_url" class="form-input" placeholder="https://..."></div>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Описание</label><textarea name="description" class="form-input" rows="4" placeholder="Описание, инструкции, совместимость..." maxlength="5000"></textarea></div>
                    <div class="form-group"><label class="form-label">Теги</label><input type="text" name="tags" class="form-input" placeholder="fabric, forge, minecraft, мод"></div>
                    <div class="form-group" style="background:var(--border-light);padding:16px;border-radius:var(--radius);">
                        <label class="checkbox-label"><input type="checkbox" name="is_author"> Я являюсь автором</label>
                        <label class="checkbox-label"><input type="checkbox" name="is_ai"> Создано с ИИ</label>
                        <label class="checkbox-label"><input type="checkbox" name="is_nsfw"> 18+</label>
                        <label class="checkbox-label"><input type="checkbox" name="agree_rules" required> <span style="font-weight:600;">Согласен с правилами *</span></label>
                    </div>
                    <div class="download-warning"><i class="fas fa-info-circle"></i> Загружая контент, вы подтверждаете что имеете на это право и контент не нарушает правила сообщества.</div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;margin-top:12px;"><i class="fas fa-cloud-upload-alt"></i> Загрузить</button>
                </form>
            </div>
        <?php endif; ?>
 
    <?php else: ?>
        <!-- ===================== LIST ===================== -->
        <div class="coding-hero">
            <h1><i class="fas fa-code"></i> Кодинг</h1>
            <p>Моды, сборки, ресурспаки, шейдеры и многое другое для Minecraft</p>
        </div>
 
        <div class="section-header">
            <div class="search-bar" style="flex:1;margin-bottom:0;">
                <i class="fas fa-search"></i>
                <form method="get" action="/coding.php" style="display:contents;"><input type="text" name="q" value="<?php echo fn_sanitize($search); ?>" placeholder="Поиск модов, сборок, ресурспаков..."></form>
            </div>
            <?php if ($user): ?>
                <a href="/coding.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</a>
            <?php endif; ?>
        </div>
 
        <!-- Categories -->
        <div class="cat-grid">
            <a href="/coding.php" class="cat-card <?php echo !$catFilter ? 'active' : ''; ?>"><i class="fas fa-th"></i> Все</a>
            <?php foreach ($categories as $key => $cat): ?>
                <a href="/coding.php?cat=<?php echo $key; ?>" class="cat-card <?php echo $catFilter === $key ? 'active' : ''; ?>"><i class="fas fa-<?php echo $cat['icon']; ?>" style="color:<?php echo $cat['color']; ?>"></i> <?php echo $cat['name']; ?></a>
            <?php endforeach; ?>
        </div>
 
        <div class="search-filters">
            <a href="/coding.php?sort=likes<?php echo $catFilter ? '&cat=' . urlencode($catFilter) : ''; ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="filter-chip <?php echo $sort === 'likes' ? 'active' : ''; ?>"><i class="fas fa-fire"></i> Популярные</a>
            <a href="/coding.php?sort=date<?php echo $catFilter ? '&cat=' . urlencode($catFilter) : ''; ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="filter-chip <?php echo $sort === 'date' ? 'active' : ''; ?>"><i class="fas fa-clock"></i> Новые</a>
        </div>
 
        <?php if (empty($items)): ?>
            <div class="empty-state">
                <i class="fas fa-code"></i>
                <h3>Пока ничего нет</h3>
                <p>Загрузите первую сборку или мод!</p>
                <?php if ($user): ?><a href="/coding.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <?php $cat = $categories[$item['category'] ?? 'other'] ?? $categories['other']; ?>
                    <div class="item-card" onclick="window.location='/coding.php?view=detail&id=<?php echo $item['id']; ?>'">
                        <div class="item-card-image" style="background: linear-gradient(135deg, <?php echo $cat['color']; ?>20, var(--border-light));">
                            <?php if (!empty($item['icon'])): ?>
                                <img src="/<?php echo fn_sanitize($item['icon']); ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-<?php echo $cat['icon']; ?>" style="color:<?php echo $cat['color']; ?>;"></i>
                            <?php endif; ?>
                        </div>
                        <div class="item-card-badges">
                            <span class="cat-badge" style="background:<?php echo $cat['color']; ?>20;color:<?php echo $cat['color']; ?>;font-size:10px;"><i class="fas fa-<?php echo $cat['icon']; ?>"></i></span>
                            <?php if (!empty($item['is_author'])): ?><span class="badge badge-author"><i class="fas fa-check"></i></span><?php endif; ?>
                        </div>
                        <div class="item-card-body">
                            <div class="item-card-title"><?php echo fn_sanitize($item['title']); ?></div>
                            <div class="item-card-author">@<?php echo fn_sanitize($item['author'] ?? ''); ?><?php if (!empty($item['version'])): ?> · v<?php echo fn_sanitize($item['version']); ?><?php endif; ?></div>
                            <div class="item-card-meta">
                                <span><i class="fas fa-heart"></i> <?php echo fn_formatNumber($item['likes'] ?? 0); ?></span>
                                <span><i class="fas fa-download"></i> <?php echo fn_formatNumber($item['downloads'] ?? 0); ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo count(fn_getItemComments('coding', $item['id'])); ?></span>
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
                        <a href="/coding.php?page=<?php echo $i; ?>&sort=<?php echo urlencode($sort); ?><?php echo $catFilter ? '&cat=' . urlencode($catFilter) : ''; ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
 
        <?php if ($user): ?>
            <div class="fab"><a href="/coding.php?view=upload" class="fab-btn"><i class="fas fa-plus"></i></a></div>
        <?php endif; ?>
    <?php endif; ?>
</div>
 
<?php
$extraJs = "
function incrementDownload(itemId) {
    apiCall('/api.php', { action: 'increment_download', item_type: 'coding', item_id: itemId });
}
async function toggleFavorite(type, itemId) {
    const data = await apiCall('/api.php', { action: 'toggle_favorite', item_type: type, item_id: itemId });
    if (data.success) showToast(data.added ? 'Добавлено в избранное' : 'Удалено из избранного');
}
";
fn_renderFooter($extraJs);
?>