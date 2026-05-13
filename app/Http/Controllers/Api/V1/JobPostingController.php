<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\JobPosting;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class JobPostingController extends Controller
{
    /**
     * Public-ish feed of approved vacancies for the mobile app.
     * Supports `?state=Lagos&q=manager&type=full_time&page=1`.
     */
    public function index(Request $request): JsonResponse
    {
        $state = trim((string) $request->query('state', ''));
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);

        $q = JobPosting::query()
            ->where('status', JobPosting::STATUS_APPROVED)
            ->where(function ($qq): void {
                $qq->whereNull('expires_at')->orWhere('expires_at', '>=', now()->toDateString());
            })
            ->orderByDesc('approved_at');

        if ($state !== '') {
            $q->where('location_state', $state);
        }
        if (in_array($type, ['full_time', 'part_time', 'contract', 'internship', 'temporary'], true)) {
            $q->where('employment_type', $type);
        }
        if ($search !== '') {
            $like = '%'.str_replace('%', '\\%', $search).'%';
            $q->where(function ($qq) use ($like): void {
                $qq->where('title', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('location_city', 'like', $like);
            });
        }

        $page = $q->paginate($perPage);
        $page->setCollection($page->getCollection()->map(fn (JobPosting $j) => $this->present($j)));

        return response()->json([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'has_more' => $page->hasMorePages(),
            ],
            'filters' => [
                'states' => JobPosting::NIGERIAN_STATES,
                'employment_types' => JobPosting::EMPLOYMENT_TYPES,
            ],
        ]);
    }

    public function show(string $jobUuid): JsonResponse
    {
        $job = JobPosting::query()
            ->where('uuid', $jobUuid)
            ->where('status', JobPosting::STATUS_APPROVED)
            ->first();

        if ($job === null) {
            return response()->json(['message' => 'Job not found.'], 404);
        }

        return response()->json(['data' => $this->present($job)]);
    }

    /**
     * List this business's own job submissions (any status), so the requester can see review progress.
     */
    public function businessIndex(Business $business): JsonResponse
    {
        $rows = JobPosting::query()
            ->where('submitted_by_business_id', $business->id)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (JobPosting $j) => $this->present($j, includeReviewMeta: true)),
        ]);
    }

    /**
     * Submit a vacancy request from a business. Always lands in `pending` status.
     */
    public function businessStore(Request $request, Business $business): JsonResponse
    {
        $data = $request->validate([
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

        $job = JobPosting::query()->create(array_merge($data, [
            'uuid' => (string) Str::uuid(),
            'source' => JobPosting::SOURCE_BUSINESS,
            'status' => JobPosting::STATUS_PENDING,
            'submitted_by_business_id' => $business->id,
            'submitted_by_user_id' => $request->user()->id,
            'currency' => $data['currency'] ?? 'NGN',
            'version' => 1,
        ]));

        return response()->json([
            'data' => $this->present($job, includeReviewMeta: true),
            'message' => 'Vacancy submitted — it will appear on the mobile feed once a platform admin approves it.',
        ], 201);
    }

    public function businessDestroy(Business $business, string $jobUuid): JsonResponse
    {
        try {
            $job = JobPosting::query()
                ->where('submitted_by_business_id', $business->id)
                ->where('uuid', $jobUuid)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Vacancy not found in this workspace.'], 404);
        }

        if ($job->status === JobPosting::STATUS_APPROVED) {
            return response()->json([
                'message' => 'This vacancy is already live. Ask a platform admin to close or delete it.',
            ], 422);
        }

        $job->delete();

        return response()->json(['data' => ['uuid' => $jobUuid, 'deleted' => true]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(JobPosting $j, bool $includeReviewMeta = false): array
    {
        $payload = [
            'uuid' => $j->uuid,
            'title' => $j->title,
            'company_name' => $j->company_name,
            'description' => $j->description,
            'location_state' => $j->location_state,
            'location_city' => $j->location_city,
            'employment_type' => $j->employment_type,
            'employment_type_label' => JobPosting::EMPLOYMENT_TYPES[$j->employment_type] ?? $j->employment_type,
            'salary_min' => $j->salary_min !== null ? (float) $j->salary_min : null,
            'salary_max' => $j->salary_max !== null ? (float) $j->salary_max : null,
            'salary_period' => $j->salary_period,
            'currency' => $j->currency,
            'contact_email' => $j->contact_email,
            'contact_phone' => $j->contact_phone,
            'apply_url' => $j->apply_url,
            'expires_at' => $j->expires_at?->toDateString(),
            'posted_at' => ($j->approved_at ?? $j->created_at)?->toIso8601String(),
            'source' => $j->source,
        ];
        if ($includeReviewMeta) {
            $payload['status'] = $j->status;
            $payload['rejection_reason'] = $j->rejection_reason;
            $payload['created_at'] = $j->created_at?->toIso8601String();
            $payload['approved_at'] = $j->approved_at?->toIso8601String();
        }

        return $payload;
    }
}
