<?php
declare(strict_types=1);
require __DIR__ . '/inc/bootstrap.php';

$title = settings()['title'];
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode([
    'name'             => $title,
    'short_name'       => $title,
    'start_url'        => './',
    'scope'            => './',
    'display'          => 'fullscreen',
    'display_override' => ['fullscreen', 'standalone'],
    'orientation'      => 'any',
    'background_color' => '#5b3df5',
    'theme_color'      => '#5b3df5',
    'icons' => [
        ['src' => 'assets/icons/icon-192.png',  'sizes' => '192x192',   'type' => 'image/png'],
        ['src' => 'assets/icons/icon-512.png',  'sizes' => '512x512',   'type' => 'image/png'],
        ['src' => 'assets/icons/icon-1024.png', 'sizes' => '1024x1024', 'type' => 'image/png'],
        ['src' => 'assets/icons/icon-maskable-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
