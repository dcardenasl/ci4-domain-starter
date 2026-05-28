# Documentation Index

Documentation for **ci4-domain-starter** — a CodeIgniter 4 template for domain apps that delegate auth and IAM to a central hub. This index only lists files that exist in the template.

> **New here?** Start with the root [`README.md`](../README.md) for quickstart and the architecture diagram, then [`CLAUDE.md`](../CLAUDE.md) for working agreements. Spanish version: [README.es.md](README.es.md).

## 🔑 Hub integration (specific to domain apps)

The defining feature of this template — how the domain app delegates auth to the hub and registers its permissions.

- [Authentication & Hub Delegation](architecture/AUTHENTICATION.md) — `DomainAuthFilter`, `HubClient`, introspect / service-token flow.
- [JWT Validation](tech/jwt-auth.md) — pointer to the hub-side JWT contract.

## 🚀 Scaffolding learning path

If you've never scaffolded a CRUD in this kit, follow this order:

1. **Quickstart** — root [`README.md`](../README.md) § "Adding a new CRUD module".
2. **Canonical playbook** — [`template/CRUD_FROM_ZERO.md`](template/CRUD_FROM_ZERO.md) (field syntax, modifiers, post-scaffolding steps).
3. **Checklist before PR** — [`template/MODULE_BOOTSTRAP_CHECKLIST.md`](template/MODULE_BOOTSTRAP_CHECKLIST.md).
4. **Technical deep dive** — [`tech/scaffolding-engine.md`](tech/scaffolding-engine.md).
5. **Architecture contract (SSOT)** — [`template/ARCHITECTURE_CONTRACT.md`](template/ARCHITECTURE_CONTRACT.md).

## 🏗️ Architecture deep dives

- [Overview](architecture/OVERVIEW.md)
- [Layers](architecture/LAYERS.md)
- [Request Flow](architecture/REQUEST_FLOW.md)
- [Services](architecture/SERVICES.md)
- [Validation](architecture/VALIDATION.md)
- [Responses](architecture/RESPONSES.md)
- [Filters](architecture/FILTERS.md)
- [Queries](architecture/QUERIES.md)
- [Patterns](architecture/PATTERNS.md)
- [Exceptions](architecture/EXCEPTIONS.md)
- [I18N](architecture/I18N.md)
- [Testing](architecture/TESTING.md)
- [Drift Guide](architecture/DRIFT_GUIDE.md)
- [Extension Guide](architecture/EXTENSION_GUIDE.md)
  Includes the aggregate-extension pattern: when to stop treating a scaffold as flat CRUD and how to evolve it into custom actions, nested resources, relation sync, and response enrichment.

## ⚙️ Technical guides

- [Scaffolding Engine](tech/scaffolding-engine.md)
- [OpenAPI](tech/openapi.md)
- [CORS](tech/cors.md)
- [Pagination](tech/pagination.md)
- [Rate Limiting](tech/rate-limiting.md)
- [Request Logging](tech/request-logging.md)
- [Monitoring & Health](tech/monitoring-health.md)
- [Audit Logging](tech/audit-logging.md)
- [Audit Operations](tech/audit-operations.md)
- [Queue](tech/QUEUE.md)
- [Transactions](tech/transactions.md)
- [Testing Guidelines](tech/TESTING_GUIDELINES.md)

## 📋 Template standards

- [Module Bootstrap Checklist](template/MODULE_BOOTSTRAP_CHECKLIST.md)
- [CRUD From Zero](template/CRUD_FROM_ZERO.md)
- [Quality Gates](template/QUALITY_GATES.md)
- [Versioning Policy](template/VERSIONING_POLICY.md)
- [Contribution Rules](template/CONTRIBUTION_RULES.md)
- [Architecture Contract (SSOT)](template/ARCHITECTURE_CONTRACT.md)

## 📐 ADRs (Architecture Decision Records)

- [0001 — Use DTO-First Architecture](adr/0001-use-dto-first-architecture.md)
- [0002 — Implement Repository Pattern](adr/0002-implement-repository-pattern.md)
- [ADR-004 — Observability Governance](adr/ADR-004-OBSERVABILITY-GOVERNANCE.md)
- [ADR-005 — Service Purity & DI](adr/ADR-005-SERVICE-PURITY-DI.md)
- [ADR-006 — Feature Toggle Policy](adr/ADR-006-FEATURE-TOGGLE-POLICY.md)
- [ADR-007 — Service Return Contracts](adr/ADR-007-SERVICE-RETURN-CONTRACTS.md)
- [ADR-008 — API Versioning & Deprecation](adr/ADR-008-API-VERSIONING-AND-DEPRECATION.md)
- [ADR-009 — Idempotency Keys](adr/ADR-009-IDEMPOTENCY-KEYS.md)
- [ADR-010 — Problem Details (RFC 7807)](adr/ADR-010-PROBLEM-DETAILS-RFC-7807.md)
- [ADR-011 — Multi-Tenancy Out of Scope](adr/ADR-011-MULTI-TENANCY-OUT-OF-SCOPE.md)
- [ADR-012 — Config Runtime Mutability](adr/ADR-012-CONFIG-RUNTIME-MUTABILITY.md)

## 🛠️ Runbooks

- [01 — Rotate JWT Secret](runbooks/01-rotate-jwt-secret.md)
- [02 — Failed Migration Recovery](runbooks/02-failed-migration-recovery.md)
- [03 — Upgrade CI4 Minor](runbooks/03-upgrade-ci4-minor.md)
- [04 — Incident: Token Leak](runbooks/04-incident-token-leak.md)

## 🔍 Audits

- [make-crud audit](audits/make-crud-audit.md)

## 📎 Agent & scope

- [Agent Quick Reference](AGENT_QUICK_REFERENCE.md)
- [Documentation Scope](DOCUMENTATION_SCOPE.md)

---

> **Out of scope here, lives on the hub:** login, logout, password reset, email verification, Google OAuth, JWT issuance/refresh, IAM admin endpoints, file storage drivers. See the hub's `docs/` for those.
