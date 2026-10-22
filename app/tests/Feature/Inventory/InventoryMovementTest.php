<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Department;
use App\Models\InventoryIssue;
use App\Models\InventoryIssueAllocation;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Receipt;
use App\Models\Requisition;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLot;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryMovementTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Department $department;

    private Product $product;

    private Supplier $supplier;

    private Warehouse $warehouse;

    private User $requester;

    private User $requisitionApprover;

    private User $orderCreator;

    private User $orderApprover;

    private User $warehouseOperator;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessControlSeeder::class);
        $this->company = Company::create([
            'tax_id' => '900123456',
            'legal_name' => 'Empresa de prueba',
            'address' => 'Calle 1',
            'phone' => '123',
            'country' => 'Colombia',
            'administrative_division' => 'Cundinamarca',
            'city' => 'Bogotá',
            'base_currency_id' => Currency::query()->where('code', 'COP')->value('id'),
        ]);
        $branch = Branch::create([
            'company_id' => $this->company->id,
            'code' => 'HQ',
            'name' => 'Sede principal',
        ]);
        $this->warehouse = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'MAIN',
            'name' => 'Bodega principal',
        ]);

        $this->requester = $this->userWithRole('solicitante', true);
        $this->requisitionApprover = $this->userWithRole('compras', true);
        $this->orderCreator = $this->userWithRole('compras', true);
        $this->orderApprover = $this->userWithRole('compras', true);
        $this->warehouseOperator = $this->userWithRole('almacen', true);
        $this->administrator = $this->userWithRole('administracion', false);
        $this->department = Department::create([
            'company_id' => $this->company->id,
            'code' => 'OPS',
            'name' => 'Operaciones',
            'manager_user_id' => $this->requisitionApprover->id,
        ]);
        $this->requester->update(['department_id' => $this->department->id]);
        $unit = Unit::create([
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
        ]);
        $this->product = Product::create([
            'code' => 'PROD-01',
            'name' => 'Producto de prueba',
            'unit_id' => $unit->id,
        ]);
        $this->supplier = Supplier::create(['legal_name' => 'Proveedor de prueba']);
    }

    public function test_purchase_order_requires_separate_approval_and_receipts_cannot_exceed_the_order(): void
    {
        $requisition = $this->approvedRequisition('5.000');
        $this->actingAs($this->orderCreator)
            ->get(route('purchasing.orders.create', ['requisition_id' => $requisition->id]))
            ->assertOk()
            ->assertSee($this->product->name);
        $order = $this->createPurchaseOrder($requisition, '1250.2500');

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->status);
        $this->actingAs($this->orderCreator)->get(route('purchasing.orders.show', $order))->assertOk();
        $this->actingAs($this->warehouseOperator)
            ->get(route('inventory.receipts.create', ['purchase_order_id' => $order->id]))
            ->assertForbidden();
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.orders.approve', $order))
            ->assertForbidden();
        $this->actingAs($this->orderApprover)
            ->post(route('purchasing.orders.approve', $order))
            ->assertRedirect(route('purchasing.orders.show', $order));
        $this->assertSame(PurchaseOrder::STATUS_APPROVED, $order->fresh()->status);
        $this->actingAs($this->warehouseOperator)
            ->get(route('inventory.receipts.create', ['purchase_order_id' => $order->id]))
            ->assertOk()
            ->assertSee($this->product->name);

        $orderItem = $order->items()->firstOrFail();
        $receiptData = [
            'purchase_order_id' => $order->id,
            'document_reference' => 'REM-001',
            'items' => [
                ['purchase_order_item_id' => $orderItem->id, 'quantity' => '3.000'],
            ],
        ];
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), $receiptData)
            ->assertRedirect(route('inventory.receipts.index'));

        $this->assertDatabaseHas('receipts', [
            'purchase_order_id' => $order->id,
            'warehouse_id' => $this->warehouse->id,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'quantity' => '3.000',
            'unit_cost_base' => '1250.2500',
            'base_currency_id' => $this->company->base_currency_id,
        ]);
        $receipt = Receipt::query()->firstOrFail();
        $this->actingAs($this->warehouseOperator)->get(route('inventory.receipts.show', $receipt))->assertOk();
        $this->get(route('inventory.receipts.index'))->assertOk();

        $receiptData['items'][0]['quantity'] = '3.000';
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), $receiptData)
            ->assertSessionHasErrors('items');
        $this->assertSame(1, Receipt::query()->count());

        $unassignedOperator = $this->userWithRole('almacen', false);
        $this->actingAs($unassignedOperator)
            ->get(route('purchasing.orders.show', $order))
            ->assertForbidden();
    }

    public function test_issues_are_partial_use_fefo_lot_costs_and_never_dispatch_expired_stock(): void
    {
        $this->product->update(['tracks_expiration' => true]);
        $firstOrder = $this->approvedPurchaseOrder('3.000', '2.5000');
        $firstOrderItem = $firstOrder->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), [
                'purchase_order_id' => $firstOrder->id,
                'items' => [
                    ['purchase_order_item_id' => $firstOrderItem->id, 'quantity' => '3.000'],
                ],
            ])->assertSessionHasErrors('items.0.expires_on');
        $this->assertSame(0, Receipt::query()->count());
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), [
                'purchase_order_id' => $firstOrder->id,
                'items' => [
                    ['purchase_order_item_id' => $firstOrderItem->id, 'quantity' => '3.000', 'expires_on' => '2026-11-01'],
                ],
            ])->assertRedirect();

        $secondOrder = $this->approvedPurchaseOrder('5.000', '3.5000');
        $secondOrderItem = $secondOrder->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)->post(route('inventory.receipts.store'), [
            'purchase_order_id' => $secondOrder->id,
            'items' => [
                ['purchase_order_item_id' => $secondOrderItem->id, 'quantity' => '5.000', 'expires_on' => '2026-12-01'],
            ],
        ])->assertRedirect();

        $issueRequisition = $this->approvedRequisition('6.000');
        $issueItem = $issueRequisition->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)
            ->get(route('inventory.issues.create', ['requisition_id' => $issueRequisition->id]))
            ->assertOk()
            ->assertSee($this->product->name);
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.issues.store'), [
                'requisition_id' => $issueRequisition->id,
                'items' => [
                    ['requisition_item_id' => $issueItem->id, 'quantity' => '7.000'],
                ],
            ])->assertSessionHasErrors('items');
        $this->assertSame(0, InventoryIssue::query()->count());
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.issues.store'), [
                'requisition_id' => $issueRequisition->id,
                'items' => [
                    ['requisition_item_id' => $issueItem->id, 'quantity' => '3.000'],
                ],
            ])->assertRedirect(route('inventory.issues.index'));
        $this->actingAs($this->warehouseOperator)->post(route('inventory.issues.store'), [
            'requisition_id' => $issueRequisition->id,
            'items' => [
                ['requisition_item_id' => $issueItem->id, 'quantity' => '3.000'],
            ],
        ])->assertRedirect(route('inventory.issues.index'));
        $firstIssue = InventoryIssue::query()->oldest('id')->firstOrFail();
        $this->get(route('inventory.issues.show', $firstIssue))->assertOk();
        $this->get(route('inventory.issues.index'))->assertOk();

        $this->actingAs($this->warehouseOperator)->post(route('inventory.issues.store'), [
            'requisition_id' => $issueRequisition->id,
            'items' => [
                ['requisition_item_id' => $issueItem->id, 'quantity' => '4.000'],
            ],
        ])->assertSessionHasErrors('items');

        $this->assertSame(2, InventoryIssue::query()->count());
        $this->assertDatabaseHas('inventory_issue_allocations', [
            'quantity' => '3.000',
            'unit_cost' => '2.5000',
        ]);
        $this->assertDatabaseHas('inventory_issue_allocations', [
            'quantity' => '3.000',
            'unit_cost' => '3.5000',
        ]);
        $this->assertSame(2, InventoryIssueAllocation::query()->count());
        $this->assertEquals(6.0, StockMovement::query()->where('movement_type', StockMovement::TYPE_ISSUE)->sum('quantity'));

        $anotherRequisition = $this->approvedRequisition('1.000');
        $anotherItem = $anotherRequisition->items()->firstOrFail();
        $this->travelTo(now()->setDate(2026, 12, 2));
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.issues.store'), [
                'requisition_id' => $anotherRequisition->id,
                'items' => [
                    ['requisition_item_id' => $anotherItem->id, 'quantity' => '1.000'],
                ],
            ])->assertSessionHasErrors('items.0.quantity');

        $this->assertSame(2, InventoryIssue::query()->count());
        $this->travelBack();
    }

    public function test_purchase_order_converts_quote_costs_to_the_company_base_currency(): void
    {
        $requisition = $this->approvedRequisition('2.000');
        $item = $requisition->items()->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.orders.store'), [
                'requisition_id' => $requisition->id,
                'supplier_id' => $this->supplier->id,
                'quote_currency_id' => $usd->id,
                'exchange_rate' => '4000.00000000',
                'quote_reference' => 'USD-001',
                'quote_date' => today()->toDateString(),
                'unit_costs' => [$item->id => '1250.2500'],
            ])
            ->assertRedirect();

        $order = PurchaseOrder::query()->where('requisition_id', $requisition->id)->firstOrFail();
        $this->assertSame($usd->id, $order->quote_currency_id);
        $this->assertSame($this->company->base_currency_id, $order->base_currency_id);
        $this->assertSame('4000.00000000', $order->exchange_rate);
        $this->assertEquals(5001000.0, (float) $order->items()->firstOrFail()->unit_cost_base);

        $this->actingAs($this->orderApprover)
            ->post(route('purchasing.orders.approve', $order))
            ->assertRedirect();
        $orderItem = $order->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), [
                'purchase_order_id' => $order->id,
                'items' => [
                    ['purchase_order_item_id' => $orderItem->id, 'quantity' => '2.000'],
                ],
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('receipt_items', [
            'purchase_order_item_id' => $orderItem->id,
            'unit_cost_base' => '5001000.0000',
        ]);
        $lot = WarehouseLot::query()->firstOrFail();
        $this->assertSame($usd->id, $lot->quote_currency_id);
        $this->assertSame($this->company->base_currency_id, $lot->base_currency_id);
        $this->assertSame('4000.00000000', $lot->exchange_rate);
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_lot_id' => $lot->id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'unit_cost_base' => '5001000.0000',
            'base_currency_id' => $this->company->base_currency_id,
        ]);

        $issueRequisition = $this->approvedRequisition('1.000');
        $issueItem = $issueRequisition->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.issues.store'), [
                'requisition_id' => $issueRequisition->id,
                'items' => [
                    ['requisition_item_id' => $issueItem->id, 'quantity' => '1.000'],
                ],
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('inventory_issue_allocations', [
            'warehouse_lot_id' => $lot->id,
            'unit_cost' => '1250.2500',
            'unit_cost_base' => '5001000.0000',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_lot_id' => $lot->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'unit_cost_base' => '5001000.0000',
            'base_currency_id' => $this->company->base_currency_id,
        ]);

        $this->actingAs($this->administrator)
            ->put(route('admin.catalogs.currencies.update', $usd), [
                'name' => $usd->name,
                'symbol' => $usd->symbol,
                'decimal_places' => $usd->decimal_places,
                'is_active' => 0,
            ])
            ->assertSessionHasErrors('is_active');

        $sameCurrencyRequisition = $this->approvedRequisition('1.000');
        $sameCurrencyItem = $sameCurrencyRequisition->items()->firstOrFail();
        $cop = Currency::query()->where('code', 'COP')->firstOrFail();
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.orders.store'), [
                'requisition_id' => $sameCurrencyRequisition->id,
                'supplier_id' => $this->supplier->id,
                'quote_currency_id' => $cop->id,
                'exchange_rate' => '2.00000000',
                'quote_reference' => 'COP-001',
                'quote_date' => today()->toDateString(),
                'unit_costs' => [$sameCurrencyItem->id => '10.0000'],
            ])
            ->assertSessionHasErrors('exchange_rate');
        $this->assertDatabaseMissing('purchase_orders', ['requisition_id' => $sameCurrencyRequisition->id]);
    }

    public function test_company_base_currency_cannot_change_after_a_purchase_order_exists(): void
    {
        $requisition = $this->approvedRequisition('1.000');
        $order = $this->createPurchaseOrder($requisition, '10.0000');
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();

        $this->actingAs($this->administrator)
            ->put(route('admin.company.update'), [
                'tax_id' => $this->company->tax_id,
                'legal_name' => $this->company->legal_name,
                'address' => $this->company->address,
                'phone' => $this->company->phone,
                'country' => $this->company->country,
                'administrative_division' => $this->company->administrative_division,
                'city' => $this->company->city,
                'base_currency_id' => $usd->id,
            ])
            ->assertSessionHasErrors('base_currency_id');

        $this->assertDatabaseHas('purchase_orders', ['id' => $order->id]);
        $this->assertSame('COP', $this->company->fresh()->baseCurrency->code);
    }

    public function test_kardex_shows_signed_movements_and_enforces_warehouse_access(): void
    {
        $order = $this->approvedPurchaseOrder('2.000', '15.0000');
        $orderItem = $order->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), [
                'purchase_order_id' => $order->id,
                'items' => [
                    ['purchase_order_item_id' => $orderItem->id, 'quantity' => '2.000'],
                ],
            ])
            ->assertRedirect();

        $requisition = $this->approvedRequisition('0.750');
        $requisitionItem = $requisition->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)->post(route('inventory.issues.store'), [
            'requisition_id' => $requisition->id,
            'items' => [
                ['requisition_item_id' => $requisitionItem->id, 'quantity' => '0.750'],
            ],
        ])->assertRedirect();

        $this->get(route('inventory.kardex', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'from_date' => today()->toDateString(),
            'to_date' => today()->toDateString(),
        ]))
            ->assertOk()
            ->assertSee('ENT-')
            ->assertSee('SAL-')
            ->assertSee('+2,000')
            ->assertSee('-0,750')
            ->assertSee('1,250');

        $this->get(route('inventory.kardex'))
            ->assertOk()
            ->assertSee('PROD-01')
            ->assertSee('Bodega principal');
        $csv = $this->get(route('inventory.kardex.export', ['format' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Código de producto', $csv->streamedContent());
        $this->assertStringContainsString('PROD-01', $csv->streamedContent());
        $this->assertStringContainsString('2,000', $csv->streamedContent());
        $xlsx = $this->get(route('inventory.kardex.export', ['format' => 'xlsx']));
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertSame('PK', substr($xlsx->streamedContent(), 0, 2));
        $pdf = $this->get(route('inventory.kardex.export', ['format' => 'pdf']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());

        $unassignedOperator = $this->userWithRole('almacen', false);
        $this->actingAs($unassignedOperator)
            ->get(route('inventory.kardex', [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
            ]))
            ->assertSessionHasErrors('warehouse_id');

        $this->actingAs($this->requester)
            ->get(route('inventory.kardex'))
            ->assertForbidden();
    }

    private function userWithRole(string $roleSlug, bool $withWarehouse): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::query()->where('slug', $roleSlug)->firstOrFail());
        if ($withWarehouse) {
            $user->warehouses()->attach($this->warehouse);
        }

        return $user;
    }

    private function approvedRequisition(string $quantity): Requisition
    {
        $this->actingAs($this->requester)
            ->post(route('purchasing.requisitions.store'), [
                'warehouse_id' => $this->warehouse->id,
                'justification' => 'Reposición para operación.',
                'needed_by' => today()->addDays(5)->toDateString(),
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => $quantity],
                ],
            ])->assertRedirect();
        $requisition = Requisition::query()->latest('id')->firstOrFail();
        $this->post(route('purchasing.requisitions.submit', $requisition))->assertRedirect();
        $this->actingAs($this->requisitionApprover)
            ->post(route('purchasing.requisitions.approve', $requisition))
            ->assertRedirect();

        return $requisition->fresh();
    }

    private function createPurchaseOrder(Requisition $requisition, string $unitCost): PurchaseOrder
    {
        $item = $requisition->items()->firstOrFail();
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.orders.store'), [
                'requisition_id' => $requisition->id,
                'supplier_id' => $this->supplier->id,
                'quote_currency_id' => Currency::query()->where('code', 'COP')->value('id'),
                'exchange_rate' => '1.00000000',
                'quote_reference' => 'COT-001',
                'quote_date' => today()->toDateString(),
                'unit_costs' => [$item->id => $unitCost],
            ])->assertRedirect();

        return PurchaseOrder::query()->where('requisition_id', $requisition->id)->firstOrFail();
    }

    private function approvedPurchaseOrder(string $quantity, string $unitCost): PurchaseOrder
    {
        $requisition = $this->approvedRequisition($quantity);
        $order = $this->createPurchaseOrder($requisition, $unitCost);
        $this->actingAs($this->orderApprover)
            ->post(route('purchasing.orders.approve', $order))
            ->assertRedirect();

        return $order->fresh();
    }
}
