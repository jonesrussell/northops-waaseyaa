<?php

declare(strict_types=1);

/**
 * Router script for PHP built-in server.
 * Handles admin SPA routing and falls back to index.php for PHP routes.
 */

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Admin SPA routes: serve static files or fall back to 200.html
if (str_starts_with($uri, '/admin')) {
    // API routes go to PHP
    if (str_starts_with($uri, '/admin/_surface') || $uri === '/admin/session' || $uri === '/admin/logout') {
        require __DIR__ . '/index.php';
        return true;
    }

    // Try to serve the exact static file
    $file = __DIR__ . $uri;
    if (is_file($file)) {
        return false; // Let built-in server handle it
    }

    // SPA fallback
    $fallback = __DIR__ . '/admin/200.html';
    if (is_file($fallback)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($fallback);
        return true;
    }
}

// Static files (CSS, JS, images)
if (is_file(__DIR__ . $uri)) {
    return false;
}

// Everything else goes to the PHP app
require __DIR__ . '/index.php';
return true;
