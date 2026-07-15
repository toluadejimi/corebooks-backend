@extends('layouts.admin-workspace')

@section('title', 'Stock — '.$business->name)

@section('content')
<h1 class="adm-page-title">Stock &amp; batches</h1>
<p class="adm-page-desc">
    Each row is a <strong>stock batch</strong>, not a product — a new batch is created when you receive a purchase (and sometimes when adding stock).
    Empty leftover batches can make the same product appear many times at 0. Default view shows on-hand only.
    Products with total stock under {{ $lowStockThreshold }} are flagged below.
</p>

@if($lowStockProducts->isNotEmpty())
    <div class="adm-card" style="border:1px solid #d97706;background:rgba(217,119,6,0.1);padding:0.9rem 1rem;margin-bottom:1rem;border-radius:10px;">
        <strong style="display:block;margin-bottom:0.35rem;color:#b45309;">
            Low stock alert — {{ $lowStockProducts->count() }} product{{ $lowStockProducts->count() === 1 ? '' : 's' }} under {{ $lowStockThreshold }}
        </strong>
        <ul style="margin:0;padding-left:1.15rem;color:#92400e;font-size:0.9rem;">
            @foreach ($lowStockProducts as $p)
                <li>
                    <strong>{{ $p->name }}</strong>
                    @if($p->sku)
                        <span style="opacity:0.8;">(SKU {{ $p->sku }})</span>
                    @endif
                    — {{ number_format((float) $p->total_qty, 2) }} on hand
                </li>
            @endforeach
        </ul>
        <div class="adm-actions" style="margin-top:0.65rem;">
            <a
                class="adm-btn adm-btn-ghost"
                href="{{ route('admin.b.stock.index', array_merge(['business' => $business, 'stock' => 'low'], array_filter(['q' => $search ?: null, 'location_uuid' => $locationUuid ?: null, 'category_id' => $categoryId]))) }}"
                style="padding:0.35rem 0.65rem;font-size:0.8rem;"
            >Show low-qty batches</a>
        </div>
    </div>
@endif

<form method="get" action="{{ route('admin.b.stock.index', $business) }}" class="adm-card" style="margin-bottom:1rem;">
    <div class="adm-grid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:0.75rem;">
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="q">Search name, SKU or barcode</label>
            <input class="adm-input" id="q" name="q" value="{{ $search }}" placeholder="e.g. Coke" autofocus>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="location_uuid">Branch</label>
            <select class="adm-select" id="location_uuid" name="location_uuid">
                <option value="">All branches</option>
                @foreach ($locations as $loc)
                    <option value="{{ $loc->uuid }}" @selected($locationUuid === $loc->uuid)>{{ $loc->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="category_id">Category</label>
            <select class="adm-select" id="category_id" name="category_id">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="stock">Stock level</label>
            <select class="adm-select" id="stock" name="stock">
                <option value="on_hand" @selected($stockFilter === 'on_hand')>On hand (hide empty)</option>
                <option value="low" @selected($stockFilter === 'low')>Low (under {{ $lowStockThreshold }})</option>
                <option value="out" @selected($stockFilter === 'out')>Empty / out of stock</option>
                <option value="all" @selected($stockFilter === 'all')>All batches</option>
            </select>
        </div>
    </div>
    <div class="adm-actions" style="margin-top:0.75rem;flex-wrap:wrap;">
        <button class="adm-btn adm-btn-primary" type="submit">Apply</button>
        @if($search !== '' || $locationUuid !== '' || $categoryId || $stockFilter !== 'on_hand')
            <a class="adm-btn adm-btn-ghost" href="{{ route('admin.b.stock.index', $business) }}">Clear</a>
        @endif
        <span style="flex:1 1 auto;"></span>
        <a
            class="adm-btn adm-btn-ghost"
            href="{{ route('admin.b.stock.print', array_merge(['business' => $business], $filterQuery)) }}"
            target="_blank"
            rel="noopener"
        >Print 88mm</a>
    </div>
</form>

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Product</th>
                <th>SKU</th>
                <th>Location</th>
                <th>Qty</th>
                <th>Expiry</th>
                @if($canManage)<th>Adjust</th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($batches as $batch)
                @php
                    $qty = (float) $batch->qty;
                    $isLow = $qty > 0 && $qty < $lowStockThreshold;
                    $isOut = $qty <= 0;
                @endphp
                <tr @if($isOut) style="background:rgba(220,38,38,0.06);" @elseif($isLow) style="background:rgba(217,119,6,0.08);" @endif>
                    <td>
                        <strong>{{ $batch->product?->name ?? '—' }}</strong>
                        @if($batch->product?->category)
                            <div style="font-size:0.75rem;color:var(--adm-muted);">{{ $batch->product->category->name }}</div>
                        @endif
                    </td>
                    <td style="color:var(--adm-muted);">{{ $batch->product?->sku ?? '—' }}</td>
                    <td>{{ $batch->location?->name ?? '—' }}</td>
                    <td>
                        <strong @if($isOut) style="color:var(--adm-danger,#dc2626);" @elseif($isLow) style="color:#b45309;" @endif>
                            {{ number_format($qty, 3) }}
                        </strong>
                        @if($isOut)
                            <span class="adm-role-pill" style="margin-left:0.35rem;font-size:0.65rem;background:rgba(220,38,38,0.12);color:#b91c1c;">Out</span>
                        @elseif($isLow)
                            <span class="adm-role-pill" style="margin-left:0.35rem;font-size:0.65rem;background:rgba(217,119,6,0.15);color:#b45309;">Low</span>
                        @endif
                    </td>
                    <td style="color:var(--adm-muted);">{{ $batch->expiry_date?->format('Y-m-d') ?? '—' }}</td>
                    @if($canManage)
                        <td>
                            <form method="post" action="{{ route('admin.b.stock.batch-qty', [$business, $batch->uuid]) }}" class="adm-actions" style="gap:0.35rem;">
                                @csrf
                                @foreach ($filterQuery as $key => $val)
                                    <input type="hidden" name="{{ $key }}" value="{{ $val }}">
                                @endforeach
                                <input class="adm-input" name="qty" type="number" step="0.001" min="0" value="{{ $batch->qty }}" style="width:110px;padding:0.4rem 0.5rem;font-size:0.8rem;">
                                <button type="submit" class="adm-btn adm-btn-primary" style="padding:0.4rem 0.65rem;font-size:0.8rem;">Save</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $canManage ? 6 : 5 }}" style="color:var(--adm-muted);">
                        @if($search !== '' || $locationUuid !== '' || $categoryId || $stockFilter !== 'on_hand')
                            No batches match your filters.
                            @if($stockFilter === 'on_hand')
                                Try <a href="{{ route('admin.b.stock.index', array_merge(['business' => $business], array_filter(['q' => $search ?: null, 'location_uuid' => $locationUuid ?: null, 'category_id' => $categoryId, 'stock' => 'all']))) }}">All batches</a> to see empty leftovers.
                            @endif
                        @else
                            No on-hand stock right now. Receive a purchase or choose <em>All batches</em> / <em>Empty</em> to see zero-qty rows.
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
