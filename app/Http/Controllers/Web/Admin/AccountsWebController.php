<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Services\AccountFundsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class AccountsWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly AccountFundsService $funds,
    ) {}

    public function index(Request $request, Business $business): View
    {
        $accounts = $this->funds->listAccounts($business);

        $totalBalance = 0.0;
        foreach ($accounts as $a) {
            $totalBalance += (float) ($a['balance'] ?? 0);
        }

        return view('admin.accounts.index', $this->workspace($request, $business) + [
            'accounts' => $accounts,
            'currencySymbol' => $this->currencySymbol($business),
            'totalBalance' => round($totalBalance, 2),
            'today' => now()->toDateString(),
        ]);
    }

    public function storeAccount(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'currency' => ['nullable', 'string', 'max:8'],
        ]);

        try {
            $this->funds->createAccount($business, $data['name'], $data['currency'] ?? null);
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['account' => $e->getMessage()])
                ->withInput();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['account' => 'Could not add this account.'])
                ->withInput();
        }

        return redirect()
            ->route('admin.b.accounts.index', $business)
            ->with('status', 'Account added.');
    }

    public function transfer(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'from_gl_uuid' => ['required', 'uuid'],
            'to_gl_uuid' => ['required', 'uuid', 'different:from_gl_uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->funds->transfer(
                $business,
                $data['from_gl_uuid'],
                $data['to_gl_uuid'],
                (float) $data['amount'],
                $data['date'] ?? null,
                $data['memo'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['transfer' => $e->getMessage()])
                ->withInput();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['transfer' => 'Transfer could not be recorded.'])
                ->withInput();
        }

        return redirect()
            ->route('admin.b.accounts.index', $business)
            ->with('status', 'Funds transferred.');
    }

    public function deposit(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'to_gl_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->funds->deposit(
                $business,
                $data['to_gl_uuid'],
                (float) $data['amount'],
                $data['date'] ?? null,
                $data['memo'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['deposit' => $e->getMessage()])
                ->withInput();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['deposit' => 'Deposit could not be recorded.'])
                ->withInput();
        }

        return redirect()
            ->route('admin.b.accounts.index', $business)
            ->with('status', 'Deposit recorded.');
    }

    public function withdraw(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'from_gl_uuid' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'date' => ['nullable', 'date'],
            'memo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->funds->withdraw(
                $business,
                $data['from_gl_uuid'],
                (float) $data['amount'],
                $data['date'] ?? null,
                $data['memo'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['withdraw' => $e->getMessage()])
                ->withInput();
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('admin.b.accounts.index', $business)
                ->withErrors(['withdraw' => 'Withdrawal could not be recorded.'])
                ->withInput();
        }

        return redirect()
            ->route('admin.b.accounts.index', $business)
            ->with('status', 'Withdrawal recorded.');
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
}
