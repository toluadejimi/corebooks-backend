@extends('layouts.admin-workspace')

@section('title', 'Products — '.$business->name)

@section('content')
<h1 class="adm-page-title">Products</h1>
<p class="adm-page-desc">Catalog items with category and image, plus on-hand totals (sum of batches).</p>

@if($canManage)
    <div class="adm-actions" style="margin-bottom:1rem;">
        <a href="{{ route('admin.b.products.create', $business) }}" class="adm-btn adm-btn-primary">+ New product</a>
    </div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th style="width:52px;"></th>
                <th>Name</th>
                <th>Category</th>
                <th>SKU</th>
                <th>Sell</th>
                <th>Stock</th>
                @if($canManage)<th></th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $p)
                <tr>
                    <td>
                        @if($p->image_url)
                            <img src="{{ $p->image_url }}" alt="" width="40" height="40" style="object-fit:cover;border-radius:8px;border:1px solid var(--adm-border);">
                        @else
                            <div style="width:40px;height:40px;border-radius:8px;background:var(--adm-accent-soft);display:flex;align-items:center;justify-content:center;color:var(--adm-muted);font-size:0.65rem;">—</div>
                        @endif
                    </td>
                    <td><strong>{{ $p->name }}</strong></td>
                    <td style="color:var(--adm-muted);">{{ $p->category?->name ?? '—' }}</td>
                    <td style="color:var(--adm-muted);">{{ $p->sku ?? '—' }}</td>
                    <td>{{ $currencySymbol }}{{ number_format($p->selling_price, 2) }}</td>
                    <td>{{ number_format((float) ($p->batches_sum_qty ?? 0), 2) }}</td>
                    @if($canManage)
                        <td class="adm-actions">
                            <a href="{{ route('admin.b.products.edit', [$business, $p->uuid]) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.8rem;">Edit</a>
                            <form action="{{ route('admin.b.products.destroy', [$business, $p->uuid]) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.35rem 0.65rem;font-size:0.8rem;">Delete</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 7 : 6 }}" style="color:var(--adm-muted);">No products yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
