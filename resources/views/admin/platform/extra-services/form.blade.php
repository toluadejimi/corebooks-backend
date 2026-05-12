@extends('layouts.admin-portfolio')

@section('title', (isset($service->id) ? 'Edit' : 'Add').' service — '.config('app.name'))

@section('content')
<div class="adm-card" style="max-width:720px;">
    <h1 class="adm-page-title" style="font-size:1.35rem;">{{ isset($service->id) ? 'Edit service' : 'New service' }}</h1>
    <form method="post" action="{{ isset($service->id) ? route('admin.platform.extra-services.update', $service) : route('admin.platform.extra-services.store') }}" style="margin-top:1rem;">
        @csrf
        @if(isset($service->id))
            @method('PUT')
        @endif
        <div class="adm-field">
            <label class="adm-label" for="slug">Slug</label>
            <input class="adm-input" id="slug" name="slug" required pattern="[a-z0-9_]+" value="{{ old('slug', $service->slug) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="title">Title</label>
            <input class="adm-input" id="title" name="title" required maxlength="255" value="{{ old('title', $service->title) }}">
        </div>
        <div class="adm-field">
            <label class="adm-label" for="description">Description</label>
            <textarea class="adm-input" id="description" name="description" rows="5">{{ old('description', $service->description) }}</textarea>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="requirements">Requirements (optional)</label>
            <textarea class="adm-input" id="requirements" name="requirements" rows="8" placeholder="Documents and info needed:&#10;- CAC certificate&#10;- Director's valid ID&#10;- Utility bill not older than 3 months">{{ old('requirements', $service->requirements) }}</textarea>
            <p class="adm-page-desc" style="margin-top:0.35rem;">Shown in the mobile app on a tappable <strong>Requirements</strong> link below each service. One bullet per line; lines starting with <code>-</code>, <code>*</code> or <code>•</code> render as bullets, blank lines become spacers.</p>
            @error('requirements')<p style="color:var(--adm-danger);font-size:0.85rem;margin-top:0.35rem;">{{ $message }}</p>@enderror
        </div>
        <div class="adm-field">
            <label class="adm-label" for="icon_url">Icon / logo URL (optional)</label>
            <input class="adm-input" id="icon_url" name="icon_url" type="text" maxlength="2048" placeholder="https://…" value="{{ old('icon_url', $service->icon_url) }}">
            <p class="adm-page-desc" style="margin-top:0.35rem;">Square PNG or SVG over HTTPS. Shown in the mobile add-ons list; if empty, a default icon is used.</p>
            @if(!empty($service->icon_url))
                <p style="margin-top:0.5rem;"><img src="{{ $service->icon_url }}" alt="" width="48" height="48" style="object-fit:contain;border-radius:10px;border:1px solid var(--adm-border);background:var(--adm-surface, #fff);padding:4px;"></p>
            @endif
        </div>
        <div class="adm-field">
            <label class="adm-label" for="application_form_json">Application form (JSON, optional)</label>
            <textarea class="adm-input" id="application_form_json" name="application_form_json" rows="14" style="font-family:ui-monospace,monospace;font-size:0.85rem;">{{ old('application_form_json', isset($service->application_form) && is_array($service->application_form) ? json_encode($service->application_form, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '') }}</textarea>
            <p class="adm-page-desc" style="margin-top:0.35rem;">If set, the mobile app shows these fields when someone taps <strong>Request service</strong> instead of a single notes box. Leave empty to keep the simple optional notes field.</p>
            <p class="adm-page-desc" style="margin-top:0.25rem;"><strong>Shape:</strong> a JSON <em>array</em> of objects, each with <code>key</code> (snake_case), <code>type</code> (<code>text</code>, <code>textarea</code>, <code>email</code>, <code>tel</code>, <code>number</code>), <code>label</code>, <code>required</code> (boolean), optional <code>max</code> (character limit).</p>
            <pre style="margin-top:0.5rem;padding:0.75rem;background:var(--adm-surface, #f8fafc);border-radius:8px;font-size:0.78rem;overflow:auto;border:1px solid var(--adm-border);">[
  {"key":"contact_name","type":"text","label":"Contact name","required":true,"max":120},
  {"key":"email","type":"email","label":"Work email","required":true,"max":255},
  {"key":"details","type":"textarea","label":"What do you need?","required":true,"max":2000}
]</pre>
            @error('application_form_json')<p style="color:var(--adm-danger);font-size:0.85rem;margin-top:0.35rem;">{{ $message }}</p>@enderror
        </div>
        <div class="adm-field">
            <label class="adm-label" for="fee_amount_ngn">Listed fee (NGN)</label>
            <input class="adm-input" id="fee_amount_ngn" name="fee_amount_ngn" type="number" step="0.01" min="0" required value="{{ old('fee_amount_ngn', $service->fee_amount_ngn) }}">
            <p class="adm-page-desc" style="margin-top:0.35rem;">This amount is shown to businesses in the app (not hidden as “on request”). Use <strong>0</strong> only if you intend to show a zero-listed fee.</p>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="sort_order">Sort order</label>
            <input class="adm-input" id="sort_order" name="sort_order" type="number" min="0" required value="{{ old('sort_order', $service->sort_order ?? 100) }}">
        </div>
        <label style="display:block;margin:0.75rem 0;"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $service->is_active ?? true))> Active (shown in app)</label>
        <div style="margin-top:1rem;display:flex;gap:0.75rem;">
            <button type="submit" class="adm-btn adm-btn-primary">Save</button>
            <a href="{{ route('admin.platform.extra-services.index') }}" class="adm-btn adm-btn-ghost">Cancel</a>
        </div>
    </form>
</div>
@endsection
