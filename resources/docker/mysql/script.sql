CREATE TABLE
  `roles` (
    `id` int NOT NULL AUTO_INCREMENT,
    `role` varchar(30) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`role`),
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  );

INSERT INTO
  roles (role)
VALUES
  ('Administrator'),
  ('Operator');

CREATE TABLE
  `users` (
    `id` int NOT NULL AUTO_INCREMENT,
    `name` varchar(50) NOT NULL,
    `username` varchar(50) NOT NULL,
    `password` varchar(150) NOT NULL,
    `email` varchar(70) NOT NULL,
    `active` tinyint DEFAULT 0,
    `role_id` int NOT NULL,
    PRIMARY KEY (`id`),
    KEY `fk_users_roles_idx` (`role_id`),
    CONSTRAINT `fk_users_roles` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`),
    UNIQUE (`username`),
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  );

CREATE TABLE
  `businesses` (
    `id` INT NOT NULL AUTO_INCREMENT,
    `business_name` VARCHAR(45) NOT NULL,
    `trade_name` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `name_UNIQUE` (`business_name` ASC) VISIBLE
  ) ENGINE = InnoDB;

COMMIT;