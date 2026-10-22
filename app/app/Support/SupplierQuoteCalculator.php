<?php

namespace App\Support;

use App\Models\SupplierQuoteItem;

class SupplierQuoteCalculator
{
    public function grossTotalInBase(SupplierQuoteItem $item): string
    {
        $quote = $item->quote;
        $quantity = (string) ($item->requisitionItem->remaining_quantity ?? $item->requisitionItem->quantity);
        $taxFraction = DecimalMath::multiply((string) $item->tax_rate, 4, '0.01', 2, 8);
        $taxFactor = DecimalMath::add('1.00000000', 8, $taxFraction, 8, 8);
        $grossUnitCost = DecimalMath::multiply((string) $item->unit_cost, 4, $taxFactor, 8, 4);
        $grossQuoteTotal = DecimalMath::multiply($grossUnitCost, 4, $quantity, 3, 4);

        return DecimalMath::multiply($grossQuoteTotal, 4, (string) $quote->exchange_rate, 8, 4);
    }
}
