<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraServiceApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExtraServiceApplicationPlatformWebController extends Controller
{
    public function index(Request $request): View
    {
        $applications = ExtraServiceApplication::query()
            ->with(['business', 'extraService'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return view('admin.platform.extra-service-applications.index', [
            'user' => $request->user(),
            'applications' => $applications,
        ]);
    }

    public function show(Request $request, ExtraServiceApplication $extraServiceApplication): View
    {
        $extraServiceApplication->load(['business', 'extraService']);

        return view('admin.platform.extra-service-applications.show', [
            'user' => $request->user(),
            'application' => $extraServiceApplication,
        ]);
    }

    public function update(Request $request, ExtraServiceApplication $extraServiceApplication): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:pending,in_progress,completed,rejected'],
            'admin_notes' => ['nullable', 'string', 'max:8000'],
        ]);

        $extraServiceApplication->update($data);

        return redirect()->route('admin.platform.extra-service-applications.show', $extraServiceApplication)->with('status', 'Application updated.');
    }
}
