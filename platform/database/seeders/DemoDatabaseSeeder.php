<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LogicException;

class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException(
                'A base demonstrativa só pode ser gerada em ambiente local ou testing.'
            );
        }

        $this->command?->info('Gerando base demonstrativa completa e isolada...');

        $this->call([
            UserSeeder::class,
            CurriculumSeeder::class,
            BNCcSeeder::class,
            CustomSkillSeeder::class,
            QuestionSeeder::class,
            DemoScenarioSeeder::class,
            PedagogicalDemoSeeder::class,
        ]);
    }
}
