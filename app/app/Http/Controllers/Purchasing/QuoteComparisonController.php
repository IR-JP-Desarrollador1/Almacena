<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Currency;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Requisition;
use App\Models\RequisitionItem;
use App\Models\RequisitionItemAward;
use App\Models\Supplier;
use App\Models\SupplierQuote;
use App\Models\SupplierQuoteItem;
use App\Models\User;
use App\Support\DecimalMath;
use App\Support\SupplierQuoteCalculator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class QuoteComparisonController extends Controller
{
    public function registrationQueue(Request $request): View
    {
        $company = $this->company();
        $requisitions = Requisition::query()
            ->where('status', Requisition::STATUS_APPROVED)
            ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $company->id))
            ->whereIn('warehouse_id', $request->user()->accessibleWarehouses()->select('warehouses.id'))
            ->whereHas('items', fn (Builder $items) => $items
                ->whereDoesntHave('award')
                ->whereRaw('requisition_items.quantity > (SELECT COALESCE(SUM(inventory_issue_items.quantity), 0) FROM inventory_issue_items WHERE inventory_issue_items.requisition_item_id = requisition_items.id)'))
            ->with(['department', 'warehouse.branch', 'requester', 'items.award'])
            ->withCount('supplierQuotes')
            ->latest()
            ->paginate(20);

        return view('purchasing.quotes.queue', compact('requisitions'));
    }

    public function register(Request $request, Requisition $requisition): View
    {
        $company = $this->company();
        $requisition = $this->accessibleApprovedRequisition($requisition->id, $request, $company->id);
        $requisition->load(['department', 'warehouse.branch', 'items.product', 'items.unit']);
        $requisition->items->each(function (RequisitionItem $item) {
            $item->remaining_quantity = $this->quantityString($this->remainingQuantityMilli($item));
        });
        $quotes = SupplierQuote::query()
            ->where('requisition_id', $requisition->id)
            ->with(['supplier', 'quoteCurrency', 'items'])
            ->orderBy('supplier_id')
            ->get();

        return view('purchasing.quotes.register', [
            'requisition' => $requisition,
            'quotes' => $quotes,
            'suppliers' => Supplier::query()->where('is_active', true)->with('latestEvaluation')->orderBy('legal_name')->get(),
            'currencies' => Currency::query()->where('is_active', true)->orderBy('code')->get(),
            'baseCurrency' => $company->baseCurrency()->firstOrFail(),
        ]);
    }

    public function index(Request $request, Requisition $requisition, SupplierQuoteCalculator $calculator): View
    {
        $company = $this->company();
        $requisition = $this->accessibleApprovedRequisition($requisition->id, $request, $company->id);
        $requisition->load([
            'department',
            'warehouse.branch',
            'items.product',
            'items.unit',
            'items.award.quoteItem.quote.supplier',
            'items.award.quoteItem.quote.purchaseOrders',
            'items.award.quoteItem.purchaseOrderItem',
            'items.inventoryIssueItems',
        ]);
        $requisition->items->each(function (RequisitionItem $item) {
            $issuedMilli = $item->inventoryIssueItems->sum(fn ($issueItem) => $this->quantityMilli($issueItem->quantity));
            $item->issued_quantity = $this->quantityString($issuedMilli);
            $item->remaining_quantity = $this->quantityString(max(0, $this->quantityMilli($item->quantity) - $issuedMilli));
        });
        $quotes = SupplierQuote::query()
            ->where('requisition_id', $requisition->id)
            ->with(['supplier.latestEvaluation', 'quoteCurrency', 'baseCurrency', 'items.requisitionItem', 'items.award', 'purchaseOrders'])
            ->orderBy('supplier_id')
            ->get();
        $offersByItem = SupplierQuoteItem::query()
            ->whereIn('requisition_item_id', $requisition->items->pluck('id'))
            ->whereHas('quote.supplier', fn (Builder $supplier) => $supplier->where('is_active', true))
            ->with(['quote.supplier.latestEvaluation', 'requisitionItem'])
            ->get()
            ->groupBy('requisition_item_id');

        foreach ($offersByItem as $offers) {
            foreach ($offers as $offer) {
                $offer->requisitionItem->remaining_quantity = $requisition->items
                    ->firstWhere('id', $offer->requisition_item_id)
                    ->remaining_quantity;
                $offer->gross_total_base = $calculator->grossTotalInBase($offer);
            }
        }

        return view('purchasing.quotes.index', [
            'requisition' => $requisition,
            'quotes' => $quotes,
            'offersByItem' => $offersByItem,
            'baseCurrency' => $company->baseCurrency()->firstOrFail(),
            'hasPendingAwards' => $requisition->items->contains(fn (RequisitionItem $item) => $item->award
                && ! $item->award->quoteItem->purchaseOrderItem
                && $this->quantityMilli($item->remaining_quantity) > 0
            ),
        ]);
    }

    public function store(Request $request, Requisition $requisition): RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->accessibleApprovedRequisition($requisition->id, $request, $company->id);
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'quote_reference' => ['required', 'string', 'max:100'],
            'quote_date' => ['required', 'date', 'before_or_equal:today'],
            'quote_currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')->where('is_active', true)],
            'exchange_rate' => ['required', 'numeric', 'decimal:0,8', 'min:0.00000001', 'max:999999999.99999999'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.requisition_item_id' => ['required', 'integer', 'distinct', 'exists:requisition_items,id'],
            'items.*.unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:99999999999.9999'],
            'items.*.tax_rate' => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:100'],
            'items.*.brand' => ['required', 'string', 'max:100'],
            'items.*.delivery_days' => ['required', 'integer', 'min:0', 'max:3650'],
        ]);

        $currency = Currency::query()->whereKey($validated['quote_currency_id'])->where('is_active', true)->firstOrFail();

        $itemIds = collect($validated['items'])->pluck('requisition_item_id')->map(fn ($id) => (int) $id);
        if ($itemIds->diff($requisition->items()->pluck('id'))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'items' => __('Las ofertas solo pueden incluir productos de esta requisición.'),
            ]);
        }

        DB::transaction(function () use ($request, $company, $requisition, $validated, $currency) {
            $company = Company::query()->whereKey($company->id)->lockForUpdate()->firstOrFail();
            Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            abort_unless($company->base_currency_id, 409, __('Configura la moneda base de la empresa antes de registrar cotizaciones.'));
            $quote = SupplierQuote::query()
                ->where('requisition_id', $requisition->id)
                ->where('supplier_id', $validated['supplier_id'])
                ->lockForUpdate()
                ->first();
            $quoteIsInUse = $quote && ($quote->items()->whereHas('award')->exists() || $quote->purchaseOrders()->exists());
            $submittedItemIds = collect($validated['items'])->pluck('requisition_item_id')->map(fn ($id) => (int) $id);

            foreach ($submittedItemIds as $itemId) {
                $item = RequisitionItem::query()
                    ->whereKey($itemId)
                    ->where('requisition_id', $requisition->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($this->remainingQuantityMilli($item) <= 0) {
                    throw ValidationException::withMessages([
                        'items' => __('No se pueden cotizar productos que ya fueron despachados por completo.'),
                    ]);
                }
                if (RequisitionItemAward::query()->where('requisition_item_id', $itemId)->exists()) {
                    throw ValidationException::withMessages([
                        'items' => __('No se pueden cotizar productos que ya fueron seleccionados.'),
                    ]);
                }
            }

            $before = $quote ? $quote->only([
                'supplier_id', 'quote_reference', 'quote_date', 'quote_currency_id', 'exchange_rate',
            ]) : null;
            if ($quoteIsInUse) {
                $headerMatches = $quote->quote_reference === $validated['quote_reference']
                    && $quote->quote_date->toDateString() === $validated['quote_date']
                    && (int) $quote->quote_currency_id === (int) $currency->id
                    && DecimalMath::compare((string) $quote->exchange_rate, (string) $validated['exchange_rate'], 8) === 0;
                $existingItemIds = $quote->items()->pluck('requisition_item_id')->map(fn ($id) => (int) $id);

                if (! $headerMatches || $submittedItemIds->intersect($existingItemIds)->isNotEmpty()) {
                    throw ValidationException::withMessages([
                        'supplier_id' => __('La cotización ya tiene selecciones; conserva sus datos y agrega solo productos pendientes.'),
                    ]);
                }
            } else {
                if (
                    $currency->id === $company->base_currency_id
                    && DecimalMath::compare((string) $validated['exchange_rate'], '1.00000000', 8) !== 0
                ) {
                    throw ValidationException::withMessages([
                        'exchange_rate' => __('La tasa debe ser 1 cuando la moneda cotizada coincide con la moneda base.'),
                    ]);
                }

                $quote ??= new SupplierQuote;
                $quote->fill([
                    'requisition_id' => $requisition->id,
                    'supplier_id' => $validated['supplier_id'],
                    'quote_reference' => $validated['quote_reference'],
                    'quote_date' => $validated['quote_date'],
                    'quote_currency_id' => $currency->id,
                    'base_currency_id' => $company->base_currency_id,
                    'exchange_rate' => $validated['exchange_rate'],
                    'created_by_user_id' => $quote->created_by_user_id ?? $request->user()->id,
                ]);
                $quote->save();
                $quote->items()->delete();
            }

            foreach ($validated['items'] as $line) {
                $quote->items()->create($line);
            }

            AuditLog::create([
                'user_id' => $request->user()->id,
                'company_id' => $company->id,
                'action' => $before === null ? 'created' : ($quoteIsInUse ? 'extended' : 'updated'),
                'entity_type' => 'supplier_quote',
                'entity_id' => $quote->id,
                'changes' => [
                    'before' => $before,
                    'after' => [
                        ...$quote->only(['supplier_id', 'quote_reference', 'quote_date', 'quote_currency_id', 'exchange_rate']),
                        'items' => $quote->items()->whereIn('requisition_item_id', $submittedItemIds)->get()->toArray(),
                    ],
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return redirect()->route('purchasing.quotes.register', $requisition)->with('status', __('Cotización guardada correctamente.'));
    }

    public function select(Request $request, Requisition $requisition, SupplierQuoteCalculator $calculator): RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->accessibleApprovedRequisition($requisition->id, $request, $company->id);
        $submittedAwards = $request->input('awards', []);
        if (! is_array($submittedAwards)) {
            throw ValidationException::withMessages(['awards' => __('Selecciona al menos un producto con suficientes ofertas.')]);
        }
        $awards = array_filter(
            $submittedAwards,
            static fn ($quoteItemId) => $quoteItemId !== null && $quoteItemId !== '',
        );
        if ($awards === []) {
            throw ValidationException::withMessages(['awards' => __('Selecciona al menos un producto con suficientes ofertas.')]);
        }
        $request->merge(['awards' => $awards]);
        $validated = $request->validate([
            'awards' => ['required', 'array', 'min:1'],
            'awards.*' => ['required', 'integer', 'exists:supplier_quote_items,id'],
            'justifications' => ['nullable', 'array'],
            'justifications.*' => ['nullable', 'string', 'max:2000'],
        ]);
        DB::transaction(function () use ($request, $company, $requisition, $validated, $calculator) {
            Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            $lines = $requisition->items()->with('product', 'unit', 'inventoryIssueItems')->get()->keyBy(fn ($item) => (string) $item->id);
            $selected = [];

            foreach ($validated['awards'] as $itemId => $quoteItemId) {
                $line = $lines->get((string) $itemId);
                if (! $line || $line->award()->exists()) {
                    throw ValidationException::withMessages([
                        'awards' => __('El producto seleccionado no pertenece a esta requisición o ya tiene proveedor elegido.'),
                    ]);
                }
                $line->remaining_quantity = $this->quantityString($this->remainingQuantityMilli($line));
                if ($this->quantityMilli($line->remaining_quantity) <= 0) {
                    throw ValidationException::withMessages([
                        'awards' => __('No se pueden adjudicar productos que ya fueron despachados por completo.'),
                    ]);
                }

                $offer = SupplierQuoteItem::query()
                    ->whereKey($quoteItemId)
                    ->where('requisition_item_id', $line->id)
                    ->whereHas('quote', fn (Builder $query) => $query
                        ->where('requisition_id', $requisition->id)
                        ->whereHas('supplier', fn (Builder $supplier) => $supplier->where('is_active', true)))
                    ->with(['quote.supplier', 'requisitionItem'])
                    ->firstOrFail();
                $offer->requisitionItem->remaining_quantity = $line->remaining_quantity;

                $supplierCount = SupplierQuoteItem::query()
                    ->where('requisition_item_id', $line->id)
                    ->whereHas('quote', fn (Builder $query) => $query
                        ->where('requisition_id', $requisition->id)
                        ->whereHas('supplier', fn (Builder $supplier) => $supplier->where('is_active', true)))
                    ->distinct()
                    ->count('supplier_quote_id');
                if ($supplierCount < 2) {
                    throw ValidationException::withMessages([
                        "awards.{$itemId}" => __('Cada producto requiere ofertas de al menos dos proveedores distintos.'),
                    ]);
                }

                $offers = SupplierQuoteItem::query()
                    ->where('requisition_item_id', $line->id)
                    ->whereHas('quote', fn (Builder $query) => $query
                        ->where('requisition_id', $requisition->id)
                        ->whereHas('supplier', fn (Builder $supplier) => $supplier->where('is_active', true)))
                    ->with(['quote', 'requisitionItem'])
                    ->get();
                $lowest = null;
                foreach ($offers as $candidate) {
                    $candidate->requisitionItem->remaining_quantity = $line->remaining_quantity;
                    $candidate->gross_total_base = $calculator->grossTotalInBase($candidate);
                    if ($lowest === null || DecimalMath::compare($candidate->gross_total_base, $lowest, 4) < 0) {
                        $lowest = $candidate->gross_total_base;
                    }
                }

                $offer->gross_total_base = $calculator->grossTotalInBase($offer);
                $justification = trim((string) ($validated['justifications'][$itemId] ?? ''));
                if (
                    DecimalMath::compare($offer->gross_total_base, $lowest, 4) > 0
                    && mb_strlen($justification) < 3
                ) {
                    throw ValidationException::withMessages([
                        "justifications.{$itemId}" => __('Justifica la selección cuando no sea la oferta de menor costo total.'),
                    ]);
                }

                $selected[] = [$line, $offer, $justification];
            }

            foreach ($selected as [$line, $offer, $justification]) {
                $award = RequisitionItemAward::create([
                    'requisition_item_id' => $line->id,
                    'supplier_quote_item_id' => $offer->id,
                    'selected_by_user_id' => $request->user()->id,
                    'justification' => $justification !== '' ? $justification : null,
                    'selected_at' => now(),
                ]);

                AuditLog::create([
                    'user_id' => $request->user()->id,
                    'company_id' => $company->id,
                    'action' => 'selected',
                    'entity_type' => 'requisition_item_award',
                    'entity_id' => $award->id,
                    'changes' => [
                        'requisition_item_id' => $line->id,
                        'supplier_quote_item_id' => $offer->id,
                        'supplier_id' => $offer->quote->supplier_id,
                        'gross_total_base' => $offer->gross_total_base,
                        'justification' => $justification !== '' ? $justification : null,
                    ],
                    'ip_address' => $request->ip(),
                ]);
            }
        });

        return redirect()->route('purchasing.quotes.index', $requisition)
            ->with('status', __('Comparación guardada. Puedes generar las órdenes borrador cuando estés listo.'));
    }

    public function generateOrders(Request $request, Requisition $requisition): RedirectResponse
    {
        $company = $this->company();
        $requisition = $this->accessibleApprovedRequisition($requisition->id, $request, $company->id);
        $user = $request->user();

        $orders = DB::transaction(function () use ($request, $company, $requisition, $user) {
            Requisition::query()->whereKey($requisition->id)->lockForUpdate()->firstOrFail();
            $awards = RequisitionItemAward::query()
                ->whereHas('requisitionItem', fn (Builder $query) => $query->where('requisition_id', $requisition->id))
                ->whereDoesntHave('quoteItem.purchaseOrderItem')
                ->with(['requisitionItem.inventoryIssueItems', 'quoteItem.quote'])
                ->get();
            $awards = $awards->filter(fn (RequisitionItemAward $award) => $this->remainingQuantityMilli($award->requisitionItem) > 0
            );

            if ($awards->isEmpty()) {
                throw ValidationException::withMessages([
                    'awards' => __('No hay comparaciones guardadas pendientes para generar órdenes.'),
                ]);
            }

            $selected = $awards
                ->map(fn (RequisitionItemAward $award) => [
                    $award->requisitionItem,
                    $award->quoteItem,
                    (string) ($award->justification ?? ''),
                ])
                ->all();

            return $this->createDraftOrders($request, $company, $requisition, $selected, $user);
        });

        return redirect()->route('purchasing.requisitions.show', $requisition)
            ->with('status', __('Se generaron :count órdenes borrador; requieren aprobación antes de recibir.', ['count' => count($orders)]));
    }

    private function createDraftOrders(
        Request $request,
        Company $company,
        Requisition $requisition,
        array $selected,
        User $user,
    ): array {
        $ordersByQuote = collect($selected)->groupBy(fn (array $selection) => $selection[1]->supplier_quote_id);
        $createdOrders = [];

        foreach ($ordersByQuote as $quoteId => $quoteSelections) {
            $quote = SupplierQuote::query()->whereKey($quoteId)->with(['supplier', 'quoteCurrency'])->lockForUpdate()->firstOrFail();
            $order = PurchaseOrder::create([
                'reference' => 'TMP-'.Str::random(24),
                'requisition_id' => $requisition->id,
                'supplier_quote_id' => $quote->id,
                'supplier_id' => $quote->supplier_id,
                'quote_currency_id' => $quote->quote_currency_id,
                'base_currency_id' => $quote->base_currency_id,
                'exchange_rate' => $quote->exchange_rate,
                'created_by_user_id' => $user->id,
                'quote_reference' => $quote->quote_reference,
                'quote_date' => $quote->quote_date,
                'status' => PurchaseOrder::STATUS_DRAFT,
            ]);
            $order->update(['reference' => 'OC-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT)]);

            foreach ($quoteSelections as [$line, $offer]) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'requisition_item_id' => $line->id,
                    'supplier_quote_item_id' => $offer->id,
                    'product_id' => $line->product_id,
                    'product_code' => $line->product_code,
                    'product_name' => $line->product_name,
                    'unit_abbreviation' => $line->unit_abbreviation,
                    'quantity' => $this->quantityString($this->remainingQuantityMilli($line)),
                    'unit_cost' => $offer->unit_cost,
                    'unit_cost_base' => DecimalMath::multiply(
                        (string) $offer->unit_cost,
                        4,
                        (string) $quote->exchange_rate,
                        8,
                        4,
                    ),
                    'tax_rate' => $offer->tax_rate,
                    'brand' => $offer->brand,
                    'delivery_days' => $offer->delivery_days,
                ]);
            }

            AuditLog::create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'action' => 'created',
                'entity_type' => 'purchase_order',
                'entity_id' => $order->id,
                'changes' => [
                    'requisition_id' => $requisition->id,
                    'supplier_id' => $quote->supplier_id,
                    'supplier_quote_id' => $quote->id,
                    'requisition_item_ids' => $quoteSelections->map(fn (array $selection) => $selection[0]->id)->all(),
                ],
                'ip_address' => $request->ip(),
            ]);
            $createdOrders[] = $order;
        }

        return $createdOrders;
    }

    private function accessibleApprovedRequisition(int $id, Request $request, int $companyId): Requisition
    {
        $requisition = Requisition::query()
            ->whereKey($id)
            ->where('status', Requisition::STATUS_APPROVED)
            ->whereHas('department', fn (Builder $departments) => $departments->where('company_id', $companyId))
            ->with(['items.award', 'warehouse.branch'])
            ->firstOrFail();
        abort_unless(
            $requisition->warehouse_id !== null && $request->user()->canAccessWarehouse($requisition->warehouse_id),
            403,
            __('No tienes acceso a la bodega de esta requisición.'),
        );

        return $requisition;
    }

    private function remainingQuantityMilli(RequisitionItem $item): int
    {
        $issued = $item->relationLoaded('inventoryIssueItems')
            ? $item->inventoryIssueItems->sum(fn ($issueItem) => $this->quantityMilli($issueItem->quantity))
            : $this->quantityMilli($item->inventoryIssueItems()->sum('quantity'));

        return max(0, $this->quantityMilli($item->quantity) - $issued);
    }

    private function quantityMilli(mixed $quantity): int
    {
        return (int) round((float) $quantity * 1000);
    }

    private function quantityString(int $quantityMilli): string
    {
        return number_format($quantityMilli / 1000, 3, '.', '');
    }

    private function company(): Company
    {
        return Company::query()->where('singleton_key', 'primary')->firstOrFail();
    }
}
