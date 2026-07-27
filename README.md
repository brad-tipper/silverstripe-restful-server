# brad-tipper/silverstripe-restful-server

An opinionated REST API layer for Silverstripe 6.

This is **not** a general-purpose, configurable REST module. It bakes in one way of doing things — one auth flow, one error format, one password reset flow, one pagination scheme — because that's what's actually needed across the products it's used in. If you want a fully configurable REST layer with pluggable strategies for everything, this probably isn't it. If you want to `composer require` a REST API with sensible, fixed conventions and start shipping, it might be exactly it.

It's published publicly for ease of distribution and because others might find it useful, but it's primarily maintained for the author's own SaaS products.

## What it gives you

- **`RestfulDataObject`** — a base `DataObject` subclass you extend to instantly expose a model over REST, with consistent serialization, permission checks, filtering, and pagination.
  ```php
  class Invoice extends RestfulDataObject
  {
      // your fields, as normal
  }
  ```
- **JWT authentication** (custom, rolled in-house — not `firebase/php-jwt` config sprawl) with login, refresh, and logout endpoints out of the box. Secrets/expiry are configurable; the flow itself is not.
- **A single, opinionated password reset flow** — one method, one set of endpoints, no configurable alternatives.
- **A consistent JSON error envelope** across every endpoint, so a single client-side error handler works everywhere.
- **Standard list endpoint behaviour** — pagination, filtering, and sorting conventions applied consistently to every resource.
- **A machine-readable schema/manifest endpoint** describing exposed resources, fields, and types — used by the companion client package to generate typed hooks (see [codegen](#codegen)).

## Requirements

- Silverstripe CMS 6
- PHP (version matching Silverstripe 6 requirements)

## Installation

```
composer require brad-tipper/silverstripe-restful-server
```

## Codegen

This module exposes a schema endpoint that the companion [`@bradtipper/restful-client`](#) package reads to generate TypeScript types and Tanstack Query hooks per resource. See that package's README for the generation step.