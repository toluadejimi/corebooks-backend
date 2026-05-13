@extends('layouts.admin-portfolio')

@section('title', 'Post a job — Platform admin')

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.platform.jobs.index') }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Jobs</a></p>

<h1 class="adm-page-title">Post a job</h1>
<p class="adm-page-desc">This job goes live on the mobile feed immediately. To approve a business-submitted vacancy instead, use the Pending tab on the jobs list.</p>

<form method="post" action="{{ route('admin.platform.jobs.store') }}" class="adm-card" style="max-width:920px;">
    @csrf
    @include('admin.platform.jobs._form')
    <div class="adm-actions" style="margin-top:1.5rem;">
        <button type="submit" class="adm-btn adm-btn-primary">Post job</button>
    </div>
</form>
@endsection
