<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BusinessRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\ReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        protected ReportingService $reporting,
    ) {}

    private function locationId(Business $business, Request $request): ?int
    {
        $uuid = $request->query('location_uuid');
        if (! $uuid) {
            return null;
        }

        return $business->locations()->where('uuid', $uuid)->value('id');
    }

    private function membership(Request $request, Business $business): ?object
    {
        return $request->user()->businesses()->where('businesses.id', $business->id)->first();
    }

    private function memberRole(Request $request, Business $business): BusinessRole
    {
        return BusinessRole::normalize($this->membership($request, $business)?->pivot->role);
    }

    /**
     * Sales staff may only call reporting endpoints that include this check,
     * scoped to their pivot-assigned branch.
     */
    private function assertSalesStaffBranchScope(Request $request, Business $business): void
    {
        if ($this->memberRole($request, $business) !== BusinessRole::Sales) {
            return;
        }

        $requestedUuid = $request->query('location_uuid');
        if (! is_string($requestedUuid) || $requestedUuid === '') {
            abort(403, 'Sales reports require your assigned branch.');
        }

        $assignedLocationId = $this->membership($request, $business)?->pivot->location_id;
        if (! $assignedLocationId) {
            abort(403, 'No assigned branch for sales reports.');
        }

        $assignedUuid = (string) $business->locations()->where('id', $assignedLocationId)->value('uuid');

        if ($assignedUuid === '' || strcasecmp($assignedUuid, $requestedUuid) !== 0) {
            abort(403, 'You can only view sales reports for your assigned branch.');
        }
    }

    /** Block P&L, tax, expenses, etc. for floor staff (mobile hides these; API must enforce). */
    private function denySalesBeyondBranchSummaries(Request $request, Business $business): void
    {
        if ($this->memberRole($request, $business) === BusinessRole::Sales) {
            abort(403, 'Sales staff can only access daily and trend sales summaries for their branch.');
        }
    }

    public function dailySales(Request $request, Business $business): JsonResponse
    {
        $this->assertSalesStaffBranchScope($request, $business);

        $date = $request->query('date', now()->toDateString());
        $loc = $this->locationId($business, $request);

        return response()->json($this->reporting->dailySummary($business, $date, $loc));
    }

    public function dashboard(Request $request, Business $business): JsonResponse
    {
        $this->denySalesBeyondBranchSummaries($request, $business);

        $from = now()->subDays(7)->startOfDay();
        $loc = $this->locationId($business, $request);

        return response()->json([
            'weekly_sales' => $this->reporting->weeklyRevenue($business, 7, $loc)->values(),
            'stock_value_estimate' => $this->reporting->stockValuation($business, $loc),
            'inventory_availability' => $this->reporting->inventoryAvailabilityTotals($business, $loc),
            'top_products' => $this->reporting->topProductsByUnits($business, $from, 5, $loc)->values(),
        ]);
    }

    public function timeseries(Request $request, Business $business): JsonResponse
    {
        $this->assertSalesStaffBranchScope($request, $business);

        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $loc = $this->locationId($business, $request);
        $rows = $this->reporting->timeseries($business, $from, $to, $loc);

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'series' => $rows->map(fn ($r) => [
                'date' => $r->date,
                'orders' => $r->orders,
                'revenue' => $r->revenue,
                'tax_total' => $r->tax_total,
            ])->values(),
        ]);
    }

    public function profitLoss(Request $request, Business $business): JsonResponse
    {
        $this->denySalesBeyondBranchSummaries($request, $business);

        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $loc = $this->locationId($business, $request);

        return response()->json($this->reporting->profitAndLoss($business, $from, $to, $loc));
    }

    public function products(Request $request, Business $business): JsonResponse
    {
        $this->denySalesBeyondBranchSummaries($request, $business);

        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $loc = $this->locationId($business, $request);

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'products' => $this->reporting->productPerformance($business, $from, $to, $loc)->values(),
            'inventory_availability' => $this->reporting->inventoryAvailabilityTotals($business, $loc),
        ]);
    }

    public function payments(Request $request, Business $business): JsonResponse
    {
        $this->denySalesBeyondBranchSummaries($request, $business);

        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $loc = $this->locationId($business, $request);

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'by_method' => $this->reporting->paymentMix($business, $from, $to, $loc)->values(),
        ]);
    }

    public function firs(Request $request, Business $business): JsonResponse
    {
        $this->denySalesBeyondBranchSummaries($request, $business);

        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $loc = $this->locationId($business, $request);

        return response()->json($this->reporting->firsComplianceReport($business, $from, $to, $loc));
    }

    /** Detailed sales listing for business owners only (filters: branch / staff / team segment). */
    public function salesLedger(Request $request, Business $business): JsonResponse
    {
        $pivotRole = BusinessRole::normalize(
            $request->user()->businesses()->where('businesses.id', $business->id)->first()?->pivot->role
        );

        abort_unless(
            $pivotRole === BusinessRole::Owner,
            403,
            'Only the business owner can view this sales report.'
        );

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'location_uuid' => ['nullable', 'uuid'],
            'seller_user_id' => ['nullable', 'integer', 'min:1'],
            'team_segment' => ['nullable', 'string', 'in:all,sales,management'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:500'],
        ]);

        [$from, $to] = $this->reporting->resolveRange(
            $validated['from'] ?? $request->query('from'),
            $validated['to'] ?? $request->query('to')
        );

        $locUuid = $validated['location_uuid'] ?? null;
        $locationId = null;
        if ($locUuid) {
            $locationId = $business->locations()->where('uuid', $locUuid)->value('id');
        }

        $sellerId = $request->filled('seller_user_id') ? (int) $validated['seller_user_id'] : null;
        $segment = strtolower((string) ($validated['team_segment'] ?? 'all'));

        $page = max(1, (int) ($validated['page'] ?? $request->query('page', 1)));
        $perPage = (int) ($validated['per_page'] ?? $request->query('per_page', 50));

        $teamForService = $sellerId !== null ? null : ($segment === 'all' ? null : $segment);

        return response()->json($this->reporting->salesLedger(
            $business,
            $from,
            $to,
            $locationId,
            $sellerId,
            $teamForService,
            $page,
            $perPage,
        ));
    }

    public function expenses(Request $request, Business $business): JsonResponse
    {
        $this->denySalesBeyondBranchSummaries($request, $business);

        [$from, $to] = $this->reporting->resolveRange($request->query('from'), $request->query('to'));
        $loc = $this->locationId($business, $request);
        $r = $this->reporting->expenseReport($business, $from, $to, $loc);

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'total' => $r['total'],
            'by_category' => $r['by_category']->toArray(),
            'lines' => $r['lines']->map(fn ($e) => [
                'uuid' => $e->uuid,
                'category' => $e->category,
                'amount' => (float) $e->amount,
                'notes' => $e->notes,
                'paid_at' => $e->paid_at?->toIso8601String(),
                'created_at' => $e->created_at?->toIso8601String(),
                'location_uuid' => $e->location?->uuid,
                'location_name' => $e->location?->name,
            ])->values(),
        ]);
    }
}
