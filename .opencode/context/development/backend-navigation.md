<!-- Context: development/navigation | Priority: low | Version: 1.0 | Updated: 2026-02-15 -->

# Backend Development Navigation

**Scope**: Server-side, APIs, databases, auth

---

## Structure

```
development/backend/
├── navigation.md
├── php-standards.md           # ✅ PHP 8.x language feature standards
├── patterns.md                # ✅ Async design patterns (Handler/Action/Service)
├── performance.md             # ✅ Production performance (OPcache, JIT, preloading)
│
├── api-patterns/              # Approach-based [future]
│   ├── rest-design.md
│   ├── graphql-design.md
│   ├── grpc-patterns.md
│   └── websocket-patterns.md
│
├── nodejs/                    # Tech-specific [future]
│   ├── express-patterns.md
│   ├── fastify-patterns.md
│   └── error-handling.md
│
├── python/                    # [future]
│   ├── fastapi-patterns.md
│   └── django-patterns.md
│
├── authentication/            # Functional concern [future]
│   ├── jwt-patterns.md
│   ├── oauth-patterns.md
│   └── session-management.md
│
└── middleware/                # [future]
    ├── logging.md
    ├── rate-limiting.md
    └── cors.md
```

---

## Quick Routes

| Task | Path |
|------|------|
| **PHP language standards** | `backend/php-standards.md` |
| **Design patterns (Handler/Action/Service)** | `backend/patterns.md` |
| **Production performance** | `backend/performance.md` |
| **API design principles** | `principles/api-design.md` |
| **REST API** | `backend/api-patterns/rest-design.md` [future] |
| **GraphQL** | `backend/api-patterns/graphql-design.md` [future] |
| **Node.js** | `backend/nodejs/express-patterns.md` [future] |
| **Python** | `backend/python/fastapi-patterns.md` [future] |
| **Auth (JWT)** | `backend/authentication/jwt-patterns.md` [future] |

---

## By Approach

**REST** → `backend/api-patterns/rest-design.md` [future]
**GraphQL** → `backend/api-patterns/graphql-design.md` [future]
**gRPC** → `backend/api-patterns/grpc-patterns.md` [future]

## By Language

**PHP** → `backend/php-standards.md`, `backend/patterns.md`, `backend/performance.md`
**Node.js** → `backend/nodejs/` [future]
**Python** → `backend/python/` [future]

## By Concern

**Authentication** → `backend/authentication/` [future]
**Middleware** → `backend/middleware/` [future]
**Data layer** → `data/` [future]

---

## Related Context

- **API Design Principles** → `principles/api-design.md`
- **Core Standards** → `../core/standards/code-quality.md`
- **Data Patterns** → `data/navigation.md` [future]
