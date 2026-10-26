<?php
/*
 * detail_pembayaran.php = daftar semua transaksi pembayaran + bisa dicetak.
 * kalo ada ?id=PAYxxx yg tampil rincian 1 transaksi aja (kaya struk/bukti bayar).
 * admin & petugas bisa liat semua + nyari, siswa cuma bisa liat punya dia sendiri.
 * yg bisa hapus transaksi cuma admin.
 */
require 'koneksi.php';
wajib_login();
$title = 'Detail Pembayaran';
$lv = $_SESSION['level'];

// ===== hapus transaksi (khusus admin) =====
// abis dihapus, cek_pembayaran siswanya diitung ulang biar statusnya bener lagi.
// dibungkus transaction juga biar hapus + sync-nya barengan
if (($_GET['aksi'] ?? '') === 'hapus' && $lv === 'admin') {
    $p = row($conn, "SELECT nisn FROM tb_pembayaran WHERE id_pembayaran=?", 's', [$_GET['id'] ?? '']);
    if ($p) {
        $conn->begin_transaction();
        try {
            q($conn, "DELETE FROM tb_pembayaran WHERE id_pembayaran=?", 's', [$_GET['id']]);
            sync_cek($conn, $p['nisn']);
            $conn->commit();
            flash('Transaksi berhasil dihapus.');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            flash('Gagal menghapus transaksi.', 'err');
        }
    }
    redirect('detail_pembayaran.php');
}

// query dasarnya sama, yg beda cuma WHERE-nya tergantung siapa yg buka
$sql = "SELECT p.id_pembayaran, p.tgl_bayar, p.nisn, p.jumlah_bulan, p.nominal_bayar, p.jumlah_bayar,
               p.kembalian, p.batas_pembayaran, p.status, s.nama, s.nama_kelas, sp.tahun, sp.nominal
        FROM tb_pembayaran p
        JOIN tb_siswa s ON s.nisn = p.nisn
        JOIN tb_spp sp ON sp.id_spp = p.id_spp";

// ===== rincian 1 transaksi =====
$id = $_GET['id'] ?? '';
if ($id !== '') {
    $d = $lv === 'siswa'
        ? row($conn, "$sql WHERE p.id_pembayaran = ? AND p.nisn = ?", 'ss', [$id, $_SESSION['username']])
        : row($conn, "$sql WHERE p.id_pembayaran = ?", 's', [$id]);
    if (!$d) { flash('Data pembayaran tidak ditemukan.', 'err'); redirect('detail_pembayaran.php'); }

    require 'layout/header.php';
    $info = [
        'ID Pembayaran'      => e($d['id_pembayaran']),
        'Tanggal Bayar'      => tgl($d['tgl_bayar']),
        'NISN'               => e($d['nisn']),
        'Nama Siswa'         => e($d['nama']),
        'Kelas'              => e($d['nama_kelas']),
        'SPP Tahun'          => e($d['tahun']) . ' (' . rupiah($d['nominal']) . ' / bulan)',
        'Jumlah Bulan'       => e($d['jumlah_bulan']) . ' bulan',
        'Total Harus Dibayar'=> '<b>' . rupiah($d['nominal_bayar']) . '</b>',
        'Uang Dibayar'       => rupiah($d['jumlah_bayar']),
        'Kembalian'          => rupiah($d['kembalian']),
        'Batas Pembayaran'   => tgl($d['batas_pembayaran']),
        'Status'             => "<span class='badge text-bg-" . ($d['status'] === 'Sudah Lunas' ? 'success' : 'danger') . "'>" . e($d['status']) . '</span>',
    ];
?>
<div class="card shadow-sm border-0" style="max-width:640px">
  <div class="card-header bg-body fw-semibold text-center">
    <i class="bi bi-receipt me-1"></i>Bukti Pembayaran SPP
  </div>
  <div class="card-body">
    <table class="table table-sm mb-0">
      <?php foreach ($info as $k => $v): ?>
      <tr><th class="fw-normal text-secondary" style="width:45%"><?= $k ?></th><td><?= $v ?></td></tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div class="card-footer bg-body noprint">
    <a class="btn btn-secondary" href="detail_pembayaran.php"><i class="bi bi-arrow-left me-1"></i>Kembali</a>
    <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak</button>
  </div>
</div>
<?php
    require 'layout/footer.php';
    exit;
}

// ===== daftar transaksi =====
$order = "ORDER BY p.tgl_bayar DESC, p.id_pembayaran DESC";
$cari = trim($_GET['cari'] ?? '');

if ($lv === 'siswa') {
    // siswa: username-nya = nisn, jadi difilter pake itu
    $data = rows($conn, "$sql WHERE p.nisn = ? $order", 's', [$_SESSION['username']]);
} elseif ($cari === '') {
    $data = rows($conn, "$sql $order");
} else {
    $like = "%$cari%";
    $data = rows($conn, "$sql WHERE p.nisn LIKE ? OR s.nama LIKE ? OR p.id_pembayaran LIKE ? $order", 'sss', [$like, $like, $like]);
}

require 'layout/header.php';
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3 noprint">
  <?php if ($lv !== 'siswa'): ?>
    <form method="get" class="d-flex gap-2">
      <input class="form-control" name="cari" placeholder="Cari ID / NISN / nama" value="<?= e($cari) ?>">
      <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
    </form>
  <?php else: ?><span></span><?php endif; ?>
  <button class="btn btn-outline-primary" onclick="window.print()"><i class="bi bi-printer me-1"></i>Cetak Laporan</button>
</div>
<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped table-bordered mb-0 align-middle small">
      <thead class="table-primary">
        <tr><th>ID</th><th>Tgl Bayar</th><th>NISN</th><th>Nama</th><th>Kelas</th><th>SPP</th><th>Bulan</th>
            <th>Nominal</th><th>Dibayar</th><th>Kembalian</th><th>Batas Bayar</th><th>Status</th><th class="noprint">Aksi</th></tr>
      </thead>
      <tbody>
      <?php if (!$data): ?><tr><td colspan="13" class="text-center text-secondary">Belum ada data pembayaran.</td></tr><?php endif; ?>
      <?php foreach ($data as $r): ?>
      <tr>
        <td><?= e($r['id_pembayaran']) ?></td><td><?= tgl($r['tgl_bayar']) ?></td><td><?= e($r['nisn']) ?></td>
        <td><?= e($r['nama']) ?></td><td><?= e($r['nama_kelas']) ?></td><td><?= e($r['tahun']) ?></td>
        <td><?= e($r['jumlah_bulan']) ?></td><td><?= rupiah($r['nominal_bayar']) ?></td><td><?= rupiah($r['jumlah_bayar']) ?></td>
        <td><?= rupiah($r['kembalian']) ?></td><td><?= tgl($r['batas_pembayaran']) ?></td>
        <td><span class="badge text-bg-<?= $r['status'] === 'Sudah Lunas' ? 'success' : 'danger' ?>"><?= e($r['status']) ?></span></td>
        <td class="noprint text-nowrap">
          <a class="btn btn-sm btn-info" href="?id=<?= urlencode($r['id_pembayaran']) ?>" title="Lihat detail"><i class="bi bi-eye"></i></a>
          <?php if ($lv === 'admin'): ?>
            <a class="btn btn-sm btn-danger" href="?aksi=hapus&id=<?= urlencode($r['id_pembayaran']) ?>" onclick="return confirm('Hapus transaksi ini?')" title="Hapus"><i class="bi bi-trash"></i></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require 'layout/footer.php'; ?>
