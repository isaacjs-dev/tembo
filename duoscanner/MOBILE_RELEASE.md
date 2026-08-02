# Tembo Scanner — entrega Android

## Configuração

- Nome: `Tembo Scanner`
- Versão: `1.0.0` (`versionCode 1`)
- Pacote: `com.duoscanner.app` (mantido para preservar compatibilidade)
- URL da API: `https://tembo.aracruz.eu/api/v1`
- URL web: `https://tembo.aracruz.eu`
- Android: mínimo 7.0 (API 24), alvo API 34

Em builds que não sejam de desenvolvimento, o cliente rejeita uma URL de API
sem HTTPS. O token de autenticação permanece no `SecureStore`; o banco local é
usado somente para dados e operações pendentes de sincronização.

## APK de homologação

- Arquivo local: `artifacts/tembo-scanner-1.0.0-preview-a016761c.apk`
- SHA-256: `3FB61140ABAB83DEAE758EC6103BC3D6BC43A6D361C2186A6042DCCD3F6B1868`
- Tamanho: `102460247` bytes
- EAS build: `a016761c-c79c-4da7-8109-43f7d8d82324`
- Página: <https://expo.dev/accounts/isaacjs-st/projects/tembo-scanner/builds/a016761c-c79c-4da7-8109-43f7d8d82324>

O perfil `preview` gera um APK instalável para homologação e utiliza assinatura
Android de desenvolvimento. Uma publicação em loja deve usar o perfil
`production`, uma chave de produção protegida e o formato AAB.

## Validações executadas

- `npm run typecheck`: aprovado.
- `npx expo-doctor`: 18/18 verificações aprovadas.
- Bundle Metro de release: aprovado (1458 módulos e 56 assets).
- TypeScript do núcleo OMR: aprovado separadamente.
- Assinatura APK v2: aprovada pelo `apksigner`.
- Manifesto APK: nome, pacote, versão e SDKs conferidos pelo `aapt2`.
- Bundle APK: URL da API de produção confirmada; nenhum endpoint local de API.
- Permissões: câmera, internet, rede, vibração e biometria do armazenamento
  seguro; áudio e armazenamento externo estão bloqueados na configuração.
- `allowBackup=false` confirmado no manifesto final.

## Validações dependentes do ambiente

No fechamento deste build, `tembo.aracruz.org` não possuía resolução DNS. O
domínio canônico adotado passou a ser `tembo.aracruz.eu`. Por
isso, autenticação, sessão contra a API, certificado TLS e fluxos completos de
sincronização precisam ser retestados após a publicação do domínio. Não havia
dispositivo físico conectado por ADB. Duas inicializações limpas do AVD
disponível falharam antes da instalação, com os serviços Android `package`,
`activity` e `window` indisponíveis; portanto, esse AVD não forneceu um teste
dinâmico válido do APK.

O `npm audit fix` não destrutivo foi aplicado. Permanecem avisos transitivos no
toolchain do Expo SDK 52 cuja correção automática exige atualização principal
para uma versão futura do Expo. Essa migração não foi forçada nesta entrega para
evitar mudança incompatível fora do escopo.

## Reprodução

```bash
npm install
npm run typecheck
npm run doctor
npm run build:android:preview
```
