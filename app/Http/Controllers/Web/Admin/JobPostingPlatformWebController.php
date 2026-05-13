<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class JobPostingPlatformWebController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', 'all');
        $state = (string) $request->query('state', '');
        $search = trim((string) $request->query('q', ''));

        $q = JobPosting::query()
            ->with(['submitterBusiness:id,uuid,name', 'submittedByUser:id,email', 'approvedByUser:id,email'])
            ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
            ->orderByDesc('created_at');

        if (in_array($status, ['pending', 'approved', 'rejected', 'closed'], true)) {
            $q->where('status', $status);
        }
        if ($state !== '' && in_array($state, JobPosting::NIGERIAN_STATES, true)) {
            $q->where('location_state', $state);
        }
        if ($search !== '') {
            $like = '%'.str_replace('%', '\\%', $search).'%';
            $q->where(function ($qq) use ($like): void {
                $qq->where('title', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('location_city', 'like', $like);
            });
        }

        $jobs = $q->paginate(30)->withQueryString();

        $counts = JobPosting::query()
            ->selectRaw("status, COUNT(*) as c")
            ->groupBy('status')
            ->pluck('c', 'status')
            ->toArray();

        return view('admin.platform.jobs.index', [
            'user' => $request->user(),
            'jobs' => $jobs,
            'status' => $status,
            'state' => $state,
            'search' => $search,
            'counts' => $counts,
            'states' => JobPosting::NIGERIAN_STATES,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.platform.jobs.create', [
            'user' => $request->user(),
            'states' => JobPosting::NIGERIAN_STATES,
            'employmentTypes' => JobPosting::EMPLOYMENT_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateData($request);

        JobPosting::query()->create(array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'source' => JobPosting::SOURCE_ADMIN,
            'status' => JobPosting::STATUS_APPROVED,
            'approved_by_user_id' => $request->user()->id,
            'approved_at' => now(),
            'version' => 1,
        ]));

        return redirect()
            ->route('admin.platform.jobs.index')
            ->with('status', 'Job posted to the public feed.');
    }

    public function edit(Request $request, JobPosting $job): View
    {
        return view('admin.platform.jobs.edit', [
            'user' => $request->user(),
            'job' => $job,
            'states' => JobPosting::NIGERIAN_STATES,
            'employmentTypes' => JobPosting::EMPLOYMENT_TYPES,
        ]);
    }

    public function update(Request $request, JobPosting $job): RedirectResponse
    {
        $data = $this->validateData($request);

        $job->fill($data);
        $job->version = (int) $job->version + 1;
        $job->save();

        return redirect()
            ->route('admin.platform.jobs.index')
            ->with('status', 'Job updated.');
    }

    public function destroy(JobPosting $job): RedirectResponse
    {
        $job->delete();

        return redirect()
            ->route('admin.platform.jobs.index')
            ->with('status', 'Job deleted.');
    }

    public function approve(Request $request, JobPosting $job): RedirectResponse
    {
        if ($job->status === JobPosting::STATUS_APPROVED) {
            return redirect()->back()->with('status', 'Already approved.');
        }
        $job->status = JobPosting::STATUS_APPROVED;
        $job->approved_by_user_id = $request->user()->id;
        $job->approved_at = now();
        $job->rejection_reason = null;
        $job->version = (int) $job->version + 1;
        $job->save();

        return redirect()
            ->route('admin.platform.jobs.index', ['status' => 'pending'])
            ->with('status', 'Vacancy approved — now visible on the mobile feed.');
    }

    public function reject(Request $request, JobPosting $job): RedirectResponse
    {
        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'max:1000'],
        ]);
        $job->status = JobPosting::STATUS_REJECTED;
        $job->rejection_reason = trim($data['rejection_reason']);
        $job->approved_by_user_id = $request->user()->id;
        $job->version = (int) $job->version + 1;
        $job->save();

        return redirect()
            ->route('admin.platform.jobs.index', ['status' => 'pending'])
            ->with('status', 'Vacancy rejected — submitter will see the reason.');
    }

    public function close(JobPosting $job): RedirectResponse
    {
        $job->status = JobPosting::STATUS_CLOSED;
        $job->version = (int) $job->version + 1;
        $job->save();

        return redirect()->back()->with('status', 'Vacancy closed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:191'],
            'company_name' => ['required', 'string', 'max:191'],
            'description' => ['required', 'string', 'max:8000'],
            'location_state' => ['required', 'string', 'max:64'],
            'location_city' => ['nullable', 'string', 'max:120'],
            'employment_type' => ['required', 'string', 'in:full_time,part_time,contract,internship,temporary'],
            'salary_min' => ['nullable', 'numeric', 'min:0'],
            'salary_max' => ['nullable', 'numeric', 'min:0'],
            'salary_period' => ['nullable', 'string', 'in:hourly,daily,weekly,monthly,annually'],
            'currency' => ['nullable', 'string', 'max:8'],
            'contact_email' => ['nullable', 'email', 'max:191'],
            'contact_phone' => ['nullable', 'string', 'max:32'],
            'apply_url' => ['nullable', 'url', 'max:500'],
            'expires_at' => ['nullable', 'date'],
        ]);
    }
}
