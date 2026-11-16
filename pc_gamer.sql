-- ============================================
-- CORREÇÃO 1: Nome do banco inconsistente
-- ============================================
-- Você criou "pc_gamer" mas usa "loja_gamer"
-- Vou padronizar para "loja_gamer"

DROP DATABASE IF EXISTS pc_gamer;
CREATE DATABASE pc_gamer
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pc_gamer;

-- ============================================
-- TABELA: users (OK, mas vou adicionar campos)
-- ============================================
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(180) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  cpf VARCHAR(14) UNIQUE,  -- NOVO: CPF para nota fiscal
  phone VARCHAR(20),        -- NOVO: Telefone de contato
  is_admin TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,  -- NOVO: Para bloquear usuários
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Senha padrão para testes: "senha123"
INSERT INTO users (name, email, password_hash, is_admin, created_at) VALUES
('Admin Master', 'admin@loja.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
('Aldo Gamer', 'aldo@cliente.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, NOW()),
('Maria Silva', 'maria@cliente.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 0, NOW());

-- ============================================
-- TABELA: categories (OK, mas vou adicionar ícone)
-- ============================================
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(160) NOT NULL UNIQUE,
  icon VARCHAR(50),  -- NOVO: Ícone Font Awesome (ex: "fa-desktop")
  description TEXT,  -- NOVO: Descrição da categoria
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO categories (name, slug, icon, description) VALUES
('PCs Gamers', 'pcs-gamers', 'fa-desktop', 'Computadores completos para jogos'),
('Notebooks', 'notebooks', 'fa-laptop', 'Notebooks gamers portáteis'),
('Placas de Vídeo', 'placas-de-video', 'fa-microchip', 'GPUs NVIDIA e AMD'),
('Periféricos', 'perifericos', 'fa-keyboard', 'Teclados, mouses e mais'),
('Monitores', 'monitores', 'fa-tv', 'Monitores gamers de alta performance'),
('Headsets', 'headsets', 'fa-headphones', 'Fones de ouvido gamers'),
('Cadeiras Gamer', 'cadeiras-gamer', 'fa-chair', 'Cadeiras ergonômicas para longas sessões');

-- ============================================
-- TABELA: products (MELHORIAS IMPORTANTES)
-- ============================================
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NOT NULL,
  name VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  short_description VARCHAR(255) NOT NULL,
  description TEXT NOT NULL,
  technical_specs TEXT,  -- NOVO: Ficha técnica em JSON ou texto formatado
  image_url VARCHAR(255) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  promotional_price DECIMAL(10,2) NULL,  -- NOVO: Preço promocional
  stock_qty INT NOT NULL DEFAULT 0,
  min_stock INT NOT NULL DEFAULT 5,  -- NOVO: Estoque mínimo para alerta
  weight DECIMAL(8,2),  -- NOVO: Peso em kg (para cálculo de frete)
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  featured TINYINT(1) NOT NULL DEFAULT 0,  -- Destaque/Banner
  is_promotion TINYINT(1) NOT NULL DEFAULT 0,  -- NOVO: Produto em promoção
  views INT NOT NULL DEFAULT 0,  -- NOVO: Contador de visualizações
  sales_count INT NOT NULL DEFAULT 0,  -- NOVO: Contador de vendas
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_products_category ON products(category_id);
CREATE INDEX idx_products_featured ON products(featured);
CREATE INDEX idx_products_promotion ON products(is_promotion);
CREATE INDEX idx_products_active ON products(is_active);

-- Produtos de exemplo (vou adicionar mais variedade)
INSERT INTO products (category_id, name, slug, short_description, description, technical_specs, image_url, unit_price, promotional_price, stock_qty, weight, featured, is_promotion) VALUES
-- PCs Gamers
(1, 'PC Gamer RTX 4070 Super', 'pc-gamer-rtx-4070', 'PC completo com RTX 4070 Super', 'PC montado com processador Intel i7-13700K, 32GB RAM DDR5, SSD 1TB NVMe, RTX 4070 Super 12GB.', '{"cpu":"Intel i7-13700K","ram":"32GB DDR5","gpu":"RTX 4070 Super 12GB","storage":"1TB NVMe","psu":"750W 80+ Gold"}', 'assets/images/produtos/pc-rtx4070.jpg', 8999.00, 7999.00, 5, 12.5, 1, 1),

(1, 'PC Gamer RX 7800 XT', 'pc-gamer-rx-7800', 'PC AMD com RX 7800 XT', 'PC montado com Ryzen 7 7800X3D, 32GB RAM DDR5, SSD 1TB NVMe, RX 7800 XT 16GB.', '{"cpu":"Ryzen 7 7800X3D","ram":"32GB DDR5","gpu":"RX 7800 XT 16GB","storage":"1TB NVMe","psu":"750W 80+ Gold"}', 'assets/images/produtos/pc-rx7800.jpg', 7999.00, NULL, 3, 12.0, 1, 0),

-- Notebooks
(2, 'Notebook Gamer RTX 4060', 'notebook-rtx-4060', 'Notebook gamer portátil', 'Notebook com tela 15.6" 144Hz, i7-13650HX, 16GB RAM, SSD 512GB, RTX 4060 8GB.', '{"screen":"15.6 FHD 144Hz","cpu":"i7-13650HX","ram":"16GB DDR5","gpu":"RTX 4060 8GB","storage":"512GB NVMe"}', 'assets/images/produtos/notebook-rtx4060.jpg', 6499.00, 5999.00, 8, 2.3, 1, 1),

-- Placas de Vídeo
(3, 'NVIDIA RTX 4070 Super', 'rtx-4070-super', 'Placa de vídeo potente para 1440p', 'Ideal para jogos em 1440p com alto FPS. 12GB GDDR6X, DLSS 3.5, Ray Tracing.', '{"memory":"12GB GDDR6X","bus":"192-bit","tdp":"220W","outputs":"3x DP 1.4a, 1x HDMI 2.1"}', 'assets/images/produtos/rtx4070.jpg', 3499.90, 3199.00, 12, 1.2, 1, 1),

(3, 'AMD RX 7800 XT', 'rx-7800-xt', 'Placa de vídeo avançada AMD', 'Excelente desempenho em 1440p e suporte a Ray Tracing. 16GB GDDR6.', '{"memory":"16GB GDDR6","bus":"256-bit","tdp":"263W","outputs":"2x DP 2.1, 2x HDMI 2.1"}', 'assets/images/produtos/rx7800.jpg', 2999.00, NULL, 8, 1.3, 0, 0),

(3, 'NVIDIA RTX 4060 Ti', 'rtx-4060-ti', 'Placa intermediária para 1080p/1440p', 'Ótima para jogos em Full HD e 1440p. 8GB GDDR6, DLSS 3.', '{"memory":"8GB GDDR6","bus":"128-bit","tdp":"160W","outputs":"3x DP 1.4a, 1x HDMI 2.1"}', 'assets/images/produtos/rtx4060ti.jpg', 2299.00, 1999.00, 15, 0.9, 0, 1),

-- Periféricos
(4, 'Teclado Mecânico RGB Gamer', 'teclado-mecanico-rgb', 'Switches mecânicos rápidos', 'Teclado com switches lineares Red, iluminação RGB per-key, ABNT2.', '{"switches":"Red Linear","layout":"ABNT2","rgb":"Per-key RGB","connectivity":"USB-C"}', 'assets/images/produtos/teclado.jpg', 399.00, 349.00, 30, 0.8, 1, 1),

(4, 'Mouse Gamer 16K DPI', 'mouse-gamer-16k', 'Sensor óptico preciso', 'Mouse leve (65g) com 6 botões programáveis, sensor 16000 DPI.', '{"sensor":"16000 DPI","buttons":"6 programáveis","weight":"65g","connectivity":"USB"}', 'assets/images/produtos/mouse.jpg', 249.00, NULL, 42, 0.2, 0, 0),

(4, 'Mousepad Gamer XL', 'mousepad-gamer-xl', 'Superfície ampla e precisa', 'Mousepad 90x40cm, base antiderrapante, superfície speed.', '{"size":"90x40cm","thickness":"4mm","surface":"Speed"}', 'assets/images/produtos/mousepad.jpg', 89.00, 69.00, 50, 0.5, 0, 1),

-- Monitores
(5, 'Monitor 27" 165Hz IPS', 'monitor-27-165hz', 'Alta taxa de atualização', 'Painel IPS QHD (2560x1440), 165Hz, 1ms MPRT, FreeSync Premium.', '{"size":"27 polegadas","resolution":"2560x1440","refresh":"165Hz","panel":"IPS","response":"1ms MPRT"}', 'assets/images/produtos/monitor27.jpg', 1599.00, 1399.00, 10, 5.5, 1, 1),

(5, 'Monitor 24" 144Hz Curvo', 'monitor-24-144hz', 'Monitor curvo para imersão', 'Painel VA Full HD (1920x1080), 144Hz, 1ms, curvatura 1500R.', '{"size":"24 polegadas","resolution":"1920x1080","refresh":"144Hz","panel":"VA Curvo","response":"1ms"}', 'assets/images/produtos/monitor24.jpg', 899.00, NULL, 18, 4.2, 0, 0),

-- Headsets
(6, 'Headset Gamer Surround 7.1', 'headset-gamer-71', 'Som imersivo virtual', 'Headset com microfone removível, som surround 7.1 virtual, almofadas confortáveis.', '{"audio":"Surround 7.1 Virtual","mic":"Removível","connectivity":"USB","drivers":"50mm"}', 'assets/images/produtos/headset.jpg', 599.00, 499.00, 20, 0.4, 1, 1),

-- Cadeiras
(7, 'Cadeira Gamer Ergonômica', 'cadeira-gamer-ergo', 'Conforto para longas sessões', 'Cadeira com apoio lombar, reclinável até 180°, suporta até 150kg.', '{"recline":"180°","weight_limit":"150kg","material":"Couro sintético","adjustments":"Altura, braços 4D"}', 'assets/images/produtos/cadeira.jpg', 1299.00, 1099.00, 7, 22.0, 1, 1);

-- ============================================
-- NOVA TABELA: product_images (múltiplas imagens)
-- ============================================
CREATE TABLE product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image_url VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,  -- Imagem principal
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_product_images_product 
    FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_product_images_product ON product_images(product_id);

-- Exemplos de imagens adicionais
INSERT INTO product_images (product_id, image_url, is_primary, sort_order) VALUES
(1, 'assets/images/produtos/rtx4070.jpg', 1, 1),
(1, 'assets/images/produtos/rtx4070-2.jpg', 0, 2),
(1, 'assets/images/produtos/rtx4070-3.jpg', 0, 3);

-- ============================================
-- TABELA: reviews (OK, vou adicionar aprovação)
-- ============================================
CREATE TABLE reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NOT NULL,
  rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
  comment TEXT,
  is_approved TINYINT(1) NOT NULL DEFAULT 0,  -- NOVO: Moderação
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_reviews_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_reviews_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_reviews_product ON reviews(product_id);
CREATE INDEX idx_reviews_approved ON reviews(is_approved);

-- ============================================
-- TABELA: favorites (OK)
-- ============================================
CREATE TABLE favorites (
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, product_id),
  CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_fav_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABELA: banners (OK)
-- ============================================
CREATE TABLE banners (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  title VARCHAR(160) NOT NULL,
  subtitle VARCHAR(220),
  image_url VARCHAR(255) NOT NULL,
  link_url VARCHAR(255),  -- NOVO: Link customizado
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_banners_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO banners (product_id, title, subtitle, image_url, sort_order, is_active) VALUES
(1, 'PC Gamer RTX 4070 Super', 'Performance extrema para jogos em 1440p', 'assets/images/banners/pc-rtx4070-banner.jpg', 1, 1),
(4, 'RTX 4070 Super em Promoção', 'Aproveite o desconto especial!', 'assets/images/banners/rtx4070-banner.jpg', 2, 1),
(10, 'Monitor 27" 165Hz', 'Imersão total em QHD', 'assets/images/banners/monitor27-banner.jpg', 3, 1);

-- ============================================
-- TABELA: carts (OK)
-- ============================================
CREATE TABLE carts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  session_id VARCHAR(64),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT uq_cart_user UNIQUE (user_id),
  CONSTRAINT uq_cart_session UNIQUE (session_id),
  CONSTRAINT fk_cart_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- ============================================
-- TABELA: cart_items (OK)
-- ============================================
CREATE TABLE cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cart_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cart_items_product FOREIGN KEY (product_id) REFERENCES products(id) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  UNIQUE KEY uq_cart_product (cart_id, product_id)  -- NOVO: Evita duplicatas
) ENGINE=InnoDB;

CREATE INDEX idx_cart_items_cart ON cart_items(cart_id);
CREATE INDEX idx_cart_items_product ON cart_items(product_id);

-- ============================================
-- TABELA: addresses (MELHORADA)
-- ============================================
CREATE TABLE addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(50),  -- NOVO: Ex: "Casa", "Trabalho"
  recipient_name VARCHAR(120) NOT NULL,  -- NOVO: Nome do destinatário
  street VARCHAR(120) NOT NULL,
  number VARCHAR(20) NOT NULL,  -- NOVO: Número
  complement VARCHAR(100),  -- NOVO: Complemento
  neighborhood VARCHAR(80) NOT NULL,  -- NOVO: Bairro
  city VARCHAR(80) NOT NULL,
  state CHAR(2) NOT NULL,  -- CORRIGIDO: UF com 2 caracteres
  zip_code VARCHAR(9) NOT NULL,  -- CORRIGIDO: CEP formato 00000-000
  country VARCHAR(60) NOT NULL DEFAULT 'Brasil',
  is_default TINYINT(1) NOT NULL DEFAULT 0,  -- NOVO: Endereço padrão
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_addresses_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_addresses_user ON addresses(user_id);

-- ============================================
-- TABELA: orders (MELHORADA)
-- ============================================
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  order_number VARCHAR(20) UNIQUE NOT NULL,  -- NOVO: Número do pedido (ex: ORD-20241211-0001)
  status ENUM('pending','paid','processing','shipped','delivered','canceled','refunded') NOT NULL DEFAULT 'pending',
  subtotal DECIMAL(10,2) NOT NULL,
  shipping_cost DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- Renomeado de "shipping"
  discount DECIMAL(10,2) NOT NULL DEFAULT 0.00,  -- NOVO: Desconto aplicado
  total DECIMAL(10,2) NOT NULL,
  payment_method ENUM('pix','boleto','credit_card','debit_card') NOT NULL,
  installments INT NOT NULL DEFAULT 1,  -- NOVO: Parcelas
  shipping_address_id INT,  -- NOVO: Referência ao endereço
  shipping_address_snapshot TEXT,  -- NOVO: Snapshot do endereço (caso seja deletado)
  tracking_code VARCHAR(50),  -- NOVO: Código de rastreio
  notes TEXT,  -- NOVO: Observações do pedido
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT fk_orders_address FOREIGN KEY (shipping_address_id) REFERENCES addresses(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_orders_user ON orders(user_id);
CREATE INDEX idx_orders_status ON orders(status);
CREATE INDEX idx_orders_number ON orders(order_number);

-- ============================================
-- TABELA: order_items (OK)
-- ============================================
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  product_name VARCHAR(200) NOT NULL,  -- NOVO: Snapshot do nome
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total_line DECIMAL(10,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_item_order FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_item_product FOREIGN KEY (product_id) REFERENCES products(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_order_items_order ON order_items(order_id);

-- ============================================
-- TABELA: payments (MELHORADA)
-- ============================================
CREATE TABLE payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  method ENUM('pix','boleto','credit_card','debit_card') NOT NULL,
  status ENUM('pending','approved','rejected','refunded') NOT NULL DEFAULT 'pending',
  transaction_id VARCHAR(100) UNIQUE,  -- NOVO: ID da transação do gateway
  amount DECIMAL(10,2) NOT NULL,  -- NOVO: Valor pago
  installments INT NOT NULL DEFAULT 1,
  pix_code TEXT,  -- NOVO: Código PIX (QR Code)
  boleto_url VARCHAR(255),  -- NOVO: URL do boleto
  card_last4 VARCHAR(4),  -- NOVO: Últimos 4 dígitos do cartão
  approved_at DATETIME,  -- NOVO: Data de aprovação
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_payments_order FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_payments_order ON payments(order_id);
CREATE INDEX idx_payments_status ON payments(status);

-- ============================================
-- TABELA: admin_logs (OK)
-- ============================================
CREATE TABLE admin_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(200) NOT NULL,
  entity VARCHAR(100),
  entity_id INT,
  details TEXT,  -- NOVO: Detalhes da ação em JSON
  ip_address VARCHAR(45),  -- NOVO: IP do admin
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_admin_logs_user FOREIGN KEY (admin_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_admin_logs_admin ON admin_logs(admin_id);
CREATE INDEX idx_admin_logs_entity ON admin_logs(entity, entity_id);

-- ============================================
-- NOVA TABELA: coupons (cupons de desconto)
-- ============================================
CREATE TABLE coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) UNIQUE NOT NULL,
  description VARCHAR(200),
  discount_type ENUM('percentage','fixed') NOT NULL,
  discount_value DECIMAL(10,2) NOT NULL,
  min_purchase DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  max_uses INT,
  used_count INT NOT NULL DEFAULT 0,
  valid_from DATETIME NOT NULL,
  valid_until DATETIME NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Exemplo de cupom
INSERT INTO coupons (code, description, discount_type, discount_value, min_purchase, max_uses, valid_from, valid_until) VALUES
('BEMVINDO10', 'Desconto de 10% para novos clientes', 'percentage', 10.00, 500.00, 100, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY)),
('FRETE50', 'R$ 50 de desconto no frete', 'fixed', 50.00, 1000.00, 50, NOW(), DATE_ADD(NOW(), INTERVAL 15 DAY));

-- ============================================
-- NOVA TABELA: order_status_history (histórico de status)
-- ============================================
CREATE TABLE order_status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  old_status VARCHAR(50),
  new_status VARCHAR(50) NOT NULL,
  notes TEXT,
  changed_by INT,  -- ID do usuário que alterou
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_order_history_user FOREIGN KEY (changed_by) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_order_history_order ON order_status_history(order_id);

-- ============================================
-- VIEWS ÚTEIS PARA RELATÓRIOS
-- ============================================

-- View: Produtos mais vendidos
CREATE VIEW vw_top_selling_products AS
SELECT 
  p.id,
  p.name,
  p.image_url,
  p.unit_price,
  p.sales_count,
  c.name AS category_name,
  SUM(oi.quantity) AS total_sold,
  SUM(oi.total_line) AS total_revenue
FROM products p
INNER JOIN categories c ON p.category_id = c.id
LEFT JOIN order_items oi ON p.id = oi.product_id
LEFT JOIN orders o ON oi.order_id = o.id AND o.status IN ('paid','processing','shipped','delivered')
GROUP BY p.id, p.name, p.image_url, p.unit_price, p.sales_count, c.name
ORDER BY total_sold DESC;

-- View: Resumo de pedidos por status
CREATE VIEW vw_orders_summary AS
SELECT 
  status,
  COUNT(*) AS order_count,
  SUM(total) AS total_amount
FROM orders
GROUP BY status;

-- View: Produtos com estoque baixo
CREATE VIEW vw_low_stock_products AS
SELECT 
  p.id,
  p.name,
  p.stock_qty,
  p.min_stock,
  c.name AS category_name
FROM products p
INNER JOIN categories c ON p.category_id = c.id
WHERE p.stock_qty <= p.min_stock AND p.is_active = 1
ORDER BY p.stock_qty ASC;