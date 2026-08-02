<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PlanSeeder::class,
            AnswerSheetTypeSeeder::class,
            ScanModeSeeder::class,
            OmrTemplateSeeder::class,
        ]);

        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn(
                'Dados demonstrativos ignorados: o ambiente atual não é local/testing.'
            );

            return;
        }

        $this->call(DemoDatabaseSeeder::class);
    }
}
