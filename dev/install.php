<?php
declare(strict_types=1);
/**
 * Ezra ABC installer — run from the command line only:
 *   php dev/install.php                  create tables, seed letters, create the admin login
 *   php dev/install.php --reset-password change the admin password
 * Safe to run again.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../inc/bootstrap.php';

function say(string $s): void { fwrite(STDOUT, $s . PHP_EOL); }
function ask(string $q, bool $hidden = false): string
{
    fwrite(STDOUT, $q);
    if ($hidden && DIRECTORY_SEPARATOR === '/' && posix_isatty(STDIN)) {
        shell_exec('stty -echo');
        $a = fgets(STDIN);
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    } else {
        $a = fgets(STDIN);
    }
    return trim((string)$a);
}
function ask_password(): string
{
    while (true) {
        $p1 = ask('Password (8+ characters): ', true);
        if (strlen($p1) < 8) { say('  Too short, try again.'); continue; }
        $p2 = ask('Type it again: ', true);
        if ($p1 !== $p2) { say("  They don't match, try again."); continue; }
        return $p1;
    }
}

try {
    $pdo = db();
} catch (Throwable $e) {
    say('Could not connect to the database: ' . $e->getMessage());
    say('Check the details in config.php.');
    exit(1);
}

$args = array_slice($argv, 1);

if (in_array('--reset-password', $args, true)) {
    $users = $pdo->query('SELECT username FROM admin_users ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);
    if (!$users) { say('No admin user yet — run without --reset-password first.'); exit(1); }
    say('Admin users: ' . implode(', ', $users));
    $u = ask('Which username? [' . $users[0] . ']: ') ?: $users[0];
    if (!in_array($u, $users, true)) { say('No such user.'); exit(1); }
    $pdo->prepare('UPDATE admin_users SET password_hash = ? WHERE username = ?')
        ->execute([password_hash(ask_password(), PASSWORD_DEFAULT), $u]);
    say('Password changed for ' . $u . '.');
    exit(0);
}

// 1. Tables
$sql = file_get_contents(__DIR__ . '/schema.sql');
foreach (array_filter(array_map('trim', explode(';', preg_replace('/^--.*$/m', '', $sql)))) as $stmt) {
    $pdo->exec($stmt);
}
say('✓ Tables are ready.');

// 1b. Upgrades for older installs (never touches the data)
$col = $pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'drawings' AND COLUMN_NAME = 'letter_case'")->fetchColumn();
if ($col !== false && !str_contains((string)$col, 'number')) {
    $pdo->exec("ALTER TABLE drawings
        MODIFY letter VARCHAR(3) NOT NULL,
        MODIFY letter_case ENUM('upper','lower','number') NOT NULL DEFAULT 'upper'");
    say('✓ Fridge can now hold number drawings too.');
}
$has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'letters' AND COLUMN_NAME = 'cheer'")->fetchColumn();
if (!(int)$has) {
    $pdo->exec('ALTER TABLE letters ADD COLUMN cheer VARCHAR(255) NULL AFTER voice');
    say('✓ Letters can now have their own "Hooray!" recording.');
}

// 2. Letters (only adds missing ones, never overwrites your changes)
$ins = $pdo->prepare('INSERT IGNORE INTO letters (letter, word, emoji) VALUES (?, ?, ?)');
$added = 0;
foreach (default_letters() as $L => [$word, $emoji]) {
    $ins->execute([$L, $word, $emoji]);
    $added += $ins->rowCount();
}
say($added ? "✓ Added $added letters with their words." : '✓ Letters already there (left as they are).');

// 3. Upload folders
foreach (['', '/drawings', '/letters'] as $sub) {
    $dir = UPLOAD_DIR . $sub;
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    say((is_dir($dir) && is_writable($dir) ? '✓ ' : '✗ NOT writable: ') . 'uploads' . $sub);
}
if (!is_writable(UPLOAD_DIR . '/drawings')) {
    say('  Fix with: sudo chown -R www-data:www-data ' . UPLOAD_DIR . ' && sudo chmod -R 775 ' . UPLOAD_DIR);
}

// 4. Admin login
$count = (int)$pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
if ($count === 0) {
    say('');
    say('Create your admin login.');
    $u = '';
    while (!preg_match('/^[A-Za-z0-9_.@-]{3,64}$/', $u)) {
        $u = ask('Username: ');
    }
    $pdo->prepare('INSERT INTO admin_users (username, password_hash) VALUES (?, ?)')
        ->execute([$u, password_hash(ask_password(), PASSWORD_DEFAULT)]);
    say('✓ Admin user "' . $u . '" created.');
} else {
    say('✓ Admin login already exists (use --reset-password to change it).');
}

say('');
say('All done. Log in at /admin/');
