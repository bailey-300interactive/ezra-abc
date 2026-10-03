<?php
declare(strict_types=1);
/**
 * GET  api/drawings.php            → the newest drawings for the fridge
 * POST api/drawings.php (JSON)     → save a drawing { image: "data:image/png;base64,...", letter, case, mode, device }
 */
require __DIR__ . '/../inc/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    if (settings()['show_fridge'] !== '1') json_out(['drawings' => []]);
    $rows = db()->query('SELECT id, letter, letter_case, file, UNIX_TIMESTAMP(created_at) AS t FROM drawings ORDER BY id DESC LIMIT 60')->fetchAll();
    json_out(['drawings' => array_map(fn($r) => [
        'id'     => (int)$r['id'],
        'letter' => $r['letter_case'] === 'lower' ? strtolower($r['letter']) : $r['letter'],
        'url'    => $r['file'],
        't'      => (int)$r['t'],
    ], $rows)]);
}

if ($method !== 'POST') json_out(['error' => 'Method not allowed'], 405);

$in = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($in)) json_out(['error' => 'Bad request'], 400);

$letter = strtoupper((string)($in['letter'] ?? ''));
$case   = ($in['case'] ?? '') === 'lower' ? 'lower' : 'upper';
$mode   = ($in['mode'] ?? '') === 'draw' ? 'draw' : 'trace';
$device = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($in['device'] ?? '')) ?: '';
$device = substr($device, 0, 64);
$image  = (string)($in['image'] ?? '');

if (!preg_match('/^[A-Z]$/', $letter)) json_out(['error' => 'Bad letter'], 400);
if (!preg_match('#^data:image/(png|jpeg);base64,#', $image, $m)) json_out(['error' => 'Bad image'], 400);

$bin = base64_decode(substr($image, strpos($image, ',') + 1), true);
if ($bin === false || strlen($bin) > 6 * 1024 * 1024) json_out(['error' => 'Image too big'], 413);

// Re-encode with GD so only a real image is ever written to disk.
$img = @imagecreatefromstring($bin);
if (!$img) json_out(['error' => 'Not an image'], 400);
$w = imagesx($img); $hgt = imagesy($img);
$max = 1400;
if ($w > $max || $hgt > $max) {
    $scale = $max / max($w, $hgt);
    $nw = (int)round($w * $scale); $nh = (int)round($hgt * $scale);
    $dst = imagecreatetruecolor($nw, $nh);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $hgt);
    imagedestroy($img);
    $img = $dst;
}

$dir = UPLOAD_DIR . '/drawings/' . date('Y-m');
if (!is_dir($dir) && !@mkdir($dir, 0775, true)) json_out(['error' => 'Uploads folder is not writable'], 500);
$name = date('Ymd-His') . '-' . $letter . ($case === 'lower' ? 'l' : '') . '-' . bin2hex(random_bytes(4)) . '.jpg';
if (!imagejpeg($img, "$dir/$name", 86)) json_out(['error' => 'Could not save'], 500);
imagedestroy($img);

$rel = 'uploads/drawings/' . date('Y-m') . '/' . $name;
db()->prepare('INSERT INTO drawings (letter, letter_case, mode, device_id, file) VALUES (?, ?, ?, ?, ?)')
    ->execute([$letter, $case, $mode, $device, $rel]);

json_out(['ok' => true, 'id' => (int)db()->lastInsertId(), 'url' => $rel]);
