<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ __('Kardex de inventario') }}</title>
    <style>
        @page { margin: 24px 20px; }
        body { color: #1f2937; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        h1 { color: #14532d; font-size: 16px; margin: 0 0 5px; }
        p { color: #4b5563; margin: 0 0 12px; }
        table { border-collapse: collapse; width: 100%; }
        th { background: #166534; color: #fff; font-weight: bold; }
        th, td { border: 1px solid #d1d5db; padding: 4px; text-align: left; }
        tbody tr:nth-child(even) { background: #f3f4f6; }
        .number { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <h1>{{ __('Kardex de inventario') }}</h1>
    <p>{{ __('Generado: :date', ['date' => $regional->dateTime($generatedAt)]) }}</p>
    <p>
        {{ __('Bodega: :warehouse', ['warehouse' => $filters['warehouse_id'] ?? __('Todas')]) }}
        · {{ __('Producto: :product', ['product' => $filters['product_id'] ?? __('Todos')]) }}
        ·         {{ __('Desde: :date', ['date' => isset($filters['from_date']) ? $regional->date($filters['from_date']) : '—']) }}
        · {{ __('Hasta: :date', ['date' => isset($filters['to_date']) ? $regional->date($filters['to_date']) : '—']) }}
    </p>
    <table>
        <thead>
            <tr>
                @foreach ($headings as $index => $heading)
                    <th class="{{ in_array($index, [8, 9, 10], true) ? 'number' : '' }}">{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $index => $value)
                        <td class="{{ in_array($index, [8, 9, 10], true) ? 'number' : '' }}">
                            @if (in_array($index, [8, 9, 10], true))
                                {{ $regional->number($value, $index === 10 ? 4 : 3) }}
                            @else
                                {{ $value }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($headings) }}">{{ __('No hay movimientos para los filtros seleccionados.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
