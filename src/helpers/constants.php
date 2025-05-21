<?php 
# URI definition
const URI_API = 1;
const URI_VERSION = 2;
const URI_RESOURCE = 3;
const URI_OPERATION_PRIMARY_ID = 4;
const URI_OPERATION = 5;
const URI_SECONDARY_ID = 6;

# Status Code
const SC_SUCCESS_OK = 200;
const SC_SUCCESS_CREATED = 201;
const SC_ERROR_BAD_REQUEST = 400;
const SC_ERROR_UNAUTHORIZED = 401;
const SC_ERROR_NOT_FOUND = 404;

# Service
const APIDB = 'ApiBuilder\ORM\APIDB';
const SERVICE_LOG = 'API';
const SERVICE_LOG_FILE = 'log/api.log';

# DB Key Environment
const HOST = 'DB_HOST';
const DBNAME = 'DB_NAME';
const USERNAME = 'DB_USERNAME';
const PASSWORD = 'DB_PASSWORD';
const CHARSET = 'DB_CHARSET';

# JWT
const KEY = 'JWT_KEY';
const ALG = 'JWT_ALG';
const EXP = 'JWT_EXP_MINS';
const SECURE = 'JWT_ENABLED';