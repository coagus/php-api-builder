---
name: skill-curator
description: Expert on authoring and maintaining Claude/Cowork skills — the markdown files with YAML frontmatter that teach AI agents how to do specialized work. Use when creating a new skill, revising an existing one, designing trigger descriptions, bumping semver, structuring references/, or deciding what belongs inline vs deferred. This skill is itself written using the principles it teaches.
---

# Skill Curator — Writing Skills for AI Agents

Skills are documentation aimed at a reader who is an LLM agent. Good skills change agent behavior; bad skills bloat context without improving anything.

## Anatomy of a skill

```
.claude/skills/<name>/
├── SKILL.md              # main file, loaded when triggered
└── references/           # optional, loaded on demand
    ├── deep-dive-A.md
    └── deep-dive-B.md
```

`SKILL.md` frontmatter:
```yaml
---
name: skill-name                    # kebab-case, matches folder
description: >                      # the trigger — this decides when to load
  One or two sentences describing when to use this skill and what it covers.
  List specific phrases the user might say.
---
```

Optional frontmatter fields (use sparingly):
- `license` — for redistributable skills.
- `version` — if tracking semver inside frontmatter rather than a CHANGELOG.
- `tags` — for discovery.

## The description field is the most important line

The description is matched against user requests to decide if the skill activates. Treat it like SEO for your agent:

- **Mention specific trigger phrases** the user might say: "create an entity", "add a JWT endpoint", "audit my API", etc.
- **Be specific about the domain**: "PHP 8.4", not "PHP". "REST APIs", not "APIs".
- **State boundaries**: "Use when... Do NOT use when...".
- **One clear purpose.** If the description straddles two purposes, split into two skills.

Bad description:
```yaml
description: Helps with PHP stuff.
```

Good description:
```yaml
description: Expert-level PHP 8.4 development practices. Use when writing, reviewing, or refactoring modern PHP — especially for property hooks, asymmetric visibility, typed properties, and PHP attributes. Covers PSR-12 style, strict types, exception design, enum usage, and common pitfalls.
```

## Content style — pattern-first, not explanation-first

Agents learn faster from canonical examples than from prose. Every section should open with code, then 1–3 sentences of context.

**Do this:**
````markdown
## Property hooks

```php
public string $email {
    set => strtolower(trim($value));
}
```

Property hooks run on assignment. Use for sanitization and validation. Two syntaxes exist: short form (`set => expr`) and long form (`set { ... }`).
````

**Not this:**
```markdown
## Property hooks

PHP 8.4 introduces property hooks, which are a powerful feature that allows developers to
define logic that runs when a property is accessed or assigned. This is useful for various
scenarios including input validation, data normalization, and computed properties...
```

## One canonical way

If multiple valid patterns exist, pick the preferred one and show only that in `SKILL.md`. Alternatives go to `references/<topic>-alternatives.md`.

Why: agents copy what they see. Showing five ways to do the same thing means the agent will randomly pick one — often the wrong one for the project's context.

## No marketing, no hedging

- Cut: "powerful", "simple", "elegant", "robust", "modern", "cutting-edge".
- Cut: "you should probably", "it may be advisable to", "in general".
- Write: declarative, direct facts.

## Length budgets

| Scope | Target | Hard cap |
|---|---|---|
| `SKILL.md` (main) | < 500 lines | 800 lines |
| A single `reference/` file | < 300 lines | 500 lines |
| Code block in SKILL.md | < 30 lines | 50 lines |
| A single section | < 30 lines | 50 lines |

If you exceed the target, move content to `references/`. An overstuffed SKILL.md hurts every agent that loads it.

## What goes in `references/`

- **Deep-dive explanations** beyond the canonical pattern.
- **Alternative approaches** not recommended by default but sometimes needed.
- **Edge cases** that most tasks won't hit.
- **Historical context / rationale** — why a choice was made.
- **Extended API reference** — complete option lists, flag exhaustion.

The main SKILL.md should link to these: `See references/jwt-rotation-deep-dive.md for the token family state machine.`

## Section ordering in SKILL.md

1. **Non-negotiable rules** first — rules that, if violated, are always wrong.
2. **Core patterns** — the canonical examples for the 80% case.
3. **Conventions & conventions** — style, naming, structure.
4. **Common operations** — how to do the specific frequent tasks.
5. **Anti-patterns** — what not to do, with examples.
6. **Checklist** — a final "before done" list.

Agents scan top-down; front-load the most important.

## Cross-referencing other skills

Use relative paths from the skill folder:
```markdown
For OWASP and auth patterns, see `../security-auditor/SKILL.md`.
```

Or mention by name when invocation is expected:
```markdown
The `api-validator` agent applies `security-auditor` on every run.
```

## Triggers and loading

A skill loads when the agent decides its description matches the task. So:
- The description must include the **literal phrases** agents will likely receive.
- Narrow triggers are better than broad ones — broad skills fire on unrelated tasks and waste context.
- If a skill is only ever loaded by another agent (not by user triggers), say so: "Invoked by the api-validator sub-agent; not meant to be loaded by top-level user requests."

## Versioning skills with SemVer

Maintain `CHANGELOG.md` next to `SKILL.md`:

```markdown
# Changelog — <skill-name>

Format: [Keep a Changelog](https://keepachangelog.com/), [SemVer](https://semver.org/).

## [1.3.0] - 2026-04-18

### Added
- Section on property hooks with the `set { ... }` long form.
- reference/property-hooks-advanced.md covering asymmetric hooks.

### Changed
- Rewrote "Immutability" section to show `readonly` classes as primary.

### Fixed
- Typo in "Enums" example.

## [1.2.0] - 2026-04-10
...
```

**Bump rules:**
- **MAJOR** — removed or contradicted a previously documented pattern (breaks downstream agent behavior).
- **MINOR** — new section, new pattern, new reference file (additive).
- **PATCH** — typos, clarifications, rephrasing.

## Quality review before publishing

Run this checklist against every new/updated skill:

- [ ] `name` in frontmatter is kebab-case and matches the folder name.
- [ ] `description` is specific, lists trigger phrases, states boundaries.
- [ ] Opens with non-negotiable rules, not fluff.
- [ ] Every non-trivial section starts with a code example.
- [ ] Code examples are runnable and realistic.
- [ ] One canonical way per problem.
- [ ] No marketing adjectives.
- [ ] Under length caps (or content spilled to `references/` with links).
- [ ] Links to sibling skills use relative paths.
- [ ] CHANGELOG entry with correct semver bump and date.
- [ ] An agent reading only this skill could follow the patterns without additional clarification.

## Common skill anti-patterns

### "The one-big-page" skill
Everything in SKILL.md, 1500+ lines. Agents can't tell what matters. **Fix:** ruthlessly move to references/.

### The duplicative skill
Overlaps 80% with an existing skill. **Fix:** consolidate, or scope narrower to the non-overlapping part.

### The vague skill
Description says "helps with code". **Fix:** specify — language, domain, scenarios.

### The prescriptive but empty skill
Lots of rules, no examples. **Fix:** every rule should have a code block showing what compliance looks like.

### The aspirational skill
Describes patterns the codebase doesn't actually use. **Fix:** align with the repo's reality, or mark aspirational sections clearly.

## When to create a new skill vs extend an existing one

- **Same domain, deeper coverage** → extend the existing skill, add references/.
- **Different domain** → new skill.
- **Different audience** (user-facing docs vs agent skill) → different files, not same.
- **Sub-specialty of an existing skill** → consider a `references/` file instead of a whole new skill.

Rule of thumb: if the description of a new skill substantially overlaps with an existing one, merge them.
