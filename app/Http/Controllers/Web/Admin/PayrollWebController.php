<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayrollService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class PayrollWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly PayrollService $payroll,
    ) {}

    public function index(Request $request, Business $business): View
    {
        /** @var BusinessRole $role */
        $role = $request->attributes->get('business_role');
        $canManage = $role->atLeast(BusinessRole::Manager);

        if ($canManage) {
            $runs = $this->payroll->listRuns($business);
        } else {
            $runs = PayrollRun::query()
                ->where('business_id', $business->id)
                ->where('status', 'final')
                ->whereHas('lines', fn ($q) => $q->where('user_id', $request->user()->id))
                ->withCount('lines')
                ->orderByDesc('period_on')
                ->get();
        }

        return view('admin.payroll.index', $this->workspace($request, $business) + [
            'runs' => $runs,
            'canManagePayroll' => $canManage,
        ]);
    }

    public function show(Request $request, Business $business, PayrollRun $run): View
    {
        $this->assertRunBusiness($business, $run);
        /** @var BusinessRole $role */
        $role = $request->attributes->get('business_role');
        if (! $role->atLeast(BusinessRole::Manager)) {
            $ok = $run->status === 'final'
                && $run->lines()->where('user_id', $request->user()->id)->exists();
            abort_unless($ok, 403);
        }

        $run->load(['lines.user']);
        if (! $role->atLeast(BusinessRole::Manager)) {
            $run->setRelation(
                'lines',
                $run->lines->where('user_id', $request->user()->id)->values(),
            );
        }
        $members = $role->atLeast(BusinessRole::Manager)
            ? $business->users()->orderBy('name')->get()
            : collect();

        return view('admin.payroll.show', $this->workspace($request, $business) + [
            'run' => $run,
            'members' => $members,
        ]);
    }

    public function storeRun(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-\d{2}$/'],
        ]);
        $periodOn = $data['period'].'-01';

        try {
            $run = $this->payroll->createRun($business, $periodOn);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.b.payroll.index', $business)->withErrors(['period' => $e->getMessage()]);
        }

        return redirect()->route('admin.b.payroll.show', [$business, $run])->with('status', 'Payroll period created.');
    }

    public function storeLine(Request $request, Business $business, PayrollRun $run): RedirectResponse
    {
        $this->assertRunBusiness($business, $run);
        $data = $request->validate([
            'user_id' => ['required', 'integer'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'housing_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $this->payroll->upsertLine(
                $business,
                $run,
                (int) $data['user_id'],
                (float) $data['basic_salary'],
                (float) ($data['housing_allowance'] ?? 0),
                (float) ($data['transport_allowance'] ?? 0),
                (float) ($data['other_allowances'] ?? 0),
            );
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.b.payroll.show', [$business, $run])->withErrors(['line' => $e->getMessage()]);
        }

        return redirect()->route('admin.b.payroll.show', [$business, $run])->with('status', 'Employee line saved.');
    }

    public function destroyLine(Request $request, Business $business, PayrollRun $run, User $user): RedirectResponse
    {
        $this->assertRunBusiness($business, $run);
        try {
            $this->payroll->deleteLine($business, $run, $user);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.b.payroll.show', [$business, $run])->withErrors(['line' => $e->getMessage()]);
        }

        return redirect()->route('admin.b.payroll.show', [$business, $run])->with('status', 'Line removed.');
    }

    public function finalize(Request $request, Business $business, PayrollRun $run): RedirectResponse
    {
        $this->assertRunBusiness($business, $run);
        try {
            $this->payroll->finalizeRun($business, $run);
        } catch (InvalidArgumentException $e) {
            return redirect()->route('admin.b.payroll.show', [$business, $run])->withErrors(['finalize' => $e->getMessage()]);
        }

        return redirect()->route('admin.b.payroll.show', [$business, $run])->with('status', 'Payroll finalised. Staff can view payslips in the app.');
    }

    private function assertRunBusiness(Business $business, PayrollRun $run): void
    {
        abort_unless((int) $run->business_id === (int) $business->id, 404);
    }
}
