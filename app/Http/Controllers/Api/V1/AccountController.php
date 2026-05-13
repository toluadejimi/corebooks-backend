<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\AccountFundsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountFundsService $funds,
    ) {}

    public function index(Business $business): JsonResponse
    {
        return response()->json([
            'data' => $this->funds->listAccounts($business),
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'currency' => ['nullable', 'string', 'max:8'],
        ]);

        try {
            $bank = $this->funds->createAccount($business, $data['name'], $data['currency'] ?? null);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not add this account.'], 500);
        }

        return response()->json([
            'data' => [
                'uuid' => $bank->uuid,
                'name' => $bank->name,
                'currency' => $bank->currency,
            ],
        ], 201);
    }

    public function transfer(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'from_gl_uuid' => ['required', 'uuid'],
            'to_gl_uuid' => ['required', 'uuid', 'different:from_gl_uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->funds->transfer(
                $business,
                $data['from_gl_uuid'],
                $data['to_gl_uuid'],
                (float) $data['amount'],
                $data['date'] ?? null,
                $data['memo'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Transfer could not be recorded.'], 500);
        }

        return response()->json([
            'data' => [
                'journal_entry_uuid' => $entry->uuid,
                'accounts' => $this->funds->listAccounts($business),
            ],
        ], 201);
    }

    public function deposit(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'to_gl_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->funds->deposit(
                $business,
                $data['to_gl_uuid'],
                (float) $data['amount'],
                $data['date'] ?? null,
                $data['memo'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Deposit could not be recorded.'], 500);
        }

        return response()->json([
            'data' => [
                'journal_entry_uuid' => $entry->uuid,
                'accounts' => $this->funds->listAccounts($business),
            ],
        ], 201);
    }

    public function withdraw(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
            'from_gl_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $entry = $this->funds->withdraw(
                $business,
                $data['from_gl_uuid'],
                (float) $data['amount'],
                $data['date'] ?? null,
                $data['memo'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'Withdrawal could not be recorded.'], 500);
        }

        return response()->json([
            'data' => [
                'journal_entry_uuid' => $entry->uuid,
                'accounts' => $this->funds->listAccounts($business),
            ],
        ], 201);
    }
}
