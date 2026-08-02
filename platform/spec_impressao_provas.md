# Especificação: Geração e Impressão Avançada de Provas

## Objetivo
Refatorar o sistema de impressão e geração de PDF de avaliações para suportar embaralhamento inteligente, persistência de versões (para correção futura via gabaritos com QR Code), padronização de impressão e formatação específica para questões Verdadeiro/Falso.

## 1. Banco de Dados e Modelagem

Para garantir que o aplicativo mobile consiga corrigir um gabarito físico embaralhado de forma consistente, o sistema precisa "lembrar" da exata permutação (mapa de embaralhamento) gerada na hora da impressão.

### Nova Tabela: `exam_copies` (Versões Impressas)
Responsável por armazenar os metadados fotográficos de cada cópia física gerada.
- `id` (bigint, PK)
- `exam_id` (foreign key)
- `turma_id` (foreign key, nullable - identifica a turma alvo da impressão)
- `copy_number` (int - Ex: Cópia 1, Cópia 2 daquele lote)
- `questions_map` (JSON - Array ordenado com os IDs das questões. Ex: `[45, 12, 89]`)
- `options_map` (JSON - Objeto mapeando Question ID para o Array ordenado com os índices originais das alternativas. Ex: `{"45": [2, 0, 1, 3]}`)
- `validation_hash` (string - Hash curto/Seguro para validar a URL do QR Code e impedir falsificações)
- `created_at`, `updated_at`

### Model: `ExamCopy`
- Relacionamentos: `belongsTo(Exam::class)`.
- Métodos auxiliares para traduzir uma resposta bruta de índice do aluno de volta para o índice de correção original do professor e vice-versa.

## 2. Modificações na Lógica de Questões (V/F)

### Exibição Visual (REQ-01)
- Em `resources/views/exams/pdf.blade.php` e `resources/views/exams/edit.blade.php` (modo print):
  - Modificar a renderização de Verdadeiro/Falso. Estava aparecendo apenas (V) e (F). Passará a ser:
    - `A) Verdadeiro`
    - `B) Falso`
- Modificar no layout Web (formulários normais) se for necessário manter a identidade visual 1:1, conforme requisitado. O REQ-01 cita "Padrão obrigatório na versão digital e impressão".

### Resolução de Gabarito do Aluno (REQ-02)
- Estudantes marcarão A ou B no bolão de V/F do "Cartão Resposta".
- Os scripts de pontuação de notas e correção manual devem espelhar que `0 => A (Verdadeiro/Falso na ordem embaralhada)` e `1 => B`.

## 3. Options Modal de Impressão (REQ-07, REQ-08, REQ-10, REQ-11, REQ-12)

A interface de Usuário de `edit.blade.php` sofrerá uma atualização ao clicar em "Imprimir / Gerar PDF". Em vez de só mostrar o painel ou baixar logo, abrirá um Modaz/Configurador "Gerar Versão de Impressão":

1. **Quantidade de Provas**: Input Numérico.
2. **Turma**: Select da Turma (se selecionada, "Quantidade" puxa em cascata a quantidade de alunos da respectiva turma matriculados).
3. Configurações de Aleatoriedade Isoladas (Checkboxes):
   - [ ] Embaralhar ordem das Questões
   - [ ] Embaralhar alternativas (Múltipla Escolha)
   - [ ] Embaralhar alternativas (Verdadeiro/Falso) - (Aviso: O padrão manterá A=Verd, B=Falso)

## 4. O Controller `ExamPrintController` e Geração em Lote

Quando o configurador confirmar, far-se-á um POST via AJAX ou form normal para um endpoint que gera/configura as cópias `ExamCopy`. 
- Ele varre de `1` até `Quantidade de Provas`. E para cada iteração:
  - Joga um `shuffle()` nas Questões (se ativado).
  - Joga um `shuffle()` nos arrays de opções (se ativado), de Múltipla Escolha e V/F de acordo com as restrições flag.
  - Salva o `ExamCopy`.

## 5. View / PDF Final (`pdf.blade.php`)

A estrutura final do PDF em DOMPDF (único arquivo de dezenas/centenas de páginas com `page-break-after: always`) deverá concatenar para CADA `ExamCopy` do lote:

1. **Página da Prova (Enunciados)**: Renderizando com as variáveis do JSON do `ExamCopy` no loop.
2. **Gabarito do Aluno (Folha de Respostas) (REQ-05, REQ-06)**:
   - Construir o Layout de bolhas com base anexada (Grid formatada via inline CSS estrito adaptada para renderização do mPDF/DomPDF).
   - Inserir **QR Code** no rodapé/cabeçalho. (Payload do QR Code: Rota para API do App com JSON contendo `{ exam_id, copy_id, validation_hash, org_id }`).
3. **Gabarito do Professor (REQ-03, REQ-04)**:
   - O gabarito não usará "Letra real (C)", mostrará a equivalente da permutação mapeada no `options_map`.

## 6. O QR Code (REQ-06)

Precisa depender de um Composer Package `simplesoftwareio/simple-qrcode`.
- Codificará os metadados. SVG ou tag `<img>` em Base64 via Helper.
