<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Department;
use App\Models\Product;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuoteComparisonDemoSeeder extends Seeder
{
    public function run(): void
    {
        $references = ['REQ-DEMO-COTIZ-001', 'REQ-DEMO-COTIZ-002'];
        $existingReferences = Requisition::query()->whereIn('reference', $references)->pluck('reference');
        if ($existingReferences->count() === count($references)) {
            $this->command?->info('Quote comparison demo requisitions already exist; no data was changed.');

            return;
        }
        if ($existingReferences->isNotEmpty()) {
            throw new RuntimeException('At least one quote comparison demo requisition already exists; no data was changed.');
        }

        $company = Company::query()->where('singleton_key', 'primary')->firstOrFail();
        $department = Department::query()->where('company_id', $company->id)->where('is_active', true)->firstOrFail();
        $approver = User::query()->whereKey($department->manager_user_id)->firstOrFail();
        $requester = User::query()->where('email', 'demo.solicitante@alfiltechnology.test')->firstOrFail();
        $creator = User::query()->where('email', 'superadmin@alfiltechnology.com')->firstOrFail();
        $warehouse = Warehouse::query()
            ->where('is_active', true)
            ->whereHas('branch', fn ($query) => $query->where('company_id', $company->id)->where('is_active', true))
            ->firstOrFail();
        $baseCurrency = Currency::query()->whereKey($company->base_currency_id)->where('is_active', true)->firstOrFail();
        $usd = Currency::query()->where('code', 'USD')->where('is_active', true)->firstOrFail();

        $products = Product::query()
            ->whereIn('code', [
                'DEMO-PROD-001',
                'DEMO-PROD-002',
                'DEMO-PROD-003',
                'DEMO-PROD-006',
                'DEMO-PROD-007',
                'DEMO-PROD-008',
            ])
            ->where('is_active', true)
            ->with('unit')
            ->get()
            ->keyBy('code');
        $suppliers = Supplier::query()
            ->whereIn('legal_name', [
                'Suministros Andinos Demo S.A.S.',
                'Distribuciones Alimentarias Demo S.A.S.',
                'Papelería Central Demo S.A.S.',
                'Papelería Integral Demo S.A.S.',
                'Ferretería Industrial Demo S.A.S.',
            ])
            ->where('is_active', true)
            ->get()
            ->keyBy('legal_name');

        if ($products->count() !== 6 || $suppliers->count() !== 5) {
            throw new RuntimeException('Required active demo products or suppliers are missing; no data was changed.');
        }

        DB::transaction(function () use ($references, $company, $department, $approver, $requester, $creator, $warehouse, $baseCurrency, $usd, $products, $suppliers) {
            $scenarios = [
                [
                    'reference' => $references[0],
                    'justification' => 'Requisición demo para comparar ofertas en COP y USD, incluyendo impuestos y conversión a moneda base.',
                    'items' => [
                        ['product' => 'DEMO-PROD-001', 'quantity' => '12.000'],
                        ['product' => 'DEMO-PROD-002', 'quantity' => '8.000'],
                        ['product' => 'DEMO-PROD-003', 'quantity' => '5.000'],
                    ],
                    'quotes' => [
                        [
                            'supplier' => 'Suministros Andinos Demo S.A.S.',
                            'reference' => 'COT-DEMO-COMP-001-COP',
                            'currency' => $baseCurrency,
                            'exchange_rate' => '1.00000000',
                            'offers' => [
                                ['unit_cost' => '7900.0000', 'tax_rate' => '0.0000', 'brand' => 'Andinos', 'delivery_days' => 5],
                                ['unit_cost' => '11900.0000', 'tax_rate' => '19.0000', 'brand' => 'Andinos', 'delivery_days' => 7],
                                ['unit_cost' => '18900.0000', 'tax_rate' => '19.0000', 'brand' => 'Andinos', 'delivery_days' => 8],
                            ],
                        ],
                        [
                            'supplier' => 'Distribuciones Alimentarias Demo S.A.S.',
                            'reference' => 'COT-DEMO-COMP-001-USD',
                            'currency' => $usd,
                            'exchange_rate' => '4200.00000000',
                            'offers' => [
                                ['unit_cost' => '1.8000', 'tax_rate' => '19.0000', 'brand' => 'Distribuciones', 'delivery_days' => 4],
                                ['unit_cost' => '2.2000', 'tax_rate' => '0.0000', 'brand' => 'Distribuciones', 'delivery_days' => 6],
                                ['unit_cost' => '4.0000', 'tax_rate' => '0.0000', 'brand' => 'Distribuciones', 'delivery_days' => 10],
                            ],
                        ],
                    ],
                ],
                [
                    'reference' => $references[1],
                    'justification' => 'Requisición demo con tres proveedores para probar adjudicación parcial y selección justificada.',
                    'items' => [
                        ['product' => 'DEMO-PROD-006', 'quantity' => '10.000'],
                        ['product' => 'DEMO-PROD-007', 'quantity' => '20.000'],
                        ['product' => 'DEMO-PROD-008', 'quantity' => '8.000'],
                    ],
                    'quotes' => [
                        [
                            'supplier' => 'Papelería Central Demo S.A.S.',
                            'reference' => 'COT-DEMO-COMP-002-PC',
                            'currency' => $baseCurrency,
                            'exchange_rate' => '1.00000000',
                            'offers' => [
                                ['unit_cost' => '5000.0000', 'tax_rate' => '19.0000', 'brand' => 'Central', 'delivery_days' => 3],
                                ['unit_cost' => '32000.0000', 'tax_rate' => '0.0000', 'brand' => 'Central', 'delivery_days' => 5],
                                ['unit_cost' => '10500.0000', 'tax_rate' => '19.0000', 'brand' => 'Central', 'delivery_days' => 4],
                            ],
                        ],
                        [
                            'supplier' => 'Papelería Integral Demo S.A.S.',
                            'reference' => 'COT-DEMO-COMP-002-PI',
                            'currency' => $baseCurrency,
                            'exchange_rate' => '1.00000000',
                            'offers' => [
                                ['unit_cost' => '4800.0000', 'tax_rate' => '19.0000', 'brand' => 'Integral', 'delivery_days' => 6],
                                ['unit_cost' => '35000.0000', 'tax_rate' => '0.0000', 'brand' => 'Integral', 'delivery_days' => 7],
                                ['unit_cost' => '9900.0000', 'tax_rate' => '19.0000', 'brand' => 'Integral', 'delivery_days' => 5],
                            ],
                        ],
                        [
                            'supplier' => 'Ferretería Industrial Demo S.A.S.',
                            'reference' => 'COT-DEMO-COMP-002-FI',
                            'currency' => $baseCurrency,
                            'exchange_rate' => '1.00000000',
                            'offers' => [
                                ['unit_cost' => '5600.0000', 'tax_rate' => '19.0000', 'brand' => 'Industrial', 'delivery_days' => 4],
                                ['unit_cost' => '28500.0000', 'tax_rate' => '0.0000', 'brand' => 'Industrial', 'delivery_days' => 2],
                                ['unit_cost' => '9500.0000', 'tax_rate' => '19.0000', 'brand' => 'Industrial', 'delivery_days' => 6],
                            ],
                        ],
                    ],
                ],
            ];

            foreach ($scenarios as $scenario) {
                $requisition = Requisition::create([
                    'reference' => $scenario['reference'],
                    'department_id' => $department->id,
                    'warehouse_id' => $warehouse->id,
                    'requester_user_id' => $requester->id,
                    'approver_user_id' => $approver->id,
                    'status' => Requisition::STATUS_APPROVED,
                    'justification' => $scenario['justification'],
                    'needed_by' => today()->addDays(14)->toDateString(),
                    'submitted_at' => now()->subDays(2),
                    'decided_at' => now()->subDay(),
                ]);

                $items = collect($scenario['items'])->map(function (array $item) use ($requisition, $products) {
                    $product = $products->get($item['product']);

                    return RequisitionItem::create([
                        'requisition_id' => $requisition->id,
                        'product_id' => $product->id,
                        'unit_id' => $product->unit_id,
                        'product_code' => $product->code,
                        'product_name' => $product->name,
                        'unit_abbreviation' => $product->unit->abbreviation,
                        'quantity' => $item['quantity'],
                    ]);
                });

                foreach ($scenario['quotes'] as $quoteData) {
                    $supplier = $suppliers->get($quoteData['supplier']);
                    $quote = SupplierQuote::create([
                        'requisition_id' => $requisition->id,
                        'supplier_id' => $supplier->id,
                        'quote_reference' => $quoteData['reference'],
                        'quote_date' => today()->subDay()->toDateString(),
                        'quote_currency_id' => $quoteData['currency']->id,
                        'base_currency_id' => $baseCurrency->id,
                        'exchange_rate' => $quoteData['exchange_rate'],
                        'created_by_user_id' => $creator->id,
                    ]);

                    foreach ($items->values() as $index => $item) {
                        $quote->items()->create([
                            'requisition_item_id' => $item->id,
                            ...$quoteData['offers'][$index],
                        ]);
                    }
                }

                AuditLog::create([
                    'user_id' => $creator->id,
                    'company_id' => $company->id,
                    'action' => 'created',
                    'entity_type' => 'requisition',
                    'entity_id' => $requisition->id,
                    'changes' => [
                        'reference' => $requisition->reference,
                        'status' => $requisition->status,
                        'demo_data' => true,
                    ],
                ]);
            }
        });

        $this->command?->info('Created 2 demo requisitions with 5 supplier quotes and 15 offers for quote comparison.');
    }
}
