<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'from_date' => ['sometimes', 'required_with:to_date', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'required_with:from_date', 'date_format:Y-m-d', 'after_or_equal:from_date'],
        ]);
        $rangeCount = null;

        if (isset($validated['from_date'], $validated['to_date'])) {
            $rangeCount = $this->rangeQuery($validated['from_date'], $validated['to_date'])->count();
        }

        return view('admin.audit.index', [
            'logs' => AuditLog::query()
                ->with('user')
                ->latest('created_at')
                ->paginate(30),
            'rangeCount' => $rangeCount,
            'expiredCount' => AuditLog::query()
                ->where('created_at', '<', now()->subDays(30))
                ->count(),
        ]);
    }

    public function destroyRange(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:from_date'],
            'confirm_deletion' => ['required', 'accepted'],
        ]);

        $deleted = DB::transaction(function () use ($request, $validated): int {
            $query = $this->rangeQuery($validated['from_date'], $validated['to_date']);
            $count = $query->delete();

            $this->auditCleanup($request, 'range', $count, [
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
            ]);

            return $count;
        });

        return redirect()->route('admin.audit.index', [
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
        ])->with('status', __('Se eliminaron :count registros del rango seleccionado.', ['count' => $deleted]));
    }

    public function destroyExpired(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm_deletion' => ['required', 'accepted'],
        ]);

        $cutoff = now()->subDays(30);
        $deleted = DB::transaction(function () use ($request, $cutoff): int {
            $query = AuditLog::query()->where('created_at', '<', $cutoff);
            $count = $query->delete();

            $this->auditCleanup($request, 'older_than_30_days', $count, [
                'cutoff' => $cutoff->toDateTimeString(),
            ]);

            return $count;
        });

        return redirect()->route('admin.audit.index')
            ->with('status', __('Se eliminaron :count registros con más de 30 días de antigüedad.', ['count' => $deleted]));
    }

    private function rangeQuery(string $fromDate, string $toDate): Builder
    {
        return AuditLog::query()
            ->where('created_at', '>=', Carbon::parse($fromDate)->startOfDay())
            ->where('created_at', '<', Carbon::parse($toDate)->addDay()->startOfDay());
    }

    private function auditCleanup(Request $request, string $scope, int $count, array $criteria): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'audit_cleanup',
            'entity_type' => 'audit_logs',
            'changes' => [
                'scope' => $scope,
                'deleted_count' => $count,
                ...$criteria,
            ],
            'ip_address' => $request->ip(),
        ]);
    }
}
