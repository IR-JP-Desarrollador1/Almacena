<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\Product;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLot;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryCountTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Warehouse $warehouse;

    private User $operator;

    private User $approver;

    private Unit $unit;

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
        $this->unit = Unit::create([
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
        ]);
        $this->operator = User::factory()->create();
        $this->operator->roles()->attach(Role::query()->where('slug', 'almacen')->firstOrFail());
        $this->operator->warehouses()->attach($this->warehouse->id);
        $this->approver = User::factory()->create();
        $this->approver->roles()->attach(Role::query()->where('slug', 'administracion')->firstOrFail());
    }

    public function test_count_approval_posts_surplus_at_weighted_average_and_shortage_from_stock_lots(): void
    {
        $surplusProduct = $this->product('PROD-SURPLUS');
        $this->receive($surplusProduct, '4.000', '2.0000');
        $this->receive($surplusProduct, '6.000', '4.0000');
        $shortageProduct = $this->product('PROD-SHORT');
        $shortageLot = $this->receive($shortageProduct, '6.000', '7.0000');

        $count = $this->createCount([
            ['product_id' => $surplusProduct->id, 'counted_quantity' => '12.000'],
            ['product_id' => $shortageProduct->id, 'counted_quantity' => '2.000'],
        ]);
        $this->actingAs($this->operator)
            ->post(route('inventory.counts.submit', $count))
            ->assertRedirect(route('inventory.counts.show', $count));

        $this->assertDatabaseHas('inventory_count_items', [
            'inventory_count_id' => $count->id,
            'product_id' => $surplusProduct->id,
            'system_quantity' => '10.000',
            'difference_quantity' => '2.000',
        ]);
        $this->assertDatabaseHas('inventory_count_items', [
            'inventory_count_id' => $count->id,
            'product_id' => $shortageProduct->id,
            'system_quantity' => '6.000',
            'difference_quantity' => '-4.000',
        ]);

        $this->actingAs($this->approver)
            ->post(route('inventory.counts.approve', $count))
            ->assertRedirect(route('inventory.counts.show', $count));

        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $surplusProduct->id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'reference_type' => 'inventory_count',
            'reference_id' => $count->id,
            'quantity' => '2.000',
            'unit_cost_base' => '3.2000',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_lot_id' => $shortageLot->id,
            'product_id' => $shortageProduct->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'reference_type' => 'inventory_count',
            'reference_id' => $count->id,
            'quantity' => '4.000',
            'unit_cost_base' => '7.0000',
        ]);
        $this->assertSame(InventoryCount::STATUS_APPROVED, $count->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'approved',
            'entity_type' => 'inventory_count',
            'entity_id' => $count->id,
        ]);
    }

    public function test_surplus_without_stock_or_history_requires_manual_cost_and_expiration_when_tracked(): void
    {
        $product = $this->product('PROD-EXP', true);
        $count = $this->createCount([
            ['product_id' => $product->id, 'counted_quantity' => '2.000'],
        ]);
        $this->actingAs($this->operator)->post(route('inventory.counts.submit', $count))->assertRedirect();
        $item = InventoryCountItem::query()->where('inventory_count_id', $count->id)->firstOrFail();

        $this->actingAs($this->approver)
            ->post(route('inventory.counts.approve', $count))
            ->assertSessionHasErrors("items.{$item->id}.surplus_unit_cost");
        $this->actingAs($this->approver)
            ->post(route('inventory.counts.approve', $count), [
                'items' => [
                    $item->id => ['surplus_unit_cost' => '2.5000'],
                ],
            ])
            ->assertSessionHasErrors("items.{$item->id}.expires_on");

        $this->actingAs($this->approver)
            ->post(route('inventory.counts.approve', $count), [
                'items' => [
                    $item->id => [
                        'surplus_unit_cost' => '2.5000',
                        'expires_on' => today()->addDays(30)->toDateString(),
                    ],
                ],
            ])
            ->assertRedirect(route('inventory.counts.show', $count));

        $lot = WarehouseLot::query()
            ->where('warehouse_id', $this->warehouse->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $this->assertEquals(2.5, (float) $lot->unit_cost_base);
        $this->assertSame(today()->addDays(30)->toDateString(), $lot->expires_on->toDateString());
    }

    public function test_approval_is_blocked_when_stock_changes_after_count_submission(): void
    {
        $product = $this->product('PROD-CHANGED');
        $this->receive($product, '10.000', '5.0000');
        $count = $this->createCount([
            ['product_id' => $product->id, 'counted_quantity' => '8.000'],
        ]);
        $this->actingAs($this->operator)->post(route('inventory.counts.submit', $count))->assertRedirect();
        $lot = $this->receive($product, '1.000', '5.0000');
        StockMovement::create([
            'warehouse_lot_id' => $lot->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'user_id' => $this->operator->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'reference_type' => 'issue',
            'reference_id' => 1,
            'quantity' => '1.000',
            'unit_cost_base' => '5.0000',
            'base_currency_id' => $this->company->base_currency_id,
            'occurred_at' => now()->addSecond(),
        ]);

        $this->actingAs($this->approver)
            ->post(route('inventory.counts.approve', $count))
            ->assertSessionHasErrors('approval');
        $this->assertSame(InventoryCount::STATUS_PENDING_APPROVAL, $count->fresh()->status);
        $this->assertDatabaseMissing('stock_movements', [
            'reference_type' => 'inventory_count',
            'reference_id' => $count->id,
        ]);
    }

    public function test_count_creator_cannot_approve_own_count(): void
    {
        $product = $this->product('PROD-OWNER');
        $this->receive($product, '2.000', '1.0000');
        $count = $this->createCount([
            ['product_id' => $product->id, 'counted_quantity' => '1.000'],
        ]);
        $this->actingAs($this->operator)->post(route('inventory.counts.submit', $count))->assertRedirect();

        $this->actingAs($this->operator)
            ->post(route('inventory.counts.approve', $count))
            ->assertForbidden();
    }

    private function product(string $code, bool $tracksExpiration = false): Product
    {
        return Product::create([
            'code' => $code,
            'name' => $code,
            'unit_id' => $this->unit->id,
            'tracks_expiration' => $tracksExpiration,
        ]);
    }

    private function receive(Product $product, string $quantity, string $unitCost): WarehouseLot
    {
        $lot = WarehouseLot::create([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'receipt_item_id' => null,
            'lot_code' => 'LOT-'.str_replace('-', '', $product->code).'-'.WarehouseLot::query()->count(),
            'quantity_received' => $quantity,
            'unit_cost' => $unitCost,
            'unit_cost_base' => $unitCost,
            'quote_currency_id' => $this->company->base_currency_id,
            'base_currency_id' => $this->company->base_currency_id,
            'exchange_rate' => '1.00000000',
            'received_at' => now()->addSeconds(WarehouseLot::query()->count()),
        ]);
        StockMovement::create([
            'warehouse_lot_id' => $lot->id,
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'user_id' => $this->operator->id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'reference_type' => 'receipt',
            'reference_id' => 1,
            'quantity' => $quantity,
            'unit_cost_base' => $unitCost,
            'base_currency_id' => $this->company->base_currency_id,
            'occurred_at' => now(),
        ]);

        return $lot;
    }

    private function createCount(array $items): InventoryCount
    {
        $this->actingAs($this->operator)
            ->post(route('inventory.counts.store'), [
                'warehouse_id' => $this->warehouse->id,
                'items' => $items,
            ])
            ->assertRedirect();

        return InventoryCount::query()->latest('id')->firstOrFail();
    }
}
