@extends('layouts.admin-workspace')

@section('title', 'Sales — '.$business->name)

@section('content')
@php
    $fmt = fn ($n) => $currencySymbol.number_format((float) $n, 2);
    $hasFilters = ($filters['location_uuid'] ?? 'all') !== 'all'
        || ! empty($filters['user_id'])
        || ($filters['status'] ?? 'all') !== 'all'
        || ($filters['payment_method'] ?? 'all') !== 'all'
        || ($filters['q'] ?? '') !== ''
        || ($filters['group_by'] ?? 'day') !== 'day'
        || $from->toDateString() !== now()->subDays(29)->toDateString()
        || $to->toDateString() !== now()->toDateString();
@endphp

<h1 class="adm-page-title">Sales</h1>
<p class="adm-page-desc">Completed POS and API sales. Filter by date, branch, staff, or payment — then export Excel or CSV. Open a row for line items, VAT, discounts, and payment split.</p>

@if ($errors->has('sale'))
    <div class="adm-card" style="border:1px solid var(--adm-danger,#dc2626);background:rgba(220,38,38,0.08);color:var(--adm-danger,#dc2626);padding:0.85rem 1rem;margin-bottom:1rem;border-radius:10px;">
        {{ $errors->first('sale') }}
    </div>
@endif

<div class="adm-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:0.75rem;margin-bottom:1.25rem;">
    <div class="adm-card" style="padding:1rem;">
        <span class="adm-page-desc" style="margin:0;display:block;font-size:0.78rem;">Sales in range</span>
        <strong style="font-size:1.35rem;font-family:Outfit,sans-serif;">{{ number_format($summary['sale_count']) }}</strong>
    </div>
    <div class="adm-card" style="padding:1rem;">
        <span class="adm-page-desc" style="margin:0;display:block;font-size:0.78rem;">Gross sales</span>
        <strong style="font-size:1.35rem;font-family:Outfit,sans-serif;">{{ $fmt($summary['grand_total']) }}</strong>
    </div>
    <div class="adm-card" style="padding:1rem;">
        <span class="adm-page-desc" style="margin:0;display:block;font-size:0.78rem;">VAT / tax</span>
        <strong style="font-size:1.35rem;font-family:Outfit,sans-serif;">{{ $fmt($summary['tax_total']) }}</strong>
    </div>
    <div class="adm-card" style="padding:1rem;">
        <span class="adm-page-desc" style="margin:0;display:block;font-size:0.78rem;">Discounts</span>
        <strong style="font-size:1.35rem;font-family:Outfit,sans-serif;">{{ $fmt($summary['discount_total']) }}</strong>
    </div>
</div>

<form method="get" action="{{ route('admin.b.sales.index', $business) }}" class="adm-card" style="margin-bottom:1.25rem;">
    <div class="adm-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;">
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="from">From</label>
            <input class="adm-input" type="date" id="from" name="from" value="{{ $from->toDateString() }}">
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="to">To</label>
            <input class="adm-input" type="date" id="to" name="to" value="{{ $to->toDateString() }}">
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="location_uuid">Branch</label>
            <select class="adm-select" id="location_uuid" name="location_uuid">
                <option value="all" @selected(($filters['location_uuid'] ?? 'all') === 'all')>All branches</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->uuid }}" @selected(($filters['location_uuid'] ?? '') === $loc->uuid)>{{ $loc->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="user_id">Staff</label>
            <select class="adm-select" id="user_id" name="user_id">
                <option value="">All staff</option>
                @foreach ($staff as $member)
                    <option value="{{ $member->id }}" @selected((int) ($filters['user_id'] ?? 0) === (int) $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="status">Status</label>
            <select class="adm-select" id="status" name="status">
                <option value="all" @selected(($filters['status'] ?? 'all') === 'all')>All statuses</option>
                <option value="completed" @selected(($filters['status'] ?? '') === 'completed')>Completed</option>
                <option value="partially_returned" @selected(($filters['status'] ?? '') === 'partially_returned')>Partially returned</option>
                <option value="returned" @selected(($filters['status'] ?? '') === 'returned')>Returned</option>
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="payment_method">Payment</label>
            <select class="adm-select" id="payment_method" name="payment_method">
                <option value="all" @selected(($filters['payment_method'] ?? 'all') === 'all')>All methods</option>
                <option value="cash" @selected(($filters['payment_method'] ?? '') === 'cash')>Cash</option>
                <option value="transfer" @selected(($filters['payment_method'] ?? '') === 'transfer')>Transfer</option>
                <option value="pos" @selected(($filters['payment_method'] ?? '') === 'pos')>POS</option>
                <option value="credit" @selected(($filters['payment_method'] ?? '') === 'credit')>Credit</option>
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="group_by">Group by</label>
            <select class="adm-select" id="group_by" name="group_by">
                <option value="day" @selected(($filters['group_by'] ?? 'day') === 'day')>Day</option>
                <option value="branch" @selected(($filters['group_by'] ?? '') === 'branch')>Branch</option>
                <option value="none" @selected(($filters['group_by'] ?? '') === 'none')>No grouping</option>
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="q">Search</label>
            <input class="adm-input" id="q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Receipt or customer">
        </div>
    </div>
    <div class="adm-actions" style="margin-top:0.85rem;flex-wrap:wrap;">
        <button class="adm-btn adm-btn-primary" type="submit">Apply filters</button>
        @if($hasFilters)
            <a class="adm-btn adm-btn-ghost" href="{{ route('admin.b.sales.index', $business) }}">Clear</a>
        @endif
        <span style="flex:1 1 auto;"></span>
        <a class="adm-btn adm-btn-ghost" href="{{ route('admin.b.sales.export', array_merge(['business' => $business, 'format' => 'xlsx'], $exportQuery)) }}">Export Excel</a>
        <a class="adm-btn adm-btn-ghost" href="{{ route('admin.b.sales.export', array_merge(['business' => $business, 'format' => 'csv'], $exportQuery)) }}">Export CSV</a>
    </div>
</form>

@forelse ($groupedSales as $groupKey => $groupRows)
    @php
        $groupTotal = $groupRows->sum(fn ($s) => (float) $s->grand_total);
        $groupLabel = match ($filters['group_by'] ?? 'day') {
            'branch' => $groupKey,
            'none' => 'All sales',
            default => $groupKey === 'unknown'
                ? 'Unknown date'
                : \Carbon\Carbon::parse($groupKey)->format('D, M j, Y'),
        };
    @endphp
    <div style="display:flex;align-items:baseline;justify-content:space-between;gap:1rem;margin:{{ $loop->first ? '0' : '1.25rem' }} 0 0.5rem;flex-wrap:wrap;">
        <h2 style="margin:0;font-family:Outfit,sans-serif;font-size:1.05rem;">{{ $groupLabel }}</h2>
        <span style="color:var(--adm-muted);font-size:0.9rem;">
            {{ $groupRows->count() }} sale{{ $groupRows->count() === 1 ? '' : 's' }}
            · <strong style="color:var(--adm-text,#0f172a);">{{ $fmt($groupTotal) }}</strong>
        </span>
    </div>
    <div class="adm-table-wrap" style="margin-bottom:0.25rem;">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Receipt</th>
                    @if(($filters['group_by'] ?? 'day') !== 'branch')
                        <th>Branch</th>
                    @endif
                    <th>Customer</th>
                    <th>Staff</th>
                    <th>Payment</th>
                    <th>Lines</th>
                    <th style="text-align:right;">Total</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($groupRows as $s)
                    <tr>
                        <td>{{ $s->sold_at?->timezone(config('app.timezone'))->format('H:i') ?? '—' }}@if(($filters['group_by'] ?? 'day') !== 'day') <span style="color:var(--adm-muted);">{{ $s->sold_at?->timezone(config('app.timezone'))->format('Y-m-d') }}</span>@endif</td>
                        <td>
                            <strong>{{ $s->receipt_no }}</strong>
                            @if($s->status && $s->status !== 'completed')
                                <span class="adm-role-pill" style="margin-left:0.35rem;font-size:0.65rem;">{{ str_replace('_', ' ', $s->status) }}</span>
                            @endif
                        </td>
                        @if(($filters['group_by'] ?? 'day') !== 'branch')
                            <td>{{ $s->location?->name ?? '—' }}</td>
                        @endif
                        <td style="color:var(--adm-muted);">{{ $s->customer?->name ?? '—' }}</td>
                        <td style="color:var(--adm-muted);">{{ $s->user?->name ?? '—' }}</td>
                        <td style="color:var(--adm-muted);font-size:0.85rem;">{{ $s->payments->pluck('method')->unique()->sort()->map(fn ($m) => ucfirst($m))->implode(', ') ?: '—' }}</td>
                        <td>{{ $s->lines_count }}</td>
                        <td style="text-align:right;"><strong>{{ $fmt($s->grand_total) }}</strong></td>
                        <td><a href="{{ route('admin.b.sales.show', [$business, $s]) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.8rem;">View</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@empty
    <div class="adm-card" style="color:var(--adm-muted);">
        No sales match these filters.
        @if($hasFilters)
            <a href="{{ route('admin.b.sales.index', $business) }}">Clear filters</a>
        @endif
    </div>
@endforelse

@if($sales->total() > 0)
    <div style="margin-top:1rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
        <span style="color:var(--adm-muted);font-size:0.85rem;">
            Showing {{ $sales->firstItem() }}–{{ $sales->lastItem() }} of {{ number_format($sales->total()) }}
            · Filtered total {{ $fmt($summary['grand_total']) }}
        </span>
        <div>{{ $sales->links() }}</div>
    </div>
@endif
@endsection
