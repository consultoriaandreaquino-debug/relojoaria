-- =====================================================================
-- Morvane — boutique de relojoaria
-- Recria o banco do zero e carrega dados de demonstração.
-- MySQL 8 / MariaDB 10.4+  ·  InnoDB  ·  utf8mb4
-- Importação: phpMyAdmin > Importar > este arquivo
--             ou: mysql -u root < database/schema.sql
-- =====================================================================

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE DATABASE IF NOT EXISTS relojoaria
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;
USE relojoaria;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Usuários: clientes e administradores, e-mail único, senha com hash
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name          VARCHAR(120) NOT NULL,
    email         VARCHAR(190) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Coleções (categorias)
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(80)  NOT NULL,
    slug        VARCHAR(90)  NOT NULL,
    description VARCHAR(400) NULL,
    is_special  TINYINT(1)   NOT NULL DEFAULT 0,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categories_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Produtos: preço DECIMAL, estoque inteiro sem sinal, ficha técnica
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id        INT UNSIGNED NOT NULL,
    name               VARCHAR(120) NOT NULL,
    slug               VARCHAR(140) NOT NULL,
    description        TEXT NOT NULL,
    price              DECIMAL(10,2) NOT NULL,
    stock              INT UNSIGNED NOT NULL DEFAULT 0,
    image              VARCHAR(255) NULL,
    case_material      VARCHAR(120) NOT NULL,
    movement_type      VARCHAR(80)  NOT NULL,
    caliber            VARCHAR(40)  NULL,
    power_reserve_h    SMALLINT UNSIGNED NULL,
    diameter_mm        DECIMAL(4,1) NOT NULL,
    water_resistance_m INT UNSIGNED NOT NULL DEFAULT 30,
    strap_type         VARCHAR(120) NOT NULL,
    dial               VARCHAR(80)  NULL,
    gender             ENUM('masculino','feminino','unissex') NOT NULL DEFAULT 'unissex',
    is_featured        TINYINT(1) NOT NULL DEFAULT 0,
    is_hero            TINYINT(1) NOT NULL DEFAULT 0,
    is_active          TINYINT(1) NOT NULL DEFAULT 1,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_products_slug (slug),
    KEY idx_products_category (category_id),
    KEY idx_products_active (is_active),
    KEY idx_products_featured (is_featured),
    KEY idx_products_created (created_at),
    KEY idx_products_price (price),
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_products_price CHECK (price >= 0),
    CONSTRAINT chk_products_diameter CHECK (diameter_mm > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Galeria de imagens do produto
CREATE TABLE product_images (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    path       VARCHAR(255) NOT NULL,
    alt        VARCHAR(160) NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    KEY idx_product_images_product (product_id, sort_order),
    CONSTRAINT fk_product_images_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Pedidos: cliente, data, forma de pagamento e total
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id              INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED NOT NULL,
    payment_method  ENUM('pix','boleto','cartao') NOT NULL,
    card_last4      CHAR(4) NULL COMMENT 'Só os 4 últimos dígitos do cartão fictício da simulação',
    status          ENUM('pago','em_separacao','enviado','entregue','cancelado') NOT NULL DEFAULT 'pago',
    total           DECIMAL(10,2) NOT NULL,
    shipping_name   VARCHAR(120) NOT NULL,
    shipping_zip    CHAR(9) NOT NULL,
    shipping_address VARCHAR(255) NOT NULL,
    shipping_city   VARCHAR(100) NOT NULL,
    shipping_state  CHAR(2) NOT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_orders_user (user_id),
    KEY idx_orders_created (created_at),
    KEY idx_orders_status (status),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id)
        REFERENCES users (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_orders_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Itens do pedido: resolve o N:N produtos x pedidos e congela o preço
CREATE TABLE order_items (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    order_id     INT UNSIGNED NOT NULL,
    product_id   INT UNSIGNED NOT NULL,
    product_name VARCHAR(120) NOT NULL COMMENT 'Nome no momento da compra',
    quantity     INT UNSIGNED NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL COMMENT 'Preço congelado na compra',
    subtotal     DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_order_items_order (order_id),
    KEY idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order FOREIGN KEY (order_id)
        REFERENCES orders (id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id)
        REFERENCES products (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_order_items_qty CHECK (quantity > 0),
    CONSTRAINT chk_order_items_price CHECK (unit_price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- Dados de demonstração
-- =====================================================================

-- Senhas (geradas com password_hash; veja README):
--   admin@morvane.com.br   / Morvane@Admin26
--   cliente@morvane.com.br / Morvane@Cliente26
INSERT INTO users (id, name, email, password_hash, role, created_at) VALUES
(1, 'Helena Morvane', 'admin@morvane.com.br',   '$2y$10$GNDpGSBdpzTVBaFNhGdVqeY3NZj9DKrTSQD0X4V2qqgmdEtVOAaqu', 'admin',    '2026-01-05 09:00:00'),
(2, 'Rafael Costa',   'cliente@morvane.com.br', '$2y$10$6UYyJ4fhQgF862mMCjW93.41rkq19xQvezXWs83NKrKrM25Ni2TMG', 'customer', '2026-08-20 18:42:00');

INSERT INTO categories (id, name, slug, description, is_special, sort_order) VALUES
(1, 'Automáticos',      'automaticos',      'Movimentos que se dão corda com o próprio pulso. Usados todos os dias, seguem funcionando.', 0, 1),
(2, 'Cronógrafos',      'cronografos',      'Cronômetro mecânico no mostrador, acionado pelos botões laterais. Para medir o que importa.', 0, 2),
(3, 'Clássicos',        'classicos',        'Caixas finas, mostradores limpos, couro. Peças que acompanham terno e camisa sem disputar atenção.', 0, 3),
(4, 'Esportivos',       'esportivos',       'Mergulho, viagem e uso pesado. Lunetas giratórias, vedação reforçada e pulseiras que aguentam água.', 0, 4),
(5, 'Minimalistas',     'minimalistas',     'Só o necessário: horas, minutos e um mostrador sem ruído.', 0, 5),
(6, 'Edições especiais','edicoes-especiais','Séries numeradas e limitadas. Quando acabam, não voltam.', 1, 6);

INSERT INTO products
(id, category_id, name, slug, description, price, stock, image, case_material, movement_type, caliber, power_reserve_h, diameter_mm, water_resistance_m, strap_type, dial, gender, is_featured, is_hero, is_active, created_at) VALUES
(1, 1, 'Ondina 39', 'ondina-39',
 'O Ondina é o relógio que a casa recomenda para quem vai comprar o primeiro automático. A caixa de 39 mm em aço 316L alterna faces escovadas e polidas e fica bem em pulsos finos e largos.\n\nO mostrador azul tem acabamento raios de sol: muda de tom conforme a luz bate. Por dentro, o calibre MV-21 trabalha a 28.800 alternâncias por hora e guarda 42 horas de reserva de marcha. Fundo em safira para ver o rotor decorado.',
 14900.00, 8, 'assets/img/products/ondina-39.webp', 'Aço 316L escovado e polido', 'Automático', 'MV-21', 42, 39.0, 100, 'Bracelete de aço com fecho dobrável', 'Azul raios de sol', 'unissex', 1, 1, 1, '2026-10-02 10:00:00'),

(2, 1, 'Serra 38 Ouro Rosé', 'serra-38-ouro-rose',
 'A Serra em ouro rosé 18k é a versão mais quente da linha. A caixa de 38 mm tem luneta polida estreita, para dar espaço ao mostrador.\n\nO mostrador branco recebe índices aplicados em ouro, um a um. Calibre automático MV-21R, com rotor em ouro rosé e 42 horas de reserva. Pulseira de couro de bezerro marrom, costurada à mão, com fivela no mesmo ouro da caixa.',
 48900.00, 3, 'assets/img/products/serra-38-ouro-rose.webp', 'Ouro rosé 18k', 'Automático', 'MV-21R', 42, 38.0, 50, 'Couro de bezerro marrom com fivela em ouro', 'Branco laqueado', 'unissex', 0, 0, 1, '2026-06-18 10:00:00'),

(3, 1, 'Varanda 40', 'varanda-40',
 'Mostrador cinza-ardósia, ponteiros dauphine e uma data discreta às 3h. O Varanda é um automático para o dia inteiro, do escritório ao jantar.\n\nO calibre MV-24 tem dois tambores e chega a 70 horas de reserva: tire na sexta, coloque na segunda e ele continua andando. Caixa de 40 mm em aço, resistente a 100 metros.',
 16900.00, 12, 'assets/img/products/varanda-40.webp', 'Aço 316L', 'Automático', 'MV-24', 70, 40.0, 100, 'Couro de bezerro cinza', 'Ardósia raios de sol', 'masculino', 0, 0, 1, '2026-04-10 10:00:00'),

(4, 2, 'Telêmetro 42', 'telemetro-42',
 'Mostrador branco com dois contadores pretos, no estilo que os pilotos usavam nos anos 1960. O cronógrafo é acionado pelos botões de 2h e 4h; o contador da esquerda soma até 30 minutos.\n\nCalibre automático MV-C60 com roda de colunas, 60 horas de reserva de marcha. Caixa de 42 mm em aço, vidro de safira abaulado e bracelete de elos maciços.',
 27900.00, 6, 'assets/img/products/telemetro-42.webp', 'Aço 316L', 'Cronógrafo automático', 'MV-C60', 60, 42.0, 100, 'Bracelete de aço com elos maciços', 'Branco com contadores pretos', 'masculino', 1, 0, 1, '2026-09-12 10:00:00'),

(5, 2, 'Interlagos 41', 'interlagos-41',
 'Caixa em titânio grau 5 com tratamento DLC preto: leve no pulso e difícil de riscar. O ponteiro central do cronógrafo é champagne, para ser lido de relance.\n\nUsa o mesmo calibre MV-C60 do Telêmetro, com 60 horas de reserva. A pulseira de borracha FKM aguenta suor, sol e água salgada sem endurecer.',
 31900.00, 2, 'assets/img/products/interlagos-41.webp', 'Titânio grau 5 com DLC preto', 'Cronógrafo automático', 'MV-C60', 60, 41.0, 100, 'Borracha FKM preta', 'Preto fosco', 'masculino', 0, 0, 1, '2026-10-20 10:00:00'),

(6, 2, 'Marés 39 Cronógrafo', 'mares-39-cronografo',
 'Um cronógrafo de corda manual, como os primeiros da casa. Todo dia, alguns giros na coroa. Muita gente gosta justamente desse ritual.\n\nMostrador creme com contadores azuis e escala taquimétrica na borda. Calibre MV-C12, 48 horas de reserva. Caixa de 39 mm em aço, com botões em forma de pistão.',
 23500.00, 0, 'assets/img/products/mares-39-cronografo.webp', 'Aço 316L polido', 'Cronógrafo de corda manual', 'MV-C12', 48, 39.0, 50, 'Couro de bezerro marrom envelhecido', 'Creme com contadores azuis', 'unissex', 0, 0, 1, '2026-03-02 10:00:00'),

(7, 3, 'Tabelião 38', 'tabeliao-38',
 'O relógio que se dá de presente em formaturas e casamentos. Caixa de 38 mm em ouro amarelo 18k, com apenas 8,6 mm de altura para passar sob o punho da camisa.\n\nCorda manual, calibre MV-07, 50 horas de reserva. Mostrador creme com índices finos em ouro e ponteiros em folha. O fundo pode ser gravado na boutique, sem custo.',
 52900.00, 2, 'assets/img/products/tabeliao-38.webp', 'Ouro amarelo 18k', 'Corda manual', 'MV-07', 50, 38.0, 30, 'Couro de jacaré preto com fivela em ouro', 'Creme', 'masculino', 1, 0, 1, '2026-05-15 10:00:00'),

(8, 3, 'Aurora 34', 'aurora-34',
 'A Aurora tem 34 mm e um mostrador prata com guilhoché de linhas concêntricas, gravado em torno mecânico. De perto, parece tecido.\n\nCalibre automático MV-18, compacto, com 38 horas de reserva. O bracelete de aço tem elos curtos que acompanham o pulso sem sobrar.',
 12400.00, 9, 'assets/img/products/aurora-34.webp', 'Aço 316L polido', 'Automático', 'MV-18', 38, 34.0, 50, 'Bracelete de aço de elos curtos', 'Prata guilhochê', 'feminino', 0, 0, 1, '2026-07-22 10:00:00'),

(9, 3, 'Vigília 39', 'vigilia-39',
 'Pequenos segundos às 6h, numa subdivisão própria. É uma solução antiga, que deixa o mostrador mais calmo e mostra que o relógio está vivo.\n\nCorda manual, calibre MV-09, 46 horas de reserva. Mostrador branco opalino, caixa em aço de 39 mm e couro marrom com costura em tom sobre tom.',
 18700.00, 5, 'assets/img/products/vigilia-39.webp', 'Aço 316L', 'Corda manual', 'MV-09', 46, 39.0, 30, 'Couro de bezerro marrom', 'Branco opalino', 'masculino', 0, 0, 1, '2026-02-08 10:00:00'),

(10, 4, 'Abrolhos 300', 'abrolhos-300',
 'Um relógio de mergulho testado a 300 metros, um por um, antes de sair da oficina. A luneta de cerâmica preta gira só no sentido anti-horário, como pede a norma de mergulho.\n\nCalibre automático MV-24, 70 horas de reserva. Ponteiros e índices com material luminescente que dura a noite toda. Pulseira de borracha com extensão para usar sobre a roupa de neoprene.',
 21900.00, 7, 'assets/img/products/abrolhos-300.webp', 'Aço 316L', 'Automático', 'MV-24', 70, 42.0, 300, 'Borracha preta com extensão de mergulho', 'Preto laqueado', 'masculino', 1, 0, 1, '2026-08-30 10:00:00'),

(11, 4, 'Altitude GMT 40', 'altitude-gmt-40',
 'Para quem vive entre dois fusos. O ponteiro GMT dá uma volta a cada 24 horas e é lido na luneta azul. O ponteiro das horas pode ser ajustado sozinho, sem parar o relógio.\n\nCalibre automático MV-24G, 70 horas de reserva. Caixa de 40 mm em aço e bracelete de três elos com ajuste fino no fecho.',
 25400.00, 4, 'assets/img/products/altitude-gmt-40.webp', 'Aço 316L', 'Automático GMT', 'MV-24G', 70, 40.0, 100, 'Bracelete de aço de três elos', 'Preto com luneta azul 24h', 'unissex', 0, 0, 1, '2026-09-25 10:00:00'),

(12, 4, 'Pedra Azul 36', 'pedra-azul-36',
 'Um esportivo de 36 mm, pensado para pulsos menores. Resiste a 200 metros e tem luneta giratória graduada em aço escovado.\n\nMostrador cinza-claro com índices grandes, fácil de ler sob o sol. Calibre automático MV-18, 38 horas de reserva.',
 13900.00, 10, 'assets/img/products/pedra-azul-36.webp', 'Aço 316L escovado', 'Automático', 'MV-18', 38, 36.0, 200, 'Bracelete de aço escovado', 'Cinza-claro', 'feminino', 0, 0, 1, '2026-10-28 10:00:00'),

(13, 5, 'Linha 38', 'linha-38',
 'Mostrador branco fosco, sem números, e ponteiros finos como traços de caneta. A caixa tem 7,9 mm de altura.\n\nPor dentro, o MV-12, um automático extrafino com microrrotor e 40 horas de reserva. A pulseira em malha milanesa ajusta em qualquer ponto, sem furos.',
 9800.00, 15, 'assets/img/products/linha-38.webp', 'Aço 316L', 'Automático extrafino', 'MV-12', 40, 38.0, 30, 'Malha milanesa de aço', 'Branco fosco', 'unissex', 0, 0, 1, '2026-01-20 10:00:00'),

(14, 5, 'Linha 38 Noite', 'linha-38-noite',
 'O mesmo desenho do Linha 38, em preto. Caixa com revestimento PVD preto fosco e mostrador preto, com índices em champagne.\n\nCalibre automático extrafino MV-12, 40 horas de reserva. Pulseira de couro preto com fecho rápido para trocar sem ferramenta.',
 10400.00, 11, 'assets/img/products/linha-38-noite.webp', 'Aço 316L com PVD preto', 'Automático extrafino', 'MV-12', 40, 38.0, 30, 'Couro de bezerro preto', 'Preto fosco', 'unissex', 0, 0, 1, '2026-10-05 10:00:00'),

(15, 5, 'Sereno 33', 'sereno-33',
 'O menor relógio da casa: 33 mm e 7 mm de altura. Mostrador cor de areia, só com marcações nas horas cheias.\n\nCorda manual, calibre MV-05, 44 horas de reserva. Couro bege macio, que fica mais bonito com o uso.',
 8900.00, 3, 'assets/img/products/sereno-33.webp', 'Aço 316L polido', 'Corda manual', 'MV-05', 44, 33.0, 30, 'Couro de bezerro bege', 'Areia', 'feminino', 0, 0, 1, '2026-08-11 10:00:00'),

(16, 6, 'Heritage Verde 40', 'heritage-verde-40',
 'Série de 150 peças numeradas, feita para marcar os 58 anos da casa. Caixa em aço com luneta canelada em ouro amarelo 18k e mostrador verde raios de sol.\n\nCalibre automático MV-24, 70 horas de reserva. O bracelete alterna elos de aço e ouro. Acompanha estojo de madeira e certificado assinado pelo relojoeiro que montou a peça.',
 46800.00, 3, 'assets/img/products/heritage-verde-40.webp', 'Aço 316L e ouro amarelo 18k', 'Automático', 'MV-24', 70, 40.0, 100, 'Bracelete bicolor aço e ouro', 'Verde raios de sol', 'unissex', 1, 0, 1, '2026-10-30 10:00:00'),

(17, 6, 'Heritage Noite 40', 'heritage-noite-40',
 'Irmão do Heritage Verde, com mostrador azul-noite e caixa inteira em ouro amarelo 18k. Série de 80 peças numeradas.\n\nCalibre automático MV-24, 70 horas de reserva. Luneta canelada, coroa com o símbolo da casa e fundo em safira com o número da peça gravado.',
 71500.00, 2, 'assets/img/products/heritage-noite-40.webp', 'Ouro amarelo 18k', 'Automático', 'MV-24', 70, 40.0, 100, 'Couro de jacaré azul-marinho', 'Azul-noite raios de sol', 'masculino', 0, 0, 1, '2026-11-02 10:00:00');

-- Galeria: vista frontal, detalhe do mostrador e vista inclinada
INSERT INTO product_images (product_id, path, alt, sort_order)
SELECT id, CONCAT('assets/img/products/', slug, '.webp'),   CONCAT(name, ', vista frontal'), 1 FROM products;
INSERT INTO product_images (product_id, path, alt, sort_order)
SELECT id, CONCAT('assets/img/products/', slug, '-2.webp'), CONCAT(name, ', detalhe do mostrador'), 2 FROM products;
INSERT INTO product_images (product_id, path, alt, sort_order)
SELECT id, CONCAT('assets/img/products/', slug, '-3.webp'), CONCAT(name, ', vista inclinada'), 3 FROM products;

-- Pedidos de exemplo. O pedido 1 foi feito quando o Linha 38 custava R$ 9.500,00:
-- o preço congelado em order_items continua 9.500,00 mesmo após o reajuste.
INSERT INTO orders (id, user_id, payment_method, card_last4, status, total, shipping_name, shipping_zip, shipping_address, shipping_city, shipping_state, created_at) VALUES
(1, 2, 'pix',    NULL,   'entregue', 9500.00,  'Rafael Costa', '01310-100', 'Av. Paulista, 1500, ap. 82', 'São Paulo', 'SP', '2026-09-14 15:20:00'),
(2, 2, 'cartao', '4242', 'enviado',  22800.00, 'Rafael Costa', '01310-100', 'Av. Paulista, 1500, ap. 82', 'São Paulo', 'SP', '2026-10-28 11:05:00');

INSERT INTO order_items (order_id, product_id, product_name, quantity, unit_price, subtotal) VALUES
(1, 13, 'Linha 38',       1, 9500.00,  9500.00),
(2, 8,  'Aurora 34',      1, 12400.00, 12400.00),
(2, 14, 'Linha 38 Noite', 1, 10400.00, 10400.00);
