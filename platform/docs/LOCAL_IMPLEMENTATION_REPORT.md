# Relatório de implementação local — créditos e revisões

Data da validação: 05/08/2026. Esta etapa foi concluída somente no ambiente local. Nenhum commit, push, deploy ou alteração no APK foi executado.

## Entregas

- limites mensais somente para leituras OMR, primeira publicação de provas e criação de questões;
- saldo e histórico individuais por professor, renovação no primeiro dia do mês e reset manual auditado pelo superadministrador;
- cortesias por usuário, perfil, instituição ou toda a plataforma, com plano temporário, créditos cumulativos, substituição, ilimitado e recurso temporário;
- módulos de Aula, Atividade e Revisão vinculados a turmas, disciplinas, questões e conteúdos existentes;
- fluxo de autoria, análise, devolução, publicação e suspensão;
- prompt para ChatGPT/Claude/Gemini sem dados de alunos e importação transacional de JSON versão 1;
- múltipla escolha, verdadeiro/falso, associação, preenchimento, ordenação, flashcard e resposta curta;
- correção automática, feedback, snapshots imutáveis das respostas, progresso, XP, níveis, sequência e conquistas privadas;
- revisão obrigatória antes da prova com bloqueio configurável pelo professor;
- relatório individual/coletivo e massa demonstrativa de todos os estados.

## Recriar a base demonstrativa

Na pasta `platform`, com `APP_ENV=local` ou `testing` e banco descartável:

```bash
php artisan migrate:fresh --seed
```

O seeder demonstrativo se recusa a executar em produção. `migrate:fresh` apaga o banco configurado, portanto nunca use o comando em uma base com dados reais.

## Credenciais principais

Todas usam a senha `password`.

| Perfil | E-mail |
| --- | --- |
| Superadministrador | `admin@admin.com` |
| Administrador institucional | `institution@email.com` |
| Coordenador | `coordinator@email.com` |
| Professor | `teacher@email.com` |
| Aluno | `student@email.com` |
| Responsável | `guardian@email.com` |

O catálogo completo está em [DEMO_DATA.md](DEMO_DATA.md).

## Validação executada

```bash
php artisan test
npm run test:js
npm run build
npm run test:e2e
composer validate --no-check-publish
php artisan schedule:list
```

Resultados:

- PHP/Laravel: 331 testes e 1.034 asserções aprovadas no ciclo completo;
- testes específicos de revisão/cotas: 9 cenários e 30 asserções aprovadas;
- JavaScript: 11 testes OMR aprovados, executados separadamente dos cenários E2E;
- Vite: compilação de produção aprovada;
- Playwright: 6 cenários aprovados em Chromium desktop e Pixel 7, sem erros de console ou overflow horizontal;
- migração e seed completos aprovados em SQLite local;
- agendamentos mensais/diários registrados corretamente.

O primeiro ciclo do Playwright detectou uma leitura nula no simulador de consumo do painel SaaS (`preview.error`). A expressão foi protegida e o segundo ciclo passou integralmente.

## Próxima etapa, após conferência manual

Depois da aprovação local, executar em uma etapa separada: revisão do diff, commit, push para GitHub, migração não destrutiva em produção e deploy. A massa demonstrativa não deve ser enviada ao banco de produção sem uma autorização específica.
