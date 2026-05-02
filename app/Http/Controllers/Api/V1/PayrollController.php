<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\PayrollLine;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayrollService $payroll,
    ) {}

    public function myPayslips(Request $request, Business $business): JsonResponse
    {
        $lines = $this->payroll->myPayslips($request->user(), $business);

        return response()->json([
            'data' => $lines->map(fn (PayrollLine $l) => $this->linePayload($l, includeMeta: true)),
        ]);
    }

    public function indexRuns(Request $request, Business $business): JsonResponse
    {
        $this->requireManager($request);

        $runs = $this->payroll->listRuns($business);

        return response()->json([
            'data' => $runs->map(fn (PayrollRun $r) => [
                'uuid' => $r->uuid,
                'period_on' => $r->period_on?->toDateString(),
                'status' => $r->status,
                'lines_count' => $r->lines_count ?? $r->lines()->count(),
            ]),
        ]);
    }

    public function storeRun(Request $request, Business $business): JsonResponse
    {
        $this->requireManager($request);
        $data = $request->validate([
            'period_on' => ['required', 'date'],
        ]);

        try {
            $run = $this->payroll->createRun($business, $data['period_on']);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'uuid' => $run->uuid,
                'period_on' => $run->period_on?->toDateString(),
                'status' => $run->status,
            ],
        ], 201);
    }

    public function showRun(Request $request, Business $business, PayrollRun $run): JsonResponse
    {
        $this->authorizeRunView($request, $business, $run);
        $run->load(['lines.user']);
        /** @var BusinessRole $role */
        $role = $request->attributes->get('business_role');
        $lines = $run->lines;
        if (! $role->atLeast(BusinessRole::Manager)) {
            $lines = $lines->where('user_id', $request->user()->id)->values();
        }

        return response()->json([
            'data' => [
                'uuid' => $run->uuid,
                'period_on' => $run->period_on?->toDateString(),
                'status' => $run->status,
                'lines' => $lines->map(fn (PayrollLine $l) => $this->linePayload($l, includeMeta: true)),
            ],
        ]);
    }

    public function storeLine(Request $request, Business $business, PayrollRun $run): JsonResponse
    {
        $this->requireManager($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $line = $this->payroll->upsertLine(
                $business,
                $run,
                (int) $data['user_id'],
                (float) $data['basic_salary'],
                (float) ($data['housing_allowance'] ?? 0),
                (float) ($data['transport_allowance'] ?? 0),
                (float) ($data['other_allowances'] ?? 0),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->linePayload($line, includeMeta: true)], 201);
    }

    public function finalizeRun(Request $request, Business $business, PayrollRun $run): JsonResponse
    {
        $this->requireManager($request);
        try {
            $run = $this->payroll->finalizeRun($business, $run);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => [
                'uuid' => $run->uuid,
                'period_on' => $run->period_on?->toDateString(),
                'status' => $run->status,
            ],
        ]);
    }

    public function destroyLine(Request $request, Business $business, PayrollRun $run, User $user): JsonResponse
    {
        $this->requireManager($request);
        try {
            $this->payroll->deleteLine($business, $run, $user);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => ['ok' => true]]);
    }

    private function requireManager(Request $request): void
    {
        /** @var BusinessRole $role */
        $role = $request->attributes->get('business_role');
        abort_unless($role->atLeast(BusinessRole::Manager), 403);
    }

    private function authorizeRunView(Request $request, Business $business, PayrollRun $run): void
    {
        abort_unless((int) $run->business_id === (int) $business->id, 404);
        /** @var BusinessRole $role */
        $role = $request->attributes->get('business_role');
        if ($role->atLeast(BusinessRole::Manager)) {
            return;
        }
        $has = $run->lines()->where('user_id', $request->user()->id)->exists();
        abort_unless($has, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function linePayload(PayrollLine $l, bool $includeMeta): array
    {
        $base = [
            'basic_salary' => (float) $l->basic_salary,
            'housing_allowance' => (float) $l->housing_allowance,
            'transport_allowance' => (float) $l->transport_allowance,
            'other_allowances' => (float) $l->other_allowances,
            'gross_salary' => (float) $l->gross_salary,
            'pension_employee' => (float) $l->pension_employee,
            'pension_employer' => (float) $l->pension_employer,
            'nhf' => (float) $l->nhf,
            'paye' => (float) $l->paye,
            'net_salary' => (float) $l->net_salary,
        ];
        if ($includeMeta) {
            $base['run_uuid'] = $l->run?->uuid;
            $base['period_on'] = $l->run?->period_on?->toDateString();
            $base['status'] = $l->run?->status;
            $base['cra_annual'] = $l->cra_annual !== null ? (float) $l->cra_annual : null;
            $base['chargeable_income_annual'] = $l->chargeable_income_annual !== null ? (float) $l->chargeable_income_annual : null;
            $base['employee'] = $l->relationLoaded('user') && $l->user
                ? ['id' => $l->user->id, 'name' => $l->user->name, 'email' => $l->user->email]
                : null;
        }

        return $base;
    }
}
