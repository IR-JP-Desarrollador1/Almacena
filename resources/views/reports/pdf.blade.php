<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px; }
        body { color: #172b28; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { margin: 0 0 6px; font-size: 17px; }
        p { margin: 0 0 12px; color: #596a62; }
        table { width: 100%; border-collapse: collapse; }
        th { color: #fff; background: #183a33; font-weight: bold; text-align: left; }
        th, td { padding: 5px 4px; border: 1px solid #dce3dd; vertical-align: top; }
        tr:nth-child(even) td { background: #f4f6f3; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>
        @if ($from && $to)
            Periodo: {{ \Illuminate\Support\Carbon::parse($from)->format('d/m/Y') }} — {{ \Illuminate\Support\Carbon::parse($to)->format('d/m/Y') }} ·
        @endif
        Registros: {{ count($rows) }} · Generado: {{ $generatedAt }}
    </p>
    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($columns as $column)
                        <td>{{ $row[$column] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($columns) }}">No hay registros para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
