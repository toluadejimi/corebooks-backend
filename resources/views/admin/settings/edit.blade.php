@extends('layouts.admin-workspace')

@section('title', 'Business settings — '.$business->name)

@section('content')
<h1 class="adm-page-title">Business settings</h1>
<p class="adm-page-desc">Profile, VAT, receipt footer, branches, and branding — aligned with the mobile app workspace screen.</p>

@if(session('logo_uploaded_url'))
<script>
document.addEventListener('DOMContentLoaded', function () {
    var el = document.getElementById('logo_url');
    if (el) el.value = @json(session('logo_uploaded_url'));
});
</script>
@endif

<div class="adm-grid cols-2" style="gap:1.5rem;align-items:start;">
    <div class="adm-card">
        <h2 style="margin-top:0;font-family:Outfit,sans-serif;font-size:1.05rem;">Branches</h2>
        <p class="adm-page-desc" style="margin-top:-0.35rem;">Outlets and warehouses. POS and reports can be scoped per branch.</p>
        <ul style="list-style:none;padding:0;margin:0 0 1rem;">
            @foreach($biz->locations as $loc)
                <li style="display:flex;align-items:center;justify-content:space-between;gap:0.75rem;padding:0.5rem 0;border-bottom:1px solid var(--adm-border);">
                    <span>
                        @if($loc->is_default)
                            <strong>{{ $loc->name }}</strong>
                            <span class="adm-role-pill" style="margin-left:0.35rem;font-size:0.65rem;">default</span>
                        @else
                            {{ $loc->name }}
                        @endif
                    </span>
                    @if($canManage && !$loc->is_default)
                        <form method="post" action="{{ route('admin.b.settings.locations.destroy', [$business, $loc]) }}" style="margin:0;" onsubmit="return confirm('Delete this branch?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Delete</button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        @if($canManage)
            <form method="post" action="{{ route('admin.b.settings.locations.store', $business) }}" class="adm-grid cols-2" style="gap:0.75rem;align-items:end;">
                @csrf
                <div class="adm-field" style="margin:0;">
                    <label class="adm-label" for="branch_name">New branch name</label>
                    <input class="adm-input" id="branch_name" name="name" placeholder="e.g. Lekki store" required>
                </div>
                <div>
                    <button type="submit" class="adm-btn adm-btn-primary">Add branch</button>
                </div>
            </form>
        @else
            <p class="adm-page-desc">Only managers can add or remove branches.</p>
        @endif
    </div>

    <div class="adm-card">
        <h2 style="margin-top:0;font-family:Outfit,sans-serif;font-size:1.05rem;">Logo upload</h2>
        @if($canManage)
            <form method="post" action="{{ route('admin.b.settings.logo', $business) }}" enctype="multipart/form-data" style="margin-bottom:1rem;">
                @csrf
                <div class="adm-field">
                    <label class="adm-label" for="logo_image">Image file</label>
                    <input class="adm-input" id="logo_image" name="image" type="file" accept="image/jpeg,image/png,image/webp,image/gif" required>
                </div>
                <button type="submit" class="adm-btn adm-btn-primary">Upload &amp; paste URL</button>
            </form>
        @endif
        <p class="adm-page-desc" style="margin:0;">HTTPS URLs from your CDN work too — paste below after upload or from elsewhere.</p>
    </div>
</div>

<div class="adm-card" style="max-width:920px;margin-top:1.25rem;">
    <h2 style="margin-top:0;font-family:Outfit,sans-serif;font-size:1.05rem;">Business profile</h2>
    @if(!$canManage)
        <p class="adm-page-desc">You can view settings here; only managers can save changes.</p>
    @endif
    <form method="post" action="{{ route('admin.b.settings.profile', $business) }}">
        @csrf @method('PUT')
        <div class="adm-field">
            <label class="adm-label" for="logo_url">Logo URL (HTTPS)</label>
            <input class="adm-input" id="logo_url" name="logo_url" type="url" value="{{ old('logo_url', $biz->logo_url) }}" {{ $canManage ? '' : 'readonly' }}>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="name">Business name</label>
            <input class="adm-input" id="name" name="name" required value="{{ old('name', $biz->name) }}" {{ $canManage ? '' : 'readonly' }}>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="phone">Phone</label>
            <input class="adm-input" id="phone" name="phone" value="{{ old('phone', $biz->phone) }}" {{ $canManage ? '' : 'readonly' }}>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="address_line1">Address line 1</label>
            <input class="adm-input" id="address_line1" name="address_line1" value="{{ old('address_line1', $biz->address_line1) }}" {{ $canManage ? '' : 'readonly' }}>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="address_line2">Address line 2</label>
            <input class="adm-input" id="address_line2" name="address_line2" value="{{ old('address_line2', $biz->address_line2) }}" {{ $canManage ? '' : 'readonly' }}>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="city">City</label>
                <input class="adm-input" id="city" name="city" value="{{ old('city', $biz->city) }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="state">State</label>
                <input class="adm-input" id="state" name="state" value="{{ old('state', $biz->state) }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="country">Country (ISO-2)</label>
                <input class="adm-input" id="country" name="country" maxlength="2" value="{{ old('country', $biz->country ?? 'NG') }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="currency">Currency code</label>
                <input class="adm-input" id="currency" name="currency" value="{{ old('currency', $biz->currency ?? 'NGN') }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="default_vat_rate">Default VAT %</label>
                <input class="adm-input" id="default_vat_rate" name="default_vat_rate" type="number" step="0.01" min="0" max="100" value="{{ old('default_vat_rate', $biz->default_vat_rate) }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="tax_id">Tax ID (TIN)</label>
                <input class="adm-input" id="tax_id" name="tax_id" value="{{ old('tax_id', $biz->tax_id) }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="receipt_footer">Receipt footer</label>
            <textarea class="adm-input" id="receipt_footer" name="receipt_footer" rows="3" {{ $canManage ? '' : 'readonly' }}>{{ old('receipt_footer', $receiptFooter) }}</textarea>
        </div>
        @if($canManage)
            <div class="adm-actions">
                <button type="submit" class="adm-btn adm-btn-primary">Save profile</button>
            </div>
        @endif
    </form>
</div>
@endsection
