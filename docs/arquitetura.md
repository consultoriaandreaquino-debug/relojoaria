# Arquitetura

PHP 8 puro (compatível com o PHP 8.0 do XAMPP), PDO/MySQL, Bootstrap 5 servido localmente e JavaScript sem bibliotecas. Nenhuma dependência de Composer ou npm.

## Fluxo de uma requisição
1. A página (`catalogo.php`, `admin/produtos.php`...) inclui `config/app.php`.
2. `config/app.php` carrega o `.env` (`config/env.php`), define constantes, registra o autoload de `src/`, abre a sessão com cookie `HttpOnly` + `SameSite=Lax` e instala o tratador de erros.
3. A página busca dados pelos repositórios em `src/` e renderiza usando `views/partials/`.
4. Formulários enviam POST para `actions/*.php` (loja) ou para a própria página (admin). Toda ação valida CSRF, valida os dados no servidor e responde com redirect (padrão Post/Redirect/Get) ou JSON quando chamada por `fetch`.

## Pastas
| Pasta | Conteúdo |
|---|---|
| `/` | páginas públicas: `index.php`, `catalogo.php`, `produto.php`, `sacola.php`, `checkout.php`, `pedido.php` |
| `conta/` | `login.php`, `cadastro.php`, `logout.php`, `pedidos.php` |
| `actions/` | `cart.php`, `auth.php`, `checkout.php` — só POST |
| `admin/` | painel; `_guard.php` no topo de todas as páginas |
| `config/` | `env.php` (loader), `database.php` (PDO), `app.php` (bootstrap) |
| `src/` | regras de negócio: `Auth`, `Cart`, `Csrf`, `ProductRepository`, `CategoryRepository`, `OrderService`, `Upload`, `helpers.php` |
| `views/` | `partials/` (head, header, footer, cartões, flash) e `error.php` |
| `assets/` | `css/` em camadas, `js/` em módulos pequenos, `img/`, `uploads/` (sem execução de PHP), `vendor/bootstrap` |
| `database/` | `schema.sql` |
| `tools/` | `render_watches.py` — gera as imagens de produto (uso opcional em desenvolvimento, não faz parte do runtime) |

`config/`, `src/`, `views/`, `database/`, `tools/` e `docs/` têm acesso negado pelo Apache (`.htaccess`).

## Modelo de dados
```
users 1───N orders 1───N order_items N───1 products N───1 categories
                                              │
                                              1───N product_images
```
- `order_items` resolve o N:N entre pedidos e produtos e guarda `unit_price` e `product_name` do momento da compra.
- `order_items.product_id` usa `ON DELETE RESTRICT`: produto vendido não pode ser apagado; o admin o desativa.
- Valores monetários em `DECIMAL(10,2)`; estoque e quantidades em `INT UNSIGNED`; `CHECK` de preço ≥ 0 e quantidade > 0.

## Carrinho
`$_SESSION['cart'] = [product_id => quantidade]`. Preço nunca vem do formulário: `Cart::details()` busca o preço atual no banco a cada exibição. Toda operação revalida o estoque.

## Checkout
`OrderService::place()` abre transação, trava as linhas dos produtos com `SELECT ... FOR UPDATE`, confere estoque e ativo, cria `orders`, cria `order_items` com o preço congelado, decrementa o estoque com `UPDATE ... WHERE stock >= ?` e faz `commit`. Qualquer falha gera `rollBack` e mensagem clara ao cliente; o carrinho só é limpo após o commit.

## CSS e JS
- `assets/css/tokens.css` → variáveis de marca
- `base.css` → tipografia, reset leve, utilitários
- `components.css` → botões, formulários, cabeçalho, cartões, sacola
- `pages.css` → home, catálogo, produto, conta, checkout
- `admin.css` → painel
- `assets/js/`: `header.js`, `reveal.js`, `gallery.js`, `cart.js`, `forms.js`, `admin.js`
