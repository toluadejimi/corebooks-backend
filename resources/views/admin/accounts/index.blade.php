@extends('layouts.admin-workspace')

@section('title', 'Accounts — '.$business->name)

@section('content')
@php
    $sym = $currencySymbol;
    $fmt = fn ($n) => $sym.number_format((float) $n, 2);
    $assetOptions = array_values(array_filter($accounts, fn ($a) => !empty($a['gl_account_uuid'])));
@endphp

<h1 class="adm-page-title">Accounts</h1>
<p class="adm-page-desc">Cash and bank balances at a glance. Move funds between accounts, record a deposit, or log a withdrawal. Every action posts a journal entry so books stay balanced.</p>

<section class="adm-card" style="margin-bottom:1.25rem;">
    <div class="adm-grid cols-2" style="gap:1rem;align-items:end;">
        <div>
            <span class="adm-page-desc" style="margin:0;display:block;">Total funds on hand</span>
            <strong style="font-size:1.6rem;font-family:Outfit,sans-serif;">{{ $fmt($totalBalance) }}</strong>
        </div>
        @if($canManage)
            <div class="adm-actions" style="justify-content:flex-end;">
                <a href="#new-account" class="adm-btn adm-btn-ghost">+ New account</a>
            </div>
        @endif
    </div>
</section>

<div class="adm-table-wrap" style="margin-bottom:1.5rem;">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Account</th>
                <th>Kind</th>
                <th>Currency</th>
                <th>Code</th>
                <th style="text-align:right;">Balance</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($accounts as $a)
                <tr>
                    <td><strong>{{ $a['name'] }}</strong></td>
                    <td><span class="adm-role-pill" style="font-size:0.65rem;">{{ $a['kind'] }}</span></td>
                    <td style="color:var(--adm-muted);">{{ $a['currency'] }}</td>
                    <td style="color:var(--adm-muted);"><code>{{ $a['gl_code'] }}</code></td>
                    <td style="text-align:right;"><strong>{{ $fmt($a['balance']) }}</strong></td>
                </tr>
            @empty
                <tr><td colspan="5" style="color:var(--adm-muted);">No accounts yet. Cash on hand is created automatically once you record a sale, deposit, or new account.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($canManage)
<section class="adm-card" style="margin-bottom:1.25rem;">
    <h2 class="adm-page-title" style="font-size:1.05rem;">Transfer funds</h2>
    <p class="adm-page-desc" style="margin-bottom:0.75rem;">Move money between cash and bank accounts (or between two banks). Posts a balanced journal entry.</p>
    <form method="post" action="{{ route('admin.b.accounts.transfer', $business) }}" class="adm-grid cols-3" style="gap:0.75rem;align-items:end;">
        @csrf
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="tr_from">From</label>
            <select class="adm-select" id="tr_from" name="from_gl_uuid" required>
                <option value="">— Pick account —</option>
                @foreach ($assetOptions as $a)
                    <option value="{{ $a['gl_account_uuid'] }}" @selected(old('from_gl_uuid') === $a['gl_account_uuid'])>{{ $a['name'] }} ({{ $fmt($a['balance']) }})</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="tr_to">To</label>
            <select class="adm-select" id="tr_to" name="to_gl_uuid" required>
                <option value="">— Pick account —</option>
                @foreach ($assetOptions as $a)
                    <option value="{{ $a['gl_account_uuid'] }}" @selected(old('to_gl_uuid') === $a['gl_account_uuid'])>{{ $a['name'] }} ({{ $fmt($a['balance']) }})</option>
                @endforeach
            </select>
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="tr_amount">Amount</label>
            <input class="adm-input" id="tr_amount" name="amount" type="number" step="0.01" min="0" required value="{{ old('amount') }}">
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="tr_date">Date</label>
            <input class="adm-input" id="tr_date" name="date" type="date" value="{{ old('date', $today) }}">
        </div>
        <div class="adm-field" style="margin:0;grid-column:span 2;">
            <label class="adm-label" for="tr_memo">Memo (optional)</label>
            <input class="adm-input" id="tr_memo" name="memo" maxlength="255" placeholder="e.g. Top-up till from main bank" value="{{ old('memo') }}">
        </div>
        <div style="grid-column:span 3;display:flex;justify-content:flex-end;gap:0.5rem;">
            <button type="submit" class="adm-btn adm-btn-primary">Transfer funds</button>
        </div>
    </form>
</section>

<div class="adm-grid cols-2" style="gap:1rem;margin-bottom:1.25rem;align-items:start;">
    <section class="adm-card">
        <h2 class="adm-page-title" style="font-size:1.05rem;">Deposit / top-up</h2>
        <p class="adm-page-desc" style="margin-bottom:0.75rem;">Add money to an account from outside (owner top-up, capital). Posts <strong>Dr account / Cr Owner contributions</strong>.</p>
        <form method="post" action="{{ route('admin.b.accounts.deposit', $business) }}">
            @csrf
            <div class="adm-field">
                <label class="adm-label" for="dep_to">Account</label>
                <select class="adm-select" id="dep_to" name="to_gl_uuid" required>
                    <option value="">— Pick account —</option>
                    @foreach ($assetOptions as $a)
                        <option value="{{ $a['gl_account_uuid'] }}">{{ $a['name'] }} ({{ $fmt($a['balance']) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-grid cols-2">
                <div class="adm-field">
                    <label class="adm-label" for="dep_amount">Amount</label>
                    <input class="adm-input" id="dep_amount" name="amount" type="number" step="0.01" min="0" required>
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="dep_date">Date</label>
                    <input class="adm-input" id="dep_date" name="date" type="date" value="{{ $today }}">
                </div>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="dep_memo">Memo (optional)</label>
                <input class="adm-input" id="dep_memo" name="memo" maxlength="255" placeholder="e.g. Cash deposit from owner">
            </div>
            <div class="adm-actions" style="justify-content:flex-end;">
                <button type="submit" class="adm-btn adm-btn-primary">Record deposit</button>
            </div>
        </form>
    </section>

    <section class="adm-card">
        <h2 class="adm-page-title" style="font-size:1.05rem;">Withdraw / pay out</h2>
        <p class="adm-page-desc" style="margin-bottom:0.75rem;">Remove money from an account (owner draw, off-book payout). Posts <strong>Dr Owner drawings / Cr account</strong>.</p>
        <form method="post" action="{{ route('admin.b.accounts.withdraw', $business) }}">
            @csrf
            <div class="adm-field">
                <label class="adm-label" for="wd_from">Account</label>
                <select class="adm-select" id="wd_from" name="from_gl_uuid" required>
                    <option value="">— Pick account —</option>
                    @foreach ($assetOptions as $a)
                        <option value="{{ $a['gl_account_uuid'] }}">{{ $a['name'] }} ({{ $fmt($a['balance']) }})</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-grid cols-2">
                <div class="adm-field">
                    <label class="adm-label" for="wd_amount">Amount</label>
                    <input class="adm-input" id="wd_amount" name="amount" type="number" step="0.01" min="0" required>
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="wd_date">Date</label>
                    <input class="adm-input" id="wd_date" name="date" type="date" value="{{ $today }}">
                </div>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="wd_memo">Memo (optional)</label>
                <input class="adm-input" id="wd_memo" name="memo" maxlength="255" placeholder="e.g. Owner withdrawal">
            </div>
            <div class="adm-actions" style="justify-content:flex-end;">
                <button type="submit" class="adm-btn adm-btn-primary">Record withdrawal</button>
            </div>
        </form>
    </section>
</div>

<section class="adm-card" id="new-account">
    <h2 class="adm-page-title" style="font-size:1.05rem;">Add a new account</h2>
    <p class="adm-page-desc" style="margin-bottom:0.75rem;">Use this for a new bank account, till, or cash drawer. We auto-create the GL row behind the scenes.</p>
    <form method="post" action="{{ route('admin.b.accounts.store-account', $business) }}" class="adm-grid cols-2" style="gap:0.75rem;align-items:end;">
        @csrf
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="acc_name">Account name</label>
            <input class="adm-input" id="acc_name" name="name" required maxlength="160" placeholder="e.g. GTBank · Operations" value="{{ old('name') }}">
        </div>
        <div class="adm-field" style="margin:0;">
            <label class="adm-label" for="acc_currency">Currency (optional)</label>
            <input class="adm-input" id="acc_currency" name="currency" maxlength="8" placeholder="{{ $business->currency ?? 'NGN' }}" value="{{ old('currency') }}">
        </div>
        <div style="grid-column:span 2;display:flex;justify-content:flex-end;">
            <button type="submit" class="adm-btn adm-btn-primary">Add account</button>
        </div>
    </form>
</section>
@endif
@endsection
