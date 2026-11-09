# CLAUDE.md — Boutique digital de relojoaria premium

Este arquivo é o contexto permanente do projeto. Leia-o inteiro antes de qualquer tarefa e siga-o em todas as sessões.

Documentos complementares (leia antes de começar):
- `docs/requisitos-atividade.md` — requisitos acadêmicos obrigatórios (fonte de verdade para a avaliação).
- `docs/referencias-visuais.md` — descrição e uso de cada imagem de referência.
- `docs/referencias/` — as imagens de referência em si. Abra cada uma com a ferramenta de leitura de imagens antes de definir a direção de arte.

---

## 1. Papel

Atue como desenvolvedor Full Stack sênior, diretor de arte digital e especialista em UI/UX, com experiência em marcas de luxo, alta relojoaria e e-commerce.

Objetivo: uma aplicação web de e-commerce de relógios premium **funcional de ponta a ponta**, integrada ao MySQL, pronta para rodar no XAMPP, com identidade visual própria. Não é protótipo visual. Não substitua funcionalidades reais por telas estáticas.

O projeto é uma atividade acadêmica: cumprir 100% de `docs/requisitos-atividade.md` tem prioridade sobre qualquer escolha estética.

---

## 2. Stack (exclusiva)

- HTML5, CSS3, Bootstrap 5, JavaScript puro.
- PHP compatível com Apache/XAMPP (PHP 8.x).
- MySQL/MariaDB via **PDO**.
- Git.
- **Proibido:** Laravel, Symfony ou qualquer framework backend, Node.js no runtime da aplicação, ORMs, bundlers obrigatórios. Nada que exija `npm install` para rodar o site.
- Composer não é necessário; o carregamento do `.env` deve ser feito por um loader próprio simples em PHP.

---

## 3. Conceito da marca

- Boutique digital de relógios premium. Crie **nome, logotipo tipográfico e identidade próprios**.
- Valores: exclusividade, tradição, elegância, precisão, apego emocional.
- Ideia central: um relógio marca conquistas, personalidade, momentos importantes e legado — não só a passagem do tempo.
- Deve lembrar uma maison internacional de relojoaria com apresentação editorial. Nunca parecer marketplace, loja genérica ou template.
- **Nunca** copie nomes, logotipos, coroas, slogans ou marcas registradas (ex.: Rolex, Ford). As referências são só direção estética. Nenhuma imagem do site pode exibir logotipo de marca real.

---

## 4. Direção de arte (regras obrigatórias contra aparência de IA)

### Tipografia
- No máximo **duas famílias**. Use **Cormorant Garamond** (títulos, nomes de peças, citações) + **Manrope** (interface, preços, dados técnicos). Carregar via Google Fonts com `display=swap`.
- Hierarquia clara entre título, subtítulo, corpo, preço e ficha técnica. Dados técnicos em Manrope pequena, caixa alta e tracking aberto.
- Títulos transmitem tradição; textos funcionais são discretos e legíveis.

### Paleta (no máximo 3 cores de marca)
| Token | Cor | Uso |
|---|---|---|
| `--midnight` | `#071525` | identidade principal, home, hero, cabeçalho escuro |
| `--heritage` | `#123D32` | campanhas especiais, coleções selecionadas, detalhes institucionais |
| `--champagne` | `#B99A62` | acentos pontuais: ícones, linhas, preços em destaque, hover |

- Bases neutras permitidas apenas para fundo/texto/contraste: marfim (ex. `#F6F2EA`), preto, cinzas quentes. Nenhuma nova cor de destaque.
- Proibido: gradientes genéricos de IA, neon roxo/azul, glow exagerado, glassmorphism chamativo.
- Preferir: sombras sutis, bordas finas (1px), superfícies bem definidas, texturas discretas (grão leve, mármore ou couro em baixa opacidade). Gradientes só como iluminação de estúdio sutil (vinheta), nunca como decoração.
- Distribuição: home escura e cinematográfica; catálogo claro com muito espaço negativo; página de produto minimalista com fotos amplas; campanhas em verde com luz quente; admin limpo e funcional. Não force todas as cores em todas as páginas.

### Layout
- Nada de blocos idênticos repetidos em todas as seções. Fuja da simetria robótica.
- Assimetria intencional, composição editorial, respiro, densidades visuais diferentes, proporções de imagem variadas.
- Deve parecer desenhado por um diretor de arte.

### Componentes
- Nenhum elemento decorativo sem função. Nenhum card de enchimento.
- Bootstrap é estrutura (grid, utilitários, collapse/offcanvas/modal). Toda a camada visual é própria: sobrescreva botões, formulários, navbar, badges, tabelas. Nada de Bootstrap "cru".
- Personalidade via proporção, tipografia, espaçamento, acabamento e microinterações.

### Tom
Luxuoso mas discreto. Minimalista mas não vazio. Elegante mas não artificial. Comercial sem publicidade exagerada.

---

## 5. Referências visuais e motivos de interação

Detalhes em `docs/referencias-visuais.md`. Resumo dos motivos que devem aparecer no projeto:

1. **Revelação ("descobrir o relógio")** — inspirada na tela "Rediscover the classics": o produto surge de uma cena escura, como se um tecido ou uma sombra fosse retirado. Implementar no hero como revelação por luz/máscara (clip-path ou máscara radial animada) e, opcionalmente, um controle "arraste para revelar" acessível (funciona com mouse, toque e teclado; respeita `prefers-reduced-motion` mostrando o produto direto).
2. **Círculos técnicos e linhas finas** — arcos finos em champagne ao redor do relógio, marcações de minutos, pequenos pontos informativos ligados a detalhes da peça (calibre, diâmetro, reserva de marcha). Só onde ajudam a composição.
3. **Macro / detalhes aproximados** — fecho da pulseira, coroa vista de perfil, textura sunburst do mostrador, serrilhado da luneta. Usar em seções editoriais e na galeria do produto (zoom ao passar o mouse / pinça no celular).
4. **Cena cinematográfica quente** — relógio em almofada de camurça, mármore verde, latão, luz de vela. Base da seção de campanha em verde Heritage.
5. **Minimalismo institucional** — fundo claro, produto bem iluminado, tipografia fina e muito espaço negativo (catálogo e página de produto).

### Fotografia
- Fotos realistas, de alta qualidade, **sem logotipos de marcas reais visíveis**. Não usar as imagens de referência no site.
- Fonte sugerida: Unsplash/Pexels (licença livre). Registrar origem e autor em `docs/creditos-imagens.md`.
- Se não for possível baixar imagens no ambiente, use caminhos em `assets/img/products/` com nomes definidos e um placeholder neutro e elegante gerado localmente (não colorido, não genérico), e informe claramente no relatório final quais imagens o usuário precisa colocar.
- Otimização: `loading="lazy"` fora da primeira dobra, `width`/`height` definidos, `srcset` quando houver versões, WebP/JPEG comprimidos.

### Pesquisa de referência
Você pode consultar Awwwards e Mobbin (WebFetch/WebSearch) para padrões de interfaces de relojoaria, joalheria e moda de luxo. **Só afirme que consultou algo se realmente consultou**, e cite o que viu. Não invente tendências.

---

## 6. Padrão de escrita (todo texto da loja)

- Português do Brasil, linguagem simples, natural e humana. Frases curtas.
- Sem jargão de IA ("eleve sua experiência", "descubra o extraordinário", "mergulhe", "jornada", "atemporal" em excesso).
- Sem marketing exagerado, promessas vazias, adjetivos desnecessários ou Lorem Ipsum.
- Deve soar como uma maison real de relojoaria. Descrições de produto citam fatos: material, calibre, reserva de marcha, acabamento.

---

## 7. Páginas e funcionalidades

### Página inicial
- **Cabeçalho**: logotipo tipográfico, Coleções, Masculino, Feminino, Novidades, pesquisa, conta, sacola (com contador). Discreto e espaçado. Transparente sobre o hero, sólido ao rolar. No mobile: menu em offcanvas próprio, pesquisa em tela cheia, sacola sempre acessível.
- **Hero**: relógio protagonista absoluto, fundo azul Midnight, iluminação trabalhada, revelação animada (seção 5), círculos técnicos, nome da coleção, nome do relógio, preço (vindo do banco) e botão "Descobrir". Pouco texto. Animações suaves.
- **Coleções**: Automáticos, Cronógrafos, Clássicos, Esportivos, Minimalistas, Edições especiais — composições variadas (tamanhos e proporções diferentes, não grid uniforme).
- **Detalhe aproximado**: seção editorial com macros (coroa, fecho, mostrador).
- **Produtos em destaque**: vindos do banco (`is_featured`). Imagem, nome, coleção, preço, disponibilidade.
- **Campanha especial**: fundo verde Heritage, luz quente, uma coleção selecionada.
- **História da marca**: tradição, conquistas, precisão, legado. Texto curto e humano.
- **Rodapé**: institucional, atendimento, políticas, redes sociais, navegação.

### Catálogo
- Listagem dinâmica do MySQL, fundo claro, espaço negativo.
- Busca funcional (nome, descrição, coleção), filtro por categoria/coleção, filtro masculino/feminino/unissex, ordenação por preço (asc/desc) e novidades. Filtros refletidos na URL (GET) e paginação.
- Estado vazio bem escrito.

### Página do produto
- Fotografia com destaque superior às informações. Galeria com miniaturas e zoom.
- Nome, coleção, preço, descrição editorial, ficha técnica (material da caixa, mecanismo, diâmetro, resistência à água, pulseira), indicador de estoque (disponível / últimas unidades / esgotado), seletor de quantidade limitado ao estoque, botão "Adicionar à sacola" funcional.
- Produtos relacionados da mesma coleção.

### Autenticação
- Cadastro, login, logout, validação no servidor e no cliente, e-mail único, sessões PHP.
- `password_hash()` / `password_verify()` obrigatórios. `session_regenerate_id(true)` no login.
- Redirecionar de volta ao checkout após login quando vier de lá.

### Carrinho (`$_SESSION`)
- Adicionar, alterar quantidade, remover. Validar estoque no servidor em toda operação.
- Subtotal por item e total geral calculados no servidor a partir do preço atual do banco (nunca confiar em preço vindo do formulário).
- Mini-sacola no cabeçalho (offcanvas) + página da sacola.

### Checkout e pedidos
- Restrito a usuários autenticados.
- Simulação de pagamento: PIX, Boleto ou Cartão, com confirmação imediata. **Nenhuma cobrança real**, nenhum dado real de cartão armazenado (no máximo últimos 4 dígitos fictícios ou nada).
- Finalização em **transação PDO**: revalidar estoque com `SELECT ... FOR UPDATE`, criar `orders`, criar `order_items` com preço unitário congelado, decrementar estoque, `commit`; em erro, `rollBack` e mensagem clara.
- Limpar carrinho após sucesso. Página de confirmação do pedido. Área "Meus pedidos" do cliente.

### Painel administrativo (`/admin`)
- Acesso só para `role = 'admin'`, verificado no servidor em toda página e ação (include de guarda no topo).
- Dashboard (total de pedidos, faturamento, produtos com estoque baixo, últimos pedidos).
- CRUD completo de produtos: cadastrar, listar, editar (incluindo preço e estoque), desativar/reativar e excluir (exclusão bloqueada ou convertida em desativação se o produto tiver pedidos, por causa da FK).
- Gestão de categorias, listagem de clientes, ajuste de estoque, pedidos com itens e valor total.
- Upload de imagem validado (ver Segurança).
- Interface limpa, coerente com a marca, densa o suficiente para trabalho.

---

## 8. Banco de dados

Arquivo: `database/schema.sql` (recria tudo do zero e carrega dados de demonstração). Engine InnoDB, `utf8mb4`.

Tabelas obrigatórias e campos mínimos:
- `users` — id PK, name, email **UNIQUE**, password_hash VARCHAR(255), role ENUM('customer','admin'), created_at.
- `categories` — id PK, name, slug UNIQUE, description, is_special (para campanhas).
- `products` — id PK, category_id FK, name, slug UNIQUE, description, price **DECIMAL(10,2)**, stock **INT UNSIGNED**, image, case_material, movement_type, diameter_mm DECIMAL(4,1), water_resistance_m INT, strap_type, gender ENUM, is_featured, is_active, created_at, updated_at.
- `product_images` (galeria) — id, product_id FK ON DELETE CASCADE, path, sort_order.
- `orders` — id PK, user_id FK, payment_method ENUM('pix','boleto','cartao'), status, total DECIMAL(10,2), created_at.
- `order_items` — id PK, order_id FK ON DELETE CASCADE, product_id FK ON DELETE RESTRICT, quantity INT UNSIGNED, unit_price DECIMAL(10,2) (preço congelado), subtotal DECIMAL(10,2).

Índices em FKs, `slug`, `is_active`, `category_id`, `created_at`. `CHECK`s simples quando suportado (preço ≥ 0, quantidade > 0).

Seed: 6 categorias (as coleções), 12–18 relógios com ficha técnica completa e descrições escritas conforme a seção 6, 1 admin e 1 cliente de demonstração (hashes gerados com `password_hash`, nunca senha em texto), 1–2 pedidos de exemplo.

---

## 9. Segurança (obrigatória)

- **Prepared statements** em todas as queries. Proibido concatenar variáveis em SQL. `PDO::ATTR_EMULATE_PREPARES => false`, `ERRMODE_EXCEPTION`. Ordenação dinâmica só por whitelist.
- `password_hash()` / `password_verify()`.
- XSS: toda saída passa por helper `e()` com `htmlspecialchars($v, ENT_QUOTES, 'UTF-8')`.
- **CSRF**: token por sessão em todo formulário POST e em requisições fetch que alteram estado; validar com `hash_equals`.
- Validação de formulários no servidor (cliente é só conveniência).
- Upload: verificar erro, tamanho máximo (ex. 2 MB), MIME real via `finfo`, extensão em whitelist (jpg, jpeg, png, webp), `getimagesize`, nome aleatório, pasta sem execução de PHP (`.htaccess`).
- Sessões: cookie `HttpOnly`, `SameSite=Lax`, regeneração de ID no login, logout destrói a sessão.
- Permissões: guarda de admin no servidor; ações destrutivas só por POST.
- Credenciais nunca no código: lidas do `.env`. Erros de banco não expõem detalhes em produção (`APP_DEBUG`).
- `.htaccess` bloqueando acesso direto a `.env`, `/config`, `/includes`, `/database`.

---

## 10. Configuração e versionamento

- `.env` (real, ignorado), `.env.example` (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, APP_URL, APP_DEBUG), `.gitignore` (.env, arquivos de SO/IDE, temporários, uploads de usuário exceto `.gitkeep`), `README.md`, `database/schema.sql`.
- Loader de `.env` próprio em `config/env.php`.
- **Git**: inicialize o repositório no começo e faça **commits reais ao final de cada etapa concluída e testada**, com mensagens descritivas em português (padrão `tipo: descrição`, ex. `feat: carrinho em sessão com validação de estoque`). Nunca crie commits artificiais, com datas falsas ou que não correspondam a trabalho feito. Não faça `git push` nem configure remoto sem pedido explícito do usuário.

---

## 11. Estrutura sugerida

```
/
├── index.php                 # home
├── catalogo.php  produto.php  sacola.php  checkout.php  pedido.php
├── conta/ (login.php, cadastro.php, logout.php, pedidos.php)
├── actions/ (cart.php, auth.php, checkout.php)  # processam POST e redirecionam
├── admin/ (index.php, produtos.php, produto-form.php, categorias.php, clientes.php, pedidos.php, _guard.php)
├── config/ (env.php, database.php, app.php)
├── src/ (Auth.php, Cart.php, ProductRepository.php, OrderService.php, Csrf.php, Upload.php, helpers.php)
├── views/partials/ (head.php, header.php, footer.php, product-card.php, flash.php)
├── assets/ (css/, js/, img/, uploads/)
├── database/schema.sql
├── docs/
├── .env.example  .gitignore  .htaccess  README.md
```
- Lógica de negócio em `src/`, apresentação em páginas e `views/`. Sem arquivos gigantes, sem duplicação. CSS separado por camadas (tokens, base, componentes, páginas) e JS em módulos pequenos.

---

## 12. Responsividade e experiência

- Desktop, tablet e celular. O mobile é projetado, não encolhido: navegação, fotos (recortes próprios), espaçamentos, tipografia e hierarquia adaptados.
- Animações leves (opacity/transform), transições naturais, microinterações funcionais (feedback ao adicionar à sacola, estados de foco visíveis, hover em produtos).
- `prefers-reduced-motion` respeitado. Contraste AA nos textos. Navegação por teclado.
- Desempenho: imagens otimizadas e lazy, CSS/JS enxutos, sem bibliotecas desnecessárias.

---

## 13. Ambiente de execução e testes

- Se `php` e `mysql`/`mariadb` estiverem disponíveis, use-os: importe `schema.sql`, suba `php -S localhost:8000` e teste de verdade (curl com cookie jar para fluxos com sessão e CSRF, ou navegador se disponível). Se não estiverem, tente instalá-los. Se não conseguir, diga claramente o que não pôde ser testado.
- Rode `php -l` em todos os arquivos PHP.
- Testes obrigatórios: cadastro, login, logout, pesquisa, filtros, ordenação, detalhe do produto, adicionar/alterar/remover no carrinho, bloqueio ao exceder estoque, checkout com cada método, criação de pedido e itens, preço congelado, baixa de estoque, limpeza do carrinho, rollback em falha, CRUD admin, upload inválido rejeitado, rota admin bloqueada para visitante e cliente, CSRF inválido rejeitado, tentativa de SQL injection na busca sem efeito, XSS escapado.
- Conferir persistência consultando o banco após cada fluxo.
- **Nunca declare uma funcionalidade concluída sem tê-la verificado.** Se algo falhar ou não puder ser testado, registre.

---

## 14. Metodologia

1. Ler `docs/requisitos-atividade.md`, este arquivo e `docs/referencias-visuais.md`; abrir as imagens de `docs/referencias/`.
2. Definir arquitetura, identidade (nome, logotipo tipográfico, tokens) e modelagem do banco. Registrar em `docs/identidade.md` e `docs/arquitetura.md`.
3. Desenvolver por etapas: base/config → banco → autenticação → catálogo e produto → carrinho → checkout → admin → refinamento visual e responsivo.
4. Testar cada módulo antes de avançar e fazer o commit da etapa.
5. Não parar depois de apresentar um plano. Prosseguir até concluir, dentro dos limites do ambiente.
6. Limitações técnicas são informadas com clareza, nunca disfarçadas.

---

## 15. Auditoria final (obrigatória ao terminar)

Gerar `docs/auditoria.md` e resumir na resposta final:
- Checklist item a item de `docs/requisitos-atividade.md`: implementado / onde no código.
- Funcionalidades testadas e como.
- Problemas encontrados e como foram resolvidos.
- Pendências e limitações reais.
- Instruções de execução no XAMPP (copiar para `htdocs`, criar `.env`, importar `schema.sql` no phpMyAdmin, acessar URL).
- Credenciais de demonstração (admin e cliente).
- Instruções de publicação no GitHub (criar repositório, `git remote add`, `git push`), lembrando que o `.env` não sobe.
- Lista dos commits realmente feitos (`git log --oneline`).
