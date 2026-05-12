@extends('layouts.admin-workspace')

@section('title', 'Purchase — '.$business->name)

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.b.purchases.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Purchases</a></p>

<h1 class="adm-page-title">Purchase breakdown</h1>
<p class="adm-page-desc">
    {{ $po->ordered_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
    · {{ $po->location?->name ?? '—' }}
    · {{ $po->supplier?->name ?? '—' }}
    @if($po->supplier?->phone)
        · {{ $po->supplier->phone }}
    @endif
</p>

<div class="adm-card" style="max-width:820px;margin-bottom:1.25rem;">
    <div class="adm-grid cols-2" style="gap:1rem;">
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Receipt total</span>
            <strong style="font-size:1.25rem;font-family:Outfit,sans-serif;">{{ $currencySymbol }}{{ number_format((float) $po->total, 2) }}</strong>
        </div>
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Status</span>
            <strong>{{ $po->status }}</strong>
        </div>
    </div>
    <p style="margin:1rem 0 0;font-size:0.8rem;color:var(--adm-muted);">
        <strong>Purchase UUID</strong> (this is the ID in <code>/purchases/…</code> URLs): <code style="user-select:all;">{{ $po->uuid }}</code>
    </p>
</div>

<h2 class="adm-page-title" style="font-size:1.05rem;margin-bottom:0.5rem;">Lines received</h2>
<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit cost</th>
                <th style="text-align:right;">Line total</th>
                <th>Expiry</th>
                <th style="color:var(--adm-muted);max-width:10rem;">Stock batch UUID <span style="font-weight:400;">(≠ purchase URL)</span></th>
            </tr>
        </thead>
        <tbody>
            @php $linesSum = 0.0; @endphp
            @foreach ($po->lines as $line)
                @php $lt = (float) $line->qty * (float) $line->unit_cost; $linesSum += $lt; @endphp
                <tr>
                    <td>{{ $line->product?->name ?? '—' }}</td>
                    <td>{{ number_format((float) $line->qty, 3) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format((float) $line->unit_cost, 2) }}</td>
                    <td style="text-align:right;"><strong>{{ $currencySymbol }}{{ number_format($lt, 2) }}</strong></td>
                    <td style="color:var(--adm-muted);">{{ $line->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                    <td style="font-size:0.75rem;color:var(--adm-muted);word-break:break-all;">{{ $line->productBatch?->uuid ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">Sum of line totals</th>
                <th style="text-align:right;">{{ $currencySymbol }}{{ number_format($linesSum, 2) }}</th>
                <th colspan="2" style="color:var(--adm-muted);font-weight:500;">
                    @if(abs($linesSum - (float) $po->total) > 0.02)
                        <span style="color:#b45309;">Differs from header total — check data.</span>
                    @else
                        Matches receipt total.
                    @endif
                </th>
            </tr>
        </tfoot>
    </table>
</div>
@endsection
