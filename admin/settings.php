<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/admin_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    $ajax = !empty($_POST['ajax']);
    if ($action === 'voice' || str_ends_with($action, '_cheer')) {
        try {
            if ($action === 'voice') {
                save_setting('voice_name', mb_substr(trim((string)($_POST['voice_name'] ?? '')), 0, 120));
                save_setting('voice_pitch', (string)max(0.7, min(1.5, (float)($_POST['voice_pitch'] ?? 1.05))));
                save_setting('speech_rate', (string)max(0.5, min(1.3, (float)($_POST['speech_rate'] ?? 0.85))));
                $action = 'save';                  // so an uploaded Hooray clip in the same form is saved too
            }
            $old = settings()['cheer_clip'] ?: null;
            $new = handle_clip_action($action, $old, 'all', 'cheer');
            if ($new !== $old) save_setting('cheer_clip', (string)$new);
            flash(match ($action) {
                'rec_cheer'    => 'Saved your Hooray clip. It plays for every letter and number without its own.',
                'remove_cheer' => 'Removed your Hooray clip. The voice says it again.',
                default        => 'Voice saved. Reload the game on the iPad to hear it.',
            });
            if ($ajax) json_out(['ok' => true]);
        } catch (Throwable $e) {
            if ($ajax) json_out(['error' => $e->getMessage()], 400);
            flash($e->getMessage(), 'err');
        }
        header('Location: settings.php#voice');
        exit;
    }
    if ($action === 'password') {
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
        $max = (int)($_POST['number_max'] ?? 100);
        save_setting('number_max', (string)(in_array($max, [10, 20, 50, 100], true) ? $max : 100));
        foreach (['allow_case', 'numbers_on', 'voice_on', 'sounds_on', 'show_fridge', 'auto_save'] as $k) {
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
    <legend>Numbers</legend>
    <label class="check"><input type="checkbox" name="numbers_on" value="1" <?= $chk('numbers_on') ?>> Show the ABC / 123 switch so Ezra can trace numbers too</label>
    <?php foreach ([10 => 'Up to 10', 20 => 'Up to 20', 50 => 'Up to 50', 100 => 'Up to 100'] as $n => $label): ?>
      <label class="radio"><input type="radio" name="number_max" value="<?= $n ?>" <?= (int)$S['number_max'] === $n ? 'checked' : '' ?>> <?= h($label) ?></label>
    <?php endforeach; ?>
    <p class="hint">Numbers start at 0 and come in groups (0–10, 11–20 …). Tapping the number card counts the things out loud.</p>
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
    <label class="check"><input type="checkbox" name="voice_on" value="1" <?= $chk('voice_on') ?>> Say the letters, numbers and words out loud</label>
    <p class="hint">Pick the voice, its pitch and speed under <a href="#voice">Voice</a> below.</p>
  </fieldset>

  <fieldset>
    <legend>Fridge</legend>
    <label class="check"><input type="checkbox" name="show_fridge" value="1" <?= $chk('show_fridge') ?>> Ezra can open the fridge 🧲 in the game</label>
    <label class="check"><input type="checkbox" name="auto_save" value="1" <?= $chk('auto_save') ?>> Put every finished tracing on the fridge automatically</label>
  </fieldset>

  <button class="btn primary">Save settings</button>
</form>

<form method="post" enctype="multipart/form-data" class="card form" id="voice">
  <?= csrf_field() ?>
  <h2>🗣️ Voice</h2>
  <p class="hint">Voices come from the device the game runs on, so <b>open this page on Ezra's iPad</b> to see and pick the iPad's voices.
  More natural ones can be downloaded on the iPad: Settings → Accessibility → Spoken Content → Voices → English
  (try <b>Ava (Premium)</b>, <b>Zoe (Premium)</b> or <b>Samantha (Enhanced)</b>), then come back and reload this page.</p>
  <label>Voice
    <select name="voice_name" id="voiceName" data-saved="<?= h($S['voice_name']) ?>">
      <option value="">✨ Automatic: the most natural voice on the device</option>
      <?php if ($S['voice_name'] !== ''): ?><option value="<?= h($S['voice_name']) ?>" selected><?= h($S['voice_name']) ?></option><?php endif; ?>
    </select>
  </label>
  <div class="voice-try">
    <button type="button" class="btn small" id="voicePreview">▶ Hear it</button>
    <button type="button" class="btn small" id="voiceNext">Try the next voice ⏭</button>
    <span class="hint" id="voiceNote"></span>
  </div>
  <label>Pitch <input type="range" name="voice_pitch" id="voicePitch" min="0.7" max="1.5" step="0.05" value="<?= h($S['voice_pitch']) ?>" oninput="this.nextElementSibling.textContent=this.value"><output><?= h($S['voice_pitch']) ?></output></label>
  <label>Talking speed <input type="range" name="speech_rate" id="voiceRate" min="0.5" max="1.3" step="0.05" value="<?= h($S['speech_rate']) ?>" oninput="this.nextElementSibling.textContent=this.value"><output><?= h($S['speech_rate']) ?></output></label>
  <p class="hint">If the saved voice isn't on a device (say, you picked it on a computer), that device uses Automatic instead.</p>

  <?= clip_box('cheer', $S['cheer_clip'] ?: null, @filemtime(APP_ROOT . '/' . $S['cheer_clip']) ?: 0, 'settings.php', [], 'Hooray! (every letter and number)', 'Hooray! You did it!', 'voice', "Using the voice above. Record your own and it plays every time he finishes one.") ?>
  <p class="hint">A letter or number with its own 🎉 Hooray clip (on the Letters and Numbers pages) uses that one instead.</p>
  <button class="btn primary" name="action" value="voice">Save voice</button>
</form>

<form method="post" class="card form">
  <?= csrf_field() ?>
  <h2>Change password</h2>
  <label>Current password <input type="password" name="current" autocomplete="current-password" required></label>
  <label>New password <input type="password" name="new" autocomplete="new-password" minlength="8" required></label>
  <label>New password again <input type="password" name="again" autocomplete="new-password" minlength="8" required></label>
  <button class="btn" name="action" value="password">Change password</button>
</form>
<?= recorder_dialog() ?>
<script>
/* Voice picker: lists this device's English voices, previews them with the pitch and speed above. */
(() => {
  const sel = document.getElementById('voiceName'), note = document.getElementById('voiceNote');
  if (!('speechSynthesis' in window)) { note.textContent = 'This browser has no voices.'; return; }
  const saved = sel.dataset.saved;
  function fill() {
    // the same novelty voices the game never uses (Zarvox, Bells, Whisper…) are left out here too
    const NOVELTY = /albert|bad news|bahh|bells|boing|bubbles|cellos|deranged|good news|hysterical|jester|junior|organ|superstar|trinoids|whisper|wobble|zarvox|ralph|fred|kathy|princess|eddy|flo|grandma|grandpa|reed|rocko|sandy|shelley/i;
    const vs = speechSynthesis.getVoices().filter(v => /^en(-|_|$)/i.test(v.lang) && !NOVELTY.test(v.name))
      .sort((a, b) => (/premium|enhanced|natural|neural/i.test(b.name) - /premium|enhanced|natural|neural/i.test(a.name)) || a.name.localeCompare(b.name));
    if (!vs.length) return;
    const cur = sel.value;
    sel.length = 1;
    vs.forEach(v => sel.add(new Option(`${v.name} (${v.lang})`, v.name)));
    if (saved && !vs.some(v => v.name === saved)) sel.add(new Option(`${saved} (not on this device)`, saved));
    sel.value = cur || saved || '';
    note.textContent = `${vs.length} English voices on this device`;
  }
  fill(); speechSynthesis.addEventListener?.('voiceschanged', fill);
  function speak() {
    speechSynthesis.cancel();
    const u = new SpeechSynthesisUtterance('Hooray! You did it! Ay is for Apple! Three! Three cars!');
    const v = speechSynthesis.getVoices().find(x => x.name === sel.value);
    if (v) u.voice = v;
    u.pitch = +document.getElementById('voicePitch').value;
    u.rate = +document.getElementById('voiceRate').value;
    speechSynthesis.speak(u);
  }
  document.getElementById('voicePreview').addEventListener('click', speak);
  document.getElementById('voiceNext').addEventListener('click', () => { sel.selectedIndex = (sel.selectedIndex + 1) % sel.length; speak(); });
  sel.addEventListener('change', speak);
})();
</script>
<?php admin_footer();
