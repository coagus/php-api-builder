-- Blog API Demo Schema
-- Compatible with MySQL 8.0+

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(20) DEFAULT 'draft',
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    body VARCHAR(500) NOT NULL,
    post_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS tags (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS post_tags (
    post_id INT NOT NULL,
    tag_id INT NOT NULL,
    PRIMARY KEY (post_id, tag_id),
    FOREIGN KEY (post_id) REFERENCES posts(id),
    FOREIGN KEY (tag_id) REFERENCES tags(id)
);

-- Seed data
INSERT IGNORE INTO users (name, email, password, active) VALUES
    ('Admin', 'admin@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1),
    ('Editor', 'editor@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1);
-- Default password for both: "password"

INSERT IGNORE INTO tags (name) VALUES ('php'), ('api'), ('tutorial'), ('docker'), ('rest');

INSERT IGNORE INTO posts (title, slug, body, status, user_id) VALUES
    ('Getting Started with PHP API Builder', 'getting-started-with-php-api-builder', 'Learn how to build REST APIs in minutes with php-api-builder.', 'published', 1),
    ('Understanding Entities', 'understanding-entities', 'Entities map PHP classes to database tables using attributes.', 'published', 1),
    ('Working with Relationships', 'working-with-relationships', 'BelongsTo, HasMany, and BelongsToMany relationships explained.', 'draft', 2);

INSERT IGNORE INTO post_tags (post_id, tag_id) VALUES (1, 1), (1, 2), (1, 5), (2, 1), (2, 3), (3, 1), (3, 2);

INSERT IGNORE INTO comments (body, post_id, user_id) VALUES
    ('Great introduction!', 1, 2),
    ('Very helpful, thanks.', 1, 2),
    ('Clear explanation of entities.', 2, 1);
