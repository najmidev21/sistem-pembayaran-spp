<?php
/*
 * dashboard.php
 * halaman pertama abis login.
 * buat admin/petugas: ringkasan jumlah siswa yg sudah lunas & belum lunas + daftar nama siswanya,
 * sama 5 transaksi terakhir. kalo siswa ya nampilin status spp dia sendiri.
 */
require 'koneksi.php';
wajib_login();
$title = 'Dashboard';
$lv = $_SESSION['level'];

if ($lv !== 'siswa') {
    // status tiap siswa diambil dari cek_pembayaran (LEFT JOIN, jadi siswa yg belum pernah bayar tetep ikut
    // dan dianggap belum lunas). trus dipisah jadi 2 daftar di php, cukup 1 query aja
    $semua = rows($conn, "SELECT s.nisn, s.nama, s.nama_kelas, c.status_pembayaran, c.jumlah_bulan, c.tgl_terakhir_bayar
                          FROM tb_siswa s LEFT JOIN cek_pembayaran c ON c.nisn = s.nisn
                          ORDER BY s.nama_kelas, s.nama");
    $lunas = $belum = [];
    foreach ($semua as $r) {
        if ($r['status_pembayaran'] === 'Sudah Lunas') $lunas[] = $r; else $belum[] = $r;
    }

    $total = row($conn, "SELECT COALESCE(SUM(CAST(nominal_bayar AS UNSIGNED)),0) AS t FROM tb_pembayaran")['t'];

    // 5 transaksi terbaru, join ke siswa cuma buat ambil namanya
    $terbaru = rows($conn, "SELECT p.id_pembayaran, p.tgl_bayar, p.jumlah_bulan, p.nominal_bayar, p.status, s.nama
                            FROM tb_pembayaran p JOIN tb_siswa s ON s.nisn = p.nisn
                            ORDER BY p.tgl_bayar DESC, p.id_pembayaran DESC LIMIT 5");
} else {
    // buat siswa cukup ambil 1 baris status dia dari cek_pembayaran (username siswa = nisn)
    $cek = row($conn, "SELECT status_pembayaran, jumlah_bulan, tgl_terakhir_bayar FROM cek_pembayaran WHERE nisn=?", 's', [$_SESSION['username']]);
}

require 'layout/header.php';
?>
<div class="alert alert-primary border-0 shadow-sm">
  <i class="bi bi-emoji-smile me-1"></i> Selamat datang, <b><?= e($_SESSION['nama']) ?></b> (<?= e($lv) ?>).
</div>

<?php if ($lv !== 'siswa'):
    // array kecil biar kartu statistiknya tinggal di-loop, ga copas html berkali2
    $kartu = [
        ['Jumlah Siswa', count($semua), 'people', ''],
        ['Sudah Lunas', count($lunas), 'check-circle', 'lunas'],
        ['Belum Lunas', count($belum), 'x-circle', 'belum'],
        ['Total Diterima', rupiah($total), 'cash-coin', ''],
    ];
?>
<div class="row g-3 mb-4">
  <?php foreach ($kartu as [$label, $nilai, $icon, $cls]): ?>
  <div class="col-6 col-lg-3">
    <div class="card stat-card <?= $cls ?> shadow-sm border-0 h-100">
      <div class="card-body">
        <div class="text-secondary small"><i class="bi bi-<?= $icon ?> me-1"></i><?= $label ?></div>
        <div class="angka <?= $icon === 'cash-coin' ? 'fs-5' : '' ?>"><?= e($nilai) ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-3 mb-4">
  <?php
  // 2 kartu daftar siswa: kiri yg sudah lunas, kanan yg belum lunas
  $daftar = [
      ['Siswa Sudah Lunas', $lunas, 'success', 'check-circle-fill', 'Belum ada siswa yang lunas.'],
      ['Siswa Belum Lunas', $belum, 'danger', 'exclamation-circle-fill', 'Semua siswa sudah lunas.'],
  ];
  foreach ($daftar as [$judul, $list, $warna, $icon, $kosong]): ?>
  <div class="col-lg-6">
    <div class="card shadow-sm border-0 h-100">
      <div class="card-header bg-body fw-semibold text-<?= $warna ?>">
        <i class="bi bi-<?= $icon ?> me-1"></i><?= $judul ?>
        <span class="badge text-bg-<?= $warna ?> ms-1"><?= count($list) ?></span>
      </div>
      <div class="card-body p-0 list-scroll">
        <table class="table table-sm table-hover mb-0 align-middle">
          <thead class="table-light"><tr><th>No</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>Bulan</th></tr></thead>
          <tbody>
          <?php if (!$list): ?><tr><td colspan="5" class="text-center text-secondary py-3"><?= $kosong ?></td></tr><?php endif; ?>
          <?php foreach ($list as $i => $r): $bln = (int)($r['jumlah_bulan'] ?? 0); ?>
          <tr>
            <td><?= $i + 1 ?></td><td><?= e($r['nisn']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['nama_kelas']) ?></td>
            <td>
              <?php if ($r['tgl_terakhir_bayar']): ?><?= $bln ?>/12
              <?php else: ?><span class="badge text-bg-secondary">Belum bayar</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div class="card shadow-sm border-0">
  <div class="card-header bg-body fw-semibold d-flex justify-content-between">
    <span><i class="bi bi-clock-history me-1"></i>Pembayaran Terbaru</span>
    <a class="small" href="detail_pembayaran.php">Lihat semua</a>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table table-hover table-striped mb-0 align-middle">
        <thead class="table-primary"><tr><th>ID</th><th>Tanggal</th><th>Nama Siswa</th><th>Bulan</th><th>Nominal</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$terbaru): ?><tr><td colspan="6" class="text-center text-secondary">Belum ada transaksi.</td></tr><?php endif; ?>
        <?php foreach ($terbaru as $r): ?>
        <tr>
          <td><a href="detail_pembayaran.php?id=<?= urlencode($r['id_pembayaran']) ?>"><?= e($r['id_pembayaran']) ?></a></td>
          <td><?= tgl($r['tgl_bayar']) ?></td><td><?= e($r['nama']) ?></td>
          <td><?= e($r['jumlah_bulan']) ?></td><td><?= rupiah($r['nominal_bayar']) ?></td>
          <td><span class="badge text-bg-<?= $r['status'] === 'Sudah Lunas' ? 'success' : 'danger' ?>"><?= e($r['status']) ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php else: ?>
<div class="card shadow-sm border-0">
  <div class="card-body">
    <h5 class="card-title">Status SPP Anda</h5>
    <?php if ($cek): $bln = (int)$cek['jumlah_bulan']; ?>
      <p class="mb-2">
        <span class="badge fs-6 text-bg-<?= $cek['status_pembayaran'] === 'Sudah Lunas' ? 'success' : 'danger' ?>"><?= e($cek['status_pembayaran']) ?></span>
        <span class="ms-2 text-secondary">Terakhir bayar: <?= tgl($cek['tgl_terakhir_bayar']) ?></span>
      </p>
      <div class="progress mb-2" style="height:22px">
        <div class="progress-bar" style="width:<?= min(100, $bln / 12 * 100) ?>%"><?= $bln ?>/12 bulan</div>
      </div>
    <?php else: ?>
      <p class="text-secondary mb-2">Anda belum pernah melakukan pembayaran.</p>
    <?php endif; ?>
    <p class="mb-0 small">Lihat rinciannya di menu <a href="detail_pembayaran.php">Detail Pembayaran</a> dan <a href="cek_pembayaran.php">Cek Pembayaran</a>.</p>
  </div>
</div>
<?php endif; ?>
<?php require 'layout/footer.php'; ?>
