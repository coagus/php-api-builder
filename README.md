![Latest Stable Version](https://poser.pugx.org/coagus/php-api-builder/v/stable) [![Total Downloads](https://poser.pugx.org/coagus/php-api-builder/downloads)](https://packagist.org/packages/firebase/php-jwt) [![License](https://poser.pugx.org/coagus/php-api-builder/license)](https://packagist.org/packages/firebase/php-jwt)

# PHP API BUILDER

A library designed to simplify the construction of APIs in PHP, ensuring clean, well-structured code, and providing out-of-the-box support for connecting a MySQL database as a resource.

## Installation

Use composer to manage your dependencies and download PHP-API-BUILDER:

``` bash
composer require coagus/php-api-builder
```

## Get Started

For the proper functioning of the API, it is necessary to modify the composer.json file and add the .htaccess and index.php files. With this, we can start the development.

``` sh
|-- composer.json
|-- .htaccess
|-- index.php
|-- services
```

### composer.json

To maintain order in our API development, we define a name for our project from which all our services will branch out. For this example, my project will be called ‘Services,’ and I will specify that it will be developed in the ‘services’ folder.

``` json
{
  "require": {
    "coagus/php-api-builder": "v0.6.0"    
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

``` sh
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

``` php
<?php
require_once 'vendor/autoload.php';

$api = new ApiBuilder\API('Services');
$api->run();
```

## Examples Demo Service

Create a Demo service file in services/Demo.php

``` php
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
    $input = getImput();
    success('Hello ' . $input['name'] . '!');
  }
}

```

### GET http://localhost/api/v1/demo 

Result:

``` json
{
  "successful": true,
  "result": "Hello World!"
}
```

### POST http://localhost/api/v1/demo/hello

Request:

``` json

{
  "name":"Agustin"
}
```

Result: 

``` json
{
  "successful": true,
  "result": "Hello Agustin!"
}
```