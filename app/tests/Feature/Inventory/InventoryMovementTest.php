<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Department;
use App\Models\InventoryIssue;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryIssueItem;
use App\Models\Permission;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Receipt;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLot;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            ->assertRedirect(route('purchasing.quotes.index', $requisition));
        $order = $this->createPurchaseOrder($requisition, '1250.2500');

        $this->assertSame(PurchaseOrder::STATUS_DRAFT, $order->status);
        $this->actingAs($this->orderCreator)
            ->get(route('purchasing.orders.show', $order))
            ->assertOk()
            ->assertSee('Marca de prueba')
            ->assertSee('Impuesto (%)');
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

        $issueRequisition = $this->activeRequisition('6.000');
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

    public function test_partial_requisition_dispatch_requires_its_dedicated_permission(): void
    {
        $issueOnlyRole = Role::create([
            'name' => 'Solo salidas generales',
            'slug' => 'solo-salidas-generales',
            'is_active' => true,
        ]);
        $issueOnlyRole->permissions()->attach(
            Permission::query()->where('code', 'inventory.issues.create')->value('id'),
        );
        $issueOnlyUser = User::factory()->create();
        $issueOnlyUser->roles()->attach($issueOnlyRole);
        $issueOnlyUser->warehouses()->attach($this->warehouse);
        $requisition = $this->activeRequisition('2.000');

        $this->actingAs($issueOnlyUser)
            ->get(route('inventory.issues.create', ['requisition_id' => $requisition->id]))
            ->assertForbidden();
        $this->post(route('inventory.issues.store'), [
            'requisition_id' => $requisition->id,
            'items' => [
                ['requisition_item_id' => $requisition->items()->firstOrFail()->id, 'quantity' => '1.000'],
            ],
        ])->assertForbidden();
        $this->assertSame(0, InventoryIssue::query()->count());
    }

    public function test_quote_registration_and_comparison_permissions_are_independent(): void
    {
        $requisition = $this->approvedRequisition('5.000');
        $registrarRole = Role::create([
            'name' => 'Registro de cotizaciones',
            'slug' => 'registro-cotizaciones',
            'is_active' => true,
        ]);
        $registrarRole->permissions()->attach(
            Permission::query()->where('code', 'purchases.quotes.manage')->value('id'),
        );
        $registrar = User::factory()->create();
        $registrar->roles()->attach($registrarRole);
        $registrar->warehouses()->attach($this->warehouse);

        $this->actingAs($registrar)
            ->get(route('purchasing.quotes.register', $requisition))
            ->assertOk();
        $this->get(route('purchasing.quotes.index', $requisition))->assertForbidden();
        $this->post(route('purchasing.quotes.select', $requisition))->assertForbidden();
        $this->post(route('purchasing.quotes.generate-orders', $requisition))->assertForbidden();

        $viewerRole = Role::create([
            'name' => 'Consulta de cotizaciones',
            'slug' => 'consulta-cotizaciones',
            'is_active' => true,
        ]);
        $viewerRole->permissions()->attach(
            Permission::query()->where('code', 'purchases.quotes.view')->value('id'),
        );
        $viewer = User::factory()->create();
        $viewer->roles()->attach($viewerRole);
        $viewer->warehouses()->attach($this->warehouse);

        $this->actingAs($viewer)
            ->get(route('purchasing.quotes.index', $requisition))
            ->assertOk()
            ->assertDontSee('Guardar comparación');
        $this->get(route('purchasing.quotes.register', $requisition))->assertForbidden();
        $this->post(route('purchasing.quotes.select', $requisition))->assertForbidden();
        $this->post(route('purchasing.quotes.generate-orders', $requisition))->assertForbidden();
    }

    public function test_partial_issue_can_include_only_selected_requisition_items(): void
    {
        $order = $this->approvedPurchaseOrder('2.000', '2.5000');
        $orderItem = $order->items()->firstOrFail();
        $this->actingAs($this->warehouseOperator)->post(route('inventory.receipts.store'), [
            'purchase_order_id' => $order->id,
            'items' => [
                ['purchase_order_item_id' => $orderItem->id, 'quantity' => '2.000'],
            ],
        ])->assertRedirect();

        $secondProduct = Product::create([
            'code' => 'PROD-02',
            'name' => 'Producto sin existencias',
            'unit_id' => $this->product->unit_id,
        ]);
        $this->actingAs($this->requester)
            ->post(route('purchasing.requisitions.store'), [
                'warehouse_id' => $this->warehouse->id,
                'justification' => 'Entrega parcial de varios productos.',
                'needed_by' => today()->addDays(5)->toDateString(),
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => '5.000'],
                    ['product_id' => $secondProduct->id, 'quantity' => '3.000'],
                ],
            ])->assertRedirect();
        $requisition = Requisition::query()->latest('id')->firstOrFail();
        $this->post(route('inventory.requisitions.activate', $requisition))->assertRedirect();
        $this->post(route('purchasing.requisitions.submit', $requisition))->assertRedirect();
        $this->actingAs($this->requisitionApprover)
            ->post(route('purchasing.requisitions.approve', $requisition))
            ->assertRedirect();
        $items = $requisition->items()->orderBy('id')->get();

        $this->actingAs($this->warehouseOperator)
            ->get(route('inventory.issues.create', ['requisition_id' => $requisition->id]))
            ->assertOk()
            ->assertSee('Incluir producto')
            ->assertSee('Marca solo los productos que entregarás en esta salida.');

        $this->post(route('inventory.issues.store'), [
            'requisition_id' => $requisition->id,
            'items' => [
                0 => ['requisition_item_id' => $items[0]->id, 'quantity' => '1.000'],
            ],
        ])->assertRedirect(route('inventory.issues.index'));

        $issue = InventoryIssue::query()->firstOrFail();
        $this->assertSame(1, $issue->items()->count());
        $this->assertDatabaseHas('inventory_issue_items', [
            'inventory_issue_id' => $issue->id,
            'requisition_item_id' => $items[0]->id,
            'quantity' => '1.000',
        ]);
        $this->assertDatabaseMissing('inventory_issue_items', [
            'inventory_issue_id' => $issue->id,
            'requisition_item_id' => $items[1]->id,
        ]);

        $this->post(route('inventory.issues.store'), [
            'requisition_id' => $requisition->id,
            'items' => [],
        ])->assertSessionHasErrors('items');
        $this->assertSame(1, InventoryIssue::query()->count());
    }

    public function test_purchase_order_converts_quote_costs_to_the_company_base_currency(): void
    {
        $requisition = $this->approvedRequisition('2.000');
        $item = $requisition->items()->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->firstOrFail();
        $this->submitQuote($requisition, $this->supplier, $usd, '4000.00000000', '1250.2500', 'USD-001');
        $otherSupplier = Supplier::create(['legal_name' => 'Proveedor alternativo']);
        $this->submitQuote($requisition, $otherSupplier, $usd, '4000.00000000', '1500.2500', 'USD-002');
        $this->selectQuoteOffer($requisition, $item, $this->supplier);
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
            ->post(route('purchasing.quotes.store', $sameCurrencyRequisition), [
                'supplier_id' => $this->supplier->id,
                'quote_currency_id' => $cop->id,
                'exchange_rate' => '2.00000000',
                'quote_reference' => 'COP-001',
                'quote_date' => today()->toDateString(),
                'items' => [
                    $sameCurrencyItem->id => [
                        'requisition_item_id' => $sameCurrencyItem->id,
                        'unit_cost' => '10.0000',
                        'tax_rate' => '0',
                        'brand' => 'Marca',
                        'delivery_days' => 2,
                    ],
                ],
            ])
            ->assertSessionHasErrors('exchange_rate');
        $this->assertDatabaseMissing('supplier_quotes', ['requisition_id' => $sameCurrencyRequisition->id]);
    }

    public function test_quote_comparison_requires_two_suppliers_and_allows_safe_selection_in_stages(): void
    {
        $requisition = $this->approvedRequisition('2.000');
        $firstItem = $requisition->items()->firstOrFail();
        $secondProduct = Product::create([
            'code' => 'PROD-02',
            'name' => 'Segundo producto',
            'unit_id' => $this->product->unit_id,
        ]);
        $secondItem = RequisitionItem::create([
            'requisition_id' => $requisition->id,
            'product_id' => $secondProduct->id,
            'unit_id' => $secondProduct->unit_id,
            'product_code' => $secondProduct->code,
            'product_name' => $secondProduct->name,
            'unit_abbreviation' => 'und',
            'quantity' => '3.000',
        ]);
        $cop = Currency::query()->where('code', 'COP')->firstOrFail();
        $otherSupplier = Supplier::create(['legal_name' => 'Proveedor alternativo']);
        $firstQuote = $this->submitQuote($requisition, $this->supplier, $cop, '1.00000000', '100.0000', 'COT-ETAPAS', $firstItem);

        $firstOffer = SupplierQuoteItem::query()
            ->where('requisition_item_id', $firstItem->id)
            ->whereHas('quote', fn ($query) => $query->where('supplier_id', $this->supplier->id))
            ->firstOrFail();
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.select', $requisition), ['awards' => [$firstItem->id => $firstOffer->id]])
            ->assertRedirect()
            ->assertSessionHasErrors("awards.{$firstItem->id}");
        $this->assertDatabaseCount('purchase_orders', 0);

        $this->submitQuote($requisition, $otherSupplier, $cop, '1.00000000', '110.0000', 'COT-ALT-1', $firstItem);
        $this->actingAs($this->orderCreator)
            ->get(route('purchasing.quotes.index', $requisition))
            ->assertOk()
            ->assertSee('Comparación de cotizaciones')
            ->assertSee($otherSupplier->legal_name);
        $otherOffer = SupplierQuoteItem::query()
            ->where('requisition_item_id', $firstItem->id)
            ->whereHas('quote', fn ($query) => $query->where('supplier_id', $otherSupplier->id))
            ->firstOrFail();
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.select', $requisition), [
                'awards' => [$firstItem->id => $otherOffer->id],
            ])
            ->assertSessionHasErrors("justifications.{$firstItem->id}");
        $this->assertDatabaseCount('purchase_orders', 0);

        $this->selectQuoteOffer($requisition, $firstItem, $this->supplier);
        $firstOrder = PurchaseOrder::query()->where('requisition_id', $requisition->id)->firstOrFail();
        $this->submitQuote($requisition, $this->supplier, $cop, '1.00000000', '200.0000', 'COT-ETAPAS', $secondItem);
        $this->submitQuote($requisition, $otherSupplier, $cop, '1.00000000', '210.0000', 'COT-ALT-1', $secondItem);

        $this->selectQuoteOffer($requisition, $secondItem, $otherSupplier, 'Se prioriza el plazo de entrega acordado para esta etapa.');

        $this->assertSame(2, PurchaseOrder::query()->where('requisition_id', $requisition->id)->count());
        $this->assertSame(1, $firstOrder->fresh()->items()->count());
        $this->assertSame(2, $firstQuote->fresh()->items()->count());
        $this->assertDatabaseHas('purchase_order_items', [
            'purchase_order_id' => $firstOrder->id,
            'requisition_item_id' => $firstItem->id,
            'unit_cost' => '100.0000',
        ]);
    }

    public function test_quote_comparison_can_be_saved_before_generating_orders(): void
    {
        $requisition = $this->approvedRequisition('2.000');
        $item = $requisition->items()->firstOrFail();
        $cop = Currency::query()->where('code', 'COP')->firstOrFail();
        $otherSupplier = Supplier::create(['legal_name' => 'Proveedor para comparación guardada']);
        $issue = InventoryIssue::create([
            'reference' => 'ISS-QUOTE-1',
            'requisition_id' => $requisition->id,
            'warehouse_id' => $requisition->warehouse_id,
            'issued_by_user_id' => $this->warehouseOperator->id,
            'issued_at' => now(),
        ]);
        InventoryIssueItem::create([
            'inventory_issue_id' => $issue->id,
            'requisition_item_id' => $item->id,
            'product_id' => $item->product_id,
            'product_code' => $item->product_code,
            'product_name' => $item->product_name,
            'unit_abbreviation' => $item->unit_abbreviation,
            'quantity' => '0.750',
        ]);

        $this->actingAs($this->orderCreator)
            ->get(route('purchasing.quotes.index', $requisition))
            ->assertOk()
            ->assertSee('Guardar comparación')
            ->assertSee('Saldo para comprar:')
            ->assertSee('Registra ofertas de al menos dos proveedores distintos para un producto antes de guardar la comparación.');

        $this->submitQuote($requisition, $this->supplier, $cop, '1.00000000', '100.0000', 'COT-GUARDADA', $item);
        $this->submitQuote($requisition, $otherSupplier, $cop, '1.00000000', '120.0000', 'COT-GUARDADA-2', $item);
        $offer = SupplierQuoteItem::query()
            ->where('requisition_item_id', $item->id)
            ->whereHas('quote', fn ($query) => $query->where('supplier_id', $this->supplier->id))
            ->firstOrFail();

        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.select', $requisition), [
                'awards' => [$item->id => $offer->id],
            ])
            ->assertRedirect(route('purchasing.quotes.index', $requisition))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('requisition_item_awards', [
            'requisition_item_id' => $item->id,
            'supplier_quote_item_id' => $offer->id,
        ]);
        $this->assertDatabaseCount('purchase_orders', 0);
        $this->actingAs($this->orderCreator)
            ->get(route('purchasing.quotes.index', $requisition))
            ->assertOk()
            ->assertSee('Generar órdenes borrador para la comparación guardada');

        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.generate-orders', $requisition))
            ->assertRedirect(route('purchasing.requisitions.show', $requisition));
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseHas('purchase_order_items', [
            'requisition_item_id' => $item->id,
            'quantity' => '1.250',
        ]);
        $this->assertDatabaseHas('purchase_order_items', [
            'requisition_item_id' => $item->id,
            'supplier_quote_item_id' => $offer->id,
        ]);

        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.generate-orders', $requisition))
            ->assertSessionHasErrors('awards');
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_quote_registration_has_a_dedicated_page_and_approved_requisition_queue(): void
    {
        $requisition = $this->approvedRequisition('2.000');

        $this->actingAs($this->orderCreator)
            ->get(route('purchasing.quotes.queue'))
            ->assertOk()
            ->assertSee($requisition->reference)
            ->assertSee(route('purchasing.quotes.register', $requisition));

        $this->get(route('purchasing.quotes.register', $requisition))
            ->assertOk()
            ->assertSee('Registrar o completar cotización')
            ->assertSee('Guardar cotización');

        $this->get(route('purchasing.quotes.index', $requisition))
            ->assertOk()
            ->assertSee(route('purchasing.quotes.register', $requisition))
            ->assertDontSee('Guardar cotización');

        $this->actingAs($this->warehouseOperator)
            ->get(route('purchasing.quotes.queue'))
            ->assertForbidden();
    }

    public function test_supplier_evaluations_keep_history_and_require_catalog_management(): void
    {
        $this->actingAs($this->requester)
            ->get(route('admin.catalogs.supplier-evaluations.index'))
            ->assertForbidden();

        $this->actingAs($this->administrator)
            ->post(route('admin.catalogs.supplier-evaluations.store'), [
                'supplier_id' => $this->supplier->id,
                'distance_score' => 5,
                'delivery_time_score' => 4,
                'fulfillment_score' => 3,
            ])
            ->assertRedirect(route('admin.catalogs.supplier-evaluations.index'));
        $this->actingAs($this->administrator)
            ->post(route('admin.catalogs.supplier-evaluations.store'), [
                'supplier_id' => $this->supplier->id,
                'distance_score' => 2,
                'delivery_time_score' => 2,
                'fulfillment_score' => 2,
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('supplier_evaluations', 2);
        $this->assertDatabaseHas('supplier_evaluations', [
            'supplier_id' => $this->supplier->id,
            'average_score' => '4.00',
        ]);
        $this->assertSame('2.00', $this->supplier->fresh()->latestEvaluation->average_score);
        $this->actingAs($this->administrator)
            ->get(route('admin.catalogs.supplier-evaluations.index'))
            ->assertOk()
            ->assertSee('2.00')
            ->assertSee('Historial de evaluaciones');
    }

    public function test_quote_selection_compares_tax_inclusive_base_totals_but_keeps_inventory_cost_net(): void
    {
        $requisition = $this->approvedRequisition('2.000');
        $item = $requisition->items()->firstOrFail();
        $cop = Currency::query()->where('code', 'COP')->firstOrFail();
        $otherSupplier = Supplier::create(['legal_name' => 'Proveedor sin impuesto']);
        $this->submitQuote($requisition, $this->supplier, $cop, '1.00000000', '100.0000', 'COT-TAX-20', $item, '20.0000');
        $this->submitQuote($requisition, $otherSupplier, $cop, '1.00000000', '110.0000', 'COT-TAX-0', $item);

        $this->selectQuoteOffer($requisition, $item, $otherSupplier);
        $order = PurchaseOrder::query()->where('requisition_id', $requisition->id)->firstOrFail();
        $orderItem = $order->items()->firstOrFail();

        $this->assertSame($otherSupplier->id, $order->supplier_id);
        $this->assertEquals(110.0, (float) $orderItem->unit_cost);
        $this->assertEquals(110.0, (float) $orderItem->unit_cost_base);
        $this->assertEquals(0.0, (float) $orderItem->tax_rate);
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

    public function test_company_base_currency_cannot_change_after_a_supplier_quote_exists(): void
    {
        $requisition = $this->approvedRequisition('1.000');
        $this->submitQuote(
            $requisition,
            $this->supplier,
            Currency::query()->where('code', 'COP')->firstOrFail(),
            '1.00000000',
            '10.0000',
            'COT-BASE',
        );
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

        $this->assertDatabaseCount('purchase_orders', 0);
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
            ->assertSee('1,250')
            ->assertSee('Formato regional: 17/08/2026');

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

    public function test_kardex_consolidates_accessible_warehouses_and_products_in_exports(): void
    {
        $firstOrder = $this->approvedPurchaseOrder('2.000', '15.0000');
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), [
                'purchase_order_id' => $firstOrder->id,
                'items' => [
                    ['purchase_order_item_id' => $firstOrder->items()->firstOrFail()->id, 'quantity' => '2.000'],
                ],
            ])
            ->assertRedirect();

        $secondWarehouse = Warehouse::create([
            'branch_id' => $this->warehouse->branch_id,
            'code' => 'SECOND',
            'name' => 'Bodega secundaria',
        ]);
        foreach ([$this->requester, $this->requisitionApprover, $this->orderCreator, $this->orderApprover, $this->warehouseOperator] as $user) {
            $user->warehouses()->syncWithoutDetaching([$secondWarehouse->id]);
        }
        $secondProduct = Product::create([
            'code' => 'PROD-02',
            'name' => 'Segundo producto',
            'unit_id' => $this->product->unit_id,
        ]);
        $secondOrder = $this->approvedPurchaseOrder('1.250', '20.0000', $secondWarehouse, $secondProduct);
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), [
                'purchase_order_id' => $secondOrder->id,
                'items' => [
                    ['purchase_order_item_id' => $secondOrder->items()->firstOrFail()->id, 'quantity' => '1.250'],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($this->warehouseOperator)
            ->get(route('inventory.kardex'))
            ->assertOk()
            ->assertSee('Bodega principal')
            ->assertSee('Bodega secundaria')
            ->assertSee('PROD-01')
            ->assertSee('PROD-02')
            ->assertSee('2 movimientos en el informe');

        $csv = $this->get(route('inventory.kardex.export', ['format' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvContent = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csvContent);
        $csvStream = fopen('php://temp', 'r+');
        fwrite($csvStream, substr($csvContent, 3));
        rewind($csvStream);
        $csvHeadings = fgetcsv($csvStream);
        $csvRows = [];
        while (($row = fgetcsv($csvStream)) !== false) {
            $csvRows[] = $row;
        }
        fclose($csvStream);
        $this->assertCount(13, $csvHeadings);
        $this->assertCount(2, $csvRows);
        $this->assertContains('2,000', array_column($csvRows, 8));
        $this->assertContains('1,250', array_column($csvRows, 8));
        $this->assertContains('2,000', array_column($csvRows, 9));
        $this->assertContains('1,250', array_column($csvRows, 9));

        $xlsx = $this->get(route('inventory.kardex.export', ['format' => 'xlsx']));
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $xlsxContent = $xlsx->streamedContent();
        $this->assertSame('PK', substr($xlsxContent, 0, 2));
        $temporaryFile = tempnam(sys_get_temp_dir(), 'kardex-');
        try {
            file_put_contents($temporaryFile, $xlsxContent);
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame('M', $sheet->getHighestColumn());
            $this->assertSame(3, $sheet->getHighestRow());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('I2')->getDataType());
            $this->assertSame(DataType::TYPE_NUMERIC, $sheet->getCell('J2')->getDataType());
            $exportedProducts = [$sheet->getCell('C2')->getValue(), $sheet->getCell('C3')->getValue()];
            $this->assertEqualsCanonicalizing(['PROD-01', 'PROD-02'], $exportedProducts);
            $spreadsheet->disconnectWorksheets();
        } finally {
            unlink($temporaryFile);
        }

        $pdf = $this->get(route('inventory.kardex.export', ['format' => 'pdf']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
    }

    public function test_filtered_kardex_exports_keep_the_opening_balance_before_the_date_range(): void
    {
        $order = $this->approvedPurchaseOrder('2.000', '15.0000');
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.receipts.store'), [
                'purchase_order_id' => $order->id,
                'items' => [
                    ['purchase_order_item_id' => $order->items()->firstOrFail()->id, 'quantity' => '2.000'],
                ],
            ])
            ->assertRedirect();

        $requisition = $this->approvedRequisition('0.750');
        $this->actingAs($this->warehouseOperator)
            ->post(route('inventory.issues.store'), [
                'requisition_id' => $requisition->id,
                'items' => [
                    ['requisition_item_id' => $requisition->items()->firstOrFail()->id, 'quantity' => '0.750'],
                ],
            ])
            ->assertRedirect();

        StockMovement::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->where('movement_type', StockMovement::TYPE_RECEIPT)
            ->update(['occurred_at' => Carbon::today()->subDays(2)->setTime(9, 0)]);
        StockMovement::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $this->product->id)
            ->where('movement_type', StockMovement::TYPE_ISSUE)
            ->update(['occurred_at' => Carbon::today()->subDay()->setTime(10, 0)]);

        $filters = [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'from_date' => today()->subDay()->toDateString(),
            'to_date' => today()->subDay()->toDateString(),
        ];

        $filteredPage = $this->actingAs($this->warehouseOperator)
            ->get(route('inventory.kardex', $filters));
        $filteredPage
            ->assertOk()
            ->assertSee('-0,750')
            ->assertSee('1,250')
            ->assertDontSee('+2,000')
            ->assertSee(route('inventory.kardex.export', [...$filters, 'format' => 'xlsx']))
            ->assertSee(route('inventory.kardex.export', [...$filters, 'format' => 'csv']))
            ->assertSee(route('inventory.kardex.export', [...$filters, 'format' => 'pdf']));

        $csv = $this->get(route('inventory.kardex.export', [...$filters, 'format' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $csvStream = fopen('php://temp', 'r+');
        fwrite($csvStream, substr($csv->streamedContent(), 3));
        rewind($csvStream);
        fgetcsv($csvStream);
        $csvRows = [];
        while (($row = fgetcsv($csvStream)) !== false) {
            $csvRows[] = $row;
        }
        fclose($csvStream);
        $this->assertCount(1, $csvRows);
        $this->assertSame('PROD-01', $csvRows[0][2]);
        $this->assertSame('-0,750', $csvRows[0][8]);
        $this->assertSame('1,250', $csvRows[0][9]);

        $xlsx = $this->get(route('inventory.kardex.export', [...$filters, 'format' => 'xlsx']));
        $xlsx->assertOk()
            ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $temporaryFile = tempnam(sys_get_temp_dir(), 'kardex-filtered-');
        try {
            file_put_contents($temporaryFile, $xlsx->streamedContent());
            $spreadsheet = IOFactory::load($temporaryFile);
            $sheet = $spreadsheet->getActiveSheet();
            $this->assertSame(2, $sheet->getHighestRow());
            $this->assertSame('PROD-01', $sheet->getCell('C2')->getValue());
            $this->assertSame(-0.75, $sheet->getCell('I2')->getValue());
            $this->assertSame(1.25, $sheet->getCell('J2')->getValue());
            $spreadsheet->disconnectWorksheets();
        } finally {
            unlink($temporaryFile);
        }

        $pdf = $this->get(route('inventory.kardex.export', [...$filters, 'format' => 'pdf']));
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
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

    private function approvedRequisition(
        string $quantity,
        ?Warehouse $warehouse = null,
        ?Product $product = null,
    ): Requisition {
        $this->actingAs($this->requester)
            ->post(route('purchasing.requisitions.store'), [
                'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
                'justification' => 'Reposición para operación.',
                'needed_by' => today()->addDays(5)->toDateString(),
                'items' => [
                    ['product_id' => ($product ?? $this->product)->id, 'quantity' => $quantity],
                ],
            ])->assertRedirect();
        $requisition = Requisition::query()->latest('id')->firstOrFail();
        $this->post(route('inventory.requisitions.activate', $requisition))->assertRedirect();
        $this->post(route('purchasing.requisitions.submit', $requisition))->assertRedirect();
        $this->actingAs($this->requisitionApprover)
            ->post(route('purchasing.requisitions.approve', $requisition))
            ->assertRedirect();

        return $requisition->fresh();
    }

    private function activeRequisition(string $quantity): Requisition
    {
        $this->actingAs($this->requester)
            ->post(route('inventory.requisitions.store'), [
                'warehouse_id' => $this->warehouse->id,
                'justification' => 'Salida parcial previa a compras.',
                'needed_by' => today()->addDays(5)->toDateString(),
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => $quantity],
                ],
            ])->assertRedirect();
        $requisition = Requisition::query()->latest('id')->firstOrFail();
        $this->post(route('inventory.requisitions.activate', $requisition))->assertRedirect();

        return $requisition->fresh();
    }

    private function createPurchaseOrder(Requisition $requisition, string $unitCost): PurchaseOrder
    {
        $item = $requisition->items()->firstOrFail();
        $cop = Currency::query()->where('code', 'COP')->firstOrFail();
        $this->submitQuote($requisition, $this->supplier, $cop, '1.00000000', $unitCost, 'COT-001');
        $otherSupplier = Supplier::create(['legal_name' => 'Proveedor alternativo']);
        $this->submitQuote($requisition, $otherSupplier, $cop, '1.00000000', (string) ((float) $unitCost + 1), 'COT-002');
        $this->selectQuoteOffer($requisition, $item, $this->supplier);

        return PurchaseOrder::query()->where('requisition_id', $requisition->id)->latest('id')->firstOrFail();
    }

    private function submitQuote(
        Requisition $requisition,
        Supplier $supplier,
        Currency $currency,
        string $exchangeRate,
        string $unitCost,
        string $reference,
        ?RequisitionItem $item = null,
        string $taxRate = '0',
    ): SupplierQuote {
        $item ??= $requisition->items()->firstOrFail();
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.store', $requisition), [
                'supplier_id' => $supplier->id,
                'quote_currency_id' => $currency->id,
                'exchange_rate' => $exchangeRate,
                'quote_reference' => $reference,
                'quote_date' => today()->toDateString(),
                'items' => [
                    $item->id => [
                        'requisition_item_id' => $item->id,
                        'unit_cost' => $unitCost,
                        'tax_rate' => $taxRate,
                        'brand' => 'Marca de prueba',
                        'delivery_days' => 4,
                    ],
                ],
            ])->assertRedirect(route('purchasing.quotes.register', $requisition));

        return SupplierQuote::query()
            ->where('requisition_id', $requisition->id)
            ->where('supplier_id', $supplier->id)
            ->firstOrFail();
    }

    private function selectQuoteOffer(
        Requisition $requisition,
        RequisitionItem $item,
        Supplier $supplier,
        ?string $justification = null,
    ): void {
        $offer = SupplierQuoteItem::query()
            ->where('requisition_item_id', $item->id)
            ->whereHas('quote', fn ($query) => $query->where('supplier_id', $supplier->id))
            ->firstOrFail();

        $payload = ['awards' => [$item->id => $offer->id]];
        if ($justification !== null) {
            $payload['justifications'] = [$item->id => $justification];
        }
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.select', $requisition), $payload)
            ->assertRedirect(route('purchasing.quotes.index', $requisition));
        $this->actingAs($this->orderCreator)
            ->post(route('purchasing.quotes.generate-orders', $requisition))
            ->assertRedirect(route('purchasing.requisitions.show', $requisition));
    }

    private function approvedPurchaseOrder(
        string $quantity,
        string $unitCost,
        ?Warehouse $warehouse = null,
        ?Product $product = null,
    ): PurchaseOrder {
        $requisition = $this->approvedRequisition($quantity, $warehouse, $product);
        $order = $this->createPurchaseOrder($requisition, $unitCost);
        $this->actingAs($this->orderApprover)
            ->post(route('purchasing.orders.approve', $order))
            ->assertRedirect();

        return $order->fresh();
    }
}
