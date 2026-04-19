---
name: code-quality-enforcer
description: Expert on code quality audit — cyclomatic complexity, SOLID principles, DRY, method/class size, nesting depth, magic numbers, dead code, naming quality, performance smells, and maintainability. Use when auditing a pull request, enforcing quality gates, or deciding whether a change is ready to ship. Designed to be zero-tolerance: any violation becomes a Blocker that triggers a re-do.
---

# Code Quality Enforcer — Zero Tolerance Audit

This skill is used by the validator. The goal is not "be nice"; the goal is to catch bad code before it ships. Every rule here has teeth: violating it is a **Blocker** that sends the change back for correction.

## The posture

- Treat the code like it will be read 100x more than it's written.
- Assume the author will not be around to explain it.
- "It works" is not a passing bar. Working badly is still failing.
- Be specific. Vague criticism ("consider improving this") is useless. Point at the line and say what's wrong and what the fix is.

## Blocker thresholds (automatic fail)

| Metric | Threshold | Rationale |
|---|---|---|
| Cyclomatic complexity per method | > 10 | Unreadable, untestable, bug-prone |
| Method length (non-test) | > 40 lines | Doing too much; split |
| Class length (non-test) | > 400 lines | God class; extract |
| Nesting depth | > 3 | Inverted logic can always flatten it |
| Parameters per method | > 5 | Signals missing value object |
| Boolean parameters | > 1 per method | Call site unreadable; refactor to enum or named method |
| Public properties on entities | — | Fine (the library's pattern) |
| Public mutable fields on services | > 0 | Encapsulation violation |
| `TODO`/`FIXME`/`HACK` | > 0 new | Don't punt — file an issue or fix it |
| `dd()`/`var_dump()`/`print_r()` | > 0 in src | Debug code left behind |
| Magic numbers in logic | > 0 new | Extract a named constant |
| Dead code (unused methods/fields/imports) | > 0 new | Delete |
| Duplicated blocks > 10 lines | > 0 new | DRY — extract |

Anything at or above threshold → Blocker → change goes back to `api-developer` with the specific fix.

## Cyclomatic complexity — counting

A method's CC = 1 + number of branching points:
- Each `if`, `elseif`, `else if` adds 1.
- Each `case` in a `switch`/`match` adds 1.
- Each `&&`, `||`, `??` in a conditional adds 1.
- Each `catch` block adds 1.
- Each `for`, `foreach`, `while`, `do-while` adds 1.

A method with CC = 1 has no branches. CC = 10 has ten decision points.

**Rule**: CC > 10 → extract branches into methods or polymorphism.

## Nesting depth

```php
// BAD — nesting depth 5
public function foo() {
    if (A) {
        if (B) {
            if (C) {
                foreach ($items as $i) {
                    if (D) {
                        ...
                    }
                }
            }
        }
    }
}
```

**Cure**: guard clauses (early return) and extraction.

```php
// GOOD
public function foo() {
    if (!A) return;
    if (!B) return;
    if (!C) return;
    foreach ($items as $i) {
        if (!D) continue;
        ...
    }
}
```

Four levels of nesting is the pain threshold. Three is the soft cap. Two or fewer is good.

## SOLID — the five questions

### S — Single Responsibility
"This class/method does X" — if the sentence needs an "and" or a comma, it's doing too much.

Red flag: class named `UserManager`, `OrderHelper`, `DataProcessor`. Vague names mean vague responsibilities.

### O — Open/Closed
Adding a new payment method should not require editing `PaymentProcessor` — it should add a new class that plugs in. If every new behavior means modifying the same switch/if-chain, the abstraction is wrong.

Watch for growing `match` / `switch` on a type field — signal to extract a polymorphic hierarchy or strategy.

### L — Liskov Substitution
If `B extends A`, any code expecting `A` must work with `B`. Violations:
- Overriding a method to throw `UnsupportedOperationException`.
- Subclass weakens preconditions or strengthens postconditions.
- Subclass changes return type in a way the interface contract didn't allow.

### I — Interface Segregation
Don't make classes depend on methods they don't use. An entity that implements `ReadOnlyEntity, WritableEntity, SearchableEntity` is finer than one fat `Entity` interface.

For this library: the existing split (`Entity`, `Service`, `APIDB`, `Resource`) is right. Maintain it.

### D — Dependency Inversion
Depend on interfaces, not concretions. For this codebase that means:
- Middleware depends on `MiddlewareInterface`, not a specific middleware class.
- Storage (`RateLimitStore`, `LoggerInterface`) is injected, not newed up.
- Tests can swap in fakes without monkey-patching.

## DRY — but not blindly

Don't deduplicate incidentally similar code that happens to share structure but evolves independently. Example:

```php
// BAD — these look the same but will diverge; forcing them into a shared helper will hurt later
function validateUserEmail(string $e): bool { ... 3 lines ... }
function validateCustomerEmail(string $e): bool { ... 3 lines ... }
```

If both are validating email per RFC 5321, extract to `EmailValidator`. If one is checking business rules the other doesn't, keep them separate.

**Rule**: duplicated code > 10 lines, or duplicated logic referenced in ≥ 3 places with the same semantic meaning → extract.

## Naming

- Methods are verbs or verb phrases: `calculateTotal`, `findByEmail`, `normalizeAddress`.
- Classes are nouns: `User`, `OrderCalculator`, `EmailValidator`.
- Booleans: prefixed predicate — `isActive`, `hasPermission`, `canPublish`.
- Collections: plural or `-List` suffix — `users`, `orderItems`, `auditLogList`.
- Don't prefix with type — `$objUser`, `$strName`, `$arrItems` is 1990s PHP.

Red flags:
- `$data`, `$info`, `$result`, `$temp` — what kind?
- `$i`, `$j`, `$k` — fine only in tight loops.
- `doStuff`, `handle`, `process`, `manage` — too vague.
- Abbreviations — `usr`, `calc`, `fmt` — spell it out.

## Dead code

```bash
# Find unused imports
rg "^use " --glob '!vendor' src/ | sort -u
# (cross-reference with usage)

# Find unused private methods
# (depends on static analysis tool; Psalm and PHPStan can detect it)

# Find TODO/FIXME/HACK
rg "TODO|FIXME|HACK|XXX" src/
```

All three need to be 0 in new code. Dead code is noise — every reader has to verify it's unused.

## Magic numbers

```php
// BAD
if ($age > 17) { ... }
if (strlen($password) < 8) { ... }
sleep(3600);

// GOOD
private const LEGAL_ADULT_AGE = 18;
private const MIN_PASSWORD_LENGTH = 8;
private const SESSION_TIMEOUT_SECONDS = 3600;
```

Numbers without names are meaningless. Exception: 0, 1, -1, and self-evident dimensions (`% 2`, `* 100` for percent-to-float).

## Comments

- **Comments explain why, not what.** The code shows what.
- **Useless**: `// increment counter` above `$counter++`. Delete.
- **Useful**: `// DB-level constraint also enforces this, but we check here to return a friendly 422 instead of a 500.`
- **Dead comments**: commented-out code. Delete. Git history is the archive.
- **Docblocks**: for public API / library-facing methods, yes. For obvious getters, no.

## Performance smells

- **`SELECT *`** in high-traffic queries with wide tables.
- **Loading everything then filtering in PHP**: `User::all()->filter(fn ...)` instead of `User::query()->where(...)->get()`.
- **String concatenation in loops** (PHP handles this OK now, but bad habit).
- **`count($arr)` in a `for` condition**: recomputed each iteration — hoist to a variable.
- **Sorting in PHP what the DB should sort**: `usort($rows, ...)` vs `->orderBy(...)`.
- **Opening DB connections inside loops**: always reuse the singleton.
- **JSON-encoding large arrays in memory** when streaming is possible.
- **`file_get_contents` of big files** — use `fopen` + streaming.

## Error handling smells

- **Swallowed exceptions**: `try { ... } catch (\Exception $e) {}` with no log, no rethrow.
- **Generic catch-all**: `catch (\Throwable $t)` in a small scope — catch specific types.
- **Errors-as-return-values** mixed with exceptions inconsistently.
- **`@` error suppression**: almost always wrong. Use proper checks.
- **No context in thrown exceptions**: `throw new \Exception('bad')` vs `throw new EntityNotFoundException::forId($class, $id)`.

## Testing smells

(See `testing-pest` for the positive side.)

- Test has no assertions — just calls the code.
- Test asserts on error message strings (brittle).
- Multiple tests share mutable state.
- Test is coupled to implementation (asserts private methods called).
- Test name lies — says `it_rejects_invalid_email` but asserts on something else.
- Tests that always pass — delete or fix.
- Commented-out tests — delete.

## Consistency

- Same style throughout a file.
- Same style throughout a directory.
- Match neighbors' conventions even if you'd personally choose differently.

If the team uses spaces, don't sneak in a tab. If methods are ordered `public / protected / private`, maintain that. Consistency beats individual preference.

## What to report

When you find violations, format each one as a Blocker:

```
### Blocker — Cyclomatic complexity too high
File: src/ORM/QueryBuilder.php:142
Method: applyFilters
Current CC: 14 (threshold: 10)
Problem: The method branches on 14 different filter types in a single chain.
Suggested fix: Extract each filter type to a private method; dispatch via a match or strategy map.
```

The developer will paste this verbatim into their retry brief. Make it unambiguous.

## What NOT to flag

- Style preferences that aren't in the repo's conventions.
- Micro-optimizations that save microseconds.
- "It's not how I'd write it" without a concrete rule.
- Anti-patterns in vendor code.
- Pre-existing violations in files not touched by the PR (unless the PR directly makes them worse).

Scope matters. You audit **the change**, not the whole codebase, unless asked.

## Checklist for every audit

- [ ] Every changed method: CC ≤ 10, length ≤ 40 lines, nesting ≤ 3.
- [ ] Every new class: length ≤ 400 lines, single responsibility.
- [ ] No TODOs / FIXMEs / dead code added.
- [ ] No dd/var_dump/print_r in src.
- [ ] Magic numbers extracted to named constants.
- [ ] Named, predicate-style booleans (no > 1 bool params).
- [ ] SOLID: no god classes, no switch-on-type anti-patterns (unless explicitly a state machine).
- [ ] DRY: no > 10-line duplications introduced.
- [ ] Error handling: specific exceptions, no swallowing, context included.
- [ ] Performance: no obvious smells for the scale the code will run at.
- [ ] Naming: descriptive, consistent with repo.
- [ ] Consistency: matches style of surrounding code.
- [ ] Tests: actually test behavior; no brittle string-match assertions.
