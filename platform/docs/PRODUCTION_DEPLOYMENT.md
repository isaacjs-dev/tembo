# Implantação do Tembo em produção

Este documento descreve a implantação segura do Laravel no domínio de produção. Ele não contém senhas, chaves, tokens nem material de assinatura.

## Estado da auditoria em 2 de agosto de 2026

- O acesso SSH por chave ao servidor foi validado, sem alterações remotas.
- O servidor dispõe de PHP 8.5.4, Composer 2.9.8, MariaDB Client 11.8.8 e das extensões PHP necessárias ao Laravel, inclusive `pdo_mysql`, `gd`, `intl`, `mbstring`, `zip` e `openssl`.
- Node.js e npm não estão disponíveis no servidor. O frontend deve ser compilado localmente ou em CI, e `public/build` deve fazer parte do artefato de implantação.
- `proc_open` é desabilitada no PHP por padrão, mas pode ser habilitada apenas para o processo CLI com `php -d disable_functions=`. Isso é necessário para scripts do Composer.
- O diretório web reservado para o Tembo existe e contém apenas a página padrão da hospedagem. Não há aplicação ou `.env` nele.
- Não há worker de fila nem cron do Laravel em execução.
- Não foi encontrada credencial MySQL reutilizável em arquivo de cliente, variável de processo ou `.env` já instalado. A senha do banco precisa ser obtida de forma segura no painel da hospedagem.
- O domínio `tembo.aracruz.org` ainda retornava `NXDOMAIN`. Sem o registro DNS, não é possível emitir ou validar o certificado TLS.

## Bloqueios antes da primeira publicação

1. Criar o subdomínio no painel da hospedagem e apontar seu registro DNS para o servidor.
2. Confirmar o document root do subdomínio e emitir o certificado Let's Encrypt depois da propagação do DNS.
3. Obter a senha do usuário MySQL fora do Git e gravá-la somente no `.env` do servidor.
4. Fazer backup e conferir se o banco está realmente vazio antes da primeira migration.
5. Publicar um artefato que contenha `public/build/manifest.json`, pois não há Node.js no servidor.

## Layout recomendado no servidor

Mantenha o código e o `.env` fora do diretório público:

```text
$HOME/apps/tembo/
├── current -> releases/AAAAmmddHHMMSS
├── releases/
└── shared/
    ├── .env
    └── storage/

$HOME/domains/aracruz.org/public_html/tembo -> $HOME/apps/tembo/current/public
```

O link deve ser criado pelo shell (`ln -s`), pois a função PHP `symlink` está desabilitada na hospedagem. Nunca publique a raiz do Laravel, `.env`, `storage` ou `vendor` diretamente dentro do document root.

## Variáveis de ambiente

Copie `.env.production.example` para o diretório compartilhado no servidor, substitua todos os `CHANGE_ME` e gere uma chave exclusiva:

```bash
php artisan key:generate
chmod 600 .env
```

Itens obrigatórios:

- `APP_ENV=production`, `APP_DEBUG=false` e `APP_URL` usando HTTPS;
- conexão `mysql` com host, banco, usuário e senha fornecidos pela hospedagem;
- cookies de sessão criptografados, `HttpOnly`, `Secure` e `SameSite=Lax`;
- cache, sessão e fila persistidos no banco;
- SMTP real para convites e recuperação de senha;
- uma `APP_KEY` exclusiva que nunca seja substituída durante deploys normais.

## Baseline de banco cross-driver

A migration `2026_08_02_000000_create_portable_baseline_schema.php` reconstrói em banco vazio as 65 tabelas de aplicação, 579 colunas, 67 índices explícitos e 106 chaves estrangeiras do snapshot consolidado.

Ela possui duas proteções:

- se `organizations` ou `users` já existir, encerra sem alterar o schema existente;
- o rollback é deliberadamente não destrutivo.

Antes da primeira execução, confira as tabelas do banco e faça backup. Em produção, use somente:

```bash
php artisan migrate --force
```

Nunca use `migrate:fresh`, `db:wipe` ou o seeder demonstrativo em produção. O `DatabaseSeeder` bloqueia os cenários fictícios fora de `local/testing`; quando necessário, ele pode instalar apenas papéis, planos e configurações-base com:

```bash
php artisan db:seed --force
```

## Sequência de release

No artefato já compilado:

```bash
php -d disable_functions= "$(command -v composer)" install \
  --no-dev --prefer-dist --no-interaction --optimize-autoloader

php artisan down --retry=60
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
```

O processo deve falhar antes da manutenção se `.env` ou `public/build/manifest.json` estiver ausente. Mantenha ao menos uma release anterior para rollback do código; não reverta banco automaticamente.

Permissões recomendadas:

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod -R ug+rwX storage bootstrap/cache
chmod 600 .env
```

## Fila e agendador

O processamento OMR multipágina usa fila. Se a hospedagem não oferecer Supervisor, configure no painel um cron exclusivo e protegido por `flock`, executado a cada minuto:

```bash
flock -n "$HOME/apps/tembo/shared/queue.lock" \
  php "$HOME/apps/tembo/current/artisan" queue:work \
  --stop-when-empty --tries=3 --timeout=120
```

O agendador ainda não possui tarefas de negócio, mas pode ser preparado com:

```bash
php "$HOME/apps/tembo/current/artisan" schedule:run
```

## OMR e limitação do servidor

Os fluxos web/mobile de leitura OMR são implementados no cliente e no Laravel. O comando administrativo `omr:calibrate`, porém, referencia um motor Python que não está presente no projeto e o servidor não possui Python. Esse comando não deve ser considerado homologado em produção até que o motor seja fornecido e instalado de forma controlada.

## Validação pós-deploy

1. Confirmar redirecionamento HTTP para HTTPS e cadeia TLS válida.
2. Acessar `/up` e exigir HTTP 200.
3. Abrir login, dashboard e ativos Vite sem conteúdo misto ou erro 404.
4. Testar sessão, logout, redefinição de senha e e-mail.
5. Criar e processar um job de teste; conferir `jobs` e `failed_jobs`.
6. Testar upload e leitura em `/storage`.
7. Validar API/mobile, retorno do botão Voltar e persistência da autenticação.
8. Conferir `storage/logs`, permissões, consumo de disco e backup restaurável.

O deploy só deve ser declarado concluído após DNS, SSL, banco, filas e esses smoke tests estarem aprovados.
