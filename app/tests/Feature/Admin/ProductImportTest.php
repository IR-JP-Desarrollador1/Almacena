<?php

namespace Tests\Feature\Admin;

use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    private User $administrator;

    private Company $company;

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
        ]);
        $this->administrator = User::factory()->create();
        $this->administrator->roles()->attach(Role::query()->where('slug', 'administracion')->firstOrFail());
        $this->actingAs($this->administrator);
    }

    public function test_csv_imports_valid_rows_skips_duplicates_and_reports_row_errors(): void
    {
        $unit = Unit::create(['code' => 'EA', 'name' => 'Each', 'abbreviation' => 'ea']);
        Unit::create([
            'code' => 'OFF',
            'name' => 'Inactive unit',
            'abbreviation' => 'off',
            'is_active' => false,
        ]);
        Product::create(['code' => 'EXISTING', 'name' => 'Existing', 'unit_id' => $unit->id]);
        $csv = implode("\n", [
            'code,name,description,unit_code,tracks_expiration,is_active',
            'P-1,Product one,,EA,yes,active',
            'P-1,Duplicate in file,,EA,0,1',
            'EXISTING,Existing duplicate,,EA,0,1',
            'P-MISSING,Missing unit,,NOPE,0,1',
            'P-INACTIVE,Inactive unit,,OFF,0,1',
            'P-2,Inactive product,,EA,0,0',
            'P-INVALID,,Missing name,EA,0,1',
        ]);

        $response = $this->post(route('admin.catalogs.products.import'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ]);

        $response->assertOk()->assertDownload('resultado_importacion_productos.csv');
        $report = $response->streamedContent();
        $this->assertStringContainsString('"Productos importados",2', $report);
        $this->assertStringContainsString('"Duplicados omitidos",2', $report);
        $this->assertStringContainsString('"Filas con errores",3', $report);
        $this->assertStringContainsString('P-MISSING', $report);
        $this->assertStringContainsString('P-INACTIVE', $report);
        $this->assertDatabaseHas('products', [
            'code' => 'P-1',
            'unit_id' => $unit->id,
            'tracks_expiration' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('products', ['code' => 'P-2', 'is_active' => false]);
        $this->assertDatabaseHas('products', ['code' => 'EXISTING', 'name' => 'Existing']);
        $this->assertDatabaseMissing('products', ['code' => 'P-INACTIVE']);
        $this->assertSame(3, Product::query()->count());
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_fictional_catalog_sample_imports_valid_rows_and_reports_issues_without_overwriting(): void
    {
        $box = Unit::create(['code' => 'BOX', 'name' => 'Box', 'abbreviation' => 'box']);
        $liter = Unit::create(['code' => 'L', 'name' => 'Liter', 'abbreviation' => 'l']);
        Unit::create([
            'code' => 'OFF',
            'name' => 'Inactive unit',
            'abbreviation' => 'off',
            'is_active' => false,
        ]);
        Product::create(['code' => 'EXISTING', 'name' => 'Original product', 'unit_id' => $box->id]);
        $sample = file_get_contents(base_path('tests/Fixtures/product-catalog-sample.csv'));

        $response = $this->post(route('admin.catalogs.products.import'), [
            'file' => UploadedFile::fake()->createWithContent('fictional-catalog.csv', $sample),
        ]);

        $response->assertOk()->assertDownload('resultado_importacion_productos.csv');
        $report = $response->streamedContent();
        $this->assertStringContainsString('"Productos importados",3', $report);
        $this->assertStringContainsString('"Duplicados omitidos",2', $report);
        $this->assertStringContainsString('"Filas con errores",2', $report);
        $this->assertStringContainsString('DEMO-ERR-UNIT', $report);
        $this->assertStringContainsString('DEMO-ERR-OFF', $report);
        $this->assertDatabaseHas('products', [
            'code' => 'DEMO-100',
            'name' => 'Guantes de nitrilo',
            'unit_id' => $box->id,
            'tracks_expiration' => true,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('products', [
            'code' => 'DEMO-200',
            'name' => 'Alcohol isopropílico',
            'unit_id' => $liter->id,
        ]);
        $this->assertDatabaseHas('products', [
            'code' => 'DEMO-300',
            'unit_id' => Unit::query()->where('code', 'OFF')->value('id'),
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('products', ['code' => 'EXISTING', 'name' => 'Original product']);
        $this->assertDatabaseMissing('products', ['code' => 'DEMO-ERR-UNIT']);
        $this->assertDatabaseCount('audit_logs', 3);
    }

    public function test_xlsx_imports_products_and_both_template_formats_are_available(): void
    {
        $unit = Unit::create(['code' => 'EA', 'name' => 'Each', 'abbreviation' => 'ea']);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['code', 'name', 'description', 'unit_code', 'tracks_expiration', 'is_active'],
            ['XLS-1', 'Workbook product', null, 'EA', true, true],
        ]);
        $temporaryFile = tempnam(sys_get_temp_dir(), 'product-import-');
        (new Xlsx($spreadsheet))->save($temporaryFile);
        $content = file_get_contents($temporaryFile);
        unlink($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        $csvTemplate = $this->get(route('admin.catalogs.products.import.template', 'csv'));
        $csvTemplate
            ->assertOk()
            ->assertDownload('plantilla_productos.csv');
        $this->assertStringContainsString(
            'code,name,description,unit_code,tracks_expiration,is_active',
            $csvTemplate->streamedContent(),
        );
        $xlsxTemplate = $this->get(route('admin.catalogs.products.import.template', 'xlsx'));
        $xlsxTemplate->assertOk()->assertDownload('plantilla_productos.xlsx');
        $templatePath = tempnam(sys_get_temp_dir(), 'product-template-');
        file_put_contents($templatePath, $xlsxTemplate->streamedContent());
        $downloadedTemplate = (new XlsxReader)->load($templatePath);
        $this->assertSame(
            ['code', 'name', 'description', 'unit_code', 'tracks_expiration', 'is_active'],
            $downloadedTemplate->getActiveSheet()->toArray()[0],
        );
        $downloadedTemplate->disconnectWorksheets();
        unlink($templatePath);

        $response = $this->post(route('admin.catalogs.products.import'), [
            'file' => UploadedFile::fake()->createWithContent('products.xlsx', $content),
        ]);

        $response->assertOk()->assertDownload('resultado_importacion_productos.csv');
        $this->assertDatabaseHas('products', ['code' => 'XLS-1', 'unit_id' => $unit->id]);
    }

    public function test_csv_import_accepts_exactly_one_thousand_rows_and_rejects_more(): void
    {
        Unit::create(['code' => 'EA', 'name' => 'Each', 'abbreviation' => 'ea']);
        $header = 'code,name,description,unit_code,tracks_expiration,is_active';
        $row = 'MAX-1,Maximum row,,EA,0,1';
        $csv = implode("\n", [$header, ...array_fill(0, 1000, $row)]);

        $response = $this->post(route('admin.catalogs.products.import'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ]);

        $response->assertOk();
        $this->assertStringContainsString('"Total de filas procesadas",1000', $response->streamedContent());
        $this->assertSame(1, Product::query()->count());

        $tooManyRows = implode("\n", [$header, ...array_fill(0, 1001, 'OVER-LIMIT,Over limit,,EA,0,1')]);
        $this->from(route('admin.catalogs.products.import.form'))
            ->post(route('admin.catalogs.products.import'), [
                'file' => UploadedFile::fake()->createWithContent('too-many.csv', $tooManyRows),
            ])
            ->assertRedirect(route('admin.catalogs.products.import.form'))
            ->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('products', ['code' => 'OVER-LIMIT']);
    }

    public function test_csv_import_accepts_utf8_bom_and_semicolon_delimiters(): void
    {
        Unit::create(['code' => 'EA', 'name' => 'Each', 'abbreviation' => 'ea']);
        $csv = "\xEF\xBB\xBF".implode("\r\n", [
            'code;name;description;unit_code;tracks_expiration;is_active',
            'CSV-1;Semicolon product;;EA;no;active',
        ]);

        $this->post(route('admin.catalogs.products.import'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
        ])->assertOk();

        $this->assertDatabaseHas('products', [
            'code' => 'CSV-1',
            'name' => 'Semicolon product',
            'is_active' => true,
            'tracks_expiration' => false,
        ]);
    }

    public function test_catalog_view_permission_cannot_access_product_import_features(): void
    {
        $viewer = User::factory()->create();
        $role = Role::create(['name' => 'Consulta de catálogos', 'slug' => 'consulta-catalogos']);
        $role->permissions()->attach(Permission::query()->where('code', 'catalogs.view')->firstOrFail());
        $viewer->roles()->attach($role);

        $this->actingAs($viewer)
            ->get(route('admin.catalogs.products.import.form'))
            ->assertForbidden();
        $this->get(route('admin.catalogs.products.index'))
            ->assertOk()
            ->assertDontSee(__('Importación de datos maestros'))
            ->assertDontSee(__('Importar productos'));
        $this->get(route('admin.catalogs.products.import.template', 'csv'))->assertForbidden();
        $this->post(route('admin.catalogs.products.import'), [
            'file' => UploadedFile::fake()->createWithContent('products.csv', 'code,name'),
        ])->assertForbidden();
    }

    public function test_master_data_import_is_a_menu_item_and_not_a_product_list_button(): void
    {
        $this->get(route('admin.catalogs.products.index'))
            ->assertOk()
            ->assertSee(__('Importación de datos maestros'))
            ->assertSee(route('admin.catalogs.products.import.form'))
            ->assertDontSee(__('Importar productos'))
            ->assertDontSee(__('Descargar plantilla CSV'))
            ->assertDontSee(__('Descargar plantilla Excel'));

        $importPage = $this->get(route('admin.catalogs.products.import.form'));
        $importPage
            ->assertOk()
            ->assertSee(__('Importación de datos maestros'))
            ->assertSee('page-heading-slot')
            ->assertSee(__('Volver a productos'))
            ->assertSee(
                '<a class="nav-link nav-link-active" href="'.route('admin.catalogs.products.import.form').'">',
                false,
            )
            ->assertDontSee(
                '<a class="nav-link nav-link-active" href="'.route('admin.catalogs.products.index').'">',
                false,
            );
    }

    public function test_file_picker_labels_follow_the_active_application_language(): void
    {
        $expected = [
            'en' => ['Choose file', 'No file selected', 'Master data import'],
            'fr' => ['Choisir un fichier', 'Aucun fichier sélectionné', 'Importation des données de référence'],
            'pt_BR' => ['Selecionar arquivo', 'Nenhum arquivo selecionado', 'Importação de dados mestres'],
        ];
        $originalLocale = app()->getLocale();
        $originalLanguage = $this->company->language ?? 'es';

        try {
            foreach ($expected as $locale => [$chooseFile, $noFile, $importLabel]) {
                $this->company->update(['language' => $locale]);

                $this->get(route('admin.catalogs.products.import.form'))
                    ->assertOk()
                    ->assertSee($chooseFile)
                    ->assertSee($noFile)
                    ->assertSee($importLabel);
            }
        } finally {
            $this->company->update(['language' => $originalLanguage]);
            app()->setLocale($originalLocale);
        }
    }

    public function test_import_rejects_a_file_with_unexpected_headers_before_creating_products(): void
    {
        $response = $this->from(route('admin.catalogs.products.import.form'))
            ->post(route('admin.catalogs.products.import'), [
                'file' => UploadedFile::fake()->createWithContent('products.csv', "code,name\nX-1,Product\n"),
            ]);

        $response->assertRedirect(route('admin.catalogs.products.import.form'))
            ->assertSessionHasErrors('file');
        $this->assertDatabaseMissing('products', ['code' => 'X-1']);
    }
}
