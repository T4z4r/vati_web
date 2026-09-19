<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1c2b22; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; }
        .meta { color: #68766d; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #c9d4cc; padding: 5px 7px; text-align: left; vertical-align: top; }
        th { background: #e8f0ea; font-size: 9px; text-transform: uppercase; letter-spacing: 0.3px; }
        tr:nth-child(even) td { background: #f6f9f7; }
        .empty { text-align: center; color: #68766d; padding: 24px; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">{{ config('app.name') }} &middot; {{ __('Generated') }}: {{ $generatedAt }}</div>
    @if (empty($rows))
        <table><tr><td class="empty">{{ __('No records found.') }}</td></tr></table>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell ?? '-' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>