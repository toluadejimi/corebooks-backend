<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanPartnerBank;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoanPartnerBankWebController extends Controller
{
    public function index(Request $request): View
    {
        $banks = LoanPartnerBank::query()->orderBy('sort_order')->orderBy('name')->get();

        return view('admin.platform.loan-banks.index', [
            'user' => $request->user(),
            'banks' => $banks,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.platform.loan-banks.form', [
            'user' => $request->user(),
            'bank' => new LoanPartnerBank([
                'sort_order' => 100,
                'is_active' => true,
                'min_amount_ngn' => 100000,
                'max_amount_ngn' => 5000000,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LoanPartnerBank::query()->create($this->validated($request));

        return redirect()->route('admin.platform.loan-banks.index')->with('status', 'Partner bank created.');
    }

    public function edit(Request $request, LoanPartnerBank $loanPartnerBank): View
    {
        return view('admin.platform.loan-banks.form', [
            'user' => $request->user(),
            'bank' => $loanPartnerBank,
        ]);
    }

    public function update(Request $request, LoanPartnerBank $loanPartnerBank): RedirectResponse
    {
        $loanPartnerBank->update($this->validated($request, $loanPartnerBank->id));

        return redirect()->route('admin.platform.loan-banks.index')->with('status', 'Partner bank updated.');
    }

    public function destroy(Request $request, LoanPartnerBank $loanPartnerBank): RedirectResponse
    {
        if ($loanPartnerBank->loanApplications()->exists()) {
            return back()->withErrors(['bank' => 'This bank is linked to loan applications and cannot be deleted. Deactivate it instead.']);
        }
        $loanPartnerBank->delete();

        return redirect()->route('admin.platform.loan-banks.index')->with('status', 'Partner bank removed.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'];
        $slugRule[] = $ignoreId !== null
            ? 'unique:loan_partner_banks,slug,'.$ignoreId
            : 'unique:loan_partner_banks,slug';

        $data = $request->validate([
            'slug' => $slugRule,
            'name' => ['required', 'string', 'max:255'],
            'min_amount_ngn' => ['required', 'numeric', 'min:0'],
            'max_amount_ngn' => ['required', 'numeric', 'min:0', 'gte:min_amount_ngn'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        return [
            'slug' => $data['slug'],
            'name' => $data['name'],
            'min_amount_ngn' => $data['min_amount_ngn'],
            'max_amount_ngn' => $data['max_amount_ngn'],
            'notes' => $data['notes'] ?? null,
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
