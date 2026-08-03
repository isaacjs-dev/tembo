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

- Arquivo local: `artifacts/tembo-scanner-1.0.0-aracruz-eu-signed-ae1aadb1.apk`
- SHA-256: `DACE1A4D440B66AC47640BA03EB3037BF914342584AB22F7C4CEA0506D9B2F93`
- Tamanho: `102460275` bytes
- EAS build: `ae1aadb1-86fa-4b67-871e-38bada01eec4`
- Página: <https://expo.dev/accounts/isaacjs-st/projects/tembo-scanner/builds/ae1aadb1-86fa-4b67-871e-38bada01eec4>

O perfil `preview` gera um APK instalável para homologação e usa a credencial
Android gerenciada pelo EAS. A continuidade dessa keystore deve ser preservada
nas próximas atualizações. Uma publicação em loja deve usar o perfil
`production` e o formato AAB.

## Validações executadas

- `npm run typecheck`: aprovado.
- `npx expo-doctor`: 18/18 verificações aprovadas.
- Bundle Metro de release: aprovado (1458 módulos e 56 assets).
- TypeScript do núcleo OMR: aprovado separadamente.
- Assinatura APK v2: aprovada pelo `apksigner` com uma credencial EAS, chave RSA
  de 2048 bits e certificado SHA-256
  `A6411DDFA7AEBDB7E1049B269D780CA08FCD34E6D296945AFA66DD5DAE7A1257`.
- Manifesto APK: nome, pacote, versão e SDKs conferidos pelo `aapt2`.
- Bundle APK: `https://tembo.aracruz.eu/api/v1` confirmada; o domínio anterior
  `tembo.aracruz.org`, endpoints locais e HTTP claro não estão presentes.
- Permissões: câmera, internet, rede, vibração e biometria do armazenamento
  seguro; áudio e armazenamento externo estão bloqueados na configuração.
- `allowBackup=false` e `usesCleartextTraffic=false` confirmados no manifesto final.

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
