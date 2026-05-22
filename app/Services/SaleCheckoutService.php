<?php

namespace App\Services;

use App\Models\Business;
use App\Models\Customer;
use App\Models\CustomerCreditEntry;
use App\Models\GlAccount;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SaleCheckoutService
{
    public function __construct(
        private readonly GeneralLedgerService $ledger,
        private readonly FundAccountService $fundAccounts,
    ) {}

    public function checkout(
        Business $business,
        int $userId,
        string $locationUuid,
        array $lines,
        array $payments,
        ?string $idempotencyKey,
        float $discountTotal = 0,
        ?string $customerUuid = null,
        ?string $soldAtDate = null,
    ): Sale {
        return DB::transaction(function () use ($business, $userId, $locationUuid, $lines, $payments, $idempotencyKey, $discountTotal, $customerUuid, $soldAtDate) {
            if ($idempotencyKey) {
                $existing = Sale::query()
                    ->where('business_id', $business->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing) {
                    return $existing->load(['lines.product', 'payments.glAccount', 'customer']);
                }
            }

            $location = $business->locations()->where('uuid', $locationUuid)->firstOrFail();
            $customer = $this->resolveCustomer($business, $customerUuid);

            $soldAt = $soldAtDate !== null && $soldAtDate !== ''
                ? Carbon::createFromFormat('Y-m-d', $soldAtDate, config('app.timezone'))->startOfDay()
                : now();

            $sale = Sale::query()->create([
                'business_id' => $business->id,
                'location_id' => $location->id,
                'user_id' => $userId,
                'customer_id' => $customer?->id,
                'uuid' => (string) Str::uuid(),
                'receipt_no' => $this->nextReceiptNo($business),
                'status' => 'completed',
                'idempotency_key' => $idempotencyKey,
                'discount_total' => $discountTotal,
                'version' => 1,
                'sold_at' => $soldAt,
            ]);

            $subtotal = 0.0;
            $taxTotal = 0.0;

            foreach ($lines as $line) {
                $product = Product::query()
                    ->where('business_id', $business->id)
                    ->where('uuid', $line['product_uuid'])
                    ->firstOrFail();

                $qty = (float) $line['qty'];
                if ($qty <= 0) {
                    throw new InvalidArgumentException('Quantity must be positive.');
                }

                $unitPrice = (float) ($line['unit_price'] ?? $product->selling_price);
                $taxRate = (float) ($line['tax_rate'] ?? $product->vat_rate ?? $business->default_vat_rate);

                $lineSubtotal = round($qty * $unitPrice, 2);
                $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
                $lineTotal = $lineSubtotal + $lineTax;

                $allocations = $this->allocateBatches(
                    $business,
                    $product,
                    $location->id,
                    $line['batch_uuid'] ?? null,
                    $qty,
                );

                // Receipt line uses the first batch touched; stock is deducted FIFO across all batches.
                SaleLine::query()->create([
                    'sale_id' => $sale->id,
                    'product_id' => $product->id,
                    'product_batch_id' => $allocations[0]['batch']->id,
                    'qty' => $qty,
                    'unit_price' => $unitPrice,
                    'tax_rate' => $taxRate,
                    'line_total' => $lineTotal,
                ]);

                foreach ($allocations as $allocation) {
                    /** @var ProductBatch $batch */
                    $batch = $allocation['batch'];
                    $take = (float) $allocation['qty'];
                    $batch->qty = round((float) $batch->qty - $take, 3);
                    if ($batch->qty < -0.0001) {
                        throw new InvalidArgumentException('Insufficient stock for '.$product->name);
                    }
                    $batch->version = (int) $batch->version + 1;
                    $batch->save();

                    StockMovement::query()->create([
                        'business_id' => $business->id,
                        'product_id' => $product->id,
                        'product_batch_id' => $batch->id,
                        'location_id' => $location->id,
                        'uuid' => (string) Str::uuid(),
                        'type' => 'out',
                        'qty' => -1 * abs($take),
                        'ref_type' => 'sale',
                        'ref_uuid' => $sale->uuid,
                        'version' => 1,
                    ]);
                }

                $subtotal += $lineSubtotal;
                $taxTotal += $lineTax;
            }

            $grandTotal = round($subtotal + $taxTotal - $discountTotal, 2);

            $sale->update([
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
            ]);

            // Normalize payment amounts BEFORE persisting so the running sum is exactly
            // equal to $grandTotal. Without this, a 0.01 drift from float / JSON serialisation
            // (especially with split cash + transfer payments) makes the GL refuse to post the
            // sale journal with "Sale journal debits do not match grand total."
            $paymentSum = 0.0;
            foreach ($payments as $i => $p) {
                $payments[$i]['amount'] = round((float) ($p['amount'] ?? 0), 2);
                $paymentSum = round($paymentSum + $payments[$i]['amount'], 2);
            }
            $drift = round($grandTotal - $paymentSum, 2);
            if (abs($drift) > 0.02) {
                throw new InvalidArgumentException(
                    'Payment total ('.number_format($paymentSum, 2)
                    .') does not match grand total ('.number_format($grandTotal, 2).').'
                );
            }
            // Absorb sub-cent drift onto the last payment so debits == grand_total exactly.
            if (! empty($payments) && abs($drift) > 0) {
                $lastIdx = count($payments) - 1;
                $payments[$lastIdx]['amount'] = round(
                    (float) $payments[$lastIdx]['amount'] + $drift,
                    2
                );
                $paymentSum = $grandTotal;
            }

            $creditTotal = 0.0;
            $creditPaymentRecords = [];
            foreach ($payments as $p) {
                $amount = (float) $p['amount'];
                $method = $p['method'];
                $glAccount = $method === 'credit'
                    ? null
                    : $this->paymentAccount($business, $method, $p['account_uuid'] ?? null);
                if ($method === 'credit') {
                    $creditTotal += $amount;
                }
                $payment = Payment::query()->create([
                    'business_id' => $business->id,
                    'sale_id' => $sale->id,
                    'uuid' => (string) Str::uuid(),
                    'method' => $method,
                    'amount' => $amount,
                    'gl_account_id' => $glAccount?->id,
                    'meta' => $p['meta'] ?? null,
                ]);
                if ($method === 'credit') {
                    $creditPaymentRecords[] = $payment;
                }
            }

            if ($creditTotal > 0) {
                if ($customer === null || $customer->is_walk_in) {
                    throw new InvalidArgumentException('Pick a saved customer (not Walk-in) to sell on credit.');
                }
                if (! $customer->credit_enabled) {
                    throw new InvalidArgumentException('Credit is not enabled for this customer.');
                }
                $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->first();
                $newBalance = round((float) $locked->credit_balance + $creditTotal, 2);
                $limit = (float) $locked->credit_limit;
                if ($limit > 0 && $newBalance > round($limit + 0.0001, 2)) {
                    throw new InvalidArgumentException(
                        'Credit limit reached. Outstanding will become '.number_format($newBalance, 2)
                        .' but the limit is '.number_format($limit, 2).'.'
                    );
                }
                $locked->credit_balance = $newBalance;
                $locked->save();

                foreach ($creditPaymentRecords as $payment) {
                    CustomerCreditEntry::query()->create([
                        'business_id' => $business->id,
                        'customer_id' => $locked->id,
                        'sale_id' => $sale->id,
                        'payment_id' => $payment->id,
                        'user_id' => $userId,
                        'uuid' => (string) Str::uuid(),
                        'type' => 'charge',
                        'method' => 'credit',
                        'amount' => (float) $payment->amount,
                        'balance_after' => $locked->credit_balance,
                        'reference' => $sale->receipt_no,
                        'notes' => 'Sale on credit',
                        'occurred_at' => $soldAt,
                    ]);
                }
            }

            $sale = $sale->fresh(['lines.product', 'payments.glAccount', 'customer']);
            $this->ledger->postSaleJournal($business, $sale);

            return $sale;
        });
    }

    /**
     * Returns the requested customer or — if none / unknown — the auto-seeded Walk-in customer.
     */
    private function resolveCustomer(Business $business, ?string $customerUuid): ?Customer
    {
        if ($customerUuid !== null && $customerUuid !== '') {
            $customer = Customer::query()
                ->where('business_id', $business->id)
                ->where('uuid', $customerUuid)
                ->first();
            if ($customer) {
                return $customer;
            }
        }

        $walkIn = Customer::query()
            ->where('business_id', $business->id)
            ->where('is_walk_in', true)
            ->first();
        if ($walkIn) {
            return $walkIn;
        }

        return Customer::query()->create([
            'business_id' => $business->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Walk-in customer',
            'is_walk_in' => true,
            'version' => 1,
        ]);
    }

    private function nextReceiptNo(Business $business): string
    {
        return 'RCP-'.$business->id.'-'.now()->format('YmdHis').'-'.strtoupper(Str::random(4));
    }

    private function paymentAccount(Business $business, string $method, ?string $accountUuid): GlAccount
    {
        if ($accountUuid !== null && trim($accountUuid) !== '') {
            return $this->fundAccounts->resolveGlAccount($business, $accountUuid);
        }

        $code = $method === 'cash'
            ? GeneralLedgerService::CODE_CASH
            : GeneralLedgerService::CODE_BANK;

        return GlAccount::query()
            ->where('business_id', $business->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    /**
     * Allocate sale quantity across batches (FIFO by expiry). POS shows total stock
     * across all batches; selling must not fail when the oldest batch alone is too small.
     *
     * @return array<int, array{batch: ProductBatch, qty: float}>
     */
    private function allocateBatches(
        Business $business,
        Product $product,
        int $locationId,
        ?string $batchUuid,
        float $qty,
    ): array {
        $query = ProductBatch::query()
            ->where('business_id', $business->id)
            ->where('product_id', $product->id)
            ->where('location_id', $locationId)
            ->where('qty', '>', 0)
            ->orderByRaw('expiry_date IS NULL, expiry_date ASC');

        if ($batchUuid) {
            $batch = (clone $query)->where('uuid', $batchUuid)->lockForUpdate()->firstOrFail();
            if ((float) $batch->qty + 0.0001 < $qty) {
                throw new InvalidArgumentException(
                    'Insufficient stock in the selected batch for '.$product->name
                    .' (available '.number_format((float) $batch->qty, 3, '.', '').').'
                );
            }

            return [['batch' => $batch, 'qty' => $qty]];
        }

        $batches = $query->lockForUpdate()->get();
        if ($batches->isEmpty()) {
            throw new InvalidArgumentException('No stock batch available for '.$product->name);
        }

        $totalAvailable = round((float) $batches->sum(fn (ProductBatch $b) => (float) $b->qty), 3);
        if ($totalAvailable + 0.0001 < $qty) {
            throw new InvalidArgumentException(
                'Insufficient stock for '.$product->name
                .' (available '.number_format($totalAvailable, 3, '.', '').').'
            );
        }

        $remaining = $qty;
        $allocations = [];
        foreach ($batches as $batch) {
            if ($remaining <= 0.0001) {
                break;
            }
            $available = (float) $batch->qty;
            if ($available <= 0) {
                continue;
            }
            $take = min($remaining, $available);
            $take = round($take, 3);
            $allocations[] = ['batch' => $batch, 'qty' => $take];
            $remaining = round($remaining - $take, 3);
        }

        if ($remaining > 0.0001) {
            throw new InvalidArgumentException('Insufficient stock for '.$product->name);
        }

        return $allocations;
    }
}
