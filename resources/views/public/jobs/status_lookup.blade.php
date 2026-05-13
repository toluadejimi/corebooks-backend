@extends('layouts.site')

@section('title', 'Check application status — CoreBooks')

@push('styles')
<style>
    .lookup-shell { padding: 5rem 0; }
    .lookup-shell .container { max-width: 560px; }
    .lookup-card { padding: 2.25rem; }
    .lookup-hero h1 {
        font-family: "Outfit", sans-serif;
        font-size: clamp(1.65rem, 3vw, 2.15rem);
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-bottom: 0.5rem;
        color: var(--text);
    }
    .lookup-hero p { color: var(--muted); margin-bottom: 1.5rem; }
    .input-help { font-size: 0.8rem; color: var(--muted); margin-top: 0.5rem; }
</style>
@endpush

@section('content')
<div class="lookup-shell">
    <div class="container">
        <div class="lookup-hero">
            <h1>Check your application status</h1>
            <p>Paste the tracking link you received after submitting, or enter the token shown on the confirmation page.</p>
        </div>
        <div class="card lookup-card">
            <form method="get" action="{{ route('public.jobs.status') }}">
                <label for="token">Tracking token</label>
                <input class="input" id="token" name="token" value="{{ request('token') }}" placeholder="e.g. a4f6c8d9..." required maxlength="64" autofocus>
                <div class="input-help">Token is the long string at the end of the link we gave you after submitting.</div>
                <div style="margin-top:1.25rem;display:flex;gap:0.75rem;flex-wrap:wrap;">
                    <button type="submit" class="btn btn-primary">Look up</button>
                    <a href="{{ route('public.jobs.apply') }}" class="btn btn-ghost">Start a new application</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
