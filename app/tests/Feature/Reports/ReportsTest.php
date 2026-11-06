<?php

namespace Tests\Feature\Reports;

use App\Models\Branch;
use App\Models\Company;
use App\Models\CostCenter;
use App\Models\Currency;
use App\Models\Department;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLot;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Currency $currency;

    private User $administrator;

    private Warehouse $warehouse;

    private CostCenter $costCenter;

    private Product $product;

    private Unit $unit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->currency = Currency::query()->where('code', 'COP')->firstOrFail();
        $this->company = Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa de prueba',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
            'base_currency_id' => $this->currency->id,
            'decimal_separator' => ',',
            'thousands_separator' => '.',
        ]);
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'HQ',
            'name' => 'Sede principal',
        ]);
        $this->costCenter = CostCenter::create([
            'company_id' => $this->company->id,
            'code' => 'CC-INV',
            'name' => 'Operación de bodega',
        ]);
        $this->warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'cost_center_id' => $this->costCenter->id,
            'code' => 'MAIN',
            'name' => 'Bodega principal',
        ]);
        $this->unit = Unit::create([
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
        ]);
        $this->product = Product::create([
            'code' => 'PROD-REPORT',
            'name' => 'Producto de reporte',
            'unit_id' => $this->unit->id,
            'is_active' => true,
        ]);
        $this->administrator = User::factory()->create();
        $this->administrator->roles()->attach(Role::query()->where('slug', 'administracion')->firstOrFail());
    }

    public function test_inventory_reports_filter_movements_and_show_stock_and_approved_adjustments(): void
    {
        $lot = $this->lot($this->warehouse, 'LOT-REPORT');
        $this->movement($lot, $this->warehouse, StockMovement::TYPE_RECEIPT, 'receipt', '10.000');
        $this->movement($lot, $this->warehouse, StockMovement::TYPE_ISSUE, 'inventory_issue', '2.000');
        $this->movement($lot, $this->warehouse, StockMovement::TYPE_ISSUE, 'inventory_transfer', '1.000');

        $stockResponse = $this->actingAs($this->administrator)
            ->get(route('reports.inventory', [
                'report' => 'stock',
                'warehouse_id' => $this->warehouse->id,
                'cost_center_id' => $this->costCenter->id,
            ]));
        $stockResponse->assertOk()
            ->assertSee($this->product->code)
            ->assertSee('CC-INV')
            ->assertSee('Bodega principal')
            ->assertSeeText('7 und')
            ->assertSeeText('14,00 COP');

        $transferResponse = $this->get(route('reports.inventory', [
            'report' => 'movements',
            'movement' => 'transfers',
            'warehouse_id' => $this->warehouse->id,
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
        ]));
        $transferResponse->assertOk()
            ->assertSee('Traslado')
            ->assertDontSee('>Entrada</td>')
            ->assertDontSee('>Salida</td>')
            ->assertSee('text-sm">1</td>', false)
            ->assertSee('text-sm">2,00 COP</td>', false)
            ->assertSee('Costo total');

        $count = InventoryCount::create([
            'reference' => 'CNT-REPORT-01',
            'warehouse_id' => $this->warehouse->id,
            'status' => InventoryCount::STATUS_APPROVED,
            'created_by_user_id' => $this->administrator->id,
            'approved_by_user_id' => $this->administrator->id,
            'submitted_at' => now(),
            'approved_at' => now(),
        ]);
        InventoryCountItem::create([
            'inventory_count_id' => $count->id,
            'product_id' => $this->product->id,
            'product_code' => $this->product->code,
            'product_name' => $this->product->name,
            'unit_abbreviation' => $this->unit->abbreviation,
            'system_quantity' => '7.000',
            'counted_quantity' => '6.000',
            'difference_quantity' => '-1.000',
        ]);

        $this->get(route('reports.inventory', ['report' => 'adjustments']))
            ->assertOk()
            ->assertSee('CNT-REPORT-01')
            ->assertSee('Faltante')
            ->assertSee('text-sm">7</td>', false)
            ->assertSee('text-sm">6</td>', false)
            ->assertSee('text-sm">-1 (Faltante)</td>', false);

        $adjustmentsExport = $this->get(route('reports.inventory', ['report' => 'adjustments', 'format' => 'csv']));
        $adjustmentsExport->assertDownload('reporte-inventario-ajustes-'.today()->format('Ymd').'.csv');
        $adjustmentsCsvRows = array_map('str_getcsv', explode("\n", trim(substr($adjustmentsExport->streamedContent(), 3))));
        $this->assertSame('CNT-REPORT-01', $adjustmentsCsvRows[1][1]);
        $this->assertSame('7', $adjustmentsCsvRows[1][6]);
        $this->assertSame('6', $adjustmentsCsvRows[1][7]);
        $this->assertSame('-1', $adjustmentsCsvRows[1][8]);
        $this->assertSame('Faltante', $adjustmentsCsvRows[1][9]);
    }

    public function test_inventory_report_respects_user_warehouse_access(): void
    {
        $this->movement($this->lot($this->warehouse, 'LOT-ACCESS-1'), $this->warehouse, StockMovement::TYPE_RECEIPT, 'receipt', '3.000');
        $otherWarehouse = Warehouse::create([
            'branch_id' => $this->warehouse->branch_id,
            'code' => 'OTHER',
            'name' => 'Bodega no autorizada',
        ]);
        $this->movement($this->lot($otherWarehouse, 'LOT-ACCESS-2'), $otherWarehouse, StockMovement::TYPE_RECEIPT, 'receipt', '4.000');

        $operator = User::factory()->create();
        $operator->roles()->attach(Role::query()->where('slug', 'almacen')->firstOrFail());
        $operator->warehouses()->attach($this->warehouse);

        $this->actingAs($operator)
            ->get(route('reports.inventory', ['report' => 'movements']))
            ->assertOk()
            ->assertSee('Bodega principal')
            ->assertDontSee('Bodega no autorizada');
    }

    public function test_purchasing_reports_filter_by_area_cost_center_and_approved_value(): void
    {
        $costCenter = CostCenter::create([
            'company_id' => $this->company->id,
            'code' => 'CC-REPORT',
            'name' => 'Operación de prueba',
        ]);
        $department = Department::create([
            'company_id' => $this->company->id,
            'cost_center_id' => $costCenter->id,
            'code' => 'AREA-REPORT',
            'name' => 'Área de prueba',
            'manager_user_id' => $this->administrator->id,
        ]);
        $supplier = Supplier::create(['legal_name' => 'Proveedor de reporte']);
        $requisition = Requisition::create([
            'reference' => 'REQ-REPORT-01',
            'department_id' => $department->id,
            'warehouse_id' => $this->warehouse->id,
            'requester_user_id' => $this->administrator->id,
            'approver_user_id' => $this->administrator->id,
            'status' => Requisition::STATUS_APPROVED,
            'justification' => 'Prueba de reporte.',
            'needed_by' => today()->addDays(3),
            'submitted_at' => now(),
            'decided_at' => now(),
        ]);
        $requisitionItem = RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'product_code' => $this->product->code,
            'product_name' => $this->product->name,
            'unit_abbreviation' => $this->unit->abbreviation,
            'quantity' => '2.000',
        ]);
        $order = PurchaseOrder::create([
            'reference' => 'PO-REPORT-01',
            'requisition_id' => $requisition->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $supplier->id,
            'quote_currency_id' => $this->currency->id,
            'base_currency_id' => $this->currency->id,
            'exchange_rate' => '1.00000000',
            'created_by_user_id' => $this->administrator->id,
            'approved_by_user_id' => $this->administrator->id,
            'quote_reference' => 'QUOTE-01',
            'quote_date' => today(),
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'requisition_item_id' => $requisitionItem->id,
            'product_id' => $this->product->id,
            'product_code' => $this->product->code,
            'product_name' => $this->product->name,
            'unit_abbreviation' => $this->unit->abbreviation,
            'quantity' => '2.000',
            'unit_cost' => '4.5000',
            'unit_cost_base' => '4.5000',
            'tax_rate' => '19.0000',
        ]);

        $this->actingAs($this->administrator)
            ->get(route('reports.purchasing', [
                'report' => 'spend',
                'cost_center_id' => $costCenter->id,
                'from_date' => today()->toDateString(),
                'to_date' => today()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('PO-REPORT-01')
            ->assertSee('Proveedor de reporte')
            ->assertSee('CC-REPORT')
            ->assertSee('10,71')
            ->assertSee('text-sm">2</td>', false)
            ->assertSee('text-sm">4,50</td>', false);

        $this->get(route('reports.purchasing', ['report' => 'requisitions', 'cost_center_id' => $costCenter->id]))
            ->assertOk()
            ->assertSee('REQ-REPORT-01')
            ->assertSee('CC-REPORT');

        $this->get(route('reports.purchasing', [
            'report' => 'orders',
            'status' => PurchaseOrder::STATUS_APPROVED,
            'cost_center_id' => $costCenter->id,
        ]))
            ->assertOk()
            ->assertSee('PO-REPORT-01')
            ->assertSee('10,71');
    }

    public function test_inventory_reports_export_all_filtered_rows_to_csv_and_excel(): void
    {
        $lot = $this->lot($this->warehouse, 'LOT-EXPORT');
        foreach (range(1, 101) as $index) {
            $this->movement($lot, $this->warehouse, StockMovement::TYPE_RECEIPT, 'receipt', '1.000');
        }

        $csvResponse = $this->actingAs($this->administrator)
            ->get(route('reports.inventory', [
                'report' => 'movements',
                'movement' => 'receipts',
                'warehouse_id' => $this->warehouse->id,
                'cost_center_id' => $this->costCenter->id,
                'from_date' => today()->toDateString(),
                'to_date' => today()->toDateString(),
                'format' => 'csv',
            ]));
        $csvResponse->assertDownload('reporte-inventario-movimientos-'.today()->format('Ymd').'.csv');
        $csv = $csvResponse->streamedContent();
        $this->assertStringContainsString("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('PROD-REPORT', $csv);
        $this->assertSame(102, substr_count(trim($csv), "\n") + 1);
        $csvRows = array_map('str_getcsv', explode("\n", trim(substr($csv, 3))));
        $this->assertSame('Costo total', $csvRows[0][8]);
        $this->assertSame('1', $csvRows[1][6]);
        $this->assertSame('2.00', $csvRows[1][7]);
        $this->assertSame('2.00', $csvRows[1][8]);

        $movementXlsxResponse = $this->get(route('reports.inventory', [
            'report' => 'movements',
            'movement' => 'receipts',
            'warehouse_id' => $this->warehouse->id,
            'format' => 'xlsx',
        ]));
        $temporaryFile = tempnam(sys_get_temp_dir(), 'almacena-movements-');
        $this->assertNotFalse($temporaryFile);

        try {
            file_put_contents($temporaryFile, $movementXlsxResponse->streamedContent());
            $sheet = IOFactory::load($temporaryFile)->getActiveSheet();
            $this->assertSame('Costo total', $sheet->getCell('I1')->getValue());
            $this->assertSame(2.0, $sheet->getCell('I2')->getValue());
            $this->assertSame('#,##0.00', $sheet->getCell('I2')->getStyle()->getNumberFormat()->getFormatCode());
        } finally {
            unlink($temporaryFile);
        }

        $xlsxResponse = $this->get(route('reports.inventory', [
            'report' => 'stock',
            'warehouse_id' => $this->warehouse->id,
            'cost_center_id' => $this->costCenter->id,
            'format' => 'xlsx',
        ]));
        $xlsxResponse->assertDownload('reporte-inventario-existencias-'.today()->format('Ymd').'.xlsx');
        $temporaryFile = tempnam(sys_get_temp_dir(), 'almacena-report-');
        $this->assertNotFalse($temporaryFile);

        try {
            file_put_contents($temporaryFile, $xlsxResponse->streamedContent());
            $sheet = IOFactory::load($temporaryFile)->getActiveSheet();
            $this->assertSame('Código de producto', $sheet->getCell('C1')->getValue());
            $this->assertSame('PROD-REPORT', $sheet->getCell('C2')->getValue());
            $this->assertEqualsWithDelta(101, $sheet->getCell('E2')->getValue(), 0.001);
            $this->assertSame('#,##0', $sheet->getCell('E2')->getStyle()->getNumberFormat()->getFormatCode());
            $this->assertSame('#,##0.00', $sheet->getCell('G2')->getStyle()->getNumberFormat()->getFormatCode());
        } finally {
            unlink($temporaryFile);
        }
    }

    public function test_purchasing_reports_export_orders_spend_and_requisitions(): void
    {
        $supplierName = '=HYPERLINK("https://example.test","Click")';
        $supplier = Supplier::create(['legal_name' => $supplierName]);
        $department = Department::create([
            'company_id' => $this->company->id,
            'cost_center_id' => $this->costCenter->id,
            'code' => 'AREA-EXPORT',
            'name' => 'Área exportación',
            'manager_user_id' => $this->administrator->id,
        ]);
        $requisition = Requisition::create([
            'reference' => 'REQ-EXPORT-01',
            'department_id' => $department->id,
            'warehouse_id' => $this->warehouse->id,
            'requester_user_id' => $this->administrator->id,
            'approver_user_id' => $this->administrator->id,
            'status' => Requisition::STATUS_APPROVED,
            'justification' => 'Datos para exportación.',
            'needed_by' => today()->addDays(3),
            'submitted_at' => now(),
            'decided_at' => now(),
        ]);
        $requisitionItem = RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'product_id' => $this->product->id,
            'unit_id' => $this->unit->id,
            'product_code' => $this->product->code,
            'product_name' => $this->product->name,
            'unit_abbreviation' => $this->unit->abbreviation,
            'quantity' => '2.000',
        ]);
        $order = PurchaseOrder::create([
            'reference' => 'PO-EXPORT-01',
            'requisition_id' => $requisition->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $supplier->id,
            'quote_currency_id' => $this->currency->id,
            'base_currency_id' => $this->currency->id,
            'exchange_rate' => '1.00000000',
            'created_by_user_id' => $this->administrator->id,
            'approved_by_user_id' => $this->administrator->id,
            'quote_reference' => 'QUOTE-EXPORT',
            'quote_date' => today(),
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'requisition_item_id' => $requisitionItem->id,
            'product_id' => $this->product->id,
            'product_code' => $this->product->code,
            'product_name' => $this->product->name,
            'unit_abbreviation' => $this->unit->abbreviation,
            'quantity' => '2.000',
            'unit_cost' => '4.5000',
            'unit_cost_base' => '4.5000',
            'tax_rate' => '19.0000',
        ]);

        $ordersResponse = $this->actingAs($this->administrator)
            ->get(route('reports.purchasing', [
                'report' => 'orders',
                'supplier_id' => $supplier->id,
                'format' => 'xlsx',
            ]));
        $ordersResponse->assertDownload('reporte-compras-ordenes-'.today()->format('Ymd').'.xlsx');
        $temporaryFile = tempnam(sys_get_temp_dir(), 'almacena-report-');
        $this->assertNotFalse($temporaryFile);

        try {
            file_put_contents($temporaryFile, $ordersResponse->streamedContent());
            $sheet = IOFactory::load($temporaryFile)->getActiveSheet();
            $this->assertSame('PO-EXPORT-01', $sheet->getCell('A2')->getValue());
            $this->assertSame($supplierName, $sheet->getCell('B2')->getValue());
            $this->assertSame('s', $sheet->getCell('B2')->getDataType());
            $this->assertEqualsWithDelta(10.71, $sheet->getCell('G2')->getValue(), 0.001);
            $this->assertSame('#,##0.00', $sheet->getCell('G2')->getStyle()->getNumberFormat()->getFormatCode());
        } finally {
            unlink($temporaryFile);
        }

        $spendResponse = $this->get(route('reports.purchasing', [
            'report' => 'spend',
            'supplier_id' => $supplier->id,
            'format' => 'csv',
        ]));
        $spendResponse->assertDownload('reporte-compras-compras-aprobadas-'.today()->format('Ymd').'.csv');
        $this->assertStringContainsString('PO-EXPORT-01', $spendResponse->streamedContent());
        $this->assertStringContainsString("'=HYPERLINK(", $spendResponse->streamedContent());
        $this->assertStringContainsString('10.71', $spendResponse->streamedContent());
        $spendRows = array_map('str_getcsv', explode("\n", trim(substr($spendResponse->streamedContent(), 3))));
        $this->assertSame('2', $spendRows[1][5]);
        $this->assertSame('4.50', $spendRows[1][6]);

        $spendXlsxResponse = $this->get(route('reports.purchasing', [
            'report' => 'spend',
            'supplier_id' => $supplier->id,
            'format' => 'xlsx',
        ]));
        $temporaryFile = tempnam(sys_get_temp_dir(), 'almacena-spend-');
        $this->assertNotFalse($temporaryFile);

        try {
            file_put_contents($temporaryFile, $spendXlsxResponse->streamedContent());
            $sheet = IOFactory::load($temporaryFile)->getActiveSheet();
            $this->assertSame('#,##0', $sheet->getCell('F2')->getStyle()->getNumberFormat()->getFormatCode());
            $this->assertSame('#,##0.00', $sheet->getCell('G2')->getStyle()->getNumberFormat()->getFormatCode());
            $this->assertSame('#,##0.00', $sheet->getCell('I2')->getStyle()->getNumberFormat()->getFormatCode());
        } finally {
            unlink($temporaryFile);
        }

        $requisitionsResponse = $this->get(route('reports.purchasing', [
            'report' => 'requisitions',
            'cost_center_id' => $this->costCenter->id,
            'format' => 'csv',
        ]));
        $requisitionsResponse->assertDownload('reporte-compras-requisiciones-'.today()->format('Ymd').'.csv');
        $this->assertStringContainsString('REQ-EXPORT-01', $requisitionsResponse->streamedContent());
    }

    public function test_reports_require_their_specific_permissions(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('reports.inventory'))
            ->assertForbidden();
        $this->get(route('reports.inventory', ['format' => 'csv']))
            ->assertForbidden();

        $this->get(route('reports.purchasing'))
            ->assertForbidden();
        $this->get(route('reports.purchasing', ['format' => 'xlsx']))
            ->assertForbidden();
    }

    private function lot(Warehouse $warehouse, string $code): WarehouseLot
    {
        return WarehouseLot::create([
            'warehouse_id' => $warehouse->id,
            'product_id' => $this->product->id,
            'receipt_item_id' => null,
            'lot_code' => $code,
            'quantity_received' => '10.000',
            'unit_cost' => '2.0000',
            'unit_cost_base' => '2.0000',
            'quote_currency_id' => $this->currency->id,
            'base_currency_id' => $this->currency->id,
            'exchange_rate' => '1.00000000',
            'received_at' => now(),
        ]);
    }

    private function movement(
        WarehouseLot $lot,
        Warehouse $warehouse,
        string $type,
        string $referenceType,
        string $quantity,
    ): StockMovement {
        return StockMovement::create([
            'warehouse_lot_id' => $lot->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $this->product->id,
            'user_id' => $this->administrator->id,
            'movement_type' => $type,
            'reference_type' => $referenceType,
            'reference_id' => 1,
            'quantity' => $quantity,
            'unit_cost_base' => '2.0000',
            'base_currency_id' => $this->currency->id,
            'occurred_at' => now(),
        ]);
    }
}
