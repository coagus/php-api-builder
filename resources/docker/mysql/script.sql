CREATE TABLE
  `roles` (
    `id` int NOT NULL AUTO_INCREMENT,
    `role` varchar(30) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`role`)
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
    UNIQUE (`username`)
  );

COMMIT;