<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobSeeker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JobSeekerPlatformWebController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'pending');
        $state = (string) $request->query('state', '');
        $search = trim((string) $request->query('q', ''));

        $q = JobSeeker::query()->orderByDesc('created_at');

        if (in_array($status, ['pending', 'active', 'declined', 'hidden', 'archived'], true)) {
            $q->where('status', $status);
        }
        if ($state !== '' && in_array($state, JobSeeker::NIGERIAN_STATES, true)) {
            $q->where('location_state', $state);
        }
        if ($search !== '') {
            $like = '%'.str_replace('%', '\\%', $search).'%';
            $q->where(function ($qq) use ($like): void {
                $qq->where('full_name', 'like', $like)
                    ->orWhere('headline', 'like', $like)
                    ->orWhere('skills', 'like', $like)
                    ->orWhere('location_city', 'like', $like);
            });
        }

        $seekers = $q->paginate(30)->withQueryString();

        $counts = JobSeeker::query()
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        return view('admin.platform.job-seekers.index', [
            'user' => $request->user(),
            'seekers' => $seekers,
            'status' => $status,
            'state' => $state,
            'search' => $search,
            'counts' => $counts,
            'states' => JobSeeker::NIGERIAN_STATES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.platform.job-seekers.create', [
            'user' => $request->user(),
            'states' => JobSeeker::NIGERIAN_STATES,
            'employmentTypes' => JobSeeker::EMPLOYMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request, null);

        $photoUrl = $this->handleUpload($request, 'photo', 'job-seekers/photos', ['image']);
        $cvData = $this->handleCv($request);

        $seeker = JobSeeker::query()->create(array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'photo_url' => $photoUrl,
            'cv_url' => $cvData['url'],
            'cv_filename' => $cvData['filename'],
            'status' => $data['status'] ?? JobSeeker::STATUS_ACTIVE,
            'created_by_user_id' => $request->user()->id,
            'version' => 1,
        ]));

        return redirect()
            ->route('admin.platform.job-seekers.index')
            ->with('status', "Seeker '{$seeker->full_name}' added.");
    }

    public function edit(Request $request, JobSeeker $seeker): View
    {
        return view('admin.platform.job-seekers.edit', [
            'user' => $request->user(),
            'seeker' => $seeker,
            'states' => JobSeeker::NIGERIAN_STATES,
            'employmentTypes' => JobSeeker::EMPLOYMENT_TYPES,
        ]);
    }

    public function update(Request $request, JobSeeker $seeker): RedirectResponse
    {
        $data = $this->validateData($request, $seeker);

        if ($request->hasFile('photo')) {
            $seeker->photo_url = $this->handleUpload($request, 'photo', 'job-seekers/photos', ['image']);
        }
        if ($request->hasFile('cv')) {
            $cvData = $this->handleCv($request);
            $seeker->cv_url = $cvData['url'];
            $seeker->cv_filename = $cvData['filename'];
        }
        if ($request->boolean('remove_photo') && $seeker->photo_url) {
            $seeker->photo_url = null;
        }
        if ($request->boolean('remove_cv') && $seeker->cv_url) {
            $seeker->cv_url = null;
            $seeker->cv_filename = null;
        }

        $seeker->fill($data);
        $seeker->version = (int) $seeker->version + 1;
        $seeker->save();

        return redirect()
            ->route('admin.platform.job-seekers.index')
            ->with('status', 'Seeker updated.');
    }

    public function destroy(JobSeeker $seeker): RedirectResponse
    {
        $seeker->delete();

        return redirect()
            ->route('admin.platform.job-seekers.index')
            ->with('status', 'Seeker removed.');
    }

    public function approve(Request $request, JobSeeker $seeker): RedirectResponse
    {
        $seeker->status = JobSeeker::STATUS_ACTIVE;
        $seeker->rejection_reason = null;
        $seeker->save();

        return redirect()
            ->back()
            ->with('status', "{$seeker->full_name} approved — now visible to businesses.");
    }

    public function decline(Request $request, JobSeeker $seeker): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $seeker->status = JobSeeker::STATUS_DECLINED;
        $seeker->rejection_reason = $data['rejection_reason'] ?? null;
        $seeker->save();

        return redirect()
            ->back()
            ->with('status', "{$seeker->full_name}'s application was declined.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?JobSeeker $existing): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:191'],
            'headline' => ['nullable', 'string', 'max:191'],
            'email' => ['nullable', 'email', 'max:191'],
            'phone' => ['nullable', 'string', 'max:32'],
            'location_state' => ['required', 'string', 'max:64'],
            'location_city' => ['nullable', 'string', 'max:120'],
            'open_to_relocate' => ['nullable', 'boolean'],
            'years_experience' => ['nullable', 'integer', 'min:0', 'max:80'],
            'employment_type' => ['required', 'string', 'in:full_time,part_time,contract,internship,temporary'],
            'expected_salary_min' => ['nullable', 'numeric', 'min:0'],
            'expected_salary_max' => ['nullable', 'numeric', 'min:0'],
            'salary_period' => ['nullable', 'string', 'in:hourly,daily,weekly,monthly,annually'],
            'currency' => ['nullable', 'string', 'max:8'],
            'about' => ['nullable', 'string', 'max:4000'],
            'skills' => ['nullable', 'string', 'max:1000'],
            'education' => ['nullable', 'string', 'max:1000'],
            'linkedin_url' => ['nullable', 'url', 'max:500'],
            'status' => ['nullable', 'string', 'in:pending,active,declined,hidden,archived'],
            'photo' => ['nullable', 'file', 'image', 'mimes:jpeg,png,webp', 'max:5120'],
            'cv' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:8192'],
        ]);
    }

    /**
     * @param  array<int, string>  $kind  ['image'] forces image rule check (already in validate); kept for future.
     */
    private function handleUpload(Request $request, string $field, string $folder, array $kind): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }
        $file = $request->file($field);
        $path = $file->store($folder, 'public');

        return $this->absoluteUrl($request, $path);
    }

    /**
     * @return array{url:?string, filename:?string}
     */
    private function handleCv(Request $request): array
    {
        if (! $request->hasFile('cv')) {
            return ['url' => null, 'filename' => null];
        }
        $file = $request->file('cv');
        $path = $file->store('job-seekers/cvs', 'public');

        return [
            'url' => $this->absoluteUrl($request, $path),
            'filename' => $file->getClientOriginalName(),
        ];
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
