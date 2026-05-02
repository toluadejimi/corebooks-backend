<?php

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Admin\Concerns\ResolvesWorkspace;
use App\Models\Business;
use App\Models\User;
use App\Services\TeamMemberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamWebController extends Controller
{
    use ResolvesWorkspace;

    public function __construct(
        private readonly TeamMemberService $teamMembers,
    ) {}

    public function index(Request $request, Business $business): View
    {
        $members = $business->users()->orderBy('name')->get();

        return view('admin.team.index', $this->workspace($request, $business) + [
            'members' => $members,
        ]);
    }

    public function store(Request $request, Business $business): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', 'string'],
        ]);

        try {
            $this->teamMembers->invite($business, $request->user(), $data);
        } catch (ValidationException $e) {
            return redirect()->route('admin.b.team.index', $business)->withErrors($e->errors())->withInput();
        }

        return redirect()->route('admin.b.team.index', $business)->with('status', 'Team member added.');
    }

    public function update(Request $request, Business $business, User $user): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string'],
        ]);

        try {
            $this->teamMembers->updateMemberRole($business, $request->user(), $user, $data['role']);
        } catch (\Throwable $e) {
            return redirect()->route('admin.b.team.index', $business)->withErrors(['role' => $e->getMessage()]);
        }

        return redirect()->route('admin.b.team.index', $business)->with('status', 'Role updated.');
    }

    public function destroy(Request $request, Business $business, User $user): RedirectResponse
    {
        try {
            $this->teamMembers->removeMember($business, $request->user(), $user);
        } catch (\Throwable $e) {
            return redirect()->route('admin.b.team.index', $business)->withErrors(['team' => $e->getMessage()]);
        }

        return redirect()->route('admin.b.team.index', $business)->with('status', 'Member removed from business.');
    }
}
