<?php
/**
 * IntegrationTest.php
 *
 * Integration test untuk endpoint dan repository SIMRS.
 * Menguji interaksi antara kode PHP dan database MySQL secara langsung.
 *
 * PENTING — Transaction Rollback Pattern:
 * Setiap test method membuka transaksi di setUp() dan me-rollback-nya di tearDown().
 * Ini memastikan data test tidak mencemari database — setiap test berjalan
 * dalam "ruang bersih" dan tidak ada data sisa setelah test selesai.
 *
 * Cara menjalankan:
 *   1. Pastikan database simrs_test sudah dibuat dengan skema yang sama dengan simrs
 *      CREATE DATABASE simrs_test;
 *      USE simrs_test;
 *      SOURCE setup_db.sql;
 *
 *   2. Jalankan hanya integration test:
 *      php vendor/bin/phpunit --testdox tests/IntegrationTest.php
 *
 *   3. Jalankan semua test:
 *      php vendor/bin/phpunit --testdox
 *
 * @author  Akmal Taufiqurrahman
 * @version 1.0.0
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;

require_once __DIR__ . '/../src/TarifCalculator.php';
require_once __DIR__ . '/../src/DokterRepository.php';

/**
 * Integration test suite untuk DokterRepository dan kalkulasi tarif.
 *
 * Menggunakan database simrs_test (terpisah dari simrs production)
 * dengan pola setUp/tearDown + beginTransaction/rollBack untuk
 * memastikan isolasi data antar test.
 */
class IntegrationTest extends TestCase
{
    /** @var PDO Koneksi ke database test */
    private PDO $pdo;

    /** @var DokterRepository Repository yang diuji */
    private DokterRepository $repo;

    /**
     * Dijalankan SEBELUM setiap test method.
     *
     * Membuat koneksi ke database test dan memulai transaksi.
     * Semua perubahan dalam test akan di-rollback di tearDown().
     */
    protected function setUp(): void
    {
        $this->pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=simrs_test;charset=utf8mb4',
            'root',
            '',
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        // Mulai transaksi — semua INSERT/UPDATE/DELETE dalam test
        // akan dibatalkan saat tearDown() memanggil rollBack()
        $this->pdo->beginTransaction();

        // Inject koneksi yang sama ke repository agar rollback mencakup semua operasi
        $this->repo = new DokterRepository($this->pdo);
    }

    /**
     * Dijalankan SETELAH setiap test method.
     *
     * Me-rollback transaksi sehingga data yang diinsert selama test
     * tidak tersimpan di database. Database kembali ke state sebelum test.
     */
    protected function tearDown(): void
    {
        // Rollback semua perubahan — database bersih kembali
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // =========================================================
    // Integration Test 1: Insert & Query Pendaftaran
    // =========================================================

    /**
     * Memverifikasi bahwa data pasien baru dapat disimpan ke database
     * dan dapat diambil kembali dengan benar.
     *
     * Test ini:
     * 1. Insert pasien baru
     * 2. Verifikasi data tersimpan dengan query SELECT
     * 3. Semua data di-rollback di tearDown — tidak ada sisa di DB
     */
    #[Test]
    public function pasien_baru_dapat_disimpan_dan_diambil_dari_database(): void
    {
        // Insert pasien baru dalam transaksi aktif
        $stmt = $this->pdo->prepare(
            "INSERT INTO pasien (nama, tgl_lahir, jenis_kelamin, no_rm, created_at)
             VALUES (:nama, :tgl_lahir, :jk, :no_rm, NOW())"
        );
        $stmt->execute([
            ':nama'     => 'Pasien Test Integration',
            ':tgl_lahir'=> '1990-06-15',
            ':jk'       => 'L',
            ':no_rm'    => 'RM-INT-TEST-001',
        ]);

        $idPasien = (int) $this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $idPasien, 'lastInsertId harus > 0 setelah insert berhasil');

        // Verifikasi data tersimpan dan dapat diambil
        $check = $this->pdo->prepare("SELECT nama, no_rm FROM pasien WHERE id_pasien = ?");
        $check->execute([$idPasien]);
        $row = $check->fetch();

        $this->assertNotFalse($row, 'Pasien harus ditemukan setelah insert');
        $this->assertEquals('Pasien Test Integration', $row['nama']);
        $this->assertEquals('RM-INT-TEST-001', $row['no_rm']);
        // tearDown() akan rollback insert ini — database tetap bersih
    }

    // =========================================================
    // Integration Test 2: Transaksi Atomicity
    // =========================================================

    /**
     * Memverifikasi bahwa method simpanPendaftaran() menggunakan transaksi
     * sehingga pendaftaran dan tagihan dibuat bersamaan atau tidak sama sekali.
     *
     * Skenario: Insert pendaftaran + tagihan berhasil → keduanya harus ada.
     */
    #[Test]
    public function simpan_pendaftaran_membuat_pendaftaran_dan_tagihan_bersamaan(): void
    {
        // Ambil id pasien dan jadwal yang sudah ada di data dummy
        $pasien = $this->pdo->query("SELECT id_pasien FROM pasien LIMIT 1")->fetch();
        $jadwal = $this->pdo->query("SELECT id_jadwal FROM jadwal_dokter LIMIT 1")->fetch();

        if (!$pasien || !$jadwal) {
            $this->markTestSkipped('Data dummy pasien/jadwal belum tersedia di DB test');
        }

        $idPasien = (int) $pasien['id_pasien'];
        $idJadwal = (int) $jadwal['id_jadwal'];

        // Jalankan simpanPendaftaran — ini menggunakan transaksi internal
        // Karena setUp() sudah beginTransaction(), transaksi ini akan ter-nest
        // (PDO MySQL mendukung savepoint untuk nested transaction)
        $idPendaftaran = $this->repo->simpanPendaftaran($idPasien, $idJadwal, 150000.0);

        $this->assertGreaterThan(0, $idPendaftaran);

        // Verifikasi pendaftaran tersimpan
        $stmtPend = $this->pdo->prepare(
            "SELECT id_pendaftaran FROM pendaftaran WHERE id_pendaftaran = ?"
        );
        $stmtPend->execute([$idPendaftaran]);
        $this->assertNotFalse($stmtPend->fetch(), 'Pendaftaran harus tersimpan');

        // Verifikasi tagihan ikut tersimpan
        $stmtTagihan = $this->pdo->prepare(
            "SELECT total_biaya, status_bayar FROM tagihan WHERE id_pendaftaran = ?"
        );
        $stmtTagihan->execute([$idPendaftaran]);
        $tagihan = $stmtTagihan->fetch();

        $this->assertNotFalse($tagihan, 'Tagihan harus tersimpan bersamaan dengan pendaftaran');
        $this->assertEquals(150000.0, (float) $tagihan['total_biaya']);
        $this->assertEquals('BELUM', $tagihan['status_bayar']);
    }

    // =========================================================
    // Integration Test 3: getLaporanKunjungan
    // =========================================================

    /**
     * Memverifikasi bahwa getLaporanKunjungan mengembalikan struktur data
     * yang benar dan semua kolom yang diperlukan ada.
     *
     * Test ini tidak bergantung pada data tertentu (dapat berjalan
     * dengan data dummy kosong sekalipun) karena hanya memvalidasi struktur.
     */
    #[Test]
    public function get_laporan_kunjungan_mengembalikan_struktur_data_yang_benar(): void
    {
        $result = $this->repo->getLaporanKunjungan(10, 2024);

        // Validasi bahwa return value adalah array
        $this->assertIsArray($result, 'getLaporanKunjungan harus mengembalikan array');

        if (count($result) > 0) {
            $firstRow = $result[0];

            // Validasi semua kolom yang dibutuhkan endpoint ada
            $this->assertArrayHasKey('nama_dokter',        $firstRow);
            $this->assertArrayHasKey('spesialisasi',       $firstRow);
            $this->assertArrayHasKey('total_kunjungan',    $firstRow);
            $this->assertArrayHasKey('total_pendapatan',   $firstRow);
            $this->assertArrayHasKey('rata_rata_kepuasan', $firstRow);

            // Validasi tipe data (setelah casting di laporan_dokter.php)
            $this->assertIsNumeric($firstRow['total_kunjungan'],
                'total_kunjungan harus numerik');
            $this->assertIsNumeric($firstRow['total_pendapatan'],
                'total_pendapatan harus numerik');
            $this->assertIsNumeric($firstRow['rata_rata_kepuasan'],
                'rata_rata_kepuasan harus numerik');
        } else {
            // Jika tidak ada data untuk Oktober 2024, test tetap valid
            // karena query berhasil dijalankan tanpa error
            $this->assertCount(0, $result);
        }
    }

    // =========================================================
    // Integration Test 4: Validasi Parameter Repository
    // =========================================================

    /**
     * Memverifikasi bahwa getLaporanKunjungan melempar exception
     * untuk parameter bulan yang tidak valid.
     */
    #[Test]
    public function get_laporan_kunjungan_throw_exception_untuk_bulan_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repo->getLaporanKunjungan(13, 2024); // bulan 13 tidak valid
    }

    /**
     * Memverifikasi bahwa getLaporanKunjungan melempar exception
     * untuk parameter tahun yang tidak valid.
     */
    #[Test]
    public function get_laporan_kunjungan_throw_exception_untuk_tahun_invalid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->repo->getLaporanKunjungan(10, 1999); // tahun sebelum 2000 tidak valid
    }
}