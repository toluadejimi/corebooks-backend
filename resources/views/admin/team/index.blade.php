@extends('layouts.admin-workspace')

@section('title', 'Team — '.$business->name)

@section('content')
<h1 class="adm-page-title">Team</h1>
<p class="adm-page-desc">Members and roles for this business. Owners can assign any role; managers can add managers and sales.</p>

<div class="adm-table-wrap" style="margin-bottom:2rem;">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                @if($canManage)<th></th>@endif
            </tr>
        </thead>
        <tbody>
            @foreach ($members as $m)
                @php($r = \App\Enums\BusinessRole::normalize($m->pivot->role))
                <tr>
                    <td><strong>{{ $m->name }}</strong></td>
                    <td style="color:var(--adm-muted);">{{ $m->email }}</td>
                    <td>
                        @if($canManage && $m->id !== $user->id)
                            <form method="post" action="{{ route('admin.b.team.update', [$business, $m]) }}" class="adm-actions" style="gap:0.35rem;">
                                @csrf @method('PATCH')
                                <select class="adm-select" name="role" style="width:auto;min-width:120px;padding:0.4rem;font-size:0.8rem;">
                                    @foreach (\App\Enums\BusinessRole::assignableBy($memberRole) as $opt)
                                        <option value="{{ $opt }}" @selected($r->value === $opt)>{{ $opt }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="adm-btn adm-btn-ghost" style="padding:0.4rem 0.65rem;font-size:0.8rem;">Update</button>
                            </form>
                        @else
                            <span class="adm-role-pill" style="background:var(--adm-accent-soft);color:var(--adm-accent);">{{ $r->value }}</span>
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
    <div class="adm-card" style="max-width:520px;">
        <h2 style="font-family:Outfit,sans-serif;font-size:1.1rem;margin:0 0 1rem;">Invite member</h2>
        <form method="post" action="{{ route('admin.b.team.store', $business) }}">
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
                    <select class="adm-select" id="t_role" name="role">
                        @foreach (\App\Enums\BusinessRole::assignableBy($memberRole) as $opt)
                            <option value="{{ $opt }}" @selected(old('role') === $opt || (!old('role') && $opt === 'sales'))>{{ $opt }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary">Add member</button>
        </form>
    </div>
@else
    <p style="color:var(--adm-muted);">You have read-only visibility here. Ask an owner or manager to change roles.</p>
@endif
@endsection
