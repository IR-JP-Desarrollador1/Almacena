<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\View\View;

class DatabaseUpdateController extends Controller
{
    public function index(Migrator $migrator): View
    {
        return view('admin.database-update.index', [
            'pendingMigrations' => $this->pendingMigrations($migrator),
        ]);
    }

    public function run(Request $request, Migrator $migrator): RedirectResponse
    {
        $request->validate([
            'confirm_update' => ['required', 'accepted'],
        ]);

        $pendingBefore = $this->pendingMigrations($migrator);

        if ($pendingBefore === []) {
            return redirect()->route('admin.database-update.index')
                ->with('status', __('No hay migraciones pendientes para aplicar.'));
        }

        $exitCode = Artisan::call('migrate', [
            '--force' => true,
            '--isolated' => true,
        ]);
        $output = trim(Artisan::output());
        $pendingAfter = $this->pendingMigrations($migrator);

        if ($exitCode !== 0 || $pendingAfter !== []) {
            return redirect()->route('admin.database-update.index')
                ->withErrors([
                    'database_update' => __('No se completó la actualización. Revisa el resultado y vuelve a intentarlo si corresponde.'),
                ])
                ->with('migration_output', $output);
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'migrations_applied',
            'entity_type' => 'database',
            'changes' => [
                'migrations' => array_values(array_diff($pendingBefore, $pendingAfter)),
            ],
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.database-update.index')
            ->with('status', __('La base de datos se actualizó correctamente.'))
            ->with('migration_output', $output);
    }

    /**
     * @return list<string>
     */
    private function pendingMigrations(Migrator $migrator): array
    {
        $paths = array_merge($migrator->paths(), [database_path('migrations')]);
        $files = $migrator->getMigrationFiles($paths);
        $ran = $migrator->repositoryExists()
            ? $migrator->getRepository()->getRan()
            : [];

        return array_values(array_diff(array_keys($files), $ran));
    }
}
