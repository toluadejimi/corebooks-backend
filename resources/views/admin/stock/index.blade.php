@extends('layouts.admin-workspace')

@section('title', 'Stock — '.$business->name)

@section('content')
<h1 class="adm-page-title">Stock &amp; batches</h1>
<p class="adm-page-desc">Each row is a stock batch (product × location). Managers can set on-hand quantity.</p>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Location</th>
                <th>Qty</th>
                <th>Expiry</th>
                @if($canManage)<th>Adjust</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($batches as $batch)
                <tr>
                    <td>{{ $batch->product?->name ?? '—' }}</td>
                    <td>{{ $batch->location?->name ?? '—' }}</td>
                    <td><strong>{{ number_format((float) $batch->qty, 3) }}</strong></td>
                    <td style="color:var(--adm-muted);">{{ $batch->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                    @if($canManage)
                        <td>
                            <form method="post" action="{{ route('admin.b.stock.batch-qty', [$business, $batch->uuid]) }}" class="adm-actions" style="gap:0.35rem;">
                                @csrf
                                <input class="adm-input" name="qty" type="number" step="0.001" min="0" value="{{ $batch->qty }}" style="width:110px;padding:0.4rem 0.5rem;font-size:0.8rem;">
                                <button type="submit" class="adm-btn adm-btn-primary" style="padding:0.4rem 0.65rem;font-size:0.8rem;">Save</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 5 : 4 }}" style="color:var(--adm-muted);">No batches. Add products with initial stock.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
