# CLAUDE.md — Sprout API Platform

Agentic project instructions for Claude Code. Read this file before making any changes.

---

## Design Philosophy

**Actions are named business operations. DTOs are their data contracts.**

Both are written to be readable by Product Owners and AI agents. `app/{Domain}/Actions/` is a product catalogue — each class name maps directly to a business requirement. DTOs make the data contract explicit without reading implementation code.

**Grow as you go.** Start with an Action and a DTO. Add a Service class only when an Action becomes too complex. Add repositories, events, and query objects only when the need is real — not in anticipation of it.

---

## Project Purpose

Sprout API Platform — a Laravel platform that starts as a polished, reusable template and becomes the home for migrated services over time.

Phase one: reimplementing the HealthCheck domain and engineering conventions of a reference prototype under this branding and a simplified trunk-based git workflow. Future domains arrive alongside future migrated services and are out of scope for now.

---

## Domain Architecture

This project uses a siloed domain architecture. Each domain lives under `app/{Domain}/` and is self-contained, pulling in only the subdirectories it needs:

```
app/{Domain}/
├── Actions/     # One class per use case (lorisleiva/laravel-actions)
├── Data/        # Spatie Data DTOs — request input and response output
├── Contracts/   # Interfaces the domain exposes or depends on
├── Enums/       # Domain enumerations
├── Providers/   # Domain-specific ServiceProvider(s), registered in bootstrap/providers.php
├── Services/    # External service calls, orchestration, data access
└── CLAUDE.md    # Domain documentation (required)
```

Not every domain needs every subdirectory — a simple domain may only have `Actions/` and `Data/`. Add a subdirectory when there's a real class to put in it, not in anticipation of one.

This is the mandatory convention for every domain added to this codebase, including domains introduced later for migrated services.

---

## Conventions for Agentic Work

- **All PHP commands:** run via `docker compose exec app` (no native PHP on host)
- **Trunk-based git:** a single `master` branch, no `develop`. Never commit directly to `master` — create a branch, commit there, push, open a PR targeting `master`, and merge
- **No AI attribution:** never include AI-attribution text (e.g. "Generated with Claude Code") in commit messages, PR descriptions, or GitHub issues
- **Domain CLAUDE.md:** every domain directory under `app/` gets a `CLAUDE.md` documenting: purpose, consumers, how to extend, auth model, and any non-obvious patterns
- **VERSION file:** `VERSION` (project root) is the single source of truth for `APP_VERSION` and `API_VERSION`. Do not add version numbers to `.env`
- **Keep this file current:** after completing a phase, adding a convention, or changing how the project is built or run — update `CLAUDE.md` before ending the session

---

_Last updated: 2026-08-17 (Established domain architecture convention)_
