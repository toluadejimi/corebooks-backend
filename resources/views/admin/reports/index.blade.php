@extends('layouts.admin-workspace')

@section('title', 'Reports — '.$business->name)

@section('content')
@php
    $sym = $currencySymbol;
    $fmt = fn ($n, $dec = 2) => $sym.number_format((float) $n, $dec);
    $trendLabels = $series->pluck('date')->values();
    $trendRevenue = $series->pluck('revenue')->values();
    $trendOrders = $series->pluck('orders')->values();
    $payLabels = $payments->pluck('method')->map(fn ($m) => strtoupper((string) $m))->values();
    $payTotals = $payments->pluck('total')->values();
    $expByCat = $expenseReport['by_category'];
    $expLabels = $expByCat->keys()->values();
    $expValues = $expByCat->values()->values();
    $prodTop = $products->take(10);
    $prodLabels = $prodTop->pluck('name')->values();
    $prodRevenues = $prodTop->pluck('revenue')->values();
    $pnlPieLabels = ['Revenue', 'COGS (est.)', 'Expenses'];
    $pnlPieValues = [
        max(0, (float) $pnl['revenue']),
        max(0, (float) $pnl['cogs_estimate']),
        max(0, (float) $pnl['expenses']),
    ];
    $eq = $exportQuery ?? '';
    $exportHref = fn (string $slug, string $formatExt) => route('admin.b.reports.export', ['business' => $business, 'report' => $slug, 'format' => $formatExt])
        . ($eq !== '' ? '?'.$eq : '');
@endphp

<style>
.rep-bg {
    position: relative;
    margin: -0.5rem -2rem 0;
    padding: 0 2rem 2rem;
    min-height: 100%;
    background:
        radial-gradient(ellipse 80% 50% at 50% -20%, rgba(99, 102, 241, 0.25), transparent 55%),
        radial-gradient(ellipse 60% 40% at 100% 0%, rgba(168, 85, 247, 0.18), transparent 50%),
        radial-gradient(ellipse 50% 35% at 0% 20%, rgba(34, 211, 238, 0.12), transparent 45%),
        linear-gradient(180deg, #f8fafc 0%, var(--adm-bg) 35%);
}
.rep-hero {
    position: relative;
    border-radius: 20px;
    padding: 1.75rem 1.75rem 1.5rem;
    margin-bottom: 1.25rem;
    background: linear-gradient(135deg,
        rgba(255,255,255,0.72) 0%,
        rgba(255,255,255,0.55) 40%,
        rgba(238, 242, 255, 0.65) 100%);
    border: 1px solid rgba(255,255,255,0.85);
    box-shadow:
        0 4px 24px rgba(79, 70, 229, 0.08),
        0 1px 0 rgba(255,255,255,0.9) inset,
        0 0 0 1px rgba(99, 102, 241, 0.06);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    overflow: hidden;
}
.rep-hero::before {
    content: "";
    position: absolute;
    inset: -40%;
    background: conic-gradient(from 210deg at 50% 50%,
        transparent 0deg,
        rgba(99, 102, 241, 0.07) 60deg,
        transparent 120deg,
        rgba(168, 85, 247, 0.06) 180deg,
        transparent 240deg,
        rgba(34, 211, 238, 0.06) 300deg,
        transparent 360deg);
    animation: rep-spin 28s linear infinite;
    pointer-events: none;
}
@keyframes rep-spin { to { transform: rotate(360deg); } }
.rep-hero-inner { position: relative; z-index: 1; }
.rep-hero h1 {
    font-family: Outfit, sans-serif;
    font-size: 1.65rem;
    font-weight: 700;
    margin: 0 0 0.35rem;
    letter-spacing: -0.03em;
    background: linear-gradient(105deg, #1e1b4b 0%, #4f46e5 45%, #7c3aed 85%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
}
.rep-hero p { margin: 0; color: var(--adm-muted); font-size: 0.95rem; max-width: 52rem; line-height: 1.5; }
.rep-tab-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    margin-top: 1.15rem;
}
.rep-tab-btn {
    font-family: Outfit, sans-serif;
    font-size: 0.8rem;
    font-weight: 600;
    padding: 0.45rem 0.95rem;
    border-radius: 999px;
    border: 1px solid rgba(79, 70, 229, 0.18);
    background: rgba(255,255,255,0.55);
    color: #4338ca;
    cursor: pointer;
}
.rep-tab-btn:hover { background: rgba(79, 70, 229, 0.12); }
.rep-tab-btn.is-active {
    background: linear-gradient(135deg, #6366f1, #7c3aed);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.35);
}
.rep-tab-panel { display: none; margin-bottom: 0.25rem; }
.rep-tab-panel.is-active { display: block; }
.rep-tab-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    margin-bottom: 1rem;
}
.rep-tab-actions .adm-btn { font-size: 0.8rem; padding: 0.4rem 0.85rem; text-decoration: none; }
.rep-details {
    border-radius: var(--radius);
    border: 1px solid var(--adm-border);
    background: var(--adm-surface);
    box-shadow: var(--shadow);
    margin-bottom: 1.25rem;
    overflow: hidden;
}
.rep-details summary {
    cursor: pointer;
    list-style: none;
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 1rem 1.25rem;
    font-family: Outfit, sans-serif;
    font-weight: 700;
    font-size: 0.95rem;
    user-select: none;
}
.rep-details summary::-webkit-details-marker { display: none; }
.rep-details summary::before {
    content: "⚙";
    font-size: 1rem;
    opacity: 0.85;
}
.rep-details[open] summary { border-bottom: 1px solid var(--adm-border); }
.rep-details-body { padding: 1rem 1.25rem 1.25rem; }
.rep-grid-filters {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
    align-items: end;
}
.rep-glass-card {
    background: linear-gradient(145deg, rgba(255,255,255,0.95), rgba(248,250,252,0.98));
    border: 1px solid rgba(226, 232, 240, 0.95);
    border-radius: var(--radius);
    box-shadow: var(--shadow), 0 0 0 1px rgba(99, 102, 241, 0.04);
    padding: 1.25rem 1.35rem;
    margin-bottom: 1.25rem;
    position: relative;
}
.rep-glass-card::after {
    content: "";
    position: absolute;
    inset: 0;
    border-radius: inherit;
    pointer-events: none;
    background: linear-gradient(125deg, rgba(99,102,241,0.04), transparent 40%, rgba(34,211,238,0.03));
}
.rep-glass-card > * { position: relative; z-index: 1; }
.rep-section-title {
    font-family: Outfit, sans-serif;
    font-size: 1.05rem;
    font-weight: 700;
    margin: 0 0 1rem;
    color: var(--adm-text);
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex-wrap: wrap;
}
.rep-section-title span.dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #a855f7);
    box-shadow: 0 0 12px rgba(99, 102, 241, 0.45);
}
.rep-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 1rem;
    margin-bottom: 1.25rem;
}
.rep-kpi {
    border-radius: 14px;
    padding: 1.1rem 1.15rem;
    background: linear-gradient(160deg, #fff 0%, #f8fafc 100%);
    border: 1px solid var(--adm-border);
    position: relative;
    overflow: hidden;
}
.rep-kpi::before {
    content: "";
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1, #a855f7, #22d3ee);
    opacity: 0.85;
}
.rep-kpi-val {
    font-family: Outfit, sans-serif;
    font-size: 1.35rem;
    font-weight: 700;
    letter-spacing: -0.02em;
}
.rep-kpi-lbl { font-size: 0.78rem; color: var(--adm-muted); margin-top: 0.25rem; text-transform: uppercase; letter-spacing: 0.06em; }
.rep-chart-wrap {
    position: relative;
    height: 260px;
}
.rep-chart-wrap.tall { height: 300px; }
.rep-table-wrap { overflow-x: auto; }
.rep-muted { color: var(--adm-muted); font-size: 0.875rem; }
.rep-pager {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
    align-items: center;
    margin-top: 1rem;
    font-size: 0.875rem;
}
.rep-pager a, .rep-pager span {
    padding: 0.35rem 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--adm-border);
    text-decoration: none;
    color: var(--adm-text);
}
.rep-pager a:hover { background: var(--adm-accent-soft); border-color: #c7d2fe; text-decoration: none; }
.rep-pager .disabled { opacity: 0.45; pointer-events: none; }
.rep-firs-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; }
@media (max-width: 640px) {
    .rep-bg { margin: 0; padding: 0 1rem 2rem; }
}
</style>

<div class="rep-bg">
    <div class="rep-hero">
        <div class="rep-hero-inner">
            <h1>Reports &amp; accounting</h1>
            <p>
                Same sections as the mobile app — switch tabs below. PDF and Excel exports respect your current filters.
                Filter by branch and date range in “Filters &amp; scope”.
            </p>
            <div class="rep-tab-bar" role="tablist" aria-label="Report tabs">
                <button type="button" class="rep-tab-btn is-active" role="tab" data-tab="daily" aria-selected="true">Daily</button>
                <button type="button" class="rep-tab-btn" role="tab" data-tab="trends" aria-selected="false">Trends</button>
                <button type="button" class="rep-tab-btn" role="tab" data-tab="pnl" aria-selected="false">P&amp;L</button>
                <button type="button" class="rep-tab-btn" role="tab" data-tab="products" aria-selected="false">Products</button>
                <button type="button" class="rep-tab-btn" role="tab" data-tab="payments" aria-selected="false">Payments</button>
                <button type="button" class="rep-tab-btn" role="tab" data-tab="expenses" aria-selected="false">Expenses</button>
                <button type="button" class="rep-tab-btn" role="tab" data-tab="firs" aria-selected="false">FIRS</button>
                @if($showLedger)
                    <button type="button" class="rep-tab-btn" role="tab" data-tab="ledger" aria-selected="false">Sales ledger</button>
                @endif
            </div>
        </div>
    </div>

    <details class="rep-details">
        <summary>Filters &amp; scope</summary>
        <div class="rep-details-body">
            <form method="get" action="{{ route('admin.b.reports.index', $business) }}" class="rep-grid-filters">
                <div>
                    <label class="adm-label" for="location_uuid">Branch / outlet</label>
                    <select class="adm-input" id="location_uuid" name="location_uuid">
                        <option value="all" {{ $scopeAllLocations ? 'selected' : '' }}>All branches</option>
                        @foreach($locations as $loc)
                            <option value="{{ $loc->uuid }}" {{ $selectedLocationUuid === $loc->uuid ? 'selected' : '' }}>
                                {{ $loc->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="adm-label" for="date">Daily report date</label>
                    <input class="adm-input" type="date" id="date" name="date" value="{{ $dailyDate }}">
                </div>
                <div>
                    <label class="adm-label" for="from">Range from</label>
                    <input class="adm-input" type="date" id="from" name="from" value="{{ $from->toDateString() }}">
                </div>
                <div>
                    <label class="adm-label" for="to">Range to</label>
                    <input class="adm-input" type="date" id="to" name="to" value="{{ $to->toDateString() }}">
                </div>
                @if($showLedger)
                <div>
                    <label class="adm-label" for="ledger_team">Ledger: team segment</label>
                    <select class="adm-input" id="ledger_team" name="ledger_team">
                        <option value="all" {{ $ledgerTeam === 'all' ? 'selected' : '' }}>Everyone</option>
                        <option value="sales" {{ $ledgerTeam === 'sales' ? 'selected' : '' }}>Sales staff</option>
                        <option value="management" {{ $ledgerTeam === 'management' ? 'selected' : '' }}>Management</option>
                    </select>
                </div>
                @endif
                <div style="grid-column: 1 / -1;">
                    <button type="submit" class="adm-btn adm-btn-primary">Apply filters</button>
                </div>
            </form>
        </div>
    </details>

    <div class="rep-tab-panel is-active" id="rep-panel-daily" data-tab="daily" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('daily', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('daily', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-kpi-grid">
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ number_format($daily['orders']) }}</div>
                <div class="rep-kpi-lbl">Orders ({{ $daily['date'] }})</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ $fmt($daily['revenue'], 0) }}</div>
                <div class="rep-kpi-lbl">Revenue that day</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ $fmt($daily['tax_total'], 0) }}</div>
                <div class="rep-kpi-lbl">Tax that day</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ number_format($daily['items_sold'], 1) }}</div>
                <div class="rep-kpi-lbl">Units sold</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ $fmt($stockValuation) }}</div>
                <div class="rep-kpi-lbl">Stock valuation (cost)</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ number_format($inventoryAvailability['products_with_stock'] ?? 0) }}</div>
                <div class="rep-kpi-lbl">SKUs in stock</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ number_format($inventoryAvailability['units_on_hand'] ?? 0, 2) }}</div>
                <div class="rep-kpi-lbl">Units on hand</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ $fmt($inventoryAvailability['cost_value_estimate'] ?? 0) }}</div>
                <div class="rep-kpi-lbl">Inventory cost (est.)</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ $fmt($inventoryAvailability['retail_value_estimate'] ?? 0) }}</div>
                <div class="rep-kpi-lbl">Inventory retail (list)</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val">{{ $fmt($expenseReport['total']) }}</div>
                <div class="rep-kpi-lbl">Expenses (range)</div>
            </div>
            <div class="rep-kpi">
                <div class="rep-kpi-val" style="color:{{ ($customerCredit['total_outstanding'] ?? 0) > 0 ? '#b91c1c' : 'inherit' }};">
                    {{ $fmt($customerCredit['total_outstanding'] ?? 0) }}
                </div>
                <div class="rep-kpi-lbl">
                    Customer credit outstanding
                    @if(($customerCredit['customers_with_debt'] ?? 0) > 0)
                        <br><span class="rep-muted">{{ number_format($customerCredit['customers_with_debt']) }} customer{{ $customerCredit['customers_with_debt'] === 1 ? '' : 's' }} owing</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="rep-tab-panel" id="rep-panel-trends" data-tab="trends" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('trends', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('trends', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Revenue trend</h2>
            <div class="rep-muted" style="margin-bottom:0.5rem;font-weight:600;">Sales by day in range</div>
            <div class="rep-chart-wrap tall"><canvas id="chartTrend"></canvas></div>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Daily trend table</h2>
            <div class="rep-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr><th>Date</th><th>Orders</th><th>Revenue</th><th>Tax</th></tr>
                    </thead>
                    <tbody>
                        @forelse($series as $row)
                            <tr>
                                <td>{{ $row->date }}</td>
                                <td>{{ $row->orders }}</td>
                                <td>{{ $fmt($row->revenue) }}</td>
                                <td>{{ $fmt($row->tax_total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="rep-muted">No sales in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rep-tab-panel" id="rep-panel-pnl" data-tab="pnl" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('pnl', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('pnl', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> P&amp;L composition</h2>
            <div class="rep-chart-wrap"><canvas id="chartPnl"></canvas></div>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Profit &amp; loss ({{ $pnl['period']['from'] }} → {{ $pnl['period']['to'] }})</h2>
            <div class="adm-grid cols-3" style="gap:0.85rem;">
                <div><span class="rep-muted">Revenue</span><br><strong>{{ $fmt($pnl['revenue']) }}</strong></div>
                <div><span class="rep-muted">Discounts</span><br><strong>{{ $fmt($pnl['discounts']) }}</strong></div>
                <div><span class="rep-muted">Tax collected</span><br><strong>{{ $fmt($pnl['tax_collected']) }}</strong></div>
                <div><span class="rep-muted">COGS (est.)</span><br><strong>{{ $fmt($pnl['cogs_estimate']) }}</strong></div>
                <div><span class="rep-muted">Gross profit</span><br><strong>{{ $fmt($pnl['gross_profit']) }}</strong></div>
                <div><span class="rep-muted">Expenses</span><br><strong>{{ $fmt($pnl['expenses']) }}</strong></div>
                <div style="grid-column:1/-1;padding-top:0.65rem;border-top:1px solid var(--adm-border);">
                    <span class="rep-muted">Net profit</span><br>
                    <strong style="font-size:1.35rem;font-family:Outfit,sans-serif;">{{ $fmt($pnl['net_profit']) }}</strong>
                    <span class="rep-muted" style="margin-left:0.5rem;">{{ $pnl['orders'] }} orders</span>
                </div>
            </div>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> COGS drill-down by product</h2>
            <p class="rep-muted" style="margin:-0.35rem 0 1rem;line-height:1.45;">
                Unit cost uses batch <strong>cost snapshot</strong> when stock was received, otherwise catalog cost — same basis as P&amp;L COGS.
                <strong>Margin vs COGS</strong> is sum of line totals (incl. VAT) minus estimated COGS — useful to spot wrong unit costs, not statutory gross margin.
            </p>
            <div class="rep-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Qty sold</th>
                            <th>Revenue (incl. VAT)</th>
                            <th>Sell/unit (ex VAT)</th>
                            <th>Cost/unit (est.)</th>
                            <th>COGS (est.)</th>
                            <th>Margin vs COGS</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pnlCogsBreakdown as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ number_format($row['units_sold'], 3) }}</td>
                                <td>{{ $fmt($row['revenue_line_total']) }}</td>
                                <td>{{ $fmt($row['avg_sell_pre_tax']) }}</td>
                                <td>{{ $fmt($row['unit_cost_weighted']) }}</td>
                                <td>{{ $fmt($row['cogs_estimate']) }}</td>
                                <td>{{ $fmt($row['margin_line_vs_cogs']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="rep-muted">No sold lines in this range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rep-tab-panel" id="rep-panel-products" data-tab="products" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('products', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('products', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Current inventory (on hand)</h2>
            <p class="rep-muted" style="margin:-0.5rem 0 1rem;font-size:0.85rem;line-height:1.45;">
                Totals from batches with quantity &gt; 0{{ ($scopeAllLocations ?? true) ? ' (all branches)' : ' (selected branch)' }}.
                Cost uses batch snapshot or catalog cost; values absurdly above list price are capped for reporting (same as stock valuation).
            </p>
            <div class="rep-kpi-grid">
                <div class="rep-kpi">
                    <div class="rep-kpi-label">SKUs in stock</div>
                    <div class="rep-kpi-val">{{ number_format($inventoryAvailability['products_with_stock'] ?? 0) }}</div>
                </div>
                <div class="rep-kpi">
                    <div class="rep-kpi-label">Units on hand</div>
                    <div class="rep-kpi-val">{{ number_format($inventoryAvailability['units_on_hand'] ?? 0, 2) }}</div>
                </div>
                <div class="rep-kpi">
                    <div class="rep-kpi-label">Cost value (est.)</div>
                    <div class="rep-kpi-val">{{ $fmt($inventoryAvailability['cost_value_estimate'] ?? 0) }}</div>
                </div>
                <div class="rep-kpi">
                    <div class="rep-kpi-label">Retail value (list)</div>
                    <div class="rep-kpi-val">{{ $fmt($inventoryAvailability['retail_value_estimate'] ?? 0) }}</div>
                </div>
            </div>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Top products by revenue</h2>
            <div class="rep-chart-wrap tall"><canvas id="chartProducts"></canvas></div>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Product performance</h2>
            <p class="rep-muted" style="margin:-0.5rem 0 1rem;font-size:0.85rem;line-height:1.45;">
                COGS uses the batch cost from the sale line when present. If that cost is far above the line&rsquo;s unit sell price (likely a data-entry mistake), it is capped at the sell price for this report so margins stay realistic.
            </p>
            <div class="rep-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr><th>Product</th><th>Units</th><th>Revenue</th><th>COGS (est.)</th><th>Margin (est.)</th></tr>
                    </thead>
                    <tbody>
                        @forelse($products as $p)
                            <tr>
                                <td>{{ $p['name'] }}</td>
                                <td>{{ number_format($p['units_sold'], 2) }}</td>
                                <td>{{ $fmt($p['revenue']) }}</td>
                                <td>{{ $fmt($p['cogs_estimate']) }}</td>
                                <td>{{ $fmt($p['margin_estimate']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="rep-muted">No line items in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rep-tab-panel" id="rep-panel-payments" data-tab="payments" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('payments', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('payments', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Payments by method</h2>
            <div class="rep-chart-wrap"><canvas id="chartPayments"></canvas></div>
            <div class="rep-table-wrap" style="margin-top:1rem;">
                <table class="adm-table">
                    <thead><tr><th>Method</th><th>Txns</th><th>Total</th></tr></thead>
                    <tbody>
                        @forelse($payments as $pay)
                            <tr>
                                <td>{{ strtoupper($pay['method']) }}</td>
                                <td>{{ $pay['transactions'] }}</td>
                                <td>{{ $fmt($pay['total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="rep-muted">No payments in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rep-glass-card" style="margin-top:1rem;">
            <h2 class="rep-section-title"><span class="dot"></span> Customer credit (receivables)</h2>
            <div class="rep-muted" style="margin-bottom:0.75rem;font-weight:600;">
                Money customers owe right now — independent of the date range.
            </div>
            <div class="rep-kpi-grid" style="margin-bottom:1rem;">
                <div class="rep-kpi">
                    <div class="rep-kpi-val" style="color:{{ ($customerCredit['total_outstanding'] ?? 0) > 0 ? '#b91c1c' : 'inherit' }};">
                        {{ $fmt($customerCredit['total_outstanding'] ?? 0) }}
                    </div>
                    <div class="rep-kpi-lbl">Total outstanding</div>
                </div>
                <div class="rep-kpi">
                    <div class="rep-kpi-val">{{ number_format($customerCredit['customers_with_debt'] ?? 0) }}</div>
                    <div class="rep-kpi-lbl">Customers owing</div>
                </div>
                <div class="rep-kpi">
                    <div class="rep-kpi-val" style="color:{{ ($customerCredit['customers_at_limit'] ?? 0) > 0 ? '#b91c1c' : 'inherit' }};">
                        {{ number_format($customerCredit['customers_at_limit'] ?? 0) }}
                    </div>
                    <div class="rep-kpi-lbl">At or over limit</div>
                </div>
                <div class="rep-kpi">
                    <div class="rep-kpi-val">{{ $fmt($customerCredit['total_limit'] ?? 0) }}</div>
                    <div class="rep-kpi-lbl">Total approved limit</div>
                </div>
            </div>

            @if(($customerCredit['top'] ?? collect())->isNotEmpty())
                <div class="rep-muted" style="margin-bottom:0.4rem;font-weight:600;">Top debtors</div>
                <div class="rep-table-wrap">
                    <table class="adm-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th style="text-align:right;">Outstanding</th>
                                <th style="text-align:right;">Limit</th>
                                <th style="text-align:right;">Utilisation</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($customerCredit['top'] as $row)
                                @php
                                    $bal = (float) $row['balance'];
                                    $lim = (float) $row['limit'];
                                    $util = $lim > 0 ? min(999, ($bal / $lim) * 100) : null;
                                @endphp
                                <tr>
                                    <td>{{ $row['name'] }}</td>
                                    <td style="text-align:right;font-weight:600;">{{ $fmt($bal) }}</td>
                                    <td style="text-align:right;">{{ $lim > 0 ? $fmt($lim) : '—' }}</td>
                                    <td style="text-align:right;{{ $util !== null && $util >= 100 ? 'color:#b91c1c;font-weight:600;' : '' }}">
                                        {{ $util === null ? '—' : number_format($util, 0).'%' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="rep-muted">No customer currently has an outstanding balance.</div>
            @endif
        </div>
    </div>

    <div class="rep-tab-panel" id="rep-panel-expenses" data-tab="expenses" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('expenses', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('expenses', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Expenses by category</h2>
            <div class="rep-chart-wrap"><canvas id="chartExpenses"></canvas></div>
            <div class="rep-table-wrap" style="margin-top:1rem;">
                <table class="adm-table">
                    <thead><tr><th>Date</th><th>Category</th><th>Amount</th><th>Notes</th></tr></thead>
                    <tbody>
                        @forelse($expenseReport['lines'] as $e)
                            <tr>
                                <td>{{ ($e->paid_at ?? $e->created_at)?->format('Y-m-d') }}</td>
                                <td>{{ $e->category ?: '—' }}</td>
                                <td>{{ $fmt($e->amount) }}</td>
                                <td style="max-width:12rem;overflow:hidden;text-overflow:ellipsis;">{{ Str::limit($e->notes, 48) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="rep-muted">No expenses in range.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rep-tab-panel" id="rep-panel-firs" data-tab="firs" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('firs', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('firs', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> FIRS / VAT assistance summary</h2>
            <p class="rep-muted" style="margin:-0.5rem 0 1rem;font-style:italic;">{{ $firs['disclaimer'] }}</p>
            <div class="rep-firs-grid" style="margin-bottom:1rem;">
                <div><span class="rep-muted">Legal name</span><br><strong>{{ $firs['taxpayer']['legal_name'] }}</strong></div>
                <div><span class="rep-muted">TIN</span><br><strong>{{ $firs['taxpayer']['tax_identification_number'] ?: '—' }}</strong></div>
                <div><span class="rep-muted">Transactions</span><br><strong>{{ $firs['summary']['transaction_count'] }}</strong></div>
                <div><span class="rep-muted">Gross sales (incl. VAT)</span><br><strong>{{ $fmt($firs['summary']['gross_sales_vat_inclusive']) }}</strong></div>
                <div><span class="rep-muted">Output VAT</span><br><strong>{{ $fmt($firs['summary']['output_vat_collected_on_sales']) }}</strong></div>
                <div><span class="rep-muted">Est. net taxable (ex VAT)</span><br><strong>{{ $fmt($firs['summary']['estimated_net_taxable_turnover_ex_vat']) }}</strong></div>
            </div>
            <div class="rep-table-wrap">
                <table class="adm-table">
                    <thead><tr><th>VAT rate %</th><th>Supply ex VAT</th><th>VAT amount</th></tr></thead>
                    <tbody>
                        @forelse($firs['by_vat_rate'] as $r)
                            <tr>
                                <td>{{ number_format((float) $r['vat_rate_percent'], 2) }}</td>
                                <td>{{ $fmt($r['supply_value_exclusive_vat']) }}</td>
                                <td>{{ $fmt($r['vat_amount']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="rep-muted">No rated lines in period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($showLedger && $ledger)
    <div class="rep-tab-panel" id="rep-panel-ledger" data-tab="ledger" role="tabpanel">
        <div class="rep-tab-actions">
            <span class="rep-muted" style="margin-right:0.35rem;font-weight:600;">Export</span>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('ledger', 'pdf') }}">PDF</a>
            <a class="adm-btn adm-btn-ghost" href="{{ $exportHref('ledger', 'xlsx') }}">Excel</a>
        </div>
        <div class="rep-glass-card">
            <h2 class="rep-section-title"><span class="dot"></span> Sales ledger (owner)</h2>
            <p class="rep-muted" style="margin-top:-0.5rem;">
                Summary: {{ $ledger['summary']['transaction_count'] }} transactions ·
                Total {{ $fmt($ledger['summary']['grand_total']) }}
                (Tax {{ $fmt($ledger['summary']['tax_total']) }})
            </p>
            <div class="rep-table-wrap">
                <table class="adm-table">
                    <thead>
                        <tr><th>Receipt</th><th>When</th><th>Branch</th><th>Seller</th><th>Total</th></tr>
                    </thead>
                    <tbody>
                        @forelse($ledger['sales'] as $s)
                            <tr>
                                <td>{{ $s['receipt_no'] }}</td>
                                <td>{{ \Carbon\Carbon::parse($s['sold_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i') }}</td>
                                <td>{{ $s['location_name'] ?? '—' }}</td>
                                <td>{{ $s['seller_name'] ?? '—' }}</td>
                                <td>{{ $fmt($s['grand_total']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="rep-muted">No rows this page.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @php $pg = $ledger['pagination']; @endphp
            @if(($pg['last_page'] ?? 1) > 1)
                <div class="rep-pager">
                    @if($pg['current_page'] > 1)
                        <a href="{{ request()->fullUrlWithQuery(['ledger_page' => $pg['current_page'] - 1]) }}">← Previous</a>
                    @else
                        <span class="disabled">← Previous</span>
                    @endif
                    <span>Page {{ $pg['current_page'] }} / {{ $pg['last_page'] }} · {{ $pg['total'] }} rows</span>
                    @if($pg['current_page'] < $pg['last_page'])
                        <a href="{{ request()->fullUrlWithQuery(['ledger_page' => $pg['current_page'] + 1]) }}">Next →</a>
                    @else
                        <span class="disabled">Next →</span>
                    @endif
                </div>
            @endif
        </div>
    </div>
    @endif
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
(function () {
    const sym = @json($sym);
    const fmtMoney = (v) => sym + Number(v).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const holo = ['#6366f1', '#a855f7', '#22d3ee', '#34d399', '#fbbf24', '#fb7185', '#818cf8', '#c084fc'];
    const font = { family: "'DM Sans', system-ui, sans-serif" };

    const charts = { trend: null, payments: null, pnl: null, expenses: null, products: null };

    function mountTrend() {
        const trendLabels = @json($trendLabels);
        const trendRev = @json($trendRevenue);
        const trendOrd = @json($trendOrders);
        const ctxT = document.getElementById('chartTrend');
        if (!ctxT || charts.trend || !trendLabels.length) return;
        charts.trend = new Chart(ctxT, {
            type: 'line',
            data: {
                labels: trendLabels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: trendRev,
                        tension: 0.35,
                        fill: true,
                        borderColor: '#6366f1',
                        backgroundColor: 'rgba(99, 102, 241, 0.12)',
                        borderWidth: 2,
                        pointRadius: 3,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#6366f1',
                    },
                    {
                        label: 'Orders',
                        data: trendOrd,
                        tension: 0.35,
                        yAxisID: 'y1',
                        borderColor: '#22d3ee',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        pointRadius: 2,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { font, usePointStyle: true } },
                    tooltip: {
                        callbacks: {
                            label: (c) => c.datasetIndex === 0 ? 'Revenue: ' + fmtMoney(c.raw) : 'Orders: ' + c.raw,
                        },
                    },
                },
                scales: {
                    x: { ticks: { font, maxRotation: 45 }, grid: { color: 'rgba(148,163,184,0.15)' } },
                    y: {
                        position: 'left',
                        ticks: { callback: (v) => sym + Number(v).toLocaleString(), font },
                        grid: { color: 'rgba(148,163,184,0.12)' },
                    },
                    y1: {
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        ticks: { font },
                    },
                },
            },
        });
    }

    function mountPayments() {
        const payL = @json($payLabels);
        const payD = @json($payTotals);
        const ctxP = document.getElementById('chartPayments');
        if (!ctxP || charts.payments || !payL.length) return;
        charts.payments = new Chart(ctxP, {
            type: 'doughnut',
            data: {
                labels: payL,
                datasets: [{ data: payD, backgroundColor: holo, borderWidth: 2, borderColor: '#fff', hoverOffset: 8 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font, padding: 14 } },
                    tooltip: { callbacks: { label: (c) => c.label + ': ' + fmtMoney(c.raw) } },
                },
            },
        });
    }

    function mountPnl() {
        const pnlL = @json($pnlPieLabels);
        const pnlV = @json($pnlPieValues);
        const ctxPn = document.getElementById('chartPnl');
        if (!ctxPn || charts.pnl || !pnlV.some((x) => x > 0)) return;
        charts.pnl = new Chart(ctxPn, {
            type: 'pie',
            data: {
                labels: pnlL,
                datasets: [{ data: pnlV, backgroundColor: ['#6366f1', '#fb7185', '#fbbf24'], borderWidth: 2, borderColor: '#fff' }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font } },
                    tooltip: { callbacks: { label: (c) => c.label + ': ' + fmtMoney(c.raw) } },
                },
            },
        });
    }

    function mountExpenses() {
        const exL = @json($expLabels);
        const exD = @json($expValues);
        const ctxE = document.getElementById('chartExpenses');
        if (!ctxE || charts.expenses || !exL.length) return;
        charts.expenses = new Chart(ctxE, {
            type: 'pie',
            data: {
                labels: exL,
                datasets: [{ data: exD, backgroundColor: holo, borderWidth: 2, borderColor: '#fff' }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { font } },
                    tooltip: { callbacks: { label: (c) => c.label + ': ' + fmtMoney(c.raw) } },
                },
            },
        });
    }

    function mountProducts() {
        const prL = @json($prodLabels);
        const prD = @json($prodRevenues);
        const ctxPr = document.getElementById('chartProducts');
        if (!ctxPr || charts.products || !prL.length) return;
        charts.products = new Chart(ctxPr, {
            type: 'bar',
            data: {
                labels: prL,
                datasets: [{
                    label: 'Revenue',
                    data: prD,
                    backgroundColor: prL.map((_, i) => holo[i % holo.length]),
                    borderRadius: 8,
                    borderSkipped: false,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (c) => fmtMoney(c.raw) } },
                },
                scales: {
                    x: { ticks: { callback: (v) => sym + Number(v).toLocaleString(), font }, grid: { color: 'rgba(148,163,184,0.12)' } },
                    y: { ticks: { font }, grid: { display: false } },
                },
            },
        });
    }

    function activateTab(name) {
        document.querySelectorAll('.rep-tab-btn').forEach((btn) => {
            const on = btn.dataset.tab === name;
            btn.classList.toggle('is-active', on);
            btn.setAttribute('aria-selected', on ? 'true' : 'false');
        });
        document.querySelectorAll('.rep-tab-panel').forEach((panel) => {
            panel.classList.toggle('is-active', panel.dataset.tab === name);
        });
        if (name === 'trends') mountTrend();
        if (name === 'payments') mountPayments();
        if (name === 'pnl') mountPnl();
        if (name === 'expenses') mountExpenses();
        if (name === 'products') mountProducts();
    }

    document.querySelectorAll('.rep-tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => activateTab(btn.dataset.tab));
    });
})();
</script>
@endsection
