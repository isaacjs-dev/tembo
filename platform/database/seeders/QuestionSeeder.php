<?php

namespace Database\Seeders;

use App\Models\BNCcNode;
use App\Models\CustomSkill;
use App\Models\Discipline;
use App\Models\KnowledgeArea;
use App\Models\Organization;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class QuestionSeeder extends Seeder
{
    private function getRandomContent($disciplineName, $type)
    {
        $pool = [
            'Matemática' => [
                'multiple_choice' => [
                    ['s' => 'Calcule o valor da expressão numérica: 5 + 3 × 2.', 'o' => ['16', '11', '10', '15'], 'c' => 1],
                    ['s' => 'Se um carro viaja a uma velocidade constante de 80 km/h, qual distância ele percorrerá em 2 horas e meia?', 'o' => ['160 km', '180 km', '200 km', '220 km'], 'c' => 2],
                    ['s' => 'Qual é a área de um retângulo cuja base mede 12 cm e a altura mede 5 cm?', 'o' => ['17 cm²', '30 cm²', '60 cm²', '120 cm²'], 'c' => 2],
                    ['s' => 'Resolva a equação: 2x - 4 = 10.', 'o' => ['x = 3', 'x = 5', 'x = 7', 'x = 14'], 'c' => 2],
                    ['s' => 'Uma TV custava R$ 2.000, mas sofreu um acréscimo de 10%. Qual o novo valor da TV?', 'o' => ['R$ 2.100', 'R$ 2.200', 'R$ 2.010', 'R$ 2.500'], 'c' => 1],
                ],
                'true_false' => [
                    ['s' => 'O número primo é aquele que possui apenas dois divisores: o 1 e ele mesmo.', 'c' => 0],
                    ['s' => 'Todo triângulo isósceles é também equilátero.', 'c' => 1],
                    ['s' => 'Na função f(x) = 2x + 3, o coeficiente linear é 2.', 'c' => 1],
                ],
            ],
            'Língua Portuguesa' => [
                'multiple_choice' => [
                    ['s' => 'Assinale a alternativa em que há erro de concordância verbal:', 'o' => ['Fazem dez anos que não o vejo.', 'Faz dez anos que não o vejo.', 'Havia muitas pessoas na sala.', 'Se houvesse mais tempo, faríamos o trabalho.'], 'c' => 0],
                    ['s' => 'Na frase "A menina comprou um livro brilhante", a palavra "brilhante" exerce a função morfológica de:', 'o' => ['Substantivo', 'Adjetivo', 'Advérbio', 'Pronome'], 'c' => 1],
                    ['s' => 'Qual figura de linguagem está presente em: "Chorou rios de lágrimas"?', 'o' => ['Metáfora', 'Eufemismo', 'Hipérbole', 'Ironia'], 'c' => 2],
                    ['s' => 'Em qual das opções a regra de acentuação foi aplicada corretamente?', 'o' => ['Pássaro, xícara, tóxico.', 'Hífen, item, polo.', 'Ruim, bambu, juíz.', 'Pêlo, vôo, idéia.'], 'c' => 0],
                ],
                'true_false' => [
                    ['s' => 'A palavra "exceção" é grafada corretamente.', 'c' => 0],
                    ['s' => 'Oração subordinada substantiva exerce função de pronome na frase.', 'c' => 1],
                ],
            ],
            'Ciências' => [
                'multiple_choice' => [
                    ['s' => 'Qual é a principal função dos glóbulos vermelhos no sangue?', 'o' => ['Defender o organismo.', 'Transportar oxigênio.', 'Coagulação sanguínea.', 'Produzir anticorpos.'], 'c' => 1],
                    ['s' => 'A fotossíntese é um processo realizado por:', 'o' => ['Animais e plantas.', 'Estritamente por fungos.', 'Plantas, algas e algumas bactérias.', 'Vírus e protozoários.'], 'c' => 2],
                    ['s' => 'A água passa do estado líquido para o gasoso através da:', 'o' => ['Fusão', 'Solidificação', 'Condensação', 'Vaporização'], 'c' => 3],
                ],
                'true_false' => [
                    ['s' => 'A Terra realiza o movimento de translação ao redor do sol, o que define as estações do ano.', 'c' => 0],
                    ['s' => 'Organismos produtores produzem seu próprio alimento apenas na ausência de luz.', 'c' => 1],
                ],
            ],
            'História' => [
                'multiple_choice' => [
                    ['s' => 'Qual país foi responsável pelo descobrimento oficial do Brasil em 1500?', 'o' => ['Espanha', 'Inglaterra', 'Portugal', 'França'], 'c' => 2],
                    ['s' => 'A Revolução Francesa (1789) teve como lema principal:', 'o' => ['Ordem e Progresso.', 'Liberdade, Igualdade e Fraternidade.', 'Paz, Terra e Pão.', 'Deus, Pátria e Família.'], 'c' => 1],
                    ['s' => 'A Guerra Fria foi o conflito ideológico entre:', 'o' => ['EUA e Alemanha.', 'EUA e União Soviética.', 'Inglaterra e França.', 'Eixo e Aliados.'], 'c' => 1],
                ],
                'true_false' => [
                    ['s' => 'A escravidão no Brasil foi oficialmente abolida através da Lei Áurea em 1888.', 'c' => 0],
                    ['s' => 'Getúlio Vargas foi o primeiro presidente eleito de forma direta no Brasil Colônia.', 'c' => 1],
                ],
            ],
        ];

        $fallback = [
            'multiple_choice' => [
                ['s' => 'Qual o principal conceito abordado neste tópico da disciplina?', 'o' => ['Apenas a leitura superficial é necessária.', 'O conceito central é a base teórica discutida.', 'Não há conceito principal definido.', 'Baseia-se unicamente em memorização.'], 'c' => 1],
                ['s' => 'Dentre as opções abaixo, qual representa a regra essencial desta matéria?', 'o' => ['O estudo prático exclui a teoria.', 'A teoria deve andar sempre acompanhada da prática.', 'Nenhuma regra se aplica invariavelmente.', 'As variáveis sempre anulam as constantes.'], 'c' => 1],
            ],
            'true_false' => [
                ['s' => 'A teoria apresentada neste módulo é coerente com a literatura clássica.', 'c' => 0],
                ['s' => 'A dedicação contínua aos estudos é um fator irrelevante para o desenvolvimento cognitivo.', 'c' => 1],
            ],
        ];

        $source = isset($pool[$disciplineName]) ? $pool[$disciplineName] : $fallback;
        $typeSource = isset($source[$type]) ? $source[$type] : $fallback[$type];
        $item = $typeSource[array_rand($typeSource)];

        if ($type === 'multiple_choice') {
            return [
                'statement' => $item['s'],
                'options' => $item['o'],
                'correct_option' => $item['c'],
            ];
        }

        // true_false: 2 alternativas A=Verdadeiro / B=Falso + gabarito (0=V, 1=F; default 0).
        return [
            'statement' => $item['s'],
            'options' => ['Verdadeiro', 'Falso'],
            'correct_option' => $item['c'] ?? 0,
        ];
    }

    public function run(): void
    {
        $org = Organization::where('subdomain', 'escola-modelo')->first();
        if (! $org) {
            $this->command->error('Organization not found.');

            return;
        }

        $owner = User::where('organization_id', $org->id)
            ->where('type', 'teacher')
            ->first() ?? User::first();

        $disciplines = Discipline::all();
        $areas = KnowledgeArea::all();
        $customSkills = CustomSkill::where('organization_id', $org->id)->pluck('id')->toArray();
        $bnccSkills = BNCcNode::where('type', 'skill')->get();

        if ($disciplines->isEmpty() || $areas->isEmpty()) {
            $this->command->error('Disciplines or Knowledge Areas are missing.');

            return;
        }

        $stages = [
            'ef_iniciais' => ['1', '2', '3', '4', '5'],
            'ef_finais' => ['6', '7', '8', '9'],
            'em' => ['1', '2', '3'],
        ];

        $totalQuestions = 2000;
        $existingQuestions = Question::where('organization_id', $org->id)->count();
        $questionsToCreate = max(0, $totalQuestions - $existingQuestions);
        $batchSize = 250;

        if ($questionsToCreate === 0) {
            $this->command->info(
                "Banco de questões já possui {$existingQuestions} registros demonstrativos."
            );

            return;
        }

        mt_srand(20260730);
        $this->command->info("Gerando {$questionsToCreate} questões demonstrativas...");

        DB::beginTransaction();

        try {
            for ($i = 0; $i < $questionsToCreate; $i++) {
                // Seleção de Dificuldade
                $randLevel = rand(1, 100);
                if ($randLevel <= 15) {
                    $level = 'very_easy';
                } elseif ($randLevel <= 50) {
                    $level = 'easy';
                } // 35%
                elseif ($randLevel <= 85) {
                    $level = 'medium';
                } // 35%
                elseif ($randLevel <= 95) {
                    $level = 'hard';
                } // 10%
                else {
                    $level = 'very_hard';
                } // 5%

                // Seleção de Etapa/Série
                $randStage = rand(1, 100);
                if ($randStage <= 40) {
                    $stage = 'ef_iniciais';
                } elseif ($randStage <= 80) {
                    $stage = 'ef_finais';
                } else {
                    $stage = 'em';
                }
                $grade = $stages[$stage][array_rand($stages[$stage])];

                // Disciplina/Área
                $discipline = $disciplines->random();
                $area = $areas->random(); // Idealmente a disciplina teria relation com area, mas pra seed tá ok rand.

                // Tipo
                $randType = rand(1, 100);
                $type = ($randType <= 80) ? 'multiple_choice' : 'true_false';

                $content = $this->getRandomContent($discipline->name, $type);

                $question = Question::create([
                    'organization_id' => $org->id,
                    'owner_id' => $owner->id,
                    'type' => $type,
                    'visibility_scope' => 'org_public',
                    'knowledge_area_id' => $area->id,
                    'discipline_id' => $discipline->id,
                    'level' => $level,
                    'stage' => $stage,
                    'grade' => $grade,
                    'content' => $content,
                ]);

                // Vinculos Custom Skills (60% sim)
                if (rand(1, 100) <= 60 && ! empty($customSkills)) {
                    $numSkills = rand(1, 3);
                    $selectedSkills = (array) array_rand(array_flip($customSkills), min($numSkills, count($customSkills)));
                    $question->customSkills()->attach($selectedSkills);
                }

                // Vinculos BNCC (50% sim)
                if (rand(1, 100) <= 50 && $bnccSkills->isNotEmpty()) {
                    // Tenta achar uma BNCC compativel com a disciplina e etapa.
                    // Se nao achar, pega 1 aleatoria so pra testar a pivot (no BD de teste pode faltar cruzamento exato)
                    $compatibleBnccs = $bnccSkills->where('discipline_id', $discipline->id)->where('stage', $stage);

                    if ($compatibleBnccs->isNotEmpty()) {
                        $numBncc = (rand(1, 100) <= 20) ? 2 : 1; // 10% do total com 2 skills = 20% dos que tem bncc.
                        $selectedBnccs = $compatibleBnccs->random(min($numBncc, $compatibleBnccs->count()))->pluck('id');
                        $question->bnccSkills()->attach($selectedBnccs);
                    } else {
                        // Força 1 aleatoria so para ter massa de teste nos dois cards
                        $question->bnccSkills()->attach($bnccSkills->random()->id);
                    }
                }

                if (($i + 1) % $batchSize == 0) {
                    $this->command->info('Inseridas '.($i + 1).' questões...');
                }
            }
            DB::commit();

            // Relatorio
            $this->command->info('=== Relatório de Questões ===');
            $this->command->info('Total: '.Question::count());
            $this->command->info('Com BNCC: '.DB::table('question_bncc_links')->distinct('question_id')->count('question_id'));
            $this->command->info('Com Custom: '.DB::table('question_custom_skill')->distinct('question_id')->count('question_id'));

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Erro ao gerar questoes: '.$e->getMessage());
        }
    }
}
