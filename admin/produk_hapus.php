<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_role('admin');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id > 0) {
    $chk = $pdo->prepare('SELECT COUNT(*) FROM detail_pesanan WHERE produk_id = ?');
    $chk->execute([$id]);
    if ((int) $chk->fetchColumn() > 0) {
        $_SESSION['flash_err'] = 'Produk tidak bisa dihapus karena pernah masuk pesanan.';
    } else {
        $pdo->prepare('DELETE FROM produk WHERE id = ?')->execute([$id]);
    }
}

redirect('admin/produk.php');
