@extends('layouts.admin-portfolio')

@section('title', 'Token pricing — '.config('app.name'))

@section('content')
<div class="adm-card" style="max-width:640px;margin-bottom:1rem;">
    <h1 class="adm-page-title" style="font-size:1.25rem;">Token pricing</h1>
    <p class="adm-page-desc">Businesses spend token credits on proposal AI drafts and catalog search. Set how many tokens each action costs. Grant token balances per business from <strong>Business subscriptions → Edit</strong>.</p>
</div>

@if (session('status'))
    <div class="adm-card" style="max-width:640px;margin-bottom:1rem;border-left:4px solid #22c55e;">
        <p style="margin:0;">{{ session('status') }}</p>
    </div>
@endif

<div class="adm-card" style="max-width:640px;">
    <form method="post" action="{{ route('admin.platform.token-settings.update') }}">
        @csrf
        @method('PUT')
        <div class="adm-field">
            <label class="adm-label" for="token_proposal_ai_cost">Tokens per successful proposal AI draft</label>
            <input class="adm-input" type="number" min="0" max="100000" step="1" id="token_proposal_ai_cost" name="token_proposal_ai_cost" value="{{ old('token_proposal_ai_cost', $proposalAiCost) }}" required>
            <p style="margin:0.35rem 0 0;font-size:0.82rem;color:var(--adm-muted);">Charged only after the AI returns a successful draft.</p>
        </div>
        <div class="adm-field">
            <label class="adm-label" for="token_app_search_cost">Tokens per product catalog search</label>
            <input class="adm-input" type="number" min="0" max="100000" step="1" id="token_app_search_cost" name="token_app_search_cost" value="{{ old('token_app_search_cost', $appSearchCost) }}" required>
            <p style="margin:0.35rem 0 0;font-size:0.82rem;color:var(--adm-muted);">The mobile app charges this when the user runs a product search (2+ characters).</p>
        </div>
        <div style="margin-top:1rem;">
            <button type="submit" class="adm-btn adm-btn-primary">Save pricing</button>
            <a href="{{ route('dashboard') }}" class="adm-btn adm-btn-ghost">Back to portfolio</a>
        </div>
    </form>
</div>
@endsection
