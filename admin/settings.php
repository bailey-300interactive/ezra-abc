<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/admin_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'password') {
        $st = db()->prepare('SELECT password_hash FROM admin_users WHERE id = ?');
        $st->execute([$_SESSION['admin_id']]);
        $hash = (string)$st->fetchColumn();
        $new = (string)($_POST['new'] ?? '');
        if (!password_verify((string)($_POST['current'] ?? ''), $hash)) flash('Your current password is wrong.', 'err');
        elseif (strlen($new) < 8) flash('The new password needs at least 8 characters.', 'err');
        elseif ($new !== ($_POST['again'] ?? '')) flash("The new passwords don't match.", 'err');
        else {
            db()->prepare('UPDATE admin_users SET password_hash = ? WHERE id = ?')->execute([password_hash($new, PASSWORD_DEFAULT), $_SESSION['admin_id']]);
            flash('Password changed.');
        }
    } else {
        $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 40) ?: 'Ezra ABC';
        save_setting('title', $title);
        save_setting('default_case', ($_POST['default_case'] ?? '') === 'lower' ? 'lower' : 'upper');
        save_setting('default_mode', ($_POST['default_mode'] ?? '') === 'draw' ? 'draw' : 'trace');
        save_setting('difficulty', ($_POST['difficulty'] ?? '') === 'medium' ? 'medium' : 'easy');
        save_setting('speech_rate', (string)max(0.5, min(1.3, (float)($_POST['speech_rate'] ?? 0.85))));
        foreach (['allow_case', 'voice_on', 'sounds_on', 'show_fridge', 'auto_save'] as $k) {
            save_setting($k, !empty($_POST[$k]) ? '1' : '0');
        }
        flash('Settings saved. Reload the game on the iPad to pick them up.');
    }
    header('Location: settings.php');
    exit;
}

$S = settings();
$chk = fn($k) => $S[$k] === '1' ? 'checked' : '';
admin_header('Settings', 'settings');
?>
<h1>Settings</h1>
<form method="post" class="card form">
  <?= csrf_field() ?>
  <label>Game title <input name="title" value="<?= h($S['title']) ?>" maxlength="40"></label>

  <fieldset>
    <legend>Letters start as</legend>
    <label class="radio"><input type="radio" name="default_case" value="upper" <?= $S['default_case'] !== 'lower' ? 'checked' : '' ?>> ABC big letters</label>
    <label class="radio"><input type="radio" name="default_case" value="lower" <?= $S['default_case'] === 'lower' ? 'checked' : '' ?>> abc little letters</label>
    <label class="check"><input type="checkbox" name="allow_case" value="1" <?= $chk('allow_case') ?>> Show the ABC / abc switch in the game</label>
  </fieldset>

  <fieldset>
    <legend>Start in</legend>
    <label class="radio"><input type="radio" name="default_mode" value="trace" <?= $S['default_mode'] !== 'draw' ? 'checked' : '' ?>> ✏️ Trace (follow the dots)</label>
    <label class="radio"><input type="radio" name="default_mode" value="draw" <?= $S['default_mode'] === 'draw' ? 'checked' : '' ?>> 🎨 Free draw</label>
    <p class="hint">Ezra can switch any time with the ✏️ / 🎨 buttons. The game remembers his last choice on that iPad.</p>
  </fieldset>

  <fieldset>
    <legend>Tracing</legend>
    <label class="radio"><input type="radio" name="difficulty" value="easy" <?= $S['difficulty'] !== 'medium' ? 'checked' : '' ?>> Easy: big targets, about ¾ of the dots per line (best for age 2)</label>
    <label class="radio"><input type="radio" name="difficulty" value="medium" <?= $S['difficulty'] === 'medium' ? 'checked' : '' ?>> Medium: smaller targets, almost every dot</label>
  </fieldset>

  <fieldset>
    <legend>Sound</legend>
    <label class="check"><input type="checkbox" name="sounds_on" value="1" <?= $chk('sounds_on') ?>> Sound effects (pops, chimes, music notes when drawing)</label>
    <label class="check"><input type="checkbox" name="voice_on" value="1" <?= $chk('voice_on') ?>> Say the letter and word out loud</label>
    <label>Talking speed <input type="range" name="speech_rate" min="0.5" max="1.3" step="0.05" value="<?= h($S['speech_rate']) ?>" oninput="this.nextElementSibling.textContent=this.value"><output><?= h($S['speech_rate']) ?></output></label>
  </fieldset>

  <fieldset>
    <legend>Fridge</legend>
    <label class="check"><input type="checkbox" name="show_fridge" value="1" <?= $chk('show_fridge') ?>> Ezra can open the fridge 🧲 in the game</label>
    <label class="check"><input type="checkbox" name="auto_save" value="1" <?= $chk('auto_save') ?>> Put every finished tracing on the fridge automatically</label>
  </fieldset>

  <button class="btn primary">Save settings</button>
</form>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <h2>Change password</h2>
  <label>Current password <input type="password" name="current" autocomplete="current-password" required></label>
  <label>New password <input type="password" name="new" autocomplete="new-password" minlength="8" required></label>
  <label>New password again <input type="password" name="again" autocomplete="new-password" minlength="8" required></label>
  <button class="btn" name="action" value="password">Change password</button>
</form>
<?php admin_footer();
