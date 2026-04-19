---
name: release-engineering
description: Expert on release engineering — semantic versioning, pre-release channels (alpha/beta/rc), conventional commits, release notes authoring, git tag strategy, GitHub Actions diagnosis, Docker Hub + Packagist verification, and post-release smoke testing. Use when preparing a release, writing release notes, investigating a CI failure, or designing a release workflow for the php-api-builder library.
---

# Release Engineering — Ship Confidently

Releases are promises to downstream users. Every tag pushed is a contract. This skill codifies how to ship them reliably.

## Non-negotiable rules

1. **Never force-push a release tag.** A published tag is immutable in users' minds — if they pulled it, they have it. If a release is broken, bump and move on.
2. **Tags must match real commits on the default branch.** No tagging from a feature branch, no tagging from a detached HEAD.
3. **Pre-flight first.** Never start a release without: clean `git status` (or consciously staged), correct branch, remote reachable, CI credentials present.
4. **Release notes are part of the release.** A tag without notes is half a release.
5. **The CI pipeline is the gate.** If CI fails, the release did not happen — regardless of whether the tag was pushed.

## SemVer for this project (PHP library)

```
MAJOR.MINOR.PATCH[-PRERELEASE][+BUILD]
```

- **MAJOR** — breaking API change (dropped method, changed signature, different behavior for same input).
- **MINOR** — backward-compatible new feature (new method, new attribute, new CLI command).
- **PATCH** — backward-compatible bug fix (no new capability).
- **PRERELEASE** — `alpha.N`, `beta.N`, `rc.N`. Ordering: `alpha < beta < rc < (stable)`.

### Current project state

The project has tags `v1.3.3` (last stable v1) and `v2.0.0-alpha.1..18`, plus `v2.0.0-beta.1`. The team is iterating on the v2 API in the alpha channel. **Next release in the alpha channel is always `v2.0.0-alpha.N+1`** where N is the current max alpha, regardless of whether a beta tag exists.

### When to change channel

- `alpha → beta` — when the public API surface is frozen (no more breaking changes expected).
- `beta → rc` — when all known blockers are fixed.
- `rc → stable` — when soaked in production without new issues for an agreed interval.

Channel changes require explicit user approval — never auto-promote.

## Conventional Commits

Use Conventional Commits so release notes can be auto-generated and commits communicate intent:

```
<type>(<scope>): <subject>

[optional body]

[optional footer]
```

### Types

- `feat` — new feature (MINOR bump).
- `fix` — bug fix (PATCH bump).
- `perf` — performance improvement.
- `refactor` — code change that doesn't add features or fix bugs.
- `docs` — documentation only.
- `test` — test code.
- `build` — build system, dependencies.
- `ci` — CI config.
- `chore` — maintenance, no production code change.
- `style` — formatting only.

### Breaking changes

Add `!` after the type/scope OR a `BREAKING CHANGE:` footer:

```
feat(orm)!: Entity::save now throws ValidationException

BREAKING CHANGE: callers catching RuntimeException for validation errors
must migrate to catch ValidationException and read $e->errors.
```

### Scope

For this project use the module as scope:

- `orm`, `drivers`, `auth`, `cli`, `http`, `middleware`, `resource`, `validation`, `attributes`, `openapi`, `helpers`, `exceptions`, `docs`, `skill`, `ci`, `deps`.

### Subject

- Imperative mood: "add", not "added" or "adds".
- Lowercase start.
- No trailing period.
- ≤ 72 chars.

### Good examples

```
feat(orm): add driver session settings on connect
fix(auth): validate iss/aud/sub/jti unconditionally
refactor(cli)!: ServeCommand uses proc_open with argv
docs(diagrams): add C4 container and request lifecycle
```

### Bad examples

```
updated stuff                    # vague, no type
Fix bug.                         # wrong case, trailing period
feat: did a bunch of things      # not imperative + vague
```

## Atomic commits

One commit = one logical change. Guidelines:

- A reviewer should be able to understand each commit independently.
- Every commit should build and pass its own tests (ideally).
- Never mix unrelated changes in a commit.
- Never commit dead code, debug leftovers, or secrets.

### When refactoring touches many files

Prefer `refactor(scope): <one-sentence what>` with the full diff over splitting artificially.

### When one feature spans multiple modules

Acceptable to have a single `feat(orm,resource): <subject>` commit listing both scopes — better than splitting a feature across 4 mid-flight commits that don't individually compile.

## Release notes — the template

Based on the v1.2.8 structure, improved. Every release note should have these sections (omit sections that are empty):

```markdown
## Version <version> (<date>)

<Optional: one-sentence highlight of what this release is about>

### 🎉 New Features
<Each feature with a sub-header, 2-4 sentences of what + why + an example>

### 🔧 Improvements
<Performance, code quality, UX improvements that aren't new features>

### 🛡️ Security
<Security fixes, hardening, posture improvements — put this HIGH in the note if any>

### 🐛 Bug Fixes
<With short repro or impact statement for each>

### 💥 Breaking Changes
<With before/after migration snippet for each>

### 📝 Technical Details
<Internal refactors worth knowing about for contributors>

### 🔄 Migration Notes
<Step-by-step migration for users upgrading from the previous version>

### 📦 Installation

\`\`\`bash
composer require coagus/php-api-builder:<version>
# or
docker pull coagus/php-api-builder:<version>
\`\`\`

### 🙏 Contributors
<If applicable; skip for solo releases>

**Full Changelog**: https://github.com/coagus/php-api-builder/compare/<previous>...<version>
```

### Emoji discipline

Use the section emojis above consistently. Do NOT sprinkle emoji throughout the prose. Emojis are section markers, not decoration.

### Writing the feature sections

Each feature gets:
- A bolded feature name.
- 1-2 sentences of what it does.
- 1 sentence of why it matters / use case.
- A small code block (≤ 10 lines) showing how to use it.

Bad (vague):
```
- Improved query builder
```

Good (specific):
```
**Column Allowlist on Query String Filters**

`?sort=` and `?fields=` now validate against the entity's public property list. Unknown or malicious columns are silently dropped — `?sort=id;DROP TABLE users` is rejected as invalid input without reaching SQL.

\`\`\`
GET /api/v1/users?sort=-createdAt,email&fields=id,name
\`\`\`
```

### Writing the breaking changes section

Every breaking change MUST include migration code:

```markdown
**`Entity::save()` now throws `ValidationException`**

Before:
\`\`\`php
try {
    $user->save();
} catch (\RuntimeException $e) {
    $errors = json_decode($e->getMessage(), true);
}
\`\`\`

After:
\`\`\`php
try {
    $user->save();
} catch (\Coagus\PhpApiBuilder\Exceptions\ValidationException $e) {
    $errors = $e->errors;
}
\`\`\`
```

## Git tag strategy

### Annotated tags only

```bash
git tag -a v2.0.0-alpha.19 -m "Release v2.0.0-alpha.19" \
    -m "See https://github.com/.../releases/tag/v2.0.0-alpha.19 for full notes"
```

Never lightweight tags (`git tag v...`). Annotated tags carry author, date, message — they're part of history.

### Tag after commit, before release

```bash
git commit -am "..."          # or per-module atomic commits
git tag -a v2.0.0-alpha.19 ...
git push origin main
git push origin v2.0.0-alpha.19    # triggers CI
```

### Signing (optional but recommended)

If the release signer has GPG/SSH keys set up, use `-s`:
```bash
git tag -s v2.0.0-alpha.19 ...
```

Signed tags verify the release came from an authorized signer.

## GitHub release creation

With `gh` CLI:

```bash
gh release create v2.0.0-alpha.19 \
    --title "v2.0.0-alpha.19" \
    --notes-file RELEASE_NOTES.md \
    --prerelease \
    --target main
```

- Always `--prerelease` for alpha/beta/rc. NEVER a stable flag on a pre-release.
- `--notes-file` — write notes to a file first, don't inline. Easier to review.
- `--target main` — explicitly pin the commit.

## GitHub Actions monitoring

### Watch a specific run

```bash
gh run watch <run-id> --exit-status
# or wait for the most recent run:
gh run watch --exit-status
```

### Poll for status without watching

```bash
gh run list --branch main --limit 1 --json status,conclusion,databaseId
# status: queued | in_progress | completed
# conclusion: success | failure | cancelled | null
```

### Timeout budget

For this project the pipeline is: test (~2-3 min) + release (~30 sec) + docker multi-arch build/push (~5-6 min) + packagist (~30 sec). **Realistic ceiling: 12 minutes.** Anything over 15 min suggests something is hung.

## CI failure diagnosis

When a run fails, extract actionable info:

```bash
# Find the failing job
gh run view <run-id> --json jobs --jq '.jobs[] | select(.conclusion=="failure")'

# Get the failing step's logs
gh run view <run-id> --log-failed

# Download all logs
gh run download <run-id>
```

### Parse logs for the actual error

Typical failure modes:

| Failure | Log signal | Likely cause |
|---|---|---|
| Test failure | `Tests: X failed, Y passed` in pest output | Real test regression |
| Syntax error | `Parse error:` or `syntax error, unexpected` | PHP 8.4 feature used against wrong PHP / typo |
| Composer install | `Your requirements could not be resolved` | Version conflict |
| Docker build | `ERROR: failed to solve` | Dockerfile issue |
| Docker push | `denied: requested access to the resource is denied` | Expired `DOCKERHUB_TOKEN` secret |
| Packagist update | curl 401/403 | Expired `PACKAGIST_TOKEN` secret |

The first three are code issues → route to api-validator → api-developer. The last two are secrets/infra → surface to the human, don't try to auto-fix.

## Retry policy for failed releases

A failed CI run means the tag is broken. Never rewrite it. Instead:

1. Keep the broken tag (historical record).
2. Optionally delete the corresponding GitHub release via `gh release delete <tag>` if it was auto-created by the workflow.
3. Fix code → new commit(s) → bump to next alpha (alpha.N+1) → tag → push → wait.

This preserves an honest history: "alpha.19 failed, alpha.20 succeeded".

## Docker image verification

After CI shows success, verify the Docker image actually works:

```bash
# Should pull without "manifest unknown"
docker pull coagus/php-api-builder:v2.0.0-alpha.19

# Quick smoke: run --version
docker run --rm coagus/php-api-builder --version 2>/dev/null || true

# Multi-arch: confirm both
docker manifest inspect coagus/php-api-builder:v2.0.0-alpha.19 | grep architecture
# Expect: amd64 and arm64
```

## Packagist verification

```bash
curl -s https://packagist.org/packages/coagus/php-api-builder.json | \
    jq '.package.versions | keys | map(select(. == "v2.0.0-alpha.19")) | length'
# Expect: 1
```

Packagist typically updates within 30 sec of the webhook. Wait up to 2 min before flagging.

## Post-release smoke test — the real confidence signal

CI going green means "tests passed and image was published". It does NOT mean the image actually works for a user doing `composer create-project` or `docker run ... init`. Always do a clean-room smoke test:

1. Create a throwaway directory.
2. Pull the newly published image.
3. Run the README's "Without PHP (Docker only)" flow verbatim.
4. Install the demo.
5. Curl every documented endpoint; verify shapes match the OpenAPI spec.
6. Tear down.

If the smoke test fails while CI succeeded, something in the packaging or bootstrap flow is broken — a high-priority issue.

## Release checklist (tie it all together)

- [ ] Pre-flight: clean working tree (or intentionally staged), on `main`, remote reachable, `gh auth status` OK.
- [ ] All commits follow Conventional Commits with correct type/scope.
- [ ] Commits are atomic (per-module where possible).
- [ ] Version number determined (next alpha from max existing `v2.0.0-alpha.N`).
- [ ] Release notes drafted in the section template, breaking changes with migration code.
- [ ] Annotated tag created with the version.
- [ ] Commits + tag pushed.
- [ ] `gh release create ... --prerelease --notes-file RELEASE_NOTES.md` succeeded.
- [ ] CI green: tests, release, docker build/push, packagist update.
- [ ] Docker image pullable with multi-arch manifest.
- [ ] Packagist shows the new version.
- [ ] Clean-room smoke test (docker init → demo:install → functional tests) passed.
- [ ] `informe.md` written with full checklist and evidence.

## What "done" looks like

A release where every checkbox above is green, the release note reads like documentation (not a changelog dump), the image pulled by a new user works without troubleshooting, and the report in `informe.md` leaves zero ambiguity about what happened.
