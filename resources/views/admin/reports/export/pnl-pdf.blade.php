<!DOCTYPE html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 10px; color: #111; margin: 12px; }
        h1 { font-size: 15px; margin: 0 0 8px; }
        h2 { font-size: 12px; margin: 18px 0 8px; color: #334155; }
        .sub { color: #444; margin: 0 0 14px; font-size: 9px; line-height: 1.35; }
        table { border-collapse: collapse; width: 100%; margin-bottom: 8px; }
        th { background: #eef2ff; font-weight: bold; font-size: 9px; text-transform: uppercase; letter-spacing: 0.03em; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 7px; text-align: left; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        tbody tr:nth-child(even) td { background: #f8fafc; }
        .note { margin-top: 10px; font-size: 8px; color: #64748b; font-style: italic; }
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
        @foreach($summaryHeaders as $i => $h)
            <th class="{{ ($summaryNumericCols[$i] ?? false) ? 'num' : '' }}">{{ $h }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($summaryRows as $row)
        <tr>
            @foreach($row as $i => $cell)
                <td class="{{ ($summaryNumericCols[$i] ?? false) ? 'num' : '' }}">{{ $cell }}</td>
            @endforeach
        </tr>
    @endforeach
    </tbody>
</table>

<h2>{{ $breakdownTitle }}</h2>
<p class="sub">{{ $breakdownHint }}</p>
<table>
    <thead>
    <tr>
        @foreach($breakdownHeaders as $i => $h)
            <th class="{{ ($breakdownNumericCols[$i] ?? false) ? 'num' : '' }}">{{ $h }}</th>
        @endforeach
    </tr>
    </thead>
    <tbody>
    @foreach($breakdownRows as $row)
        <tr>
            @foreach($row as $i => $cell)
                <td class="{{ ($breakdownNumericCols[$i] ?? false) ? 'num' : '' }}">{{ $cell }}</td>
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
