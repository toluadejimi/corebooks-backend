@extends('layouts.admin-portfolio')

@section('title', 'Business subscriptions — '.config('app.name'))

@section('content')
<div class="adm-card" style="margin-bottom:1rem;">
    <h1 class="adm-page-title" style="font-size:1.35rem;">Business subscriptions</h1>
    <p class="adm-page-desc">Assign plans and set access to <strong>active</strong> (paid or complimentary), <strong>trialing</strong> (trial end date), or <strong>inactive</strong> (workspace blocked for POS/catalog).</p>
</div>

<div class="adm-card">
    @if ($businesses->isEmpty())
        <p style="color:var(--adm-muted);">No businesses in the system.</p>
    @else
        <table class="adm-table" style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid var(--adm-border);">
                    <th style="padding:0.5rem;">Business</th>
                    <th style="padding:0.5rem;">Plan</th>
                    <th style="padding:0.5rem;">Status</th>
                    <th style="padding:0.5rem;">Trial ends</th>
                    <th style="padding:0.5rem;">Period ends</th>
                    <th style="padding:0.5rem;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($businesses as $b)
                    <tr style="border-bottom:1px solid var(--adm-border);">
                        <td style="padding:0.65rem 0.5rem;">
                            <strong>{{ $b->name }}</strong><br>
                            <code style="font-size:0.75rem;color:var(--adm-muted);">{{ $b->uuid }}</code>
                        </td>
                        <td style="padding:0.65rem 0.5rem;">{{ $b->subscriptionPlan?->name ?? '—' }}</td>
                        <td style="padding:0.65rem 0.5rem;"><span class="adm-role-pill">{{ $b->subscription_status }}</span></td>
                        <td style="padding:0.65rem 0.5rem;">{{ $b->subscription_trial_ends_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                        <td style="padding:0.65rem 0.5rem;">{{ $b->subscription_current_period_end?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                        <td style="padding:0.65rem 0.5rem;">
                            <a href="{{ route('admin.platform.business-subscriptions.edit', $b) }}" class="adm-btn adm-btn-ghost" style="height:34px;">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        <div style="margin-top:1rem;">{{ $businesses->links() }}</div>
    @endif
</div>

<p style="margin-top:1rem;"><a href="{{ route('dashboard') }}">← Portfolio</a></p>
@endsection
