# Morvane — boutique de relojoaria

E-commerce acadêmico de relógios premium, feito em **PHP 8 + PDO + MySQL/MariaDB + Bootstrap 5**, sem frameworks, pronto para o XAMPP. Marca fictícia, pagamentos simulados.

- Vitrine com revelação animada do relógio, coleções, catálogo com busca, filtros e paginação
- Página de produto com galeria, zoom e ficha técnica
- Sacola em `$_SESSION`, checkout com PIX/boleto/cartão simulados e pedido em transação
- Painel `/admin` com CRUD de produtos, coleções, pedidos e clientes

## Requisitos
- XAMPP com PHP 8.0 ou mais novo (extensões `pdo_mysql`, `fileinfo`, `mbstring`, `bcmath` — todas vêm no XAMPP)
- MySQL 8 ou MariaDB 10.4+
- Apache com `mod_rewrite` e `AllowOverride All` (padrão do XAMPP)

## Instalação no XAMPP
1. Copie a pasta do projeto para `C:\xampp\htdocs\relojoaria`.
2. Copie `.env.example` para `.env` e ajuste se precisar:
   ```
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_NAME=relojoaria
   DB_USER=root
   DB_PASS=
   APP_URL=http://localhost/relojoaria
   APP_DEBUG=false
   ```
   `APP_URL` precisa terminar no nome da pasta usada em `htdocs`.
3. Ligue Apache e MySQL no painel do XAMPP.
4. Importe o banco: phpMyAdmin → **Importar** → `database/schema.sql`. Ou pelo terminal:
   ```
   C:\xampp\mysql\bin\mysql.exe -u root < database\schema.sql
   ```
   O script cria o banco `relojoaria`, recria todas as tabelas e carrega os dados de demonstração.
5. Acesse `http://localhost/relojoaria`.

## Contas de demonstração
| Perfil | E-mail | Senha |
|---|---|---|
| Administrador | admin@morvane.com.br | Morvane@Admin26 |
| Cliente | cliente@morvane.com.br | Morvane@Cliente26 |

As senhas estão no banco apenas como hash (`password_hash`). Cartão de teste no checkout: `4242 4242 4242 4242`, qualquer validade futura, CVV de 3 dígitos.

## Estrutura
```
index.php catalogo.php produto.php sacola.php checkout.php pedido.php atendimento.php
conta/      login, cadastro, logout, meus pedidos
actions/    cart.php, auth.php, checkout.php (só POST, com CSRF)
admin/      painel; _guard.php no topo de cada página
config/     env.php (loader do .env), database.php (PDO), app.php (bootstrap)
src/        Auth, Cart, Csrf, ProductRepository, CategoryRepository, OrderService, ProductAdmin, Upload, helpers
views/      partes de layout (cabeçalho, rodapé, cartões) e página de erro
assets/     css em camadas, js em módulos, imagens, vendor/bootstrap, uploads
database/   schema.sql
docs/       requisitos, identidade, arquitetura, créditos de imagens, auditoria
tools/      render_watches.py (gera as fotos) e smoke-test.php (teste de ponta a ponta)
```
Mais detalhes em `docs/arquitetura.md` e `docs/identidade.md`.

## Segurança
- Credenciais só no `.env` (fora do Git); `.htaccess` bloqueia `.env`, `config/`, `src/`, `views/`, `database/`, `tools/` e `docs/`.
- Todas as consultas com prepared statements (`ATTR_EMULATE_PREPARES = false`); ordenação só por lista fixa.
- `password_hash`/`password_verify`, `session_regenerate_id` no login, cookie `HttpOnly` + `SameSite=Lax`.
- Token CSRF em todo POST e em todo `fetch` que altera estado.
- Saída escapada com `e()` (`htmlspecialchars`).
- Upload validado (tamanho, MIME real, extensão, dimensões, nome aleatório) em pasta que não executa PHP.
- Com `APP_DEBUG=false`, erros mostram só uma mensagem neutra.

## Teste de ponta a ponta
Com o banco recém-importado e o Apache ligado:
```
C:\xampp\php\php.exe tools\smoke-test.php
```
São 73 verificações (catálogo, sacola, conta, checkout com os três pagamentos, painel, uploads, CSRF, SQL injection, XSS). O teste cria pedidos e altera estoque: importe o `schema.sql` de novo depois.

## Publicar no GitHub
```
git remote add origin https://github.com/SEU-USUARIO/relojoaria.git
git branch -M main
git push -u origin main
```
O `.env` não vai para o repositório (está no `.gitignore`); quem clonar copia o `.env.example`.

## Imagens
As fotos dos relógios são renderizações geradas por `tools/render_watches.py`, sem marcas reais. Veja `docs/creditos-imagens.md`.
