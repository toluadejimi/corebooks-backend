@extends('layouts.admin-workspace')

@section('title', 'Record purchase — '.$business->name)

@section('content')
@php
    $defaultLoc = $locations->firstWhere('is_default') ?? $locations->first();
@endphp
<p style="margin:0 0 1rem;"><a href="{{ route('admin.b.purchases.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Purchases</a></p>

<h1 class="adm-page-title">Record purchase</h1>
<p class="adm-page-desc">Receive stock from a supplier. Creates a purchase record, new batches at the branch, stock movements, and updates each product’s catalog cost to the unit cost on that line.</p>

@if($errors->has('purchase'))
    <div class="adm-flash err" style="margin-bottom:1rem;">{{ $errors->first('purchase') }}</div>
@endif

<div class="adm-card" style="max-width:920px;">
    <form method="post" action="{{ route('admin.b.purchases.store', $business) }}" id="purchase-form">
        @csrf
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="location_uuid">Receive at branch</label>
                <select class="adm-select" id="location_uuid" name="location_uuid" required>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->uuid }}" @selected(old('location_uuid', $defaultLoc?->uuid) === $loc->uuid)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="supplier_uuid">Existing supplier</label>
                <select class="adm-select" id="supplier_uuid" name="supplier_uuid">
                    <option value="">— New supplier (fill name below) —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->uuid }}" @selected(old('supplier_uuid') === $s->uuid)>{{ $s->name }}@if($s->phone) · {{ $s->phone }}@endif</option>
                    @endforeach
                </select>
                @if($canManage ?? false)
                    <p style="margin:0.35rem 0 0;font-size:0.8rem;"><a href="{{ route('admin.b.suppliers.index', $business) }}">Manage suppliers →</a></p>
                @endif
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="supplier_name">New supplier name</label>
                <input class="adm-input" id="supplier_name" name="supplier_name" value="{{ old('supplier_name') }}" placeholder="Required if no supplier selected above">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="supplier_phone">New supplier phone</label>
                <input class="adm-input" id="supplier_phone" name="supplier_phone" value="{{ old('supplier_phone') }}" placeholder="Optional">
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="ordered_at">Purchase date</label>
                <input class="adm-input" id="ordered_at" name="ordered_at" type="date" value="{{ old('ordered_at', $today) }}">
                <p style="margin:0.35rem 0 0;font-size:0.8rem;color:var(--adm-muted);">Backdate if you're recording an older receipt.</p>
            </div>
        </div>

        <h2 style="font-family:Outfit,sans-serif;font-size:1.05rem;margin:1.25rem 0 0.75rem;">Lines</h2>
        <p class="adm-page-desc" style="margin-top:-0.25rem;">At least one line with product, quantity, and unit cost.</p>

        <div id="lines-wrap"></div>
        <button type="button" class="adm-btn adm-btn-ghost" id="add-line" style="margin-top:0.75rem;">+ Add line</button>

        <div class="adm-actions" style="margin-top:1.5rem;">
            <button type="submit" class="adm-btn adm-btn-primary">Receive stock</button>
        </div>
    </form>
</div>

<template id="line-template">
    <div class="purchase-line adm-card" style="padding:1rem;margin-bottom:0.75rem;background:var(--adm-accent-soft);border-color:#c7d2fe;">
        <div class="adm-grid cols-2" style="gap:0.75rem;">
            <div class="adm-field" style="grid-column:1/-1;margin:0;">
                <label class="adm-label">Product</label>
                <select class="adm-select product-select" data-name-product required>
                    <option value="">— Select —</option>
                    @foreach ($products as $p)
                        <option value="{{ $p->uuid }}" data-cost="{{ $p->cost_price }}">{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-field" style="margin:0;">
                <label class="adm-label">Qty</label>
                <input class="adm-input qty-input" type="number" step="0.001" min="0.001" required placeholder="0" data-name-qty>
            </div>
            <div class="adm-field" style="margin:0;">
                <label class="adm-label">Unit cost</label>
                <input class="adm-input unit-cost-input" type="number" step="0.01" min="0" required placeholder="0.00" data-name-unitcost>
            </div>
            <div class="adm-field" style="grid-column:1/-1;margin:0;">
                <label class="adm-label">Batch expiry (optional)</label>
                <input class="adm-input expiry-input" type="date" data-name-expiry>
            </div>
            <div style="grid-column:1/-1;">
                <button type="button" class="adm-btn adm-btn-danger remove-line" style="padding:0.35rem 0.65rem;font-size:0.8rem;">Remove line</button>
            </div>
        </div>
    </div>
</template>

<script>
(function () {
    var wrap = document.getElementById('lines-wrap');
    var tpl = document.getElementById('line-template');
    var addBtn = document.getElementById('add-line');
    var oldLines = @json(old('lines', []));

    function applyNames(block, i) {
        block.querySelector('[data-name-product]').setAttribute('name', 'lines[' + i + '][product_uuid]');
        block.querySelector('[data-name-qty]').setAttribute('name', 'lines[' + i + '][qty]');
        block.querySelector('[data-name-unitcost]').setAttribute('name', 'lines[' + i + '][unit_cost]');
        block.querySelector('[data-name-expiry]').setAttribute('name', 'lines[' + i + '][expiry_date]');
    }

    function renumberLines() {
        var blocks = wrap.querySelectorAll('.purchase-line');
        blocks.forEach(function (block, idx) {
            applyNames(block, idx);
        });
    }

    function bindLine(root) {
        root.querySelector('.remove-line').addEventListener('click', function () {
            root.remove();
            renumberLines();
        });
        var sel = root.querySelector('.product-select');
        var costIn = root.querySelector('.unit-cost-input');
        sel.addEventListener('change', function () {
            var opt = sel.options[sel.selectedIndex];
            var c = opt.getAttribute('data-cost');
            if (c !== null && c !== '' && (costIn.value === '' || costIn.value === '0')) {
                costIn.value = parseFloat(c).toFixed(2);
            }
        });
    }

    function addLine(prefill) {
        var node = tpl.content.cloneNode(true);
        var div = node.querySelector('.purchase-line');
        wrap.appendChild(div);
        bindLine(div);
        renumberLines();

        if (prefill && typeof prefill === 'object') {
            var last = wrap.querySelector('.purchase-line:last-of-type');
            if (!last) return;
            if (prefill.product_uuid) last.querySelector('.product-select').value = prefill.product_uuid;
            if (prefill.qty != null && prefill.qty !== '') last.querySelector('.qty-input').value = prefill.qty;
            if (prefill.unit_cost != null && prefill.unit_cost !== '') last.querySelector('.unit-cost-input').value = prefill.unit_cost;
            if (prefill.expiry_date) last.querySelector('.expiry-input').value = prefill.expiry_date;
        }
    }

    addBtn.addEventListener('click', function () { addLine(null); });

    if (Array.isArray(oldLines) && oldLines.length > 0) {
        oldLines.forEach(function (row) { addLine(row); });
    } else {
        addLine(null);
    }
})();
</script>
@endsection
