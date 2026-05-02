@extends('layouts.site')

@section('title', 'Admin sign in — '.config('app.name'))

@push('styles')
<style>
    .login-wrap {
        min-height: calc(100vh - 2rem);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
    }
    .login-grid {
        display: grid;
        width: min(960px, 100%);
        gap: 0;
        border-radius: 20px;
        overflow: hidden;
        border: 1px solid var(--border);
        background: var(--bg-card);
        backdrop-filter: blur(24px);
    }
    @media (min-width: 840px) {
        .login-grid { grid-template-columns: 1fr 1fr; }
    }
    .login-aside {
        display: none;
        padding: 2.5rem;
        background: linear-gradient(155deg, rgba(99,102,241,0.28) 0%, rgba(15,18,28,0.95) 42%, rgba(52,211,153,0.1) 100%);
        border-right: 1px solid var(--border);
    }
    @media (min-width: 840px) {
        .login-aside { display: flex; flex-direction: column; justify-content: center; }
    }
    .login-aside h2 {
        font-family: "Outfit", sans-serif;
        font-size: 1.75rem;
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-bottom: 0.75rem;
    }
    .login-aside p { color: var(--muted); font-size: 0.95rem; max-width: 32ch; }
    .login-form-area { padding: 2.25rem 2rem; }
    @media (min-width: 840px) {
        .login-form-area { padding: 2.75rem 2.5rem; }
    }
    .login-form-area h1 {
        font-family: "Outfit", sans-serif;
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }
    .login-form-area .sub { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.75rem; }
    .field { margin-bottom: 1.1rem; }
    .remember {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin: 1rem 0 1.5rem;
        font-size: 0.875rem;
        color: var(--muted);
    }
    .remember input { width: 1rem; height: 1rem; accent-color: var(--accent); }
</style>
@endpush

@section('content')
<div class="login-wrap">
    <div class="login-grid">
        <aside class="login-aside">
            <div style="font-family:Outfit,sans-serif;font-size:2rem;font-weight:700;letter-spacing:-0.03em;margin-bottom:0.5rem;">{{ config('app.name') }}<span style="color:var(--accent);">.</span></div>
            <h2 style="margin-top:1.5rem;">Admin console</h2>
            <p>Same email and password as the mobile app. Session-based — sign out when finished on shared devices.</p>
        </aside>
        <div class="login-form-area">
            <h1>Welcome back</h1>
            <p class="sub">Secure access to businesses, catalog, stock, team, and reports.</p>

            @if ($errors->any())
                <div class="card" style="margin-bottom:1.25rem;padding:1rem;border-color:rgba(248,113,113,0.35);">
                    @foreach ($errors->all() as $error)
                        <p class="error" style="margin:0;">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="post" action="{{ route('login.store') }}">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username" autofocus>
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input class="input" id="password" name="password" type="password" required autocomplete="current-password">
                </div>
                <label class="remember">
                    <input type="checkbox" name="remember" value="1" {{ old('remember') ? 'checked' : '' }}>
                    Keep me signed in on this device
                </label>
                <button type="submit" class="btn btn-primary" style="width:100%;">Sign in</button>
            </form>

            <p style="margin-top:1.5rem;font-size:0.8125rem;color:var(--muted);text-align:center;">
                <a href="{{ url('/') }}">← Back to home</a>
            </p>
        </div>
    </div>
</div>
@endsection
