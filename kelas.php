<?php
/*
 * kelas.php = CRUD data kelas (khusus admin).
 * polanya sama kaya siswa.php: ?aksi=list/tambah/edit/hapus, simpan lewat POST.
 * id kelas dibikin otomatis (KLS001, KLS002, ...) pake next_id().
 */
require 'koneksi.php';
wajib_login(['admin']);
$title = 'Data Kelas';
$aksi = $_GET['aksi'] ?? 'list';

// ===== proses simpan =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $d = [trim($_POST['nama_kelas']), trim($_POST['komp_keahlian']), trim($_POST['id_kelas'])];
    try {
        if ($_POST['mode'] === 'tambah') {
            q($conn, "INSERT INTO tb_kelas (nama_kelas, komp_keahlian, id_kelas) VALUES (?,?,?)", 'sss', $d);
            flash('Data kelas berhasil ditambahkan.');
        } else {
            // nama_kelas di tb_siswa ga perlu di-update manual lagi, soalnya udah ada
            // foreign key fk_siswa_nama_kelas ON UPDATE CASCADE, jadi otomatis ikut ganti sama database. hemat 1 query
            q($conn, "UPDATE tb_kelas SET nama_kelas=?, komp_keahlian=? WHERE id_kelas=?", 'sss', $d);
            flash('Data kelas berhasil diubah.');
        }
    } catch (mysqli_sql_exception $ex) {
        flash('Gagal menyimpan (nama kelas mungkin sudah ada).', 'err');
    }
    redirect('kelas.php');
}

// ===== proses hapus, ditolak FK kalo kelasnya masih ada siswanya =====
if ($aksi === 'hapus') {
    try {
        q($conn, "DELETE FROM tb_kelas WHERE id_kelas=?", 's', [$_GET['id'] ?? '']);
        flash('Data kelas berhasil dihapus.');
    } catch (mysqli_sql_exception $ex) {
        flash('Gagal menghapus: kelas masih dipakai oleh data siswa.', 'err');
    }
    redirect('kelas.php');
}

// data buat form edit diambil sebelum header, biar kalo id-nya ga ada bisa langsung di-redirect
if ($aksi === 'edit') {
    $r = row($conn, "SELECT id_kelas, nama_kelas, komp_keahlian FROM tb_kelas WHERE id_kelas=?", 's', [$_GET['id'] ?? '']);
    if (!$r) { flash('Data kelas tidak ditemukan.', 'err'); redirect('kelas.php'); }
}

require 'layout/header.php';

if ($aksi === 'tambah' || $aksi === 'edit'):
    if ($aksi === 'tambah') $r = ['id_kelas' => next_id($conn, 'tb_kelas', 'id_kelas', 'KLS'), 'nama_kelas' => '', 'komp_keahlian' => ''];
?>
<div class="card shadow-sm border-0" style="max-width:640px">
  <div class="card-header bg-body fw-semibold"><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?> Kelas</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="mode" value="<?= e($aksi) ?>">
      <div class="col-md-4">
        <label class="form-label">ID Kelas</label>
        <input class="form-control" name="id_kelas" value="<?= e($r['id_kelas']) ?>" maxlength="11" readonly>
      </div>
      <div class="col-md-8">
        <label class="form-label">Nama Kelas</label>
        <input class="form-control" name="nama_kelas" value="<?= e($r['nama_kelas']) ?>" maxlength="10" required>
      </div>
      <div class="col-12">
        <label class="form-label">Kompetensi Keahlian</label>
        <input class="form-control" name="komp_keahlian" value="<?= e($r['komp_keahlian']) ?>" maxlength="50" required>
      </div>
      <div class="col-12">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a class="btn btn-secondary" href="kelas.php">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php else:
    // sekalian ngitung jumlah siswa per kelas pake LEFT JOIN + GROUP BY, jadi ga perlu query COUNT per baris
    $data = rows($conn, "SELECT k.id_kelas, k.nama_kelas, k.komp_keahlian, COUNT(s.nisn) AS jml_siswa
                         FROM tb_kelas k LEFT JOIN tb_siswa s ON s.id_kelas = k.id_kelas
                         GROUP BY k.id_kelas, k.nama_kelas, k.komp_keahlian ORDER BY k.id_kelas");
?>
<div class="mb-3"><a class="btn btn-primary" href="?aksi=tambah"><i class="bi bi-plus-lg me-1"></i>Tambah Kelas</a></div>
<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped table-bordered mb-0 align-middle">
      <thead class="table-primary"><tr><th>No</th><th>ID Kelas</th><th>Nama Kelas</th><th>Kompetensi Keahlian</th><th>Jumlah Siswa</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($data as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['id_kelas']) ?></td><td><?= e($r['nama_kelas']) ?></td><td><?= e($r['komp_keahlian']) ?></td>
        <td><span class="badge text-bg-secondary"><?= (int)$r['jml_siswa'] ?></span></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-warning" href="?aksi=edit&id=<?= urlencode($r['id_kelas']) ?>"><i class="bi bi-pencil"></i></a>
          <a class="btn btn-sm btn-danger" href="?aksi=hapus&id=<?= urlencode($r['id_kelas']) ?>" onclick="return confirm('Hapus data ini?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; require 'layout/footer.php'; ?>
