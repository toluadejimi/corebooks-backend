<?php

namespace App\Services;

/**
 * Nigeria payroll approximations for SMEs (NGN, monthly inputs).
 *
 * - Pension (Pension Reform Act): 8% employee + 10% employer on monthly emoluments
 *   (basic + housing + transport). Employer portion is informational only on payslip.
 * - NHF (National Housing Fund Act): 2.5% of monthly basic salary (employee).
 * - PAYE: Sixth Schedule progressive bands applied to annual chargeable income after
 *   Consolidated Relief Allowance (CRA) per ITA (simplified max-of-two-components rule).
 *
 * This is not legal or tax advice; thresholds and rates change with Finance Acts — adjust as needed.
 */
class NigeriaPayrollCalculator
{
    /** @var list<array{width: float, rate: float}> slices of annual chargeable income, rates 7%–24% */
    private const ANNUAL_PAYE_SLICES = [
        ['width' => 300_000, 'rate' => 0.07],
        ['width' => 300_000, 'rate' => 0.11],
        ['width' => 500_000, 'rate' => 0.15],
        ['width' => 500_000, 'rate' => 0.19],
        ['width' => 1_600_000, 'rate' => 0.21],
    ];

    private const TOP_PAYE_RATE = 0.24;

    /**
     * @return array{
     *   gross_salary: float,
     *   pension_employee: float,
     *   pension_employer: float,
     *   nhf: float,
     *   paye: float,
     *   cra_annual: float,
     *   chargeable_income_annual: float,
     *   net_salary: float
     * }
     */
    public function computeMonthly(
        float $basic,
        float $housing,
        float $transport,
        float $other,
    ): array {
        $basic = round(max(0, $basic), 2);
        $housing = round(max(0, $housing), 2);
        $transport = round(max(0, $transport), 2);
        $other = round(max(0, $other), 2);

        $gross = round($basic + $housing + $transport + $other, 2);
        $pensionBase = round($basic + $housing + $transport, 2);
        $pensionBase = min($pensionBase, $gross);

        $pensionEmployee = round($pensionBase * 0.08, 2);
        $pensionEmployer = round($pensionBase * 0.10, 2);
        $nhf = round($basic * 0.025, 2);

        $grossAnnual = $gross * 12;
        $pensionAnnual = $pensionEmployee * 12;
        $nhfAnnual = $nhf * 12;

        $craAnnual = $this->consolidatedReliefAnnual($grossAnnual);
        $chargeableAnnual = max(0, round($grossAnnual - $pensionAnnual - $nhfAnnual - $craAnnual, 2));
        $annualTax = $this->annualPayeOnChargeable($chargeableAnnual);
        $paye = round($annualTax / 12, 2);

        $net = round($gross - $pensionEmployee - $nhf - $paye, 2);

        return [
            'gross_salary' => $gross,
            'pension_employee' => $pensionEmployee,
            'pension_employer' => $pensionEmployer,
            'nhf' => $nhf,
            'paye' => $paye,
            'cra_annual' => round($craAnnual, 2),
            'chargeable_income_annual' => $chargeableAnnual,
            'net_salary' => $net,
        ];
    }

    private function consolidatedReliefAnnual(float $grossAnnual): float
    {
        $g = max(0, $grossAnnual);
        $a = 200_000 + 0.20 * $g;
        $b = 0.01 * $g + 0.0035 * max($g - 200_000, 0);

        return round(max($a, $b), 2);
    }

    private function annualPayeOnChargeable(float $chargeableAnnual): float
    {
        if ($chargeableAnnual <= 0) {
            return 0.0;
        }

        $rem = $chargeableAnnual;
        $tax = 0.0;

        foreach (self::ANNUAL_PAYE_SLICES as $slice) {
            $w = $slice['width'];
            $r = $slice['rate'];
            $chunk = min($rem, $w);
            $tax += $chunk * $r;
            $rem -= $chunk;
            if ($rem <= 1e-6) {
                return round($tax, 2);
            }
        }

        $tax += $rem * self::TOP_PAYE_RATE;

        return round($tax, 2);
    }
}
