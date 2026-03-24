# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this application.

## Overview

NorthOps marketing site and lead pipeline CRM, built on the [Waaseyaa framework](https://github.com/waaseyaa/framework). Supports dual-brand operation (NorthOps + Web Networks) with AI-powered lead qualification.

## Architecture

```
src/
├── Access/            Authorization policies (DashboardAccessPolicy, LeadAccessPolicy)
├── Command/           CLI commands (SeedBrandsCommand)
├── Controller/
│   ├── Api/           JSON API controllers (LeadController — 13 endpoints)
│   ├── DashboardController   Admin UI (pipeline board, lead detail, settings)
│   └── MarketingController   Public site (home, about, services, contact)
├── Domain/
│   ├── Pipeline/      Lead lifecycle (LeadManager, LeadFactory, StageTransitionRules)
│   │   └── EventSubscriber/  Discord notifications, activity logging
│   └── Qualification/ AI scoring (QualificationService, SectorNormalizer)
├── Entity/            Brand, Lead, LeadActivity, LeadAttachment, ContactSubmission
├── Provider/          AppServiceProvider, PipelineServiceProvider
└── Support/           Cross-cutting utilities
```

### Key Patterns

- **Entities** extend `ContentEntityBase` and register via `EntityTypeManager`
- **Persistence** uses `EntityRepository` + `SqlStorageDriver` (see `.claude/rules/waaseyaa-framework.md`)
- **Routes** defined in `ServiceProvider::routes()` via `WaaseyaaRouter`
- **Auth** via `Waaseyaa\Auth\AuthManager` (session-based)
- **Config** via `config/waaseyaa.php` — use `getenv()` or `env()` helper, NEVER `$_ENV`
- **HTTP client** via `waaseyaa/http-client` — all external HTTP calls use injected `HttpClientInterface` (Discord, Anthropic API, north-cloud)
- **Discord notifications** via `DiscordNotifier` — centralized webhook sender with `sendEmbed()`, used by all event subscribers and controllers
- **Contact form** saves to `ContactSubmission` entity + sends Discord notification via `DiscordNotifier` + auto-creates pipeline Lead
- **Lead pipeline** stages: lead → qualified → contacted → proposal → negotiation → won/lost
- **AI qualification** via Claude API (claude-haiku-4-5) — rates, scores, and categorizes leads
- **Dual-brand** support via `Brand` entity — NorthOps (default) and Web Networks (finder's fee tracking)
- **Admin dashboard** at `/admin` — Kanban board, lead list, detail/edit, settings
- **JSON API** at `/api/leads`, `/api/brands`, `/api/config` — full CRUD + qualification + import

## Orchestration Table

| File Pattern | Skill | Spec |
|-------------|-------|------|
| `src/Entity/**` | `waaseyaa:entity-system` | entity-system.md |
| `src/Access/**` | `waaseyaa:access-control` | access-control.md |
| `src/Domain/Pipeline/**` | `feature-dev` | lead-pipeline-design.md |
| `src/Domain/Qualification/**` | `feature-dev` | lead-pipeline-design.md |
| `src/Controller/Api/**` | `feature-dev` | lead-pipeline-design.md |
| `src/Support/**` | `feature-dev` | lead-pipeline-design.md |
| `src/Provider/**` | `feature-dev` | lead-pipeline-design.md |
| `.claude/rules/**` | `updating-codified-context` | — |
| `docs/specs/**` | `updating-codified-context` | — |

## MCP Federation

Register Waaseyaa's MCP server in `.claude/settings.json` for on-demand framework specs:

```json
{
  "mcpServers": {
    "waaseyaa": {
      "command": "node",
      "args": ["vendor/waaseyaa/mcp/server.js"],
      "cwd": "."
    }
  }
}
```

## Development

```bash
composer install                    # Install dependencies
php -S localhost:8080 -t public     # Dev server
./vendor/bin/phpunit                # Run tests
bin/waaseyaa                        # CLI
bin/waaseyaa sync-rules             # Update framework rules from Waaseyaa
bin/waaseyaa pipeline:seed-brands   # Seed NorthOps + Web Networks brands (idempotent)
bin/waaseyaa pipeline:import-rfps   # Import RFPs from north-cloud (--days=7, --dry-run)
```

### Environment Variables

| Variable | Purpose | Required |
|----------|---------|----------|
| `DISCORD_WEBHOOK_URL` | Contact form + pipeline notifications | No |
| `ANTHROPIC_API_KEY` | AI lead qualification (Claude API) | For qualification |
| `NORTHCLOUD_URL` | North-cloud RFP import API | For RFP import |
| `PIPELINE_API_KEY` | API key for machine-to-machine endpoints | For import endpoint |
| `COMPANY_PROFILE` | Company description for AI qualification prompts | No (has default) |

## Codified Context

This app uses a three-tier codified context system inherited from Waaseyaa:

| Tier | Location | Purpose |
|------|----------|---------|
| **Constitution** | `CLAUDE.md` (this file) | Architecture, conventions, orchestration |
| **Rules** | `.claude/rules/waaseyaa-*.md` | Framework invariants (always active, never cited) |
| **Specs** | `docs/specs/*.md` | Domain contracts for each subsystem |

Framework rules are owned by Waaseyaa. Update them via `bin/waaseyaa sync-rules` after `composer update`.

When modifying a subsystem, update its spec in the same PR.

## Known Gaps

- **API endpoints are unauthenticated** — all `/api/leads` routes use `allowAll()`. Access policies exist but aren't wired to API routes. See #55.
- **Phase 2 pending** — email sequences, PDF proposal generation (port LaTeX pipeline from web-networks-pipeline)
- **Phase 3 pending** — external integrations (Calendly, LinkedIn, Mailchimp)
- **Phase 4 pending** — analytics and revenue reporting
- **Search limited** — API lead search only matches on `label` field (no OR-condition support in EntityQuery)

## Gotchas

- **Never use `$_ENV`** — Waaseyaa's `EnvLoader` only populates `putenv()`/`getenv()`. Use `getenv()` or the `env()` helper.
- **SQLite write access** — Both the `.sqlite` file AND its parent directory need write permissions for WAL/journal files.
- **No `loadByProperties()` on SqlEntityStorage** — Use `getStorage('type')->getQuery()->condition('field', $value)->execute()` for property-based lookups.
- **ServiceProvider method signatures must match parent exactly** — PHP enforces strict compatibility. Check `ServiceProvider::commands()` and `ServiceProvider::routes()` signatures when overriding.
- **`dep deploy` broken for local use** — SSH user mismatch (deployer vs jones). Use `ssh jones@northops.ca "sudo -u deployer bash -c '...'"` for manual deploys. See #56.
