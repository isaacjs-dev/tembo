# Tembo

Plataforma SaaS educacional multi-instituição para criar, aplicar e corrigir avaliações on-line, impressas e híbridas. O sistema reúne banco de questões, provas com versões seguras, leitura OMR, correção por rubrica, relatórios pedagógicos, materiais de revisão e acompanhamento familiar.

## O que está incluído

- Perfis de administrador global, gestor institucional, professor, aluno e responsável.
- Isolamento por organização, RBAC dos cinco perfis fixos, contas ativas, lixeira e trilha de auditoria.
- Banco de questões com taxonomia curricular, habilidades, filtros, duplicação e validações por tipo.
- Provas on-line, impressas e híbridas com disponibilidade, tempo, tentativas, embaralhamento determinístico e liberação programada de resultados.
- Respostas com salvamento automático idempotente, retomada, encerramento por prazo e proteção contra acessos fora da turma.
- Impressão de cadernos e cartões-resposta versionados, QR assinado e criptografado, captura/revisão OMR e correção em lote.
- Questões objetivas, verdadeiro/falso e discursivas; correção manual com justificativa, feedback e rubricas.
- Painéis com distribuição de notas, evolução, domínio por habilidade, acerto por questão, risco pedagógico e andamento ao vivo.
- Materiais de aprendizagem por turma e taxonomia, recomendações explicáveis e progresso aberto/concluído.
- Portal do responsável limitado aos alunos vinculados e aos resultados expressamente liberados.
- Interface responsiva, navegação por teclado, foco visível, semântica e preferências de contraste/movimento.

## Requisitos

- PHP 8.2 ou superior, Composer 2 e extensões usuais do Laravel (`pdo`, `mbstring`, `openssl`, `fileinfo`, `dom`); `gd` é recomendada para imagens.
- Node.js 20.19+ ou 22.12+ e npm.
- SQLite para desenvolvimento/testes. Em produção, configure um banco persistente compatível e valide a migração no ambiente de destino.
- Um servidor de e-mail real quando a verificação de conta estiver habilitada.

## Instalação local

Na pasta `platform`:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm ci
npm run build
php artisan serve
```

No PowerShell, substitua `cp .env.example .env` por:

```powershell
Copy-Item .env.example .env
```

O `composer setup` também instala dependências, cria o `.env`, gera a chave, migra o banco e compila os ativos. O seed cria uma base demonstrativa completa, incluindo um banco com mais de 2.000 questões.

> `migrate:fresh` apaga todas as tabelas do banco configurado antes de recriá-las. Use-o somente em desenvolvimento ou testes. Os dados demonstrativos possuem uma trava adicional e não são gerados quando `APP_ENV` não é `local` ou `testing`.

## Contas demonstrativas

Depois de `php artisan migrate:fresh --seed`, todas usam a senha `password`:

| Perfil | E-mail |
| --- | --- |
| Administrador global | `admin@admin.com` |
| Gestor da Escola Horizonte | `institution@email.com` |
| Coordenador pedagógico | `coordinator@email.com` |
| Professor de Matemática | `teacher@email.com` |
| Professora de Língua Portuguesa | `teacher.portugues@email.com` |
| Aluno com histórico completo | `student@email.com` |
| Responsável | `guardian@email.com` |
| Gestor do tenant Aurora | `institution.aurora@email.com` |
| Professora do tenant Aurora | `teacher.aurora@email.com` |
| Aluno do tenant Aurora | `student01.aurora@demo.avaliation.test` |
| Gestor de instituição suspensa | `institution.inactive@email.com` |

Essas credenciais servem apenas para desenvolvimento e demonstração. Não execute o seed em um banco de produção e remova ou troque todas as senhas antes de publicar o sistema.

A Escola Horizonte contém as turmas `1º Ano A`, `1º Ano B`, `2º Ano A`, `6º Ano A` e `Reforço de Matemática`, com professores compartilhados, alunos, responsáveis, avaliações on-line/impressas/híbridas, desempenhos variados, materiais, leituras OMR, convites, lixeira e auditoria. O Colégio Aurora possui dados independentes para validar o isolamento entre tenants. Veja o catálogo completo e os roteiros de teste em [Dados demonstrativos](docs/DEMO_DATA.md).

## Desenvolvimento e testes

```bash
composer dev
composer test
npm run test:js
npm run build
php vendor/bin/pint --test
```

`composer dev` inicia servidor, fila, logs e Vite.

## Operação em produção

Configure no mínimo:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` com HTTPS e uma `APP_KEY` exclusiva e protegida.
- Banco, cache, sessões e filas persistentes; mantenha `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true` e cookies seguros no domínio correto.
- Transporte de e-mail, remetente válido e processamento contínuo de fila com `php artisan queue:work --tries=3`.
- Diretórios `storage` e `bootstrap/cache` graváveis, armazenamento persistente para PDFs/capturas e rotina de backup testada.
- Agendador do Laravel a cada minuto (`php artisan schedule:run`) quando tarefas agendadas forem adicionadas.
- Deploy atômico com `php artisan migrate --force`, `npm ci && npm run build` e `php artisan optimize`.
- Monitoramento de erros, filas falhas, espaço de armazenamento, latência, tentativas de login e eventos de auditoria.

Não altere ou descarte a `APP_KEY` sem um plano de rotação: ela participa da proteção dos payloads OMR. Segredos OMR institucionais, quando configurados, também devem ser únicos, protegidos e rotacionados com procedimento controlado.

## Ativos locais e privacidade

Fontes, ícones, gráficos, editor, leitor PDF, QR e OpenCV são entregues localmente; os fluxos normais não dependem de CDN. O OpenCV 4.8.0 está em `public/vendor/opencv/`, acompanhado da licença. SHA-256 do arquivo validado:

```text
806CB5646AFA6FA946B736AFA1BEAF1443BDFDA718404D75F9794DDC2C10B1CC
```

## Documentação

- [Relatório de qualidade e entrega](docs/QUALITY_REPORT_2026-07-29.md)
- [Dados demonstrativos, credenciais e roteiros](docs/DEMO_DATA.md)
- [Implantação segura em produção](docs/PRODUCTION_DEPLOYMENT.md)
- [Templates e geometria OMR](docs/OMR_TEMPLATES.md)
- [Análise histórica do estado inicial](docs/ANALISE_ESTADO_ATUAL.md)

## Limites conhecidos

O pipeline OMR foi validado por testes de geometria, segurança, geração PDF e revisão digital, mas a homologação final deve incluir impressoras, papéis, câmeras e condições reais de iluminação. A plataforma não promete detecção automática de plágio/originalidade nem agrupamento de respostas discursivas por IA; a correção por rubrica e a revisão humana continuam sendo a fonte de decisão pedagógica.

Os cargos institucionais customizáveis já restringem o acesso OMR, mas ainda não formam uma camada geral de autorização para todas as telas. A autorização efetiva do restante da plataforma usa os cinco perfis fixos, escopo de organização/turma/autoria e policies/middlewares do Laravel.

O banco SQLite é recriado a partir do snapshot consolidado `database/schema/sqlite-schema.sql`, que representa as 72 migrações históricas do projeto. Novas alterações de estrutura devem ser criadas normalmente como novos arquivos em `database/migrations`.
