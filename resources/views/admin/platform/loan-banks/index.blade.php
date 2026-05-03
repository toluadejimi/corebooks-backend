@extends('layouts.admin-portfolio')

@section('title', 'Loan partner banks — '.config('app.name'))

@section('content')
<div class="adm-card" style="margin-bottom:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <h1 class="adm-page-title" style="font-size:1.35rem;margin:0;">Partner banks</h1>
        <a href="{{ route('admin.platform.loan-banks.create') }}" class="adm-btn adm-btn-primary">Add bank</a>
    </div>
    <p class="adm-page-desc" style="margin-top:0.5rem;">Businesses choose one bank when applying; requested amount must fall between each bank’s min and max (NGN).</p>
</div>

<div class="adm-card">
    <table class="adm-table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--adm-border);">
                <th style="padding:0.5rem;">Name</th>
                <th style="padding:0.5rem;">Slug</th>
                <th style="padding:0.5rem;">Min ₦</th>
                <th style="padding:0.5rem;">Max ₦</th>
                <th style="padding:0.5rem;">Active</th>
                <th style="padding:0.5rem;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($banks as $b)
                <tr style="border-bottom:1px solid var(--adm-border);">
                    <td style="padding:0.65rem 0.5rem;">{{ $b->name }}</td>
                    <td style="padding:0.65rem 0.5rem;"><code>{{ $b->slug }}</code></td>
                    <td style="padding:0.65rem 0.5rem;">{{ number_format((float) $b->min_amount_ngn, 2) }}</td>
                    <td style="padding:0.65rem 0.5rem;">{{ number_format((float) $b->max_amount_ngn, 2) }}</td>
                    <td style="padding:0.65rem 0.5rem;">{{ $b->is_active ? 'Yes' : 'No' }}</td>
                    <td style="padding:0.65rem 0.5rem;">
                        <a href="{{ route('admin.platform.loan-banks.edit', $b) }}" class="adm-btn adm-btn-ghost" style="height:34px;">Edit</a>
                        <form method="post" action="{{ route('admin.platform.loan-banks.destroy', $b) }}" style="display:inline;" onsubmit="return confirm('Delete this bank?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="adm-btn adm-btn-ghost" style="height:34px;color:var(--adm-danger);">Delete</button>
                        </form>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<p style="margin-top:1rem;"><a href="{{ route('dashboard') }}">← Portfolio</a></p>
@endsection
