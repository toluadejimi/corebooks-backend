<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Shop') — {{ config('app.name') }}</title>
    <style>
        :root { --bg:#0f1419; --card:#1a222c; --text:#e8eef5; --muted:#8b9bb0; --accent:#3b82f6; --line:#2a3544; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: ui-sans-serif, system-ui, sans-serif; background:var(--bg); color:var(--text); line-height:1.5; }
        a { color: var(--accent); }
        .wrap { max-width: 960px; margin: 0 auto; padding: 1.25rem; }
        h1 { font-size: 1.5rem; margin: 0 0 0.5rem; letter-spacing: -0.02em; }
        .muted { color: var(--muted); font-size: 0.9rem; }
        .grid { display: grid; gap: 1rem; grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); margin-top: 1.25rem; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; overflow: hidden; text-decoration: none; color: inherit; display: block; transition: border-color .15s; }
        .card:hover { border-color: var(--accent); }
        .card img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block; background: #111; }
        .card .meta { padding: 0.65rem 0.75rem; }
        .price { font-weight: 700; color: #93c5fd; }
        .btn { display:inline-flex; align-items:center; justify-content:center; gap:.4rem; padding:.65rem 1.1rem; border-radius:10px; border:none; font-weight:600; cursor:pointer; background:var(--accent); color:#fff; text-decoration:none; font-size:0.95rem; }
        .btn:disabled { opacity: .45; cursor: not-allowed; }
        .field { margin-bottom: 1rem; }
        label { display:block; font-size:0.8rem; color:var(--muted); margin-bottom:0.25rem; }
        input { width:100%; padding:0.65rem 0.75rem; border-radius:10px; border:1px solid var(--line); background:#111820; color:var(--text); font-size:1rem; }
        .err { color: #f87171; font-size: 0.9rem; margin: 0.5rem 0; }
        .flash { padding: 0.75rem 1rem; border-radius: 10px; background: rgba(248,113,113,.12); border: 1px solid rgba(248,113,113,.35); margin-bottom: 1rem; }
    </style>
</head>
<body>
    <div class="wrap">
        @yield('content')
    </div>
</body>
</html>
