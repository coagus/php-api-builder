# PHP API Builder v2 — Análisis y Diseño

> Documento vivo de arquitectura y diseño para la nueva versión de php-api-builder.
> Última actualización: 2026-04-03

---

## 1. Visión del Proyecto

Crear una librería disponible en PHP Composer que permita construir APIs RESTful de forma simple, con un ORM potente pero fácil de usar, aprovechando todo el potencial de PHP 8.4.

### 1.1 Objetivos

- API RESTful basada en recursos con CRUD automático a partir de entidades
- ORM simple pero potente que mapee tablas de base de datos
- Compatible con PHP 8.4 (máximo soportado en SiteGround)
- Distribuible como paquete Composer (ya publicado como `coagus/php-api-builder`)
- Zero-config donde sea posible, pero altamente configurable cuando se necesite

### 1.2 Estado Actual (v1.3.3)

La versión actual ya cuenta con:

- Entidades como clases PHP que representan tablas de la DB
- CRUD automático basado en el recurso de la URL
- Autenticación JWT (firebase/php-jwt)
- Logging (Monolog)
- Configuración por variables de entorno (phpdotenv)
- Sorting por campos del query
- Validación básica por existencia de campo en tabla
- Soporte MySQL
- PSR-4 autoloading

---

## 2. Estándar REST — Buenas Prácticas

### 2.1 Diseño de Recursos (URLs)

El estándar REST define que las URLs representan recursos (sustantivos, nunca verbos) y los métodos HTTP definen la acción:

| Método | Ruta | Acción | Status Code |
|--------|------|--------|-------------|
| GET | /api/v1/users | Listar todos (con paginación) | 200 OK |
| GET | /api/v1/users/{id} | Obtener uno específico | 200 OK |
| POST | /api/v1/users | Crear nuevo recurso | 201 Created |
| PUT | /api/v1/users/{id} | Reemplazo completo del recurso | 200 OK |
| PATCH | /api/v1/users/{id} | Actualización parcial | 200 OK |
| DELETE | /api/v1/users/{id} | Eliminar recurso | 204 No Content |

**Convenciones de nombrado:**
- Usar sustantivos en plural: `/users`, `/orders`, `/products`
- Minúsculas con guiones para multi-palabra: `/user-profiles`
- Recursos anidados para relaciones: `/users/{id}/orders`
- Máximo 2 niveles de anidamiento

### 2.2 PUT vs PATCH

| Aspecto | PUT | PATCH |
|---------|-----|-------|
| Propósito | Reemplazo completo | Actualización parcial |
| Payload | Todos los campos del recurso | Solo los campos a modificar |
| Idempotente | Sí | Sí |
| Si omites un campo | Se establece en null/default | Se mantiene el valor actual |

**Decisión:** Soportar ambos (PUT y PATCH).

### 2.3 Códigos de Respuesta HTTP

#### Éxito
- `200 OK` — Respuesta exitosa con body (GET, PUT, PATCH)
- `201 Created` — Recurso creado (POST), incluir header `Location` con la URL del nuevo recurso
- `204 No Content` — Acción exitosa sin body (DELETE)

#### Error del Cliente
- `400 Bad Request` — Request mal formado (JSON inválido, parámetros faltantes)
- `401 Unauthorized` — No autenticado (falta token o token inválido)
- `403 Forbidden` — Autenticado pero sin permisos
- `404 Not Found` — Recurso no existe
- `405 Method Not Allowed` — Método HTTP no soportado en ese endpoint
- `409 Conflict` — Conflicto (ej: registro duplicado por unique constraint)
- `422 Unprocessable Entity` — Validación fallida (datos correctos en formato pero inválidos en reglas de negocio)
- `429 Too Many Requests` — Rate limiting excedido

#### Error del Servidor
- `500 Internal Server Error` — Error inesperado del servidor

### 2.4 Formato de Errores (RFC 7807 — Problem Details)

Estándar para respuestas de error consistentes:

```json
{
  "type": "https://api.example.com/errors/validation-error",
  "title": "Validation Error",
  "status": 422,
  "detail": "The field 'email' must be a valid email address.",
  "instance": "/api/v1/users",
  "errors": {
    "email": ["Must be a valid email address"],
    "name": ["Is required", "Must be at least 2 characters"]
  }
}
```

### 2.5 Convención de Nombres: URL vs JSON

**Decisión:** Seguir la convención de las APIs más reconocidas (Google, GitHub, Stripe, Twitter):

| Contexto | Convención | Ejemplo |
|----------|------------|---------|
| URL path | kebab-case | `/api/v1/user-profiles` |
| Query params | snake_case | `?per_page=25&sort=-created_at` |
| JSON body (request/response) | lowerCamelCase | `{"currentPage": 2, "perPage": 25}` |
| DB columns | snake_case | `created_at`, `role_id` |
| PHP properties | camelCase | `$createdAt`, `$roleId` |

La librería convierte automáticamente entre estas convenciones en cada capa.

### 2.6 Paginación

#### Offset-based (más simple, recomendado para la mayoría de casos)

Request: `GET /api/v1/users?page=2&per_page=25`

Response:
```json
{
  "data": [...],
  "meta": {
    "currentPage": 2,
    "perPage": 25,
    "total": 150,
    "totalPages": 6
  },
  "links": {
    "first": "/api/v1/users?page=1&per_page=25",
    "prev": "/api/v1/users?page=1&per_page=25",
    "next": "/api/v1/users?page=3&per_page=25",
    "last": "/api/v1/users?page=6&per_page=25"
  }
}
```

#### Cursor-based (más eficiente para datasets grandes)

Request: `GET /api/v1/users?cursor=eyJpZCI6MjV9&limit=25`

Usar cuando el dataset es muy grande o se inserta/elimina frecuentemente.

**Decisión:** Soportar offset-based por defecto, con opción de cursor-based.

### 2.7 Filtrado, Ordenamiento y Búsqueda

```
GET /api/v1/users?status=active&role=admin          # Filtrado
GET /api/v1/users?sort=-created_at,name              # Orden (- = DESC)
GET /api/v1/users?search=john                        # Búsqueda general
GET /api/v1/users?fields=id,name,email               # Selección de campos
```

**Operadores de filtro avanzados (opcional):**
```
GET /api/v1/users?age[gte]=18&age[lte]=65           # Mayor/menor que
GET /api/v1/users?status[in]=active,pending          # En lista
GET /api/v1/users?name[like]=john%                   # Like/contains
```

### 2.8 Versionado

```
/api/v1/users    ← Versión en la URL (más común y recomendado)
```

Alternativas menos comunes: header `Accept: application/vnd.api.v1+json` o query param `?version=1`.

**Decisión:** Versionado en URL (`/api/v1/`).

### 2.9 Relaciones entre Recursos

```
GET /api/v1/users/{id}/orders          # Órdenes de un usuario
GET /api/v1/orders/{id}/items          # Items de una orden
GET /api/v1/users/{id}?include=orders  # Eager loading de relaciones
```

### 2.10 Autenticación y Autorización

#### Estrategia: JWT con prácticas de seguridad OAuth 2.1

**Decisión:** No incluir un servidor OAuth 2.0 completo (evita agregar 7+ dependencias como `league/oauth2-server`). En su lugar, implementar un sistema JWT robusto que adopte las mejores prácticas de seguridad de OAuth 2.1 sobre `firebase/php-jwt` (dependencia que ya existe en v1).

Si un desarrollador necesita OAuth 2.0 completo (login con Google, GitHub, etc.), puede integrar `league/oauth2-server` o `league/oauth2-client` por su cuenta junto a esta librería.

**¿Por qué este enfoque?**
- JWT y OAuth 2.0 no compiten: OAuth es el protocolo (quién pide acceso y cómo), JWT es el formato del token. Google usa ambos juntos.
- Un servidor OAuth 2.0 completo agrega complejidad innecesaria para la mayoría de APIs.
- Las prácticas de seguridad de OAuth 2.1 se pueden implementar sobre JWT sin el protocolo completo.
- Mantiene la librería ligera (no duplica dependencias).

#### Niveles de autenticación (3 capas)

```
Request entrante
    │
    ▼
¿Recurso/método tiene #[PublicResource]?
    ├── SÍ → Acceso libre, sin verificación
    │
    └── NO → ¿Existe clase APIKey en el proyecto?
              ├── SÍ → APIKey::validate() → API key auth
              │         (para integraciones servicio-a-servicio)
              │
              └── NO → Auth::validateSession()
                        → Verificar JWT Bearer token
                        → Verificar firma, expiración, issuer, audience
                        → Verificar scopes si aplica
```

#### Access Token (corta duración)

```php
// Generación del access token
Auth::createAccessToken(
    user: $userData,
    scopes: ['users:read', 'orders:write'],  // Permisos granulares
    expiresIn: 900                            // 15 minutos (configurable)
);
```

Payload del access token:
```json
{
    "iss": "https://mi-api.com",
    "sub": "user:42",
    "aud": "https://mi-api.com/api",
    "iat": 1712150400,
    "exp": 1712151300,
    "jti": "unique-token-id-uuid",
    "scopes": ["users:read", "orders:write"],
    "data": {
        "id": 42,
        "name": "Christian",
        "role": "admin"
    }
}
```

**Claims estándar implementados:**
- `iss` (issuer): quién emitió el token
- `sub` (subject): identificador del usuario
- `aud` (audience): para quién es el token (tu API)
- `iat` (issued at): cuándo se emitió
- `exp` (expiration): cuándo expira
- `jti` (JWT ID): ID único para prevenir reutilización
- `scopes`: permisos del token
- `data`: datos del usuario (custom)

#### Refresh Token (larga duración, con rotación)

El refresh token permite renovar el access token sin re-autenticarse. Se almacena en DB (no en JWT) para poder revocarlo.

```php
// Flujo de login → emite ambos tokens
public function postLogin(): void {
    $credentials = getInput();
    $user = $this->validateCredentials($credentials);

    $accessToken  = Auth::createAccessToken($user, expiresIn: 900);
    $refreshToken = Auth::createRefreshToken($user, expiresIn: 604800); // 7 días

    success([
        'accessToken'  => $accessToken,
        'refreshToken' => $refreshToken,
        'expiresIn'    => 900,
        'tokenType'    => 'Bearer'
    ]);
}

// Flujo de refresh → rota el refresh token
#[PublicResource]
public function postRefresh(): void {
    $input = getInput();
    $tokens = Auth::refreshTokens($input->refreshToken);
    // 1. Valida el refresh token en DB
    // 2. Genera NUEVO access token
    // 3. Genera NUEVO refresh token (rotación)
    // 4. Invalida el refresh token anterior
    // 5. Si el refresh token ya fue usado → REVOCAR TODOS (posible robo)

    success([
        'accessToken'  => $tokens->accessToken,
        'refreshToken' => $tokens->refreshToken,
        'expiresIn'    => 900,
        'tokenType'    => 'Bearer'
    ]);
}
```

**Rotación de refresh tokens (OAuth 2.1 best practice):**
Cada vez que se usa un refresh token, se genera uno nuevo y el anterior se invalida. Si alguien intenta usar un refresh token ya usado, se revocan TODOS los tokens de esa sesión (indica posible robo).

#### Scopes (permisos granulares)

```php
// Definir scopes requeridos por recurso o método
#[Scope('users:read')]
public function get(): void { /* solo lectura de usuarios */ }

#[Scope('users:write')]
public function post(): void { /* crear usuarios */ }

#[Scope('admin')]
public function delete(): void { /* solo admins */ }
```

Los scopes se verifican automáticamente contra los scopes del token.

#### Algoritmos de firma

```env
# .env — Configuración de seguridad
JWT_ALGORITHM=RS256          # Asimétrico (recomendado para producción)
JWT_PRIVATE_KEY=./keys/private.pem
JWT_PUBLIC_KEY=./keys/public.pem
JWT_ACCESS_TOKEN_TTL=900     # 15 minutos
JWT_REFRESH_TOKEN_TTL=604800 # 7 días
JWT_ISSUER=https://mi-api.com
JWT_AUDIENCE=https://mi-api.com/api
```

| Algoritmo | Tipo | Seguridad | Uso recomendado |
|-----------|------|-----------|-----------------|
| HS256 | Simétrico (secret) | Buena | Desarrollo, APIs internas simples |
| RS256 | Asimétrico (pub/priv key) | Alta | **Producción (recomendado)** |
| ES256 | Asimétrico (elliptic curve) | Muy alta | Producción, tokens compactos |

**Decisión:** Soportar los tres. RS256 como default para producción.

#### Tabla de refresh tokens en DB

```sql
CREATE TABLE refresh_tokens (
    id VARCHAR(36) PRIMARY KEY,       -- UUID
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,  -- SHA-256 del token (nunca guardar plain)
    family_id VARCHAR(36) NOT NULL,   -- Agrupa tokens de una misma sesión
    expires_at TIMESTAMP NOT NULL,
    revoked BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_token_hash (token_hash),
    INDEX idx_family_id (family_id)
);
```

El `family_id` permite revocar todos los tokens de una sesión si se detecta reutilización (posible robo).

#### Header de autenticación
```
Authorization: Bearer <access_token>
```

#### Resumen de seguridad implementada

| Práctica | Descripción |
|----------|-------------|
| Access token corto | 15-60 min, reduce ventana de ataque |
| Refresh token con rotación | Token nuevo en cada refresh, el viejo se invalida |
| Detección de robo | Si un refresh token ya usado se reutiliza → revocar familia completa |
| Algoritmos asimétricos | RS256/ES256 para producción (clave pública verifica, privada firma) |
| Claims estándar | iss, sub, aud, exp, jti — validación completa en cada request |
| Scopes | Permisos granulares por recurso y método |
| Token hash en DB | Refresh tokens almacenados como hash, nunca en texto plano |
| JTI único | Previene replay attacks |

### 2.11 Rate Limiting

Headers de respuesta estándar:
```
X-Rate-Limit-Limit: 100
X-Rate-Limit-Remaining: 95
X-Rate-Limit-Reset: 1620000000
```

### 2.12 Caching

Headers estándar:
```
ETag: "33a64df551425fcc55e4d42a148795d9f25f89d4"
Cache-Control: max-age=3600
Last-Modified: Thu, 03 Apr 2026 10:00:00 GMT
```

### 2.13 CORS (Cross-Origin Resource Sharing)

Headers necesarios para que el API sea consumible desde frontends:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS
Access-Control-Allow-Headers: Content-Type, Authorization
```

### 2.14 Documentación Automática (OpenAPI/Swagger)

El estándar actual (2026) espera que las APIs entreguen su especificación OpenAPI como parte del producto. La librería debería poder generar automáticamente un `openapi.json` basado en las entidades registradas.

---

## 3. Arquitectura Actual vs Propuesta

### 3.1 Análisis Profundo de la Arquitectura v1.3.3

#### Flujo de Request Completo

```
index.php → new API('DemoApi') → $api->run()
  ├── loadEnv()                      → Carga variables de .env
  ├── setCors()                      → Valida origen y configura headers CORS
  ├── getRequestUri()                → Parsea URL en array de segmentos
  ├── validateRequest()              → Valida estructura, formato, IDs numéricos
  ├── getInstanceResourceService()   → Descubre e instancia clase del recurso
  ├── getOperation()                 → Genera nombre de método: post + Activate = postActivate
  ├── authorizeOperation()           → Verifica #[PublicResource], APIKey o JWT
  └── call_user_func()              → Ejecuta el método y retorna JSON
```

#### Capas del Sistema

| Capa | Clases | Responsabilidad |
|------|--------|-----------------|
| Routing | API.php, utils.php | Parseo de URL, descubrimiento de clases, despacho |
| Auth | Auth.php, API.authorizeOperation() | JWT, API keys, atributo #[PublicResource] |
| Servicios | APIDB.php, clases custom del usuario | Lógica CRUD y operaciones personalizadas |
| ORM | Entity.php, SqlBuilder.php | Generación SQL, mapeo objeto↔tabla |
| DB | DataBase.php | Conexión PDO, ejecución de queries, paginación |
| Helpers | api-tools.php, constants.php, utils.php | Respuestas, errores, logging, utilidades |

#### Sistema de Entidades

Las entidades se definen como clases PHP con propiedades públicas sin tipo que mapean columnas de la DB. No hay encapsulamiento: todo es público, sin tipos, sin validación, y los booleans requieren un método aparte.

```php
// v1: propiedades públicas sin tipo — simple pero desprotegido
class User extends Entity {
    public $id;         // Sin tipo, sin protección de escritura
    public $name;       // Acepta cualquier valor
    public $username;   // Sin validación
    public $password;   // Se expone en responses JSON
    public $email;      // "hola" es un email válido aquí
    public $active;     // No es bool nativo, necesita conversión manual
    public $roleId;     // → columna role_id (conversión camelCase↔snake_case sí funciona)

    // Workaround: método aparte para definir qué campos son boolean
    protected function getBooleanFields() { return ['active']; }
}
```

**Problemas de este enfoque:**
- `$user->id = "texto"` no lanza error — sin seguridad de tipos
- `$user->password` se incluye en JSON responses si no se excluye manualmente
- `$user->email = "no-es-email"` se guarda en DB sin validar
- Los booleans requieren `getBooleanFields()` porque PHP no sabe que `active` es bool
- Cualquier código externo puede escribir `$user->id = 999` corrompiendo el estado

**En v2 con PHP 8.4** estos problemas se resuelven con typed properties, property hooks y asymmetric visibility (ver sección 4.1 para el paradigma completo y sección 4.2 para el diseño de entidades v2).

El descubrimiento es automático: `/api/v1/users` → singular `user` → PascalCase `User` → busca `{Project}\Entities\User`.

#### Sistema de Routing y Operaciones

La URL define la operación automáticamente:

| URL | Método HTTP | Operación generada | ¿Quién maneja? |
|-----|------------|-------------------|-----------------|
| /api/v1/users | GET | get() | APIDB.get() → Entity.getAll() |
| /api/v1/users | POST | post() | APIDB.post() → Entity.save() |
| /api/v1/users/1 | GET | get() | APIDB.get() → Entity.getById(1) |
| /api/v1/users/1 | PUT | put() | APIDB.put() → valida todos los campos → save(1) |
| /api/v1/users/1 | PATCH | patch() | APIDB.patch() → permite campos parciales → save(1) |
| /api/v1/users/1 | DELETE | delete() | APIDB.delete() → Entity.delete(1) |
| /api/v1/users/1/activate | POST | postActivate() | User.postActivate() (custom) |

#### Atributos PHP Existentes

- `#[PublicResource]` — Marca clase o método como público (sin auth)
- `#[Route('custom-path')]` — Mapea una ruta custom a una clase servicio
- `#[Table('custom_table')]` — Sobrescribe el nombre de tabla de una entidad

#### Sistema de Auth (3 niveles)

1. Clase tiene `#[PublicResource]` → acceso libre
2. Método tiene `#[PublicResource]` → acceso libre para ese método
3. Si existe clase APIKey → valida API key
4. Si no → valida JWT via `Auth::validateSession()`

#### Query Features Actuales

- Paginación: `?page=0&rowsPerPage=10` (offset-based, -1 para todos)
- Búsqueda: `?search=keyword` (LIKE %% en todos los campos)
- Filtrado: `?field=value` (match exacto por campo)
- Orden: hardcoded `ORDER BY id DESC`

### 3.2 Fortalezas (mantener y potenciar)

- **Convention over Configuration**: URL → clase → tabla automáticamente, sin configurar rutas
- **Entidades declarativas**: propiedades públicas = columnas, muy intuitivo
- **CRUD sin código**: solo definir entidad y ya tiene REST completo
- **Extensibilidad**: servicios custom heredan APIDB y agregan operaciones (postLogin, postActivate)
- **Atributos PHP modernos**: ya usa #[PublicResource], #[Route], #[Table]
- **Conversión automática**: camelCase (PHP) ↔ snake_case (DB) transparente
- **Auth flexible**: JWT + API keys + público, configurable por clase/método
- **CORS extensible**: por .env o por clase Cors con orígenes de BD
- **Testing**: 35+ tests unitarios con buena cobertura
- **DevOps ready**: Docker Compose, CI/CD con GitHub Actions, Postman collection

### 3.3 Debilidades Críticas (corregir en v2)

#### Seguridad

- **SQL Injection en SqlBuilder**: `fillWhere()` usa interpolación de strings (`WHERE field = '$value'`). Esto permite inyección SQL directa. v2 DEBE usar prepared statements con parámetros en TODOS los queries, no solo en mutaciones.
- **Sin validación de input**: los campos se aceptan tal cual del request, sin sanitización, límites de longitud, ni validación de tipo/formato.
- **Password podría filtrarse**: en el flujo estándar de GET, los campos sensibles como password se retornan si no se excluyen explícitamente.

#### Arquitectura

- **APIDB mezcla responsabilidades**: valida, construye entidad y persiste, todo en la misma clase. Dificulta testing y extensión.
- **Sin transacciones**: operaciones multi-tabla pueden quedar en estado inconsistente.
- **Sin middleware**: no hay forma de agregar lógica transversal (logging de requests, rate limiting, transformaciones) sin tocar el core.
- **Sin inyección de dependencias**: las clases instancian sus dependencias directamente, dificultando testing y swappeo de implementaciones.
- **Global `$debugData`**: variable global para debug, dificulta testing y puede filtrar info en producción.

#### Funcionalidad

- **Sin relaciones**: no hay forma de definir HasMany, BelongsTo, ni hacer JOINs
- **Sorting hardcoded**: siempre `ORDER BY id DESC`, no configurable por request
- **Sin selección de campos**: no se puede pedir solo ciertos campos (`?fields=id,name`)
- **Sin filtros avanzados**: solo match exacto, no hay gte, lte, in, like, between
- **Solo MySQL**: sin soporte para PostgreSQL ni SQLite
- **Sin migraciones**: schema manual
- **Sin documentación automática**: no genera OpenAPI/Swagger

### 3.4 Mejoras propuestas para v2

| Área | v1 (Actual) | v2 (Propuesto) |
|------|-------------|----------------|
| **Seguridad** | SQL por interpolación | Prepared statements en TODOS los queries |
| **PHP** | 8.4 (ya) | 8.4 con property hooks, readonly, enums, typed properties |
| **Entidades** | Propiedades públicas sin tipo | Typed properties, property hooks, atributos para metadata |
| **Validación** | Solo existencia de campo | Validación por atributos (#[Required], #[Email], #[MaxLength]) |
| **Relaciones** | No implementado | #[HasMany], #[BelongsTo], #[BelongsToMany] + eager loading |
| **Respuestas** | `{successful, result}` | Formato estándar `{data, meta, links, errors}` (RFC 7807) |
| **Filtrado** | Solo match exacto + search LIKE | Operadores avanzados: gte, lte, in, like, between |
| **Sorting** | Hardcoded `id DESC` | Configurable por request: `?sort=-created_at,name` |
| **Selección** | Todos los campos siempre | `?fields=id,name,email` para elegir campos |
| **Paginación** | Offset-based básica | Offset-based mejorada + cursor-based opcional |
| **HTTP Methods** | GET, POST, PUT, PATCH, DELETE | Mismos + status codes semánticos (201, 204, 422) |
| **Auth** | JWT + API key | JWT con prácticas OAuth 2.1: access tokens cortos, refresh tokens con rotación, scopes, RS256/ES256 |
| **Rate Limiting** | No | Configurable con headers estándar |
| **CORS** | .env o clase Cors | Mejorado con soporte credentials, cache, preflight completo |
| **Documentación** | Manual (README + Postman) | Generación automática de OpenAPI spec desde entidades |
| **Bases de datos** | Solo MySQL | MySQL + PostgreSQL + SQLite |
| **Middleware** | No | Pipeline de middleware para concerns transversales |
| **DI** | Instanciación directa | Container de inyección de dependencias |
| **Campos sensibles** | Exclusión manual | Atributo #[Hidden] para excluir de responses automáticamente |
| **Transacciones** | No | Soporte de transacciones DB |
| **Soft Deletes** | No (solo hard delete) | Opcional con #[SoftDelete] y campo deleted_at |

---

## 4. Filosofía y Decisiones Técnicas

### 4.0 Principios de Diseño

> "Fácil de usar, rápido, fácil de interpretar, estándares actuales, seguro, código limpio con altos estándares."

| Principio | Implicación en el diseño |
|-----------|--------------------------|
| **Fácil de usar** | Active Record, convention over config, zero-boilerplate. El desarrollador define una entidad y tiene API REST completa |
| **Rápido** | Prepared statements cacheados, lazy loading, sin overhead innecesario, queries optimizados con solo los campos necesarios |
| **Fácil de interpretar** | Código declarativo con atributos PHP, nombres claros, sin magia oculta. Leer una entidad te dice todo |
| **Estándares actuales** | PSR-4, PSR-7, PSR-12, PSR-15, RFC 7807, OpenAPI 3.1, JSON:API inspired responses |
| **Seguro** | Prepared statements en TODO, validación por atributos, campos #[Hidden], CORS estricto, rate limiting |
| **Código limpio** | Typed properties, enums, readonly, SRP, DI, sin globals, sin magic numbers |

### Estándares PSR a Implementar

| PSR | Nombre | Para qué |
|-----|--------|----------|
| PSR-4 | Autoloading | Ya implementado. Carga automática de clases por namespace |
| PSR-7 | HTTP Message Interfaces | Objetos Request/Response inmutables y estándar |
| PSR-12 | Extended Coding Style | Estilo de código consistente y profesional |
| PSR-15 | HTTP Server Request Handlers | Middleware pipeline estándar |

### 4.1 Paradigma: OOP Moderno con PHP 8.4 (Pragmático, no Ceremonioso)

#### El problema con la POO tradicional

La POO clásica dicta: "todo privado, acceso solo por getters/setters". Para una entidad con 10 campos, eso son 20+ métodos boilerplate que no aportan valor:

```php
// ❌ POO tradicional — ceremonioso, lento de escribir, difícil de leer
class User {
    private int $id;
    private string $name;
    private string $email;
    private string $password;
    private bool $active;

    public function getId(): int { return $this->id; }
    public function getName(): string { return $this->name; }
    public function setName(string $name): void {
        if (strlen($name) > 100) throw new \InvalidArgumentException('Too long');
        $this->name = $name;
    }
    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Invalid');
        $this->email = $email;
    }
    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): void {
        $this->password = password_hash($password, PASSWORD_ARGON2ID);
    }
    // ... y así por cada campo. 60+ líneas para 5 campos.
}
```

#### El atajo de v1 — simple pero desprotegido

```php
// ❌ v1 actual — fácil pero sin seguridad
class User extends Entity {
    public $id;         // sin tipo, cualquiera escribe cualquier cosa
    public $name;       // sin validación
    public $email;      // acepta "hola" como email
    public $password;   // se expone en JSON responses
    public $active;     // necesita getBooleanFields() aparte
}
```

#### PHP 8.4: Lo mejor de ambos mundos

PHP 8.4 introdujo **property hooks** y **asymmetric visibility**, que resuelven este dilema histórico. Permiten propiedades públicas con comportamiento: la simplicidad de acceso directo con la protección del encapsulamiento.

```php
// ✅ v2 con PHP 8.4 — simple, seguro, declarativo
#[Table('users')]
#[SoftDelete]
class User extends Entity {
    #[PrimaryKey]
    public private(set) int $id;           // Lee público, solo el framework lo escribe

    #[Required, MaxLength(50)]
    public string $name {
        set => trim($value);               // Se limpia al asignar
    }

    #[Required, Email, Unique]
    public string $email {
        set => strtolower(trim($value));   // Se normaliza al asignar
    }

    #[Hidden]                               // Nunca sale en JSON
    public string $password {
        set => password_hash($value, PASSWORD_ARGON2ID);  // Se hashea al asignar
    }

    public bool $active = false;            // Tipo bool nativo, no necesita getBooleanFields()

    #[BelongsTo(Role::class)]
    public int $roleId;

    #[HasMany(Order::class)]
    public array $orders;
}
```

**Resultado:** 20 líneas vs 60+ líneas, mismo nivel de protección, mucho más legible.

#### Reglas de visibilidad por tipo de propiedad

| Tipo de propiedad | Visibilidad | Mecanismo PHP 8.4 | Ejemplo |
|-------------------|-------------|-------------------|---------|
| **ID / Primary Key** | Lee público, escribe solo framework | `public private(set)` | `public private(set) int $id` |
| **Campo normal** | Lee y escribe público, con tipo | Typed property | `public string $name` |
| **Campo con transformación** | Lee y escribe público, transforma al asignar | Property hook `set` | `set => trim($value)` |
| **Campo computado/virtual** | Solo lectura, calculado | Property hook `get` | `get => $this->firstName . ' ' . $this->lastName` |
| **Campo sensible** | Nunca en JSON, escribe público | `#[Hidden]` + hook | `#[Hidden] public string $password { set => hash(...) }` |
| **Campo de solo lectura post-creación** | Escribe solo en create | `#[ReadOnly]` | `#[ReadOnly] public string $slug` |
| **Timestamp automático** | No se asigna manualmente | `public private(set)` + hook lifecycle | Se llena en `beforeCreate`/`beforeUpdate` |
| **Relación** | Solo lectura, carga lazy | `public private(set)` | `#[HasMany(Order::class)] public private(set) array $orders` |

#### Filosofía aplicada a cada capa de la librería

##### Capa de Entidades (lo que crea el desarrollador usuario)

```php
// El desarrollador escribe esto — SIMPLE, declarativo, todo expresado con atributos
#[Table('products')]
class Product extends Entity {
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(200)]
    public string $name;

    #[Required, Min(0)]
    public float $price {
        set => round($value, 2);    // Siempre 2 decimales
    }

    #[In(['active', 'draft', 'archived'])]
    #[Default('draft')]
    public string $status;

    #[BelongsTo(Category::class)]
    public int $categoryId;

    // Campo virtual — no existe en DB, se calcula
    public string $displayPrice {
        get => '$' . number_format($this->price, 2);
    }
}
```

El framework lee los atributos por reflection y sabe: qué validar, qué transformar, qué ocultar, qué relaciones cargar, y qué SQL generar.

##### Capa ORM / Query Builder (internals del framework)

```php
// Interno del framework — usa composición, interfaces, final classes
final class QueryBuilder {
    private array $wheres = [];       // Privado: solo se modifica via métodos
    private array $bindings = [];     // Privado: parámetros PDO
    private array $orderBy = [];
    private ?int $limit = null;
    private ?int $offset = null;

    // API fluent pública — cada método retorna $this
    public function where(string $field, mixed $operator, mixed $value = null): self { ... }
    public function orderBy(string $field, string $direction = 'asc'): self { ... }
    public function paginate(int $page = 1, int $perPage = 25): PaginatedResult { ... }

    // Método interno — construye el SQL final (nunca se llama desde afuera)
    private function buildSql(): string { ... }
    private function buildWhereClause(): string { ... }
}
```

Aquí sí aplica encapsulamiento estricto: `wheres`, `bindings`, y los métodos de construcción SQL son privados porque el estado interno NO debe manipularse directamente.

##### Capa HTTP / Middleware (internals del framework)

```php
// Interfaces claras, clases final, inmutabilidad donde aplica
interface MiddlewareInterface {
    public function handle(Request $request, callable $next): Response;
}

final class AuthMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly Auth $auth,              // Inyectado, readonly
        private readonly ScopeValidator $scopes,  // Inyectado, readonly
    ) {}

    public function handle(Request $request, callable $next): Response { ... }
}

// Request inmutable — readonly class
readonly class Request {
    public function __construct(
        public string $method,
        public string $uri,
        public array $headers,
        public ?array $body,
        public array $queryParams,
    ) {}
}
```

##### Capa de Servicios (lo que crea el desarrollador usuario)

```php
// Servicio puro — sin DB, métodos claros con tipos
class Payment extends Service {
    public function post(): void {
        $input = $this->getInput();
        $charge = $this->callStripeApi($input);
        $this->success($charge, StatusCode::CREATED);
    }

    // Método privado: lógica interna que no se expone como endpoint
    private function callStripeApi(object $data): object { ... }
}

// Servicio híbrido — CRUD + custom, los métodos son los endpoints
class User extends APIDB {
    // Los métodos públicos SON los endpoints REST
    #[PublicResource]
    public function postLogin(): void { ... }

    #[Scope('admin')]
    public function postDeactivate(): void { ... }

    // Métodos privados: lógica interna, nunca se exponen como endpoint
    private function sendWelcomeEmail(string $email): void { ... }
    private function validateCredentials(object $input): object { ... }
}
```

#### Resumen: Cuándo usar qué

| Contexto | Enfoque | Razón |
|----------|---------|-------|
| **Entidades** | Propiedades públicas tipadas + hooks + atributos | Simplicidad, declarativo, auto-documentado |
| **Query Builder** | Privado internamente, API fluent pública | Estado interno protegido, seguridad SQL |
| **Request/Response** | `readonly class` | Inmutabilidad, seguridad |
| **Middleware** | `final class` + interface + constructor injection | No extensible por herencia, composable |
| **Servicios del usuario** | Público = endpoint, privado = lógica interna | Convención clara: visibilidad = exposición |
| **Config/DTOs** | `readonly class` con constructor promotion | Datos que no cambian después de crearse |
| **Helpers/Utilidades** | Funciones puras (funcional) cuando no necesitan estado | Sin ceremonia innecesaria |

#### Principio general

> **"La visibilidad debe comunicar intención, no ceremonia."**
>
> - `public` = el usuario de la librería o el framework lo usa directamente
> - `private` = lógica interna que no debe manipularse desde afuera
> - `protected` = extensible por subclases (entidades, servicios custom)
> - `readonly` = dato que no cambia después de inicialización
> - `final` = clase que no debe extenderse (componentes core del framework)
> - Property hooks = comportamiento en asignación/lectura sin getters/setters
> - Asymmetric visibility = lectura pública, escritura restringida

---

### 4.2 Estilo de ORM — Active Record Tipado

**Decisión: Active Record** — alineado con el principio de "fácil de usar".

El v1 ya es Active Record (`$entity->save()`). Lo mantenemos pero con tipos estrictos, atributos declarativos y prepared statements.

**v1 (actual):**
```php
class User extends Entity {
    public $id;
    public $name;
    public $email;
    public $active;
    public $roleId;

    protected function getBooleanFields() { return ['active']; }
}
```

**v2 (propuesto):**
```php
#[Table('users')]
#[SoftDelete]
class User extends Entity {
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(50)]
    public string $name;

    #[Required, Email, Unique]
    public string $email;

    #[Hidden]
    public string $password {
        set => password_hash($value, PASSWORD_ARGON2ID);
    }

    public bool $active = false;

    #[BelongsTo(Role::class)]
    public int $roleId;

    #[HasMany(Order::class)]
    public array $orders;
}
```

**¿Por qué Active Record y no Data Mapper?**
- Más simple de entender: lees la clase y sabes todo
- Menos archivos: no necesitas repositorios separados
- Tu v1 ya lo usa, la migración es natural
- Para APIs REST con CRUD, Active Record es ideal
- Los atributos PHP compensan la falta de separación: la metadata ES la documentación

### 4.3 Sistema de Migraciones — No incluir (por ahora)

**Decisión: El desarrollador maneja el schema.**

Razones:
- Mantiene la librería enfocada (API + ORM, no herramienta de DB management)
- Reduce complejidad del paquete
- Herramientas como Phinx o Doctrine Migrations ya existen para eso
- Fase futura: podría generarse un schema SQL desde los atributos de las entidades (como referencia, no como migración automática)

**Futuro opcional:**
```bash
# Posible CLI que genera SQL de referencia basado en entidades
php api-builder schema:generate
# Output: CREATE TABLE users (id INT AUTO_INCREMENT, name VARCHAR(50) NOT NULL, ...)
```

### 4.4 Query Builder — 5 Niveles de Complejidad

**Decisión: API fluent (encadenada) con prepared statements obligatorios y salida a raw SQL seguro para queries complejos.**

El Query Builder ofrece un camino progresivo: empieza simple, sube de nivel solo cuando lo necesitas, y siempre tienes escape a SQL raw seguro.

#### Nivel 1 — Shortcuts (CRUD automático, cubre el 60%)

El CRUD automático de APIDB usa estos métodos internamente. El desarrollador no escribe nada, el framework los genera desde los query params de la URL.

```php
$user  = User::find(1);                          // SELECT ... WHERE id = :id
$users = User::all();                            // SELECT ... (paginado por defecto)
$admins = User::where('role', 'admin')->get();   // SELECT ... WHERE role = :role
```

Para el endpoint REST, los query params (snake_case) se traducen automáticamente:
```
GET /api/v1/users?active=true&role_id[gte]=2&sort=-name&fields=id,name,email&page=2&per_page=25
```
La librería convierte: snake_case (URL) → camelCase (PHP) → snake_case (DB) transparentemente.

#### Nivel 2 — Query Builder fluent (cubre el 80%)

Para queries más específicos dentro de servicios custom:

```php
$users = User::query()
    ->where('active', true)
    ->where('roleId', '>=', 2)
    ->whereIn('status', ['active', 'pending'])
    ->whereNotNull('email')
    ->whereBetween('createdAt', ['2026-01-01', '2026-12-31'])
    ->orderBy('name')
    ->orderBy('createdAt', 'desc')
    ->select('id', 'name', 'email')
    ->paginate(page: 2, perPage: 25);
```

Todo genera SQL con prepared statements. Nunca se interpolan valores.

#### Nivel 3 — Relaciones con eager loading (cubre el 90%)

```php
// Traer usuario con sus órdenes y el rol
$user = User::with('orders', 'role')->find(1);

// Filtrar sobre relaciones
$users = User::query()
    ->whereHas('orders', function (QueryBuilder $q) {
        $q->where('total', '>', 100);
    })
    ->get();
// "Usuarios que tienen al menos una orden mayor a $100"
```

Genera JOINs o subqueries internamente.

#### Nivel 4 — Scopes reutilizables (queries frecuentes encapsulados)

Los scopes encapsulan queries comunes dentro de la entidad para no repetirlos:

```php
class User extends Entity {
    #[PrimaryKey]
    public private(set) int $id;
    public string $name;
    public bool $active = false;
    public int $roleId;

    // Scopes: queries frecuentes como métodos estáticos
    public static function scopeActive(QueryBuilder $query): QueryBuilder {
        return $query->where('active', true);
    }

    public static function scopeAdmins(QueryBuilder $query): QueryBuilder {
        return $query->where('roleId', 1);
    }

    public static function scopeRecentlyCreated(QueryBuilder $query, int $days = 30): QueryBuilder {
        return $query->where('createdAt', '>=', date('Y-m-d', strtotime("-{$days} days")));
    }
}

// Uso encadenado — se leen como inglés
$activeAdmins = User::active()->admins()->orderBy('name')->get();
$newUsers = User::active()->recentlyCreated(7)->paginate(perPage: 10);
```

#### Nivel 5 — Raw SQL seguro (para el 100% restante)

Siempre habrá queries que el builder no puede expresar: reportes con múltiples JOINs, subqueries correlacionados, funciones de agregación complejas, CTEs (WITH), UNION. Para eso, la salida a SQL raw SIEMPRE con prepared statements:

```php
// Raw query directo — SQL libre pero valores SIEMPRE como parámetros PDO
$results = Connection::getInstance()->query(
    sql: "
        SELECT
            u.id,
            u.name,
            r.role AS role_name,
            COUNT(o.id) AS total_orders,
            SUM(o.total) AS total_spent
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN orders o ON o.user_id = u.id
        WHERE u.active = :active
            AND u.created_at >= :since
        GROUP BY u.id, u.name, r.role
        HAVING SUM(o.total) > :min_spent
        ORDER BY total_spent DESC
        LIMIT :limit OFFSET :offset
    ",
    params: [
        'active' => true,
        'since' => '2026-01-01',
        'min_spent' => 1000,
        'limit' => 25,
        'offset' => 0,
    ]
);

// Ejemplo completo: raw query dentro de un Service como endpoint REST
class Report extends Service {
    public function getSalesByMonth(): void {
        $since = $this->getQueryParams()['since'] ?? '2026-01-01';

        $results = Connection::getInstance()->query(
            sql: "
                SELECT
                    DATE_FORMAT(o.created_at, '%Y-%m') AS month,
                    COUNT(*) AS total_orders,
                    SUM(o.total) AS revenue
                FROM orders o
                WHERE o.created_at >= :since
                GROUP BY month
                ORDER BY month DESC
            ",
            params: ['since' => $since]
        );

        $this->success($results);
    }
}
// GET /api/v1/reports/sales-by-month?since=2026-01-01
```

#### Resumen de niveles

| Nivel | Método | Cuándo usar | Seguridad |
|-------|--------|-------------|-----------|
| 1 | Shortcuts (`find`, `all`, `where`) | CRUD estándar, el 60% de los casos | Prepared statements |
| 2 | Query Builder fluent | Filtros combinados, ordenamiento, paginación custom | Prepared statements |
| 3 | Eager loading (`with`, `whereHas`) | Queries con relaciones entre entidades | Prepared statements |
| 4 | Scopes | Queries frecuentes encapsulados para reutilizar | Prepared statements |
| 5 | Raw SQL | Reportes, agregaciones, SQL avanzado | Prepared statements (named params) |

**Principio:** el framework nunca te limita. Empieza simple, sube de nivel solo cuando lo necesitas.

### 4.5 Middleware Pipeline — PSR-15 Inspired

**Decisión: Pipeline de middleware con atributos para registro por recurso/método.**

```php
// Middleware global (se aplica a todo)
// En index.php o bootstrap
$api = new API('MyApp');
$api->middleware([
    CorsMiddleware::class,
    RateLimitMiddleware::class,
    AuthMiddleware::class,     // Reemplaza el sistema actual de authorizeOperation
    LoggingMiddleware::class,
]);

// Middleware por recurso (vía atributo)
#[Middleware(CacheMiddleware::class, ttl: 300)]
class Product extends APIDB { }

// Middleware por método
class User extends APIDB {
    #[Middleware(ThrottleMiddleware::class, maxAttempts: 5)]
    #[PublicResource]
    public function postLogin() { ... }
}
```

**Orden de ejecución:**
```
Request → CORS → Rate Limit → Auth → Logging → [Resource Handler] → Response
                                                      ↑
                                              Middleware por recurso/método
```

**¿Por qué middleware?**
- Separa concerns (auth, logging, CORS ya no están embebidos en API.php)
- Composable: activa/desactiva features sin tocar el core
- Testeable: cada middleware se testea aislado
- Estándar: PSR-15 es el patrón aceptado en PHP moderno

### 4.6 Hooks/Events en Entidades — Métodos de Ciclo de Vida

**Decisión: Hooks como métodos override en la entidad (simple y declarativo).**

```php
class User extends Entity {
    // Se ejecuta ANTES de insertar
    protected function beforeCreate(): void {
        $this->createdAt = new DateTime();
        $this->active = false;
    }

    // Se ejecuta DESPUÉS de insertar
    protected function afterCreate(): void {
        // Enviar email de bienvenida, logging, etc.
        Logger::info("User created: {$this->id}");
    }

    // Se ejecuta ANTES de actualizar
    protected function beforeUpdate(): void {
        $this->updatedAt = new DateTime();
    }

    // Se ejecuta ANTES de eliminar
    protected function beforeDelete(): bool {
        // Retornar false cancela la eliminación
        return $this->orders()->count() === 0;
    }

    // Se ejecuta DESPUÉS de eliminar
    protected function afterDelete(): void {
        // Limpiar datos relacionados, cache, etc.
    }
}
```

**¿Por qué hooks y no un sistema de eventos (event dispatcher)?**
- Más simple: override un método, sin registrar listeners
- Más fácil de leer: abres la entidad y ves toda la lógica
- Alineado con Active Record: la entidad sabe su ciclo de vida
- Futuro: si se necesita un event dispatcher, se agrega sin romper los hooks

### 4.7 CLI Tool — Sí, minimalista

**Decisión: CLI básico para productividad del desarrollador.**

```bash
# Crear una entidad nueva con boilerplate
php api-builder make:entity User

# Crear un servicio custom
php api-builder make:service Payment

# Generar OpenAPI spec desde las entidades
php api-builder docs:generate

# Verificar configuración y conexión
php api-builder health:check

# Generar SQL de referencia desde entidades (futuro)
php api-builder schema:dump
```

**¿Por qué incluirlo?**
- Reduce errores: genera código con la estructura correcta
- Acelera desarrollo: no hay que recordar la estructura exacta
- Genera docs: OpenAPI spec automático desde atributos
- Es opcional: la librería funciona sin el CLI

### 4.8 Sistema de Respuesta — Opción A (Métodos Heredados + Auto-wrap)

**Decisión:** Los Services y APIDB heredan métodos de respuesta de una clase base `Resource`. Esto reemplaza las funciones globales `success()` y `error()` de v1 con métodos de instancia `$this->success()`.

Adicionalmente, si un método retorna un valor sin llamar ningún método de respuesta, el framework lo envuelve automáticamente en `$this->success()`.

#### Herencia de Resource

```php
// Clase base abstracta del framework — NO la crea el developer
abstract class Resource {
    // Métodos de respuesta (protegidos, disponibles en Services y APIDB)
    protected function success(mixed $data, int $code = 200): void { ... }
    protected function created(mixed $data): void { ... }         // 201 + header Location
    protected function noContent(): void { ... }                   // 204
    protected function error(string $msg, int $code = 400): void { ... }

    // Acceso al request
    protected function getInput(): object { ... }                  // JSON body
    protected function getQueryParams(): array { ... }             // Query string params
}

// Jerarquía:
// Resource (base)
//   ├── Service (para servicios puros sin DB)
//   └── APIDB (para CRUD con Entity)
//         └── Los servicios híbridos del developer extienden APIDB
```

#### v1 vs v2

```php
// ❌ v1 — funciones globales
success($result);                  // Función suelta en scope global
error('Not found', 404);           // Función suelta que lanza excepción

// ✅ v2 — métodos de instancia
$this->success($result);           // Método heredado de Resource
$this->created($user);             // 201 con header Location
$this->noContent();                // 204 para DELETE
$this->error('Not found', 404);    // Lanza excepción tipada
```

#### Uso en cada tipo de recurso

```php
// Service puro — control explícito
class Payment extends Service {
    public function post(): void {
        $input = $this->getInput();
        $charge = $this->callStripe($input);
        $this->created($charge);  // 201
    }

    private function callStripe(object $data): object { ... }
}

// Servicio híbrido — CRUD heredado + operaciones custom
class User extends APIDB {
    // get(), post(), put(), patch(), delete() vienen heredados de APIDB
    // APIDB usa internamente $this->success(), $this->created(), etc.

    #[PublicResource]
    public function postLogin(): void {
        $input = $this->getInput();
        $user = $this->validateCredentials($input);
        $token = Auth::createAccessToken($user);
        $this->success(['accessToken' => $token, 'tokenType' => 'Bearer']);
    }

    #[Scope('admin')]
    public function postDeactivate(): void {
        $user = User::find($this->priId);
        $user->active = false;
        $user->save();
        $this->success(['message' => 'User deactivated']);
    }

    private function validateCredentials(object $input): object { ... }
}

// Auto-wrap: si el método retorna algo sin llamar success(), el framework envuelve
class Health extends Service {
    #[PublicResource]
    public function get(): array {
        return ['status' => 'ok', 'timestamp' => time()];
        // El framework detecta el return y hace $this->success() automáticamente
    }
}
```

#### APIDB internamente usa los métodos así

```php
// Dentro del framework — el developer NO escribe esto, es automático
class APIDB extends Resource {
    public function get(): void {
        if ($this->priId) {
            $entity = $this->getEmptyEntity();
            $record = $entity::find($this->priId);
            if (!$record) $this->error('Resource not found', 404);
            $this->success($record);               // 200
        } else {
            $records = $this->getEmptyEntity()::all();
            $this->success($records);               // 200 con paginación
        }
    }

    public function post(): void {
        $entity = $this->getFilledEntity();
        $entity->save();
        $this->created($entity);                    // 201
    }

    public function delete(): void {
        $entity = $this->getEmptyEntity()::find($this->priId);
        if (!$entity) $this->error('Resource not found', 404);
        $entity->delete();
        $this->noContent();                          // 204
    }
}
```

#### Formato de las respuestas

Todos los métodos de respuesta generan JSON con el formato estándar definido:

##### Response exitoso (colección)

#### Response exitoso (colección)
```json
{
    "data": [
        { "id": 1, "name": "John", "email": "john@example.com" },
        { "id": 2, "name": "Jane", "email": "jane@example.com" }
    ],
    "meta": {
        "currentPage": 1,
        "perPage": 25,
        "total": 150,
        "totalPages": 6
    },
    "links": {
        "self":  "/api/v1/users?page=1&per_page=25",
        "first": "/api/v1/users?page=1&per_page=25",
        "prev":  null,
        "next":  "/api/v1/users?page=2&per_page=25",
        "last":  "/api/v1/users?page=6&per_page=25"
    }
}
```

#### Response exitoso (recurso individual)
```json
{
    "data": {
        "id": 1,
        "name": "John",
        "email": "john@example.com",
        "role": { "id": 1, "name": "Admin" },
        "orders": [
            { "id": 10, "total": 99.90 }
        ]
    }
}
```

#### Response de error (RFC 7807)
```json
{
    "type": "validation-error",
    "title": "Validation Error",
    "status": 422,
    "detail": "One or more fields failed validation.",
    "errors": {
        "email": ["Must be a valid email address", "Already exists"],
        "name": ["Is required"]
    }
}
```

#### Response de creación (201)
```json
{
    "data": { "id": 42, "name": "New User", "email": "new@example.com" }
}
```
Headers: `Location: /api/v1/users/42`

#### Response de eliminación (204)
Sin body, solo status code 204.

### 4.9 Sistema de Validación por Atributos

**Decisión: Atributos PHP nativos para declarar reglas de validación.**

```php
// Atributos disponibles en el framework
#[Required]                    // Campo obligatorio
#[Email]                       // Formato email válido
#[Unique]                      // Único en la tabla
#[MaxLength(100)]              // Longitud máxima
#[MinLength(2)]                // Longitud mínima
#[Min(0)]                      // Valor mínimo numérico
#[Max(999)]                    // Valor máximo numérico
#[Pattern('/^[a-z]+$/')]       // Regex personalizado
#[In(['active', 'inactive'])] // Valor en lista permitida
#[Hidden]                      // No incluir en responses
#[ReadOnly]                    // No modificable después de crear
#[Default('pending')]          // Valor por defecto
```

La validación se ejecuta automáticamente en POST, PUT y PATCH antes de persistir. Los errores se retornan en formato RFC 7807 con status 422.

### 4.10 Sistema Dual: Services vs Entities

**Concepto clave:** Un recurso en la URL puede resolverse a dos tipos de handler completamente distintos.

#### Tipo 1: Entity Resource (con DB)
Recursos que mapean a una tabla de base de datos con CRUD automático.

```
GET /api/v1/users  →  Descubre Entity User  →  APIDB maneja CRUD automático
```

```php
// entities/User.php
#[Table('users')]
class User extends Entity {
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(50)]
    public string $name;

    #[Required, Email, Unique]
    public string $email;
}
// Con solo esto, ya tienes: GET, POST, PUT, PATCH, DELETE con validación
```

#### Tipo 2: Service Resource (agnóstico a DB)
Recursos que NO están atados a ninguna tabla. Pueden hacer cualquier cosa: llamar APIs externas, procesar datos, orquestar operaciones, servir como gateway, o incluso interactuar con la DB de forma custom.

```
GET /api/v1/health         →  Descubre Service Health       →  Health.get()
POST /api/v1/payments      →  Descubre Service Payment      →  Payment.post()
GET /api/v1/weather/bogota →  Descubre Service Weather       →  Weather.get('bogota')
POST /api/v1/notifications →  Descubre Service Notification  →  Notification.post()
```

```php
// services/Health.php — Sin DB, solo lógica
#[PublicResource]
class Health {
    public function get(): void {
        success(['status' => 'ok', 'timestamp' => time()]);
    }

    public function getDatabase(): void {
        // Verifica conexión a DB opcionalmente
        $db = new Connection();
        success(['database' => 'connected']);
    }
}

// services/Payment.php — Llama API externa, puede o no usar DB
class Payment {
    public function post(): void {
        $input = getInput();
        // Llamar a Stripe, PayPal, MercadoPago...
        $response = $this->callExternalApi('https://api.stripe.com/charges', $input);
        // Opcionalmente guardar en DB
        $log = new PaymentLog();
        $log->amount = $input->amount;
        $log->save();
        success($response, SC_SUCCESS_CREATED);
    }
}

// services/Weather.php — 100% proxy a API externa
#[Route('weather')]
class Weather {
    public function get(): void {
        $city = $this->priId; // El "ID" es en realidad el nombre de la ciudad
        $data = file_get_contents("https://api.weather.com/{$city}");
        success(json_decode($data));
    }
}
```

#### Tipo 3: Híbrido (Service que extiende APIDB)
Tu patrón actual para recursos que tienen CRUD + operaciones custom.

```php
// services/User.php — CRUD automático + operaciones custom
class User extends APIDB {
    // CRUD viene heredado (get, post, put, patch, delete)

    // Operaciones custom adicionales
    #[PublicResource]
    public function postLogin(): void { /* auth custom */ }

    public function postActivate(): void { /* activación custom */ }

    public function getStats(): void { /* estadísticas, sin CRUD */ }
}
```

#### Flujo de Resolución del Router

```
URL recibida: /api/v1/{resource}
    │
    ▼
¿Existe clase Service para {resource}?
    │
    ├── SÍ → ¿Extiende APIDB?
    │         ├── SÍ → Tipo 3 (Híbrido): CRUD + custom methods
    │         └── NO → Tipo 2 (Service puro): solo custom methods
    │
    └── NO → ¿Existe clase Entity para {resource}?
              ├── SÍ → Tipo 1 (Entity): CRUD automático vía APIDB
              └── NO → Error 404: Resource not found
```

**Esto ya funciona en v1** (el `getClass()` en utils.php hace este descubrimiento). En v2 lo formalizamos con mejor documentación, tipado e interfaz.

### 4.11 Capa de Base de Datos — PDO Multi-Driver

**Decisión: PDO como base, con abstracción de drivers.**

PDO es la opción correcta para esta librería porque soporta múltiples motores de DB con la misma API, tiene prepared statements nativos para seguridad, manejo de excepciones integrado, y es el estándar de facto en PHP (65% de preferencia según encuesta JetBrains 2025). MySQLi es ~15% más rápido en benchmarks, pero solo funciona con MySQL, lo cual anula tu objetivo de soportar múltiples bases de datos.

#### Arquitectura de Drivers

```
┌─────────────────────────────────┐
│      QueryBuilder (fluent)       │
│  Genera SQL genérico + params    │
└──────────────┬──────────────────┘
               │
┌──────────────▼──────────────────┐
│      Connection (singleton)      │
│  Maneja conexión PDO, txn,       │
│  delega al driver correcto       │
└──────────────┬──────────────────┘
               │
     ┌─────────┼─────────┐
     ▼         ▼         ▼
┌─────────┐ ┌──────┐ ┌────────┐
│  MySQL   │ │ Pgsql│ │ SQLite │
│  Driver  │ │Driver│ │ Driver │
└─────────┘ └──────┘ └────────┘
```

#### DriverInterface — Lo que cada driver implementa

```php
interface DriverInterface {
    // Construcción del DSN para PDO
    public function getDsn(): string;

    // Diferencias de sintaxis SQL entre motores
    public function getAutoIncrementSyntax(): string;
    // MySQL: "AUTO_INCREMENT"  |  PostgreSQL: "SERIAL"  |  SQLite: "AUTOINCREMENT"

    public function getBooleanType(): string;
    // MySQL: "TINYINT(1)"  |  PostgreSQL: "BOOLEAN"  |  SQLite: "INTEGER"

    public function getTimestampDefault(): string;
    // MySQL: "CURRENT_TIMESTAMP"  |  PostgreSQL: "CURRENT_TIMESTAMP"  |  SQLite: "CURRENT_TIMESTAMP"

    public function getLimitOffsetSyntax(int $limit, int $offset): string;
    // Igual en los tres: "LIMIT {limit} OFFSET {offset}"

    public function getUpsertSyntax(string $table, array $fields, string $conflictKey): string;
    // MySQL: "ON DUPLICATE KEY UPDATE"  |  PostgreSQL: "ON CONFLICT DO UPDATE"  |  SQLite: "ON CONFLICT REPLACE"

    public function quoteIdentifier(string $identifier): string;
    // MySQL: "`field`"  |  PostgreSQL: "\"field\""  |  SQLite: "\"field\""

    public function supportsReturning(): bool;
    // MySQL: false  |  PostgreSQL: true (INSERT ... RETURNING id)  |  SQLite: false
}
```

#### Configuración por .env

```env
# MySQL (default, compatible con SiteGround)
DB_DRIVER=mysql
DB_HOST=localhost
DB_PORT=3306
DB_NAME=my_api
DB_USERNAME=root
DB_PASSWORD=secret
DB_CHARSET=utf8mb4

# PostgreSQL
DB_DRIVER=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_NAME=my_api
DB_USERNAME=postgres
DB_PASSWORD=secret

# SQLite (ideal para testing y desarrollo)
DB_DRIVER=sqlite
DB_PATH=./database.sqlite
```

#### Connection — Singleton con Transacciones

```php
class Connection {
    private static ?Connection $instance = null;
    private PDO $pdo;
    private DriverInterface $driver;

    public static function getInstance(): self { /* singleton */ }

    // Transacciones
    public function beginTransaction(): void { $this->pdo->beginTransaction(); }
    public function commit(): void { $this->pdo->commit(); }
    public function rollBack(): void { $this->pdo->rollBack(); }

    public function transaction(callable $callback): mixed {
        $this->beginTransaction();
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    // Query execution — SIEMPRE prepared statements
    public function query(string $sql, array $params = []): array { /* ... */ }
    public function execute(string $sql, array $params = []): bool { /* ... */ }
    public function lastInsertId(): string|false { /* ... */ }
}
```

#### Uso de Transacciones en Servicios

```php
class Order extends APIDB {
    public function post(): void {
        Connection::getInstance()->transaction(function ($db) {
            $order = $this->getFilledEntity();
            $order->save();

            foreach ($order->items as $item) {
                $item->orderId = $order->id;
                $item->save();
            }

            // Si algo falla, todo se revierte automáticamente
        });
    }
}
```

### 4.12 Arquitectura de Componentes v2

```
┌──────────────────────────────────────────────────────────┐
│                      index.php                            │
│               $api = new API('MyApp')                     │
│               $api->middleware([...])                      │
│               $api->run()                                 │
└────────────────────────┬─────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────┐
│                Middleware Pipeline                         │
│   CORS → RateLimit → Auth → Logging → Handler             │
└────────────────────────┬─────────────────────────────────┘
                         │
┌────────────────────────▼─────────────────────────────────┐
│                      Router                               │
│   URL parsing → Class discovery → Method resolution       │
└────┬───────────────────┬───────────────────┬─────────────┘
     │                   │                   │
     ▼                   ▼                   ▼
┌──────────┐     ┌──────────────┐    ┌─────────────────┐
│ Service   │     │ Service +    │    │ Entity (APIDB)   │
│ (puro)    │     │ APIDB        │    │ (auto-CRUD)      │
│           │     │ (híbrido)    │    │                  │
│ Sin DB    │     │ CRUD +       │    │ CRUD automático  │
│ APIs ext. │     │ custom ops   │    │ Validación       │
│ Gateway   │     │ postLogin    │    │ Hooks            │
│ Proxy     │     │ postActivate │    │                  │
└──────────┘     └──────┬───────┘    └────────┬─────────┘
                        │                      │
            ┌───────────▼──────────────────────▼──────────┐
            │         Entity (Active Record)                │
            │   Typed properties + PHP Attributes           │
            │   #[Required] #[Email] #[Hidden] #[BelongsTo]│
            │   Property hooks, lifecycle hooks              │
            │                                               │
            │   ⚡ Opcional: los Services puros NO usan     │
            │      esta capa si no necesitan DB              │
            └────────────────────┬─────────────────────────┘
                                 │
            ┌────────────────────▼─────────────────────────┐
            │           Query Builder (fluent)               │
            │   ->where()->orderBy()->select()->paginate()  │
            │   100% prepared statements (PDO params)        │
            └────────────────────┬─────────────────────────┘
                                 │
            ┌────────────────────▼─────────────────────────┐
            │            Connection (PDO)                    │
            │   Singleton, transacciones, driver detection   │
            └──────┬─────────────┬─────────────┬───────────┘
                   ▼             ▼             ▼
              ┌─────────┐  ┌─────────┐  ┌──────────┐
              │  MySQL   │  │  Pgsql  │  │  SQLite  │
              │  Driver  │  │  Driver │  │  Driver  │
              └─────────┘  └─────────┘  └──────────┘
```

### 4.13 Estructura de Archivos del Paquete (v2)

#### Namespace Confirmado: `Coagus\PhpApiBuilder\`

**Breaking change respecto a v1.** El namespace cambia de `ApiBuilder\` a `Coagus\PhpApiBuilder\` siguiendo el estándar de Packagist (vendor/package).

| Versión | Namespace | Autoload PSR-4 |
|---------|-----------|----------------|
| v1.x | `ApiBuilder\` | `"ApiBuilder\\": "src/"` |
| v2.0.0 | `Coagus\PhpApiBuilder\` | `"Coagus\\PhpApiBuilder\\": "src/"` |

**composer.json v2:**

```json
{
    "name": "coagus/php-api-builder",
    "autoload": {
        "psr-4": {
            "Coagus\\PhpApiBuilder\\": "src/"
        }
    }
}
```

**Imports en el código de los usuarios de la librería:**

```php
// v1 (antes)
use ApiBuilder\API;
use ApiBuilder\ORM\Entity;

// v2 (ahora)
use Coagus\PhpApiBuilder\API;
use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\Table;
use Coagus\PhpApiBuilder\Validation\Attributes\Required;
```

#### Estructura del Paquete

```
src/
├── API.php                         # Entry point, orquestador
├── Router.php                      # Parseo de URL, discovery, despacho
│
├── Auth/
│   ├── Auth.php                    # JWT manager (access + refresh tokens)
│   ├── ApiKeyAuth.php              # API key auth (servicio-a-servicio)
│   ├── RefreshTokenStore.php       # Almacenamiento y rotación de refresh tokens
│   └── ScopeValidator.php          # Validación de scopes/permisos
│
├── Http/
│   ├── Request.php                 # Objeto request (PSR-7 inspired)
│   ├── Response.php                # Objeto response estándar
│   └── Middleware/
│       ├── MiddlewareInterface.php
│       ├── MiddlewarePipeline.php  # Ejecutor de la cadena
│       ├── CorsMiddleware.php
│       ├── RateLimitMiddleware.php
│       ├── AuthMiddleware.php
│       └── LoggingMiddleware.php
│
├── ORM/
│   ├── Entity.php                  # Base Active Record
│   ├── QueryBuilder.php            # Query builder fluent (prepared stmts)
│   ├── Connection.php              # Singleton PDO, transacciones
│   └── Drivers/
│       ├── DriverInterface.php     # Contrato para drivers
│       ├── MySqlDriver.php         # MySQL / MariaDB
│       ├── PostgresDriver.php      # PostgreSQL
│       └── SqliteDriver.php        # SQLite
│
├── Validation/
│   ├── Validator.php               # Motor de validación por reflection
│   └── Attributes/
│       ├── Required.php
│       ├── Email.php
│       ├── Unique.php
│       ├── MaxLength.php
│       ├── MinLength.php
│       ├── Min.php
│       ├── Max.php
│       ├── Pattern.php
│       ├── In.php
│       ├── Hidden.php
│       ├── ReadOnly.php
│       └── Default.php
│
├── Attributes/
│   ├── PublicResource.php          # Sin auth (clase o método)
│   ├── Route.php                   # Ruta custom para Services
│   ├── Table.php                   # Nombre de tabla para Entities
│   ├── PrimaryKey.php
│   ├── SoftDelete.php
│   ├── Middleware.php              # Middleware por recurso/método
│   ├── BelongsTo.php              # Relación N:1
│   ├── HasMany.php                # Relación 1:N
│   └── BelongsToMany.php          # Relación N:M
│
├── Resource/
│   ├── Resource.php                # Base abstracta: success(), created(), error(), getInput()
│   ├── APIDB.php                   # CRUD handler para Entity resources (extends Resource)
│   ├── Service.php                 # Base class para Services puros sin DB (extends Resource)
│   └── ResourceHooks.php          # Trait con hooks de ciclo de vida
│
└── Helpers/
    ├── ApiResponse.php             # Response builder estándar
    ├── ErrorHandler.php            # Errores RFC 7807
    └── Utils.php                   # Naming, discovery, utilidades
```

#### Estructura del Proyecto del Desarrollador (quien usa la librería)

```
mi-api/
├── composer.json                   # require: "coagus/php-api-builder"
├── .env                            # Configuración (DB_DRIVER, JWT_KEY, etc.)
├── .htaccess                       # Rewrite rules para routing
├── index.php                       # Entry point
│
├── services/                       # ← Services (agnósticos a DB)
│   ├── Health.php                  # GET /api/v1/health
│   ├── Payment.php                 # POST /api/v1/payments (API externa)
│   ├── Weather.php                 # GET /api/v1/weather/{city} (proxy)
│   ├── Notification.php            # POST /api/v1/notifications
│   └── User.php                    # Híbrido: CRUD + postLogin, postActivate
│
├── entities/                       # ← Entities (mapeadas a tablas DB)
│   ├── User.php                    # → tabla users
│   ├── Role.php                    # → tabla roles
│   ├── Order.php                   # → tabla orders
│   └── Product.php                 # → tabla products
│
└── log/                            # Logs automáticos
```

### 4.14 Estrategia de Testing

#### Filosofía

> "Si no tiene test, no funciona" — pero pragmático: testear lo que importa, no inflar cobertura con tests vacíos.

#### Framework: Pest (sobre PHPUnit)

Pest v4 es el estándar moderno para testing en PHP 2025-2026. Está construido sobre PHPUnit 12 (requiere PHP 8.3+), ofrece sintaxis expresiva y reduce el boilerplate sin sacrificar poder.

```php
// Pest — limpio, expresivo
test('user entity saves correctly', function () {
    $user = new User();
    $user->name = 'Carlos';
    $user->email = 'carlos@test.com';
    $user->password = 'secure123';
    $user->save();

    expect($user->id)->toBeInt()
        ->and($user->name)->toBe('Carlos')
        ->and($user->email)->toBe('carlos@test.com')
        ->and($user->password)->not->toBe('secure123'); // hashed
});

// Agrupación lógica
describe('QueryBuilder', function () {
    test('builds simple SELECT', function () {
        $sql = User::query()->where('active', true)->toSql();
        expect($sql)->toContain('WHERE active = ?');
    });

    test('supports eager loading', function () {
        $users = User::query()->with('orders')->get();
        expect($users[0]->orders)->toBeArray();
    });
});
```

**Proporción recomendada:** 80-90% Pest + 10-20% PHPUnit (para tests que necesiten traits complejos o data providers avanzados).

#### Estructura de Tests

```
tests/
├── Unit/                           # Tests aislados, sin DB
│   ├── Validation/
│   │   ├── RequiredTest.php        # Atributo #[Required]
│   │   ├── EmailTest.php           # Atributo #[Email]
│   │   ├── MaxLengthTest.php       # Atributo #[MaxLength]
│   │   └── ValidatorTest.php       # Orquestador de validación
│   ├── Http/
│   │   ├── RequestTest.php         # Parsing de request
│   │   └── ResponseTest.php        # Formato de respuestas
│   ├── ORM/
│   │   ├── QueryBuilderTest.php    # Construcción de SQL (sin ejecutar)
│   │   └── EntityMetadataTest.php  # Lectura de atributos PHP
│   ├── Auth/
│   │   ├── JwtTokenTest.php        # Generación y validación JWT
│   │   └── ScopeValidatorTest.php  # Verificación de scopes
│   └── Helpers/
│       └── UtilsTest.php           # Funciones utilitarias
│
├── Integration/                    # Tests con DB real (SQLite in-memory)
│   ├── ORM/
│   │   ├── EntityCrudTest.php      # save(), delete(), getById()
│   │   ├── RelationsTest.php       # BelongsTo, HasMany, BelongsToMany
│   │   ├── SoftDeleteTest.php      # Soft delete y restore
│   │   ├── EagerLoadingTest.php    # N+1 prevention
│   │   └── TransactionTest.php     # Commit y rollback
│   ├── Middleware/
│   │   └── PipelineTest.php        # Cadena de middlewares
│   └── APIDB/
│       ├── GetTest.php             # GET con paginación, filtros, sorting
│       ├── PostTest.php            # POST con validación
│       ├── PutPatchTest.php        # PUT vs PATCH behavior
│       └── DeleteTest.php          # DELETE y soft delete
│
├── Feature/                        # Tests end-to-end del API
│   ├── AuthFlowTest.php            # Login → token → refresh → revoke
│   ├── CrudFlowTest.php            # CRUD completo de un recurso
│   └── ErrorHandlingTest.php       # Errores RFC 7807
│
├── Pest.php                        # Configuración global de Pest
├── TestCase.php                    # Clase base con helpers
└── Fixtures/
    ├── Entities/                   # Entidades de prueba
    │   ├── TestUser.php
    │   └── TestOrder.php
    └── migrations.sql              # Schema para SQLite in-memory
```

#### Base de Datos para Tests: SQLite In-Memory

```php
// tests/TestCase.php
class TestCase {
    protected static Connection $db;

    public static function setUpDatabase(): void
    {
        Connection::configure([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);
        self::$db = Connection::getInstance();
        self::$db->exec(file_get_contents(__DIR__ . '/Fixtures/migrations.sql'));
    }
}

// tests/Pest.php
uses(TestCase::class)->in('Integration', 'Feature');

beforeEach(function () {
    TestCase::setUpDatabase(); // DB fresca en cada test
});
```

**¿Por qué SQLite in-memory?** Rapidez extrema (sin I/O de disco), aislamiento total (cada test tiene DB limpia), no requiere servidor externo. Los drivers PDO aseguran que el SQL generado sea compatible.

#### Qué Testear por Capa

| Capa | Tipo de Test | Qué verificar |
|------|-------------|---------------|
| Entity | Unit + Integration | Atributos PHP leídos correctamente, property hooks ejecutan (trim, hash), CRUD funciona, relaciones cargan |
| QueryBuilder | Unit | SQL generado es correcto, parámetros bind son seguros, cada nivel (1-5) genera SQL válido |
| Validation | Unit | Cada atributo valida correctamente, mensajes de error son claros, combinaciones de atributos funcionan |
| APIDB | Integration | CRUD automático respeta entity, paginación, filtros, sorting, soft delete |
| Auth | Unit + Integration | JWT genera/valida, refresh token rota, scopes se verifican, tokens expirados se rechazan |
| Middleware | Integration | Pipeline ejecuta en orden, middleware puede cortar la cadena, errores se propagan |
| Response | Unit | Formato JSON correcto, status codes correctos, RFC 7807 en errores |
| Router | Unit | URLs se parsean a recursos, métodos se mapean a operaciones, rutas custom funcionan |

#### Coverage Targets

```
Cobertura mínima global:    80%
Cobertura crítica (ORM):    90%+
Cobertura Auth:             95%+
Cobertura Validation:       95%+
```

#### CI/CD con GitHub Actions

```yaml
name: Tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.3', '8.4']

    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pdo_sqlite, pdo_mysql, mbstring
          coverage: xdebug

      - run: composer install --prefer-dist --no-progress
      - run: vendor/bin/pest --coverage --min=80
      - run: vendor/bin/pest --type-coverage --min=95  # Pest type coverage
```

**Matrix builds:** Se testea en PHP 8.3 y 8.4 para garantizar compatibilidad. Type coverage de Pest verifica que los tipos estén bien definidos en todo el código.

#### Comando CLI para Tests

```bash
# Desde el CLI de la librería
php api test                    # Ejecutar todos los tests
php api test --unit             # Solo unit tests
php api test --integration      # Solo integration tests
php api test --coverage         # Con reporte de cobertura
```

### 4.15 Logging y Trazabilidad de Errores

#### Filosofía

> "Log solo lo que importa: errores. Pero cuando logues, logea TODO el contexto."

No se logean todas las peticiones (eso es trabajo de un access log de Apache/Nginx). La librería logea **solo errores**, pero con un nivel de detalle que permita reconstruir exactamente qué pasó.

#### Request ID — Trazabilidad End-to-End

Cada petición que entra al API recibe un **Request ID único** en el punto de entrada. Este ID se propaga por TODAS las capas del sistema y aparece en cada log entry y en cada respuesta de error al cliente.

```php
// En API.php — punto de entrada
$requestId = bin2hex(random_bytes(8)); // Ej: "a3f4b2c1e9d80716"

// Se inyecta en el contexto global
RequestContext::setId($requestId);

// Aparece en TODA respuesta de error al cliente
{
    "type": "https://api.example.com/errors/validation",
    "title": "Validation Error",
    "status": 422,
    "detail": "The field 'email' is not a valid email address",
    "requestId": "a3f4b2c1e9d80716"  // ← para soporte técnico
}
```

**¿Para qué?** El usuario reporta un error con el `requestId`, el desarrollador busca ese ID en los logs y ve TODO: desde qué llegó en la petición, qué queries se ejecutaron, y dónde exactamente falló.

#### Stack de Logging: PSR-3 + Monolog

Se mantiene Monolog (ya en v1) pero con configuración estructurada:

```php
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\IntrospectionProcessor;

class LogFactory
{
    public static function create(string $channel = 'api'): Logger
    {
        $logger = new Logger($channel);

        // Handler: archivos rotativos, solo errores
        $handler = new RotatingFileHandler(
            filename: 'log/api-error.log',
            maxFiles: 30,               // 30 días de retención
            level: Logger::ERROR,       // ← SOLO errores
        );

        // Formato JSON estructurado
        $handler->setFormatter(new JsonFormatter());

        // Procesador: agrega file/line/class automáticamente
        $logger->pushProcessor(new IntrospectionProcessor());

        // Procesador custom: agrega request context
        $logger->pushProcessor(function (array $record): array {
            $record['extra']['requestId'] = RequestContext::getId();
            $record['extra']['method'] = $_SERVER['REQUEST_METHOD'] ?? 'CLI';
            $record['extra']['uri'] = $_SERVER['REQUEST_URI'] ?? '';
            $record['extra']['userId'] = RequestContext::getUserId();
            $record['extra']['ip'] = $_SERVER['REMOTE_ADDR'] ?? '';
            return $record;
        });

        $logger->pushHandler($handler);
        return $logger;
    }
}
```

#### Formato del Log Entry (JSON Estructurado)

Cada entrada de error en el log contiene contexto completo:

```json
{
    "message": "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'carlos@test.com' for key 'users.email_unique'",
    "context": {
        "entity": "User",
        "operation": "save",
        "input": {
            "name": "Carlos",
            "email": "carlos@test.com",
            "password": "***PROTECTED***"
        },
        "query": "INSERT INTO users (name, email, password) VALUES (?, ?, ?)",
        "exception": {
            "class": "PDOException",
            "code": 23000,
            "file": "/vendor/coagus/php-api-builder/src/ORM/Connection.php",
            "line": 142,
            "trace": [
                "#0 Connection->execute() at Connection.php:142",
                "#1 Entity->save() at Entity.php:89",
                "#2 APIDB->post() at APIDB.php:54",
                "#3 API->run() at API.php:78",
                "#4 index.php:12"
            ]
        }
    },
    "level": 400,
    "level_name": "ERROR",
    "channel": "api",
    "datetime": "2026-04-03T14:32:15.234Z",
    "extra": {
        "requestId": "a3f4b2c1e9d80716",
        "method": "POST",
        "uri": "/api/v1/users",
        "userId": null,
        "ip": "192.168.1.100",
        "file": "Connection.php",
        "line": 142,
        "class": "Connection"
    }
}
```

#### Protección de Datos Sensibles

**NUNCA** se logean datos sensibles. El sistema aplica sanitización automática:

```php
class SensitiveDataFilter
{
    private const PROTECTED_FIELDS = [
        'password', 'token', 'secret', 'apiKey', 'api_key',
        'authorization', 'credit_card', 'ssn', 'cvv',
    ];

    public static function sanitize(array $data): array
    {
        foreach ($data as $key => &$value) {
            if (is_array($value)) {
                $value = self::sanitize($value);
            } elseif (self::isSensitive($key)) {
                $value = '***PROTECTED***';
            }
        }
        return $data;
    }

    private static function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);
        foreach (self::PROTECTED_FIELDS as $field) {
            if (str_contains($normalized, $field)) {
                return true;
            }
        }
        return false;
    }
}
```

#### Flujo de Error — De Petición a Log

```
Cliente envía POST /api/v1/users
        │
        ▼
   ┌─────────────┐
   │  API.php     │ ← RequestContext::setId(random_bytes)
   │  Entry Point │
   └──────┬──────┘
          │
          ▼
   ┌─────────────┐
   │  Middleware  │ ← try/catch envuelve todo el pipeline
   │  Pipeline    │
   └──────┬──────┘
          │
          ▼
   ┌─────────────┐
   │  APIDB       │ ← Ejecuta post() → Entity->save()
   │  post()      │
   └──────┬──────┘
          │
          ▼
   ┌─────────────┐
   │  Entity      │ ← Valida, ejecuta hooks, llama a Connection
   │  save()      │
   └──────┬──────┘
          │
          ▼
   ┌─────────────┐
   │  Connection  │ ← PDO lanza PDOException
   │  execute()   │    (ej: duplicate email)
   └──────┬──────┘
          │
          ▼ EXCEPCIÓN SE PROPAGA
          │
   ┌─────────────┐
   │  ErrorHandler│ ← Captura la excepción
   │  (global)    │    1. Logea con CONTEXTO COMPLETO
   │              │    2. Responde al cliente con RFC 7807
   │              │    3. Incluye requestId en la respuesta
   └─────────────┘
```

#### ErrorHandler — Punto Centralizado

```php
class ErrorHandler
{
    private Logger $logger;

    public function handle(\Throwable $e): void
    {
        // 1. Construir contexto completo
        $context = [
            'exception' => [
                'class' => get_class($e),
                'code' => $e->getCode(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $this->formatTrace($e->getTrace()),
            ],
        ];

        // 2. Agregar contexto de la operación actual
        if (RequestContext::hasOperation()) {
            $context['entity'] = RequestContext::getEntity();
            $context['operation'] = RequestContext::getOperation();
            $context['input'] = SensitiveDataFilter::sanitize(
                RequestContext::getInput()
            );
        }

        // 3. Logear
        $this->logger->error($e->getMessage(), $context);

        // 4. Responder al cliente (sin exponer internals en producción)
        $response = [
            'type' => $this->mapExceptionToType($e),
            'title' => $this->mapExceptionToTitle($e),
            'status' => $this->mapExceptionToStatus($e),
            'requestId' => RequestContext::getId(),
        ];

        if (env('APP_DEBUG', false)) {
            $response['detail'] = $e->getMessage();
            $response['trace'] = $context['exception']['trace'];
        } else {
            $response['detail'] = $this->getSafeMessage($e);
        }

        http_response_code($response['status']);
        header('Content-Type: application/problem+json');
        echo json_encode($response);
    }
}
```

#### Niveles de Detalle por Entorno

| Entorno | Log Level | Response Detail | Stack Trace en Response |
|---------|-----------|-----------------|------------------------|
| development | ERROR + WARNING | Mensaje completo + query | Sí |
| staging | ERROR | Mensaje completo | No |
| production | ERROR | Mensaje genérico seguro | No |

En producción, el cliente recibe un mensaje genérico ("An internal error occurred") con el `requestId`. Los detalles completos solo existen en el archivo de log del servidor.

#### Configuración

```php
// .env
LOG_LEVEL=error          # error | warning | debug (default: error)
LOG_PATH=log/            # Directorio de logs
LOG_MAX_FILES=30         # Días de retención
APP_DEBUG=false          # true solo en development
```

### 4.16 Seguridad — Headers y Hardening (OWASP)

#### Filosofía

> "Seguro por defecto. El desarrollador no debería tener que pensar en headers de seguridad."

La librería incluye un middleware de seguridad **habilitado por defecto** que inyecta headers de protección siguiendo las recomendaciones del [OWASP Secure Headers Project](https://owasp.org/www-project-secure-headers/) y el [REST Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/REST_Security_Cheat_Sheet.html).

#### Headers Automáticos

```php
// SecurityHeadersMiddleware — incluido por defecto en el pipeline
class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        $response = $next($request);

        // Prevenir MIME-type sniffing
        $response->header('X-Content-Type-Options', 'nosniff');

        // Prevenir clickjacking (APIs no deberían embeberse en iframes)
        $response->header('X-Frame-Options', 'DENY');

        // Deshabilitar XSS filter del browser (obsoleto pero no daña)
        $response->header('X-XSS-Protection', '0');

        // Forzar HTTPS (solo si APP_HTTPS=true)
        if (env('APP_HTTPS', false)) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        // No cachear respuestas con datos sensibles por defecto
        $response->header('Cache-Control', 'no-store');

        // Content-Type siempre explícito
        $response->header('Content-Type', 'application/json; charset=utf-8');

        // Ocultar tecnología del servidor
        $response->header('X-Powered-By', ''); // eliminar header de PHP

        // Referrer policy
        $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions Policy (deshabilitar APIs del browser innecesarias)
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        return $response;
    }
}
```

#### CORS Configurado por Defecto

CORS ya existía en v1 pero se mejora con configuración vía `.env`:

```php
// .env
CORS_ALLOWED_ORIGINS=https://myapp.com,https://admin.myapp.com
CORS_ALLOWED_METHODS=GET,POST,PUT,PATCH,DELETE,OPTIONS
CORS_ALLOWED_HEADERS=Content-Type,Authorization,X-Request-ID
CORS_MAX_AGE=86400
CORS_ALLOW_CREDENTIALS=true
```

**Importante:** En producción, NUNCA usar `CORS_ALLOWED_ORIGINS=*`. El middleware valida esto y lanza un warning en el log si detecta wildcard con `CORS_ALLOW_CREDENTIALS=true` (es un problema de seguridad conocido).

#### Sanitización de Input

Toda entrada del usuario pasa por sanitización antes de llegar al recurso:

```php
class InputSanitizer
{
    public static function sanitize(mixed $input): mixed
    {
        if (is_string($input)) {
            // Remover null bytes
            $input = str_replace("\0", '', $input);
            // Strip tags en campos que no deberían tener HTML
            // (configurable por campo vía atributos)
            return $input;
        }

        if (is_array($input)) {
            return array_map(self::sanitize(...), $input);
        }

        return $input;
    }
}
```

**Nota sobre XSS:** Las APIs REST retornan JSON, no HTML. El riesgo de XSS es bajo, pero la sanitización previene ataques de inyección almacenada (stored XSS) si los datos se muestran luego en un frontend.

#### Checklist de Seguridad Integrada

| Protección | Cómo la implementa la librería |
|-----------|-------------------------------|
| SQL Injection | QueryBuilder con prepared statements (PDO `?` bind) |
| XSS | Sanitización de input + headers `X-Content-Type-Options` |
| CSRF | No aplica: APIs REST stateless usan tokens, no cookies |
| Clickjacking | Header `X-Frame-Options: DENY` |
| MITM | HSTS header cuando `APP_HTTPS=true` |
| Brute Force | Rate limiting middleware (configurable) |
| Token theft | JWT access cortos (15min) + refresh rotation con detección |
| Data exposure | `#[Hidden]` en campos sensibles, `SensitiveDataFilter` en logs |
| CORS misconfiguration | Validación de wildcard + credentials conflict |

### 4.17 Documentación Automática — OpenAPI/Swagger

#### Filosofía

> "La documentación que se escribe a mano se desactualiza. La que se genera del código siempre está al día."

Los mismos atributos PHP que definen la validación, relaciones y estructura de las entidades generan automáticamente una especificación OpenAPI 3.1 completa — sin que el desarrollador escriba una línea de documentación.

#### ¿Cómo Funciona?

La librería lee los atributos y tipos de las entidades en runtime y genera el esquema:

```php
// De esta entidad...
#[Table('users')]
class User extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required, MaxLength(50)]
    public string $name { set => trim($value); }

    #[Required, Email, Unique]
    public string $email { set => strtolower(trim($value)); }

    #[Hidden]
    public string $password { set => password_hash($value, PASSWORD_ARGON2ID); }

    #[BelongsTo(Role::class)]
    public int $roleId;
}
```

Se genera automáticamente:

```yaml
# GET /api/v1/docs → devuelve esta spec
openapi: "3.1.0"
info:
  title: "My API"
  version: "1.0.0"
paths:
  /api/v1/users:
    get:
      summary: "List users"
      parameters:
        - name: page
          in: query
          schema: { type: integer, default: 1 }
        - name: per_page
          in: query
          schema: { type: integer, default: 20 }
        - name: sort
          in: query
          schema: { type: string, example: "-created_at,name" }
      responses:
        "200":
          description: "Paginated list of users"
          content:
            application/json:
              schema:
                type: object
                properties:
                  data:
                    type: array
                    items: { $ref: "#/components/schemas/User" }
                  meta: { $ref: "#/components/schemas/PaginationMeta" }
                  links: { $ref: "#/components/schemas/PaginationLinks" }
    post:
      summary: "Create user"
      requestBody:
        required: true
        content:
          application/json:
            schema:
              type: object
              required: [name, email, password, roleId]
              properties:
                name: { type: string, maxLength: 50 }
                email: { type: string, format: email }
                password: { type: string }    # Hidden: no aparece en responses
                roleId: { type: integer }
      responses:
        "201":
          description: "User created"
components:
  schemas:
    User:
      type: object
      properties:
        id: { type: integer, readOnly: true }
        name: { type: string, maxLength: 50 }
        email: { type: string, format: email }
        roleId: { type: integer }
        # password NO aparece (es #[Hidden])
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
      bearerFormat: JWT
```

#### Mapeo de Atributos PHP → OpenAPI

| Atributo PHP | OpenAPI Schema |
|-------------|----------------|
| `public int $id` | `type: integer` |
| `public string $name` | `type: string` |
| `public float $price` | `type: number, format: float` |
| `public bool $active` | `type: boolean` |
| `#[Required]` | Campo en `required: []` del schema |
| `#[MaxLength(50)]` | `maxLength: 50` |
| `#[MinLength(3)]` | `minLength: 3` |
| `#[Email]` | `format: email` |
| `#[Hidden]` | Excluido de response schemas |
| `#[PrimaryKey]` | `readOnly: true` |
| `#[BelongsTo(Role)]` | `type: integer` (FK) |
| `#[HasMany(Order)]` | Documentado en include params |
| `#[PublicResource]` | Sin `security` en el path |
| `public private(set)` | `readOnly: true` |
| Default value `= true` | `default: true` |

#### Endpoints de Documentación

```
GET /api/v1/docs            → Spec OpenAPI en JSON
GET /api/v1/docs/swagger    → UI de Swagger embebida (HTML)
GET /api/v1/docs/redoc      → UI de ReDoc embebida (HTML)
```

El desarrollador puede deshabilitar la documentación en producción:

```php
// .env
API_DOCS=true               # true | false (default: true)
API_DOCS_PATH=docs           # Ruta personalizable
```

#### CLI para Exportar

```bash
php api docs:generate              # Genera openapi.json en raíz
php api docs:generate --yaml       # Genera openapi.yaml
php api docs:generate --output=public/docs/api.json
```

#### Atributos Opcionales para Documentación Extra

Para desarrolladores que quieran agregar descripciones o ejemplos a sus campos:

```php
#[Description('Full name of the user')]
#[Example('Carlos García')]
#[Required, MaxLength(50)]
public string $name { set => trim($value); }

#[Description('User role identifier')]
#[Example(2)]
#[BelongsTo(Role::class)]
public int $roleId;
```

Estos atributos son opcionales — la documentación se genera bien sin ellos, pero permiten hacerla más rica.

### 4.18 CLI — Scaffolding y Entorno de Desarrollo

#### Filosofía

> "De cero a API funcionando en menos de 5 minutos — con o sin PHP instalado."

El CLI de php-api-builder va más allá de generar archivos. Es la experiencia completa de onboarding: crear proyecto, levantar entorno, generar código, testear, y documentar. Soporta dos caminos: **PHP local** (si el developer ya tiene PHP) y **Docker-first** (si solo tiene Docker instalado).

#### Dos Caminos de Inicialización

##### Camino A — Con PHP local instalado

```bash
# Opción 1: Desde un proyecto vacío
composer require coagus/php-api-builder
php api init

# Opción 2: Crear proyecto completo desde cero
composer create-project coagus/php-api-builder-skeleton mi-api
cd mi-api
php api init
```

##### Camino B — Docker-first (sin PHP local)

Para desarrolladores que **no tienen PHP instalado** en su máquina. Solo necesitan Docker:

```bash
# 1. Crear directorio del proyecto
mkdir mi-api && cd mi-api

# 2. Ejecutar init desde la imagen Docker publicada
docker run --rm -it -v $(pwd):/app coagus/php-api-builder init
```

Desglose del comando:

| Flag | Qué hace |
|------|----------|
| `--rm` | Elimina el contenedor al terminar (es desechable, solo sirve para el init) |
| `-it` | **i**nteractive + **t**ty — permite que el init haga preguntas en la terminal |
| `-v $(pwd):/app` | Monta el directorio actual dentro del contenedor, así los archivos generados quedan en tu máquina |
| `coagus/php-api-builder` | Imagen publicada en Docker Hub con PHP 8.4, Composer y el CLI preinstalados |
| `init` | El comando que ejecuta el CLI interactivo |

El init genera exactamente la misma estructura que el Camino A, pero sin necesidad de tener PHP ni Composer instalados localmente.

#### Imagen Docker Publicada: `coagus/php-api-builder`

La librería publica y mantiene una imagen oficial en Docker Hub que sirve para dos propósitos:

1. **Herramienta CLI** — para ejecutar `init` y otros comandos sin PHP local
2. **Imagen base** — los proyectos generados pueden usarla como `FROM` en su Dockerfile

```dockerfile
# Dockerfile de la imagen publicada: coagus/php-api-builder
FROM php:8.4-cli

# Extensiones PDO (todas, para soportar cualquier driver)
RUN docker-php-ext-install pdo_mysql pdo_pgsql \
    && apt-get update && apt-get install -y \
       libzip-dev unzip libpq-dev sqlite3 libsqlite3-dev \
    && docker-php-ext-install zip pdo_sqlite

# OpenSSL para JWT
RUN apt-get install -y libssl-dev

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar la librería globalmente
RUN composer global require coagus/php-api-builder

# Agregar el binario al PATH
ENV PATH="/root/.composer/vendor/bin:${PATH}"

WORKDIR /app

# Entrypoint: ejecuta el CLI de php-api-builder
ENTRYPOINT ["php", "api"]
CMD ["--help"]
```

**Ubicación en el repositorio de la librería:**

```
php-api-builder/
├── src/                              # Código fuente de la librería
├── docker/
│   └── cli/
│       └── Dockerfile                # ← Dockerfile de la imagen publicada
├── .github/
│   └── workflows/
│       └── docker-publish.yml        # CI/CD: build + push en cada release
├── composer.json
└── ...
```

**CI/CD — Publicación automática con GitHub Actions:**

```yaml
# .github/workflows/docker-publish.yml
name: Publish Docker Image

on:
  release:
    types: [published]

jobs:
  docker:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - uses: docker/login-action@v3
        with:
          username: ${{ secrets.DOCKERHUB_USERNAME }}
          password: ${{ secrets.DOCKERHUB_TOKEN }}

      - uses: docker/build-push-action@v5
        with:
          context: .
          file: docker/cli/Dockerfile
          push: true
          tags: |
            coagus/php-api-builder:${{ github.event.release.tag_name }}
            coagus/php-api-builder:latest
```

La imagen se actualiza automáticamente con cada release de la librería, igual que el paquete en Packagist. El tag `latest` siempre apunta a la versión estable más reciente. El developer nunca tiene que pensar en qué versión de la imagen usar.

#### Script Wrapper `./api` — Detección Automática PHP vs Docker

El `init` genera un script `./api` en la raíz del proyecto que **detecta automáticamente** si debe usar PHP local o Docker. El developer siempre usa el mismo comando sin pensar:

```bash
# El developer siempre escribe esto:
./api make:entity User
./api test
./api serve

# El script decide internamente si ejecutar:
#   php api make:entity User          (si PHP está instalado)
#   docker compose exec app php api make:entity User  (si no)
```

El script wrapper:

```bash
#!/bin/bash
# ./api — Wrapper inteligente para php-api-builder CLI
# Detecta PHP local o usa Docker automáticamente

set -e

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

# Verificar si PHP local está disponible y es ≥8.3
php_available() {
    if ! command -v php &> /dev/null; then
        return 1
    fi
    local version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
    local major=$(echo $version | cut -d. -f1)
    local minor=$(echo $version | cut -d. -f2)
    if [ "$major" -lt 8 ] || ([ "$major" -eq 8 ] && [ "$minor" -lt 3 ]); then
        return 1
    fi
    return 0
}

# Verificar si Docker Compose está corriendo
docker_running() {
    docker compose ps --status running 2>/dev/null | grep -q "app"
}

# Ejecutar comando
if php_available; then
    # PHP local disponible — usar directamente
    php api "$@"
elif docker_running; then
    # Docker corriendo — ejecutar dentro del contenedor app
    docker compose exec app php api "$@"
elif command -v docker &> /dev/null; then
    # Docker disponible pero no corriendo — usar imagen standalone
    echo -e "${YELLOW}PHP not found locally. Using Docker...${NC}"
    docker run --rm -it -v "$(pwd):/app" coagus/php-api-builder "$@"
else
    echo "Error: Neither PHP (≥8.3) nor Docker found."
    echo "Install one of:"
    echo "  PHP 8.3+  → https://www.php.net/downloads"
    echo "  Docker    → https://docs.docker.com/get-docker/"
    exit 1
fi
```

**Flujo de decisión del wrapper:**

```
./api make:entity User
        │
        ▼
   ¿PHP ≥8.3 local?
   ├─ SÍ → php api make:entity User
   │
   ├─ NO → ¿Docker Compose corriendo?
   │        ├─ SÍ → docker compose exec app php api make:entity User
   │        │
   │        └─ NO → ¿Docker instalado?
   │                 ├─ SÍ → docker run --rm -v $(pwd):/app coagus/php-api-builder make:entity User
   │                 │
   │                 └─ NO → Error: instalar PHP o Docker
```

Esto significa que un equipo mixto puede trabajar en el mismo proyecto: unos con PHP local, otros con Docker, todos usan `./api` y funciona igual.

El comando `init` es **interactivo** y configura todo el proyecto:

```
$ php api init

  ╔══════════════════════════════════════╗
  ║   PHP API Builder v2 — Setup         ║
  ╚══════════════════════════════════════╝

  Project name: mi-api
  API version prefix [v1]: v1

  Database driver:
  ❯ MySQL
    PostgreSQL
    SQLite

  Database host [localhost]: localhost
  Database port [3306]: 3306
  Database name: mi_api_db
  Database user [root]: root
  Database password: ****

  Enable JWT authentication? [Y/n]: Y
  JWT algorithm:
  ❯ RS256 (recommended)
    ES256
    HS256

  Generate Docker environment? [Y/n]: Y

  ✓ Created .env
  ✓ Created .htaccess
  ✓ Created index.php
  ✓ Created entities/
  ✓ Created services/
  ✓ Created middleware/
  ✓ Created log/
  ✓ Created docker-compose.yml
  ✓ Created Dockerfile
  ✓ Created tests/
  ✓ Generated JWT keys (keys/private.pem, keys/public.pem)

  Your API is ready! Next steps:

    docker compose up -d      ← Start development environment
    php api make:entity User  ← Create your first entity
    php api serve             ← Start development server (without Docker)
```

#### Estructura Generada

```
mi-api/
├── api                              # ← Wrapper script (detecta PHP vs Docker)
├── composer.json                    # Con dependencias configuradas
├── .env                             # Configuración generada
├── .env.example                     # Template para otros developers
├── .gitignore                       # Ignora .env, log/, vendor/, keys/
├── .htaccess                        # Rewrite rules para Apache
├── index.php                        # Entry point del API
│
├── entities/                        # Aquí van las entidades
│   └── .gitkeep
├── services/                        # Aquí van los servicios
│   └── Health.php                   # Health check incluido por defecto
├── middleware/                       # Middleware custom
│   └── .gitkeep
├── tests/                           # Tests del proyecto
│   ├── Pest.php
│   ├── TestCase.php
│   └── Feature/
│       └── HealthTest.php           # Test del health check
│
├── keys/                            # JWT keys (si se eligió RS256/ES256)
│   ├── private.pem
│   └── public.pem
│
├── docker/                          # Docker setup (si se eligió)
│   ├── php/
│   │   └── Dockerfile
│   ├── nginx/
│   │   └── default.conf
│   └── mysql/                       # O postgresql/ según driver
│       └── init.sql
├── docker-compose.yml
│
└── log/                             # Logs de errores
    └── .gitkeep
```

#### Docker — Detección y Setup Automático

El comando `init` detecta si Docker está instalado y ofrece generar el entorno:

```php
// Lógica interna del comando init
$dockerAvailable = shell_exec('docker --version 2>/dev/null') !== null;
$composeAvailable = shell_exec('docker compose version 2>/dev/null') !== null;

if ($dockerAvailable && $composeAvailable) {
    // Ofrecer generar Docker
} else {
    // Informar que Docker no está disponible
    // Generar solo el proyecto sin Docker
    // Sugerir instalación de Docker
}
```

El `docker-compose.yml` generado varía según el driver de DB elegido:

```yaml
# docker-compose.yml (ejemplo con MySQL)
services:
  app:
    build:
      context: .
      dockerfile: docker/php/Dockerfile
    ports:
      - "${APP_PORT:-8080}:80"          # ← Puerto configurable
    volumes:
      - .:/var/www/html
    depends_on:
      db:
        condition: service_healthy
    environment:
      - APP_ENV=development
      - APP_DEBUG=true

  db:
    image: mysql:8.4
    ports:
      - "${DB_PORT:-3306}:3306"         # ← Puerto configurable
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}
      MYSQL_DATABASE: ${DB_NAME}
    volumes:
      - db_data:/var/lib/mysql
      - ./docker/mysql/init.sql:/docker-entrypoint-initdb.d/init.sql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 5

  phpmyadmin:                           # Solo en development
    image: phpmyadmin/phpmyadmin
    ports:
      - "${PMA_PORT:-8081}:80"          # ← Puerto configurable
    environment:
      PMA_HOST: db
    depends_on:
      - db

volumes:
  db_data:
```

Para PostgreSQL genera `postgres:16` + `pgadmin4`, para SQLite no genera servicio de DB.

#### Manejo de Conflictos de Puertos

Problema común: el developer ya tiene MySQL en 3306, Apache en 80, u otro proyecto en 8080. Los puertos son configurables vía `.env`:

```bash
# .env — Si los puertos por defecto están ocupados, cambiar aquí:
APP_PORT=8080        # Puerto del API (default: 8080)
DB_PORT=3306         # Puerto de la DB (default: 3306)
PMA_PORT=8081        # Puerto de phpMyAdmin (default: 8081)
```

El `docker-compose.yml` usa la sintaxis `${VAR:-default}` que significa: "usa el valor de la variable, o si no existe, usa el default". Así funciona sin `.env` (con los defaults) y con `.env` (con puertos custom).

**Detección automática en `init` y `env:check`:**

El CLI detecta puertos ocupados antes de intentar levantar Docker:

```bash
$ ./api env:check

  Docker Environment
  ──────────────────
  Docker              27.4.0         ✓
  Docker Compose      2.32.0         ✓

  Port Availability
  ──────────────────
  Port 8080 (APP)     available      ✓
  Port 3306 (DB)      IN USE         ✗ ← Conflict detected!
    → Process: mysqld (PID 1234)
    → Fix: Change DB_PORT in .env (suggested: 3307)
  Port 8081 (PMA)     available      ✓
```

```php
// Lógica interna de detección de puertos
class PortChecker
{
    public static function isAvailable(int $port): bool
    {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($socket) {
            fclose($socket);
            return false; // Puerto ocupado
        }
        return true; // Puerto disponible
    }

    public static function suggestAlternative(int $port): int
    {
        // Buscar el siguiente puerto disponible
        for ($p = $port + 1; $p <= $port + 100; $p++) {
            if (self::isAvailable($p)) {
                return $p;
            }
        }
        return $port + 1000; // Fallback
    }
}
```

Durante el `init`, si detecta un conflicto, pregunta:

```
  Port 3306 is already in use by another process.
  Suggested alternative: 3307

  Use port 3307 for MySQL? [Y/n]: Y
  ✓ DB_PORT=3307 saved to .env
```

#### Dockerfile Optimizado

```dockerfile
# docker/php/Dockerfile
FROM php:8.4-apache

# Extensiones según driver seleccionado
RUN docker-php-ext-install pdo_mysql   # o pdo_pgsql

# Extensiones comunes
RUN apt-get update && apt-get install -y \
    libzip-dev unzip \
    && docker-php-ext-install zip opcache

# OPcache para rendimiento
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Apache mod_rewrite
RUN a]2enmod rewrite

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80
```

#### Comandos de Generación (Scaffolding)

```bash
# Crear entidad (genera clase + test)
php api make:entity Product
php api make:entity Product --fields="name:string,price:float,active:bool"
php api make:entity Product --soft-delete --timestamps

# Crear servicio
php api make:service Payment
php api make:service Payment --public    # Sin auth requerida

# Crear middleware
php api make:middleware RateLimiter
php api make:middleware LogRequest

# Crear test
php api make:test Product               # Test para entidad existente
php api make:test AuthFlow --feature     # Test feature/e2e
```

Ejemplo de entidad generada con `--fields`:

```bash
$ php api make:entity Product --fields="name:string,price:float,categoryId:int" --soft-delete

  ✓ Created entities/Product.php
  ✓ Created tests/Integration/ProductTest.php
```

```php
// entities/Product.php (generado)
<?php

namespace App\Entities;

use Coagus\PhpApiBuilder\ORM\Entity;
use Coagus\PhpApiBuilder\Attributes\{Table, PrimaryKey, SoftDelete};
use Coagus\PhpApiBuilder\Validation\Attributes\{Required};

#[Table('products')]
#[SoftDelete]
class Product extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required]
    public string $name {
        set => trim($value);
    }

    #[Required]
    public float $price;

    #[Required]
    public int $categoryId;

    // TODO: Add validation attributes, relationships, and hooks
}
```

#### Entorno de Desarrollo: `php api serve`

Para desarrolladores que no quieren Docker:

```bash
$ php api serve

  PHP API Builder v2 Development Server

  ✓ PHP 8.4.3 detected
  ✓ Database connection OK (MySQL @ localhost:3306)
  ✓ OPcache enabled

  API running at http://localhost:8080/api/v1
  Swagger UI at http://localhost:8080/api/v1/docs/swagger

  Press Ctrl+C to stop.
```

Internamente usa el servidor built-in de PHP (`php -S`) con un router file que simula las rewrite rules de Apache.

#### Ambiente de Colaboración: `php api env:check`

Cuando un desarrollador nuevo clona el proyecto, este comando valida que tiene todo listo:

```bash
$ php api env:check

  ╔═══════════════════════════════════════╗
  ║   Environment Check                    ║
  ╚═══════════════════════════════════════╝

  PHP Version         8.4.3          ✓ Required: ≥8.3
  Composer            2.8.1          ✓
  OPcache             enabled        ✓
  PDO MySQL           enabled        ✓
  PDO PostgreSQL      not installed  ⚠ Optional
  PDO SQLite          enabled        ✓
  OpenSSL             enabled        ✓ Required for JWT
  Mbstring            enabled        ✓

  Docker              27.4.0         ✓
  Docker Compose      2.32.0         ✓

  .env file           exists         ✓
  Database connection OK             ✓
  JWT keys            valid          ✓
  Log directory       writable       ✓

  ✓ All checks passed! Ready to develop.
```

Si algo falla:

```bash
  .env file           missing        ✗
    → Run: cp .env.example .env && php api init --env-only

  Database connection FAILED         ✗
    → Check DB_HOST, DB_PORT, DB_USER, DB_PASSWORD in .env
    → Or run: docker compose up -d

  JWT keys            missing        ✗
    → Run: php api keys:generate
```

#### Onboarding de Nuevos Developers

##### Si el developer tiene PHP local:

```bash
git clone https://github.com/team/mi-api.git
cd mi-api
composer install
cp .env.example .env               # Editar con credenciales locales
./api env:check                    # Verificar todo
docker compose up -d               # Levantar DB con Docker
./api serve                        # O usar PHP built-in server
curl http://localhost:8080/api/v1/health
./api test                         # Correr tests
```

##### Si el developer solo tiene Docker:

```bash
git clone https://github.com/team/mi-api.git
cd mi-api
cp .env.example .env               # Editar con credenciales locales
docker compose up -d               # Levanta PHP + DB + herramientas
./api env:check                    # Wrapper usa Docker automáticamente
curl http://localhost:8080/api/v1/health
./api test                         # Tests dentro del contenedor
./api make:entity Product          # Todo funciona via Docker
```

No necesita `composer install` porque el contenedor `app` ya tiene todo instalado. El `docker-compose.yml` monta el código como volumen, así los cambios se reflejan en tiempo real.

##### Creación de Proyecto Nuevo (sin PHP local):

```bash
# 1. Solo necesita Docker
mkdir mi-api && cd mi-api
docker run --rm -it -v $(pwd):/app coagus/php-api-builder init

# 2. El init genera todo, incluyendo docker-compose.yml
docker compose up -d

# 3. Listo — API corriendo
curl http://localhost:8080/api/v1/health
# → {"data":{"status":"healthy","timestamp":"2026-04-03T..."}}

# 4. Crear entidades, servicios, etc.
./api make:entity User --fields="name:string,email:string" --soft-delete
./api make:service Payment --public
./api test
```

#### AI Skill — Asistente de Desarrollo

La librería incluye un **skill** (asistente de IA) que enseña a Claude Code y Cowork a trabajar con php-api-builder. Con el skill instalado, la IA conoce todos los atributos, patrones, convenciones y comandos de la librería — puede generar entities, services, middleware, configurar auth, y resolver dudas como si fuera un experto en la librería.

**Instalación con CLI:**

```bash
$ ./api skill:install

  ✓ Copied skill to .claude/skills/php-api-builder/SKILL.md
  ✓ Copied references to .claude/skills/php-api-builder/references/

  AI skill installed! Claude Code and Cowork now know
  how to work with php-api-builder.
```

El comando copia el skill desde `vendor/coagus/php-api-builder/resources/skill/php-api-builder/` hacia `.claude/skills/php-api-builder/` en la raíz del proyecto. Desde ese momento, cualquier sesión de Claude Code o Cowork que abra el proyecto detecta el skill automáticamente.

**Ubicación en la librería:**

```
php-api-builder/                     # ← Repositorio de la librería
├── src/                             # Código fuente
├── resources/
│   ├── docs/                        # Documentación de arquitectura
│   └── skill/
│       └── php-api-builder/         # ← El skill vive aquí
│           ├── SKILL.md             # Instrucciones para la IA
│           └── references/
│               └── architecture-decisions.md
└── ...
```

**Distribución:**

| Canal | Cómo llega al developer |
|-------|------------------------|
| Composer | `composer require coagus/php-api-builder` → skill incluido en `resources/skill/` → `./api skill:install` lo activa |
| GitHub Release | El `.skill` (zip) se adjunta como asset en cada release para descarga directa |
| README | Link al `.skill` para instalación rápida en Cowork sin tener el proyecto |

**¿Qué sabe hacer la IA con el skill instalado?**

- Generar entities completas con atributos, hooks, relaciones y property hooks de PHP 8.4
- Crear services puros e híbridos con los patrones correctos de la librería
- Escribir queries usando los 5 niveles del QueryBuilder
- Configurar auth JWT, middleware, CORS
- Seguir las convenciones de nombres (snake_case URL, lowerCamelCase JSON, etc.)
- Resolver errores comunes y sugerir buenas prácticas específicas de la librería

#### Resumen de Todos los Comandos CLI

Todos los comandos funcionan con `./api` (wrapper inteligente) o `php api` (directo):

| Comando | Descripción |
|---------|-------------|
| `./api init` | Inicializar proyecto nuevo (interactivo) |
| `./api serve` | Servidor de desarrollo (PHP built-in) |
| `./api env:check` | Verificar entorno y dependencias |
| `./api make:entity Name` | Generar entidad + test |
| `./api make:service Name` | Generar servicio + test |
| `./api make:middleware Name` | Generar middleware |
| `./api make:test Name` | Generar test |
| `./api keys:generate` | Generar par de llaves JWT (RS256/ES256) |
| `./api docs:generate` | Exportar OpenAPI spec a archivo |
| `./api test` | Ejecutar tests (Pest) |
| `./api test --coverage` | Tests con reporte de cobertura |
| `./api skill:install` | Instalar AI skill en `.claude/skills/php-api-builder/` |

**Inicialización sin PHP local (solo Docker):**

| Comando | Descripción |
|---------|-------------|
| `docker run --rm -it -v $(pwd):/app coagus/php-api-builder init` | Crear proyecto nuevo sin PHP instalado |
| `docker run --rm -v $(pwd):/app coagus/php-api-builder make:entity Name` | Generar entidad sin PHP instalado |

### 4.19 Manejo de Archivos (File Uploads)

#### Filosofía

> "Si tu API necesita recibir archivos, que sea simple. Pero el storage no es nuestra responsabilidad."

La librería ofrece soporte básico para recibir archivos vía `multipart/form-data`, pero no implementa un sistema de storage completo (eso sería un paquete aparte como Flysystem).

#### Upload Básico en un Service

```php
#[Route('documents')]
class DocumentService extends Service
{
    public function post(): void
    {
        $file = $this->getUploadedFile('document');

        if (!$file) {
            $this->error('No file uploaded', 400);
            return;
        }

        // Validaciones
        $file->validateType(['application/pdf', 'image/jpeg', 'image/png']);
        $file->validateMaxSize(5 * 1024 * 1024); // 5MB

        // Mover a destino
        $path = $file->moveTo('uploads/' . uniqid() . '.' . $file->extension());

        $this->created([
            'filename' => $file->originalName(),
            'path' => $path,
            'size' => $file->size(),
            'mimeType' => $file->mimeType(),
        ]);
    }
}
```

#### Objeto UploadedFile

```php
class UploadedFile
{
    public function originalName(): string;       // Nombre original
    public function extension(): string;          // Extensión del archivo
    public function mimeType(): string;           // MIME type real (finfo)
    public function size(): int;                  // Tamaño en bytes
    public function tempPath(): string;           // Ruta temporal

    public function moveTo(string $destination): string;  // Mover archivo
    public function validateType(array $allowed): void;   // Validar MIME
    public function validateMaxSize(int $bytes): void;    // Validar tamaño

    public function isValid(): bool;              // ¿Upload exitoso?
}
```

**Seguridad:**
- El MIME type se valida con `finfo_file()` (no confía en lo que dice el cliente)
- Los nombres de archivo se sanitizan (sin path traversal: `../`, sin caracteres especiales)
- El destino de upload debe estar fuera del directorio público por defecto

#### Integración con Entidades (Upload + CRUD)

Para casos donde una entidad tiene un archivo asociado:

```php
#[Table('products')]
class Product extends Entity
{
    #[PrimaryKey]
    public private(set) int $id;

    #[Required]
    public string $name;

    public ?string $imagePath = null;  // Ruta al archivo
}

// En el Service híbrido:
class ProductService extends APIDB
{
    protected string $entity = Product::class;

    // POST /api/v1/products (con multipart)
    public function post(): void
    {
        $input = $this->getInput();
        $file = $this->getUploadedFile('image');

        if ($file) {
            $file->validateType(['image/jpeg', 'image/png', 'image/webp']);
            $file->validateMaxSize(2 * 1024 * 1024);
            $input->imagePath = $file->moveTo('uploads/products/');
        }

        $product = new Product();
        $product->fill($input);
        $product->save();

        $this->created($product);
    }
}
```

**Nota:** La librería NO incluye redimensionamiento de imágenes, CDN upload, ni storage en S3. Eso queda del lado del desarrollador o de paquetes Composer especializados (Intervention Image, Flysystem, etc.).

### 4.20 CI/CD — Pipeline Completo (Tests → Release → Docker Hub)

#### Filosofía

> "Push a main, y si los tests pasan, todo se publica solo: GitHub Release, Packagist, Docker Hub."

El pipeline automatiza completamente el ciclo de release. El developer solo hace push (o merge de un PR), y el sistema se encarga del resto.

#### Flujo Visual del Pipeline

```
Developer hace push/merge a main
        │
        ▼
┌──────────────────────┐
│  1. TESTS            │
│  ─────────────────   │
│  PHP 8.3 + 8.4       │
│  Unit tests (Pest)   │
│  Integration tests   │
│  Type coverage       │
│  Code coverage ≥80%  │
└──────────┬───────────┘
           │ ¿Pasan?
     ┌─────┴─────┐
     │ NO        │ SÍ
     ▼           ▼
  Pipeline    ┌──────────────────────┐
  FALLA       │  2. DETERMINAR       │
              │  VERSIÓN             │
              │  ─────────────────   │
              │  Lee commit messages │
              │  feat: → minor       │
              │  fix: → patch        │
              │  BREAKING: → major   │
              └──────────┬───────────┘
                         │
                         ▼
              ┌──────────────────────┐
              │  3. CREAR TAG +      │
              │  GITHUB RELEASE      │
              │  ─────────────────   │
              │  Tag: v2.1.0         │
              │  Changelog generado  │
              │  Assets adjuntos     │
              └──────────┬───────────┘
                         │
                    ┌────┴────┐
                    ▼         ▼
          ┌─────────────┐ ┌─────────────────┐
          │ 4. PACKAGIST│ │ 5. DOCKER HUB   │
          │ ──────────  │ │ ──────────────  │
          │ Webhook     │ │ Build imagen    │
          │ automático  │ │ Push :2.1.0     │
          │ por tag     │ │ Push :latest    │
          └─────────────┘ └─────────────────┘
```

#### Workflow Completo: `.github/workflows/release.yml`

```yaml
name: Test, Release & Publish

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

permissions:
  contents: write        # Necesario para crear releases y tags

jobs:
  # ═══════════════════════════════════════
  # JOB 1: Tests en matrix PHP 8.3 + 8.4
  # ═══════════════════════════════════════
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ['8.3', '8.4']

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP ${{ matrix.php }}
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
          extensions: pdo_sqlite, pdo_mysql, pdo_pgsql, mbstring, openssl
          coverage: xdebug

      - name: Install dependencies
        run: composer install --prefer-dist --no-progress

      - name: Run tests with coverage
        run: vendor/bin/pest --coverage --min=80

      - name: Run type coverage
        run: vendor/bin/pest --type-coverage --min=95

  # ═══════════════════════════════════════
  # JOB 2: Crear Release (solo en main, no en PRs)
  # ═══════════════════════════════════════
  release:
    needs: test                          # Solo si los tests pasan
    if: github.ref == 'refs/heads/main' && github.event_name == 'push'
    runs-on: ubuntu-latest

    outputs:
      new_tag: ${{ steps.tag.outputs.new_tag }}
      changelog: ${{ steps.tag.outputs.changelog }}

    steps:
      - uses: actions/checkout@v4
        with:
          fetch-depth: 0                 # Historial completo para generar changelog

      # Determinar nueva versión basada en commits convencionales
      # feat: → minor (1.2.0 → 1.3.0)
      # fix:  → patch (1.2.0 → 1.2.1)
      # feat! o BREAKING CHANGE: → major (1.2.0 → 2.0.0)
      - name: Determine version bump
        id: tag
        uses: mathieudutour/github-tag-action@v6.2
        with:
          github_token: ${{ secrets.GITHUB_TOKEN }}
          default_bump: patch
          release_branches: main
          # Conventional commits:
          # feat(orm): add eager loading  → minor
          # fix(auth): token expiry bug   → patch
          # feat!: new entity API         → major

      # Crear GitHub Release con changelog autogenerado
      - name: Create GitHub Release
        uses: softprops/action-gh-release@v2
        with:
          tag_name: ${{ steps.tag.outputs.new_tag }}
          name: "v${{ steps.tag.outputs.new_tag }}"
          body: ${{ steps.tag.outputs.changelog }}
          generate_release_notes: true
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

  # ═══════════════════════════════════════
  # JOB 3: Publicar imagen en Docker Hub
  # ═══════════════════════════════════════
  docker:
    needs: release                       # Solo después de crear el release
    if: needs.release.outputs.new_tag != ''
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      # Login en Docker Hub
      - name: Login to Docker Hub
        uses: docker/login-action@v3
        with:
          username: ${{ secrets.DOCKERHUB_USERNAME }}
          password: ${{ secrets.DOCKERHUB_TOKEN }}

      # Setup BuildX para builds multi-plataforma (opcional pero recomendado)
      - name: Set up Docker Buildx
        uses: docker/setup-buildx-action@v3

      # Build y push con tag de versión + latest
      - name: Build and push Docker image
        uses: docker/build-push-action@v6
        with:
          context: .
          file: docker/cli/Dockerfile
          push: true
          tags: |
            coagus/php-api-builder:${{ needs.release.outputs.new_tag }}
            coagus/php-api-builder:latest
          cache-from: type=gha           # Cache de GitHub Actions (builds más rápidos)
          cache-to: type=gha,mode=max

  # ═══════════════════════════════════════
  # JOB 4: Notificar a Packagist (opcional)
  # ═══════════════════════════════════════
  packagist:
    needs: release
    if: needs.release.outputs.new_tag != ''
    runs-on: ubuntu-latest

    steps:
      # Packagist normalmente actualiza por webhook de GitHub,
      # pero este paso fuerza la actualización inmediata
      - name: Update Packagist
        run: |
          curl -X POST "https://packagist.org/api/update-package?username=${{ secrets.PACKAGIST_USERNAME }}&apiToken=${{ secrets.PACKAGIST_TOKEN }}" \
            -d '{"repository":{"url":"https://github.com/coagus/php-api-builder"}}'
```

#### Secrets Necesarios en GitHub

El pipeline necesita estos secrets configurados en **Settings → Secrets and variables → Actions**:

| Secret | Dónde obtenerlo | Para qué |
|--------|----------------|----------|
| `GITHUB_TOKEN` | Automático (GitHub lo provee) | Crear tags, releases |
| `DOCKERHUB_USERNAME` | Tu usuario de Docker Hub | Login en Docker Hub |
| `DOCKERHUB_TOKEN` | Docker Hub → Account Settings → Security → New Access Token | Push de imágenes |
| `PACKAGIST_USERNAME` | Tu usuario de Packagist | Forzar actualización |
| `PACKAGIST_TOKEN` | Packagist → Profile → API Token | Autenticación API |

**`GITHUB_TOKEN` es especial**: GitHub lo genera automáticamente para cada workflow, no necesitas crearlo. Solo necesitas el `permissions: contents: write` en el workflow para que pueda crear tags y releases.

#### Conventional Commits — El Estándar de Mensajes

El pipeline usa **Conventional Commits** para determinar automáticamente qué tipo de versión crear:

```bash
# Patch (1.2.0 → 1.2.1) — corrección de bug
git commit -m "fix(orm): correct eager loading with nested relations"
git commit -m "fix(auth): handle expired refresh token edge case"

# Minor (1.2.0 → 1.3.0) — nueva funcionalidad
git commit -m "feat(orm): add cursor-based pagination"
git commit -m "feat(cli): add make:middleware command"

# Major (1.2.0 → 2.0.0) — cambio que rompe compatibilidad
git commit -m "feat!: redesign Entity attribute API"
git commit -m "feat(orm): drop PHP 8.2 support

BREAKING CHANGE: minimum PHP version is now 8.3"
```

**Formato:** `tipo(scope): descripción`

| Tipo | Bump | Cuándo usarlo |
|------|------|---------------|
| `fix` | patch | Bug corregido |
| `feat` | minor | Nueva funcionalidad que no rompe nada |
| `feat!` | major | Cambio que rompe backward compatibility |
| `docs` | — | Solo documentación (no genera release) |
| `test` | — | Solo tests (no genera release) |
| `refactor` | — | Refactoring sin cambio funcional (no genera release) |
| `chore` | — | Mantenimiento (no genera release) |

Si todos los commits desde el último release son `docs`, `test`, `refactor`, o `chore`, **no se genera release**. Solo `fix` y `feat` generan versiones nuevas.

#### Packagist — Actualización Automática

Packagist tiene dos mecanismos de actualización:

1. **Webhook de GitHub** (ya lo tienes configurado): Packagist se entera cuando hay un nuevo tag y actualiza el paquete. Esto toma entre 5-15 minutos.

2. **API de Packagist** (el job `packagist` del workflow): Fuerza la actualización inmediata. Así el `composer require coagus/php-api-builder` muestra la nueva versión instantáneamente después del release.

No necesitas los dos — el webhook es suficiente para la mayoría de casos. El job de API es solo para que la actualización sea instantánea.

#### Resultado Final

Después de hacer merge de un PR a main:

```
✓ Tests passed (PHP 8.3 + 8.4)
✓ Version determined: 2.3.0 (feat commit detected)
✓ GitHub Release created: v2.3.0 with changelog
✓ Docker Hub: coagus/php-api-builder:2.3.0 + :latest pushed
✓ Packagist: coagus/php-api-builder updated to 2.3.0

Timeline: ~3-5 minutes from push to everything published
```

---

## 5. Features de PHP 8.4 a Aprovechar

### 5.1 Property Hooks (nuevo en 8.4)
```php
class User extends Entity {
    public string $name {
        set => ucfirst(strtolower($value));
        get => $this->name;
    }
}
```

### 5.2 Asymmetric Visibility (nuevo en 8.4)
```php
class User extends Entity {
    public private(set) int $id;        // Se lee público, se escribe solo interno
    public protected(set) string $name;  // Se lee público, se escribe en subclases
}
```

### 5.3 Atributos PHP (8.0+) para metadata de entidades
```php
class User extends Entity {
    #[PrimaryKey, AutoIncrement]
    public readonly int $id;

    #[Required, MaxLength(100)]
    public string $name;

    #[Required, Email, Unique]
    public string $email;

    #[HasMany(Order::class)]
    public array $orders;
}
```

### 5.4 Enums para estados y configuración
```php
enum HttpMethod: string {
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case PATCH = 'PATCH';
    case DELETE = 'DELETE';
}

enum SortDirection: string {
    case ASC = 'asc';
    case DESC = 'desc';
}
```

### 5.5 Readonly Classes
```php
readonly class ApiResponse {
    public function __construct(
        public int $status,
        public mixed $data,
        public ?array $meta = null,
        public ?array $links = null,
    ) {}
}
```

### 5.6 Intersection Types y Union Types
```php
function handle(Request&Authenticatable $request): Response|JsonResponse
```

### 5.7 Constructor Property Promotion
```php
class DatabaseConfig {
    public function __construct(
        private string $host = 'localhost',
        private int $port = 3306,
        private string $database,
        private string $username,
        private string $password,
    ) {}
}
```

---

## Historial de Cambios

| Fecha | Cambio |
|-------|--------|
| 2026-04-03 | Creación del documento: análisis de estándares REST, estado actual v1.3.3, mejoras propuestas, features PHP 8.4 |
| 2026-04-03 | Análisis profundo del código fuente v1.3.3: flujo de request, capas, fortalezas, debilidades críticas (SQL injection, sin validación, sin relaciones) |
| 2026-04-03 | Definición de filosofía de diseño y resolución de todas las decisiones técnicas: Active Record tipado, Query Builder fluent, Middleware PSR-15, Hooks de ciclo de vida, CLI minimalista, validación por atributos, estructura de responses, arquitectura de componentes v2 |
| 2026-04-03 | Sistema dual Services/Entities documentado: Services puros (sin DB, APIs externas, gateways), Entities (CRUD auto), Híbridos (APIDB + custom). Capa de DB con PDO multi-driver (MySQL, PostgreSQL, SQLite), DriverInterface, Connection singleton con transacciones |
| 2026-04-03 | Convención de nombres definida: snake_case en query params URL (como Google, GitHub, Stripe, Twitter), lowerCamelCase en JSON, kebab-case en URL paths. Renumeración de secciones |
| 2026-04-03 | Estrategia de autenticación definida: JWT con prácticas OAuth 2.1 sobre firebase/php-jwt (sin agregar league/oauth2-server). Access tokens cortos (15min), refresh tokens con rotación y detección de robo, scopes, RS256/ES256, claims estándar, tabla refresh_tokens |
| 2026-04-03 | Paradigma OOP definido: PHP 8.4 pragmático (property hooks, asymmetric visibility, typed properties, readonly classes, final classes). Reglas de visibilidad por capa. Actualización de sección 3.1 para contrastar v1 (sin tipos) vs v2 (tipado y protegido) |
| 2026-04-03 | Query Builder expandido a 5 niveles de complejidad: shortcuts, fluent builder, eager loading, scopes, raw SQL seguro. Sistema de Response definido: Opción A (métodos heredados de Resource) + auto-wrap. Herencia: Resource → Service / APIDB. Reemplaza funciones globales success()/error() por $this->success()/$this->error() |
| 2026-04-03 | Estrategia de testing (4.14): Pest v4 como framework primario, estructura tests Unit/Integration/Feature, SQLite in-memory para DB de tests, CI/CD con GitHub Actions matrix PHP 8.3/8.4, coverage targets 80% global / 90%+ ORM / 95%+ Auth y Validation |
| 2026-04-03 | Logging y trazabilidad (4.15): Log solo errores, Request ID propagado en todas las capas, JSON estructurado con Monolog/PSR-3, stack trace completo, protección de datos sensibles (SensitiveDataFilter), ErrorHandler centralizado con RFC 7807, niveles de detalle por entorno |
| 2026-04-03 | Seguridad OWASP (4.16): SecurityHeadersMiddleware por defecto, headers automáticos (X-Content-Type-Options, X-Frame-Options, HSTS, Referrer-Policy, Permissions-Policy), CORS configurable por .env, sanitización de input, checklist de protecciones integradas |
| 2026-04-03 | OpenAPI/Swagger auto-generado (4.17): Atributos PHP se mapean directamente a OpenAPI 3.1 schema, endpoints /docs, /docs/swagger, /docs/redoc incluidos, CLI para exportar spec, atributos opcionales #[Description] y #[Example] para documentación enriquecida |
| 2026-04-03 | CLI Scaffolding y Dev Environment (4.18): Comando `php api init` interactivo (estilo npx), detección de Docker, generación de docker-compose.yml por driver, Dockerfile optimizado, comandos make:entity/service/middleware, `php api serve` para dev sin Docker, `php api env:check` para onboarding de nuevos developers |
| 2026-04-03 | File Uploads (4.19): Soporte multipart/form-data, objeto UploadedFile con validación de MIME (finfo) y tamaño, sanitización de nombres, integración con Services y APIDB híbridos. Storage externo queda como responsabilidad del desarrollador |
| 2026-04-03 | Docker-first workflow (4.18 ampliada): Imagen Docker publicada `coagus/php-api-builder` en Docker Hub, comando `docker run --rm -it` para init sin PHP local, script wrapper `./api` con detección automática PHP vs Docker, Dockerfile de la imagen con todas las extensiones PDO, tres flujos de onboarding documentados (PHP local, Docker-only, proyecto nuevo sin PHP) |
| 2026-04-03 | Dockerfile en repositorio (4.18): Ubicación en `docker/cli/Dockerfile`, CI/CD con GitHub Actions para publicar imagen en Docker Hub por release. Puertos configurables vía .env con sintaxis `${VAR:-default}`, detección automática de conflictos de puertos en `init` y `env:check`, sugerencia de puertos alternativos |
| 2026-04-03 | CI/CD Pipeline completo (4.20): Workflow GitHub Actions con 4 jobs encadenados — tests matrix PHP 8.3/8.4, auto-release con Conventional Commits (mathieudutour/github-tag-action + softprops/action-gh-release), Docker Hub publish (docker/build-push-action con cache GHA), Packagist API update. Secrets documentados. Conventional Commits como estándar de mensajes |
