<?php
/*
 * header.php
 * template atas yg dipake semua halaman setelah login: navbar, sidebar menu, sama judul halaman.
 * menu yg muncul beda2 tergantung level user (admin / petugas / siswa):
 *   admin   -> Dashboard, Data Kelas, Data Siswa, Cek Pembayaran, Pembayaran, Detail Pembayaran, Data Petugas
 *   petugas -> Dashboard, Cek Pembayaran, Pembayaran, Detail Pembayaran
 *   siswa   -> Dashboard, Cek Pembayaran, Detail Pembayaran (cuma data dia sendiri)
 * pake bootstrap 5 dari CDN, jadi pas buka aplikasinya harus ada internet ya.
 */
$lv = $_SESSION['level'];
$page = basename($_SERVER['PHP_SELF']);

// daftar menu: [file, label, icon, level yg boleh liat]
$menus = [
    ['dashboard.php',         'Dashboard',         'speedometer2',    ['admin', 'petugas', 'siswa']],
    ['kelas.php',             'Data Kelas',        'building',        ['admin']],
    ['siswa.php',             'Data Siswa',        'people',          ['admin']],
    ['cek_pembayaran.php',    'Cek Pembayaran',    'clipboard-check', ['admin', 'petugas', 'siswa']],
    ['pembayaran.php',        'Pembayaran',        'cart-plus',       ['admin', 'petugas']],
    ['detail_pembayaran.php', 'Detail Pembayaran', 'receipt',         ['admin', 'petugas', 'siswa']],
    ['petugas.php',           'Data Petugas',      'person-badge',    ['admin']],
];
// data spp dikelola dari halaman data siswa, jadi pas buka spp.php menu Data Siswa yg nyala
$aktif = $page === 'spp.php' ? 'siswa.php' : $page;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Aplikasi SPP') ?> - Aplikasi SPP</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-dark bg-primary shadow-sm sticky-top noprint">
  <div class="container-fluid">
    <div class="d-flex align-items-center">
      <!-- tombol ini cuma muncul di hp, buat buka sidebar -->
      <button class="btn btn-primary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
        <i class="bi bi-list fs-4"></i>
      </button>
      <span class="navbar-brand fw-bold"><i class="bi bi-wallet2 me-2"></i>Aplikasi Pembayaran SPP</span>
    </div>
    <span class="navbar-text text-white">
      <i class="bi bi-person-circle me-1"></i><?= e($_SESSION['nama']) ?>
      <span class="badge text-bg-warning text-uppercase ms-1"><?= e($lv) ?></span>
    </span>
  </div>
</nav>

<div class="d-flex">
  <!-- sidebar: di laptop tampil biasa, di hp jadi offcanvas (geser dari kiri) -->
  <aside class="offcanvas-lg offcanvas-start bg-body border-end sidebar noprint" tabindex="-1" id="sidebar">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title">Menu</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#sidebar"></button>
    </div>
    <div class="offcanvas-body p-2">
      <ul class="nav nav-pills flex-column w-100">
        <li class="menu-group">Menu</li>
        <?php foreach ($menus as [$file, $label, $icon, $boleh]): if (!in_array($lv, $boleh, true)) continue; ?>
          <li class="nav-item">
            <a class="nav-link <?= $aktif === $file ? 'active' : 'link-body-emphasis' ?>" href="<?= $file ?>"><i class="bi bi-<?= $icon ?> me-2"></i><?= $label ?></a>
          </li>
        <?php endforeach; ?>
        <li class="menu-group">Akun</li>
        <li class="nav-item">
          <a class="nav-link text-danger" href="logout.php" onclick="return confirm('Keluar dari aplikasi?')"><i class="bi bi-box-arrow-right me-2"></i>Logout</a>
        </li>
      </ul>
    </div>
  </aside>

  <main class="flex-grow-1 p-3 p-md-4 content">
    <h1 class="h4 mb-3"><?= e($title ?? '') ?></h1>
    <?php flash(); ?>
