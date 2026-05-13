<?php

namespace App\Http\Controllers\Web\Admin;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Services\AdminReportExportService;
use App\Services\ReportingService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportsWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        protected ReportingService $reporting,
        protected AdminReportExportService $reportExports,
    ) {}

    public function index(Request $request, Business $business): View
    {
        /** @var BusinessRole $memberRole */
        $memberRole = $request->attributes->get('business_role');
        $showLedger = $memberRole === BusinessRole::Owner;

        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $dailyDate = $request->query('date', now()->toDateString());

        $locations = $business->locations()->orderBy('name')->get(['id', 'uuid', 'name']);

        $rawLoc = $request->query('location_uuid');
        $locationId = null;
        if ($rawLoc !== null && $rawLoc !== '' && strtolower((string) $rawLoc) !== 'all') {
            $locationId = $business->locations()->where('uuid', $rawLoc)->value('id');
        }

        $daily = $this->reporting->dailySummary($business, $dailyDate, $locationId);
        $series = $this->reporting->timeseries($business, $from, $to, $locationId);
        $pnl = $this->reporting->profitAndLoss($business, $from, $to, $locationId);
        $pnlCogsBreakdown = $this->reporting->profitLossCogsBreakdown($business, $from, $to, $locationId);
        $products = $this->reporting->productPerformance($business, $from, $to, $locationId);
        $payments = $this->reporting->paymentMix($business, $from, $to, $locationId);
        $expenseReport = $this->reporting->expenseReport($business, $from, $to, $locationId);
        $stockValuation = $this->reporting->stockValuation($business, $locationId);
        $inventoryAvailability = $this->reporting->inventoryAvailabilityTotals($business, $locationId);
        $customerCredit = $this->reporting->customerCreditSummary($business);
        $firs = $this->reporting->firsComplianceReport($business, $from, $to, $locationId);

        $ledgerTeam = strtolower((string) $request->query('ledger_team', 'all'));
        if (! in_array($ledgerTeam, ['all', 'sales', 'management'], true)) {
            $ledgerTeam = 'all';
        }

        $ledger = null;
        if ($showLedger) {
            $ledgerPage = max(1, (int) $request->query('ledger_page', 1));
            $ledger = $this->reporting->salesLedger(
                $business,
                $from,
                $to,
                $locationId,
                null,
                $ledgerTeam,
                $ledgerPage,
                25,
                'ledger_page',
            );
        }

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.reports.index', $this->workspace($request, $business) + [
            'exportQuery' => http_build_query($request->query()),
            'from' => $from,
            'to' => $to,
            'dailyDate' => $dailyDate,
            'daily' => $daily,
            'series' => $series,
            'pnl' => $pnl,
            'pnlCogsBreakdown' => $pnlCogsBreakdown,
            'products' => $products,
            'payments' => $payments,
            'expenseReport' => $expenseReport,
            'stockValuation' => $stockValuation,
            'inventoryAvailability' => $inventoryAvailability,
            'customerCredit' => $customerCredit,
            'firs' => $firs,
            'locations' => $locations,
            'selectedLocationUuid' => $rawLoc && strtolower((string) $rawLoc) !== 'all' ? (string) $rawLoc : '',
            'scopeAllLocations' => $rawLoc === null || $rawLoc === '' || strtolower((string) $rawLoc) === 'all',
            'ledger' => $ledger,
            'ledgerTeam' => $ledgerTeam,
            'showLedger' => $showLedger,
            'currencySymbol' => $currencySymbol,
        ]);
    }

    public function export(Request $request, Business $business, string $report, string $format): BinaryFileResponse
    {
        /** @var \App\Enums\BusinessRole $memberRole */
        $memberRole = $request->attributes->get('business_role');

        return $this->reportExports->download($request, $business, $memberRole, $report, $format);
    }
}
