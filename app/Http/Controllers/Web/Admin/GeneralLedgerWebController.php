<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Services\GeneralLedgerService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GeneralLedgerWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly GeneralLedgerService $ledger,
    ) {}

    public function index(Request $request, Business $business): View
    {
        $this->ledger->ensureDefaultChart($business);

        $asOf = (string) $request->query('as_of', now()->toDateString());
        $accounts = $business->glAccounts()->orderBy('code')->get();

        $entries = $business->journalEntries()
            ->with(['lines.account'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $trial = $this->ledger->trialBalance($business, $asOf);
        $trialMeta = [
            'as_of' => $asOf,
            'total_debit' => round(array_sum(array_column($trial, 'debit')), 2),
            'total_credit' => round(array_sum(array_column($trial, 'credit')), 2),
        ];

        $currencySymbol = match (strtoupper((string) ($business->currency ?? 'NGN'))) {
            'NGN' => '₦',
            'USD' => '$',
            'GBP' => '£',
            'EUR' => '€',
            default => ($business->currency ?? '¤').' ',
        };

        return view('admin.general-ledger.index', $this->workspace($request, $business) + [
            'accounts' => $accounts,
            'entries' => $entries,
            'trialRows' => $trial,
            'trialMeta' => $trialMeta,
            'asOf' => $asOf,
            'currencySymbol' => $currencySymbol,
        ]);
    }
}
