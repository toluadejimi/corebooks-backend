<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Businesses — '.config('app.name'))</title>
    @include('layouts.partials.admin-styles')
</head>
<body class="adm-body">
    <header class="adm-topbar">
        <div class="adm-topbar-start">
            <a href="{{ route('dashboard') }}" class="adm-brand">{{ config('app.name') }}<span>.</span></a>
        </div>
        <div class="adm-actions">
            <button type="button" class="adm-icon-btn adm-theme-btn" aria-label="Color theme">◐</button>
            <a href="{{ route('admin.passkey-setup') }}" class="adm-btn adm-btn-ghost">Passkey</a>
            <span class="adm-user-email">{{ $user->email }}</span>
            <form method="post" action="{{ route('logout') }}" style="display:inline;">@csrf
                <button type="submit" class="adm-btn adm-btn-ghost">Sign out</button>
            </form>
        </div>
    </header>
    <main class="adm-main adm-portfolio-main">
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
    @include('layouts.partials.admin-shell-scripts')
</body>
</html>
