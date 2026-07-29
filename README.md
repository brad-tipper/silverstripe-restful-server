# brad-tipper/silverstripe-restful-server

An opinionated REST API layer for Silverstripe 6.

This is **not** a general-purpose, configurable REST module. It bakes in one way of doing things — one auth flow, one error format, one password reset flow, one pagination scheme — because that's what's actually needed across the products it's used in.

It's published publicly for ease of distribution and because others might find it useful, but it's primarily maintained for the author's own SaaS products.

## What it gives you

- **`RestfulDataObject`** — a base `DataObject` subclass you extend to instantly expose a model over REST, with consistent serialization, permission checks, filtering, and pagination.
  ```php
  class Invoice extends RestfulDataObject
  {
      private static array $db = [
          'Amount' => 'Decimal(15,2)',
          'Description' => 'Varchar(255)',
      ];
  }
  ```
- **JWT authentication** (custom, rolled in-house) with login, refresh, logout, and identity endpoints out of the box. Secrets and expiry are configurable via `RESTFUL_JWT_SECRET` and `RESTFUL_JWT_EXPIRY`.
- **A single, opinionated password reset flow** — one method (`requestReset`/`resetPassword`), no configurable alternatives.
- **A consistent JSON error envelope** across every endpoint: `{ "error": "...", "details": { ... } }`.
- **Standard CRUD endpoints** for every `RestfulDataObject` subclass — list (paginated/filtered/sorted), show, create, update, delete.
- **Standard list endpoint behaviour** — pagination (`page`, `perPage`), filtering (`filter`), and sorting (`sort`) conventions applied consistently to every resource.

## Requirements

- Silverstripe CMS 6
- PHP ^8.3

## Installation

```
composer require brad-tipper/silverstripe-restful-server
```

## Configuration

Set these environment variables:

| Variable | Default | Description |
|----------|---------|-------------|
| `RESTFUL_JWT_SECRET` | *(required)* | HMAC secret for JWT signing |
| `RESTFUL_JWT_EXPIRY` | `900` | Access token TTL in seconds |
| `RESTFUL_EMAIL_FROM` | `no-reply@example.com` | From address for password reset emails |

## API Endpoints

### Auth

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/auth/login` | Sign in with `{ email, password }` |
| POST | `/api/auth/logout` | Revoke session (requires auth) |
| POST | `/api/auth/refresh` | Exchange refresh token for new access token |
| POST | `/api/auth/request-reset` | Request password reset email |
| POST | `/api/auth/reset-password` | Complete password reset |
| GET | `/api/auth/identity` | Check current auth status |

### Resources (auto-discovered)

Every class extending `RestfulDataObject` gets these endpoints automatically:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/{resource}` | List (paginated, filterable, sortable) |
| GET | `/api/{resource}/{uuid}` | Show single record |
| POST | `/api/{resource}` | Create record |
| PATCH | `/api/{resource}/{uuid}` | Update record |
| DELETE | `/api/{resource}/{uuid}` | Delete record |

## Error Format

Every error response follows this shape:

```json
{
  "error": "Human-readable error message",
  "details": { "code": "optional_code" }
}
```

## Pagination

List endpoints accept `?page=1&perPage=50` (default perPage: 100, max: 100). The response includes:

```json
{
  "data": [...],
  "pagination": {
    "page": 1,
    "perPage": 50,
    "total": 200,
    "hasMore": true
  }
}
```

## Filtering

Use `?filter=field:ExactMatch:value,field:PartialMatch:value`. Multiple filters are combined as AND.

## Sorting

Use `?sort=field` or `?sort=-field` for descending order. Only DB columns are accepted.

## Group-Based Auth

The module provides helpers for a **"REST API Users"** group (`restful-api-users` code). Applications must call the group setup/add-member helpers from their registration and login policy; the module does not silently grant JWT access to every CMS member.

The group is managed by `BradTipper\RestfulServer\Security\RestfulApiMemberGroup`:

```php
// Ensure the group exists (called automatically on dev/build)
RestfulApiMemberGroup::ensure();

// Check if a member is an API user
RestfulApiMemberGroup::isApiUser($member);

// Add a member to the API users group
RestfulApiMemberGroup::addMember($member);
```

### Auth Sessions in the CMS

The module adds an **AuthSessions** tab to every Member in the CMS (under the Security section). This replaces the need for a standalone AuthSessionAdmin. The tab shows all API sessions for that member in a read-only gridfield.

## Codegen

The module does not generate an application client. Define the complete application API, including custom controllers and response shapes, as an OpenAPI document in the consuming project and generate its TypeScript client with a standard tool such as [`@hey-api/openapi-ts`](https://heyapi.dev/). For example:

```json
{
  "scripts": {
    "gen:api": "openapi-ts --file openapi-ts.config.ts"
  }
}
```

Keep generated output out of hand-edited source and regenerate it whenever the OpenAPI contract changes.
