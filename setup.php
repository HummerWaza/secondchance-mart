<?php
// ============================================================
// SecondChance Mart - Database Setup & Seeder
// Visit http://localhost:8000/setup.php  (delete after use)
// ============================================================
error_reporting(E_ALL); ini_set('display_errors', 1); set_time_limit(300);
require_once __DIR__ . '/config/database.php';
$steps = []; $errors = [];
function step($m){global $steps;$steps[]=$m;}
function fail($m){global $errors;$errors[]=$m;}
try { $pdo = getDB(); step("Connected: ".DB_NAME); }
catch(Exception $e){ die('<h2>DB failed: '.htmlspecialchars($e->getMessage()).'</h2>'); }

// ── DROP ALL ─────────────────────────────────────────────────
$drops = ['delivery_status','email_notifications','payments','order_items','orders','cart','products','categories','warehouse_staff','suppliers','admins','customers','users'];
$pdo->exec("SET FOREIGN_KEY_CHECKS=0");
foreach($drops as $t){ try{$pdo->exec("DROP TABLE IF EXISTS $t");}catch(PDOException $e){fail("Drop $t: ".$e->getMessage());} }
$pdo->exec("SET FOREIGN_KEY_CHECKS=1");
step("Old tables dropped");

// ── CREATE TABLES (matching original schema) ─────────────────
$schema = [
"CREATE TABLE users (id INT PRIMARY KEY AUTO_INCREMENT, email VARCHAR(255) NOT NULL, password VARCHAR(255) NOT NULL, role ENUM('customer','admin','supplier','delivery') DEFAULT 'customer', is_active TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_email_role (email, role)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE customers (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, first_name VARCHAR(100) NOT NULL, last_name VARCHAR(100) NOT NULL, phone VARCHAR(20), address TEXT, city VARCHAR(100), postal_code VARCHAR(20), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE admins (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE suppliers (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, company_name VARCHAR(255) NOT NULL, contact_person VARCHAR(255), phone VARCHAR(20), address TEXT, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE warehouse_staff (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, name VARCHAR(255) NOT NULL, vehicle_number VARCHAR(50), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE categories (id INT PRIMARY KEY AUTO_INCREMENT, name VARCHAR(100) NOT NULL, slug VARCHAR(100) UNIQUE NOT NULL, icon VARCHAR(100), description TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE products (id INT PRIMARY KEY AUTO_INCREMENT, supplier_id INT NULL, category_id INT NOT NULL, name VARCHAR(255) NOT NULL, description TEXT, original_price DECIMAL(10,2) NOT NULL, discount_price DECIMAL(10,2) NOT NULL, discount_percentage INT DEFAULT 0, stock_quantity INT DEFAULT 0, expiry_date DATE NULL, image_url VARCHAR(500), deal_type ENUM('near_expiry','overstock','damaged_pkg','seasonal','general') DEFAULT 'general', status ENUM('active','inactive') DEFAULT 'active', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (category_id) REFERENCES categories(id), FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE cart (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, product_id INT NOT NULL, quantity INT DEFAULT 1, added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_cart_item (user_id, product_id), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE orders (id INT PRIMARY KEY AUTO_INCREMENT, user_id INT NOT NULL, order_number VARCHAR(20) UNIQUE NOT NULL, total_amount DECIMAL(10,2) NOT NULL, status ENUM('pending','confirmed','packed','out_for_delivery','delivered','cancelled') DEFAULT 'pending', payment_method ENUM('card','paynow','bank_transfer') NOT NULL, payment_status ENUM('pending','paid','failed') DEFAULT 'pending', shipping_name VARCHAR(255), shipping_phone VARCHAR(20), shipping_address TEXT, shipping_city VARCHAR(100), shipping_postal VARCHAR(20), notes TEXT, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE order_items (id INT PRIMARY KEY AUTO_INCREMENT, order_id INT NOT NULL, product_id INT NOT NULL, product_name VARCHAR(255) NOT NULL, quantity INT NOT NULL, unit_price DECIMAL(10,2) NOT NULL, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE, FOREIGN KEY (product_id) REFERENCES products(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE payments (id INT PRIMARY KEY AUTO_INCREMENT, order_id INT NOT NULL UNIQUE, amount DECIMAL(10,2) NOT NULL, method ENUM('card','paynow','bank_transfer') NOT NULL, status ENUM('pending','completed','failed') DEFAULT 'pending', transaction_id VARCHAR(255), paid_at TIMESTAMP NULL, FOREIGN KEY (order_id) REFERENCES orders(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE email_notifications (id INT PRIMARY KEY AUTO_INCREMENT, order_id INT NULL, recipient_email VARCHAR(255) NOT NULL, recipient_type ENUM('customer','admin','supplier','warehouse'), subject VARCHAR(255), body TEXT, trigger_event VARCHAR(100), status ENUM('sent','failed','pending') DEFAULT 'pending', sent_at TIMESTAMP NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE delivery_status (id INT PRIMARY KEY AUTO_INCREMENT, order_id INT NOT NULL, status VARCHAR(100) NOT NULL, notes TEXT, updated_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE, FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE INDEX idx_products_category ON products(category_id)",
"CREATE INDEX idx_products_status ON products(status)",
"CREATE INDEX idx_orders_user ON orders(user_id)",
];
foreach($schema as $sql){ try{$pdo->exec($sql);}catch(PDOException $e){fail("Schema: ".$e->getMessage());} }
step("13 tables created");

// ── SEED USERS ───────────────────────────────────────────────
$uStmt = $pdo->prepare("INSERT IGNORE INTO users (email,password,role,is_active) VALUES (?,?,?,1)");
$uData = [
    ['admin@secondchancemart.com',  password_hash('admin123',   PASSWORD_DEFAULT), 'admin'],
    ['john@example.com',            password_hash('password123',PASSWORD_DEFAULT), 'customer'],
    ['sarah@example.com',           password_hash('password123',PASSWORD_DEFAULT), 'customer'],
    ['supplier@example.com',        password_hash('password123',PASSWORD_DEFAULT), 'supplier'],
];
foreach($uData as $u){ try{$uStmt->execute($u);}catch(PDOException $e){fail("User: ".$e->getMessage());} }

// Admin profile
$adminId = $pdo->query("SELECT id FROM users WHERE email='admin@secondchancemart.com' AND role='admin'")->fetchColumn();
if($adminId) $pdo->prepare("INSERT IGNORE INTO admins (user_id,name) VALUES (?,?)")->execute([$adminId,'Admin User']);
// Customer profiles
foreach([['john@example.com','John','Tan'],['sarah@example.com','Sarah','Lim']] as $cu){
    $cid = $pdo->query("SELECT id FROM users WHERE email='{$cu[0]}' AND role='customer'")->fetchColumn();
    if($cid) $pdo->prepare("INSERT IGNORE INTO customers (user_id,first_name,last_name) VALUES (?,?,?)")->execute([$cid,$cu[1],$cu[2]]);
}
// Supplier profile
$supUserId = $pdo->query("SELECT id FROM users WHERE email='supplier@example.com' AND role='supplier'")->fetchColumn();
$supplierId = null;
if($supUserId){
    $pdo->prepare("INSERT IGNORE INTO suppliers (user_id,company_name,contact_person,phone) VALUES (?,?,?,?)")->execute([$supUserId,'Demo Supplier Pte Ltd','Demo Contact','+65 9000 0000']);
    $supplierId = $pdo->lastInsertId() ?: $pdo->query("SELECT id FROM suppliers WHERE user_id=$supUserId")->fetchColumn();
}
step("4 demo users seeded (admin@secondchancemart.com / admin123)");

// ── SEED CATEGORIES ──────────────────────────────────────────
$cats = [
    ['Bakery & Bread',        'bakery-bread',        'bread-slice', 'Fresh and packaged breads, buns, cakes'],
    ['Dairy & Eggs',          'dairy-eggs',          'egg',         'Milk, yogurt, cheese, butter, eggs'],
    ['Beverages',             'beverages',           'coffee',      'Juices, teas, coffees, energy drinks, water'],
    ['Snacks & Confectionery','snacks-confectionery','cookie',      'Chips, biscuits, chocolates, sweets, candy'],
    ['Frozen Foods',          'frozen-foods',        'snowflake',   'Frozen meats, seafood, vegetables, ready meals'],
    ['Canned & Packaged',     'canned-packaged',     'archive',     'Canned goods, instant noodles, sauces, condiments'],
    ['Health & Organic',      'health-organic',      'heart',       'Organic, sugar-free, low-fat, health foods'],
    ['Meat & Seafood',        'meat-seafood',        'fish',        'Fresh and chilled meat, fish, poultry'],
    ['Household & Personal',  'household-personal',  'home',        'Cleaning, toiletries, personal care'],
];
$cStmt = $pdo->prepare("INSERT IGNORE INTO categories (name,slug,icon,description) VALUES (?,?,?,?)");
foreach($cats as $c){ try{$cStmt->execute($c);}catch(PDOException $e){fail("Cat: ".$e->getMessage());} }
$catMap = [];
foreach($pdo->query("SELECT id,slug FROM categories") as $row) $catMap[$row['slug']] = $row['id'];
step("9 categories seeded");

// ── IMAGE POOL ───────────────────────────────────────────────
$img = [
  'bread'     =>'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=400&h=300&fit=crop&q=80',
  'buns'      =>'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400&h=300&fit=crop&q=80',
  'cake'      =>'https://images.unsplash.com/photo-1578985545062-69928b1d9587?w=400&h=300&fit=crop&q=80',
  'milk'      =>'https://images.unsplash.com/photo-1563636619-e9143da7973b?w=400&h=300&fit=crop&q=80',
  'yogurt'    =>'https://images.unsplash.com/photo-1488477181946-6428a0291777?w=400&h=300&fit=crop&q=80',
  'cheese'    =>'https://images.unsplash.com/photo-1452195100486-9cc805987862?w=400&h=300&fit=crop&q=80',
  'butter'    =>'https://images.unsplash.com/photo-1589985270826-4b7bb135bc9d?w=400&h=300&fit=crop&q=80',
  'eggs'      =>'https://images.unsplash.com/photo-1582722872445-44dc5f7e3c8f?w=400&h=300&fit=crop&q=80',
  'juice'     =>'https://images.unsplash.com/photo-1621506289937-a8e4df240d0b?w=400&h=300&fit=crop&q=80',
  'tea'       =>'https://images.unsplash.com/photo-1556679343-c7306c1976bc?w=400&h=300&fit=crop&q=80',
  'coffee'    =>'https://images.unsplash.com/photo-1509042239860-f550ce710b93?w=400&h=300&fit=crop&q=80',
  'milo'      =>'https://images.unsplash.com/photo-1544145945-f90425340c7e?w=400&h=300&fit=crop&q=80',
  'water'     =>'https://images.unsplash.com/photo-1548839140-29a749e1cf4d?w=400&h=300&fit=crop&q=80',
  'chips'     =>'https://images.unsplash.com/photo-1566478989037-eec170784d0b?w=400&h=300&fit=crop&q=80',
  'biscuit'   =>'https://images.unsplash.com/photo-1558961363-fa8fdf82db35?w=400&h=300&fit=crop&q=80',
  'chocolate' =>'https://images.unsplash.com/photo-1481391319762-47dff72954d9?w=400&h=300&fit=crop&q=80',
  'candy'     =>'https://images.unsplash.com/photo-1582716401301-b2407dc7563d?w=400&h=300&fit=crop&q=80',
  'frozen'    =>'https://images.unsplash.com/photo-1607623814075-e51df1bdc82f?w=400&h=300&fit=crop&q=80',
  'icecream'  =>'https://images.unsplash.com/photo-1501443762994-82bd5dace89a?w=400&h=300&fit=crop&q=80',
  'nuggets'   =>'https://images.unsplash.com/photo-1562967914-608f82629710?w=400&h=300&fit=crop&q=80',
  'can'       =>'https://images.unsplash.com/photo-1608686207856-001b95cf60ca?w=400&h=300&fit=crop&q=80',
  'noodles'   =>'https://images.unsplash.com/photo-1569718212165-3a8278d5f624?w=400&h=300&fit=crop&q=80',
  'sauce'     =>'https://images.unsplash.com/photo-1563805042-7684c019e1cb?w=400&h=300&fit=crop&q=80',
  'rice'      =>'https://images.unsplash.com/photo-1536304993881-ff86e0c9ef1d?w=400&h=300&fit=crop&q=80',
  'organic'   =>'https://images.unsplash.com/photo-1540420773420-3366772f4999?w=400&h=300&fit=crop&q=80',
  'oats'      =>'https://images.unsplash.com/photo-1495214783159-3503fd1b572d?w=400&h=300&fit=crop&q=80',
  'protein'   =>'https://images.unsplash.com/photo-1593095948071-474c5cc2989d?w=400&h=300&fit=crop&q=80',
  'chicken'   =>'https://images.unsplash.com/photo-1587593810167-a84920ea0781?w=400&h=300&fit=crop&q=80',
  'fish'      =>'https://images.unsplash.com/photo-1510130387422-82bed34b37e9?w=400&h=300&fit=crop&q=80',
  'pork'      =>'https://images.unsplash.com/photo-1432139509613-5c4255815697?w=400&h=300&fit=crop&q=80',
  'shrimp'    =>'https://images.unsplash.com/photo-1565680018434-b513d5e5fd47?w=400&h=300&fit=crop&q=80',
  'salmon'    =>'https://images.unsplash.com/photo-1519708227418-c8fd9a32b7a2?w=400&h=300&fit=crop&q=80',
  'soap'      =>'https://images.unsplash.com/photo-1584305574647-0cc949a2bb9f?w=400&h=300&fit=crop&q=80',
  'detergent' =>'https://images.unsplash.com/photo-1563453392212-326f5e854473?w=400&h=300&fit=crop&q=80',
  'shampoo'   =>'https://images.unsplash.com/photo-1556228720-195a672e8a03?w=400&h=300&fit=crop&q=80',
  'toothpaste'=>'https://images.unsplash.com/photo-1571945153237-4929e783af4a?w=400&h=300&fit=crop&q=80',
  'tissue'    =>'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=400&h=300&fit=crop&q=80',
  'soda'      =>'https://images.unsplash.com/photo-1527960471264-932f39eb5846?w=400&h=300&fit=crop&q=80',
];

// deal_type mapping to original schema enum values:
// general=clearance/flash-sale, near_expiry=near-expiry, overstock=overstock, seasonal=flash-sale
// Format: [cat_slug, name, desc, orig, disc, pct, stock, deal_type, expiry_days, img_key]
$products = [
// BAKERY & BREAD - 22
['bakery-bread','Gardenia Original Classic White Bread 600g','Soft sandwich bread for everyday meals.',3.50,1.80,49,40,'near_expiry',4,'bread'],
['bakery-bread','Sunshine Enriched Sandwich Bread 500g','Fortified white bread with added vitamins.',3.20,1.50,53,35,'near_expiry',3,'bread'],
['bakery-bread','Gardenia Wholemeal Bread 550g','High-fibre wholemeal loaf. Light, fluffy texture.',3.80,2.20,42,30,'general',5,'bread'],
['bakery-bread','Bonjour Crispy French Baguette 250g','Crusty baguette baked fresh. Clearance pack.',4.50,2.00,56,20,'general',2,'bread'],
['bakery-bread','Old Chang Kee Curry Puff Bun 4pcs','Fluffy buns with curry potato filling.',5.90,2.80,53,25,'near_expiry',2,'buns'],
['bakery-bread','BreadTalk Raisin Loaf 400g','Sweet raisin bread loaf. Great for breakfast.',4.20,2.10,50,18,'general',3,'bread'],
['bakery-bread','Polar Puffs Butter Cake 350g','Classic Singapore butter cake from Polar Puffs.',6.50,3.20,51,15,'general',3,'cake'],
['bakery-bread','Prima Taste Pandan Chiffon Cake 400g','Moist pandan-flavoured chiffon cake. Flash sale.',7.80,3.90,50,12,'seasonal',4,'cake'],
['bakery-bread','Sunshine Hotdog Rolls 6pcs','Soft hotdog buns for BBQ and picnics.',3.50,1.70,51,30,'near_expiry',2,'buns'],
['bakery-bread','Gardenia Kaya Bread 400g','Coconut kaya-flavoured pull-apart bread.',4.00,2.00,50,22,'general',3,'bread'],
['bakery-bread','Breadtalk Cranberry Loaf 380g','Soft bread studded with dried cranberries.',4.90,2.50,49,20,'general',4,'bread'],
['bakery-bread','Four Seasons Mantou Buns 10pcs','Traditional steamed Chinese mantou buns.',3.20,1.60,50,28,'near_expiry',3,'buns'],
['bakery-bread','Tip Top White Bread 600g','Standard soft white sandwich loaf.',3.00,1.40,53,45,'near_expiry',2,'bread'],
['bakery-bread','Gardenia Multi-Grain Bread 500g','Nutritious multi-grain loaf with seeds.',4.20,2.20,48,18,'general',5,'bread'],
['bakery-bread','Polar Puffs Chicken Pie 4pcs','Flaky pastry pies with chicken filling.',8.50,4.20,51,12,'general',2,'buns'],
['bakery-bread','Bengawan Solo Pandan Layer Cake 300g','Traditional Singaporean pandan layered cake.',9.80,4.90,50,8,'seasonal',3,'cake'],
['bakery-bread','Sunshine Soft Rolls 6pcs','Light and fluffy dinner rolls. Pack of 6.',2.80,1.40,50,35,'near_expiry',2,'buns'],
['bakery-bread','QQ Rice Mini Mochi 200g','Chewy Japanese-style mochi rice cakes.',4.50,2.20,51,20,'general',5,'buns'],
['bakery-bread','Gardenia Roti Boy Bun 2pcs','Famous coffee bun with buttery filling.',3.80,1.90,50,18,'near_expiry',2,'buns'],
['bakery-bread','Premier Garlic Bread 300g','Pre-sliced garlic butter toast. Oven-ready.',4.20,2.10,50,15,'general',6,'bread'],
['bakery-bread','Bonjour Croissant 4pcs','Buttery flaky croissants. Freshly baked.',5.50,2.70,51,10,'seasonal',2,'buns'],
['bakery-bread','Gardenia Delimas Soft Roll 6pcs','Small soft dinner rolls. Perfect for sandwiches.',3.50,1.75,50,25,'near_expiry',3,'buns'],
// DAIRY & EGGS - 22
['dairy-eggs','Meiji Full Cream Milk 1L','Rich full cream fresh milk by Meiji.',3.50,1.90,46,40,'near_expiry',4,'milk'],
['dairy-eggs','Magnolia UHT Fresh Milk 1L','Long-life pasteurised full cream milk.',3.20,1.60,50,50,'general',14,'milk'],
['dairy-eggs','Marigold HL Milk 1L','Low-fat high calcium milk.',3.80,2.00,47,30,'general',7,'milk'],
['dairy-eggs','F&N Magnolia Pure Farm Milk 2L','Value family pack of fresh pasteurised milk.',6.50,3.20,51,20,'general',5,'milk'],
['dairy-eggs','Meiji Strawberry Milk 200ml x6','Kids favourite strawberry-flavoured milk.',5.80,2.90,50,25,'seasonal',5,'milk'],
['dairy-eggs','Nestle Milo UHT Drink 200ml x24','Ready-to-drink Milo chocolate malt. Carton.',18.90,9.50,50,15,'general',30,'milo'],
['dairy-eggs','Yoplait Strawberry Yogurt 135g x4','Smooth French-style strawberry yogurt.',4.90,2.40,51,30,'near_expiry',5,'yogurt'],
['dairy-eggs','Meiji Plain Yogurt 500g','Natural unsweetened yogurt. Good for digestion.',4.50,2.20,51,22,'near_expiry',5,'yogurt'],
['dairy-eggs','King Cole Vanilla Yogurt 400g','Creamy vanilla yogurt. Snack or breakfast.',3.80,1.90,50,18,'general',6,'yogurt'],
['dairy-eggs','Farmers Union Greek Yogurt 160g x4','Thick and creamy Greek-style yogurt.',6.90,3.50,49,12,'general',7,'yogurt'],
['dairy-eggs','Anchor Cheddar Cheese 250g','New Zealand cheddar cheese block.',6.80,3.40,50,15,'general',30,'cheese'],
['dairy-eggs','President Brie Cheese 125g','Soft French brie. Perfect for cheese boards.',7.50,3.80,49,10,'near_expiry',7,'cheese'],
['dairy-eggs','Kraft Singles Cheese 400g','Classic American-style processed cheese slices.',6.20,3.10,50,20,'general',21,'cheese'],
['dairy-eggs','Anchor Unsalted Butter 250g','Premium New Zealand butter for baking.',5.90,3.00,49,18,'general',30,'butter'],
['dairy-eggs','Lurpak Slightly Salted Butter 200g','Danish butter with light salt. Spreadable.',6.50,3.30,49,12,'general',30,'butter'],
['dairy-eggs','Golden Churn Pure Creamery Butter 340g','Rich creamy butter in a tin.',8.50,4.30,49,10,'general',60,'butter'],
['dairy-eggs','Country Farm Omega-3 Eggs 10pcs','Omega-3 enriched free-range eggs.',4.50,2.30,49,30,'near_expiry',6,'eggs'],
['dairy-eggs','Seng Choon Fresh Eggs 10pcs','Popular Singapore fresh chicken eggs.',3.20,1.60,50,40,'near_expiry',5,'eggs'],
['dairy-eggs','King Egg Free Range Eggs 6pcs','Cage-free premium eggs.',3.80,1.90,50,22,'general',7,'eggs'],
['dairy-eggs','Snowflakes Fresh Milk 1L','Locally produced fresh pasteurised milk.',3.50,1.80,49,28,'near_expiry',3,'milk'],
['dairy-eggs','Dutch Lady Low Fat Milk 1L','Reduced-fat milk for everyday drinking.',3.30,1.65,50,25,'near_expiry',4,'milk'],
['dairy-eggs','Meiji Hokkaido Cheese Spread 150g','Smooth cream cheese spread from Meiji.',5.50,2.80,49,15,'general',14,'cheese'],
// BEVERAGES - 22
['beverages','Pokka Green Tea 500ml x24','Unsweetened Japanese-style green tea. Carton.',18.00,9.00,50,20,'general',60,'tea'],
['beverages','Yeo\'s Chrysanthemum Tea 300ml x24','Traditional Chinese chrysanthemum flower tea.',14.50,7.20,50,25,'general',90,'tea'],
['beverages','F&N Ice Lemon Tea 1.5L','Refreshing lemon tea. Great party drink.',3.80,1.90,50,30,'general',60,'tea'],
['beverages','Nestle Milo 3-in-1 Packet 33g x20','Convenient milo sachets for hot or cold drinks.',8.90,4.50,49,35,'general',120,'milo'],
['beverages','Nescafe Classic 3-in-1 200g','Smooth blend instant coffee powder tin.',8.50,4.20,51,28,'general',90,'coffee'],
['beverages','OldTown 3-in-1 White Coffee 40g x15','Malaysian white coffee sachets.',9.80,4.90,50,22,'general',120,'coffee'],
['beverages','Pokka Lychee Drink 500ml x24','Sweet lychee-flavoured still drink. Carton.',15.80,7.90,50,18,'general',60,'juice'],
['beverages','Marigold Peel Fresh Orange Juice 1L','No-pulp orange juice. 100% juice.',4.20,2.10,50,25,'general',14,'juice'],
['beverages','Tropicana Pure Premium OJ 1.75L','Premium pulp-free orange juice. Family size.',7.50,3.80,49,15,'near_expiry',7,'juice'],
['beverages','100PLUS Isotonic Drink 500ml x24','Sports isotonic drink. Carton of 24.',18.90,9.50,50,20,'overstock',90,'soda'],
['beverages','Coca-Cola 1.5L x6','Classic Coke multipack. Great value.',9.90,4.90,51,15,'general',90,'soda'],
['beverages','Pepsi Max 1.5L x6','Zero-calorie cola multipack.',9.50,4.70,51,12,'general',90,'soda'],
['beverages','Tiger Beer 325ml x24','Singapore Tiger lager beer. Carton clearance.',38.90,22.00,43,10,'general',120,'soda'],
['beverages','Sprite Lemon-Lime 1.5L x6','Refreshing citrus soda multipack.',9.50,4.70,51,15,'general',90,'soda'],
['beverages','7-Up 1.5L x6','Classic lemon-lime soda. Multipack.',9.20,4.60,50,18,'general',90,'soda'],
['beverages','Evian Natural Spring Water 750ml x6','French premium mineral water multipack.',9.80,4.90,50,20,'general',180,'water'],
['beverages','Dasani Purified Water 1.5L x12','Crisp purified drinking water. Bulk buy.',6.50,3.20,51,35,'general',180,'water'],
['beverages','Ribena Blackcurrant Cordial 1L','Concentrated blackcurrant drink. Mix to taste.',5.80,2.90,50,20,'general',120,'juice'],
['beverages','Heaven and Earth Ice Lemon Tea 500ml x24','Popular SG ice lemon tea. Carton.',15.50,7.80,50,18,'general',60,'tea'],
['beverages','Pokka Oolong Tea 500ml x24','Taiwanese-style oolong tea. Clearance carton.',16.00,8.00,50,15,'general',60,'tea'],
['beverages','Red Bull Energy Drink 250ml x24','Caffeine energy drink. Carton of 24.',36.00,18.00,50,10,'general',120,'soda'],
['beverages','Milo Dinosaur Packet 200ml x24','Thick chocolate malt with extra milo powder.',22.50,11.25,50,12,'general',30,'milo'],
// SNACKS & CONFECTIONERY - 22
['snacks-confectionery','Pringles Original 134g','Classic stackable potato crisps in a tube.',3.90,1.95,50,40,'general',60,'chips'],
['snacks-confectionery','Pringles Sour Cream & Onion 134g','Tangy flavoured Pringles. Snack clearance.',3.90,1.95,50,35,'general',60,'chips'],
['snacks-confectionery','Lay\'s Salted 180g','Classic salted potato chips. Sharing pack.',3.50,1.75,50,30,'general',45,'chips'],
['snacks-confectionery','Doritos Nacho Cheese 170g','Bold nacho cheese tortilla chips.',3.80,1.90,50,28,'general',45,'chips'],
['snacks-confectionery','Jack N Jill Roller Coaster 85g x5','Wavy ridged crisps multipack. Kids favourite.',5.50,2.70,51,25,'general',60,'chips'],
['snacks-confectionery','Oreo Original Sandwich Cookies 432g','Classic cream-filled chocolate cookies.',4.50,2.25,50,35,'general',90,'biscuit'],
['snacks-confectionery','Oreo Double Stuf 352g','Extra cream-filled Oreo. Flash sale.',5.20,2.60,50,22,'seasonal',90,'biscuit'],
['snacks-confectionery','Khong Guan Assorted Biscuits 650g','Popular Singapore Khong Guan tin.',8.90,4.50,49,20,'general',120,'biscuit'],
['snacks-confectionery','Julie\'s Love Letter Biscuits 200g','Classic thin crispy egg roll biscuits.',3.50,1.75,50,30,'general',60,'biscuit'],
['snacks-confectionery','Hup Seng Cream Crackers 800g','Classic cream crackers. Budget-friendly.',4.20,2.10,50,40,'general',120,'biscuit'],
['snacks-confectionery','KitKat 4-Finger Milk Chocolate 45g x12','Classic KitKat bar multipack.',14.50,7.20,50,20,'general',90,'chocolate'],
['snacks-confectionery','Cadbury Dairy Milk 165g','Smooth Cadbury milk chocolate block.',4.90,2.45,50,25,'general',90,'chocolate'],
['snacks-confectionery','Ferrero Rocher 200g 16pcs','Hazelnut praline chocolates. Gift box.',12.90,6.50,50,12,'general',60,'chocolate'],
['snacks-confectionery','Lindt Excellence Dark 70% 100g','Premium Swiss dark chocolate.',5.50,2.75,50,18,'general',90,'chocolate'],
['snacks-confectionery','M&Ms Peanut Chocolate 160g','Colourful peanut chocolate candies.',4.20,2.10,50,22,'general',90,'candy'],
['snacks-confectionery','Skittles Fruits 174g','Rainbow fruit-flavoured chewy candies.',3.80,1.90,50,20,'general',90,'candy'],
['snacks-confectionery','Mentos Fresh Mint Roll 38g x12','Classic chewy mint candy. Multipack.',8.50,4.25,50,30,'general',120,'candy'],
['snacks-confectionery','Ricola Herb Throat Drops 75g x3','Swiss herbal drops. 3-pack clearance.',9.80,4.90,50,15,'general',90,'candy'],
['snacks-confectionery','Tiger Biscuits Assorted 700g','Tiger brand assorted cream biscuit tin.',9.50,4.75,50,18,'general',120,'biscuit'],
['snacks-confectionery','Nutella 750g','Hazelnut and cocoa spread. Value jar.',10.90,5.50,50,20,'general',180,'chocolate'],
['snacks-confectionery','Pocky Chocolate Sticks 47g x10','Japanese Glico Pocky biscuit sticks.',12.80,6.40,50,15,'general',90,'biscuit'],
['snacks-confectionery','Want Want Rice Crackers 112g x5','Lightly salted puffed rice crackers.',6.50,3.25,50,25,'general',90,'chips'],
// FROZEN FOODS - 22
['frozen-foods','CP Foods Chicken Nuggets 1kg','Crispy Thai CP brand chicken nuggets.',9.90,4.95,50,25,'general',30,'nuggets'],
['frozen-foods','Ramly Beef Burger Patties 5pcs','Popular Malaysian Ramly frozen burger patties.',6.50,3.25,50,30,'general',30,'frozen'],
['frozen-foods','CP Foods Cocktail Sausages 500g','Mini chicken sausages. Perfect for parties.',5.90,2.95,50,22,'general',30,'frozen'],
['frozen-foods','Pauls Vanilla Ice Cream 2L','Australian Pauls full cream vanilla ice cream.',9.50,4.75,50,15,'seasonal',60,'icecream'],
['frozen-foods','Nestle Drumstick Ice Cream x6','Classic cone ice cream pack. Mixed flavours.',8.50,4.25,50,12,'general',60,'icecream'],
['frozen-foods','Wall\'s Magnum Almond Ice Cream x4','Premium Belgian chocolate almond ice cream.',9.80,4.90,50,10,'general',60,'icecream'],
['frozen-foods','Birds Eye Garden Peas 500g','Flash-frozen sweet garden peas.',3.50,1.75,50,28,'general',90,'frozen'],
['frozen-foods','McCain French Fries Straight Cut 1kg','Crispy oven-ready straight cut fries.',6.80,3.40,50,20,'general',60,'frozen'],
['frozen-foods','De Cuisine Dim Sum Mixed Set 300g','Assorted frozen har gow and siu mai.',8.50,4.25,50,15,'general',30,'nuggets'],
['frozen-foods','Figo Fishcake Slices 500g','Local Singapore-style fish cake slices.',5.50,2.75,50,18,'near_expiry',7,'frozen'],
['frozen-foods','CP Foods Frozen Prawns 500g','IQF frozen shell-on tiger prawns.',12.90,6.50,50,12,'general',60,'shrimp'],
['frozen-foods','Tasty Bite Vegetable Gyoza 400g','Japanese-style frozen veggie dumplings.',7.80,3.90,50,15,'general',60,'frozen'],
['frozen-foods','McCain Pizza Snacks 400g','Mini frozen pizzas. Quick snack.',6.50,3.25,50,10,'general',30,'frozen'],
['frozen-foods','Golden Chef Shredded Chicken 500g','Ready-to-use cooked shredded chicken.',8.90,4.50,49,14,'near_expiry',10,'chicken'],
['frozen-foods','King\'s Roti Prata Frozen 10pcs','Ready-to-cook frozen prata.',4.90,2.45,50,20,'general',90,'frozen'],
['frozen-foods','Ajinomoto Gyoza 600g','Authentic Japanese pan-fried dumplings.',9.80,4.90,50,15,'general',90,'frozen'],
['frozen-foods','Haagen-Dazs Strawberry 473ml','Premium strawberry ice cream pint.',13.90,6.90,50,8,'seasonal',60,'icecream'],
['frozen-foods','Snowflake Frozen Tofu 400g','Silken tofu for hot pot and soups.',3.20,1.60,50,20,'near_expiry',7,'frozen'],
['frozen-foods','Kawan Frozen Chapatti 12pcs','Soft whole wheat Indian flatbread. Halal.',5.50,2.75,50,18,'general',90,'frozen'],
['frozen-foods','TGI Fridays Mozzarella Sticks 311g','Restaurant-style breaded mozzarella sticks.',9.50,4.75,50,10,'general',60,'nuggets'],
['frozen-foods','Figo Crab Stick 500g','Imitation crab sticks for sushi and cooking.',5.80,2.90,50,22,'near_expiry',7,'frozen'],
['frozen-foods','Swensen\'s Cookie & Cream Ice Cream 750ml','Rich cookies and cream ice cream tub.',9.90,4.95,50,12,'seasonal',60,'icecream'],
// CANNED & PACKAGED - 22
['canned-packaged','Maggi 2-Minute Instant Noodles Chicken 5pcs','Classic Maggi chicken instant noodles.',2.50,1.25,50,50,'general',120,'noodles'],
['canned-packaged','Indomie Mi Goreng Fried Noodles 5pcs','Iconic Indonesian fried noodle.',2.80,1.40,50,45,'general',120,'noodles'],
['canned-packaged','Prima Taste Singapore Curry Noodles 2pcs','Authentic Singapore curry laksa noodles.',3.90,1.95,50,30,'general',90,'noodles'],
['canned-packaged','Nissin Demae Ramen Soy Sauce 5pcs','Japanese-style ramen with soy broth.',3.50,1.75,50,28,'general',90,'noodles'],
['canned-packaged','Ayam Brand Tuna Chunk In Brine 185g x3','Canned tuna in spring water. 3-pack.',6.50,3.25,50,30,'general',730,'can'],
['canned-packaged','Heinz Baked Beans 415g x3','Classic tomato sauce baked beans.',5.80,2.90,50,25,'general',730,'can'],
['canned-packaged','Ayam Brand Sardines In Tomato Sauce 215g x3','Rich tomato sardines. 3-can pack.',5.50,2.75,50,30,'general',730,'can'],
['canned-packaged','Campbell\'s Cream of Mushroom Soup 305g x3','Ready-to-use condensed mushroom soup.',6.90,3.45,50,20,'general',365,'can'],
['canned-packaged','Ligo Whole Kernel Corn 400g x4','Sweet canned sweetcorn kernels.',5.80,2.90,50,25,'general',730,'can'],
['canned-packaged','Kikkoman Soy Sauce 1L','Premium Japanese naturally brewed soy sauce.',5.90,2.95,50,20,'general',365,'sauce'],
['canned-packaged','Lee Kum Kee Oyster Sauce 510g','Popular Chinese oyster sauce.',4.80,2.40,50,25,'general',365,'sauce'],
['canned-packaged','Heinz Tomato Ketchup 910g','Classic tomato ketchup large bottle.',5.50,2.75,50,20,'general',365,'sauce'],
['canned-packaged','Prima Taste Char Kway Teow Paste 250g','Authentic Singapore CKT cooking paste.',4.20,2.10,50,22,'general',180,'sauce'],
['canned-packaged','Tiger Brand Jasmine Rice 5kg','Premium Thai fragrant jasmine rice.',12.80,6.40,50,15,'general',365,'rice'],
['canned-packaged','Super Fine Basmati Rice 5kg','Aged long-grain Indian basmati rice.',13.90,6.95,50,10,'general',365,'rice'],
['canned-packaged','Knorr Chicken Stock Cubes 10pcs','Handy stock cubes for soups and cooking.',2.80,1.40,50,40,'general',365,'sauce'],
['canned-packaged','Best Foods Real Mayonnaise 445g','Creamy real mayonnaise. Great for sandwiches.',5.90,2.95,50,18,'general',180,'sauce'],
['canned-packaged','Kimball Chilli Sauce 340g x3','Singapore favourite chilli sauce. Value 3-pack.',5.50,2.75,50,25,'overstock',180,'sauce'],
['canned-packaged','Ha Ha Fried Shallots 200g','Ready-to-use crispy fried shallots.',3.50,1.75,50,20,'general',90,'sauce'],
['canned-packaged','Elephant Brand Coconut Milk 400ml x4','Thick coconut milk for curries.',7.80,3.90,50,20,'general',365,'can'],
['canned-packaged','Mae Ploy Red Curry Paste 400g','Thai red curry paste. Restaurant quality.',4.50,2.25,50,18,'general',180,'sauce'],
['canned-packaged','Quaker Oats Instant 800g','Classic rolled oats. Breakfast clearance.',5.80,2.90,50,22,'general',180,'oats'],
// HEALTH & ORGANIC - 22
['health-organic','Sanitarium Weet-Bix 575g','Whole grain wheat biscuits. High fibre breakfast.',5.80,2.90,50,25,'general',180,'oats'],
['health-organic','Uncle Tobys Oats Quick Sachets 10pcs','Convenient instant oat sachets.',5.50,2.75,50,20,'general',90,'oats'],
['health-organic','Quaker Granola Honey & Oat 700g','Crunchy oat clusters with honey.',7.80,3.90,50,15,'general',120,'oats'],
['health-organic','Nature\'s Path Organic Granola 325g','USDA organic granola with chia seeds.',9.90,4.95,50,12,'general',90,'organic'],
['health-organic','Bob\'s Red Mill Rolled Oats 907g','Old-fashioned thick rolled oats. US import.',8.50,4.25,50,18,'general',180,'oats'],
['health-organic','Ensure Original Nutrition Shake 400g','Complete balanced nutrition powder.',22.90,11.50,50,10,'near_expiry',30,'protein'],
['health-organic','Myprotein Impact Whey Protein 500g','Chocolate flavour whey protein.',22.00,11.00,50,8,'general',180,'protein'],
['health-organic','Blackmores Fish Oil 1000mg x60','Premium Australian omega-3 fish oil.',18.90,9.50,50,15,'general',365,'organic'],
['health-organic','Vita Coco Pure Coconut Water 500ml x6','100% natural coconut water. No added sugar.',13.80,6.90,50,12,'general',60,'juice'],
['health-organic','Health Paradise Organic Brown Rice 2kg','Unpolished brown rice. High fibre.',7.50,3.75,50,15,'general',365,'rice'],
['health-organic','Naturel Organic Extra Virgin Olive Oil 500ml','Cold-pressed EVOO from Spain.',9.80,4.90,50,12,'general',365,'organic'],
['health-organic','Kellogg\'s Special K Red Berries 500g','Low-fat cereal with strawberry pieces.',6.80,3.40,50,20,'general',90,'oats'],
['health-organic','Gold Kili Ginger Honey Tea x20','Natural ginger honey drink sachets.',5.50,2.75,50,22,'general',180,'tea'],
['health-organic','NOW Foods Spirulina 500mg x100','Organic blue-green algae supplement.',15.80,7.90,50,10,'general',365,'organic'],
['health-organic','Nespresso Altissio Capsules x10','Compatible Nespresso pods. Clearance box.',6.90,3.45,50,15,'general',180,'coffee'],
['health-organic','Health Works Chia Seeds 454g','Raw chia seeds high in omega-3.',8.90,4.45,50,12,'general',365,'organic'],
['health-organic','Hegen Manuka Honey 250g','New Zealand MGO 250+ certified manuka honey.',32.90,16.50,50,8,'general',730,'organic'],
['health-organic','Nestle Fitness Cereal Choco 375g','Crispy whole grain flakes with chocolate.',5.50,2.75,50,18,'general',90,'oats'],
['health-organic','Kellogg\'s All-Bran Flakes 380g','High-bran wheat flakes. Digestive health.',5.80,2.90,50,15,'general',90,'oats'],
['health-organic','Coconut Merchant Desiccated Coconut 500g','Fine desiccated coconut for baking.',4.50,2.25,50,18,'general',180,'organic'],
['health-organic','Amara Organic Turmeric Latte 160g','Organic golden latte mix.',12.90,6.45,50,10,'general',180,'organic'],
['health-organic','Koka Purple Wheat Noodles 85g x5','Whole wheat purple noodles. Low GI.',5.90,2.95,50,20,'general',90,'noodles'],
// MEAT & SEAFOOD - 22
['meat-seafood','Sadia Halal Chicken Drumsticks 1kg','Brazilian Sadia frozen chicken drumsticks.',8.90,4.50,49,20,'near_expiry',5,'chicken'],
['meat-seafood','Ayamas Chicken Breast Fillet 500g','Skinless boneless chicken breast. Chilled.',6.50,3.25,50,18,'near_expiry',4,'chicken'],
['meat-seafood','CP Foods Minced Chicken 500g','Freshly minced halal chicken.',4.80,2.40,50,15,'near_expiry',3,'chicken'],
['meat-seafood','Sadia Chicken Wings 1kg','Premium frozen chicken wings. Halal.',9.50,4.75,50,12,'general',30,'chicken'],
['meat-seafood','Ramly Chicken Burger Patties 4pcs','Halal chicken burger patties.',5.50,2.75,50,20,'near_expiry',3,'chicken'],
['meat-seafood','Springboard Australian Beef Striploin 300g','Chilled grass-fed Australian beef.',18.90,9.50,50,8,'near_expiry',3,'pork'],
['meat-seafood','Woolworths Australian Beef Mince 500g','Chilled extra lean beef mince.',9.80,4.90,50,10,'near_expiry',3,'pork'],
['meat-seafood','Tulip Canned Luncheon Meat 397g x2','Danish pork luncheon meat. 2-can value.',8.50,4.25,50,20,'general',365,'can'],
['meat-seafood','QiMin Thinly Sliced Pork Belly 300g','Thinly sliced pork for hotpot and BBQ.',6.80,3.40,50,12,'near_expiry',3,'pork'],
['meat-seafood','Nanyang Fishball 500g','Traditional Singapore fish balls. Chilled.',4.50,2.25,50,15,'near_expiry',4,'fish'],
['meat-seafood','Figo Fish Tofu 300g','Tofu-fish cake hybrid. Popular for soups.',3.80,1.90,50,20,'near_expiry',4,'fish'],
['meat-seafood','Sea Delight Black Tiger Prawns 500g','Large shell-on frozen tiger prawns.',14.90,7.50,50,10,'general',60,'shrimp'],
['meat-seafood','Tassal Atlantic Salmon Fillet 300g','Fresh chilled salmon from Tasmania.',14.50,7.25,50,8,'near_expiry',3,'salmon'],
['meat-seafood','Dory Fish Fillet 500g','Basa dory fish fillet. Mild white fish.',8.50,4.25,50,12,'near_expiry',4,'fish'],
['meat-seafood','Lucky Star Squid Rings 500g','IQF frozen squid rings. Ready to fry.',9.80,4.90,50,10,'general',60,'shrimp'],
['meat-seafood','Golden Fresh Clams 300g','Frozen asari clams for pasta.',6.50,3.25,50,12,'general',30,'fish'],
['meat-seafood','Patagonia Yellowfin Tuna Steak 200g','Premium sashimi-grade tuna. Chilled.',12.90,6.45,50,6,'near_expiry',3,'fish'],
['meat-seafood','Zwan Cocktail Sausages 400g','Pork sausages in brine. Ready-to-eat.',4.80,2.40,50,18,'general',90,'pork'],
['meat-seafood','Farmland Streaky Bacon 200g','Thin-sliced pork streaky bacon.',5.50,2.75,50,15,'near_expiry',5,'pork'],
['meat-seafood','Ayamas Chicken Franks 500g','Halal chicken hot dogs. Chilled pack.',5.90,2.95,50,16,'near_expiry',4,'chicken'],
['meat-seafood','Pacific West Battered Fish 400g','Crispy batter-coated white fish fillets.',7.80,3.90,50,12,'general',60,'fish'],
['meat-seafood','Frabelle Yellowfin Tuna Belly 200g','Rich fatty tuna belly slices.',9.80,4.90,50,8,'seasonal',5,'fish'],
// HOUSEHOLD & PERSONAL - 26 products (total = 202)
['household-personal','Dettol Antibacterial Hand Wash 500ml','Original Dettol liquid hand soap. Refill size.',5.50,2.75,50,30,'general',365,'soap'],
['household-personal','Lifebuoy Total 10 Body Wash 900ml','Antibacterial body wash. Germ protection.',7.80,3.90,50,22,'general',365,'soap'],
['household-personal','Dove Deeply Nourishing Body Wash 550ml','Moisturising body wash with milk cream.',7.50,3.75,50,20,'general',365,'soap'],
['household-personal','Colgate Total Whitening Toothpaste 175g x2','Anti-cavity whitening toothpaste twin pack.',5.80,2.90,50,25,'general',365,'toothpaste'],
['household-personal','Darlie White + White Toothpaste 225g','Whitening formula toothpaste. Popular SG brand.',3.80,1.90,50,30,'general',365,'toothpaste'],
['household-personal','Sensodyne Rapid Relief Toothpaste 75g','Sensitive teeth toothpaste. Fast-acting relief.',7.90,3.95,50,18,'general',365,'toothpaste'],
['household-personal','Head & Shoulders Anti-Dandruff Shampoo 400ml','Classic 2-in-1 anti-dandruff shampoo.',9.50,4.75,50,20,'general',365,'shampoo'],
['household-personal','Pantene Pro-V Silky Smooth Shampoo 750ml','Vitamin-enriched smoothing shampoo.',10.90,5.45,50,15,'general',365,'shampoo'],
['household-personal','Sunsilk Smooth & Manageable Shampoo 650ml','Budget-friendly anti-frizz shampoo.',6.50,3.25,50,22,'general',365,'shampoo'],
['household-personal','Rejoice 2-in-1 Shampoo 380ml','Shampoo and conditioner in one.',5.50,2.75,50,18,'general',365,'shampoo'],
['household-personal','Persil Colour Protect Liquid 1.5L','Advanced colour-safe liquid laundry detergent.',12.90,6.45,50,12,'general',730,'detergent'],
['household-personal','Top Front Load Detergent 4kg','Popular Singapore laundry detergent powder.',19.90,9.95,50,10,'general',730,'detergent'],
['household-personal','Dynamo Power Liquid Detergent 3.2kg','Concentrated stain-fighter laundry liquid.',18.50,9.25,50,10,'general',730,'detergent'],
['household-personal','Dettol Antiseptic Liquid 500ml','Multi-purpose antiseptic for home use.',7.80,3.90,50,15,'general',730,'soap'],
['household-personal','Mr Muscle Kitchen Cleaner 500ml','Degreaser spray for kitchen surfaces.',4.80,2.40,50,18,'general',365,'detergent'],
['household-personal','Cif Cream Cleaner 500ml','Gentle abrasive cream for tough stains.',5.50,2.75,50,15,'general',365,'detergent'],
['household-personal','Kodomo Baby Shampoo 200ml x3','Gentle tear-free baby shampoo. 3-pack.',8.50,4.25,50,12,'general',365,'shampoo'],
['household-personal','Huggies Ultra Dry Nappies M 46pcs','Medium disposable nappies for 6-11kg babies.',22.90,11.50,50,8,'general',365,'tissue'],
['household-personal','Kleenex Tissue 3ply x6 boxes','Soft facial tissue. 6-box value pack.',8.90,4.45,50,20,'overstock',365,'tissue'],
['household-personal','Scott Kitchen Roll 3pcs','Absorbent kitchen paper towel. 3-roll pack.',5.80,2.90,50,18,'overstock',365,'tissue'],
['household-personal','Tempo Pocket Tissue x10','Handy travel-size tissue packs. 10-pack.',3.50,1.75,50,25,'overstock',365,'tissue'],
['household-personal','Nivea Men Body Lotion 400ml','Daily moisturising lotion for men.',8.90,4.45,50,15,'general',365,'soap'],
['household-personal','Vaseline Intensive Care Lotion 725ml','Repairing dry skin body lotion. Large size.',9.80,4.90,50,12,'general',365,'soap'],
['household-personal','Gillette Mach3 Razor Blades 8pcs','Triple blade razor refill cartridges.',18.90,9.45,50,10,'general',730,'soap'],
['household-personal','Listerine Cool Mint Mouthwash 500ml','Antibacterial mouthwash. 12-hour fresh breath.',6.80,3.40,50,18,'general',365,'toothpaste'],
['household-personal','Softlan Fabric Conditioner 1.6L','Floral-scented fabric softener. Large bottle.',7.50,3.75,50,15,'general',730,'detergent'],
];

// ── INSERT PRODUCTS ──────────────────────────────────────────
$pStmt = $pdo->prepare(
    "INSERT INTO products (supplier_id,category_id,name,description,original_price,
     discount_price,discount_percentage,stock_quantity,deal_type,expiry_date,image_url,status)
     VALUES (?,?,?,?,?,?,?,?,?,DATE_ADD(CURDATE(), INTERVAL ? DAY),?,?)"
);
$inserted = 0;
foreach($products as $p){
    list($slug,$name,$desc,$orig,$disc,$pct,$stock,$deal,$days,$imgKey) = $p;
    $catId = $catMap[$slug] ?? null;
    if(!$catId){ fail("Unknown slug: $slug"); continue; }
    $imgUrl = $img[$imgKey] ?? $img['can'];
    try{
        $pStmt->execute([$supplierId,$catId,$name,$desc,$orig,$disc,$pct,$stock,$deal,$days,$imgUrl,'active']);
        $inserted++;
    }catch(PDOException $e){ fail("Product '$name': ".$e->getMessage()); }
}
step("$inserted products inserted");

try { $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status='active'")->fetchColumn(); }
catch(Exception $e){ $totalProducts = 0; }

?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>SecondChance Mart - Setup Complete</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.card{background:#fff;border-radius:16px;box-shadow:0 4px 24px rgba(0,0,0,.10);max-width:680px;width:100%;padding:40px}
.logo{font-size:2rem;font-weight:800;color:#e67e22;margin-bottom:4px}.logo span{color:#27ae60}
h1{font-size:1.4rem;color:#2c3e50;margin-bottom:24px}
.badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:.78rem;font-weight:600;margin-bottom:20px}
.ok{background:#e8f5e9;color:#2e7d32}.err{background:#fce4ec;color:#c62828}
.steps{list-style:none;margin-bottom:20px}.steps li{padding:8px 0;border-bottom:1px solid #f5f5f5;font-size:.9rem;color:#37474f}
.steps li::before{content:"✅ "}
.errors{background:#fff3f3;border-left:4px solid #e53935;border-radius:6px;padding:14px;margin-bottom:20px}
.errors p{font-size:.85rem;color:#b71c1c;margin-bottom:4px}
.stats{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:24px}
.stat{background:#f8f9fa;border-radius:10px;padding:16px;text-align:center}
.stat .num{font-size:1.8rem;font-weight:800;color:#e67e22}.stat .lbl{font-size:.75rem;color:#78909c;margin-top:2px}
.creds{background:#fff8e1;border-radius:10px;padding:16px;margin-bottom:24px}
.creds h3{font-size:.9rem;color:#f57f17;margin-bottom:8px}
.creds table{width:100%;font-size:.85rem}.creds td{padding:3px 8px}
.creds td:first-child{font-weight:600;color:#5d4037;width:140px}
.btn{display:inline-block;padding:12px 28px;background:#e67e22;color:#fff;border-radius:8px;text-decoration:none;font-weight:600;font-size:.95rem;margin-right:10px}
.btn.sec{background:#27ae60}
.warn{margin-top:20px;padding:12px;background:#fff3e0;border-radius:8px;font-size:.82rem;color:#e65100}
</style></head><body>
<div class="card">
<div class="logo">Second<span>Chance</span> Mart</div>
<h1>Database Setup Complete</h1>
<?php if(empty($errors)):?><span class="badge ok">All steps completed successfully</span>
<?php else:?><span class="badge err"><?=count($errors)?> error(s) — check below</span><?php endif;?>
<div class="stats">
  <div class="stat"><div class="num"><?=$totalProducts?></div><div class="lbl">Products</div></div>
  <div class="stat"><div class="num">9</div><div class="lbl">Categories</div></div>
  <div class="stat"><div class="num">4</div><div class="lbl">Demo Users</div></div>
</div>
<ul class="steps"><?php foreach($steps as $s):?><li><?=htmlspecialchars($s)?></li><?php endforeach;?></ul>
<?php if(!empty($errors)):?>
<div class="errors"><?php foreach($errors as $er):?><p><?=htmlspecialchars($er)?></p><?php endforeach;?></div>
<?php endif;?>
<div class="creds"><h3>Demo Login Credentials</h3>
<table>
  <tr><td>Admin:</td><td>admin@secondchancemart.com &nbsp;/&nbsp; admin123</td></tr>
  <tr><td>Customer:</td><td>john@example.com &nbsp;/&nbsp; password123</td></tr>
  <tr><td>Supplier:</td><td>supplier@example.com &nbsp;/&nbsp; password123</td></tr>
</table></div>
<a href="index.php" class="btn">Go to Shop</a>
<a href="admin/" class="btn sec">Admin Panel</a>
<p class="warn">&#9888; <strong>Security:</strong> Delete <code>setup.php</code> after running on a live server.</p>
</div></body></html>
