CREATE DATABASE `lv_store`;
USE `lv_store`;

CREATE TABLE `products` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `description` VARCHAR(255) NOT NULL,
  `in_stock`  BOOLEAN DEFAULT TRUE,
  PRIMARY KEY (`id`)
);