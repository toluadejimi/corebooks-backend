@extends('layouts.admin-workspace')

@section('title', 'General ledger — '.$business->name)

@section('content')
@php
    $sym = $currencySymbol;
    $fmt = fn ($n, $dec = 2) => $sym.number_format((float) $n, $dec);
@endphp

<h1 class="adm-page-title">General ledger</h1>
<p class="adm-page-desc">
    Double-entry books: chart of accounts, posted journal entries (including POS, expenses, and payroll), and trial balance.
</p>

<section class="adm-card" style="margin-bottom:1.25rem;" id="chart">
    <h2 class="adm-page-title" style="font-size:1.05rem;">Chart of accounts</h2>
    <p class="adm-page-desc" style="margin-bottom:0.75rem;">System accounts are created automatically; extend the chart via the API or future UI.</p>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>System</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($accounts as $a)
                    <tr>
                        <td><code>{{ $a->code }}</code></td>
                        <td>{{ $a->name }}</td>
                        <td>{{ $a->type }}</td>
                        <td>{{ $a->is_system ? 'Yes' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="color:var(--adm-muted);">No accounts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>

<section class="adm-card" style="margin-bottom:1.25rem;" id="journals">
    <h2 class="adm-page-title" style="font-size:1.05rem;">Journal entries</h2>
    <p class="adm-page-desc" style="margin-bottom:0.75rem;">Auto-posted from POS sales, expense entries, and finalised payroll.</p>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Memo</th>
                    <th>Lines</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $e)
                    <tr>
                        <td>{{ $e->entry_date?->toDateString() ?? '—' }}</td>
                        <td><span class="adm-role-pill" style="font-size:0.65rem;">{{ $e->source_type }}</span></td>
                        <td>{{ Str::limit($e->memo ?? '—', 64) }}</td>
                        <td>
                            <ul style="margin:0;padding-left:1.1rem;font-size:0.8rem;color:var(--adm-muted);">
                                @foreach ($e->lines as $ln)
                                    <li>{{ $ln->account?->code }} {{ $ln->account?->name }} — Dr {{ $fmt($ln->debit) }} / Cr {{ $fmt($ln->credit) }}</li>
                                @endforeach
                            </ul>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="color:var(--adm-muted);">No journal entries yet. Record a sale, expense, or finalise payroll to generate postings.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div style="margin-top:1rem;">{{ $entries->links() }}</div>
</section>

<section class="adm-card" id="trial">
    <h2 class="adm-page-title" style="font-size:1.05rem;">Trial balance</h2>
    <form method="get" action="{{ route('admin.b.ledger.index', $business) }}" class="adm-inline-form" style="margin-bottom:1rem;flex-wrap:wrap;gap:0.75rem;">
        <div class="adm-field">
            <label class="adm-label" for="as_of">As of date</label>
            <input class="adm-input" id="as_of" name="as_of" type="date" value="{{ $asOf }}">
        </div>
        <button type="submit" class="adm-btn adm-btn-primary" style="height:42px;margin-top:1.35rem;">Run</button>
        <a href="{{ route('admin.b.ledger.index', $business) }}#trial" class="adm-btn adm-btn-ghost" style="height:42px;margin-top:1.35rem;">Today</a>
    </form>
    <p class="adm-page-desc" style="margin-bottom:0.75rem;">
        Totals: debit <strong>{{ $fmt($trialMeta['total_debit']) }}</strong> · credit <strong>{{ $fmt($trialMeta['total_credit']) }}</strong>
        @if(abs($trialMeta['total_debit'] - $trialMeta['total_credit']) > 0.02)
            <span style="color:#b45309;">(difference should be zero when books are balanced)</span>
        @endif
    </p>
    <div class="adm-table-wrap">
        <table class="adm-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th style="text-align:right;">Debit</th>
                    <th style="text-align:right;">Credit</th>
                    <th style="text-align:right;">Net (Dr−Cr)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($trialRows as $row)
                    <tr>
                        <td><code>{{ $row['code'] }}</code></td>
                        <td>{{ $row['name'] }}</td>
                        <td>{{ $row['type'] }}</td>
                        <td style="text-align:right;">{{ $fmt($row['debit']) }}</td>
                        <td style="text-align:right;">{{ $fmt($row['credit']) }}</td>
                        <td style="text-align:right;">{{ $fmt($row['net_dr_minus_cr']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
@endsection
