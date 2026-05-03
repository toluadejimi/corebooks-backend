@extends('layouts.admin-portfolio')

@section('title', (isset($bank->id) ? 'Edit' : 'Add').' partner bank — '.config('app.name'))

@section('content')
<div class="adm-card" style="max-width:640px;">
    <h1 class="adm-page-title" style="font-size:1.35rem;">{{ isset($bank->id) ? 'Edit partner bank' : 'New partner bank' }}</h1>
    <form method="post" action="{{ isset($bank->id) ? route('admin.platform.loan-banks.update', $bank) : route('admin.platform.loan-banks.store') }}" style="margin-top:1rem;">
        @csrf
        @if(isset($bank->id))
            @method('PUT')
        @endif
        <div class="adm-field">
            <label class="adm-label" for="slug">Slug</label>
            <input class="adm-input" id="slug" name="slug" required pattern="[a-z0-9_]+" value="{{ old('slug', $bank->slug) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="name">Bank name</label>
            <input class="adm-input" id="name" name="name" required maxlength="255" value="{{ old('name', $bank->name) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="min_amount_ngn">Minimum loan (NGN)</label>
            <input class="adm-input" id="min_amount_ngn" name="min_amount_ngn" type="number" step="0.01" min="0" required value="{{ old('min_amount_ngn', $bank->min_amount_ngn) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="max_amount_ngn">Maximum loan (NGN)</label>
            <input class="adm-input" id="max_amount_ngn" name="max_amount_ngn" type="number" step="0.01" min="0" required value="{{ old('max_amount_ngn', $bank->max_amount_ngn) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="notes">Notes (optional)</label>
            <textarea class="adm-input" id="notes" name="notes" rows="2">{{ old('notes', $bank->notes) }}</textarea>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="sort_order">Sort order</label>
            <input class="adm-input" id="sort_order" name="sort_order" type="number" min="0" required value="{{ old('sort_order', $bank->sort_order ?? 100) }}">
        </div>
        <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $bank->is_active ?? true))> Active (shown to businesses)</label>
        <div style="margin-top:1rem;display:flex;gap:0.75rem;">
            <button type="submit" class="adm-btn adm-btn-primary">Save</button>
            <a href="{{ route('admin.platform.loan-banks.index') }}" class="adm-btn adm-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
