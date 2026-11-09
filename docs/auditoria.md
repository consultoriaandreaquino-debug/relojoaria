# Auditoria final — Morvane

Data: 09/11/2026 · Ambiente de teste: Windows 11, XAMPP (Apache 2.4, PHP 8.0.30, MariaDB 10.4.32), navegador embutido do Claude (Chromium).

Resumo: todos os itens de `docs/requisitos-atividade.md` foram implementados e verificados no ambiente real (Apache + MariaDB). O teste automatizado `tools/smoke-test.php` passa 73 de 73 verificações. A entrega no GitHub depende do autor (push não foi feito, por instrução).

---

## 1. Checklist dos requisitos

### Etapa 01 — Arquitetura e ambiente
| Requisito | Situação | Onde |
|---|---|---|
| HTML5, CSS3, Bootstrap e JavaScript | Implementado | Bootstrap 5.3.3 local em `assets/vendor/bootstrap/`; CSS próprio em `assets/css/` (tokens, base, components, pages, admin); JS puro em `assets/js/` |
| PHP compatível com Apache/XAMPP | Implementado e testado no Apache do XAMPP | todo o projeto; sem frameworks, sem Composer |
| MySQL/MariaDB com PDO | Implementado | `config/database.php` (PDO, `ERRMODE_EXCEPTION`, `EMULATE_PREPARES=false`) |
| Credenciais fora do código | Implementado | `.env` lido por `config/env.php` (loader próprio) |
| Arquivo baseado em variáveis de ambiente | Implementado | `.env` + `env()` / `env_bool()` |
| `.env` ignorado, `.env.example` no repositório | Implementado e conferido (`git ls-files` não lista `.env`) | `.gitignore`, `.env.example` |
| Prepared statements, sem concatenação de variáveis | Implementado | `src/*.php`, `admin/*.php`; ordenação só por whitelist (`ProductRepository::SORTS`); `IN (?, ?)` montado só com placeholders |
| `password_hash()` / `password_verify()` | Implementado | `src/Auth.php` (`register`, `attempt`, rehash automático) |
| Controle de acesso no servidor ao `/admin` | Implementado | `admin/_guard.php` incluído no topo de todas as páginas do painel |

### Etapa 02 — MVP
| Requisito | Situação | Onde |
|---|---|---|
| Vitrine dinâmica do banco | Implementado | `index.php` (hero, destaques, campanha), `catalogo.php` |
| Filtro por categoria e busca | Implementado | `catalogo.php` + `ProductRepository::search()` (busca, coleção, gênero, disponibilidade, ordenação, paginação, tudo na URL) |
| Página do produto (nome, preço, descrição, imagem, estoque) | Implementado | `produto.php` (galeria, zoom, ficha técnica, indicador disponível/últimas unidades/esgotado) |
| Adicionar ao carrinho | Implementado | `actions/cart.php` + `src/Cart.php`; `fetch` com CSRF e fallback sem JS |
| Alterar quantidade sem exceder estoque | Implementado | `Cart::add/update` revalidam no servidor; seletor limitado no cliente |
| Remover item | Implementado | `actions/cart.php` (`remove`, `clear`) |
| Subtotal e total dinâmicos | Implementado | `Cart::details()` calcula com `bcmath` a partir do preço atual do banco |
| Checkout só para autenticados | Implementado | `checkout.php` e `actions/checkout.php` (`Auth::requireLogin`), retorno ao checkout após login |
| Simulação PIX/Boleto/Cartão com confirmação imediata | Implementado | `checkout.php`, `OrderService::validateCheckout` (cartão de teste com Luhn; só os 4 últimos dígitos são gravados) |
| Criação do pedido | Implementado | `OrderService::place()` |
| Registro de cada item | Implementado | `order_items` com nome, quantidade, preço congelado e subtotal |
| Decremento de estoque | Implementado | `UPDATE ... SET stock = stock - ? WHERE id = ? AND stock >= ?` dentro da transação, após `SELECT ... FOR UPDATE` |
| Limpeza do carrinho | Implementado | `actions/checkout.php` limpa só depois do `commit` |
| Painel restrito a admin | Implementado | `admin/_guard.php` (visitante → login; cliente → 403; todo POST com CSRF) |
| CRUD de produtos (cadastrar, listar, editar preço/estoque, excluir/desativar) | Implementado | `admin/produtos.php`, `admin/produto-form.php`, `src/ProductAdmin.php` |
| Pedidos com itens e valor total | Implementado | `admin/pedidos.php` (lista com resumo dos itens; detalhe com itens, preço congelado, total e troca de status) |

### Etapa 03 — Banco de dados (`database/schema.sql`)
| Requisito | Situação | Onde |
|---|---|---|
| Clientes x administradores, e-mail único, senha criptografada | Implementado | `users.role ENUM('customer','admin')`, `UNIQUE (email)`, `password_hash VARCHAR(255)` |
| Produtos ligados a categorias com preço, estoque e imagem | Implementado | `products.category_id` FK, `price DECIMAL(10,2)`, `stock INT UNSIGNED`, `image`; galeria em `product_images` |
| Pedido com cliente, data, pagamento e total | Implementado | `orders.user_id`, `created_at`, `payment_method ENUM('pix','boleto','cartao')`, `total DECIMAL(10,2)` |
| N:N pedidos x produtos com quantidade e preço congelado | Implementado | `order_items` (`quantity INT UNSIGNED`, `unit_price DECIMAL(10,2)`, `subtotal`) |
| PKs, FKs e tipos corretos | Implementado | FKs com `RESTRICT`/`CASCADE` conforme o caso, índices em FKs, slug, `is_active`, `created_at`; `CHECK` de preço ≥ 0 e quantidade > 0 (verificados: o MariaDB recusa preço negativo) |

### Etapa 04 — Entrega e versionamento
| Requisito | Situação | Onde |
|---|---|---|
| Repositório Git | Repositório local criado. **Push não feito** (instrução do autor); ver seção 7 | `.git/` |
| Histórico contínuo de commits | 10 commits, um por etapa testada (seção 8) | `git log` |
| `.gitignore` | Implementado | ignora `.env`, uploads, arquivos de SO/IDE, temporários e as imagens de referência de terceiros |
| `schema.sql` recriando tudo com carga inicial | Implementado e importado várias vezes do zero | `database/schema.sql` |
| `.env.example` | Implementado | raiz do projeto |

---

## 2. O que foi testado e como

Todos os testes rodaram contra o Apache do XAMPP em `http://localhost/relojoaria` e o MariaDB real, conferindo o banco depois de cada fluxo.

**Automatizado — `tools/smoke-test.php` (73/73 ok)**, cliente HTTP com cookies e CSRF reais:
- bloqueio de `.env`, `config/`, `src/`, `database/`, `views/`, `tools/` (403);
- home com preço do banco; catálogo, filtro por coleção e por gênero, ordenação por preço, paginação; produto e 404;
- SQL injection na busca (`' OR 1=1 --`) sem efeito; XSS na busca escapado; ordenação fora da whitelist ignorada;
- sacola: adicionar (com preço falso no POST, ignorado), alterar, remover, bloqueio acima do estoque, peça esgotada, CSRF inválido (403), GET em ação (405);
- cadastro (e-mail duplicado recusado, hash bcrypt no banco, retorno ao checkout), logout, login com senha errada e certa;
- checkout com PIX, boleto e cartão: pedido criado, itens com preço congelado, baixa de estoque, total igual à soma dos itens, sacola limpa, só 4 dígitos do cartão;
- estoque alterado no meio da compra: pedido recusado sem gravar nada; reajuste de preço não altera pedido antigo; cliente não vê pedido de outro (404);
- painel: visitante → login, cliente → 403, admin abre todas as páginas, POST sem CSRF → 403, upload de PHP disfarçado e imagem pequena recusados, cadastro com foto, edição de preço/estoque, ajuste rápido de estoque, desativação tirando da loja, exclusão de peça vendida virando desativação, exclusão de peça sem pedidos apagando registro e arquivo, coleção com peças protegida, troca de status.

**Manual, com curl e consultas SQL:**
- rollback real: com `CHECKOUT_FAILPOINT=1` no `.env` (só age com `APP_DEBUG=true`), a falha acontece depois de inserir pedido, itens e baixar estoque; o banco voltou ao estado anterior (estoque, `orders` e `order_items` iguais; o `AUTO_INCREMENT` consumido prova que houve insert desfeito), a sacola continuou e a mensagem apareceu. Depois de tirar a flag, a mesma sacola fechou o pedido;
- uploads recusados: `.php`, JPG com código PHP, PNG com extensão `.jpg`, 50×50 px e 5,8 MB. Arquivo `.php` colocado à mão em `assets/uploads/products/` não executa (403);
- limite de 5 tentativas de login por sessão; `next` externo (`//evil.com`) ignorado; ID de sessão trocado no login;
- XSS armazenado: nome `Teste <b>Silva</b>` gravado e exibido escapado;
- `APP_DEBUG=false` com senha errada no `.env`: página 500 neutra, sem SQLSTATE nem caminho de arquivo; quadro de credenciais de demonstração some do login;
- `php -l` em todos os 44 arquivos PHP: sem erros.

**No navegador (Chromium embutido):** home, catálogo, produto, sacola, mini-sacola via `fetch`, cadastro com validação no cliente, login, checkout completo com cartão de teste, confirmação, painel (dashboard, produtos, formulário). Layout conferido em 1024 px e 375 px (menu offcanvas, pesquisa em tela cheia, filtros, galeria). A revelação do hero foi testada nos dois caminhos: com `prefers-reduced-motion` (peça aparece direto) e forçando o modo animado pelo console (abre até 52%, soltar o controle acima de 70% completa até 100% e anuncia "Peça revelada").

---

## 3. Problemas encontrados e como foram resolvidos
| Problema | Solução |
|---|---|
| Os documentos estavam na subpasta `Challenge 4 bimestre`; o site precisa abrir em `/relojoaria` | Conteúdo movido para a raiz do projeto |
| 19 de 20 fotos livres do Unsplash mostravam marcas reais nos mostradores | Fotos descartadas; criado `tools/render_watches.py`, que renderiza as 17 peças e as cenas editoriais sem marcas |
| CSRF inválido devolvia 500: o Apache não reconhece o código 419 | Trocado para 403 |
| "Adicionar à sacola" recarregava a página: o campo `name="action"` sombreava `form.action` no JS | `form.getAttribute('action')` em `cart.js` |
| Busca por "ouro" trazia peças de "couro" | Busca passou a casar no início das palavras |
| Texto enviado em codificação não UTF-8 gravava caracteres inválidos | `input()` descarta valores que não são UTF-8 válido |
| Com o banco fora do ar, a página de erro saía com HTML duplicado | Saída do cabeçalho em buffer, descartada se falhar |
| Rótulos técnicos do hero saíam da tela no celular; linha decorativa cortava texto | Posições e estilos ajustados para ≤ 575 px |
| Foco da pesquisa entrava com atraso e perdia o que era digitado | Foco imediato |
| Marca de "pedido confirmado" não aparecia com redução de movimento | Traço visível por padrão nesse modo |
| `src/Upload.php` ficou fora do commit do painel (`3512469`) e entrou no commit seguinte (`5d0afe6`) | Registrado aqui; o histórico não foi reescrito |

---

## 4. Pendências e limitações reais
- **Push para o GitHub não foi feito** (instrução explícita). A entrega exige o link do repositório remoto; os passos estão na seção 7.
- **Fotografia:** as imagens são renderizações geradas por script, coerentes e sem marcas, mas não são fotos reais. Para um resultado fotográfico, substitua os arquivos em `assets/img/products/` (mesmos nomes) ou envie novas fotos pelo painel.
- **Fontes e Google Fonts:** Cormorant Garamond e Manrope vêm do Google Fonts; sem internet, o site usa as fontes de reserva (Georgia/system-ui). O Bootstrap é local e funciona offline.
- **Página 404 do Apache:** URLs que não existem como arquivo mostram a página padrão do Apache (o `ErrorDocument` exigiria fixar o caminho `/relojoaria` no `.htaccess`). Produtos e pedidos inexistentes usam a página 404 própria.
- **Pagamento e e-mail:** simulados. Nenhuma cobrança, nenhum e-mail enviado.
- **Testes de navegador:** feitos só no Chromium embutido. Firefox, Safari e leitores de tela não foram testados. Contraste de cores calculado (seção de identidade), não auditado com ferramenta externa.
- **Limite de login** é por sessão (um atacante pode trocar de cookie); suficiente para o escopo acadêmico, não para produção.
- **Pesquisa de referência** (Awwwards/Mobbin): não consultada. A direção de arte saiu das imagens de referência enviadas e do `CLAUDE.md`.

---

## 5. Executar no XAMPP
1. Copie a pasta para `C:\xampp\htdocs\relojoaria`.
2. Copie `.env.example` para `.env` e ajuste usuário/senha do MySQL e `APP_URL` (padrão: `http://localhost/relojoaria`).
3. Ligue Apache e MySQL no painel do XAMPP.
4. phpMyAdmin → **Importar** → `database/schema.sql` (cria o banco `relojoaria` e os dados de demonstração).
5. Acesse `http://localhost/relojoaria`.
6. Opcional: `C:\xampp\php\php.exe tools\smoke-test.php` e depois reimporte o `schema.sql`.

## 6. Credenciais de demonstração
| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | admin@morvane.com.br | Morvane@Admin26 |
| Cliente | cliente@morvane.com.br | Morvane@Cliente26 |

Cartão de teste: `4242 4242 4242 4242`, validade futura, CVV de 3 dígitos.

## 7. Publicar no GitHub
1. Crie um repositório vazio no GitHub (sem README, para não conflitar).
2. Na pasta do projeto:
   ```
   git remote add origin https://github.com/SEU-USUARIO/relojoaria.git
   git branch -M main
   git push -u origin main
   ```
3. Confira no GitHub que `.env` **não** aparece (ele está no `.gitignore`); só o `.env.example` sobe. As imagens de `docs/referencias/` também ficam fora, por conterem marcas de terceiros.

## 8. Commits realmente feitos (`git log --oneline`)
```
507b1a8 test: teste de ponta a ponta em PHP CLI e README de instalação
5d0afe6 style: ajustes responsivos, busca por início de palavra e página de atendimento
3512469 feat: painel administrativo com CRUD de produtos, coleções e pedidos
56441a0 feat: checkout com pagamento simulado e pedido em transação PDO
d0c6126 feat: cadastro, login e logout com sessões PHP
c4cddf1 feat: vitrine, catálogo, página do produto e sacola em sessão
b8329c2 feat: fotografia de estúdio gerada localmente, sem marcas de terceiros
1a88af1 feat: modelagem do banco com dados de demonstração
b2f2683 chore: estrutura base, loader de .env, conexão PDO e proteções do Apache
```
Mais o commit que adiciona esta auditoria (`docs: auditoria final`).
