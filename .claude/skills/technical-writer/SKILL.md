---
name: technical-writer
description: Expert on developer documentation — READMEs, architecture docs, code examples, API references, changelogs. Use when writing or reviewing user-facing docs for the php-api-builder library. Covers audience analysis, structure, voice, example quality, and common anti-patterns in developer docs.
---

# Technical Writing — Docs Developers Actually Read

This skill is for writing docs that get used. Bad docs are worse than no docs — they waste reader time and erode trust.

## Know your reader in 3 seconds

Every doc has a reader in a specific state:
- **Evaluating** — "should I use this library?" → wants a 30-second pitch, a runnable example, and a feature matrix.
- **Getting started** — "how do I set it up?" → wants exact commands in order, no prose digressions.
- **Using** — "how do I do X?" → wants a recipe, not a philosophy.
- **Debugging** — "why did it break?" → wants error messages mapped to fixes.
- **Extending** — "how does it work inside?" → wants architecture and design rationale.

A good doc tells the reader within the first screen which mode it's written for. Mixing modes is the #1 cause of dense, skippable walls of text.

## Structure of a great README

In order:

1. **One-line description** — what it is, in the first paragraph, no marketing adjectives.
2. **Badges** — stable version, license, build status. Useful, not decorative.
3. **The hook** — a single small code example that communicates the library's essence. The `php-api-builder` README does this well: a Product entity with attributes, followed by "that's it — you have a CRUD API".
4. **Features list** — short bullets, ~10 items max. Each one should be a single noun phrase or short sentence.
5. **Quick start** — the shortest path to a working result. Exact commands. Copy-paste should work.
6. **Core concepts** — the minimum vocabulary to understand everything else.
7. **Task-oriented recipes** — "how to do X". One section per common task.
8. **Reference** — exhaustive lists: CLI commands, attributes, config vars.
9. **Architecture/design** — only if it helps users; otherwise push to a separate file.
10. **Installation, requirements, license, author** — at the bottom.

## Voice

- **Direct.** "Define your entities." Not "You can optionally consider defining your entities."
- **Present tense.** "The library generates CRUD endpoints." Not "will generate".
- **Second person.** "You add `#[Required]`." Not "one adds" or "the developer should add".
- **Active voice.** "The ORM executes the query." Not "the query is executed".
- **No marketing.** Strip "powerful", "elegant", "robust", "cutting-edge", "seamless". They don't help and they undermine credibility.
- **No apologies.** "Unfortunately the syntax is..." — just state it. The reader doesn't need your feelings.

## Code examples

### Examples must be runnable
Copy-paste should work. If an example depends on something not shown, either show it inline or say exactly where it is. Bad:
```php
// somewhere you have a User entity
$user = User::find(1);
```
Good:
```php
// Assumes you have entities/User.php extending Entity — see "Creating an Entity" above.
$user = User::find(1);
```

### Minimal complete
Show the least code needed to demonstrate the point. Every extra line is a chance for the reader to get distracted.

### Realistic, not toy
`User`, `Order`, `Product`, `Post` — classic domains the reader has mental models for. Not `Foo`, `Bar`, `Baz`.

### Language tag on every block
```` ```php ```` , ```` ```bash ```` , ```` ```json ```` . Makes syntax highlighting work and signals the intended environment.

### Pair with annotation
When showing a non-obvious line, annotate inline:
```php
public private(set) int $id;    // public read, private write (PHP 8.4 asymmetric visibility)
```
Short, to the right, explains only the non-obvious.

## Section length

- A paragraph should be 1–4 sentences.
- A section (under one `##`) should fit in one screen — ~30 lines including code.
- If a section grows past that, either split it or move depth to a separate doc and link.

## Headings

- `#` once per doc (title).
- `##` for major sections.
- `###` for subsections. Rarely need `####`.
- Title case is fine and standard. Sentence case is also fine. Pick one per doc.
- Headings are nav landmarks — readable in isolation, specific: "Creating an entity" > "Entities".

## Lists

- Bullet lists: for enumerated items without sequence.
- Numbered lists: for sequential steps (installing, migrating).
- Prefer prose over bullets when items are related narrative — bulletpointing prose creates visual noise and hides connections.
- 3–8 items per list. More than 8 and you need sub-structure or a table.

## Tables

Tables beat prose when:
- Comparing properties across N things (attributes, commands, status codes).
- Documenting a mapping (convention → example).
- Listing options with descriptions.

Don't table just to format — if a 3-item list works fine as bullets, keep it as bullets.

## Anti-patterns to avoid

- **Marketing prose in technical docs.** "Our powerful ORM leverages cutting-edge PHP 8.4 features" → "The ORM uses PHP 8.4 property hooks and attributes."
- **Redundant intros.** "In this section, we will discuss..." → cut. Start with the content.
- **Obvious explanations.** "This command runs the command" → cut.
- **TODO/placeholder sections.** Either write the section or delete the heading. A visible TODO is a trust-killer.
- **Mystery meat links.** "Read more [here](...)" → say what's on the other side: "Read the [JWT rotation design doc](...)".
- **Stale examples.** If the library's API changed and the README still shows the old version, it's worse than useless. When a change alters an example, update the doc in the same PR.
- **Changelogs buried inside READMEs.** Changelog = separate file. README describes the current version.
- **Step-by-step tutorials inside the README.** Move long tutorials to `resources/docs/` and link.

## Cross-referencing

- Link to anchors within the same doc: `[see "Query Builder"](#query-builder)`.
- Link to other files by relative path: `[details](resources/docs/01-analisis-y-diseno.md)`.
- Never link to specific line numbers — they drift.
- Don't link the word "here". Link the meaningful phrase.

## Updating vs rewriting

When a feature changes, the default should be **surgical edits**, not rewrites:
- Find the existing section that covers the area.
- Update the minimum needed to reflect the change.
- Preserve surrounding style and tone.

A rewrite is justified when:
- The existing section is objectively confusing (verified by reader feedback).
- The underlying concept changed fundamentally (rare).

## Examples of good doc decisions

### Feature added → update README
- Append a subsection under the most relevant section (not a new top-level heading unless the feature is major).
- Add the feature to the features list (keep list under 10 items; demote older items if needed).
- If there's a CLI command, add it to the CLI reference.

### Breaking change → escalate
- Flag as BREAKING in the update brief.
- Don't silently update — the orchestrator or maintainer should review.
- Add a migration note in CHANGELOG (separate from README).

### New concept → consider a reference doc
- If it needs more than ~40 lines, put it in `resources/docs/`.
- Summarize in the README with a link.

## Checklist before "done"

- [ ] Opens with a clear one-liner of what this doc covers.
- [ ] Audience mode is consistent (evaluating / using / extending).
- [ ] All code blocks have language tags.
- [ ] All code examples are copy-paste runnable (or note what's assumed).
- [ ] No marketing adjectives.
- [ ] No TODOs or placeholders.
- [ ] Links have meaningful anchor text.
- [ ] Section lengths are scannable.
- [ ] No duplicate content — either cross-reference or consolidate.
- [ ] Tone matches the rest of the codebase.
