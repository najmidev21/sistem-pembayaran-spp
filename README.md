# Aplikasi Pembayaran SPP (PHP Native + MySQL)

## Instalasi di XAMPP
1. Salin folder `app_spp` ke `C:\xampp\htdocs\app_spp`.
2. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
3. Buka `http://localhost/phpmyadmin` → tab **Import** → pilih `db_spp.sql` → **Go**.
   (Script otomatis membuat database `db_spp`, 6 tabel, relasi, dan data uji coba.)
4. Buka `http://localhost/app_spp`.

## Akun Uji Coba
| Level   | Username     | Password   |
|---------|--------------|------------|
| admin   | admin        | admin123   |
| petugas | budi / siti  | petugas123 |
| siswa   | 0061234501 / 0061234502 (NISN) | siswa123 |

## Struktur Menu (Navbar) & Hak Akses
| Menu                | Admin | Petugas | Siswa |
|---------------------|:-----:|:-------:|:-----:|
| Dashboard (jumlah & daftar siswa lunas / belum lunas) | ✓ | ✓ | ✓ (status sendiri) |
| Data Kelas          | ✓ | – | – |
| Data Siswa (+ tombol Data SPP) | ✓ | – | – |
| Cek Pembayaran      | ✓ | ✓ | ✓ (milik sendiri) |
| Pembayaran (entri transaksi) | ✓ | ✓ | – |
| Detail Pembayaran   | ✓ (bisa hapus) | ✓ | ✓ (milik sendiri) |
| Data Petugas        | ✓ | – | – |

## Struktur File
```
app_spp/
├── db_spp.sql          Database, tabel, relasi, data uji
├── koneksi.php         Koneksi DB + fungsi bantu (prepared statement, escape, dll.)
├── index.php           Login
├── logout.php
├── dashboard.php
├── siswa.php kelas.php spp.php petugas.php   CRUD master data (spp.php dibuka dari Data Siswa)
├── pembayaran.php      Entri transaksi (hitung total & kembalian otomatis)
├── detail_pembayaran.php  Daftar transaksi + bukti bayar per transaksi + cetak
├── cek_pembayaran.php  Status lunas / belum lunas per siswa
├── layout/header.php, footer.php
└── assets/style.css
```

## Aturan Bisnis
- `tb_spp.nominal` = besaran SPP **per bulan**.
- Total bayar = jumlah_bulan × nominal; kembalian = jumlah_bayar − total.
- Status **Sudah Lunas** bila total bulan yang dibayar untuk tahun SPP siswa mencapai 12.
- `batas_pembayaran` = tanggal 10 pada bulan setelah periode yang dibayar.
- Setiap transaksi otomatis memperbarui tabel `cek_pembayaran`.

## Data Uji Coba
- Master (5 baris tiap tabel): `tb_spp`, `tb_kelas`, `tb_petugas`, `tb_siswa`
- Transaksi (2 baris tiap tabel): `tb_pembayaran`, `cek_pembayaran`
