# Fase 0 — Segurança multi-tenant e contratos OMR

**Data:** 2026-08-08

**Branch:** `feat/phase-0-security-contracts`

**Parecer:** APROVADO COM PENDÊNCIA HUMANA

## Resultado

A fase fecha o escopo P0 automatizável: contexto institucional fail-closed, membership sem alteração da conta global, validações tenant-aware, QR v3/v4/v5 com contrato explícito, imagens OMR privadas, upload idempotente e mapeamento Mobile single-page coerente com `rpp`, `qs/qe` e embaralhamento.

O parecer não constitui homologação física do OMR. Fotografias reais, impressoras, celulares suportados, dataset anotado, multipágina Mobile e fluxo offline completo pertencem às fases posteriores e permanecem pendentes.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Arquivos principais | Testes | Revisor | Status |
| --- | --- | --- | --- | --- | --- | --- |
| IAM-02/IAM-11 — contexto e isolamento | Contexto nulo ou FK legado podia produzir consulta sem escopo ou ultrapassar membership | Tenant scope fail-closed; membership ativa passa a ser autoritativa; contexto inválido recebe 403 | `User.php`, `TenantScope.php`, `EnsureActiveAccount.php` | `TenantIsolationHardeningTest` e regressão completa | Orquestrador após implementação do agente backend | APROVADO |
| Conta global versus vínculo | Administração institucional alterava identidade/senha/status global ao editar vínculo | Edição institucional tornou identidade somente leitura; ativação/desativação atua apenas na pivot e preserva outros tenants | `StudentController.php`, `TeacherController.php`, `OrganizationMembershipService.php`, views | testes de preservação de senha, status e memberships | Orquestrador | APROVADO |
| Relações tenant-aware | Taxonomias, turmas, alunos, professores e compartilhamentos aceitavam IDs externos em alguns fluxos | Regras e queries validam organização/membership; sincronização de turmas preserva vínculos de outros contextos | `QuestionController.php`, `SchoolClassController.php`, `ActiveOrganizationMember.php` | testes negativos cross-tenant | Orquestrador | APROVADO |
| QR-01..03 — contrato e compatibilidade | Fallback legado permissivo e confusão entre `v` e `tpl_v`; campos modernos podiam ser adulterados | Allowlist por versão, HMAC obrigatório, v4 histórico com `tpl` preservado, v5 completo e sem downgrade | `QrCodeSigningService.php`, `OfflineOmrQrService.php`, `qr-parser.ts`, `qr-validator.ts` | PHP de assinatura/adulteração e Mobile de v4/v5 | Orquestrador após implementação do agente OMR | APROVADO |
| Imagens OMR privadas | Novos scans e páginas usavam disco público e caminhos apareciam em JSON | Escrita no disco local, endpoints autenticados, paths ocultos, catch-all público bloqueado e migration com checksum antes de remover origem | controllers OMR, models, routes, migration `000110` | acesso próprio/tenant externo, colisão de arquivo e compatibilidade histórica | Orquestrador | APROVADO |
| OMR-07/OFF-02..04 — idempotência | Retry dependia apenas de sessão/página e não distinguia conteúdo conflitante | Chave única por organização/uploader, fingerprint canônico, resposta determinística e 409 para reuso divergente | `OmrApiController.php`, `OmrScanPage.php`, migration `000100` | retry igual, ordem JSON equivalente e conflito | Orquestrador | APROVADO |
| OMR-01/06/10 — grid e shuffle Mobile | `v`, `tpl_v` e capacidade de grid eram confundidos | Geometria assinada usa `rpp`; `qs/qe` recorta a página; helpers puros convertem posição impressa e opções embaralhadas | `grid-mapping.ts`, `answer-mapping.ts`, telas de scan e tipos | 6 testes Mobile, incluindo 40/50 questões e shuffle | Orquestrador | APROVADO |
| Confirmação humana | Endpoint legado aceitava path de storage e reprocessamento podia confirmar/lançar nota | Endpoint sem consumidores removido; reprocessamento fica `reviewing`, valida imagem e registra auditoria | `OmrController.php`, `UpdateOmrScanLocalRequest.php`, routes | segurança de rota, status, ausência de nota e audit log | Orquestrador | APROVADO |

## Alterações técnicas

- Banco: duas migrations aditivas; nenhuma exclusão de dados. O rollback da migration de privacidade não torna arquivos públicos novamente.
- API: páginas OMR expõem `image_url` autenticada, não `image_path`; retry conflitante retorna `409 IDEMPOTENCY_CONFLICT`.
- Web: imagens e páginas são servidas apenas após tenant/permissão OMR; edição de membro não redefine conta global.
- Mobile: contrato QR e template são campos distintos; este build rejeita explicitamente cartões multipágina em vez de processá-los incorretamente.
- Segurança: contexto deriva do usuário autenticado e membership; o FK legado só é aceito para contas que ainda não possuem qualquer pivot.

## Evidências

- `php artisan test --compact`: 352 testes, 1.142 asserções, aprovado.
- `npm run test:js`: 11 testes, aprovado.
- `npm run build`: aprovado.
- `npm run test:e2e`: 6 cenários Chromium desktop/mobile, aprovado.
- `npm run test:grid`: 6 testes de contrato/grid/mapeamento, aprovado.
- `npm run typecheck`: aprovado.
- `npx expo-doctor`: 18/18, aprovado.
- `composer validate --no-check-publish`: aprovado.
- `vendor/bin/pint --dirty` e `git diff --check`: aprovados.

## Revisão independente

As implementações iniciais de backend/tenancy e OMR foram produzidas por agentes distintos. O Orquestrador realizou revisão separada, encontrou e corrigiu: bypass por FK legado após membership, colisão destrutiva na migration, rejeição indevida do campo `tpl` em cartões v4, fingerprint não canônico e endpoint que aceitava path privado controlado pelo cliente.

Os dois agentes convocados para a revalidação final não concluíram por limite de uso da ferramenta. Por transparência, o parecer permanece **APROVADO COM PENDÊNCIA HUMANA**, embora toda a validação automatizada disponível esteja verde.

## Pendências reais

- Executar migrations em ambiente de homologação com backup e observar colisões preservadas pela migration.
- Homologar QR e OMR em papel, impressoras e celulares reais.
- Criar dataset anotado, baseline e holdout antes de declarar precisão do motor.
- Implementar sessão multipágina Mobile e fluxo offline E2E; o build atual bloqueia multipágina com mensagem explícita.
- Continuar por `IAM-002`, sem tratar o restante do roadmap como concluído.
