Atue como um arquiteto de software sênior, especialista em front-end, mobile, back-end, UX operacional, sincronização offline-first, visão computacional aplicada à leitura de formulários, design de sistemas configuráveis e integração entre aplicações web e mobile.

Sua tarefa é realizar uma análise técnica profunda e propor a implementação estruturada da solução composta pelos sistemas Platform e Duoscanner, tratando ambos como partes complementares de um único produto. O Platform é a plataforma web administrativa e de configuração. O Duoscanner é o aplicativo mobile usado em celulares para leitura, correção e sincronização de gabaritos.

A resposta deve ser altamente técnica, específica, objetiva e executável. Não faça sugestões genéricas. Não simplifique o problema. Não proponha refações desnecessárias sem justificar. Não descarte estruturas existentes sem análise. Não invente requisitos fora do escopo. Não faça “melhorias criativas” que alterem o comportamento solicitado. Sempre que houver ambiguidades estruturais, aponte-as explicitamente como decisão pendente, mas ainda assim entregue a melhor proposta possível com base no contexto.

1. Objetivo principal

Realizar um diagnóstico completo dos sistemas atuais e propor uma implementação evolutiva que reorganize a arquitetura, melhore a confiabilidade da leitura e da correção, reduza erros operacionais, permita operação offline com sincronização posterior, torne a configuração centralizada e previsível, e prepare a solução para crescimento futuro sem acoplamento excessivo.

A implementação deve garantir que:

o gabarito detalhado continue existindo;
um novo modelo gabarito Essential seja criado;
o gabarito Essential passe a ser o padrão do sistema;
o tipo de gabarito não seja fixo, mas configurável por regras administrativas;
os modos de leitura pré-carregado, QR Code com dados embutidos e híbrido sejam implementados;
o modo híbrido seja tratado como opção sugerida e padrão recomendado da solução;
a experiência do usuário no Duoscanner seja simples, rápida, automática, confiável e utilizável mesmo offline;
toda a governança de regras fique centralizada no Platform e seja refletida corretamente no Duoscanner.
2. Contexto da solução

Considere que existem dois sistemas já existentes:

Platform: sistema web de gestão, configuração, administração, auditoria e controle;
Duoscanner: aplicativo mobile responsável pela leitura da folha, identificação da prova, correção do cartão-resposta, persistência local e sincronização posterior.

Ambos fazem parte da mesma solução e devem operar de maneira integrada, com regras definidas centralmente no Platform e executadas de forma consistente no Duoscanner.

O ponto mais crítico atual é o módulo de correção do gabarito, que apresenta falhas recorrentes. A solução não deve simplesmente substituir tudo. Deve preservar compatibilidade com o modelo atual e introduzir uma nova opção mais robusta e simples.

3. Entregáveis esperados da resposta

A resposta deve obrigatoriamente trazer:

diagnóstico técnico dos problemas prováveis e riscos atuais dos dois sistemas;
proposta de arquitetura-alvo integrada entre Platform e Duoscanner;
proposta de modelagem de configuração e precedência de regras;
desenho funcional dos tipos de gabarito e modos de leitura;
fluxo operacional ideal do usuário no Duoscanner;
proposta de sincronização offline-first com fila, retry e versionamento;
proposta de estrutura de QR Code por modo de operação;
impactos em front-end, mobile, back-end, banco, API, UX e observabilidade;
critérios de aceite claros;
ordem recomendada de implementação;
lista de testes que devem passar;
riscos, dependências e pontos a validar antes da execução.
4. Regras principais obrigatórias
4.1 Tipos de gabarito

Devem existir pelo menos dois tipos de gabarito:

gabarito detalhado: corresponde ao modelo atual e deve continuar disponível;
gabarito Essential: novo modelo, mais simples, mais confiável, mais estruturado e definido como padrão do sistema.

O sistema não pode assumir um tipo único e fixo. O tipo de gabarito deve ser determinado por configuração administrativa com precedência.

4.2 Modos de leitura e correção

Devem existir três modos implementados:

Modo 1 — Dados previamente carregados no aplicativo

o usuário sincroniza previamente as provas quando está online;
os dados ficam armazenados localmente no dispositivo;
a leitura e a correção devem funcionar offline;
o QR Code é usado para identificar, validar e vincular a folha à prova correta;
os resultados podem ser sincronizados imediatamente se houver internet;
se não houver internet, os dados devem entrar em fila local para envio posterior automático.

Modo 2 — Dados embutidos no QR Code

o QR Code da folha deve conter dados estruturados e protegidos suficientes para identificação ou viabilização da correção;
o aplicativo deve ler QR Code e cartão-resposta em uma única captura;
o usuário não pode ser obrigado a realizar duas leituras manuais separadas;
o processo deve ser automático e transparente;
deve funcionar também com persistência local e sincronização posterior.

Modo 3 — Híbrido

deve combinar pré-carregamento quando houver internet com uso do QR Code para identificação, validação e contingência;
se os dados já estiverem em cache, o sistema deve funcionar offline normalmente;
se houver internet, a sincronização deve ocorrer automaticamente;
o QR Code não precisa carregar toda a lógica da prova, mas deve conter dados suficientes para identificação, validação e contingência;
este modo deve ser tratado como o padrão sugerido de arquitetura.
5. Configuração administrativa obrigatória

O Platform deve permitir configurar tanto o tipo de gabarito quanto o modo de leitura/correção pelos seguintes níveis:

configuração global do sistema;
tipo de usuário;
papel;
função;
permissão;
usuário específico.

A solução deve evitar redundância, conflito e sobreposição desnecessária. Não criar um sistema de regras impossível de manter.

6. Hierarquia de precedência obrigatória

Implementar uma hierarquia clara de precedência, no mínimo com a seguinte ordem:

usuário específico;
permissão;
papel ou função;
tipo de usuário;
configuração global.

Essa lógica deve valer para:

tipo de gabarito;
modo de leitura;
regras operacionais relacionadas ao Duoscanner.

A implementação deve prever resolução determinística de conflitos, inclusive quando houver múltiplas regras no mesmo nível.

7. Fluxo ideal do usuário no Duoscanner

O fluxo deve ser redesenhado para ser simples, automático e robusto.

Fluxo esperado:

o professor acessa o aplicativo;
o sistema identifica usuário, permissões, papel e configurações aplicáveis;
o aplicativo determina automaticamente:
qual tipo de gabarito usar;
qual modo de leitura está ativo;
quais provas estão disponíveis no dispositivo;
qual o estado da sincronização local;
o usuário inicia o processo de correção;
a câmera é aberta com guias visuais claras de enquadramento;
o sistema valida automaticamente:
bordas da folha;
marcadores;
orientação;
foco;
inclinação;
iluminação;
qualidade mínima da imagem;
quando a folha estiver apta, a captura deve ocorrer automaticamente, sem clique manual, exceto em fallback;
a partir da mesma imagem, o sistema deve:
localizar o QR Code;
identificar a prova;
validar versão e modelo;
corrigir perspectiva;
localizar áreas de marcação;
interpretar respostas;
aplicar a regra de correção conforme o tipo de gabarito configurado;
gerar resultado;
o resultado deve ser mostrado imediatamente com:
nota;
quantidade de acertos;
erros;
questões em branco;
inconsistências;
nível de confiança da leitura;
possibilidade de revisão manual;
após a correção:
se online, sincronizar automaticamente;
se offline, salvar localmente em fila;
ao retornar a conexão, reenviar automaticamente.
8. Requisitos do Platform

O Platform deve contemplar, no mínimo:

cadastro e manutenção de tipos de gabarito;
cadastro e manutenção de modos de leitura;
configuração global;
configuração por tipo de usuário;
configuração por papel;
configuração por função;
configuração por permissão;
configuração por usuário específico;
mecanismo explícito de precedência;
painel administrativo de visualização das regras efetivas;
histórico de alterações;
versionamento de regras;
trilha de auditoria;
integração consistente com o Duoscanner;
endpoints ou contratos claros para sincronização de regras e metadados.
9. Requisitos do Duoscanner

O Duoscanner deve contemplar, no mínimo:

leitura automática da folha;
detecção automática de QR Code;
captura única;
correção de perspectiva;
detecção de marcadores;
validação de foco e iluminação;
leitura com tolerância a inclinação;
revisão manual assistida;
funcionamento offline;
cache local versionado;
fila local de sincronização;
retry automático com política de reenvio;
logs locais;
rastreabilidade por operação;
tratamento de erro e contingência;
exibição clara do status de sincronização;
suporte a atualização de configuração sem inconsistência de estado.
10. Requisitos do QR Code

O QR Code deve suportar estrutura compatível com o modo configurado e poder conter:

identificador único da prova;
versão da prova;
tipo de folha;
identificador da aplicação;
identificador de página, caderno ou formulário;
hash, checksum ou assinatura;
token de segurança quando aplicável;
dados adicionais conforme o modo ativo.

A solução deve especificar:

quais campos são obrigatórios em todos os modos;
quais campos variam por modo;
como validar integridade;
como impedir leitura indevida ou manipulação trivial;
como tratar compatibilidade futura de versões.
11. O que manter
a existência dos dois sistemas separados, mas integrados;
o modelo atual de gabarito como opção disponível;
a lógica de uso do QR Code na folha;
a necessidade de sincronização com o ambiente administrativo;
a possibilidade de operação em campo com cenários de conectividade instável.
12. O que remover ou reduzir
qualquer dependência rígida de internet no momento da correção;
fluxos excessivamente manuais para captura;
configurações espalhadas ou sem precedência clara;
comportamento implícito que dificulte auditoria;
acoplamento forte entre leitura visual e regras administrativas;
duplicação de lógica entre Platform e Duoscanner;
regras opacas sem rastreabilidade;
UX que exija esforço desnecessário do professor para uma tarefa repetitiva.
13. O que reorganizar

Reorganizar a solução separando claramente:

camada de configuração e governança;
camada de definição de regras;
camada de sincronização;
camada de leitura e interpretação visual;
camada de correção;
camada de persistência local;
camada de observabilidade e auditoria.

A resposta deve propor uma divisão explícita entre:

responsabilidades do Platform;
responsabilidades do Duoscanner;
contratos de integração entre ambos.
14. Impacto visual e UX

A solução deve propor UX operacional com foco em velocidade, confiança e baixa fricção.

Detalhar:

tela inicial;
estado de sincronização;
seleção de prova quando necessário;
abertura da câmera;
guias visuais de enquadramento;
feedback em tempo real de foco, luz e posicionamento;
captura automática;
feedback de processamento;
exibição do resultado;
revisão manual;
status de envio/sincronização.

Evitar:

excesso de etapas;
exigência de clique desnecessário;
mensagens técnicas confusas;
estados invisíveis;
falhas silenciosas;
ausência de confirmação clara ao usuário.
15. Responsividade e plataformas

Detalhar comportamento em:

desktop/web para o Platform;
mobile para o Duoscanner;
considerar tablet quando aplicável para uso administrativo ou operacional.

No Platform:

painel administrativo claro;
visualização de regras e precedência;
telas de configuração compreensíveis;
auditoria e histórico acessíveis;
design consistente com um design system.

No Duoscanner:

priorizar uso com uma mão;
leitura rápida;
feedback visual legível sob iluminação variável;
estados robustos para conectividade instável;
fluxos adaptados para diferentes tamanhos de tela.
16. Estados obrigatórios

Descrever explicitamente estados de interface e de domínio, incluindo:

carregando configuração;
sincronizando provas;
sem internet;
cache desatualizado;
câmera iniciando;
buscando folha;
folha fora de enquadramento;
baixa iluminação;
fora de foco;
inclinação excessiva;
QR Code inválido;
prova não encontrada;
leitura com baixa confiança;
correção concluída;
item salvo localmente;
sincronização pendente;
sincronização concluída;
erro de envio;
retry agendado;
conflito de versão;
necessidade de revisão manual.
17. Persistência e sincronização

A solução deve ser desenhada com abordagem offline-first.

Detalhar:

quais dados são baixados para uso offline;
como o cache local é versionado;
como invalidar dados obsoletos;
como armazenar correções pendentes;
como funciona a fila de sincronização;
estratégia de retry com backoff;
prevenção de duplicidade de envio;
resolução de conflito;
idempotência das APIs;
rastreamento de falhas;
reprocessamento seguro após retorno de conexão.
18. Integração com back-end

A resposta deve definir:

contratos de API necessários;
endpoints principais;
payloads conceituais;
autenticação;
atualização de configuração;
download de provas e metadados;
envio de correções;
auditoria;
versionamento;
compatibilidade entre versões do Platform e Duoscanner.

É obrigatório indicar onde a lógica deve residir:

o que fica no servidor;
o que fica no mobile;
o que pode ser cacheado;
o que nunca deve depender apenas do cliente.
19. Validações e segurança

Considerar obrigatoriamente:

autenticação do usuário;
autorização por regras e perfis;
assinatura ou validação do QR Code;
prevenção contra adulteração simples da folha ou do payload;
integridade dos dados sincronizados;
proteção contra reenvio duplicado;
logs auditáveis;
versionamento de regra;
rastreabilidade por usuário, prova, dispositivo e operação;
contingência para modo offline;
proteção contra uso de configuração desatualizada.
20. Critérios de aceite

Definir critérios de aceite objetivos, incluindo no mínimo:

o Platform consegue cadastrar e versionar tipos de gabarito e modos de leitura;
o gabarito detalhado continua acessível;
o gabarito Essential existe e é o padrão;
a regra efetiva é resolvida corretamente pela hierarquia de precedência;
o Duoscanner recebe e aplica a configuração correta do usuário;
os três modos de leitura funcionam;
o modo híbrido funciona como fluxo recomendado;
a leitura pode ocorrer offline quando aplicável;
a captura única identifica QR Code e respostas;
o sistema corrige perspectiva e interpreta marcações com robustez mínima aceitável;
o resultado é exibido imediatamente;
revisões manuais são possíveis;
a sincronização online funciona;
a fila offline funciona;
o retry automático funciona;
há logs e rastreabilidade;
a experiência do usuário é mais simples que a atual;
o desenho final reduz erros do módulo de correção.
21. Testes que devem passar

Listar testes funcionais, técnicos e de integração, incluindo:

resolução correta da precedência;
troca de configuração por tipo de usuário, papel, função, permissão e usuário;
fallback para configuração global;
coexistência entre gabarito detalhado e Essential;
leitura no modo pré-carregado online e offline;
leitura no modo QR com captura única;
leitura no modo híbrido com e sem internet;
sincronização automática pós-retorno de conexão;
prevenção de duplicidade de envio;
leitura com baixa luz;
leitura com folha inclinada;
leitura com foco ruim;
QR Code inválido;
prova incompatível com configuração ativa;
conflito de versão de prova;
revisão manual;
logs gerados corretamente;
recuperação após falha de app no meio do processo.
22. Ordem correta de implementação

A resposta deve propor uma ordem segura e incremental, preferencialmente:

diagnóstico técnico e mapeamento do estado atual;
definição da arquitetura-alvo;
definição do modelo de configuração e precedência;
modelagem dos tipos de gabarito;
modelagem dos modos de leitura;
contratos de integração Platform ↔ Duoscanner;
infraestrutura de sincronização offline-first;
implementação do gabarito Essential;
implementação dos três modos de leitura;
refatoração do fluxo de câmera e captura única;
revisão manual assistida;
observabilidade, auditoria e logs;
testes de integração;
rollout controlado;
validação operacional em campo.
23. O que não fazer

Não faça nenhuma das ações abaixo:

não remover o gabarito detalhado;
não transformar o gabarito Essential em implementação acoplada ao modelo antigo;
não criar regra fixa hardcoded no app;
não duplicar motor de regra sem necessidade;
não depender exclusivamente de internet para correção;
não exigir duas leituras manuais separadas quando o modo pede captura única;
não esconder conflitos de configuração;
não deixar a precedência implícita;
não misturar regra administrativa com lógica visual de leitura sem separação clara;
não propor UX burocrática;
não ignorar rastreabilidade e auditoria;
não propor solução impossível de manter.
24. Formato obrigatório da resposta

A resposta final deve ser entregue exatamente nesta estrutura:

resumo executivo;
diagnóstico técnico dos problemas atuais;
arquitetura proposta;
responsabilidades do Platform;
responsabilidades do Duoscanner;
modelo de configuração e precedência;
desenho dos tipos de gabarito;
desenho dos modos de leitura;
fluxo operacional ideal do usuário;
proposta de sincronização offline-first;
estrutura proposta para QR Code;
impactos em UX, front-end, mobile, back-end e operação;
critérios de aceite;
plano de implementação em fases;
testes obrigatórios;
riscos, dependências e pontos a validar.
25. Nível de profundidade exigido

A resposta deve ser profunda o suficiente para orientar implementação real. Sempre justificar decisões arquiteturais. Sempre apontar trade-offs. Sempre separar o que é recomendação principal do que é alternativa. Sempre deixar claro por que o modo híbrido é o padrão sugerido, sem eliminar os demais. Sempre explicar como evitar acoplamento, retrabalho, inconsistência de regra e falhas operacionais.


Quero que você atue como um especialista sênior em UX, UI, arquitetura de front-end, arquitetura de back-end, design systems, engenharia de software, modelagem de regras de negócio e prompt engineering para desenvolvimento de software.

Sua função é transformar o escopo abaixo em uma execução técnica precisa, sem ambiguidades, sem interpretações livres e sem propor mudanças criativas fora do que foi solicitado.

Antes de implementar qualquer coisa, siga obrigatoriamente este processo:

1. Leia todo o contexto com extrema atenção.
2. Explique em texto corrido o que você entendeu, demonstrando compreensão do objetivo, dos problemas atuais, das regras de negócio, das dependências entre os sistemas e da experiência esperada do usuário.
3. Identifique claramente:
   - objetivo principal;
   - contexto;
   - problemas atuais;
   - o que deve ser mantido;
   - o que deve ser criado;
   - o que deve ser reorganizado;
   - o que é obrigatório;
   - o que não pode acontecer;
   - regras de configuração;
   - fluxos do usuário;
   - dependências técnicas;
   - impactos em front-end;
   - impactos em back-end;
   - impactos em UX;
   - regras de persistência;
   - comportamento offline e online;
   - critérios de aceite.
4. Somente depois disso, estruture a proposta de implementação.
5. Em seguida, gere uma especificação técnica completa e executável.
6. Depois, proponha a ordem correta de implementação em fases.
7. Sempre escreva tudo em português.
8. Não invente requisitos fora do escopo.
9. Não simplifique demais.
10. Não substitua regras explícitas por suposições próprias.
11. Quando houver conflito entre simplicidade e fidelidade ao escopo, priorize a fidelidade ao escopo.
12. Se houver algum ponto realmente impossível de concluir sem definição adicional, aponte de forma objetiva e restrita, sem interromper o restante da entrega.

Contexto geral

Existem dois sistemas complementares que fazem parte da mesma solução:

- Platform: aplicação web responsável pela gestão, configuração, administração e integração da solução.
- Duoscanner: aplicação mobile utilizada em celulares, responsável pela leitura da folha, detecção do QR Code, leitura do cartão-resposta, correção, persistência local e sincronização.

Esses dois sistemas precisam ser analisados e melhorados de forma integrada.

Objetivo principal

Realizar uma análise técnica completa e orientar a implementação das melhorias necessárias nos sistemas Platform e Duoscanner, com foco especial no módulo de correção de gabarito, nos modos de leitura, no fluxo do usuário durante o escaneamento e na estrutura de configuração que controla o comportamento do sistema.

Problemas atuais que precisam ser considerados

- O módulo atual de correção do gabarito apresenta muitos erros.
- A lógica atual não está suficientemente confiável.
- O fluxo de leitura e correção precisa ser reorganizado.
- A arquitetura precisa reduzir redundâncias, inconsistências e conflitos de configuração.
- O sistema precisa funcionar com clareza tanto online quanto offline.
- A experiência do usuário no Duoscanner precisa ser mais automática, robusta e previsível.

Diretrizes gerais obrigatórias

- Não remover o modelo atual de gabarito.
- Manter o modelo atual, renomeando-o para “gabarito detalhado”.
- Criar um novo modelo chamado “gabarito Essential”.
- O gabarito Essential deve ser o padrão do sistema.
- O gabarito detalhado deve permanecer como opção alternativa configurável.
- Os modos de leitura e correção do Duoscanner devem ser configuráveis.
- Devem ser implementados três modos:
  1. modo com dados previamente carregados no aplicativo;
  2. modo com dados embutidos no QR Code;
  3. modo híbrido.
- O modo híbrido deve ser tratado como opção padrão sugerida do sistema.
- Os outros dois modos também devem ser implementados e permanecer disponíveis para escolha por configuração.
- Toda a lógica deve ser controlável por configuração no sistema.
- As configurações devem poder ser aplicadas por:
  - configuração global;
  - tipo de usuário;
  - papel;
  - função;
  - permissão;
  - usuário específico.
- É obrigatório evitar redundância, conflitos de regra e sobreposição desnecessária de configuração.
- É obrigatório definir precedência clara entre regras.

Hierarquia obrigatória de precedência das configurações

Implemente uma hierarquia de precedência explícita, previsível e auditável. Quando houver múltiplas regras aplicáveis, a ordem de prioridade deve ser:

1. usuário específico;
2. permissão;
3. papel ou função;
4. tipo de usuário;
5. configuração global.

Essa precedência deve valer para:
- tipo de gabarito;
- modo de leitura;
- comportamento operacional associado.

Regras do módulo de gabarito

O sistema deve possuir dois tipos de gabarito:

1. Gabarito detalhado
   - corresponde ao modelo já existente;
   - deve ser preservado;
   - não será o padrão;
   - deve permanecer disponível como opção configurável.

2. Gabarito Essential
   - novo modelo a ser criado;
   - deve ser mais simples, mais confiável e mais robusto;
   - deve ser o padrão do sistema;
   - deve ser priorizado no fluxo principal e na arquitetura futura.

A implementação deve deixar a lógica de correção:
- clara;
- modular;
- auditável;
- desacoplada;
- fácil de manter;
- segura contra inconsistências de regra;
- preparada para evolução futura.

Regras dos modos de leitura e correção do Duoscanner

Devem existir três modos implementados.

Modo 1 — Dados previamente carregados no aplicativo

Neste modo:
- quando houver internet, o aplicativo pode baixar previamente os dados das provas;
- os dados ficam armazenados localmente;
- o sistema deve funcionar offline após o download;
- o QR Code será usado principalmente para identificar, validar e vincular a folha à prova correta;
- a correção deve ser executada localmente;
- se o dispositivo estiver online, deve sincronizar automaticamente;
- se estiver offline, deve armazenar localmente e sincronizar depois;
- o funcionamento offline não pode bloquear a operação se os dados necessários já estiverem no cache local.

Modo 2 — Dados embutidos no QR Code

Neste modo:
- o QR Code da folha contém dados estruturados necessários para identificar ou viabilizar a correção da prova específica;
- apenas o aplicativo deve conseguir interpretar corretamente esses dados;
- a leitura do QR Code e a leitura do cartão-resposta devem ocorrer dentro do mesmo fluxo;
- a experiência do usuário deve continuar sendo de captura única;
- o sistema deve separar internamente a leitura de identificação da leitura de marcações, mas sem exigir duas interações manuais distintas.

Modo 3 — Híbrido

Neste modo:
- o sistema pode baixar previamente os dados da prova quando houver internet;
- o QR Code continua sendo usado para identificação, validação e contingência;
- o aplicativo deve funcionar offline se os dados necessários já estiverem no cache;
- se estiver online, deve sincronizar normalmente;
- se estiver offline, deve operar localmente e sincronizar depois;
- o QR Code não precisa carregar toda a lógica completa, mas deve conter dados suficientes para identificação segura, validação e fallback operacional.

O modo híbrido deve ser tratado como padrão sugerido da solução, mas isso não substitui a implementação completa dos outros dois modos.

Configuração dos modos

O sistema deve permitir configurar o modo de leitura e correção por:
- configuração global;
- tipo de usuário;
- papel;
- função;
- permissão;
- usuário específico.

Essa configuração deve ser administrada no Platform e refletida corretamente no Duoscanner.

Fluxo obrigatório do usuário no Duoscanner

Independentemente do modo selecionado, o fluxo do usuário deve ser simples, automático, previsível e confiável.

Fluxo esperado:

1. O usuário acessa o aplicativo.
2. O aplicativo identifica o usuário autenticado e resolve as configurações aplicáveis.
3. O sistema determina:
   - o tipo de gabarito ativo;
   - o modo de leitura ativo;
   - as permissões do usuário;
   - as provas disponíveis no dispositivo;
   - o estado da sincronização local.
4. O usuário inicia a correção.
5. A câmera é aberta com suporte visual adequado ao enquadramento.
6. O sistema deve detectar automaticamente:
   - bordas da folha;
   - marcadores;
   - orientação;
   - foco;
   - inclinação;
   - iluminação mínima.
7. Quando a folha estiver corretamente posicionada, a captura deve ocorrer automaticamente.
8. A mesma imagem deve ser utilizada para:
   - localizar o QR Code;
   - identificar a prova;
   - validar versão e folha;
   - corrigir perspectiva;
   - localizar áreas de resposta;
   - ler marcações;
   - aplicar a regra de correção;
   - gerar o resultado.
9. O sistema deve exibir imediatamente:
   - nota;
   - acertos;
   - erros;
   - questões em branco;
   - inconsistências de leitura;
   - nível de confiança;
   - opção de revisão manual, quando necessário.
10. Após a correção:
   - se online, sincronizar automaticamente;
   - se offline, armazenar localmente em fila de sincronização;
   - quando a internet retornar, reenviar automaticamente.

Requisitos de UX e UI

A implementação deve garantir:
- fluxo simples;
- mínimo de interação manual;
- captura automática sempre que possível;
- feedback visual claro durante enquadramento;
- estados explícitos de leitura, processamento, sucesso, falha, pendência e sincronização;
- mensagens claras de erro e contingência;
- indicação clara quando a prova não estiver disponível offline;
- indicação clara do modo ativo, quando isso for relevante ao usuário;
- coerência visual entre estados;
- previsibilidade operacional.

Impactos visuais e de interação

Considere e detalhe:
- layout das telas envolvidas;
- estados de scanner;
- loading;
- feedback visual de captura;
- status de sincronização;
- revisão manual;
- erro de leitura;
- erro de identificação da prova;
- prova indisponível offline;
- sucesso de correção;
- fila de sincronização pendente.

Responsividade e plataformas

Detalhe o comportamento esperado para:
- mobile, como prioridade principal;
- desktop e tablet no Platform, para telas administrativas e de configuração;
- consistência entre web e mobile no que diz respeito às nomenclaturas, estados, regras e feedbacks.

Impactos técnicos obrigatórios

A especificação deve cobrir:
- arquitetura entre Platform e Duoscanner;
- contratos de integração;
- modelo de configuração e resolução de precedência;
- persistência local no mobile;
- versionamento de provas e gabaritos;
- sincronização online/offline;
- fila de reenvio com retry;
- auditoria;
- rastreabilidade;
- logs;
- validações;
- estratégia de fallback;
- tratamento de conflito de configuração;
- segurança dos dados;
- segurança do QR Code;
- regras de cache;
- estratégia de atualização local;
- desacoplamento entre tipo de gabarito e modo de leitura.

Requisitos específicos do Platform

O Platform deve permitir:
- cadastrar e manter tipos de gabarito;
- cadastrar e manter modos de leitura;
- definir configurações globais;
- definir configurações por tipo de usuário;
- definir configurações por papel;
- definir configurações por função;
- definir configurações por permissão;
- definir configurações por usuário específico;
- visualizar a regra final resolvida;
- auditar alterações;
- versionar configurações;
- controlar o que é padrão;
- integrar essas definições ao Duoscanner.

Requisitos específicos do Duoscanner

O Duoscanner deve permitir:
- leitura automática da folha;
- detecção automática do QR Code;
- correção em captura única;
- recorte e correção de perspectiva;
- leitura das marcações;
- validação de foco e iluminação;
- funcionamento offline;
- cache local versionado;
- persistência local;
- sincronização posterior;
- reenvio automático com retry;
- revisão manual;
- logs de leitura;
- exibição do status de sincronização;
- tratamento de falhas.

Estrutura mínima do QR Code

A especificação deve prever QR Code compatível com o modo configurado, podendo conter:
- identificador único da prova;
- versão da prova;
- tipo da folha;
- identificador da aplicação;
- identificador do caderno ou página;
- hash, checksum ou assinatura de validação;
- token de segurança quando necessário;
- outros dados estritamente necessários conforme o modo.

O que não fazer

- Não remover o gabarito detalhado.
- Não transformar o gabarito detalhado no padrão.
- Não implementar apenas um dos modos.
- Não tratar o modo híbrido apenas como ideia teórica: ele deve ser implementado.
- Não criar regras de configuração sem precedência clara.
- Não permitir comportamento imprevisível quando houver múltiplas regras.
- Não depender exclusivamente de internet.
- Não exigir duas leituras manuais separadas se o fluxo pode ser resolvido em captura única.
- Não misturar de forma acoplada a lógica do tipo de gabarito com a lógica do modo de leitura.
- Não deixar a UX ambígua ou dependente de interpretação do usuário.
- Não introduzir mudanças visuais ou funcionais fora do escopo descrito.

Critérios de aceite obrigatórios

Considere como obrigatório que a solução final permita demonstrar:

1. Existência e funcionamento dos dois tipos de gabarito:
   - gabarito detalhado;
   - gabarito Essential.

2. Gabarito Essential configurado como padrão do sistema.

3. Existência e funcionamento dos três modos de leitura:
   - dados previamente carregados;
   - dados embutidos no QR Code;
   - híbrido.

4. Modo híbrido definido como padrão sugerido.

5. Configuração funcionando por:
   - global;
   - tipo de usuário;
   - papel;
   - função;
   - permissão;
   - usuário específico.

6. Precedência de configuração funcionando corretamente.

7. Duoscanner operando online e offline conforme o modo configurado.

8. Leitura da folha com captura automática.

9. Leitura do QR Code e do cartão-resposta dentro do mesmo fluxo.

10. Correção funcionando com resultado consistente.

11. Sincronização automática quando houver internet.

12. Persistência local e sincronização posterior quando não houver internet.

13. Estados visuais e mensagens coerentes.

14. Logs, auditoria e rastreabilidade mínimos implementados.

Testes que devem passar

Inclua e detalhe os testes mínimos necessários, cobrindo:
- teste da precedência das configurações;
- teste de aplicação correta do gabarito por regra;
- teste de aplicação correta do modo de leitura por regra;
- teste de funcionamento do gabarito Essential como padrão;
- teste de manutenção do gabarito detalhado como opção;
- teste do fluxo online com sincronização imediata;
- teste do fluxo offline com sincronização posterior;
- teste do modo híbrido;
- teste de leitura por QR Code com captura única;
- teste de fallback;
- teste de persistência local;
- teste de retry de sincronização;
- teste de revisão manual;
- teste de mensagens de erro;
- teste de conflitos de configuração;
- teste de integração entre Platform e Duoscanner.

Ordem correta de implementação

Estruture a implementação em fases, com ordem lógica e dependências bem definidas.

A resposta final deve obrigatoriamente ser entregue nestas seções:

1. Entendimento do escopo
2. Diagnóstico dos problemas atuais
3. Arquitetura proposta
4. Regras de negócio
5. Modelo de configuração e precedência
6. Especificação do gabarito detalhado e do gabarito Essential
7. Especificação dos três modos de leitura
8. Fluxo do usuário no Duoscanner
9. Impactos no Platform
10. Impactos no Duoscanner
11. Impactos em UX/UI
12. Persistência, sincronização e funcionamento offline
13. Segurança e validações
14. Critérios de aceite
15. Testes obrigatórios
16. Ordem de implementação em fases

Execute com precisão máxima. Não resuma demais. Não invente escopo. Não omita regras importantes. Não faça melhorias criativas fora do pedido. Produza uma especificação técnica realmente utilizável por uma IA implementadora.