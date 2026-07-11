@extends('layouts.admin-workspace')

@section('title', ($purchase ? 'Edit draft purchase' : 'Record purchase').' — '.$business->name)

@section('content')
@php
    $defaultLoc = $locations->firstWhere('is_default') ?? $locations->first();
    $isDraft = $purchase !== null;
    $formAction = $isDraft
        ? route('admin.b.purchases.receive', [$business, $purchase->uuid])
        : route('admin.b.purchases.store', $business);
    $draftAction = $isDraft
        ? route('admin.b.purchases.draft', [$business, $purchase->uuid])
        : route('admin.b.purchases.store', $business);
    $prefillLocation = old('location_uuid', $purchase?->location?->uuid ?? $defaultLoc?->uuid);
    $prefillSupplier = old('supplier_uuid', $purchase?->supplier?->uuid);
    $prefillOrderedAt = old('ordered_at', $purchase?->ordered_at?->toDateString() ?? $today);
    $initialLines = old('lines', $draftLines ?? []);
@endphp
<p style="margin:0 0 1rem;"><a href="{{ route('admin.b.purchases.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Purchases</a></p>

<h1 class="adm-page-title">{{ $isDraft ? 'Continue draft purchase' : 'Record purchase' }}</h1>
<p class="adm-page-desc">
    @if($isDraft)
        This draft has not updated stock yet. Save again anytime, or receive stock when payment and lines are ready.
    @else
        Receive stock from a supplier. Creates a purchase record, new batches at the branch, stock movements, and updates each product’s catalog cost to the unit cost on that line. Use <strong>Save as draft</strong> to come back later without touching stock or funds.
    @endif
</p>

@if($errors->has('purchase'))
    <div class="adm-flash err" style="margin-bottom:1rem;">{{ $errors->first('purchase') }}</div>
@endif

<div class="adm-card" style="max-width:920px;">
    <form method="post" action="{{ $formAction }}" id="purchase-form">
        @csrf
        <input type="hidden" name="intent" id="intent-field" value="receive">
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="location_uuid">Receive at branch</label>
                <select class="adm-select" id="location_uuid" name="location_uuid" required>
                    @foreach ($locations as $loc)
                        <option value="{{ $loc->uuid }}" @selected($prefillLocation === $loc->uuid)>{{ $loc->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="supplier_uuid">Existing supplier</label>
                <select class="adm-select" id="supplier_uuid" name="supplier_uuid">
                    <option value="">— New supplier (fill name below) —</option>
                    @foreach ($suppliers as $s)
                        <option value="{{ $s->uuid }}" @selected($prefillSupplier === $s->uuid)>{{ $s->name }}@if($s->phone) · {{ $s->phone }}@endif</option>
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
                <input class="adm-input" id="ordered_at" name="ordered_at" type="date" value="{{ $prefillOrderedAt }}">
                <p style="margin:0.35rem 0 0;font-size:0.8rem;color:var(--adm-muted);">Backdate if you're recording an older receipt.</p>
            </div>
        </div>

        <h2 style="font-family:Outfit,sans-serif;font-size:1.05rem;margin:1.25rem 0 0.75rem;">Payment</h2>
        <p class="adm-page-desc" style="margin-top:-0.25rem;">Required when you receive stock. Skipped for drafts — funds are deducted only on receive.</p>
        <div class="adm-card" style="padding:1rem;margin-bottom:1rem;background:var(--adm-accent-soft);border-color:#c7d2fe;">
            <div class="adm-field" style="margin:0 0 0.75rem;">
                <label class="adm-label">
                    <input type="checkbox" id="pay-split" name="pay_split" value="1" @checked(old('pay_split')) style="margin-right:0.35rem;">
                    Split cash &amp; bank transfer
                </label>
            </div>
            <div id="pay-single">
                <div class="adm-grid cols-2">
                    <div class="adm-field" style="margin:0;">
                        <label class="adm-label" for="pay_method_single">Method</label>
                        <select class="adm-select" id="pay_method_single" name="payments[0][method]">
                            <option value="cash" @selected(old('payments.0.method', 'cash') === 'cash')>Cash</option>
                            <option value="transfer" @selected(old('payments.0.method') === 'transfer')>Transfer</option>
                            <option value="pos" @selected(old('payments.0.method') === 'pos')>POS</option>
                        </select>
                    </div>
                    <div class="adm-field" style="margin:0;">
                        <label class="adm-label" for="pay_account_single">Pay from account</label>
                        <select class="adm-select" id="pay_account_single" name="payments[0][account_uuid]">
                            @foreach ($accounts as $acc)
                                @if ($acc['is_active'] ?? true)
                                    <option value="{{ $acc['uuid'] }}" @selected(old('payments.0.account_uuid') === $acc['uuid'])>
                                        {{ $acc['name'] }} — {{ number_format((float) ($acc['balance'] ?? 0), 2) }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                </div>
                <input type="hidden" name="payments[0][amount]" id="pay_amount_single" value="0">
            </div>
            <div id="pay-split-fields" style="display:none;">
                <div class="adm-grid cols-2">
                    <div class="adm-field" style="margin:0;">
                        <label class="adm-label" for="pay_cash_account">Cash account</label>
                        <select class="adm-select" id="pay_cash_account" name="payments[0][account_uuid]">
                            @foreach ($accounts as $acc)
                                @if (($acc['is_active'] ?? true) && ($acc['kind'] ?? '') === 'cash')
                                    <option value="{{ $acc['uuid'] }}">{{ $acc['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                        <input type="hidden" name="payments[0][method]" value="cash">
                    </div>
                    <div class="adm-field" style="margin:0;">
                        <label class="adm-label" for="pay_cash_amount">Cash amount</label>
                        <input class="adm-input" type="number" step="0.01" min="0.01" id="pay_cash_amount" value="{{ old('pay_cash_amount') }}">
                    </div>
                    <div class="adm-field" style="margin:0;">
                        <label class="adm-label" for="pay_transfer_account">Transfer account</label>
                        <select class="adm-select" id="pay_transfer_account" name="payments[1][account_uuid]">
                            @foreach ($accounts as $acc)
                                @if (($acc['is_active'] ?? true) && ($acc['kind'] ?? '') !== 'cash')
                                    <option value="{{ $acc['uuid'] }}">{{ $acc['name'] }}</option>
                                @endif
                            @endforeach
                        </select>
                        <input type="hidden" name="payments[1][method]" value="transfer">
                    </div>
                    <div class="adm-field" style="margin:0;">
                        <label class="adm-label">Transfer amount</label>
                        <input class="adm-input" type="text" id="pay_transfer_amount_display" readonly placeholder="Auto (total − cash)">
                        <input type="hidden" name="payments[1][amount]" id="pay_amount_transfer" value="0">
                    </div>
                </div>
                <input type="hidden" name="payments[0][amount]" id="pay_amount_cash" value="0">
            </div>
        </div>

        <h2 style="font-family:Outfit,sans-serif;font-size:1.05rem;margin:1.25rem 0 0.75rem;">Lines</h2>
        <p class="adm-page-desc" style="margin-top:-0.25rem;">At least one line with product, quantity, and unit cost.</p>

        <div id="lines-wrap"></div>
        <button type="button" class="adm-btn adm-btn-ghost" id="add-line" style="margin-top:0.75rem;">+ Add line</button>

        <div class="adm-card" style="margin-top:1rem;padding:1rem 1.15rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
            <div>
                <span class="adm-page-desc" style="margin:0;display:block;">Total worth of goods</span>
                <strong id="purchase-total" style="font-size:1.35rem;font-family:Outfit,sans-serif;">{{ $currencySymbol }}0.00</strong>
            </div>
            <span class="adm-page-desc" style="margin:0;font-size:0.8rem;">Qty × unit cost for all lines</span>
        </div>

        <div class="adm-actions" style="margin-top:1.5rem;">
            <button
                type="submit"
                class="adm-btn adm-btn-primary"
                id="btn-receive"
                data-intent="receive"
                formaction="{{ $formAction }}"
            >Receive stock</button>
            <button
                type="submit"
                class="adm-btn adm-btn-ghost"
                id="btn-draft"
                data-intent="draft"
                formaction="{{ $draftAction }}"
            >Save as draft</button>
        </div>
    </form>
</div>

<template id="line-template">
    <div class="purchase-line adm-card" style="padding:1rem;margin-bottom:0.75rem;background:var(--adm-accent-soft);border-color:#c7d2fe;">
        <div class="adm-grid cols-2" style="gap:0.75rem;">
            <div class="adm-field" style="grid-column:1/-1;margin:0;">
                <label class="adm-label">Product</label>
                <div class="product-picker" style="position:relative;">
                    <input type="hidden" class="product-select" data-name-product value="">
                    <input
                        type="search"
                        class="adm-input product-search"
                        placeholder="Search name, SKU or barcode…"
                        autocomplete="off"
                        spellcheck="false"
                    >
                    <div class="product-picker-list" hidden style="position:absolute;z-index:30;left:0;right:0;top:calc(100% + 4px);max-height:220px;overflow:auto;background:var(--adm-card,#fff);border:1px solid var(--adm-border,#d1d5db);border-radius:10px;box-shadow:0 8px 24px rgba(15,23,42,0.12);"></div>
                </div>
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

<style>
.product-picker-item {
    display: block;
    width: 100%;
    text-align: left;
    border: 0;
    background: transparent;
    padding: 0.65rem 0.85rem;
    cursor: pointer;
    font: inherit;
    color: inherit;
}
.product-picker-item:hover,
.product-picker-item.is-active {
    background: var(--adm-accent-soft, #eef2ff);
}
.product-picker-item small {
    display: block;
    color: var(--adm-muted, #64748b);
    font-size: 0.75rem;
    margin-top: 0.15rem;
}
.product-picker-empty {
    padding: 0.75rem 0.85rem;
    color: var(--adm-muted, #64748b);
    font-size: 0.85rem;
}
</style>

<script>
(function () {
    var wrap = document.getElementById('lines-wrap');
    var tpl = document.getElementById('line-template');
    var addBtn = document.getElementById('add-line');
    var totalEl = document.getElementById('purchase-total');
    var currencySymbol = @json($currencySymbol);
    var oldLines = @json($initialLines);
    var products = @json($products->map(fn ($p) => [
        'uuid' => $p->uuid,
        'name' => $p->name,
        'sku' => $p->sku,
        'barcode' => $p->barcode,
        'cost' => (float) $p->cost_price,
    ])->values());

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

    function purchaseTotal() {
        var sum = 0;
        wrap.querySelectorAll('.purchase-line').forEach(function (block) {
            var q = parseFloat(block.querySelector('.qty-input').value) || 0;
            var c = parseFloat(block.querySelector('.unit-cost-input').value) || 0;
            sum += q * c;
        });
        return Math.round(sum * 100) / 100;
    }

    function formatMoney(amount) {
        return currencySymbol + amount.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function updateTotal() {
        if (totalEl) {
            totalEl.textContent = formatMoney(purchaseTotal());
        }
    }

    function findProduct(uuid) {
        for (var i = 0; i < products.length; i++) {
            if (products[i].uuid === uuid) return products[i];
        }
        return null;
    }

    function productLabel(p) {
        var bits = [];
        if (p.sku) bits.push('SKU ' + p.sku);
        if (p.barcode) bits.push(p.barcode);
        return bits.length ? bits.join(' · ') : '';
    }

    function filterProducts(query) {
        var q = String(query || '').trim().toLowerCase();
        if (!q) return products.slice(0, 50);
        var out = [];
        for (var i = 0; i < products.length; i++) {
            var p = products[i];
            var hay = ((p.name || '') + ' ' + (p.sku || '') + ' ' + (p.barcode || '')).toLowerCase();
            if (hay.indexOf(q) !== -1) {
                out.push(p);
                if (out.length >= 50) break;
            }
        }
        return out;
    }

    function closePicker(picker) {
        var list = picker.querySelector('.product-picker-list');
        list.hidden = true;
        list.innerHTML = '';
    }

    function selectProduct(root, product) {
        var hidden = root.querySelector('.product-select');
        var search = root.querySelector('.product-search');
        var costIn = root.querySelector('.unit-cost-input');
        hidden.value = product.uuid;
        search.value = product.name;
        if (costIn.value === '' || costIn.value === '0') {
            costIn.value = Number(product.cost || 0).toFixed(2);
        }
        closePicker(root.querySelector('.product-picker'));
        updateTotal();
    }

    function renderPicker(root, query, activeIndex) {
        var picker = root.querySelector('.product-picker');
        var list = picker.querySelector('.product-picker-list');
        var matches = filterProducts(query);
        list.innerHTML = '';
        if (matches.length === 0) {
            var empty = document.createElement('div');
            empty.className = 'product-picker-empty';
            empty.textContent = 'No products match “' + query + '”.';
            list.appendChild(empty);
            list.hidden = false;
            return;
        }
        matches.forEach(function (p, idx) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'product-picker-item' + (idx === activeIndex ? ' is-active' : '');
            btn.setAttribute('data-uuid', p.uuid);
            btn.innerHTML = '<strong></strong>';
            btn.querySelector('strong').textContent = p.name;
            var meta = productLabel(p);
            if (meta) {
                var small = document.createElement('small');
                small.textContent = meta;
                btn.appendChild(small);
            }
            btn.addEventListener('mousedown', function (e) {
                e.preventDefault();
                selectProduct(root, p);
            });
            list.appendChild(btn);
        });
        list.hidden = false;
    }

    function bindProductPicker(root) {
        var picker = root.querySelector('.product-picker');
        var hidden = root.querySelector('.product-select');
        var search = root.querySelector('.product-search');
        var activeIndex = 0;

        search.addEventListener('focus', function () {
            activeIndex = 0;
            renderPicker(root, search.value, activeIndex);
        });
        search.addEventListener('input', function () {
            hidden.value = '';
            activeIndex = 0;
            renderPicker(root, search.value, activeIndex);
        });
        search.addEventListener('keydown', function (e) {
            var list = picker.querySelector('.product-picker-list');
            if (list.hidden) return;
            var items = list.querySelectorAll('.product-picker-item');
            if (!items.length) return;
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                activeIndex = Math.min(activeIndex + 1, items.length - 1);
                renderPicker(root, search.value, activeIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                activeIndex = Math.max(activeIndex - 1, 0);
                renderPicker(root, search.value, activeIndex);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                var uuid = items[activeIndex].getAttribute('data-uuid');
                var p = findProduct(uuid);
                if (p) selectProduct(root, p);
            } else if (e.key === 'Escape') {
                closePicker(picker);
            }
        });
        search.addEventListener('blur', function () {
            setTimeout(function () {
                closePicker(picker);
                if (!hidden.value) {
                    search.value = '';
                } else {
                    var p = findProduct(hidden.value);
                    if (p) search.value = p.name;
                }
            }, 120);
        });
    }

    function setProductValue(root, uuid) {
        var p = findProduct(uuid);
        if (!p) return;
        selectProduct(root, p);
    }

    function bindLine(root) {
        root.querySelector('.remove-line').addEventListener('click', function () {
            root.remove();
            renumberLines();
            updateTotal();
        });
        bindProductPicker(root);
        root.querySelector('.qty-input').addEventListener('input', updateTotal);
        root.querySelector('.unit-cost-input').addEventListener('input', updateTotal);
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
            if (prefill.product_uuid) setProductValue(last, prefill.product_uuid);
            if (prefill.qty != null && prefill.qty !== '') last.querySelector('.qty-input').value = prefill.qty;
            if (prefill.unit_cost != null && prefill.unit_cost !== '') last.querySelector('.unit-cost-input').value = prefill.unit_cost;
            if (prefill.expiry_date) last.querySelector('.expiry-input').value = prefill.expiry_date;
        }
        updateTotal();
    }

    addBtn.addEventListener('click', function () { addLine(null); });

    if (Array.isArray(oldLines) && oldLines.length > 0) {
        oldLines.forEach(function (row) { addLine(row); });
    } else {
        addLine(null);
    }

    var form = document.getElementById('purchase-form');
    var paySplit = document.getElementById('pay-split');
    var paySingle = document.getElementById('pay-single');
    var paySplitFields = document.getElementById('pay-split-fields');
    var payCashAmount = document.getElementById('pay_cash_amount');
    var intentField = document.getElementById('intent-field');
    var submitIntent = 'receive';
    var submitting = false;

    function setIntent(intent) {
        submitIntent = intent;
        intentField.value = intent;
    }

    document.getElementById('btn-draft').addEventListener('click', function () {
        setIntent('draft');
    });
    document.getElementById('btn-receive').addEventListener('click', function () {
        setIntent('receive');
    });

    function togglePayMode() {
        var split = paySplit.checked;
        paySingle.style.display = split ? 'none' : 'block';
        paySplitFields.style.display = split ? 'block' : 'none';
        if (split) {
            paySingle.querySelectorAll('[name]').forEach(function (el) { el.disabled = true; });
            paySplitFields.querySelectorAll('[name]').forEach(function (el) { el.disabled = false; });
        } else {
            paySingle.querySelectorAll('[name]').forEach(function (el) { el.disabled = false; });
            paySplitFields.querySelectorAll('[name]').forEach(function (el) { el.disabled = true; });
        }
    }

    paySplit.addEventListener('change', togglePayMode);
    togglePayMode();
    payCashAmount.addEventListener('input', function () {
        var total = purchaseTotal();
        var cash = parseFloat(payCashAmount.value) || 0;
        var transfer = Math.round((total - cash) * 100) / 100;
        var display = document.getElementById('pay_transfer_amount_display');
        if (display) {
            display.value = transfer > 0 ? formatMoney(transfer) : '';
        }
    });

    form.addEventListener('submit', function (e) {
        if (submitting) {
            e.preventDefault();
            return;
        }

        var submitter = e.submitter;
        if (submitter && submitter.getAttribute('data-intent')) {
            setIntent(submitter.getAttribute('data-intent'));
        }

        var missingProduct = false;
        wrap.querySelectorAll('.purchase-line').forEach(function (block) {
            if (!block.querySelector('.product-select').value) {
                missingProduct = true;
            }
        });
        if (missingProduct) {
            e.preventDefault();
            alert('Select a product on every line (search and pick from the list).');
            return;
        }

        var total = purchaseTotal();
        if (total < 0.01) {
            e.preventDefault();
            alert('Add at least one line with quantity and unit cost.');
            return;
        }
        if (submitIntent === 'draft') {
            submitting = true;
            document.getElementById('btn-receive').disabled = true;
            document.getElementById('btn-draft').disabled = true;
            return;
        }
        if (paySplit.checked) {
            var cash = parseFloat(payCashAmount.value) || 0;
            var transfer = Math.round((total - cash) * 100) / 100;
            if (cash < 0.01 || transfer < 0.01) {
                e.preventDefault();
                alert('Split amounts must leave at least 0.01 for cash and transfer.');
                return;
            }
            document.getElementById('pay_amount_cash').value = cash.toFixed(2);
            document.getElementById('pay_amount_transfer').value = transfer.toFixed(2);
        } else {
            document.getElementById('pay_amount_single').value = total.toFixed(2);
        }
        submitting = true;
        document.getElementById('btn-receive').disabled = true;
        document.getElementById('btn-draft').disabled = true;
    });
})();
</script>
@endsection
