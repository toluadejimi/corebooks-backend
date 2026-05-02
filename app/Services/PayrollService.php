<?php

namespace App\Services;

use App\Models\Business;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PayrollService
{
    public function __construct(
        private readonly NigeriaPayrollCalculator $calculator,
    ) {}

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PayrollRun>
     */
    public function listRuns(Business $business)
    {
        return $business->payrollRuns()
            ->orderByDesc('period_on')
            ->withCount('lines')
            ->get();
    }

    public function createRun(Business $business, string $periodYmd): PayrollRun
    {
        $period = CarbonImmutable::parse($periodYmd)->startOfMonth();

        return DB::transaction(function () use ($business, $period) {
            $exists = PayrollRun::query()
                ->where('business_id', $business->id)
                ->whereDate('period_on', $period->toDateString())
                ->lockForUpdate()
                ->exists();
            if ($exists) {
                throw new InvalidArgumentException('A payroll run already exists for that month.');
            }

            return PayrollRun::query()->create([
                'business_id' => $business->id,
                'uuid' => (string) Str::uuid(),
                'period_on' => $period->toDateString(),
                'status' => 'draft',
            ]);
        });
    }

    public function finalizeRun(Business $business, PayrollRun $run): PayrollRun
    {
        $this->assertRunBelongs($business, $run);
        if (! $run->isDraft()) {
            throw new InvalidArgumentException('Payroll is already finalised.');
        }
        if ($run->lines()->doesntExist()) {
            throw new InvalidArgumentException('Add at least one employee line before finalising.');
        }
        $run->status = 'final';
        $run->save();

        return $run->fresh(['lines.user']);
    }

    public function upsertLine(
        Business $business,
        PayrollRun $run,
        int $userId,
        float $basic,
        float $housing,
        float $transport,
        float $other,
    ): PayrollLine {
        $this->assertRunBelongs($business, $run);
        if (! $run->isDraft()) {
            throw new InvalidArgumentException('Cannot edit a finalised payroll.');
        }
        $this->assertUserOnTeam($business, $userId);

        $computed = $this->calculator->computeMonthly($basic, $housing, $transport, $other);

        /** @var PayrollLine $line */
        $line = PayrollLine::query()->updateOrCreate(
            [
                'payroll_run_id' => $run->id,
                'user_id' => $userId,
            ],
            [
                'basic_salary' => $basic,
                'housing_allowance' => $housing,
                'transport_allowance' => $transport,
                'other_allowances' => $other,
                'gross_salary' => $computed['gross_salary'],
                'pension_employee' => $computed['pension_employee'],
                'pension_employer' => $computed['pension_employer'],
                'nhf' => $computed['nhf'],
                'paye' => $computed['paye'],
                'cra_annual' => $computed['cra_annual'],
                'chargeable_income_annual' => $computed['chargeable_income_annual'],
                'net_salary' => $computed['net_salary'],
            ],
        );

        return $line->fresh('user');
    }

    public function deleteLine(Business $business, PayrollRun $run, User $user): void
    {
        $this->assertRunBelongs($business, $run);
        if (! $run->isDraft()) {
            throw new InvalidArgumentException('Cannot edit a finalised payroll.');
        }
        PayrollLine::query()
            ->where('payroll_run_id', $run->id)
            ->where('user_id', $user->id)
            ->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, PayrollLine>
     */
    public function myPayslips(User $user, Business $business)
    {
        return PayrollLine::query()
            ->join('payroll_runs', 'payroll_runs.id', '=', 'payroll_lines.payroll_run_id')
            ->where('payroll_lines.user_id', $user->id)
            ->where('payroll_runs.business_id', $business->id)
            ->where('payroll_runs.status', 'final')
            ->orderByDesc('payroll_runs.period_on')
            ->select('payroll_lines.*')
            ->with(['run', 'user'])
            ->get();
    }

    private function assertRunBelongs(Business $business, PayrollRun $run): void
    {
        if ((int) $run->business_id !== (int) $business->id) {
            abort(404);
        }
    }

    private function assertUserOnTeam(Business $business, int $userId): void
    {
        $ok = $business->users()->where('users.id', $userId)->exists();
        if (! $ok) {
            throw new InvalidArgumentException('User is not a member of this business.');
        }
    }
}
