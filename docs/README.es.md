# Índice de documentación

Documentación de **ci4-domain-starter** — plantilla de CodeIgniter 4 para apps de dominio que delegan auth e IAM a un hub central. Este índice solo lista archivos que existen en la plantilla.

> **¿Primera vez aquí?** Empieza por el [`README.es.md`](../README.es.md) raíz para el quickstart y el diagrama de arquitectura, luego [`CLAUDE.md`](../CLAUDE.md) para los acuerdos de trabajo. English version: [README.md](README.md).

## 🔑 Integración con el hub (específico de apps de dominio)

La característica que define esta plantilla — cómo la app delega auth en el hub y registra sus permisos.

- [Autenticación y delegación al hub](architecture/AUTHENTICATION.es.md) — `DomainAuthFilter`, `HubClient`, flujo introspect / service-token.
- [Validación de JWT](tech/jwt-auth.es.md) — puntero al contrato de JWT del lado del hub.

## 🚀 Ruta de aprendizaje del scaffolding

Si nunca has scaffoldeado un CRUD en este kit, sigue este orden:

1. **Quickstart** — [`README.es.md`](../README.es.md) raíz § "Añadir un nuevo módulo CRUD".
2. **Playbook canónico** — [`template/CRUD_FROM_ZERO.es.md`](template/CRUD_FROM_ZERO.es.md) (sintaxis de campos, modificadores, pasos post-scaffolding).
3. **Checklist antes de PR** — [`template/MODULE_BOOTSTRAP_CHECKLIST.es.md`](template/MODULE_BOOTSTRAP_CHECKLIST.es.md).
4. **Deep dive técnico** — [`tech/scaffolding-engine.es.md`](tech/scaffolding-engine.es.md).
5. **Contrato de arquitectura (SSOT)** — [`template/ARCHITECTURE_CONTRACT.es.md`](template/ARCHITECTURE_CONTRACT.es.md).

## 🏗️ Deep dives de arquitectura

- [Overview](architecture/OVERVIEW.es.md)
- [Layers](architecture/LAYERS.es.md)
- [Request Flow](architecture/REQUEST_FLOW.es.md)
- [Services](architecture/SERVICES.es.md)
- [Validation](architecture/VALIDATION.es.md)
- [Responses](architecture/RESPONSES.es.md)
- [Filters](architecture/FILTERS.es.md)
- [Queries](architecture/QUERIES.es.md)
- [Patterns](architecture/PATTERNS.es.md)
- [Exceptions](architecture/EXCEPTIONS.es.md)
- [I18N](architecture/I18N.es.md)
- [Testing](architecture/TESTING.es.md)
- [Drift Guide](architecture/DRIFT_GUIDE.es.md)
- [Extension Guide](architecture/EXTENSION_GUIDE.es.md)

## ⚙️ Guías técnicas

- [Scaffolding Engine](tech/scaffolding-engine.es.md)
- [OpenAPI](tech/openapi.es.md)
- [CORS](tech/cors.es.md)
- [Pagination](tech/pagination.es.md)
- [Rate Limiting](tech/rate-limiting.es.md)
- [Request Logging](tech/request-logging.es.md)
- [Monitoring & Health](tech/monitoring-health.es.md)
- [Audit Logging](tech/audit-logging.es.md)
- [Audit Operations](tech/audit-operations.es.md)
- [Queue](tech/QUEUE.es.md)
- [Transactions](tech/transactions.es.md)
- [Testing Guidelines](tech/TESTING_GUIDELINES.es.md)

## 📋 Estándares de plantilla

- [Module Bootstrap Checklist](template/MODULE_BOOTSTRAP_CHECKLIST.es.md)
- [CRUD From Zero](template/CRUD_FROM_ZERO.es.md)
- [Quality Gates](template/QUALITY_GATES.es.md)
- [Versioning Policy](template/VERSIONING_POLICY.es.md)
- [Contribution Rules](template/CONTRIBUTION_RULES.es.md)
- [Architecture Contract (SSOT)](template/ARCHITECTURE_CONTRACT.es.md)

## 📐 ADRs (decisiones de arquitectura)

- [0001 — Use DTO-First Architecture](adr/0001-use-dto-first-architecture.es.md)
- [0002 — Implement Repository Pattern](adr/0002-implement-repository-pattern.es.md)
- [ADR-004 — Observability Governance](adr/ADR-004-OBSERVABILITY-GOVERNANCE.es.md)
- [ADR-005 — Service Purity & DI](adr/ADR-005-SERVICE-PURITY-DI.es.md)
- [ADR-006 — Feature Toggle Policy](adr/ADR-006-FEATURE-TOGGLE-POLICY.es.md)
- [ADR-007 — Service Return Contracts](adr/ADR-007-SERVICE-RETURN-CONTRACTS.es.md)
- [ADR-008 — API Versioning & Deprecation](adr/ADR-008-API-VERSIONING-AND-DEPRECATION.es.md)
- [ADR-009 — Idempotency Keys](adr/ADR-009-IDEMPOTENCY-KEYS.es.md)
- [ADR-010 — Problem Details (RFC 7807)](adr/ADR-010-PROBLEM-DETAILS-RFC-7807.es.md)
- [ADR-011 — Multi-Tenancy Out of Scope](adr/ADR-011-MULTI-TENANCY-OUT-OF-SCOPE.es.md)
- [ADR-012 — Config Runtime Mutability](adr/ADR-012-CONFIG-RUNTIME-MUTABILITY.es.md)

## 🛠️ Runbooks

- [01 — Rotar JWT Secret](runbooks/01-rotate-jwt-secret.es.md)
- [02 — Recuperación de migración fallida](runbooks/02-failed-migration-recovery.es.md)
- [03 — Upgrade CI4 minor](runbooks/03-upgrade-ci4-minor.es.md)
- [04 — Incidente: token leak](runbooks/04-incident-token-leak.es.md)

## 🔍 Auditorías

- [make-crud audit](audits/make-crud-audit.es.md)

## 📎 Agente y alcance

- [Agent Quick Reference](AGENT_QUICK_REFERENCE.es.md)
- [Documentation Scope](DOCUMENTATION_SCOPE.es.md)

---

> **Fuera de alcance aquí, vive en el hub:** login, logout, reseteo de contraseña, verificación de email, Google OAuth, emisión/refresh de JWT, endpoints administrativos de IAM, drivers de almacenamiento de ficheros. Ver `docs/` del hub para esos temas.
