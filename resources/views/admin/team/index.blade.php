@extends('layouts.admin-workspace')

@section('title', 'Team — '.$business->name)

@section('content')
<h1 class="adm-page-title">Team</h1>
<p class="adm-page-desc">Members and roles for this business. <strong>Sales</strong> staff must be assigned to a branch. Owners can assign any role; managers can add managers and sales.</p>

<div class="adm-table-wrap" style="margin-bottom:2rem;">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Branch</th>
                @if($canManage)<th></th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $m)
                @php($r = \App\Enums\BusinessRole::normalize($m->pivot->role))
                @php($lid = $m->pivot->location_id ?? null)
                @php($loc = $lid ? ($locById->get($lid) ?? null) : null)
                <tr>
                    <td><strong>{{ $m->name }}</strong></td>
                    <td style="color:var(--adm-muted);">{{ $m->email }}</td>
                    <td>
                        @if($canManage && $m->id !== $user->id)
                            <form method="post" action="{{ route('admin.b.team.update', [$business, $m]) }}" class="adm-actions team-member-form" style="gap:0.35rem;flex-wrap:wrap;" data-team-role-branch>
                                @csrf @method('PATCH')
                                <select class="adm-select team-role-select" name="role" style="width:auto;min-width:120px;padding:0.4rem;font-size:0.8rem;">
                                    @foreach (\App\Enums\BusinessRole::assignableBy($memberRole) as $opt)
                                        <option value="{{ $opt }}" @selected($r->value === $opt)>{{ $opt }}</option>
                                    @endforeach
                                </select>
                                <span class="team-branch-wrap" data-branch-wrap style="{{ $r === \App\Enums\BusinessRole::Sales ? '' : 'display:none;' }}">
                                    <select class="adm-select" name="location_uuid" style="width:auto;min-width:140px;padding:0.4rem;font-size:0.8rem;" @if($r === \App\Enums\BusinessRole::Sales) required @endif>
                                        <option value="">— Select branch —</option>
                                        @foreach ($locations as $branch)
                                            <option value="{{ $branch->uuid }}" @selected($loc && $loc->uuid === $branch->uuid)>{{ $branch->name }}</option>
                                        @endforeach
                                    </select>
                                </span>
                                <button type="submit" class="adm-btn adm-btn-ghost" style="padding:0.4rem 0.65rem;font-size:0.8rem;">Update</button>
                            </form>
                        @else
                            <span class="adm-role-pill" style="background:var(--adm-accent-soft);color:var(--adm-accent);">{{ $r->value }}</span>
                        @endif
                    </td>
                    <td style="color:var(--adm-muted);">
                        @if($r === \App\Enums\BusinessRole::Sales)
                            {{ $loc?->name ?? '— not assigned —' }}
                        @else
                            —
                        @endif
                    </td>
                    @if($canManage)
                        <td>
                            @if($m->id !== $user->id)
                                <form method="post" action="{{ route('admin.b.team.destroy', [$business, $m]) }}" onsubmit="return confirm('Remove this member from the business?');" style="display:inline;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.35rem 0.65rem;font-size:0.8rem;">Remove</button>
                                </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

@if($canManage)
    <div class="adm-card" style="max-width:560px;">
        <h2 style="font-family:Outfit,sans-serif;font-size:1.1rem;margin:0 0 1rem;">Invite member</h2>
        <form method="post" action="{{ route('admin.b.team.store', $business) }}" id="team-invite-form" data-team-role-branch>
            @csrf
            <div class="adm-grid cols-2">
                <div class="adm-field">
                    <label class="adm-label" for="t_name">Name</label>
                    <input class="adm-input" id="t_name" name="name" required value="{{ old('name') }}">
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="t_email">Email</label>
                    <input class="adm-input" id="t_email" name="email" type="email" required value="{{ old('email') }}">
                </div>
            </div>
            <div class="adm-grid cols-2">
                <div class="adm-field">
                    <label class="adm-label" for="t_password">Password (new users)</label>
                    <input class="adm-input" id="t_password" name="password" type="password" placeholder="Required if email is new">
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="t_role">Role</label>
                    <select class="adm-select team-role-select" id="t_role" name="role">
                        @foreach (\App\Enums\BusinessRole::assignableBy($memberRole) as $opt)
                            <option value="{{ $opt }}" @selected(old('role', 'sales') === $opt)>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="adm-field team-branch-wrap" id="invite-branch-wrap" data-branch-wrap>
                <label class="adm-label" for="t_location_uuid">Branch</label>
                @if($locations->isEmpty())
                    <p class="adm-page-desc" style="margin:0;color:var(--adm-danger,#dc2626);">Add a branch under Settings before inviting sales staff.</p>
                @else
                    <select class="adm-select" id="t_location_uuid" name="location_uuid">
                        <option value="">— Select branch —</option>
                        @foreach ($locations as $branch)
                            <option value="{{ $branch->uuid }}" @selected(old('location_uuid') === $branch->uuid)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                    <p class="adm-page-desc" style="margin:0.35rem 0 0;font-size:0.78rem;">Required for sales staff — they can only sell at this branch.</p>
                @endif
            </div>
            <button type="submit" class="adm-btn adm-btn-primary">Add member</button>
        </form>
    </div>
@else
    <p style="color:var(--adm-muted);">You have read-only visibility here. Ask an owner or manager to change roles.</p>
@endif

@if($canManage)
<script>
(function () {
    function syncBranchField(form) {
        const role = form.querySelector('.team-role-select, [name=role]');
        const wrap = form.querySelector('[data-branch-wrap]');
        const branch = form.querySelector('[name=location_uuid]');
        if (!role || !wrap) return;
        const isSales = role.value === 'sales';
        wrap.style.display = isSales ? '' : 'none';
        if (branch) branch.required = isSales;
    }

    document.querySelectorAll('[data-team-role-branch]').forEach(function (form) {
        const role = form.querySelector('.team-role-select, [name=role]');
        if (role) role.addEventListener('change', function () { syncBranchField(form); });
        syncBranchField(form);
    });
})();
</script>
@endif
@endsection
