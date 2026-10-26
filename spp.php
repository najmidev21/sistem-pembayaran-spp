<?php
/*
 * spp.php = CRUD data spp (khusus admin).
 * nominal di sini maksudnya biaya spp PER BULAN ya, nanti di pembayaran dikali jumlah bulan.
 */
require 'koneksi.php';
wajib_login(['admin']);
$title = 'Data SPP';
$aksi = $_GET['aksi'] ?? 'list';

// ===== proses simpan =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tahun = (int)$_POST['tahun'];
    $nominal = preg_replace('/\D/', '', $_POST['nominal']); // buang semua yg bukan angka, jaga2 kalo ada titik/koma
    $id = trim($_POST['id_spp']);
    try {
        if ($_POST['mode'] === 'tambah') {
            q($conn, "INSERT INTO tb_spp (id_spp, tahun, nominal) VALUES (?,?,?)", 'sis', [$id, $tahun, $nominal]);
            flash('Data SPP berhasil ditambahkan.');
        } else {
            q($conn, "UPDATE tb_spp SET tahun=?, nominal=? WHERE id_spp=?", 'iss', [$tahun, $nominal, $id]);
            flash('Data SPP berhasil diubah.');
        }
    } catch (mysqli_sql_exception $ex) {
        flash('Gagal menyimpan data SPP.', 'err');
    }
    redirect('spp.php');
}

// ===== proses hapus =====
// tb_spp ga punya foreign key (sesuai diagram), jadi harus dicek manual dulu masih dipake apa nggak.
// pake EXISTS biar database berhenti nyari begitu ketemu 1 baris, ga usah ngitung semua kaya COUNT
if ($aksi === 'hapus') {
    $id = $_GET['id'] ?? '';
    $pakai = row($conn, "SELECT EXISTS(SELECT 1 FROM tb_siswa WHERE id_spp=?) OR EXISTS(SELECT 1 FROM tb_pembayaran WHERE id_spp=?) AS n", 'ss', [$id, $id])['n'];
    if ($pakai) {
        flash('Gagal menghapus: SPP masih dipakai oleh siswa/pembayaran.', 'err');
    } else {
        q($conn, "DELETE FROM tb_spp WHERE id_spp=?", 's', [$id]);
        flash('Data SPP berhasil dihapus.');
    }
    redirect('spp.php');
}

// data buat form edit diambil sebelum header, biar kalo id-nya ga ada bisa langsung di-redirect
if ($aksi === 'edit') {
    $r = row($conn, "SELECT id_spp, tahun, nominal FROM tb_spp WHERE id_spp=?", 's', [$_GET['id'] ?? '']);
    if (!$r) { flash('Data SPP tidak ditemukan.', 'err'); redirect('spp.php'); }
}

require 'layout/header.php';

if ($aksi === 'tambah' || $aksi === 'edit'):
    if ($aksi === 'tambah') $r = ['id_spp' => next_id($conn, 'tb_spp', 'id_spp', 'SPP'), 'tahun' => date('Y'), 'nominal' => ''];
?>
<div class="card shadow-sm border-0" style="max-width:640px">
  <div class="card-header bg-body fw-semibold"><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?> SPP</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="mode" value="<?= e($aksi) ?>">
      <div class="col-md-4">
        <label class="form-label">ID SPP</label>
        <input class="form-control" name="id_spp" value="<?= e($r['id_spp']) ?>" readonly>
      </div>
      <div class="col-md-8">
        <label class="form-label">Tahun Ajaran</label>
        <input type="number" class="form-control" name="tahun" value="<?= e($r['tahun']) ?>" min="2000" max="2100" required>
      </div>
      <div class="col-12">
        <label class="form-label">Nominal per Bulan</label>
        <div class="input-group">
          <span class="input-group-text">Rp</span>
          <input type="number" class="form-control" name="nominal" value="<?= e($r['nominal']) ?>" min="0" required>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a class="btn btn-secondary" href="spp.php">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php else:
    $data = rows($conn, "SELECT id_spp, tahun, nominal FROM tb_spp ORDER BY tahun DESC");
?>
<div class="mb-3 d-flex gap-2">
  <a class="btn btn-primary" href="?aksi=tambah"><i class="bi bi-plus-lg me-1"></i>Tambah SPP</a>
  <a class="btn btn-outline-secondary" href="siswa.php"><i class="bi bi-arrow-left me-1"></i>Kembali ke Data Siswa</a>
</div>
<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped table-bordered mb-0 align-middle">
      <thead class="table-primary"><tr><th>No</th><th>ID SPP</th><th>Tahun</th><th>Nominal / Bulan</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($data as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['id_spp']) ?></td><td><?= e($r['tahun']) ?></td><td><?= rupiah($r['nominal']) ?></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-warning" href="?aksi=edit&id=<?= urlencode($r['id_spp']) ?>"><i class="bi bi-pencil"></i></a>
          <a class="btn btn-sm btn-danger" href="?aksi=hapus&id=<?= urlencode($r['id_spp']) ?>" onclick="return confirm('Hapus data ini?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; require 'layout/footer.php'; ?>
