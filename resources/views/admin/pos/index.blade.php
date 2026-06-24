@extends('layouts.admin-workspace')

@section('title', 'Point of sale — '.$business->name)

@section('content')
<style>
    .pos-shell { display: grid; grid-template-columns: 1fr min(360px, 38vw); gap: 1rem; align-items: start; }
    @media (max-width: 960px) { .pos-shell { grid-template-columns: 1fr; } }
    .pos-toolbar { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 0.75rem; align-items: center; }
    .pos-toolbar .adm-input, .pos-toolbar .adm-select { min-width: 140px; margin: 0; }
    .pos-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(148px, 1fr)); gap: 0.6rem; max-height: calc(100vh - 220px); overflow: auto; padding-right: 0.25rem; }
    .pos-product { border: 1px solid var(--adm-border); border-radius: 12px; padding: 0.65rem; background: var(--adm-card); cursor: pointer; transition: border-color 0.15s, box-shadow 0.15s; text-align: left; }
    .pos-product:hover:not(:disabled) { border-color: var(--adm-accent); box-shadow: 0 4px 14px rgba(37,99,235,0.12); }
    .pos-product:disabled { opacity: 0.55; cursor: not-allowed; }
    .pos-product .name { font-weight: 600; font-size: 0.88rem; line-height: 1.25; margin-bottom: 0.25rem; }
    .pos-product .meta { font-size: 0.75rem; color: var(--adm-muted); }
    .pos-product .price { font-family: Outfit, sans-serif; font-weight: 700; margin-top: 0.35rem; }
    .pos-cart { position: sticky; top: 0.5rem; border: 1px solid var(--adm-border); border-radius: 14px; background: var(--adm-card); padding: 1rem; display: flex; flex-direction: column; max-height: calc(100vh - 120px); }
    .pos-cart-lines { flex: 1; overflow: auto; margin: 0.75rem 0; min-height: 120px; }
    .pos-cart-line { display: grid; grid-template-columns: 1fr auto; gap: 0.35rem 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid var(--adm-border); font-size: 0.88rem; }
    .pos-cart-line:last-child { border-bottom: none; }
    .pos-qty { display: inline-flex; align-items: center; gap: 0.25rem; }
    .pos-qty button { width: 26px; height: 26px; border-radius: 6px; border: 1px solid var(--adm-border); background: var(--adm-bg); cursor: pointer; }
    .pos-totals { border-top: 1px solid var(--adm-border); padding-top: 0.75rem; font-size: 0.9rem; }
    .pos-totals .grand { font-size: 1.2rem; font-family: Outfit, sans-serif; font-weight: 800; }
    .pos-modal-backdrop { position: fixed; inset: 0; background: rgba(15,23,42,0.45); z-index: 200; display: none; align-items: center; justify-content: center; padding: 1rem; }
    .pos-modal-backdrop.open { display: flex; }
    .pos-modal { background: var(--adm-card); border-radius: 14px; width: min(480px, 100%); max-height: 90vh; overflow: auto; padding: 1.25rem; border: 1px solid var(--adm-border); }
    .pos-status { font-size: 0.85rem; margin-top: 0.5rem; min-height: 1.2em; }
    .pos-status.err { color: #b91c1c; }
    .pos-status.ok { color: #15803d; }
</style>

<h1 class="adm-page-title">Point of sale</h1>
<p class="adm-page-desc">Sell from the browser. Checkout posts to sales &amp; transactions. Print receipts on 58mm thermal printers (Horizon Pay S60, Sunmi, etc.) via the built-in print dialog.</p>

<div class="pos-shell">
    <section>
        <div class="pos-toolbar">
            <input type="search" id="pos-search" class="adm-input" placeholder="Search name, SKU, barcode…" style="flex:1;min-width:180px;">
            <select id="pos-category" class="adm-select">
                <option value="">All categories</option>
                <option value="__uncat__">Uncategorized</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->uuid }}">{{ $cat->name }}</option>
                @endforeach
            </select>
            <select id="pos-location" class="adm-select" title="Branch">
                @foreach($locations as $loc)
                    <option value="{{ $loc->uuid }}" @selected($loc->uuid === $location->uuid)>{{ $loc->name }}</option>
                @endforeach
            </select>
            <button type="button" class="adm-btn adm-btn-ghost" id="pos-quick-add">+ Quick sell</button>
        </div>
        <div id="pos-grid" class="pos-grid" aria-live="polite"></div>
        <p id="pos-empty" class="adm-page-desc" style="display:none;margin-top:1rem;">No products match your search.</p>
    </section>

    <aside class="pos-cart">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:0.5rem;">
            <strong style="font-family:Outfit,sans-serif;font-size:1.05rem;">Cart</strong>
            <button type="button" class="adm-btn adm-btn-ghost" id="pos-clear" style="padding:0.25rem 0.5rem;font-size:0.8rem;">Clear</button>
        </div>

        <div class="adm-field" style="margin:0.75rem 0 0;">
            <label class="adm-label" for="pos-customer">Customer</label>
            <select id="pos-customer" class="adm-select">
                @foreach($customers as $c)
                    <option value="{{ $c->uuid }}" @selected($c->is_walk_in) data-walk-in="{{ $c->is_walk_in ? '1' : '0' }}" data-credit="{{ $c->credit_enabled ? '1' : '0' }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <div id="pos-cart-lines" class="pos-cart-lines">
            <p class="adm-page-desc" style="margin:0;">Tap products to add them here.</p>
        </div>

        <div class="pos-totals">
            <div style="display:flex;justify-content:space-between;"><span>Subtotal (ex VAT)</span><span id="pos-sub-ex">{{ $currencySymbol }}0.00</span></div>
            <div style="display:flex;justify-content:space-between;margin-top:0.25rem;"><span>VAT</span><span id="pos-tax">{{ $currencySymbol }}0.00</span></div>
            <div style="display:flex;justify-content:space-between;margin-top:0.25rem;"><span>Discount</span><span id="pos-discount-preview">{{ $currencySymbol }}0.00</span></div>
            <div style="display:flex;justify-content:space-between;margin-top:0.5rem;" class="grand">
                <span>Total</span><span id="pos-grand">{{ $currencySymbol }}0.00</span>
            </div>
        </div>

        <button type="button" class="adm-btn adm-btn-primary" id="pos-checkout" style="width:100%;margin-top:0.85rem;" disabled>Checkout</button>
        <p id="pos-status" class="pos-status"></p>
    </aside>
</div>

{{-- Checkout modal --}}
<div class="pos-modal-backdrop" id="checkout-modal" role="dialog" aria-modal="true">
    <div class="pos-modal">
        <h2 style="font-family:Outfit,sans-serif;margin:0 0 0.75rem;">Complete sale</h2>
        <div class="adm-field">
            <label class="adm-label" for="pay-method">Payment method</label>
            <select id="pay-method" class="adm-select">
                <option value="cash">Cash</option>
                <option value="transfer">Bank transfer</option>
                <option value="pos">POS / card</option>
                <option value="credit">Customer credit</option>
            </select>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="pay-account">Deposit to account</label>
            <select id="pay-account" class="adm-select">
                @foreach($accounts as $acc)
                    <option value="{{ $acc['uuid'] }}" data-kind="{{ $acc['kind'] ?? '' }}">{{ $acc['name'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="pay-discount">Discount ({{ $currencySymbol }})</label>
                <input type="number" step="0.01" min="0" id="pay-discount" class="adm-input" value="0">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="pay-date">Sale date</label>
                <input type="date" id="pay-date" class="adm-input" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}">
            </div>
        </div>
        <div class="adm-field">
            <label class="adm-label">Receipt</label>
            <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                <label><input type="radio" name="receipt_mode" value="none" checked> No receipt</label>
                <label><input type="radio" name="receipt_mode" value="print"> Print thermal (58mm)</label>
            </div>
        </div>
        <p class="adm-page-desc" style="margin:0.5rem 0 0;">Charge: <strong id="pay-charge-preview">{{ $currencySymbol }}0.00</strong></p>
        <p id="checkout-error" class="pos-status err"></p>
        <div class="adm-actions" style="margin-top:1rem;">
            <button type="button" class="adm-btn adm-btn-ghost" id="checkout-cancel">Cancel</button>
            <button type="button" class="adm-btn adm-btn-primary" id="checkout-confirm">Confirm sale</button>
        </div>
    </div>
</div>

{{-- Quick sell modal --}}
<div class="pos-modal-backdrop" id="quick-modal">
    <div class="pos-modal">
        <h2 style="font-family:Outfit,sans-serif;margin:0 0 0.75rem;">Quick sell (no stock)</h2>
        <div class="adm-field">
            <label class="adm-label" for="quick-name">Item name</label>
            <input id="quick-name" class="adm-input" placeholder="e.g. Haircut, delivery fee">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="quick-price">Price ({{ $currencySymbol }})</label>
            <input type="number" step="0.01" min="0" id="quick-price" class="adm-input" value="0">
        </div>
        <p id="quick-error" class="pos-status err"></p>
        <div class="adm-actions">
            <button type="button" class="adm-btn adm-btn-ghost" id="quick-cancel">Cancel</button>
            <button type="button" class="adm-btn adm-btn-primary" id="quick-save">Create &amp; add</button>
        </div>
    </div>
</div>

<script>
(function () {
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
    const SYM = @json($currencySymbol);
    const DEFAULT_VAT = {{ $defaultVat }};
    const PRODUCTS = @json(json_decode($productsJson));
    const CHECKOUT_URL = @json(route('admin.b.pos.checkout', $business));
    const QUICK_URL = @json(route('admin.b.pos.quick-product', $business));

    let products = [...PRODUCTS];
    const cart = new Map();

    const el = (id) => document.getElementById(id);
    const money = (n) => SYM + Number(n).toFixed(2);
    const round2 = (n) => Math.round(n * 100) / 100;

    function tracksStock(p) { return p.track_stock !== false; }
    function isSellable(p) { return !tracksStock(p) || (p.stock_qty > 0); }

    function lineSubEx(line) { return round2(line.qty * line.unit_price); }
    function lineTax(line) { return round2(lineSubEx(line) * (line.tax_rate / 100)); }
    function lineGross(line) { return round2(lineSubEx(line) + lineTax(line)); }

    function cartLines() {
        return [...cart.values()];
    }

    function totals(discount) {
        let sub = 0, tax = 0;
        for (const l of cartLines()) {
            sub += lineSubEx(l);
            tax += lineTax(l);
        }
        sub = round2(sub);
        tax = round2(tax);
        const grand = round2(sub + tax - (discount || 0));
        return { sub, tax, grand };
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
                : 'No stock tracking';
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
            setStatus('Only ' + p.stock_qty + ' in stock for ' + p.name, true);
            return;
        }
        cart.set(p.uuid, {
            product_uuid: p.uuid,
            name: p.name,
            qty: nextQty,
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
            wrap.innerHTML = '<p class="adm-page-desc" style="margin:0;">Tap products to add them here.</p>';
            el('pos-checkout').disabled = true;
        } else {
            el('pos-checkout').disabled = false;
            for (const line of cartLines()) {
                const row = document.createElement('div');
                row.className = 'pos-cart-line';
                row.innerHTML = '<div><strong></strong><div class="pos-qty" style="margin-top:0.25rem;"><button type="button" data-act="minus">−</button><span></span><button type="button" data-act="plus">+</button></div></div><div style="text-align:right;"></div>';
                row.querySelector('strong').textContent = line.name;
                row.querySelector('.pos-qty span').textContent = 'Qty ' + line.qty;
                row.querySelector('div:last-child').innerHTML = money(lineGross(line)) + '<br><button type="button" data-act="remove" style="font-size:0.75rem;border:none;background:none;color:var(--adm-muted);cursor:pointer;">Remove</button>';
                row.querySelector('[data-act="minus"]').addEventListener('click', () => {
                    line.qty = round2(line.qty - 1);
                    if (line.qty <= 0) cart.delete(line.product_uuid);
                    renderCart();
                });
                row.querySelector('[data-act="plus"]').addEventListener('click', () => {
                    const p = products.find((x) => x.uuid === line.product_uuid);
                    if (p) addToCart(p, 1);
                });
                row.querySelector('[data-act="remove"]').addEventListener('click', () => {
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

    function openModal(id) { el(id).classList.add('open'); }
    function closeModal(id) { el(id).classList.remove('open'); }

    function defaultAccountForMethod(method) {
        const sel = el('pay-account');
        const opts = [...sel.options];
        if (method === 'cash') {
            return opts.find((o) => o.dataset.kind === 'cash') || opts[0];
        }
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
            el('checkout-error').textContent = 'Discount cannot exceed the total.';
            btn.disabled = false;
            return;
        }
        const method = el('pay-method').value;
        const custOpt = el('pos-customer').selectedOptions[0];
        if (method === 'credit' && custOpt?.dataset.walkIn === '1') {
            el('checkout-error').textContent = 'Pick a saved customer (not Walk-in) for credit sales.';
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
                account_uuid: el('pay-account').value,
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
            cart.clear();
            renderCart();
            closeModal('checkout-modal');
            setStatus('Sale #' + body.data.receipt_no + ' recorded.', false);
            const printReceipt = document.querySelector('input[name="receipt_mode"]:checked')?.value === 'print';
            if (printReceipt && body.receipt_url) {
                window.open(body.receipt_url + '?print=1', '_blank', 'noopener');
            }
        } catch (e) {
            el('checkout-error').textContent = e.message || 'Checkout failed';
        } finally {
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
        el('quick-error').textContent = '';
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
            setStatus(name + ' added to cart.', false);
        } catch (e) {
            el('quick-error').textContent = e.message;
        }
    });

    renderGrid();
    renderCart();
})();
</script>
@endsection
