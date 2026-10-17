<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/admin_layout.php';
require_login();

$S = settings();
$max = in_array((int)$S['number_max'], [10, 20, 50, 100], true) ? (int)$S['number_max'] : 100;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $n = filter_var($_POST['num'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
    $ajax = !empty($_POST['ajax']);
    try {
        if ($n === false) throw new RuntimeException('Unknown number.');
        $action = (string)($_POST['action'] ?? 'save');
        $row = number_clips()[$n] ?? ['voice' => null, 'cheer' => null];
        $voice = handle_clip_action($action, $row['voice'], 'n' . $n, 'voice');
        $cheer = handle_clip_action($action, $row['cheer'], 'n' . $n, 'cheer');
        db()->prepare('INSERT INTO numbers (num, voice, cheer) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE voice = VALUES(voice), cheer = VALUES(cheer)')
            ->execute([$n, $voice, $cheer]);
        flash(match ($action) {
            'rec_voice'    => "Saved your recording for $n.",
            'rec_cheer'    => "Saved your Hooray clip for $n.",
            'remove_voice' => "Removed the voice clip for $n. The iPad's voice will say it instead.",
            'remove_cheer' => "Removed the Hooray clip for $n.",
            default        => "Saved $n.",
        });
        if ($ajax) json_out(['ok' => true]);
    } catch (Throwable $e) {
        if ($ajax) json_out(['error' => $e->getMessage()], 400);
        flash($e->getMessage(), 'err');
    }
    header('Location: numbers.php#N-' . (int)$n);
    exit;
}

$clips = number_clips();
$recorded = count(array_filter($clips, fn($c, $k) => $k <= $max && ($c['voice'] || $c['cheer']), ARRAY_FILTER_USE_BOTH));
admin_header('Numbers', 'numbers');
?>
<h1>Numbers <small class="count">0 to <?= $max ?><?= $recorded ? " · $recorded with your voice" : '' ?></small></h1>
<p class="lead">When Ezra opens a number the game says “<b>Three! Three cars!</b>”. Record it in your own voice, or upload a clip, and
your recording plays instead. The 🎉 Hooray clip replaces “Hooray! You did it!” when he finishes tracing that number.
<?php if ($S['numbers_on'] !== '1'): ?><br><b>Numbers are turned off</b> in <a href="settings.php">Settings</a>, so Ezra can't see them yet.<?php endif; ?>
How high the numbers go is set in <a href="settings.php">Settings</a>.</p>

<nav class="jump">
  <?php for ($a = 0; $a <= $max; $a = $a === 0 ? 11 : $a + 10): $b = $a === 0 ? min(10, $max) : min($a + 9, $max); ?>
    <a href="#N-<?= $a ?>"><?= $a ?>–<?= $b ?></a>
  <?php endfor; ?>
</nav>

<div class="letters numbers">
<?php for ($n = 0; $n <= $max; $n++): $c = $clips[$n] ?? ['voice' => null, 'cheer' => null, 'ver' => 0]; [$emoji, $noun] = number_thing($n); ?>
  <form method="post" enctype="multipart/form-data" class="card letter" id="N-<?= $n ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="num" value="<?= $n ?>">
    <div class="lc-head">
      <div class="big"><?= $n ?></div>
      <div class="pic"><span><?= h($emoji) ?></span></div>
      <div class="num-say"><?= h(number_word($n)) ?><br><span class="muted"><?= $n === 0 ? 'no' : $n ?> <?= h($noun) ?></span></div>
    </div>
    <?= clip_box('voice', $c['voice'], $c['ver'], 'numbers.php', ['num' => (string)$n], (string)$n, number_phrase($n), 'N-' . $n, "Using the iPad's voice") ?>
    <?= clip_box('cheer', $c['cheer'], $c['ver'], 'numbers.php', ['num' => (string)$n], "$n: Hooray", 'Hooray! You did it!', 'N-' . $n, $S['cheer_clip'] ? 'Using your Hooray clip from Settings' : "Using the iPad's voice") ?>
    <div class="actions"><button class="btn primary" name="action" value="save">Save uploads</button></div>
  </form>
<?php endfor; ?>
</div>
<?= recorder_dialog() ?>
<?php admin_footer();
