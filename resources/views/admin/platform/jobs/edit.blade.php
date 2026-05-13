@extends('layouts.admin-portfolio')

@section('title', 'Edit job — Platform admin')

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.platform.jobs.index') }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Jobs</a></p>

<h1 class="adm-page-title">Edit job</h1>
@if ($job->source === 'business')
    <p class="adm-page-desc">
        Submitted by <strong>{{ $job->submitterBusiness?->name ?? 'a business' }}</strong>
        @if($job->submittedByUser) ({{ $job->submittedByUser->email }})@endif.
        Status: <strong>{{ ucfirst($job->status) }}</strong>.
    </p>
@endif

<form method="post" action="{{ route('admin.platform.jobs.update', $job) }}" class="adm-card" style="max-width:920px;">
    @csrf @method('PUT')
    @include('admin.platform.jobs._form', ['job' => $job])
    <div class="adm-actions" style="margin-top:1.5rem;">
        <button type="submit" class="adm-btn adm-btn-primary">Save changes</button>
    </div>
</form>
@endsection
