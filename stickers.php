<?php
/**
 * stickers.php — Полноценная страница стикеров My Cryndel
 * Загрузка, пакеты, просмотр, модальные окна, лайки, комментарии
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

    if ($action === 'upload_sticker') {
        $title = trim($_POST['title'] ?? '');
        $use = trim($_POST['use'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $packName = trim($_POST['pack_name'] ?? '');
        $stickerUrl = trim($_POST['sticker_url'] ?? '');
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
            $uploadedFile = fn_handleUpload('sticker_file', ['png', 'gif', 'webp', 'webm', 'jpg', 'jpeg', 'svg', 'tgs']);
            $stickerFile = $uploadedFile ?: $stickerUrl;

            if (empty($stickerFile)) {
                $message = 'Загрузите файл стикера или укажите ссылку';
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
                    'pack_name' => $packName,
                    'file' => $stickerFile,
                    'author' => $user['username'],
                    'user_id' => $user['id'],
                    'is_author' => $isAuthor,
                    'is_ai' => $isAI,
                    'is_nsfw' => $isNsfw,
                    'likes' => 0,
                    'liked_by' => [],
                    'added_by' => [],
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                file_put_contents(STICKERS_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $message = 'Стикер загружен!';
                $messageType = 'success';
                $view = 'detail';
                $itemId = $id;
            }
        }
    }

    if ($action === 'add_comment' && !empty($_POST['item_id']) && !empty(trim($_POST['comment_text'] ?? ''))) {
        fn_addItemComment('sticker', $_POST['item_id'], $user['id'], trim($_POST['comment_text']));
        $message = 'Комментарий добавлен';
        $messageType = 'success';
        $view = 'detail';
        $itemId = $_POST['item_id'];
    }

    if ($action === 'delete_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('sticker', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            $file = STICKERS_DIR . $_POST['item_id'] . '.json';
            if (file_exists($file)) unlink($file);
            $message = 'Стикер удален';
            $messageType = 'success';
            $view = 'list';
        }
    }

    if ($action === 'edit_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('sticker', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            $item['title'] = trim($_POST['title'] ?? $item['title']);
            $item['description'] = trim($_POST['description'] ?? $item['description']);
            $item['tags'] = trim($_POST['tags'] ?? $item['tags']);
            $item['pack_name'] = trim($_POST['pack_name'] ?? $item['pack_name'] ?? '');
            $item['updated_at'] = date('Y-m-d H:i:s');
            $newFile = fn_handleUpload('sticker_file', ['png', 'gif', 'webp', 'webm', 'jpg', 'jpeg', 'svg']);
            if ($newFile) $item['file'] = $newFile;
            file_put_contents(STICKERS_DIR . $_POST['item_id'] . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = 'Стикер обновлен';
            $messageType = 'success';
            $view = 'detail';
            $itemId = $_POST['item_id'];
        }
    }

    if ($action === 'add_to_my_stickers' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('sticker', $_POST['item_id']);
        if ($item) {
            if (!isset($item['added_by'])) $item['added_by'] = [];
            if (!in_array($user['id'], $item['added_by'])) {
                $item['added_by'][] = $user['id'];
                file_put_contents(STICKERS_DIR . $_POST['item_id'] . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            }
            fn_toggleFavorite($user['id'], 'sticker', $_POST['item_id']);
            $message = 'Стикер добавлен в ваши стикеры!';
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
$packFilter = $_GET['pack'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 48;

$allItems = fn_getItems('sticker', $search, 999, 0, $sort);
if ($packFilter) {
    $allItems = array_filter($allItems, function($i) use ($packFilter) {
        return ($i['pack_name'] ?? '') === $packFilter;
    });
    $allItems = array_values($allItems);
}
$totalItems = count($allItems);
$totalPages = max(1, ceil($totalItems / $perPage));
$items = array_slice($allItems, ($page - 1) * $perPage, $perPage);

// Get unique packs
$packs = [];
foreach (fn_getItems('sticker', '', 999) as $s) {
    $pn = $s['pack_name'] ?? '';
    if ($pn && !isset($packs[$pn])) $packs[$pn] = ['name' => $pn, 'count' => 0, 'author' => $s['author'] ?? ''];
    if ($pn) $packs[$pn]['count']++;
}

$detailItem = null;
$detailComments = [];
if ($view === 'detail' && $itemId) {
    $detailItem = fn_getItemById('sticker', $itemId);
    if ($detailItem) $detailComments = fn_getItemComments('sticker', $itemId);
}

// ============================================
// РЕНДЕРИНГ
// ============================================
$extraCss = '
.stickers-hero { background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 50%, #fcd34d 100%); padding: 40px 20px; text-align: center; color: #fff; margin-bottom: 24px; border-radius: var(--radius-xl); }
.stickers-hero h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; text-shadow: 0 2px 4px rgba(0,0,0,0.1); }
.stickers-hero p { font-size: 16px; opacity: 0.9; }
.sticker-modal-img { max-width: 240px; max-height: 240px; margin: 0 auto 16px; display: block; }
.sticker-hover-like { position: absolute; top: 4px; right: 4px; opacity: 0; transition: opacity 0.2s; }
.sticker-card:hover .sticker-hover-like { opacity: 1; }
.pack-card { display: flex; align-items: center; gap: 12px; padding: 12px 16px; background: var(--card-bg); border: 1px solid var(--border-light); border-radius: var(--radius); cursor: pointer; transition: var(--transition); text-decoration: none; color: var(--text); }
.pack-card:hover { border-color: var(--primary); background: var(--primary-lighter); }
.pack-icon { width: 44px; height: 44px; border-radius: var(--radius); background: var(--warning-light); display: flex; align-items: center; justify-content: center; font-size: 20px; }
.pack-info { flex: 1; }
.pack-name { font-weight: 600; font-size: 14px; }
.pack-meta { font-size: 12px; color: var(--text-light); }
@media (max-width: 768px) { .stickers-hero { padding: 24px 16px; } .stickers-hero h1 { font-size: 24px; } }
';

fn_renderHead($view === 'detail' && $detailItem ? $detailItem['title'] . ' — Стикеры' : 'Стикеры', $extraCss);
fn_renderHeader('stickers');
?>

<div class="content-container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo fn_sanitize($message); ?></div>
    <?php endif; ?>

    <?php if ($view === 'detail' && $detailItem): ?>
        <!-- ===================== DETAIL VIEW ===================== -->
        <a href="/stickers.php" class="btn btn-ghost btn-sm" style="margin-bottom: 16px;"><i class="fas fa-arrow-left"></i> Назад</a>

        <div class="detail-header">
            <div style="text-align:center; padding: 32px;">
                <?php if (!empty($detailItem['file'])): ?>
                    <img src="/<?php echo fn_sanitize($detailItem['file']); ?>" alt="" class="sticker-modal-img">
                <?php else: ?>
                    <i class="fas fa-sticky-note" style="font-size:80px;color:var(--warning);"></i>
                <?php endif; ?>
            </div>
            <div class="detail-info">
                <div class="detail-title"><?php echo fn_sanitize($detailItem['title']); ?></div>
                <div class="detail-author">
                    <?php $authorUser = fn_getUserByUsername($detailItem['author'] ?? ''); if ($authorUser): ?>
                        <img src="<?php echo fn_getAvatarUrl($authorUser); ?>" alt="">
                    <?php endif; ?>
                    <span>@<?php echo fn_sanitize($detailItem['author'] ?? ''); ?></span>
                    <?php if (!empty($detailItem['is_author'])): ?><span class="badge badge-author"><i class="fas fa-check"></i> Автор</span><?php endif; ?>
                    <?php if (!empty($detailItem['is_ai'])): ?><span class="badge badge-ai"><i class="fas fa-robot"></i> AI</span><?php endif; ?>
                </div>
                <?php if (!empty($detailItem['pack_name'])): ?>
                    <div style="margin: 8px 0;">
                        <a href="/stickers.php?pack=<?php echo urlencode($detailItem['pack_name']); ?>" class="tag tag-primary" style="font-size: 13px;"><i class="fas fa-layer-group"></i> <?php echo fn_sanitize($detailItem['pack_name']); ?></a>
                    </div>
                <?php endif; ?>
                <?php if (!empty($detailItem['description'])): ?>
                    <div class="detail-description"><?php echo nl2br(fn_sanitize($detailItem['description'])); ?></div>
                <?php endif; ?>
                <div class="detail-stats">
                    <div class="detail-stat"><i class="fas fa-heart"></i> <?php echo fn_formatNumber($detailItem['likes'] ?? 0); ?> лайков</div>
                    <div class="detail-stat"><i class="fas fa-plus-circle"></i> <?php echo fn_formatNumber(count($detailItem['added_by'] ?? [])); ?> добавили</div>
                    <div class="detail-stat"><i class="fas fa-comment"></i> <?php echo count($detailComments); ?> комментариев</div>
                    <div class="detail-stat"><i class="fas fa-clock"></i> <?php echo fn_timeAgo($detailItem['created_at']); ?></div>
                </div>
                <div class="detail-actions" style="margin-top: 12px;">
                    <button class="like-btn <?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'active' : ''; ?>"
                            onclick="likeItem('sticker','<?php echo $detailItem['id']; ?>',this)">
                        <i class="<?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'fas' : 'far'; ?> fa-heart"></i>
                        <span class="like-count"><?php echo $detailItem['likes'] ?? 0; ?></span>
                    </button>
                    <?php if ($user): ?>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="action" value="add_to_my_stickers">
                            <input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>">
                            <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-plus"></i> Добавить в свои</button>
                        </form>
                    <?php endif; ?>
                </div>
                <?php if (!empty($detailItem['tags'])): ?>
                    <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:12px;">
                        <?php foreach (explode(',', $detailItem['tags']) as $tag): ?>
                            <a href="/stickers.php?q=<?php echo urlencode(trim($tag)); ?>" class="tag">#<?php echo fn_sanitize(trim($tag)); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($user && ($detailItem['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="display: flex; gap: 8px;">
                    <button class="btn btn-outline btn-sm" onclick="document.getElementById('editForm').style.display=document.getElementById('editForm').style.display==='none'?'block':'none'"><i class="fas fa-edit"></i> Редактировать</button>
                    <form method="post" onsubmit="return confirm('Удалить?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Удалить</button></form>
                </div>
                <div id="editForm" style="display:none;padding:20px;">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_item"><input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>">
                        <div class="form-group"><label class="form-label">Название</label><input type="text" name="title" value="<?php echo fn_sanitize($detailItem['title']); ?>" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Описание</label><textarea name="description" class="form-input" rows="3"><?php echo fn_sanitize($detailItem['description'] ?? ''); ?></textarea></div>
                        <div class="form-group"><label class="form-label">Пакет</label><input type="text" name="pack_name" value="<?php echo fn_sanitize($detailItem['pack_name'] ?? ''); ?>" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Теги</label><input type="text" name="tags" value="<?php echo fn_sanitize($detailItem['tags'] ?? ''); ?>" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Новый файл</label><input type="file" name="sticker_file" accept="image/*,.webm,.tgs" class="form-input"></div>
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
                    <input type="text" name="comment_text" placeholder="Написать комментарий..." required style="flex:1;" class="form-input">
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
            <div class="alert alert-info"><a href="/?action=login">Войдите</a>, чтобы загрузить стикер</div>
        <?php else: ?>
            <a href="/stickers.php" class="btn btn-ghost btn-sm" style="margin-bottom: 16px;"><i class="fas fa-arrow-left"></i> Назад</a>
            <div class="upload-section">
                <h2><i class="fas fa-cloud-upload-alt"></i> Загрузить стикер</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_sticker">
                    <div class="form-row">
                        <div class="form-group"><label class="form-label">Название *</label><input type="text" name="title" class="form-input" placeholder="Крутой стикер" required maxlength="200"></div>
                        <div class="form-group"><label class="form-label">Юз (уникальный) *</label><input type="text" name="use" class="form-input" placeholder="cool-sticker" required maxlength="100" pattern="[a-zA-Z0-9_\-]+"><div class="form-hint">Латиница, цифры, дефис, подчеркивание</div></div>
                    </div>
                    <div class="form-group"><label class="form-label">Стикер-пакет (необязательно)</label><input type="text" name="pack_name" class="form-input" placeholder="Мой пакет стикеров"><div class="form-hint">Введите имя пакета чтобы группировать стикеры</div></div>
                    <div class="form-group">
                        <label class="form-label">Файл стикера *</label>
                        <div class="form-row">
                            <div>
                                <div class="file-upload-area" onclick="document.getElementById('stickerFile').click()">
                                    <i class="fas fa-image"></i>
                                    <p>Загрузить стикер<br><small>PNG, GIF, WebP, WebM, SVG, TGS до 10 МБ</small></p>
                                </div>
                                <input type="file" name="sticker_file" id="stickerFile" accept="image/*,.webm,.tgs" style="display:none;">
                            </div>
                            <div><label class="form-label">Или ссылка</label><input type="url" name="sticker_url" class="form-input" placeholder="https://..."></div>
                        </div>
                    </div>
                    <div class="form-group"><label class="form-label">Описание</label><textarea name="description" class="form-input" rows="3" placeholder="Описание стикера..." maxlength="1000"></textarea></div>
                    <div class="form-group"><label class="form-label">Теги</label><input type="text" name="tags" class="form-input" placeholder="смешной, мем, майнкрафт"></div>
                    <div class="form-group" style="background: var(--border-light); padding: 16px; border-radius: var(--radius);">
                        <label class="checkbox-label"><input type="checkbox" name="is_author"> Я являюсь автором</label>
                        <label class="checkbox-label"><input type="checkbox" name="is_ai"> Создано с ИИ</label>
                        <label class="checkbox-label"><input type="checkbox" name="is_nsfw"> 18+</label>
                        <label class="checkbox-label"><input type="checkbox" name="agree_rules" required> <span style="font-weight:600;">Согласен с правилами *</span></label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%;"><i class="fas fa-cloud-upload-alt"></i> Загрузить стикер</button>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ===================== LIST ===================== -->
        <div class="stickers-hero">
            <h1><i class="fas fa-sticky-note"></i> Стикеры</h1>
            <p>Коллекция стикеров сообщества — используй в постах и комментариях</p>
        </div>

        <div class="section-header">
            <div class="search-bar" style="flex:1; margin-bottom:0;">
                <i class="fas fa-search"></i>
                <form method="get" action="/stickers.php" style="display:contents;">
                    <input type="text" name="q" value="<?php echo fn_sanitize($search); ?>" placeholder="Поиск стикеров...">
                </form>
            </div>
            <?php if ($user): ?>
                <a href="/stickers.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</a>
            <?php endif; ?>
        </div>

        <div class="search-filters">
            <a href="/stickers.php?sort=likes" class="filter-chip <?php echo $sort === 'likes' ? 'active' : ''; ?>"><i class="fas fa-fire"></i> Популярные</a>
            <a href="/stickers.php?sort=date" class="filter-chip <?php echo $sort === 'date' ? 'active' : ''; ?>"><i class="fas fa-clock"></i> Новые</a>
            <?php if ($packFilter): ?>
                <a href="/stickers.php" class="filter-chip" style="background:var(--danger-light);color:var(--danger);"><i class="fas fa-times"></i> Сбросить пакет: <?php echo fn_sanitize($packFilter); ?></a>
            <?php endif; ?>
        </div>

        <!-- Sticker Packs -->
        <?php if (!empty($packs) && !$packFilter && !$search): ?>
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 12px;"><i class="fas fa-layer-group" style="color:var(--warning);"></i> Стикер-пакеты</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 10px;">
                    <?php foreach ($packs as $pack): ?>
                        <a href="/stickers.php?pack=<?php echo urlencode($pack['name']); ?>" class="pack-card">
                            <div class="pack-icon"><i class="fas fa-layer-group" style="color:var(--warning);"></i></div>
                            <div class="pack-info">
                                <div class="pack-name"><?php echo fn_sanitize($pack['name']); ?></div>
                                <div class="pack-meta"><?php echo $pack['count']; ?> стикеров · @<?php echo fn_sanitize($pack['author']); ?></div>
                            </div>
                            <i class="fas fa-chevron-right" style="color:var(--text-muted);font-size:12px;"></i>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <i class="fas fa-sticky-note"></i>
                <h3>Стикеров пока нет</h3>
                <p><?php echo $search ? 'По запросу ничего не найдено' : 'Загрузите первый стикер!'; ?></p>
                <?php if ($user): ?><a href="/stickers.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить стикер</a><?php endif; ?>
            </div>
        <?php else: ?>
            <div class="stickers-grid">
                <?php foreach ($items as $item): ?>
                    <div class="sticker-card" onclick="window.location='/stickers.php?view=detail&id=<?php echo $item['id']; ?>'">
                        <?php if (!empty($item['file'])): ?>
                            <img src="/<?php echo fn_sanitize($item['file']); ?>" alt="<?php echo fn_sanitize($item['title']); ?>">
                        <?php else: ?>
                            <i class="fas fa-sticky-note" style="font-size:36px;color:var(--warning);"></i>
                        <?php endif; ?>
                        <div class="sticker-likes"><i class="fas fa-heart"></i> <?php echo $item['likes'] ?? 0; ?></div>
                        <div class="sticker-hover-like">
                            <button class="like-btn btn-sm" onclick="event.stopPropagation();likeItem('sticker','<?php echo $item['id']; ?>',this)" style="font-size:11px;padding:3px 8px;">
                                <i class="<?php echo ($user && in_array($user['id'], $item['liked_by'] ?? [])) ? 'fas' : 'far'; ?> fa-heart"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="/stickers.php?page=<?php echo $i; ?>&sort=<?php echo urlencode($sort); ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?><?php echo $packFilter ? '&pack=' . urlencode($packFilter) : ''; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="fab"><a href="/stickers.php?view=upload" class="fab-btn"><i class="fas fa-plus"></i></a></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php fn_renderFooter(); ?>