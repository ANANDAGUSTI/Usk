<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pageTitle = 'Keranjang';

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['hapus_id'])) {
        unset($_SESSION['cart'][(int) $_POST['hapus_id']]);
    } elseif (isset($_POST['update'])) {
        foreach ($_POST['qty'] ?? [] as $id => $q) {
            $id = (int) $id;
            $q = max(0, (int) $q);
            if ($q === 0) {
                unset($_SESSION['cart'][$id]);
            } else {
                $st = $pdo->prepare('SELECT stok FROM produk WHERE id = ?');
                $st->execute([$id]);
                $stok = (int) ($st->fetch()['stok'] ?? 0);
                $_SESSION['cart'][$id] = min($q, $stok);
            }
        }
    }
    header('Location: ' . url('keranjang.php'));
    exit;
}

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
    $_SESSION['cart'][$pid] = $qty;
    $sub = $qty * (float) $p['harga'];
    $total += $sub;
    $items[] = [
        'produk' => $p,
        'qty' => $qty,
        'subtotal' => $sub,
    ];
}

require __DIR__ . '/includes/header.php';
?>
<h1>Keranjang</h1>

<?php if (empty($items)): ?>
  <p class="muted">Keranjang kosong. <a href="<?= htmlspecialchars(url('index.php')) ?>">Belanja</a></p>
<?php else: ?>
  <form method="post">
    <table>
      <thead>
        <tr>
          <th>Produk</th>
          <th>Harga</th>
          <th>Jumlah</th>
          <th>Subtotal</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $row): ?>
          <?php $p = $row['produk']; ?>
          <tr>
            <td><?= htmlspecialchars($p['nama']) ?></td>
            <td>Rp <?= number_format((float) $p['harga'], 0, ',', '.') ?></td>
            <td>
              <input type="number" name="qty[<?= (int) $p['id'] ?>]" value="<?= (int) $row['qty'] ?>"
                     min="0" max="<?= (int) $p['stok'] ?>" style="width:4rem;">
            </td>
            <td>Rp <?= number_format($row['subtotal'], 0, ',', '.') ?></td>
            <td>
              <button type="submit" name="hapus_id" value="<?= (int) $p['id'] ?>" class="btn btn-ghost btn-sm">Hapus</button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p class="stack" style="margin-top:1rem;">
      <button type="submit" name="update" value="1" class="btn btn-ghost">Perbarui keranjang</button>
      <a class="btn" href="<?= htmlspecialchars(url('checkout.php')) ?>">Checkout</a>
    </p>
  </form>
  <p><strong>Total: Rp <?= number_format($total, 0, ',', '.') ?></strong></p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
