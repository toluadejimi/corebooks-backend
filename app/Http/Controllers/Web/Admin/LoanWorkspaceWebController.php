<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\BusinessLoanApplication;
use App\Models\LoanPartnerBank;
use App\Services\SubscriptionAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LoanWorkspaceWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly SubscriptionAccess $subscriptionAccess,
    ) {}

    public function edit(Request $request, Business $business): View
    {
        $application = BusinessLoanApplication::query()->where('business_id', $business->id)->first();

        $loanEnabled = $this->subscriptionAccess->allowsLoanFeature($business);

        $banks = LoanPartnerBank::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.loan.edit', $this->workspace($request, $business) + [
            'application' => $application,
            'loanEnabled' => $loanEnabled,
            'banks' => $banks,
        ]);
    }

    public function update(Request $request, Business $business): RedirectResponse
    {
        $application = BusinessLoanApplication::query()->firstOrNew(['business_id' => $business->id]);
        if (! $application->exists) {
            $application->uuid = (string) Str::uuid();
            $application->status = 'draft';
        }

        if (in_array($application->status, ['submitted', 'under_review', 'approved'], true)) {
            return back()->withErrors(['loan' => 'This application is locked.']);
        }

        if ($application->status === 'rejected') {
            $application->status = 'draft';
        }

        $data = $request->validate([
            'loan_partner_bank_id' => [
                'nullable',
                'integer',
                Rule::exists('loan_partner_banks', 'id')->where('is_active', true),
            ],
            'tax_id' => ['nullable', 'string', 'max:128'],
            'cac_registration_number' => ['nullable', 'string', 'max:128'],
            'cac_certificate_url' => ['nullable', 'string', 'max:2048'],
            'loan_amount_requested' => ['nullable', 'numeric', 'min:0'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'business_summary' => ['nullable', 'string', 'max:5000'],
            'certificate_file' => ['nullable', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:12288'],
        ]);

        if ($request->hasFile('certificate_file')) {
            $path = $request->file('certificate_file')->store("businesses/{$business->id}/loan", 'public');
            $relative = Storage::disk('public')->url($path);
            $data['cac_certificate_url'] = str_starts_with($relative, 'http')
                ? $relative
                : rtrim($request->root() ?: (string) config('app.url'), '/').'/'.ltrim($relative, '/');
        }
        unset($data['certificate_file']);

        $application->fill($data);
        $application->save();
        $application->refresh();

        if ($application->loan_partner_bank_id !== null && $application->loan_amount_requested !== null) {
            $bank = LoanPartnerBank::query()
                ->whereKey($application->loan_partner_bank_id)
                ->where('is_active', true)
                ->first();
            if ($bank === null) {
                return back()->withErrors([
                    'loan_partner_bank_id' => 'Selected bank is no longer available. Pick another from the list.',
                ])->withInput();
            }
            $amt = (float) $application->loan_amount_requested;
            if ($amt > 0 && ! $bank->amountIsAllowed($amt)) {
                return back()->withErrors([
                    'loan_amount_requested' => sprintf(
                        'For %s, amount must be between ₦%s and ₦%s.',
                        $bank->name,
                        number_format((float) $bank->min_amount_ngn, 2),
                        number_format((float) $bank->max_amount_ngn, 2),
                    ),
                ])->withInput();
            }
        }

        return redirect()->route('admin.b.loan.edit', $business)->with('status', 'Draft saved.');
    }

    public function submit(Request $request, Business $business): RedirectResponse
    {
        $application = BusinessLoanApplication::query()->where('business_id', $business->id)->first();
        if ($application === null) {
            return back()->withErrors(['loan' => 'Save a draft before submitting.']);
        }

        if ($application->status !== 'draft') {
            return back()->withErrors(['loan' => 'Only draft applications can be submitted.']);
        }

        $validated = $request->validate([
            'loan_partner_bank_id' => [
                'required',
                'integer',
                Rule::exists('loan_partner_banks', 'id')->where('is_active', true),
            ],
            'tax_id' => ['required', 'string', 'max:128'],
            'cac_registration_number' => ['required', 'string', 'max:128'],
            'cac_certificate_url' => ['required', 'string', 'max:2048'],
            'loan_amount_requested' => ['required', 'numeric', 'min:1'],
            'purpose' => ['required', 'string', 'max:5000'],
        ]);

        $bank = LoanPartnerBank::query()
            ->whereKey((int) $validated['loan_partner_bank_id'])
            ->where('is_active', true)
            ->firstOrFail();

        if (! $bank->amountIsAllowed((float) $validated['loan_amount_requested'])) {
            return back()->withErrors([
                'loan_amount_requested' => sprintf(
                    'For %s, amount must be between ₦%s and ₦%s.',
                    $bank->name,
                    number_format((float) $bank->min_amount_ngn, 2),
                    number_format((float) $bank->max_amount_ngn, 2),
                ),
            ]);
        }

        $application->update([
            'status' => 'submitted',
            'loan_partner_bank_id' => $bank->id,
            'tax_id' => $validated['tax_id'],
            'cac_registration_number' => $validated['cac_registration_number'],
            'cac_certificate_url' => $validated['cac_certificate_url'],
            'loan_amount_requested' => $validated['loan_amount_requested'],
            'purpose' => $validated['purpose'],
        ]);

        return redirect()->route('admin.b.loan.edit', $business)->with('status', 'Application submitted for review.');
    }
}
