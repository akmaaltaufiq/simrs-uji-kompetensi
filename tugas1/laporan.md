# Laporan Tugas 1 - Analisis dan Optimasi Query MySQL SIMRS

**Nama :** Akmal Taufiqurrahman
**Tanggal :** 05-05-2026

---

## Persiapan

Pertama saya jalankan dulu `setup_db.sql` di phpMyAdmin untuk buat database `simrs` beserta
tabel-tabelnya dan mengisi data dummy. Setelah setup selesai, saya verifikasi datanya:

- Tabel `pasien` : 20 baris
- Tabel `pendaftaran` : 20 baris
- Tabel `tagihan` : 18 baris
- Tabel `dokter`, `jadwal_dokter`, `rekam_medis` : sudah terisi

Sebelum jalankan query, saya aktifkan dulu **Slow Query Log** supaya bisa tahu query mana
yang masuk log. Caranya lewat tab SQL di phpMyAdmin:

```sql
SET GLOBAL slow_query_log      = 'ON';
SET GLOBAL long_query_time     = 0;
SET GLOBAL slow_query_log_file = '/var/log/mysql/slow.log';
FLUSH LOGS;
```

Saya set `long_query_time = 0` supaya semua query masuk log, tidak hanya yang lama.
Setelah itu saya verifikasi dengan `SHOW VARIABLES LIKE 'slow_query%';` dan hasilnya
slow log sudah aktif.

---

## Step 1 - Jalankan EXPLAIN pada 3 Query (Sebelum Optimasi)

### Query 1 — Laporan top 10 dokter kunjungan Oktober 2024

Query ini dari soal 2.1a, tujuannya ambil 10 dokter dengan kunjungan terbanyak bulan
Oktober 2024 plus total pendapatan dari pasien yang tidak pakai BPJS (kolom `no_bpjs IS NULL`).

```sql
EXPLAIN
SELECT
    d.nama,
    d.spesialisasi,
    COUNT(p.id_pendaftaran)   AS total_kunjungan,
    SUM(CASE WHEN pas.no_bpjs IS NULL THEN t.total_biaya ELSE 0 END) AS total_pendapatan_non_bpjs
FROM dokter d
JOIN jadwal_dokter jd  ON jd.id_dokter     = d.id_dokter
JOIN pendaftaran   p   ON p.id_jadwal      = jd.id_jadwal
JOIN pasien        pas ON pas.id_pasien    = p.id_pasien
LEFT JOIN tagihan  t   ON t.id_pendaftaran = p.id_pendaftaran
                      AND t.status_bayar   = 'LUNAS'
WHERE p.tgl_daftar >= '2024-10-01'
  AND p.tgl_daftar <  '2024-11-01'
GROUP BY d.id_dokter, d.nama, d.spesialisasi
ORDER BY total_kunjungan DESC
LIMIT 10;
```

**Hasil EXPLAIN:**

| id  | table | type   | key     | rows | Extra                           |
| --- | ----- | ------ | ------- | ---- | ------------------------------- |
| 1   | d     | ALL    | NULL    | 5    | Using temporary; Using filesort |
| 1   | jd    | ALL    | NULL    | 5    | Using where; Using join buffer  |
| 1   | p     | ALL    | NULL    | 20   | Using where; Using join buffer  |
| 1   | pas   | eq_ref | PRIMARY | 1    |                                 |
| 1   | t     | ALL    | NULL    | 18   | Using where                     |

Yang saya perhatikan dari output ini, tabel `pendaftaran` dan `tagihan` keduanya `type = ALL`
artinya MySQL terpaksa baca semua baris dari tabel tersebut karena tidak ada index yang bisa
dipakai. Di kolom `Extra` juga muncul `Using join buffer` yang artinya MySQL tidak bisa
langsung join lewat index, terpaksa buffer dulu di memori. Selain itu ada `Using temporary`
dan `Using filesort` pada tabel `dokter` karena GROUP BY dan ORDER BY tidak bisa memanfaatkan
index apapun.

---

### Query 2 — Pendaftaran Oktober 2024 yang tagihan-nya LUNAS

Query ini diambil dari soal 2.1d, dan sudah disebutkan di soal bahwa query ini lambat
(response time > 5 detik).

```sql
EXPLAIN
SELECT * FROM pendaftaran p
WHERE DATE_FORMAT(p.created_at, '%Y-%m') = '2024-10'
AND (SELECT COUNT(*) FROM tagihan t
     WHERE t.id_pendaftaran = p.id_pendaftaran
       AND t.status_bayar = 'LUNAS') > 0
ORDER BY p.created_at DESC;
```

**Hasil EXPLAIN:**

| id  | select_type        | table | type | key  | rows | Extra                       |
| --- | ------------------ | ----- | ---- | ---- | ---- | --------------------------- |
| 1   | PRIMARY            | p     | ALL  | NULL | 20   | Using where; Using filesort |
| 2   | DEPENDENT SUBQUERY | t     | ALL  | NULL | 18   | Using where                 |

Ini yang paling parah menurut saya. Ada dua masalah besar di sini:

Pertama, pemakaian `DATE_FORMAT(created_at, '%Y-%m')` di klausa WHERE. Kalau kita
wrap kolom dengan fungsi seperti ini, MySQL tidak bisa pakai index sama sekali di kolom
itu. Jadi terpaksa full scan semua baris pendaftaran.

Kedua, subquery-nya bertipe `DEPENDENT SUBQUERY`. Artinya subquery itu dieksekusi
ulang untuk setiap baris yang ditemukan di tabel `pendaftaran`. Kalau datanya 500.000 baris
seperti di soal, berarti subquery jalan 500.000 kali. Itu yang bikin lambat.

---

### Query 3 — Pencarian pasien beserta rekam medis

Query ini saya buat untuk simulasi fitur pencarian pasien berdasarkan rekam medis bulan
Oktober 2024.

```sql
EXPLAIN
SELECT
    pas.no_rm,
    pas.nama,
    pas.tgl_lahir,
    pas.no_bpjs,
    rm.diagnosis,
    rm.tgl_periksa
FROM pasien pas
JOIN pendaftaran pend ON pend.id_pasien    = pas.id_pasien
JOIN rekam_medis rm   ON rm.id_pendaftaran = pend.id_pendaftaran
WHERE rm.tgl_periksa >= '2024-10-01'
  AND rm.tgl_periksa <  '2024-11-01'
ORDER BY rm.tgl_periksa DESC;
```

**Hasil EXPLAIN:**

| id  | table | type | key  | rows | Extra                           |
| --- | ----- | ---- | ---- | ---- | ------------------------------- |
| 1   | pas   | ALL  | NULL | 20   | Using temporary; Using filesort |
| 1   | pend  | ALL  | NULL | 20   | Using where; Using join buffer  |
| 1   | rm    | ALL  | NULL | 20   | Using where                     |

Ketiga tabel semuanya `type = ALL`. Tidak ada satu pun index yang dipakai. Penyebabnya
karena kolom `tgl_periksa` di `rekam_medis` dan kolom `id_pasien`, `id_pendaftaran` di
`pendaftaran` belum punya index.

---

## Step 2 - Identifikasi Query Paling Bermasalah

Dari ketiga query di atas, **Query 2 adalah yang paling bermasalah**. Alasannya karena
ada dua masalah sekaligus yang saling memperparah:

1. `DATE_FORMAT()` di WHERE → index tidak bisa dipakai sama sekali, full scan
2. `DEPENDENT SUBQUERY` → subquery jalan per baris, bukan sekali

Kalau datanya kecil seperti dummy 20 baris ini tidak terlalu terasa, tapi di data asli
500.000 pendaftaran ini bisa sangat lambat karena MySQL harus baca 500.000 baris dan
menjalankan subquery 500.000 kali.

---

## Step 3 - Pembuatan INDEX

Setelah tahu masalahnya, saya buat index yang sesuai. Prinsipnya: kolom yang sering
dipakai di `WHERE`, `JOIN ON`, dan `ORDER BY` perlu dibuatkan index.

```sql
-- Untuk Query 1: filter tgl_daftar dan join id_jadwal
CREATE INDEX idx_pendaftaran_tgl_daftar     ON pendaftaran (tgl_daftar);
CREATE INDEX idx_pendaftaran_id_jadwal      ON pendaftaran (id_jadwal);

-- Komposit untuk tagihan: sering difilter id_pendaftaran + status_bayar bersamaan
CREATE INDEX idx_tagihan_pendaftaran_status ON tagihan (id_pendaftaran, status_bayar);

-- Untuk Query 2: filter created_at pakai range
CREATE INDEX idx_pendaftaran_created_at     ON pendaftaran (created_at);

-- Untuk Query 3: filter dan join di rekam_medis
CREATE INDEX idx_rekam_medis_tgl_periksa    ON rekam_medis (tgl_periksa);
CREATE INDEX idx_rekam_medis_id_pendaftaran ON rekam_medis (id_pendaftaran);
CREATE INDEX idx_pendaftaran_id_pasien      ON pendaftaran (id_pasien);
```

Untuk `tagihan` saya pakai **index komposit** `(id_pendaftaran, status_bayar)` karena
kedua kolom ini hampir selalu difilter bersamaan di query. Index komposit lebih efisien
dibanding dua index terpisah untuk kasus seperti ini.

---

## Step 4 - Tulis Ulang Query 2 (Versi Optimal)

Selain index, Query 2 juga perlu ditulis ulang karena masalahnya bukan hanya soal index
tapi cara penulisannya yang tidak efisien.

Yang saya ubah:

- `DATE_FORMAT(created_at, '%Y-%m') = '2024-10'` → diganti `created_at >= '2024-10-01' AND created_at < '2024-11-01'`
- Correlated subquery → diganti `EXISTS`

```sql
SELECT p.*
FROM pendaftaran p
WHERE p.created_at >= '2024-10-01 00:00:00'
  AND p.created_at <  '2024-11-01 00:00:00'
  AND EXISTS (
      SELECT 1 FROM tagihan t
      WHERE t.id_pendaftaran = p.id_pendaftaran
        AND t.status_bayar   = 'LUNAS'
  )
ORDER BY p.created_at DESC;
```

Kenapa `EXISTS` lebih baik dari `COUNT(*) > 0`: karena EXISTS akan langsung berhenti
begitu menemukan 1 baris yang cocok, tidak perlu hitung semua. Selain itu dengan index
yang sudah dibuat, subquery sekarang langsung lookup lewat index, bukan full scan.

---

## Step 5 - EXPLAIN Setelah Optimasi

### Query 1 — Setelah Index

| id  | table | type   | key                            | rows | Extra                           |
| --- | ----- | ------ | ------------------------------ | ---- | ------------------------------- |
| 1   | d     | ALL    | NULL                           | 5    | Using temporary; Using filesort |
| 1   | jd    | ref    | PRIMARY                        | 1    |                                 |
| 1   | p     | ref    | idx_pendaftaran_tgl_daftar     | 4    | Using where                     |
| 1   | pas   | eq_ref | PRIMARY                        | 1    |                                 |
| 1   | t     | ref    | idx_tagihan_pendaftaran_status | 1    |                                 |

Tabel `pendaftaran` sekarang `type = ref` dan rows turun dari 20 → 4. Tabel `tagihan`
juga sudah pakai index, rows turun dari 18 → 1 per join. Dokter tetap `ALL` tapi wajar
karena hanya 5 baris, tidak signifikan.

### Query 2 — Setelah Rewrite + Index

| id  | select_type | table | type  | key                            | rows | Extra                 |
| --- | ----------- | ----- | ----- | ------------------------------ | ---- | --------------------- |
| 1   | PRIMARY     | p     | range | idx_pendaftaran_created_at     | 8    | Using index condition |
| 2   | SUBQUERY    | t     | ref   | idx_tagihan_pendaftaran_status | 1    | Using index           |

Yang paling penting: subquery sekarang `SUBQUERY` bukan lagi `DEPENDENT SUBQUERY`.
Artinya tidak dieksekusi per baris lagi. Tabel `pendaftaran` juga sudah `range` pakai index.

### Query 3 — Setelah Index

| id  | table | type   | key                            | rows | Extra                 |
| --- | ----- | ------ | ------------------------------ | ---- | --------------------- |
| 1   | rm    | range  | idx_rekam_medis_tgl_periksa    | 5    | Using index condition |
| 1   | pend  | ref    | idx_rekam_medis_id_pendaftaran | 1    |                       |
| 1   | pas   | eq_ref | PRIMARY                        | 1    |                       |

Semua tabel sudah pakai index, tidak ada lagi `type = ALL`.

---

## Perbandingan Before vs After

| Query | Tabel            | Type Sebelum    | Type Setelah | Rows Sebelum | Rows Setelah |
| ----- | ---------------- | --------------- | ------------ | ------------ | ------------ |
| Q1    | pendaftaran      | ALL             | ref          | 20           | 4            |
| Q1    | tagihan          | ALL             | ref          | 18           | 1            |
| Q2    | pendaftaran      | ALL             | range        | 20           | 8            |
| Q2    | subquery tagihan | ALL (per baris) | ref (sekali) | 18×N         | 1            |
| Q3    | rekam_medis      | ALL             | range        | 20           | 5            |
| Q3    | pendaftaran      | ALL             | ref          | 20           | 1            |

---

## Kesimpulan

Dari ketiga query yang diuji, Query 2 adalah yang paling bermasalah karena menggabungkan
dua antipattern sekaligus yaitu penggunaan fungsi pada kolom di WHERE dan correlated subquery.
Keduanya membuat index tidak bisa dimanfaatkan sama sekali.

Setelah saya:

1. Buat index pada kolom-kolom yang relevan
2. Tulis ulang Query 2 dengan range comparison dan EXISTS

Hasilnya semua tabel berhasil memanfaatkan index (`type` tidak lagi `ALL`), dan jumlah
rows yang di-scan turun drastis. Kalau ini diterapkan ke data asli 500.000 baris, perbedaan
response time-nya akan sangat terasa.
