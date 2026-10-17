<?php
declare(strict_types=1);

function admin_header(string $title, string $active = ''): void
{
    $base = base_path();
    $S = settings();
    $nav = [
        'letters'  => ['letters.php', '🔤 Letters'],
        'numbers'  => ['numbers.php', '🔢 Numbers'],
        'drawings' => ['drawings.php', '🧲 Fridge'],
        'settings' => ['settings.php', '⚙️ Settings'],
    ];
    $v = (string)@filemtime(APP_ROOT . '/assets/css/admin.css');
    ?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> · <?= h($S['title']) ?> admin</title>
<link rel="icon" href="<?= h($base) ?>/favicon.ico">
<link rel="icon" type="image/png" sizes="32x32" href="<?= h($base) ?>/assets/icons/favicon-32.png">
<link rel="apple-touch-icon" href="<?= h($base) ?>/assets/icons/apple-touch-icon.png">
<link rel="stylesheet" href="<?= h($base) ?>/assets/css/admin.css?v=<?= h($v) ?>">
</head>
<body>
<?php if ($active !== 'login'): ?>
<header class="top">
  <a class="brand" href="<?= h($base) ?>/admin/"><img src="<?= h($base) ?>/assets/icons/logo-ezra.png" alt=""> <?= h($S['title']) ?> <span>admin</span></a>
  <nav>
    <?php foreach ($nav as $key => [$href, $label]): ?>
      <a href="<?= h($href) ?>" class="<?= $key === $active ? 'on' : '' ?>"><?= h($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= h($base) ?>/" target="_blank">▶ Open game</a>
    <a href="logout.php" class="muted">Log out</a>
  </nav>
</header>
<?php endif; ?>
<main>
<?php if ($f = flash()): ?>
  <div class="flash <?= h($f[1]) ?>"><?= h($f[0]) ?></div>
<?php endif;
}

function admin_footer(string $extraJs = ''): void
{
    $v = (string)@filemtime(APP_ROOT . '/assets/js/admin.js');
    ?>
</main>
<script>window.CSRF = <?= json_encode(csrf_token()) ?>;</script>
<script src="<?= h(base_path()) ?>/assets/js/admin.js?v=<?= h($v) ?>"></script>
<?= $extraJs ?>
</body>
</html>
<?php
}

/** Save an uploaded picture as a square-ish JPEG/PNG (max 600 px). Returns the relative path. */
function save_letter_picture(array $file, string $letter): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException(upload_error_text($file['error'] ?? 4));
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true)) {
        throw new RuntimeException('That picture type isn\'t supported. Use JPG, PNG, WebP or GIF (iPhone HEIC photos: share them as JPG first).');
    }
    $src = @imagecreatefromstring((string)file_get_contents($file['tmp_name']));
    if (!$src) throw new RuntimeException('Could not read that picture.');
    if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
        $o = (int)(@exif_read_data($file['tmp_name'])['Orientation'] ?? 1);
        if ($o === 3) $src = imagerotate($src, 180, 0);
        if ($o === 6) $src = imagerotate($src, -90, 0);
        if ($o === 8) $src = imagerotate($src, 90, 0);
    }
    $w = imagesx($src); $hgt = imagesy($src);
    $side = min($w, $hgt);                          // centre square crop, shown in a circle
    $out = 600;
    $dst = imagecreatetruecolor($out, $out);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, (int)(($w - $side) / 2), (int)(($hgt - $side) / 2), $out, $out, $side, $side);
    $png = in_array($mime, ['image/png', 'image/webp', 'image/gif'], true);
    $name = $letter . '-pic-' . bin2hex(random_bytes(4)) . ($png ? '.png' : '.jpg');
    $ok = $png ? imagepng($dst, UPLOAD_DIR . '/letters/' . $name, 7) : imagejpeg($dst, UPLOAD_DIR . '/letters/' . $name, 88);
    if (!$ok) throw new RuntimeException('Could not save the picture. Is the uploads folder writable?');
    return 'uploads/letters/' . $name;
}

/** Save an uploaded or recorded voice clip ($kind: 'voice' or 'cheer'). Returns the relative path. */
function save_letter_voice(array $file, string $letter, string $kind = 'voice'): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException(upload_error_text($file['error'] ?? 4));
    if ($file['size'] > 10 * 1024 * 1024) throw new RuntimeException('Voice clips can be up to 10 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = [
        'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3', 'audio/mp4' => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/aac' => 'aac',
        'audio/wav' => 'wav', 'audio/x-wav' => 'wav', 'audio/wave' => 'wav', 'audio/ogg' => 'ogg', 'audio/webm' => 'webm',
        'video/mp4' => 'm4a', 'video/webm' => 'webm', 'application/ogg' => 'ogg',
    ][$mime] ?? null;
    if (!$ext) throw new RuntimeException('That sound type isn\'t supported (' . $mime . '). Use MP3, M4A, WAV or record one here.');
    $kind = $kind === 'cheer' ? 'cheer' : 'voice';
    $name = $letter . '-' . $kind . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . '/letters/' . $name)) {
        throw new RuntimeException('Could not save the clip. Is the uploads folder writable?');
    }
    return 'uploads/letters/' . $name;
}

function delete_upload(?string $rel): void
{
    if (!$rel || !str_starts_with($rel, 'uploads/')) return;
    $path = realpath(APP_ROOT . '/' . $rel);
    $root = realpath(UPLOAD_DIR);
    if ($path && $root && str_starts_with($path, $root . DIRECTORY_SEPARATOR) && is_file($path)) @unlink($path);
}

function upload_error_text(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That file is too big for the server\'s upload limit.',
        UPLOAD_ERR_PARTIAL => 'The upload was cut off. Try again.',
        UPLOAD_ERR_NO_FILE => 'No file was chosen.',
        default => 'The upload failed (error ' . $code . ').',
    };
}

/**
 * One voice-clip box: the clip with a player and Remove, or a note that the iPad's voice is used,
 * then 🎙️ Record (in the browser) and ⬆️ Upload. $kind is 'voice' (says the letter/number) or
 * 'cheer' (replaces “Hooray! You did it!”). Posts to $post with $fields plus action rec_<kind>
 * (recorded, file field "clip"), remove_<kind>, or a file field named <kind> with action save.
 */
function clip_box(string $kind, ?string $path, $ver, string $post, array $fields, string $title, string $say, string $back, string $fallback): string
{
    $label = $kind === 'cheer' ? '🎉 Hooray clip' : '🗣️ Voice';
    ob_start(); ?>
    <div class="voice <?= h($kind) ?>">
      <div class="voice-label"><?= $label ?></div>
      <?php if ($path): ?>
        <audio controls preload="none" src="../<?= h($path) ?>?v=<?= h((string)$ver) ?>"></audio>
        <button class="link danger" name="action" value="remove_<?= h($kind) ?>" formnovalidate>Remove</button>
      <?php else: ?>
        <span class="hint"><?= h($fallback) ?></span>
      <?php endif; ?>
      <div class="voice-actions">
        <button type="button" class="btn small rec" data-post="<?= h($post) ?>"
          data-fields="<?= h(json_encode($fields + ['action' => 'rec_' . $kind])) ?>"
          data-title="<?= h($title) ?>" data-say="<?= h($say) ?>" data-back="<?= h($back) ?>" data-name="<?= h($kind) ?>">🎙️ Record</button>
        <label class="file small">⬆️ Upload <input type="file" name="<?= h($kind) ?>" accept="audio/*"></label>
      </div>
    </div>
    <?php return (string)ob_get_clean();
}

/** The recorder pop-up used by every 🎙️ Record button. */
function recorder_dialog(): string
{
    return <<<'HTML'
<dialog id="recDialog" class="card">
  <h2>🎙️ Record <span id="recLetter"></span></h2>
  <p>Say something like “<b id="recSay"></b>”. Tap stop when you're done.</p>
  <div class="rec-meter"><span id="recLevel"></span></div>
  <p class="rec-time" id="recTime">0.0 s</p>
  <audio id="recPreview" controls hidden></audio>
  <div class="actions">
    <button type="button" class="btn" id="recCancel">Cancel</button>
    <button type="button" class="btn danger" id="recStop">⏹ Stop</button>
    <button type="button" class="btn" id="recAgain" hidden>Again</button>
    <button type="button" class="btn primary" id="recSave" hidden>Save</button>
  </div>
</dialog>
HTML;
}

/** Saves, records or removes a 'voice' / 'cheer' clip for one row. Returns the new path (or the old one). */
function handle_clip_action(string $action, ?string $current, string $prefix, string $kind): ?string
{
    if ($action === 'rec_' . $kind) {
        $new = save_letter_voice($_FILES['clip'] ?? [], $prefix, $kind);
        delete_upload($current);
        return $new;
    }
    if ($action === 'remove_' . $kind) {
        delete_upload($current);
        return null;
    }
    if ($action === 'save' && !empty($_FILES[$kind]['name'])) {
        $new = save_letter_voice($_FILES[$kind], $prefix, $kind);
        delete_upload($current);
        return $new;
    }
    return $current;
}
