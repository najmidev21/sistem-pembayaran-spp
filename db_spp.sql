-- =====================================================
-- Database Aplikasi Pembayaran SPP
-- Sesuai diagram: tb_spp, tb_petugas, tb_kelas, tb_siswa,
--                 tb_pembayaran, cek_pembayaran
-- =====================================================
DROP DATABASE IF EXISTS db_spp;
CREATE DATABASE db_spp CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE db_spp;

-- ---------- MASTER DATA ----------
CREATE TABLE tb_spp (
  id_spp   VARCHAR(11) NOT NULL,
  tahun    INT(11)     NOT NULL,
  nominal  VARCHAR(40) NOT NULL,
  PRIMARY KEY (id_spp)
) ENGINE=InnoDB;

CREATE TABLE tb_petugas (
  id_petugas   VARCHAR(11) NOT NULL,
  username     VARCHAR(25) NOT NULL,
  password     VARCHAR(32) NOT NULL,          -- MD5 (32 karakter)
  nama_petugas VARCHAR(35) NOT NULL,
  level        ENUM('admin','petugas','siswa') NOT NULL,
  PRIMARY KEY (id_petugas),
  UNIQUE KEY uk_username (username)
) ENGINE=InnoDB;

CREATE TABLE tb_kelas (
  id_kelas      VARCHAR(11) NOT NULL,
  nama_kelas    VARCHAR(10) NOT NULL,
  komp_keahlian VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_kelas),
  UNIQUE KEY uk_nama_kelas (nama_kelas)
) ENGINE=InnoDB;

CREATE TABLE tb_siswa (
  nisn       VARCHAR(10) NOT NULL,
  nis        VARCHAR(8)  NOT NULL,
  nama       VARCHAR(50) NOT NULL,
  id_kelas   VARCHAR(11) NOT NULL,
  nama_kelas VARCHAR(10) NOT NULL,
  alamat     TEXT        NOT NULL,
  no_telp    VARCHAR(13) NOT NULL,
  id_spp     VARCHAR(40) NOT NULL,
  PRIMARY KEY (nisn),
  KEY idx_siswa_nama (nama),
  KEY idx_siswa_telp (no_telp),
  KEY idx_siswa_spp (id_spp),   -- tanpa FK, sesuai diagram (tb_spp berdiri sendiri)
  CONSTRAINT fk_siswa_kelas FOREIGN KEY (id_kelas) REFERENCES tb_kelas(id_kelas)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT fk_siswa_nama_kelas FOREIGN KEY (nama_kelas) REFERENCES tb_kelas(nama_kelas)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ---------- TRANSAKSI ----------
CREATE TABLE tb_pembayaran (
  id_pembayaran      VARCHAR(11)  NOT NULL,
  status             ENUM('Belum Lunas','Sudah Lunas') NOT NULL,
  nisn               VARCHAR(10)  NOT NULL,
  tgl_bayar          DATE         NOT NULL,
  tgl_terakhir_bayar DATE         NOT NULL,
  batas_pembayaran   DATE         NOT NULL,
  jumlah_bulan       VARCHAR(10)  NOT NULL,
  id_spp             VARCHAR(40)  NOT NULL,
  nominal_bayar      VARCHAR(100) NOT NULL,
  jumlah_bayar       VARCHAR(40)  NOT NULL,
  kembalian          VARCHAR(100) NOT NULL,
  PRIMARY KEY (id_pembayaran),
  CONSTRAINT fk_bayar_siswa FOREIGN KEY (nisn) REFERENCES tb_siswa(nisn)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  KEY idx_bayar_spp (id_spp),   -- tanpa FK, sesuai diagram
  -- index gabungan nisn+id_spp, dipake pas ngitung total bulan yg udah dibayar per siswa (SUM ... GROUP BY nisn, id_spp)
  KEY idx_bayar_nisn_spp (nisn, id_spp),
  -- buat ORDER BY tgl_bayar DESC di riwayat & dashboard biar ga sorting full tabel
  KEY idx_bayar_tgl (tgl_bayar)
) ENGINE=InnoDB;

CREATE TABLE cek_pembayaran (
  nisn               VARCHAR(10) NOT NULL,
  tgl_terakhir_bayar DATE        NOT NULL,
  tgl_sekarang       DATE        NOT NULL,
  status_pembayaran  ENUM('Belum Lunas','Sudah Lunas') NOT NULL,
  jumlah_bulan       VARCHAR(5)  NOT NULL,
  nama               VARCHAR(50) NOT NULL,
  no_telp            VARCHAR(13) NOT NULL,
  PRIMARY KEY (nisn),
  CONSTRAINT fk_cek_siswa FOREIGN KEY (nisn) REFERENCES tb_siswa(nisn)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cek_nama FOREIGN KEY (nama) REFERENCES tb_siswa(nama)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_cek_telp FOREIGN KEY (no_telp) REFERENCES tb_siswa(no_telp)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- =====================================================
-- DATA UJI COBA: 5 baris master, 2 baris transaksi
-- =====================================================
INSERT INTO tb_spp (id_spp, tahun, nominal) VALUES
('SPP001', 2022, '200000'),
('SPP002', 2023, '225000'),
('SPP003', 2024, '250000'),
('SPP004', 2025, '275000'),
('SPP005', 2026, '300000');

-- Password (MD5): admin=admin123, petugas=petugas123, siswa=siswa123
-- Akun level siswa memakai NISN sebagai username
INSERT INTO tb_petugas (id_petugas, username, password, nama_petugas, level) VALUES
('PTG001', 'admin',      MD5('admin123'),   'Administrator',  'admin'),
('PTG002', 'budi',       MD5('petugas123'), 'Budi Santoso',   'petugas'),
('PTG003', 'siti',       MD5('petugas123'), 'Siti Aminah',    'petugas'),
('PTG004', '0061234501', MD5('siswa123'),   'Andi Pratama',   'siswa'),
('PTG005', '0061234502', MD5('siswa123'),   'Bella Safitri',  'siswa');

INSERT INTO tb_kelas (id_kelas, nama_kelas, komp_keahlian) VALUES
('KLS001', 'X RPL 1',   'Rekayasa Perangkat Lunak'),
('KLS002', 'X TKJ 1',   'Teknik Komputer dan Jaringan'),
('KLS003', 'XI RPL 1',  'Rekayasa Perangkat Lunak'),
('KLS004', 'XI MM 1',   'Multimedia'),
('KLS005', 'XII RPL 1', 'Rekayasa Perangkat Lunak');

INSERT INTO tb_siswa (nisn, nis, nama, id_kelas, nama_kelas, alamat, no_telp, id_spp) VALUES
('0061234501', '22010001', 'Andi Pratama',   'KLS001', 'X RPL 1',   'Jl. Merdeka No. 10, Pamulang',     '081234567801', 'SPP005'),
('0061234502', '22010002', 'Bella Safitri',  'KLS002', 'X TKJ 1',   'Jl. Pajajaran No. 5, Tangsel',     '081234567802', 'SPP005'),
('0061234503', '22010003', 'Citra Lestari',  'KLS003', 'XI RPL 1',  'Jl. Siliwangi No. 21, Pamulang',   '081234567803', 'SPP004'),
('0061234504', '22010004', 'Dimas Saputra',  'KLS004', 'XI MM 1',   'Jl. Benda Barat No. 7, Pamulang',  '081234567804', 'SPP004'),
('0061234505', '22010005', 'Eka Ramadhan',   'KLS005', 'XII RPL 1', 'Jl. Raya Puspiptek No. 3, Tangsel','081234567805', 'SPP003');

-- Transaksi (2 baris)
INSERT INTO tb_pembayaran (id_pembayaran, status, nisn, tgl_bayar, tgl_terakhir_bayar, batas_pembayaran,
  jumlah_bulan, id_spp, nominal_bayar, jumlah_bayar, kembalian) VALUES
('PAY001', 'Sudah Lunas', '0061234501', '2026-10-05', '2026-10-05', '2027-10-10', '12', 'SPP005', '3600000', '3600000', '0'),
('PAY002', 'Belum Lunas', '0061234502', '2026-10-20', '2026-10-20', '2027-01-10', '3',  'SPP005', '900000',  '1000000', '100000');

INSERT INTO cek_pembayaran (nisn, tgl_terakhir_bayar, tgl_sekarang, status_pembayaran, jumlah_bulan, nama, no_telp) VALUES
('0061234501', '2026-10-05', '2026-10-26', 'Sudah Lunas', '12', 'Andi Pratama',  '081234567801'),
('0061234502', '2026-10-20', '2026-10-26', 'Belum Lunas', '3',  'Bella Safitri', '081234567802');
