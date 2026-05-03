@extends('layouts.admin-portfolio')

@section('title', 'Subscription — '.$business->name.' — '.config('app.name'))

@section('content')
<div class="adm-card" style="max-width:640px;margin-bottom:1rem;">
    <h1 class="adm-page-title" style="font-size:1.25rem;">Subscription: {{ $business->name }}</h1>
    <p class="adm-page-desc"><code>{{ $business->uuid }}</code></p>
    <p style="margin:0.5rem 0 0;font-size:0.9rem;color:var(--adm-muted);">Token balance: <strong>{{ (int) ($business->token_balance ?? 0) }}</strong> · Prices: <a href="{{ route('admin.platform.token-settings.edit') }}">Token pricing</a></p>
</div>

<div class="adm-card" style="max-width:640px;">
    <form method="post" action="{{ route('admin.platform.business-subscriptions.update', $business) }}">
        @csrf
        @method('PUT')
        <div class="adm-field">
            <label class="adm-label" for="subscription_status">Access status</label>
            <select class="adm-input" id="subscription_status" name="subscription_status" required>
                @foreach (['active' => 'Active (full access)', 'trialing' => 'Trialing (until trial end)', 'inactive' => 'Inactive (blocked)'] as $val => $label)
                    <option value="{{ $val }}" @selected(old('subscription_status', $business->subscription_status) === $val)>{{ $label }}</option>
                @endforeach
            </select>
            <p style="margin:0.35rem 0 0;font-size:0.82rem;color:var(--adm-muted);"><strong>Inactive</strong> stops catalog/POS until you activate again. Leave billing period empty for active with no fixed end.</p>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="subscription_plan_id">Plan</label>
            <select class="adm-input" id="subscription_plan_id" name="subscription_plan_id">
                <option value="">— None (use for inactive only) —</option>
                @foreach ($plans as $p)
                    <option value="{{ $p->id }}" @selected((string) old('subscription_plan_id', $business->subscription_plan_id) === (string) $p->id)>{{ $p->name }} ({{ $p->slug }})</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="subscription_trial_ends_at">Trial ends (local time)</label>
            <input class="adm-input" type="datetime-local" id="subscription_trial_ends_at" name="subscription_trial_ends_at" value="{{ old('subscription_trial_ends_at', $business->subscription_trial_ends_at?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}">
            <p style="margin:0.35rem 0 0;font-size:0.82rem;color:var(--adm-muted);">Required when status is <strong>Trialing</strong>.</p>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="subscription_current_period_end">Billing period ends (optional)</label>
            <input class="adm-input" type="datetime-local" id="subscription_current_period_end" name="subscription_current_period_end" value="{{ old('subscription_current_period_end', $business->subscription_current_period_end?->timezone(config('app.timezone'))->format('Y-m-d\TH:i')) }}">
            <p style="margin:0.35rem 0 0;font-size:0.82rem;color:var(--adm-muted);">Only used when <strong>Active</strong>. Empty = no automatic expiry.</p>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="token_credit_adjust">Adjust token credits</label>
            <input class="adm-input" type="number" step="1" id="token_credit_adjust" name="token_credit_adjust" value="{{ old('token_credit_adjust') }}" placeholder="e.g. 500 to add, or -100 to remove">
            <p style="margin:0.35rem 0 0;font-size:0.82rem;color:var(--adm-muted);">Positive adds tokens; negative removes (cannot go below zero).</p>
        </div>
        <div style="margin-top:1rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
            <button type="submit" class="adm-btn adm-btn-primary">Save</button>
            <a href="{{ route('admin.platform.business-subscriptions.index') }}" class="adm-btn adm-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
