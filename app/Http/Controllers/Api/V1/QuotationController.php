<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\SendQuotationPdf;
use App\Models\Business;
use App\Models\Quotation;
use App\Models\QuotationLine;
use App\Services\PdfRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuotationController extends Controller
{
    public function __construct(
        private readonly PdfRenderer $pdf,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 30), 1), 100);
        $query = Quotation::query()->where('business_id', $business->id)->orderByDesc('id');
        $paginator = $query->paginate($perPage);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Quotation $q) => $this->quotationSummary($q))
        );

        return response()->json($paginator);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $this->validatedPayload($request);

        $quotation = DB::transaction(function () use ($business, $data): Quotation {
            $number = $this->allocateQuotationNumber($business);
            $q = Quotation::query()->create([
                'business_id' => $business->id,
                'number' => $number,
                'client_name' => $data['client_name'],
                'client_email' => $data['client_email'] ?? null,
                'client_phone' => $data['client_phone'] ?? null,
                'client_address' => $data['client_address'] ?? null,
                'status' => 'draft',
                'valid_until' => $data['valid_until'] ?? null,
                'notes' => $data['notes'] ?? null,
                'currency' => strtoupper((string) ($data['currency'] ?? $business->currency ?? 'NGN')),
                'subtotal_ex_vat' => 0,
                'vat_total' => 0,
                'grand_total' => 0,
                'version' => 1,
            ]);
            $this->replaceLines($q, $data['lines'], $business);
            $this->recalculateTotals($q);

            return $q->fresh(['lines']);
        });

        return response()->json(['data' => $this->quotationDetail($quotation)], 201);
    }

    public function show(Business $business, Quotation $quotation): JsonResponse
    {
        $this->assertBelongs($business, $quotation);

        return response()->json(['data' => $this->quotationDetail($quotation)]);
    }

    public function update(Request $request, Business $business, Quotation $quotation): JsonResponse
    {
        $this->assertBelongs($business, $quotation);
        $data = $this->validatedPayload($request);

        if (isset($data['client_version']) && (int) $data['client_version'] !== (int) $quotation->version) {
            return response()->json([
                'message' => 'Version conflict',
                'server' => $this->quotationDetail($quotation->fresh(['lines'])),
            ], 409);
        }

        DB::transaction(function () use ($quotation, $business, $data): void {
            foreach (['client_name', 'client_email', 'client_phone', 'client_address', 'valid_until', 'notes', 'status'] as $key) {
                if (array_key_exists($key, $data)) {
                    $quotation->{$key} = $data[$key];
                }
            }
            if (array_key_exists('currency', $data)) {
                $quotation->currency = strtoupper((string) $data['currency']);
            }
            if (array_key_exists('lines', $data)) {
                $this->replaceLines($quotation, $data['lines'], $business);
            }
            $quotation->version = (int) $quotation->version + 1;
            $quotation->save();
            $this->recalculateTotals($quotation->fresh(['lines']));
        });

        return response()->json(['data' => $this->quotationDetail($quotation->fresh(['lines']))]);
    }

    public function destroy(Business $business, Quotation $quotation): JsonResponse
    {
        $this->assertBelongs($business, $quotation);
        $quotation->delete();

        return response()->json(['ok' => true]);
    }

    public function pdf(Business $business, Quotation $quotation): BinaryFileResponse
    {
        $this->assertBelongs($business, $quotation);
        $quotation->load(['lines', 'business']);
        $html = View::make('pdf.quotation', [
            'business' => $business,
            'quotation' => $quotation,
            'sym' => $this->currencySymbol($quotation->currency),
        ])->render();

        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $quotation->number) ?? 'quotation';

        return $this->pdf->downloadResponse($html, $safe.'.pdf');
    }

    public function email(Request $request, Business $business, Quotation $quotation): JsonResponse
    {
        $this->assertBelongs($business, $quotation);
        $data = $request->validate([
            'to' => ['required', 'email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $quotation->load(['lines', 'business']);
        $html = View::make('pdf.quotation', [
            'business' => $business,
            'quotation' => $quotation,
            'sym' => $this->currencySymbol($quotation->currency),
        ])->render();
        $binary = $this->pdf->renderBinary($html);

        Mail::to($data['to'])->send(new SendQuotationPdf(
            $business,
            $quotation,
            $binary,
            $data['message'] ?? null,
        ));

        $quotation->status = 'sent';
        $quotation->save();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:64'],
            'client_address' => ['nullable', 'string', 'max:2000'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'currency' => ['nullable', 'string', 'max:8'],
            'status' => ['nullable', 'string', 'in:draft,sent,accepted,declined,expired'],
            'client_version' => ['nullable', 'integer', 'min:1'],
            'lines' => ['required', 'array', 'min:1', 'max:50'],
            'lines.*.description' => ['required_with:lines', 'string', 'max:512'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'min:0.0001', 'max:999999'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0', 'max:999999999'],
            'lines.*.vat_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
    }

    private function allocateQuotationNumber(Business $business): string
    {
        $prefix = 'QT-'.now()->format('Y').'-';
        $last = Quotation::query()
            ->where('business_id', $business->id)
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $next = 1;
        if (is_string($last) && str_starts_with($last, $prefix)) {
            $suffix = substr($last, strlen($prefix));
            $next = max(1, (int) $suffix + 1);
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function replaceLines(Quotation $quotation, array $lines, Business $business): void
    {
        $quotation->lines()->delete();
        $defaultVat = (float) ($business->default_vat_rate ?? 0);
        foreach ($lines as $idx => $line) {
            $qty = (float) $line['quantity'];
            $unit = (float) $line['unit_price'];
            $vatP = array_key_exists('vat_percent', $line) && $line['vat_percent'] !== null && $line['vat_percent'] !== ''
                ? (float) $line['vat_percent']
                : $defaultVat;
            $sub = round($qty * $unit, 2);
            $vat = round($sub * ($vatP / 100), 2);
            $total = round($sub + $vat, 2);
            QuotationLine::query()->create([
                'quotation_id' => $quotation->id,
                'sort_order' => $idx,
                'description' => (string) $line['description'],
                'quantity' => $qty,
                'unit_price' => $unit,
                'vat_percent' => $vatP,
                'line_subtotal_ex_vat' => $sub,
                'line_vat' => $vat,
                'line_total' => $total,
            ]);
        }
    }

    private function recalculateTotals(Quotation $quotation): void
    {
        $quotation->load('lines');
        $sub = (float) $quotation->lines->sum('line_subtotal_ex_vat');
        $vat = (float) $quotation->lines->sum('line_vat');
        $grand = (float) $quotation->lines->sum('line_total');
        $quotation->subtotal_ex_vat = round($sub, 2);
        $quotation->vat_total = round($vat, 2);
        $quotation->grand_total = round($grand, 2);
        $quotation->save();
    }

    private function assertBelongs(Business $business, Quotation $quotation): void
    {
        abort_if((int) $quotation->business_id !== (int) $business->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationSummary(Quotation $q): array
    {
        return [
            'uuid' => $q->uuid,
            'number' => $q->number,
            'client_name' => $q->client_name,
            'client_email' => $q->client_email,
            'status' => $q->status,
            'grand_total' => (float) $q->grand_total,
            'currency' => $q->currency,
            'valid_until' => $q->valid_until?->toDateString(),
            'version' => (int) $q->version,
            'updated_at' => $q->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function quotationDetail(Quotation $q): array
    {
        $q->load('lines');

        return [
            'uuid' => $q->uuid,
            'number' => $q->number,
            'client_name' => $q->client_name,
            'client_email' => $q->client_email,
            'client_phone' => $q->client_phone,
            'client_address' => $q->client_address,
            'status' => $q->status,
            'valid_until' => $q->valid_until?->toDateString(),
            'notes' => $q->notes,
            'currency' => $q->currency,
            'subtotal_ex_vat' => (float) $q->subtotal_ex_vat,
            'vat_total' => (float) $q->vat_total,
            'grand_total' => (float) $q->grand_total,
            'version' => (int) $q->version,
            'updated_at' => $q->updated_at?->toIso8601String(),
            'lines' => $q->lines->map(fn (QuotationLine $l) => [
                'sort_order' => (int) $l->sort_order,
                'description' => $l->description,
                'quantity' => (float) $l->quantity,
                'unit_price' => (float) $l->unit_price,
                'vat_percent' => $l->vat_percent !== null ? (float) $l->vat_percent : null,
                'line_subtotal_ex_vat' => (float) $l->line_subtotal_ex_vat,
                'line_vat' => (float) $l->line_vat,
                'line_total' => (float) $l->line_total,
            ])->values()->all(),
        ];
    }

    private function currencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'NGN' => '₦',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $currency.' ',
        };
    }
}
