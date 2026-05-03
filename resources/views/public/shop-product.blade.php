@extends('layouts.public-shop')

@section('title', $product->name.' — '.$business->name)

@section('content')
    <p class="muted"><a href="{{ route('public.shop', $business) }}">← All products</a></p>
    <h1>{{ $product->name }}</h1>
    <p class="price" style="font-size:1.35rem;margin:0.25rem 0 1rem;">{{ $business->currency ?? 'NGN' }} {{ number_format((float) $product->selling_price, 2) }}</p>
    @if($product->image_url)
        <img src="{{ $product->image_url }}" alt="" style="max-width:100%;max-height:320px;border-radius:14px;border:1px solid var(--line);">
    @endif
    @php
        $gallery = is_array($product->gallery_urls) ? $product->gallery_urls : [];
    @endphp
    @if(count($gallery) > 0)
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-top:0.75rem;">
            @foreach($gallery as $g)
                @if(is_string($g) && $g !== $product->image_url)
                    <img src="{{ $g }}" alt="" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--line);">
                @endif
            @endforeach
        </div>
    @endif
    @if(is_array($product->variations) && count($product->variations) > 0)
        <div style="margin-top:1rem;">
            <div class="muted" style="margin-bottom:0.35rem;">Options</div>
            <ul style="margin:0;padding-left:1.2rem;color:var(--muted);font-size:0.9rem;">
                @foreach($product->variations as $v)
                    <li>
                        <strong style="color:var(--text);">{{ $v['name'] ?? 'Option' }}</strong>:
                        @if(isset($v['options']) && is_array($v['options']))
                            {{ implode(', ', $v['options']) }}
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($errors->any())
        <div class="flash">
            @foreach($errors->all() as $e){{ $e }}@if(!$loop->last)<br>@endif @endforeach
        </div>
    @endif

    <form method="post" action="{{ route('public.shop.checkout', $business) }}" style="margin-top:1.5rem;max-width:360px;">
        @csrf
        <input type="hidden" name="product_uuid" value="{{ $product->uuid }}">
        <div class="field">
            <label for="qty">Quantity</label>
            <input id="qty" name="qty" type="number" step="0.001" min="0.001" value="{{ old('qty', '1') }}" required>
        </div>
        <div class="field">
            <label for="customer_email">Your email</label>
            <input id="customer_email" name="customer_email" type="email" value="{{ old('customer_email') }}" required placeholder="you@example.com">
        </div>
        <button type="submit" class="btn">Pay with card (SprintPay)</button>
    </form>
@endsection
