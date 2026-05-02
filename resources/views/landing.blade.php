@extends('layouts.site')

@section('title', config('app.name').' — Inventory, POS & accounting')

@push('styles')
<style>
    .nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.35rem 0;
    }
    .logo {
        font-family: "Outfit", sans-serif;
        font-weight: 700;
        font-size: 1.35rem;
        letter-spacing: -0.03em;
        color: var(--text);
    }
    .logo span { color: var(--accent); }
    .nav-links { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
    .hero {
        padding: 3.5rem 0 4rem;
        display: grid;
        gap: 2.5rem;
        align-items: center;
    }
    @media (min-width: 960px) {
        .hero {
            grid-template-columns: 1.1fr 0.9fr;
            padding: 5rem 0 5.5rem;
        }
    }
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
        padding: 0.4rem 0.95rem;
        border-radius: 999px;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #c4d4ff;
        border: 1px solid rgba(91, 140, 255, 0.35);
        background: rgba(91, 140, 255, 0.12);
        margin-bottom: 1.25rem;
    }
    .hero-copy h1 {
        font-family: "Outfit", sans-serif;
        font-size: clamp(2.35rem, 4.5vw, 3.35rem);
        font-weight: 700;
        letter-spacing: -0.035em;
        line-height: 1.08;
        margin-bottom: 1.1rem;
    }
    .hero-copy p.lead {
        color: var(--muted);
        font-size: 1.125rem;
        line-height: 1.65;
        max-width: 46ch;
        margin-bottom: 1.75rem;
    }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; }
    .hero-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
        margin-top: 2rem;
        padding-top: 1.75rem;
        border-top: 1px solid var(--border);
    }
    .hero-meta div strong {
        font-family: "Outfit", sans-serif;
        font-size: 1.35rem;
        font-weight: 700;
        display: block;
        color: var(--text);
    }
    .hero-meta div span { font-size: 0.8rem; color: var(--muted); }
    .hero-panel {
        border-radius: 20px;
        border: 1px solid var(--border);
        background: linear-gradient(165deg, rgba(91, 140, 255, 0.14) 0%, rgba(18, 22, 36, 0.85) 45%, rgba(15, 18, 28, 0.92) 100%);
        padding: 1.5rem;
        box-shadow: 0 24px 80px -20px rgba(0, 0, 0, 0.55);
    }
    .hero-panel-inner {
        border-radius: 14px;
        border: 1px solid rgba(255,255,255,0.06);
        background: rgba(0,0,0,0.35);
        padding: 1.25rem 1.35rem;
        font-size: 0.8125rem;
        color: var(--muted);
        line-height: 1.55;
    }
    .hero-panel-inner .row {
        display: flex;
        justify-content: space-between;
        padding: 0.45rem 0;
        border-bottom: 1px solid rgba(255,255,255,0.05);
    }
    .hero-panel-inner .row:last-child { border-bottom: none; }
    .hero-panel-inner .row b { color: #e8ecf4; font-weight: 600; }
    .section-title {
        font-family: "Outfit", sans-serif;
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-bottom: 0.5rem;
        text-align: center;
    }
    .section-sub {
        text-align: center;
        color: var(--muted);
        max-width: 52ch;
        margin: 0 auto 2.5rem;
        font-size: 1rem;
    }
    .bento {
        display: grid;
        gap: 1rem;
        padding-bottom: 5rem;
    }
    @media (min-width: 768px) {
        .bento { grid-template-columns: repeat(3, 1fr); }
    }
    .bento .card {
        transition: transform 0.18s ease, border-color 0.18s ease;
    }
    .bento .card:hover {
        transform: translateY(-3px);
        border-color: rgba(91, 140, 255, 0.25);
    }
    .bento .card.wide {
        grid-column: 1 / -1;
    }
    @media (min-width: 768px) {
        .bento .card.wide { grid-column: span 2; }
    }
    .feat-icon {
        width: 44px; height: 44px;
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(91, 140, 255, 0.25), rgba(52, 211, 153, 0.12));
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.25rem;
    }
    .bento h3 {
        font-family: "Outfit", sans-serif;
        font-size: 1.08rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .bento p { color: var(--muted); font-size: 0.9rem; line-height: 1.55; margin: 0; }
    footer {
        border-top: 1px solid var(--border);
        padding: 2rem 0;
        text-align: center;
        color: var(--muted);
        font-size: 0.875rem;
    }
</style>
@endpush

@section('content')
<div class="container">
    <nav class="nav">
        <div class="logo">{{ config('app.name') }}<span>.</span></div>
        <div class="nav-links">
            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-primary" style="padding:0.55rem 1.1rem;font-size:0.875rem;">Dashboard</a>
            @else
                <a href="{{ url('/up') }}" class="btn btn-ghost" style="padding:0.55rem 1rem;font-size:0.875rem;">API status</a>
                <a href="{{ route('login') }}" class="btn btn-primary" style="padding:0.55rem 1.1rem;font-size:0.875rem;">Admin sign in</a>
            @endauth
        </div>
    </nav>

    <section class="hero">
        <div class="hero-copy">
            <div class="badge">POS · Stock · Reports · Roles</div>
            <h1>Operate the store with clarity, online or off</h1>
            <p class="lead">
                Multi-location inventory with batches, VAT-aware pricing, team roles,
                and accounting-style reports — one stack for the floor and the back office.
            </p>
            <div class="hero-actions">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary">Go to dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-primary">Open admin console</a>
                    <a href="{{ route('login') }}" class="btn btn-ghost">Workspace sign in</a>
                @endauth
            </div>
            <div class="hero-meta">
                <div><strong>API-first</strong><span>Mobile &amp; integrations</span></div>
                <div><strong>Multi-tenant</strong><span>Isolated businesses</span></div>
                <div><strong>Sanctum</strong><span>Secure sessions &amp; tokens</span></div>
            </div>
        </div>
        <div class="hero-panel" aria-hidden="true">
            <div style="font-size:0.7rem;text-transform:uppercase;letter-spacing:0.12em;color:#8b95a8;margin-bottom:0.75rem;">Live snapshot</div>
            <div class="hero-panel-inner">
                <div class="row"><span>Today revenue</span><b>₦ 128,400</b></div>
                <div class="row"><span>Low stock SKUs</span><b>6</b></div>
                <div class="row"><span>Stock value (cost)</span><b>₦ 2.4M</b></div>
                <div class="row"><span>Net (30d est.)</span><b style="color:#6ee7b7;">+ ₦ 312K</b></div>
            </div>
            <p style="margin:1rem 0 0;font-size:0.75rem;color:#64748b;line-height:1.45;">Illustrative numbers — connect your workspace for real data.</p>
        </div>
    </section>

    <h2 class="section-title">Built for retail operations</h2>
    <p class="section-sub">From shelf to ledger: keep staff aligned, inventory accurate, and owners informed.</p>

    <section class="bento">
        <article class="card wide">
            <div class="feat-icon">📦</div>
            <h3>Inventory that matches reality</h3>
            <p>Products per business, batches with quantities and expiry, locations, and low-stock signals so replenishment stays ahead of stockouts.</p>
        </article>
        <article class="card">
            <div class="feat-icon">⚡</div>
            <h3>Roles &amp; sync</h3>
            <p>Owner, manager, and sales roles with API + mobile. Pull catalog when online; structured endpoints for POS flows.</p>
        </article>
        <article class="card">
            <div class="feat-icon">📊</div>
            <h3>Reporting suite</h3>
            <p>Daily sales, date ranges, P&amp;L with COGS estimate, product performance, payment mix, and expenses in one place.</p>
        </article>
    </section>
</div>

<footer>
    <div class="container">
        {{ config('app.name') }} · Laravel {{ app()->version() }}
    </div>
</footer>
@endsection
