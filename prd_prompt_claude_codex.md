# PROMPT MESTRE (Claude / Codex) — Plataforma de Avaliações (Web + Mobile OMR)
> **Objetivo deste arquivo:** servir como **fonte de instrução** para uma LLM (Claude/Codex) gerar a implementação completa do sistema, com base no PRD/RDP consolidado e nas atualizações solicitadas.

---

## 1) Contexto do Produto (o que é)
Você irá implementar uma plataforma para:
- **Banco de questões** (reutilizável, com compartilhamento)
- **Montagem de avaliações/formulários** (wizard)
- **Aplicação**:
  - Online (aluno responde no navegador)
  - PDF impresso (prova + gabarito + folha de respostas OMR)
- **Correção**:
  - Web (auto para objetivas + assistida para discursivas)
  - Mobile (app scanner OMR para correção de papel, com revisão e ajuste manual, offline/online)
- **Relatórios**: por aluno/turma/questão + exportações
- **SaaS**: planos/assinaturas (Free com anúncios + pagos), limites e “precisão/qualidade” por plano

---

## 2) Stack fixa (obrigatória)
- Backend: **Laravel + MySQL**
- Frontend: **Blade + Tailwind CSS**
- Autenticação: **Breeze ou Jetstream (escolha 1 e padronize)**
- PDFs: **Dompdf (ou lib equivalente), padronize**
- Testes: **PHPUnit + Feature tests**
- Padrões: **Services, Policies, FormRequests, Migrations, Seeders**
- Observação: o app mobile pode ser **React Native/Expo** (recomendado), mas deve manter contrato claro com o backend.

---

## 3) Regras de ouro (arquitetura e qualidade)
1) **Nega por padrão**: UI pode esconder, mas o backend sempre bloqueia via **Policies**.
2) **Escopo por organização (tenant)**: toda query deve filtrar `organization_id`.
3) **Visibilidade de questões é crítica** (detalhada em RN-04 a RN-06).
4) **Soft Delete + Lixeira** em TODOS os modelos principais (RN-26).
5) **Logs poderosos e flexíveis** para eventos do sistema (RN-27).
6) Não faça “processamento batch de correção OMR no servidor”; OMR é **local no app**, com sync posterior (RN-16).

---

## 4) Perfis do sistema (ATUALIZADO)
### 4.1 Administrador do Sistema (Plataforma / SaaS)
- Governa o sistema como um todo: planos, assinaturas, logs globais, configurações de plataforma.
- Pode criar/gerenciar Instituições (organizações) e usuários institucionais.

### 4.2 Instituição (Gestor da Instituição / Escola)
> **NOVO PERFIL solicitado**
- Atua dentro de **uma organização específica**.
- Pode:
  - Cadastrar professores
  - Criar turmas
  - Cadastrar/importar alunos
  - Ver relatórios institucionais (se habilitado)
- **Não** mexe em configurações globais do SaaS, mas pode ver/gerenciar “Minha Assinatura” (dependendo das regras do produto).

### 4.3 Professor
- Banco de questões, avaliações, aplicação, correção e relatórios conforme permissões.

### 4.4 Aluno
- Responde logado ou por **código/token único aluno+avaliação**.

---

## 5) Permissões (ACL) — deve existir e ser granular
### 5.1 Professor (já previsto)
Mantenha o catálogo de permissões por módulo:
- Questões: `question.create`, `question.edit_own`, `question.view_shared`, `question.duplicate_shared`, `question.share_own`, `question.manage_tags`
- Avaliações: `exam.create`, `exam.edit_own`, `exam.assign_class`, `exam.publish_online`, `exam.generate_pdf`, `exam.view_results`, `exam.grade`
- Turmas/Alunos: `class.create`, `student.create`, `student.import`, `student.generate_codes`, `student.view`
- Relatórios: `reporting.view`

### 5.2 Instituição (novo)
Crie um conjunto de permissões para o perfil Instituição (ou role “org_manager”):
- `org.teacher.create` / `org.teacher.manage` (cadastrar/ativar/desativar professor)
- `org.class.create` / `org.class.manage`
- `org.student.create` / `org.student.import` / `org.student.manage`
- `org.reporting.view` (opcional conforme plano)
- `org.subscription.view` / `org.subscription.manage` (se aplicável ao seu modelo)

### 5.3 Permissões de Lixeira/Exclusão definitiva
- `trash.view` (ver lixeira)
- `trash.restore` (restaurar)
- `trash.force_delete` (apagar de vez — requer confirmação forte)

---

## 6) Regras de Negócio (ATUALIZADO)
Numere e implemente como invariantes do sistema:

- **RN-01 (Tenant):** toda entidade pertence a uma organização; sempre filtrar por `organization_id`.
- **RN-02 (Perfis):** Administrador do Sistema, Instituição, Professor, Aluno.
- **RN-03 (ACL):** acesso via permissões granulares; backend é fonte da verdade.
- **RN-04 (Visibilidade Questões):** Professor vê **apenas** (a) suas questões (owner) e (b) questões compartilhadas explicitamente com ele.
- **RN-05 (Compartilhada somente leitura):** destinatário não edita a original.
- **RN-06 (Duplicar para editar):** duplicação cria cópia com `owner_id` do professor e `source_question_id` apontando para original.
- **RN-07 (Compartilhar):** owner compartilha com usuários específicos (e agora também pode escolher “Compartilhamento público”).
- **RN-08 (Compartilhamento público):** questão pode ser marcada como **PÚBLICA NA ORGANIZAÇÃO** (`ORG_PUBLIC`) e fica visível a todos os professores da organização **somente se**:
  - a organização habilitar a feature (admin/sistema), e
  - o professor tiver permissão de acesso a biblioteca pública (feature/perm).
- **RN-09..RN-25:** manter regras de avaliação, tokens, correção, assinatura, grace period etc. conforme PRD.
- **RN-26 (Soft Delete e Lixeira):** todo modelo principal deve ter `deleted_at` e suportar:
  - **Soft Delete** (vai para lixeira)
  - **Restaurar** (retorna ao estado ativo)
  - **Apagar definitivamente** (force delete) só com permissão `trash.force_delete` + confirmação forte “Tem certeza?”.
- **RN-27 (Log):** módulo poderoso e flexível para monitorar eventos do sistema:
  - registrar ações relevantes (criar/editar/excluir/restaurar/publicar/corrigir/override assinatura/etc.)
  - armazenar: quem, quando, o quê, entidade afetada, antes/depois (quando aplicável), IP/user-agent (quando fizer sentido)
  - permitir filtros e exportação (CSV) para auditoria.

---

## 7) Precisão / Qualidade por plano (ATUALIZADO)
Você deve implementar “precisão por plano adquirido” como parte da arquitetura de planos:
- Além de features e limites, cada plano pode definir **níveis de qualidade**.
Exemplos recomendados:
- `omr_precision_level`: BASIC | PRO (impacta tolerância/confiança e recursos de revisão assistida)
- `ai_feedback_level`: OFF | BASIC | ADVANCED (se IA existir no futuro; manter pronto)
- `reporting_depth`: BASIC | ADVANCED (ex.: análise por item avançada)

Regra:
- o nível (quality tier) deve ser consultado na execução de funcionalidades (mobile OMR, relatórios, etc.)
- exibir na UI quando uma opção está indisponível “Disponível no plano X”.

---

## 8) Telas (Web + Mobile) — com narrativa de fluxo dentro da tela
### 8.1 Regras gerais de UI
- UI inspirada em AdminLTE + Duolingo
- Layout base com sidebar/topbar/breadcrumb
- Sempre narrar o fluxo: “o usuário clica → sistema valida → feedback → próximo passo”.

### 8.2 Referências visuais (ZIP)
Há um ZIP de referência com HTML+imagens. Você deve usar como padrão visual e estrutural:
- `stitch_avaliation_on/guia_de_estilos_duoadmin/code.html`
- `stitch_avaliation_on/layout_base_duoadmin/code.html`
- Exemplos de telas:
  - `listagem_de_usuários_-_duoadmin`
  - `alunos_da_turma_-_duoadmin`
  - `acesso_do_aluno_via_código`
  - `detalhes_da_resposta_do_aluno`
  - `relatório_de_desempenho_por_turma`
  - `histórico_de_pagamentos_-_duoadmin`
  - `gestão_de_assinaturas_-_admin_1`
  - `eventos_de_webhook_-_admin`
  - `histórico_de_digitalizações_-_mobile`

### 8.3 Novas telas obrigatórias (por mudanças)
1) **Instituição > Gestão de Professores**
   - Listar professores, criar/editar, ativar/desativar, reset senha.
2) **Instituição > Turmas e Alunos**
   - Pode ser reaproveitamento das telas do professor, mas com escopo institucional.
3) **Questões > Compartilhar**
   - Agora deve ter opção: “Compartilhar com pessoas” e “Publicar na organização (público)”.
4) **Lixeira (Trash)**
   - Listagem por entidade (Questões, Avaliações, Turmas, Alunos, etc.)
   - Ações: restaurar, apagar definitivamente (com confirmação forte)
5) **Logs**
   - Tela de logs com filtros por usuário, ação, entidade, período; detalhe do evento.

---

## 9) Modelo de Dados — exigências mínimas (sem código aqui)
Você deve criar tabelas e relacionamentos para:
- Organizações (instituições), usuários, permissões/roles
- Questões, compartilhamentos, tags, anexos
- Turmas, alunos
- Avaliações, itens, atribuições
- Tokens aluno+avaliação
- Tentativas e respostas
- Correção (notas, feedback, rubricas)
- Scans OMR (imagem + payload + status)
- Planos, features, limites, qualidade por plano
- Assinaturas, overrides, webhooks
- **Logs** (RN-27)
- **Soft delete** (`deleted_at`) em todos os modelos principais (RN-26)

---

## 10) Entregáveis obrigatórios da implementação (o que você deve gerar)
1) Backend Laravel (migrations, models, policies, services, form requests, seeders)
2) Frontend Blade + Tailwind (telas completas conforme catálogo)
3) PDFs (prova/gabarito/folha OMR com QR)
4) Endpoints para app mobile (sync, upload scan, histórico)
5) Testes (Feature tests cobrindo regras críticas)
6) Documentação curta: como rodar local, como seedar dados e como validar os fluxos principais

---

## 11) Pontos críticos para testes (mínimo)
- Visibilidade: professor não vê questão privada de outro
- Compartilhamento público: só aparece se habilitado e na mesma organização
- Duplicar para editar: cria cópia com `source_question_id`
- Tokens: expiração, single-use, status
- Soft delete: registro some das listas ativas, aparece na lixeira, restaura, e force delete exige permissão + confirmação
- Logs: ações críticas geram eventos (criar/editar/excluir/restaurar/publicar/corrigir/override)

---

## 12) Saída esperada da LLM (Claude/Codex)
Você deve gerar:
- Plano de implementação em etapas
- Estrutura de pastas e arquivos
- Implementação completa (código) seguindo padrões do Laravel
- Telas Blade + Tailwind conforme ZIP (onde possível)
- Testes cobrindo regras RN-04..RN-08, RN-26, RN-27 e tokens

> Importante: sempre que uma ação estiver indisponível por permissão/plano, explicar na UI com tooltip/microcopy.
