<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();

        return view('admin.plans.index', compact('plans'));
    }

    public function create()
    {
        return view('admin.plans.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target_audience' => 'required|in:teacher,institution,both',
            'original_price' => 'required|numeric|min:0',
            'promotional_price' => 'nullable|numeric|min:0|lt:original_price',
            'promo_starts_at' => 'nullable|date|required_with:promotional_price',
            'promo_ends_at' => 'nullable|date|after:promo_starts_at|required_with:promotional_price',
            'is_visible' => 'nullable|boolean',
            'is_most_popular' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'tier_level' => 'nullable|integer|min:0',
            // Limites
            'limits.max_students' => 'nullable|integer|min:0',
            'limits.max_teachers' => 'nullable|integer|min:0',
            'limits.max_classes' => 'nullable|integer|min:0',
            'limits.max_exams' => 'nullable|integer|min:0',
            'limits.monthly_omr_scans' => 'nullable|integer|min:0',
            'limits.monthly_exam_publications' => 'nullable|integer|min:0',
            'limits.monthly_questions_created' => 'nullable|integer|min:0',
            // Features
            'features.export_pdf' => 'nullable|boolean',
            'features.omr' => 'nullable|boolean',
            'features.sharing' => 'nullable|boolean',
            'features.certificates' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($validated, $request) {
            $plan = Plan::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'target_audience' => $validated['target_audience'],
                'price' => $validated['original_price'],
                'original_price' => $validated['original_price'],
                'promotional_price' => $validated['promotional_price'] ?? null,
                'promo_starts_at' => $validated['promo_starts_at'] ?? null,
                'promo_ends_at' => $validated['promo_ends_at'] ?? null,
                'is_visible' => $request->boolean('is_visible'),
                'is_most_popular' => $request->boolean('is_most_popular'),
                'sort_order' => $validated['sort_order'] ?? 0,
                'status' => 'active',
                'tier_level' => $validated['tier_level'] ?? 0,
            ]);

            $this->syncLimitsAndFeatures($plan, $request);
        });

        return redirect()->route('admin.plans.index')->with('status', 'Plano criado com sucesso!');
    }

    public function edit(string $id)
    {
        $plan = Plan::with(['planLimits', 'planFeatures'])->findOrFail($id);

        return view('admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, string $id)
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'target_audience' => 'required|in:teacher,institution,both',
            'original_price' => 'required|numeric|min:0',
            'promotional_price' => 'nullable|numeric|min:0',
            'promo_starts_at' => 'nullable|date|required_with:promotional_price',
            'promo_ends_at' => 'nullable|date|after:promo_starts_at|required_with:promotional_price',
            'is_visible' => 'nullable|boolean',
            'is_most_popular' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'tier_level' => 'nullable|integer|min:0',
            'limits.max_students' => 'nullable|integer|min:0',
            'limits.max_teachers' => 'nullable|integer|min:0',
            'limits.max_classes' => 'nullable|integer|min:0',
            'limits.max_exams' => 'nullable|integer|min:0',
            'limits.monthly_omr_scans' => 'nullable|integer|min:0',
            'limits.monthly_exam_publications' => 'nullable|integer|min:0',
            'limits.monthly_questions_created' => 'nullable|integer|min:0',
            'features.export_pdf' => 'nullable|boolean',
            'features.omr' => 'nullable|boolean',
            'features.sharing' => 'nullable|boolean',
            'features.certificates' => 'nullable|boolean',
        ]);

        DB::transaction(function () use ($plan, $validated, $request) {
            $plan->update([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'target_audience' => $validated['target_audience'],
                'price' => $validated['original_price'],
                'original_price' => $validated['original_price'],
                'promotional_price' => $validated['promotional_price'] ?? null,
                'promo_starts_at' => $validated['promo_starts_at'] ?? null,
                'promo_ends_at' => $validated['promo_ends_at'] ?? null,
                'is_visible' => $request->boolean('is_visible'),
                'is_most_popular' => $request->boolean('is_most_popular'),
                'sort_order' => $validated['sort_order'] ?? 0,
                'tier_level' => $validated['tier_level'] ?? 0,
            ]);

            $this->syncLimitsAndFeatures($plan, $request);
        });

        return redirect()->route('admin.plans.index')->with('status', 'Plano atualizado com sucesso!');
    }

    public function destroy(string $id)
    {
        $plan = Plan::findOrFail($id);

        if ($plan->subscriptions()->where('status', 'active')->exists()) {
            return redirect()->route('admin.plans.index')
                ->with('error', 'Não é possível excluir plano com assinaturas ativas.');
        }

        $plan->delete();

        return redirect()->route('admin.plans.index')->with('status', 'Plano excluído com sucesso!');
    }

    /**
     * Sincroniza plan_limits e plan_features normalizados.
     */
    private function syncLimitsAndFeatures(Plan $plan, Request $request): void
    {
        $limitKeys = [
            'max_students', 'max_teachers', 'max_classes', 'max_exams',
            'monthly_omr_scans', 'monthly_exam_publications', 'monthly_questions_created',
        ];
        foreach ($limitKeys as $key) {
            $value = $request->input("limits.$key");
            PlanLimit::updateOrCreate(
                ['plan_id' => $plan->id, 'resource_key' => $key],
                ['limit_value' => $value !== '' && $value !== null ? (int) $value : null]
            );
        }

        $featureKeys = ['export_pdf', 'omr', 'sharing', 'certificates'];
        foreach ($featureKeys as $key) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature_key' => $key],
                ['enabled' => $request->boolean("features.$key")]
            );
        }
    }
}
