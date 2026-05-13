@php
    $j = $job ?? null;
@endphp
<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="title">Job title</label>
        <input class="adm-input" id="title" name="title" value="{{ old('title', $j?->title) }}" required maxlength="191" placeholder="e.g. Sales executive">
    </div>
    <div class="adm-field">
        <label class="adm-label" for="company_name">Company / employer</label>
        <input class="adm-input" id="company_name" name="company_name" value="{{ old('company_name', $j?->company_name) }}" required maxlength="191">
    </div>
</div>

<div class="adm-field">
    <label class="adm-label" for="description">Description &amp; requirements</label>
    <textarea class="adm-input" id="description" name="description" rows="8" required maxlength="8000">{{ old('description', $j?->description) }}</textarea>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="location_state">State</label>
        <select class="adm-select" id="location_state" name="location_state" required>
            <option value="">— Select state —</option>
            @foreach ($states as $s)
                <option value="{{ $s }}" @selected(old('location_state', $j?->location_state) === $s)>{{ $s }}</option>
            @endforeach
        </select>
    </div>
    <div class="adm-field">
        <label class="adm-label" for="location_city">City / area (optional)</label>
        <input class="adm-input" id="location_city" name="location_city" value="{{ old('location_city', $j?->location_city) }}" maxlength="120">
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="employment_type">Employment type</label>
        <select class="adm-select" id="employment_type" name="employment_type" required>
            @foreach ($employmentTypes as $val => $label)
                <option value="{{ $val }}" @selected(old('employment_type', $j?->employment_type ?? 'full_time') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="adm-field">
        <label class="adm-label" for="expires_at">Expires on (optional)</label>
        <input class="adm-input" id="expires_at" name="expires_at" type="date" value="{{ old('expires_at', $j?->expires_at?->format('Y-m-d')) }}">
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="salary_min">Salary min (optional)</label>
        <input class="adm-input" id="salary_min" name="salary_min" type="number" step="0.01" min="0" value="{{ old('salary_min', $j?->salary_min) }}">
    </div>
    <div class="adm-field">
        <label class="adm-label" for="salary_max">Salary max (optional)</label>
        <input class="adm-input" id="salary_max" name="salary_max" type="number" step="0.01" min="0" value="{{ old('salary_max', $j?->salary_max) }}">
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="salary_period">Salary period (optional)</label>
        <select class="adm-select" id="salary_period" name="salary_period">
            @foreach (['' => '— Not stated —', 'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'annually' => 'Annually'] as $val => $label)
                <option value="{{ $val }}" @selected(old('salary_period', $j?->salary_period) === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="adm-field">
        <label class="adm-label" for="currency">Currency</label>
        <input class="adm-input" id="currency" name="currency" value="{{ old('currency', $j?->currency ?? 'NGN') }}" maxlength="8">
    </div>
</div>

<h2 style="font-family:Outfit,sans-serif;font-size:1.05rem;margin:1.25rem 0 0.5rem;">How to apply</h2>
<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="contact_email">Contact email</label>
        <input class="adm-input" id="contact_email" name="contact_email" type="email" value="{{ old('contact_email', $j?->contact_email) }}" maxlength="191">
    </div>
    <div class="adm-field">
        <label class="adm-label" for="contact_phone">Contact phone</label>
        <input class="adm-input" id="contact_phone" name="contact_phone" value="{{ old('contact_phone', $j?->contact_phone) }}" maxlength="32">
    </div>
</div>
<div class="adm-field">
    <label class="adm-label" for="apply_url">Apply URL (optional)</label>
    <input class="adm-input" id="apply_url" name="apply_url" type="url" value="{{ old('apply_url', $j?->apply_url) }}" maxlength="500" placeholder="https://...">
</div>
