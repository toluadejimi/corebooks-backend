@extends('layouts.admin-workspace')

@section('title', 'Suppliers — '.$business->name)

@section('content')
<h1 class="adm-page-title">Suppliers</h1>
<p class="adm-page-desc">Vendors you buy stock from. <strong>Receipt total</strong> is the sum of recorded purchase amounts. <strong>Ledger balance</strong> increases when new purchases are received (legacy receipts before this update may still show 0 until you run a one-off sync if needed).</p>

@if($canManage)
    <div class="adm-actions" style="margin-bottom:1rem;">
        <a href="{{ route('admin.b.suppliers.create', $business) }}" class="adm-btn adm-btn-primary">+ New supplier</a>
        <a href="{{ route('admin.b.purchases.index', $business) }}" class="adm-btn adm-btn-ghost">Purchases</a>
    </div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Phone</th>
                <th>Purchases</th>
                <th style="text-align:right;">Receipt total</th>
                <th style="text-align:right;">Ledger balance</th>
                @if($canManage)<th></th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($suppliers as $s)
                <tr>
                    <td><strong>{{ $s->name }}</strong></td>
                    <td style="color:var(--adm-muted);">{{ $s->phone ?? '—' }}</td>
                    <td>{{ $s->purchase_orders_count }}</td>
                    <td style="text-align:right;"><strong>{{ $currencySymbol }}{{ number_format((float) ($s->purchase_orders_total ?? 0), 2) }}</strong></td>
                    <td style="text-align:right;">{{ $currencySymbol }}{{ number_format((float) $s->balance, 2) }}</td>
                    @if($canManage)
                        <td class="adm-actions">
                            <a href="{{ route('admin.b.suppliers.edit', [$business, $s->uuid]) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.8rem;">Edit</a>
                            <form action="{{ route('admin.b.suppliers.destroy', [$business, $s->uuid]) }}" method="post" style="display:inline;" onsubmit="return confirm('Delete supplier “{{ $s->name }}”?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.35rem 0.65rem;font-size:0.8rem;" @if($s->purchase_orders_count > 0) disabled title="Has purchase history" @endif>Delete</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage ? 6 : 5 }}" style="color:var(--adm-muted);">No suppliers yet.@if($canManage) Create one to pick when recording purchases.@endif</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
