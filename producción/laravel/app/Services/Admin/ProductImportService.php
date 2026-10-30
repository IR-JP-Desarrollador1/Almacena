<?php

namespace App\Services\Admin;

use App\Models\Product;
use App\Models\Unit;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ProductImportService
{
    private const HEADERS = ['code', 'name', 'description', 'unit_code', 'tracks_expiration', 'is_active'];

    private const MAX_ROWS = 1000;

    public function import(UploadedFile $file, Closure $audit): array
    {
        $rows = $this->readRows($file);
        $results = [];
        $importedCodes = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;

            if (count($row) !== count(self::HEADERS)) {
                $results[] = $this->result($line, '', 'error', __('Cada fila debe tener exactamente :count columnas.', ['count' => count(self::HEADERS)]));

                continue;
            }

            $data = array_combine(self::HEADERS, array_map(
                static fn ($value) => is_string($value) ? trim($value) : $value,
                $row,
            ));
            $code = (string) ($data['code'] ?? '');

            if ($code !== '' && in_array($code, $importedCodes, true)) {
                $results[] = $this->result($line, $code, 'duplicate', __('El código ya se importó en una fila anterior.'));

                continue;
            }

            if ($code !== '' && Product::query()->where('code', $code)->exists()) {
                $results[] = $this->result($line, $code, 'duplicate', __('El código ya existe; no se modificó el producto.'));

                continue;
            }

            $data['tracks_expiration'] = $this->booleanValue($data['tracks_expiration'] ?? null);
            $data['is_active'] = $this->booleanValue($data['is_active'] ?? null);
            $validator = Validator::make($data, [
                'code' => ['required', 'string', 'max:50'],
                'name' => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string', 'max:2000'],
                'unit_code' => ['required', 'string', 'max:50'],
                'tracks_expiration' => ['required', 'boolean'],
                'is_active' => ['required', 'boolean'],
            ], [], [
                'code' => __('Código'),
                'name' => __('Nombre'),
                'description' => __('Descripción'),
                'unit_code' => __('Código de unidad'),
                'tracks_expiration' => __('Maneja vencimiento'),
                'is_active' => __('Estado'),
            ]);

            if ($validator->fails()) {
                $results[] = $this->result($line, $code, 'error', $validator->errors()->first());

                continue;
            }

            $unit = Unit::query()->where('code', $data['unit_code'])->first();

            if (! $unit) {
                $results[] = $this->result($line, $code, 'error', __('No existe una unidad con ese código.'));

                continue;
            }

            if ((bool) $data['is_active'] && ! $unit->is_active) {
                $results[] = $this->result($line, $code, 'error', __('Un producto activo requiere una unidad activa.'));

                continue;
            }

            $product = DB::transaction(function () use ($data, $unit, $audit) {
                $product = Product::create([
                    'code' => $data['code'],
                    'name' => $data['name'],
                    'description' => $data['description'] ?: null,
                    'unit_id' => $unit->id,
                    'tracks_expiration' => (bool) $data['tracks_expiration'],
                    'is_active' => (bool) $data['is_active'],
                ]);
                $audit($product);

                return $product;
            });

            $importedCodes[] = $product->code;
            $results[] = $this->result($line, $product->code, 'imported', __('Producto importado.'));
        }

        return [
            'imported' => count(array_filter($results, static fn (array $result) => $result['status'] === 'imported')),
            'duplicates' => count(array_filter($results, static fn (array $result) => $result['status'] === 'duplicate')),
            'errors' => count(array_filter($results, static fn (array $result) => $result['status'] === 'error')),
            'results' => $results,
        ];
    }

    public function headers(): array
    {
        return self::HEADERS;
    }

    private function readRows(UploadedFile $file): array
    {
        if (strtolower($file->getClientOriginalExtension()) === 'xlsx') {
            $reader = new Xlsx;
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class(self::MAX_ROWS) implements IReadFilter
            {
                public function __construct(private int $maxRows) {}

                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= $this->maxRows + 2
                        && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'F'], true);
                }
            });
            try {
                $spreadsheet = $reader->load($file->getRealPath());
            } catch (ReaderException) {
                throw ValidationException::withMessages(['file' => __('No se pudo leer el archivo de Excel.')]);
            }

            $sheet = $spreadsheet->getActiveSheet();

            if ($sheet->getHighestDataRow() > self::MAX_ROWS + 1) {
                throw ValidationException::withMessages([
                    'file' => __('El archivo no puede contener más de :count filas de datos.', ['count' => self::MAX_ROWS]),
                ]);
            }

            if (Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) > count(self::HEADERS)) {
                throw ValidationException::withMessages([
                    'file' => __('La primera fila debe contener estas columnas, en este orden: :headers.', [
                        'headers' => implode(', ', self::HEADERS),
                    ]),
                ]);
            }

            $records = $sheet->toArray(null, true, false, false);
            $spreadsheet->disconnectWorksheets();
        } else {
            $records = $this->readCsv($file);
        }

        $header = array_map(static fn ($value) => strtolower(trim((string) $value)), array_shift($records) ?? []);
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0] ?? '') ?? '';

        if ($header !== self::HEADERS) {
            throw ValidationException::withMessages([
                'file' => __('La primera fila debe contener estas columnas, en este orden: :headers.', [
                    'headers' => implode(', ', self::HEADERS),
                ]),
            ]);
        }

        $records = array_filter($records, static function (array $row): bool {
            foreach ($row as $value) {
                if ($value !== null && trim((string) $value) !== '') {
                    return true;
                }
            }

            return false;
        });

        if (count($records) > self::MAX_ROWS) {
            throw ValidationException::withMessages([
                'file' => __('El archivo no puede contener más de :count filas de datos.', ['count' => self::MAX_ROWS]),
            ]);
        }

        return $records;
    }

    private function readCsv(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages(['file' => __('No se pudo leer el archivo.')]);
        }

        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = substr_count((string) $firstLine, ';') > substr_count((string) $firstLine, ',') ? ';' : ',';
        $records = [];

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $records[] = $row;

            if (count($records) > self::MAX_ROWS + 1) {
                fclose($handle);
                throw ValidationException::withMessages([
                    'file' => __('El archivo no puede contener más de :count filas de datos.', ['count' => self::MAX_ROWS]),
                ]);
            }
        }

        fclose($handle);

        return $records;
    }

    private function booleanValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        return match (mb_strtolower(trim($value))) {
            'sí', 'si', 'yes', 'true', 'active', 'activo', 'activa' => '1',
            'no', 'false', 'inactive', 'inactivo', 'inactiva' => '0',
            default => $value,
        };
    }

    private function result(int $row, string $code, string $status, string $detail): array
    {
        return [
            'row' => $row,
            'code' => $code,
            'status' => $status,
            'detail' => $detail,
        ];
    }
}
