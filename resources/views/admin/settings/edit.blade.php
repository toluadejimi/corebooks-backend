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

<div class="adm-card" style="max-width:920px;margin-top:1.25rem;">
    <h2 style="margin-top:0;font-family:Outfit,sans-serif;font-size:1.05rem;">POS card terminal</h2>
    <p class="adm-page-desc" style="margin-top:-0.35rem;">
        Configure bank card acceptance on Android POS devices (Horizon Pay S60). Values match the ENKWAVE terminal prep: host IP/port, component keys, and merchant TID.
        The mobile POS app downloads this when staff open checkout.
    </p>
    @if(!$canManage)
        <p class="adm-page-desc">Only managers can change terminal settings.</p>
    @endif
    <form method="post" action="{{ route('admin.b.settings.pos_terminal', $business) }}">
        @csrf @method('PUT')
        <div class="adm-field">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;">
                <input type="hidden" name="pos_enabled" value="0">
                <input type="checkbox" name="pos_enabled" value="1" @checked(old('pos_enabled', $posTerminal['pos_enabled'] ?? false)) {{ $canManage ? '' : 'disabled' }}>
                Enable card payments on mobile POS
            </label>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="terminal_no">Terminal ID (TID)</label>
                <input class="adm-input" id="terminal_no" name="terminal_no" value="{{ old('terminal_no', $posTerminal['terminal_no'] ?? '') }}" {{ $canManage ? '' : 'readonly' }} placeholder="e.g. 2ETP0012">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="merchant_no">Merchant ID (MID)</label>
                <input class="adm-input" id="merchant_no" name="merchant_no" value="{{ old('merchant_no', $posTerminal['merchant_no'] ?? '') }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="merchant_name">Merchant name (receipt)</label>
                <input class="adm-input" id="merchant_name" name="merchant_name" value="{{ old('merchant_name', $posTerminal['merchant_name'] ?? $biz->name) }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="device_sn">Device serial (optional)</label>
                <input class="adm-input" id="device_sn" name="device_sn" value="{{ old('device_sn', $posTerminal['device_sn'] ?? '') }}" {{ $canManage ? '' : 'readonly' }}>
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="host_ip">Host IP / hostname</label>
                <input class="adm-input" id="host_ip" name="host_ip" value="{{ old('host_ip', $posTerminal['host_ip'] ?? '') }}" {{ $canManage ? '' : 'readonly' }} placeholder="TMS / acquirer host">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="host_port">Host port</label>
                <input class="adm-input" id="host_port" name="host_port" value="{{ old('host_port', $posTerminal['host_port'] ?? '') }}" {{ $canManage ? '' : 'readonly' }} placeholder="8080">
            </div>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="ssl">Use SSL</label>
                <select class="adm-select" id="ssl" name="ssl" {{ $canManage ? '' : 'disabled' }}>
                    <option value="true" @selected(old('ssl', $posTerminal['ssl'] ?? 'true') === 'true')>Yes</option>
                    <option value="false" @selected(old('ssl', $posTerminal['ssl'] ?? 'true') === 'false')>No</option>
                </select>
            </div>
            <div class="adm-field">
                <label class="adm-label" for="account_type">Default account type</label>
                <select class="adm-select" id="account_type" name="account_type" {{ $canManage ? '' : 'disabled' }}>
                    @foreach(['00' => 'Default', '10' => 'Savings', '20' => 'Current', '30' => 'Corporate'] as $val => $label)
                        <option value="{{ $val }}" @selected(old('account_type', $posTerminal['account_type'] ?? '00') === $val)>{{ $label }} ({{ $val }})</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="base_url">Processor API base URL</label>
            <input class="adm-input" id="base_url" name="base_url" type="url" value="{{ old('base_url', $posTerminal['base_url'] ?? '') }}" {{ $canManage ? '' : 'readonly' }} placeholder="https://enkpayapp.enkwave.com/api/">
            <p class="adm-page-desc" style="margin:0.35rem 0 0;font-size:0.78rem;">Used for key exchange and purchase authorization (pos-logs / pos callbacks).</p>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="pos_logo_url">Receipt logo URL (optional)</label>
            <input class="adm-input" id="pos_logo_url" name="logo_url" type="url" value="{{ old('logo_url', $posTerminal['logo_url'] ?? $biz->logo_url) }}" {{ $canManage ? '' : 'readonly' }}>
        </div>
        <div class="adm-grid cols-2">
            <div class="adm-field">
                <label class="adm-label" for="comp_key1">Component key 1</label>
                <input class="adm-input" id="comp_key1" name="comp_key1" value="{{ old('comp_key1', $posTerminal['comp_key1'] ?? '') }}" {{ $canManage ? '' : 'readonly' }} autocomplete="off">
            </div>
            <div class="adm-field">
                <label class="adm-label" for="comp_key2">Component key 2</label>
                <input class="adm-input" id="comp_key2" name="comp_key2" value="{{ old('comp_key2', $posTerminal['comp_key2'] ?? '') }}" {{ $canManage ? '' : 'readonly' }} autocomplete="off">
            </div>
        </div>
        @if($canManage)
            <div class="adm-actions">
                <button type="submit" class="adm-btn adm-btn-primary">Save POS terminal</button>
            </div>
        @endif
    </form>
</div>
@endsection
