# Fase 13 — Estabilidade pedagógica

**Tarefa:** `PED-001`  
**Estado:** concluída  
**Parecer:** APROVADO por revisão técnica em duas passagens  
**Pendência humana:** observação da migration e da concorrência no banco de produção durante o rollout

## Resultado

O fluxo existente de revisões, aulas e atividades foi estabilizado sem ampliar o escopo para a entrega ao aluno prevista em `PED-002`. Tentativas de revisão agora usam um snapshot imutável do conteúdo, continuam legíveis mesmo após alterações históricas permitidas e não recebem XP duas vezes em retries ou requisições concorrentes.

Publicação, revisão e suspensão passaram a obedecer transições explícitas. Conteúdo com tentativas não pode ser alterado ou excluído, decisões de revisão não podem ser contornadas pelo autor e a aprovação independente fica registrada com hash do conteúdo. Aulas e atividades passaram a salvar conteúdo, turmas, questões e revisão gerada na mesma transação, preservando a autoria da fonte.

Papéis institucionais usam a matriz central de permissões e o workspace selecionado. Diretor e coordenador podem gerir e revisar; pedagogo pode visualizar e revisar; professor gere seu escopo; papéis customizados respeitam permissões configuradas. Usuários somente leitura agora possuem páginas próprias de visualização, sem receber controles de edição.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Revisor | Status |
| --- | --- | --- | --- | --- | --- |
| PED-01 — tentativa estável | Execução e correção consultavam itens vivos | Snapshot/hash por tentativa; respostas usam chave estável e suportam backfill legado | `RevisionWorkflowTest` | Revisão independente + segunda passagem local | aprovado |
| PED-01 — idempotência | Repetição concorrente podia premiar XP novamente | Locks transacionais e `rewarded_at`; perfil limitado a organização+aluno | Testes de conclusão repetida e isolamento | Revisão independente + segunda passagem local | aprovado |
| PED-01 — workflow | Status, revisão e mutações não formavam uma máquina de estados segura | Transições explícitas, hash aprovado, autorrevisão proibida e conteúdo histórico imutável | Testes de publicação, rejeição, suspensão e mutação | Revisão independente + segunda passagem local | aprovado |
| PED-01 — permissões | Regras duplicadas divergiam da matriz institucional | Permissões pedagógicas centralizadas e workspace fail-closed | Built-ins, papel customizado e cross-tenant | Revisão independente + segunda passagem local | aprovado |
| PED-01 — atomicidade | Falha em pivôs/revisão podia deixar Lesson/Activity parcial | Operações e geração de revisão na mesma transação | Falha forçada com rollback integral | Revisão independente + segunda passagem local | aprovado |
| PED-01 — autoria | Geração por gestor podia trocar o autor da fonte | Revisão gerada preserva autor da aula/atividade; ator fica em `updated_by` | Teste de geração por gestor | Revisão independente + segunda passagem local | aprovado |
| Integração com avaliação | Revisão obrigatória vencida podia bloquear avaliação para sempre | Bloqueio pré-avaliação ignora revisão já expirada, cujo acesso também está encerrado | Caso dedicado de prazo vencido | Revisão independente + segunda passagem local | aprovado |
| Leitura institucional | Papel apenas leitor recebia lista sem página útil | Rotas e telas `show` seguras para aulas e atividades | Testes de matriz e Blade | Revisão independente + segunda passagem local | aprovado |

## Alterações técnicas

### Banco e compatibilidade

- `revisions`: hash e instante da aprovação;
- `revision_attempts`: snapshot JSON, hash e marcador idempotente de recompensa;
- `revision_responses`: chave estável do item no snapshot e unicidade por tentativa;
- backfill aditivo restaura chaves de respostas existentes e considera recompensas históricas já concedidas;
- a migration possui caminho de rollback simétrico e não apaga conteúdo pedagógico existente.

### Execução e histórico

- início da tentativa congela configuração, itens, alternativas, respostas corretas e referências necessárias;
- execução, resposta, recurso, conclusão e resultado consultam o mesmo snapshot;
- tentativas antigas recebem snapshot uma única vez quando acessadas;
- itens já respondidos preservam o snapshot registrado na resposta;
- revisão com tentativas fica protegida contra edição, importação, reordenação e exclusão;
- relatórios continuam identificando alunos removidos logicamente.

### Segurança e workflow

- tentativa, resultado e gamificação exigem aluno e organização do contexto autenticado;
- publicação após pedido de alterações volta obrigatoriamente para revisão;
- reativação de conteúdo suspenso requer revisor independente;
- professor não acessa conteúdo pedagógico exclusivo de outro escopo;
- o administrador global também respeita o workspace selecionado nas operações contextualizadas.

### Web, API e Mobile

- Web: formulários e ações refletem somente transições permitidas e mostram proteção histórica;
- API Mobile: N/A; nenhum DTO, endpoint, QR, OMR ou formato offline foi alterado;
- `PED-002` permanece responsável por disponibilizar aulas/atividades ao aluno com progresso persistente.

## Evidências

- Laravel integral: **454 testes, 1.856 assertivas**, aprovado;
- regressão final focada: **14 testes, 88 assertivas**, aprovada;
- JavaScript Web: **11/11**, aprovado;
- Vite build de produção: aprovado;
- Mobile TypeScript: aprovado;
- Mobile grid/contratos: **8/8**, aprovado;
- Blade view cache e Pint: aprovados;
- E2E pedagógico alterado: **2/2** em desktop e viewport mobile;
- E2E completo: **10/12** na execução conjunta; dois timeouts de 30 s ocorreram em cenário de materiais reutilizáveis não alterado, e o mesmo cenário passou **2/2** quando repetido isoladamente;
- `git diff --check`: sem erros; apenas aviso de normalização LF/CRLF no task master.

## Revisão independente

A primeira passagem encontrou três problemas objetivos: revisão obrigatória expirada bloqueando a avaliação, possibilidade de o autor contornar uma decisão de revisão e ausência de visualização útil para papéis somente leitura. Os três foram corrigidos na causa, receberam testes e passaram no reteste focado.

O agente revisor ficou indisponível por limite externo antes de emitir uma última mensagem formal. Conforme o fallback previsto no prompt-base, foi executada uma segunda passagem local separada da implementação, confrontando diff, máquina de estados, isolamento, migration, Blade, formatação e testes. Parecer final: **APROVADO**, sem achado bloqueante remanescente.

## Pendências reais

- acompanhar locks e duração da migration no banco real durante rollout controlado; a suíte automatizada usa o banco de teste;
- validar concorrência sob a engine e a carga de produção por observabilidade, embora locks, constraints e idempotência estejam cobertos funcionalmente;
- entrega, progresso e conclusão de aulas/atividades pelo aluno pertencem a `PED-002` e não foram declarados prontos nesta fase.
