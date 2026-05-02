<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #111; margin: 12px; }
        h1 { font-size: 15px; margin: 0 0 8px; letter-spacing: -0.02em; }
        .sub { color: #444; margin: 0 0 14px; font-size: 9px; line-height: 1.35; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #eef2ff; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; vertical-align: top; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .note { margin-top: 12px; font-size: 8px; color: #64748b; font-style: italic; }
    </style>
</head>
<body>
<h1>{{ $title }}</h1>
@if(!empty($subtitle))
    <p class="sub">{{ $subtitle }}</p>
@endif
<table>
    <thead>
    <tr>
        @foreach($headers as $i => $h)
            <th class="{{ ($numericCols[$i] ?? false) ? 'num' : '' }}">{{ $h }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
        <tr>
            @foreach($row as $i => $cell)
                <td class="{{ ($numericCols[$i] ?? false) ? 'num' : '' }}">{{ $cell }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>
@if(!empty($footerNote))
    <p class="note">{{ $footerNote }}</p>
@endif
</body>
</html>
