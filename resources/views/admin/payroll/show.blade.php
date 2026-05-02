@extends('layouts.admin-workspace')

@section('title', 'Payroll '.($run->period_on?->format('F Y') ?? '').' — '.$business->name)

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.b.payroll.index', $business) }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Payroll</a></p>
<h1 class="adm-page-title">Payroll — {{ $run->period_on?->format('F Y') }}</h1>
<p class="adm-page-desc">Status: <strong>{{ $run->status }}</strong>@if($run->status === 'draft' && $canManage) · Draft: edit lines, then finalise.@endif</p>

@if (session('status'))
    <div class="adm-flash ok">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="adm-flash err">@foreach ($errors->all() as $e){{ $e }}@if(!$loop->last)<br>@endif @endforeach</div>
@endif

@if($canManage && $run->status === 'draft' && $members->isNotEmpty())
    <div class="adm-card" style="margin-bottom:1.25rem;">
        <h2 class="adm-page-title" style="font-size:1.05rem;">Add or update employee line</h2>
        <form method="post" action="{{ route('admin.b.payroll.lines.store', [$business, $run]) }}" style="display:grid;gap:0.75rem;">
            @csrf
            <div class="adm-field">
                <label class="adm-label" for="user_id">Team member</label>
                <select class="adm-select" id="user_id" name="user_id" required>
                    <option value="">— Select —</option>
                    @foreach ($members as $m)
                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->email }})</option>
                    @endforeach
                </select>
            </div>
            <div class="adm-grid cols-2">
                <div class="adm-field">
                    <label class="adm-label" for="basic_salary">Basic (monthly)</label>
                    <input class="adm-input" id="basic_salary" name="basic_salary" type="number" step="0.01" min="0" required value="{{ old('basic_salary') }}">
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="housing_allowance">Housing</label>
                    <input class="adm-input" id="housing_allowance" name="housing_allowance" type="number" step="0.01" min="0" value="{{ old('housing_allowance', 0) }}">
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="transport_allowance">Transport</label>
                    <input class="adm-input" id="transport_allowance" name="transport_allowance" type="number" step="0.01" min="0" value="{{ old('transport_allowance', 0) }}">
                </div>
                <div class="adm-field">
                    <label class="adm-label" for="other_allowances">Other allowances</label>
                    <input class="adm-input" id="other_allowances" name="other_allowances" type="number" step="0.01" min="0" value="{{ old('other_allowances', 0) }}">
                </div>
            </div>
            <button type="submit" class="adm-btn adm-btn-primary" style="justify-self:start;">Save line</button>
        </form>
    </div>
@endif

<div class="adm-table-wrap">
    <table class="adm-table">
        <thead>
            <tr>
                <th>Employee</th>
                <th>Gross</th>
                <th>Pension (8%)</th>
                <th>NHF</th>
                <th>PAYE</th>
                <th>Net</th>
                @if($canManage && $run->status === 'draft')<th></th>@endif
            </tr>
        </thead>
        <tbody>
            @forelse ($run->lines as $line)
                <tr>
                    <td>{{ $line->user?->name ?? '—' }}</td>
                    <td>{{ number_format((float) $line->gross_salary, 2) }}</td>
                    <td>{{ number_format((float) $line->pension_employee, 2) }}</td>
                    <td>{{ number_format((float) $line->nhf, 2) }}</td>
                    <td>{{ number_format((float) $line->paye, 2) }}</td>
                    <td><strong>{{ number_format((float) $line->net_salary, 2) }}</strong></td>
                    @if($canManage && $run->status === 'draft')
                        <td>
                            <form method="post" action="{{ route('admin.b.payroll.lines.destroy', [$business, $run, $line->user]) }}" style="display:inline;" onsubmit="return confirm('Remove this line?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="adm-btn adm-btn-danger" style="padding:0.35rem 0.55rem;font-size:0.8rem;">Remove</button>
                            </form>
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $canManage && $run->status === 'draft' ? 7 : 6 }}" style="color:var(--adm-muted);">No lines yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if($canManage && $run->status === 'draft' && $run->lines->isNotEmpty())
    <form method="post" action="{{ route('admin.b.payroll.finalize', [$business, $run]) }}" style="margin-top:1.25rem;" onsubmit="return confirm('Finalise this payroll? Staff will see payslips; you cannot edit after this.');">
        @csrf
        <button type="submit" class="adm-btn adm-btn-primary">Finalise payroll</button>
    </form>
@endif

@if($run->lines->isNotEmpty())
    <p class="adm-page-desc" style="margin-top:1.5rem;">
        Employer pension (10% of emoluments) is shown for reference on payslips in-app: 
        @foreach($run->lines as $line)
            <span style="white-space:nowrap;">{{ $line->user?->name }}: {{ number_format((float) $line->pension_employer, 2) }}@if(!$loop->last); @endif</span>
        @endforeach
    </p>
@endif
@endsection
