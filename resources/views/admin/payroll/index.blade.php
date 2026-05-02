@extends('layouts.admin-workspace')

@section('title', 'Payroll — '.$business->name)

@section('content')
<h1 class="adm-page-title">Payroll</h1>
<p class="adm-page-desc">
    Nigeria-style monthly payroll: pension (8% / 10%), NHF (2.5% of basic), and PAYE on chargeable income after CRA.
    Managers create a period, enter allowances per team member, then finalise so staff can view payslips in the mobile app.
</p>

@if($canManagePayroll)
    <div class="adm-card" style="margin-bottom:1.25rem;">
        <h2 class="adm-page-title" style="font-size:1.05rem;">New payroll month</h2>
        <p class="adm-page-desc" style="margin-bottom:1rem;">Use the first day of the month (YYYY-MM).</p>
        <form method="post" action="{{ route('admin.b.payroll.store', $business) }}" class="adm-inline-form">
            @csrf
            <div class="adm-field">
                <label class="adm-label" for="period">Period (YYYY-MM)</label>
                <input class="adm-input" id="period" name="period" type="text" required pattern="\d{4}-\d{2}" placeholder="{{ now()->format('Y-m') }}" value="{{ old('period', now()->format('Y-m')) }}">
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="height:42px;">Create draft</button>
        </form>
    </div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Period</th>
                <th>Status</th>
                <th>Lines</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($runs as $r)
                <tr>
                    <td>{{ $r->period_on?->format('F Y') ?? '—' }}</td>
                    <td><span class="adm-role-pill" style="font-size:0.65rem;">{{ $r->status }}</span></td>
                    <td>{{ $r->lines_count ?? $r->lines()->count() }}</td>
                    <td><a href="{{ route('admin.b.payroll.show', [$business, $r]) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.8rem;">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="4" style="color:var(--adm-muted);">No payroll periods yet.@if($canManagePayroll) Create a draft month above.@else Finalised payslips will appear here once your manager posts payroll.@endif</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
