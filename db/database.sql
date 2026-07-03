SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
CREATE TABLE `categories` (
  `category_id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`category_id`),
  FOREIGN KEY (`parent_id`) REFERENCES `categories`(`category_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','client') DEFAULT 'client',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `products` (
  `product_id` int(11) NOT NULL AUTO_INCREMENT,
  `category_id` int(11) NOT NULL,
  `name` varchar(200) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `stock_quantity` int(11) DEFAULT 0,
  `image_url` varchar(255) DEFAULT 'no_image.jpg',
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`product_id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `product_specs` (
  `spec_id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `spec_name` varchar(100) NOT NULL,
  `spec_value` varchar(100) NOT NULL,
  PRIMARY KEY (`spec_id`),
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `status` enum('new','processing','shipped','delivered','cancelled') DEFAULT 'new',
  `total_amount` decimal(10,2) DEFAULT 0.00,
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`order_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `order_items` (
  `order_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `price_at_purchase` decimal(10,2) NOT NULL,
  PRIMARY KEY (`order_item_id`),
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$
CREATE TRIGGER `check_product_price_insert` BEFORE INSERT ON `products`
FOR EACH ROW BEGIN
    IF NEW.price < 0 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Ціна не може бути від\'ємною';
    END IF;
END$$
CREATE TRIGGER `update_order_total_after_insert` AFTER INSERT ON `order_items`
FOR EACH ROW BEGIN
    UPDATE `orders` 
    SET `total_amount` = `total_amount` + (NEW.price_at_purchase * NEW.quantity)
    WHERE `order_id` = NEW.order_id;
END$$

DELIMITER ;

DELIMITER $$
CREATE PROCEDURE `create_order`(IN `p_user_id` INT, OUT `p_order_id` INT)
BEGIN
    INSERT INTO `orders` (`user_id`, `status`, `total_amount`) VALUES (`p_user_id`, 'new', 0);
    SET `p_order_id` = LAST_INSERT_ID();
END$$

DELIMITER ;

INSERT INTO `categories` (`name`, `parent_id`) VALUES 
('Ноутбуки', NULL),
('Комплектуючі', NULL),
('Відеокарти', 2),
('Процесори', 2),
('Монітори', NULL);

INSERT INTO `users` (`email`, `password_hash`, `full_name`, `role`) VALUES 
('admin@techshop.com', '$2y$10$15isXN1S8.G1gd.oYz8eFOIUq1YZ5qBm106M3hG6FfjjDg0qKNolK', 'Головний Адміністратор', 'admin'),
('client@email.com', '$2y$10$.AaTHVqciLQUSNPLq2.muuiXNvSJnmHY0eKzK.JQ1Oi1PzsmTtmo2', 'Іван Клименко', 'client');

INSERT INTO `products` (`category_id`, `name`, `description`, `price`, `stock_quantity`, `image_url`) VALUES 
(1, 'Asus ROG Strix', 'Ігровий ноутбук, 16GB RAM, RTX 3060', 45000.00, 10, 'images/asus_rog.jpg'),
(3, 'NVIDIA RTX 4070', 'Потужна відеокарта для 2K геймінгу', 28999.00, 5, 'images/rtx4070.jpg'),
(4, 'Intel Core i5-13600K', 'Процесор 13-го покоління', 12500.00, 20, 'images/i5_13600k.jpg'),
(1, 'Dell XPS 13', 'Ультрабук для роботи та навчання', 32000.00, 15, 'images/dell_xps13.jpg'),
(1, 'MacBook Air M2', 'Легкий ноутбук з чіпом Apple M2', 38000.00, 8, 'images/macbook_air_m2.jpg'),
(3, 'AMD Radeon RX 7800 XT', 'Відеокарта для високопродуктивних ігор', 25999.00, 7, 'images/rx7800xt.jpg'),
(4, 'AMD Ryzen 7 7700X', 'Високопродуктивний процесор', 14500.00, 12, 'images/ryzen7_7700x.jpg'),
(2, 'Corsair Vengeance LPX 16GB', 'Оперативна память DDR4 3200MHz', 2999.00, 25, 'images/corsair_ram.jpg'),
(2, 'Samsung 980 PRO 1TB', 'Швидкий NVMe SSD накопичувач', 5999.00, 18, 'images/samsung_ssd.jpg'),
(1, 'Lenovo ThinkPad X1', 'Бізнес ноутбук з високою продуктивністю', 55000.00, 6, 'images/lenovo_thinkpad.jpg'),
(3, 'NVIDIA RTX 4080', 'Флагманська відеокарта для 4K геймінгу', 79999.00, 3, 'images/rtx4080.jpg'),
(4, 'Intel Core i9-13900K', 'Топовий процесор для ентузіастів', 29999.00, 8, 'images/i9_13900k.jpg'),
(2, 'Kingston HyperX Fury 32GB', 'Оперативна пам\'ять DDR4 3600MHz', 4999.00, 20, 'images/kingston_ram.jpg'),
(2, 'Western Digital Black SN850 2TB', 'SSD для високошвидкісного зберігання', 8999.00, 10, 'images/wd_sn850.jpg'),
(5, 'Samsung Odyssey G9', 'Ультраширокий ігровий монітор 49"', 69999.00, 4, 'images/samsung_g9.jpg');

INSERT INTO `product_specs` (`product_id`, `spec_name`, `spec_value`) VALUES 
(1, 'Процесор', 'AMD Ryzen 7'),
(1, 'Оперативна пам\'ять', '16 ГБ'),
(2, 'Відеопам\'ять', '12 ГБ'),
(4, 'Процесор', 'Intel Core i7'),
(4, 'Оперативна пам\'ять', '16 ГБ'),
(5, 'Процесор', 'Apple M2'),
(5, 'Оперативна пам\'ять', '8 ГБ'),
(6, 'Відеопам\'ять', '16 ГБ'),
(7, 'Ядра', '8 ядер / 16 потоків'),
(8, 'Частота', '3200 МГц'),
(9, 'Швидкість читання', '7000 МБ/с'),
(10, 'Процесор', 'Intel Core i7'),
(10, 'Оперативна пам\'ять', '16 ГБ'),
(11, 'Відеопам\'ять', '16 ГБ'),
(12, 'Ядра', '24 ядра / 32 потоки'),
(13, 'Частота', '3600 МГц'),
(14, 'Швидкість читання', '7000 МБ/с'),
(15, 'Діагональ', '49 дюймів'),
(15, 'Роздільна здатність', '5120x1440');

COMMIT;
