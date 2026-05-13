@extends('layouts.admin-portfolio')

@section('title', 'Jobs — Platform admin')

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('dashboard') }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Back to portfolio</a></p>

<h1 class="adm-page-title">Jobs</h1>
<p class="adm-page-desc">
    Manage the public vacancy feed shown on the mobile app. Businesses can also submit vacancy requests from the app —
    those land here as <strong>Pending</strong> and only go live after you approve them.
</p>

<div class="adm-actions" style="margin-bottom:1rem;">
    <a href="{{ route('admin.platform.jobs.create') }}" class="adm-btn adm-btn-primary">+ Post a job</a>
</div>

@php
    $tabs = [
        'all' => ['All', array_sum($counts ?? [])],
        'pending' => ['Pending approval', $counts['pending'] ?? 0],
        'approved' => ['Approved', $counts['approved'] ?? 0],
        'rejected' => ['Rejected', $counts['rejected'] ?? 0],
        'closed' => ['Closed', $counts['closed'] ?? 0],
    ];
@endphp
<div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    @foreach ($tabs as $k => [$label, $cnt])
        <a
            href="{{ route('admin.platform.jobs.index', array_filter(['status' => $k, 'state' => $state, 'q' => $search])) }}"
            class="adm-btn {{ $status === $k ? 'adm-btn-primary' : 'adm-btn-ghost' }}"
            style="padding:0.4rem 0.75rem;font-size:0.85rem;"
        >
            {{ $label }} <span style="opacity:0.7;">({{ $cnt }})</span>
        </a>
    @endforeach
</div>

<form method="get" action="{{ route('admin.platform.jobs.index') }}" class="adm-card" style="margin-bottom:1rem;">
    <input type="hidden" name="status" value="{{ $status }}">
    <div class="adm-grid cols-2" style="gap:0.75rem;">
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="q">Search title, company or city</label>
            <input class="adm-input" id="q" name="q" value="{{ $search }}" placeholder="e.g. Sales manager">
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="state">State</label>
            <select class="adm-select" id="state" name="state">
                <option value="">— Any state —</option>
                @foreach ($states as $s)
                    <option value="{{ $s }}" @selected($state === $s)>{{ $s }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="adm-actions" style="margin-top:0.75rem;">
        <button class="adm-btn adm-btn-primary" type="submit">Apply filters</button>
        @if($state || $search)
            <a class="adm-btn adm-btn-ghost" href="{{ route('admin.platform.jobs.index', ['status' => $status]) }}">Clear</a>
        @endif
    </div>
</form>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Title</th>
                <th>Company</th>
                <th>Location</th>
                <th>Type</th>
                <th>Source</th>
                <th>Status</th>
                <th>Posted</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($jobs as $job)
                <tr>
                    <td>
                        <strong>{{ $job->title }}</strong>
                        @if($job->rejection_reason && $job->status === 'rejected')
                            <div style="font-size:0.75rem;color:var(--adm-danger,#c33);margin-top:0.2rem;">
                                Reason: {{ \Illuminate\Support\Str::limit($job->rejection_reason, 80) }}
                            </div>
                        @endif
                    </td>
                    <td>{{ $job->company_name }}</td>
                    <td>
                        {{ $job->location_state }}
                        @if($job->location_city)<div style="font-size:0.75rem;color:var(--adm-muted);">{{ $job->location_city }}</div>@endif
                    </td>
                    <td style="font-size:0.85rem;">{{ \App\Models\JobPosting::EMPLOYMENT_TYPES[$job->employment_type] ?? $job->employment_type }}</td>
                    <td style="font-size:0.85rem;">
                        @if($job->source === 'business')
                            {{ $job->submitterBusiness?->name ?? 'Business' }}
                            @if($job->submittedByUser)<div style="font-size:0.7rem;color:var(--adm-muted);">{{ $job->submittedByUser->email }}</div>@endif
                        @else
                            <span style="color:var(--adm-muted);">Platform</span>
                        @endif
                    </td>
                    <td>
                        @php
                            $color = ['pending' => '#b15c00', 'approved' => '#0a7e3e', 'rejected' => '#b00020', 'closed' => '#666'][$job->status] ?? '#666';
                        @endphp
                        <span style="display:inline-block;padding:0.15rem 0.5rem;border-radius:999px;background:{{ $color }}1a;color:{{ $color }};font-size:0.75rem;font-weight:600;text-transform:capitalize;">
                            {{ $job->status }}
                        </span>
                    </td>
                    <td style="font-size:0.8rem;color:var(--adm-muted);">{{ $job->created_at?->diffForHumans() }}</td>
                    <td class="adm-actions">
                        @if($job->status === 'pending')
                            <form action="{{ route('admin.platform.jobs.approve', $job) }}" method="post" style="display:inline;">
                                @csrf
                                <button type="submit" class="adm-btn adm-btn-primary" style="padding:0.3rem 0.6rem;font-size:0.8rem;">Approve</button>
                            </form>
                            <button type="button" class="adm-btn adm-btn-danger" style="padding:0.3rem 0.6rem;font-size:0.8rem;"
                                onclick="document.getElementById('reject-{{ $job->id }}').style.display='block';">
                                Reject
                            </button>
                            <div id="reject-{{ $job->id }}" style="display:none;margin-top:0.5rem;">
                                <form action="{{ route('admin.platform.jobs.reject', $job) }}" method="post">
                                    @csrf
                                    <textarea name="rejection_reason" class="adm-input" rows="2" placeholder="Reason..." required style="margin-bottom:0.4rem;font-size:0.8rem;"></textarea>
                                    <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.3rem 0.6rem;font-size:0.75rem;">Submit reject</button>
                                </form>
                            </div>
                        @endif
                        <a href="{{ route('admin.platform.jobs.edit', $job) }}" class="adm-btn adm-btn-ghost" style="padding:0.3rem 0.6rem;font-size:0.8rem;">Edit</a>
                        @if($job->status === 'approved')
                            <form action="{{ route('admin.platform.jobs.close', $job) }}" method="post" style="display:inline;">
                                @csrf
                                <button type="submit" class="adm-btn adm-btn-ghost" style="padding:0.3rem 0.6rem;font-size:0.8rem;" onclick="return confirm('Close this vacancy?');">Close</button>
                            </form>
                        @endif
                        <form action="{{ route('admin.platform.jobs.destroy', $job) }}" method="post" style="display:inline;" onsubmit="return confirm('Permanently delete this vacancy?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.3rem 0.6rem;font-size:0.8rem;">Delete</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" style="color:var(--adm-muted);">No jobs match these filters.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;">{{ $jobs->links() }}</div>
@endsection
