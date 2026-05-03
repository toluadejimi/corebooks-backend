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
                <label class="adm-label" for="partner_bank_search">Partner bank</label>
                <input type="search" class="adm-input" id="partner_bank_search" autocomplete="off" placeholder="Type to filter banks…" style="margin-bottom:0.5rem;">
                <select class="adm-input" id="loan_partner_bank_id" name="loan_partner_bank_id" size="{{ $banks->isEmpty() ? 1 : min(8, max(3, $banks->count() + 1)) }}" style="height:auto;max-height:14rem;">
                    <option value="" data-label="">— Choose later (draft) —</option>
                    @foreach ($banks as $b)
                        <option
                            value="{{ $b->id }}"
                            data-label="{{ strtolower($b->name.' '.$b->slug) }}"
                            @selected((string) old('loan_partner_bank_id', $application?->loan_partner_bank_id) === (string) $b->id)
                        >
                            {{ $b->name }} (₦{{ number_format((float) $b->min_amount_ngn, 0) }} – ₦{{ number_format((float) $b->max_amount_ngn, 0) }})
                        </option>
                    @endforeach
                </select>
                @unless($banks->isEmpty())
                    <div id="partner_bank_chips" style="display:flex;flex-wrap:wrap;gap:0.5rem;margin-top:0.65rem;">
                        @foreach ($banks as $b)
                            <button type="button" class="adm-btn adm-btn-ghost partner-bank-chip" data-bank-id="{{ $b->id }}" data-label="{{ strtolower($b->name.' '.$b->slug) }}" style="display:inline-flex;align-items:center;gap:0.45rem;padding:0.35rem 0.55rem;border-radius:10px;border:1px solid var(--adm-border);">
                                @if(!empty($b->logo_url))
                                    <img src="{{ $b->logo_url }}" alt="" width="28" height="28" style="object-fit:contain;border-radius:6px;">
                                @else
                                    <span style="width:28px;height:28px;display:inline-flex;align-items:center;justify-content:center;background:var(--adm-border);border-radius:6px;font-size:0.75rem;">₦</span>
                                @endif
                                <span style="font-size:0.88rem;max-width:10rem;text-align:left;line-height:1.2;">{{ $b->name }}</span>
                            </button>
                        @endforeach
                    </div>
                @endunless
                @error('loan_partner_bank_id')<p style="color:var(--adm-danger);font-size:0.85rem;margin:0.25rem 0 0;">{{ $message }}</p>@enderror
                @if($banks->isEmpty())
                    <p style="color:var(--adm-warn, #b45309);font-size:0.85rem;margin:0.35rem 0 0;">No partner banks are configured yet. Ask your platform admin to add banks under Portfolio → Partner banks before you can submit.</p>
                @endif
            </div>
            @unless($banks->isEmpty())
            <script>
            (function () {
                var search = document.getElementById('partner_bank_search');
                var sel = document.getElementById('loan_partner_bank_id');
                if (!search || !sel) return;
                function norm(s) { return (s || '').toLowerCase().trim(); }
                function applyFilter() {
                    var q = norm(search.value);
                    var opts = sel.querySelectorAll('option');
                    opts.forEach(function (opt) {
                        if (opt.value === '') {
                            opt.hidden = false;
                            return;
                        }
                        var lab = opt.getAttribute('data-label') || opt.textContent || '';
                        opt.hidden = q.length > 0 && lab.indexOf(q) === -1;
                    });
                    document.querySelectorAll('.partner-bank-chip').forEach(function (chip) {
                        var lab = chip.getAttribute('data-label') || '';
                        chip.style.display = (q.length === 0 || lab.indexOf(q) !== -1) ? 'inline-flex' : 'none';
                    });
                }
                search.addEventListener('input', applyFilter);
                sel.addEventListener('change', function () {
                    document.querySelectorAll('.partner-bank-chip').forEach(function (chip) {
                        var on = chip.getAttribute('data-bank-id') === sel.value;
                        chip.style.outline = on ? '2px solid var(--adm-primary, #2563eb)' : '';
                        chip.style.outlineOffset = on ? '2px' : '';
                    });
                });
                document.querySelectorAll('.partner-bank-chip').forEach(function (chip) {
                    chip.addEventListener('click', function () {
                        sel.value = chip.getAttribute('data-bank-id');
                        sel.dispatchEvent(new Event('change'));
                    });
                });
                applyFilter();
                sel.dispatchEvent(new Event('change'));
            })();
            </script>
            @endunless
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
