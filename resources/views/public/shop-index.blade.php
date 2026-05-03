@extends('layouts.public-shop')

@section('title', $business->name.' — Online shop')

@section('content')
    <h1>{{ $business->name }}</h1>
    <p class="muted">Order online from this catalogue. Prices in {{ strtoupper($business->currency ?? 'NGN') }}.</p>
    @if($products->isEmpty())
        <p class="muted" style="margin-top:2rem;">No products are available for online ordering yet.</p>
    @else
        <div class="grid">
            @foreach($products as $p)
                <a class="card" href="{{ route('public.shop.product', [$business, $p]) }}">
                    @if($p->image_url)
                        <img src="{{ $p->image_url }}" alt="">
                    @else
                        <div style="aspect-ratio:1;background:#111;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:0.75rem;">No photo</div>
                    @endif
                    <div class="meta">
                        <div style="font-weight:600;font-size:0.9rem;line-height:1.25;">{{ $p->name }}</div>
                        <div class="price">{{ number_format((float) $p->selling_price, 2) }}</div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
