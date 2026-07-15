<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <title>Stock list — {{ $business->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            width: 100%;
            overflow-x: hidden;
        }
        body {
            font-family: "Courier New", Courier, monospace;
            font-size: 12px;
            line-height: 1.3;
            color: #000;
            background: #fff;
            padding: 2mm;
        }
        .slip {
            width: 88mm;
            max-width: 100%;
            margin: 0 auto;
            overflow: hidden;
            word-wrap: break-word;
        }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .muted { font-size: 10px; }
        .divider { border-top: 1px dashed #000; margin: 5px 0; }
        .title { font-size: 13px; letter-spacing: 0.06em; margin: 3px 0; }
        .meta { font-size: 10px; margin-bottom: 2px; }
        table.items {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        table.items th,
        table.items td {
            text-align: left;
            vertical-align: top;
            padding: 2px 0;
            font-size: 11px;
        }
        table.items th {
            border-bottom: 1px solid #000;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.items .col-name { width: 52%; word-break: break-word; }
        table.items .col-loc { width: 22%; word-break: break-word; }
        table.items .col-qty { width: 14%; text-align: right; white-space: nowrap; }
        table.items .col-flag { width: 12%; text-align: right; white-space: nowrap; font-size: 10px; }
        .low { font-weight: 700; }
        .out { font-weight: 700; }
        .footer { margin-top: 8px; font-size: 10px; }
        .screen-actions {
            margin: 0.75rem auto;
            max-width: 88mm;
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
            html, body { width: 88mm; padding: 0; margin: 0; overflow: visible; }
            body { padding: 0; }
            .screen-actions, .print-hint { display: none !important; }
            @page { size: 88mm auto; margin: 2mm; }
        }
    </style>
</head>
<body>
    <div class="slip">
        <div class="center bold" style="font-size:14px;">{{ $business->name }}</div>
        @if($business->phone)
            <div class="center muted">{{ $business->phone }}</div>
        @endif

        <div class="divider"></div>
        <div class="center title bold">STOCK LIST</div>
        <div class="center muted">{{ $printedAt->format('M j, Y g:i A') }}</div>
        <div class="center muted">{{ $batches->count() }} batch{{ $batches->count() === 1 ? '' : 'es' }}</div>

        <div class="divider"></div>
        <div class="meta">Branch: {{ $locationLabel }}</div>
        <div class="meta">Level: {{ $stockLabel }}</div>
        @if($categoryLabel)
            <div class="meta">Category: {{ $categoryLabel }}</div>
        @endif
        @if($search !== '')
            <div class="meta">Search: {{ $search }}</div>
        @endif

        <div class="divider"></div>

        @if($batches->isEmpty())
            <div class="center muted" style="padding:8px 0;">No batches to print.</div>
        @else
            <table class="items">
                <thead>
                    <tr>
                        <th class="col-name">Product</th>
                        <th class="col-loc">Loc</th>
                        <th class="col-qty">Qty</th>
                        <th class="col-flag"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($batches as $batch)
                        @php
                            $qty = (float) $batch->qty;
                            $isOut = $qty <= 0;
                            $isLow = ! $isOut && $qty < $lowStockThreshold;
                            $qtyText = rtrim(rtrim(number_format($qty, 3, '.', ''), '0'), '.');
                        @endphp
                        <tr>
                            <td class="col-name">
                                {{ $batch->product?->name ?? '—' }}
                                @if($batch->product?->sku)
                                    <div class="muted">{{ $batch->product->sku }}</div>
                                @endif
                            </td>
                            <td class="col-loc">{{ $batch->location?->name ?? '—' }}</td>
                            <td class="col-qty {{ $isOut ? 'out' : ($isLow ? 'low' : '') }}">{{ $qtyText }}</td>
                            <td class="col-flag">
                                @if($isOut) OUT
                                @elseif($isLow) LOW
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="divider"></div>
        <div class="center footer">Printed from CoreBooks · 88mm</div>
    </div>

    <p class="print-hint center muted" style="margin-top:0.75rem;font-size:11px;font-family:system-ui,sans-serif;">
        Choose your 88mm thermal printer when prompted. Paper size: 88mm.
    </p>

    <div class="screen-actions">
        <button type="button" class="primary" onclick="doPrint()">Print stock list</button>
        <a href="{{ $backUrl }}">Back to stock</a>
    </div>

    <script>
        var autoPrint = @json($autoPrint);

        function doPrint() {
            window.print();
        }

        if (autoPrint) {
            window.addEventListener('load', function () {
                setTimeout(doPrint, 350);
            });
        }
    </script>
</body>
</html>
