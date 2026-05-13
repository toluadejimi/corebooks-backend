@extends('layouts.site')

@section('title', 'Apply as a job seeker — CoreBooks')

@push('styles')
<style>
    .apply-shell {
        position: relative;
        padding: 4rem 0 5rem;
    }
    .apply-shell .container { max-width: 880px; }
    .apply-hero {
        text-align: center;
        margin-bottom: 2.5rem;
    }
    .apply-hero .pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.9rem;
        font-size: 0.8rem;
        font-weight: 600;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        border-radius: 999px;
        background: rgba(99, 102, 241, 0.14);
        border: 1px solid rgba(99, 102, 241, 0.35);
        color: #c7d2fe;
        margin-bottom: 1.25rem;
    }
    .apply-hero h1 {
        font-family: "Outfit", sans-serif;
        font-size: clamp(2rem, 4vw, 2.75rem);
        font-weight: 700;
        letter-spacing: -0.02em;
        background: linear-gradient(135deg, #fff 0%, #c7d2fe 60%, #a5b4fc 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        margin-bottom: 0.75rem;
    }
    .apply-hero p { color: var(--muted); max-width: 38rem; margin: 0 auto; }
    .apply-card {
        position: relative;
        overflow: hidden;
    }
    .apply-card::before {
        content: "";
        position: absolute;
        inset: -1px;
        background: linear-gradient(135deg, rgba(99,102,241,0.45), transparent 40%);
        z-index: 0;
        opacity: 0.6;
        pointer-events: none;
        border-radius: 16px;
    }
    .apply-form { position: relative; z-index: 1; }
    .apply-section-title {
        font-family: "Outfit", sans-serif;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #a5b4fc;
        margin: 1.75rem 0 0.85rem;
    }
    .apply-section-title:first-of-type { margin-top: 0.25rem; }
    .field-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.85rem; }
    @media (max-width: 600px) { .field-grid { grid-template-columns: 1fr; } }
    .field { margin-bottom: 0.85rem; }
    select.input { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%2394a3b8' d='M6 8L0 0h12z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 1rem center; padding-right: 2.5rem; }
    textarea.input { min-height: 110px; resize: vertical; line-height: 1.5; }
    .file-input { padding: 0.7rem; }
    .checkbox-row {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.85rem 1rem;
        border-radius: 10px;
        border: 1px solid var(--border);
        background: rgba(0, 0, 0, 0.2);
        margin-top: 0.5rem;
    }
    .checkbox-row input[type=checkbox] { margin-top: 4px; accent-color: var(--accent); width: 16px; height: 16px; }
    .checkbox-row label { display: block; font-size: 0.9rem; color: var(--text); margin: 0; }
    .checkbox-row a { color: #a5b4fc; }
    .alert {
        padding: 0.85rem 1rem;
        border-radius: 10px;
        font-size: 0.9rem;
        margin-bottom: 1.25rem;
        background: rgba(248, 113, 113, 0.12);
        border: 1px solid rgba(248, 113, 113, 0.4);
        color: #fecaca;
    }
    .alert ul { margin: 0; padding-left: 1.1rem; }
    .submit-row { display: flex; flex-wrap: wrap; gap: 0.75rem; align-items: center; margin-top: 1.5rem; }
    .submit-row .btn { padding: 0.95rem 1.6rem; font-size: 0.95rem; }
    .hp-trap { position: absolute; left: -10000px; top: auto; width: 1px; height: 1px; opacity: 0; }
    .top-nav {
        position: relative;
        padding: 1.25rem 0;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .top-nav .brand {
        font-family: "Outfit", sans-serif;
        font-weight: 700;
        font-size: 1.15rem;
        letter-spacing: 0.02em;
        color: var(--text);
    }
    .top-nav .links { display: flex; gap: 0.5rem; }
    .top-nav .links .btn { padding: 0.55rem 1rem; font-size: 0.85rem; }
</style>
@endpush

@section('content')
<div class="apply-shell">
    <div class="container">
        <nav class="top-nav">
            <a href="{{ url('/') }}" class="brand">CoreBooks</a>
            <div class="links">
                <a href="{{ route('public.jobs.status') }}" class="btn btn-ghost">Check application status</a>
            </div>
        </nav>

        <div class="apply-hero">
            <div class="pill">For job seekers</div>
            <h1>Get noticed by hiring businesses</h1>
            <p>Build a public profile that local businesses on CoreBooks can browse, save, and contact you from. Your details stay hidden until our team approves your submission.</p>
        </div>

        @if ($errors->any())
            <div class="alert">
                <strong>Please fix the following:</strong>
                <ul>
                    @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="card apply-card">
            <form method="post" action="{{ route('public.jobs.apply.submit') }}" enctype="multipart/form-data" class="apply-form" autocomplete="on">
                @csrf

                {{-- honeypot: bots fill all fields; real users don't see this --}}
                <div class="hp-trap" aria-hidden="true">
                    <label for="website">Website</label>
                    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
                </div>

                <h2 class="apply-section-title">Who you are</h2>
                <div class="field-grid">
                    <div class="field">
                        <label for="full_name">Full name *</label>
                        <input class="input" id="full_name" name="full_name" value="{{ old('full_name') }}" required maxlength="191">
                    </div>
                    <div class="field">
                        <label for="headline">Headline / role</label>
                        <input class="input" id="headline" name="headline" value="{{ old('headline') }}" maxlength="191" placeholder="e.g. Frontend developer · 3 yrs">
                    </div>
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label for="email">Email *</label>
                        <input class="input" id="email" name="email" type="email" value="{{ old('email') }}" required maxlength="191">
                    </div>
                    <div class="field">
                        <label for="phone">Phone *</label>
                        <input class="input" id="phone" name="phone" value="{{ old('phone') }}" required maxlength="32" placeholder="+234...">
                    </div>
                </div>

                <h2 class="apply-section-title">Where you'd work</h2>
                <div class="field-grid">
                    <div class="field">
                        <label for="location_state">State *</label>
                        <select class="input" id="location_state" name="location_state" required>
                            <option value="">— Select —</option>
                            @foreach ($states as $st)
                                <option value="{{ $st }}" @selected(old('location_state') === $st)>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="location_city">City / town</label>
                        <input class="input" id="location_city" name="location_city" value="{{ old('location_city') }}" maxlength="120">
                    </div>
                </div>

                <div class="checkbox-row">
                    <input type="hidden" name="open_to_relocate" value="0">
                    <input type="checkbox" name="open_to_relocate" id="open_to_relocate" value="1" @checked(old('open_to_relocate'))>
                    <label for="open_to_relocate">I'm open to relocating for the right opportunity</label>
                </div>

                <h2 class="apply-section-title">What you want</h2>
                <div class="field-grid">
                    <div class="field">
                        <label for="employment_type">Role type *</label>
                        <select class="input" id="employment_type" name="employment_type" required>
                            @foreach ($employmentTypes as $val => $label)
                                <option value="{{ $val }}" @selected(old('employment_type', 'full_time') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="years_experience">Years of experience</label>
                        <input class="input" id="years_experience" name="years_experience" type="number" min="0" max="80" value="{{ old('years_experience', 0) }}">
                    </div>
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label for="expected_salary_min">Expected salary — min</label>
                        <input class="input" id="expected_salary_min" name="expected_salary_min" type="number" min="0" step="1000" value="{{ old('expected_salary_min') }}" placeholder="e.g. 250000">
                    </div>
                    <div class="field">
                        <label for="expected_salary_max">Expected salary — max</label>
                        <input class="input" id="expected_salary_max" name="expected_salary_max" type="number" min="0" step="1000" value="{{ old('expected_salary_max') }}">
                    </div>
                </div>

                <div class="field-grid">
                    <div class="field">
                        <label for="salary_period">Period</label>
                        <select class="input" id="salary_period" name="salary_period">
                            @foreach (['' => '— Not stated —', 'hourly' => 'Hourly', 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'annually' => 'Annually'] as $val => $label)
                                <option value="{{ $val }}" @selected(old('salary_period') === $val)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label for="currency">Currency</label>
                        <input class="input" id="currency" name="currency" value="{{ old('currency', 'NGN') }}" maxlength="8">
                    </div>
                </div>

                <h2 class="apply-section-title">Tell us about yourself</h2>
                <div class="field">
                    <label for="about">About</label>
                    <textarea class="input" id="about" name="about" maxlength="4000" placeholder="A short summary of your experience and what you bring to a team.">{{ old('about') }}</textarea>
                </div>
                <div class="field">
                    <label for="skills">Top skills</label>
                    <textarea class="input" id="skills" name="skills" maxlength="1000" placeholder="Comma separated, e.g. Flutter, Sales, Customer support">{{ old('skills') }}</textarea>
                </div>
                <div class="field">
                    <label for="education">Education</label>
                    <textarea class="input" id="education" name="education" maxlength="1000">{{ old('education') }}</textarea>
                </div>
                <div class="field">
                    <label for="linkedin_url">LinkedIn URL</label>
                    <input class="input" id="linkedin_url" name="linkedin_url" type="url" value="{{ old('linkedin_url') }}" placeholder="https://...">
                </div>

                <h2 class="apply-section-title">Photo & CV</h2>
                <div class="field-grid">
                    <div class="field">
                        <label for="photo">Profile photo (optional — jpeg/png/webp, ≤5MB)</label>
                        <input class="input file-input" id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp">
                    </div>
                    <div class="field">
                        <label for="cv">CV * (pdf/doc/docx, ≤8MB)</label>
                        <input class="input file-input" id="cv" name="cv" type="file" required accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                    </div>
                </div>

                <div class="checkbox-row" style="margin-top:1rem;">
                    <input type="checkbox" name="consent" id="consent" value="1" required @checked(old('consent'))>
                    <label for="consent">
                        I agree that CoreBooks may show my profile to verified hiring businesses after admin review. I consent to be contacted via the email and phone I provided. I can request removal at any time by emailing <a href="mailto:support@salesapp.site">support@salesapp.site</a>.
                    </label>
                </div>

                <div class="submit-row">
                    <button type="submit" class="btn btn-primary">Submit application</button>
                    <a href="{{ route('public.jobs.status') }}" class="btn btn-ghost">Already applied? Check status</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
