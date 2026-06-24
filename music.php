<?php
/**
 * music.php — Полноценная страница музыки My Cryndel
 * Загрузка, прослушивание, лайки, комментарии, поиск, категории
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

    if ($action === 'upload_music') {
        $title = trim($_POST['title'] ?? '');
        $use = trim($_POST['use'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $tags = trim($_POST['tags'] ?? '');
        $musicUrl = trim($_POST['music_url'] ?? '');
        $iconUrl = trim($_POST['icon_url'] ?? '');
        $isAuthor = !empty($_POST['is_author']);
        $isAI = !empty($_POST['is_ai']);
        $isNsfw = !empty($_POST['is_nsfw']);
        $agreeRules = !empty($_POST['agree_rules']);

        if (empty($title) || empty($use)) {
            $message = 'Заполните обязательные поля: название и юз';
            $messageType = 'error';
        } elseif (!$agreeRules) {
            $message = 'Необходимо согласиться с правилами';
            $messageType = 'error';
        } else {
            // Handle file uploads
            $uploadedMusic = fn_handleUpload('music_file', ['mp3', 'ogg', 'wav', 'flac', 'aac', 'webm', 'm4a']);
            $uploadedIcon = fn_handleUpload('icon_file', ['jpg', 'jpeg', 'png', 'gif', 'webp'], MAX_AVATAR_SIZE);

            $musicFile = $uploadedMusic ?: $musicUrl;
            $iconFile = $uploadedIcon ?: $iconUrl;

            if (empty($musicFile)) {
                $message = 'Укажите ссылку или загрузите файл музыки';
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
                    'file' => $musicFile,
                    'icon' => $iconFile,
                    'author' => $user['username'],
                    'user_id' => $user['id'],
                    'is_author' => $isAuthor,
                    'is_ai' => $isAI,
                    'is_nsfw' => $isNsfw,
                    'likes' => 0,
                    'liked_by' => [],
                    'downloads' => 0,
                    'plays' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                file_put_contents(MUSIC_DIR . $id . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $message = 'Музыка успешно загружена!';
                $messageType = 'success';
                $view = 'detail';
                $itemId = $id;
            }
        }
    }

    if ($action === 'add_comment' && !empty($_POST['item_id']) && !empty(trim($_POST['comment_text'] ?? ''))) {
        fn_addItemComment('music', $_POST['item_id'], $user['id'], trim($_POST['comment_text']));
        $message = 'Комментарий добавлен';
        $messageType = 'success';
        $view = 'detail';
        $itemId = $_POST['item_id'];
    }

    if ($action === 'delete_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('music', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            $file = MUSIC_DIR . $_POST['item_id'] . '.json';
            if (file_exists($file)) unlink($file);
            $message = 'Музыка удалена';
            $messageType = 'success';
            $view = 'list';
        }
    }

    if ($action === 'edit_item' && !empty($_POST['item_id'])) {
        $item = fn_getItemById('music', $_POST['item_id']);
        if ($item && ($item['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')) {
            $item['title'] = trim($_POST['title'] ?? $item['title']);
            $item['description'] = trim($_POST['description'] ?? $item['description']);
            $item['tags'] = trim($_POST['tags'] ?? $item['tags']);
            $item['updated_at'] = date('Y-m-d H:i:s');
            $newIcon = fn_handleUpload('icon_file', ['jpg', 'jpeg', 'png', 'gif', 'webp'], MAX_AVATAR_SIZE);
            if ($newIcon) $item['icon'] = $newIcon;
            file_put_contents(MUSIC_DIR . $_POST['item_id'] . '.json', json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            $message = 'Музыка обновлена';
            $messageType = 'success';
            $view = 'detail';
            $itemId = $_POST['item_id'];
        }
    }
}

// ============================================
// ПОЛУЧЕНИЕ ДАННЫХ
// ============================================
$search = $_GET['q'] ?? '';
$sort = $_GET['sort'] ?? 'likes';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 24;

$allItems = fn_getItems('music', $search, 999, 0, $sort);
$totalItems = count($allItems);
$totalPages = max(1, ceil($totalItems / $perPage));
$items = array_slice($allItems, ($page - 1) * $perPage, $perPage);

// Detail view
$detailItem = null;
$detailComments = [];
if ($view === 'detail' && $itemId) {
    $detailItem = fn_getItemById('music', $itemId);
    if ($detailItem) {
        $detailComments = fn_getItemComments('music', $itemId);
        // Increment plays
        $detailItem['plays'] = ($detailItem['plays'] ?? 0) + 1;
        file_put_contents(MUSIC_DIR . $itemId . '.json', json_encode($detailItem, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
}

// ============================================
// РЕНДЕРИНГ
// ============================================
$extraCss = '
.music-hero { background: linear-gradient(135deg, #059669 0%, #10b981 50%, #34d399 100%); padding: 40px 20px; text-align: center; color: #fff; margin-bottom: 24px; border-radius: var(--radius-xl); }
.music-hero h1 { font-size: 32px; font-weight: 800; margin-bottom: 8px; }
.music-hero p { font-size: 16px; opacity: 0.9; }
.music-item-card .play-overlay {
    position: absolute; top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0,0,0,0.3); display: flex; align-items: center; justify-content: center;
    opacity: 0; transition: opacity 0.2s;
}
.music-item-card:hover .play-overlay { opacity: 1; }
.play-overlay i { font-size: 36px; color: #fff; text-shadow: 0 2px 8px rgba(0,0,0,0.3); }
.track-number { width: 32px; text-align: center; font-weight: 600; color: var(--text-muted); font-size: 14px; }
.track-row {
    display: flex; align-items: center; gap: 12px; padding: 10px 14px;
    border-radius: var(--radius); transition: var(--transition); cursor: pointer;
}
.track-row:hover { background: var(--border-light); }
.track-row .track-thumb { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; flex-shrink: 0; background: var(--border-light); display: flex; align-items: center; justify-content: center; overflow: hidden; }
.track-row .track-thumb img { width: 100%; height: 100%; object-fit: cover; }
.track-row .track-thumb i { font-size: 18px; color: var(--text-muted); }
.track-row .track-info { flex: 1; min-width: 0; }
.track-row .track-title { font-size: 14px; font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.track-row .track-artist { font-size: 12px; color: var(--text-light); }
.track-row .track-actions { display: flex; align-items: center; gap: 4px; }
.detail-player-area { background: var(--border-light); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px; }
.detail-player-art { width: 120px; height: 120px; border-radius: var(--radius); overflow: hidden; flex-shrink: 0; background: var(--primary-light); display: flex; align-items: center; justify-content: center; }
.detail-player-art img { width: 100%; height: 100%; object-fit: cover; }
.detail-player-art i { font-size: 40px; color: var(--primary); }
.detail-player-controls { flex: 1; }
@media (max-width: 768px) {
    .music-hero { padding: 24px 16px; }
    .music-hero h1 { font-size: 24px; }
    .detail-player-area { flex-direction: column; text-align: center; }
    .detail-player-art { width: 160px; height: 160px; }
}
';

fn_renderHead($view === 'detail' && $detailItem ? $detailItem['title'] . ' — Музыка' : 'Музыка', $extraCss);
fn_renderHeader('music');
?>

<div class="content-container">
    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> <?php echo fn_sanitize($message); ?></div>
    <?php endif; ?>

    <?php if ($view === 'detail' && $detailItem): ?>
        <!-- ===================== DETAIL VIEW ===================== -->
        <a href="/music.php" class="btn btn-ghost btn-sm" style="margin-bottom: 16px;"><i class="fas fa-arrow-left"></i> Назад к списку</a>

        <div class="detail-header">
            <div class="detail-player-area">
                <div class="detail-player-art">
                    <?php if (!empty($detailItem['icon'])): ?>
                        <img src="/<?php echo fn_sanitize($detailItem['icon']); ?>" alt="">
                    <?php else: ?>
                        <i class="fas fa-music"></i>
                    <?php endif; ?>
                </div>
                <div class="detail-player-controls">
                    <div class="detail-title"><?php echo fn_sanitize($detailItem['title']); ?></div>
                    <div class="detail-author">
                        <?php
                        $authorUser = fn_getUserByUsername($detailItem['author'] ?? '');
                        if ($authorUser): ?>
                            <img src="<?php echo fn_getAvatarUrl($authorUser); ?>" alt="">
                        <?php endif; ?>
                        <span>@<?php echo fn_sanitize($detailItem['author'] ?? ''); ?></span>
                        <?php if (!empty($detailItem['is_author'])): ?><span class="badge badge-author"><i class="fas fa-check"></i> Автор</span><?php endif; ?>
                        <?php if (!empty($detailItem['is_ai'])): ?><span class="badge badge-ai"><i class="fas fa-robot"></i> AI</span><?php endif; ?>
                        <?php if (!empty($detailItem['is_nsfw'])): ?><span class="badge badge-nsfw">18+</span><?php endif; ?>
                    </div>
                    <div style="margin: 12px 0;">
                        <?php if (!empty($detailItem['file'])): ?>
                            <button class="btn btn-primary btn-lg" onclick="playTrack('<?php echo fn_sanitize($detailItem['file']); ?>', '<?php echo fn_sanitize($detailItem['title']); ?>', '<?php echo fn_sanitize($detailItem['author'] ?? ''); ?>')">
                                <i class="fas fa-play"></i> Слушать
                            </button>
                        <?php endif; ?>
                        <button class="like-btn <?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'active' : ''; ?>"
                                onclick="likeItem('music','<?php echo $detailItem['id']; ?>',this)"
                                style="margin-left: 8px;">
                            <i class="<?php echo ($user && in_array($user['id'], $detailItem['liked_by'] ?? [])) ? 'fas' : 'far'; ?> fa-heart"></i>
                            <span class="like-count"><?php echo $detailItem['likes'] ?? 0; ?></span>
                        </button>
                        <?php if ($user): ?>
                            <button class="btn btn-outline btn-sm" onclick="toggleFavorite('music','<?php echo $detailItem['id']; ?>')" style="margin-left: 4px;">
                                <i class="<?php echo fn_isFavorite($user['id'], 'music', $detailItem['id']) ? 'fas' : 'far'; ?> fa-bookmark"></i>
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="detail-stats">
                        <div class="detail-stat"><i class="fas fa-heart"></i> <?php echo fn_formatNumber($detailItem['likes'] ?? 0); ?> лайков</div>
                        <div class="detail-stat"><i class="fas fa-play"></i> <?php echo fn_formatNumber($detailItem['plays'] ?? 0); ?> прослушиваний</div>
                        <div class="detail-stat"><i class="fas fa-comment"></i> <?php echo count($detailComments); ?> комментариев</div>
                        <div class="detail-stat"><i class="fas fa-clock"></i> <?php echo fn_timeAgo($detailItem['created_at']); ?></div>
                    </div>
                </div>
            </div>

            <?php if (!empty($detailItem['description'])): ?>
                <div class="detail-info">
                    <div class="detail-description"><?php echo nl2br(fn_sanitize($detailItem['description'])); ?></div>
                    <?php if (!empty($detailItem['tags'])): ?>
                        <div style="display:flex;flex-wrap:wrap;gap:4px;">
                            <?php foreach (explode(',', $detailItem['tags']) as $tag): ?>
                                <a href="/music.php?q=<?php echo urlencode(trim($tag)); ?>" class="tag tag-primary">#<?php echo fn_sanitize(trim($tag)); ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($user && ($detailItem['user_id'] === $user['id'] || ($user['role'] ?? '') === 'admin')): ?>
            <div class="card" style="margin-bottom: 20px;">
                <div class="card-body" style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <button class="btn btn-outline btn-sm" onclick="document.getElementById('editForm').style.display=document.getElementById('editForm').style.display==='none'?'block':'none'"><i class="fas fa-edit"></i> Редактировать</button>
                    <form method="post" onsubmit="return confirm('Удалить?')"><input type="hidden" name="action" value="delete_item"><input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>"><button class="btn btn-danger btn-sm"><i class="fas fa-trash"></i> Удалить</button></form>
                </div>
                <div id="editForm" style="display:none;padding:20px;">
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="edit_item">
                        <input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>">
                        <div class="form-group"><label class="form-label">Название</label><input type="text" name="title" value="<?php echo fn_sanitize($detailItem['title']); ?>" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Описание</label><textarea name="description" class="form-input" rows="3"><?php echo fn_sanitize($detailItem['description'] ?? ''); ?></textarea></div>
                        <div class="form-group"><label class="form-label">Теги (через запятую)</label><input type="text" name="tags" value="<?php echo fn_sanitize($detailItem['tags'] ?? ''); ?>" class="form-input"></div>
                        <div class="form-group"><label class="form-label">Новая обложка</label><input type="file" name="icon_file" accept="image/*" class="form-input"></div>
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
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="item_id" value="<?php echo $detailItem['id']; ?>">
                    <img src="<?php echo fn_getAvatarUrl($user); ?>" alt="" class="comment-avatar" style="width:36px;height:36px;">
                    <input type="text" name="comment_text" placeholder="Написать комментарий..." class="form-input" required style="flex:1;">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i></button>
                </form>
            <?php else: ?>
                <div class="alert alert-info"><a href="/?action=login">Войдите</a>, чтобы оставить комментарий</div>
            <?php endif; ?>

            <?php if (empty($detailComments)): ?>
                <div style="text-align:center;padding:20px;color:var(--text-muted);">Пока нет комментариев. Будьте первым!</div>
            <?php else: ?>
                <?php foreach ($detailComments as $comment): ?>
                    <div class="comment-item">
                        <img src="<?php echo fn_sanitize($comment['avatar'] ?? ''); ?>" alt="" class="comment-avatar">
                        <div class="comment-body">
                            <a href="/<?php echo fn_sanitize($comment['username'] ?? ''); ?>" class="comment-author">@<?php echo fn_sanitize($comment['username'] ?? ''); ?></a>
                            <div class="comment-text"><?php echo nl2br(fn_sanitize($comment['content'])); ?></div>
                            <div class="comment-time"><?php echo fn_timeAgo($comment['created_at']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    <?php elseif ($view === 'upload'): ?>
        <!-- ===================== UPLOAD FORM ===================== -->
        <?php if (!$user): ?>
            <div class="alert alert-info"><a href="/?action=login">Войдите</a>, чтобы загрузить музыку</div>
        <?php else: ?>
            <a href="/music.php" class="btn btn-ghost btn-sm" style="margin-bottom: 16px;"><i class="fas fa-arrow-left"></i> Назад</a>

            <div class="upload-section">
                <h2><i class="fas fa-cloud-upload-alt"></i> Загрузить музыку</h2>
                <form method="post" enctype="multipart/form-data" id="uploadForm">
                    <input type="hidden" name="action" value="upload_music">

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Название *</label>
                            <input type="text" name="title" class="form-input" placeholder="Название трека" required maxlength="200">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Юз (уникальный) *</label>
                            <input type="text" name="use" class="form-input" placeholder="my-cool-track" required maxlength="100" pattern="[a-zA-Z0-9_\-]+">
                            <div class="form-hint">Только латиница, цифры, дефис, подчеркивание</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Иконка / Обложка</label>
                        <div class="form-row">
                            <div>
                                <div class="file-upload-area" onclick="document.getElementById('iconFileInput').click()">
                                    <i class="fas fa-image"></i>
                                    <p>Загрузить обложку<br><small>JPG, PNG, GIF, WebP до 1 МБ</small></p>
                                </div>
                                <input type="file" name="icon_file" id="iconFileInput" accept="image/*" style="display:none;">
                            </div>
                            <div>
                                <label class="form-label">Или ссылка на обложку</label>
                                <input type="url" name="icon_url" class="form-input" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Музыка *</label>
                        <div class="form-row">
                            <div>
                                <div class="file-upload-area" onclick="document.getElementById('musicFileInput').click()">
                                    <i class="fas fa-headphones"></i>
                                    <p>Загрузить трек<br><small>MP3, OGG, WAV, FLAC, AAC до 10 МБ</small></p>
                                </div>
                                <input type="file" name="music_file" id="musicFileInput" accept="audio/*" style="display:none;">
                            </div>
                            <div>
                                <label class="form-label">Или ссылка на музыку</label>
                                <input type="url" name="music_url" class="form-input" placeholder="https://...">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Описание</label>
                        <textarea name="description" class="form-input" rows="3" placeholder="Расскажите о треке..." maxlength="2000"></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Теги (через запятую)</label>
                        <input type="text" name="tags" class="form-input" placeholder="minecraft, ambient, chill">
                    </div>

                    <div class="form-group" style="background: var(--border-light); padding: 16px; border-radius: var(--radius);">
                        <label class="checkbox-label"><input type="checkbox" name="is_author"> <span>Я являюсь автором этого трека</span></label>
                        <label class="checkbox-label"><input type="checkbox" name="is_ai"> <span>Создано с использованием ИИ</span></label>
                        <label class="checkbox-label"><input type="checkbox" name="is_nsfw"> <span>18+ контент</span></label>
                        <label class="checkbox-label"><input type="checkbox" name="agree_rules" required> <span style="font-weight:600;">Я ознакомлен с правилами и согласен с ними *</span></label>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width: 100%;"><i class="fas fa-cloud-upload-alt"></i> Загрузить музыку</button>
                </form>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- ===================== LIST VIEW ===================== -->
        <div class="music-hero">
            <h1><i class="fas fa-music"></i> Музыка</h1>
            <p>Слушай, загружай и делись треками с сообществом</p>
        </div>

        <div class="section-header">
            <div class="search-bar" style="flex:1; margin-bottom: 0;">
                <i class="fas fa-search"></i>
                <form method="get" action="/music.php" style="display:contents;">
                    <input type="text" name="q" value="<?php echo fn_sanitize($search); ?>" placeholder="Поиск музыки по названию, тегам, автору...">
                </form>
            </div>
            <?php if ($user): ?>
                <a href="/music.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить</a>
            <?php endif; ?>
        </div>

        <div class="search-filters">
            <a href="/music.php?sort=likes<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="filter-chip <?php echo $sort === 'likes' ? 'active' : ''; ?>"><i class="fas fa-fire"></i> Популярные</a>
            <a href="/music.php?sort=date<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="filter-chip <?php echo $sort === 'date' ? 'active' : ''; ?>"><i class="fas fa-clock"></i> Новые</a>
            <a href="/music.php?sort=title<?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="filter-chip <?php echo $sort === 'title' ? 'active' : ''; ?>"><i class="fas fa-sort-alpha-down"></i> По названию</a>
        </div>

        <?php if (empty($items)): ?>
            <div class="empty-state">
                <i class="fas fa-music"></i>
                <h3>Музыки пока нет</h3>
                <p><?php echo $search ? 'По запросу «' . fn_sanitize($search) . '» ничего не найдено' : 'Будьте первым, кто загрузит трек!'; ?></p>
                <?php if ($user): ?>
                    <a href="/music.php?view=upload" class="btn btn-primary"><i class="fas fa-plus"></i> Загрузить музыку</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Grid view -->
            <div class="items-grid">
                <?php foreach ($items as $item): ?>
                    <div class="item-card music-item-card" onclick="window.location='/music.php?view=detail&id=<?php echo $item['id']; ?>'">
                        <div class="item-card-image" style="position:relative;">
                            <?php if (!empty($item['icon'])): ?>
                                <img src="/<?php echo fn_sanitize($item['icon']); ?>" alt="">
                            <?php else: ?>
                                <i class="fas fa-music"></i>
                            <?php endif; ?>
                            <div class="play-overlay"><i class="fas fa-play-circle"></i></div>
                        </div>
                        <div class="item-card-badges">
                            <?php if (!empty($item['is_author'])): ?><span class="badge badge-author"><i class="fas fa-check"></i></span><?php endif; ?>
                            <?php if (!empty($item['is_ai'])): ?><span class="badge badge-ai">AI</span><?php endif; ?>
                            <?php if (!empty($item['is_nsfw'])): ?><span class="badge badge-nsfw">18+</span><?php endif; ?>
                        </div>
                        <div class="item-card-body">
                            <div class="item-card-title"><?php echo fn_sanitize($item['title']); ?></div>
                            <div class="item-card-author">@<?php echo fn_sanitize($item['author'] ?? ''); ?></div>
                            <div class="item-card-meta">
                                <span><i class="fas fa-heart"></i> <?php echo fn_formatNumber($item['likes'] ?? 0); ?></span>
                                <span><i class="fas fa-play"></i> <?php echo fn_formatNumber($item['plays'] ?? 0); ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo count(fn_getItemComments('music', $item['id'])); ?></span>
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

            <!-- List view (track rows) -->
            <div style="margin-top: 24px;">
                <div class="card">
                    <div class="card-body" style="padding: 8px;">
                        <?php $n = 0; foreach ($items as $item): $n++; ?>
                            <div class="track-row" onclick="event.stopPropagation();">
                                <div class="track-number"><?php echo $n; ?></div>
                                <div class="track-thumb">
                                    <?php if (!empty($item['icon'])): ?>
                                        <img src="/<?php echo fn_sanitize($item['icon']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="fas fa-music"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="track-info">
                                    <a href="/music.php?view=detail&id=<?php echo $item['id']; ?>" class="track-title" style="color:var(--text);text-decoration:none;"><?php echo fn_sanitize($item['title']); ?></a>
                                    <div class="track-artist">@<?php echo fn_sanitize($item['author'] ?? ''); ?></div>
                                </div>
                                <div class="track-actions">
                                    <?php if (!empty($item['file'])): ?>
                                        <button class="btn btn-ghost btn-icon" onclick="playTrack('/<?php echo fn_sanitize($item['file']); ?>', '<?php echo fn_sanitize($item['title']); ?>', '<?php echo fn_sanitize($item['author'] ?? ''); ?>')" title="Слушать"><i class="fas fa-play"></i></button>
                                    <?php endif; ?>
                                    <button class="like-btn btn-sm <?php echo ($user && in_array($user['id'], $item['liked_by'] ?? [])) ? 'active' : ''; ?>"
                                            onclick="likeItem('music','<?php echo $item['id']; ?>',this)">
                                        <i class="<?php echo ($user && in_array($user['id'], $item['liked_by'] ?? [])) ? 'fas' : 'far'; ?> fa-heart"></i>
                                        <span class="like-count"><?php echo $item['likes'] ?? 0; ?></span>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="/music.php?page=<?php echo $i; ?>&sort=<?php echo urlencode($sort); ?><?php echo $search ? '&q=' . urlencode($search) : ''; ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($user): ?>
            <div class="fab"><a href="/music.php?view=upload" class="fab-btn"><i class="fas fa-plus"></i></a></div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$extraJs = "
async function toggleFavorite(type, itemId) {
    const data = await apiCall('/api.php', { action: 'toggle_favorite', item_type: type, item_id: itemId });
    if (data.success) showToast(data.added ? 'Добавлено в избранное' : 'Удалено из избранного');
}
";
fn_renderFooter($extraJs);
?>