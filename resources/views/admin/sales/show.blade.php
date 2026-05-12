@extends('layouts.admin-workspace')

@section('title', 'Sale '.$sale->receipt_no.' — '.$business->name)

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.b.sales.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Sales</a></p>

<h1 class="adm-page-title">Sale breakdown</h1>
<p class="adm-page-desc">
    {{ $sale->sold_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
    · {{ $sale->location?->name ?? '—' }}
    · {{ $sale->customer?->name ?? 'Walk-in / —' }}
    · Staff: {{ $sale->user?->name ?? '—' }}
</p>

<div class="adm-card" style="max-width:820px;margin-bottom:1.25rem;">
    <div class="adm-grid cols-2" style="gap:1rem;">
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Subtotal (ex VAT)</span>
            <strong style="font-size:1.1rem;font-family:Outfit,sans-serif;">{{ $currencySymbol }}{{ number_format((float) $sale->subtotal, 2) }}</strong>
        </div>
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">VAT</span>
            <strong style="font-size:1.1rem;font-family:Outfit,sans-serif;">{{ $currencySymbol }}{{ number_format((float) $sale->tax_total, 2) }}</strong>
        </div>
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Discounts</span>
            <strong style="font-size:1.1rem;font-family:Outfit,sans-serif;">{{ $currencySymbol }}{{ number_format((float) $sale->discount_total, 2) }}</strong>
        </div>
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Grand total</span>
            <strong style="font-size:1.35rem;font-family:Outfit,sans-serif;">{{ $currencySymbol }}{{ number_format((float) $sale->grand_total, 2) }}</strong>
        </div>
    </div>
    <p style="margin:1rem 0 0;font-size:0.8rem;color:var(--adm-muted);">UUID: {{ $sale->uuid }} · Status: {{ $sale->status }}</p>
</div>

<h2 class="adm-page-title" style="font-size:1.05rem;margin-bottom:0.5rem;">Line items</h2>
<div class="adm-table-wrap" style="margin-bottom:1.5rem;">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit (ex VAT)</th>
                <th>VAT %</th>
                <th style="text-align:right;">Line total</th>
                <th style="color:var(--adm-muted);">Batch</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->lines as $line)
                <tr>
                    <td>{{ $line->product?->name ?? '—' }}</td>
                    <td>{{ number_format((float) $line->qty, 3) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format((float) $line->unit_price, 2) }}</td>
                    <td>{{ number_format((float) $line->tax_rate, 2) }}%</td>
                    <td style="text-align:right;"><strong>{{ $currencySymbol }}{{ number_format((float) $line->line_total, 2) }}</strong></td>
                    <td style="font-size:0.75rem;color:var(--adm-muted);">{{ $line->batch?->uuid ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2 class="adm-page-title" style="font-size:1.05rem;margin-bottom:0.5rem;">Payments</h2>
<div class="adm-table-wrap" style="max-width:520px;">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Method</th>
                <th style="text-align:right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sale->payments as $pay)
                <tr>
                    <td><span class="adm-role-pill" style="font-size:0.65rem;">{{ strtoupper($pay->method) }}</span></td>
                    <td style="text-align:right;">{{ $currencySymbol }}{{ number_format((float) $pay->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="2" style="color:var(--adm-muted);">No payment rows (legacy sale).</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <th>Total paid</th>
                <th style="text-align:right;">{{ $currencySymbol }}{{ number_format((float) $sale->payments->sum('amount'), 2) }}</th>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
