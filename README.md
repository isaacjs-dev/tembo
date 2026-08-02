# Tembo

Monorepo da plataforma educacional Tembo e do aplicativo móvel Tembo Scanner.

## Projetos

- [`platform/`](platform/README.md): plataforma SaaS Laravel para instituições, professores, estudantes e responsáveis.
- [`duoscanner/`](duoscanner/DUOSCANNER_DOC.md): aplicativo Expo/React Native para captura, revisão e sincronização OMR.

## Identidade

A marca Tembo usa uma paleta azul-celeste/pastel e um símbolo de elefante. Tokens, contraste, componentes e regras de aplicação estão documentados em [`platform/docs/BRAND_GUIDE.md`](platform/docs/BRAND_GUIDE.md).

## Produção

A URL oficial configurada para web e API mobile é:

```text
https://tembo.aracruz.org
```

O procedimento de publicação, variáveis obrigatórias, baseline de banco, permissões, filas e validação pós-deploy está em [`platform/docs/PRODUCTION_DEPLOYMENT.md`](platform/docs/PRODUCTION_DEPLOYMENT.md).

Nenhum segredo de produção, banco de dados, certificado ou chave de assinatura deve ser versionado neste repositório.

## Testes principais

```bash
cd platform
composer install
npm ci
php artisan test
npm run test:js
npm run build
```

Para o aplicativo:

```bash
cd duoscanner
npm ci
npx tsc --noEmit
npx expo-doctor
```

