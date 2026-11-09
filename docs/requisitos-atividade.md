# Requisitos da atividade — Atividade final do 4º bimestre

> Transcrição do documento original "Challenge 4º Bimestre - Ecommerce". Todos os itens são obrigatórios e serão conferidos na auditoria final.

Desenvolver uma aplicação web de comércio eletrônico (tema livre) implementando o fluxo completo de venda ponta a ponta.

**Objetivo técnico:** consolidar a integração Front-end/Back-end demonstrando manipulação de banco de dados relacional (CRUD), controle de estado de sessão, manipulação de variáveis de ambiente, boas práticas de segurança e controle de versão profissional via Git.

## Etapa 01 — Arquitetura e ambiente

### Requisitos obrigatórios
- [ ] HTML5, CSS3, Bootstrap e JavaScript.
- [ ] PHP compatível com execução via Apache/XAMPP.
- [ ] Banco de dados MySQL/MariaDB utilizando a extensão PDO.

### Gestão de configurações (.env)
- [ ] Credenciais do banco (host, usuário, senha, banco) não podem estar fixadas no código.
- [ ] Usar arquivo de configuração baseado em variáveis de ambiente (.env ou script de configuração isolado).
- [ ] O arquivo com dados sensíveis reais deve ser ignorado pelo Git, mantendo no repositório um modelo (`.env.example`).

### Segurança da aplicação
- [ ] **Prepared statements**: proibida concatenação de variáveis em queries SQL.
- [ ] **Criptografia de senhas**: uso obrigatório de `password_hash()` e `password_verify()`. Senhas em texto puro desclassificam o módulo de autenticação.
- [ ] **Controle de acesso**: validação de sessão no servidor para restringir as rotas do painel administrativo.

## Etapa 02 — MVP

### Catálogo e detalhes
- [ ] Vitrine com listagem dinâmica de produtos vindos do banco.
- [ ] Filtro por categoria ou campo de busca funcional.
- [ ] Página individual do produto com identificação, preço unitário, descrição, imagem e indicador de disponibilidade de estoque.

### Sessão e carrinho de compras (via `$_SESSION`)
- [ ] Adicionar produto ao carrinho.
- [ ] Alterar quantidade (sem exceder o estoque disponível).
- [ ] Remover item.
- [ ] Cálculo dinâmico do subtotal e do total geral.

### Checkout e baixa de estoque
- [ ] Conclusão da compra restrita a usuários autenticados (login/cadastro).
- [ ] Simulação de pagamento: seleção de método (PIX, Boleto ou Cartão) com confirmação imediata.
- [ ] Finalização do pedido:
  - [ ] Criação do registro de pedido.
  - [ ] Registro detalhado de cada item da compra.
  - [ ] Decremento automático no estoque de cada produto vendido.
  - [ ] Limpeza do carrinho na sessão.

### Módulo 4 — Painel administrativo (`/admin`)
- [ ] Acesso restrito a usuários com perfil administrativo.
- [ ] CRUD completo de produtos: cadastrar, listar, editar preço/estoque e excluir/desativar.
- [ ] Visualização dos pedidos com listagem de itens e valor total.

## Etapa 03 — Modelagem do banco de dados

Entregar um script `.sql` funcional que atenda a:
- [ ] **Autenticação e perfis**: diferenciar clientes e administradores, garantir e-mails únicos e suportar senhas criptografadas.
- [ ] **Catálogo**: relacionar produtos a categorias, com controle de preço, estoque e imagem.
- [ ] **Transações de venda**: registrar cliente, data, forma de pagamento e total de cada compra.
- [ ] **Itens do pedido**: resolver a relação N:N entre produtos e pedidos, gravando a quantidade e congelando o preço unitário praticado na compra.
- [ ] **Integridade e tipagem**: PKs, FKs e tipos compatíveis (valores monetários com precisão decimal e contadores inteiros).

## Etapa 04 — Padrão de entrega e versionamento
- [ ] **Repositório Git (GitHub/GitLab)**: entrega via link do repositório remoto. Não são aceitos `.zip`.
- [ ] **Histórico de commits**: deve refletir o processo contínuo de desenvolvimento (proibido commit único do projeto pronto).
- [ ] **`.gitignore`**: obrigatório, ignorando temporários, arquivos de sistema e credenciais reais (`.env`).
- [ ] **Banco**: `schema.sql` na raiz ou em `/database`, recriando todas as tabelas com carga inicial de teste.
- [ ] **`.env.example`**: modelo com os nomes das variáveis de conexão.
