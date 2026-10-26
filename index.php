<?php
/*
 * index.php = halaman login.
 * alurnya: user ngisi username + password -> dicek ke tb_petugas (password disimpen pake MD5)
 * -> kalo cocok, data user disimpen ke session trus diarahin ke dashboard.
 * siswa juga login di sini, username-nya pake NISN.
 */
require 'koneksi.php';
if (!empty($_SESSION['login'])) redirect('dashboard.php'); // udah login ngapain login lagi

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';
    // cuma ambil kolom yg dibutuhin aja buat session, password ga usah ikut diambil
    $user = row($conn, "SELECT id_petugas, username, nama_petugas, level FROM tb_petugas WHERE username = ? AND password = MD5(?)", 'ss', [$u, $p]);
    if ($user) {
        session_regenerate_id(true); // ganti id session biar ga kena session fixation
        $_SESSION['login']      = true;
        $_SESSION['id_petugas'] = $user['id_petugas'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['nama']       = $user['nama_petugas'];
        $_SESSION['level']      = $user['level'];
        redirect('dashboard.php');
    }
    $err = 'Username atau password salah.';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - Aplikasi SPP</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="login-bg d-flex align-items-center justify-content-center p-3">
  <div class="card shadow-lg border-0" style="width:100%;max-width:380px">
    <div class="card-body p-4">
      <div class="text-center mb-4">
        <i class="bi bi-wallet2 text-primary" style="font-size:3rem"></i>
        <h4 class="fw-bold mb-1">Aplikasi SPP</h4>
        <p class="text-secondary small mb-0">Silakan login untuk melanjutkan</p>
      </div>
      <?php if ($err): ?><div class="alert alert-danger py-2"><i class="bi bi-x-circle me-1"></i><?= e($err) ?></div><?php endif; ?>
      <form method="post">
        <div class="form-floating mb-3">
          <input class="form-control" id="username" name="username" placeholder="Username" required autofocus value="<?= e($_POST['username'] ?? '') ?>">
          <label for="username">Username / NISN</label>
        </div>
        <div class="form-floating mb-3">
          <input type="password" class="form-control" id="password" name="password" placeholder="Password" required>
          <label for="password">Password</label>
        </div>
        <button class="btn btn-primary w-100 py-2"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
      </form>
    </div>
  </div>
</div>
</body>
</html>
