# PHP API BUILDER

API Builder is a library designed to simplify the construction of APIs in PHP, ensuring clean, well-structured code, and providing out-of-the-box support for connecting a MySQL database as a resource.

## Installation

Use composer to manage your dependencies and download PHP-API-BUILDER:

``` bash
composer require coagus/php-api-builder
```

## Get Started

1. Add .htaccess file

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

2. Modify composer.json, add your own autoload with the project name, in this case is Service

``` json
{
  "require": {
    "coagus/api-builder": "^0.1.1"
  },
  "autoload": {
    "psr-4": {
      "Services\\": "services/"
    }
  }
}
```

3. Add index.php

``` php
<?php
require_once 'vendor/autoload.php';

$api = new ApiBuilder\API();
$api->run('Services');
```

4. Create a Demo service file in services/Demo.php

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

The files will see like that:

``` sh
|-- composer.json
|-- .htaccess
|-- index.php
|-- services
|   `-- Demo.php
```

5. Test in postman or other application, execute a get http://localhost/api/v1/demo , the result will be:

``` json
{
  "successful": true,
  "result": "Hello World!"
}
```

6. Execute a post http://localhost/api/v1/demo/hello , the result will be:

``` json
{
  "successful": true,
  "result": "Hello Agustin!"
}
```