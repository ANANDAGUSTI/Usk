<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pageTitle = 'Checkout';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$errors = [];
$nama = trim((string) ($_POST['nama_pembeli'] ?? ''));
$telepon = trim((string) ($_POST['telepon'] ?? ''));
$alamat = trim((string) ($_POST['alamat'] ?? ''));

// Build cart snapshot
$items = [];
$total = 0.0;
foreach ($_SESSION['cart'] as $pid => $qty) {
    $pid = (int) $pid;
    $qty = (int) $qty;
    if ($qty < 1) {
        continue;
    }
    $st = $pdo->prepare('SELECT id, nama, harga, stok FROM produk WHERE id = ?');
    $st->execute([$pid]);
    $p = $st->fetch();
    if (!$p) {
        unset($_SESSION['cart'][$pid]);
        continue;
    }
    $qty = min($qty, (int) $p['stok']);
    if ($qty < 1) {
        continue;
    }
    $sub = $qty * (float) $p['harga'];
    $total += $sub;
    $items[] = ['produk' => $p, 'qty' => $qty, 'subtotal' => $sub];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($nama === '') {
        $errors[] = 'Nama wajib diisi.';
    }
    if ($telepon === '') {
        $errors[] = 'Telepon wajib diisi.';
    }
    if ($alamat === '') {
        $errors[] = 'Alamat wajib diisi.';
    }
    if (empty($items)) {
        $errors[] = 'Keranjang kosong atau stok tidak mencukupi.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stLock = $pdo->prepare('SELECT id, stok, harga FROM produk WHERE id = ? FOR UPDATE');
            $totalCheck = 0.0;
            $locked = [];
            foreach ($_SESSION['cart'] as $pid => $qty) {
                $pid = (int) $pid;
                $qty = (int) $qty;
                if ($qty < 1) {
                    continue;
                }
                $stLock->execute([$pid]);
                $p = $stLock->fetch();
                if (!$p || $qty > (int) $p['stok']) {
                    throw new RuntimeException('Stok produk berubah. Silakan perbarui keranjang.');
                }
                $q = min($qty, (int) $p['stok']);
                $locked[] = ['id' => $pid, 'qty' => $q, 'harga' => (float) $p['harga']];
                $totalCheck += $q * (float) $p['harga'];
            }

            if (empty($locked)) {
                throw new RuntimeException('Tidak ada item valid.');
            }

            $insP = $pdo->prepare(
                'INSERT INTO pesanan (nama_pembeli, telepon, alamat, total, status) VALUES (?,?,?,?,?)'
            );
            $insP->execute([$nama, $telepon, $alamat, $totalCheck, 'menunggu']);
            $pesananId = (int) $pdo->lastInsertId();

            $insD = $pdo->prepare(
                'INSERT INTO detail_pesanan (pesanan_id, produk_id, qty, harga_satuan, subtotal) VALUES (?,?,?,?,?)'
            );
            $upd = $pdo->prepare('UPDATE produk SET stok = stok - ? WHERE id = ?');

            foreach ($locked as $row) {
                $sub = $row['qty'] * $row['harga'];
                $insD->execute([$pesananId, $row['id'], $row['qty'], $row['harga'], $sub]);
                $upd->execute([$row['qty'], $row['id']]);
            }

            $pdo->commit();
            $_SESSION['cart'] = [];
            $_SESSION['checkout_ok'] = $pesananId;
            header('Location: ' . url('checkout.php'));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

$okId = $_SESSION['checkout_ok'] ?? null;
if ($okId !== null) {
    unset($_SESSION['checkout_ok']);
}

require __DIR__ . '/includes/header.php';
?>

<?php if ($okId): ?>
  <div class="alert alert-ok">
    Pesanan berhasil dikirim. Nomor pesanan: <strong>#<?= (int) $okId ?></strong>.
    Terima kasih telah berbelanja.
  </div>
  <p><a class="btn" href="<?= htmlspecialchars(url('index.php')) ?>">Kembali belanja</a></p>
<?php else: ?>

<h1>Checkout</h1>

<?php if ($errors): ?>
  <div class="alert alert-err">
    <?php foreach ($errors as $e): ?>
      <div><?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (empty($items)): ?>
  <p class="muted">Keranjang kosong. <a href="<?= htmlspecialchars(url('index.php')) ?>">Belanja dulu</a></p>
<?php else: ?>

  <h2>Ringkasan</h2>
  <table>
    <thead>
      <tr><th>Produk</th><th>Jumlah</th><th>Subtotal</th></tr>
    </thead>
    <tbody>
      <?php foreach ($items as $row): ?>
        <tr>
          <td><?= htmlspecialchars($row['produk']['nama']) ?></td>
          <td><?= (int) $row['qty'] ?></td>
          <td>Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <p><strong>Total: Rp <?= number_format($total, 0, ',', '.') ?></strong></p>

  <h2>Data pembeli</h2>
  <form method="post">
    <div class="form-group">
      <label for="nama_pembeli">Nama</label>
      <input type="text" id="nama_pembeli" name="nama_pembeli" required maxlength="120"
             value="<?= htmlspecialchars($nama) ?>">
    </div>
    <div class="form-group">
      <label for="telepon">Telepon</label>
      <input type="text" id="telepon" name="telepon" required maxlength="32"
             value="<?= htmlspecialchars($telepon) ?>">
    </div>
    <div class="form-group">
      <label for="alamat">Alamat</label>
      <textarea id="alamat" name="alamat" required maxlength="2000"><?= htmlspecialchars($alamat) ?></textarea>
    </div>
    <button type="submit" class="btn">Konfirmasi pesanan</button>
    <a class="btn btn-ghost" href="<?= htmlspecialchars(url('keranjang.php')) ?>">Kembali ke keranjang</a>
  </form>
<?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
