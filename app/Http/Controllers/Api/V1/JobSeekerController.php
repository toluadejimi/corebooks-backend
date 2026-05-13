<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\JobSeeker;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobSeekerController extends Controller
{
    /**
     * Paginated, filterable list of active seekers for the mobile "Job seekers" tab.
     * The route is gated to authenticated business members; we still mark seekers as
     * shortlisted-by-this-business so the UI can show a filled bookmark.
     */
    public function index(Request $request, Business $business): JsonResponse
    {
        $state = trim((string) $request->query('state', ''));
        $type = trim((string) $request->query('type', ''));
        $search = trim((string) $request->query('q', ''));
        $perPage = min(max((int) $request->query('per_page', 30), 1), 100);

        $q = JobSeeker::query()
            ->where('status', JobSeeker::STATUS_ACTIVE)
            ->orderByDesc('created_at');

        if ($state !== '') {
            $q->where('location_state', $state);
        }
        if (in_array($type, ['full_time', 'part_time', 'contract', 'internship', 'temporary'], true)) {
            $q->where('employment_type', $type);
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

        $page = $q->paginate($perPage);

        $shortlistedIds = $business->shortlistedSeekers()
            ->whereIn('job_seekers.id', collect($page->items())->pluck('id'))
            ->pluck('job_seekers.id')
            ->all();
        $shortlistedSet = array_flip($shortlistedIds);

        $page->setCollection(
            $page->getCollection()->map(fn (JobSeeker $s) => $this->present($s, isset($shortlistedSet[$s->id])))
        );

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
                'states' => JobSeeker::NIGERIAN_STATES,
                'employment_types' => JobSeeker::EMPLOYMENT_TYPES,
            ],
        ]);
    }

    public function show(Business $business, string $seekerUuid): JsonResponse
    {
        $seeker = JobSeeker::query()
            ->where('uuid', $seekerUuid)
            ->where('status', JobSeeker::STATUS_ACTIVE)
            ->first();

        if ($seeker === null) {
            return response()->json(['message' => 'Seeker not found.'], 404);
        }

        $isShortlisted = $business->shortlistedSeekers()
            ->where('job_seekers.id', $seeker->id)
            ->exists();

        return response()->json(['data' => $this->present($seeker, $isShortlisted)]);
    }

    /** List this business's shortlist (most recent first). */
    public function shortlist(Business $business): JsonResponse
    {
        $rows = $business->shortlistedSeekers()
            ->orderByDesc('business_seeker_shortlists.created_at')
            ->limit(500)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (JobSeeker $s) => $this->present($s, true)),
        ]);
    }

    public function shortlistStore(Request $request, Business $business, string $seekerUuid): JsonResponse
    {
        try {
            $seeker = JobSeeker::query()
                ->where('uuid', $seekerUuid)
                ->where('status', JobSeeker::STATUS_ACTIVE)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Seeker not available.'], 404);
        }

        $note = $request->input('note');
        $business->shortlistedSeekers()->syncWithoutDetaching([
            $seeker->id => [
                'note' => is_string($note) ? mb_substr(trim($note), 0, 500) : null,
                'added_by_user_id' => $request->user()?->id,
            ],
        ]);

        return response()->json(['data' => $this->present($seeker, true)], 201);
    }

    public function shortlistDestroy(Business $business, string $seekerUuid): JsonResponse
    {
        $seeker = JobSeeker::query()->where('uuid', $seekerUuid)->first();
        if ($seeker === null) {
            return response()->json(['message' => 'Seeker not found.'], 404);
        }
        $business->shortlistedSeekers()->detach($seeker->id);

        return response()->json(['data' => ['uuid' => $seekerUuid, 'shortlisted' => false]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(JobSeeker $s, bool $isShortlisted): array
    {
        return [
            'uuid' => $s->uuid,
            'full_name' => $s->full_name,
            'headline' => $s->headline,
            'email' => $s->email,
            'phone' => $s->phone,
            'photo_url' => $s->photo_url,
            'cv_url' => $s->cv_url,
            'cv_filename' => $s->cv_filename,
            'location_state' => $s->location_state,
            'location_city' => $s->location_city,
            'open_to_relocate' => (bool) $s->open_to_relocate,
            'years_experience' => (int) $s->years_experience,
            'employment_type' => $s->employment_type,
            'employment_type_label' => JobSeeker::EMPLOYMENT_TYPES[$s->employment_type] ?? $s->employment_type,
            'expected_salary_min' => $s->expected_salary_min !== null ? (float) $s->expected_salary_min : null,
            'expected_salary_max' => $s->expected_salary_max !== null ? (float) $s->expected_salary_max : null,
            'salary_period' => $s->salary_period,
            'currency' => $s->currency,
            'about' => $s->about,
            'skills' => $s->skills,
            'education' => $s->education,
            'linkedin_url' => $s->linkedin_url,
            'is_shortlisted' => $isShortlisted,
            'created_at' => $s->created_at?->toIso8601String(),
        ];
    }
}
