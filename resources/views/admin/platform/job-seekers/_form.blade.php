@php($s = $seeker ?? null)

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="full_name">Full name *</label>
        <input class="adm-input" id="full_name" name="full_name" value="{{ old('full_name', $s?->full_name) }}" required maxlength="191">
    </div>
    <div class="adm-field">
        <label class="adm-label" for="headline">Headline / role</label>
        <input class="adm-input" id="headline" name="headline" value="{{ old('headline', $s?->headline) }}" maxlength="191" placeholder="e.g. Frontend developer · 3 yrs">
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="email">Email</label>
        <input class="adm-input" id="email" name="email" type="email" value="{{ old('email', $s?->email) }}" maxlength="191">
    </div>
    <div class="adm-field">
        <label class="adm-label" for="phone">Phone</label>
        <input class="adm-input" id="phone" name="phone" value="{{ old('phone', $s?->phone) }}" maxlength="32">
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="location_state">State *</label>
        <select class="adm-select" id="location_state" name="location_state" required>
            <option value="">— Select —</option>
            @foreach ($states as $st)
                <option value="{{ $st }}" @selected(old('location_state', $s?->location_state) === $st)>{{ $st }}</option>
            @endforeach
        </select>
    </div>
    <div class="adm-field">
        <label class="adm-label" for="location_city">City</label>
        <input class="adm-input" id="location_city" name="location_city" value="{{ old('location_city', $s?->location_city) }}" maxlength="120">
    </div>
</div>

<div class="adm-field" style="margin-bottom:0.5rem;">
    <label style="display:inline-flex;align-items:center;gap:0.5rem;">
        <input type="hidden" name="open_to_relocate" value="0">
        <input type="checkbox" name="open_to_relocate" value="1" @checked(old('open_to_relocate', $s?->open_to_relocate) == 1)>
        <span>Open to relocating</span>
    </label>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="years_experience">Years of experience</label>
        <input class="adm-input" id="years_experience" name="years_experience" type="number" min="0" max="80" value="{{ old('years_experience', $s?->years_experience ?? 0) }}">
    </div>
    <div class="adm-field">
        <label class="adm-label" for="employment_type">Wants</label>
        <select class="adm-select" id="employment_type" name="employment_type" required>
            @foreach ($employmentTypes as $val => $label)
                <option value="{{ $val }}" @selected(old('employment_type', $s?->employment_type ?? 'full_time') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="expected_salary_min">Expected salary — min</label>
        <input class="adm-input" id="expected_salary_min" name="expected_salary_min" type="number" step="0.01" min="0" value="{{ old('expected_salary_min', $s?->expected_salary_min) }}">
    </div>
    <div class="adm-field">
        <label class="adm-label" for="expected_salary_max">Expected salary — max</label>
        <input class="adm-input" id="expected_salary_max" name="expected_salary_max" type="number" step="0.01" min="0" value="{{ old('expected_salary_max', $s?->expected_salary_max) }}">
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="salary_period">Salary period</label>
        <select class="adm-select" id="salary_period" name="salary_period">
            @foreach (['' => '— Not stated —', 'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'annually' => 'Annually'] as $val => $label)
                <option value="{{ $val }}" @selected(old('salary_period', $s?->salary_period) === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="adm-field">
        <label class="adm-label" for="currency">Currency</label>
        <input class="adm-input" id="currency" name="currency" value="{{ old('currency', $s?->currency ?? 'NGN') }}" maxlength="8">
    </div>
</div>

<div class="adm-field">
    <label class="adm-label" for="about">About</label>
    <textarea class="adm-input" id="about" name="about" rows="4" maxlength="4000">{{ old('about', $s?->about) }}</textarea>
</div>
<div class="adm-field">
    <label class="adm-label" for="skills">Skills</label>
    <textarea class="adm-input" id="skills" name="skills" rows="2" maxlength="1000" placeholder="Comma-separated, e.g. Flutter, Dart, Firebase">{{ old('skills', $s?->skills) }}</textarea>
</div>
<div class="adm-field">
    <label class="adm-label" for="education">Education</label>
    <textarea class="adm-input" id="education" name="education" rows="2" maxlength="1000">{{ old('education', $s?->education) }}</textarea>
</div>
<div class="adm-field">
    <label class="adm-label" for="linkedin_url">LinkedIn URL</label>
    <input class="adm-input" id="linkedin_url" name="linkedin_url" type="url" value="{{ old('linkedin_url', $s?->linkedin_url) }}" maxlength="500" placeholder="https://...">
</div>

<h2 style="font-family:Outfit,sans-serif;font-size:1.05rem;margin:1.25rem 0 0.5rem;">Media</h2>
<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="photo">Photo (jpeg/png/webp, ≤5MB)</label>
        <input class="adm-input" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
        @if($s?->photo_url)
            <div style="margin-top:0.5rem;display:flex;align-items:center;gap:0.5rem;">
                <img src="{{ $s->photo_url }}" alt="" style="width:48px;height:48px;border-radius:6px;object-fit:cover;border:1px solid var(--adm-border,#ddd);">
                <label style="display:inline-flex;align-items:center;gap:0.4rem;font-size:0.85rem;">
                    <input type="checkbox" name="remove_photo" value="1"> Remove existing photo
                </label>
            </div>
        @endif
    </div>
    <div class="adm-field">
        <label class="adm-label" for="cv">CV (pdf/doc/docx, ≤8MB)</label>
        <input class="adm-input" id="cv" name="cv" type="file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
        @if($s?->cv_url)
            <div style="margin-top:0.5rem;display:flex;align-items:center;gap:0.5rem;font-size:0.85rem;">
                <a href="{{ $s->cv_url }}" target="_blank" rel="noopener">📎 {{ $s->cv_filename ?: 'View current CV' }}</a>
                <label style="display:inline-flex;align-items:center;gap:0.4rem;">
                    <input type="checkbox" name="remove_cv" value="1"> Remove
                </label>
            </div>
        @endif
    </div>
</div>

<div class="adm-grid cols-2" style="gap:0.75rem;">
    <div class="adm-field">
        <label class="adm-label" for="status">Visibility</label>
        <select class="adm-select" id="status" name="status">
            @foreach (['pending' => 'Pending review', 'active' => 'Approved (visible on mobile)', 'declined' => 'Declined', 'hidden' => 'Hidden', 'archived' => 'Archived'] as $val => $label)
                <option value="{{ $val }}" @selected(old('status', $s?->status ?? 'active') === $val)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>
