# Relatório de qualidade e entrega — 29/07/2026

## Resultado

O núcleo do Avaliation On foi corrigido e fechado como um produto SaaS educacional executável: autenticação e autorização multi-tenant, banco de questões, avaliações on-line/impressas/híbridas, impressão e OMR, correção, relatórios, aprendizagem e portal da família. A entrega passou por revisões paralelas de backend, professor, aluno, impressão/OMR, acessibilidade e segurança, seguidas de uma nova rodada integrada.

Não se declara equivalência integral com produtos externos. As referências serviram para identificar padrões de fluxo e controles; a implementação foi adaptada à arquitetura e ao escopo do sistema.

## Principais correções

### Segurança, contas e multi-tenant

- Normalização do RBAC dos cinco perfis fixos e validação de organização/turma/autoria em controladores, serviços e policies/middlewares.
- Bloqueio imediato de usuário ou organização inativos também em sessões já abertas.
- Bloqueio de tokens Sanctum existentes após desativação, além de verificação/troca de senha nas rotas autenticadas da API.
- Verificação de e-mail exigida no auto-registro e para responsáveis convidados, com troca obrigatória da senha provisória destes responsáveis.
- Limites de requisição para registro, entrada em prova, início, salvamento automático, envio e conclusão de materiais.
- Redação de campos sensíveis na auditoria, exclusão lógica com lixeira e restauração controlada.
- Portal do responsável isolado por vínculo, organização e regras de liberação do professor.
- API do scanner condicionada à licença/permissão OMR e limitada às provas publicadas do próprio autor antes de expor gabarito ou estudantes.
- QR OMR v4 com HMAC do payload completo, entropia de 128 bits e AES-256-GCM separado por organização; leitura legada v3 preservada.

### Avaliações e experiência do professor

- Configuração completa dos modos on-line, impresso e híbrido, com instruções, janela de acesso, duração, tentativas e liberação imediata, programada ou manual.
- Publicação impedida quando a prova não possui questões válidas; código de acesso único e auditado.
- Seletor de questões em duas etapas, filtros curriculares e validação específica por tipo.
- Embaralhamento estável por tentativa e cópia, preservando o gabarito correto.
- Correção manual discursiva com feedback, justificativa e rubrica.
- Relatórios com filtros por período, prova e turma; distribuição, evolução, domínio de habilidades, itens críticos, risco pedagógico e progresso ao vivo.

### Prova on-line e experiência do aluno

- Acesso restrito à instituição, turma, publicação, código e janela configurados.
- Salvamento automático idempotente, suporte a reenvio após oscilação, retomada e encerramento pelo servidor.
- Resultados, gabarito e comentários obedecem individualmente às opções de liberação.
- Layout responsivo, estados claros e componentes navegáveis por teclado.

### Impressão e OMR

- Geração transacional com numeração única e hash aleatório, limitada pela interface a 200 cópias no lote avançado e 100 na exportação de cartões.
- Congelamento do mapa de questões/opções em cada cópia para impedir divergências posteriores.
- Cartões-resposta A4 validados para 20, 50 e 100 questões, com paginação, marcas de alinhamento, QR e geometria do template.
- Tratamento explícito de template inexistente/inválido e rollback em falha.
- Captura, idempotência, revisão humana e correção em lote com isolamento institucional.
- OpenCV 4.8.0, PDF.js e jsQR empacotados localmente, sem dependência operacional de CDN.

### Aprendizagem e família

- CRUD institucional de materiais com autor, turma, taxonomia, publicação, lixeira e auditoria.
- Recomendações ao aluno calculadas apenas a partir de avaliações da organização atual e explicadas no cartão.
- Registro único de abertura com contagem de visualizações, conclusão idempotente e linguagem motivacional sem penalizar o erro.
- Portal do responsável com múltiplos alunos vinculáveis e histórico limitado ao conteúdo liberado.

## Decisões informadas por referências

| Referência de mercado/padrão | Decisão adotada |
| --- | --- |
| [Moodle — question bank](https://docs.moodle.org/400/en/question/edit) e [Canvas — item banks](https://community.canvaslms.com/html/assets/Canvas_Admin_Guide.pdf) | Banco reutilizável, filtros, taxonomia, validação e seleção antes da publicação. |
| [Gradescope — prova por template fixo](https://guides.gradescope.com/hc/en-us/articles/22242117406093-Creating-an-Exam-Quiz-Assignment) e [ZipGrade](https://www.zipgrade.com/) | Cópia impressa com mapa congelado, cartão versionado, leitura em lote e revisão humana. |
| [Gradescope — answer groups](https://guides.gradescope.com/hc/en-us/articles/24838908062093-AI-assisted-grading-and-answer-groups) | Fluxo de correção consistente por rubrica; agrupamento automático por IA não foi prometido. |
| [Formative — respostas ao vivo](https://help.formative.com/en/articles/6198532-view-and-score-responses) | Painel de andamento ao vivo baseado no estado persistido das tentativas. |
| [Khan Academy — relatórios para professores](https://support.khanacademy.org/hc/en-us/articles/360031129891-What-reporting-options-are-available-on-Khan-Academy-for-teachers-to-track-student-performance) | Evolução, domínio por habilidade e identificação de necessidades de revisão. |
| [Google Classroom — rubricas](https://support.google.com/edu/classroom/answer/9335069?co=GENIE.Platform%3DDesktop&hl=en) | Critérios explícitos, níveis e pontuação na correção discursiva. |
| [WCAG 2.2](https://www.w3.org/TR/WCAG22/) | Semântica, foco, teclado, nomes acessíveis, contraste, redução de movimento e responsividade. |
| [Laravel — autorização](https://laravel.com/docs/12.x/authorization) e [rate limiting](https://laravel.com/docs/12.x/rate-limiting) | Policies/middleware para RBAC e tenant; limites de requisição nos pontos abusáveis. |

## Evidências automatizadas

| Verificação | Resultado final |
| --- | --- |
| PHPUnit/Pest | 313 testes, 886 asserções, 0 falhas, 0 avisos |
| Vitest | 8 testes, 8 aprovados |
| Vite | Build de produção aprovado |
| Composer audit | Nenhum aviso de segurança |
| npm audit | 0 vulnerabilidades conhecidas |
| Laravel Pint | Estilo aprovado após normalização do projeto |
| Composer validate | `composer.json` e lock válidos |
| Rotas | 210 rotas, sem colisão de nome ou método/URI |
| Instalação limpa | Todas as migrations e seed concluídos em SQLite temporário |
| Views | Cache Blade compilado sem erro |
| Auditoria visual | 32 cenários-base e rodada final; sem overflow horizontal ou alerta nas telas finais |

A instalação limpa confirmou cinco perfis demonstrativos, 2.000 questões, papel de responsável, vínculo familiar e tabela de progresso. O banco local existente recebeu apenas as migrations pendentes; nenhum dado do usuário foi apagado ou substituído.

## Evidências reproduzíveis

- Aluno, aprendizagem desktop: [`storage/app/qa-final-learning-desktop.png`](../storage/app/qa-final-learning-desktop.png) e [relatório JSON](../storage/app/qa-final-learning-desktop.json).
- Aluno, material em celular: [`storage/app/qa-final-learning-mobile.png`](../storage/app/qa-final-learning-mobile.png) e [relatório JSON](../storage/app/qa-final-learning-mobile.json).
- Família, painel desktop: [`storage/app/qa-final-guardian-desktop.png`](../storage/app/qa-final-guardian-desktop.png) e [relatório JSON](../storage/app/qa-final-guardian-desktop.json).
- Família, histórico em celular: [`storage/app/qa-final-guardian-mobile.png`](../storage/app/qa-final-guardian-mobile.png) e [relatório JSON](../storage/app/qa-final-guardian-mobile.json).
- Relatórios: [`storage/app/qa-reports-desktop.png`](../storage/app/qa-reports-desktop.png).
- Configuração de prova: [`storage/app/qa-teacher-exam-desktop.png`](../storage/app/qa-teacher-exam-desktop.png).
- PDF OMR de QA com três cópias e seis páginas: [`storage/app/qa/omr-answer-sheets-3-copies.pdf`](../storage/app/qa/omr-answer-sheets-3-copies.pdf), 397.733 bytes, SHA-256 `068BDACFFC4A0EFDAD52F675434ED4177E1487D61A313DF7AC78ACA6779661D0`.

Os arquivos em `storage/app/qa-a11y-*` preservam as demais capturas e relatórios da varredura de acessibilidade.

## Acessibilidade e interface

Foram revisadas 32 rotas/cenários em desktop e celular. A auditoria cobre nomes acessíveis, associação de rótulos, hierarquia de títulos, tabelas, textos alternativos, regiões dinâmicas, foco, teclado e overflow. Foram corrigidos, entre outros, cartões clicáveis, abas, modais, menu móvel, ícones decorativos, contraste de textos secundários e conteúdo revelado por hover/foco.

Isso é evidência técnica forte, mas não substitui uma homologação com usuários e tecnologias assistivas reais. Antes de produção, recomenda-se uma rodada com NVDA/JAWS/VoiceOver, ampliação, contraste do sistema e navegação somente por teclado.

## Desempenho e dependências

Os recursos pesados de gráficos, editor, PDF e OMR são carregados em partes separadas pelo Vite. O bundle principal validado ficou em aproximadamente 270 kB (98 kB gzip) e o CSS em 119 kB (17 kB gzip). Fontes e bibliotecas são locais para previsibilidade, privacidade e uso em redes escolares restritas.

## Pendências de homologação, não defeitos ocultos

- Realizar um piloto físico OMR com modelos reais de impressora, escalas, papéis, celulares, sombras, inclinação e iluminação; ajustar limiares por dispositivo se necessário.
- Executar testes de carga com a infraestrutura escolhida, sobretudo início simultâneo de provas, autosave, envio, relatórios e upload de lotes OMR.
- Fazer teste de restauração de backup, rotação de segredos, entrega de e-mail e procedimento de recuperação de fila.
- Validar a política jurídica e institucional para retenção, exportação e exclusão de dados pessoais.
- A semelhança/originalidade textual e o agrupamento inteligente de respostas discursivas não foram implementados. Qualquer futura automação deve manter explicabilidade e revisão humana.
- O painel “ao vivo” consulta o estado persistido; não foi adicionada uma camada WebSocket/push.
- Materiais registram abertura e conclusão, mas não tempo detalhado de estudo, simulações adaptativas ou autoria multimídia avançada.
- Cargos institucionais customizáveis restringem hoje o OMR, mas não substituem o RBAC geral: as demais telas usam os cinco perfis fixos, escopo de tenant/turma/autoria e policies/middlewares. A ampliação desses cargos para autorização contextual em toda a plataforma requer uma etapa própria de produto e migração.

## Conclusão

O sistema está consistente para implantação em ambiente de homologação e piloto controlado. A passagem para produção depende das validações operacionais acima, da configuração segura da infraestrutura e da homologação OMR/acessibilidade em condições reais.
