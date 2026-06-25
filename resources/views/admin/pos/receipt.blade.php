<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Receipt #{{ $sale->receipt_no }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 100%;
            overflow-x: hidden;
        }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 11px;
            line-height: 1.3;
            color: #000;
            background: #fff;
            padding: 2mm;
        }
        .receipt {
            width: 58mm;
            max-width: 100%;
            margin: 0 auto;
            overflow: hidden;
            word-wrap: break-word;
        }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { font-size: 9px; }
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        .row {
            display: flex;
            justify-content: space-between;
            gap: 3px;
            align-items: flex-start;
        }
        .row .name { flex: 1; min-width: 0; word-break: break-word; }
        .row .amt { flex-shrink: 0; white-space: nowrap; }
        .title { font-size: 12px; letter-spacing: 0.06em; margin: 3px 0; }
        .total { font-size: 13px; margin-top: 3px; }
        .footer { margin-top: 8px; font-style: italic; font-size: 10px; }
        .screen-actions {
            margin: 0.75rem auto;
            max-width: 58mm;
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }
        .screen-actions button, .screen-actions a {
            font-family: system-ui, sans-serif;
            font-size: 14px;
            padding: 0.65rem;
            border-radius: 8px;
            border: 1px solid #ccc;
            background: #f5f5f5;
            cursor: pointer;
            text-decoration: none;
            color: #111;
            text-align: center;
            touch-action: manipulation;
        }
        .screen-actions .primary { background: #1d4ed8; color: #fff; border-color: #1d4ed8; }
        @media print {
            html, body { width: 58mm; padding: 0; margin: 0; overflow: visible; }
            body { padding: 0; }
            .screen-actions, .print-hint { display: none !important; }
            @page { size: 58mm auto; margin: 1mm; }
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="center bold" style="font-size:13px;">{{ $business->name }}</div>
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
        @if($sale->customer && !$sale->customer->is_walk_in)
            <div class="center muted">{{ $sale->customer->name }}</div>
        @endif
        @if($sale->payments->isNotEmpty())
            <div class="center muted">
                {{ strtoupper($sale->payments->pluck('method')->unique()->implode(' + ')) }}
            </div>
        @endif

        <div class="divider"></div>

        @foreach($sale->lines as $line)
            <div class="row" style="margin-bottom:3px;">
                <span class="name">{{ $line->product?->name ?? 'Item' }} × {{ rtrim(rtrim(number_format((float) $line->qty, 3, '.', ''), '0'), '.') }} {{ $line->product?->unit ?? 'pcs' }}</span>
                <span class="amt">{{ $currencySymbol }}{{ number_format((float) $line->line_total, 2) }}</span>
            </div>
        @endforeach

        <div class="divider"></div>
        <div class="row"><span>Subtotal</span><span>{{ $currencySymbol }}{{ number_format((float) $sale->subtotal, 2) }}</span></div>
        <div class="row"><span>VAT</span><span>{{ $currencySymbol }}{{ number_format((float) $sale->tax_total, 2) }}</span></div>
        @if((float) $sale->discount_total > 0)
            <div class="row"><span>Discount</span><span>-{{ $currencySymbol }}{{ number_format((float) $sale->discount_total, 2) }}</span></div>
        @endif
        <div class="row total bold"><span>TOTAL</span><span>{{ $currencySymbol }}{{ number_format((float) $sale->grand_total, 2) }}</span></div>

        <div class="divider"></div>
        <div class="center footer">{{ $receiptFooter }}</div>
    </div>

    <p class="print-hint center muted" style="margin-top:0.75rem;font-size:11px;font-family:system-ui,sans-serif;">
        Select the built-in printer on your S60 when prompted.
    </p>

    <div class="screen-actions">
        <button type="button" class="primary" onclick="doPrint()">Print receipt</button>
        <a href="{{ route('admin.b.pos.index', $business) }}">Back to POS</a>
    </div>

    <script>
        var returnPos = @json($returnToPos);
        var posUrl = @json(route('admin.b.pos.index', $business));
        var autoPrint = @json($autoPrint);
        var printed = false;

        function goBackToPos() {
            window.location.replace(posUrl);
        }

        function doPrint() {
            printed = true;
            window.print();
        }

        if (autoPrint) {
            window.addEventListener('load', function () {
                setTimeout(function () { doPrint(); }, 350);
            });
        }

        window.addEventListener('afterprint', function () {
            if (returnPos) goBackToPos();
        });

        if (returnPos && autoPrint) {
            setTimeout(function () {
                if (!printed) doPrint();
            }, 800);
            setTimeout(goBackToPos, 12000);
        }
    </script>
</body>
</html>
