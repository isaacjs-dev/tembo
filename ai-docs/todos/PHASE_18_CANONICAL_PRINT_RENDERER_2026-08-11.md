# Fase 18 — Renderizador canônico de Avaliações

**Tarefa:** `APP-002` / `APP-05..07`  
**Estado:** aprovado

## Resultado

A Avaliação impressa passou a ser representada por um documento canônico, produzido a partir do snapshot de questões, mapas de embaralhamento, recursos versionados, aparência e contexto de identidade. O preview HTML, o PDF simples e a seção de Avaliação do lote avançado usam os mesmos partials Blade e o mesmo contrato, com hash SHA-256 reproduzível.

Cópias novas preservam também título, professor, instituição, disciplina, aluno, matrícula, turma e número da cópia. Alterar ou remover depois uma questão, opção, recurso, template ou nome vivo não altera o documento histórico.

## Matriz de rastreabilidade

| Requisito | Situação anterior | Implementação | Evidência | Status |
| --- | --- | --- | --- | --- |
| Renderizador único | Preview Alpine e PDFs Blade divergentes | DTO/documento canônico e partials compartilhados | Testes de preview, PDF simples e lote | concluído |
| Histórico reproduzível | PDF ordenava pelo mapa, mas lia models vivos | Conteúdo, recursos, mapas, aparência e contexto lidos do snapshot da cópia | Teste altera/destaca questão e renomeia entidades após a geração | concluído |
| Tokens seguros | Definições aceitavam arrays livres | Catálogo fechado de tokens e schemas por contexto | Tokens, chaves, CSS e URLs desconhecidos rejeitados | concluído |
| Assets seguros | Paths e metadados não eram normalizados no versionamento | MIME raster, tamanho, hash, disco e path validados; recursos só viram data URI após conferência | Traversal, SVG, URL remota e hash inválido rejeitados | concluído |
| Preferências | Hash podia mudar sem efeito visual | Termo, valor, marcador e separador renderizados pelo canônico | Teste de preferências | concluído |
| Paginação | `page-break-inside: avoid` podia estourar questão longa | Questões grandes permitem quebra e layout de duas colunas cai para uma coluna | PDF longo e fallback de coluna testados | concluído |
| Segurança | Preview canônico inexistente | Endpoint autor/tenant, CSP restritiva, escaping contextual e Dompdf endurecido | Testes IDOR/XSS e configuração do renderer | concluído |
| Compatibilidade OMR | Regra `@page` do layout poderia alterar geometria do cartão | Lote combinado preserva uma única regra A4 portrait/10 mm; layout de página aplica-se ao PDF exclusivo da Avaliação | Pipeline OMR/PDF e teste combinado | concluído |

## Alterações técnicas

- `ExamQuestionSnapshotService`: fonte única do snapshot de questões e recursos;
- `AssessmentPrintContextService`: snapshot de identidade e campos dinâmicos;
- `AppearanceDefinitionSchema`: normaliza layouts, cabeçalhos e assets em envelope fechado;
- `AppearanceTokenResolver`: resolve somente tokens autorizados e aplica fallback explícito;
- `CanonicalPrintDocumentService`: valida mapas/tipos, resolve recursos, preferências, contexto e hash;
- partials `exams.print.*`: HTML/CSS Dompdf-safe compartilhado entre preview e PDF;
- `ExamPrintService`: grava `render_context` por cópia no snapshot v2;
- `ExamController`: export simples e lote usam o documento canônico; preview recebe CSP e autoria/tenant;
- `pdf_advanced`: apenas a seção da Avaliação foi migrada; cartão e gabarito mantêm os contratos existentes;
- tela de edição: iframe sandboxed mostra o preview real e explica que embaralhamento/individualização acontecem na geração do lote.

## Banco, APIs e compatibilidade

Não houve migration nem alteração de API, QR ou formato Mobile. O campo JSON `exam_copies.template_snapshot`, introduzido e versionado em `APP-001`, recebeu aditivamente `render_context`. Cópias antigas continuam legíveis com fallback vivo explicitamente identificado como `legacy_copy_with_live_context`; elas não são declaradas integralmente imutáveis.

QR v3/v4/v5, geometria OMR, cartão-resposta, gabarito, `card_template_id/version` e contratos offline foram preservados.

## Evidências

- suíte Laravel completa: **498 testes / 2.114 assertivas**, aprovada;
- regressão independente focada: **73 / 425**, aprovada;
- testes dedicados APP-002: **12 / 52**, aprovados;
- JavaScript/Vitest: **11 / 11**, aprovado;
- Vite build: aprovado;
- Mobile TypeScript: aprovado;
- Mobile grid/QR/snapshot: **8 / 8**, aprovado;
- Blade `view:cache`: aprovado;
- Pint dos arquivos afetados: aprovado;
- `git diff --check`: aprovado.

Uma primeira rodada ampliada concorrente teve interferência no storage de testes e no cache Blade. Os testes afetados foram reexecutados sem concorrência e passaram; a suíte completa exclusiva confirmou o resultado final.

## Revisão independente

O primeiro parecer reprovou a implementação por dependência de nomes vivos no histórico, preferências sem efeito visual, preview confundido com lote embaralhado, cabeçalho sem altura efetiva, tipo desconhecido aceito como discursivo, matrícula incorreta, risco de duas colunas com questão longa e conflito global de `@page` com o cartão OMR.

Todos os achados foram corrigidos na causa e receberam regressão dedicada.

**Parecer final:** `APROVADO`, com **73 testes / 425 assertivas**, Pint e diff-check verdes e nenhum achado bloqueante remanescente.

## Pendências reais

- homologação física A4, impressoras, logos e equivalência visual fina permanece em `APP-003`, `PREV-001`, `CARD-001/002` e `QR-002`;
- o renderizador local antigo permanece inativo em `x-show="false"` até a limpeza visual de `PREV-001`;
- assets de templates são validados e versionados, mas a galeria/logo editável pertence a `APP-004`;
- o layout de página da Avaliação é aplicado integralmente no PDF exclusivo; no lote combinado, a geometria OMR A4 portrait tem precedência deliberada.
