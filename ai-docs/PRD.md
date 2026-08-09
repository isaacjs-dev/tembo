# Tembo — PRD executável de evolução integral

**Versão:** 1.0

**Atualizado em:** 2026-08-08

**Estado:** ativo
**Idioma do produto e das tarefas:** português do Brasil

## 1. Governança e fontes de verdade

Este documento é a fonte única de requisitos executáveis para a evolução integral do Tembo. O arquivo `PROMPT_BASE_MESTRE_TEMBO_CLAUDE_CODEX_v1.md` define o processo de engenharia e os gates de qualidade; `Novas Func.md` preserva a direção de produto e a transcrição original. Quando houver conflito, prevalecem: pedido atual, segurança e integridade, código/schema/testes em uso, este PRD e, por último, exemplos das transcrições.

O trabalho será entregue em incrementos compatíveis, testados e rastreáveis. Não estão autorizados deploy em produção, exclusão de dados reais, mudança de preços, contratação de serviços, rotação de segredos nem quebra deliberada de API, QR ou formato offline.

Estados usados neste documento: **Implementado**, **Parcial**, **Ausente** e **Não homologado**. “Implementado” exige evidência automatizada; homologações físicas permanecem separadas.

## 2. Produto, usuários e objetivo

O Tembo é uma plataforma educacional para criar, aplicar, imprimir, responder, corrigir e acompanhar Avaliações. A plataforma atende instituições, gestores, diretores, coordenadores, pedagogos, professores institucionais ou independentes, alunos, responsáveis quando vinculados e administradores globais.

Objetivos:

- manter Laravel como autoridade de identidade, tenancy, autorização, planos, cotas, correção oficial e auditoria;
- oferecer Avaliações online, impressas, híbridas e fluxos OMR temporariamente offline;
- garantir que nenhum dado, nota, cartão ou resultado atravesse tenants ou seja associado silenciosamente à pessoa errada;
- tornar criação, impressão, leitura, revisão e análise simples para uso comum e configuráveis para usuários avançados;
- preservar contratos e dados históricos durante toda evolução.

Não objetivos desta execução: criar um frontend Web separado, criar módulo artificial chamado SintiFlux, prometer precisão OMR absoluta ou liberar OMR Mobile sem dataset e homologação física.

## 3. Arquitetura confirmada

- `platform/`: PHP 8.2+, Laravel 12, Blade, Alpine.js, Tailwind CSS, Vite, Sanctum, PDF/impressão e OMR Web TypeScript/OpenCV.js.
- `duoscanner/`: Expo 52, React Native 0.76 e TypeScript, câmera, SQLite, armazenamento seguro, OMR local e sincronização.
- Web é integrado ao monólito Laravel; não existe frontend Web separado.
- API, QR, templates, geometrias e estados offline são contratos versionados. Web e Mobile podem executar localmente, mas o backend confirma o resultado oficial.

## 4. Estado inicial verificado

**Implementado:** CRUD e execução de Avaliações online/impressas/híbridas; portal do aluno; autosave; resultados; PDFs e cópias individualizadas; QR assinado v3/v4/v5; dois templates OMR versionados; OMR Web com revisão humana; biblioteca pessoal/institucional de questões; relatórios básicos; ledger/cortesias; autoria de aulas e atividades; fluxo de revisões, snapshots, XP e relatórios.

**Parcial:** wizard de Avaliação; previews; dados da introdução do aluno; filtros e métricas institucionais; entrega de aulas/atividades; cotas generalizadas; contratos e persistência offline; segurança multi-tenant; cartão/cabeçalho no PDF; OMR Mobile single-page e multipágina.

**Ausente:** biblioteca pública global moderada; recursos de questão reutilizáveis N:M; moderação, denúncia, deduplicação, reputação e recompensa configurável; catálogos profissionais 10×; gerenciador/editor de cabeçalhos; renderizador único de preview/PDF; dataset OMR anotado e homologação física.

**Não homologado:** OMR Mobile/offline. Existem motores divergentes, semântica incorreta de `v`/`tpl_v`/`rpp`, sessão multipágina incompleta, qualidade de câmera simulada, confirmação final de sync incompleta e ausência de ground truth físico.

## 5. Requisitos funcionais

### 5.1 Identidade, instituições e autorização

- **IAM-01:** uma pessoa possui conta global e memberships independentes por workspace.
- **IAM-02:** `user_organization` é a autoridade de contexto e papel; contexto ausente nunca remove isolamento.
- **IAM-03:** uma pessoa pode ser aluno em uma instituição e professor em outra.
- **IAM-04:** professores independentes usam workspace pessoal e podem criar turmas, vínculos e conteúdo próprios.
- **IAM-05:** convites vinculam contas existentes ou permitem ativação pelo titular sem senha permanente definida por terceiros.
- **IAM-06:** remover turma ou membership não exclui nem desativa a conta global.
- **IAM-07:** professor–turma, professor–aluno, professor–disciplina e turma–disciplina são relações persistentes e tenant-aware.
- **IAM-08:** diretor, coordenador, pedagogo, professor e aluno obedecem matriz formal de policies/permissões.
- **IAM-09:** somente o proprietário do plano altera o próprio plano.
- **IAM-10:** instituição pode corrigir Avaliação de seu contexto pela Web com auditoria; Mobile não recebe essa permissão.
- **IAM-11:** todo ID de relacionamento, filtro, export, arquivo e sync é validado contra contexto e autorização.
- **IAM-12:** alterações sensíveis registram organização, ator, entidade, ação, origem, request ID e before/after apropriado.

### 5.2 Avaliações e jornada acadêmica

- **ASM-01:** manter modalidades online, impressa com resposta digital, impressa com cartão OMR e operação offline.
- **ASM-02:** criar Avaliação em oito etapas: Informações, Questões, Público, Aplicação, Aparência, Cartão-resposta, Pré-visualização e Publicação.
- **ASM-03:** cada etapa é recuperável; backend valida o gate final de publicação.
- **ASM-04:** Avaliação pode ter disciplina opcional, turmas, alunos específicos ou nenhum público imediato.
- **ASM-05:** preservar horários, tentativas, códigos de acesso, publicação e liberação de resultado.
- **ASM-06:** gerar cópias individualizadas e versionadas por aluno, com ordem e alternativas embaralhadas reversíveis.
- **ASM-07:** permitir saída somente Avaliação, somente cartão, ambos, gabarito autorizado ou disponibilização digital.
- **ASM-08:** introdução do aluno informa Avaliação, professor, instituição, disciplina, janela, prazo, modalidade, status e tentativas.
- **ASM-09:** previews reais cobrem desktop, tablet, celular e impressão.
- **ASM-10:** templates e gabaritos históricos permanecem vinculados à versão usada na geração.
- **ASM-11:** apresentação digital suporta Avaliação inteira, quantidade configurável por bloco/tela e organização automática conforme o conteúdo.
- **PED-01:** preservar autoria, importação, aprovação, execução, snapshots, gamificação e relatórios de revisões.
- **PED-02:** aulas e atividades possuem publicação, entrega, execução/progresso do aluno e relatórios próprios.

### 5.3 Biblioteca, colaboração, planos e créditos

- **LIB-01:** questões e recursos suportam escopos pessoal, compartilhado específico, institucional e público da plataforma.
- **LIB-02:** Recurso de Questão versionado representa texto, imagem, gráfico, tabela, fórmula, diagrama ou documento e pode servir a várias questões.
- **LIB-03:** visibilidade de uma questão nunca excede a dos recursos obrigatórios vinculados.
- **LIB-04:** biblioteca oferece pesquisa, filtros, paginação e uso auditável sem carregar coleções inteiras.
- **LIB-05:** publicação pública passa por submissão, moderação, aprovação/rejeição e histórico.
- **LIB-06:** denúncia, deduplicação, direitos de uso, reputação e prevenção de spam fazem parte do fluxo público.
- **PLAN-01:** aluno permanece Free; professor e instituição têm Free ou plano pago, conforme catálogo vigente.
- **PLAN-02:** consumo e concessão usam ledger idempotente, nunca apenas decremento sem histórico.
- **PLAN-03:** cotas abrangem recursos configuráveis e contam memberships corretos.
- **PLAN-04:** recompensas públicas são configuráveis, versionadas, limitadas e concedidas exatamente uma vez após aprovação.
- **PLAN-05:** vigência, carência, downgrade, cortesias e entitlement usam um resolvedor coerente.

### 5.4 Aparência, cabeçalhos, cartões e impressão

- **APP-01:** oferecer pelo menos 10 layouts de Avaliação, 10 cabeçalhos e 10 cartões-resposta realmente distintos e documentados.
- **APP-02:** templates de sistema são imutáveis; usuário/instituição pode duplicar, personalizar, versionar, definir padrão e arquivar.
- **APP-03:** cabeçalho de Avaliação e cabeçalho de cartão são contextos distintos.
- **APP-04:** editor simples troca modelo, campos e logo; editor avançado usa canvas para elementos, alinhamento, tamanho, distribuição e undo/redo.
- **APP-05:** fonte persistida é um schema JSON validado e editável, não uma imagem nem serialização opaca do canvas.
- **APP-06:** tokens dinâmicos pertencem a uma whitelist, possuem fallback e escape seguro.
- **APP-07:** um renderizador HTML/CSS canônico alimenta preview e PDF.
- **APP-08:** snapshots de layout, cabeçalho, logo, cartão e geometria são imutáveis em cópias históricas.
- **APP-09:** PDFs A4 preservam margens, quebras, imagens, QR, fiduciais, bolhas e legibilidade em conteúdo curto ou longo.

### 5.5 QR, OMR e sincronização

- **QR-01:** preservar leitura de QR v3/v4/v5 e introduzir versão nova somente para wire incompatível.
- **QR-02:** payload é compacto, autenticado, versionado e sem PII ou gabarito em claro.
- **QR-03:** validar tenant, Avaliação, cópia, página, template, revisão e assinatura; QR não substitui autorização.
- **QR-04:** impressão mantém contraste, tamanho físico e quiet zone adequados no envelope homologado.
- **OMR-01:** Web e Mobile compartilham schema, fixtures, geometria e classificação pura; adapters de imagem podem diferir.
- **OMR-02:** pipeline mede imagem real, orientação, perspectiva, fiduciais, escala, luz, blur e ROIs.
- **OMR-03:** classificar marca simples, fraca, apagada, dupla, ambígua e em branco com confiança reproduzível.
- **OMR-04:** baixa confiança sempre exige revisão humana com região/questão identificada.
- **OMR-05:** vínculo cartão→cópia→Avaliação→aluno é inequívoco; inconsistência bloqueia correção automática.
- **OMR-06:** `v`, `tpl_v`, `rpp`, `g`, `qs`, `qe`, `oc` e maps de embaralhamento mantêm semântica única.
- **OMR-07:** multipágina usa uma sessão persistente e consolida exatamente uma vez.
- **OMR-08:** cópia guarda snapshot de gabarito, pontos, ordem, template, cabeçalho e revisão.
- **OMR-09:** intervenção humana e alteração de resultado são auditadas.
- **OMR-10:** Mobile captura pela câmera; Web suporta câmera e upload, usando o mesmo contrato de resultado.
- **OMR-11:** em condições normais o fluxo é apontar, detectar, ler, confirmar, corrigir quando necessário e salvar; recorte, orientação, seleção manual de QR, threshold e contraste são apenas fallback assistido.
- **OFF-01:** Mobile baixa apenas escopo autorizado e protege PII, gabaritos e imagens em repouso.
- **OFF-02:** fila persiste captura, revisão, envio, processamento, confirmação, conflito e falha através de reinícios.
- **OFF-03:** `client_operation_id` garante idempotência atômica; timeout/retry nunca duplica resultado.
- **OFF-04:** upload retorna recibo e o Mobile aguarda estado final do servidor antes de marcar sincronizado.
- **OFF-05:** conflito de versão, correção concorrente ou acesso revogado é explícito e auditável; notas não usam last-write-wins silencioso.
- **OFF-06:** dead-letter possui recuperação visível sem perda do registro local.

### 5.6 Relatórios e operação

- **REP-01:** instituição filtra por professor, disciplina, turma, aluno, Avaliação e período.
- **REP-02:** métricas incluem participação, médias, tentativas, correções, revisões, aulas, atividades, OMR e uso.
- **REP-03:** agregações são feitas no banco, paginadas e indexadas.
- **REP-04:** auditoria institucional exibe somente eventos do próprio contexto.

## 6. Requisitos não funcionais e gates

- **Segurança:** zero acesso cross-tenant nos testes; dados sensíveis e imagens não são públicos; inputs e IDs possuem autorização server-side.
- **Compatibilidade:** migrations aditivas seguem expand/backfill/dual-read/cutover; APIs v1/v2, QR históricos, templates e filas existentes permanecem legíveis durante transição.
- **Acessibilidade:** WCAG 2.2 AA, teclado, foco, labels, contraste, mensagens, movimento reduzido e alvo de toque mínimo de 44 px.
- **Responsividade:** 1440, 1280, 768 e 390 px sem overflow funcional.
- **Desempenho:** sem N+1; paginação obrigatória; endpoints de listagem p95 ≤500 ms sob seed documentado; LCP p75 ≤2,5 s em celular médio para jornadas principais.
- **Impressão:** regressão por rasterização e inspeção A4; nenhum corte em QR, fiducial, questão ou alternativa.
- **OMR:** dataset versionado com ground truth; zero associação silenciosa incorreta; métricas por condição; Web/Mobile parity; relatório separa simulação de teste físico. Antes de abrir o holdout, congelar limiares mínimos: autoaceite ≥99,9%, erro confiante ≤0,1%, exatidão por questão ≥99,5%, ambiguidades reais enviadas à revisão ≥99%, QR na primeira tentativa ≥99% e em até duas tentativas ≥99,9%, todos dentro do envelope declarado.
- **Sync:** exatamente um resultado em retries concorrentes, reinício e reconexão; nenhuma perda confirmada.

## 7. Evolução mínima do modelo e contratos

- Contexto: workspace institucional/pessoal, membership autoritativo e perfil acadêmico por usuário+workspace.
- Relações: professor–disciplina, professor–aluno, turma–disciplina e Avaliação–aluno com índices únicos tenant-aware.
- Aparência: templates e versões de layout/cabeçalho/cartão; preferências do usuário com fallback institucional; snapshots na cópia.
- Biblioteca: Recurso de Questão, pivot N:M, submissões/revisões/denúncias públicas e regras versionadas de recompensa integradas ao ledger.
- OMR: operação idempotente, recibo/status final, revisão de prova/cópia e snapshots; imagens privadas.
- Contratos públicos novos serão documentados com JSON Schema/fixtures e lançados de forma aditiva em API v2 ou versão sucessora justificada.

`LearningMaterial` continua representando conteúdo entregue ao aluno; Recurso de Questão representa dependência reutilizável de enunciados. Os conceitos não devem ser duplicados ou confundidos.

## 8. Estratégia de entrega e aceite

1. checkpoint e baseline;
2. tenancy, identidade e segurança crítica OMR;
3. relações acadêmicas e jornada de Avaliação;
4. biblioteca pública, recursos, moderação e recompensas;
5. layouts, cabeçalhos, cartões, renderização e previews;
6. QR/OMR Web-Mobile, offline, conflitos e paridade;
7. relatórios, acessibilidade e desempenho;
8. regressão, revisão independente, OMR AUDIT REPORT e rollout controlado.

Cada tarefa exige testes focados, regressão proporcional, revisão independente e atualização da matriz `requisito → implementação → arquivos → testes → revisor → status`. Produção e OMR físico permanecem pendências humanas explícitas até autorização e evidência.

## 9. Dataset e homologação OMR

O manifest por imagem registra QR/schema/template, `qs/qe/rpp/oc/g`, ground truth de cada bolha, dispositivo, câmera, distância, ângulo, iluminação, papel, impressora, escala, compressão e consentimento. Deve cobrir templates atuais/históricos, 5–60 questões, 2–6 alternativas, múltiplas páginas, rotação, perspectiva, blur, sombras, rasuras, marca dupla, fotocópia e escalas 95/100/fit.

Fixtures de calibração e holdout são separadas. Os limiares de cada métrica devem ser congelados antes do holdout e não podem ser reajustados usando suas imagens. O parecer de homologação exige todos os gates atendidos e, no mínimo, 200 capturas holdout em três classes de telefone e três impressoras. Sem hardware disponível, o parecer será `APROVADO COM PENDÊNCIA HUMANA`, nunca “homologado”.

## 10. Riscos e decisões reservadas ao usuário

- preços, equivalências comerciais e limites finais de recompensa;
- política jurídica de retenção e consentimento de imagens/dados pessoais;
- matriz física final de aparelhos, impressoras e papéis;
- deploy e rollout em produção;
- qualquer migração destrutiva ou quebra deliberada de cliente histórico.

Detalhes técnicos reversíveis serão decididos pela engenharia com evidência, sem interromper a execução.
