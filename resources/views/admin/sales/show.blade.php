@extends('layouts.admin-workspace')

@section('title', 'Sale '.$sale->receipt_no.' — '.$business->name)

@section('content')
<p style="margin:0 0 1rem;display:flex;flex-wrap:wrap;gap:0.5rem;align-items:center;">
    <a href="{{ route('admin.b.sales.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Sales</a>
    @if($sale->status !== 'voided')
        <a href="{{ route('admin.b.pos.receipt', [$business, $sale->uuid]) }}" class="adm-btn adm-btn-primary" style="padding:0.35rem 0.65rem;font-size:0.85rem;" target="_blank" rel="noopener">Print receipt</a>
    @endif
</p>

@if ($errors->has('sale'))
    <div class="adm-card" style="border:1px solid var(--adm-danger,#dc2626);background:rgba(220,38,38,0.08);color:var(--adm-danger,#dc2626);padding:0.85rem 1rem;margin-bottom:1rem;border-radius:10px;max-width:820px;">
        {{ $errors->first('sale') }}
    </div>
@endif

@if ($errors->has('confirm'))
    <div class="adm-card" style="border:1px solid var(--adm-danger,#dc2626);background:rgba(220,38,38,0.08);color:var(--adm-danger,#dc2626);padding:0.85rem 1rem;margin-bottom:1rem;border-radius:10px;max-width:820px;">
        {{ $errors->first('confirm') }}
    </div>
@endif

<h1 class="adm-page-title">Sale breakdown</h1>
<p class="adm-page-desc">
    {{ $sale->sold_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
    · {{ $sale->location?->name ?? '—' }}
    · {{ $sale->customer?->name ?? 'Walk-in customer' }}
    · Staff: {{ $sale->user?->name ?? '—' }}
    @if($sale->status === 'voided')
        · <span style="color:var(--adm-danger,#dc2626);font-weight:600;">Voided</span>
    @endif
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

@if($canManage && $sale->status === 'completed' && ! $hasCreditPayment)
<div class="adm-card" style="max-width:820px;margin-bottom:1.25rem;">
    <h2 style="margin-top:0;font-family:Outfit,sans-serif;font-size:1.05rem;">Correct customer</h2>
    <p class="adm-page-desc" style="margin-top:-0.25rem;">Change who this sale is linked to. Does not change amounts, stock, or payments.</p>
    <form method="post" action="{{ route('admin.b.sales.customer', [$business, $sale->uuid]) }}">
        @csrf @method('PUT')
        <div class="adm-field" style="max-width:360px;">
            <label class="adm-label" for="customer_uuid">Customer</label>
            <select class="adm-select" id="customer_uuid" name="customer_uuid">
                <option value="">Walk-in customer</option>
                @foreach ($customers as $c)
                    <option value="{{ $c->uuid }}" @selected($sale->customer?->uuid === $c->uuid)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn-primary">Save customer</button>
        </div>
    </form>
</div>
@elseif($canManage && $sale->status === 'completed' && $hasCreditPayment)
<div class="adm-card" style="max-width:820px;margin-bottom:1.25rem;">
    <h2 style="margin-top:0;font-family:Outfit,sans-serif;font-size:1.05rem;">Correct customer</h2>
    <p class="adm-page-desc" style="margin:0;">This sale used credit, so the customer cannot be changed here. Void the sale and re-sell if needed.</p>
</div>
@endif

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
<div class="adm-table-wrap" style="max-width:520px;margin-bottom:1.5rem;">
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

@if($canManage && $sale->status === 'completed')
<div class="adm-card" style="max-width:820px;border:1px solid var(--adm-danger,#dc2626);">
    <h2 style="margin-top:0;font-family:Outfit,sans-serif;font-size:1.05rem;color:var(--adm-danger,#dc2626);">Void sale</h2>
    <p class="adm-page-desc" style="margin-top:-0.25rem;">
        Use this to correct a wrong sale. Voiding restores stock from the original batches, reverses customer credit if any, removes the sale from the ledger, and keeps this receipt marked <strong>voided</strong> for audit.
        To fix products or amounts, void here and ring the sale again in POS.
    </p>
    <form
        method="post"
        action="{{ route('admin.b.sales.void', [$business, $sale->uuid]) }}"
        onsubmit="return confirm('Void this sale? Stock will be restored and the journal entry removed.');"
    >
        @csrf
        <div class="adm-field">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="checkbox" name="confirm" value="1" required>
                I understand this cannot be undone from here
            </label>
        </div>
        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn-danger">Void sale</button>
        </div>
    </form>
</div>
@elseif($sale->status === 'voided')
<div class="adm-card" style="max-width:820px;background:rgba(220,38,38,0.06);">
    <p class="adm-page-desc" style="margin:0;">This sale was voided. Stock and ledger have been reversed. Re-enter the correct sale from Point of sale if needed.</p>
</div>
@endif
@endsection
