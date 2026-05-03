<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessLoanApplication;
use App\Models\LoanPartnerBank;
use App\Services\SubscriptionAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoanApplicationController extends Controller
{
    public function __construct(
        private readonly SubscriptionAccess $subscriptionAccess,
    ) {}

    public function show(Request $request, Business $business): JsonResponse
    {
        if (! $this->subscriptionAccess->allowsLoanFeature($business)) {
            return response()->json(['message' => 'Loan applications require a Pro or Pro Plus plan.'], 403);
        }

        $app = BusinessLoanApplication::query()->where('business_id', $business->id)->with('partnerBank')->first();

        return response()->json([
            'data' => $app ? $this->serialize($app) : null,
        ]);
    }

    public function update(Request $request, Business $business): JsonResponse
    {
        if (! $this->subscriptionAccess->allowsLoanFeature($business)) {
            return response()->json(['message' => 'Loan applications require a Pro or Pro Plus plan.'], 403);
        }

        $app = BusinessLoanApplication::query()->firstOrNew(['business_id' => $business->id]);

        if (in_array($app->status, ['submitted', 'under_review', 'approved'], true)) {
            throw ValidationException::withMessages([
                'status' => ['This application cannot be edited in its current state.'],
            ]);
        }

        if ($app->status === 'rejected') {
            $app->status = 'draft';
        }

        $data = $request->validate([
            'loan_partner_bank_id' => ['nullable', 'integer', 'exists:loan_partner_banks,id'],
            'tax_id' => ['nullable', 'string', 'max:128'],
            'cac_registration_number' => ['nullable', 'string', 'max:128'],
            'cac_certificate_url' => ['nullable', 'string', 'max:2048'],
            'additional_documents' => ['nullable', 'array'],
            'loan_amount_requested' => ['nullable', 'numeric', 'min:0'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'business_summary' => ['nullable', 'string', 'max:5000'],
        ]);

        if (isset($data['loan_partner_bank_id'])) {
            $this->assertActiveBankId($data['loan_partner_bank_id']);
        }

        if (! $app->exists) {
            $app->uuid = (string) Str::uuid();
            $app->status = 'draft';
        }

        $app->fill($data);
        $app->save();

        $bank = $app->loan_partner_bank_id
            ? LoanPartnerBank::query()->whereKey($app->loan_partner_bank_id)->first()
            : null;
        if ($bank !== null && $app->loan_amount_requested !== null) {
            $this->assertAmountForBank($bank, (float) $app->loan_amount_requested);
        }

        return response()->json(['data' => $this->serialize($app->fresh('partnerBank'))]);
    }

    public function submit(Request $request, Business $business): JsonResponse
    {
        if (! $this->subscriptionAccess->allowsLoanFeature($business)) {
            return response()->json(['message' => 'Loan applications require a Pro or Pro Plus plan.'], 403);
        }

        $app = BusinessLoanApplication::query()->where('business_id', $business->id)->first();
        if ($app === null) {
            throw ValidationException::withMessages([
                'loan' => ['Save your application as a draft before submitting.'],
            ]);
        }

        if ($app->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => ['Only draft applications can be submitted.'],
            ]);
        }

        $data = $request->validate([
            'loan_partner_bank_id' => ['required', 'integer', 'exists:loan_partner_banks,id'],
            'tax_id' => ['required', 'string', 'max:128'],
            'cac_registration_number' => ['required', 'string', 'max:128'],
            'cac_certificate_url' => ['required', 'string', 'max:2048'],
            'loan_amount_requested' => ['required', 'numeric', 'min:1'],
            'purpose' => ['required', 'string', 'max:5000'],
        ]);

        $bank = $this->assertActiveBankId((int) $data['loan_partner_bank_id']);
        $this->assertAmountForBank($bank, (float) $data['loan_amount_requested']);

        $app->update([
            'status' => 'submitted',
            'loan_partner_bank_id' => $bank->id,
            'tax_id' => $data['tax_id'],
            'cac_registration_number' => $data['cac_registration_number'],
            'cac_certificate_url' => $data['cac_certificate_url'],
            'loan_amount_requested' => $data['loan_amount_requested'],
            'purpose' => $data['purpose'],
        ]);

        return response()->json(['data' => $this->serialize($app->fresh('partnerBank'))]);
    }

    public function uploadDocument(Request $request, Business $business): JsonResponse
    {
        if (! $this->subscriptionAccess->allowsLoanFeature($business)) {
            return response()->json(['message' => 'Loan applications require a Pro or Pro Plus plan.'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:pdf,jpeg,jpg,png,webp', 'max:12288'],
        ]);

        $file = $request->file('file');
        $path = $file->store("businesses/{$business->id}/loan", 'public');
        $relative = Storage::disk('public')->url($path);
        $url = str_starts_with($relative, 'http')
            ? $relative
            : rtrim($request->root() ?: (string) config('app.url'), '/').'/'.ltrim($relative, '/');

        return response()->json(['url' => $url, 'path' => $path], 201);
    }

    private function assertActiveBankId(int $id): LoanPartnerBank
    {
        $bank = LoanPartnerBank::query()->whereKey($id)->where('is_active', true)->first();
        if ($bank === null) {
            throw ValidationException::withMessages([
                'loan_partner_bank_id' => ['Select a valid partner bank from the list.'],
            ]);
        }

        return $bank;
    }

    private function assertAmountForBank(LoanPartnerBank $bank, float $amount): void
    {
        if (! $bank->amountIsAllowed($amount)) {
            throw ValidationException::withMessages([
                'loan_amount_requested' => [
                    sprintf(
                        'For %s, amount must be between ₦%s and ₦%s.',
                        $bank->name,
                        number_format((float) $bank->min_amount_ngn, 2),
                        number_format((float) $bank->max_amount_ngn, 2),
                    ),
                ],
            ]);
        }
    }

    private function serialize(BusinessLoanApplication $a): array
    {
        $a->loadMissing('partnerBank');

        return [
            'uuid' => $a->uuid,
            'status' => $a->status,
            'loan_partner_bank_id' => $a->loan_partner_bank_id,
            'partner_bank' => $a->partnerBank?->toApiArray(),
            'tax_id' => $a->tax_id,
            'cac_registration_number' => $a->cac_registration_number,
            'cac_certificate_url' => $a->cac_certificate_url,
            'additional_documents' => $a->additional_documents,
            'loan_amount_requested' => $a->loan_amount_requested !== null ? (float) $a->loan_amount_requested : null,
            'purpose' => $a->purpose,
            'business_summary' => $a->business_summary,
            'admin_notes' => $a->admin_notes,
            'reviewed_at' => $a->reviewed_at?->toIso8601String(),
        ];
    }
}
