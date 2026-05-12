<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraService;
use App\Support\ExtraServiceFormDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ExtraServiceWebController extends Controller
{
    public function index(Request $request): View
    {
        $services = ExtraService::query()->orderBy('sort_order')->orderBy('title')->get();

        return view('admin.platform.extra-services.index', [
            'user' => $request->user(),
            'services' => $services,
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.platform.extra-services.form', [
            'user' => $request->user(),
            'service' => new ExtraService([
                'sort_order' => 100,
                'is_active' => true,
                'fee_amount_ngn' => 0,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ExtraService::query()->create($this->validated($request));

        return redirect()->route('admin.platform.extra-services.index')->with('status', 'Service created.');
    }

    public function edit(Request $request, ExtraService $extraService): View
    {
        return view('admin.platform.extra-services.form', [
            'user' => $request->user(),
            'service' => $extraService,
        ]);
    }

    public function update(Request $request, ExtraService $extraService): RedirectResponse
    {
        $extraService->update($this->validated($request, $extraService->id));

        return redirect()->route('admin.platform.extra-services.index')->with('status', 'Service updated.');
    }

    public function destroy(Request $request, ExtraService $extraService): RedirectResponse
    {
        if ($extraService->applications()->exists()) {
            return back()->withErrors(['service' => 'Applications exist for this service. Deactivate it instead of deleting.']);
        }
        $extraService->delete();

        return redirect()->route('admin.platform.extra-services.index')->with('status', 'Service removed.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $slugRule = ['required', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'];
        $slugRule[] = $ignoreId !== null
            ? 'unique:extra_services,slug,'.$ignoreId
            : 'unique:extra_services,slug';

        $data = $request->validate([
            'slug' => $slugRule,
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:8000'],
            'requirements' => ['nullable', 'string', 'max:16000'],
            'icon_url' => ['nullable', 'string', 'max:2048'],
            'application_form_json' => ['nullable', 'string', 'max:65000'],
            'fee_amount_ngn' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $applicationForm = null;
        $rawForm = $data['application_form_json'] ?? null;
        if (is_string($rawForm) && trim($rawForm) !== '') {
            $decoded = json_decode($rawForm, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'application_form_json' => ['Invalid JSON: '.json_last_error_msg()],
                ]);
            }
            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'application_form_json' => ['Application form must be a JSON array.'],
                ]);
            }
            ExtraServiceFormDefinition::assertValidDefinition($decoded);
            $applicationForm = $decoded;
        }

        return [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'requirements' => ($data['requirements'] ?? '') !== '' ? $data['requirements'] : null,
            'icon_url' => ($data['icon_url'] ?? '') !== '' ? $data['icon_url'] : null,
            'application_form' => $applicationForm,
            'fee_amount_ngn' => $data['fee_amount_ngn'],
            'sort_order' => (int) $data['sort_order'],
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
