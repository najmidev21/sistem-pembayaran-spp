<?php
/*
 * pembayaran.php = entri transaksi pembayaran spp (admin & petugas).
 *
 * alur algoritmanya kurang lebih gini:
 *  1. pilih siswa -> ketauan nominal spp per bulan + udah bayar berapa bulan
 *  2. isi jumlah bulan yg mau dibayar + uang yg dikasih
 *  3. total = jumlah bulan x nominal, kembalian = uang dikasih - total
 *  4. dicek dulu: udah lunas belum, bulannya ngelebihin sisa ga, uangnya kurang ga
 *  5. kalo aman, simpen ke tb_pembayaran + update tabel cek_pembayaran
 * itungan total & kembalian juga ada versi javascript-nya di bawah, biar langsung keliatan pas ngetik.
 * tapi yg dipake buat nyimpen tetep itungan php ya, soalnya js bisa diakalin dari browser.
 */
require 'koneksi.php';
wajib_login(['admin', 'petugas']);
$title = 'Entri Pembayaran SPP';

// subquery ini dipake 2 kali (pas simpan & pas nampilin dropdown), isinya total bulan yg udah dibayar
// per siswa per spp. dihitung sekali jalan pake GROUP BY, jadi ga perlu query satu2 tiap siswa
$sqlSiswa = "SELECT s.nisn, s.nama, s.nama_kelas, s.id_spp, p.tahun, p.nominal, COALESCE(b.sudah, 0) AS sudah
             FROM tb_siswa s
             JOIN tb_spp p ON p.id_spp = s.id_spp
             LEFT JOIN (SELECT nisn, id_spp, SUM(CAST(jumlah_bulan AS UNSIGNED)) AS sudah
                        FROM tb_pembayaran GROUP BY nisn, id_spp) b
                    ON b.nisn = s.nisn AND b.id_spp = s.id_spp";

// ===== proses simpan pembayaran =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nisn  = $_POST['nisn'] ?? '';
    $bulan = max(1, min(12, (int)($_POST['jumlah_bulan'] ?? 1))); // dijaga biar cuma 1-12
    $bayar = (int)preg_replace('/\D/', '', $_POST['jumlah_bayar'] ?? '');
    $tgl   = $_POST['tgl_bayar'] ?? '';
    // tanggal dicek formatnya, kalo ngaco (misal diubah lewat inspect element) pake tanggal hari ini
    $dt = DateTime::createFromFormat('Y-m-d', $tgl);
    if (!$dt || $dt->format('Y-m-d') !== $tgl) $tgl = date('Y-m-d');

    // data siswa, nominal, sama bulan yg udah dibayar diambil sekaligus dalam 1 query
    $s = row($conn, "$sqlSiswa WHERE s.nisn = ?", 's', [$nisn]);
    if (!$s) { flash('Siswa tidak ditemukan.', 'err'); redirect('pembayaran.php'); }

    $sudah   = (int)$s['sudah'];
    $sisa    = 12 - $sudah;
    $nominal = $bulan * (int)$s['nominal'];

    // validasi2 dulu sebelum disimpen
    if ($sisa <= 0)       { flash('SPP siswa ini untuk tahun tersebut sudah lunas.', 'err'); redirect('pembayaran.php'); }
    if ($bulan > $sisa)   { flash("Jumlah bulan melebihi sisa tagihan ($sisa bulan).", 'err'); redirect('pembayaran.php'); }
    if ($bayar < $nominal){ flash('Jumlah bayar kurang dari nominal yang harus dibayar.', 'err'); redirect('pembayaran.php'); }

    $status  = ($sudah + $bulan) >= 12 ? 'Sudah Lunas' : 'Belum Lunas';
    // batas bayar berikutnya = tgl 10 di bulan setelah periode yg dibayar
    $batas   = date('Y-m-10', strtotime("+$bulan month", strtotime($tgl)));
    $kembali = $bayar - $nominal;

    // insert transaksi + update cek_pembayaran dibungkus transaction,
    // jadi kalo salah satu gagal semuanya dibatalin (rollback), data ga setengah2
    $conn->begin_transaction();
    try {
        $id = next_id($conn, 'tb_pembayaran', 'id_pembayaran', 'PAY');
        q($conn, "INSERT INTO tb_pembayaran (id_pembayaran, status, nisn, tgl_bayar, tgl_terakhir_bayar, batas_pembayaran,
                  jumlah_bulan, id_spp, nominal_bayar, jumlah_bayar, kembalian) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
          'sssssssssss', [$id, $status, $nisn, $tgl, $tgl, $batas, (string)$bulan, $s['id_spp'], (string)$nominal, (string)$bayar, (string)$kembali]);
        sync_cek($conn, $nisn);
        $conn->commit();
    } catch (mysqli_sql_exception $ex) {
        $conn->rollback();
        flash('Gagal menyimpan pembayaran, silakan coba lagi.', 'err');
        redirect('pembayaran.php');
    }

    flash("Pembayaran $id berhasil disimpan. Kembalian: " . rupiah($kembali));
    redirect('detail_pembayaran.php?id=' . urlencode($id)); // langsung tampil bukti bayarnya
}

// buat dropdown siswa. dulu tiap siswa dipanggil bulan_dibayar() satu2 (N+1 query),
// sekarang cukup 1 query join doang
$siswa = rows($conn, "$sqlSiswa ORDER BY s.nama");

require 'layout/header.php';
?>
<div class="card shadow-sm border-0" style="max-width:760px">
  <div class="card-header bg-body fw-semibold"><i class="bi bi-cart-plus me-1"></i>Form Pembayaran</div>
  <div class="card-body">
    <form method="post" id="fBayar" class="row g-3">
      <div class="col-md-6">
        <label class="form-label">ID Pembayaran</label>
        <input class="form-control" value="<?= e(next_id($conn, 'tb_pembayaran', 'id_pembayaran', 'PAY')) ?>" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label">Petugas</label>
        <input class="form-control" value="<?= e($_SESSION['nama']) ?>" readonly>
      </div>
      <div class="col-12">
        <label class="form-label">Siswa</label>
        <!-- data nominal, tahun, sama bulan yg udah dibayar ditaro di data-* biar bisa dibaca js tanpa ajax -->
        <select class="form-select" name="nisn" id="nisn" required>
          <option value="">- Pilih Siswa -</option>
          <?php foreach ($siswa as $s): $lunas = (int)$s['sudah'] >= 12; // yg udah lunas ga bisa dipilih lagi ?>
            <option value="<?= e($s['nisn']) ?>" data-nominal="<?= (int)$s['nominal'] ?>" data-tahun="<?= e($s['tahun']) ?>" data-sudah="<?= (int)$s['sudah'] ?>" <?= $lunas ? 'disabled' : '' ?>>
              <?= e($s['nisn'] . ' - ' . $s['nama'] . ' (' . $s['nama_kelas'] . ')') ?><?= $lunas ? ' - SUDAH LUNAS' : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label">SPP Tahun / Nominal</label>
        <input class="form-control" id="infoSpp" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label">Sudah Dibayar</label>
        <input class="form-control" id="infoSudah" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label">Tanggal Bayar</label>
        <input type="date" class="form-control" name="tgl_bayar" value="<?= date('Y-m-d') ?>" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Jumlah Bulan</label>
        <input type="number" class="form-control" name="jumlah_bulan" id="bulan" min="1" max="12" value="1" required>
      </div>
      <div class="col-md-6">
        <label class="form-label">Total Harus Dibayar</label>
        <input class="form-control fw-bold" id="total" readonly>
      </div>
      <div class="col-md-6">
        <label class="form-label">Jumlah Bayar</label>
        <div class="input-group">
          <span class="input-group-text">Rp</span>
          <input type="number" class="form-control" name="jumlah_bayar" id="bayar" min="0" required>
        </div>
      </div>
      <div class="col-12">
        <label class="form-label">Kembalian</label>
        <input class="form-control fw-bold" id="kembali" readonly>
      </div>
      <div class="col-12">
        <button class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Simpan Pembayaran</button>
        <button type="reset" class="btn btn-secondary">Reset</button>
      </div>
    </form>
  </div>
</div>

<script>
// format angka ke rupiah ala indonesia
const rp = n => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
const $ = id => document.getElementById(id);

// hitung() dipanggil tiap kali siswa/bulan/uang bayar diganti.
// ambil data dari option siswa yg dipilih, trus ngitung total & kembalian, hasilnya langsung ditampilin.
// kalo uangnya kurang, kolom kembalian jadi merah biar petugasnya ngeh
function hitung() {
  const o = $('nisn').selectedOptions[0];
  const nominal = +(o?.dataset.nominal || 0), sudah = +(o?.dataset.sudah || 0);
  const sisa = 12 - sudah;
  $('infoSpp').value = o?.value ? o.dataset.tahun + ' / ' + rp(nominal) + ' per bulan' : '';
  $('infoSudah').value = o?.value ? sudah + ' bulan (sisa ' + sisa + ' bulan)' : '';
  $('bulan').max = Math.max(sisa, 1); // max bulan ngikutin sisa tagihan
  const total = nominal * (+$('bulan').value || 0);
  $('total').value = rp(total);
  const k = (+$('bayar').value || 0) - total;
  $('kembali').value = k >= 0 ? rp(k) : 'Kurang ' + rp(-k);
  $('kembali').classList.toggle('is-invalid', k < 0);
}
['nisn', 'bulan', 'bayar'].forEach(id => $(id).addEventListener('input', hitung));
$('nisn').addEventListener('change', hitung);
// pas tombol reset dipencet, nunggu form ke-reset dulu baru diitung ulang
$('fBayar').addEventListener('reset', () => setTimeout(hitung));
hitung();
</script>
<?php require 'layout/footer.php'; ?>
