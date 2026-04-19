# Agentes de desarrollo — php-api-builder

Este directorio contiene un sistema de agentes especializados para desarrollar, validar, documentar y mantener la skill del library `coagus/php-api-builder` v2.

## Arquitectura

```
Tú (usuario)
    │
    ▼
php-api-dev ── orquestador, planifica y delega
    │
    ├── Build pipeline ──────────────────────────────────────────┐
    │                                                            │
    ├──► api-developer   ── escribe PHP (entities, services, etc.)│
    │         │                                                  │
    │         ▼                                                  │
    ├──► api-validator   ── tests, OWASP, RFC 7807, code quality │
    │         │                                                  │
    │   (si ≥1 Blocker → reintenta developer, máx. 3 ciclos)     │
    │   (si PASS → documenter + skill-updater en paralelo)       │
    │                                                            │
    ├──► api-documenter  ── README + resources/docs/ + diagramas │
    ├──► skill-updater   ── resources/skill/ + CHANGELOG semver  │
    │                                                            │
    └── Release pipeline ────────────────────────────────────────┘
              │
              ▼
         api-releaser  ── commit (Christian <christian@agustin.gt>)
                        → tag v2.0.0-alpha.N → push → gh release
                        → wait CI (≤12min) → Docker smoke test
                        → informe.md
              │
      (si CI FAIL → handoff a validator → developer → retry alpha.N+1)
```

## Cómo se usan

El agente principal es `php-api-dev`. No invoques a los sub-agentes directamente — el orquestador lo hace por ti con los briefs correctos.

Ejemplos de invocación:

- "Usa `php-api-dev` para agregar un atributo `#[InRange(min, max)]` que valide rangos numéricos."
- "Lánzame al `php-api-dev` para arreglar el bug en `SoftDelete` cuando hay relaciones `BelongsToMany`."
- "Pídele al `php-api-dev` que agregue un nuevo CLI `make:enum` para scaffoldear enums PHP."

El orquestador:
1. Lee la petición, explora el repo y crea un plan (tareas visibles en el panel).
2. Pregunta si algo está ambiguo (nuevo nombre de atributo, breaking change, etc.).
3. Delega a `api-developer`.
4. Delega a `api-validator`. Si falla, reintenta (máx. 3).
5. Al pasar, lanza `api-documenter` + `skill-updater` en paralelo.
6. Corre un gate final (`./api test`, `git diff --stat`) y reporta.

## Archivos

| Archivo | Rol |
|---|---|
| `php-api-dev.md` | Orquestador. Planifica y delega. No escribe código. |
| `api-developer.md` | Implementa en `src/` y `tests/`. No toca docs ni skill. |
| `api-validator.md` | Reporta PASS/FAIL. Read-only excepto su reporte en `/tmp/`. |
| `api-documenter.md` | Edita `README.md` y `resources/docs/`. No toca skill. |
| `skill-updater.md` | Edita `resources/skill/` + CHANGELOG semver. No toca código. |
| `api-releaser.md` | Commit/push/tag/release/wait-CI/Docker-smoke-test/informe. No toca código ni docs. |

## Skills de especialización

Cada sub-agente carga (con `Read`) una o más skills al inicio de cada corrida. Las skills viven en `.claude/skills/` y son la "biblioteca de experticia" del equipo.

| Skill | Usa | Contenido |
|---|---|---|
| `php-expert` | developer, validator | PHP 8.4 (hooks, asimetría, strict types), PSR-12, attributes, enums, pitfalls |
| `mysql-expert` | developer, validator | Esquemas, índices, PDO, tx, migraciones, patrones ALTER seguros |
| `postgres-expert` | developer, validator | JSONB, TIMESTAMPTZ, UUID, GIN, CTEs, RETURNING, RLS, EXPLAIN ANALYZE |
| `sqlite-expert` | developer, validator | WAL, type affinity, FTS5, JSON1, concurrencia, tests in-memory |
| `orm-patterns` | developer, validator | Active Record, N+1, eager/lazy, identity map, scopes, cascadas, bulk ops |
| `rest-api-design` | developer, validator, documenter | HTTP, status codes, RFC 7807, paginación, OpenAPI |
| `security-auditor` | validator | OWASP Top 10, JWT, CORS, secrets, SQL/XSS/CSRF, uploads |
| `code-quality-enforcer` | developer, validator | CC ≤ 10, método ≤ 40 líneas, nesting ≤ 3, SOLID, DRY, naming |
| `testing-pest` | developer, validator | Pest, fixtures, tiers unit/feature/integration, flakiness |
| `diagrams-expert` | documenter | Mermaid: flowcharts, sequence, ER, state, class, C4 |
| `technical-writer` | documenter, skill-updater, releaser | Voz, estructura de README, calidad de ejemplos |
| `skill-curator` | skill-updater | Cómo escribir skills para agentes, triggers, semver |
| `release-engineering` | releaser | SemVer, conventional commits, release notes, CI diagnosis, Docker verify |

**Mapeo agente → skills autocargadas:**

- `api-developer` → php-expert, rest-api-design, orm-patterns, testing-pest, code-quality-enforcer, mysql-expert, postgres-expert, sqlite-expert
- `api-validator` → code-quality-enforcer, security-auditor, rest-api-design, orm-patterns, testing-pest, php-expert, mysql-expert, postgres-expert, sqlite-expert
- `api-documenter` → technical-writer, diagrams-expert, rest-api-design
- `skill-updater` → skill-curator, technical-writer
- `api-releaser` → release-engineering, technical-writer

El orquestador NO carga estas skills — delega. Pero sí las conoce y puede referenciarlas en briefs cuando una tarea toque un área específica (p.ej. "presta especial atención a la sección de file uploads de security-auditor").

### Ritual de carga

Cada sub-agente arranca su system prompt con algo como:

```
## Specialization skills you MUST load first

Before reading anything else, use the Read tool on:
1. .claude/skills/php-expert/SKILL.md
2. .claude/skills/mysql-expert/SKILL.md
...
```

Eso garantiza que la experticia entre al contexto antes de tocar código.

### Extender el sistema de skills

Para agregar una nueva skill:
1. Crea `.claude/skills/<nombre>/SKILL.md` con frontmatter YAML (`name`, `description`).
2. (Opcional) Crea `.claude/skills/<nombre>/references/` para deep-dives.
3. Actualiza el agente que debe cargarla, agregándola a la lista "Specialization skills you MUST load first".
4. Actualiza la tabla de arriba.
5. Considera un CHANGELOG.md para la skill si vas a mantenerla versionada.

La skill `skill-curator` explica exactamente cómo escribir skills bien.

## Límites de responsabilidad (importantes)

Cada sub-agente tiene **fronteras estrictas** para evitar colisiones:

- Solo `api-developer` edita `src/` y `tests/`.
- Solo `api-documenter` edita `README.md` y `resources/docs/`.
- Solo `skill-updater` edita `resources/skill/`.
- Solo `api-validator` produce el veredicto PASS/FAIL.

El orquestador **nunca** hace el trabajo de un sub-agente — siempre delega.

## Política de reintentos

El validator clasifica hallazgos en tres niveles:

- **Blocker** — viola una regla del `code-quality-enforcer`, `security-auditor`, u otras skills críticas. Dispara **retry automático**.
- **Warning** — no ideal pero no bloqueante. Se reporta, no dispara retry.
- **Info** — observación neutral.

**Regla**: cualquier Blocker ≥ 1 → el orquestador relanza `api-developer` con la lista textual de Blockers (file, line, problem, suggested fix). El developer debe corregir **solo** eso. Máximo **3 ciclos**.

Si tras 3 ciclos aún hay Blockers, el pipeline se detiene, la tarea queda `in_progress` y el orquestador te pregunta: aceptar los Blockers como Warnings, relajar una regla, o tomar control manual.

## Diagramas en la documentación

El `api-documenter` genera o actualiza diagramas Mermaid en `resources/docs/diagrams/` cuando el cambio toca:

- Lifecycle de request / middleware chain → flowchart o sequence.
- Modelo de entidades → ER diagram.
- State machines (status, workflow) → stateDiagram-v2.
- Interacción cross-component (auth, rate limit, APIs externas) → sequence.
- Arquitectura de alto nivel (nuevo componente, dep externa) → C4 Context o Container.

Los diagramas viven uno-por-archivo con caption + contexto, y se linkean desde `01-analisis-y-diseno.md`.

## Flujo de release (api-releaser)

Cuando le dices al orquestador "release", "ship", "tag", "nueva versión" o similar, delega a `api-releaser`. Este agente es reutilizable — lo usas en cada release, no solo en el primero.

### Identidad de commits

Siempre: **`Christian Agustin <christian@agustin.gt>`** vía `git -c user.name=... -c user.email=...` por comando (sin tocar config global).

### Canal de versiones

Política actual: **stay on `alpha`** hasta que expresamente digas "move to beta". Cada release bumpea el número alpha más alto (alpha.18 → alpha.19 → alpha.20 …).

### Pipeline de 8 fases

1. **Pre-flight** — verifica branch main, remote alcanzable, `gh auth`, `docker compose`.
2. **Commits atómicos por módulo** — Conventional Commits (`feat(orm)!:`, `fix(auth):`, etc.). Nunca `git add -A`.
3. **Version bump** — detecta el alpha más alto y calcula el siguiente.
4. **Release notes** — drafting con el template del skill `release-engineering` (Features / Improvements / Security / Bug Fixes / Breaking / Technical / Migration / Installation), estructura basada en v1.2.8 pero mejorada.
5. **Tag + push** — annotated tag, push main + tag. Esto dispara el workflow `release.yml`.
6. **Wait CI** — `gh run watch` con exit status. Budget: 12 min (4 jobs: test, release, docker multi-arch, packagist).
7. **Smoke test** — clean-room Docker-only: `mkdir api-demo && docker run … init && docker compose up && ./api demo:install` + pruebas funcionales de todos los endpoints del Blog demo + auth flow + rate limit + OpenAPI.
8. **Informe** — `informe.md` con checklist completo + URLs (release, Docker, Packagist, CI run) + duración.

### Política de reintentos en fallo de CI

Si CI falla, `api-releaser` clasifica y reporta. El orquestador rutea según la clasificación:

| Clasificación | Ruta |
|---|---|
| `code-regression` | validator extrae Blockers de los logs → developer fixa → releaser retry alpha.N+1 |
| `dependency-conflict` | developer ajusta composer.json/lock → releaser retry alpha.N+1 |
| `flaky-test` | un retry sin cambio; si falla de nuevo, trata como `code-regression` |
| `infra-failure` | surface al usuario (no auto-fix) |
| `secrets-issue` | surface al usuario (DOCKERHUB_TOKEN, PACKAGIST_TOKEN expirados) |

**Nunca force-push, nunca borrar tags**. Cada intento fallido queda en historia; el próximo éxito es alpha.N+1.

Máximo **3 reintentos de release** (alpha.N, N+1, N+2). Si los 3 fallan, pausa y te consulta.

### Smoke test — por qué importa

CI verde significa "tests pasaron + imagen publicada". NO significa que la imagen funcione out-of-the-box. El smoke test hace lo que haría un usuario nuevo siguiendo el README: `docker run … init`, `demo:install`, `curl` a cada endpoint. Si falla aquí pero CI estaba en verde, hay un bug de empaquetado o bootstrap de alta prioridad.

Cleanup: si pasa todo, borra el directorio temporal. Si algo falla, **conserva** `api-demo/` para debug y te reporta la ruta.

## Versionado de la skill

Cada vez que `skill-updater` corre, bumpea la versión en `resources/skill/php-api-builder/CHANGELOG.md`:

- **MAJOR** — cambió un patrón documentado (breaking para agentes que usaban el viejo).
- **MINOR** — nueva capacidad (atributo, comando, hook, middleware).
- **PATCH** — correcciones de typos o clarificaciones.

## Extender el sistema

Si necesitas un nuevo sub-agente (por ejemplo `api-migrator` para gestionar migraciones de DB), crea el archivo `.md` aquí mismo con frontmatter YAML (`name`, `description`, `tools`, `model`) y agrega su paso al orquestador en `php-api-dev.md`.

## Modelos recomendados

- `php-api-dev` → `opus` (orquestación compleja).
- `api-developer`, `api-validator`, `api-documenter`, `skill-updater` → `sonnet` (tareas focalizadas, más rápido y barato).

Puedes ajustarlo editando el frontmatter `model:` de cada archivo.
