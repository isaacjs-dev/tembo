# Fase 14 — Entrega pedagógica ao aluno

**Tarefa:** `PED-002`  
**Estado:** concluída  
**Parecer:** APROVADO por revisão independente após duas rodadas de correção  
**Pendência humana:** observar a migration e a concorrência no banco real durante o rollout

## Resultado

Aulas e atividades publicadas agora chegam ao aluno conforme organização, turma, período de disponibilidade e vínculo ativo. A jornada Web inclui catálogo paginado, aula com progresso persistente, conclusão idempotente, atividade com tentativas, salvamento parcial, retomada, envio, correção objetiva e resultado. Questões discursivas e atividades impressas ou sem questões seguem para correção manual autorizada.

Cada progresso ou tentativa mantém snapshot imutável de conteúdo, configurações, questões e versões de recursos. Alterações posteriores não mudam a experiência histórica; conteúdo com histórico não pode ser excluído, somente arquivado. Relatórios docentes incluem alunos atualmente atribuídos e registros históricos, inclusive contas removidas logicamente.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Revisor | Status |
| --- | --- | --- | --- | --- | --- |
| PED-02 — entrega | Aulas e atividades não eram entregues no portal do aluno | Catálogo tenant-aware por turma, status e janela, com paginação separada | `PedagogicalDeliveryTest` e E2E desktop/mobile | Revisão independente | aprovado |
| PED-02 — progresso de aula | Não havia progresso persistente | Início, atividade e conclusão idempotente com snapshot/hash | Testes de snapshot, conclusão repetida e isolamento | Revisão independente | aprovado |
| PED-02 — tentativa de atividade | Não havia execução pelo aluno | Tentativas numeradas, limite, prazo, salvamento parcial, retomada e envio | Testes objetivos, discursivos, prazo e limite | Revisão independente | aprovado |
| PED-02 — correção | Não havia correção integrada à entrega | Correção objetiva automática; discursiva/impressa/manual com autorização e auditoria | Testes de nota objetiva e correção manual | Revisão independente | aprovado |
| PED-02 — idempotência | Retry/concorrência podia produzir estado divergente | Envio transacional, lock da tentativa e retorno determinístico do resultado terminal | Teste de POST repetido e auditoria única | Revisão independente | aprovado |
| PED-02 — precisão da nota | Divisão decimal podia somar 9,99 em uma atividade de 10 pontos | Distribuição determinística em centavos, preservando o total exato | Caso 10 pontos/3 questões = 10,00 | Revisão independente | aprovado |
| PED-02 — histórico | Exclusão ou conta arquivada podia tornar relatórios incompletos | Exclusão bloqueada quando há progresso; relatórios usam alunos removidos logicamente | Testes de exclusão e relatório histórico | Revisão independente | aprovado |
| PED-02 — recursos | Recursos vivos podiam mudar ou vazar entre contextos | Referências versionadas no snapshot e entrega contextual com validação de tenant, path e hash | Testes de recursos e isolamento | Revisão independente | aprovado |

## Alterações técnicas

### Banco e compatibilidade

- `lesson_progress` registra estado, snapshot, hash e marcos temporais por aula e aluno;
- `activity_attempts` registra tentativa, snapshot, nota, total, estado e correção;
- `activity_responses` usa chave estável do item do snapshot e persiste resposta, acerto, pontos e feedback;
- constraints e índices impedem progresso ou tentativa duplicados no mesmo escopo;
- migration aditiva e reversível, sem alterar contratos históricos de Avaliação, QR, OMR ou Mobile.

### Backend e segurança

- regras de entrega, snapshot e disponibilidade estão centralizadas em serviços;
- queries exigem organização atual, aluno autenticado, turma atribuída ou histórico próprio;
- início e envio usam transações e locks; retry de envio devolve o mesmo resultado;
- downloads verificam tentativa/progresso, versão, tenant, storage, caminho e hash;
- relatórios e correção manual reutilizam a matriz institucional e preservam registros históricos;
- exclusão de conteúdo com progresso é recusada com orientação para arquivamento.

### Web, API e Mobile

- Web: catálogo responsivo, aula, execução de atividade, resultado e relatórios;
- dashboard do aluno ganhou acesso direto a “Aulas e atividades”;
- API Mobile: N/A; nenhum DTO, endpoint ou formato offline foi alterado;
- QR, cartão-resposta, impressão e OMR: N/A.

## Evidências

- Laravel integral antes do último ajuste de revisão: **460 testes, 1.902 assertivas**, aprovado;
- regressão ampliada pós-correção: **66 testes, 462 assertivas**, aprovada;
- testes dedicados pós-correção: **8 testes, 64 assertivas**, aprovados;
- reteste independente final: **44 testes, 273 assertivas**, aprovado;
- JavaScript Web: **11/11**, aprovado;
- Vite build de produção: aprovado;
- Blade view cache e Pint: aprovados;
- E2E da jornada pedagógica: **2/2** em Chromium desktop e Pixel 7;
- `git diff --check`: sem erros.

O primeiro E2E apontou corretamente que a atividade inicialmente escolhida na fixture estava vencida e, portanto, não oferecia início. O cenário foi alinhado a uma segunda atividade ainda disponível e passou nos dois viewports. O projeto não possui script `npm test`; o script confirmado e executado foi `npm run test:js`.

## Revisão independente

A primeira passagem reprovou quatro problemas objetivos: envio não idempotente/atômico, exclusão tornando histórico inacessível, perda de um centavo na divisão de pontos e relatórios omitindo aluno removido logicamente. As quatro causas foram corrigidas e receberam casos dedicados.

No primeiro reteste, o revisor confirmou as quatro correções e encontrou uma corrida remanescente entre exclusão e criação do primeiro progresso/tentativa, além do risco de auditoria duplicada no autoenvio por prazo. Exclusão e criação passaram a bloquear a mesma linha-pai, na mesma ordem, e o autoenvio passou a auditar apenas a requisição que efetivamente transiciona a tentativa. Parecer final: **APROVADO**, sem achados bloqueantes ou médios remanescentes.

## Pendências reais

- observar duração e locks da migration no banco de produção durante rollout controlado;
- acompanhar concorrência e desempenho com a engine e carga reais, apesar da cobertura funcional de locks, constraints e retries;
- a suíte automatizada usa SQLite e não reproduz contenção real de row locks; a ordem e as invariantes foram revisadas, mas devem ser observadas na engine de produção;
- aplicativos Mobile e fluxo offline de conteúdo pedagógico não fazem parte de `PED-002` e não foram declarados implementados.
