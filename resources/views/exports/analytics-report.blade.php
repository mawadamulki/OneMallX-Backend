<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] ?? 'Analytics Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 14px; margin: 18px 0 8px; color: #111827; }
        .meta { margin-bottom: 16px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; font-weight: bold; }
        tr:nth-child(even) td { background: #fafafa; }
    </style>
</head>
<body>
    <h1>{{ $report['title'] ?? 'Analytics Report' }}</h1>
    <div class="meta">
        <div><strong>{{ $report['subtitle'] ?? '' }}</strong></div>
        <div>Period: {{ $report['period_label'] ?? '' }}</div>
        <div>Generated: {{ $report['generated_at'] ?? '' }}</div>
    </div>

    @foreach ($report['sections'] ?? [] as $section)
        <h2>{{ $section['title'] ?? 'Section' }}</h2>
        <table>
            <thead>
                <tr>
                    @foreach ($section['headers'] ?? [] as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($section['rows'] ?? [] as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endforeach
</body>
</html>
