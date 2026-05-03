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
            <label class="adm-label" for="fee_amount_ngn">Fee (NGN)</label>
            <input class="adm-input" id="fee_amount_ngn" name="fee_amount_ngn" type="number" step="0.01" min="0" required value="{{ old('fee_amount_ngn', $service->fee_amount_ngn) }}">
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
