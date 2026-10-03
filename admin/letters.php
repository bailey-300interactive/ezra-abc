<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/admin_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $L = strtoupper((string)($_POST['letter'] ?? ''));
    $ajax = !empty($_POST['ajax']);
    $st = db()->prepare('SELECT * FROM letters WHERE letter = ?');
    $st->execute([$L]);
    $row = $st->fetch();
    try {
        if (!$row) throw new RuntimeException('Unknown letter.');
        $action = (string)($_POST['action'] ?? 'save');

        if ($action === 'save') {
            $word  = mb_substr(trim((string)($_POST['word'] ?? '')), 0, 40);
            $emoji = mb_substr(trim((string)($_POST['emoji'] ?? '')), 0, 8);
            $on    = !empty($_POST['enabled']) ? 1 : 0;
            $image = $row['image'];
            $voice = $row['voice'];
            if (!empty($_FILES['image']['name'])) {
                $new = save_letter_picture($_FILES['image'], $L);
                delete_upload($image); $image = $new;
            }
            if (!empty($_FILES['voice']['name'])) {
                $new = save_letter_voice($_FILES['voice'], $L);
                delete_upload($voice); $voice = $new;
            }
            db()->prepare('UPDATE letters SET word = ?, emoji = ?, enabled = ?, image = ?, voice = ? WHERE letter = ?')
                ->execute([$word, $emoji, $on, $image, $voice, $L]);
            flash("Saved $L.");
        } elseif ($action === 'voice') {                       // recorded in the browser
            $new = save_letter_voice($_FILES['voice'] ?? [], $L);
            delete_upload($row['voice']);
            db()->prepare('UPDATE letters SET voice = ? WHERE letter = ?')->execute([$new, $L]);
            flash("Saved your recording for $L.");
        } elseif ($action === 'remove_image') {
            delete_upload($row['image']);
            db()->prepare('UPDATE letters SET image = NULL WHERE letter = ?')->execute([$L]);
            flash("Removed the picture for $L. It shows the emoji again.");
        } elseif ($action === 'remove_voice') {
            delete_upload($row['voice']);
            db()->prepare('UPDATE letters SET voice = NULL WHERE letter = ?')->execute([$L]);
            flash("Removed the voice clip for $L. The iPad's voice will read it instead.");
        } elseif ($action === 'reset') {
            [$w, $e] = default_letters()[$L];
            db()->prepare('UPDATE letters SET word = ?, emoji = ? WHERE letter = ?')->execute([$w, $e, $L]);
            flash("Reset $L to $w $e.");
        }
        if ($ajax) json_out(['ok' => true]);
    } catch (Throwable $e) {
        if ($ajax) json_out(['error' => $e->getMessage()], 400);
        flash($e->getMessage(), 'err');
    }
    header('Location: letters.php#L-' . $L);
    exit;
}

$rows = letters();
admin_header('Letters', 'letters');
?>
<h1>Letters</h1>
<p class="lead">Each letter shows a word and a picture when Ezra opens it, and says “<b>A</b> is for <b>Apple</b>!” out loud.
Change the word, use your own photo (try <b>E is for Ezra</b>), or record your own voice. Turn a letter off to hide it from the game.</p>

<div class="letters">
<?php foreach ($rows as $r): $L = $r['letter']; $ver = $r['ver']; ?>
  <form method="post" enctype="multipart/form-data" class="card letter <?= $r['enabled'] ? '' : 'off' ?>" id="L-<?= h($L) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="letter" value="<?= h($L) ?>">
    <div class="lc-head">
      <div class="big"><?= h($L) ?><small><?= h(strtolower($L)) ?></small></div>
      <div class="pic">
        <?php if ($r['image']): ?><img src="../<?= h($r['image']) ?>?v=<?= h((string)$ver) ?>" alt=""><?php else: ?><span><?= h($r['emoji'] ?: '❔') ?></span><?php endif; ?>
      </div>
      <label class="switch" title="Show this letter in the game">
        <input type="checkbox" name="enabled" value="1" <?= $r['enabled'] ? 'checked' : '' ?>><span></span>
      </label>
    </div>
    <div class="row2">
      <label>Word <input name="word" value="<?= h($r['word']) ?>" maxlength="40" placeholder="Apple"></label>
      <label class="emoji">Emoji <input name="emoji" value="<?= h($r['emoji']) ?>" maxlength="8"></label>
    </div>
    <label class="file">📷 Picture <input type="file" name="image" accept="image/*"></label>
    <?php if ($r['image']): ?>
      <button class="link danger" name="action" value="remove_image" formnovalidate>Remove picture</button>
    <?php endif; ?>
    <div class="voice">
      <?php if ($r['voice']): ?>
        <audio controls preload="none" src="../<?= h($r['voice']) ?>?v=<?= h((string)$ver) ?>"></audio>
        <button class="link danger" name="action" value="remove_voice" formnovalidate>Remove voice</button>
      <?php else: ?>
        <span class="hint">🗣️ Using the iPad's voice</span>
      <?php endif; ?>
      <div class="voice-actions">
        <button type="button" class="btn small rec" data-letter="<?= h($L) ?>">🎙️ Record</button>
        <label class="file small">⬆️ Upload clip <input type="file" name="voice" accept="audio/*"></label>
      </div>
    </div>
    <div class="actions">
      <button class="btn primary" name="action" value="save">Save</button>
      <button class="link" name="action" value="reset" formnovalidate title="Back to the original word and emoji">Reset</button>
    </div>
  </form>
<?php endforeach; ?>
</div>

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
<script>window.LETTER_WORDS = <?= json_encode(array_column($rows, 'word', 'letter'), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
<?php admin_footer();
