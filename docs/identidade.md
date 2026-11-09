# Identidade — Morvane

## Nome
**Morvane** — casa de relojoaria fictícia criada para este projeto. Nome inventado, sem relação com marcas existentes. A história usada no site (fundação em 1968, em São Paulo, pela família Morvane) é ficção da marca.

Assinatura curta: **Morvane · Relojoaria desde 1968**
Frase da casa: **"Para os momentos que ficam."**

A ideia central: um relógio marca uma conquista, um começo, uma herança. A casa fala de peças que acompanham a vida de alguém, não de tecnologia ou de status.

## Logotipo tipográfico
- Palavra `MORVANE` em **Cormorant Garamond 500**, caixa alta, tracking `0.32em`.
- Símbolo: um aro fino (1px) com uma única marcação de minuto às 12h, em champagne — lembra o rehaut de um mostrador. Construído em SVG inline (`views/partials/logo.php`), sem imagem.
- Versões: clara (marfim sobre Midnight) e escura (Midnight sobre marfim). O símbolo pode aparecer sozinho como favicon.
- Nada de coroas, brasões ou ornamentos que lembrem marcas reais.

## Tipografia (duas famílias)
| Uso | Família | Detalhe |
|---|---|---|
| Títulos, nomes de peças, citações | Cormorant Garamond (400, 500, itálico 400) | tamanhos grandes, entrelinha curta, itálico para a segunda linha de títulos |
| Interface, preços, dados técnicos | Manrope (300–600) | ficha técnica em 11–12px, caixa alta, tracking 0.14–0.18em |

Carregadas pelo Google Fonts com `display=swap`.

## Paleta (três cores de marca)
| Token | Cor | Onde |
|---|---|---|
| `--midnight` | `#071525` | home, hero, cabeçalho escuro, rodapé |
| `--heritage` | `#123D32` | campanha, edições especiais, detalhes institucionais |
| `--champagne` | `#B99A62` | linhas, ícones, preço em destaque, hover, foco |

Neutros de apoio (não são cores de destaque): marfim `#F6F2EA`, papel `#FBF9F5`, tinta `#14181D`, cinzas quentes `#6B665E` / `#A39E94` / `#E4DED3`.

Contraste verificado (AA): tinta sobre marfim, marfim sobre Midnight e marfim sobre Heritage passam com folga. Champagne é usada em texto apenas em tamanhos grandes ou sobre fundos escuros (sobre Midnight: ~6,9:1); sobre marfim, o preço usa a tinta e a champagne fica só nas linhas.

## Distribuição de cor por página
- **Home**: escura e cinematográfica (Midnight), com respiro em marfim nas seções editoriais.
- **Campanha**: Heritage com luz quente e textura de mármore em baixa opacidade.
- **Catálogo e produto**: claros (marfim/papel), muito espaço negativo, fotos amplas.
- **Admin**: papel e tinta, champagne só em estados ativos.

## Motivos visuais
1. **Revelação** — o relógio do hero sai da sombra por uma máscara de luz radial; o controle "Arraste para revelar" funciona com mouse, toque e teclado. Com `prefers-reduced-motion`, a peça aparece direto.
2. **Círculos técnicos** — arcos de 1px em champagne, marcações de minuto e três pontos informativos ligados a dados reais do banco (calibre, diâmetro, reserva de marcha).
3. **Macro** — coroa de perfil, fecho e mostrador raios de sol em seção editorial assimétrica e no zoom da galeria.
4. **Cena quente** — campanha em verde com mármore e luz lateral.
5. **Minimalismo institucional** — catálogo e produto em fundo claro.

## Acabamento de componentes
- Bordas de 1px, raio quase zero (2px), sombras curtas e suaves.
- Botões: retângulo fino, texto em caixa alta com tracking; o preenchimento entra da esquerda no hover.
- Foco visível em champagne (2px) em todos os elementos interativos.
- Sem gradientes decorativos, sem neon, sem glassmorphism. Vinheta radial só como luz de estúdio.

## Tom de escrita
Português simples, frases curtas, fatos da peça (material, calibre, reserva de marcha, acabamento). Sem "eleve", "mergulhe", "jornada", "descubra o extraordinário".

## Fotografia
As fotos de produto são renderizações de estúdio geradas localmente (`tools/render_watches.py`), porque as fotos livres disponíveis mostram marcas reais nos mostradores. Detalhes e créditos em `docs/creditos-imagens.md`.
