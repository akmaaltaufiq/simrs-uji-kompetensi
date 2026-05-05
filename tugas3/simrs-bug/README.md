# simrs-buggy — Tugas Debugging & Profiling

> **Studi Kasus Uji Kompetensi Analis Program — LSP UPNVJ**  
> Bagian V · Observasi Praktik · Tugas 3

---

## Deskripsi Tugas

Folder ini berisi project PHP native SIMRS yang **sengaja mengandung bug dan performance issue**.  
Tugas Anda adalah:

1. Jalankan PHPUnit dan catat test mana yang **GAGAL**
2. Gunakan **Xdebug step debugging** di VS Code untuk menemukan root cause setiap bug
3. **Perbaiki** setiap bug hingga seluruh PHPUnit test PASS
4. Identifikasi **query MySQL yang lambat** menggunakan Slow Query Log + EXPLAIN
5. Buat laporan `profiling_report.md` berisi temuan dan rekomendasi

---

## Struktur Project

```
simrs-buggy/
├── db_connection.php          # Koneksi PDO ke MySQL
├── src/
│   ├── TarifCalculator.php    # Logika kalkulasi tarif <- mengandung bug
│   └── DokterRepository.php  # Akses data dokter      <- mengandung performance issue
├── tests/
│   └── TarifCalculatorTest.php # Test suite PHPUnit
├── phpunit.xml                # Konfigurasi PHPUnit
├── composer.json              # Dependensi
└── README.md                  # File ini
```

---

## Cara Menjalankan

### 1. Install dependensi
```bash
composer install
```

### 2. Jalankan seluruh test suite
```bash
php vendor/bin/phpunit --testdox
```

### 3. Jalankan dengan laporan coverage
```bash
php vendor/bin/phpunit --testdox --coverage-html coverage-report
```

---

## Petunjuk untuk Asesi

- Jangan ubah file `tests/TarifCalculatorTest.php` — test adalah **ground truth**
- Semua perbaikan dilakukan di `src/TarifCalculator.php` dan `db_connection.php`
- Untuk performance issue di `DokterRepository.php`, rewrite query menggunakan JOIN
- Dokumentasikan setiap temuan di `profiling_report.md`
- Commit setiap perbaikan secara terpisah dengan pesan commit yang deskriptif

---

## Template `profiling_report.md`

```markdown
# Laporan Debugging & Profiling — SIMRS Buggy

## 1. Daftar Bug yang Ditemukan

| No | File | Baris | Deskripsi Bug | Root Cause |
|----|------|-------|---------------|------------|
| 1  |      |       |               |            |

## 2. Perbaikan yang Dilakukan

### Bug #1 — [Judul]
**Root cause:** ...
**Perbaikan:** ...
**Test yang sebelumnya gagal:** ...

## 3. Performance Issue

### Query Lambat — DokterRepository::getLaporanKunjungan()
**Masalah:** ...
**Output EXPLAIN sebelum optimasi:** ...
**Solusi:** ...
**Output EXPLAIN setelah optimasi:** ...

## 4. Rekomendasi Lanjutan
...
```


