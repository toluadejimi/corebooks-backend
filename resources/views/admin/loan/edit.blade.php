@extends('layouts.admin-workspace')

@section('title', 'Business loan — '.$business->name)

@section('content')
<div class="adm-card" style="margin-bottom:1rem;">
    <h1 class="adm-page-title" style="font-size:1.35rem;">Business loan application</h1>
    @unless($loanEnabled)
        <p class="adm-page-desc" style="color:var(--adm-warn, #b45309);">Your current plan does not include loan access. Upgrade to <strong>Pro</strong> or <strong>Pro Plus</strong> in the mobile app subscription flow.</p>
    @else
        <p class="adm-page-desc">Provide tax and CAC details. Save a draft, then submit for review.</p>
    @endunless
</div>

@if($loanEnabled && $canManage)
    <div class="adm-card">
        <form method="post" action="{{ route('admin.b.loan.update', $business) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')
            <div class="adm-field">
                <label class="adm-label" for="tax_id">Tax ID</label>
                <input class="adm-input" id="tax_id" name="tax_id" value="{{ old('tax_id', $application?->tax_id) }}">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="cac_registration_number">CAC registration number</label>
                <input class="adm-input" id="cac_registration_number" name="cac_registration_number" value="{{ old('cac_registration_number', $application?->cac_registration_number) }}">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="cac_certificate_url">CAC certificate URL (or upload below)</label>
                <input class="adm-input" id="cac_certificate_url" name="cac_certificate_url" value="{{ old('cac_certificate_url', $application?->cac_certificate_url) }}">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="certificate_file">Upload certificate (PDF or image)</label>
                <input class="adm-input" id="certificate_file" name="certificate_file" type="file" accept=".pdf,image/*">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="loan_partner_bank_id">Partner bank</label>
                <select class="adm-input" id="loan_partner_bank_id" name="loan_partner_bank_id">
                    <option value="">— Choose later (draft) —</option>
                    @foreach ($banks as $b)
                        <option value="{{ $b->id }}" @selected((string) old('loan_partner_bank_id', $application?->loan_partner_bank_id) === (string) $b->id)>
                            {{ $b->name }} (₦{{ number_format((float) $b->min_amount_ngn, 0) }} – ₦{{ number_format((float) $b->max_amount_ngn, 0) }})
                        </option>
                    @endforeach
                </select>
                @error('loan_partner_bank_id')<p style="color:var(--adm-danger);font-size:0.85rem;margin:0.25rem 0 0;">{{ $message }}</p>@enderror
                @if($banks->isEmpty())
                    <p style="color:var(--adm-warn, #b45309);font-size:0.85rem;margin:0.35rem 0 0;">No partner banks are configured yet. Ask your platform admin to add banks under Portfolio → Partner banks before you can submit.</p>
                @endif
            </div>
            <div class="adm-field">
                <label class="adm-label" for="loan_amount_requested">Loan amount requested (NGN)</label>
                <input class="adm-input" id="loan_amount_requested" name="loan_amount_requested" type="number" step="0.01" min="0" value="{{ old('loan_amount_requested', $application?->loan_amount_requested) }}">
                @error('loan_amount_requested')<p style="color:var(--adm-danger);font-size:0.85rem;margin:0.25rem 0 0;">{{ $message }}</p>@enderror
            </div>
            <div class="adm-field">
                <label class="adm-label" for="purpose">Purpose</label>
                <textarea class="adm-input" id="purpose" name="purpose" rows="4">{{ old('purpose', $application?->purpose) }}</textarea>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="business_summary">Business summary</label>
                <textarea class="adm-input" id="business_summary" name="business_summary" rows="4">{{ old('business_summary', $application?->business_summary) }}</textarea>
            </div>
            <p style="margin:0.5rem 0;color:var(--adm-muted);font-size:0.85rem;">Status: <strong>{{ $application?->status ?? 'not started' }}</strong></p>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.75rem;">
                <button type="submit" class="adm-btn adm-btn-primary">Save draft</button>
            </div>
        </form>
        @if($application && $application->status === 'draft')
            <form method="post" action="{{ route('admin.b.loan.submit', $business) }}" style="margin-top:1rem;border-top:1px solid var(--adm-border);padding-top:1rem;">
                @csrf
                <input type="hidden" name="loan_partner_bank_id" value="{{ $application->loan_partner_bank_id }}">
                <input type="hidden" name="tax_id" value="{{ $application->tax_id }}">
                <input type="hidden" name="cac_registration_number" value="{{ $application->cac_registration_number }}">
                <input type="hidden" name="cac_certificate_url" value="{{ $application->cac_certificate_url }}">
                <input type="hidden" name="loan_amount_requested" value="{{ $application->loan_amount_requested }}">
                <input type="hidden" name="purpose" value="{{ $application->purpose }}">
                @if($banks->isEmpty())
                    <p style="color:var(--adm-muted);">Submit is unavailable until partner banks exist.</p>
                @elseif(!$application->loan_partner_bank_id)
                    <p style="color:var(--adm-muted);">Save a draft with a <strong>partner bank</strong> selected before submitting.</p>
                @else
                    <button type="submit" class="adm-btn adm-btn-primary">Submit for review</button>
                @endif
            </form>
        @endif
    </div>
@elseif($loanEnabled && !$canManage)
    <div class="adm-card"><p>Only managers and owners can edit this application.</p></div>
@endif
@endsection
