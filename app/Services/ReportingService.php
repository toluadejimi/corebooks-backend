<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Location;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public const MAX_RANGE_DAYS = 366;

    /**
     * If stored unit cost exceeds this multiple of the reference sell price, treat it as bad data
     * (e.g. purchase line total entered as unit cost) and cap cost at the sell price for reporting.
     */
    private const COGS_COST_VS_SELL_RATIO_CEIL = 15;

    /**
     * SQL fragment: unit cost for COGS on a joined sale line (uses batch snapshot, else catalog cost).
     */
    private function cogsEffectiveUnitCostSql(): string
    {
        $m = self::COGS_COST_VS_SELL_RATIO_CEIL;
        $raw = 'COALESCE(NULLIF(product_batches.cost_price_snapshot, 0), products.cost_price)';
        $sell = 'sale_lines.unit_price';

        return "(CASE WHEN {$sell} > 0 AND ({$raw}) > ({$sell} * {$m}) THEN LEAST(({$raw}), {$sell}) ELSE ({$raw}) END)";
    }

    /**
     * SQL fragment: unit cost for on-hand inventory (joined product_batches + products).
     */
    private function inventoryEffectiveUnitCostSql(): string
    {
        $m = self::COGS_COST_VS_SELL_RATIO_CEIL;
        $raw = 'COALESCE(NULLIF(product_batches.cost_price_snapshot, 0), products.cost_price)';
        $list = 'products.selling_price';

        return "(CASE WHEN {$list} > 0 AND ({$raw}) > ({$list} * {$m}) THEN LEAST(({$raw}), {$list}) ELSE ({$raw}) END)";
    }

    public function resolveRange(?string $from, ?string $to): array
    {
        $toDate = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : (clone $toDate)->subDays(29)->startOfDay();

        if ($fromDate->gt($toDate)) {
            return [now()->subDays(29)->startOfDay(), now()->endOfDay()];
        }

        if ($fromDate->diffInDays($toDate) > self::MAX_RANGE_DAYS) {
            $fromDate = (clone $toDate)->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        }

        return [$fromDate, $toDate];
    }

    /**
     * @return array{date: string, orders: int, revenue: float, tax_total: float, discount_total: float, items_sold: float, avg_order_value: float}
     */
    public function dailySummary(Business $business, string $date, ?int $locationId = null): array
    {
        $day = Carbon::parse($date)->toDateString();

        $agg = Sale::query()
            ->where('business_id', $business->id)
            ->whereDate('sold_at', $day)
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as revenue, COALESCE(SUM(tax_total),0) as tax_total, COALESCE(SUM(discount_total),0) as discount_total')
            ->first();

        $itemsSold = (float) SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.business_id', $business->id)
            ->whereDate('sales.sold_at', $day)
            ->when($locationId !== null, fn ($q) => $q->where('sales.location_id', $locationId))
            ->sum('sale_lines.qty');

        $orders = (int) $agg->orders;
        $revenue = (float) $agg->revenue;

        return [
            'date' => $day,
            'orders' => $orders,
            'revenue' => $revenue,
            'tax_total' => (float) $agg->tax_total,
            'discount_total' => (float) $agg->discount_total,
            'items_sold' => $itemsSold,
            'avg_order_value' => $orders > 0 ? round($revenue / $orders, 2) : 0.0,
        ];
    }

    /**
     * @return Collection<int, object{d: string, orders: int, revenue: float, tax_total: float}>
     */
    public function timeseries(Business $business, Carbon $from, Carbon $to, ?int $locationId = null): Collection
    {
        return Sale::query()
            ->where('business_id', $business->id)
            ->whereBetween('sold_at', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('DATE(sold_at) as d, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as revenue, COALESCE(SUM(tax_total),0) as tax_total')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => (object) [
                'date' => $r->d,
                'orders' => (int) $r->orders,
                'revenue' => (float) $r->revenue,
                'tax_total' => (float) $r->tax_total,
            ]);
    }

    /**
     * COGS estimate: Σ (qty × unit cost). Prefers the batch's cost_price_snapshot from when stock was
     * received (sale_lines.product_batch_id), then falls back to the product's current catalog cost.
     * If a snapshot is absurdly higher than the line's pre-tax unit sell price (likely data entry error),
     * it is capped at that sell price so P&amp;L and product tables stay usable.
     * Using only catalog cost made historical P&amp;L swing when someone later edits product.cost_price.
     *
     * @return array{period: array{from: string, to: string}, revenue: float, tax_collected: float, discounts: float, cogs_estimate: float, gross_profit: float, expenses: float, net_profit: float, orders: int}
     */
    public function profitAndLoss(Business $business, Carbon $from, Carbon $to, ?int $locationId = null): array
    {
        $salesAgg = Sale::query()
            ->where('business_id', $business->id)
            ->whereBetween('sold_at', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as revenue, COALESCE(SUM(tax_total),0) as tax_total, COALESCE(SUM(discount_total),0) as discount_total')
            ->first();

        $unitCostSql = $this->cogsEffectiveUnitCostSql();

        $cogs = (float) SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->leftJoin('product_batches', 'product_batches.id', '=', 'sale_lines.product_batch_id')
            ->where('sales.business_id', $business->id)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('sales.location_id', $locationId))
            ->selectRaw('COALESCE(SUM(sale_lines.qty * '.$unitCostSql.'), 0) as v')
            ->value('v');

        $expenses = (float) Expense::query()
            ->where('business_id', $business->id)
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->sum('amount');

        $revenue = (float) $salesAgg->revenue;
        $tax = (float) $salesAgg->tax_total;
        $discounts = (float) $salesAgg->discount_total;
        $gross = round($revenue - $cogs, 2);
        $net = round($gross - $expenses, 2);

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'orders' => (int) $salesAgg->orders,
            'revenue' => $revenue,
            'tax_collected' => $tax,
            'discounts' => $discounts,
            'cogs_estimate' => round($cogs, 2),
            'gross_profit' => $gross,
            'expenses' => round($expenses, 2),
            'net_profit' => $net,
        ];
    }

    /**
     * @return Collection<int, array{product_uuid: ?string, name: string, units_sold: float, revenue: float, cogs_estimate: float, margin_estimate: float}>
     */
    public function productPerformance(Business $business, Carbon $from, Carbon $to, ?int $locationId = null): Collection
    {
        $unitCostSql = $this->cogsEffectiveUnitCostSql();

        $rows = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->leftJoin('product_batches', 'product_batches.id', '=', 'sale_lines.product_batch_id')
            ->where('sales.business_id', $business->id)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('sales.location_id', $locationId))
            ->groupBy('products.id', 'products.uuid', 'products.name')
            ->orderByDesc(DB::raw('SUM(sale_lines.line_total)'))
            ->selectRaw('products.uuid as product_uuid, products.name as name, SUM(sale_lines.qty) as units_sold, SUM(sale_lines.line_total) as revenue, SUM(sale_lines.qty * '.$unitCostSql.') as cogs_est')
            ->get();

        return $rows->map(fn ($r) => [
            'product_uuid' => $r->product_uuid,
            'name' => $r->name,
            'units_sold' => (float) $r->units_sold,
            'revenue' => (float) $r->revenue,
            'cogs_estimate' => (float) $r->cogs_est,
            'margin_estimate' => round((float) $r->revenue - (float) $r->cogs_est, 2),
        ]);
    }

    /**
     * Per-product COGS breakdown (same unit-cost basis as {@see profitAndLoss}) for admin drill-down and exports.
     *
     * @return Collection<int, array{
     *     name: string,
     *     units_sold: float,
     *     revenue_line_total: float,
     *     revenue_pre_tax: float,
     *     avg_sell_pre_tax: float,
     *     unit_cost_weighted: float,
     *     cogs_estimate: float,
     *     margin_line_vs_cogs: float
     * }>
     */
    public function profitLossCogsBreakdown(Business $business, Carbon $from, Carbon $to, ?int $locationId = null): Collection
    {
        $unitCostSql = $this->cogsEffectiveUnitCostSql();

        $rows = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->join('products', 'products.id', '=', 'sale_lines.product_id')
            ->leftJoin('product_batches', 'product_batches.id', '=', 'sale_lines.product_batch_id')
            ->where('sales.business_id', $business->id)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('sales.location_id', $locationId))
            ->groupBy('products.id', 'products.uuid', 'products.name')
            ->orderByDesc(DB::raw('SUM(sale_lines.qty * '.$unitCostSql.')'))
            ->selectRaw('products.name as name, SUM(sale_lines.qty) as units_sold, SUM(sale_lines.line_total) as revenue_line_total, SUM(sale_lines.qty * sale_lines.unit_price) as revenue_pre_tax, SUM(sale_lines.qty * '.$unitCostSql.') as cogs_est')
            ->get();

        return $rows->map(function ($r) {
            $qty = (float) $r->units_sold;
            $preTax = (float) $r->revenue_pre_tax;
            $lineTot = (float) $r->revenue_line_total;
            $cogs = (float) $r->cogs_est;
            $avgSell = $qty > 0 ? round($preTax / $qty, 4) : 0.0;
            $unitCostW = $qty > 0 ? round($cogs / $qty, 4) : 0.0;

            return [
                'name' => (string) $r->name,
                'units_sold' => $qty,
                'revenue_line_total' => round($lineTot, 2),
                'revenue_pre_tax' => round($preTax, 2),
                'avg_sell_pre_tax' => $avgSell,
                'unit_cost_weighted' => $unitCostW,
                'cogs_estimate' => round($cogs, 2),
                'margin_line_vs_cogs' => round($lineTot - $cogs, 2),
            ];
        })->values();
    }

    /**
     * @return Collection<int, array{method: string, transactions: int, total: float}>
     */
    public function paymentMix(Business $business, Carbon $from, Carbon $to, ?int $locationId = null): Collection
    {
        return Payment::query()
            ->join('sales', 'sales.id', '=', 'payments.sale_id')
            ->where('payments.business_id', $business->id)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('sales.location_id', $locationId))
            ->groupBy('payments.method')
            ->orderByDesc(DB::raw('SUM(payments.amount)'))
            ->selectRaw('payments.method as method, COUNT(*) as transactions, COALESCE(SUM(payments.amount),0) as total')
            ->get()
            ->map(fn ($r) => [
                'method' => $r->method,
                'transactions' => (int) $r->transactions,
                'total' => (float) $r->total,
            ]);
    }

    /**
     * @return array{total: float, by_category: Collection, lines: Collection}
     */
    public function expenseReport(Business $business, Carbon $from, Carbon $to, ?int $locationId = null): array
    {
        $lines = Expense::query()
            ->where('business_id', $business->id)
            ->whereRaw('COALESCE(paid_at, created_at) BETWEEN ? AND ?', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->with('location')
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->get();

        $byCategory = $lines->groupBy(fn ($e) => $e->category ?: 'Uncategorized')
            ->map(fn (Collection $g) => round((float) $g->sum('amount'), 2));

        return [
            'total' => round((float) $lines->sum('amount'), 2),
            'by_category' => $byCategory,
            'lines' => $lines,
        ];
    }

    /**
     * Total customer credit currently outstanding across the business (sum of positive
     * `credit_balance`). Walk-in customers are excluded since they cannot owe by design.
     * Includes a small top-debtors list for the UI breakdown.
     *
     * @return array{
     *     total_outstanding: float,
     *     customers_with_debt: int,
     *     customers_at_limit: int,
     *     total_limit: float,
     *     top: Collection<int, array{uuid:string, name:string, balance:float, limit:float}>
     * }
     */
    public function customerCreditSummary(Business $business, int $topLimit = 10): array
    {
        $base = Customer::query()
            ->where('business_id', $business->id)
            ->where('is_walk_in', false);

        $totals = (clone $base)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN credit_balance > 0 THEN credit_balance ELSE 0 END), 0) as total_outstanding,
                COUNT(CASE WHEN credit_balance > 0 THEN 1 END) as customers_with_debt,
                COUNT(CASE WHEN credit_enabled = 1 AND credit_limit > 0 AND credit_balance >= credit_limit THEN 1 END) as customers_at_limit,
                COALESCE(SUM(CASE WHEN credit_enabled = 1 THEN credit_limit ELSE 0 END), 0) as total_limit
            ')
            ->first();

        $top = (clone $base)
            ->where('credit_balance', '>', 0)
            ->orderByDesc('credit_balance')
            ->limit(max(1, $topLimit))
            ->get(['uuid', 'name', 'credit_balance', 'credit_limit'])
            ->map(fn (Customer $c) => [
                'uuid' => (string) $c->uuid,
                'name' => (string) $c->name,
                'balance' => (float) $c->credit_balance,
                'limit' => (float) $c->credit_limit,
            ])
            ->values();

        return [
            'total_outstanding' => round((float) ($totals->total_outstanding ?? 0), 2),
            'customers_with_debt' => (int) ($totals->customers_with_debt ?? 0),
            'customers_at_limit' => (int) ($totals->customers_at_limit ?? 0),
            'total_limit' => round((float) ($totals->total_limit ?? 0), 2),
            'top' => $top,
        ];
    }

    /**
     * Estimated value of on-hand inventory at cost: Σ (batch qty × unit cost).
     * Uses each batch’s {@see ProductBatch::$cost_price_snapshot} when set (from purchase/receipt),
     * otherwise the product’s current catalog {@see Product::$cost_price}.
     * Previously only catalog cost was used, so one high catalog price inflated every batch for that SKU.
     * Snapshots absurdly above catalog list price are capped (same rule as COGS on sale lines).
     */
    public function stockValuation(Business $business, ?int $locationId = null): float
    {
        $unitCostSql = $this->inventoryEffectiveUnitCostSql();

        $v = Product::query()
            ->where('products.business_id', $business->id)
            ->join('product_batches', 'products.id', '=', 'product_batches.product_id')
            ->when($locationId !== null, fn ($q) => $q->where('product_batches.location_id', $locationId))
            ->selectRaw('COALESCE(SUM(product_batches.qty * '.$unitCostSql.'),0) as v')
            ->value('v');

        return (float) ($v ?? 0);
    }

    /**
     * Current availability across products with positive batch qty (optionally one branch).
     *
     * @return array{
     *     products_with_stock: int,
     *     units_on_hand: float,
     *     cost_value_estimate: float,
     *     retail_value_estimate: float
     * }
     */
    public function inventoryAvailabilityTotals(Business $business, ?int $locationId = null): array
    {
        $unit = $this->inventoryEffectiveUnitCostSql();

        $row = ProductBatch::query()
            ->join('products', 'products.id', '=', 'product_batches.product_id')
            ->where('products.business_id', $business->id)
            ->where('product_batches.qty', '>', 0)
            ->when($locationId !== null, fn ($q) => $q->where('product_batches.location_id', $locationId))
            ->selectRaw(
                'COUNT(DISTINCT products.id) as skus, '.
                'COALESCE(SUM(product_batches.qty), 0) as units, '.
                'COALESCE(SUM(product_batches.qty * '.$unit.'), 0) as cost_val, '.
                'COALESCE(SUM(product_batches.qty * products.selling_price), 0) as retail_val'
            )
            ->first();

        return [
            'products_with_stock' => (int) ($row?->skus ?? 0),
            'units_on_hand' => round((float) ($row?->units ?? 0), 3),
            'cost_value_estimate' => round((float) ($row?->cost_val ?? 0), 2),
            'retail_value_estimate' => round((float) ($row?->retail_val ?? 0), 2),
        ];
    }

    /**
     * @return Collection<int, array{date: string, revenue: float}>
     */
    public function weeklyRevenue(Business $business, int $days = 7, ?int $locationId = null): Collection
    {
        $from = now()->subDays($days)->startOfDay();

        return Sale::query()
            ->where('business_id', $business->id)
            ->where('sold_at', '>=', $from)
            ->when($locationId !== null, fn ($q) => $q->where('location_id', $locationId))
            ->selectRaw('DATE(sold_at) as d, COALESCE(SUM(grand_total),0) as revenue')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->map(fn ($r) => [
                'date' => (string) $r->d,
                'revenue' => (float) $r->revenue,
            ]);
    }

    public function topProductsByUnits(Business $business, Carbon $from, int $limit = 10, ?int $locationId = null): Collection
    {
        return SaleLine::query()
            ->select('product_id', DB::raw('SUM(qty) as units'))
            ->whereIn('sale_id', function ($q) use ($business, $from, $locationId) {
                $q->select('id')->from('sales')
                    ->where('business_id', $business->id)
                    ->where('sold_at', '>=', $from);
                if ($locationId !== null) {
                    $q->where('location_id', $locationId);
                }
            })
            ->groupBy('product_id')
            ->orderByDesc('units')
            ->limit($limit)
            ->with('product:id,uuid,name')
            ->get()
            ->map(fn ($r) => [
                'product_uuid' => $r->product?->uuid,
                'name' => $r->product?->name,
                'units' => (float) $r->units,
            ]);
    }

    /**
     * Nigeria VAT-focused regulatory summary for internal bookkeeping and VAT return assistance.
     * Not a substitute for professional tax advice or official FIRS submission formats.
     *
     * @return array<string, mixed>
     */
    public function firsComplianceReport(Business $business, Carbon $from, Carbon $to, ?int $locationId = null): array
    {
        $business->refresh();

        $saleQuery = Sale::query()
            ->where('business_id', $business->id)
            ->whereBetween('sold_at', [$from, $to]);

        if ($locationId !== null) {
            $saleQuery->where('location_id', $locationId);
        }

        $agg = (clone $saleQuery)
            ->selectRaw(
                'COUNT(*) as orders,
                 COALESCE(SUM(grand_total), 0) as gross_sales,
                 COALESCE(SUM(subtotal), 0) as subtotal_before_discount,
                 COALESCE(SUM(discount_total), 0) as discount_total,
                 COALESCE(SUM(tax_total), 0) as output_vat'
            )
            ->first();

        $byRateRows = SaleLine::query()
            ->join('sales', 'sales.id', '=', 'sale_lines.sale_id')
            ->where('sales.business_id', $business->id)
            ->whereBetween('sales.sold_at', [$from, $to])
            ->when($locationId !== null, fn ($q) => $q->where('sales.location_id', $locationId))
            ->groupBy('sale_lines.tax_rate')
            ->orderBy('sale_lines.tax_rate')
            ->select([
                DB::raw('sale_lines.tax_rate as vat_rate_percent'),
                DB::raw('COALESCE(SUM(sale_lines.qty * sale_lines.unit_price), 0) as supply_value_ex_vat'),
                DB::raw('COALESCE(SUM(sale_lines.line_total - (sale_lines.qty * sale_lines.unit_price)), 0) as vat_collected'),
                DB::raw('COALESCE(SUM(sale_lines.line_total), 0) as line_total_gross'),
            ])
            ->get();

        $locationBlock = null;
        if ($locationId !== null) {
            $loc = Location::query()
                ->where('business_id', $business->id)
                ->where('id', $locationId)
                ->first(['uuid', 'name']);
            if ($loc) {
                $locationBlock = [
                    'uuid' => $loc->uuid,
                    'name' => $loc->name,
                ];
            }
        }

        $taxpayerLines = collect([
            $business->address_line1,
            $business->address_line2,
            collect([$business->city, $business->state, $business->country])->filter()->implode(', '),
        ])->filter()->implode("\n");

        return [
            'document_type' => 'firs_sales_vat_summary',
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'scope' => [
                'location' => $locationBlock,
                'currency' => $business->currency,
            ],
            'disclaimer' => 'Prepared from CoreBooks transaction data for bookkeeping and VAT return assistance only. Confirm figures with your accountant before filing with the Federal Inland Revenue Service.',
            'taxpayer' => [
                'legal_name' => $business->name,
                'tax_identification_number' => $business->tax_id,
                'phone' => $business->phone,
                'registered_address_lines' => $taxpayerLines !== '' ? $taxpayerLines : null,
            ],
            'summary' => [
                'transaction_count' => (int) $agg->orders,
                'gross_sales_vat_inclusive' => round((float) $agg->gross_sales, 2),
                'subtotal_ex_vat_before_discount' => round((float) $agg->subtotal_before_discount, 2),
                'discounts_given' => round((float) $agg->discount_total, 2),
                'estimated_net_taxable_turnover_ex_vat' => round(max(0.0, (float) $agg->gross_sales - (float) $agg->output_vat), 2),
                'output_vat_collected_on_sales' => round((float) $agg->output_vat, 2),
                'effective_statutory_vat_rate_percent' => (float) $business->default_vat_rate,
            ],
            'by_vat_rate' => $byRateRows->map(fn ($r) => [
                'vat_rate_percent' => (float) $r->vat_rate_percent,
                'supply_value_exclusive_vat' => round((float) $r->supply_value_ex_vat, 2),
                'vat_amount' => round((float) $r->vat_collected, 2),
                'gross_line_totals_inclusive_vat' => round((float) $r->line_total_gross, 2),
            ])->values(),
        ];
    }

    /**
     * Paginated detailed sales for ownership review (filters by branch / seller / team segment).
     *
     * @return array<string, mixed>
     */
    public function salesLedger(
        Business $business,
        Carbon $from,
        Carbon $to,
        ?int $locationId,
        ?int $sellerUserId,
        ?string $teamSegment,
        int $page,
        int $perPage,
        string $pageParameterName = 'page',
    ): array {
        $perPage = min(max($perPage, 1), 500);

        $applyFilters = function ($query) use ($business, $from, $to, $locationId, $sellerUserId, $teamSegment): void {
            $query->where('sales.business_id', $business->id)
                ->whereBetween('sold_at', [$from, $to]);

            if ($locationId !== null) {
                $query->where('sales.location_id', $locationId);
            }

            if ($sellerUserId !== null) {
                $query->where('sales.user_id', $sellerUserId);

                return;
            }

            $segment = strtolower((string) ($teamSegment ?? 'all'));
            if ($segment === 'sales') {
                $ids = $business->users()->wherePivot('role', BusinessRole::Sales->value)->pluck('users.id');
                $query->whereIn('sales.user_id', $ids);
            } elseif ($segment === 'management') {
                $ids = $business->users()->wherePivotIn('role', [
                    BusinessRole::Owner->value,
                    BusinessRole::Manager->value,
                ])->pluck('users.id');
                $query->whereIn('sales.user_id', $ids);
            }
        };

        $agg = Sale::query();
        $applyFilters($agg);
        $sums = $agg->selectRaw(
            'COUNT(*) as c,
             COALESCE(SUM(grand_total), 0) as grand_total,
             COALESCE(SUM(subtotal), 0) as subtotal,
             COALESCE(SUM(tax_total), 0) as tax_total,
             COALESCE(SUM(discount_total), 0) as discount_total'
        )->first();

        $list = Sale::query()
            ->with(['location:id,uuid,name', 'user:id,name,email'])
            ->tap($applyFilters)
            ->orderByDesc('sold_at');

        /** @var LengthAwarePaginator $pager */
        $pager = $list->paginate($perPage, ['*'], $pageParameterName, max($page, 1));

        $sales = collect($pager->items())->map(function (Sale $s) {
            return [
                'uuid' => $s->uuid,
                'receipt_no' => $s->receipt_no,
                'sold_at' => $s->sold_at?->toIso8601String(),
                'grand_total' => round((float) $s->grand_total, 2),
                'subtotal' => round((float) $s->subtotal, 2),
                'tax_total' => round((float) $s->tax_total, 2),
                'discount_total' => round((float) $s->discount_total, 2),
                'location_uuid' => $s->location?->uuid,
                'location_name' => $s->location?->name,
                'seller_user_id' => $s->user_id,
                'seller_name' => $s->user?->name,
                'seller_email' => $s->user?->email,
            ];
        })->values();

        return [
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'summary' => [
                'transaction_count' => (int) ($sums->c ?? 0),
                'grand_total' => round((float) ($sums->grand_total ?? 0), 2),
                'subtotal' => round((float) ($sums->subtotal ?? 0), 2),
                'tax_total' => round((float) ($sums->tax_total ?? 0), 2),
                'discount_total' => round((float) ($sums->discount_total ?? 0), 2),
            ],
            'sales' => $sales,
            'pagination' => [
                'current_page' => $pager->currentPage(),
                'last_page' => $pager->lastPage(),
                'per_page' => $pager->perPage(),
                'total' => $pager->total(),
            ],
        ];
    }
}
