# Identidade visual Tembo

## Conceito

O símbolo combina o elefante — associado a memória, inteligência e cuidado — com uma expressão acolhedora. Os cantos arredondados e os azuis suaves tornam a marca próxima do ambiente escolar, sem comprometer a leitura em interfaces administrativas.

## Arquivos oficiais

- `public/brand/tembo-mark.svg`: símbolo isolado para ícones e aplicativo.
- `public/brand/tembo-logo.svg`: assinatura horizontal com tagline.
- `public/favicon.svg`: versão otimizada para navegador.
- `resources/views/components/application-logo.blade.php`: componente da interface Laravel.

Não distorcer, girar, alterar as proporções ou substituir as cores internas do símbolo. A área de respiro mínima ao redor da marca corresponde a 25% da altura do símbolo.

## Paleta

| Token | Hex | Uso |
| --- | --- | --- |
| Primary | `#1D78A6` | Ações principais, links e marca |
| Primary dark | `#155B80` | Hover, texto sobre fundos claros e contraste |
| Primary light | `#DCEFF8` | Seleções, menus ativos e realces suaves |
| Secondary | `#4776BF` | Gráficos e ações complementares |
| Secondary dark | `#345891` | Hover secundário |
| Accent | `#6670B5` | Rubricas e agrupamentos especiais |
| Canvas | `#F5FAFD` | Fundo geral |
| Border | `#D7E6EE` | Divisores e contornos |
| Control border | `#7895A5` | Limites acessíveis de campos e controles |
| Heading | `#173243` | Títulos |
| Text | `#405B69` | Texto corrido |

Verde, âmbar e vermelho são reservados a sucesso, atenção e erro. Eles não devem ser usados como cores de marca.

## Tipografia e componentes

A família principal é Plus Jakarta Sans, já distribuída localmente pelo bundle. Títulos usam pesos 700–800; controles e labels, 600–700; texto corrido, 400–500.

Os componentes canônicos estão em `resources/css/app.css`: `btn-primary`, `btn-secondary`, `btn-danger`, `btn-ghost`, `input-field`, `card`, `badge-*`, `alert-*`, `sidebar-*`, `loading-state` e `loading-spinner`. Novas telas devem reutilizar essas classes e os tokens do `tailwind.config.js` em vez de cores hexadecimais locais.

Os tons interativos escuros foram escolhidos para manter contraste legível com texto branco. Foco de teclado recebe contorno azul visível; animações são reduzidas automaticamente quando o sistema operacional solicita menos movimento.
