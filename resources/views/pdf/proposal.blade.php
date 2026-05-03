<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #111; margin: 14px; line-height: 1.45; }
        h1 { font-size: 17px; margin: 0 0 6px; }
        .muted { color: #475569; font-size: 9px; margin: 0 0 16px; }
        .meta { margin-bottom: 18px; font-size: 9px; color: #334155; }
        .body { font-size: 10px; }
        .body h2 { font-size: 13px; margin: 14px 0 6px; }
        .body h3 { font-size: 11px; margin: 12px 0 4px; }
        .body p { margin: 0 0 8px; }
        .body ul { margin: 0 0 8px 16px; padding: 0; }
        .body li { margin-bottom: 4px; }
    </style>
</head>
<body>
<h1>{{ e($proposal->title) }}</h1>
<p class="muted">{{ $business->name }} · {{ $proposal->created_at?->format('Y-m-d') }}</p>
<div class="meta">
    @if($proposal->client_name)<strong>For:</strong> {{ e($proposal->client_name) }}<br/>@endif
    @if($proposal->client_email){{ e($proposal->client_email) }}@endif
</div>
<div class="body">
    {!! $bodyHtml ?? '' !!}
</div>
</body>
</html>
