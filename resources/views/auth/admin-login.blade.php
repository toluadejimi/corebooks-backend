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

            <form method="post" action="{{ route('login.store') }}" id="admin-login-form">
                @csrf
                <div class="field">
                    <label for="email">Email</label>
                    <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="username webauthn" autofocus>
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

            <div style="margin:1.25rem 0;border-top:1px solid var(--border);padding-top:1.25rem;">
                <p style="font-size:0.875rem;color:var(--muted);margin-bottom:0.75rem;">Have a passkey on this device? Enter your email above, then:</p>
                <button type="button" class="btn" id="btn-passkey-login" style="width:100%;border:1px solid var(--border);background:rgba(255,255,255,0.04);">Sign in with passkey (Face ID / Touch ID / Windows Hello)</button>
                <p style="margin-top:0.75rem;font-size:0.78rem;color:var(--muted);">First time: sign in with password once, open <strong>Passkey</strong> from the admin header, and register this browser.</p>
            </div>

            <p style="margin-top:1.5rem;font-size:0.8125rem;color:var(--muted);text-align:center;">
                <a href="{{ url('/') }}">← Back to home</a>
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/@laragear/webpass@2/dist/webpass.js" defer></script>
<script defer>
document.addEventListener('DOMContentLoaded', function () {
    try {
        var saved = localStorage.getItem('admin_login_email');
        var emailEl = document.getElementById('email');
        if (saved && emailEl && !emailEl.value) emailEl.value = saved;
    } catch (e) {}
    var form = document.getElementById('admin-login-form');
    if (form) {
        form.addEventListener('submit', function () {
            try {
                var v = document.getElementById('email').value;
                if (v) localStorage.setItem('admin_login_email', v);
            } catch (e) {}
        });
    }
    var btn = document.getElementById('btn-passkey-login');
    if (!btn || !window.Webpass) return;
    if (Webpass.isUnsupported()) {
        btn.disabled = true;
        btn.textContent = 'Passkeys not supported in this browser';
        return;
    }
    var webpass = Webpass.create({ findCsrfToken: true });
    btn.addEventListener('click', async function () {
        var email = document.getElementById('email').value.trim();
        if (!email) {
            alert('Enter your email first so we can find your passkey.');
            document.getElementById('email').focus();
            return;
        }
        btn.disabled = true;
        try {
            var res = await webpass.assert(
                { path: '/admin/webauthn/login/options', body: { email: email }, credentials: 'same-origin' },
                { path: '/admin/webauthn/login', credentials: 'same-origin' }
            );
            if (res.success) {
                try { localStorage.setItem('admin_login_email', email); } catch (e) {}
                window.location.href = '{{ url('/admin') }}';
            } else {
                alert((res.error && res.error.message) ? res.error.message : 'Passkey sign-in failed. Use your password or register a passkey from the admin dashboard after signing in.');
                btn.disabled = false;
            }
        } catch (e) {
            alert(e.message || 'Passkey sign-in failed.');
            btn.disabled = false;
        }
    });
});
</script>
@endpush
