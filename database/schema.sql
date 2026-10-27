-- =====================================================================
--  EDM Retail  |  Enterprise Data Management - Retail & E-Commerce Project
--  Database : MySQL / MariaDB (XAMPP)
--  Import   : phpMyAdmin > Import  (or: mysql -u root < schema.sql)
--  NOTE     : This script DROPS and re-creates the database 'edm_retail'.
-- =====================================================================
DROP DATABASE IF EXISTS edm_retail;
CREATE DATABASE edm_retail CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE edm_retail;

-- ---------------------------------------------------------------------
-- 1. MASTER DATA (CRM / Product master / Supplier master)
-- ---------------------------------------------------------------------
CREATE TABLE users (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(100) NOT NULL,
  email         VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('admin','manager','cashier','logistics','customer') NOT NULL DEFAULT 'customer',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at    DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE categories (
  id   INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id      INT AUTO_INCREMENT PRIMARY KEY,
  name    VARCHAR(120) NOT NULL,
  contact VARCHAR(120) NULL
) ENGINE=InnoDB;

CREATE TABLE customers (                      -- CRM source
  id                    INT AUTO_INCREMENT PRIMARY KEY,
  user_id               INT NOT NULL UNIQUE,
  phone                 VARCHAR(30) NULL,
  address               VARCHAR(255) NULL,
  loyalty_points        INT NOT NULL DEFAULT 0,
  preferred_category_id INT NULL,
  consent_at            DATETIME NULL,        -- GDPR consent timestamp
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (preferred_category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE products (                       -- Inventory source
  id            INT AUTO_INCREMENT PRIMARY KEY,
  sku           VARCHAR(30)  NOT NULL UNIQUE,
  name          VARCHAR(150) NOT NULL,
  description   VARCHAR(500) NULL,
  category_id   INT NOT NULL,
  supplier_id   INT NULL,
  price         DECIMAL(10,2) NOT NULL,
  cost_price    DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock_qty     INT NOT NULL DEFAULT 0,
  reorder_level INT NOT NULL DEFAULT 10,
  emoji         VARCHAR(10) NOT NULL DEFAULT '📦',
  is_active     TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB;

CREATE TABLE offers (                         -- Personalized offers
  id               INT AUTO_INCREMENT PRIMARY KEY,
  title            VARCHAR(150) NOT NULL,
  category_id      INT NOT NULL,
  discount_percent DECIMAL(5,2) NOT NULL,
  valid_from       DATE NOT NULL,
  valid_to         DATE NOT NULL,
  is_active        TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 2. TRANSACTIONS (POS + Online storefront share ONE orders table)
-- ---------------------------------------------------------------------
CREATE TABLE orders (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  order_no         VARCHAR(30) NOT NULL UNIQUE,
  channel          ENUM('online','pos') NOT NULL,
  customer_id      INT NULL,                  -- NULL = walk-in at POS
  cashier_id       INT NULL,                  -- set for POS sales
  status           ENUM('processing','shipped','delivered','completed','cancelled') NOT NULL,
  subtotal         DECIMAL(12,2) NOT NULL,
  discount         DECIMAL(12,2) NOT NULL DEFAULT 0,
  total            DECIMAL(12,2) NOT NULL,
  payment_method   ENUM('cash','cod','gcash_sim','card_sim') NOT NULL,
  shipping_address VARCHAR(255) NULL,
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES customers(id),
  FOREIGN KEY (cashier_id)  REFERENCES users(id),
  INDEX idx_orders_created (created_at),
  INDEX idx_orders_channel (channel)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  order_id        INT NOT NULL,
  product_id      INT NOT NULL,
  product_name    VARCHAR(150) NOT NULL,      -- snapshot at time of sale
  qty             INT NOT NULL,
  unit_price      DECIMAL(10,2) NOT NULL,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  line_total      DECIMAL(12,2) NOT NULL,     -- qty*unit_price - discount_amount
  FOREIGN KEY (order_id)   REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE shipments (                      -- Logistics provider data
  id          INT AUTO_INCREMENT PRIMARY KEY,
  order_id    INT NOT NULL UNIQUE,
  carrier     VARCHAR(60) NOT NULL DEFAULT 'Local Courier',
  tracking_no VARCHAR(40) NOT NULL,
  status      ENUM('pending','picked_up','in_transit','delivered','failed') NOT NULL DEFAULT 'pending',
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  product_id   INT NOT NULL,
  change_qty   INT NOT NULL,
  reason       ENUM('sale','restock','cancel_return','adjustment') NOT NULL,
  ref_order_id INT NULL,
  user_id      INT NULL,
  note         VARCHAR(200) NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. GOVERNANCE (audit trail) + CDC (change data capture)
-- ---------------------------------------------------------------------
CREATE TABLE audit_logs (
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NULL,
  actor      VARCHAR(150) NOT NULL,
  action     VARCHAR(50)  NOT NULL,
  table_name VARCHAR(50)  NULL,
  record_id  INT NULL,
  old_value  TEXT NULL,
  new_value  TEXT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_audit_action (action),
  INDEX idx_audit_created (created_at)
) ENGINE=InnoDB;

CREATE TABLE cdc_product_log (                -- filled automatically by MySQL triggers
  id         BIGINT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  op         ENUM('INSERT','UPDATE') NOT NULL,
  old_price  DECIMAL(10,2) NULL,
  new_price  DECIMAL(10,2) NULL,
  old_stock  INT NULL,
  new_stock  INT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. MINI DATA WAREHOUSE (ETL target)
-- ---------------------------------------------------------------------
CREATE TABLE dw_sales_fact (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  order_item_id INT NOT NULL UNIQUE,          -- prevents duplicate loads
  order_id      INT NOT NULL,
  order_no      VARCHAR(30) NOT NULL,
  sale_date     DATE NOT NULL,
  channel       VARCHAR(10) NOT NULL,         -- standardized: ONLINE / POS
  customer_name VARCHAR(100) NOT NULL,        -- standardized: Title Case
  category      VARCHAR(80) NOT NULL,
  product_sku   VARCHAR(30) NOT NULL,
  product_name  VARCHAR(150) NOT NULL,
  qty           INT NOT NULL,
  revenue       DECIMAL(12,2) NOT NULL,
  cost          DECIMAL(12,2) NOT NULL,
  profit        DECIMAL(12,2) NOT NULL,
  loaded_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dw_date (sale_date)
) ENGINE=InnoDB;

CREATE TABLE dw_daily_sales (
  sale_date DATE NOT NULL,
  channel   VARCHAR(10) NOT NULL,
  category  VARCHAR(80) NOT NULL,
  units     INT NOT NULL,
  revenue   DECIMAL(14,2) NOT NULL,
  profit    DECIMAL(14,2) NOT NULL,
  PRIMARY KEY (sale_date, channel, category)
) ENGINE=InnoDB;

CREATE TABLE etl_runs (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  started_at  DATETIME NOT NULL,
  finished_at DATETIME NULL,
  extracted   INT NOT NULL DEFAULT 0,
  loaded      INT NOT NULL DEFAULT 0,
  rejected    INT NOT NULL DEFAULT 0,
  duplicates  INT NOT NULL DEFAULT 0,
  status      ENUM('running','success','failed') NOT NULL DEFAULT 'running',
  message     VARCHAR(255) NULL,
  run_by      VARCHAR(150) NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 5. VIEW = "Data Virtualization": one unified sales view across channels,
--    no data copied (see slide: Integration Methods)
-- ---------------------------------------------------------------------
CREATE VIEW v_sales_unified AS
SELECT oi.id AS order_item_id, o.id AS order_id, o.order_no,
       DATE(o.created_at) AS sale_date, o.created_at,
       UPPER(o.channel) AS channel, o.status,
       c.name AS category, p.sku AS product_sku, oi.product_name,
       oi.qty, oi.line_total AS revenue,
       (oi.qty * p.cost_price) AS cost,
       (oi.line_total - oi.qty * p.cost_price) AS profit
FROM order_items oi
JOIN orders o     ON o.id = oi.order_id
JOIN products p   ON p.id = oi.product_id
JOIN categories c ON c.id = p.category_id
WHERE o.status <> 'cancelled';

-- =================== SEED DATA ===================
INSERT INTO categories(id,name) VALUES (1,'Grocery'),(2,'Beverages'),(3,'Personal Care'),(4,'Household'),(5,'Electronics');
INSERT INTO suppliers(id,name,contact) VALUES (1,'Metro Foods Trading','metro@supplier.test'),(2,'Island Beverage Corp','island@supplier.test'),(3,'CleanCare Distributors','cleancare@supplier.test'),(4,'TechParts PH','techparts@supplier.test');
INSERT INTO users(id,name,email,password_hash,role) VALUES
(1,'System Administrator','admin@edmretail.test','$2y$10$BvSqI9WX.NcHd5uPWzxzOO4NyVyw2SmfgXPBzfIrnbg3l1AAVr3xS','admin'),
(2,'Ines Manager','manager@edmretail.test','$2y$10$BvSqI9WX.NcHd5uPWzxzOO4NyVyw2SmfgXPBzfIrnbg3l1AAVr3xS','manager'),
(3,'Carlo Cashier','cashier@edmretail.test','$2y$10$BvSqI9WX.NcHd5uPWzxzOO4NyVyw2SmfgXPBzfIrnbg3l1AAVr3xS','cashier'),
(4,'Swift Courier API','logistics@edmretail.test','$2y$10$BvSqI9WX.NcHd5uPWzxzOO4NyVyw2SmfgXPBzfIrnbg3l1AAVr3xS','logistics'),
(5,'Kenji Bunani','kenji@gmail.com','$2y$10$BvSqI9WX.NcHd5uPWzxzOO4NyVyw2SmfgXPBzfIrnbg3l1AAVr3xS','customer'),
(6,'Angelica Salinas','angelica@gmail.com','$2y$10$BvSqI9WX.NcHd5uPWzxzOO4NyVyw2SmfgXPBzfIrnbg3l1AAVr3xS','customer'),
(7,'PEDRO PENDUKO','pedro@edmretail.test','$2y$10$BvSqI9WX.NcHd5uPWzxzOO4NyVyw2SmfgXPBzfIrnbg3l1AAVr3xS','customer');
INSERT INTO customers(id,user_id,phone,address,loyalty_points,preferred_category_id,consent_at) VALUES
(1,5,'09171234567','12 Mabini St, Mandaue City, Cebu',0,2,NOW()),
(2,6,'09281234567','45 Colon St, Cebu City',0,1,NOW()),
(3,7,NULL,NULL,0,NULL,NOW());   -- intentionally incomplete: shows up in Data Quality report
INSERT INTO products(id,sku,name,description,category_id,supplier_id,price,cost_price,stock_qty,reorder_level,emoji) VALUES
(1,'GRC-001','Jasmine Rice 5kg','Quality jasmine rice 5kg for everyday use.',1,1,285,230,80,20,'🍚'),
(2,'GRC-002','Canned Sardines 155g','Quality canned sardines 155g for everyday use.',1,1,24,17,200,50,'🐟'),
(3,'GRC-003','Instant Noodles Pack','Quality instant noodles pack for everyday use.',1,1,18,12,300,80,'🍜'),
(4,'BEV-001','Bottled Water 1L','Quality bottled water 1l for everyday use.',2,2,20,12,150,40,'💧'),
(5,'BEV-002','Instant Coffee 3-in-1 (30s)','Quality instant coffee 3-in-1 (30s) for everyday use.',2,2,125,95,90,25,'☕'),
(6,'BEV-003','Orange Juice 1L','Quality orange juice 1l for everyday use.',2,2,89,65,60,20,'🍊'),
(7,'PC-001','Shampoo 340ml','Quality shampoo 340ml for everyday use.',3,3,135,98,50,15,'🧴'),
(8,'PC-002','Bath Soap 3-Pack','Quality bath soap 3-pack for everyday use.',3,3,79,55,70,20,'🧼'),
(9,'HH-001','Dishwashing Liquid 500ml','Quality dishwashing liquid 500ml for everyday use.',4,3,65,44,12,20,'🫧'),
(10,'HH-002','Laundry Detergent 2kg','Quality laundry detergent 2kg for everyday use.',4,3,245,190,40,15,'🧺'),
(11,'EL-001','USB-C Charging Cable','Quality usb-c charging cable for everyday use.',5,4,199,120,35,10,'🔌'),
(12,'EL-002','Wireless Mouse','Quality wireless mouse for everyday use.',5,4,449,300,25,8,'🖱️'),
(13,'EL-003','Power Bank 10000mAh','Quality power bank 10000mah for everyday use.',5,4,899,640,6,8,'🔋');
INSERT INTO offers(title,category_id,discount_percent,valid_from,valid_to) VALUES
('Beverage Lovers - 10% off drinks',2,10,CURDATE()-INTERVAL 30 DAY,CURDATE()+INTERVAL 90 DAY),
('Home Care Saver - 8% off household',4,8,CURDATE()-INTERVAL 30 DAY,CURDATE()+INTERVAL 90 DAY),
('Tech Deals - 5% off electronics',5,5,CURDATE()-INTERVAL 30 DAY,CURDATE()+INTERVAL 90 DAY),
('Pantry Stocker - 5% off grocery',1,5,CURDATE()-INTERVAL 30 DAY,CURDATE()+INTERVAL 90 DAY);
INSERT INTO orders(id,order_no,channel,customer_id,cashier_id,status,subtotal,discount,total,payment_method,shipping_address,created_at) VALUES
(1,'TMP-1','pos',2,3,'completed',79,0,79,'cash',NULL,NOW() - INTERVAL 9 DAY - INTERVAL 8 HOUR),
(2,'TMP-2','online',1,NULL,'delivered',1158,0,1158,'card_sim','12 Mabini St, Mandaue City, Cebu',NOW() - INTERVAL 9 DAY - INTERVAL 9 HOUR),
(3,'TMP-3','pos',1,3,'completed',3225,0,3225,'cash',NULL,NOW() - INTERVAL 8 DAY - INTERVAL 8 HOUR),
(4,'TMP-4','pos',2,3,'completed',250,0,250,'cash',NULL,NOW() - INTERVAL 7 DAY - INTERVAL 2 HOUR),
(5,'TMP-5','online',1,NULL,'delivered',36,0,36,'cod','12 Mabini St, Mandaue City, Cebu',NOW() - INTERVAL 7 DAY - INTERVAL 7 HOUR),
(6,'TMP-6','online',2,NULL,'delivered',54,0,54,'gcash_sim','45 Colon St, Cebu City',NOW() - INTERVAL 7 DAY - INTERVAL 6 HOUR),
(7,'TMP-7','online',2,NULL,'delivered',1060,0,1060,'card_sim','45 Colon St, Cebu City',NOW() - INTERVAL 7 DAY - INTERVAL 7 HOUR),
(8,'TMP-8','online',1,NULL,'delivered',571,0,571,'gcash_sim','12 Mabini St, Mandaue City, Cebu',NOW() - INTERVAL 6 DAY - INTERVAL 3 HOUR),
(9,'TMP-9','pos',NULL,3,'completed',471,0,471,'cash',NULL,NOW() - INTERVAL 5 DAY - INTERVAL 8 HOUR),
(10,'TMP-10','online',2,NULL,'delivered',415,0,415,'cod','45 Colon St, Cebu City',NOW() - INTERVAL 5 DAY - INTERVAL 1 HOUR),
(11,'TMP-11','online',3,NULL,'shipped',4243,0,4243,'cod','7 Rizal Ave, Lapu-Lapu City',NOW() - INTERVAL 4 DAY - INTERVAL 8 HOUR),
(12,'TMP-12','pos',1,3,'completed',570,0,570,'cash',NULL,NOW() - INTERVAL 4 DAY - INTERVAL 7 HOUR),
(13,'TMP-13','pos',2,3,'completed',680,0,680,'cash',NULL,NOW() - INTERVAL 3 DAY - INTERVAL 9 HOUR),
(14,'TMP-14','online',1,NULL,'shipped',323,0,323,'card_sim','12 Mabini St, Mandaue City, Cebu',NOW() - INTERVAL 3 DAY - INTERVAL 2 HOUR),
(15,'TMP-15','pos',1,3,'completed',1340,0,1340,'cash',NULL,NOW() - INTERVAL 3 DAY - INTERVAL 9 HOUR),
(16,'TMP-16','pos',NULL,3,'completed',135,0,135,'cash',NULL,NOW() - INTERVAL 3 DAY - INTERVAL 5 HOUR),
(17,'TMP-17','pos',NULL,3,'completed',288,0,288,'cash',NULL,NOW() - INTERVAL 3 DAY - INTERVAL 8 HOUR),
(18,'TMP-18','online',3,NULL,'shipped',2247,0,2247,'gcash_sim','7 Rizal Ave, Lapu-Lapu City',NOW() - INTERVAL 2 DAY - INTERVAL 6 HOUR),
(19,'TMP-19','online',2,NULL,'shipped',173,0,173,'card_sim','45 Colon St, Cebu City',NOW() - INTERVAL 2 DAY - INTERVAL 5 HOUR),
(20,'TMP-20','pos',1,3,'completed',795,0,795,'cash',NULL,NOW() - INTERVAL 1 DAY - INTERVAL 9 HOUR),
(21,'TMP-21','online',1,NULL,'processing',185,0,185,'card_sim','12 Mabini St, Mandaue City, Cebu',NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR),
(22,'TMP-22','online',3,NULL,'processing',405,0,405,'card_sim','7 Rizal Ave, Lapu-Lapu City',NOW() - INTERVAL 0 DAY - INTERVAL 9 HOUR),
(23,'TMP-23','online',2,NULL,'processing',933,0,933,'card_sim','45 Colon St, Cebu City',NOW() - INTERVAL 0 DAY - INTERVAL 2 HOUR),
(24,'TMP-24','pos',NULL,3,'completed',691,0,691,'cash',NULL,NOW() - INTERVAL 0 DAY - INTERVAL 5 HOUR),
(25,'TMP-25','pos',NULL,3,'completed',268,0,268,'cash',NULL,NOW() - INTERVAL 0 DAY - INTERVAL 6 HOUR),
(26,'TMP-26','pos',1,3,'completed',428,0,428,'cash',NULL,NOW() - INTERVAL 0 DAY - INTERVAL 9 HOUR);
UPDATE orders SET order_no = CONCAT('ORD-', DATE_FORMAT(created_at,'%Y%m%d'), '-', LPAD(id,5,'0'));
INSERT INTO order_items(order_id,product_id,product_name,qty,unit_price,discount_amount,line_total) VALUES
(1,8,'Bath Soap 3-Pack',1,79,0,79),
(2,1,'Jasmine Rice 5kg',3,285,0,855),
(2,3,'Instant Noodles Pack',2,18,0,36),
(2,6,'Orange Juice 1L',3,89,0,267),
(3,13,'Power Bank 10000mAh',3,899,0,2697),
(3,11,'USB-C Charging Cable',2,199,0,398),
(3,9,'Dishwashing Liquid 500ml',2,65,0,130),
(4,5,'Instant Coffee 3-in-1 (30s)',2,125,0,250),
(5,3,'Instant Noodles Pack',2,18,0,36),
(6,3,'Instant Noodles Pack',3,18,0,54),
(7,10,'Laundry Detergent 2kg',2,245,0,490),
(7,1,'Jasmine Rice 5kg',2,285,0,570),
(8,3,'Instant Noodles Pack',1,18,0,18),
(8,6,'Orange Juice 1L',2,89,0,178),
(8,5,'Instant Coffee 3-in-1 (30s)',3,125,0,375),
(9,5,'Instant Coffee 3-in-1 (30s)',1,125,0,125),
(9,8,'Bath Soap 3-Pack',1,79,0,79),
(9,6,'Orange Juice 1L',3,89,0,267),
(10,1,'Jasmine Rice 5kg',1,285,0,285),
(10,9,'Dishwashing Liquid 500ml',2,65,0,130),
(11,12,'Wireless Mouse',3,449,0,1347),
(11,11,'USB-C Charging Cable',1,199,0,199),
(11,13,'Power Bank 10000mAh',3,899,0,2697),
(12,1,'Jasmine Rice 5kg',2,285,0,570),
(13,12,'Wireless Mouse',1,449,0,449),
(13,3,'Instant Noodles Pack',2,18,0,36),
(13,9,'Dishwashing Liquid 500ml',3,65,0,195),
(14,3,'Instant Noodles Pack',2,18,0,36),
(14,6,'Orange Juice 1L',3,89,0,267),
(14,4,'Bottled Water 1L',1,20,0,20),
(15,7,'Shampoo 340ml',3,135,0,405),
(15,13,'Power Bank 10000mAh',1,899,0,899),
(15,3,'Instant Noodles Pack',2,18,0,36),
(16,7,'Shampoo 340ml',1,135,0,135),
(17,11,'USB-C Charging Cable',1,199,0,199),
(17,6,'Orange Juice 1L',1,89,0,89),
(18,13,'Power Bank 10000mAh',2,899,0,1798),
(18,12,'Wireless Mouse',1,449,0,449),
(19,2,'Canned Sardines 155g',3,24,0,72),
(19,9,'Dishwashing Liquid 500ml',1,65,0,65),
(19,3,'Instant Noodles Pack',2,18,0,36),
(20,10,'Laundry Detergent 2kg',3,245,0,735),
(20,4,'Bottled Water 1L',3,20,0,60),
(21,4,'Bottled Water 1L',3,20,0,60),
(21,5,'Instant Coffee 3-in-1 (30s)',1,125,0,125),
(22,7,'Shampoo 340ml',3,135,0,405),
(23,1,'Jasmine Rice 5kg',3,285,0,855),
(23,2,'Canned Sardines 155g',1,24,0,24),
(23,3,'Instant Noodles Pack',3,18,0,54),
(24,3,'Instant Noodles Pack',2,18,0,36),
(24,7,'Shampoo 340ml',3,135,0,405),
(24,5,'Instant Coffee 3-in-1 (30s)',2,125,0,250),
(25,5,'Instant Coffee 3-in-1 (30s)',2,125,0,250),
(25,3,'Instant Noodles Pack',1,18,0,18),
(26,8,'Bath Soap 3-Pack',2,79,0,158),
(26,7,'Shampoo 340ml',2,135,0,270);
INSERT INTO shipments(order_id,carrier,tracking_no,status,updated_at) VALUES
(2,'Swift Courier','TRK00000261','delivered',(NOW() - INTERVAL 9 DAY - INTERVAL 9 HOUR) + INTERVAL 2 HOUR),
(5,'Swift Courier','TRK00000576','delivered',(NOW() - INTERVAL 7 DAY - INTERVAL 7 HOUR) + INTERVAL 2 HOUR),
(6,'Swift Courier','TRK00000672','delivered',(NOW() - INTERVAL 7 DAY - INTERVAL 6 HOUR) + INTERVAL 2 HOUR),
(7,'Swift Courier','TRK00000768','delivered',(NOW() - INTERVAL 7 DAY - INTERVAL 7 HOUR) + INTERVAL 2 HOUR),
(8,'Swift Courier','TRK00000871','delivered',(NOW() - INTERVAL 6 DAY - INTERVAL 3 HOUR) + INTERVAL 2 HOUR),
(10,'Swift Courier','TRK00001084','delivered',(NOW() - INTERVAL 5 DAY - INTERVAL 1 HOUR) + INTERVAL 2 HOUR),
(11,'Swift Courier','TRK00001144','in_transit',(NOW() - INTERVAL 4 DAY - INTERVAL 8 HOUR) + INTERVAL 2 HOUR),
(14,'Swift Courier','TRK00001427','in_transit',(NOW() - INTERVAL 3 DAY - INTERVAL 2 HOUR) + INTERVAL 2 HOUR),
(18,'Swift Courier','TRK00001873','in_transit',(NOW() - INTERVAL 2 DAY - INTERVAL 6 HOUR) + INTERVAL 2 HOUR),
(19,'Swift Courier','TRK00001989','in_transit',(NOW() - INTERVAL 2 DAY - INTERVAL 5 HOUR) + INTERVAL 2 HOUR),
(21,'Swift Courier','TRK00002115','pending',NOW() - INTERVAL 1 DAY - INTERVAL 2 HOUR),
(22,'Swift Courier','TRK00002234','pending',NOW() - INTERVAL 0 DAY - INTERVAL 9 HOUR),
(23,'Swift Courier','TRK00002327','pending',NOW() - INTERVAL 0 DAY - INTERVAL 2 HOUR);
UPDATE customers c SET loyalty_points = COALESCE((SELECT FLOOR(SUM(total)/100) FROM orders o WHERE o.customer_id=c.id AND o.status<>'cancelled'),0);

-- ---------------------------------------------------------------------
-- 6. CDC TRIGGERS (created AFTER seeding so the log only captures real changes)
-- ---------------------------------------------------------------------
DELIMITER $$
CREATE TRIGGER trg_products_cdc_insert AFTER INSERT ON products
FOR EACH ROW
BEGIN
  INSERT INTO cdc_product_log(product_id,op,new_price,new_stock)
  VALUES (NEW.id,'INSERT',NEW.price,NEW.stock_qty);
END$$

CREATE TRIGGER trg_products_cdc_update AFTER UPDATE ON products
FOR EACH ROW
BEGIN
  IF OLD.price <> NEW.price OR OLD.stock_qty <> NEW.stock_qty THEN
    INSERT INTO cdc_product_log(product_id,op,old_price,new_price,old_stock,new_stock)
    VALUES (NEW.id,'UPDATE',OLD.price,NEW.price,OLD.stock_qty,NEW.stock_qty);
  END IF;
END$$
DELIMITER ;
