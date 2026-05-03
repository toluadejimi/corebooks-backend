@extends('layouts.admin-portfolio')

@section('title', 'More services — '.config('app.name'))

@section('content')
<div class="adm-card" style="margin-bottom:1.5rem;">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:1rem;">
        <h1 class="adm-page-title" style="font-size:1.35rem;margin:0;">More services</h1>
        <a href="{{ route('admin.platform.extra-services.create') }}" class="adm-btn adm-btn-primary">Add service</a>
    </div>
    <p class="adm-page-desc" style="margin-top:0.5rem;">Listed in the mobile app <strong>Add-ons</strong> tab with title, description, optional icon, and <strong>listed fee (NGN)</strong>. Businesses submit applications from the app.</p>
</div>

<div class="adm-card">
    <table class="adm-table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid var(--adm-border);">
                <th style="padding:0.5rem;">Icon</th>
                <th style="padding:0.5rem;">Title</th>
                <th style="padding:0.5rem;">Slug</th>
                <th style="padding:0.5rem;">Fee ₦</th>
                <th style="padding:0.5rem;">Active</th>
                <th style="padding:0.5rem;"></th>
            </tr>
        </thead>
        <tbody>
            @foreach ($services as $s)
                <tr style="border-bottom:1px solid var(--adm-border);">
                    <td style="padding:0.65rem 0.5rem;">
                        @if(!empty($s->icon_url))
                            <img src="{{ $s->icon_url }}" alt="" width="36" height="36" style="object-fit:contain;border-radius:8px;border:1px solid var(--adm-border);background:var(--adm-surface, #fff);padding:2px;">
                        @else
                            <span style="color:var(--adm-muted);font-size:0.85rem;">—</span>
                        @endif
                    </td>
                    <td style="padding:0.65rem 0.5rem;">{{ $s->title }}</td>
                    <td style="padding:0.65rem 0.5rem;"><code>{{ $s->slug }}</code></td>
                    <td style="padding:0.65rem 0.5rem;">{{ number_format((float) $s->fee_amount_ngn, 2) }}</td>
                    <td style="padding:0.65rem 0.5rem;">{{ $s->is_active ? 'Yes' : 'No' }}</td>
                    <td style="padding:0.65rem 0.5rem;">
                        <a href="{{ route('admin.platform.extra-services.edit', $s) }}" class="adm-btn adm-btn-ghost" style="height:34px;">Edit</a>
                        <form method="post" action="{{ route('admin.platform.extra-services.destroy', $s) }}" style="display:inline;" onsubmit="return confirm('Delete this service?');">
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
