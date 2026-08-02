# Relatório de Análise Técnica e Quality Assurance (QA)
**Projeto:** SaaS Avaliation On P/A

---

## 1. Resumo Executivo

O sistema **Avaliation On P/A** é uma plataforma SaaS educacional inovadora com arquitetura baseada no ecossistema Laravel. O projeto tem como foco principal a digitalização do fluxo de avaliações acadêmicas, contemplando testes online (Portal do Aluno) e correção automatizada baseada em papel (OMR - Optical Mark Recognition).

A plataforma adota uma interface padronizada (DuoAdmin) com Tailwind CSS e Alpine.js, promovendo uma experiência moderna e reativa. Em um nível técnico, a estrutura base atende bem ao modelo relacional com separação de perfis (Global Admin, Institution Admin, Teacher e Student). Contudo, a lógica core (Multi-tenancy e OMR) ainda carece de amadurecimento arquitetural (ex: extração de lógicas densas para Serviços, uso de escopos globais e implementação de rotinas de testes automatizados com Pest/PHPUnit).

Apesar da consistência visual e navegação fluida, identificam-se **riscos críticos** no isolamento de dados entre os inquilinos (instituições) e na fragilidade do ecossistema de testes (realizados via scripts PHP procedurais improvisados ao longo da construção).

---

## 2. Análise QA por Funcionalidade

| Funcionalidade | Status | Risco | Sugestões QA & Cenários Adicionais de Validação |
|:---|:---:|:---:|:---|
| **Gestão de Planos & Assinaturas (Admin)** | ✅ Correta | Médio | Testes Positivos: CRUD de Planos. Borda: Deletar plano com instituições ativas referenciando-o. (Soft Delete mandatório). |
| **Dashboards Dinâmicos** | ✅ Corretos | Baixo | Recentemente refatorados para queries reais. Cenário Positivo: Verificação de `200 OK` e cargas corretas. Borda: Admin inst. sem professores (Empty states). |
| **Captura OMR via Webcam** | ⚠️ Revisar | Alto | Fluxo funcional (`getUserMedia` -> Canvas -> Upload), mas suscetível a timeout na correção OMR síncrona, e falhas em dispositivos com pouca resolução. Necessário processamento assíncrono em Fila (Queue) e tratamento robusto para imagens borradas/tamanho excedido. |
| **Portal do Aluno (Resolução Online)** | ✅ Correta | Médio | Filtro via turmas (`whereHas('schoolClasses')`) está sólido. Cenário Negativo: Aluno tentando burlar timer modificado visivelmente no DOM. Borda: Tentativa de re-submit (`ExamSubmission` dupla). |
| **CRUD Professores/Turmas/Alunos** | ✅ Correta | Alto | Isolamento multi-tenant manual (`where('organization_id', auth()->user()->organization_id)`). **Risco Crítico**: Desenvolvedor esquecer a query no Controller e gerar vazamento de dados inter-escolas (IDOR). |

**Prioridade de Correção imediata**: [Alta] - Introdução de processamento assíncrono para OMR e Global Scopes para os tenants.

---

## 3. Skill 1 — Contexto e Dados do Processo

- **Objetivo Geral:** Oferecer uma suíte educacional digital acessível (gratuitamente monetizada com ads) e robusta (versões Premium) para criação, distribuição e correção de gabaritos físicos/digitais.
- **Regras de Negócio Chave:** O sistema tem restrições rigorosas baseadas em _Tiers_ (Planos). Por exemplo: "Exportar em PDF" pode ser um Feature Flag do plano Pro.
- **Estrutura de Acesso (RBAC):**
  - *Global Admin:* Gestão global do SaaS (Planos).
  - *Institution Admin:* Gestão de faturamento, professores, turmas e logs.
  - *Teacher:* Criação de avaliações, visualização atrelada à organização.
  - *Student:* Consome testes apenas das turmas as quais foram matriculados.

- **Entradas:** Gabaritos escaneados (WebP/JPEG/PNG via câmera ou upload), dados de matrículas, cadastros.
- **Saídas:** Dashboards, arquivos PDF processados, resultados com Score, histórico em lixeira (Trash/SoftDelete).

---

## 4. Skill 2 — Forma de Implementação (Como foi programado)

- **Backend:** Laravel 10/11 como orquestrador MVC. Uso explícito de Facades, Eloquent Models para o relacional e `artisan tinker` manual como plataforma adjacente de testes.
- **Frontend:** Uso do Motor Blade combinando com componentes X-Blade para reuso (`x-app-layout`). A reatividade é administrada de forma esparsa e funcional via **Alpine.js**. O compilador utilizado é o Vite (`npm run build`). Algumas views misturam lógicas condicionais complexas da UI diretamente no Blade.
- **Inconsistências Técnicas & Acoplamento:**
  - **Controllers Extensos:** `OmrController` concentra a regra de negócio do OCR/Gabarito e do upload.
  - **Identificação Multi-Tenant:** Atualmente implementada manualmente nas clauses `where('organization_id', $orgId)`. É uma forma propensa ao erro humano.
  - **Testes Procedurais (Anti-pattern):** A verificação pós-mudança ocorre criando/usando scripts na raiz (`test_dashboards.php`, `test_all.php`). O Laravel dispõe do Pest e do PHPUnit, o uso do ecossistema de testes do Framework é fundamental para a saúde a longo prazo.

---

## 5. Skill 3 — Diretrizes de Trabalho ("Maneira de Operar")

Para escalar e dar manutenção preditiva e sustentável ao *Avaliation On*, é necessário padronizar as diretrizes do time de dev e DevOps:

1. **Test-Driven / Behavior-Driven Flow:**
   - Adotar o `Pest` ou o `PHPUnit` nativamente. O comando de Continuous Integration deverá rodar `php artisan test` garantindo cobertura do Controller, Models de Relacionamento e Fluxos de Login/Dashboards.
   - Script como `test_all.php` devem ser convertidos em **Feature Tests** do Laravel: `php artisan make:test DashboardTest --pest`.
2. **Design Patterns Exigidos:**
   - *Services Layer:* Retirar lógicas de negócio dos Controllers (O Processamento da Imagem OMR deve morar em um `app/Services/OmrScannerService.php`).
   - *Form Requests:* Padronizar TODA validação via classes `App\Http\Requests\NomeDoRequest.php`.
   - *Action Classes:* Para Single-Responsibility, como `AssignStudentToClassAction`.
3. **Fluxos de Deploy e Manutenção:**
   - Estabelecer ambientes segregados: DEV (Local), STAGING (Deploy espelhado) e PRODUCTION.
   - Todo PR (Pull Request) para a Main/Master só avança se passar nos Linting e nos Testes.

---

## 6. Revisão Técnica de Código (Frontend/Backend)

### 6.1 Backend (PHP/Laravel)
- **Positivo:** Os relacionamentos de banco foram corretamente declarados (`belongsTo`, `hasMany`), como exemplificado em `SchoolClass` e `User`.
- **Alerta (N+1 Queries):** Há risco no carregamento do feed de atividades recentes (`$recentActivities = ExamSubmission::with(['user', 'exam'])`). Embora esteja sendo utilizado o Eager Loading (`with`), é preciso manter cautela com carregamentos mais complexos nos Dashboards num futuro próximo.
- **Multi-tenancy Risco:** O uso de `$orgId = auth()->user()->organization_id;` espalhado pelos métodos repetidamente. 

### 6.2 Frontend (Blade/Tailwind/Alpine)
- **Positivo:** Uso excepcional da semântica tailwind unificando o design system. Implementação inteligente via `@stack('scripts')` no `app.blade.php` e `@push('scripts')` nas views.
- **Alerta (Poluição JS/HTML):** A lógica de WebRTC (`getUserMedia`), Canvas fallback, e Upload dentro da view `webscan.blade.php` (com ±300 linhas de HTML/JS encadeado) está acoplada e densa. Deveria ser modularizada para um arquivo estático `.js` compilado via mix ou injetado via componente Alpine mais limpo.

---

## 7. Refatorações Propostas

1. **Implementar Global Scopes para Organization ID**:
   Criar um escopo, exemplo `TenantScope`, anexado aos Models dependentes (`Exam`, `SchoolClass`, `StudentProfile`) no método `boot()`.
   ```php
   protected static function booted() {
       static::addGlobalScope(new TenantScope);
   }
   ```
   *Impacto: Elimina o risco de IDOR (Insecure Direct Object Reference) com um ganho imediato de legibilidade.*
2. **Refatorar Rotina OMR (Optical Mark Recognition)**:
   Mover validações complexas e lógicas de upload para um Job processado de modo *Assíncrono* em *Background* (`Queue/Jobs`).
3. **Extrair JS do WebScan**:
   Mover a API das Câmeras (JavaScript Puro) para dentro de `resources/js/omr-scanner.js` e ativá-lo no Vite do Laravel.

---

## 8. Testes e Validação Pós-Mudança

- Migrar o mapeamento atual (`test_all.php`) para o nativo **Pest** (ex: `pest tests/Feature/DashboardAccessTest.php`).
- **Cenários de Teste Essenciais (Negative Path):**
  - `Teacher` tentanto acessar API endpoint do Dashboard via `GET /admin/plans` esperando HTTP 403 Forbidden.
  - Submissão de um form de `WebScan` do ALUNO-A feito via token no ALUNO-B (Teste de integridade e Auth no OMR).

---

## 9. Checklist de Segurança Pré-Deploy

* [ ] **IDOR Vulnerabilities** — Certificar que toda requisição de delete/update valida a qual Instituição o registro (turma, ID, prova) pertence, se os Global Scopes omitirem essa averiguação nas edições.
* [ ] **Proteção CSRF** — Validar nas requisições do Portal do OMR se as diretivas `@csrf` operam perfeitamente na submissão do form formData do JS.
* [ ] **Validação em Back-end** — Confirmar se extensões de Imagens submetidas na view `webscan` estão filtradas sob Strict Type check no server-side para impedir Web Shell scripts disfarçados de imagem (.php).
* [ ] **Sanitização XSS:** Confirmar escapes do blade (uso correto de `{{ }}` invés de `{!! !!}`) em títulos de Provas originados pelo usuário, já praticado atualmente pelo padrão do Framework.
* [ ] **Segredos & Arquivos:** Mudar a variável de ambiente `.env` `APP_DEBUG=true` para `false` *terminantemente*. Desativar Telescope se habilitado para evitar vazamentos em Produção.

---

## 10. Plano de Ação Prioritário

| Período | Ação Sugerida | Fator/Valor Entregue |
| --- | --- | --- |
| **Curto Prazo (Sprint 4)** | 1. Implementar Multi-tenant via pacote (ex: Stancl/Tenancy) ou Single DB Global Scopes.<br> 2. Substituir `test_all.php` pelos Tests do Laravel. | Fechamento de brechas Críticas de vazamento de dados inter-escolas; Pipeline limpa. |
| **Médio Prazo** | 1. Refatorar o `webscan.blade.php` separando UI de lógicas JS e OMR pesadas; <br> 2. Converter processamento OMR para Laravel Horizon (Background Queue/Workers). | Escalabilidade; Reduzir chance de "Timeout 504" caso OMR engasgue no volume. |
| **Médio Prazo** | Setup de CI/CD (GitHub Actions) exigindo rodar `php artisan test` e `npm run build` antes de todo PR. | Prevenção de quebra de views e bugs nos commits. |
