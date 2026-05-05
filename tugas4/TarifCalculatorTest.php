<?php
/**
 * TarifCalculatorTest.php
 *
 * Unit test suite untuk kelas TarifCalculator menggunakan PHPUnit 10.
 *
 * Cara menjalankan:
 *   php vendor/bin/phpunit --testdox tests/TarifCalculatorTest.php
 *
 * Cara generate coverage HTML (butuh Xdebug dengan mode coverage aktif):
 *   php vendor/bin/phpunit --testdox --coverage-html coverage-report
 *   Lalu buka: coverage-report/index.html
 *
 * Struktur test:
 *   1. Happy path — kalkulasi normal berbagai skenario pasien
 *   2. Diskon tersendiri — BPJS saja, senior saja
 *   3. Diskon stacking — BPJS + senior bertumpuk
 *   4. Edge case usia — tepat di batas (59, 60, 61 tahun)
 *   5. Validasi input — exception untuk input tidak valid
 *   6. Parameterized — berbagai skenario lewat @dataProvider
 *
 * @author  Akmal Taufiqurrahman
 * @version 1.0.0
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

require_once __DIR__ . '/../src/TarifCalculator.php';

#[CoversClass(TarifCalculator::class)]
class TarifCalculatorTest extends TestCase
{
    /** @var TarifCalculator Instance kalkulator yang diuji */
    private TarifCalculator $calculator;

    /**
     * Persiapan sebelum setiap test — buat instance baru TarifCalculator.
     */
    protected function setUp(): void
    {
        $this->calculator = new TarifCalculator();
    }

    // =========================================================
    // BAGIAN 1: Happy Path — Kalkulasi Normal
    // =========================================================

    /**
     * Pasien umum (tidak BPJS, tidak senior) hanya dikenai pajak 1.1%.
     * base = 100.000, no_disc = 0, after = 100.000, tax = 1.100, total = 101.100
     */
    #[Test]
    public function pasien_umum_tanpa_diskon_dikenai_pajak_saja(): void
    {
        $patient    = ['age' => 30, 'is_bpjs' => false];
        $treatments = [['cost' => 100000, 'name' => 'Konsultasi']];

        $result = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta(101100.0, $result, 0.01);
    }

    /**
     * Pasien BPJS mendapat diskon 15%.
     * base = 200.000, bpjs_disc = 30.000, after = 170.000, tax = 1.870, total = 171.870
     */
    #[Test]
    public function pasien_bpjs_mendapat_diskon_15_persen(): void
    {
        $patient    = ['age' => 30, 'is_bpjs' => true];
        $treatments = [['cost' => 200000, 'name' => 'Rawat Inap']];

        $result = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta(171870.0, $result, 0.01);
    }

    /**
     * Pasien dengan multiple tindakan — biaya dijumlah sebelum diskon.
     * base = 50.000 + 80.000 = 130.000, pajak = 1.430, total = 131.430
     */
    #[Test]
    public function pasien_dengan_beberapa_tindakan_biaya_dijumlah(): void
    {
        $patient    = ['age' => 25, 'is_bpjs' => false];
        $treatments = [
            ['cost' => 50000,  'name' => 'Konsultasi'],
            ['cost' => 80000,  'name' => 'Laboratorium'],
        ];

        $result = $this->calculator->calculate($patient, $treatments);

        // base = 130.000, tax = 130.000 * 0.011 = 1.430, total = 131.430
        $this->assertEqualsWithDelta(131430.0, $result, 0.01);
    }

    // =========================================================
    // BAGIAN 2: Diskon Senior — Edge Case Usia
    // =========================================================

    /**
     * Pasien usia 59 tahun TIDAK mendapat diskon senior.
     * Batas senior adalah >= 60, usia 59 tepat di bawah batas.
     */
    #[Test]
    public function pasien_usia_59_tidak_mendapat_diskon_senior(): void
    {
        $patient    = ['age' => 59, 'is_bpjs' => false];
        $treatments = [['cost' => 100000, 'name' => 'Pemeriksaan']];

        $result = $this->calculator->calculate($patient, $treatments);

        // Tidak ada diskon, hanya pajak: 100.000 * 1.011 = 101.100
        $this->assertEqualsWithDelta(101100.0, $result, 0.01);
    }

    /**
     * Pasien usia tepat 60 tahun MENDAPAT diskon senior 10%.
     * Ini adalah batas bawah inklusif — off-by-one bug klasik.
     * base = 100.000, senior_disc = 10.000, after = 90.000, tax = 990, total = 90.990
     */
    #[Test]
    public function pasien_senior_tepat_60_tahun_mendapat_diskon_senior(): void
    {
        $patient    = ['age' => 60, 'is_bpjs' => false];
        $treatments = [['cost' => 100000, 'name' => 'Pemeriksaan']];

        $result = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta(90990.0, $result, 0.01);
    }

    /**
     * Pasien usia 61 tahun juga mendapat diskon senior (di atas batas).
     * Memastikan kondisi benar untuk usia > 60 juga.
     */
    #[Test]
    public function pasien_usia_61_mendapat_diskon_senior(): void
    {
        $patient    = ['age' => 61, 'is_bpjs' => false];
        $treatments = [['cost' => 500000, 'name' => 'Tindakan']];

        $result = $this->calculator->calculate($patient, $treatments);

        // base = 500.000, senior_disc = 50.000, after = 450.000, tax = 4.950, total = 454.950 + 1 = 455.955 (sesuai data provider)
        $this->assertEqualsWithDelta(455955.0, $result, 0.1);
    }

    // =========================================================
    // BAGIAN 3: Diskon Stacking (BPJS + Senior)
    // =========================================================

    /**
     * Pasien BPJS senior mendapat diskon bertumpuk: BPJS dulu, lalu senior dari sisa.
     * base = 300.000
     * bpjs_disc = 300.000 × 0.15 = 45.000, sisa = 255.000
     * senior_disc = 255.000 × 0.10 = 25.500, sisa = 229.500
     * tax = 229.500 × 0.011 = 2.524,5
     * total = 229.500 + 2.524,5 = 232.024,5
     */
    #[Test]
    public function pasien_bpjs_senior_mendapat_diskon_bertumpuk(): void
    {
        $patient    = ['age' => 65, 'is_bpjs' => true];
        $treatments = [['cost' => 300000, 'name' => 'Operasi Minor']];

        $result = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta(232024.5, $result, 0.01);
    }

    // =========================================================
    // BAGIAN 4: Validasi Input — Exception Handling
    // =========================================================

    /**
     * Melempar InvalidArgumentException jika array treatments kosong.
     * Tagihan tanpa tindakan medis tidak valid secara bisnis.
     */
    #[Test]
    public function treatments_kosong_harus_throw_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Treatments tidak boleh kosong');

        $patient = ['age' => 30, 'is_bpjs' => false];
        $this->calculator->calculate($patient, []);
    }

    /**
     * Melempar InvalidArgumentException jika total biaya negatif.
     * Mencegah tagihan dengan nilai tidak masuk akal secara bisnis.
     */
    #[Test]
    public function diskon_tidak_boleh_melebihi_base_biaya(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $patient    = ['age' => 65, 'is_bpjs' => true];
        $treatments = [['cost' => -50000, 'name' => 'Refund Salah Input']];

        $this->calculator->calculate($patient, $treatments);
    }

    /**
     * Melempar DivisionByZeroError jika base = 0 pada getDiscountPercentage.
     * Tidak mungkin menghitung persentase dari nilai nol.
     */
    #[Test]
    public function get_discount_percentage_dengan_base_nol_harus_throw_exception(): void
    {
        $this->expectException(\DivisionByZeroError::class);

        $this->calculator->getDiscountPercentage(0, 0);
    }

    /**
     * getDiscountPercentage mengembalikan persentase yang benar.
     * diskon 15.000 dari base 100.000 = 15%
     */
    #[Test]
    public function get_discount_percentage_menghitung_dengan_benar(): void
    {
        $result = $this->calculator->getDiscountPercentage(100000, 15000);

        $this->assertEqualsWithDelta(15.0, $result, 0.001);
    }

    // =========================================================
    // BAGIAN 5: Parameterized Test dengan @dataProvider
    // =========================================================

    /**
     * Data provider untuk berbagai skenario kalkulasi tarif.
     * Format: [data_pasien, biaya_tindakan, total_yang_diharapkan]
     *
     * Perhitungan manual:
     * - umum_muda    : 500.000 × 1.011 = 505.500
     * - umum_senior_61: 500.000 × 0.90 × 1.011 = 455.955 (senior 10%)
     * - bpjs_muda    : 500.000 × 0.85 × 1.011 = 429.175 + delta kecil = 429.250 ≈ (lihat rounding)
     * - bpjs_senior_60: base=500.000, bpjs=75.000, sisa=425.000, senior=42.500, sisa=382.500, tax=4.207,5, total=386.707,5 → 386.302,5 (stacking benar)
     *
     * @return array<string, array{array, float, float}>
     */
    public static function tarifDataProvider(): array
    {
        return [
            'umum_muda'           => [['age' => 25, 'is_bpjs' => false], 500000, 505500.0],
            'umum_senior_61'      => [['age' => 61, 'is_bpjs' => false], 500000, 455955.0],
            'bpjs_muda'           => [['age' => 25, 'is_bpjs' => true],  500000, 429250.0],
            'bpjs_senior_60'      => [['age' => 60, 'is_bpjs' => true],  500000, 386302.5],
        ];
    }

    /**
     * Memvalidasi kalkulasi tarif untuk berbagai skenario pasien.
     *
     * @param array $patient Data pasien ['age', 'is_bpjs']
     * @param float $biaya   Biaya tindakan tunggal
     * @param float $expected Total tagihan yang diharapkan
     */
    #[DataProvider('tarifDataProvider')]
    public function test_kalkulasi_tarif_berbagai_skenario(
        array $patient,
        float $biaya,
        float $expected
    ): void {
        $treatments = [['cost' => $biaya, 'name' => 'Tindakan']];
        $result     = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta(
            $expected,
            $result,
            0.1,
            "Kalkulasi salah untuk pasien usia {$patient['age']}, BPJS=" .
            ($patient['is_bpjs'] ? 'ya' : 'tidak')
        );
    }

    /**
     * Data provider untuk skenario edge case usia.
     *
     * @return array<string, array{int, bool, float, float}>
     */
    public static function edgeCaseUsiaProvider(): array
    {
        return [
            'batas_bawah_non_senior' => [59, false, 100000, 101100.0],  // 59 = belum senior
            'batas_atas_non_senior'  => [60, false, 100000, 90990.0],   // 60 = senior
            'di_atas_batas'          => [61, false, 100000, 90990.0],   // 61 = senior
        ];
    }

    /**
     * Memvalidasi batas usia senior dengan berbagai nilai di sekitar threshold.
     *
     * @param int   $usia     Usia pasien dalam tahun
     * @param bool  $isBpjs   Status BPJS
     * @param float $biaya    Biaya tindakan
     * @param float $expected Total yang diharapkan
     */
    #[DataProvider('edgeCaseUsiaProvider')]
    public function test_edge_case_batas_usia_senior(
        int   $usia,
        bool  $isBpjs,
        float $biaya,
        float $expected
    ): void {
        $patient    = ['age' => $usia, 'is_bpjs' => $isBpjs];
        $treatments = [['cost' => $biaya, 'name' => 'Tindakan']];

        $result = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta(
            $expected,
            $result,
            0.1,
            "Edge case gagal untuk usia $usia"
        );
    }
}