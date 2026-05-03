@extends('layouts.admin-portfolio')

@section('title', 'Passkey (biometric) — '.config('app.name'))

@section('content')
<div class="adm-card" style="max-width:560px;">
    <h1 class="adm-page-title" style="font-size:1.35rem;">Passkey for admin sign-in</h1>
    <p class="adm-page-desc">Register this browser or device so you can use <strong>Touch ID</strong>, <strong>Face ID</strong>, <strong>Windows Hello</strong>, or a screen lock instead of typing your password on every visit.</p>
    <p class="adm-page-desc" style="margin-top:0.5rem;">You stay signed in the same way as before; this only replaces the password step on <em>this</em> device.</p>

    <div id="passkey-msg" style="margin:1rem 0;font-size:0.9rem;color:var(--adm-muted);"></div>

    <button type="button" class="adm-btn adm-btn-primary" id="btn-register-passkey">Register this device</button>
    <p style="margin-top:1.25rem;"><a href="{{ route('dashboard') }}">← Back to portfolio</a></p>
</div>

<script src="https://cdn.jsdelivr.net/npm/@laragear/webpass@2/dist/webpass.js" defer></script>
<script defer>
document.addEventListener('DOMContentLoaded', function () {
    const btn = document.getElementById('btn-register-passkey');
    const msg = document.getElementById('passkey-msg');
    if (!window.Webpass) {
        msg.textContent = 'Could not load passkey helper. Check your network or try another browser.';
        btn.disabled = true;
        return;
    }
    if (Webpass.isUnsupported()) {
        msg.textContent = 'This browser or device does not support passkeys / WebAuthn.';
        btn.disabled = true;
        return;
    }
    const webpass = Webpass.create({ findCsrfToken: true });
    btn.addEventListener('click', async function () {
        msg.textContent = 'When prompted, confirm with your fingerprint, face, or device PIN…';
        btn.disabled = true;
        try {
            const { success, error } = await webpass.attest('/admin/webauthn/register/options', '/admin/webauthn/register');
            if (success) {
                msg.textContent = 'Passkey saved. You can use “Sign in with passkey” on the login page from now on.';
                msg.style.color = 'var(--adm-success, #059669)';
            } else {
                msg.textContent = (error && error.message) ? error.message : 'Registration was cancelled or failed.';
                msg.style.color = 'var(--adm-danger)';
                btn.disabled = false;
            }
        } catch (e) {
            msg.textContent = e.message || 'Something went wrong.';
            msg.style.color = 'var(--adm-danger)';
            btn.disabled = false;
        }
    });
});
</script>
@endsection
