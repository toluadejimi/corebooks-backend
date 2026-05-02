@extends('layouts.admin-workspace')

@section('title', 'Overview — '.$business->name)

@section('content')
<h1 class="adm-page-title">Overview</h1>
<p class="adm-page-desc">Snapshot for <strong>{{ $business->name }}</strong>. Use the sidebar to manage products, stock batches, and team.</p>

<div class="adm-grid cols-4" style="margin-bottom:2rem;">
    <div class="adm-stat">
        <div class="adm-stat-val">{{ $productCount }}</div>
        <div class="adm-stat-lbl">Products</div>
    </div>
    <div class="adm-stat">
        <div class="adm-stat-val">{{ number_format($totalQty, 0) }}</div>
        <div class="adm-stat-lbl">Units in stock</div>
    </div>
    <div class="adm-stat">
        <div class="adm-stat-val">₦{{ number_format($stockValue, 0) }}</div>
        <div class="adm-stat-lbl">Stock value (cost)</div>
    </div>
    <div class="adm-stat">
        <div class="adm-stat-val">{{ $lowStockCount }}</div>
        <div class="adm-stat-lbl">Low / out of stock</div>
    </div>
</div>

<div class="adm-grid cols-2">
    <div class="adm-card">
        <h3 style="font-family:Outfit,sans-serif;font-size:1rem;margin:0 0 0.5rem;">Locations</h3>
        <p style="color:var(--adm-muted);margin:0;font-size:0.9rem;">{{ $locationCount }} location(s) configured.</p>
    </div>
    <div class="adm-card">
        <h3 style="font-family:Outfit,sans-serif;font-size:1rem;margin:0 0 0.5rem;">Team</h3>
        <p style="color:var(--adm-muted);margin:0;font-size:0.9rem;">{{ $teamCount }} member(s).</p>
        @if($canManage)
            <p style="margin:0.75rem 0 0;"><a href="{{ route('admin.b.team.index', $business) }}">Manage team →</a></p>
        @endif
    </div>
</div>
@endsection
