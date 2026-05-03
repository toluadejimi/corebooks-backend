@extends('layouts.admin-portfolio')

@section('title', 'Your businesses — '.config('app.name'))

@section('content')
<div class="adm-portfolio-hero">
    <h1 class="adm-page-title" style="font-size:2rem;">Business portfolio</h1>
    <p class="adm-page-desc">Connect to a business to manage catalog, stock, and team. Register a new business anytime — you become the owner.</p>
</div>

@php($platformEmails = config('salesapp.platform_admin_emails', []))
@php($isPlatformAdmin = in_array(strtolower($user->email), $platformEmails, true))
@if ($isPlatformAdmin)
    <div class="adm-card" style="margin-bottom:2rem;border-left:4px solid var(--adm-accent, #6366f1);">
        <h2 class="adm-page-title" style="font-size:1.1rem;">Platform administration</h2>
        <p class="adm-page-desc" style="margin-bottom:0.75rem;">Configure subscription plans, partner banks, and review loan applications.</p>
        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
            <a href="{{ route('admin.platform.plans.index') }}" class="adm-btn adm-btn-primary">Subscription plans</a>
            <a href="{{ route('admin.platform.loan-banks.index') }}" class="adm-btn adm-btn-ghost">Partner banks</a>
            <a href="{{ route('admin.platform.loans.index') }}" class="adm-btn adm-btn-ghost">Loan applications</a>
            @if (config('salesapp.allow_web_migrations', true))
                <form method="post" action="{{ route('admin.platform.migrations.run') }}" style="display:inline;margin:0;" onsubmit="return confirm('Run pending database migrations? This is for servers without SSH. Continue?');">
                    @csrf
                    <button type="submit" class="adm-btn adm-btn-ghost" style="border:1px dashed var(--adm-border);">Run migrations</button>
                </form>
            @endif
        </div>
        @if (session('migration_log'))
            <details style="margin-top:1rem;" open>
                <summary style="cursor:pointer;color:var(--adm-muted);font-size:0.9rem;">Migration output</summary>
                <pre style="margin:0.75rem 0 0;padding:1rem;background:var(--adm-bg);border:1px solid var(--adm-border);border-radius:var(--radius, 14px);font-size:0.78rem;overflow:auto;max-height:22rem;white-space:pre-wrap;">{{ session('migration_log') }}</pre>
            </details>
        @endif
    </div>
@endif

<div class="adm-card" style="margin-bottom:2rem;">
    <h2 class="adm-page-title" style="font-size:1.15rem;">Register a new business</h2>
    <p class="adm-page-desc" style="margin-bottom:1rem;">Creates a default location (Main) and assigns you as <strong>owner</strong>.</p>
    <form method="post" action="{{ route('admin.businesses.store') }}" class="adm-inline-form">
        @csrf
        <div class="adm-field">
            <label class="adm-label" for="biz_name">Business name</label>
            <input class="adm-input" id="biz_name" name="name" type="text" required maxlength="255" placeholder="e.g. Tolu Retail Ltd" value="{{ old('name') }}">
        </div>
        <button type="submit" class="adm-btn adm-btn-primary" style="height:42px;">Create business</button>
    </form>
</div>

<h2 class="adm-page-title" style="font-size:1.15rem;margin-bottom:1rem;">Your businesses</h2>
@if ($businesses->isEmpty())
    <div class="adm-card">
        <p style="color:var(--adm-muted);margin:0;">You are not linked to any business yet. Create one above.</p>
    </div>
@else
    <div class="adm-portfolio-grid">
        @foreach ($businesses as $b)
            @php($role = \App\Enums\BusinessRole::normalize($b->pivot->role))
            <article class="adm-biz-card">
                <h3>{{ $b->name }}</h3>
                <p style="margin:0;color:var(--adm-muted);font-size:0.85rem;">{{ $b->currency }} · VAT {{ $b->default_vat_rate }}%</p>
                <span class="adm-role-pill" style="display:inline-block;margin-top:0.25rem;">{{ $role->value }}</span>
                <div class="adm-actions" style="margin-top:auto;padding-top:0.5rem;">
                    <a href="{{ route('admin.b.overview', $b) }}" class="adm-btn adm-btn-primary">Connect &amp; manage</a>
                </div>
            </article>
        @endforeach
    </div>
@endif
@endsection
