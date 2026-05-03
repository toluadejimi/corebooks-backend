@extends('layouts.admin-portfolio')

@section('title', 'Loan applications — '.config('app.name'))

@section('content')
<div class="adm-card" style="margin-bottom:1rem;">
    <h1 class="adm-page-title" style="font-size:1.35rem;">Loan applications</h1>
    <p class="adm-page-desc">Submitted by businesses on Pro / Pro Plus plans.</p>
</div>

<div class="adm-card">
    @if ($applications->isEmpty())
        <p style="color:var(--adm-muted);">No applications yet.</p>
    @else
        <table class="adm-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid var(--adm-border);">
                    <th style="padding:0.5rem;">Business</th>
                    <th style="padding:0.5rem;">Bank</th>
                    <th style="padding:0.5rem;">Status</th>
                    <th style="padding:0.5rem;">Updated</th>
                    <th style="padding:0.5rem;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($applications as $a)
                    <tr style="border-bottom:1px solid var(--adm-border);">
                        <td style="padding:0.65rem 0.5rem;">{{ $a->business?->name ?? '—' }}</td>
                        <td style="padding:0.65rem 0.5rem;">{{ $a->partnerBank?->name ?? '—' }}</td>
                        <td style="padding:0.65rem 0.5rem;"><span class="adm-role-pill">{{ $a->status }}</span></td>
                        <td style="padding:0.65rem 0.5rem;">{{ $a->updated_at?->format('Y-m-d H:i') }}</td>
                        <td style="padding:0.65rem 0.5rem;"><a href="{{ route('admin.platform.loans.show', $a) }}" class="adm-btn adm-btn-ghost" style="height:34px;">Review</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:1rem;">{{ $applications->links() }}</div>
    @endif
</div>
<p><a href="{{ route('dashboard') }}">← Portfolio</a></p>
@endsection
