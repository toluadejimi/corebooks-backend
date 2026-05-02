@extends('layouts.admin-workspace')

@section('title', 'Purchase — '.$business->name)

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.b.purchases.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Purchases</a></p>

<h1 class="adm-page-title">Purchase receipt</h1>
<p class="adm-page-desc">
    {{ $po->ordered_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}
    · {{ $po->location?->name ?? '—' }}
    · {{ $po->supplier?->name ?? '—' }}
    @if($po->supplier?->phone)
        · {{ $po->supplier->phone }}
    @endif
</p>

<div class="adm-card" style="max-width:720px;margin-bottom:1.25rem;">
    <div class="adm-grid cols-2" style="gap:1rem;">
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Total</span>
            <strong style="font-size:1.25rem;font-family:Outfit,sans-serif;">{{ $currencySymbol }}{{ number_format((float) $po->total, 2) }}</strong>
        </div>
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Status</span>
            <strong>{{ $po->status }}</strong>
        </div>
    </div>
    <p style="margin:1rem 0 0;font-size:0.8rem;color:var(--adm-muted);">UUID: {{ $po->uuid }}</p>
</div>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Unit cost</th>
                <th>Line total</th>
                <th>Expiry</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($po->lines as $line)
                @php $lt = (float) $line->qty * (float) $line->unit_cost; @endphp
                <tr>
                    <td>{{ $line->product?->name ?? '—' }}</td>
                    <td>{{ number_format((float) $line->qty, 3) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format((float) $line->unit_cost, 2) }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($lt, 2) }}</td>
                    <td style="color:var(--adm-muted);">{{ $line->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
