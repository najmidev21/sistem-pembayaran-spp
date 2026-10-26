<?php
/*
 * siswa.php = CRUD data siswa (khusus admin).
 * 1 file ini ngurusin semuanya, dibedain pake ?aksi= di url:
 *   list (default) -> tabel siswa + pencarian
 *   tambah / edit  -> form
 *   hapus          -> hapus data
 * proses simpan form masuknya lewat POST.
 */
require 'koneksi.php';
wajib_login(['admin']);
$title = 'Data Siswa';
$aksi = $_GET['aksi'] ?? 'list';

// ===== proses simpan (tambah / edit) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // nama_kelas di tb_siswa itu salinan dari tb_kelas (sesuai diagram), jadi diambil dulu dari id_kelas yg dipilih
    $kelas = row($conn, "SELECT nama_kelas FROM tb_kelas WHERE id_kelas=?", 's', [$_POST['id_kelas']]);
    // urutannya disamain sama urutan tanda tanya di query bawah, nisn ditaro paling akhir biar bisa dipake buat WHERE pas update
    $d = [
        trim($_POST['nis']), trim($_POST['nama']), $_POST['id_kelas'], $kelas['nama_kelas'] ?? '',
        trim($_POST['alamat']), trim($_POST['no_telp']), $_POST['id_spp'], trim($_POST['nisn']),
    ];
    try {
        if ($_POST['mode'] === 'tambah') {
            q($conn, "INSERT INTO tb_siswa (nis, nama, id_kelas, nama_kelas, alamat, no_telp, id_spp, nisn) VALUES (?,?,?,?,?,?,?,?)", 'ssssssss', $d);
            flash('Data siswa berhasil ditambahkan.');
        } else {
            q($conn, "UPDATE tb_siswa SET nis=?, nama=?, id_kelas=?, nama_kelas=?, alamat=?, no_telp=?, id_spp=? WHERE nisn=?", 'ssssssss', $d);
            // kalo spp siswanya diganti, status lunas di cek_pembayaran harus diitung ulang
            sync_cek($conn, $d[7]);
            flash('Data siswa berhasil diubah.');
        }
    } catch (mysqli_sql_exception $ex) {
        flash('Gagal menyimpan (NISN mungkin sudah terdaftar).', 'err');
    }
    redirect('siswa.php');
}

// ===== proses hapus =====
// kalo siswanya udah punya transaksi, foreign key bakal nolak, jadi errornya ditangkep aja
if ($aksi === 'hapus') {
    try {
        q($conn, "DELETE FROM tb_siswa WHERE nisn=?", 's', [$_GET['id'] ?? '']);
        flash('Data siswa berhasil dihapus.');
    } catch (mysqli_sql_exception $ex) {
        flash('Gagal menghapus: siswa sudah memiliki transaksi pembayaran.', 'err');
    }
    redirect('siswa.php');
}

// data buat form edit diambil sebelum header, biar kalo id-nya ga ada bisa langsung di-redirect
if ($aksi === 'edit') {
    $r = row($conn, "SELECT nisn, nis, nama, id_kelas, alamat, no_telp, id_spp FROM tb_siswa WHERE nisn=?", 's', [$_GET['id'] ?? '']);
    if (!$r) { flash('Data siswa tidak ditemukan.', 'err'); redirect('siswa.php'); }
}

require 'layout/header.php';

// ===== tampilan form =====
if ($aksi === 'tambah' || $aksi === 'edit'):
    if ($aksi === 'tambah') $r = ['nisn' => '', 'nis' => '', 'nama' => '', 'id_kelas' => '', 'alamat' => '', 'no_telp' => '', 'id_spp' => ''];
    // buat isi dropdown kelas sama spp
    $kelas = rows($conn, "SELECT id_kelas, nama_kelas, komp_keahlian FROM tb_kelas ORDER BY nama_kelas");
    $spp = rows($conn, "SELECT id_spp, tahun, nominal FROM tb_spp ORDER BY tahun DESC");
?>
<div class="card shadow-sm border-0" style="max-width:760px">
  <div class="card-header bg-body fw-semibold"><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?> Siswa</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="mode" value="<?= e($aksi) ?>">
      <div class="col-md-6">
        <label class="form-label">NISN</label>
        <input class="form-control" name="nisn" value="<?= e($r['nisn']) ?>" maxlength="10" pattern="\d{10}" title="10 digit angka" required <?= $aksi === 'edit' ? 'readonly' : '' ?>>
      </div>
      <div class="col-md-6">
        <label class="form-label">NIS</label>
        <input class="form-control" name="nis" value="<?= e($r['nis']) ?>" maxlength="8" required>
      </div>
      <div class="col-12">
        <label class="form-label">Nama</label>
        <input class="form-control" name="nama" value="<?= e($r['nama']) ?>" maxlength="50" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Kelas</label>
        <select class="form-select" name="id_kelas" required>
          <option value="">- Pilih Kelas -</option>
          <?php foreach ($kelas as $k): ?>
            <option value="<?= e($k['id_kelas']) ?>" <?= $r['id_kelas'] === $k['id_kelas'] ? 'selected' : '' ?>><?= e($k['nama_kelas'] . ' - ' . $k['komp_keahlian']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">No. Telp</label>
        <input class="form-control" name="no_telp" value="<?= e($r['no_telp']) ?>" maxlength="13" required>
      </div>
      <div class="col-12">
        <label class="form-label">Alamat</label>
        <textarea class="form-control" name="alamat" rows="3" required><?= e($r['alamat']) ?></textarea>
      </div>
      <div class="col-12">
        <label class="form-label">SPP</label>
        <select class="form-select" name="id_spp" required>
          <option value="">- Pilih SPP -</option>
          <?php foreach ($spp as $s): ?>
            <option value="<?= e($s['id_spp']) ?>" <?= $r['id_spp'] === $s['id_spp'] ? 'selected' : '' ?>><?= e($s['tahun']) ?> - <?= rupiah($s['nominal']) ?>/bulan</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a class="btn btn-secondary" href="siswa.php">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php else:
    // ===== tampilan list =====
    // kalo kolom cari kosong ga usah pake WHERE LIKE, soalnya LIKE '%..%' itu ga bisa pake index jadi lebih berat
    $cari = trim($_GET['cari'] ?? '');
    $sql = "SELECT s.nisn, s.nis, s.nama, s.nama_kelas, s.alamat, s.no_telp, p.tahun, p.nominal
            FROM tb_siswa s JOIN tb_spp p ON p.id_spp = s.id_spp";
    if ($cari === '') {
        $data = rows($conn, "$sql ORDER BY s.nama");
    } else {
        $like = "%$cari%";
        $data = rows($conn, "$sql WHERE s.nisn LIKE ? OR s.nama LIKE ? OR s.nama_kelas LIKE ? ORDER BY s.nama", 'sss', [$like, $like, $like]);
    }
?>
<div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
  <div class="d-flex gap-2">
    <a class="btn btn-primary" href="?aksi=tambah"><i class="bi bi-plus-lg me-1"></i>Tambah Siswa</a>
    <!-- data spp ga ada di menu navbar (sesuai soal), jadi kelolanya dari sini -->
    <a class="btn btn-outline-primary" href="spp.php"><i class="bi bi-cash-stack me-1"></i>Data SPP</a>
  </div>
  <form method="get" class="d-flex gap-2">
    <input class="form-control" name="cari" placeholder="Cari NISN / nama / kelas" value="<?= e($cari) ?>">
    <button class="btn btn-outline-secondary"><i class="bi bi-search"></i></button>
  </form>
</div>
<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped table-bordered mb-0 align-middle">
      <thead class="table-primary"><tr><th>No</th><th>NISN</th><th>NIS</th><th>Nama</th><th>Kelas</th><th>Alamat</th><th>No. Telp</th><th>SPP</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php if (!$data): ?><tr><td colspan="9" class="text-center text-secondary">Data tidak ditemukan.</td></tr><?php endif; ?>
      <?php foreach ($data as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['nisn']) ?></td><td><?= e($r['nis']) ?></td><td><?= e($r['nama']) ?></td>
        <td><?= e($r['nama_kelas']) ?></td><td><?= e($r['alamat']) ?></td><td><?= e($r['no_telp']) ?></td>
        <td><?= e($r['tahun']) ?><br><small class="text-secondary"><?= rupiah($r['nominal']) ?></small></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-warning" href="?aksi=edit&id=<?= urlencode($r['nisn']) ?>"><i class="bi bi-pencil"></i></a>
          <a class="btn btn-sm btn-danger" href="?aksi=hapus&id=<?= urlencode($r['nisn']) ?>" onclick="return confirm('Hapus data ini?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; require 'layout/footer.php'; ?>
