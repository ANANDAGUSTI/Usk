<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pageTitle = 'Login';

if (auth_user()) {
    if (auth_role() === 'admin') {
        redirect('admin/index.php');
    }
    if (auth_role() === 'petugas') {
        redirect('petugas/index.php');
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');

    $st = $pdo->prepare('SELECT id, username, password, role FROM pengguna WHERE username = ?');
    $st->execute([$user]);
    $row = $st->fetch();

    if ($row && password_verify($pass, $row['password'])) {
        $_SESSION['user'] = [
            'id' => (int) $row['id'],
            'username' => $row['username'],
            'role' => $row['role'],
        ];
        if ($row['role'] === 'admin') {
            redirect('admin/index.php');
        }
        redirect('petugas/index.php');
    }
    $error = 'Username atau password salah.';
}

require __DIR__ . '/includes/header.php';
?>

<h1>Login Admin / Petugas</h1>
<p class="muted">Pembeli tidak perlu login — halaman ini hanya untuk pengelola.</p>

<?php if ($error): ?>
  <div class="alert alert-err"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" style="max-width:360px;">
  <div class="form-group">
    <label for="username">Username</label>
    <input type="text" id="username" name="username" required autocomplete="username" autofocus>
  </div>
  <div class="form-group">
    <label for="password">Password</label>
    <input type="password" id="password" name="password" required autocomplete="current-password">
  </div>
  <button type="submit" class="btn">Masuk</button>
  <a class="btn btn-ghost" href="<?= htmlspecialchars(url('index.php')) ?>">Kembali ke toko</a>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
