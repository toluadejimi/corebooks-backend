@extends('layouts.admin-portfolio')

@section('title', 'Subscription plans — '.config('app.name'))

@section('content')
<div class="adm-card" style="margin-bottom:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <h1 class="adm-page-title" style="font-size:1.35rem;margin:0;">Subscription plans</h1>
        <a href="{{ route('admin.platform.plans.create') }}" class="adm-btn adm-btn-primary">New plan</a>
    </div>
    <p class="adm-page-desc" style="margin-top:0.5rem;">Prices in NGN per billing period. Mobile sign-up uses these plans.</p>
</div>

<div class="adm-card">
    <table class="adm-table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--adm-border);">
                <th style="padding:0.5rem;">Name</th>
                <th style="padding:0.5rem;">Slug</th>
                <th style="padding:0.5rem;">Price</th>
                <th style="padding:0.5rem;">Max records</th>
                <th style="padding:0.5rem;">Active</th>
                <th style="padding:0.5rem;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($plans as $p)
                <tr style="border-bottom:1px solid var(--adm-border);">
                    <td style="padding:0.65rem 0.5rem;">{{ $p->name }}</td>
                    <td style="padding:0.65rem 0.5rem;"><code>{{ $p->slug }}</code></td>
                    <td style="padding:0.65rem 0.5rem;">₦{{ number_format($p->priceNaira(), 0) }} / {{ $p->billing_interval }}</td>
                    <td style="padding:0.65rem 0.5rem;">{{ number_format($p->max_records) }}</td>
                    <td style="padding:0.65rem 0.5rem;">{{ $p->is_active ? 'Yes' : 'No' }}</td>
                    <td style="padding:0.65rem 0.5rem;">
                        <a href="{{ route('admin.platform.plans.edit', $p) }}" class="adm-btn adm-btn-ghost" style="height:34px;">Edit</a>
                        <form method="post" action="{{ route('admin.platform.plans.destroy', $p) }}" style="display:inline;" onsubmit="return confirm('Delete this plan?');">
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
