@extends('layouts.admin-pos')

@section('title', 'Point of sale — '.$business->name)

@section('content')
<style>
    .pos-page { display: flex; flex-direction: column; height: 100%; min-height: 0; overflow: hidden; }
    .pos-header { flex-shrink: 0; margin-bottom: 0.5rem; }
    .pos-header .adm-page-title { font-size: 1.15rem; margin: 0 0 0.15rem; }
    .pos-header .adm-page-desc { font-size: 0.78rem; margin: 0; line-height: 1.3; }

    .pos-shell {
        flex: 1;
        min-height: 0;
        display: grid;
        grid-template-columns: 1fr min(340px, 36vw);
        gap: 0.65rem;
        overflow: hidden;
    }

    .pos-products-pane {
        min-height: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .pos-toolbar {
        display: flex;
        flex-wrap: wrap;
        gap: 0.4rem;
        margin-bottom: 0.5rem;
        align-items: center;
        flex-shrink: 0;
    }
    .pos-toolbar .adm-input,
    .pos-toolbar .adm-select {
        min-width: 0;
        margin: 0;
        font-size: 0.88rem;
        padding: 0.45rem 0.55rem;
    }
    .pos-toolbar .pos-search { flex: 1 1 140px; }
    .pos-toolbar .pos-filter { flex: 1 1 100px; max-width: 140px; }
    .pos-toolbar .pos-quick-btn { flex-shrink: 0; padding: 0.45rem 0.6rem; font-size: 0.82rem; }

    .pos-grid {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(108px, 1fr));
        gap: 0.45rem;
        padding-bottom: 0.35rem;
        align-content: start;
    }

    .pos-product {
        border: 1px solid var(--adm-border);
        border-radius: 10px;
        padding: 0.55rem;
        background: var(--adm-card);
        cursor: pointer;
        transition: border-color 0.15s;
        text-align: left;
        width: 100%;
        min-width: 0;
    }
    .pos-product:active:not(:disabled) { border-color: var(--adm-accent); background: var(--adm-accent-soft); }
    .pos-product:disabled { opacity: 0.5; cursor: not-allowed; }
    .pos-product .name {
        font-weight: 600;
        font-size: 0.82rem;
        line-height: 1.2;
        margin-bottom: 0.2rem;
        word-break: break-word;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    .pos-product .meta { font-size: 0.68rem; color: var(--adm-muted); }
    .pos-product .price { font-family: Outfit, sans-serif; font-weight: 700; font-size: 0.88rem; margin-top: 0.25rem; }

    .pos-cart {
        min-height: 0;
        border: 1px solid var(--adm-border);
        border-radius: 12px;
        background: var(--adm-card);
        padding: 0.65rem;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .pos-cart-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
        flex-shrink: 0;
    }
    .pos-cart-head strong { font-family: Outfit, sans-serif; font-size: 0.95rem; }

    .pos-cart .adm-field { margin: 0.45rem 0 0; flex-shrink: 0; }
    .pos-cart .adm-label { font-size: 0.75rem; margin-bottom: 0.15rem; }
    .pos-cart .adm-select { font-size: 0.85rem; padding: 0.4rem 0.5rem; }

    .pos-cart-lines {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
        margin: 0.45rem 0;
    }

    .pos-cart-line {
        display: grid;
        grid-template-columns: 1fr auto;
        gap: 0.25rem 0.4rem;
        padding: 0.4rem 0;
        border-bottom: 1px solid var(--adm-border);
        font-size: 0.82rem;
    }
    .pos-cart-line:last-child { border-bottom: none; }
    .pos-line-name { word-break: break-word; font-size: 0.85rem; }
    .pos-line-total { font-size: 0.82rem; white-space: nowrap; }

    .pos-qty { display: inline-flex; align-items: center; gap: 0.2rem; }
    .pos-qty button {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 1px solid var(--adm-border);
        background: var(--adm-bg);
        cursor: pointer;
        font-size: 1rem;
        line-height: 1;
        touch-action: manipulation;
    }

    .pos-totals {
        border-top: 1px solid var(--adm-border);
        padding-top: 0.5rem;
        font-size: 0.82rem;
        flex-shrink: 0;
    }
    .pos-totals .grand { font-size: 1.05rem; font-family: Outfit, sans-serif; font-weight: 800; }

    .pos-checkout-btn {
        width: 100%;
        margin-top: 0.55rem;
        flex-shrink: 0;
        padding: 0.65rem;
        font-size: 0.95rem;
        touch-action: manipulation;
    }

    .pos-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15,23,42,0.5);
        z-index: 300;
        display: none;
        align-items: flex-end;
        justify-content: center;
        padding: 0;
    }
    .pos-modal-backdrop.open { display: flex; }
    .pos-modal {
        background: var(--adm-card);
        border-radius: 16px 16px 0 0;
        width: 100%;
        max-width: 520px;
        max-height: 92dvh;
        overflow-y: auto;
        padding: 1rem 1rem calc(1rem + env(safe-area-inset-bottom));
        border: 1px solid var(--adm-border);
        -webkit-overflow-scrolling: touch;
    }

    .pos-status { font-size: 0.82rem; margin-top: 0.35rem; min-height: 1em; flex-shrink: 0; }
    .pos-status.err { color: #b91c1c; }
    .pos-status.ok { color: #15803d; }

    /* S60 / narrow terminal: stack with fixed bottom cart */
    @media (max-width: 900px), (max-height: 820px) {
        .pos-header .adm-page-desc { display: none; }
        .pos-shell {
            grid-template-columns: 1fr;
            grid-template-rows: 1fr auto;
            gap: 0.4rem;
        }
        .pos-products-pane { min-height: 0; }
        .pos-grid { grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); }
        .pos-cart {
            max-height: min(46dvh, 360px);
            border-radius: 12px 12px 0 0;
            box-shadow: 0 -6px 24px rgba(15,23,42,0.1);
        }
        .pos-toolbar .pos-filter { max-width: none; flex: 1 1 45%; }
    }

    @media (min-width: 901px) and (min-height: 821px) {
        .pos-modal-backdrop { align-items: center; padding: 1rem; }
        .pos-modal { border-radius: 14px; max-height: 90vh; }
    }
</style>

<div class="pos-page">
    <div class="pos-header">
        <h1 class="adm-page-title">Point of sale</h1>
        <p class="adm-page-desc">Tap products to sell. Receipts print on the S60 built-in printer at checkout.</p>
    </div>

    <div class="pos-shell">
        <section class="pos-products-pane">
            <div class="pos-toolbar">
                <input type="search" id="pos-search" class="adm-input pos-search" placeholder="Search…" autocomplete="off">
                <select id="pos-category" class="adm-select pos-filter">
                    <option value="">All categories</option>
                    <option value="__uncat__">Uncategorized</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat->uuid }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
                <select id="pos-location" class="adm-select pos-filter" title="Branch">
                    @foreach($locations as $loc)
                        <option value="{{ $loc->uuid }}" @selected($loc->uuid === $location->uuid)>{{ $loc->name }}</option>
                    @endforeach
                </select>
                <button type="button" class="adm-btn adm-btn-ghost pos-quick-btn" id="pos-quick-add">+ Quick</button>
            </div>
            <div id="pos-grid" class="pos-grid" aria-live="polite"></div>
            <p id="pos-empty" class="adm-page-desc" style="display:none;margin:0.5rem 0 0;">No products match.</p>
        </section>

        <aside class="pos-cart">
            <div class="pos-cart-head">
                <strong>Cart</strong>
                <button type="button" class="adm-btn adm-btn-ghost" id="pos-clear" style="padding:0.2rem 0.45rem;font-size:0.75rem;">Clear</button>
            </div>

            <div class="adm-field">
                <label class="adm-label" for="pos-customer">Customer</label>
                <select id="pos-customer" class="adm-select">
                    @foreach($customers as $c)
                        <option value="{{ $c->uuid }}" @selected($c->is_walk_in) data-walk-in="{{ $c->is_walk_in ? '1' : '0' }}" data-credit="{{ $c->credit_enabled ? '1' : '0' }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div id="pos-cart-lines" class="pos-cart-lines">
                <p class="adm-page-desc" style="margin:0;font-size:0.82rem;">Tap a product to add it.</p>
            </div>

            <div class="pos-totals">
                <div style="display:flex;justify-content:space-between;"><span>Subtotal (ex VAT)</span><span id="pos-sub-ex">{{ $currencySymbol }}0.00</span></div>
                <div style="display:flex;justify-content:space-between;margin-top:0.2rem;"><span>VAT</span><span id="pos-tax">{{ $currencySymbol }}0.00</span></div>
                <div style="display:flex;justify-content:space-between;margin-top:0.2rem;"><span>Discount</span><span id="pos-discount-preview">{{ $currencySymbol }}0.00</span></div>
                <div style="display:flex;justify-content:space-between;margin-top:0.35rem;" class="grand">
                    <span>Total</span><span id="pos-grand">{{ $currencySymbol }}0.00</span>
                </div>
            </div>

            <button type="button" class="adm-btn adm-btn-primary pos-checkout-btn" id="pos-checkout" disabled>Checkout</button>
            <p id="pos-status" class="pos-status"></p>
        </aside>
    </div>
</div>

{{-- Checkout modal --}}
<div class="pos-modal-backdrop" id="checkout-modal" role="dialog" aria-modal="true">
    <div class="pos-modal">
        <h2 style="font-family:Outfit,sans-serif;margin:0 0 0.65rem;font-size:1.05rem;">Complete sale</h2>
        <div class="adm-field">
            <label class="adm-label" for="pay-method">Payment</label>
            <select id="pay-method" class="adm-select">
                <option value="cash">Cash</option>
                <option value="transfer">Transfer</option>
                <option value="pos">POS / card</option>
                <option value="credit">Credit</option>
            </select>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="pay-account">Account</label>
            <select id="pay-account" class="adm-select">
                @foreach($accounts as $acc)
                    <option value="{{ $acc['uuid'] }}" data-kind="{{ $acc['kind'] ?? '' }}">{{ $acc['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="pay-discount">Discount</label>
                <input type="number" step="0.01" min="0" id="pay-discount" class="adm-input" value="0" inputmode="decimal">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="pay-date">Date</label>
                <input type="date" id="pay-date" class="adm-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
            </div>
        </div>
        <div class="adm-field">
            <label class="adm-label">Receipt</label>
            <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.88rem;">
                <input type="checkbox" id="pay-print-receipt" checked>
                Print on terminal (58mm)
            </label>
        </div>
        <p class="adm-page-desc" style="margin:0.4rem 0 0;font-size:0.88rem;">Charge: <strong id="pay-charge-preview">{{ $currencySymbol }}0.00</strong></p>
        <p id="checkout-error" class="pos-status err"></p>
        <div class="adm-actions" style="margin-top:0.75rem;">
            <button type="button" class="adm-btn adm-btn-ghost" id="checkout-cancel">Cancel</button>
            <button type="button" class="adm-btn adm-btn-primary" id="checkout-confirm" style="flex:1;">Confirm &amp; print</button>
        </div>
    </div>
</div>

{{-- Quick sell modal --}}
<div class="pos-modal-backdrop" id="quick-modal">
    <div class="pos-modal">
        <h2 style="font-family:Outfit,sans-serif;margin:0 0 0.65rem;font-size:1.05rem;">Quick sell</h2>
        <div class="adm-field">
            <label class="adm-label" for="quick-name">Name</label>
            <input id="quick-name" class="adm-input" placeholder="e.g. Delivery fee">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="quick-price">Price ({{ $currencySymbol }})</label>
            <input type="number" step="0.01" min="0" id="quick-price" class="adm-input" value="0" inputmode="decimal">
        </div>
        <p id="quick-error" class="pos-status err"></p>
        <div class="adm-actions">
            <button type="button" class="adm-btn adm-btn-ghost" id="quick-cancel">Cancel</button>
            <button type="button" class="adm-btn adm-btn-primary" id="quick-save">Add</button>
        </div>
    </div>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const SYM = @json($currencySymbol);
    const DEFAULT_VAT = {{ $defaultVat }};
    const PRODUCTS = @json($products);
    const CHECKOUT_URL = @json(route('admin.b.pos.checkout', $business));
    const QUICK_URL = @json(route('admin.b.pos.quick-product', $business));
    const POS_URL = @json(route('admin.b.pos.index', $business));

    let products = [...PRODUCTS];
    const cart = new Map();

    const el = (id) => document.getElementById(id);
    const money = (n) => SYM + Number(n).toFixed(2);
    const round2 = (n) => Math.round(n * 100) / 100;

    function tracksStock(p) {
        if (p.track_stock === false || p.track_stock === 0 || p.track_stock === '0') return false;
        return true;
    }
    function isSellable(p) { return !tracksStock(p) || (p.stock_qty > 0); }

    function lineSubEx(line) { return round2(line.qty * line.unit_price); }
    function lineTax(line) { return round2(lineSubEx(line) * (line.tax_rate / 100)); }
    function lineGross(line) { return round2(lineSubEx(line) + lineTax(line)); }

    function cartLines() { return [...cart.values()]; }

    function totals(discount) {
        let sub = 0, tax = 0;
        for (const l of cartLines()) {
            sub += lineSubEx(l);
            tax += lineTax(l);
        }
        sub = round2(sub);
        tax = round2(tax);
        return { sub, tax, grand: round2(sub + tax - (discount || 0)) };
    }

    function printReceiptOnTerminal(receiptUrl) {
        const url = receiptUrl + (receiptUrl.includes('?') ? '&' : '?') + 'print=1&return=1';
        window.location.href = url;
    }

    function renderGrid() {
        const q = el('pos-search').value.trim().toLowerCase();
        const cat = el('pos-category').value;
        const grid = el('pos-grid');
        const filtered = products.filter((p) => {
            if (cat === '__uncat__' && p.category_uuid) return false;
            if (cat && cat !== '__uncat__' && p.category_uuid !== cat) return false;
            if (!q) return true;
            return (p.name || '').toLowerCase().includes(q)
                || (p.sku || '').toLowerCase().includes(q)
                || (p.barcode || '').toLowerCase().includes(q);
        });

        grid.innerHTML = '';
        el('pos-empty').style.display = filtered.length ? 'none' : 'block';

        for (const p of filtered) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pos-product';
            btn.disabled = !isSellable(p);
            const stockLabel = tracksStock(p)
                ? (p.stock_qty > 0 ? 'Stock ' + p.stock_qty : 'Out of stock')
                : 'No stock';
            btn.innerHTML = '<div class="name"></div><div class="meta"></div><div class="price"></div>';
            btn.querySelector('.name').textContent = p.name;
            btn.querySelector('.meta').textContent = stockLabel;
            btn.querySelector('.price').textContent = money(p.selling_price);
            btn.addEventListener('click', () => addToCart(p));
            grid.appendChild(btn);
        }
    }

    function addToCart(p, qty) {
        if (!isSellable(p)) return;
        const add = qty || 1;
        const existing = cart.get(p.uuid);
        const nextQty = round2((existing ? existing.qty : 0) + add);
        if (tracksStock(p) && p.stock_qty > 0 && nextQty > p.stock_qty + 0.0001) {
            setStatus('Only ' + p.stock_qty + ' in stock', true);
            return;
        }
        cart.set(p.uuid, {
            product_uuid: p.uuid,
            name: p.name,
            qty: nextQty,
            unit: p.unit || 'pcs',
            unit_price: p.selling_price,
            tax_rate: p.vat_rate ?? DEFAULT_VAT,
        });
        renderCart();
        setStatus('');
    }

    function renderCart() {
        const wrap = el('pos-cart-lines');
        wrap.innerHTML = '';
        if (cart.size === 0) {
            wrap.innerHTML = '<p class="adm-page-desc" style="margin:0;font-size:0.82rem;">Tap a product to add it.</p>';
            el('pos-checkout').disabled = true;
        } else {
            el('pos-checkout').disabled = false;
            for (const line of cartLines()) {
                const row = document.createElement('div');
                row.className = 'pos-cart-line';
                row.innerHTML =
                    '<div class="pos-line-main">' +
                        '<strong class="pos-line-name"></strong>' +
                        '<div class="pos-qty" style="margin-top:0.2rem;">' +
                            '<button type="button" data-act="minus">−</button>' +
                            '<span class="pos-line-qty"></span>' +
                            '<button type="button" data-act="plus">+</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="pos-line-total"></div>';
                row.querySelector('.pos-line-name').textContent = line.name;
                row.querySelector('.pos-line-qty').textContent = '×' + line.qty + ' ' + (line.unit || 'pcs');
                row.querySelector('.pos-line-total').innerHTML =
                    money(lineGross(line)) +
                    '<br><button type="button" data-act="remove" style="font-size:0.72rem;border:none;background:none;color:var(--adm-muted);cursor:pointer;padding:0;">Remove</button>';
                row.querySelector('[data-act="minus"]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    line.qty = round2(line.qty - 1);
                    if (line.qty <= 0) cart.delete(line.product_uuid);
                    renderCart();
                });
                row.querySelector('[data-act="plus"]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    const p = products.find((x) => x.uuid === line.product_uuid);
                    if (p) addToCart(p, 1);
                });
                row.querySelector('[data-act="remove"]').addEventListener('click', (e) => {
                    e.stopPropagation();
                    cart.delete(line.product_uuid);
                    renderCart();
                });
                wrap.appendChild(row);
            }
        }
        const disc = parseFloat(el('pay-discount')?.value || '0') || 0;
        const t = totals(disc);
        el('pos-sub-ex').textContent = money(t.sub);
        el('pos-tax').textContent = money(t.tax);
        el('pos-discount-preview').textContent = money(disc);
        el('pos-grand').textContent = money(t.grand);
        if (el('pay-charge-preview')) el('pay-charge-preview').textContent = money(t.grand);
    }

    function setStatus(msg, isErr) {
        const s = el('pos-status');
        s.textContent = msg || '';
        s.className = 'pos-status' + (isErr ? ' err' : msg ? ' ok' : '');
    }

    function openModal(id) { el(id).classList.add('open'); document.body.style.overflow = 'hidden'; }
    function closeModal(id) { el(id).classList.remove('open'); document.body.style.overflow = ''; }

    function defaultAccountForMethod(method) {
        const opts = [...el('pay-account').options];
        if (method === 'cash') return opts.find((o) => o.dataset.kind === 'cash') || opts[0];
        return opts.find((o) => o.dataset.kind !== 'cash') || opts[0];
    }

    el('pos-search').addEventListener('input', renderGrid);
    el('pos-category').addEventListener('change', renderGrid);
    el('pos-location').addEventListener('change', () => {
        const url = new URL(window.location.href);
        url.searchParams.set('location_uuid', el('pos-location').value);
        window.location.href = url.toString();
    });
    el('pos-clear').addEventListener('click', () => { cart.clear(); renderCart(); });
    el('pos-checkout').addEventListener('click', () => {
        el('checkout-error').textContent = '';
        const opt = defaultAccountForMethod(el('pay-method').value);
        if (opt) el('pay-account').value = opt.value;
        renderCart();
        openModal('checkout-modal');
    });
    el('checkout-cancel').addEventListener('click', () => closeModal('checkout-modal'));
    el('pay-method').addEventListener('change', () => {
        const opt = defaultAccountForMethod(el('pay-method').value);
        if (opt) el('pay-account').value = opt.value;
    });
    el('pay-discount').addEventListener('input', renderCart);

    el('checkout-confirm').addEventListener('click', async () => {
        const btn = el('checkout-confirm');
        btn.disabled = true;
        el('checkout-error').textContent = '';
        const discount = parseFloat(el('pay-discount').value || '0') || 0;
        const t = totals(discount);
        if (t.grand < 0) {
            el('checkout-error').textContent = 'Discount exceeds total.';
            btn.disabled = false;
            return;
        }
        const method = el('pay-method').value;
        const custOpt = el('pos-customer').selectedOptions[0];
        if (method === 'credit' && custOpt?.dataset.walkIn === '1') {
            el('checkout-error').textContent = 'Pick a saved customer for credit.';
            btn.disabled = false;
            return;
        }
        const payload = {
            location_uuid: el('pos-location').value,
            customer_uuid: el('pos-customer').value || null,
            lines: cartLines().map((l) => ({
                product_uuid: l.product_uuid,
                qty: l.qty,
                unit_price: l.unit_price,
                tax_rate: l.tax_rate,
            })),
            payments: [{
                method,
                amount: t.grand,
                ...(el('pay-account').value ? { account_uuid: el('pay-account').value } : {}),
            }],
            discount_total: discount,
            sold_at: el('pay-date').value || null,
            idempotency_key: crypto.randomUUID(),
        };
        try {
            const res = await fetch(CHECKOUT_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': CSRF,
                },
                body: JSON.stringify(payload),
            });
            const body = await res.json();
            if (!res.ok) throw new Error(body.message || 'Checkout failed');

            const printReceipt = el('pay-print-receipt').checked;
            if (printReceipt && body.receipt_url) {
                printReceiptOnTerminal(body.receipt_url);
                return;
            }

            cart.clear();
            renderCart();
            closeModal('checkout-modal');
            setStatus('Sale #' + body.data.receipt_no + ' recorded.', false);
        } catch (e) {
            el('checkout-error').textContent = e.message || 'Checkout failed';
            btn.disabled = false;
        }
    });

    el('pos-quick-add').addEventListener('click', () => {
        el('quick-name').value = '';
        el('quick-price').value = '0';
        el('quick-error').textContent = '';
        openModal('quick-modal');
    });
    el('quick-cancel').addEventListener('click', () => closeModal('quick-modal'));
    el('quick-save').addEventListener('click', async () => {
        const name = el('quick-name').value.trim();
        const price = parseFloat(el('quick-price').value || '0') || 0;
        if (!name) { el('quick-error').textContent = 'Enter a name.'; return; }
        try {
            const res = await fetch(QUICK_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': CSRF },
                body: JSON.stringify({ name, selling_price: price, location_uuid: el('pos-location').value, vat_rate: DEFAULT_VAT }),
            });
            const body = await res.json();
            if (!res.ok) throw new Error(body.message || 'Could not create item');
            const p = body.data;
            p.track_stock = false;
            p.stock_qty = 0;
            products.push(p);
            products.sort((a, b) => a.name.localeCompare(b.name));
            addToCart(p, 1);
            closeModal('quick-modal');
            renderGrid();
            setStatus(name + ' added.', false);
        } catch (e) {
            el('quick-error').textContent = e.message;
        }
    });

    renderGrid();
    renderCart();
})();
</script>
@endsection
