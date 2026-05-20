# Release procedure — ci4-domain-starter

This document describes how to publish a new release of `ci4-domain-starter`. The repo is versioned **only by git tags** (`composer.json` does not carry a `version` field — it is `type: project`). A tag push on `main` triggers `.github/workflows/release.yml`, which extracts the matching `## [VERSION]` block from `CHANGELOG.md` and creates the corresponding GitHub Release.

## Pre-flight checklist

Before tagging, every item below must be true. Treat any "no" as a blocker.

1. **`dev` is green on CI.** `.github/workflows/ci.yml` is passing on the latest `dev` commit (matrix PHP 8.2 / 8.3 + MySQL 8.0, `composer quality`, coverage gate).
2. **Working tree is clean.** `git status --porcelain` returns nothing on `dev`.
3. **Local quality gate passes.**
   ```bash
   COMPOSER_PROCESS_TIMEOUT=1800 composer quality
   vendor/bin/phpunit
   php spark swagger:generate
   git diff --exit-code public/swagger.json     # zero drift
   ```
4. **`CHANGELOG.md` has a dated `## [X.Y.Z]` section** at the top (under `## [Unreleased]`, which should be empty). The version string in the heading must match the tag you will push (without the `v` prefix — `1.0.0`, not `v1.0.0`).
5. **`composer.json` constraint for `dcardenasl/ci4-api-core` is a published version, not `dev-main`.** Before tagging:
   ```bash
   grep -E '"dcardenasl/ci4-api-core"' composer.json
   ```
   The output must show a concrete constraint (e.g. `"^0.4.1"`), never `"dev-main"`. The workspace's path repository remains in place (marked `canonical: false`) for local cross-edit, but the published constraint is what downstream consumers will resolve via Packagist.
6. **README badges match.** `Status:` in `README.md` / `README.es.md` reflects the version you're about to tag.
7. **Fresh-clone smoke** is green against a running hub (`ci4-api-starter` on :8080 with the app registered and an X-App-Key issued):
   ```bash
   cd /tmp && rm -rf domain-smoke && git clone --depth 1 -b dev <repo> domain-smoke && cd domain-smoke
   bash init.sh
   ```
   Verifies `composer install`, migrations, `domain:sync-permissions` against the hub, and the dev server boot.

For a major release (`X.0.0`), also confirm:

- Any `### ⚠️ Breaking Changes` and `### Migration Guide` blocks in the `[X.0.0]` section accurately describe the upgrade path from the previous minor.
- Any ADRs introduced this cycle are listed under `### Added`.
- The `[X.0.0]: …compare/vX-1.Y.Z...vX.0.0` link at the bottom of `CHANGELOG.md` resolves on GitHub.

## Release steps

The branching model is `dev → main → tag`. Tags are always cut from `main`.

1. **On `dev`, land the release-marker commit.** This commit only finalises `CHANGELOG.md` (rename `[Unreleased]` → `[X.Y.Z] — YYYY-MM-DD`, add a fresh empty `[Unreleased]` on top) and the matching `Status:` lines in `README.md` / `README.es.md`. The `composer.json` constraint swap should land as a **separate prior commit** so the release marker remains scoped to changelog + badges.
   ```bash
   git checkout dev
   git pull --ff-only
   # Edit CHANGELOG.md + README badges
   git add CHANGELOG.md README.md README.es.md
   git commit -m "chore: release vX.Y.Z"
   git push origin dev
   ```
2. **Merge `dev` into `main`.** Open a PR and merge fast-forward (or via a merge commit, depending on repo policy). Do not squash — the release marker commit should survive.
   ```bash
   # Via the GitHub UI (preferred) or:
   git checkout main && git pull --ff-only
   git merge --ff-only dev
   git push origin main
   ```
3. **Tag and push.** The tag must be created **from `main`**, not from `dev`. The workflow checks out the tag at the matching commit.
   ```bash
   git checkout main
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```
4. **Watch the workflow.** `.github/workflows/release.yml` will:
   - Check out the tag.
   - Run an inline `awk` over `CHANGELOG.md` to extract the body between `## [X.Y.Z]` and the next `## [` heading.
   - Create the GitHub Release with that body as the release notes. If the release already exists (re-tag scenario), it edits the existing one instead of failing.
5. **Verify the release page.** Open `https://github.com/dcardenasl/ci4-domain-starter/releases/tag/vX.Y.Z` and confirm the notes match the `[X.Y.Z]` block of `CHANGELOG.md`. If the workflow extracted an empty body, the most likely cause is a heading mismatch (stray trailing spaces or wrong version-string casing).

## Post-release

- Confirm `[Unreleased]` exists on `dev` and is empty so the next cycle has a clean target.
- If `ci4-kickstart` or downstream generated domain projects need to bump their template snapshot, open the matching PRs.
- Update `TASKS.md` (workspace-level and per-repo) to close any items the release shipped. Archive completed entries in `TASKS_ARCHIVE.md`.

## Rollback

A tag push triggers the release workflow exactly once. If the release notes are wrong, **prefer editing the GitHub Release directly** (the workflow is idempotent on re-tag and will overwrite the notes from `CHANGELOG.md`).

A bad tag can be retracted with:
```bash
git tag -d vX.Y.Z
git push --delete origin vX.Y.Z
```
This is only safe if **no downstream has pulled the tag yet**. Once a tag is consumed by Composer/Packagist, by another repo's CI, or by a contributor's clone, retraction can leave inconsistent state — prefer a follow-up `vX.Y.(Z+1)` patch release with a corrective `CHANGELOG.md` entry.

## Notes specific to this repo

- **`composer.json` constraint swap.** During pre-1.0 work, `composer.json` carried `"dcardenasl/ci4-api-core": "dev-main"` to consume the workspace path repository. Before tagging, the constraint must be flipped to a published Packagist version. The path repository is preserved with `"canonical": false` so it remains available as a local override without blocking Packagist resolution. Land the pin in its own `chore: pin ci4-api-core to vX.Y.Z` commit, separate from the release marker, so the history reads cleanly.
- **Swagger drift.** `composer quality` runs `swagger-validate` (regenerates and `git diff --exit-code`s `public/swagger.json`). A drift here is always a missing `php spark swagger:generate` before commit, never the release procedure itself.
- **Hub coupling.** This template depends on a running `ci4-api-starter` hub at deploy time, not at tag time. A release does not need to coordinate with a hub release unless the changelog calls out a contract change (introspect payload, service-token shape, permission-registration endpoint, etc.).
- **Database migrations.** Unlike `ci4-admin-starter`, this template owns its own schema. A release that adds migrations must include them in the release-marker branch — there is no out-of-band migration step.
- **Permission catalog sync.** `php spark domain:sync-permissions` is idempotent and intended to be re-run after every release that adds entries to `Config\DomainPermissions::PERMISSIONS`. The release notes should call this out under `### Added` so operators remember to run it post-deploy.
- **Coverage gate.** Currently a soft-fail (`continue-on-error: true`) in CI. Releases are not blocked by coverage drops, but the line-coverage % printed by `coverage:check` should not regress materially between minor versions.
