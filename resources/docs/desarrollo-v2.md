# PHP API Builder v2.0.0 — Guía de Desarrollo Paso a Paso

> Este documento es la guía de ejecución para Claude Code y/o Cursor.
> Cada fase indica exactamente qué construir, qué testear, y cuándo pasar a la siguiente.
>
> **Documento de arquitectura:** `resources/docs/01-analisis-y-diseno.md`
> **Skill de la librería:** `.claude/skills/php-api-builder/SKILL.md`
> **Código v1 de referencia:** `resources/php-api-builder-v1.33/` (en la carpeta padre del repo)
> **Rama de trabajo:** `feature/v2.0.0`

---

## Reglas Generales

1. **Lee la sección del documento de arquitectura antes de implementar cada paso.** El documento tiene todos los detalles, ejemplos de código, y decisiones técnicas ya tomadas. No improvises — sigue el diseño.
2. **Namespace:** `Coagus\PhpApiBuilder\` — ver sección 4.13 del doc de arquitectura.
3. **PHP mínimo:** 8.3 (recomendado 8.4 para property hooks).
4. **Cada fase termina con tests pasando.** No avances a la siguiente fase si los tests de la actual fallan.
5. **Commits con Conventional Commits:** `feat(scope):`, `fix(scope):`, `test(scope):`, `refactor(scope):`.
6. **No borres el código de v1** — está en la carpeta `resources/php-api-builder-v1.33/` como referencia pero NO forma parte del paquete v2.

---

## FASE 0 — Preparación del Proyecto

**Objetivo:** Estructura limpia del paquete, dependencias, autoload configurado.

### Pasos:

0.1. Lee la sección **4.13** del documento de arquitectura (Estructura de Archivos del Paquete v2) completa.

0.2. Actualiza `composer.json`:

```json
{
    "name": "coagus/php-api-builder",
    "description": "Build RESTful APIs in PHP in minutes. Define entities, get automatic CRUD, JWT auth, validation, and OpenAPI docs.",
    "version": "2.0.0-alpha.1",
    "type": "library",
    "license": "MIT",
    "homepage": "https://github.com/coagus/php-api-builder",
    "keywords": ["php", "api", "rest", "orm", "crud", "jwt", "openapi"],
    "require": {
        "php": "^8.3",
        "firebase/php-jwt": "^7.0",
        "monolog/monolog": "^3.5",
        "vlucas/phpdotenv": "^5.6"
    },
    "require-dev": {
        "pestphp/pest": "^3.0"
    },
    "autoload": {
        "psr-4": {
            "Coagus\\PhpApiBuilder\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "authors": [
        {
            "name": "Christian Agustin",
            "email": "christian@agustin.gt",
            "role": "Developer"
        }
    ],
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

0.3. Crea la estructura de directorios completa (solo directorios vacíos con `.gitkeep`):

```
src/
├── Auth/
├── Http/
│   └── Middleware/
├── ORM/
│   └── Drivers/
├── Validation/
│   └── Attributes/
├── Attributes/
├── Resource/
├── Helpers/
└── CLI/

tests/
├── Unit/
│   ├── ORM/
│   ├── Validation/
│   ├── Http/
│   ├── Auth/
│   └── Helpers/
├── Integration/
│   ├── ORM/
│   ├── APIDB/
│   └── Middleware/
└── Feature/
```

0.4. Borra los archivos de v1 del `src/` actual (el código de v1 sigue en `resources/php-api-builder-v1.33/` como referencia). Borra también `demo/`, los tests viejos, y los helpers globales.

0.5. Configura Pest:

```bash
composer install
./vendor/bin/pest --init
```

0.6. Crea `tests/Pest.php` y `tests/TestCase.php` base (ver sección 4.14).

### Verificación Fase 0:

```bash
composer dump-autoload
./vendor/bin/pest
```

El autoload debe funcionar sin errores. Pest debe ejecutar y reportar 0 tests, 0 assertions (no hay tests todavía, pero el framework funciona).

**Commit:** `feat(project): restructure for v2.0.0 — new namespace, Pest, directory structure`

---

## FASE 1 — Core: Connection + Drivers PDO

**Objetivo:** Conectar a MySQL, PostgreSQL y SQLite via PDO con un driver interface.

### Lee primero:
- Sección **4.11** (Capa de Base de Datos — PDO Multi-Driver) completa.
- Sección **5.7** (Constructor Property Promotion) para el estilo.

### Archivos a crear:

1.1. `src/ORM/Drivers/DriverInterface.php` — Contrato con métodos para dialecto SQL.

1.2. `src/ORM/Drivers/MySqlDriver.php` — Implementación MySQL (quoting, LIMIT syntax, date functions).

1.3. `src/ORM/Drivers/PostgresDriver.php` — Implementación PostgreSQL.

1.4. `src/ORM/Drivers/SqliteDriver.php` — Implementación SQLite.

1.5. `src/ORM/Connection.php` — Singleton PDO. Métodos: `configure()`, `getInstance()`, `query()`, `execute()`, `transaction()`. Detecta driver desde config y carga el DriverInterface correcto.

1.6. `src/Helpers/Utils.php` — Funciones de conversión de nombres: `camelToSnake()`, `snakeToCamel()`, `tableize()` (clase → tabla). SIN funciones globales, todo como métodos estáticos.

### Tests a crear:

1.7. `tests/Unit/ORM/ConnectionTest.php`:
- Test que Connection es singleton.
- Test que se configura con array de settings.
- Test que lanza excepción si no está configurado.

1.8. `tests/Unit/ORM/DriversTest.php`:
- Test que MySqlDriver, PostgresDriver, SqliteDriver implementan DriverInterface.
- Test que cada driver genera el quoting correcto de identificadores.

1.9. `tests/Integration/ORM/ConnectionIntegrationTest.php`:
- Usando SQLite in-memory: conectar, crear tabla, insertar, leer.
- Test de transacciones: commit y rollback.

### Verificación Fase 1:

```bash
./vendor/bin/pest
```

Todos los tests de Connection y Drivers deben pasar. La conexión SQLite in-memory debe funcionar.

**Commit:** `feat(orm): add PDO Connection singleton with multi-driver support (MySQL, PostgreSQL, SQLite)`

---

## FASE 2 — Attributes PHP (Metadata del Sistema)

**Objetivo:** Definir todos los atributos PHP que el sistema usa como metadata.

### Lee primero:
- Sección **4.9** (Sistema de Validación por Atributos).
- Sección **5.3** (Atributos PHP 8.0+ para metadata).
- La tabla de atributos completa en el SKILL.md.

### Archivos a crear:

2.1. **Atributos de Entidad** en `src/Attributes/`:
- `Table.php` — `#[Table('nombre_tabla')]`
- `PrimaryKey.php` — `#[PrimaryKey]`
- `SoftDelete.php` — `#[SoftDelete]`
- `Route.php` — `#[Route('custom-path')]`
- `PublicResource.php` — `#[PublicResource]`
- `Middleware.php` — `#[Middleware(Class::class)]`

2.2. **Atributos de Relación** en `src/Attributes/`:
- `BelongsTo.php` — `#[BelongsTo(Role::class)]`
- `HasMany.php` — `#[HasMany(Order::class)]`
- `BelongsToMany.php` — `#[BelongsToMany(Tag::class)]`

2.3. **Atributos de Validación** en `src/Validation/Attributes/`:
- `Required.php`
- `Email.php`
- `Unique.php`
- `MaxLength.php`
- `MinLength.php`
- `Min.php`
- `Max.php`
- `Pattern.php`
- `In.php`
- `Hidden.php`
- `ReadOnly.php`
- `DefaultValue.php`

2.4. **Atributos de Documentación** (opcionales) en `src/Attributes/`:
- `Description.php` — `#[Description('texto')]`
- `Example.php` — `#[Example('valor')]`

### Tests a crear:

2.5. `tests/Unit/Validation/AttributesTest.php`:
- Crear una clase de test con atributos y verificar que se leen correctamente vía Reflection.
- Verificar que cada atributo guarda sus parámetros (`MaxLength(50)` → `$maxLength = 50`).

### Verificación Fase 2:

```bash
./vendor/bin/pest tests/Unit/Validation/
```

Todos los atributos deben poder instanciarse y leerse via `ReflectionAttribute`.

**Commit:** `feat(attributes): add all PHP attributes for entities, validation, relations, and docs`

---

## FASE 3 — Entity Base (Active Record)

**Objetivo:** La clase Entity con propiedades tipadas, lectura de atributos, y CRUD básico.

### Lee primero:
- Sección **4.2** (Estilo de ORM — Active Record Tipado) completa.
- Sección **4.1** (Paradigma OOP Moderno) — las reglas de visibilidad.
- Sección **4.6** (Hooks de Ciclo de Vida).
- Sección **5.1** y **5.2** (Property Hooks y Asymmetric Visibility).

### Archivos a crear:

3.1. `src/ORM/Entity.php` — Clase base abstracta:
- Lee `#[Table]` para determinar la tabla.
- Lee `#[PrimaryKey]` para el campo ID.
- Lee `#[SoftDelete]` para soft deletes.
- Métodos estáticos: `find($id)`, `all()`, `query()` (retorna QueryBuilder).
- Métodos de instancia: `save()` (insert o update), `delete()`, `fill($data)`, `toArray()`.
- El `toArray()` excluye campos con `#[Hidden]`.
- Hooks de ciclo de vida: `beforeCreate()`, `afterCreate()`, `beforeUpdate()`, `afterUpdate()`, `beforeDelete()`, `afterDelete()` — métodos protegidos vacíos que la subclase puede override.
- `save()` ejecuta INSERT si no tiene ID, UPDATE si lo tiene.
- Usa Connection para ejecutar queries.

3.2. `src/Validation/Validator.php` — Motor de validación:
- Recibe una Entity y lee sus atributos de validación vía Reflection.
- Valida cada campo según sus atributos (`#[Required]` → no puede ser null/vacío, `#[Email]` → filter_var, etc.).
- Retorna array de errores o null si todo es válido.
- Se ejecuta automáticamente en `Entity->save()` antes de persisir.

### Tests a crear:

3.3. Crear entidades de prueba en `tests/Fixtures/Entities/`:
- `TestUser.php` — Entidad completa con todos los atributos (#[Table], #[Required], #[Email], #[Hidden], #[SoftDelete], property hooks).
- `TestRole.php` — Entidad simple.
- `TestOrder.php` — Entidad con #[BelongsTo(TestUser::class)].

3.4. `tests/Fixtures/migrations.sql` — Schema SQLite para las entidades de prueba.

3.5. `tests/Unit/ORM/EntityMetadataTest.php`:
- Test que lee #[Table] correctamente.
- Test que lee #[PrimaryKey] correctamente.
- Test que lee #[SoftDelete].
- Test que toArray() excluye campos #[Hidden].

3.6. `tests/Unit/Validation/ValidatorTest.php`:
- Test #[Required] rechaza null y string vacío.
- Test #[Email] valida formato.
- Test #[MaxLength(50)] rechaza strings largos.
- Test combinación de atributos.
- Test que sin atributos, todo pasa.

3.7. `tests/Integration/ORM/EntityCrudTest.php`:
- Usando SQLite in-memory con las migraciones de prueba.
- Test save() nuevo registro (INSERT) → retorna con ID.
- Test save() registro existente (UPDATE) → modifica campos.
- Test find($id) → retorna entidad.
- Test all() → retorna array.
- Test delete() → elimina registro.
- Test soft delete → pone deleted_at, no borra realmente.
- Test que hooks se ejecutan (beforeCreate, afterCreate, etc.).
- Test que validación se ejecuta antes de save().

### Verificación Fase 3:

```bash
./vendor/bin/pest
```

CRUD completo funcionando contra SQLite. Validación bloqueando saves inválidos. Hooks ejecutándose. Campos #[Hidden] excluidos del output.

**Commit:** `feat(orm): add Entity base class with Active Record CRUD, validation, hooks, and soft deletes`

---

## FASE 4 — Query Builder (5 Niveles)

**Objetivo:** Query Builder fluent con prepared statements, los 5 niveles de complejidad.

### Lee primero:
- Sección **4.4** (Query Builder — 5 Niveles de Complejidad) completa. Tiene ejemplos de cada nivel.

### Archivos a crear:

4.1. `src/ORM/QueryBuilder.php`:
- Se instancia desde `Entity::query()`.
- Métodos fluent: `where()`, `orWhere()`, `whereIn()`, `whereBetween()`, `whereNull()`, `whereNotNull()`.
- `orderBy($field, $direction)`.
- `select($fields)`.
- `limit($n)`, `offset($n)`.
- `get()` → ejecuta y retorna array de Entities.
- `first()` → ejecuta con LIMIT 1 y retorna Entity o null.
- `count()` → SELECT COUNT(*).
- `paginate($page, $perPage)` → retorna datos + meta de paginación.
- `toSql()` → retorna el SQL generado (para debugging/testing).
- `getBindings()` → retorna los parámetros bind.
- **100% prepared statements** — NUNCA interpolación de strings.

4.2. Agregar al `Entity.php`:
- Scopes: método estático que recibe QueryBuilder y retorna QueryBuilder. El QueryBuilder detecta scopes via `__call` magic.

### Tests a crear:

4.3. `tests/Unit/ORM/QueryBuilderTest.php` — Tests de SQL generado SIN ejecutar:
- Test `where('active', true)` genera `WHERE active = ?` con bind `[true]`.
- Test `where('price', '>=', 100)` genera `WHERE price >= ?`.
- Test `whereIn('status', ['active', 'pending'])` genera `WHERE status IN (?, ?)`.
- Test `orderBy('created_at', 'desc')` genera `ORDER BY created_at DESC`.
- Test `select(['id', 'name'])` genera `SELECT id, name`.
- Test `limit(10)->offset(20)` genera `LIMIT 10 OFFSET 20`.
- Test encadenamiento múltiple genera SQL correcto.
- Test `toSql()` nunca contiene valores directos (solo `?`).

4.4. `tests/Integration/ORM/QueryBuilderIntegrationTest.php`:
- Contra SQLite in-memory con datos de prueba insertados.
- Test Level 1: `find()`, `all()`.
- Test Level 2: `where()`, `orderBy()`, `limit()`.
- Test Level 3: Eager loading (ver Fase 5 — lo pospones o haces stub).
- Test Level 4: Scopes (`->active()->recent()`).
- Test Level 5: `Connection::query()` con raw SQL.
- Test `paginate()` retorna data + meta correctos.

### Verificación Fase 4:

```bash
./vendor/bin/pest
```

Query Builder genera SQL seguro (parameterizado). Encadenamiento funciona. Paginación retorna meta correcta.

**Commit:** `feat(orm): add fluent QueryBuilder with 5 levels — where, orderBy, scopes, pagination, raw SQL`

---

## FASE 5 — Relaciones y Eager Loading

**Objetivo:** BelongsTo, HasMany, BelongsToMany + eager loading para evitar N+1.

### Lee primero:
- La parte de relaciones en las secciones **4.2** y **4.4** (nivel 3 del QueryBuilder).
- El ejemplo de eager loading en el SKILL.md.

### Archivos a crear/modificar:

5.1. Agregar al `Entity.php`:
- Resolución de `#[BelongsTo]`: carga la entidad padre por FK.
- Resolución de `#[HasMany]`: carga array de entidades hijas.
- Resolución de `#[BelongsToMany]`: carga via tabla pivot.

5.2. Agregar al `QueryBuilder.php`:
- Método `with('relation1', 'relation2')` para eager loading.
- Método `with('relation.nested')` para eager loading anidado.
- El eager loading hace queries separados agrupados (no N+1).

### Tests a crear:

5.3. `tests/Integration/ORM/RelationsTest.php`:
- Crear datos de prueba: users + roles + orders.
- Test BelongsTo: `$user->role` carga el Role.
- Test HasMany: `$user->orders` carga array de Orders.
- Test eager loading: `User::query()->with('orders')->get()` — verificar que solo hace 2 queries (users + orders), no N+1.
- Test eager loading anidado: `User::query()->with('orders.items')->get()`.

### Verificación Fase 5:

```bash
./vendor/bin/pest
```

Relaciones cargan correctamente. Eager loading reduce queries. N+1 eliminado.

**Commit:** `feat(orm): add BelongsTo, HasMany, BelongsToMany relations with eager loading`

---

## FASE 6 — Http Layer (Request, Response, Middleware Pipeline)

**Objetivo:** Objetos Request/Response y middleware pipeline PSR-15 inspired.

### Lee primero:
- Sección **4.5** (Middleware Pipeline — PSR-15 Inspired).
- Sección **4.8** (Sistema de Respuesta).
- Sección **4.16** (Seguridad — Headers y Hardening).

### Archivos a crear:

6.1. `src/Http/Request.php` — Parsea la petición HTTP entrante: method, URI, headers, body, query params.

6.2. `src/Http/Response.php` — Construye respuestas HTTP: status code, headers, body JSON.

6.3. `src/Http/Middleware/MiddlewareInterface.php` — Contrato con `handle(Request, callable $next): Response`.

6.4. `src/Http/Middleware/MiddlewarePipeline.php` — Ejecuta cadena de middlewares en orden.

6.5. `src/Http/Middleware/CorsMiddleware.php` — CORS configurable por .env (ver sección 4.16).

6.6. `src/Http/Middleware/SecurityHeadersMiddleware.php` — Headers OWASP automáticos (ver sección 4.16).

6.7. `src/Helpers/ApiResponse.php` — Formato de respuesta estándar: `{data, meta, links}` para success, RFC 7807 para errores.

### Tests a crear:

6.8. `tests/Unit/Http/RequestTest.php`:
- Test parseo de method, URI, body.
- Test extracción de query params.
- Test getInput() retorna objeto.

6.9. `tests/Unit/Http/ResponseTest.php`:
- Test formato JSON correcto.
- Test status codes.
- Test headers se agregan correctamente.

6.10. `tests/Integration/Middleware/PipelineTest.php`:
- Test que middlewares se ejecutan en orden.
- Test que un middleware puede cortar la cadena (retornar sin llamar $next).
- Test SecurityHeadersMiddleware agrega los headers correctos.
- Test CorsMiddleware responde a OPTIONS correctamente.

### Verificación Fase 6:

```bash
./vendor/bin/pest
```

Request parsea correctamente. Response genera JSON válido con status codes correctos. Pipeline ejecuta middlewares en orden.

**Commit:** `feat(http): add Request, Response, Middleware pipeline with CORS and security headers`

---

## FASE 7 — Resource Base + APIDB + Service

**Objetivo:** La jerarquía Resource → Service / APIDB con CRUD automático.

### Lee primero:
- Sección **4.8** (Sistema de Respuesta — Opción A).
- Sección **4.10** (Sistema Dual: Services vs Entities).
- Los ejemplos de Service, APIDB, e híbrido en el SKILL.md.

### Archivos a crear:

7.1. `src/Resource/Resource.php` — Clase base abstracta:
- `success($data, $code)`, `created($data)`, `noContent()`, `error($msg, $code)`.
- `getInput()`, `getQueryParams()`.
- `getUploadedFile($name)` (stub que se completa en Fase 11).

7.2. `src/Resource/Service.php` — Extiende Resource. Base para servicios puros sin DB.

7.3. `src/Resource/APIDB.php` — Extiende Resource. CRUD automático:
- Propiedad `protected string $entity` que define la Entity class.
- `get()` → Lista paginada con filtros, sorting, y sparse fields del query string. Si hay ID en la URL, retorna uno.
- `post()` → Crea nueva entidad, valida, retorna 201.
- `put($id)` → Reemplazo completo, valida, retorna 200.
- `patch($id)` → Actualización parcial, valida solo campos enviados, retorna 200.
- `delete($id)` → Elimina (o soft delete), retorna 204.
- Auto-wrap: las respuestas siguen el formato estándar automáticamente.
- Soporta métodos custom: `postLogin()`, `postActivate()`, etc.

### Tests a crear:

7.4. `tests/Integration/APIDB/GetTest.php`:
- Test GET lista retorna formato `{data, meta, links}`.
- Test GET con paginación `?page=2&per_page=5`.
- Test GET con sorting `?sort=-name,created_at`.
- Test GET con filtros `?filter[active]=true`.
- Test GET con sparse fields `?fields=id,name`.
- Test GET by ID retorna `{data: {...}}`.
- Test GET by ID inexistente retorna 404.

7.5. `tests/Integration/APIDB/PostTest.php`:
- Test POST válido retorna 201 con data.
- Test POST inválido (falta #[Required]) retorna 422 con errores RFC 7807.

7.6. `tests/Integration/APIDB/PutPatchTest.php`:
- Test PUT reemplaza todos los campos.
- Test PATCH actualiza solo campos enviados.

7.7. `tests/Integration/APIDB/DeleteTest.php`:
- Test DELETE retorna 204.
- Test DELETE con SoftDelete pone deleted_at.
- Test GET después de soft delete no retorna el registro.

### Verificación Fase 7:

```bash
./vendor/bin/pest
```

CRUD completo funcionando. Respuestas en formato estándar. Validación bloquea requests inválidos. Soft delete funciona.

**Commit:** `feat(resource): add Resource, Service, APIDB with auto CRUD, validation, pagination, filtering`

---

## FASE 8 — Router + API Entry Point

**Objetivo:** Routing desde URL a clase/método. API.php como orquestador principal.

### Lee primero:
- Sección **4.12** (Arquitectura de Componentes v2) — el diagrama de flujo completo.
- Sección **2.1** (Diseño de Recursos URLs) — la tabla de métodos y rutas.
- Revisa `resources/php-api-builder-v1.33/src/API.php` como referencia (pero reescribe, no copies).

### Archivos a crear:

8.1. `src/Router.php`:
- Parsea URL: `/api/v1/users/123/orders` → resource=`users`, id=`123`, subresource=`orders`.
- Discovery de clases: busca en el namespace del proyecto las clases que matchean el resource.
- Resolución de método: HTTP method + action → método de la clase (`GET` → `get()`, `POST /login` → `postLogin()`).
- Soporte para `#[Route('custom-path')]`.

8.2. `src/API.php` — Entry point:
- Constructor recibe namespace del proyecto.
- `middleware(array $classes)` para registrar middlewares.
- `run()` → crea Request, ejecuta pipeline de middlewares, llega al Router, despacha al Resource.
- Genera RequestID (ver sección 4.15).
- Try/catch global que delega a ErrorHandler.

8.3. `src/Helpers/ErrorHandler.php` — Captura excepciones, logea con contexto completo (ver sección 4.15), responde RFC 7807.

### Tests a crear:

8.4. `tests/Unit/RouterTest.php`:
- Test parseo de URL a resource/id/action.
- Test resolución de método HTTP a método de clase.
- Test que #[Route] custom override funciona.
- Test URL inválida retorna 404.

8.5. `tests/Feature/CrudFlowTest.php`:
- Test end-to-end: simular petición HTTP completa (POST crear → GET listar → GET by ID → PATCH actualizar → DELETE).
- Verificar status codes, formato de respuestas, y datos.

8.6. `tests/Feature/ErrorHandlingTest.php`:
- Test recurso inexistente → 404 RFC 7807.
- Test método no soportado → 405 RFC 7807.
- Test validación falla → 422 RFC 7807.
- Test error interno → 500 RFC 7807 con requestId.

### Verificación Fase 8:

```bash
./vendor/bin/pest
```

**Este es el milestone más importante.** Después de esta fase, la librería ya funciona end-to-end: una Entity con atributos genera un API REST completo con CRUD, validación, paginación, y manejo de errores.

**Commit:** `feat(core): add Router, API entry point, and ErrorHandler — end-to-end REST API working`

**Tag:** `v2.0.0-alpha.1` — Primera versión funcional.

---

## FASE 9 — Authentication (JWT + OAuth 2.1 Practices)

**Objetivo:** JWT auth con access tokens cortos, refresh tokens con rotación, scopes.

### Lee primero:
- Sección **2.10** (Autenticación y Autorización) completa — es la más detallada del documento.

### Archivos a crear:

9.1. `src/Auth/Auth.php` — JWT manager: `generateAccessToken()`, `generateRefreshToken()`, `validateToken()`, `decodeToken()`.

9.2. `src/Auth/RefreshTokenStore.php` — Storage de refresh tokens en DB. Rotación: cada uso genera nuevo refresh token e invalida el anterior. Detección de robo: si se usa un token ya rotado, revocar toda la familia.

9.3. `src/Auth/ScopeValidator.php` — Verifica que el token tenga los scopes necesarios para la operación.

9.4. `src/Auth/ApiKeyAuth.php` — Auth por API key para comunicación servicio-a-servicio.

9.5. `src/Http/Middleware/AuthMiddleware.php` — Middleware que valida JWT en el header Authorization. Excepciona para `#[PublicResource]`.

### Tests a crear:

9.6. `tests/Unit/Auth/JwtTokenTest.php`:
- Test generación de access token con claims correctos.
- Test validación de token válido.
- Test rechazo de token expirado.
- Test rechazo de token con firma inválida.

9.7. `tests/Unit/Auth/ScopeValidatorTest.php`:
- Test scope válido pasa.
- Test scope insuficiente falla.

9.8. `tests/Integration/Auth/RefreshTokenTest.php` (SQLite):
- Test rotación: usar refresh token → obtener nuevo par.
- Test reuse detection: usar refresh token ya rotado → revocar familia.

9.9. `tests/Feature/AuthFlowTest.php`:
- Test flujo completo: login → access token → request protegido → refresh → nuevo access token → revoke.
- Test recurso público (#[PublicResource]) accesible sin token.
- Test recurso protegido rechaza sin token → 401.
- Test recurso protegido con token expirado → 401.

### Verificación Fase 9:

```bash
./vendor/bin/pest
```

Auth funciona end-to-end. Tokens se generan, validan, rotan, y revocan correctamente.

**Commit:** `feat(auth): add JWT auth with OAuth 2.1 practices — access tokens, refresh rotation, scopes`

**Tag:** `v2.0.0-alpha.2`

---

## FASE 10 — Logging y Trazabilidad

**Objetivo:** Error-only logging con request ID y contexto completo.

### Lee primero:
- Sección **4.15** (Logging y Trazabilidad de Errores) completa.

### Archivos a crear:

10.1. `src/Helpers/RequestContext.php` — Singleton que almacena request ID, user ID, método, URI, entity, operación, input. Propagado por todas las capas.

10.2. `src/Helpers/LogFactory.php` — Crea logger Monolog configurado con JsonFormatter, RotatingFileHandler, y procesadores custom.

10.3. `src/Helpers/SensitiveDataFilter.php` — Sanitiza passwords, tokens, api keys de los logs.

10.4. Integrar logging en `ErrorHandler.php` — Cada error se logea con contexto completo de RequestContext.

### Tests a crear:

10.5. `tests/Unit/Helpers/SensitiveDataFilterTest.php`:
- Test que password → `***PROTECTED***`.
- Test que token → `***PROTECTED***`.
- Test que campos normales no se tocan.
- Test con arrays anidados.

10.6. `tests/Unit/Helpers/RequestContextTest.php`:
- Test set/get request ID.
- Test reset entre requests.

### Verificación Fase 10:

```bash
./vendor/bin/pest
```

**Commit:** `feat(logging): add error-only logging with request ID correlation and sensitive data protection`

---

## FASE 11 — File Uploads

**Objetivo:** Soporte básico para multipart/form-data con validación de MIME y tamaño.

### Lee primero:
- Sección **4.19** (Manejo de Archivos).

### Archivos a crear:

11.1. `src/Http/UploadedFile.php` — Objeto con: `originalName()`, `extension()`, `mimeType()` (via finfo), `size()`, `moveTo()`, `validateType()`, `validateMaxSize()`, `isValid()`.

11.2. Completar `Resource::getUploadedFile($name)` que retorna UploadedFile o null.

### Tests a crear:

11.3. `tests/Unit/Http/UploadedFileTest.php`:
- Test validación de MIME type.
- Test validación de tamaño.
- Test sanitización de nombre de archivo (sin path traversal).

### Verificación Fase 11:

```bash
./vendor/bin/pest
```

**Commit:** `feat(http): add file upload support with MIME validation and sanitization`

---

## FASE 12 — OpenAPI/Swagger Auto-Generation

**Objetivo:** Generar OpenAPI 3.1 spec desde los atributos de las entidades.

### Lee primero:
- Sección **4.17** (Documentación Automática — OpenAPI/Swagger) completa — tiene la tabla de mapeo de atributos PHP → OpenAPI.

### Archivos a crear:

12.1. `src/OpenAPI/SchemaGenerator.php` — Lee atributos de Entity via Reflection y genera el schema OpenAPI para cada entidad.

12.2. `src/OpenAPI/SpecBuilder.php` — Genera la spec completa: paths (de las clases descubiertas), components/schemas (de las entities), securitySchemes.

12.3. `src/OpenAPI/DocsController.php` — Service que responde a `/docs` (JSON), `/docs/swagger` (HTML Swagger UI), `/docs/redoc` (HTML ReDoc).

### Tests a crear:

12.4. `tests/Unit/OpenAPI/SchemaGeneratorTest.php`:
- Test que int → `type: integer`.
- Test que #[Required] agrega campo a `required`.
- Test que #[Hidden] excluye campo del schema.
- Test que #[MaxLength(50)] genera `maxLength: 50`.
- Test que #[PrimaryKey] genera `readOnly: true`.

12.5. `tests/Integration/OpenAPI/SpecBuilderTest.php`:
- Test que la spec generada es un JSON válido.
- Test que las rutas CRUD están presentes.
- Test que #[PublicResource] no tiene security requirement.

### Verificación Fase 12:

```bash
./vendor/bin/pest
```

**Commit:** `feat(openapi): auto-generate OpenAPI 3.1 spec from entity attributes`

**Tag:** `v2.0.0-beta.1` — Feature-complete, testing pendiente de estabilización.

---

## FASE 13 — CLI Tool

**Objetivo:** Comandos de scaffolding, server de desarrollo, y herramientas de entorno.

### Lee primero:
- Sección **4.7** (CLI Tool) y **4.18** (CLI — Scaffolding y Entorno de Desarrollo) completa.

### Archivos a crear:

13.1. `bin/api` — Entry point CLI (ejecutable).

13.2. `src/CLI/Application.php` — Dispatcher de comandos.

13.3. `src/CLI/Commands/InitCommand.php` — `./api init` interactivo.

13.4. `src/CLI/Commands/ServeCommand.php` — `./api serve` (PHP built-in server).

13.5. `src/CLI/Commands/EnvCheckCommand.php` — `./api env:check` con detección de Docker y puertos.

13.6. `src/CLI/Commands/MakeEntityCommand.php` — `./api make:entity` con `--fields` y `--soft-delete`.

13.7. `src/CLI/Commands/MakeServiceCommand.php` — `./api make:service`.

13.8. `src/CLI/Commands/MakeMiddlewareCommand.php` — `./api make:middleware`.

13.9. `src/CLI/Commands/MakeTestCommand.php` — `./api make:test`.

13.10. `src/CLI/Commands/KeysGenerateCommand.php` — `./api keys:generate` (RSA/EC key pair).

13.11. `src/CLI/Commands/DocsGenerateCommand.php` — `./api docs:generate`.

13.12. `src/CLI/Commands/SkillInstallCommand.php` — `./api skill:install`.

13.13. `src/CLI/Templates/` — Templates para archivos generados (entity, service, middleware, test).

13.14. Generar el wrapper script `api` (bash) que detecta PHP vs Docker (ver sección 4.18).

### Tests a crear:

13.15. `tests/Unit/CLI/MakeEntityCommandTest.php`:
- Test que genera archivo PHP válido.
- Test que `--fields` genera propiedades correctas.
- Test que `--soft-delete` agrega el atributo.

### Verificación Fase 13:

```bash
./vendor/bin/pest
php bin/api --help
```

Todos los comandos listados. `make:entity Test` genera un archivo válido.

**Commit:** `feat(cli): add scaffolding commands — init, serve, make:entity, make:service, env:check`

---

## FASE 14 — Docker Image + CI/CD

**Objetivo:** Dockerfile de la imagen publicable y workflow completo de GitHub Actions.

### Lee primero:
- Sección **4.18** (la parte de la imagen Docker y CI/CD).
- Sección **4.20** (CI/CD — Pipeline Completo) completa.

### Archivos a crear:

14.1. `docker/cli/Dockerfile` — La imagen `coagus/php-api-builder` publicable.

14.2. `.github/workflows/release.yml` — El workflow completo de 4 jobs: test → release → docker → packagist.

14.3. Actualizar `.gitignore` para v2: agregar `log/`, `keys/`, `.env`, `vendor/`.

### Verificación Fase 14:

```bash
# Test local del Dockerfile
docker build -t coagus/php-api-builder:dev -f docker/cli/Dockerfile .
docker run --rm coagus/php-api-builder:dev --help
```

La imagen se construye, ejecuta, y muestra la ayuda del CLI.

**Commit:** `feat(docker): add publishable CLI Docker image and full CI/CD pipeline`

---

## FASE 15 — Integración Final y Estabilización

**Objetivo:** Tests end-to-end completos, coverage, y estabilización.

### Pasos:

15.1. Ejecutar TODOS los tests:

```bash
./vendor/bin/pest --coverage
```

Verificar que coverage es ≥80% global, ≥90% ORM, ≥95% Auth y Validation.

15.2. Crear una **app demo** en `demo/` que demuestre todas las features:
- Entity con todos los atributos.
- Service puro.
- Híbrido con custom endpoints.
- Middleware custom.
- Auth JWT.
- Docker Compose funcional.

15.3. Revisar que el README.md refleja las APIs finales (ajustar si cambió algo durante desarrollo).

15.4. Verificar OpenAPI spec generada con la app demo.

15.5. Ejecutar type coverage:

```bash
./vendor/bin/pest --type-coverage --min=95
```

15.6. Test de la imagen Docker:

```bash
docker build -t coagus/php-api-builder:dev -f docker/cli/Dockerfile .
mkdir /tmp/test-api && cd /tmp/test-api
docker run --rm -it -v $(pwd):/app coagus/php-api-builder:dev init
# Verificar que genera proyecto completo
```

### Verificación Final:

```bash
./vendor/bin/pest --coverage --min=80
./vendor/bin/pest --type-coverage --min=95
```

**Si todo pasa:**

```bash
git tag v2.0.0
git push origin feature/v2.0.0
git push origin v2.0.0
```

Merge `feature/v2.0.0` → `main`. El CI/CD se encarga del release, Docker Hub, y Packagist.

**Commit final:** `chore: prepare v2.0.0 release — all tests passing, demo app complete`

**Tag:** `v2.0.0`

---

## Resumen de Fases y Dependencias

```
FASE 0  Preparación           → Base del proyecto
FASE 1  Connection + Drivers  → Depende de 0
FASE 2  Attributes PHP        → Depende de 0
FASE 3  Entity Base           → Depende de 1 y 2
FASE 4  Query Builder         → Depende de 3
FASE 5  Relaciones            → Depende de 4
FASE 6  Http Layer            → Depende de 0 (paralelo a 1-5)
FASE 7  Resource/APIDB        → Depende de 5 y 6
FASE 8  Router + API.php      → Depende de 7 ★ MILESTONE: API funcional
FASE 9  Auth JWT              → Depende de 8
FASE 10 Logging               → Depende de 8
FASE 11 File Uploads          → Depende de 6
FASE 12 OpenAPI               → Depende de 8
FASE 13 CLI                   → Depende de 8
FASE 14 Docker + CI/CD        → Depende de 13
FASE 15 Integración Final     → Depende de TODO
```

Fases 9, 10, 11, 12, y 13 pueden desarrollarse en paralelo después de la Fase 8.
