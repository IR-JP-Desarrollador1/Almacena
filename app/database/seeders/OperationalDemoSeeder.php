<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Department;
use App\Models\InventoryCount;
use App\Models\InventoryCountItem;
use App\Models\InventoryIssue;
use App\Models\InventoryIssueAllocation;
use App\Models\InventoryIssueItem;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Receipt;
use App\Models\ReceiptItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\RequisitionItemAward;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLot;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class OperationalDemoSeeder extends Seeder
{
    private const REQUISITION_REFERENCES = [
        'REQ-DEMO-OPS-001',
        'REQ-DEMO-OPS-002',
    ];

    private const ORDER_REFERENCES = [
        'OC-DEMO-OPS-001',
        'OC-DEMO-OPS-002',
    ];

    private const RECEIPT_REFERENCES = [
        'ENT-DEMO-OPS-001',
        'ENT-DEMO-OPS-002',
        'ENT-DEMO-OPS-003',
    ];

    private const ISSUE_REFERENCES = [
        'SAL-DEMO-OPS-001',
        'SAL-DEMO-OPS-002',
        'TRA-DEMO-OPS-001',
    ];

    private const COUNT_REFERENCE = 'CON-DEMO-OPS-001';

    public function run(): void
    {
        DB::transaction(function (): void {
            $existingCount = Requisition::query()->whereIn('reference', self::REQUISITION_REFERENCES)->count()
                + PurchaseOrder::query()->whereIn('reference', self::ORDER_REFERENCES)->count()
                + Receipt::query()->whereIn('reference', self::RECEIPT_REFERENCES)->count()
                + InventoryIssue::query()->whereIn('reference', self::ISSUE_REFERENCES)->count()
                + InventoryCount::query()->where('reference', self::COUNT_REFERENCE)->count();
            $expectedCount = count(self::REQUISITION_REFERENCES)
                + count(self::ORDER_REFERENCES)
                + count(self::RECEIPT_REFERENCES)
                + count(self::ISSUE_REFERENCES)
                + 1;

            if ($existingCount === $expectedCount) {
                $this->command?->info('Operational demo transactions already exist; no data was changed.');

                return;
            }

            if ($existingCount > 0) {
                throw new RuntimeException('Some operational demo transactions already exist; no data was changed.');
            }

            $company = Company::query()->where('singleton_key', 'primary')->firstOrFail();
            $baseCurrency = Currency::query()->whereKey($company->base_currency_id)->where('is_active', true)->firstOrFail();
            $approver = User::query()->where('email', 'superadmin@alfiltechnology.com')->firstOrFail();
            $requester = User::query()->where('email', 'demo.solicitante@alfiltechnology.test')->firstOrFail();
            $buyer = User::query()->where('email', 'demo.compras@alfiltechnology.test')->firstOrFail();

            $branch = Branch::query()
                ->where('company_id', $company->id)
                ->where('is_active', true)
                ->first();
            if (! $branch) {
                $branch = Branch::query()->create([
                    'company_id' => $company->id,
                    'code' => 'DEMO-OPS-SEDE',
                    'name' => 'Sede operativa de demostración',
                    'is_active' => true,
                ]);
            }

            $warehouse = Warehouse::query()
                ->where('branch_id', $branch->id)
                ->where('is_active', true)
                ->first();
            if (! $warehouse) {
                $warehouse = Warehouse::query()->create([
                    'branch_id' => $branch->id,
                    'code' => 'DEMO-OPS-BOD-01',
                    'name' => 'Bodega operativa de demostración',
                    'is_active' => true,
                ]);
            }

            $destinationWarehouse = Warehouse::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'code' => 'DEMO-OPS-BOD-02'],
                ['name' => 'Bodega destino de demostración', 'is_active' => true],
            );
            if (! $destinationWarehouse->is_active) {
                $destinationWarehouse->update(['is_active' => true]);
            }

            $department = Department::query()->firstOrCreate(
                ['company_id' => $company->id, 'code' => 'DEMO-OPS'],
                [
                    'name' => 'Operaciones de demostración',
                    'manager_user_id' => $approver->id,
                    'is_active' => true,
                ],
            );
            if (! $department->is_active) {
                $department->update(['is_active' => true, 'manager_user_id' => $approver->id]);
            }

            $products = Product::query()
                ->whereIn('code', ['DEMO-PROD-001', 'DEMO-PROD-003', 'DEMO-PROD-006', 'DEMO-PROD-010'])
                ->where('is_active', true)
                ->with('unit')
                ->get()
                ->keyBy('code');
            if ($products->count() !== 4) {
                throw new RuntimeException('Required active demo products are missing; no transactions were created.');
            }

            $suppliers = collect([
                'Suministros Andinos Demo S.A.S.',
                'Empaques del Centro Demo S.A.S.',
                'Ferretería Industrial Demo S.A.S.',
            ])->mapWithKeys(function (string $legalName): array {
                $supplier = Supplier::query()->where('legal_name', $legalName)->first();
                if (! $supplier) {
                    $supplier = Supplier::query()->create([
                        'legal_name' => $legalName,
                        'is_active' => true,
                    ]);
                } elseif (! $supplier->is_active) {
                    $supplier->update(['is_active' => true]);
                }

                return [$legalName => $supplier];
            });

            $rice = $products->get('DEMO-PROD-001');
            $oil = $products->get('DEMO-PROD-003');
            $paper = $products->get('DEMO-PROD-006');
            $screws = $products->get('DEMO-PROD-010');
            $requisition = $this->createRequisition(
                self::REQUISITION_REFERENCES[0],
                $department,
                $warehouse,
                $requester,
                $approver,
                Requisition::STATUS_APPROVED,
                'Compra de productos para abastecer la operación de demostración.',
                [
                    ['product' => $rice, 'quantity' => '10.000'],
                    ['product' => $paper, 'quantity' => '10.000'],
                ],
            );
            $dispatchRequisition = $this->createRequisition(
                self::REQUISITION_REFERENCES[1],
                $department,
                $warehouse,
                $requester,
                $approver,
                Requisition::STATUS_ACTIVE,
                'Entrega parcial de suministros para el equipo de operaciones.',
                [['product' => $paper, 'quantity' => '3.000']],
            );

            $quoteItems = [];
            foreach ([
                [
                    'supplier' => $suppliers->get('Suministros Andinos Demo S.A.S.'),
                    'reference' => 'COT-DEMO-OPS-001-A',
                    'offers' => [
                        ['unit_cost' => '7900.0000', 'tax_rate' => '19.0000', 'brand' => 'Andinos', 'delivery_days' => 4],
                        ['unit_cost' => '2100.0000', 'tax_rate' => '19.0000', 'brand' => 'Andinos', 'delivery_days' => 4],
                    ],
                ],
                [
                    'supplier' => $suppliers->get('Empaques del Centro Demo S.A.S.'),
                    'reference' => 'COT-DEMO-OPS-001-B',
                    'offers' => [
                        ['unit_cost' => '8250.0000', 'tax_rate' => '19.0000', 'brand' => 'Empaques', 'delivery_days' => 6],
                        ['unit_cost' => '2300.0000', 'tax_rate' => '19.0000', 'brand' => 'Empaques', 'delivery_days' => 5],
                    ],
                ],
            ] as $quoteData) {
                $quoteItems[] = $this->createQuote($requisition, $quoteData, $baseCurrency, $buyer);
            }

            $awardedQuoteItems = [];
            foreach ($requisition->items as $item) {
                $quoteItem = $quoteItems[0][$item->product_id];
                RequisitionItemAward::query()->create([
                    'requisition_item_id' => $item->id,
                    'supplier_quote_item_id' => $quoteItem->id,
                    'selected_by_user_id' => $buyer->id,
                    'justification' => null,
                    'selected_at' => now()->subDays(2),
                ]);
                $awardedQuoteItems[$item->id] = $quoteItem;
            }

            $purchaseOrder = $this->createPurchaseOrder(
                self::ORDER_REFERENCES[0],
                $requisition,
                $warehouse,
                $suppliers->get('Suministros Andinos Demo S.A.S.'),
                $baseCurrency,
                $buyer,
                $approver,
                'COT-DEMO-OPS-001-A',
                $requisition->items->map(fn (RequisitionItem $item): array => [
                    'requisition_item' => $item,
                    'quote_item' => $awardedQuoteItems[$item->id],
                    'quantity' => $item->quantity,
                    'unit_cost' => $awardedQuoteItems[$item->id]->unit_cost,
                    'tax_rate' => $awardedQuoteItems[$item->id]->tax_rate,
                    'brand' => $awardedQuoteItems[$item->id]->brand,
                    'delivery_days' => $awardedQuoteItems[$item->id]->delivery_days,
                ])->all(),
            );
            $directOrder = $this->createPurchaseOrder(
                self::ORDER_REFERENCES[1],
                null,
                $warehouse,
                $suppliers->get('Ferretería Industrial Demo S.A.S.'),
                $baseCurrency,
                $buyer,
                $approver,
                null,
                [
                    ['product' => $screws, 'quantity' => '6.000', 'unit_cost' => '3000.0000', 'tax_rate' => '19.0000'],
                    ['product' => $oil, 'quantity' => '4.000', 'unit_cost' => '9000.0000', 'tax_rate' => '19.0000'],
                ],
            );

            $orderReceipt = $this->createReceipt(
                self::RECEIPT_REFERENCES[0],
                $warehouse,
                $approver,
                $baseCurrency,
                $purchaseOrder,
                null,
                $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'product' => $products->get($item->product_code),
                    'order_item' => $item,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'unit_cost_base' => $item->unit_cost_base,
                    'expires_on' => $item->product_code === $rice->code ? today()->addMonths(8)->toDateString() : null,
                ])->all(),
            );
            $directOrderReceipt = $this->createReceipt(
                self::RECEIPT_REFERENCES[1],
                $warehouse,
                $approver,
                $baseCurrency,
                $directOrder,
                null,
                $directOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'product' => $products->get($item->product_code),
                    'order_item' => $item,
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'unit_cost_base' => $item->unit_cost_base,
                    'expires_on' => $item->product_code === $oil->code ? today()->addMonths(12)->toDateString() : null,
                ])->all(),
            );
            $directReceipt = $this->createReceipt(
                self::RECEIPT_REFERENCES[2],
                $warehouse,
                $approver,
                $baseCurrency,
                null,
                'donation',
                [
                    [
                        'product' => $rice,
                        'quantity' => '2.000',
                        'unit_cost' => '8100.0000',
                        'unit_cost_base' => '8100.0000',
                        'expires_on' => today()->addMonths(9)->toDateString(),
                    ],
                    [
                        'product' => $screws,
                        'quantity' => '2.000',
                        'unit_cost' => '2800.0000',
                        'unit_cost_base' => '2800.0000',
                        'expires_on' => null,
                    ],
                ],
            );

            $paperOrderLot = WarehouseLot::query()
                ->where('receipt_item_id', $orderReceipt->items()->where('product_id', $paper->id)->value('id'))
                ->firstOrFail();
            $this->createIssue(
                self::ISSUE_REFERENCES[0],
                InventoryIssue::SOURCE_REQUISITION,
                $warehouse,
                $approver,
                [['product' => $paper, 'quantity' => '3.000', 'requisition_item' => $dispatchRequisition->items->first(), 'allocations' => [['lot' => $paperOrderLot, 'quantity' => '3.000']]]],
                $dispatchRequisition,
                null,
                null,
            );
            $this->createIssue(
                self::ISSUE_REFERENCES[1],
                InventoryIssue::SOURCE_DIRECT,
                $warehouse,
                $approver,
                [['product' => $paper, 'quantity' => '1.000', 'allocations' => [['lot' => $paperOrderLot, 'quantity' => '1.000']]]],
                null,
                'internal_consumption',
                null,
            );
            $this->createIssue(
                self::ISSUE_REFERENCES[2],
                InventoryIssue::SOURCE_TRANSFER,
                $warehouse,
                $approver,
                [['product' => $paper, 'quantity' => '2.000', 'allocations' => [['lot' => $paperOrderLot, 'quantity' => '2.000']]]],
                null,
                null,
                $destinationWarehouse,
            );

            $this->createApprovedCount(
                self::COUNT_REFERENCE,
                $warehouse,
                $buyer,
                $approver,
                [
                    ['product' => $rice, 'counted_quantity' => '13.000', 'surplus_unit_cost' => '7933.3333', 'expires_on' => today()->addMonths(9)->toDateString()],
                    ['product' => $paper, 'counted_quantity' => '3.000'],
                ],
            );

            $this->audit($approver, $company, 'created_and_approved', 'purchase_order', $purchaseOrder->id, [
                'reference' => $purchaseOrder->reference,
                'source' => 'requisition',
            ]);
            $this->audit($approver, $company, 'created_and_approved', 'purchase_order', $directOrder->id, [
                'reference' => $directOrder->reference,
                'source' => 'direct',
            ]);
            $this->audit($approver, $company, 'received', 'receipt', $orderReceipt->id, ['reference' => $orderReceipt->reference]);
            $this->audit($approver, $company, 'received', 'receipt', $directOrderReceipt->id, ['reference' => $directOrderReceipt->reference]);
            $this->audit($approver, $company, 'received', 'receipt', $directReceipt->id, ['reference' => $directReceipt->reference, 'source' => 'direct']);

            $this->command?->info('Created operational demo data: 2 requisitions, 2 quotes, 2 orders, 3 receipts, 3 issues, 1 transfer, and 1 approved count with surplus and shortage adjustments.');
        });
    }

    private function createRequisition(
        string $reference,
        Department $department,
        Warehouse $warehouse,
        User $requester,
        User $approver,
        string $status,
        string $justification,
        array $lines,
    ): Requisition {
        $requisition = Requisition::query()->create([
            'reference' => $reference,
            'department_id' => $department->id,
            'warehouse_id' => $warehouse->id,
            'requester_user_id' => $requester->id,
            'approver_user_id' => $approver->id,
            'status' => $status,
            'justification' => $justification,
            'needed_by' => today()->addDays(14)->toDateString(),
            'submitted_at' => $status === Requisition::STATUS_APPROVED ? now()->subDays(3) : null,
            'decided_at' => $status === Requisition::STATUS_APPROVED ? now()->subDays(2) : null,
        ]);

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];
            RequisitionItem::query()->create([
                'requisition_id' => $requisition->id,
                'product_id' => $product->id,
                'unit_id' => $product->unit_id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_abbreviation' => $product->unit->abbreviation,
                'quantity' => $line['quantity'],
            ]);
        }

        $company = Company::query()->where('singleton_key', 'primary')->firstOrFail();
        $this->audit($requester, $company, 'created', 'requisition', $requisition->id, [
            'reference' => $requisition->reference,
            'status' => $requisition->status,
        ]);
        if ($status === Requisition::STATUS_APPROVED) {
            $this->audit($approver, $company, 'approved', 'requisition', $requisition->id, [
                'reference' => $requisition->reference,
                'status' => $requisition->status,
            ]);
        }

        return $requisition->load('items');
    }

    private function createQuote(
        Requisition $requisition,
        array $quoteData,
        Currency $baseCurrency,
        User $buyer,
    ): array {
        $quote = SupplierQuote::query()->create([
            'requisition_id' => $requisition->id,
            'supplier_id' => $quoteData['supplier']->id,
            'quote_reference' => $quoteData['reference'],
            'quote_date' => today()->subDays(2)->toDateString(),
            'quote_currency_id' => $baseCurrency->id,
            'base_currency_id' => $baseCurrency->id,
            'exchange_rate' => '1.00000000',
            'created_by_user_id' => $buyer->id,
        ]);
        $quoteItems = [];

        foreach ($requisition->items->values() as $index => $item) {
            $quoteItems[$item->product_id] = SupplierQuoteItem::query()->create([
                'supplier_quote_id' => $quote->id,
                'requisition_item_id' => $item->id,
                ...$quoteData['offers'][$index],
            ]);
        }

        $company = Company::query()->where('singleton_key', 'primary')->firstOrFail();
        $this->audit($buyer, $company, 'created', 'supplier_quote', $quote->id, [
            'requisition_id' => $requisition->id,
            'quote_reference' => $quote->quote_reference,
            'supplier_id' => $quote->supplier_id,
        ]);

        return $quoteItems;
    }

    private function createPurchaseOrder(
        string $reference,
        ?Requisition $requisition,
        Warehouse $warehouse,
        Supplier $supplier,
        Currency $baseCurrency,
        User $buyer,
        User $approver,
        ?string $quoteReference,
        array $lines,
    ): PurchaseOrder {
        $order = PurchaseOrder::query()->create([
            'reference' => $reference,
            'requisition_id' => $requisition?->id,
            'warehouse_id' => $warehouse->id,
            'supplier_quote_id' => $requisition ? $lines[0]['quote_item']->supplier_quote_id : null,
            'supplier_id' => $supplier->id,
            'quote_currency_id' => $baseCurrency->id,
            'base_currency_id' => $baseCurrency->id,
            'exchange_rate' => '1.00000000',
            'created_by_user_id' => $buyer->id,
            'approved_by_user_id' => $approver->id,
            'quote_reference' => $quoteReference,
            'quote_date' => $quoteReference ? today()->subDays(2)->toDateString() : null,
            'status' => PurchaseOrder::STATUS_APPROVED,
            'approved_at' => now()->subDay(),
        ]);

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'] ?? $line['requisition_item']->product;
            $unitCost = $line['unit_cost'];
            $order->items()->create([
                'requisition_item_id' => $line['requisition_item']->id ?? null,
                'supplier_quote_item_id' => $line['quote_item']->id ?? null,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_abbreviation' => $product->unit->abbreviation,
                'quantity' => $line['quantity'],
                'unit_cost' => $unitCost,
                'unit_cost_base' => $unitCost,
                'tax_rate' => $line['tax_rate'] ?? '0.0000',
                'brand' => $line['brand'] ?? 'Marca demo',
                'delivery_days' => $line['delivery_days'] ?? 5,
            ]);
        }

        return $order->load('items');
    }

    private function createReceipt(
        string $reference,
        Warehouse $warehouse,
        User $receiver,
        Currency $baseCurrency,
        ?PurchaseOrder $order,
        ?string $directReason,
        array $lines,
    ): Receipt {
        $receivedAt = now()->subDay();
        $receipt = Receipt::query()->create([
            'reference' => $reference,
            'source_type' => $order ? 'purchase_order' : 'direct',
            'direct_reason' => $directReason,
            'purchase_order_id' => $order?->id,
            'warehouse_id' => $warehouse->id,
            'received_by_user_id' => $receiver->id,
            'document_reference' => 'DOC-'.$reference,
            'received_at' => $receivedAt,
        ]);

        foreach ($lines as $index => $line) {
            /** @var Product $product */
            $product = $line['product'];
            $receiptItem = ReceiptItem::query()->create([
                'receipt_id' => $receipt->id,
                'purchase_order_item_id' => $line['order_item']->id ?? null,
                'product_id' => $product->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'unit_cost_base' => $line['unit_cost_base'],
                'expires_on' => $line['expires_on'],
            ]);
            $lot = WarehouseLot::query()->create([
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'receipt_item_id' => $receiptItem->id,
                'lot_code' => $reference.'-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'quantity_received' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
                'unit_cost_base' => $line['unit_cost_base'],
                'quote_currency_id' => $baseCurrency->id,
                'base_currency_id' => $baseCurrency->id,
                'exchange_rate' => '1.00000000',
                'expires_on' => $line['expires_on'],
                'received_at' => $receivedAt,
            ]);
            StockMovement::query()->create([
                'warehouse_lot_id' => $lot->id,
                'warehouse_id' => $warehouse->id,
                'product_id' => $product->id,
                'user_id' => $receiver->id,
                'movement_type' => StockMovement::TYPE_RECEIPT,
                'reference_type' => 'receipt',
                'reference_id' => $receipt->id,
                'quantity' => $line['quantity'],
                'unit_cost_base' => $line['unit_cost_base'],
                'base_currency_id' => $baseCurrency->id,
                'occurred_at' => $receivedAt,
            ]);
        }

        return $receipt->load('items');
    }

    private function createIssue(
        string $reference,
        string $sourceType,
        Warehouse $warehouse,
        User $issuer,
        array $lines,
        ?Requisition $requisition,
        ?string $directReason,
        ?Warehouse $destinationWarehouse,
    ): InventoryIssue {
        $issuedAt = now();
        $issue = InventoryIssue::query()->create([
            'reference' => $reference,
            'source_type' => $sourceType,
            'direct_reason' => $directReason,
            'requisition_id' => $requisition?->id,
            'warehouse_id' => $warehouse->id,
            'destination_warehouse_id' => $destinationWarehouse?->id,
            'issued_by_user_id' => $issuer->id,
            'document_reference' => 'DOC-'.$reference,
            'issued_at' => $issuedAt,
        ]);

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];
            $issueItem = InventoryIssueItem::query()->create([
                'inventory_issue_id' => $issue->id,
                'requisition_item_id' => $line['requisition_item']->id ?? null,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_abbreviation' => $product->unit->abbreviation,
                'quantity' => $line['quantity'],
            ]);

            foreach ($line['allocations'] as $allocation) {
                /** @var WarehouseLot $lot */
                $lot = $allocation['lot'];
                InventoryIssueAllocation::query()->create([
                    'inventory_issue_item_id' => $issueItem->id,
                    'warehouse_lot_id' => $lot->id,
                    'quantity' => $allocation['quantity'],
                    'unit_cost' => $lot->unit_cost,
                    'unit_cost_base' => $lot->unit_cost_base,
                ]);
                StockMovement::query()->create([
                    'warehouse_lot_id' => $lot->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $product->id,
                    'user_id' => $issuer->id,
                    'movement_type' => StockMovement::TYPE_ISSUE,
                    'reference_type' => $sourceType === InventoryIssue::SOURCE_TRANSFER ? 'inventory_transfer' : 'inventory_issue',
                    'reference_id' => $issue->id,
                    'quantity' => $allocation['quantity'],
                    'unit_cost_base' => $lot->unit_cost_base,
                    'base_currency_id' => $lot->base_currency_id,
                    'occurred_at' => $issuedAt,
                ]);
            }

            if ($destinationWarehouse) {
                $lot = $line['allocations'][0]['lot'];
                $destinationLot = WarehouseLot::query()->create([
                    'warehouse_id' => $destinationWarehouse->id,
                    'product_id' => $product->id,
                    'receipt_item_id' => null,
                    'lot_code' => $reference.'-'.str_pad((string) $issueItem->id, 4, '0', STR_PAD_LEFT),
                    'quantity_received' => $line['quantity'],
                    'unit_cost' => $lot->unit_cost,
                    'unit_cost_base' => $lot->unit_cost_base,
                    'quote_currency_id' => $lot->quote_currency_id,
                    'base_currency_id' => $lot->base_currency_id,
                    'exchange_rate' => $lot->exchange_rate,
                    'expires_on' => $lot->expires_on,
                    'received_at' => $issuedAt,
                ]);
                StockMovement::query()->create([
                    'warehouse_lot_id' => $destinationLot->id,
                    'warehouse_id' => $destinationWarehouse->id,
                    'product_id' => $product->id,
                    'user_id' => $issuer->id,
                    'movement_type' => StockMovement::TYPE_RECEIPT,
                    'reference_type' => 'inventory_transfer',
                    'reference_id' => $issue->id,
                    'quantity' => $line['quantity'],
                    'unit_cost_base' => $lot->unit_cost_base,
                    'base_currency_id' => $lot->base_currency_id,
                    'occurred_at' => $issuedAt,
                ]);
            }
        }

        $company = Company::query()->where('singleton_key', 'primary')->firstOrFail();
        $this->audit(
            $issuer,
            $company,
            $sourceType === InventoryIssue::SOURCE_TRANSFER ? 'transferred' : 'issued',
            'inventory_issue',
            $issue->id,
            [
                'reference' => $issue->reference,
                'source_type' => $issue->source_type,
                'items' => $issue->items()->get(['product_id', 'quantity'])->toArray(),
            ],
        );

        return $issue;
    }

    private function createApprovedCount(
        string $reference,
        Warehouse $warehouse,
        User $creator,
        User $approver,
        array $lines,
    ): InventoryCount {
        $submittedAt = now();
        $count = InventoryCount::query()->create([
            'reference' => $reference,
            'warehouse_id' => $warehouse->id,
            'status' => InventoryCount::STATUS_PENDING_APPROVAL,
            'created_by_user_id' => $creator->id,
            'submitted_at' => $submittedAt,
        ]);
        $company = Company::query()->where('singleton_key', 'primary')->firstOrFail();
        $this->audit($creator, $company, 'submitted_for_approval', 'inventory_count', $count->id, [
            'reference' => $count->reference,
            'status' => InventoryCount::STATUS_PENDING_APPROVAL,
        ]);
        $countItems = [];

        foreach ($lines as $line) {
            /** @var Product $product */
            $product = $line['product'];
            $movements = StockMovement::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('product_id', $product->id)
                ->get(['id', 'movement_type', 'quantity']);
            $systemQuantity = $movements->sum(fn (StockMovement $movement): float => $movement->movement_type === StockMovement::TYPE_RECEIPT
                ? (float) $movement->quantity
                : -(float) $movement->quantity);
            $countedQuantity = (float) $line['counted_quantity'];
            $item = InventoryCountItem::query()->create([
                'inventory_count_id' => $count->id,
                'product_id' => $product->id,
                'product_code' => $product->code,
                'product_name' => $product->name,
                'unit_abbreviation' => $product->unit->abbreviation,
                'system_quantity' => number_format($systemQuantity, 3, '.', ''),
                'movement_id_snapshot' => $movements->max('id'),
                'counted_quantity' => $line['counted_quantity'],
                'difference_quantity' => number_format($countedQuantity - $systemQuantity, 3, '.', ''),
                'surplus_unit_cost' => $line['surplus_unit_cost'] ?? null,
                'expires_on' => $line['expires_on'] ?? null,
            ]);
            $countItems[] = $item;
        }

        $approvedAt = now();
        foreach ($countItems as $item) {
            $difference = (float) $item->difference_quantity;
            if ($difference > 0) {
                $surplusLot = WarehouseLot::query()->create([
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $item->product_id,
                    'receipt_item_id' => null,
                    'lot_code' => $reference.'-'.str_pad((string) $item->id, 4, '0', STR_PAD_LEFT),
                    'quantity_received' => $item->difference_quantity,
                    'unit_cost' => $item->surplus_unit_cost,
                    'unit_cost_base' => $item->surplus_unit_cost,
                    'quote_currency_id' => $this->baseCurrencyId(),
                    'base_currency_id' => $this->baseCurrencyId(),
                    'exchange_rate' => '1.00000000',
                    'expires_on' => $item->expires_on,
                    'received_at' => $approvedAt,
                ]);
                StockMovement::query()->create([
                    'warehouse_lot_id' => $surplusLot->id,
                    'warehouse_id' => $warehouse->id,
                    'product_id' => $item->product_id,
                    'user_id' => $approver->id,
                    'movement_type' => StockMovement::TYPE_RECEIPT,
                    'reference_type' => 'inventory_count',
                    'reference_id' => $count->id,
                    'quantity' => $item->difference_quantity,
                    'unit_cost_base' => $item->surplus_unit_cost,
                    'base_currency_id' => $this->baseCurrencyId(),
                    'occurred_at' => $approvedAt,
                ]);
            } elseif ($difference < 0) {
                $remaining = abs($difference);
                $lots = WarehouseLot::query()
                    ->where('warehouse_id', $warehouse->id)
                    ->where('product_id', $item->product_id)
                    ->orderBy('received_at')
                    ->orderBy('id')
                    ->get();
                foreach ($lots as $lot) {
                    $balance = $lot->movements->sum(fn (StockMovement $movement): float => $movement->movement_type === StockMovement::TYPE_RECEIPT
                        ? (float) $movement->quantity
                        : -(float) $movement->quantity);
                    if ($balance <= 0) {
                        continue;
                    }

                    $quantity = min($remaining, $balance);
                    StockMovement::query()->create([
                        'warehouse_lot_id' => $lot->id,
                        'warehouse_id' => $warehouse->id,
                        'product_id' => $item->product_id,
                        'user_id' => $approver->id,
                        'movement_type' => StockMovement::TYPE_ISSUE,
                        'reference_type' => 'inventory_count',
                        'reference_id' => $count->id,
                        'quantity' => number_format($quantity, 3, '.', ''),
                        'unit_cost_base' => $lot->unit_cost_base,
                        'base_currency_id' => $this->baseCurrencyId(),
                        'occurred_at' => $approvedAt,
                    ]);
                    $remaining -= $quantity;
                    if ($remaining <= 0) {
                        break;
                    }
                }

                if ($remaining > 0) {
                    throw new RuntimeException('The demo shortage exceeds available lot balances; no transactions were created.');
                }
            }
        }

        $count->update([
            'status' => InventoryCount::STATUS_APPROVED,
            'approved_by_user_id' => $approver->id,
            'approved_at' => $approvedAt,
        ]);
        $this->audit($approver, $company, 'approved', 'inventory_count', $count->id, [
            'reference' => $count->reference,
            'status' => InventoryCount::STATUS_APPROVED,
            'items' => $count->items()->get([
                'product_id',
                'system_quantity',
                'counted_quantity',
                'difference_quantity',
            ])->toArray(),
        ]);

        return $count;
    }

    private function baseCurrencyId(): int
    {
        return (int) Company::query()->where('singleton_key', 'primary')->value('base_currency_id');
    }

    private function audit(User $user, Company $company, string $action, string $entityType, int $entityId, array $changes): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'created_at' => now(),
        ]);
    }
}
