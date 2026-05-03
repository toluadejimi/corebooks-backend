@extends('layouts.admin-portfolio')

@section('title', (isset($plan->id) ? 'Edit' : 'Create').' plan — '.config('app.name'))

@section('content')
<div class="adm-card" style="max-width:720px;">
    <h1 class="adm-page-title" style="font-size:1.35rem;">{{ isset($plan->id) ? 'Edit plan' : 'New plan' }}</h1>
    <form method="post" action="{{ isset($plan->id) ? route('admin.platform.plans.update', $plan) : route('admin.platform.plans.store') }}" class="adm-stack" style="margin-top:1rem;">
        @csrf
        @if(isset($plan->id))
            @method('PUT')
        @endif

        <div class="adm-field">
            <label class="adm-label" for="slug">Slug (lowercase, underscores)</label>
            <input class="adm-input" id="slug" name="slug" required value="{{ old('slug', $plan->slug) }}" pattern="[a-z0-9_]+">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="name">Display name</label>
            <input class="adm-input" id="name" name="name" required maxlength="255" value="{{ old('name', $plan->name) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="description">Description</label>
            <textarea class="adm-input" id="description" name="description" rows="2">{{ old('description', $plan->description) }}</textarea>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="price_ngn">Price (NGN whole amount)</label>
            <input class="adm-input" id="price_ngn" name="price_ngn" type="number" step="0.01" min="0" required value="{{ old('price_ngn', isset($plan->id) ? $plan->priceNaira() : '') }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="billing_interval">Billing</label>
            <select class="adm-input" id="billing_interval" name="billing_interval">
                <option value="monthly" @selected(old('billing_interval', $plan->billing_interval) === 'monthly')>Monthly</option>
                <option value="yearly" @selected(old('billing_interval', $plan->billing_interval) === 'yearly')>Yearly</option>
            </select>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="max_records">Max records (products + key entities)</label>
            <input class="adm-input" id="max_records" name="max_records" type="number" min="1" required value="{{ old('max_records', $plan->max_records ?? 5000) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="sort_order">Sort order</label>
            <input class="adm-input" id="sort_order" name="sort_order" type="number" min="0" required value="{{ old('sort_order', $plan->sort_order ?? 100) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="features_text">Feature bullets (one per line)</label>
            <textarea class="adm-input" id="features_text" name="features_text" rows="8" placeholder="Item inventory&#10;Accounting reports">{{ old('features_text', isset($plan->features) ? implode("\n", $plan->features ?? []) : '') }}</textarea>
        </div>
        <fieldset class="adm-field" style="border:1px solid var(--adm-border);border-radius:8px;padding:1rem;">
            <legend style="padding:0 0.5rem;">Feature flags</legend>
            <label style="display:block;margin:0.35rem 0;"><input type="checkbox" name="feature_inventory" value="1" @checked(old('feature_inventory', $plan->feature_inventory ?? true))> Inventory</label>
            <label style="display:block;margin:0.35rem 0;"><input type="checkbox" name="feature_accounting_reports" value="1" @checked(old('feature_accounting_reports', $plan->feature_accounting_reports ?? true))> Accounting reports</label>
            <label style="display:block;margin:0.35rem 0;"><input type="checkbox" name="feature_tax_reports" value="1" @checked(old('feature_tax_reports', $plan->feature_tax_reports ?? true))> Tax reports</label>
            <label style="display:block;margin:0.35rem 0;"><input type="checkbox" name="feature_database_backup" value="1" @checked(old('feature_database_backup', $plan->feature_database_backup ?? true))> Database backup</label>
            <label style="display:block;margin:0.35rem 0;"><input type="checkbox" name="feature_business_loan" value="1" @checked(old('feature_business_loan', $plan->feature_business_loan ?? false))> Business loan access</label>
        </fieldset>
        <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true))> Plan is active (visible to new sign-ups)</label>

        <div style="margin-top:1rem;display:flex;gap:0.75rem;">
            <button type="submit" class="adm-btn adm-btn-primary">Save</button>
            <a href="{{ route('admin.platform.plans.index') }}" class="adm-btn adm-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
