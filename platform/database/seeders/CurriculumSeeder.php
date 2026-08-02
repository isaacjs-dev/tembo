<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurriculumSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Pegar a organização padrao (se houver, senão pegar a 1) ou ignorar se nao for necessario
        // As knowledge areas e disciplines tem 'organization_id' na estrutura atual.
        // Vamos logar para a primeira organização existente ou criar uma mock.
        $orgId = DB::table('organizations')
            ->where('subdomain', 'escola-modelo')
            ->value('id');
        if (! $orgId) {
            $orgId = DB::table('organizations')->insertGetId([
                'name' => 'Organização Padrão',
                'slug' => 'org-padrao',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 2. Níveis de Dificuldade
        $levels = ['Muito Fácil', 'Fácil', 'Médio', 'Difícil', 'Muito Difícil'];
        foreach ($levels as $l) {
            DB::table('difficulty_levels')->updateOrInsert(['name' => $l], ['name' => $l]);
        }

        // 3. Séries/Ano (1º ao 9º EF, 1º ao 3º EM)
        $years = [
            '1º ano EF',
            '2º ano EF',
            '3º ano EF',
            '4º ano EF',
            '5º ano EF',
            '6º ano EF',
            '7º ano EF',
            '8º ano EF',
            '9º ano EF',
            '1º ano EM',
            '2º ano EM',
            '3º ano EM',
        ];
        foreach ($years as $y) {
            DB::table('school_years')->updateOrInsert(['name' => $y], ['name' => $y]);
        }

        // 4. Áreas de Conhecimento e suas Disciplinas
        $areasAndDisciplines = [
            'Linguagens' => ['Língua Portuguesa', 'Língua Inglesa', 'Arte', 'Educação Física'],
            'Matemática' => ['Matemática'],
            'Ciências da Natureza' => ['Ciências', 'Biologia', 'Física', 'Química'],
            'Ciências Humanas' => ['Geografia', 'História', 'Filosofia', 'Sociologia'],
            'Ensino Religioso' => ['Ensino Religioso'],
        ];

        foreach ($areasAndDisciplines as $areaName => $disciplines) {
            $areaId = DB::table('knowledge_areas')->where('name', $areaName)->where('organization_id', $orgId)->value('id');
            if (! $areaId) {
                $areaId = DB::table('knowledge_areas')->insertGetId([
                    'organization_id' => $orgId,
                    'name' => $areaName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            foreach ($disciplines as $discName) {
                // Insere ou pega a disciplina e seta a knowledge_area_id
                $discId = DB::table('disciplines')->where('name', $discName)->where('organization_id', $orgId)->value('id');
                if ($discId) {
                    DB::table('disciplines')->where('id', $discId)->update(['knowledge_area_id' => $areaId]);
                } else {
                    $discId = DB::table('disciplines')->insertGetId([
                        'organization_id' => $orgId,
                        'knowledge_area_id' => $areaId,
                        'name' => $discName,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // 5. Unidades Temáticas e Objetos de Conhecimento (Exemplos ilustrativos solicitados)
                if ($discName === 'Ciências') {
                    $this->seedThematicUnits($discId, 'Matéria e Energia', ['Propriedades da matéria', 'Transformações químicas']);
                    $this->seedThematicUnits($discId, 'Vida e Evolução', ['Seres vivos no ambiente', 'Cadeias alimentares']);
                }
                if ($discName === 'Matemática') {
                    $this->seedThematicUnits($discId, 'Números', ['Sistemas de numeração', 'Frações', 'Operações fundamentais']);
                    $this->seedThematicUnits($discId, 'Álgebra', ['Equações de 1º Grau', 'Expressões algébricas']);
                }
            }
        }
    }

    private function seedThematicUnits($discId, $unitName, $objects)
    {
        $unitId = DB::table('thematic_units')->where('name', $unitName)->where('discipline_id', $discId)->value('id');
        if (! $unitId) {
            $unitId = DB::table('thematic_units')->insertGetId([
                'discipline_id' => $discId,
                'name' => $unitName,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        foreach ($objects as $objName) {
            $objId = DB::table('knowledge_objects')->where('name', $objName)->where('thematic_unit_id', $unitId)->value('id');
            if (! $objId) {
                DB::table('knowledge_objects')->insert([
                    'thematic_unit_id' => $unitId,
                    'name' => $objName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
