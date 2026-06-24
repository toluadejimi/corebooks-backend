<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt #{{ $sale->receipt_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            line-height: 1.35;
            color: #000;
            background: #fff;
            padding: 4mm;
        }
        .receipt { width: 58mm; max-width: 100%; margin: 0 auto; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { font-size: 10px; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        .row { display: flex; justify-content: space-between; gap: 4px; }
        .row .name { flex: 1; word-break: break-word; }
        .row .amt { white-space: nowrap; }
        .title { font-size: 13px; letter-spacing: 0.08em; margin: 4px 0; }
        .total { font-size: 14px; margin-top: 4px; }
        .footer { margin-top: 10px; font-style: italic; font-size: 11px; }
        .screen-actions {
            margin: 1rem auto;
            max-width: 58mm;
            display: flex;
            gap: 0.5rem;
            justify-content: center;
        }
        .screen-actions button, .screen-actions a {
            font-family: system-ui, sans-serif;
            font-size: 13px;
            padding: 0.5rem 0.85rem;
            border-radius: 6px;
            border: 1px solid #ccc;
            background: #f5f5f5;
            cursor: pointer;
            text-decoration: none;
            color: #111;
        }
        .screen-actions .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        @media print {
            body { padding: 0; }
            .screen-actions { display: none !important; }
            @page { size: 58mm auto; margin: 2mm; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center bold" style="font-size:14px;">{{ $business->name }}</div>
        @if($business->address_line1)
            <div class="center muted">{{ $business->address_line1 }}</div>
        @endif
        @if($business->city || $business->state)
            <div class="center muted">{{ trim(($business->city ?? '').', '.($business->state ?? ''), ', ') }}</div>
        @endif
        @if($business->phone)
            <div class="center muted">{{ $business->phone }}</div>
        @endif
        @if($business->tax_id)
            <div class="center muted">TIN: {{ $business->tax_id }}</div>
        @endif

        <div class="divider"></div>
        <div class="center title bold">SALES RECEIPT</div>
        <div class="center">#{{ $sale->receipt_no }}</div>
        <div class="center muted">
            {{ $sale->sold_at?->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
        </div>
        @if($sale->customer)
            <div class="center muted">Customer: {{ $sale->customer->name }}</div>
        @endif
        @if($sale->payments->isNotEmpty())
            <div class="center muted">
                Payment: {{ strtoupper($sale->payments->pluck('method')->unique()->implode(' + ')) }}
            </div>
        @endif

        <div class="divider"></div>
        <div class="muted" style="margin-bottom:4px;">Amounts incl. VAT</div>

        @foreach($sale->lines as $line)
            <div class="row" style="margin-bottom:4px;">
                <span class="name">{{ $line->product?->name ?? 'Item' }} × {{ rtrim(rtrim(number_format((float) $line->qty, 3, '.', ''), '0'), '.') }}</span>
                <span class="amt">{{ $currencySymbol }}{{ number_format((float) $line->line_total, 2) }}</span>
            </div>
        @endforeach

        <div class="divider"></div>
        <div class="row"><span>Subtotal (ex VAT)</span><span>{{ $currencySymbol }}{{ number_format((float) $sale->subtotal, 2) }}</span></div>
        <div class="row"><span>VAT</span><span>{{ $currencySymbol }}{{ number_format((float) $sale->tax_total, 2) }}</span></div>
        @if((float) $sale->discount_total > 0)
            <div class="row"><span>Discount</span><span>-{{ $currencySymbol }}{{ number_format((float) $sale->discount_total, 2) }}</span></div>
        @endif
        <div class="row total bold"><span>TOTAL</span><span>{{ $currencySymbol }}{{ number_format((float) $sale->grand_total, 2) }}</span></div>

        <div class="divider"></div>
        <div class="center footer">{{ $receiptFooter }}</div>
    </div>

    <div class="screen-actions">
        <button type="button" class="primary" onclick="window.print()">Print receipt</button>
        <a href="{{ route('admin.b.pos.index', $business) }}">Back to POS</a>
    </div>

    @if($autoPrint)
        <script>window.addEventListener('load', function () { window.print(); });</script>
    @endif
</body>
</html>
