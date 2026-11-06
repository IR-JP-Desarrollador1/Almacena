<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\InventoryIssue;
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

class TransferBetweenWarehousesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_transfer_stock_between_authorized_warehouses(): void
    {
        $this->seed(AccessControlSeeder::class);

        $company = Company::create([
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
            'company_id' => $company->id,
            'code' => 'HQ',
            'name' => 'Sede principal',
        ]);
        $origin = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'MAIN',
            'name' => 'Bodega principal',
        ]);
        $destination = Warehouse::create([
            'branch_id' => $branch->id,
            'code' => 'SEC',
            'name' => 'Bodega secundaria',
        ]);

        $unit = Unit::create([
            'code' => 'UND',
            'name' => 'Unidad',
            'abbreviation' => 'und',
        ]);
        $product = Product::create([
            'code' => 'PROD-01',
            'name' => 'Producto de prueba',
            'unit_id' => $unit->id,
        ]);

        $warehouseOperator = User::factory()->create();
        $warehouseOperator->roles()->attach(Role::query()->where('slug', 'almacen')->firstOrFail());
        $warehouseOperator->warehouses()->attach([$origin->id, $destination->id]);

        $originLot = WarehouseLot::create([
            'warehouse_id' => $origin->id,
            'product_id' => $product->id,
            'receipt_item_id' => null,
            'lot_code' => 'LOT-ORIG',
            'quantity_received' => '10.000',
            'unit_cost' => '1000.0000',
            'unit_cost_base' => '1000.0000',
            'quote_currency_id' => $company->base_currency_id,
            'base_currency_id' => $company->base_currency_id,
            'exchange_rate' => '1.00000000',
            'received_at' => now(),
        ]);
        StockMovement::create([
            'warehouse_lot_id' => $originLot->id,
            'warehouse_id' => $origin->id,
            'product_id' => $product->id,
            'user_id' => $warehouseOperator->id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'reference_type' => 'direct_receipt',
            'reference_id' => 1,
            'quantity' => '10.000',
            'unit_cost_base' => '1000.0000',
            'base_currency_id' => $company->base_currency_id,
            'occurred_at' => now(),
        ]);

        $this->actingAs($warehouseOperator)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('Traslados entre bodegas'))
            ->assertSee('inventory/transfers/create');

        $this->get(route('inventory.transfers.create'))
            ->assertOk()
            ->assertSee(__('Registrar traslado'))
            ->assertSee(__('Productos del traslado'))
            ->assertSee(__('Escribe el código o nombre del producto'))
            ->assertSee('data-product-search-widget', false)
            ->assertSee('name="items[0][product_id]"', false)
            ->assertSee(__('Confirmar traslado'));

        $this->actingAs($warehouseOperator)
            ->post(route('inventory.transfers.store'), [
                'origin_warehouse_id' => $origin->id,
                'destination_warehouse_id' => $destination->id,
                'items' => [[
                    'product_id' => $product->id,
                    'quantity' => '2.500',
                ]],
            ])
            ->assertRedirect(route('inventory.issues.index'));

        $this->assertDatabaseHas('inventory_issues', [
            'warehouse_id' => $origin->id,
            'destination_warehouse_id' => $destination->id,
            'source_type' => InventoryIssue::SOURCE_TRANSFER,
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $origin->id,
            'product_id' => $product->id,
            'movement_type' => StockMovement::TYPE_ISSUE,
            'reference_type' => 'inventory_transfer',
        ]);
        $this->assertDatabaseHas('stock_movements', [
            'warehouse_id' => $destination->id,
            'product_id' => $product->id,
            'movement_type' => StockMovement::TYPE_RECEIPT,
            'reference_type' => 'inventory_transfer',
        ]);
    }
}
