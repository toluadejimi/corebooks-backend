<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payment — {{ config('app.name') }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .card { background: #1e293b; padding: 2rem; border-radius: 1rem; max-width: 28rem; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 0.5rem; }
        p { color: #94a3b8; margin: 0 0 1rem; line-height: 1.5; }
        .ok { color: #4ade80; }
        .bad { color: #f87171; }
    </style>
</head>
<body>
    <div class="card">
        @if ($ok)
            <h1 class="ok">Payment confirmed</h1>
            <p>Your CoreBooks workspace subscription is active. You can close this tab and return to the app.</p>
        @else
            <h1 class="bad">Could not confirm payment</h1>
            <p>If you completed payment, wait a moment and open the app again. Reference: <code>{{ e($reference) }}</code></p>
        @endif
    </div>
</body>
</html>
