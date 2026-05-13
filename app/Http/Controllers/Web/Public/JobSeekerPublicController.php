<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use App\Models\JobSeeker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public-facing job seeker self-submission.
 *
 *   GET  /jobs/apply              public form
 *   POST /jobs/apply              create a pending seeker profile + return tracking token
 *   GET  /jobs/status             form to enter tracking token (or token via query)
 *   GET  /jobs/status/{token}     show review status
 *
 * Anti-abuse measures:
 *   - per-IP rate limit (3 submissions / hour, 12 / day)
 *   - honeypot field "website" must be empty
 *   - strict file mime/size validation (images: 5MB, CV: 8MB pdf/doc/docx)
 *   - default status is `pending` and is hidden from the mobile feed until an admin approves
 *   - tracking_token is a 48-char random hex used for status lookup
 *
 * Submitted contacts (email/phone) become visible to businesses ONLY after admin approval.
 */
class JobSeekerPublicController extends Controller
{
    public function showForm(Request $request): View
    {
        return view('public.jobs.apply', [
            'states' => JobSeeker::NIGERIAN_STATES,
            'employmentTypes' => JobSeeker::EMPLOYMENT_TYPES,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        if ($request->filled('website')) {
            return redirect()->route('public.jobs.apply')
                ->withErrors(['form' => 'Submission flagged. Please try again.']);
        }

        $rateKey = 'jobs-apply:'.$request->ip();
        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            $seconds = RateLimiter::availableIn($rateKey);

            return redirect()->route('public.jobs.apply')
                ->withInput($request->except(['photo', 'cv']))
                ->withErrors([
                    'form' => 'Too many submissions from this network. Try again in '.max(1, (int) ceil($seconds / 60)).' minute(s).',
                ]);
        }
        $dailyKey = 'jobs-apply-day:'.$request->ip();
        if (RateLimiter::tooManyAttempts($dailyKey, 12)) {
            return redirect()->route('public.jobs.apply')
                ->withInput($request->except(['photo', 'cv']))
                ->withErrors(['form' => 'Daily submission limit reached. Please come back tomorrow.']);
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:191'],
            'headline' => ['nullable', 'string', 'max:191'],
            'email' => ['required', 'email', 'max:191'],
            'phone' => ['required', 'string', 'min:7', 'max:32'],
            'location_state' => ['required', 'string', 'max:64', 'in:'.implode(',', JobSeeker::NIGERIAN_STATES)],
            'location_city' => ['nullable', 'string', 'max:120'],
            'open_to_relocate' => ['nullable', 'boolean'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'employment_type' => ['required', 'string', 'in:full_time,part_time,contract,internship,temporary'],
            'expected_salary_min' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'expected_salary_max' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'salary_period' => ['nullable', 'string', 'in:hourly,daily,weekly,monthly,annually'],
            'currency' => ['nullable', 'string', 'max:8'],
            'about' => ['nullable', 'string', 'max:4000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'education' => ['nullable', 'string', 'max:1000'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'cv' => ['required', 'file', 'mimes:pdf,doc,docx', 'max:8192'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'You must agree to the terms before submitting.',
        ]);

        RateLimiter::hit($rateKey, 3600);
        RateLimiter::hit($dailyKey, 86400);

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('job-seekers/photos', 'public');
            $photoUrl = $this->absoluteUrl($request, $path);
        }
        $cvUrl = null;
        $cvFilename = null;
        if ($request->hasFile('cv')) {
            $cvFile = $request->file('cv');
            $cvPath = $cvFile->store('job-seekers/cvs', 'public');
            $cvUrl = $this->absoluteUrl($request, $cvPath);
            $cvFilename = $cvFile->getClientOriginalName();
        }

        $token = bin2hex(random_bytes(24)); // 48 hex chars
        $seeker = JobSeeker::query()->create(array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'photo_url' => $photoUrl,
            'cv_url' => $cvUrl,
            'cv_filename' => $cvFilename,
            'status' => JobSeeker::STATUS_PENDING,
            'submitted_via' => JobSeeker::SUBMITTED_VIA_PUBLIC,
            'tracking_token' => $token,
            'applied_at' => now(),
            'applicant_ip' => $request->ip(),
            'applicant_user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'currency' => $data['currency'] ?? 'NGN',
            'version' => 1,
        ]));

        return redirect()
            ->route('public.jobs.status.show', ['token' => $token])
            ->with('status', 'Thanks '.$seeker->full_name.' — your application is in review.');
    }

    public function statusLanding(Request $request): View|RedirectResponse
    {
        $token = trim((string) $request->query('token', ''));
        if ($token !== '' && preg_match('/^[a-f0-9]{16,64}$/i', $token)) {
            return redirect()->route('public.jobs.status.show', ['token' => $token]);
        }

        return view('public.jobs.status_lookup');
    }

    public function statusShow(Request $request, string $token): View
    {
        $seeker = JobSeeker::query()->where('tracking_token', $token)->first();

        $shortlistCount = 0;
        if ($seeker !== null && $seeker->status === JobSeeker::STATUS_ACTIVE) {
            $shortlistCount = $seeker->businessesShortlisting()->count();
        }

        return view('public.jobs.status', [
            'seeker' => $seeker,
            'token' => $token,
            'shortlistCount' => $shortlistCount,
        ]);
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        $relative = Storage::disk('public')->url($path);
        if (str_starts_with($relative, 'http')) {
            return $relative;
        }

        return rtrim($request->root() ?: (string) config('app.url'), '/').'/'.ltrim($relative, '/');
    }
}
