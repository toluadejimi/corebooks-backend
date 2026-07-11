@extends('layouts.admin-workspace')

@section('title', 'Purchases — '.$business->name)

@section('content')
<h1 class="adm-page-title">Purchases</h1>
<p class="adm-page-desc">Goods received into stock (purchase orders). Drafts appear first — continue them anytime before receiving stock.</p>

@if($canManage)
    <div class="adm-actions" style="margin-bottom:1rem;">
        <a href="{{ route('admin.b.purchases.create', $business) }}" class="adm-btn adm-btn-primary">+ Record purchase</a>
        <a href="{{ route('admin.b.suppliers.index', $business) }}" class="adm-btn adm-btn-ghost">Suppliers</a>
    </div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>When</th>
                <th>Branch</th>
                <th>Supplier</th>
                <th>Lines</th>
                <th>Total</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $o)
                <tr>
                    <td>{{ $o->ordered_at?->timezone(config('app.timezone'))->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $o->location?->name ?? '—' }}</td>
                    <td>{{ $o->supplier?->name ?? '—' }}</td>
                    <td>{{ $o->lines_count }}</td>
                    <td><strong>{{ $currencySymbol }}{{ number_format((float) $o->total, 2) }}</strong></td>
                    <td><span class="adm-role-pill" style="font-size:0.65rem;">{{ $o->status }}</span></td>
                    <td class="adm-actions">
                        @if($o->status === 'draft' && $canManage)
                            <a href="{{ route('admin.b.purchases.edit', [$business, $o->uuid]) }}" class="adm-btn adm-btn-primary" style="padding:0.35rem 0.65rem;font-size:0.8rem;">Continue</a>
                        @else
                            <a href="{{ route('admin.b.purchases.show', [$business, $o->uuid]) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.8rem;">View</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" style="color:var(--adm-muted);">No purchases yet.@if($canManage) Use “Record purchase” to receive stock from a supplier.@endif</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection
