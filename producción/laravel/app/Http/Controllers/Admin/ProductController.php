<?php

namespace App\Http\Controllers\Admin;

use App\Models\Product;
use App\Models\Unit;
use App\Services\Admin\ProductImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductController extends OrganizationAdminController
{
    private const AUDIT_FIELDS = ['code', 'name', 'description', 'unit_id', 'tracks_expiration', 'is_active'];

    public function index(): View
    {
        $this->company();

        return view('admin.catalogs.products.index', [
            'products' => Product::query()->with('unit')->orderBy('name')->paginate(20),
        ]);
    }

    public function importForm(): View
    {
        $this->company();

        return view('admin.catalogs.products.import');
    }

    public function downloadTemplate(string $format, ProductImportService $importer): StreamedResponse
    {
        $this->company();
        abort_unless(in_array($format, ['csv', 'xlsx'], true), 404);

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($importer) {
                $output = fopen('php://output', 'wb');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $importer->headers(), ',', '"', '');
                fclose($output);
            }, 'plantilla_productos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->streamDownload(function () use ($importer) {
            $spreadsheet = new Spreadsheet;
            $spreadsheet->getActiveSheet()->fromArray($importer->headers(), null, 'A1');
            $spreadsheet->getActiveSheet()->freezePane('A2');
            (new Xlsx($spreadsheet))->save('php://output');
            $spreadsheet->disconnectWorksheets();
        }, 'plantilla_productos.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function importFile(Request $request, ProductImportService $importer): StreamedResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);
        $summary = $importer->import(
            $validated['file'],
            function (Product $product) use ($request, $company): void {
                $this->audit(
                    $request,
                    $company,
                    'created',
                    'product',
                    $product,
                    null,
                    $product->only(self::AUDIT_FIELDS),
                );
            },
        );

        return response()->streamDownload(function () use ($summary) {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            $this->writeCsvRow($output, [__('Resumen de importación')]);
            $this->writeCsvRow($output, [__('Productos importados'), $summary['imported']]);
            $this->writeCsvRow($output, [__('Duplicados omitidos'), $summary['duplicates']]);
            $this->writeCsvRow($output, [__('Filas con errores'), $summary['errors']]);
            $this->writeCsvRow($output, [__('Total de filas procesadas'), count($summary['results'])]);
            $this->writeCsvRow($output, []);
            $this->writeCsvRow($output, [__('Fila'), __('Código'), __('Resultado'), __('Detalle')]);

            foreach ($summary['results'] as $result) {
                $status = match ($result['status']) {
                    'imported' => __('Importado'),
                    'duplicate' => __('Duplicado'),
                    default => __('Error'),
                };
                $this->writeCsvRow($output, [$result['row'], $result['code'], $status, $result['detail']]);
            }

            fclose($output);
        }, 'resultado_importacion_productos.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function create(): View
    {
        $this->company();

        return view('admin.catalogs.products.form', [
            'product' => new Product,
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:products,code'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where('is_active', true)],
            'tracks_expiration' => ['required', 'boolean'],
        ]);
        $validated['is_active'] = true;

        $product = DB::transaction(function () use ($request, $company, $validated) {
            $product = Product::create($validated);
            $this->audit($request, $company, 'created', 'product', $product, null, $product->only(self::AUDIT_FIELDS));

            return $product;
        });

        return redirect()->route('admin.catalogs.products.edit', $product)->with('status', __('Producto creado.'));
    }

    public function edit(Product $product): View
    {
        $this->company();

        return view('admin.catalogs.products.form', [
            'product' => $product->load('unit'),
            'units' => Unit::query()
                ->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $product->unit_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('products', 'code')->ignore($product->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'unit_id' => ['required', Rule::exists('units', 'id')->where(
                fn ($query) => $query->where('is_active', true)->orWhere('id', $product->unit_id),
            )],
            'tracks_expiration' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
        ]);

        if ($validated['is_active'] && ! Unit::query()->whereKey($validated['unit_id'])->where('is_active', true)->exists()) {
            return back()->withErrors(['unit_id' => __('Un producto activo requiere una unidad activa.')])->withInput();
        }

        DB::transaction(function () use ($request, $company, $product, $validated) {
            $before = $product->only(self::AUDIT_FIELDS);
            $product->update($validated);
            $this->audit($request, $company, 'updated', 'product', $product, $before, $product->only(self::AUDIT_FIELDS));
        });

        return redirect()->route('admin.catalogs.products.index')->with('status', __('Producto actualizado.'));
    }

    private function writeCsvRow($output, array $values): void
    {
        $values = array_map(static function ($value) {
            if (is_string($value) && preg_match('/^\s*[=+\-@]/u', $value)) {
                return "'".$value;
            }

            return $value;
        }, $values);

        fputcsv($output, $values, ',', '"', '');
    }
}
