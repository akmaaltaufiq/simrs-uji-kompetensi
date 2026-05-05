<?php
/**
 * TarifCalculator.php
 * Kalkulasi tarif pasien SIMRS
 *
 * Versi final (fixed) — semua bug telah diperbaiki
 */

class TarifCalculator
{
    private const BPJS_DISCOUNT   = 0.15;
    private const SENIOR_DISCOUNT = 0.10;
    private const TAX_RATE        = 0.011;

    /**
     * Hitung total tagihan pasien setelah diskon dan pajak.
     *
     * Urutan perhitungan:
     *  1. Jumlahkan semua biaya treatment (base)
     *  2. Hitung total diskon (BPJS + Senior, dihitung dari base, bukan bertingkat)
     *  3. Kurangi diskon dari base
     *  4. Tambahkan pajak 1.1% dari hasil setelah diskon
     *
     * @param array $patient
     * @param array $treatments
     * @return float
     */
    public function calculate(array $patient, array $treatments): float
    {
        // VALIDASI: treatments tidak boleh kosong
        if (empty($treatments)) {
            throw new InvalidArgumentException('Treatments tidak boleh kosong');
        }

        $base = array_sum(array_column($treatments, 'cost'));

        // VALIDASI: tidak boleh negatif
        if ($base < 0) {
            throw new InvalidArgumentException('Total biaya tidak boleh negatif');
        }

        $discount = $this->applyDiscount($patient, $base);

        $afterDiscount = $base - $discount;

        $tax = $afterDiscount * self::TAX_RATE;

        return $afterDiscount + $tax;
    }

    /**
     * Hitung diskon total (flat dari base, bukan sequential)
     */
    private function applyDiscount(array $patient, float $base): float
    {
        $discount = 0;

        // Diskon BPJS
        if ($patient['is_bpjs']) {
            $discount += $base * self::BPJS_DISCOUNT;
        }

        // Diskon Senior (>= 60)
        if ($patient['age'] >= 60) {
            $discount += $base * self::SENIOR_DISCOUNT;
        }

        // VALIDASI: diskon tidak boleh melebihi base
        if ($discount > $base) {
            throw new InvalidArgumentException('Diskon melebihi biaya dasar');
        }

        return $discount;
    }

    /**
     * Hitung persentase diskon
     */
    public function getDiscountPercentage(float $base, float $discount): float
    {
        if ($base == 0) {
            throw new DivisionByZeroError('Base biaya tidak boleh nol');
        }

        return ($discount / $base) * 100;
    }
}