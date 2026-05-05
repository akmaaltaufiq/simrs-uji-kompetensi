# Laporan Debugging & Profiling — SIMRS Buggy

**Nama Asesi  :** ___________________________________  
**No. Registrasi :** ___________________________________  
**Tanggal Uji  :** ___________________________________  

---

## 1. Hasil Eksekusi PHPUnit Awal

> Jalankan: `php vendor/bin/phpunit --testdox`  
> Catat test yang GAGAL di bawah ini.

| No | Nama Test | Status | Pesan Error |
|----|-----------|--------|-------------|
| 1  |           | FAIL / PASS |        |
| 2  |           | FAIL / PASS |        |
| 3  |           | FAIL / PASS |        |
| 4  |           | FAIL / PASS |        |
| 5  |           | FAIL / PASS |        |

---

## 2. Daftar Bug yang Ditemukan

| No | File | Baris | Jenis Bug | Deskripsi Singkat |
|----|------|-------|-----------|-------------------|
| 1  |      |       |           |                   |
| 2  |      |       |           |                   |
| 3  |      |       |           |                   |
| 4  |      |       |           |                   |

---

## 3. Analisis Root Cause & Perbaikan

### Bug #1 — [Judul Bug]

**File:** `_____.php` baris ___  
**Root Cause:**  
> _Jelaskan mengapa bug ini terjadi_

**Kode Sebelum Diperbaiki:**
```php
// paste kode yang bermasalah
```

**Kode Setelah Diperbaiki:**
```php
// paste kode yang sudah benar
```

**Test yang Kini PASS:**  
- [ ] `nama_test_yang_sebelumnya_gagal`

---

### Bug #2 — [Judul Bug]

**File:** `_____.php` baris ___  
**Root Cause:**  
> _Jelaskan mengapa bug ini terjadi_

**Kode Sebelum Diperbaiki:**
```php
// paste kode yang bermasalah
```

**Kode Setelah Diperbaiki:**
```php
// paste kode yang sudah benar
```

**Test yang Kini PASS:**  
- [ ] `nama_test_yang_sebelumnya_gagal`

---

### Bug #3 — [Judul Bug]

**File:** `_____.php` baris ___  
**Root Cause:**  
> _Jelaskan mengapa bug ini terjadi_

**Kode Sebelum Diperbaiki:**
```php
// paste kode yang bermasalah
```

**Kode Setelah Diperbaiki:**
```php
// paste kode yang sudah benar
```

---

## 4. Performance Issue — Query N+1

### Identifikasi

**File:** `src/DokterRepository.php`  
**Method:** `getLaporanKunjungan()`  

**Penjelasan masalah:**  
> _Jelaskan mengapa query ini lambat_

**Output Slow Query Log:**
```
# Query yang masuk slow log:
...
```

**Output EXPLAIN sebelum optimasi:**
```
+----+-------------+-------+...
| id | select_type | table |...
+----+-------------+-------+...
|    |             |       |...
```

### Solusi

**Query yang dioptimalkan:**
```sql
-- Tulis query JOIN pengganti di sini
SELECT
    d.nama AS nama_dokter,
    ...
FROM dokter d
JOIN ...
WHERE ...
GROUP BY ...
```

**Output EXPLAIN setelah optimasi:**
```
+----+-------------+-------+...
```

**Perbandingan performa:**

| Metrik | Sebelum | Setelah |
|--------|---------|---------|
| Jumlah query per request | N+1 | 1 |
| Waktu eksekusi (estimasi) | ~___ ms | ~___ ms |

---

## 5. Hasil PHPUnit Setelah Perbaikan

> Tempel output `php vendor/bin/phpunit --testdox` setelah semua bug diperbaiki

```
PHPUnit 10.x.x

SIMRS Buggy Test Suite
 ✔ ...
 ✔ ...

OK (X tests, X assertions)
```

---

## 6. Rekomendasi Lanjutan

1. _Tulis rekomendasi untuk mencegah bug serupa_
2. _Rekomendasi optimasi query_
3. _Rekomendasi penambahan validasi_

---

*Laporan dibuat sebagai bagian dari Uji Kompetensi Analis Program — LSP UPNVJ*
