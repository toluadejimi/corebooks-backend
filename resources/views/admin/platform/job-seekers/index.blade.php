@extends('layouts.admin-portfolio')

@section('title', 'Job seekers — Platform admin')

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('dashboard') }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Portfolio</a></p>

<h1 class="adm-page-title">Job seekers</h1>
<p class="adm-page-desc">People looking for work. Businesses browse this list on the mobile app, contact them, and add to their shortlist.</p>

<div class="adm-actions" style="margin-bottom:1rem;">
    <a href="{{ route('admin.platform.job-seekers.create') }}" class="adm-btn adm-btn-primary">+ Add seeker</a>
</div>

@php
    $tabs = [
        'all' => ['All', array_sum($counts ?? [])],
        'active' => ['Active', $counts['active'] ?? 0],
        'hidden' => ['Hidden', $counts['hidden'] ?? 0],
        'archived' => ['Archived', $counts['archived'] ?? 0],
    ];
@endphp
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    @foreach ($tabs as $k => [$label, $cnt])
        <a
            href="{{ route('admin.platform.job-seekers.index', array_filter(['status' => $k, 'state' => $state, 'q' => $search])) }}"
            class="adm-btn {{ $status === $k ? 'adm-btn-primary' : 'adm-btn-ghost' }}"
            style="padding:0.4rem 0.75rem;font-size:0.85rem;"
        >
            {{ $label }} <span style="opacity:0.7;">({{ $cnt }})</span>
        </a>
    @endforeach
</div>

<form method="get" action="{{ route('admin.platform.job-seekers.index') }}" class="adm-card" style="margin-bottom:1rem;">
    <input type="hidden" name="status" value="{{ $status }}">
    <div class="adm-grid cols-2" style="gap:0.75rem;">
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="q">Search name, headline or skills</label>
            <input class="adm-input" id="q" name="q" value="{{ $search }}" placeholder="e.g. Sales executive">
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="state">State</label>
            <select class="adm-select" id="state" name="state">
                <option value="">— Any —</option>
                @foreach ($states as $st)
                    <option value="{{ $st }}" @selected($state === $st)>{{ $st }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="adm-actions" style="margin-top:0.75rem;">
        <button class="adm-btn adm-btn-primary" type="submit">Apply filters</button>
        @if($state || $search)
            <a class="adm-btn adm-btn-ghost" href="{{ route('admin.platform.job-seekers.index', ['status' => $status]) }}">Clear</a>
        @endif
    </div>
</form>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th></th>
                <th>Name</th>
                <th>Headline</th>
                <th>Location</th>
                <th>Wants</th>
                <th style="text-align:right;">Expected</th>
                <th>CV</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($seekers as $s)
                <tr>
                    <td>
                        @if($s->photo_url)
                            <img src="{{ $s->photo_url }}" alt="" style="width:36px;height:36px;border-radius:50%;object-fit:cover;border:1px solid var(--adm-border,#ddd);">
                        @else
                            <div style="width:36px;height:36px;border-radius:50%;background:#e2e8f0;color:#475569;display:inline-flex;align-items:center;justify-content:center;font-weight:700;">{{ strtoupper(substr($s->full_name, 0, 1)) }}</div>
                        @endif
                    </td>
                    <td>
                        <strong>{{ $s->full_name }}</strong>
                        @if($s->years_experience)<div style="font-size:0.75rem;color:var(--adm-muted);">{{ $s->years_experience }} yr{{ $s->years_experience == 1 ? '' : 's' }} exp.</div>@endif
                    </td>
                    <td>{{ $s->headline ?: '—' }}</td>
                    <td>
                        {{ $s->location_state }}
                        @if($s->location_city)<div style="font-size:0.75rem;color:var(--adm-muted);">{{ $s->location_city }}</div>@endif
                    </td>
                    <td style="font-size:0.85rem;">{{ \App\Models\JobSeeker::EMPLOYMENT_TYPES[$s->employment_type] ?? $s->employment_type }}</td>
                    <td style="text-align:right;font-size:0.85rem;">
                        @if($s->expected_salary_min || $s->expected_salary_max)
                            {{ $s->currency }} {{ number_format((float) ($s->expected_salary_min ?? $s->expected_salary_max), 0) }}
                            @if($s->expected_salary_max && $s->expected_salary_min && $s->expected_salary_max != $s->expected_salary_min)
                                – {{ number_format((float) $s->expected_salary_max, 0) }}
                            @endif
                            @if($s->salary_period)<div style="font-size:0.7rem;color:var(--adm-muted);">/{{ $s->salary_period }}</div>@endif
                        @else
                            <span style="color:var(--adm-muted);">—</span>
                        @endif
                    </td>
                    <td>
                        @if($s->cv_url)
                            <a href="{{ $s->cv_url }}" target="_blank" rel="noopener" style="font-size:0.85rem;">📎 Open</a>
                        @else
                            <span style="color:var(--adm-muted);">—</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $color = ['active' => '#0a7e3e', 'hidden' => '#666', 'archived' => '#b15c00'][$s->status] ?? '#666';
                        @endphp
                        <span style="display:inline-block;padding:0.15rem 0.5rem;border-radius:999px;background:{{ $color }}1a;color:{{ $color }};font-size:0.75rem;font-weight:600;text-transform:capitalize;">
                            {{ $s->status }}
                        </span>
                    </td>
                    <td class="adm-actions">
                        <a href="{{ route('admin.platform.job-seekers.edit', $s) }}" class="adm-btn adm-btn-ghost" style="padding:0.3rem 0.6rem;font-size:0.8rem;">Edit</a>
                        <form action="{{ route('admin.platform.job-seekers.destroy', $s) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete {{ $s->full_name }}?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.3rem 0.6rem;font-size:0.8rem;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" style="color:var(--adm-muted);">No seekers match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;">{{ $seekers->links() }}</div>
@endsection
