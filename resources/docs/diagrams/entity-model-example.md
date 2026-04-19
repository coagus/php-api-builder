# Entity Model — Illustrative Example (Blog Demo)

```mermaid
erDiagram
    USER ||--o{ POST : authors
    USER ||--o{ COMMENT : writes
    POST ||--o{ COMMENT : has
    POST }o--o{ TAG : tagged_with
    POST_TAG }o--|| POST : links
    POST_TAG }o--|| TAG : links

    USER {
        int id PK
        varchar name
        varchar email UK
        varchar password
        tinyint active
        datetime created_at
    }

    POST {
        int id PK
        varchar title
        varchar slug
        text body
        varchar status
        int user_id FK
        datetime created_at
        datetime updated_at
        datetime deleted_at "nullable (SoftDelete)"
    }

    COMMENT {
        int id PK
        varchar body
        int post_id FK
        int user_id FK
        datetime created_at
    }

    TAG {
        int id PK
        varchar name UK
    }

    POST_TAG {
        int post_id PK "FK"
        int tag_id PK "FK"
    }
```

**Figure 4 — Illustrative entity model.** This is the schema shipped with `./api demo:install` (Blog API demo), not a schema the library itself requires — `php-api-builder` has no fixed data model. It shows the three relationship attributes the ORM supports: `#[BelongsTo]` (Post → User, Comment → Post, Comment → User), `#[HasMany]` (User → Posts, Post → Comments), and `#[BelongsToMany]` (Post ↔ Tag via the `post_tags` pivot). `POST.deleted_at` marks the `#[SoftDelete]` convention — soft-deleted rows are filtered out of queries by `Entity::find()` and `Entity::all()`. See `resources/demo/schema.sql` and `resources/demo/entities/`.
