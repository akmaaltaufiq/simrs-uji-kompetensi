<?php
/**
 * TarifCalculatorTest.php
 * Unit test untuk TarifCalculator — dijalankan dengan PHPUnit 10
 *
 * Jalankan: php vendor/bin/phpunit --testdox tests/TarifCalculatorTest.php
 *
 * Beberapa test di bawah akan GAGAL karena ada bug di TarifCalculator.php.
 * Tugas asesi: temukan root cause dan perbaiki bug hingga seluruh test PASS.
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

require_once __DIR__ . '/../src/TarifCalculator.php';

class TarifCalculatorTest extends TestCase
{
    private TarifCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TarifCalculator();
    }


    /** @test */
    public function pasien_umum_tanpa_diskon_dikenai_pajak_saja(): void
    {
        $patient    = ['age' => 30, 'is_bpjs' => false];
        $treatments = [['cost' => 100000, 'name' => 'Konsultasi']];

        $result = $this->calculator->calculate($patient, $treatments);

        // 100000 + (100000 * 0.011) = 101100
        $this->assertEqualsWithDelta(101100.0, $result, 0.01);
    }

    /** @test */
    public function pasien_bpjs_mendapat_diskon_15_persen(): void
    {
        $patient    = ['age' => 30, 'is_bpjs' => true];
        $treatments = [['cost' => 200000, 'name' => 'Rawat Inap']];

        $result = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta(171870.0, $result, 0.01);
    }

    /** @test */
    public function pasien_senior_tepat_60_tahun_mendapat_diskon_senior(): void
    {
        $patient    = ['age' => 60, 'is_bpjs' => false];
        $treatments = [['cost' => 100000, 'name' => 'Pemeriksaan']];

        $result = $this->calculator->calculate($patient, $treatments);

        // base=100000, senior_disc=10000, after=90000, tax=990, total=90990
        $this->assertEqualsWithDelta(90990.0, $result, 0.01);
    }

    /** @test */
    public function pasien_bpjs_senior_mendapat_diskon_bertumpuk(): void
    {
        $patient    = ['age' => 65, 'is_bpjs' => true];
        $treatments = [['cost' => 300000, 'name' => 'Operasi Minor']];

        $result = $this->calculator->calculate($patient, $treatments);

        // base=300000
        // bpjs_disc = 300000 * 0.15 = 45000, sisa = 255000
        // senior_disc = 255000 * 0.10 = 25500, sisa = 229500
        // tax = 229500 * 0.011 = 2524.5
        // total = 232024.5
        $this->assertEqualsWithDelta(232024.5, $result, 0.01);
    }

    
    /** @test */
    public function treatments_kosong_harus_throw_exception(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Treatments tidak boleh kosong');

        $patient = ['age' => 30, 'is_bpjs' => false];
        $this->calculator->calculate($patient, []);
    }

    /** @test */
    public function diskon_tidak_boleh_melebihi_base_biaya(): void
    {
        $patient    = ['age' => 65, 'is_bpjs' => true];
        $treatments = [['cost' => -50000, 'name' => 'Refund Salah Input']];
        
        $this->expectException(InvalidArgumentException::class);
    }

    /** @test */
    public function get_discount_percentage_dengan_base_nol_harus_throw_exception(): void
    {
        // BUG #2 lanjutan: division by zero pada getDiscountPercentage
        $this->expectException(DivisionByZeroError::class);

        $this->calculator->getDiscountPercentage(0, 0);
    }

    // =========================================================
    // PARAMETERIZED TEST
    // =========================================================

    public static function tarifDataProvider(): array
    {
        return [
            'umum_muda'           => [['age' => 25, 'is_bpjs' => false], 500000, 505500.0],
            'umum_senior_61'      => [['age' => 61, 'is_bpjs' => false], 500000, 455955.0],
            'bpjs_muda'           => [['age' => 25, 'is_bpjs' => true],  500000, 429250.0],
            'bpjs_senior_60'      => [['age' => 60, 'is_bpjs' => true],  500000, 386302.5],
        ];
    }

    #[DataProvider('tarifDataProvider')]
    public function test_kalkulasi_tarif_berbagai_skenario(
        array $patient,
        float $biaya,
        float $expected
    ): void {
        $treatments = [['cost' => $biaya, 'name' => 'Tindakan']];
        $result     = $this->calculator->calculate($patient, $treatments);

        $this->assertEqualsWithDelta($expected, $result, 0.1,
            "Kalkulasi salah untuk pasien usia {$patient['age']}, BPJS=" .
            ($patient['is_bpjs'] ? 'ya' : 'tidak')
        );
    }
}
