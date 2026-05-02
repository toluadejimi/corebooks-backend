@extends('layouts.admin-workspace')

@php($editing = $product !== null)
@section('title', ($editing ? 'Edit' : 'New').' product — '.$business->name)

@section('content')
<h1 class="adm-page-title">{{ $editing ? 'Edit product' : 'New product' }}</h1>
<p class="adm-page-desc">{{ $editing ? 'Update catalog fields. Stock remains on batches — adjust under Stock.' : 'Initial stock creates the first batch at the selected location.' }}</p>

@if($canManage)
<div class="adm-card" style="max-width:640px;margin-bottom:1rem;">
    <h2 style="margin-top:0;font-size:1rem;font-family:Outfit,sans-serif;">Quick add category</h2>
    <form method="post" action="{{ route('admin.b.categories.store', $business) }}" class="adm-grid cols-2" style="gap:0.75rem;align-items:end;">
        @csrf
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="new_category_name">New category name</label>
            <input class="adm-input" id="new_category_name" name="name" placeholder="e.g. Beverages" required>
        </div>
        <div><button type="submit" class="adm-btn adm-btn-ghost">Create category</button></div>
    </form>
</div>
@endif

<div class="adm-card" style="max-width:640px;">
    <form method="post" action="{{ $editing ? route('admin.b.products.update', [$business, $product]) : route('admin.b.products.store', $business) }}">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="adm-field">
            <label class="adm-label" for="name">Name</label>
            <input class="adm-input" id="name" name="name" required value="{{ old('name', $product->name ?? '') }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="image_url">Product image URL</label>
            <input class="adm-input" id="image_url" name="image_url" maxlength="2048" placeholder="https://…" value="{{ old('image_url', $product->image_url ?? '') }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="category_id">Category</label>
            <select class="adm-select" id="category_id" name="category_id">
                <option value="">— None —</option>
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" @selected((string) old('category_id', $product->category_id ?? '') === (string) $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="sku">SKU</label>
                <input class="adm-input" id="sku" name="sku" value="{{ old('sku', $product->sku ?? '') }}">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="barcode">Barcode</label>
                <input class="adm-input" id="barcode" name="barcode" value="{{ old('barcode', $product->barcode ?? '') }}">
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="cost_price">Cost price</label>
                <input class="adm-input" id="cost_price" name="cost_price" type="number" step="0.01" min="0" value="{{ old('cost_price', $product->cost_price ?? 0) }}">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="selling_price">Selling price</label>
                <input class="adm-input" id="selling_price" name="selling_price" type="number" step="0.01" min="0" value="{{ old('selling_price', $product->selling_price ?? 0) }}">
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="vat_rate">VAT %</label>
                <input class="adm-input" id="vat_rate" name="vat_rate" type="number" step="0.01" min="0" max="100" value="{{ old('vat_rate', $product->vat_rate ?? $business->default_vat_rate) }}">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="low_stock_threshold">Low stock threshold</label>
                <input class="adm-input" id="low_stock_threshold" name="low_stock_threshold" type="number" min="0" value="{{ old('low_stock_threshold', $product->low_stock_threshold ?? 0) }}">
            </div>
        </div>
        @if(!$editing)
            <div class="adm-grid cols-2">
                <div class="adm-field">
                    <label class="adm-label" for="initial_qty">Initial stock qty</label>
                    <input class="adm-input" id="initial_qty" name="initial_qty" type="number" step="0.001" min="0" value="{{ old('initial_qty', 0) }}">
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="location_uuid">Location</label>
                    <select class="adm-select" id="location_uuid" name="location_uuid">
                        @foreach ($locations as $loc)
                            <option value="{{ $loc->uuid }}" @selected($loc->is_default)>{{ $loc->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="expiry_date">Batch expiry (optional)</label>
                <input class="adm-input" id="expiry_date" name="expiry_date" type="date" value="{{ old('expiry_date') }}">
            </div>
        @endif
        <div class="adm-actions">
            <button type="submit" class="adm-btn adm-btn-primary">Save</button>
            <a href="{{ route('admin.b.products.index', $business) }}" class="adm-btn adm-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
