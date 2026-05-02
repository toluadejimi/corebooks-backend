<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #111; margin: 12px; }
        h1 { font-size: 15px; margin: 0 0 8px; }
        .sub { color: #444; margin: 0 0 14px; font-size: 9px; line-height: 1.35; white-space: pre-line; }
        h2 { font-size: 12px; margin: 18px 0 8px; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
        th { background: #eef2ff; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
    </style>
</head>
<body>
<h1>FIRS / VAT summary</h1>
<p class="sub">{{ $subtitle }}</p>

<table>
    <thead><tr><th>Field</th><th class="num">Value</th></tr></thead>
    <tbody>
    @foreach($summaryRows as $r)
        <tr><td>{{ $r[0] }}</td><td class="num">{{ $r[1] }}</td></tr>
    @endforeach
    </tbody>
</table>

<h2>By VAT rate</h2>
<table>
    <thead>
    <tr>
        <th class="num">VAT rate %</th>
        <th class="num">Supply ex VAT</th>
        <th class="num">VAT amount</th>
    </tr>
    </thead>
    <tbody>
    @foreach($rateRows as $r)
        <tr>
            <td class="num">{{ $r[0] }}</td>
            <td class="num">{{ $r[1] }}</td>
            <td class="num">{{ $r[2] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
</body>
</html>
