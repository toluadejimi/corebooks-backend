<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="mobile-web-app-capable" content="yes">
    <title>@yield('title', $business->name.' — POS')</title>
    @include('layouts.partials.admin-styles')
    <style>
        body.adm-body.pos-terminal { overflow: hidden; height: 100dvh; }
        .pos-terminal .adm-topbar { padding: 0.35rem 0.65rem; min-height: 44px; }
        .pos-terminal .adm-topbar .adm-user-email,
        .pos-terminal .adm-topbar a[href*="privacy"],
        .pos-terminal .adm-topbar a[href*="passkey"] { display: none; }
        .pos-terminal .adm-brand { font-size: 0.95rem; }
        .pos-terminal .pos-topbar-title {
            font-family: Outfit, sans-serif;
            font-weight: 700;
            font-size: 0.95rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 42vw;
        }
        .pos-terminal .adm-shell { display: block; min-height: 0; height: calc(100dvh - 44px); }
        .pos-terminal .adm-sidebar { display: none !important; }
        .pos-terminal .adm-main {
            padding: 0.65rem 0.65rem 0;
            height: 100%;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .pos-terminal .adm-nav-toggle { display: none; }
        .pos-terminal .adm-nav-overlay { display: none !important; }
    </style>
</head>
<body class="adm-body pos-terminal">
    <header class="adm-topbar">
        <div class="adm-topbar-start">
            <a href="{{ route('admin.b.overview', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.3rem 0.5rem;font-size:0.8rem;margin-right:0.35rem;">←</a>
            <span class="pos-topbar-title">{{ $business->name }}</span>
        </div>
        <div class="adm-actions">
            <span class="adm-user-email">{{ $user->email }}</span>
            <form method="post" action="{{ route('logout') }}" style="display:inline;">@csrf
                <button type="submit" class="adm-btn adm-btn-ghost" style="padding:0.3rem 0.5rem;font-size:0.8rem;">Out</button>
            </form>
        </div>
    </header>
    <div class="adm-shell">
        <main class="adm-main">
            @if ($errors->any())
                <div class="adm-flash err" style="margin-bottom:0.5rem;">
                    @foreach ($errors->all() as $e){{ $e }}@if(!$loop->last)<br>@endif @endforeach
                </div>
            @endif
            @yield('content')
        </main>
    </div>
    @include('layouts.partials.admin-shell-scripts')
</body>
</html>
