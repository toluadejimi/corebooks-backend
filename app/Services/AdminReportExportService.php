<?php

namespace App\Services;

use App\Enums\BusinessRole;
use App\Models\Business;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AdminReportExportService
{
    public function __construct(
        protected ReportingService $reporting,
    ) {}

    protected function sym(Business $business): string
    {
        return match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };
    }

    protected function locationId(Request $request, Business $business): ?int
    {
        $rawLoc = $request->query('location_uuid');
        if ($rawLoc === null || $rawLoc === '' || strtolower((string) $rawLoc) === 'all') {
            return null;
        }

        return $business->locations()->where('uuid', $rawLoc)->value('id');
    }

    protected function ledgerTeam(Request $request): string
    {
        $ledgerTeam = strtolower((string) $request->query('ledger_team', 'all'));

        return in_array($ledgerTeam, ['all', 'sales', 'management'], true) ? $ledgerTeam : 'all';
    }

    public function download(Request $request, Business $business, BusinessRole $memberRole, string $report, string $format): BinaryFileResponse
    {
        $report = strtolower($report);
        $format = strtolower($format);

        if ($report === 'ledger' && $memberRole !== BusinessRole::Owner) {
            abort(403);
        }

        if (! in_array($format, ['pdf', 'xlsx'], true)) {
            abort(404);
        }

        return match ($report) {
            'daily' => $this->exportDaily($request, $business, $format),
            'trends' => $this->exportTrends($request, $business, $format),
            'pnl' => $this->exportPnl($request, $business, $format),
            'products' => $this->exportProducts($request, $business, $format),
            'payments' => $this->exportPayments($request, $business, $format),
            'expenses' => $this->exportExpenses($request, $business, $format),
            'firs' => $this->exportFirs($request, $business, $format),
            'ledger' => $this->exportLedger($request, $business, $format),
            default => abort(404),
        };
    }

    /**
     * @param  list<null|bool|float|int|string>  $cells
     */
    protected function normalizeCells(array $cells): array
    {
        return array_map(function (mixed $value): null|bool|float|int|string {
            if ($value === null || is_bool($value) || is_int($value) || is_float($value) || is_string($value)) {
                return $value;
            }
            if ($value instanceof \Stringable) {
                return (string) $value;
            }

            return (string) $value;
        }, $cells);
    }

    protected function pdfDownload(string $html, string $filename): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'pdf');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temp file for PDF.');
        }

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        file_put_contents($tmp, $dompdf->output());

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/pdf',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<list<null|bool|float|int|string>>  $lines
     */
    protected function xlsxDownload(string $filename, array $lines): BinaryFileResponse
    {
        $tmp = tempnam(sys_get_temp_dir(), 'lsx');
        if ($tmp === false) {
            throw new \RuntimeException('Could not create temp file for Excel.');
        }

        $writer = new Writer();
        $writer->openToFile($tmp);
        foreach ($lines as $line) {
            $writer->addRow(Row::fromValues($this->normalizeCells($line)));
        }
        $writer->close();

        return response()->download($tmp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string|int|float>>  $rows
     * @param  array<int, bool>  $numericCols
     */
    protected function tablePdf(string $title, string $subtitle, array $headers, array $rows, array $numericCols = [], ?string $footerNote = null): string
    {
        return View::make('admin.reports.export.table-pdf', [
            'title' => $title,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
            'numericCols' => $numericCols,
            'footerNote' => $footerNote,
        ])->render();
    }

    protected function exportDaily(Request $request, Business $business, string $format): BinaryFileResponse
    {
        $dailyDate = $request->query('date', now()->toDateString());
        $locId = $this->locationId($request, $business);
        $daily = $this->reporting->dailySummary($business, $dailyDate, $locId);
        $stockVal = $this->reporting->stockValuation($business, $locId);
        $inv = $this->reporting->inventoryAvailabilityTotals($business, $locId);
        $sym = $this->sym($business);

        $headers = ['Metric', 'Value'];
        $rows = [
            ['Report date', $daily['date']],
            ['Orders', (string) $daily['orders']],
            ['Revenue', $sym.number_format($daily['revenue'], 2)],
            ['Tax total', $sym.number_format($daily['tax_total'], 2)],
            ['Discounts', $sym.number_format($daily['discount_total'], 2)],
            ['Units sold', number_format($daily['items_sold'], 2)],
            ['Avg order value', $sym.number_format($daily['avg_order_value'], 2)],
            ['Stock valuation (cost)', $sym.number_format($stockVal, 2)],
            ['SKUs in stock (on hand)', (string) $inv['products_with_stock']],
            ['Units on hand', number_format($inv['units_on_hand'], 2)],
            ['Inventory cost value (est.)', $sym.number_format($inv['cost_value_estimate'], 2)],
            ['Inventory retail value (list)', $sym.number_format($inv['retail_value_estimate'], 2)],
        ];
        $subtitle = $business->name.' · '.$dailyDate;

        $fname = 'daily-sales-'.$dailyDate;

        if ($format === 'pdf') {
            $html = $this->tablePdf('Daily sales snapshot', $subtitle, $headers, $rows, [1 => true], null);

            return $this->pdfDownload($html, $fname.'.pdf');
        }

        $lines = [$headers];
        foreach ($rows as $r) {
            $lines[] = [$r[0], strip_tags((string) $r[1])];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }

    protected function exportTrends(Request $request, Business $business, string $format): BinaryFileResponse
    {
        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $series = $this->reporting->timeseries($business, $from, $to, $this->locationId($request, $business));
        $sym = $this->sym($business);

        $headers = ['Date', 'Orders', 'Revenue', 'Tax'];
        $rows = [];
        foreach ($series as $row) {
            $rows[] = [
                $row->date,
                (string) $row->orders,
                $sym.number_format($row->revenue, 2),
                $sym.number_format($row->tax_total, 2),
            ];
        }
        $subtitle = $business->name.' · '.$from->toDateString().' → '.$to->toDateString();

        $fname = 'sales-by-day-'.$from->toDateString().'_to_'.$to->toDateString();

        if ($format === 'pdf') {
            $html = $this->tablePdf('Sales by day', $subtitle, $headers, $rows, [1 => true, 2 => true, 3 => true]);

            return $this->pdfDownload($html, $fname.'.pdf');
        }

        $lines = [$headers];
        foreach ($series as $row) {
            $lines[] = [$row->date, $row->orders, $row->revenue, $row->tax_total];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }

    protected function exportPnl(Request $request, Business $business, string $format): BinaryFileResponse
    {
        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $locId = $this->locationId($request, $business);
        $pnl = $this->reporting->profitAndLoss($business, $from, $to, $locId);
        $breakdown = $this->reporting->profitLossCogsBreakdown($business, $from, $to, $locId);
        $sym = $this->sym($business);

        $headers = ['Line', 'Amount'];
        $rows = [
            ['Revenue', $sym.number_format($pnl['revenue'], 2)],
            ['Tax collected', $sym.number_format($pnl['tax_collected'], 2)],
            ['Discounts', $sym.number_format($pnl['discounts'], 2)],
            ['COGS (est.)', $sym.number_format($pnl['cogs_estimate'], 2)],
            ['Gross profit', $sym.number_format($pnl['gross_profit'], 2)],
            ['Expenses', $sym.number_format($pnl['expenses'], 2)],
            ['Net profit', $sym.number_format($pnl['net_profit'], 2)],
            ['Orders (count)', (string) $pnl['orders']],
        ];
        $subtitle = $business->name.' · '.$pnl['period']['from'].' → '.$pnl['period']['to'];
        $fname = 'pnl-'.$from->toDateString().'_to_'.$to->toDateString();

        $bdHeaders = ['Product', 'Qty sold', 'Revenue (incl. VAT)', 'Sell/unit (ex VAT)', 'Cost/unit (est.)', 'COGS (est.)', 'Margin vs COGS'];
        $bdRows = [];
        foreach ($breakdown as $b) {
            $bdRows[] = [
                $b['name'],
                number_format($b['units_sold'], 3),
                $sym.number_format($b['revenue_line_total'], 2),
                $sym.number_format($b['avg_sell_pre_tax'], 2),
                $sym.number_format($b['unit_cost_weighted'], 2),
                $sym.number_format($b['cogs_estimate'], 2),
                $sym.number_format($b['margin_line_vs_cogs'], 2),
            ];
        }

        $breakdownHint = 'Unit cost uses batch snapshot when available, else current catalog cost — same basis as P&amp;L COGS. Margin = sum of line totals (incl. VAT) minus COGS (not statutory gross margin).';

        if ($format === 'pdf') {
            $html = View::make('admin.reports.export.pnl-pdf', [
                'title' => 'Profit & loss',
                'subtitle' => $subtitle,
                'summaryHeaders' => $headers,
                'summaryRows' => $rows,
                'summaryNumericCols' => [1 => true],
                'breakdownTitle' => 'COGS drill-down by product',
                'breakdownHint' => $breakdownHint,
                'breakdownHeaders' => $bdHeaders,
                'breakdownRows' => $bdRows,
                'breakdownNumericCols' => [1 => true, 2 => true, 3 => true, 4 => true, 5 => true, 6 => true],
                'footerNote' => null,
            ])->render();

            return $this->pdfDownload($html, $fname.'.pdf');
        }

        $lines = [
            $headers,
            ['Revenue', $pnl['revenue']],
            ['Tax collected', $pnl['tax_collected']],
            ['Discounts', $pnl['discounts']],
            ['COGS (est.)', $pnl['cogs_estimate']],
            ['Gross profit', $pnl['gross_profit']],
            ['Expenses', $pnl['expenses']],
            ['Net profit', $pnl['net_profit']],
            ['Orders (count)', $pnl['orders']],
            [''],
            ['COGS drill-down by product'],
            [''],
            $bdHeaders,
        ];
        foreach ($breakdown as $b) {
            $lines[] = [
                $b['name'],
                $b['units_sold'],
                $b['revenue_line_total'],
                $b['avg_sell_pre_tax'],
                $b['unit_cost_weighted'],
                $b['cogs_estimate'],
                $b['margin_line_vs_cogs'],
            ];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }

    protected function exportProducts(Request $request, Business $business, string $format): BinaryFileResponse
    {
        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $locId = $this->locationId($request, $business);
        $products = $this->reporting->productPerformance($business, $from, $to, $locId);
        $inv = $this->reporting->inventoryAvailabilityTotals($business, $locId);
        $sym = $this->sym($business);

        $headers = ['Product', 'Units sold', 'Revenue', 'COGS (est.)', 'Margin (est.)'];
        $rows = [];
        foreach ($products as $p) {
            $rows[] = [
                $p['name'],
                number_format($p['units_sold'], 2),
                $sym.number_format($p['revenue'], 2),
                $sym.number_format($p['cogs_estimate'], 2),
                $sym.number_format($p['margin_estimate'], 2),
            ];
        }
        $subtitle = $business->name.' · '.$from->toDateString().' → '.$to->toDateString();
        $fname = 'products-'.$from->toDateString().'_to_'.$to->toDateString();

        if ($format === 'pdf') {
            $invHeaders = ['Metric', 'Value'];
            $invRows = [
                ['SKUs in stock', (string) $inv['products_with_stock']],
                ['Units on hand', number_format($inv['units_on_hand'], 2)],
                ['Cost value (est.)', $sym.number_format($inv['cost_value_estimate'], 2)],
                ['Retail value (list)', $sym.number_format($inv['retail_value_estimate'], 2)],
            ];
            $cogsNote = 'COGS uses batch cost when present; values far above the line unit sell price are capped at that price for reporting.';

            $html = View::make('admin.reports.export.products-pdf', [
                'subtitle' => $subtitle,
                'invHeaders' => $invHeaders,
                'invRows' => $invRows,
                'invNumericCols' => [1 => true],
                'perfHeaders' => $headers,
                'perfRows' => $rows,
                'perfNumericCols' => [1 => true, 2 => true, 3 => true, 4 => true],
                'cogsNote' => $cogsNote,
            ])->render();

            return $this->pdfDownload($html, $fname.'.pdf');
        }

        $lines = [
            ['Current inventory (on hand)'],
            ['Metric', 'Value'],
            ['SKUs in stock', $inv['products_with_stock']],
            ['Units on hand', $inv['units_on_hand']],
            ['Cost value (est.)', $inv['cost_value_estimate']],
            ['Retail value (list)', $inv['retail_value_estimate']],
            [''],
            ['Product performance', $from->toDateString().' → '.$to->toDateString()],
            [''],
            $headers,
        ];
        foreach ($products as $p) {
            $lines[] = [$p['name'], $p['units_sold'], $p['revenue'], $p['cogs_estimate'], $p['margin_estimate']];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }

    protected function exportPayments(Request $request, Business $business, string $format): BinaryFileResponse
    {
        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $payments = $this->reporting->paymentMix($business, $from, $to, $this->locationId($request, $business));
        $sym = $this->sym($business);

        $headers = ['Method', 'Transactions', 'Total'];
        $rows = [];
        foreach ($payments as $pay) {
            $rows[] = [
                strtoupper((string) $pay['method']),
                (string) $pay['transactions'],
                $sym.number_format($pay['total'], 2),
            ];
        }
        $subtitle = $business->name.' · '.$from->toDateString().' → '.$to->toDateString();
        $fname = 'payments-'.$from->toDateString().'_to_'.$to->toDateString();

        if ($format === 'pdf') {
            return $this->pdfDownload($this->tablePdf('Payments by method', $subtitle, $headers, $rows, [1 => true, 2 => true]), $fname.'.pdf');
        }

        $lines = [$headers];
        foreach ($payments as $pay) {
            $lines[] = [strtoupper((string) $pay['method']), $pay['transactions'], $pay['total']];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }

    protected function exportExpenses(Request $request, Business $business, string $format): BinaryFileResponse
    {
        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $expenseReport = $this->reporting->expenseReport($business, $from, $to, $this->locationId($request, $business));
        $sym = $this->sym($business);

        $headers = ['Date', 'Category', 'Amount', 'Notes'];
        $rows = [];
        foreach ($expenseReport['lines'] as $e) {
            $rows[] = [
                ($e->paid_at ?? $e->created_at)?->format('Y-m-d') ?? '—',
                $e->category ?: '—',
                $sym.number_format((float) $e->amount, 2),
                mb_substr((string) ($e->notes ?? ''), 0, 180),
            ];
        }
        $subtitle = $business->name.' · '.$from->toDateString().' → '.$to->toDateString().
            ' · Period total: '.$sym.number_format($expenseReport['total'], 2);
        $fname = 'expenses-'.$from->toDateString().'_to_'.$to->toDateString();

        if ($format === 'pdf') {
            return $this->pdfDownload($this->tablePdf('Expense lines', $subtitle, $headers, $rows, [2 => true]), $fname.'.pdf');
        }

        $lines = [$headers];
        foreach ($expenseReport['lines'] as $e) {
            $lines[] = [
                ($e->paid_at ?? $e->created_at)?->format('Y-m-d') ?? '',
                $e->category ?: '',
                round((float) $e->amount, 2),
                (string) ($e->notes ?? ''),
            ];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }

    protected function exportFirs(Request $request, Business $business, string $format): BinaryFileResponse
    {
        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $firs = $this->reporting->firsComplianceReport($business, $from, $to, $this->locationId($request, $business));
        $sym = $this->sym($business);

        $headers = ['Field', 'Value'];
        $rows = [
            ['Legal name', $firs['taxpayer']['legal_name']],
            ['TIN', $firs['taxpayer']['tax_identification_number'] ?: '—'],
            ['Transactions', (string) $firs['summary']['transaction_count']],
            ['Gross sales (incl. VAT)', $sym.number_format($firs['summary']['gross_sales_vat_inclusive'], 2)],
            ['Output VAT', $sym.number_format($firs['summary']['output_vat_collected_on_sales'], 2)],
            ['Est. net taxable (ex VAT)', $sym.number_format($firs['summary']['estimated_net_taxable_turnover_ex_vat'], 2)],
        ];

        $fname = 'firs-'.$from->toDateString().'_to_'.$to->toDateString();

        $summaryRows = array_map(fn ($r) => [$r[0], is_string($r[1]) ? strip_tags($r[1]) : (string) $r[1]], $rows);
        $rateRows = [];
        foreach ($firs['by_vat_rate'] as $r) {
            $rateRows[] = [
                number_format((float) $r['vat_rate_percent'], 2),
                $sym.number_format($r['supply_value_exclusive_vat'], 2),
                $sym.number_format($r['vat_amount'], 2),
            ];
        }

        if ($format === 'pdf') {
            $html = View::make('admin.reports.export.firs-pdf', [
                'subtitle' => $business->name.' · '.$firs['period']['from'].' → '.$firs['period']['to']."\n\n".$firs['disclaimer'],
                'summaryRows' => $summaryRows,
                'rateRows' => $rateRows,
            ])->render();

            return $this->pdfDownload($html, $fname.'.pdf');
        }

        $lines = [
            ['Summary'],
            ['Field', 'Value'],
            ...array_map(fn ($r) => [$r[0], $r[1]], $summaryRows),
            [''],
            ['By VAT rate'],
            ['VAT rate %', 'Supply ex VAT', 'VAT amount'],
        ];
        foreach ($firs['by_vat_rate'] as $r) {
            $lines[] = [
                round((float) $r['vat_rate_percent'], 4),
                round((float) $r['supply_value_exclusive_vat'], 2),
                round((float) $r['vat_amount'], 2),
            ];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }

    protected function exportLedger(Request $request, Business $business, string $format): BinaryFileResponse
    {
        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $ledger = $this->reporting->salesLedger(
            $business,
            $from,
            $to,
            $this->locationId($request, $business),
            null,
            $this->ledgerTeam($request),
            1,
            5000,
            'ledger_page',
        );
        $sym = $this->sym($business);

        $headers = ['Receipt', 'When', 'Branch', 'Seller', 'Total'];
        $rows = [];
        foreach ($ledger['sales'] as $s) {
            $rows[] = [
                $s['receipt_no'],
                Carbon::parse($s['sold_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                $s['location_name'] ?? '—',
                $s['seller_name'] ?? '—',
                $sym.number_format($s['grand_total'], 2),
            ];
        }

        $sum = $ledger['summary'];
        $shown = count($ledger['sales']);
        $totalRows = (int) ($ledger['pagination']['total'] ?? $shown);
        $subtitle = $business->name.' · '.$ledger['period']['from'].' → '.$ledger['period']['to'].
            ' · '.$sum['transaction_count'].' transactions · Total '.$sym.number_format($sum['grand_total'], 2).
            ' (Tax '.$sym.number_format($sum['tax_total'], 2).')'.
            ' · Exported '.$shown.' of '.$totalRows.' ledger rows (max 5000 per export).';

        $footerNote = $shown < $totalRows
            ? 'Export is truncated. Narrow the date range or branch filter and export again to capture remaining rows.'
            : null;

        $fname = 'sales-ledger-'.$from->toDateString().'_to_'.$to->toDateString();

        if ($format === 'pdf') {
            return $this->pdfDownload(
                $this->tablePdf('Sales ledger', $subtitle, $headers, $rows, [4 => true], $footerNote),
                $fname.'.pdf'
            );
        }

        $lines = [
            ['Summary'],
            ['Transactions', (string) $sum['transaction_count']],
            ['Grand total', $sum['grand_total']],
            ['Tax total', $sum['tax_total']],
            [''],
            $headers,
        ];
        foreach ($ledger['sales'] as $s) {
            $lines[] = [
                $s['receipt_no'],
                Carbon::parse($s['sold_at'])->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                $s['location_name'] ?? '',
                $s['seller_name'] ?? '',
                $s['grand_total'],
            ];
        }

        return $this->xlsxDownload($fname.'.xlsx', $lines);
    }
}
