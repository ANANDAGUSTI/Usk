<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';
require_role('petugas');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['flash_err'] = 'ID pesanan tidak valid.';
    redirect('petugas/index.php');
}

$st = $pdo->prepare('SELECT status FROM pesanan WHERE id = ?');
$st->execute([$id]);
$row = $st->fetch();
if (!$row) {
    $_SESSION['flash_err'] = 'Pesanan tidak ditemukan.';
    redirect('petugas/index.php');
}

if (($row['status'] ?? '') !== 'selesai') {
    $_SESSION['flash_err'] = 'Pesanan hanya bisa dihapus jika statusnya Selesai.';
    redirect('petugas/index.php');
}

$pdo->prepare('DELETE FROM pesanan WHERE id = ?')->execute([$id]);
$_SESSION['flash_ok'] = 'Pesanan berhasil dihapus.';
redirect('petugas/index.php');

