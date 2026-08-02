<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\PlanLimit;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed plans + plan_limits + plan_features.
     * Usa updateOrCreate para ser idempotente.
     */
    public function run(): void
    {
        $plans = [
            [
                'slug' => 'start',
                'name' => 'Start',
                'target_audience' => 'both',
                'price' => 0,
                'original_price' => 0,
                'is_visible' => true,
                'is_most_popular' => false,
                'sort_order' => 1,
                'status' => 'active',
                'tier_level' => 0,
                'limits' => [
                    'max_students' => 30,
                    'max_classes' => 2,
                    'max_exams' => 5,
                    'max_teachers' => 1,
                ],
                'features' => [
                    'export_pdf' => false,
                    'omr' => false,
                    'sharing' => false,
                    'certificates' => false,
                ],
            ],
            [
                'slug' => 'pro',
                'name' => 'Profissional',
                'target_audience' => 'both',
                'price' => 49.90,
                'original_price' => 49.90,
                'promotional_price' => 39.90,
                'promo_starts_at' => now(),
                'promo_ends_at' => now()->addMonths(3),
                'is_visible' => true,
                'is_most_popular' => true,
                'sort_order' => 2,
                'status' => 'active',
                'tier_level' => 1,
                'limits' => [
                    'max_students' => 200,
                    'max_classes' => 20,
                    'max_exams' => 50,
                    'max_teachers' => 10,
                ],
                'features' => [
                    'export_pdf' => true,
                    'omr' => true,
                    'sharing' => true,
                    'certificates' => false,
                ],
            ],
            [
                'slug' => 'enterprise',
                'name' => 'Enterprise',
                'target_audience' => 'institution',
                'price' => 149.90,
                'original_price' => 149.90,
                'is_visible' => true,
                'is_most_popular' => false,
                'sort_order' => 3,
                'status' => 'active',
                'tier_level' => 2,
                'limits' => [
                    'max_students' => null,
                    'max_classes' => null,
                    'max_exams' => null,
                    'max_teachers' => null,
                ],
                'features' => [
                    'export_pdf' => true,
                    'omr' => true,
                    'sharing' => true,
                    'certificates' => true,
                ],
            ],
        ];

        foreach ($plans as $data) {
            $limitsData = $data['limits'];
            $featuresData = $data['features'];

            // Mantém JSON legado para retrocompatibilidade
            $data['limits'] = $limitsData;
            $data['features'] = array_keys(array_filter($featuresData));

            $plan = Plan::updateOrCreate(
                ['slug' => $data['slug']],
                $data
            );

            // Popular tabelas normalizadas
            foreach ($limitsData as $key => $value) {
                PlanLimit::updateOrCreate(
                    ['plan_id' => $plan->id, 'resource_key' => $key],
                    ['limit_value' => $value]
                );
            }

            foreach ($featuresData as $key => $enabled) {
                PlanFeature::updateOrCreate(
                    ['plan_id' => $plan->id, 'feature_key' => $key],
                    ['enabled' => (bool) $enabled]
                );
            }
        }
    }
}
