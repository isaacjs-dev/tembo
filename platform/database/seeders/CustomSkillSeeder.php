<?php

namespace Database\Seeders;

use App\Models\CustomSkill;
use App\Models\Organization;
use Illuminate\Database\Seeder;

class CustomSkillSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('subdomain', 'escola-modelo')->first();
        if (! $org) {
            $this->command->error('Instituição base não encontrada.');

            return;
        }

        $skills = [
            // Matemática
            'Frações: Soma e Subtração',
            'Frações: Multiplicação',
            'Geometria Espacial',
            'Geometria Plana',
            'Função Afim',
            'Função Quadrática',
            'Trigonometria',
            'Estatística Básica',
            'Probabilidade',
            'Análise Combinatória',
            'Matriz e Determinantes',
            'Logaritmos',
            'Juros Simples',
            'Juros Compostos',

            // Português
            'Interpretação de Texto',
            'Concordância Verbal',
            'Concordância Nominal',
            'Regência',
            'Crase',
            'Pontuação',
            'Figuras de Linguagem',
            'Tipologia Textual',
            'Funções da Linguagem',
            'Sintaxe do Período Simples',
            'Vozes Verbais',
            'Morfologia',

            // Ciências / Biologia
            'Sistema Digestório',
            'Sistema Circulatório',
            'Cadeia Alimentar',
            'Ecologia Básico',
            'Genética Mendeliana',
            'Evolução',
            'Citologia',
            'Reinos dos Seres Vivos',
            'Ciclos Biogeoquímicos',

            // História
            'Linha do Tempo',
            'Revolução Francesa',
            'Segunda Guerra Mundial',
            'Guerra Fria',
            'Brasil Colônia',
            'Império do Brasil',
            'Era Vargas',
            'Ditadura Militar',
            'Revolução Industrial',
            'Grécia Antiga',
            'Roma Antiga',

            // Outros
            'Leitura de Mapas',
            'Climatologia',
            'Geopolítica',
            'Globalização',
            'Física: Cinemática',
            'Física: Leis de Newton',
            'Física: Eletrodinâmica',
            'Química: Tabela Periódica',
            'Química: Ligações',
            'Química: Estequiometria',
        ];

        foreach ($skills as $skill) {
            CustomSkill::firstOrCreate([
                'organization_id' => $org->id,
                'name' => $skill,
            ]);
        }
        $this->command->info('Custom Skills geradas com sucesso!');
    }
}
