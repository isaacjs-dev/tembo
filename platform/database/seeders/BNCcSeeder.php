<?php

namespace Database\Seeders;

use App\Models\BNCcComponentSchema;
use App\Models\BNCcNode;
use App\Models\Discipline;
use Illuminate\Database\Seeder;

class BNCcSeeder extends Seeder
{
    public function run(): void
    {
        $math = Discipline::firstOrCreate(['name' => 'Matemática']);
        $lang = Discipline::firstOrCreate(['name' => 'Língua Portuguesa']);
        $hist = Discipline::firstOrCreate(['name' => 'História']);
        $cien = Discipline::firstOrCreate(['name' => 'Ciências']);

        // --- MATEMÁTICA (EF FINAIS) ---
        $mathSchema = [
            ['level' => 1, 'type' => 'thematic_unit', 'label' => 'Unidade Temática'],
            ['level' => 2, 'type' => 'knowledge_object', 'label' => 'Objeto de Conhecimento'],
            ['level' => 3, 'type' => 'skill', 'label' => 'Habilidade'],
        ];

        BNCcComponentSchema::firstOrCreate([
            'discipline_id' => $math->id,
            'stage' => 'ef_finais',
        ], [
            'schema_json' => $mathSchema,
        ]);

        // Unidade Temática
        $utMath = BNCcNode::firstOrCreate([
            'discipline_id' => $math->id,
            'stage' => 'ef_finais',
            'type' => 'thematic_unit',
            'title' => 'Números',
        ]);

        // Objeto de Conhecimento
        $objMath = BNCcNode::firstOrCreate([
            'discipline_id' => $math->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'knowledge_object',
            'title' => 'Sistema de numeração decimal: características, leitura, escrita e comparação de números naturais e de números racionais representados na forma decimal',
            'parent_id' => $utMath->id,
        ]);

        // Habilidade
        BNCcNode::firstOrCreate([
            'discipline_id' => $math->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF06MA01',
            'title' => 'Comparar, ordenar, ler e escrever números naturais e números racionais cuja representação decimal é finita, fazendo uso da reta numérica.',
            'parent_id' => $objMath->id,
        ]);

        BNCcNode::firstOrCreate([
            'discipline_id' => $math->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF06MA02',
            'title' => 'Reconhecer o sistema de numeração decimal, como o que prevaleceu no mundo ocidental, e destacar semelhanças e diferenças com outros sistemas, de modo a sistematizar suas principais características (base, valor posicional e função do zero).',
            'parent_id' => $objMath->id,
        ]);

        // --- LÍNGUA PORTUGUESA (EF FINAIS) ---
        $langSchema = [
            ['level' => 1, 'type' => 'field_of_action', 'label' => 'Campo de Atuação'],
            ['level' => 2, 'type' => 'practice_axis', 'label' => 'Eixo/Prática de Linguagem'],
            ['level' => 3, 'type' => 'knowledge_object', 'label' => 'Objeto de Conhecimento'],
            ['level' => 4, 'type' => 'skill', 'label' => 'Habilidade'],
        ];

        BNCcComponentSchema::firstOrCreate([
            'discipline_id' => $lang->id,
            'stage' => 'ef_finais',
        ], [
            'schema_json' => $langSchema,
        ]);

        $fieldAct = BNCcNode::firstOrCreate([
            'discipline_id' => $lang->id,
            'stage' => 'ef_finais',
            'type' => 'field_of_action',
            'title' => 'Campo jornalístico-midiático',
        ]);

        $practAxis = BNCcNode::firstOrCreate([
            'discipline_id' => $lang->id,
            'stage' => 'ef_finais',
            'type' => 'practice_axis',
            'title' => 'Leitura',
            'parent_id' => $fieldAct->id,
        ]);

        $objLang = BNCcNode::firstOrCreate([
            'discipline_id' => $lang->id,
            'stage' => 'ef_finais',
            'type' => 'knowledge_object',
            'title' => 'Reconstrução das condições de produção e recepção de textos',
            'parent_id' => $practAxis->id,
        ]);

        BNCcNode::firstOrCreate([
            'discipline_id' => $lang->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF67LP01',
            'title' => 'Analisar a estrutura e funcionamento de reportagens.',
            'parent_id' => $objLang->id,
        ]);

        // --- HISTÓRIA (EF FINAIS - 6º) ---
        $utHist = BNCcNode::firstOrCreate([
            'discipline_id' => $hist->id,
            'stage' => 'ef_finais',
            'type' => 'thematic_unit',
            'title' => 'História: tempo, espaço e formas de registros',
        ]);
        $objHist = BNCcNode::firstOrCreate([
            'discipline_id' => $hist->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'knowledge_object',
            'title' => 'As origens da humanidade',
            'parent_id' => $utHist->id,
        ]);
        BNCcNode::firstOrCreate([
            'discipline_id' => $hist->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF06HI01',
            'title' => 'Identificar diferentes formas de compreensão da noção de tempo.',
            'parent_id' => $objHist->id,
        ]);
        BNCcNode::firstOrCreate([
            'discipline_id' => $hist->id,
            'stage' => 'ef_finais',
            'grade' => '6',
            'type' => 'skill',
            'code' => 'EF06HI02',
            'title' => 'Identificar a gênese da produção do saber histórico.',
            'parent_id' => $objHist->id,
        ]);

        // --- CIÊNCIAS (EF FINAIS - 7º) ---
        $utCien = BNCcNode::firstOrCreate([
            'discipline_id' => $cien->id,
            'stage' => 'ef_finais',
            'type' => 'thematic_unit',
            'title' => 'Vida e Evolução',
        ]);
        $objCien = BNCcNode::firstOrCreate([
            'discipline_id' => $cien->id,
            'stage' => 'ef_finais',
            'grade' => '7',
            'type' => 'knowledge_object',
            'title' => 'Cadeias alimentares',
            'parent_id' => $utCien->id,
        ]);
        BNCcNode::firstOrCreate([
            'discipline_id' => $cien->id,
            'stage' => 'ef_finais',
            'grade' => '7',
            'type' => 'skill',
            'code' => 'EF07CI01',
            'title' => 'Discutir a aplicação do conhecimento para prever o fluxo de energia.',
            'parent_id' => $objCien->id,
        ]);
        BNCcNode::firstOrCreate([
            'discipline_id' => $cien->id,
            'stage' => 'ef_finais',
            'grade' => '7',
            'type' => 'skill',
            'code' => 'EF07CI02',
            'title' => 'Diferenciar organismos produtores, consumidores e decompositores.',
            'parent_id' => $objCien->id,
        ]);
    }
}
