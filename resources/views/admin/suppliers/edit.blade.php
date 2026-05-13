@extends('layouts.admin-workspace')

@section('title', 'Edit supplier — '.$business->name)

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.b.suppliers.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Suppliers</a></p>

<h1 class="adm-page-title">Edit supplier</h1>

<form method="post" action="{{ route('admin.b.suppliers.update', [$business, $supplier->uuid]) }}" class="adm-card" style="max-width:480px;">
    @csrf @method('PUT')
    <div class="adm-field" style="margin-bottom:1rem;">
        <label class="adm-label" for="name">Name</label>
        <input class="adm-input" id="name" name="name" value="{{ old('name', $supplier->name) }}" required maxlength="255">
    </div>
    <div class="adm-field" style="margin-bottom:1rem;">
        <label class="adm-label" for="phone">Phone (optional)</label>
        <input class="adm-input" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}" maxlength="32">
    </div>
    <div class="adm-field" style="margin-bottom:1rem;">
        <label class="adm-label" for="email">Email (optional)</label>
        <input class="adm-input" id="email" name="email" type="email" value="{{ old('email', $supplier->email) }}" maxlength="191">
    </div>
    <div class="adm-field" style="margin-bottom:1.25rem;">
        <label class="adm-label" for="address">Address (optional)</label>
        <textarea class="adm-input" id="address" name="address" rows="3" maxlength="500">{{ old('address', $supplier->address) }}</textarea>
    </div>
    <p class="adm-page-desc" style="margin-bottom:1rem;">
        Sum of purchase receipts: <strong>{{ $currencySymbol }}{{ number_format($receiptsTotal, 2) }}</strong>
        · Ledger balance on file: <strong>{{ $currencySymbol }}{{ number_format((float) $supplier->balance, 2) }}</strong>
    </p>
    <button type="submit" class="adm-btn adm-btn-primary">Save changes</button>
</form>
@endsection
