@extends('layouts.admin-portfolio')

@section('title', 'Service application — '.config('app.name'))

@section('content')
<div class="adm-card" style="margin-bottom:1rem;">
    <h1 class="adm-page-title" style="font-size:1.25rem;">Service application</h1>
    <p class="adm-page-desc"><strong>{{ $application->business?->name }}</strong> · {{ $application->extraService?->title }}</p>
</div>

<div class="adm-card" style="margin-bottom:1rem;">
    <h2 class="adm-page-title" style="font-size:1rem;">Details</h2>
    <dl style="display:grid;grid-template-columns:160px 1fr;gap:0.35rem 1rem;margin:0.75rem 0;">
        <dt style="color:var(--adm-muted);">Status</dt><dd><span class="adm-role-pill">{{ $application->status }}</span></dd>
        <dt style="color:var(--adm-muted);">Fee (catalog)</dt><dd>₦{{ number_format((float) ($application->extraService?->fee_amount_ngn ?? 0), 2) }}</dd>
        <dt style="color:var(--adm-muted);">Applicant notes</dt><dd style="white-space:pre-wrap;">{{ $application->applicant_notes ?: '—' }}</dd>
        @if(!empty($application->applicant_payload) && is_array($application->applicant_payload))
            <dt style="color:var(--adm-muted);">Form answers</dt>
            <dd style="grid-column:1/-1;">
                <dl style="display:grid;grid-template-columns:140px 1fr;gap:0.25rem 0.75rem;margin:0;">
                    @foreach ($application->applicant_payload as $k => $v)
                        <dt style="color:var(--adm-muted);font-size:0.9em;">{{ $k }}</dt>
                        <dd style="white-space:pre-wrap;margin:0;">{{ is_scalar($v) ? (string) $v : json_encode($v) }}</dd>
                    @endforeach
                </dl>
            </dd>
        @endif
        <dt style="color:var(--adm-muted);">Submitted</dt><dd>{{ $application->created_at?->format('Y-m-d H:i') }}</dd>
    </dl>
</div>

<div class="adm-card">
    <h2 class="adm-page-title" style="font-size:1rem;">Update</h2>
    <form method="post" action="{{ route('admin.platform.extra-service-applications.update', $application) }}" style="margin-top:0.75rem;">
        @csrf
        @method('PUT')
        <div class="adm-field">
            <label class="adm-label" for="status">Status</label>
            <select class="adm-input" id="status" name="status" required>
                @foreach (['pending','in_progress','completed','rejected'] as $st)
                    <option value="{{ $st }}" @selected(old('status', $application->status) === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="admin_notes">Internal notes</label>
            <textarea class="adm-input" id="admin_notes" name="admin_notes" rows="4">{{ old('admin_notes', $application->admin_notes) }}</textarea>
        </div>
        <button type="submit" class="adm-btn adm-btn-primary">Save</button>
    </form>
</div>

<p style="margin-top:1rem;"><a href="{{ route('admin.platform.extra-service-applications.index') }}">← All applications</a></p>
@endsection
