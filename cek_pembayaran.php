<?php
/*
 * cek_pembayaran.php = ngeliat status lunas / belum lunas tiap siswa.
 * datanya dari tabel cek_pembayaran yg selalu di-update tiap ada transaksi (lewat sync_cek).
 * siswa yg belum pernah bayar tetep muncul karena pake LEFT JOIN dari tb_siswa.
 */
require 'koneksi.php';
wajib_login();
$title = 'Cek Status Pembayaran';
$lv = $_SESSION['level'];

// ===== tombol perbarui status (admin/petugas) =====
// dulu di-loop per siswa, tiap siswa 4 query. sekarang sync_cek() tanpa nisn
// langsung ngerjain semua siswa sekaligus, cuma 2 query berapapun jumlah siswanya
if (($_GET['aksi'] ?? '') === 'refresh' && $lv !== 'siswa') {
    sync_cek($conn);
    flash('Status pembayaran berhasil diperbarui.');
    redirect('cek_pembayaran.php');
}

$sql = "SELECT s.nisn, s.nama, s.nama_kelas, s.no_telp, c.tgl_terakhir_bayar, c.tgl_sekarang,
               c.status_pembayaran, c.jumlah_bulan
        FROM tb_siswa s LEFT JOIN cek_pembayaran c ON c.nisn = s.nisn";
$data = $lv === 'siswa'
    ? rows($conn, "$sql WHERE s.nisn = ?", 's', [$_SESSION['username']])
    : rows($conn, "$sql ORDER BY s.nama");

require 'layout/header.php';
?>
<?php if ($lv !== 'siswa'): ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <span class="text-secondary small"><i class="bi bi-info-circle me-1"></i>Status dihitung dari total bulan yang dibayar pada tahun SPP siswa (12 bulan = Lunas).</span>
  <a class="btn btn-primary" href="?aksi=refresh"><i class="bi bi-arrow-repeat me-1"></i>Perbarui Status</a>
</div>
<?php endif; ?>
<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped table-bordered mb-0 align-middle">
      <thead class="table-primary">
        <tr><th>No</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>No. Telp</th><th>Tgl Terakhir Bayar</th>
            <th>Tgl Cek</th><th>Bulan Dibayar</th><th>Sisa Bulan</th><th>Status</th></tr>
      </thead>
      <tbody>
      <?php foreach ($data as $i => $r):
          // kalo siswanya belum ada di cek_pembayaran (belum pernah bayar), nilainya null jadi dianggap 0
          $bln = (int)($r['jumlah_bulan'] ?? 0);
          $status = $r['tgl_terakhir_bayar'] ? $r['status_pembayaran'] : 'Belum Pernah Bayar';
          $warna = $status === 'Sudah Lunas' ? 'success' : ($r['tgl_terakhir_bayar'] ? 'danger' : 'secondary'); ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['nisn']) ?></td><td><?= e($r['nama']) ?></td><td><?= e($r['nama_kelas']) ?></td>
        <td><?= e($r['no_telp']) ?></td><td><?= tgl($r['tgl_terakhir_bayar']) ?></td><td><?= tgl($r['tgl_sekarang']) ?></td>
        <td>
          <div class="progress" style="height:18px;min-width:90px">
            <div class="progress-bar bg-<?= $warna ?>" style="width:<?= min(100, $bln / 12 * 100) ?>%"><?= $bln ?></div>
          </div>
        </td>
        <td><?= max(0, 12 - $bln) ?></td>
        <td><span class="badge text-bg-<?= $warna ?>"><?= e($status) ?></span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require 'layout/footer.php'; ?>
