# Base demonstrativa

Esta base foi criada para desenvolvimento, demonstração e testes funcionais. Todos os nomes, e-mails, matrículas, pagamentos e eventos são fictícios.

## Gerar ou recriar

Na pasta `platform`:

```bash
php artisan migrate:fresh --seed
```

O comando apaga e recria o banco configurado no `.env`. Execute-o somente em uma base descartável de desenvolvimento ou teste. A geração dos cenários demonstrativos é bloqueada fora dos ambientes `local` e `testing`.

O fluxo validado e recomendado para um resultado previsível é `migrate:fresh --seed`. Não execute `db:seed` repetidamente sobre a mesma base: os cenários históricos e transacionais são intencionalmente recriados pelo fluxo limpo.

## Credenciais

Todas as contas demonstrativas usam a senha `password`.

| Contexto | Perfil | E-mail |
| --- | --- | --- |
| Plataforma | Superadministrador | `admin@admin.com` |
| Escola Horizonte | Administrador institucional | `institution@email.com` |
| Escola Horizonte | Coordenador pedagógico | `coordinator@email.com` |
| Escola Horizonte | Professor de Matemática | `teacher@email.com` |
| Escola Horizonte | Professora de Língua Portuguesa | `teacher.portugues@email.com` |
| Escola Horizonte | Aluno principal | `student@email.com` |
| Escola Horizonte | Responsável principal | `guardian@email.com` |
| Colégio Aurora | Administrador institucional | `institution.aurora@email.com` |
| Colégio Aurora | Professor | `teacher.aurora@email.com` |
| Colégio Aurora | Aluno principal | `student01.aurora@demo.avaliation.test` |
| Colégio Aurora | Responsável | `guardian.aurora@email.com` |
| Instituição suspensa | Administrador institucional | `institution.inactive@email.com` |

Também são criados:

- 20 alunos ativos na Escola Horizonte: `student@email.com` e `aluno02@demo.avaliation.test` até `aluno20@demo.avaliation.test`;
- 8 alunos no Colégio Aurora: `student01.aurora@demo.avaliation.test` até `student08.aurora@demo.avaliation.test`;
- professores, alunos e vínculos inativos ou pendentes para testar bloqueios;
- quatro responsáveis na Escola Horizonte, incluindo um responsável ligado a dois alunos;
- um usuário e uma turma removidos logicamente para testar a lixeira.

## Instituições e isolamento

### Escola Horizonte — Demonstração

Tenant principal no plano Profissional. Possui:

- turmas `1º Ano A`, `1º Ano B`, `2º Ano A`, `6º Ano A` e `Reforço de Matemática`;
- professor vinculado a várias turmas e professora compartilhando turmas;
- alunos matriculados em mais de uma turma para simular progressão, reforço e consultas cruzadas;
- coordenador representado pelo perfil institucional de professor com o cargo customizado `Coordenador Pedagógico`;
- áreas de conhecimento, disciplinas, BNCC, habilidades próprias e mais de 2.000 questões;
- avaliações publicadas, fechadas, futuras, em rascunho e removidas;
- aplicações on-line, impressas e híbridas;
- entregas corrigidas, enviadas aguardando correção e em andamento;
- notas altas, médias e baixas, duas tentativas, feedback, respostas certas e erradas e correção discursiva por rubrica;
- materiais publicados, rascunhos, itens removidos e progresso aberto/concluído;
- aulas publicadas, futuras e em rascunho, vinculadas a uma ou mais turmas;
- atividades on-line, impressas e híbridas com questões relacionadas;
- revisões nos estados rascunho, em análise, devolvida, publicada e suspensa;
- exercícios de múltipla escolha, verdadeiro/falso, associação, preenchimento, ordenação, flashcard e resposta curta;
- tentativas de revisão concluídas/em andamento, snapshots, XP, níveis, sequência e conquistas privadas;
- cópias de prova, cartões-resposta e leituras OMR confirmadas, sincronizadas, em revisão, pendentes e rejeitadas;
- convites pendente, aceito, expirado e recusado;
- regras de configuração ativas, inativas, globais e específicas por usuário;
- assinatura ativa e assinatura histórica cancelada;
- eventos de importação, exportação, pagamento, cortesia, baixa confiança OMR e bloqueio entre tenants;
- trilhas de auditoria recentes e antigas.

### Colégio Aurora — Tenant isolado

Tenant independente no plano Enterprise, com administrador, professor, oito alunos, responsável, currículo próprio, turma, questões, avaliação, desempenhos e material exclusivo.

Use suas contas para confirmar que usuários da Escola Horizonte não visualizam nem alteram dados do Colégio Aurora e vice-versa.

### Instituição Suspensa — Cenário Demo

Tenant inativo com assinatura `past_due`, vencimento antigo, evento crítico de pagamento pendente e professor inativo. Serve para validar o bloqueio de autenticação e de sessões já existentes.

## Roteiros práticos

### Superadministrador

Entre com `admin@admin.com`.

- compare as três instituições e seus estados ativo/inativo;
- confira os planos Profissional, Enterprise e o cenário vencido;
- consulte eventos de pagamento confirmado, pagamento pendente e cortesia de 30 dias;
- valide os limites mensais individuais de leituras OMR, provas publicadas e questões cadastradas;
- teste cortesias ativas e agendadas, créditos extras por recurso e plano temporário gratuito;
- confirme que a instituição suspensa não consegue operar.

### Administrador institucional

Entre com `institution@email.com`.

- consulte e edite professores, alunos, responsáveis e as cinco turmas;
- observe professores vinculados a mais de uma turma;
- confira o cargo customizado do coordenador e suas permissões;
- teste convites pendentes, aceitos, expirados e recusados;
- consulte lixeira, auditoria, importação simulada e eventos;
- compare os registros com o tenant Aurora para validar o isolamento.

### Coordenador

Entre com `coordinator@email.com`.

- acompanhe turmas, professores, alunos, avaliações, relatórios e OMR;
- compare o desempenho individual com a distribuição coletiva;
- localize alunos com desempenho baixo e materiais recomendados;
- confirme que o coordenador não administra a assinatura SaaS.

### Professor

Entre com `teacher@email.com`.

- abra as avaliações diagnóstica, bimestral, discursiva, impressa, futura e em rascunho;
- filtre e reutilize questões objetivas, verdadeiro/falso e discursivas;
- acompanhe uma avaliação com entregas em andamento;
- revise uma entrega aguardando correção e uma correção por rubrica;
- gere ou confira as versões impressas e cartões-resposta;
- percorra a fila OMR com leituras de diferentes níveis de confiança;
- consulte resultados da turma e materiais relacionados aos erros.
- abra Aulas, Atividades e Revisões; edite os itens e confira o fluxo de publicação;
- gere um prompt, copie para uma IA externa e cole o JSON retornado no Tembo;
- consulte o relatório coletivo da revisão demonstrativa.

Entre com `teacher.portugues@email.com` para verificar um professor com outra disciplina e acesso somente ao contexto atribuído.

### Aluno

Entre com `student@email.com`.

- consulte avaliações concluídas, uma segunda tentativa e uma avaliação em andamento;
- confira nota, respostas, feedback e histórico quando a configuração da prova permitir;
- confirme que resultados futuros ou ainda não liberados permanecem ocultos;
- abra materiais e compare os estados de progresso;
- execute `Missão: dominar números e gráficos` para testar os tipos interativos, feedback e gamificação;
- confira que progresso, XP, nível, sequência e conquistas são privados;
- tente acessar dados de outro aluno ou do tenant Aurora e confirme o bloqueio.

### Responsável

Entre com `guardian@email.com`.

- consulte somente o aluno vinculado;
- acompanhe resultados que já foram liberados;
- confirme que respostas, notas não liberadas e outros alunos permanecem inacessíveis.

## Estados cobertos

| Área | Exemplos |
| --- | --- |
| Contas | ativas, inativas, não verificadas e removidas |
| Instituições | ativas e suspensa |
| Assinaturas | ativa, cancelada e vencida/pendente |
| Turmas | ativas, compartilhadas e removida |
| Avaliações | rascunho, publicada, futura, fechada e removida |
| Entregas | em andamento, enviada e corrigida |
| Desempenho | insuficiente, regular, bom e excelente |
| Conteúdo | publicado, rascunho, aberto, concluído e removido |
| Aulas e atividades | rascunho, publicada, futura, on-line, impressa e híbrida |
| Revisões | rascunho, em análise, devolvida, publicada, suspensa, concluída e em andamento |
| Créditos mensais | consumo normal, créditos extras acumulados e cortesia agendada |
| OMR | pendente, revisão, confirmada, sincronizada e rejeitada |
| Convites | pendente, aceito, expirado e recusado |
| Auditoria | eventos recentes, antigos, informativos, alerta e críticos |

## Regras novas cobertas

- somente leituras OMR, primeira publicação de provas e criação de questões consomem créditos mensais;
- os limites pertencem individualmente a cada professor e renovam no primeiro dia do mês;
- reimpressões, downloads e edições não consomem novamente;
- cortesias concedem plano temporário, créditos extras, substituição de limite, uso ilimitado ou recurso premium;
- uma cortesia institucional beneficia cada professor atual ou futuro durante sua vigência;
- a revisão obrigatória antes da prova só bloqueia o início quando o professor ativa explicitamente essa opção;
- alterações publicadas aparecem imediatamente, mas respostas já dadas preservam o snapshot do item respondido.

## Validação automatizada

O teste `tests/Feature/DemoDatabaseSeederTest.php` recria a base e verifica contas, senha, instituições, turmas, professores, questões, avaliações, materiais, aulas, atividades, revisões, gamificação, consumo, cortesias, OMR, assinaturas, lixeira e isolamento entre tenants.

```bash
php artisan test tests/Feature/DemoDatabaseSeederTest.php
php artisan test tests/Feature/RevisionWorkflowTest.php
```
