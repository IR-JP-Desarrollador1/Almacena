<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OperationalTranslationsTest extends TestCase
{
    public function test_main_operational_views_have_translations_for_each_supported_language(): void
    {
        $views = [
            'dashboard.blade.php',
            'inventory/kardex.blade.php',
            'inventory/kardex-pdf.blade.php',
            'inventory/receipts/create.blade.php',
            'inventory/receipts/index.blade.php',
            'inventory/receipts/show.blade.php',
            'inventory/issues/create.blade.php',
            'inventory/issues/index.blade.php',
            'inventory/issues/show.blade.php',
            'purchasing/requisitions/form.blade.php',
            'purchasing/requisitions/index.blade.php',
            'purchasing/requisitions/show.blade.php',
            'purchasing/orders/index.blade.php',
            'purchasing/orders/show.blade.php',
            'purchasing/quotes/index.blade.php',
            'admin/catalogs/products/import.blade.php',
            'admin/catalogs/supplier-evaluations/index.blade.php',
        ];
        $translationKeys = [];

        foreach ($views as $view) {
            $contents = File::get(resource_path("views/{$view}"));
            preg_match_all('/__\(\s*\'((?:\\\\.|[^\'])*)\'/', $contents, $matches);

            foreach ($matches[1] as $key) {
                if ($key !== 'Excel (.xlsx)' && $key !== 'CSV' && $key !== 'PDF' && $key !== 'requisition_status.') {
                    $translationKeys[$key] = true;
                }
            }
        }

        foreach (['en', 'fr', 'pt_BR'] as $locale) {
            $catalog = json_decode(
                File::get(resource_path("lang/{$locale}.json")),
                true,
                512,
                JSON_THROW_ON_ERROR
            );

            foreach (array_keys($translationKeys) as $key) {
                $this->assertArrayHasKey($key, $catalog, "{$locale}.json is missing the translation for: {$key}");
            }

            foreach (['draft', 'pending_approval', 'approved', 'rejected'] as $status) {
                $this->assertArrayHasKey("requisition_status.{$status}", $catalog);
            }
        }
    }

    public function test_operational_labels_and_dynamic_statuses_are_translated_at_runtime(): void
    {
        $expected = [
            'en' => ['Record receipt', 'Pending approval', 'Warehouse: MAIN', 'Regional format: 08/17/2026'],
            'fr' => ['Enregistrer la réception', "En attente d'approbation", 'Entrepôt : MAIN', 'Format régional : 17/08/2026'],
            'pt_BR' => ['Registrar recebimento', 'Pendente de aprovação', 'Almoxarifado: MAIN', 'Formato regional: 17/08/2026'],
        ];
        $originalLocale = app()->getLocale();

        try {
            foreach ($expected as $locale => [$receiptLabel, $statusLabel, $warehouseLabel, $dateFormatHint]) {
                app()->setLocale($locale);

                $this->assertSame($receiptLabel, __('Registrar entrada'));
                $this->assertSame($statusLabel, __('requisition_status.pending_approval'));
                $this->assertSame($warehouseLabel, __('Bodega: :warehouse', ['warehouse' => 'MAIN']));
                $this->assertSame(
                    $dateFormatHint,
                    __('Formato regional: :date', ['date' => $locale === 'en' ? '08/17/2026' : '17/08/2026'])
                );
            }
        } finally {
            app()->setLocale($originalLocale);
        }
    }
}
