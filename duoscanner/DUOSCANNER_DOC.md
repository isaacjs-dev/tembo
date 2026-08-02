# DuoScanner - App de Correcao OMR de Provas

## Visao Geral

DuoScanner e um aplicativo mobile (React Native + Expo) para correcao automatica de provas por leitura optica (OMR - Optical Mark Recognition). O app integra-se com a plataforma web Laravel "Avaliacoes Online", permitindo que professores escaneiem folhas de resposta impressas, detectem automaticamente as marcacoes dos alunos, e sincronizem as notas com o sistema.

## Stack Tecnologica

### App Mobile
- **Framework**: React Native com Expo SDK 55
- **Linguagem**: TypeScript
- **Navegacao**: expo-router (file-based routing)
- **Estado**: Zustand (stores: auth, exam, scan, sync)
- **Estilizacao**: StyleSheet nativo + design tokens customizados
- **Banco local**: expo-sqlite (dados estruturados) + expo-file-system (imagens)
- **Persistencia**: react-native-mmkv (token, configuracoes)
- **Camera**: expo-camera (captura + QR Code)
- **Processamento**: expo-image-manipulator (crop, rotacao, resize)
- **Fonte**: Plus Jakarta Sans (via @expo-google-fonts)
- **Icones**: @expo/vector-icons (MaterialIcons)
- **HTTP**: Axios com interceptors de auth

### Backend API (Laravel)
- **Auth**: Laravel Sanctum (token-based)
- **Endpoints**: RESTful API v1 em `/api/v1/`
- **Middleware**: CheckOmrApiAccess (verifica permissao + feature do plano)
- **Servico**: OmrGradingService (correcao compartilhada web/mobile)

---

## Arquitetura

### Estrutura do Projeto

```
duoscanner/
  app/                          # Expo Router - telas
    _layout.tsx                 # Root layout (auth check, fontes)
    (auth)/login.tsx            # Tela de login
    (tabs)/                     # Bottom tab navigation
      _layout.tsx               # Tab bar + Camera FAB
      index.tsx                 # Dashboard/Inicio
      scans.tsx                 # Historico de scans
      files.tsx                 # Provas baixadas
      profile.tsx               # Perfil + logout
    scan/                       # Fluxo de digitalizacao
      select-exam.tsx           # Selecionar prova
      camera.tsx                # Camera + QR reader
      adjust.tsx                # Ajuste de imagem
      review-data.tsx           # Revisar dados do aluno
      review-marks.tsx          # Revisar marcacoes OMR
      manual-edit.tsx           # Edicao manual das respostas
      result.tsx                # Resultado com nota
    scan-detail/[id].tsx        # Detalhe de um scan
  src/
    components/ui/              # Componentes reutilizaveis (Button, Card, Badge, etc.)
    components/scan/            # Componentes especificos do scan
    services/                   # Camada de API (Axios)
    lib/                        # Logica de negocio (OMR, grading, QR parser)
    store/                      # Zustand stores
    hooks/                      # React hooks customizados
    theme/                      # Design tokens (cores, tipografia)
    types/                      # TypeScript interfaces
    utils/                      # Utilidades (formatacao, hash)
```

### Backend - Novos Arquivos Laravel

```
platform/
  routes/api.php                           # Rotas da API REST
  app/Http/Controllers/Api/
    AuthController.php                     # Login/Logout Sanctum
    ExamApiController.php                  # Listagem + download de provas
    OmrApiController.php                   # CRUD de scans OMR
  app/Http/Middleware/
    CheckOmrApiAccess.php                  # Middleware de permissao OMR
  app/Services/
    OmrGradingService.php                  # Servico de correcao reutilizavel
  database/migrations/
    2026_03_01_130000_add_copy_id_...php    # Adiciona copy_id e source ao omr_scans
```

---

## Fluxo Principal

### 1. Autenticacao
- Professor faz login com email/senha
- Backend retorna token Sanctum + dados do usuario
- Token armazenado em MMKV para persistencia

### 2. Download de Prova (offline-first)
- App lista provas publicadas da organizacao via `GET /api/v1/exams`
- Professor baixa prova especifica via `GET /api/v1/exams/{id}/download`
- Payload inclui: gabarito, copias (com questions_map e options_map), lista de alunos
- Dados salvos localmente em Zustand store

### 3. Digitalizacao
1. **Selecionar Prova** - Escolher qual prova vai corrigir
2. **Camera** - Apontar para QR Code da folha de resposta
3. **QR Detectado** - Identifica exam_id, copy_id, validation_hash
4. **Capturar Foto** - Tirar foto do cartao resposta
5. **Ajustar Imagem** - Crop, rotacao, ajuste automatico

### 4. Processamento OMR
- Algoritmo detecta marcadores de calibracao (4 quadrados pretos nos cantos)
- Grid dividido em 4 colunas x 15 linhas (layout do PDF)
- Para cada questao, analisa intensidade dos pixels em cada bolha (A-E)
- Retorna opcao detectada + score de confianca por questao

### 5. Revisao e Correcao
- **Revisar Marcacoes** - Grid de respostas detectadas com indicadores de confianca
- **Edicao Manual** - Corrigir questoes com baixa confianca, botoes A-E por questao
- **Selecionar Aluno** - Associar scan a um aluno da turma

### 6. Calculo da Nota
- Respostas visuais (bubble A=0, B=1, etc.) sao mapeadas para indices originais via `ExamCopy.options_map`
- `options_map[questionId][visualIndex]` retorna o indice original da opcao
- Comparar indice original com `Question.content.correct_option`
- Somar pontos (pivot `exam_questions.points`) das corretas

### 7. Sincronizacao
- Scan salvo localmente com status `confirmed`
- Quando online, upload via `POST /api/v1/omr/scans` (multipart: imagem + dados)
- Backend cria OmrScan + roda autoGrade -> ExamSubmission + ExamAnswers
- Status atualizado para `synced`

---

## API Endpoints

| Metodo | Rota | Descricao |
|--------|------|-----------|
| POST | `/api/v1/auth/login` | Login com email/senha, retorna token Sanctum |
| POST | `/api/v1/auth/logout` | Invalida token atual |
| GET | `/api/v1/auth/me` | Dados do usuario autenticado |
| GET | `/api/v1/exams` | Lista provas publicadas da organizacao |
| GET | `/api/v1/exams/{exam}/download` | Payload completo para uso offline |
| GET | `/api/v1/omr/scans` | Lista scans da organizacao (paginado) |
| POST | `/api/v1/omr/scans` | Upload de scan (multipart) |
| GET | `/api/v1/omr/scans/{scan}` | Detalhe de um scan |
| PUT | `/api/v1/omr/scans/{scan}/confirm` | Confirmar scan com respostas |
| PUT | `/api/v1/omr/scans/{scan}/reject` | Rejeitar scan |

### Autenticacao
Todas as rotas (exceto login) requerem header `Authorization: Bearer {token}`.
Rotas de OMR adicionalmente verificam permissao `manage_omr` e feature `mobile_omr_scanning` no plano.

### Payload de Download (GET /exams/{id}/download)
```json
{
  "exam": { "id": 1, "title": "Prova de Matematica", "status": "published" },
  "copies": [
    {
      "id": 10,
      "copy_number": 1,
      "validation_hash": "abc123",
      "questions_map": [45, 12, 89],
      "options_map": { "45": [2,0,1,3], "12": [0,1], "89": [1,3,0,2,4] }
    }
  ],
  "questions": [
    { "id": 45, "type": "multiple_choice", "correct_option": 2, "option_count": 4, "points": 1.0 }
  ],
  "students": [
    { "id": 10, "name": "Alice Silva", "registration_number": "2024001" }
  ]
}
```

---

## Design System

### Paleta de Cores
| Nome | Hex | Uso |
|------|-----|-----|
| Primary | `#55ca02` | Botoes, badges, destaques |
| Primary Dark | `#46a302` | Sombra 3D dos botoes |
| Background | `#f7f8f5` | Fundo de telas |
| Danger | `#ff4b4b` | Erros, questoes incorretas |
| Amber | `#f59e0b` | Avisos, confianca media |
| Border | `#e5e5e5` | Bordas de cards e inputs |
| Login Orange | `#ec5b13` | Tema da tela de login |

### Tipografia
- **Fonte**: Plus Jakarta Sans
- **Pesos**: Regular (400), Medium (500), SemiBold (600), Bold (700), ExtraBold (800)

### Componentes
- **Button**: Efeito 3D tatil (shadow 4px, translateY no press), variantes primary/secondary/outline/danger/login
- **Card**: Border 2px #e5e5e5, rounded-xl, shadow sutil
- **Badge**: Rounded-full, bg-color/10, texto bold
- **ScoreCircle**: SVG circular com progresso, nota centralizada
- **BubbleGrid**: Grid de bolhas A-E com estados selecionado/correto/errado
- **Header**: Barra superior com titulo, voltar, acao direita

### Navegacao
- **Bottom Tabs**: 5 itens (Inicio, Scans, Camera FAB, Arquivos, Perfil)
- **Camera FAB**: Botao circular verde elevado acima da tab bar
- **Scan Flow**: Stack navigation modal (select -> camera -> adjust -> review -> result)

---

## Algoritmo OMR

### Estrutura da Folha de Resposta (pdf_advanced.blade.php)
- 4 marcadores de calibracao (quadrados pretos 18x18px) nos cantos da area de bolhas. Medidas reais em milimetros (ex: 184x153mm).
- Grid dinâmico baseado em _layout_version_: suporta colunas variáveis, com fallback nativo (v0).
- Cada celula visual: numero da questao + bolhas circulares (A-E para MC, A-B para V/F)
- Schema QR Code (v1): `{ "e": exam_id, "c": copy_id, "h": validation_hash, "p": page, "pt": total_pages, "qs": start_q, "qe": end_q, "v": layout_version, "cols": 4, "rpp": 15, "chk": hmac_checksum }`
- **HMAC Checksum**: Previne que alunos misturem folhas de diferentes provas.
- **Multi-sheet Upload**: Folhas da mesma sessão (`session_id`) são enviadas separadamente pelo app e consolidadas num *Job* assíncrono Backend (Laravel).

### Pipeline de Processamento (Engine Typescript Pura)
1. **Decode Raw Pixels**: Decodificação rápida com `fast-png` (evita bridges para código nativo C++).
2. **Fiducial Detection**: BFS flood-fill nos cantos para encontrar 4 componentes conectados quadrados perfeitos (Fiduciais).
3. **Perspectiva Geométrica (Homography)**: Algoritmo DLT (Direct Linear Transform) nativo re-projeta o polígono distorcido da foto da câmera em um retângulo A4 virtual perfeito e plano.
4. **Calculo Físico (MM)**: A Engine lê as metragens do template (`template-registry.ts`) e o Aspect Ratio das fiduciais, garantindo que o cabeçalho alto/baixo não destrua o *row spacing*.
5. **Amostragem Otsu + Bubble Classification**: As bolhas daquela célula são binarizadas usando limiar de Otsu (Otsu Threshold), e a taxa de pixels escuros vs brancos define se a bolha foi rasurada, marcada fortemente, fracamente, ou em branco.
6. **Confianca e Múltiplas Marcas**: A taxa global de confiança alerta professores se a Prova foi rasurada ou tem baixa qualidade (`< 80%`). Reflexo imediato de UI nos painéis *Admin* da Instituição.

### Logica de Correcao (Reverse-Mapping)
```
Bubble preenchida: A (indice visual 0)
options_map["questao_45"] = [2, 0, 1, 3]
options_map["questao_45"][0] = 2 (indice original)
correct_option = 2
2 === 2 -> CORRETO!
```

---

## Offline-First

### Camadas de Armazenamento
1. **MMKV**: Token de auth, configuracoes rapidas
2. **Zustand Stores**: Estado em memoria (exam list, scan queue, sync status)
3. **expo-file-system**: Imagens dos scans em `documentDirectory/scans/`

### Fluxo Offline
1. Professor baixa prova (gabarito + alunos) enquanto tem conexao
2. Pode escanear e corrigir provas sem internet
3. Resultados salvos localmente com status `confirmed`
4. Quando volta online, fila de sync processa automaticamente
5. Backend registra OmrScan com `source: 'mobile'`

---

## Permissoes e Seguranca

### Roles que podem usar o app
- `institution_admin` - Bypass total
- `global_admin` - Bypass total
- `teacher` - Precisa de permissao `manage_omr` na InstitutionRole

### Feature do Plano
- A organizacao deve ter `mobile_omr_scanning` habilitado no plano
- Verificado pelo middleware `CheckOmrApiAccess` em cada request OMR

### Idempotencia
- Cada scan tem um `idempotency_key` unico
- Evita duplicacao de uploads (se o app reenvia, backend retorna o existente)

---

## Como Rodar

### Backend (Laravel)
```bash
cd platform/
composer install
php artisan migrate
php artisan serve
```

### App Mobile (Expo)
```bash
cd duoscanner/
npm install
npx expo start
# Escanear QR code com Expo Go no celular
# Ou: npx expo start --android / --ios
```

### Configuracao
- Ajustar `API_BASE_URL` em `src/services/api.ts` para o IP do servidor Laravel
- Para emulador Android: `http://10.0.2.2:8000/api/v1`
- Para device fisico: usar IP da maquina na rede local

---

## Modelos de Tela de Referencia

Os designs foram baseados nos modelos em `D:/.dev/avaliation_on/modelos_pages/`:
- `login_scanner_app/` - Tela de login
- `selecionar_prova_para_digitalizar/` - Selecao de prova
- `ajustar_imagem_(cropping)/` - Ajuste de imagem
- `revisar_dados_extraídos/` - Revisao de dados do aluno
- `revisar_marcações_-_mobile/` - Revisao de marcacoes
- `edição_manual_-_mobile/` - Edicao manual
- `resultado_da_digitalização_-_mobile/` - Resultado do scan
- `histórico_de_digitalizações_-_mobile/` - Historico
- `detalhe_da_digitalização_-_mobile/` - Detalhe do scan
- `fila_de_correção_vazia_-_comemoração/` - Fila vazia

---

## Tabelas do Banco Afetadas

| Tabela | Alteracao |
|--------|----------|
| `personal_access_tokens` | Nova (Sanctum) |
| `omr_scans` | Adicionado `copy_id` FK + `source` enum |
| `exam_submissions` | Criado pelo autoGrade via API |
| `exam_answers` | Criado pelo autoGrade via API |
| `users` | Adicionado trait HasApiTokens |

---

## Proximos Passos

1. **Melhorar OMR**: Integrar OpenCV via modulo nativo para deteccao mais precisa de bolhas
2. **Identificacao automatica de aluno**: OCR no campo de nome/matricula da folha
3. **Proctoring**: Validacao de integridade do scan (deteccao de fraude)
4. **Relatorios**: Dashboard com estatisticas de correcao no app
5. **Push Notifications**: Notificar professor quando sync completa
6. **QTI Export**: Exportar resultados em formato padrao educacional
