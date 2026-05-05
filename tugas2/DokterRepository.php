<?php
/**
 * Repository untuk mengambil laporan rekap kunjungan dokter
 */

require_once __DIR__ . '/db_connection.php';

class DokterRepository
{
    private PDO $pdo;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->pdo = getConnection();
    }

    /**
     * Ambil laporan berdasarkan bulan & tahun
     *
     * @param int $bulan
     * @param int $tahun
     * @return array
     */
    public function getLaporanKunjungan(int $bulan, int $tahun): array
    {
        // Range tanggal (biar index kepakai)
        $tglMulai = sprintf('%04d-%02d-01', $tahun, $bulan);
        $tglAkhir = date('Y-m-d', strtotime("$tglMulai +1 month"));

        $sql = "
            SELECT
                d.nama AS nama_dokter,
                d.spesialisasi,
                COUNT(DISTINCT p.id_pendaftaran) AS total_kunjungan,
                COALESCE(SUM(t.total_biaya), 0) AS total_pendapatan,
                COALESCE(ROUND(AVG(rm.rating_kepuasan), 2), 0) AS rata_rata_kepuasan
            FROM dokter d
            JOIN jadwal_dokter jd ON jd.id_dokter = d.id_dokter
            JOIN pendaftaran p ON p.id_jadwal = jd.id_jadwal
                AND p.tgl_daftar >= :tgl_mulai
                AND p.tgl_daftar < :tgl_akhir
            LEFT JOIN tagihan t ON t.id_pendaftaran = p.id_pendaftaran
                AND t.status_bayar = 'LUNAS'
            LEFT JOIN rekam_medis rm ON rm.id_pendaftaran = p.id_pendaftaran
            WHERE d.status_aktif = 1
            GROUP BY d.id_dokter, d.nama, d.spesialisasi
            ORDER BY total_kunjungan DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':tgl_mulai' => $tglMulai,
            ':tgl_akhir' => $tglAkhir,
        ]);

        return $stmt->fetchAll();
    }
}