[![Latest Stable Version](https://poser.pugx.org/coagus/php-api-builder/v/stable)](https://packagist.org/packages/coagus/php-api-builder)
[![Total Downloads](https://poser.pugx.org/coagus/php-api-builder/downloads)](https://packagist.org/packages/coagus/php-api-builder) 
[![License](https://poser.pugx.org/coagus/php-api-builder/license)](https://packagist.org/packages/coagus/php-api-builder)
[![PHP Unit Test](https://github.com/coagus/php-api-builder/workflows/PHP%20Unit%20Test/badge.svg)](https://github.com/coagus/php-api-builder/actions)

# PHP API BUILDER 

PHP API Builder is a lightweight and powerful library that streamlines API development in PHP. It provides:

- **Clean Architecture**: Enforces a well-structured and maintainable codebase
- **ORM Integration**: Built-in MySQL database integration with a simple yet powerful ORM
- **Authentication**: Out-of-the-box JWT authentication support
- **RESTful Services**: Easy implementation of RESTful endpoints
- **Error Handling**: Comprehensive error handling and debugging capabilities
- **Zero Configuration**: Minimal setup required with sensible defaults
- **PSR-4 Compliant**: Follows PHP-FIG standards for maximum compatibility

Perfect for building robust and scalable APIs while maintaining clean and organized code.

## Installation

Use composer to manage your dependencies and download PHP-API-BUILDER:

```bash
composer require coagus/php-api-builder
```

## Get Started

For the proper functioning of the API, it is necessary to modify the composer.json file and add the .htaccess and index.php files. With this, we can start the development.

```sh
|-- composer.json
|-- .htaccess
|-- index.php
|-- services
```

### composer.json

To maintain order in our API development, we define a name for our project from which all our services will branch out. For this example, my project will be called 'Services,' and I will specify that it will be developed in the 'services' folder.

```json
{
  "require": {
    "coagus/php-api-builder": "v1.0.0"
  }
  "autoload": {
    "psr-4": {
      "Services\\": "services/"
    }
  }
}
```

### .htaccess

This file defines the behavior of our server. Generally, the entry point is our index.php, and the URL does not define the folders within the server.

```sh
# Disable directory listing (prevents showing files in an empty directory)
Options All -Indexes

# Disable MultiViews option, serving only the exact requested file
Options -MultiViews

# Enable the URL rewriting engine
RewriteEngine On

# Redirect all requests that are not existing files to index.php
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [QSA,L]

# Set cache headers, establishing Cache-Control as private
<IfModule mod_headers.c>
    Header set Cache-Control "private"
</IfModule>
```

### index.php

The index.php is the entry point to the API; it only defines the project specified in its namespace.

```php
<?php
require_once 'vendor/autoload.php';

$api = new ApiBuilder\API('Services');
$api->run();
```

## Examples Demo Service

Create a Demo service file in services/Demo.php

```php
<?php
namespace Services;

class Demo
{
  public function get()
  {
    success('Hello World!');
  }

  public function postHello()
  {
    $input = getInput();
    success('Hello ' . $input['name'] . '!');
  }
}
```

### GET http://localhost/api/v1/demo

Result:

```json
{
  "successful": true,
  "result": "Hello World!"
}
```

### POST http://localhost/api/v1/demo/hello

Request:

```json
{
  "name": "Agustin"
}
```

Result:

```json
{
  "successful": true,
  "result": "Hello Agustin!"
}
```

## Example ORM

### Database

Create your entity in your database, for example User:

```sql
CREATE TABLE `roles` (
    `id` int NOT NULL AUTO_INCREMENT,
    `role` varchar(30) NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY (`role`)
  );

INSERT INTO roles (role)
VALUES ('Administrator'), ('Operator');

CREATE TABLE `users` (
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
```

### Environment File

Create de environment file ".env"

```shell
# DataBase configuration
DB_HOST=yourHost
DB_NAME=yourDbName
DB_USERNAME=yourUsername
DB_PASSWORD=yourPassword
DB_CHARSET=UTF8
```

### Entity

create the entity in your API services/entities/User.php

```php
<?php
namespace DemoApi\Entities;

use ApiBuilder\ORM\Entity;

class User extends Entity
{
  public $id;
  public $name;
  public $username;
  public $password;
  public $email;
  public $active;
  public $roleId;
}
```

### POST http://localhost/api/v1/users

Request:

```json
{
  "name": "Agustin",
  "username": "agustin",
  "password": "Pa$$word",
  "email": "christian@agustin.gt",
  "active": 1,
  "roleId": 1
}
```

Result:

```json
{
  "successful": true,
  "result": {
    "id": 1,
    "name": "Agustin",
    "username": "agustin",
    "password": "Pa$$word",
    "email": "christian@agustin.gt",
    "active": 1,
    "roleId": 1
  }
}
```

### GET http://localhost/api/v1/users

Result:

```json
{
  "successful": true,
  "result": {
    "pagination": {
      "count": 1,
      "page": "0",
      "rowsPerPage": "10"
    },
    "data": [
      {
        "id": 1,
        "name": "Agustin",
        "username": "agustin",
        "password": "Pa$$word",
        "email": "christian@agustin.gt",
        "active": 1,
        "roleId": 1
      }
    ]
  }
}
```

### PUT http://localhost/api/v1/users/1

Request:

```json
{
  "name": "Christian Agustin"
}
```

Result:

```json
{
  "successful": true,
  "result": {
    "id": 1,
    "name": "Christian Agustin",
    "username": "agustin",
    "password": "Pa$$word",
    "email": "christian@agustin.gt",
    "active": 1,
    "roleId": 1
  }
}
```

### DELETE http://localhost/api/v1/users/1

Result:

```json
{
  "successful": true,
  "result": "Deleted!"
}
```
