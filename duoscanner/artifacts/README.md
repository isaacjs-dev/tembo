# Artefatos Android

O APK de homologação é gerado pelo perfil `preview` do EAS:

```bash
npm run build:android:preview
```

Os arquivos `.apk` desta pasta não são versionados. O build validado desta
entrega é `tembo-scanner-1.0.0-aracruz-eu-signed-ae1aadb1.apk` (EAS build
`ae1aadb1-86fa-4b67-871e-38bada01eec4`). O arquivo
`tembo-scanner-1.0.0-aracruz-eu.apk` foi preservado apenas para diagnóstico e
não deve ser distribuído, pois o build correspondente não possui assinatura.
