<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\User;
use App\Services\ReportingService;
use App\Services\SaleVoidService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Writer as CsvWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class SalesWebController extends Controller
{
    use ResolvesWorkspace;

    private const EXPORT_LIMIT = 5000;

    public function __construct(
        protected ReportingService $reporting,
        protected SaleVoidService $saleVoid,
    ) {}

    public function index(Request $request, Business $business): View
    {
        [$filters, $from, $to] = $this->resolveFilters($request, $business);
        $query = $this->filteredSalesQuery($business, $filters, $from, $to);

        $summary = (clone $query)
            ->toBase()
            ->selectRaw('COUNT(*) as sale_count')
            ->selectRaw('COALESCE(SUM(grand_total), 0) as grand_total')
            ->selectRaw('COALESCE(SUM(tax_total), 0) as tax_total')
            ->selectRaw('COALESCE(SUM(discount_total), 0) as discount_total')
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal')
            ->first();

        $sales = (clone $query)
            ->with(['location:id,name', 'customer:id,name', 'user:id,name', 'payments:id,sale_id,method,amount'])
            ->withCount('lines')
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString();

        $tz = config('app.timezone');
        $groupBy = $filters['group_by'];
        $groupedSales = $sales->getCollection()->groupBy(function (Sale $sale) use ($tz, $groupBy): string {
            return match ($groupBy) {
                'branch' => $sale->location?->name ?: 'No branch',
                'none' => 'all',
                default => $sale->sold_at?->timezone($tz)->toDateString() ?? 'unknown',
            };
        });

        if ($groupBy === 'day') {
            $groupedSales = $groupedSales->sortKeysDesc();
        } else {
            $groupedSales = $groupedSales->sortKeys();
        }

        return view('admin.sales.index', $this->workspace($request, $business) + [
            'sales' => $sales,
            'groupedSales' => $groupedSales,
            'summary' => [
                'sale_count' => (int) ($summary->sale_count ?? 0),
                'grand_total' => (float) ($summary->grand_total ?? 0),
                'tax_total' => (float) ($summary->tax_total ?? 0),
                'discount_total' => (float) ($summary->discount_total ?? 0),
                'subtotal' => (float) ($summary->subtotal ?? 0),
            ],
            'filters' => $filters,
            'from' => $from,
            'to' => $to,
            'locations' => $business->locations()->orderByDesc('is_default')->orderBy('name')->get(['id', 'uuid', 'name']),
            'staff' => $this->staffOptions($business),
            'currencySymbol' => $this->currencySymbol($business),
            'exportQuery' => array_filter([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'location_uuid' => $filters['location_uuid'] !== 'all' ? $filters['location_uuid'] : null,
                'user_id' => $filters['user_id'] ?: null,
                'status' => $filters['status'] !== 'all' ? $filters['status'] : null,
                'payment_method' => $filters['payment_method'] !== 'all' ? $filters['payment_method'] : null,
                'q' => $filters['q'] !== '' ? $filters['q'] : null,
                'group_by' => $filters['group_by'] !== 'day' ? $filters['group_by'] : null,
            ], static fn ($v) => $v !== null && $v !== ''),
        ]);
    }

    public function export(Request $request, Business $business, string $format): BinaryFileResponse
    {
        $format = strtolower($format);
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            abort(404);
        }

        [$filters, $from, $to] = $this->resolveFilters($request, $business);
        $sales = $this->filteredSalesQuery($business, $filters, $from, $to)
            ->with(['location:id,name', 'customer:id,name', 'user:id,name', 'payments:id,sale_id,method,amount'])
            ->withCount('lines')
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(self::EXPORT_LIMIT)
            ->get();

        $tz = config('app.timezone');
        $filename = sprintf(
            'sales-%s-%s-to-%s.%s',
            preg_replace('/[^a-z0-9]+/i', '-', strtolower($business->name)) ?: 'business',
            $from->toDateString(),
            $to->toDateString(),
            $format
        );

        $header = [
            'Receipt',
            'When',
            'Branch',
            'Customer',
            'Staff',
            'Status',
            'Payment methods',
            'Lines',
            'Subtotal',
            'Tax',
            'Discount',
            'Grand total',
        ];

        $rows = [];
        $rows[] = $header;
        foreach ($sales as $sale) {
            $rows[] = [
                (string) $sale->receipt_no,
                $sale->sold_at?->timezone($tz)->format('Y-m-d H:i') ?? '',
                $sale->location?->name ?? '',
                $sale->customer?->name ?? '',
                $sale->user?->name ?? '',
                (string) $sale->status,
                $sale->payments->pluck('method')->unique()->sort()->implode(', '),
                (int) $sale->lines_count,
                round((float) $sale->subtotal, 2),
                round((float) $sale->tax_total, 2),
                round((float) $sale->discount_total, 2),
                round((float) $sale->grand_total, 2),
            ];
        }

        $rows[] = [];
        $rows[] = [
            'Summary',
            '',
            '',
            '',
            '',
            '',
            '',
            $sales->count(),
            round((float) $sales->sum('subtotal'), 2),
            round((float) $sales->sum('tax_total'), 2),
            round((float) $sales->sum('discount_total'), 2),
            round((float) $sales->sum('grand_total'), 2),
        ];
        if ($sales->count() >= self::EXPORT_LIMIT) {
            $rows[] = ['Note', 'Export capped at '.self::EXPORT_LIMIT.' rows. Narrow the date range if needed.'];
        }

        if ($format === 'csv') {
            return $this->csvDownload($filename, $rows);
        }

        return $this->xlsxDownload($filename, $rows);
    }

    public function show(Request $request, Business $business, string $saleUuid): View|RedirectResponse
    {
        $saleUuid = trim($saleUuid);

        $sale = Sale::query()
            ->where('business_id', $business->id)
            ->where(function ($q) use ($saleUuid): void {
                $q->where('uuid', $saleUuid)
                    ->orWhere('receipt_no', $saleUuid);
            })
            ->first();

        if ($sale === null) {
            return redirect()
                ->route('admin.b.sales.index', $business)
                ->withErrors([
                    'sale' => "We couldn't find that sale (it may have been deleted or belongs to a different workspace).",
                ]);
        }

        if ($sale->uuid !== $saleUuid) {
            return redirect()->route('admin.b.sales.show', [$business, $sale->uuid]);
        }

        $sale->load([
            'lines.product',
            'lines.batch',
            'payments',
            'customer',
            'location',
            'user',
        ]);

        $customers = Customer::query()
            ->where('business_id', $business->id)
            ->where(function ($q): void {
                $q->where('is_walk_in', false)->orWhereNull('is_walk_in');
            })
            ->orderBy('name')
            ->get(['id', 'uuid', 'name']);

        $hasCreditPayment = $sale->payments->contains(fn ($p) => $p->method === 'credit');

        return view('admin.sales.show', $this->workspace($request, $business) + [
            'sale' => $sale,
            'customers' => $customers,
            'hasCreditPayment' => $hasCreditPayment,
            'currencySymbol' => $this->currencySymbol($business),
        ]);
    }

    public function updateCustomer(Request $request, Business $business, string $saleUuid): RedirectResponse
    {
        $sale = $this->findSaleOrAbort($business, $saleUuid);

        $data = $request->validate([
            'customer_uuid' => ['nullable', 'uuid'],
        ]);

        try {
            $this->saleVoid->updateCustomer(
                $business,
                $sale,
                $data['customer_uuid'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.b.sales.show', [$business, $sale->uuid])
                ->withErrors(['sale' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.b.sales.show', [$business, $sale->uuid])
                ->withErrors(['sale' => 'Could not update customer on this sale.']);
        }

        return redirect()
            ->route('admin.b.sales.show', [$business, $sale->uuid])
            ->with('status', 'Sale customer updated.');
    }

    public function void(Request $request, Business $business, string $saleUuid): RedirectResponse
    {
        $sale = $this->findSaleOrAbort($business, $saleUuid);

        $request->validate([
            'confirm' => ['accepted'],
        ], [
            'confirm.accepted' => 'Tick the confirmation box to void this sale.',
        ]);

        try {
            $this->saleVoid->void($business, $sale);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.b.sales.show', [$business, $sale->uuid])
                ->withErrors(['sale' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.b.sales.show', [$business, $sale->uuid])
                ->withErrors(['sale' => 'Could not void this sale. Try again or contact support.']);
        }

        return redirect()
            ->route('admin.b.sales.show', [$business, $sale->uuid])
            ->with('status', 'Sale voided. Stock was restored and the sale journal was removed.');
    }

    private function findSaleOrAbort(Business $business, string $saleUuid): Sale
    {
        $sale = Sale::query()
            ->where('business_id', $business->id)
            ->where('uuid', trim($saleUuid))
            ->first();

        if ($sale === null) {
            abort(404);
        }

        return $sale;
    }

    /**
     * @return array{0: array<string, mixed>, 1: Carbon, 2: Carbon}
     */
    private function resolveFilters(Request $request, Business $business): array
    {
        [$from, $to] = $this->reporting->resolveRange(
            $request->query('from'),
            $request->query('to'),
        );

        $locationUuid = trim((string) $request->query('location_uuid', 'all'));
        if ($locationUuid === '') {
            $locationUuid = 'all';
        }

        $locationId = null;
        if (strtolower($locationUuid) !== 'all') {
            $locationId = $business->locations()->where('uuid', $locationUuid)->value('id');
            if ($locationId === null) {
                $locationUuid = 'all';
            }
        }

        $userId = $request->integer('user_id') ?: null;
        if ($userId !== null) {
            $allowed = $this->staffOptions($business)->contains('id', $userId);
            if (! $allowed) {
                $userId = null;
            }
        }

        $status = strtolower(trim((string) $request->query('status', 'all')));
        if (! in_array($status, ['all', 'completed', 'partially_returned', 'returned', 'voided'], true)) {
            $status = 'all';
        }

        $paymentMethod = strtolower(trim((string) $request->query('payment_method', 'all')));
        if (! in_array($paymentMethod, ['all', 'cash', 'transfer', 'pos', 'credit'], true)) {
            $paymentMethod = 'all';
        }

        $groupBy = strtolower(trim((string) $request->query('group_by', 'day')));
        if (! in_array($groupBy, ['day', 'branch', 'none'], true)) {
            $groupBy = 'day';
        }

        $q = trim((string) $request->query('q', ''));

        return [[
            'location_uuid' => $locationUuid,
            'location_id' => $locationId,
            'user_id' => $userId,
            'status' => $status,
            'payment_method' => $paymentMethod,
            'group_by' => $groupBy,
            'q' => $q,
        ], $from, $to];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredSalesQuery(Business $business, array $filters, Carbon $from, Carbon $to): Builder
    {
        $query = Sale::query()
            ->where('business_id', $business->id)
            ->whereBetween('sold_at', [$from, $to]);

        if (! empty($filters['location_id'])) {
            $query->where('location_id', $filters['location_id']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (($filters['status'] ?? 'all') !== 'all') {
            $query->where('status', $filters['status']);
        } else {
            // Default list excludes voided sales so totals stay meaningful.
            $query->where('status', '!=', 'voided');
        }

        if (($filters['payment_method'] ?? 'all') !== 'all') {
            $method = $filters['payment_method'];
            $query->whereHas('payments', static fn ($p) => $p->where('method', $method));
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
            $query->where(function ($q) use ($like): void {
                $q->where('receipt_no', 'like', $like)
                    ->orWhereHas('customer', static fn ($c) => $c->where('name', 'like', $like));
            });
        }

        return $query;
    }

    /**
     * @return Collection<int, User>
     */
    private function staffOptions(Business $business): Collection
    {
        return User::query()
            ->whereHas('businesses', static fn ($q) => $q->where('businesses.id', $business->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    private function currencySymbol(Business $business): string
    {
        return match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };
    }

    /**
     * @param  list<list<null|bool|float|int|string>>  $rows
     */
    private function xlsxDownload(string $filename, array $rows): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lsx');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temp file for Excel.');
        }

        $writer = new XlsxWriter();
        $writer->openToFile($tmp);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }
        $writer->close();

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<list<null|bool|float|int|string>>  $rows
     */
    private function csvDownload(string $filename, array $rows): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'csv');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temp file for CSV.');
        }

        $writer = new CsvWriter();
        $writer->openToFile($tmp);
        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues(array_map(
                static fn ($v) => is_scalar($v) || $v === null ? $v : (string) $v,
                $row
            )));
        }
        $writer->close();

        return response()->download($tmp, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ])->deleteFileAfterSend(true);
    }
}
