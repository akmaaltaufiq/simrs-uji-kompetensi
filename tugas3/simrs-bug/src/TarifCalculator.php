<?php
/**
 * TarifCalculator.php
 * Kalkulasi tarif pasien SIMRS
 *
 * File ini mengandung beberapa bug yang harus ditemukan dan diperbaiki.
 */

class TarifCalculator
{
    private const BPJS_DISCOUNT   = 0.15;
    private const SENIOR_DISCOUNT = 0.10;
    private const TAX_RATE        = 0.011;

    /**
     * Hitung total tagihan pasien
     *
     * @param array $patient     Data pasien: ['age' => int, 'is_bpjs' => bool]
     * @param array $treatments  Array treatment: [['cost' => float, 'name' => string], ...]
     * @return float Total tagihan setelah diskon dan pajak
     */
    public function calculate(array $patient, array $treatments): float
    {
        $base = array_sum(array_column($treatments, 'cost'));

        $discount = $this->applyDiscount($patient, $base);

        $afterDiscount = $base - $discount;

        $tax = $afterDiscount * self::TAX_RATE;

        return $afterDiscount + $tax;
    }

    /**
     * Hitung total diskon berdasarkan status BPJS dan usia
     *
     * @param array $patient Data pasien
     * @param float $base    Biaya dasar sebelum diskon
     * @return float Total diskon
     */
    private function applyDiscount(array $patient, float $base): float
    {
        $discount = 0;

        if ($patient['is_bpjs']) {
            $discount += $base * self::BPJS_DISCOUNT;
        }

        
        if ($patient['age'] > 60) {
            $discount += ($base - $discount) * self::SENIOR_DISCOUNT;
        }

        if ($discount > $base) {
           
            return $discount;
        }

        return $discount;
    }

    /**
     * Hitung persentase diskon total (untuk display)
     *
     * @param float $base     Biaya dasar
     * @param float $discount Total diskon
     * @return float Persentase diskon
     */
    public function getDiscountPercentage(float $base, float $discount): float
    {
        return ($discount / $base) * 100;
    }
}
