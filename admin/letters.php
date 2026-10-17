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

        $voice = handle_clip_action($action, $row['voice'], $L, 'voice');
        $cheer = handle_clip_action($action, $row['cheer'] ?? null, $L, 'cheer');

        if ($action === 'save') {
            $word  = mb_substr(trim((string)($_POST['word'] ?? '')), 0, 40);
            $emoji = mb_substr(trim((string)($_POST['emoji'] ?? '')), 0, 8);
            $on    = !empty($_POST['enabled']) ? 1 : 0;
            $image = $row['image'];
            if (!empty($_FILES['image']['name'])) {
                $new = save_letter_picture($_FILES['image'], $L);
                delete_upload($image); $image = $new;
            }
            db()->prepare('UPDATE letters SET word = ?, emoji = ?, enabled = ?, image = ?, voice = ?, cheer = ? WHERE letter = ?')
                ->execute([$word, $emoji, $on, $image, $voice, $cheer, $L]);
            flash("Saved $L.");
        } elseif (in_array($action, ['rec_voice', 'remove_voice', 'rec_cheer', 'remove_cheer'], true)) {
            db()->prepare('UPDATE letters SET voice = ?, cheer = ? WHERE letter = ?')->execute([$voice, $cheer, $L]);
            flash(match ($action) {
                'rec_voice'    => "Saved your recording for $L.",
                'rec_cheer'    => "Saved your Hooray clip for $L.",
                'remove_voice' => "Removed the voice clip for $L. The iPad's voice will read it instead.",
                'remove_cheer' => "Removed the Hooray clip for $L.",
            });
        } elseif ($action === 'remove_image') {
            delete_upload($row['image']);
            db()->prepare('UPDATE letters SET image = NULL WHERE letter = ?')->execute([$L]);
            flash("Removed the picture for $L. It shows the emoji again.");
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
$S = settings();
$NAMES = ['A' => 'Ay', 'B' => 'Bee', 'C' => 'See', 'D' => 'Dee', 'E' => 'Ee', 'F' => 'Eff', 'G' => 'Gee', 'H' => 'Aitch', 'I' => 'Eye',
    'J' => 'Jay', 'K' => 'Kay', 'L' => 'El', 'M' => 'Em', 'N' => 'En', 'O' => 'Oh', 'P' => 'Pee', 'Q' => 'Cue', 'R' => 'Ar', 'S' => 'Ess',
    'T' => 'Tee', 'U' => 'You', 'V' => 'Vee', 'W' => 'Double-you', 'X' => 'Ex', 'Y' => 'Why', 'Z' => 'Zee'];
admin_header('Letters', 'letters');
?>
<h1>Letters</h1>
<p class="lead">Each letter shows a word and a picture when Ezra opens it, and says “<b>A</b> is for <b>Apple</b>!” out loud.
Change the word, use your own photo (try <b>E is for Ezra</b>), or record your own voice. The 🎉 Hooray clip replaces
“Hooray! You did it!” when he finishes tracing it. Turn a letter off to hide it from the game.</p>

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
    <?php $say = $NAMES[$L] . "! $L is for " . ($r['word'] ?: '…') . '!'; ?>
    <?= clip_box('voice', $r['voice'], $ver, 'letters.php', ['letter' => $L], $L, $say, 'L-' . $L, "Using the iPad's voice") ?>
    <?= clip_box('cheer', $r['cheer'] ?? null, $ver, 'letters.php', ['letter' => $L], "$L: Hooray", 'Hooray! You did it!', 'L-' . $L, $S['cheer_clip'] ? 'Using your Hooray clip from Settings' : "Using the iPad's voice") ?>
    <div class="actions">
      <button class="btn primary" name="action" value="save">Save</button>
      <button class="link" name="action" value="reset" formnovalidate title="Back to the original word and emoji">Reset</button>
    </div>
  </form>
<?php endforeach; ?>
</div>

<?= recorder_dialog() ?>
<?php admin_footer();
