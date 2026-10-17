<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/admin_layout.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'delete') {
        $st = db()->prepare('SELECT file FROM drawings WHERE id = ?');
        $st->execute([(int)($_POST['id'] ?? 0)]);
        if ($f = $st->fetchColumn()) {
            delete_upload($f);
            db()->prepare('DELETE FROM drawings WHERE id = ?')->execute([(int)$_POST['id']]);
            flash('Drawing removed.');
        }
    } elseif ($action === 'delete_all' && ($_POST['confirm'] ?? '') === 'yes') {
        foreach (db()->query('SELECT file FROM drawings')->fetchAll(PDO::FETCH_COLUMN) as $f) delete_upload($f);
        db()->exec('DELETE FROM drawings');
        flash('The fridge is empty.');
    }
    header('Location: drawings.php');
    exit;
}

$per = 48;
$page = max(1, (int)($_GET['p'] ?? 1));
$total = (int)db()->query('SELECT COUNT(*) FROM drawings')->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$st = db()->prepare('SELECT * FROM drawings ORDER BY id DESC LIMIT ? OFFSET ?');
$st->execute([$per, ($page - 1) * $per]);
$rows = $st->fetchAll();

admin_header('Fridge', 'drawings');
?>
<h1>Fridge <small class="count"><?= $total ?> drawing<?= $total === 1 ? '' : 's' ?></small></h1>
<p class="lead">Drawings land here when Ezra taps ⭐<?= settings()['auto_save'] === '1' ? ', and every time he finishes tracing a letter or number' : '' ?>.
The newest 60 show on the fridge in the game.</p>

<?php if (!$rows): ?>
  <div class="card empty">Nothing on the fridge yet.</div>
<?php else: ?>
<div class="gallery">
  <?php foreach ($rows as $r): $ch = $r['letter_case'] === 'lower' ? strtolower($r['letter']) : $r['letter']; ?>
    <figure class="card">
      <a href="../<?= h($r['file']) ?>" target="_blank"><img src="../<?= h($r['file']) ?>" loading="lazy" alt="<?= h($ch) ?>"></a>
      <figcaption>
        <b><?= h($ch) ?></b> <?= $r['mode'] === 'draw' ? '🎨' : '✏️' ?>
        <span class="muted"><?= h(date('M j, g:ia', strtotime($r['created_at']))) ?></span>
        <a class="link" href="../<?= h($r['file']) ?>" download>Download</a>
        <form method="post" onsubmit="return confirm('Remove this drawing?')">
          <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="link danger" name="action" value="delete">Remove</button>
        </form>
      </figcaption>
    </figure>
  <?php endforeach; ?>
</div>
<?php if ($pages > 1): ?>
  <nav class="pager">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?p=<?= $i ?>" class="<?= $i === $page ? 'on' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </nav>
<?php endif; ?>
<form method="post" class="danger-zone" onsubmit="return confirm('Remove every drawing? This can\'t be undone.')">
  <?= csrf_field() ?><input type="hidden" name="confirm" value="yes">
  <button class="btn danger" name="action" value="delete_all">Empty the whole fridge</button>
</form>
<?php endif; ?>
<?php admin_footer();
