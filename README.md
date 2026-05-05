# SIMRS — Studi Kasus Uji Kompetensi Analis Program

> **LSP Teknologi Informasi · Bagian V: Observasi Praktik**  
> Nama: Akmal Taufiqurrahman · Tanggal: 05-05-2026

---

## Daftar Isi

- [Gambaran Umum](#gambaran-umum)
- [Struktur Repositori](#struktur-repositori)
- [Cara Setup & Menjalankan](#cara-setup--menjalankan)
- [Tugas 1 — Analisis & Optimasi Database](#tugas-1--analisis--optimasi-database)
- [Tugas 2 — Implementasi Laporan Dokter PHP Native](#tugas-2--implementasi-laporan-dokter-php-native)
- [Tugas 3 — Debugging & Profiling](#tugas-3--debugging--profiling)
- [Tugas 4 — Unit Test & Integration Test](#tugas-4--unit-test--integration-test)

---

## Gambaran Umum

Repository ini berisi implementasi empat tugas praktik Uji Kompetensi Analis Program menggunakan **PHP 8.2 native (PDO)** dan **MySQL 8.0**, tanpa framework MVC.

| Tugas   | Topik                                  | Unit Kompetensi Utama                             |
| ------- | -------------------------------------- | ------------------------------------------------- |
| Tugas 1 | Analisis skalabilitas & optimasi query | J.620100.002.01, J.620100.020.02                  |
| Tugas 2 | Implementasi fitur PHP native          | J.620100.021.02, J.620100.022.02, J.620100.023.02 |
| Tugas 3 | Debugging & profiling                  | J.620100.025.02, J.620100.031.01, J.620100.032.01 |
| Tugas 4 | Unit test & integration test           | J.620100.033.02, J.620100.034.02                  |

---

## Struktur Repositori

```
simrs/
├── README.md                        ← File ini
│
├── tugas1/
│   └── laporan.md                   ← Laporan EXPLAIN before-after + index SQL
│
├── tugas2/
│   ├── db_connection.php            ← Koneksi PDO (getConnection())
│   ├── DokterRepository.php         ← Query laporan kunjungan dokter
│   └── laporan_dokter.php           ← Controller endpoint GET JSON
│
├── tugas3/
│   ├── profiling_report.md          ← Laporan bug + root cause + optimasi query
│   └── simrs-bug/
│       ├── src/
│       │   ├── TarifCalculator.php  ← Kelas tarif (sudah diperbaiki)
│       │   └── DokterRepository.php ← Query N+1 (sudah dioptimasi)
│       ├── tests/
│       │   └── TarifCalculatorTest.php  ← Test suite PHPUnit (jangan diubah)
│       ├── db_connection.php
│       ├── phpunit.xml
│       └── composer.json
│
└── tugas4/
    ├── tests/
    │   ├── TarifCalculatorTest.php  ← ≥8 unit test + @dataProvider
    │   └── PendaftaranIntegrationTest.php ← ≥3 integration test + rollback
    └── coverage-report/             ← Laporan coverage HTML (≥80%)
```

---

## Cara Setup & Menjalankan

### Prasyarat

- PHP 8.2 + Xdebug 3
- MySQL 8.0 (sudah berjalan)
- Composer
- Apache 2.4 / XAMPP

### 1. Setup Database

```bash
# Masuk ke MySQL CLI
mysql -u root -p

# Import schema + data dummy
mysql -u root -p simrs < setup_db.sql
```

### 2. Konfigurasi Koneksi

Edit `tugas2/db_connection.php` dan `tugas3/simrs-bug/db_connection.php` sesuai konfigurasi lokal:

```php
$host   = '127.0.0.1';
$dbname = 'simrs';
$user   = 'root';
$pass   = '';       // sesuaikan password MySQL
$port   = '3306';
```

### 3. Install Dependensi (Tugas 3 & 4)

```bash
cd tugas3/simrs-bug
composer install

cd ../../tugas4
composer install
```

### 4. Jalankan PHPUnit

```bash
# Tugas 3 — pastikan semua test PASS
cd tugas3/simrs-bug
php vendor/bin/phpunit --testdox

# Tugas 4 — dengan laporan coverage
cd tugas4
php vendor/bin/phpunit --testdox --coverage-html coverage-report
```

### 5. Akses Endpoint Tugas 2

Setelah web server berjalan:

```
GET http://localhost/simrs/tugas2/laporan_dokter.php?bulan=10&tahun=2024
```

Contoh response:

```json
{
  "status": "success",
  "bulan": 10,
  "tahun": 2024,
  "data": [
    {
      "nama_dokter": "dr. Budi Santoso",
      "spesialisasi": "Umum",
      "total_kunjungan": 12,
      "total_pendapatan": 3500000,
      "rata_rata_kepuasan": 4.75
    }
  ]
}
```

---

## Tugas 1 — Analisis & Optimasi Database

> Laporan lengkap: [`tugas1/laporan.md`](tugas1/laporan.md)

### Ringkasan Temuan

Tiga query dianalisis menggunakan `EXPLAIN`. Query 2 ditetapkan sebagai **paling bermasalah** karena menggabungkan dua antipattern:

1. `DATE_FORMAT(created_at, '%Y-%m')` di WHERE → MySQL tidak bisa pakai index
2. `DEPENDENT SUBQUERY` → subquery dieksekusi ulang per baris (O(N))

### Index yang Dibuat

```sql
CREATE INDEX idx_pendaftaran_tgl_daftar     ON pendaftaran (tgl_daftar);
CREATE INDEX idx_pendaftaran_id_jadwal      ON pendaftaran (id_jadwal);
CREATE INDEX idx_pendaftaran_created_at     ON pendaftaran (created_at);
CREATE INDEX idx_pendaftaran_id_pasien      ON pendaftaran (id_pasien);
CREATE INDEX idx_tagihan_pendaftaran_status ON tagihan (id_pendaftaran, status_bayar);
CREATE INDEX idx_rekam_medis_tgl_periksa    ON rekam_medis (tgl_periksa);
CREATE INDEX idx_rekam_medis_id_pendaftaran ON rekam_medis (id_pendaftaran);
```

### Hasil Optimasi (Before vs After)

| Query | Tabel       | Type Sebelum    | Type Setelah | Rows Sebelum | Rows Setelah |
| ----- | ----------- | --------------- | ------------ | ------------ | ------------ |
| Q1    | pendaftaran | ALL             | ref          | 20           | 4            |
| Q1    | tagihan     | ALL             | ref          | 18           | 1            |
| Q2    | pendaftaran | ALL             | range        | 20           | 8            |
| Q2    | tagihan     | ALL (per baris) | ref (sekali) | 18×N         | 1            |
| Q3    | rekam_medis | ALL             | range        | 20           | 5            |
| Q3    | pendaftaran | ALL             | ref          | 20           | 1            |

---

## Tugas 2 — Implementasi Laporan Dokter PHP Native

### Arsitektur File

```
tugas2/
├── db_connection.php     → getConnection(): PDO
├── DokterRepository.php  → getLaporanKunjungan(int $bulan, int $tahun): array
└── laporan_dokter.php    → Validasi input → DokterRepository → JSON response
```

### Poin Penting Implementasi

- **PDO Prepared Statement** dengan named parameter (`:tgl_mulai`, `:tgl_akhir`)
- Filter tanggal menggunakan **range comparison** bukan `DATE_FORMAT()` agar index terpakai
- Response JSON dengan header `Content-Type: application/json`
- Validasi parameter `bulan` (1–12) dan `tahun` (2000–2100) sebelum query
- Semua fungsi dilengkapi **PHPDoc** (`/** ... */`)

---

## Tugas 3 — Debugging & Profiling

> Laporan lengkap: [`tugas3/profiling_report.md`](tugas3/profiling_report.md)

### Bug yang Ditemukan & Diperbaiki

| No  | File                | Bug                                         | Fix                                                |
| --- | ------------------- | ------------------------------------------- | -------------------------------------------------- |
| 1   | TarifCalculator.php | Tidak ada validasi `$treatments` kosong     | Tambah `if (empty($treatments)) throw ...`         |
| 2   | TarifCalculator.php | Off-by-one: `> 60` seharusnya `>= 60`       | Ganti kondisi menjadi `>= 60`                      |
| 3   | TarifCalculator.php | Tidak ada guard biaya negatif               | Tambah `if ($base < 0) throw ...`                  |
| 4   | TarifCalculator.php | Division by zero di `getDiscountPercentage` | Tambah `if ($base == 0) throw DivisionByZeroError` |

### Performance Issue

`DokterRepository::getLaporanKunjungan()` menggunakan pola **N+1 query**: 1 query untuk ambil daftar dokter, lalu 2 query per dokter untuk kunjungan dan pendapatan. Dengan 45 dokter = **91 query per request**.

**Solusi:** Rewrite menjadi 1 JOIN query dengan GROUP BY — total query turun dari 91 menjadi **1**, estimasi waktu dari ~450ms menjadi ~15ms.

### Hasil PHPUnit Setelah Perbaikan

```
PHPUnit 10.x.x

TarifCalculatorTest
 ✔ Pasien umum tanpa diskon dikenai pajak saja
 ✔ Pasien bpjs mendapat diskon 15 persen
 ✔ Pasien senior tepat 60 tahun mendapat diskon senior
 ✔ Pasien bpjs senior mendapat diskon bertumpuk
 ✔ Treatments kosong harus throw exception
 ✔ Diskon tidak boleh melebihi base biaya
 ✔ Get discount percentage dengan base nol harus throw exception
 ✔ test_kalkulasi_tarif_berbagai_skenario [umum_muda]
 ✔ test_kalkulasi_tarif_berbagai_skenario [umum_senior_61]
 ✔ test_kalkulasi_tarif_berbagai_skenario [bpjs_muda]
 ✔ test_kalkulasi_tarif_berbagai_skenario [bpjs_senior_60]

OK (11 tests, 11 assertions)
```

---

## Tugas 4 — Unit Test & Integration Test

### Unit Test — TarifCalculator (≥8 test, coverage ≥80%)

Test mencakup skenario: pasien umum, BPJS, senior (batas 60 tahun), kombinasi, edge case (kosong, negatif, divisi nol), dan parameterized test dengan `@dataProvider`.

### Integration Test — POST /pendaftaran_pasien.php (≥3 test)

Setiap test menggunakan `setUp()`/`tearDown()` dengan **database transaction rollback** sehingga data test tidak mencemari database production/test.

```php
protected function setUp(): void
{
    $this->pdo = getConnection();
    $this->pdo->beginTransaction();   // mulai transaksi sebelum test
}

protected function tearDown(): void
{
    $this->pdo->rollBack();           // rollback setelah test selesai
}
```

### Jalankan Coverage Report

```bash
cd tugas4
php vendor/bin/phpunit --testdox --coverage-html coverage-report
# Buka coverage-report/index.html di browser untuk verifikasi ≥80%
```

---

## Teknologi yang Digunakan

| Komponen | Versi      |
| -------- | ---------- |
| PHP      | 8.2        |
| MySQL    | 8.0        |
| PDO      | Native PHP |
| PHPUnit  | 10.x       |
| Xdebug   | 3.x        |
| Apache   | 2.4        |
| Composer | 2.x        |
