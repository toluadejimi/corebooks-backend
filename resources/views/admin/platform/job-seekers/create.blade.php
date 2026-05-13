@extends('layouts.admin-portfolio')

@section('title', 'Add seeker — Platform admin')

@section('content')
<p style="margin:0 0 1rem;"><a href="{{ route('admin.platform.job-seekers.index') }}" class="adm-btn adm-btn-ghost" style="padding:0.35rem 0.65rem;font-size:0.85rem;">← Job seekers</a></p>
<h1 class="adm-page-title">Add seeker</h1>
<p class="adm-page-desc">Create a profile for a candidate. Once active, it appears in the mobile "Job seekers" tab.</p>

<form action="{{ route('admin.platform.job-seekers.store') }}" method="post" class="adm-card" enctype="multipart/form-data" style="max-width:760px;">
    @csrf
    @include('admin.platform.job-seekers._form', ['seeker' => null])

    @if ($errors->any())
        <div class="adm-alert adm-alert-error" style="margin-top:1rem;">
            <ul style="margin:0;padding-left:1.25rem;">
                @foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="adm-actions" style="margin-top:1rem;">
        <button type="submit" class="adm-btn adm-btn-primary">Create seeker</button>
        <a href="{{ route('admin.platform.job-seekers.index') }}" class="adm-btn adm-btn-ghost">Cancel</a>
    </div>
</form>
@endsection
