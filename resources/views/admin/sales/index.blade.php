@extends('layouts.admin-workspace')

@section('title', 'Sales — '.$business->name)

@section('content')
<h1 class="adm-page-title">Sales</h1>
<p class="adm-page-desc">Completed POS and API sales. Open a row for line items, VAT, discounts, and payment split.</p>

@if ($errors->has('sale'))
    <div class="adm-card" style="border:1px solid var(--adm-danger,#dc2626);background:rgba(220,38,38,0.08);color:var(--adm-danger,#dc2626);padding:0.85rem 1rem;margin-bottom:1rem;border-radius:10px;">
        {{ $errors->first('sale') }}
    </div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>When</th>
                <th>Receipt</th>
                <th>Branch</th>
                <th>Customer</th>
                <th>Staff</th>
                <th>Lines</th>
                <th style="text-align:right;">Total</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sales as $s)
                <tr>
                    <td>{{ $s->sold_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                    <td><strong>{{ $s->receipt_no }}</strong></td>
                    <td>{{ $s->location?->name ?? '—' }}</td>
                    <td style="color:var(--adm-muted);">{{ $s->customer?->name ?? '—' }}</td>
                    <td style="color:var(--adm-muted);">{{ $s->user?->name ?? '—' }}</td>
                    <td>{{ $s->lines_count }}</td>
                    <td style="text-align:right;"><strong>{{ $currencySymbol }}{{ number_format((float) $s->grand_total, 2) }}</strong></td>
                    <td><a href="{{ route('admin.b.sales.show', [$business, $s]) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.8rem;">View</a></td>
                </tr>
            @empty
                <tr><td colspan="8" style="color:var(--adm-muted);">No sales yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div style="margin-top:1rem;">{{ $sales->links() }}</div>
@endsection
