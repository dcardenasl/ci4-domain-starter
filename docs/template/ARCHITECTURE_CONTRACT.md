# Architecture Contract — pointer

> **The authoritative document lives in the scaffolding package, not here.**
>
> Read it at: `vendor/dcardenasl/ci4-api-crud-maker/docs/ARCHITECTURE_CONTRACT.md`
> (or, in the upstream repo: `https://github.com/dcardenasl/ci4-api-crud-maker/blob/main/docs/ARCHITECTURE_CONTRACT.md`)

This file used to be a maintained copy of the contract, which led to drift between the two
versions. The drift was identified in the May 2026 audit. To prevent future divergence:

- The package's copy is the single source of truth for module-level architecture rules.
- This stub stays in place so existing links (CLAUDE.md, README.md, etc.) keep working.
- Do **not** edit the package's copy from this repo — change it in `ci4-api-crud-maker`
  and re-install via composer.

If `composer install` has not yet been run in this project, the file in
`vendor/dcardenasl/ci4-api-crud-maker/docs/` will not exist. Run `composer install` first.
