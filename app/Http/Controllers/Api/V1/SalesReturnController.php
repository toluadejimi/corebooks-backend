<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Services\SalesReturnService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SalesReturnController extends Controller
{
    public function __construct(
        private readonly SalesReturnService $service,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 30), 1), 100);
        $page = SalesReturn::query()
            ->where('business_id', $business->id)
            ->with(['sale:id,uuid,receipt_no', 'customer:id,uuid,name', 'lines.product:id,uuid,name'])
            ->orderByDesc('returned_at')
            ->paginate($perPage);

        $page->setCollection($page->getCollection()->map(fn (SalesReturn $r) => $this->summary($r)));

        return response()->json($page);
    }

    public function show(Business $business, string $salesReturn): JsonResponse
    {
        $model = $this->findReturnForBusiness($business, $salesReturn);
        if ($model === null) {
            return response()->json(['message' => 'Return not found in this workspace.'], 404);
        }
        $model->load(['sale:id,uuid,receipt_no', 'customer:id,uuid,name', 'lines.product:id,uuid,name']);

        return response()->json(['data' => $this->summary($model)]);
    }

    public function returnableForSale(Business $business, string $sale): JsonResponse
    {
        $saleModel = $this->findSaleForBusiness($business, $sale);
        if ($saleModel === null) {
            return response()->json(['message' => 'Sale not found in this workspace.'], 404);
        }
        $saleModel->load(['lines.product:id,uuid,name', 'returns.lines']);

        $alreadyByLine = [];
        foreach ($saleModel->returns as $r) {
            foreach ($r->lines as $rl) {
                $alreadyByLine[$rl->sale_line_id] = ($alreadyByLine[$rl->sale_line_id] ?? 0) + (float) $rl->qty;
            }
        }

        return response()->json([
            'data' => [
                'uuid' => $saleModel->uuid,
                'receipt_no' => $saleModel->receipt_no,
                'status' => $saleModel->status,
                'sold_at' => $saleModel->sold_at?->toIso8601String(),
                'grand_total' => (float) $saleModel->grand_total,
                'lines' => $saleModel->lines->map(function ($l) use ($alreadyByLine) {
                    $returned = (float) ($alreadyByLine[$l->id] ?? 0);
                    $remaining = round((float) $l->qty - $returned, 3);

                    return [
                        'sale_line_id' => $l->id,
                        'product_uuid' => $l->product?->uuid,
                        'product_name' => $l->product?->name,
                        'qty_sold' => (float) $l->qty,
                        'qty_returned' => $returned,
                        'qty_remaining' => max(0, $remaining),
                        'unit_price' => (float) $l->unit_price,
                        'tax_rate' => (float) $l->tax_rate,
                    ];
                }),
            ],
        ]);
    }

    public function store(Request $request, Business $business, string $sale): JsonResponse
    {
        $saleModel = $this->findSaleForBusiness($business, $sale);
        if ($saleModel === null) {
            return response()->json(['message' => 'Sale not found in this workspace.'], 404);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'refund_method' => ['nullable', 'string', 'in:cash,transfer,pos,store_credit'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.sale_line_id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'numeric', 'min:0.001'],
            'lines.*.restock' => ['nullable', 'boolean'],
        ]);

        try {
            $return = $this->service->process(
                $business,
                $saleModel,
                (int) $request->user()->id,
                $data['lines'],
                $data['reason'] ?? null,
                $data['refund_method'] ?? 'cash',
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $this->summary($return->load(['sale:id,uuid,receipt_no', 'customer:id,uuid,name', 'lines.product:id,uuid,name']))], 201);
    }

    private function findSaleForBusiness(Business $business, string $saleUuid): ?Sale
    {
        try {
            return $business->sales()->where('uuid', $saleUuid)->firstOrFail();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function findReturnForBusiness(Business $business, string $returnUuid): ?SalesReturn
    {
        try {
            return SalesReturn::query()
                ->where('business_id', $business->id)
                ->where('uuid', $returnUuid)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    private function summary(SalesReturn $r): array
    {
        return [
            'uuid' => $r->uuid,
            'returned_at' => $r->returned_at?->toIso8601String(),
            'reason' => $r->reason,
            'refund_method' => $r->refund_method,
            'refund_total' => (float) $r->refund_total,
            'sale' => $r->sale ? [
                'uuid' => $r->sale->uuid,
                'receipt_no' => $r->sale->receipt_no,
            ] : null,
            'customer' => $r->customer ? [
                'uuid' => $r->customer->uuid,
                'name' => $r->customer->name,
            ] : null,
            'lines' => $r->lines->map(fn ($l) => [
                'product_uuid' => $l->product?->uuid,
                'product_name' => $l->product?->name,
                'qty' => (float) $l->qty,
                'unit_price' => (float) $l->unit_price,
                'tax_rate' => (float) $l->tax_rate,
                'refund_amount' => (float) $l->refund_amount,
                'restock' => (bool) $l->restock,
            ]),
        ];
    }
}
