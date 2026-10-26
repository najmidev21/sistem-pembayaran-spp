<?php
/*
 * petugas.php = CRUD akun login (khusus admin).
 * semua akun (admin, petugas, siswa) disimpen di tb_petugas, bedanya di kolom level.
 * password disimpen pake MD5, pas edit kalo kolom password dikosongin berarti password lama ga diganti.
 */
require 'koneksi.php';
wajib_login(['admin']);
$title = 'Data Petugas';
$aksi = $_GET['aksi'] ?? 'list';
$levels = ['admin', 'petugas', 'siswa'];

// ===== proses simpan =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = trim($_POST['id_petugas']);
    $user = trim($_POST['username']);
    $nama = trim($_POST['nama_petugas']);
    // level dicek dulu biar ga bisa diisi aneh2 lewat inspect element
    $level = in_array($_POST['level'], $levels, true) ? $_POST['level'] : 'petugas';
    $pass = $_POST['password'] ?? '';
    try {
        if ($_POST['mode'] === 'tambah') {
            q($conn, "INSERT INTO tb_petugas (id_petugas, username, password, nama_petugas, level) VALUES (?,?,MD5(?),?,?)",
              'sssss', [$id, $user, $pass, $nama, $level]);
            flash('Data petugas berhasil ditambahkan.');
        } elseif ($pass !== '') {
            // password diisi -> sekalian diupdate dalam 1 query aja
            q($conn, "UPDATE tb_petugas SET username=?, nama_petugas=?, level=?, password=MD5(?) WHERE id_petugas=?",
              'sssss', [$user, $nama, $level, $pass, $id]);
            flash('Data petugas berhasil diubah.');
        } else {
            q($conn, "UPDATE tb_petugas SET username=?, nama_petugas=?, level=? WHERE id_petugas=?", 'ssss', [$user, $nama, $level, $id]);
            flash('Data petugas berhasil diubah.');
        }
    } catch (mysqli_sql_exception $ex) {
        flash('Gagal menyimpan (username mungkin sudah dipakai).', 'err');
    }
    redirect('petugas.php');
}

// ===== proses hapus, akun yg lagi dipake login ga boleh dihapus =====
if ($aksi === 'hapus') {
    if (($_GET['id'] ?? '') === $_SESSION['id_petugas']) {
        flash('Tidak bisa menghapus akun yang sedang login.', 'err');
    } else {
        q($conn, "DELETE FROM tb_petugas WHERE id_petugas=?", 's', [$_GET['id']]);
        flash('Data petugas berhasil dihapus.');
    }
    redirect('petugas.php');
}

// data buat form edit diambil sebelum header, biar kalo id-nya ga ada bisa langsung di-redirect
if ($aksi === 'edit') {
    $r = row($conn, "SELECT id_petugas, username, nama_petugas, level FROM tb_petugas WHERE id_petugas=?", 's', [$_GET['id'] ?? '']);
    if (!$r) { flash('Data petugas tidak ditemukan.', 'err'); redirect('petugas.php'); }
}

require 'layout/header.php';

if ($aksi === 'tambah' || $aksi === 'edit'):
    if ($aksi === 'tambah') $r = ['id_petugas' => next_id($conn, 'tb_petugas', 'id_petugas', 'PTG'), 'username' => '', 'nama_petugas' => '', 'level' => 'petugas'];
?>
<div class="card shadow-sm border-0" style="max-width:640px">
  <div class="card-header bg-body fw-semibold"><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?> Petugas</div>
  <div class="card-body">
    <form method="post" class="row g-3">
      <input type="hidden" name="mode" value="<?= e($aksi) ?>">
      <div class="col-md-4">
        <label class="form-label">ID Petugas</label>
        <input class="form-control" name="id_petugas" value="<?= e($r['id_petugas']) ?>" readonly>
      </div>
      <div class="col-md-8">
        <label class="form-label">Username</label>
        <input class="form-control" name="username" value="<?= e($r['username']) ?>" maxlength="25" required>
      </div>
      <div class="col-12">
        <label class="form-label">Password</label>
        <input type="password" class="form-control" name="password" <?= $aksi === 'tambah' ? 'required' : 'placeholder="Kosongkan jika tidak diubah"' ?>>
      </div>
      <div class="col-md-8">
        <label class="form-label">Nama Petugas</label>
        <input class="form-control" name="nama_petugas" value="<?= e($r['nama_petugas']) ?>" maxlength="35" required>
      </div>
      <div class="col-md-4">
        <label class="form-label">Level</label>
        <select class="form-select" name="level">
          <?php foreach ($levels as $l): ?><option <?= $r['level'] === $l ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <div class="form-text mb-2"><i class="bi bi-info-circle me-1"></i>Untuk akun level <b>siswa</b>, isi username dengan NISN siswa.</div>
        <button class="btn btn-primary"><i class="bi bi-save me-1"></i>Simpan</button>
        <a class="btn btn-secondary" href="petugas.php">Batal</a>
      </div>
    </form>
  </div>
</div>
<?php else:
    // password sengaja ga di-SELECT, ga ada gunanya ditampilin
    $data = rows($conn, "SELECT id_petugas, username, nama_petugas, level FROM tb_petugas ORDER BY id_petugas");
    $warna = ['admin' => 'danger', 'petugas' => 'primary', 'siswa' => 'success'];
?>
<div class="mb-3"><a class="btn btn-primary" href="?aksi=tambah"><i class="bi bi-plus-lg me-1"></i>Tambah Petugas</a></div>
<div class="card shadow-sm border-0">
  <div class="table-responsive">
    <table class="table table-hover table-striped table-bordered mb-0 align-middle">
      <thead class="table-primary"><tr><th>No</th><th>ID</th><th>Username</th><th>Nama Petugas</th><th>Level</th><th>Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($data as $i => $r): ?>
      <tr>
        <td><?= $i + 1 ?></td><td><?= e($r['id_petugas']) ?></td><td><?= e($r['username']) ?></td>
        <td><?= e($r['nama_petugas']) ?></td>
        <td><span class="badge text-bg-<?= $warna[$r['level']] ?? 'secondary' ?>"><?= e($r['level']) ?></span></td>
        <td class="text-nowrap">
          <a class="btn btn-sm btn-warning" href="?aksi=edit&id=<?= urlencode($r['id_petugas']) ?>"><i class="bi bi-pencil"></i></a>
          <a class="btn btn-sm btn-danger" href="?aksi=hapus&id=<?= urlencode($r['id_petugas']) ?>" onclick="return confirm('Hapus data ini?')"><i class="bi bi-trash"></i></a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; require 'layout/footer.php'; ?>
