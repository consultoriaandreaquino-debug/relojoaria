# Créditos e origem das imagens

## Resumo
Todas as imagens exibidas no site foram **geradas localmente** pelo script `tools/render_watches.py` (Python + numpy + Pillow). Nenhuma fotografia de terceiros é usada no site, e nenhuma imagem exibe marca real.

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
| `assets/img/placeholder.svg` | imagem neutra para produto sem foto |

Fontes usadas apenas para escrever números e a marca fictícia dentro das renderizações: Times New Roman, Georgia e Arial do Windows.

## Como trocar por fotografias reais
Basta salvar fotos próprias (sem marcas de terceiros) com os mesmos nomes em `assets/img/products/`, ou enviar novas imagens pelo painel em **Admin → Produtos → Editar**. Formatos aceitos no upload: JPG, PNG e WebP até 2 MB.

## Imagens de referência
As imagens em `docs/referencias/` foram enviadas pelo autor do projeto apenas como direção estética. Elas contêm marcas registradas, **não são usadas no site** e estão no `.gitignore` para não serem publicadas no repositório.
