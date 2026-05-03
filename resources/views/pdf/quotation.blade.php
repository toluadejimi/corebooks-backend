<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #111; margin: 14px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #475569; font-size: 9px; margin: 0 0 14px; }
        .grid { width: 100%; margin-bottom: 14px; }
        .grid td { vertical-align: top; width: 50%; padding: 0 8px 0 0; }
        .label { font-size: 8px; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; margin-bottom: 2px; }
        table.lines { border-collapse: collapse; width: 100%; margin-top: 8px; }
        table.lines th { background: #f1f5f9; font-size: 8px; text-transform: uppercase; letter-spacing: 0.04em; }
        table.lines th, table.lines td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .totals { margin-top: 12px; width: 260px; margin-left: auto; font-size: 10px; }
        .totals td { padding: 4px 0; border-bottom: 1px solid #e2e8f0; }
        .totals .grand { font-weight: bold; font-size: 12px; border-bottom: none; }
        .notes { margin-top: 16px; font-size: 9px; color: #334155; line-height: 1.45; }
    </style>
</head>
<body>
<h1>Quotation</h1>
<p class="muted">{{ $business->name }} · {{ $quotation->number }} · {{ $quotation->created_at?->format('Y-m-d') }}</p>

<table class="grid">
    <tr>
        <td>
            <div class="label">Bill to</div>
            <strong>{{ $quotation->client_name }}</strong><br/>
            @if($quotation->client_email){{ $quotation->client_email }}<br/>@endif
            @if($quotation->client_phone){{ $quotation->client_phone }}<br/>@endif
            @if($quotation->client_address){!! nl2br(e($quotation->client_address)) !!}@endif
        </td>
        <td>
            <div class="label">Valid until</div>
            {{ $quotation->valid_until?->format('Y-m-d') ?? '—' }}<br/><br/>
            <div class="label">Currency</div>
            {{ $quotation->currency }}
        </td>
    </tr>
</table>

<table class="lines">
    <thead>
    <tr>
        <th>#</th>
        <th>Description</th>
        <th class="num">Qty</th>
        <th class="num">Unit</th>
        <th class="num">VAT %</th>
        <th class="num">Line total</th>
    </tr>
    </thead>
    <tbody>
    @foreach($quotation->lines as $i => $line)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $line->description }}</td>
            <td class="num">{{ number_format((float) $line->quantity, 4, '.', '') }}</td>
            <td class="num">{{ $sym }}{{ number_format((float) $line->unit_price, 2, '.', ',') }}</td>
            <td class="num">{{ $line->vat_percent !== null ? number_format((float) $line->vat_percent, 2) : '—' }}</td>
            <td class="num">{{ $sym }}{{ number_format((float) $line->line_total, 2, '.', ',') }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

<table class="totals">
    <tr>
        <td>Subtotal (ex VAT)</td>
        <td class="num">{{ $sym }}{{ number_format((float) $quotation->subtotal_ex_vat, 2, '.', ',') }}</td>
    </tr>
    <tr>
        <td>VAT</td>
        <td class="num">{{ $sym }}{{ number_format((float) $quotation->vat_total, 2, '.', ',') }}</td>
    </tr>
    <tr class="grand">
        <td>Grand total</td>
        <td class="num">{{ $sym }}{{ number_format((float) $quotation->grand_total, 2, '.', ',') }}</td>
    </tr>
</table>

@if($quotation->notes)
    <div class="notes">
        <div class="label">Notes</div>
        {!! nl2br(e($quotation->notes)) !!}
    </div>
@endif
</body>
</html>
