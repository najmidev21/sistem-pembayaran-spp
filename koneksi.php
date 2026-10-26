<?php
/*
 * koneksi.php
 * file ini tuh "jantung"nya aplikasi, isinya koneksi ke database + fungsi2 bantu
 * yang dipake di hampir semua halaman. jadi tiap halaman tinggal require file ini aja.
 */
session_start();

// biar kalo query error langsung dilempar exception, jadi bisa ditangkep pake try catch
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// konek ke db, cukup sekali aja di sini trus $conn dipake ulang di semua halaman
$conn = new mysqli('localhost', 'root', '', 'db_spp');
$conn->set_charset('utf8mb4');

// fungsi e() ini buat ngamanin output ke html, biar kalo ada yg iseng masukin <script> ga kejalan (anti XSS)
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ngubah angka biasa jadi format rupiah, misal 300000 jadi "Rp 300.000"
function rupiah($n) { return 'Rp ' . number_format((float)$n, 0, ',', '.'); }

// ngubah format tanggal dari database (Y-m-d) jadi d-m-Y biar lebih enak dibaca, kalo kosong ya tampil "-"
function tgl($d) { return $d ? date('d-m-Y', strtotime($d)) : '-'; }

/*
 * q() = fungsi query utama.
 * caranya: sql di-prepare dulu, trus parameternya di-bind sesuai tipe ('s' string, 'i' integer),
 * baru dieksekusi. jadi nilai dari user ga pernah ditempel langsung ke sql -> aman dari sql injection.
 * fungsi ini balikin statement-nya, jadi bisa dipake buat insert/update/delete juga.
 */
function q($conn, $sql, $types = '', $params = []) {
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    return $st;
}

// sama kaya q() tapi langsung ambil semua barisnya jadi array, buat nampilin tabel
function rows($conn, $sql, $types = '', $params = []) {
    return q($conn, $sql, $types, $params)->get_result()->fetch_all(MYSQLI_ASSOC);
}

// kalo cuma butuh 1 baris aja (misal ambil data buat form edit) pake ini
function row($conn, $sql, $types = '', $params = []) {
    return q($conn, $sql, $types, $params)->get_result()->fetch_assoc();
}

/*
 * wajib_login() dipanggil di paling atas tiap halaman.
 * pertama ngecek udah login belum, kalo belum ya dilempar ke halaman login.
 * kalo $levels diisi (misal ['admin']) berarti halaman itu cuma boleh dibuka level tsb,
 * selain itu dibalikin ke dashboard + dikasih pesan.
 */
function wajib_login($levels = []) {
    if (empty($_SESSION['login'])) { header('Location: index.php'); exit; }
    if ($levels && !in_array($_SESSION['level'], $levels, true)) {
        flash('Anda tidak memiliki akses ke halaman tersebut.', 'err');
        header('Location: dashboard.php'); exit;
    }
}

/*
 * next_id() buat bikin id otomatis kaya PAY001, PAY002 dst.
 * caranya ambil angka paling gede di belakang prefix (pake SUBSTRING + CAST),
 * terus ditambah 1 dan di-pad nol di depan biar jadi 3 digit.
 * cuma 1 query MAX aja, ga perlu ambil semua data trus diitung di php.
 * $table sama $col ga dari input user ya, jadi aman ditempel langsung.
 */
function next_id($conn, $table, $col, $prefix) {
    $len = strlen($prefix) + 1;
    $r = row($conn, "SELECT MAX(CAST(SUBSTRING($col, $len) AS UNSIGNED)) AS m FROM $table WHERE $col LIKE ?", 's', [$prefix . '%']);
    return $prefix . str_pad(((int)$r['m']) + 1, 3, '0', STR_PAD_LEFT);
}

/*
 * flash() ini fungsi dua arah:
 * - kalo dipanggil pake pesan -> pesannya disimpen dulu di session (soalnya abis itu di-redirect)
 * - kalo dipanggil tanpa parameter -> pesan di session ditampilin pake alert bootstrap trus dihapus
 * jadi pesannya cuma muncul sekali aja abis aksi tambah/edit/hapus.
 */
function flash($msg = null, $type = 'ok') {
    if ($msg !== null) { $_SESSION['flash'] = [$msg, $type]; return; }
    if (!empty($_SESSION['flash'])) {
        [$m, $t] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        $cls  = $t === 'err' ? 'danger' : 'success';
        $icon = $t === 'err' ? 'exclamation-triangle-fill' : 'check-circle-fill';
        echo "<div class='alert alert-$cls alert-dismissible fade show' role='alert'>"
           . "<i class='bi bi-$icon me-2'></i>" . e($m)
           . "<button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
    }
}

// pindah halaman, jangan lupa exit biar kode di bawahnya ga ikut jalan
function redirect($url) { header("Location: $url"); exit; }

/*
 * sync_cek() buat nyamain isi tabel cek_pembayaran sama data transaksi asli di tb_pembayaran.
 * dipanggil tiap abis ada transaksi baru / transaksi dihapus.
 *
 * dulu caranya ambil data siswa, tgl terakhir, jumlah bulan satu2 (4 query per siswa),
 * kalo mau refresh semua siswa berarti 4 x jumlah siswa query, lemot kalo siswanya banyak.
 * sekarang diganti jadi cuma 2 query aja:
 *   1. INSERT ... SELECT ... GROUP BY -> langsung ngitung tgl terakhir + total bulan + status
 *      di database, kalo nisn udah ada tinggal di-update (ON DUPLICATE KEY UPDATE)
 *   2. DELETE baris cek yang siswanya udah ga punya transaksi sama sekali
 * kalo $nisn diisi -> cuma siswa itu doang, kalo null -> semua siswa sekaligus.
 */
function sync_cek($conn, $nisn = null) {
    $where = $nisn === null ? '' : 'WHERE s.nisn = ?';
    $types = $nisn === null ? '' : 's';
    $param = $nisn === null ? [] : [$nisn];

    // yg diitung cuma bulan buat spp yg lagi dipake siswa (p.id_spp = s.id_spp), sesuai aturan lunas 12 bulan
    q($conn, "INSERT INTO cek_pembayaran (nisn, tgl_terakhir_bayar, tgl_sekarang, status_pembayaran, jumlah_bulan, nama, no_telp)
              SELECT s.nisn, MAX(p.tgl_bayar), CURDATE(),
                     IF(SUM(IF(p.id_spp = s.id_spp, CAST(p.jumlah_bulan AS UNSIGNED), 0)) >= 12, 'Sudah Lunas', 'Belum Lunas'),
                     SUM(IF(p.id_spp = s.id_spp, CAST(p.jumlah_bulan AS UNSIGNED), 0)),
                     s.nama, s.no_telp
              FROM tb_siswa s JOIN tb_pembayaran p ON p.nisn = s.nisn
              $where
              GROUP BY s.nisn, s.nama, s.no_telp, s.id_spp
              ON DUPLICATE KEY UPDATE tgl_terakhir_bayar=VALUES(tgl_terakhir_bayar), tgl_sekarang=CURDATE(),
                status_pembayaran=VALUES(status_pembayaran), jumlah_bulan=VALUES(jumlah_bulan),
                nama=VALUES(nama), no_telp=VALUES(no_telp)", $types, $param);

    // hapus data cek yg udah ga ada transaksinya (misal transaksinya dihapus admin)
    q($conn, "DELETE FROM cek_pembayaran
              WHERE " . ($nisn === null ? '' : 'nisn = ? AND ') . "
                    NOT EXISTS (SELECT 1 FROM tb_pembayaran p WHERE p.nisn = cek_pembayaran.nisn)", $types, $param);
}
