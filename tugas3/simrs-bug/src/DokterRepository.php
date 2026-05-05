<?php
/**
 * DokterRepository.php
 * Repository untuk mengakses data dokter dan kunjungan pasien di SIMRS
 *
 * PERFORMANCE ISSUE: Query N+1 pada method getLaporanKunjungan().
 * Untuk setiap dokter, dijalankan query terpisah ke tabel pendaftaran
 * dan tagihan — sangat lambat saat jumlah dokter besar.
 */

require_once __DIR__ . '/../db_connection.php';

class DokterRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = getConnection();
    }

    /**
     * Ambil semua dokter aktif
     *
     * @return array List dokter aktif
     */
    public function findAllAktif(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_dokter, kode_dokter, nama, spesialisasi
             FROM dokter
             WHERE status_aktif = 1"
        );
        return $stmt->fetchAll();
    }

    /**
     * Ambil laporan kunjungan per dokter untuk bulan dan tahun tertentu
     *
     * PERFORMANCE ISSUE (Query N+1):
     * Method ini memanggil findAllAktif() yang menghasilkan N dokter,
     * lalu untuk setiap dokter menjalankan 2 query terpisah (kunjungan + pendapatan).
     * Total query = 1 + (N * 2). Dengan 45 dokter = 91 query per request.
     *
     *
     * @param int $bulan  Bulan (1-12)
     * @param int $tahun  Tahun (misal 2024)
     * @return array Laporan kunjungan dokter
     */
    public function getLaporanKunjungan(int $bulan, int $tahun): array
    {
        $dokters = $this->findAllAktif(); // Query 1
        $laporan = [];

        foreach ($dokters as $dokter) {
            // Query N (per dokter): hitung kunjungan
            $stmtKunjungan = $this->pdo->prepare(
                "SELECT COUNT(*) as total
                 FROM pendaftaran p
                 WHERE p.id_jadwal IN (
                     SELECT id_jadwal FROM jadwal_dokter WHERE id_dokter = :id
                 )
                 AND MONTH(p.tgl_daftar) = :bulan
                 AND YEAR(p.tgl_daftar) = :tahun"
            );
            $stmtKunjungan->execute([
                ':id'    => $dokter['id_dokter'],
                ':bulan' => $bulan,
                ':tahun' => $tahun,
            ]);
            $kunjungan = $stmtKunjungan->fetch();

            // Query N (per dokter): hitung pendapatan
            $stmtPendapatan = $this->pdo->prepare(
                "SELECT SUM(t.total_biaya) as total_pendapatan
                 FROM tagihan t
                 JOIN pendaftaran p ON t.id_pendaftaran = p.id_pendaftaran
                 WHERE p.id_jadwal IN (
                     SELECT id_jadwal FROM jadwal_dokter WHERE id_dokter = :id
                 )
                 AND t.status_bayar = 'LUNAS'
                 AND MONTH(p.tgl_daftar) = :bulan
                 AND YEAR(p.tgl_daftar) = :tahun"
            );
            $stmtPendapatan->execute([
                ':id'    => $dokter['id_dokter'],
                ':bulan' => $bulan,
                ':tahun' => $tahun,
            ]);
            $pendapatan = $stmtPendapatan->fetch();

            $laporan[] = [
                'nama_dokter'      => $dokter['nama'],
                'spesialisasi'     => $dokter['spesialisasi'],
                'total_kunjungan'  => (int) $kunjungan['total'],
                'total_pendapatan' => (float) ($pendapatan['total_pendapatan'] ?? 0),
            ];
        }

        return $laporan;
    }
}
