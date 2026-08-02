# RDP + Prompt de Implementação (Claude/Codex)
## Plataforma de Avaliações Online + PDF + Correção Web/Mobile (OMR)

> **Versão:** 2.0 (atualizada)
> **Status:** Documento de handoff técnico-funcional
> **Objetivo:** Servir como base de execução para Claude/Codex (engenharia de software), com regras claras, fluxos narrados, entidades, telas, critérios de aceite e plano de implementação.

---

# 1) Como usar este arquivo no Claude/Codex

Use este arquivo como **fonte principal de requisitos**. Ao implementar:

1. **Não invente regras** fora deste documento sem registrar como suposição.
2. **Priorize segurança e escopo por organização (tenant)**.
3. **Aplique permissões e plano adquirido no backend**, nunca apenas na interface.
4. **Siga os fluxos narrados** (seção 10) como referência de comportamento real do sistema.
5. **Implemente Soft Delete + Lixeira + Log global** conforme RN-26 e RN-27.
6. Em caso de conflito:
   - Regra de negócio > fluxo > tela > microcopy.
7. Se precisar assumir algo, registre em uma seção "Suposições" no código/projeto e destaque para validação humana.

---

# 2) Visão Geral do Produto

Plataforma SaaS educacional para:
- criar banco de questões reutilizável;
- montar avaliações/formulários;
- aplicar online (login e/ou código/token);
- gerar PDF para impressão (prova, gabarito, folha de respostas);
- corrigir online (objetivas + discursivas);
- corrigir provas em papel via app mobile (OMR/leitura de bolhas por câmera);
- emitir relatórios por aluno, turma e questão;
- operar com planos/assinaturas (Free/Pago), ads e tolerância de pagamento (grace period).

---

# 3) Perfis do Sistema (Atualizado)

## 3.1 Perfis principais

### A) Administrador Global (SaaS) *(opcional para operação da plataforma, recomendado)*
Perfil interno da plataforma (empresa dona do sistema), usado para:
- gerenciar catálogo de planos;
- monitorar assinaturas globais;
- webhooks de pagamento;
- logs globais;
- suporte avançado / auditoria.

> Se o projeto não tiver time interno SaaS neste momento, este perfil pode ser reservado para fase futura.

---

### B) **Instituição** (novo perfil solicitado) ✅
Perfil gestor da escola/organização (tenant), com poderes administrativos dentro da própria instituição.

**Pode:**
- cadastrar e gerenciar professores;
- cadastrar e gerenciar turmas;
- cadastrar e gerenciar alunos;
- configurar regras da instituição (tokens, branding PDF, defaults);
- visualizar relatórios institucionais (conforme plano/permissão);
- acompanhar uso do plano adquirido e limites;
- gerir cobrança/assinatura da instituição (quando habilitado);
- acessar lixeira e logs (se permitido pelo plano/permissão).

**Não pode:**
- acessar dados de outras instituições;
- alterar planos globais (isso é do Admin Global);
- burlar trilha de auditoria.

> Observação: este perfil substitui, na prática, o "Admin" por organização da versão anterior. Se desejar, manter o nome visual "Administrador" e usar o papel técnico `institution_admin`.

---

### C) Professor (com permissões granulares)
Cria questões, monta avaliações, publica, corrige e analisa resultados.

**Pode (conforme ACL + plano):**
- criar e editar questões próprias;
- ver questões compartilhadas;
- duplicar questões compartilhadas;
- compartilhar questões próprias (inclusive **público**, se permitido);
- criar avaliações;
- gerar PDF;
- gerar códigos/tokens;
- corrigir provas e ver relatórios.

---

### D) Aluno
Responde avaliações online (logado ou por código), acompanha resultados quando liberados.

---

## 3.2 Precisão por plano adquirido (nova exigência) ✅
O sistema deve aplicar **controle preciso por plano adquirido** em três camadas:

1. **Features (liga/desliga):** ex. gerar PDF, scanner OMR, relatórios avançados, API.
2. **Limites quantitativos:** ex. nº máximo de professores, turmas, alunos, avaliações/mês, PDFs/mês, scans/mês.
3. **Escopo de compartilhamento:** ex. compartilhamento público habilitado apenas em planos específicos.

### Regra de precedência (precisa e determinística)
O acesso efetivo deve ser calculado assim:

`Acesso Efetivo = Plano Ativo + Overrides da Assinatura + Permissões do Usuário`

Onde:
- **Plano Ativo** define o teto base;
- **Overrides** (manuais) ajustam temporariamente features/limites da assinatura;
- **Permissões do Usuário** refinam o que cada pessoa pode fazer dentro do que o plano permite.

### Exemplos práticos
- Se o plano **não** tem `mobile_omr_scanning`, o professor **não** pode escanear, mesmo que tenha permissão de correção.
- Se o plano permite gerar PDF, mas o professor não tem `exam.generate_pdf`, ele **não** pode gerar PDF.
- Se o plano permite compartilhar publicamente, mas o professor não tem `question.share_public`, ele **não** vê essa opção.

---

# 4) Escopo (Atualizado)

## 4.1 Em escopo
1. Autenticação (login, logout, recuperação de senha).
2. Perfis: Instituição, Professor, Aluno (+ Admin Global opcional).
3. ACL granular (principalmente Professor e Instituição com módulos específicos).
4. Multi-tenant por instituição.
5. Banco de questões com compartilhamento:
   - privado
   - compartilhado com professores específicos
   - **público** (novo)
6. Turmas e alunos (CRUD + import CSV).
7. Avaliações (wizard completo).
8. Aplicação online por login e/ou token.
9. Geração de PDF (prova/gabarito/folha).
10. Correção web + correção mobile OMR.
11. Relatórios básicos.
12. Planos/assinaturas + grace period + ads.
13. Lixeira (soft delete) + restauração + exclusão permanente com confirmação forte.
14. Módulo de Log global e flexível (eventos de usuários e sistema).

## 4.2 Fora de escopo (MVP)
- Proctoring avançado (webcam, IA de fraude, lock de sistema operacional).
- SSO/SAML.
- QTI/IMS completo.
- Marketplace público externo de questões com pagamento/licenciamento (pode haver compartilhamento público interno na plataforma).

---

# 5) Compartilhamento de Questões (Atualizado com "Público") ✅

## 5.1 Modos de visibilidade/compartilhamento
Cada questão deve possuir um `visibility_scope` e regras de acesso:

1. **private** (privada)
   - Somente o dono (professor criador) e a Instituição (se política permitir auditoria)

2. **shared_specific** (compartilhada com professores específicos)
   - Somente professores explicitamente selecionados
   - Sempre somente leitura para receptores

3. **org_public** (pública da instituição)
   - Visível para professores da mesma instituição
   - Somente leitura para quem não é o dono
   - Edição apenas via duplicação

4. **platform_public** (pública na plataforma) ✅ *(novo, sujeito a plano/permissão)*
   - Visível para outras instituições/usuários elegíveis (escopo configurável)
   - Sempre somente leitura para consumidores
   - Edição apenas via duplicação
   - Deve registrar origem (`source_question_id` + `source_owner` + `source_org`)

## 5.2 Regras do compartilhamento público
- O compartilhamento público **só aparece** se:
  - plano da instituição permitir `question_public_sharing`; e
  - usuário tiver permissão `question.share_public`.
- O dono pode remover uma questão do modo público (despublicar), sem apagar cópias já duplicadas.
- Questões públicas devem manter trilha de autoria/origem.
- (Opcional futuro) fila de moderação para conteúdo público.

---

# 6) Permissões (ACL) — Atualização com Instituição e Compartilhamento Público

## 6.1 Permissões do perfil Instituição (sugestão de catálogo)

### Gestão institucional
- `institution.dashboard.view`
- `institution.settings.manage`
- `institution.billing.view`
- `institution.billing.manage`
- `institution.logs.view`
- `institution.trash.view`
- `institution.trash.restore`
- `institution.trash.force_delete`

### Usuários/Professores
- `teacher.view`
- `teacher.create`
- `teacher.edit`
- `teacher.disable`
- `teacher.permissions.manage`

### Turmas/Alunos
- `class.view_all`
- `class.create`
- `class.edit`
- `student.view_all`
- `student.create`
- `student.edit`
- `student.import`

### Relatórios institucionais
- `reporting.institution.view`
- `reporting.export`

---

## 6.2 Permissões do Professor (adicionadas/ajustadas)
Além das existentes, incluir:
- `question.share_public` ✅ (compartilhar questão publicamente)
- `question.view_public` ✅ (ver questões públicas da plataforma, quando habilitado)

> Importante: `question.view_public` e `question.share_public` dependem de plano adquirido + política da instituição.

---

# 7) Planos e Assinaturas (com precisão por plano adquirido)

## 7.1 Features sugeridas para controle por plano
- `ads_enabled`
- `question_bank_enabled`
- `question_sharing_specific_enabled`
- `question_org_public_enabled`
- `question_public_sharing_enabled` ✅
- `question_public_catalog_browse_enabled` ✅
- `online_exam_mode`
- `code_access_mode`
- `export_pdf_exam`
- `export_pdf_answer_key`
- `export_pdf_bubble_sheet`
- `mobile_omr_scanning`
- `advanced_reports`
- `audit_log_access`
- `trash_access`
- `api_access`
- `team_management`

## 7.2 Limites sugeridos por plano
- `max_teachers`
- `max_classes`
- `max_students`
- `max_questions`
- `max_exams_active`
- `max_exam_attempts_month`
- `max_pdf_exports_month`
- `max_omr_scans_month`
- `max_storage_mb`

## 7.3 Precisão operacional (comportamento obrigatório)
Ao atingir limite:
- bloquear a ação específica;
- informar claramente qual limite foi atingido;
- mostrar consumo atual vs limite;
- oferecer CTA de upgrade (quando houver);
- registrar evento no Log (RN-27).

Exemplo de mensagem:
> "Você atingiu o limite de 500 alunos do plano atual (500/500). Faça upgrade para continuar cadastrando."

---

# 8) Regras de Negócio (Atualizadas e consolidadas)

## Escopo, segurança e visibilidade
**RN-01 (Escopo por instituição):** toda entidade principal possui `organization_id` e só pode ser acessada dentro da instituição do usuário.

**RN-02 (Visibilidade de questões):** professor só vê (a) suas questões, (b) questões compartilhadas com ele, (c) questões `org_public` da sua instituição (se feature habilitada), e (d) questões `platform_public` quando plano/permissão permitirem.

**RN-03 (Estados de visibilidade):** `visibility_scope ∈ {private, shared_specific, org_public, platform_public}`.

**RN-04 (Compartilhada é somente leitura):** receptor nunca edita a original.

**RN-05 (Duplicar para editar):** duplicação cria nova questão com novo owner e referência de origem.

**RN-06 (Compartilhar específico):** só com professores válidos (mesma instituição, salvo regra explícita em compartilhamento público).

**RN-06A (Compartilhar público):** só disponível se plano + permissão + política institucional permitirem.

## Avaliações, tentativas e tokens
**RN-07 (Status da avaliação):** `draft`, `published`, `closed`.

**RN-08 (Status da tentativa):** `in_progress`, `submitted`, `graded`.

**RN-09 (Status do token):** `new`, `used`, `expired`, `revoked`.

**RN-10 (Token por aluno+avaliação):** cada aluno tem token único por avaliação (quando modo código estiver ativo).

**RN-11 (Expiração de token):** token deve respeitar `expires_at` ou default da instituição.

**RN-12 (Publicação exige público):** avaliação só publica com turmas/alunos definidos.

**RN-13 (Gabarito PDF separado):** prova e gabarito devem ser exportáveis separadamente.

**RN-14 (Folha de respostas para objetivas):** bubble sheet só contempla questões objetivas.

**RN-15 (OMR com identificação):** scan deve ser associado por QR/versionamento (ou fallback manual com confirmação forte).

**RN-16 (Correção):** objetivas autocorrigem; discursivas exigem correção manual antes da publicação final da nota.

**RN-17 (Import CSV):** requer colunas mínimas e relatório de falhas por linha.

## Auditoria, cobrança e ads
**RN-18 (Auditoria):** ações sensíveis registram ator, data/hora, entidade e antes/depois (quando aplicável).

**RN-19 (Grace period):** `block_date = due_date + grace_days`.

**RN-20 (Status da assinatura):** `PENDING`, `ACTIVE`, `PAST_DUE`, `SUSPENDED`, `CANCELED`.

**RN-21 (PAST_DUE / SUSPENDED):** durante tolerância pode limitar features; após `block_date`, bloqueia conforme política.

**RN-22 (Override manual):** exige motivo obrigatório e log.

**RN-23 (Ads):** anúncios aparecem apenas se `ads_enabled=true`, com identificação visual "Anúncio".

**RN-24 (Rate limit):** login e validação de código devem ter limitação por IP/dispositivo.

**RN-25 (Plano + Permissão):** nenhuma ação é liberada apenas por permissão; plano adquirido também precisa permitir a feature/limite.

## Novas regras solicitadas ✅
**RN-26 (Soft Delete e Lixeira):** todas as entidades elegíveis devem possuir `deleted_at` (Soft Delete) e integrar um sistema de **Lixeira** para armazenar registros desativados. Registros podem ser **restaurados** ou **apagados permanentemente** (hard delete) somente por usuário com permissão apropriada e com validação de segurança ativa (confirmação explícita, ex.: “Tem certeza que deseja apagar este registro permanentemente?”).

**RN-27 (Log global poderoso e flexível):** o sistema deve possuir um módulo de **Log** para monitorar eventos realizados pelos usuários e eventos de sistema em todos os módulos (autenticação, CRUD, compartilhamentos, publicações, correções, cobrança, limites de plano, erros, webhooks, restaurações e exclusões permanentes), com filtros, busca, severidade, contexto e correlação por entidade/usuário.

---

# 9) Módulo de Lixeira e Log (Detalhamento funcional)

## 9.1 Lixeira (Trash)
### Objetivo
Evitar perda acidental de dados e permitir recuperação segura.

### Entidades sugeridas para Soft Delete (MVP)
- questões
- turmas
- alunos
- avaliações (rascunho/encerradas, com restrições)
- tokens (opcional, preferível revogar em vez de deletar)
- usuários (desativar + soft delete sob política)

### Comportamento
- Excluir = move para lixeira (`deleted_at` preenchido).
- Restaurar = limpa `deleted_at` e reativa relacionamentos válidos.
- Excluir permanentemente = hard delete (com confirmação forte + permissão + log).

### Restrições importantes
- Não permitir hard delete se houver integridade histórica crítica sem estratégia (ex.: tentativas/notas já emitidas).
- Em vez de apagar histórico acadêmico, preferir "anonimizar" / "desativar" (validar com política da instituição/LGPD).

---

## 9.2 Log global (RN-27)
### Objetivo
Dar visibilidade operacional e segurança sobre tudo o que acontece no sistema.

### Tipos de eventos (mínimo)
- `auth.login.success`, `auth.login.failed`, `auth.password.reset.requested`
- `question.created`, `question.updated`, `question.shared_specific`, `question.shared_public`, `question.duplicated`, `question.deleted`, `question.restored`, `question.force_deleted`
- `class.created`, `student.imported`, `student.deleted`, `student.restored`
- `exam.created`, `exam.published`, `exam.closed`, `exam.pdf_generated`
- `token.generated`, `token.used`, `token.expired`, `token.revoked`
- `attempt.started`, `attempt.submitted`, `attempt.graded`, `grade.updated`
- `scan.processed`, `scan.sync_failed`
- `subscription.status_changed`, `subscription.override_applied`, `plan.limit_reached`
- `webhook.received`, `webhook.failed`
- `trash.restore`, `trash.force_delete`

### Campos recomendados no log
- `id`
- `timestamp`
- `organization_id`
- `actor_user_id` (nullable para eventos de sistema)
- `actor_role`
- `event_code`
- `severity` (`info`, `warning`, `error`, `security`)
- `entity_type`
- `entity_id`
- `message`
- `context_json` (payload resumido)
- `before_json` / `after_json` (quando aplicável)
- `ip`
- `user_agent`
- `request_id` / `correlation_id`

### Telas do módulo de log (sugestão)
- **LOG-01:** Listagem global com filtros (período, usuário, evento, severidade, módulo)
- **LOG-02:** Detalhe do evento (antes/depois, contexto, correlação)

---

# 10) Fluxos Narrados (Atualizado — narrar o fluxo completo) ✅

> Nesta seção, o fluxo é descrito **de forma narrativa e operacional**, para orientar implementação e UX real.

## 10.1 Fluxo narrado da Instituição (gestor da escola)

A Instituição entra no sistema pela tela de login. Depois de autenticada, ela cai no **Dashboard Institucional**, onde vê um resumo do plano atual, consumo dos limites (professores, turmas, alunos, PDFs etc.) e alertas como pagamento em atraso, limite próximo de ser atingido ou importações com erro.

A primeira ação típica da Instituição é configurar a base: ela acessa o módulo de **Configurações** para ajustar branding dos PDFs (logo/cabeçalho/rodapé), política padrão de expiração de tokens, e preferências gerais de aplicação.

Em seguida, a Instituição vai ao módulo de **Professores** e cadastra os docentes. Para cada professor, define permissões granulares: alguns podem criar e compartilhar questões, outros só aplicar e corrigir. Se o plano adquirido não permitir alguma feature (por exemplo, compartilhamento público), essa opção não aparece ou aparece bloqueada com explicação.

Depois, a Instituição estrutura a operação acadêmica em **Turmas e Alunos**. Ela pode criar turmas manualmente ou permitir que professores façam isso, conforme a organização interna. Ao cadastrar alunos, pode incluir um a um ou importar via CSV. Se houver erros (matrícula duplicada, coluna faltando), o sistema mostra um relatório linha a linha para correção.

Com o sistema em uso, a Instituição acompanha relatórios institucionais e o módulo de **Logs**, onde consegue ver eventos relevantes: quem criou avaliações, quem publicou provas, quem alterou permissões, quem restaurou registros da lixeira e se alguém tentou executar ações sem permissão.

Se a assinatura entrar em atraso, a Instituição vê um **banner global** informando que está em tolerância até determinada data. Durante esse período, o sistema pode restringir recursos avançados. Se o prazo vencer, algumas ações ficam bloqueadas conforme as regras do plano. A Instituição então acessa "Minha Assinatura/Cobrança" para regularizar.

Quando ocorrer exclusão acidental de uma turma, aluno ou questão, a Instituição pode entrar na **Lixeira**, localizar o item, revisar o que foi removido e restaurar. Se precisar apagar definitivamente, o sistema exige permissão específica e confirmação forte, registrando tudo no Log.

---

## 10.2 Fluxo narrado do Professor (banco de questões → avaliação → aplicação → correção)

O Professor faz login e entra no **Dashboard do Professor**. Lá ele vê atalhos para Banco de Questões, Avaliações, Turmas e Correções Pendentes. Se o plano for Free/Basic com anúncios, um espaço identificado como “Anúncio” aparece no topo ou em um card, sem interferir no fluxo principal.

### Etapa 1 — Construção do banco de questões
O Professor abre o **Banco de Questões**. Ele encontra duas áreas principais: suas questões e questões compartilhadas. Se o plano e a política da instituição permitirem, ele também pode ver abas/segmentos de questões públicas da instituição ou públicas da plataforma.

Ao criar uma nova questão, o Professor escolhe o tipo (múltipla escolha, V/F, curta, dissertativa etc.), preenche enunciado, define disciplina, tags, dificuldade e pontuação. Em questões objetivas, o sistema valida se há alternativas suficientes e se existe resposta correta marcada. Em questões discursivas, ele pode cadastrar rubrica e critérios.

Quando deseja colaborar, o Professor abre uma questão própria e escolhe como compartilhar:
- com professores específicos;
- como pública da instituição;
- como pública da plataforma (se o plano permitir).

Se outro professor receber uma questão compartilhada, ele pode visualizá-la, mas não editar a original. O caminho correto é **Duplicar para editar**: o sistema cria uma cópia vinculada à origem e a partir daí a questão passa a ser daquele professor.

### Etapa 2 — Preparação do público (turmas e alunos)
Antes de aplicar provas, o Professor acessa **Turmas** e cria suas turmas. Dentro de cada turma, ele cadastra os alunos manualmente ou importa via CSV. Ao importar, o sistema pede o mapeamento de colunas e mostra uma prévia. Se encontrar erros, não “engole” o problema: apresenta cada linha rejeitada com motivo.

### Etapa 3 — Montagem da avaliação (wizard)
O Professor entra em **Avaliações** e inicia uma nova avaliação usando o wizard.

No **Passo 1**, ele define título, tipo e instruções.
No **Passo 2**, ele seleciona questões do banco (próprias, compartilhadas e públicas conforme acesso).
No **Passo 3**, ele configura como a avaliação será aplicada:
- online e/ou PDF;
- com login, código/token ou ambos;
- tempo limite;
- janela de disponibilidade;
- tentativas;
- embaralhamento;
- versões A/B/C para impressão.
No **Passo 4**, ele vincula turmas e/ou alunos específicos. Se escolheu acesso por código, o sistema oferece a geração de tokens em lote.

Se faltar algo importante (por exemplo, nenhuma questão selecionada, janela inválida, nenhum aluno vinculado), o wizard bloqueia o avanço/publicação e explica exatamente o motivo.

### Etapa 4 — Publicação e operação da avaliação
Ao publicar, a avaliação muda de rascunho para publicada. O Professor é levado para a tela de **Resumo da Avaliação**, onde encontra:
- link da prova online;
- exportação de códigos (CSV/PDF);
- status da janela;
- botão para encerrar a avaliação.

Se a avaliação for impressa, ele pode gerar os PDFs (prova, gabarito e folha de respostas) escolhendo a versão (A/B/C) e as opções desejadas. Se a prova for mista (objetivas + discursivas), o sistema avisa que a folha de respostas cobre apenas as objetivas.

### Etapa 5 — Correção e resultados
Conforme os alunos respondem, as questões objetivas podem ser autocorrigidas. As discursivas entram na **Fila de Correção**. O Professor abre cada submissão, revisa respostas, atribui nota e feedback, e só então publica a nota final.

Se ainda houver discursivas sem nota, o sistema não permite publicar a correção final daquela tentativa e mostra quais itens faltam.

Depois disso, o Professor acessa **Resultados** e **Relatórios** para acompanhar desempenho por aluno, turma e questão, exportando CSV/Excel quando necessário.

---

## 10.3 Fluxo narrado do Aluno (login ou código)

O Aluno pode entrar de duas formas.

### Caminho A — Login
Se a instituição usa contas de aluno, ele entra com e-mail e senha e acessa suas avaliações disponíveis.

### Caminho B — Código/Token
Se a instituição usa aplicação por código, o Aluno acessa a tela “Entrar com Código”, digita o token e o sistema valida:
- se o código existe;
- se pertence a uma avaliação válida;
- se ainda não expirou;
- se ainda não foi usado.

Se estiver tudo certo, o Aluno entra na prova.

### Resolução da prova
Durante a prova, o Aluno vê o enunciado e responde questão por questão. Se houver tempo limite, um contador fica visível. Se o sistema permitir autosave, as respostas são salvas automaticamente.

Quando termina, o Aluno vai para a tela de **Revisão e Envio**, que mostra quantas questões faltam. Ele pode voltar e corrigir respostas antes de confirmar o envio.

Após enviar, o sistema mostra uma confirmação. Se o Professor/Instituição tiver liberado os resultados, o Aluno pode ver a nota e o detalhamento. Caso contrário, verá a mensagem de que o resultado ainda não foi liberado.

---

## 10.4 Fluxo narrado da correção em papel (App Mobile OMR)

O Professor (ou aplicador autorizado) abre o app mobile, faz login e sincroniza as avaliações disponíveis para digitalização. O app pode baixar gabaritos e metadados para uso offline, se essa feature estiver disponível no plano.

Na hora de corrigir, o aplicador seleciona a avaliação (ou o app identifica automaticamente pelo QR da folha), abre o scanner e enquadra a folha na moldura. O app detecta QR, versão da prova e marcações das bolhas.

Se a imagem estiver ruim ou houver marcações ambíguas, o app não segue silenciosamente: ele leva para a tela de **Conferência de Marcações**, onde o usuário revisa e ajusta manualmente o que for necessário.

Depois de confirmar, o app mostra o resultado da digitalização (nota, questões incorretas etc.) e salva. Se estiver offline, guarda em fila local com status pendente. Mais tarde, ao sincronizar, os dados sobem para o servidor e a tentativa aparece no web.

O backend deve tratar esse envio com idempotência para não criar tentativas duplicadas se o app reenviar o mesmo scan.

---

## 10.5 Fluxo narrado de exclusão, lixeira e restauração (RN-26)

Quando um usuário com permissão exclui uma questão, turma ou aluno, o sistema não apaga de vez. Em vez disso, move o registro para a **Lixeira** (Soft Delete). Esse item deixa de aparecer nas telas normais, mas continua recuperável.

Na Lixeira, o usuário autorizado pode filtrar por tipo de registro, data e responsável pela exclusão. Ao abrir um item, ele vê contexto suficiente para decidir:
- **Restaurar** (volta ao estado ativo, respeitando integridade de dados), ou
- **Excluir permanentemente**.

A exclusão permanente exige uma confirmação explícita (ex.: modal com aviso severo e confirmação textual) e só é permitida para usuários com permissão específica. Toda ação de exclusão, restauração e hard delete deve gerar evento no Log global.

---

## 10.6 Fluxo narrado de cobrança e plano adquirido

A Instituição acompanha seu plano em “Minha Assinatura”. Lá ela vê:
- plano atual;
- status da assinatura;
- próximo vencimento;
- data limite de tolerância (`block_date`);
- consumo de limites (professores, alunos, PDFs, scans etc.).

Quando um usuário tenta executar uma ação bloqueada pelo plano (ex.: gerar PDF quando o recurso não está incluso, ou cadastrar aluno acima do limite), o sistema interrompe a operação com uma mensagem clara explicando o motivo e, se aplicável, um CTA de upgrade.

Se a assinatura entrar em `PAST_DUE`, um banner global alerta que a instituição está em tolerância até determinada data. Se passar para `SUSPENDED`, as áreas bloqueadas deixam de funcionar conforme a política, mas sempre com feedback claro — nunca apenas “erro genérico”.

---

# 11) Catálogo de Telas (resumo prático, com foco nos fluxos)

## 11.1 Autenticação
- AUTH-01 Login
- AUTH-02 Recuperar Senha
- AUTH-03 AppShell (layout base autenticado)

## 11.2 Instituição (novo perfil)
- INS-01 Dashboard Institucional
- INS-02 Professores (listagem)
- INS-03 Criar/Editar Professor
- INS-04 Permissões do Professor (ACL)
- INS-05 Turmas (listagem)
- INS-06 Alunos/Turma
- INS-07 Importar Alunos CSV
- INS-08 Configurações da Instituição
- INS-09 Minha Assinatura / Cobrança
- INS-10 Lixeira
- INS-11 Logs

## 11.3 Professor
- PRF-00 Dashboard
- PRF-Q01 Banco de Questões
- PRF-Q02 Criar/Editar Questão
- PRF-Q03 Visualizar Questão
- PRF-Q04 Compartilhar Questão (inclui público)
- PRF-Q05 Duplicar Questão
- PRF-C01 Turmas
- PRF-C02 Criar/Editar Turma
- PRF-C03 Alunos da Turma
- PRF-C04 Criar/Editar Aluno
- PRF-C05 Importar CSV
- PRF-E01 Avaliações (listagem)
- PRF-E02 Wizard de Avaliação
- PRF-E03 Resumo da Avaliação Publicada
- PRF-E04 Gerar PDF
- PRF-G01 Fila de Correção
- PRF-G02 Corrigir Submissão
- PRF-R01 Resultados (overview)
- PRF-R02 Relatório por Turma/Questão
- PRF-R03 Detalhe da Resposta

## 11.4 Aluno
- STU-01 Entrar com Código
- STU-02 Fazer Prova
- STU-03 Revisão e Envio
- STU-04 Confirmação
- STU-05 Resultado

## 11.5 Mobile OMR
- MOB-01 Login
- MOB-02 Selecionar Avaliação
- MOB-03 Scanner
- MOB-04 Conferência de Marcações
- MOB-05 Resultado da Digitalização
- MOB-06 Histórico/Sync

## 11.6 Componentes globais
- CMP-01 Banner de Assinatura (PAST_DUE/SUSPENDED)
- CMP-02 Banner de Anúncio (ads)
- CMP-03 Indicador de limite de plano (consumo/limite) ✅

---

# 12) Entidades e Modelo de Dados (Atualizado)

## 12.1 Entidades novas/ajustadas

### organizations (instituições)
- representa a escola/tenant
- armazena config, plano, branding, políticas

### users
- inclui roles: `global_admin`, `institution_admin`, `teacher`, `student`

### questions (ajuste)
- adicionar `visibility_scope`
- adicionar metadados de origem pública/compartilhamento
- `deleted_at` (RN-26)

### question_shares (ajuste)
- suportar tipos de compartilhamento (`specific`, `org_public`, `platform_public`)

### trash_actions (opcional)
- histórico específico de lixeira (pode ser coberto por logs, mas é útil)

### system_logs / event_logs ✅
- módulo RN-27 (separado de audit_logs simples)
- pode coexistir com audit_logs ou substituí-lo

## 12.2 Campos mínimos novos importantes

### `questions`
- `visibility_scope` (`private|shared_specific|org_public|platform_public`)
- `is_public_active` (bool, opcional se usar apenas scope)
- `deleted_at`

### `classes`, `students`, `exams`, `users` (quando aplicável)
- `deleted_at`

### `event_logs`
- ver seção 9.2 (campos recomendados)

---

# 13) Contratos funcionais (resumo)

## 13.1 Compartilhar questão publicamente ✅
**Ação:** publicar questão em catálogo público (instituição/plataforma)

**Quem:** Professor (com permissão) ou Instituição (se gestão centralizada)

**Pré-condições:**
- plano permite `question_public_sharing_enabled`
- usuário tem `question.share_public`
- questão é própria (owner)

**Entradas:**
- `question_id`
- `public_scope`: `org_public` ou `platform_public`
- (opcional) tags públicas / categoria pública

**Saídas:**
- status sucesso
- nova visibilidade da questão

**Erros comuns:**
- plano não permite
- sem permissão
- questão não pertence ao usuário

**Logs:**
- `question.shared_public`

---

## 13.2 Lixeira (restaurar / excluir permanente) ✅

### Restaurar registro
- **Quem:** Instituição/Admin com `*.trash.restore`
- **Entrada:** tipo entidade + id
- **Saída:** registro restaurado
- **Log:** `trash.restore`

### Excluir permanentemente
- **Quem:** Instituição/Admin com `*.trash.force_delete`
- **Entrada:** tipo entidade + id + confirmação explícita
- **Saída:** registro removido definitivamente (se permitido)
- **Validação:** integridade + permissão + confirmação forte
- **Log:** `trash.force_delete`

---

## 13.3 Log global (consulta)
- **Quem:** Instituição/Admin/Global Admin com `logs.view`
- **Filtros:** período, usuário, severidade, módulo, evento, entidade
- **Saída:** lista paginada + detalhe de evento

---

# 14) Checklist de Aceite (Atualizado)

## 14.1 Perfil Instituição ✅
- Dado um usuário `institution_admin`, quando faz login, então vê o dashboard institucional e pode cadastrar professores, turmas e alunos dentro da sua instituição.
- Dado um `institution_admin`, quando tenta acessar dados de outra instituição, então recebe acesso negado.

## 14.2 Precisão por plano adquirido ✅
- Dado um plano sem `mobile_omr_scanning`, quando o professor tenta acessar o scanner, então o sistema bloqueia com mensagem de plano.
- Dado um plano com limite de 100 alunos e consumo 100/100, quando a Instituição tenta cadastrar mais 1 aluno, então a ação é bloqueada e o sistema mostra consumo vs limite.
- Dado um usuário com permissão para gerar PDF, mas plano sem `export_pdf_exam`, quando ele tenta gerar PDF, então a ação é bloqueada por plano.

## 14.3 Compartilhamento público ✅
- Dado professor com plano e permissão adequados, quando compartilha uma questão como `platform_public`, então a questão passa a aparecer para usuários com `question.view_public`.
- Dado um receptor de questão pública, quando tenta editar a original, então o sistema bloqueia e orienta a duplicar.

## 14.4 Soft Delete e Lixeira (RN-26) ✅
- Dado uma questão excluída, quando o usuário abre o banco de questões, então a questão não aparece na listagem padrão.
- Dado uma questão excluída, quando um usuário com permissão abre a lixeira, então consegue restaurar a questão.
- Dado um usuário sem permissão de hard delete, quando tenta excluir permanentemente um item da lixeira, então a ação é negada.
- Dado um usuário com permissão de hard delete, quando exclui permanentemente, então o sistema exige confirmação forte e registra log.

## 14.5 Log global (RN-27) ✅
- Dado uma ação relevante (ex.: publicação de avaliação), quando concluída, então um evento correspondente aparece no módulo de logs.
- Dado uma tentativa de login inválida, então um evento de log é registrado com severidade adequada.
- Dado um filtro por usuário e período no módulo de logs, então a listagem retorna apenas eventos correspondentes.

---

# 15) Plano de Implementação (ordem sugerida para Claude/Codex)

## Fase 1 — Base e segurança
- Auth + AppShell
- Multi-tenant por instituição
- ACL base (papéis + permissões)
- Logs mínimos de autenticação

## Fase 2 — Perfil Instituição (novo)
- Dashboard institucional
- CRUD professores
- ACL professor
- CRUD turmas/alunos + import CSV
- Configurações da instituição

## Fase 3 — Banco de questões (com compartilhamentos)
- CRUD questões
- compartilhamento específico
- `org_public`
- `platform_public` (com gating por plano/permissão)
- duplicação rastreável

## Fase 4 — Avaliações e aplicação online
- Wizard de avaliação
- publicação
- tokens
- fluxo aluno (resposta/revisão/envio)

## Fase 5 — Correção, resultados e relatórios
- autocorreção objetivas
- correção discursiva
- resultados e relatórios

## Fase 6 — PDF + OMR mobile
- geração PDF prova/gabarito/folha com QR
- contratos backend para mobile scanner
- sync/scans/idempotência

## Fase 7 — Planos/assinaturas com precisão por plano
- features/limits enforcement
- painéis de cobrança
- grace period + banner
- ads placements
- logs de limites atingidos

## Fase 8 — Lixeira + Log global (hardening)
- Soft Delete em entidades alvo
- telas da lixeira (restaurar/hard delete)
- módulo de Log global com filtros e detalhe
- cobertura de eventos RN-27

---

# 16) Suposições registradas (para validação humana)

1. O perfil "Instituição" será implementado como `institution_admin` (admin por tenant).
2. O perfil "Administrador Global" é opcional, mas recomendado para operação SaaS.
3. Compartilhamento público `platform_public` significa catálogo público dentro da plataforma (não necessariamente aberto na internet anônima).
4. Nem todas as entidades permitirão hard delete se houver histórico acadêmico crítico; pode haver política de anonimização.
5. O app mobile pode ser implementado em stack separada; este documento define principalmente contrato/fluxo e backend de suporte.

---

# 17) Prompt final de execução para Claude/Codex (copiar e usar)

Implemente a plataforma descrita neste documento como um sistema multi-tenant para instituições de ensino, com perfis `institution_admin`, `teacher` e `student` (e opcionalmente `global_admin`), seguindo rigorosamente as Regras de Negócio (RN-01 a RN-27), com foco especial em:

1. controle de acesso por organização + permissão + plano adquirido;
2. banco de questões com compartilhamento privado, específico, público da instituição e público da plataforma (sempre com edição por duplicação);
3. fluxo completo de avaliação online e por token;
4. geração de PDFs (prova/gabarito/folha) e suporte a correção mobile OMR;
5. Soft Delete + Lixeira (RN-26) com restauração e hard delete seguro;
6. módulo de Log global poderoso e flexível (RN-27);
7. mensagens de erro claras, estados de UI consistentes e logs para eventos críticos.

Antes de codar, gere:
- um plano de implementação em etapas;
- estrutura de entidades/tabelas;
- lista de rotas/telas;
- matriz de permissões e gating por plano;
- checklist de testes baseado na seção 14.

Se precisar assumir algo que não está explícito, registre como “Suposição” e destaque para validação.

---

## Fim do documento
