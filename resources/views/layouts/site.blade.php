<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #050810;
            --bg-card: rgba(16, 20, 34, 0.78);
            --border: rgba(255, 255, 255, 0.09);
            --text: #eef2f8;
            --muted: #94a3b8;
            --accent: #6366f1;
            --accent-dim: #4f46e5;
            --glow: rgba(99, 102, 241, 0.38);
            --success: #34d399;
            --danger: #f87171;
        }
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: "DM Sans", system-ui, sans-serif;
            background: var(--bg-deep);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.55;
            -webkit-font-smoothing: antialiased;
            font-size: 1rem;
        }
        .mesh-bg {
            position: fixed;
            inset: 0;
            z-index: -1;
            background:
                radial-gradient(ellipse 80% 50% at 18% -8%, rgba(99, 102, 241, 0.28), transparent 55%),
                radial-gradient(ellipse 55% 42% at 100% 0%, rgba(52, 211, 153, 0.1), transparent 45%),
                radial-gradient(ellipse 50% 70% at 50% 100%, rgba(129, 140, 248, 0.12), transparent 50%),
                var(--bg-deep);
        }
        .grid-noise {
            position: fixed;
            inset: 0;
            z-index: -1;
            opacity: 0.04;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E");
            pointer-events: none;
        }
        a { color: var(--accent); text-decoration: none; transition: color 0.15s ease; }
        a:hover { color: #8cb4ff; }
        .container { width: min(1120px, 100% - 2rem); margin-inline: auto; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem 1.35rem;
            font-weight: 600;
            font-size: 0.9375rem;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            transition: transform 0.12s ease, box-shadow 0.12s ease, background 0.15s ease;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent-dim) 100%);
            color: #fff;
            box-shadow: 0 4px 28px var(--glow);
        }
        .btn-primary:hover { box-shadow: 0 8px 36px var(--glow); transform: translateY(-1px); }
        .btn-ghost {
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.04); }
        .card {
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 1.75rem;
        }
        .heading-font { font-family: "Outfit", sans-serif; }
        .input {
            width: 100%;
            padding: 0.85rem 1rem;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(0,0,0,0.25);
            color: var(--text);
            font-size: 1rem;
        }
        .input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(91, 140, 255, 0.2);
        }
        label { display: block; font-size: 0.8125rem; font-weight: 500; color: var(--muted); margin-bottom: 0.35rem; }
        .error { color: var(--danger); font-size: 0.875rem; margin-top: 0.35rem; }
    </style>
    @stack('styles')
</head>
<body>
    <div class="mesh-bg" aria-hidden="true"></div>
    <div class="grid-noise" aria-hidden="true"></div>
    @yield('content')
</body>
</html>
