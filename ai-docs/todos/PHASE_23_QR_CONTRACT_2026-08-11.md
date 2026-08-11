# Fase 23 — Contrato QR OMR e retrocompatibilidade

**Tarefa:** `QR-001` / `QR-01..03`
**Estado:** aprovado após revisão independente

## Resultado

O Tembo passa a publicar e executar um contrato QR normativo e fail-closed nos três runtimes. O emissor continua em `v5`; não foi criada uma versão artificialmente incompatível. Cartões `v3`, `early-v4`, `full-v4` e `v5` permanecem no envelope documentado, enquanto versões, campos, tipos, geometrias ou assinaturas desconhecidas são rejeitados antes do motor OMR.

O QR continua sendo prova de integridade, não autorização. Laravel autentica o tenant e vincula o payload à Avaliação, cópia, hash, versão impressa, template, página, intervalo e contagem de alternativas. Cópias históricas sem evidência canônica completa podem ser capturadas, mas obrigatoriamente seguem para revisão humana e não recebem nota automática.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Contrato publicado | Allowlists duplicadas e documentação antiga | JSON Schema 2020-12, documento normativo e vetores golden compartilhados | JSON válido e testes nos três runtimes | concluído |
| Retrocompatibilidade | Compatibilidade existia sem matriz única | v3, early/full v4 e v5 formalizados; nenhum v6 criado | Vetores positivos e assinaturas históricas | concluído |
| Fail-closed | Web fazia apenas `JSON.parse`; tipos divergiam | Parsers tipados com allowlist, tipos, limites, assinatura e geometria | Vetores negativos Web/Mobile/PHP | concluído |
| Assinatura e tenant | HMAC já existia, mas não tinha golden cross-tenant | HMAC golden real com chave pública de teste; tenant correto aceita e outro rejeita | `QrPayloadContractTest` | concluído |
| Binding semântico | Template/versão e página não eram confrontados integralmente com a cópia | `PrintedQrBindingService` valida exam/copy/hash/template/version/page/range/rpp/oc | Integração API e casos negativos | concluído |
| Persistência segura | Divergência podia ser percebida tarde | API e Web aplicam binding antes de aceitar a leitura; respostas fora da página falham | Nenhuma página/scan em casos rejeitados | concluído |
| Histórico incompleto | Snapshot parcial podia parecer moderno | Ausência de layout ou questões canônicas marca `legacy_binding` e exige revisão | Scan `reviewing`, score nulo | concluído |
| Mobile offline | Permaneciam helpers de gabarito em texto puro | Caminhos `gab` removidos; payload assinado original é preservado; autenticidade/nota no sync | Typecheck e testes Mobile | concluído |
| Documentação operacional | Seeder e docs prometiam correção autônoma/chaves inexistentes | Modos e limites descritos conforme implementação real | Seeder e `OMR_TEMPLATES.md` atualizados | concluído |

## Alterações técnicas

- `contracts/omr/qr-payload.schema.json`: schema normativo com propriedades adicionais proibidas.
- `contracts/omr/qr-contract.vectors.json`: vetores golden positivos e negativos consumidos por PHP, Web e Mobile.
- `contracts/omr/QR_CONTRACT.md`: matriz de versões, campos e fronteira de confiança.
- `PrintedQrBindingService`: validação HMAC e associação semântica da página impressa.
- `QrCodeSigningService`: tipos exatos, limites, geometria e formatos de assinatura alinhados ao contrato.
- `OfflineOmrQrService` e `ConsolidateAnswersJob`: revalidação no sync, mapeamento por posição impressa e revisão obrigatória para evidência histórica incompleta.
- `OmrController`: binding server-side e rejeição de respostas fora de `qs..qe` antes da persistência.
- Web OMR: parser tipado antes do worker/engine.
- Mobile: parser alinhado, remoção de correção por `gab` plaintext e mensagens explícitas de validação no servidor.
- `ScanModeSeeder` e documentação OMR: descrição fiel do modo offline.
- Banco/migrations: `N/A`.
- Campos QR físicos, tamanho, quiet zone e nível de correção: não alterados; pertencem a `QR-002`.

## Compatibilidade por versão

| Versão | Leitura estrutural | Verificação servidor | Captura offline | Observação |
| --- | --- | --- | --- | --- |
| v3 | sim | sim | não | histórico, sem geometria moderna assinada |
| early-v4 | sim | sim | não | preserva `tpl`, mas não possui contrato completo de página |
| full-v4 | sim | sim | sim | autenticidade e nota continuam no servidor |
| v5 | sim | sim | sim | emissor atual, assinatura compacta de 128 bits |
| desconhecida | não | não | não | falha fechada em todos os runtimes |

## OMR AUDIT REPORT

- Identidade: `e`, `c` e `h` são opacos e confrontados com entidades tenant-scoped.
- Template: `tpl_id/tpl_v` precisam corresponder à cópia quando conhecidos.
- Página: `p/pt/qs/qe/rpp/oc/g` são validados estruturalmente e, quando existe snapshot canônico, reconstruídos e comparados ao contrato impresso.
- HMAC: o emissor v5 usa 128 bits em base64url; formas históricas aceitas permanecem documentadas.
- Privacidade: PII e `gab` plaintext não pertencem a nenhuma allowlist.
- Web: parser estrutural antecede o engine; HMAC continua exclusivamente no backend.
- Mobile: guarda o payload assinado exato, permite captura apenas com geometria completa e aguarda sync para autenticidade/nota.
- Histórico parcial: preservado para captura assistida, nunca para autocorreção silenciosa.
- Homologação física: não realizada nesta fase e explicitamente reservada a `QR-002`.

## Evidências

- Laravel completo: **529 testes / 2.517 assertivas**;
- revisão independente focada: **39 / 240**;
- Web/Vitest final: **35 / 35**;
- Mobile grid/QR: **13 / 13**;
- Mobile TypeScript, Vite build, Blade cache, Pint e `git diff --check`: aprovados;
- schema e vetores: JSON bem formado; assinaturas golden verificadas no tenant da fixture e rejeitadas em outro tenant.

## Revisão independente

O revisor encontrou dois problemas materiais durante o ciclo:

1. limites/campos opcionais não estavam perfeitamente alinhados nos três parsers;
2. cópia com apenas ID/versão do template, mas sem layout e questões congeladas, ainda poderia ser autocorrigida.

Os parsers foram alinhados e a noção de binding legado passou a exigir evidência canônica completa. O reteste confirmou que snapshot parcial resulta em `reviewing` com score nulo, enquanto identidade conhecida divergente continua fail-closed.

**Parecer final:** `APROVADO`, sem achados críticos, altos ou médios remanescentes.

## Referências técnicas

- JSON Schema Draft 2020-12: https://json-schema.org/draft/2020-12
- NIST SP 800-107 Rev. 1 — truncamento de HMAC: https://tsapps.nist.gov/publication/get_pdf.cfm?pub_id=911479
- OWASP Cryptographic Storage Cheat Sheet — AEAD/AES-GCM: https://cheatsheetsseries.owasp.org/cheatsheets/Cryptographic_Storage_Cheat_Sheet.html

## Pendências reais

- executar `QR-002`: regressão raster, quiet zone, contraste, densidade e leitura em aparelhos/impressoras reais;
- validar diretamente o JSON Schema com meta-schema/AJV pode fortalecer a garantia futura; hoje a paridade executável é coberta pelos vetores compartilhados;
- multipágina Mobile, dataset de câmera e proteção da fila offline continuam nas fases OMR/OFF correspondentes.
