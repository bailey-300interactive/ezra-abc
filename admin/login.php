<?php
declare(strict_types=1);
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/admin_layout.php';

if (is_logged_in()) { header('Location: letters.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (attempt_login(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) {
        header('Location: letters.php');
        exit;
    }
    $error = 'That username or password is wrong.';
}

admin_header('Log in', 'login');
?>
<form method="post" class="card login">
  <img src="../assets/icons/icon-192.png" alt="" class="login-icon">
  <h1><?= h(settings()['title']) ?></h1>
  <?php if ($error): ?><div class="flash err"><?= h($error) ?></div><?php endif; ?>
  <?= csrf_field() ?>
  <label>Username <input name="username" autocomplete="username" required autofocus></label>
  <label>Password <input name="password" type="password" autocomplete="current-password" required></label>
  <button class="btn primary">Log in</button>
</form>
<?php admin_footer();
