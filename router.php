<?php
/**
 * router.php — Маршрутизатор для PHP built-in server
 * php -S localhost:8080 router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// PHP files that should be executed (not served as static)
$phpFiles = ['api.php', 'music.php', 'stickers.php', 'coding.php', 'themes.php', 'functions.php'];

// Check if it's a known PHP file
foreach ($phpFiles as $phpFile) {
    if ($uri === '/' . $phpFile || basename($uri) === $phpFile) {
        require __DIR__ . '/' . $phpFile;
        return true;
    }
}

// Serve static files (CSS, JS, images, uploads, etc.)
if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    // Don't serve PHP files as static
    if (strtolower($ext) === 'php') {
        require __DIR__ . $uri;
        return true;
    }
    return false; // Let PHP built-in server handle static files
}

// Route everything else to index.php
require __DIR__ . '/index.php';