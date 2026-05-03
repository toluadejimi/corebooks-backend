@extends('layouts.public-shop')

@section('title', 'Thank you')

@section('content')
    <h1>Thank you</h1>
    <p class="muted">If your payment completed, the merchant will confirm your order. Reference: <code>{{ $ref ?? '—' }}</code></p>
    <p style="margin-top:1.5rem;"><a href="{{ route('public.shop', $business) }}">← Back to shop</a></p>
@endsection
