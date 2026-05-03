<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessLoanApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanApplicationPlatformWebController extends Controller
{
    public function index(Request $request): View
    {
        $applications = BusinessLoanApplication::query()
            ->with(['business', 'partnerBank'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        return view('admin.platform.loans.index', [
            'user' => $request->user(),
            'applications' => $applications,
        ]);
    }

    public function show(Request $request, BusinessLoanApplication $loanApplication): View
    {
        $loanApplication->load(['business', 'reviewer', 'partnerBank']);

        return view('admin.platform.loans.show', [
            'user' => $request->user(),
            'application' => $loanApplication,
        ]);
    }

    public function update(Request $request, BusinessLoanApplication $loanApplication): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:draft,submitted,under_review,approved,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:8000'],
        ]);

        $loanApplication->admin_notes = $data['admin_notes'] ?? $loanApplication->admin_notes;
        $loanApplication->status = $data['status'];
        if (in_array($data['status'], ['approved', 'rejected', 'under_review'], true)) {
            $loanApplication->reviewed_by = $request->user()->id;
            $loanApplication->reviewed_at = now();
        }
        $loanApplication->save();

        return redirect()->route('admin.platform.loans.show', $loanApplication)->with('status', 'Application updated.');
    }
}
