<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', $business->name.' — '.config('app.name'))</title>
    @include('layouts.partials.admin-styles')
</head>
<body class="adm-body">
    <button type="button" class="adm-nav-overlay" aria-label="Close menu"></button>
    <header class="adm-topbar">
        <div class="adm-topbar-start">
            <button type="button" class="adm-icon-btn adm-nav-toggle" aria-label="Open menu">☰</button>
            <a href="{{ route('dashboard') }}" class="adm-brand">{{ config('app.name') }}<span>.</span></a>
        </div>
        <div class="adm-actions">
            <button type="button" class="adm-icon-btn adm-theme-btn" aria-label="Color theme">◐</button>
            <span class="adm-user-email">{{ $user->email }}</span>
            <form method="post" action="{{ route('logout') }}" style="display:inline;">@csrf
                <button type="submit" class="adm-btn adm-btn-ghost">Sign out</button>
            </form>
        </div>
    </header>
    <div class="adm-shell">
        <aside class="adm-sidebar">
            <div class="adm-biz-name">
                {{ $business->name }}
                <span class="adm-role-pill">{{ $memberRole->value }}</span>
            </div>
            <nav class="adm-nav">
                <a href="{{ route('admin.b.overview', $business) }}" class="{{ request()->routeIs('admin.b.overview') ? 'active' : '' }}">
                    <span class="adm-nav-icon">◆</span> Overview
                </a>
                <a href="{{ route('admin.b.reports.index', $business) }}" class="{{ request()->routeIs('admin.b.reports.*') ? 'active' : '' }}">
                    <span class="adm-nav-icon">⊞</span> Reports
                </a>
                <a href="{{ route('admin.b.products.index', $business) }}" class="{{ request()->routeIs('admin.b.products.*') ? 'active' : '' }}">
                    <span class="adm-nav-icon">▤</span> Products
                </a>
                <a href="{{ route('admin.b.stock.index', $business) }}" class="{{ request()->routeIs('admin.b.stock.*') ? 'active' : '' }}">
                    <span class="adm-nav-icon">▣</span> Stock &amp; batches
                </a>
                <a href="{{ route('admin.b.purchases.index', $business) }}" class="{{ request()->routeIs('admin.b.purchases.*') ? 'active' : '' }}">
                    <span class="adm-nav-icon">⎘</span> Purchases
                </a>
                <a href="{{ route('admin.b.team.index', $business) }}" class="{{ request()->routeIs('admin.b.team.*') ? 'active' : '' }}">
                    <span class="adm-nav-icon">◎</span> Team
                    @unless($canManage)<span style="font-size:0.65rem;opacity:0.7;">view</span>@endunless
                </a>
                <a href="{{ route('admin.b.payroll.index', $business) }}" class="{{ request()->routeIs('admin.b.payroll.*') ? 'active' : '' }}">
                    <span class="adm-nav-icon">₦</span> Payroll
                </a>
                <a href="{{ route('admin.b.settings.edit', $business) }}" class="{{ request()->routeIs('admin.b.settings.*') ? 'active' : '' }}">
                    <span class="adm-nav-icon">⚙</span> Settings
                </a>
            </nav>
            <div class="adm-sidebar-foot">
                <a href="{{ route('dashboard') }}">← All businesses</a>
                <div style="margin-top:0.5rem;opacity:0.8;">Disconnect workspace</div>
            </div>
        </aside>
        <main class="adm-main">
            @if (session('status'))
                <div class="adm-flash ok">{{ session('status') }}</div>
            @endif
            @if ($errors->any())
                <div class="adm-flash err">
                    @foreach ($errors->all() as $e){{ $e }}@if(!$loop->last)<br>@endif @endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    @include('layouts.partials.admin-shell-scripts')
</body>
</html>
