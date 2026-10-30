<?php

namespace App\Http\Controllers\Admin;

use App\Models\Supplier;
use App\Models\SupplierEvaluation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierEvaluationController extends OrganizationAdminController
{
    public function index(): View
    {
        $this->company();

        return view('admin.catalogs.supplier-evaluations.index', [
            'suppliers' => Supplier::query()
                ->where('is_active', true)
                ->with('latestEvaluation.evaluatedBy')
                ->orderBy('legal_name')
                ->paginate(20),
            'evaluations' => SupplierEvaluation::query()
                ->with(['supplier', 'evaluatedBy'])
                ->latest()
                ->paginate(20, ['*'], 'history_page'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = $this->company();
        $validated = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'distance_score' => ['required', 'integer', 'between:1,5'],
            'delivery_time_score' => ['required', 'integer', 'between:1,5'],
            'fulfillment_score' => ['required', 'integer', 'between:1,5'],
        ]);

        $evaluation = DB::transaction(function () use ($request, $company, $validated) {
            $supplier = Supplier::query()->whereKey($validated['supplier_id'])->where('is_active', true)->firstOrFail();
            $average = number_format(array_sum([
                $validated['distance_score'],
                $validated['delivery_time_score'],
                $validated['fulfillment_score'],
            ]) / 3, 2, '.', '');
            $evaluation = SupplierEvaluation::create([
                ...$validated,
                'evaluated_by_user_id' => $request->user()->id,
                'average_score' => $average,
            ]);
            $this->audit(
                $request,
                $company,
                'evaluated',
                'supplier_evaluation',
                $evaluation,
                null,
                [
                    ...$evaluation->only([
                        'supplier_id', 'distance_score', 'delivery_time_score', 'fulfillment_score', 'average_score',
                    ]),
                    'supplier_name' => $supplier->legal_name,
                ],
            );

            return $evaluation;
        });

        return redirect()->route('admin.catalogs.supplier-evaluations.index')
            ->with('status', __('Calificación de proveedor guardada. Promedio de la evaluación: :score.', [
                'score' => $evaluation->average_score,
            ]));
    }
}
