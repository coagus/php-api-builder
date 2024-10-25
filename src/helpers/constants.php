<?php 
# URI definition
define('URI_RESOURCE', 3);
define('URI_OPERATION_PRIMARY_ID', 4);
define('URI_OPERATION', 5);
define('URI_SECONDARY_ID', 6);

# Status Code
define('SC_SUCCESS_OK', 200);
define('SC_SUCCESS_CREATED', 201);
define('SC_ERROR_BAD_REQUEST', 400);
define('SC_ERROR_UNAUTHORIZED', 401);
define('SC_ERROR_NOT_FOUND', 404);

# Service
define('APIDB', 'ApiBuilder\ORM\APIDB');
define('SERVICE_LOG', 'API');
define('SERVICE_LOG_FILE', 'log/api.log');

# DB Key Environment
define('HOST', 'DB_HOST');
define('DBNAME', 'DB_NAME');
define('USERNAME', 'DB_USERNAME');
define('PASSWORD', 'DB_PASSWORD');
define('CHARSET', 'DB_CHARSET');