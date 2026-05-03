@extends('layouts.admin-portfolio')

@section('title', 'Loan #'.$application->id.' — '.config('app.name'))

@section('content')
<div class="adm-card" style="margin-bottom:1rem;">
    <h1 class="adm-page-title" style="font-size:1.25rem;">Loan application</h1>
    <p class="adm-page-desc"><strong>{{ $application->business?->name }}</strong> · {{ $application->uuid }}</p>
</div>

<div class="adm-card" style="margin-bottom:1rem;">
    <h2 class="adm-page-title" style="font-size:1rem;">Details</h2>
    <dl style="display:grid;grid-template-columns:160px 1fr;gap:0.35rem 1rem;margin:0.75rem 0;">
        <dt style="color:var(--adm-muted);">Tax ID</dt><dd>{{ $application->tax_id ?: '—' }}</dd>
        <dt style="color:var(--adm-muted);">CAC reg.</dt><dd>{{ $application->cac_registration_number ?: '—' }}</dd>
        <dt style="color:var(--adm-muted);">CAC certificate</dt><dd>@if($application->cac_certificate_url)<a href="{{ $application->cac_certificate_url }}" target="_blank" rel="noopener">Open file</a>@else — @endif</dd>
        <dt style="color:var(--adm-muted);">Partner bank</dt><dd>
            @if($application->partnerBank)
                {{ $application->partnerBank->name }}
                <span style="color:var(--adm-muted);font-size:0.9em;">(allowed ₦{{ number_format((float) $application->partnerBank->min_amount_ngn, 2) }} – ₦{{ number_format((float) $application->partnerBank->max_amount_ngn, 2) }})</span>
            @else
                —
            @endif
        </dd>
        <dt style="color:var(--adm-muted);">Amount requested</dt><dd>{{ $application->loan_amount_requested !== null ? '₦'.number_format((float) $application->loan_amount_requested, 2) : '—' }}</dd>
        <dt style="color:var(--adm-muted);">Purpose</dt><dd style="white-space:pre-wrap;">{{ $application->purpose ?: '—' }}</dd>
        <dt style="color:var(--adm-muted);">Summary</dt><dd style="white-space:pre-wrap;">{{ $application->business_summary ?: '—' }}</dd>
    </dl>
</div>

<div class="adm-card">
    <h2 class="adm-page-title" style="font-size:1rem;">Review</h2>
    <form method="post" action="{{ route('admin.platform.loans.update', $application) }}" style="margin-top:0.75rem;">
        @csrf
        @method('PUT')
        <div class="adm-field">
            <label class="adm-label" for="status">Status</label>
            <select class="adm-input" id="status" name="status" required>
                @foreach (['draft','submitted','under_review','approved','rejected'] as $st)
                    <option value="{{ $st }}" @selected(old('status', $application->status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="admin_notes">Internal notes</label>
            <textarea class="adm-input" id="admin_notes" name="admin_notes" rows="4">{{ old('admin_notes', $application->admin_notes) }}</textarea>
        </div>
        <button type="submit" class="adm-btn adm-btn-primary">Save review</button>
    </form>
</div>

<p style="margin-top:1rem;"><a href="{{ route('admin.platform.loans.index') }}">← All applications</a></p>
@endsection
