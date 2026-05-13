@extends('layouts.site')

@section('title', 'Application status — CoreBooks')

@push('styles')
<style>
    .status-shell { padding: 4rem 0 5rem; }
    .status-shell .container { max-width: 720px; }
    .status-card { padding: 2.25rem; position: relative; overflow: hidden; }
    .status-card::before {
        content: "";
        position: absolute;
        inset: -1px;
        z-index: 0;
        pointer-events: none;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--accentBg) 0%, transparent 55%);
        opacity: 0.7;
    }
    .status-inner { position: relative; z-index: 1; }
    .status-eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.35rem 0.85rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        margin-bottom: 1.25rem;
    }
    .status-eyebrow .dot {
        width: 8px; height: 8px; border-radius: 50%;
        box-shadow: 0 0 0 4px var(--accentBg);
    }
    .status-card h1 {
        font-family: "Outfit", sans-serif;
        font-size: clamp(1.6rem, 3vw, 2.1rem);
        font-weight: 700;
        letter-spacing: -0.02em;
        margin-bottom: 0.4rem;
        color: var(--text);
    }
    .status-card .name { color: var(--muted); margin-bottom: 1.5rem; }
    .status-meta-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.85rem;
        margin: 1.5rem 0 1.75rem;
    }
    @media (max-width: 560px) { .status-meta-grid { grid-template-columns: 1fr; } }
    .meta-tile {
        padding: 0.95rem 1.05rem;
        border-radius: 12px;
        background: rgba(255,255,255,0.04);
        border: 1px solid var(--border);
    }
    .meta-tile .lbl { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); font-weight: 600; }
    .meta-tile .val { font-size: 1.05rem; font-weight: 700; font-family: "Outfit", sans-serif; margin-top: 0.25rem; }
    .reason-block {
        background: rgba(248, 113, 113, 0.07);
        border: 1px solid rgba(248, 113, 113, 0.25);
        border-radius: 12px;
        padding: 1rem 1.15rem;
        margin: 1rem 0 0;
    }
    .reason-block .lbl { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: #fecaca; font-weight: 700; }
    .reason-block .body { color: var(--text); margin-top: 0.4rem; }
    .pickup-block {
        background: rgba(52, 211, 153, 0.07);
        border: 1px solid rgba(52, 211, 153, 0.3);
        border-radius: 12px;
        padding: 1rem 1.15rem;
        margin: 1rem 0 0;
        color: var(--text);
    }
    .pickup-block .lbl { font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: #86efac; font-weight: 700; }
    .pickup-block .body { margin-top: 0.4rem; font-size: 1rem; }
    .pickup-block .body strong { color: #86efac; }
    .copy-row {
        display: flex; align-items: center; gap: 0.5rem;
        padding: 0.75rem 1rem;
        background: rgba(0,0,0,0.25);
        border: 1px dashed var(--border);
        border-radius: 10px;
        font-family: "DM Sans", monospace;
        font-size: 0.85rem;
        color: var(--muted);
        word-break: break-all;
        margin-top: 0.75rem;
    }
    .actions-row { display: flex; gap: 0.75rem; flex-wrap: wrap; margin-top: 1.5rem; }
</style>
@endpush

@section('content')
<div class="status-shell">
    <div class="container">

        @if ($seeker === null)
            <div class="card status-card" style="--accentBg: rgba(148, 163, 184, 0.12);">
                <div class="status-inner">
                    <span class="status-eyebrow" style="background: rgba(148, 163, 184, 0.14); color: #cbd5e1;">
                        <span class="dot" style="background: #94a3b8;"></span> Not found
                    </span>
                    <h1>We couldn't find this application</h1>
                    <p style="color: var(--muted);">The link may be incorrect or expired. Double-check the tracking token, or submit a new application.</p>
                    <div class="actions-row">
                        <a href="{{ route('public.jobs.apply') }}" class="btn btn-primary">Start a new application</a>
                        <a href="{{ route('public.jobs.status') }}" class="btn btn-ghost">Try again</a>
                    </div>
                </div>
            </div>
        @else
            @php
                $s = $seeker;
                $statusMeta = [
                    'pending' => ['color' => '#fbbf24', 'bg' => 'rgba(251, 191, 36, 0.12)', 'fg' => '#fde68a', 'label' => 'In review', 'title' => 'Your application is being reviewed', 'sub' => "We'll notify you as soon as our team approves or declines your profile."],
                    'active' => ['color' => '#34d399', 'bg' => 'rgba(52, 211, 153, 0.12)', 'fg' => '#86efac', 'label' => 'Approved', 'title' => "You're live!", 'sub' => 'Hiring businesses can now see and contact you. Keep your phone close — you may be reached directly.'],
                    'declined' => ['color' => '#f87171', 'bg' => 'rgba(248, 113, 113, 0.12)', 'fg' => '#fecaca', 'label' => 'Declined', 'title' => "We couldn't approve this submission", 'sub' => "You're welcome to submit a new application with updated details."],
                    'hidden' => ['color' => '#94a3b8', 'bg' => 'rgba(148, 163, 184, 0.12)', 'fg' => '#cbd5e1', 'label' => 'Hidden', 'title' => 'Profile is currently hidden', 'sub' => 'Contact support if you would like it re-listed.'],
                    'archived' => ['color' => '#94a3b8', 'bg' => 'rgba(148, 163, 184, 0.12)', 'fg' => '#cbd5e1', 'label' => 'Archived', 'title' => 'Profile archived', 'sub' => 'This application is no longer active.'],
                ];
                $m = $statusMeta[$s->status] ?? $statusMeta['pending'];
            @endphp

            <div class="card status-card" style="--accentBg: {{ $m['bg'] }};">
                <div class="status-inner">
                    <span class="status-eyebrow" style="background: {{ $m['bg'] }}; color: {{ $m['fg'] }};">
                        <span class="dot" style="background: {{ $m['color'] }};"></span> {{ $m['label'] }}
                    </span>
                    <h1>{{ $m['title'] }}</h1>
                    <div class="name">{{ $s->full_name }}{{ $s->headline ? ' — '.$s->headline : '' }}</div>
                    <p style="color: var(--muted); margin-bottom: 0;">{{ $m['sub'] }}</p>

                    <div class="status-meta-grid">
                        <div class="meta-tile">
                            <div class="lbl">Submitted</div>
                            <div class="val">{{ optional($s->applied_at ?? $s->created_at)->format('j M Y') }}</div>
                        </div>
                        <div class="meta-tile">
                            <div class="lbl">Location</div>
                            <div class="val">{{ $s->location_city ? $s->location_city.', ' : '' }}{{ $s->location_state }}</div>
                        </div>
                        @if($s->status === 'active')
                            <div class="meta-tile">
                                <div class="lbl">Businesses viewing</div>
                                <div class="val">{{ number_format($shortlistCount) }}</div>
                            </div>
                        @endif
                        @if($s->cv_url)
                            <div class="meta-tile">
                                <div class="lbl">CV on file</div>
                                <div class="val">
                                    <a href="{{ $s->cv_url }}" target="_blank" rel="noopener" style="color:#a5b4fc;">{{ $s->cv_filename ?: 'Open' }}</a>
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($s->status === 'declined' && $s->rejection_reason)
                        <div class="reason-block">
                            <div class="lbl">Reviewer note</div>
                            <div class="body">{{ $s->rejection_reason }}</div>
                        </div>
                    @endif

                    @if($s->status === 'active' && $shortlistCount > 0)
                        <div class="pickup-block">
                            <div class="lbl">You've been picked</div>
                            <div class="body">
                                <strong>{{ number_format($shortlistCount) }}</strong> business{{ $shortlistCount === 1 ? '' : 'es' }} have added you to their shortlist. Keep your phone close and check your email — they may reach out directly.
                            </div>
                        </div>
                    @endif

                    <div class="copy-row" title="Save this link to check back later">
                        🔗 {{ route('public.jobs.status.show', ['token' => $token]) }}
                    </div>

                    <div class="actions-row">
                        @if($s->status === 'declined')
                            <a href="{{ route('public.jobs.apply') }}" class="btn btn-primary">Apply again</a>
                        @endif
                        <a href="{{ route('public.jobs.status') }}" class="btn btn-ghost">Look up another</a>
                        <a href="{{ url('/') }}" class="btn btn-ghost">Back to home</a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
