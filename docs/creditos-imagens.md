# Créditos e origem das imagens

## Resumo
Todas as imagens exibidas no site foram **geradas localmente** pelos scripts `tools/render_watches.py` (Python + numpy + Pillow) e `tools/render_ensaio.py` (cenas 3D no Blender/Cycles, usado como módulo Python `bpy`). Nenhuma fotografia de terceiros é usada no site, e nenhuma imagem exibe marca real.

## Por que não há fotos do Unsplash/Pexels
Durante o desenvolvimento foram baixadas e inspecionadas 20 fotos livres de relógios do Unsplash (images.unsplash.com). Em 19 delas o mostrador mostra uma marca registrada legível (Rolex, Omega, Tissot, Fossil, Seiko, Braun, Festina, Apple e outras), o que o projeto proíbe. A única sem marca visível (mão segurando um relógio de mostrador branco) não combinava com as demais. Por isso as fotos foram descartadas e não estão no repositório.

## Arquivos gerados
| Caminho | Conteúdo |
|---|---|
| `assets/img/products/{slug}.webp` | foto principal em estúdio claro (1000×1250) |
| `assets/img/products/{slug}-thumb.webp` | miniatura para listagens (480×600) |
| `assets/img/products/{slug}-2.webp` | macro do mostrador com foco seletivo |
| `assets/img/products/{slug}-3.webp` | vista inclinada em fundo escuro |
| `assets/img/products/{slug}-hero.webp` | recorte com transparência para o hero |
| `assets/img/editorial/macro-mostrador.webp` | mostrador verde raios de sol |
| `assets/img/editorial/macro-coroa.webp` | perfil da caixa com coroa gravada |
| `assets/img/editorial/macro-fecho.webp` | fecho dobrável em ouro |
| `assets/img/editorial/campanha-heritage.webp` | cena da campanha: almofada de camurça, mármore verde, luz de vela |
| `assets/img/editorial/ensaio-xadrez.webp` | ensaio: Heritage Noite 40 de pé num tabuleiro de nogueira e ébano, entre peças de xadrez douradas (2400×1500; `-sm` com 1200×750) |
| `assets/img/editorial/ensaio-clube.webp` | ensaio, vista de cima: Heritage Verde 40 sobre o jornal do Clube AQUINO, óculos de sol, roupão branco com vivo verde e sombra de palmeira (1600×2000; `-sm` com 800×1000) |
| `assets/img/editorial/ensaio-campo.webp` | ensaio: Altitude GMT 40 de pé num green ao pôr do sol, com bola de golfe e bandeira ao fundo (1600×2000; `-sm` com 800×1000) |
| `assets/img/placeholder.svg` | imagem neutra para produto sem foto |

Fontes usadas apenas para escrever números e a marca dentro das renderizações: Times New Roman, Georgia e Arial do Windows. Fora do Windows o script usa os equivalentes livres de mesmas medidas (Liberation Serif/Sans e DejaVu Serif).

## Como trocar por fotografias reais
Basta salvar fotos próprias (sem marcas de terceiros) com os mesmos nomes em `assets/img/products/`, ou enviar novas imagens pelo painel em **Admin → Produtos → Editar**. Formatos aceitos no upload: JPG, PNG e WebP até 2 MB.

## Ensaio "Fim de semana"
As três cenas do ensaio foram inspiradas em fotos de estilo de vida enviadas pelo fundador (xadrez com peças douradas, clube de descanso com roupão e jornal sob céu azul, carrinho de golfe ao pôr do sol). As fotos originais **não foram copiadas nem incluídas no repositório**: serviram só como direção de luz, cor e composição. Nenhuma pessoa, logotipo ou marca real aparece nas cenas geradas.

As cenas são modeladas e renderizadas em 3D com materiais físicos (metal, safira, madeira envernizada, papel, tecido atoalhado, grama em fios), céu físico, luz solar e profundidade de campo de lente. O mostrador de cada relógio usa a mesma arte de `render_watches.py`. Para gerar de novo: `pip install bpy numpy pillow` num ambiente virtual e `python tools/render_ensaio.py` (cerca de uma hora no processador; `--preview` faz uma versão rápida).

## Imagens de referência
As imagens em `docs/referencias/` foram enviadas pelo autor do projeto apenas como direção estética. Elas contêm marcas registradas, **não são usadas no site** e estão no `.gitignore` para não serem publicadas no repositório.
