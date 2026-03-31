# NorthOps Lead Engine & Automation — Design Spec

**Date:** 2026-03-30
**Status:** Approved
**Repos:** northops-waaseyaa, jonesrussell/agency-agents, north-cloud, claudriel, waaseyaa/framework

---

## 1. Overview

A unified lead engine powering two brands (NorthOps and Web Networks) through four progressively capable phases. Three systems evolve together with clear boundaries: agency-agents provides specialist expertise, NorthOps is the work engine, and Claudriel is the operations brain.

### Architecture

```
agency-agents (expertise, read-only)
    │
    │  filesystem read (env var path)
    ▼
NorthOps (work engine)
    │
    │  HTTP webhook (env-gated)
    ▼
Claudriel (ops brain, commitment consumer)
```

### System Boundaries

| System | Owns | Does NOT Own |
|--------|------|-------------|
| `agency-agents` | Specialist prompt files (161), eval harness | Runtime, state, API |
| `northops-waaseyaa` | Leads, scoring, routing, proposals, outreach, brand logic | Personal tasks, calendar, daily briefs |
| `north-cloud` | Crawlers, RFP discovery, signal detection | Lead qualification, outreach |
| `claudriel` | Commitments, briefs, schedule, personal ops | Lead scoring, proposals, marketing |
| `waaseyaa/framework` | Entity system, storage, auth, admin SPA | Business logic |

### Integration Contracts

| Integration | Protocol | Direction | Phase |
|------------|----------|-----------|-------|
| agency-agents → NorthOps | Filesystem (`AGENCY_AGENTS_PATH` env var) | Read-only | 2 |
| north-cloud → NorthOps | HTTP API (`/api/leads` POST, `PIPELINE_API_KEY`) | Push | 1 (exists), 3 (new endpoints) |
| NorthOps → Discord | Webhook (`DISCORD_WEBHOOK_URL`) | Push | 1 (exists), 3 (T1 alerts) |
| NorthOps → Claudriel | HTTP webhook (`CLAUDRIEL_WEBHOOK_URL`, `CLAUDRIEL_API_KEY`) | Push | 4 |
| Claudriel → agency-agents | None | — | Future (out of scope) |

All integrations are gated by environment variables. Each system works standalone without the others configured.

---

## 2. Phase 1 — CRM Foundations (2 weeks)

**Goal:** The engine can *think* — leads get scored with brand-specific logic and routed to the correct brand.

**Repos:** `northops-waaseyaa` (primary), `waaseyaa/framework` (if entity system changes needed)

### 2.1 Entity Field Additions

Add to the Lead entity type definition:

| Field | Type | Values / Format | Purpose |
|-------|------|----------------|---------|
| `budget_range` | string | `under_2k`, `2k_5k`, `5k_10k`, `10k_25k`, `25k_plus` | Budget bracket for scoring |
| `urgency` | string | `low`, `medium`, `high`, `critical` | Time pressure indicator |
| `tier` | string | `T1`, `T2`, `T3`, `T4`, `T5` | Normalized priority from score |
| `organization_type` | string | `startup`, `nonprofit`, `charity`, `indigenous`, `community`, `government` | Org classification for routing |
| `funding_status` | string | `none`, `applied`, `received`, `unknown` | Grant funding state (Web Networks) |
| `routing_confidence` | integer | 0–100 | Auto-router certainty |
| `lead_source` | string | `rfp`, `signal`, `job_posting`, `funding`, `website_audit`, `manual`, `directory` | Normalized ingestion source |
| `last_scored_at` | timestamp | ISO 8601 | Decay precision, re-scoring guard |
| `specialist_context` | JSON | `{ specialists: [...], reasoning: {...} }` | Audit trail of specialist reasoning. Field added Phase 1, populated Phase 2. |

### 2.2 New Services

#### RoutingService (`src/Domain/Pipeline/RoutingService.php`)

Applies routing rules to assign brand and routing confidence on lead creation.

**Routing rules (ordered by confidence):**

| # | Condition | Route To | Confidence |
|---|-----------|----------|------------|
| 1 | Source is northops.ca contact form | NorthOps | 100% |
| 2 | Source is webnetworks contact form | Web Networks | 100% |
| 3 | Brand explicitly set on creation | Specified brand | 100% |
| 4 | Organization is registered non-profit/charity | Web Networks | 95% |
| 5 | Organization is Indigenous org | Web Networks | 95% |
| 6 | Sector is SaaS, AI/ML, or DevOps | NorthOps | 90% |
| 7 | `lead_source` is `funding` | Web Networks | 90% |
| 8 | `lead_source` is `website_audit` | Web Networks | 90% |
| 9 | `lead_source` is `signal` (founder intent) | NorthOps | 85% |
| 10 | Budget >$15K and technical scope | NorthOps | 80% |
| 11 | RFP sector is government digital services | Evaluate both | 50% |
| 12 | No matching rule | Manual review | 0% |

For dual-brand leads (confidence <60%): route to primary brand, tag secondary for awareness.

#### BrandScoringContext (`src/Domain/Qualification/BrandScoringContext.php`)

Returns brand-specific prompt context for AI qualification.

**Prompt structure:**

```
SYSTEM: You are a lead qualification assistant for {brand_name}.
{brand_context}
{company_profile}

Evaluate this lead and return JSON:
- score: 0-100
- confidence: low/medium/high
- tier: T1/T2/T3/T4/T5
- reasoning: 1-2 sentences
- recommended_action: string
```

**NorthOps context:** ICP = funded startups, CTOs, urgent builds. Score highly for: founder/CTO role, funded, urgent timeline, tech stack match, budget ≥$5K. Score low for: no budget, no urgency, scope too large for sprint, maintenance-only.

**Web Networks context:** ICP = non-profits, Indigenous orgs, grant-funded. Score highly for: registered non-profit, has funding, outdated/missing website, accessibility issues. Score low for: for-profit, no digital need, good existing website, no funding source.

### 2.3 Changes to Existing Services

| Service | Change |
|---------|--------|
| `ProspectScoringService` | Accept `BrandScoringContext`, return tier alongside score, set `last_scored_at` |
| `LeadFactory` | Call `RoutingService` on create, populate new fields, set `lead_source` |
| `SectorNormalizer` | Add `organization_type` detection from sector keywords |

### 2.4 Score Decay

New CLI command: `bin/waaseyaa pipeline:decay-scores`

Runs daily via cron. Uses `last_scored_at` for precision.

| Brand | 14 days | 30 days | 60 days | 90 days |
|-------|---------|---------|---------|---------|
| NorthOps | −5 | −10 | −20 | — |
| Web Networks | — | −3 | −8 | −15 |

### 2.5 Unified Tier System

| Tier | Score Range | NorthOps Action | Web Networks Action |
|------|-------------|----------------|---------------------|
| T1 | 80–100 | Same-day outreach | 24h outreach |
| T2 | 60–79 | 24h outreach | 48h outreach |
| T3 | 40–59 | Nurture sequence | Nurture sequence |
| T4 | 20–39 | Monitor signals | Monitor signals |
| T5 | 0–19 | Archive | Archive |

### 2.6 Phase 1 Deliverables

- [ ] 9 new Lead entity fields (including lead_source, last_scored_at, specialist_context)
- [ ] `RoutingService` with 12 routing rules
- [ ] `BrandScoringContext` with NorthOps + Web Networks prompts
- [ ] Updated `ProspectScoringService` (brand-aware, returns tier, sets last_scored_at)
- [ ] Updated `LeadFactory` (routing on create, lead_source population)
- [ ] Updated `SectorNormalizer` (organization_type detection)
- [ ] `pipeline:decay-scores` CLI command
- [ ] Tests for routing rules, scoring context, decay logic

---

## 3. Phase 2 — Agent Integration (2 weeks)

**Goal:** The engine can *reason* — 7 specialists provide domain expertise for qualification, scoping, and draft generation.

**Repos:** `northops-waaseyaa` (primary), `jonesrussell/agency-agents` (docs)

### 3.1 Agent Loading Architecture

```
AGENCY_AGENTS_PATH=/path/to/agency-agents   (env var)
         │
         ▼
AgentPromptLoader (new service)
         │
         ├── loadAgent('sales/deal-strategist')
         │      → reads {path}/sales/deal-strategist.md
         │      → parses frontmatter (name, description, vibe)
         │      → returns AgentPrompt value object
         │      → caches in-memory (keyed by slug)
         │
         └── listAgents(?division)
                → scans directories, returns available agents
```

In-memory cache (simple associative array keyed by slug). No cache invalidation needed — prompts don't change at runtime.

### 3.2 The 7 Specialists

| Specialist | Division/Path | Used By | Purpose |
|-----------|--------------|---------|---------|
| `deal-strategist` | `sales/` | `ProspectScoringService` | Lead qualification reasoning, deal assessment |
| `outbound-strategist` | `sales/` | Future Phase 3 | Outreach sequence generation |
| `backend-architect` | `engineering/` | `ProspectScoringService` | Technical feasibility for RFPs |
| `devops-engineer` | `engineering/` | `ProspectScoringService` | Infra/DevOps scope assessment |
| `product-strategist` | `product/` | `ProspectScoringService` | Product-market fit evaluation |
| `growth-hacker` | `marketing/` | `ProspectScoringService` | Growth channel assessment |
| `ai-engineer` | `engineering/` | `ProspectScoringService` | AI feature feasibility (RAG, LLM, embeddings). **New agent — create in Phase 2** as `engineering/engineering-ai-engineer.md` in jonesrussell/agency-agents fork. |

### 3.3 Specialist Selection Logic

`SpecialistSelector` maps sector tags to relevant specialists:

```
Lead sector tags → SpecialistSelector → list of AgentPrompt slugs

Rules:
- always include: deal-strategist (every lead gets deal qualification)
- sector includes "ai" or "ml" → add ai-engineer
- sector includes "saas" or "startup" → add product-strategist
- sector includes "devops" or "infrastructure" → add devops-engineer
- sector includes "web" or "application" → add backend-architect
- lead_source is "signal" or "job_posting" → add growth-hacker
- max 3 specialists per scoring call (keep prompt focused)
```

### 3.4 Enhanced Scoring Flow

```
Lead arrives (already routed + base-scored from Phase 1)
         │
         ▼
SpecialistSelector picks relevant specialists (max 3)
         │
         ▼
AgentPromptLoader loads specialist prompts (from cache)
         │
         ▼
Claude API call with:
  - Brand context (Phase 1 BrandScoringContext)
  - Specialist context (appended as domain expertise sections)
  - Lead data
         │
         ▼
Enhanced score + specialist reasoning stored:
  - score, tier updated on Lead
  - specialist_context JSON populated with:
    { specialists: ["deal-strategist", "backend-architect"],
      reasoning: { "deal-strategist": "...", "backend-architect": "..." } }
  - last_scored_at updated
```

### 3.5 New Entity Field

| Field | Type | Phase | Purpose |
|-------|------|-------|---------|
| `source_url` | string | 2 | Origin URL of the signal (HN post, Reddit thread, grant page, RFP URL) |

### 3.6 New Services

| Class | Location | Responsibility |
|-------|----------|---------------|
| `AgentPromptLoader` | `src/Domain/Pipeline/` | Load, parse, cache agent markdown files |
| `AgentPrompt` | `src/Domain/Pipeline/` | Value object: name, description, vibe, content, slug |
| `SpecialistSelector` | `src/Domain/Pipeline/` | Map sector tags + lead_source → relevant specialist slugs |

### 3.7 Changes to Existing Services

| Service | Change |
|---------|--------|
| `ProspectScoringService` | Inject `AgentPromptLoader` + `SpecialistSelector`, append specialist context to prompt, store specialist_context on lead |
| `PipelineServiceProvider` | Register new services, bind `AGENCY_AGENTS_PATH` from env |

### 3.8 agency-agents Repo Work

- Create `engineering/engineering-ai-engineer.md` — AI feature scoping specialist (RAG, LLM integrations, embeddings, prompt engineering)
- Document NorthOps integration in README or `NORTHOPS.md`
- Verify 7 agent files have consistent frontmatter format (6 existing + 1 new)
- PR #28 (eval harness) continues independently — no dependency

### 3.9 Phase 2 Deliverables

- [ ] `AgentPromptLoader` service with in-memory caching
- [ ] `AgentPrompt` value object
- [ ] `SpecialistSelector` (sector → specialists mapping, max 3)
- [ ] `ProspectScoringService` updated for specialist-enhanced scoring
- [ ] `PipelineServiceProvider` wiring + `AGENCY_AGENTS_PATH` env
- [ ] `source_url` field added to Lead entity
- [ ] Tests for loader, selector, enhanced scoring, caching
- [ ] agency-agents repo: create `engineering-ai-engineer.md` agent
- [ ] agency-agents repo: NorthOps integration docs

---

## 4. Phase 3 — Outreach Engine (4 weeks)

**Goal:** The engine can *act* — signal detection, outreach templates, hot-lead alerts, website audit pipeline.

**Repos:** `northops-waaseyaa` (primary), `north-cloud` (crawlers)

### 4.1 Signal Ingestion

| Signal Type | Crawler Source | Frequency | `lead_source` Value |
|------------|---------------|-----------|---------------------|
| RFP | Government portals | Daily | `rfp` (exists) |
| Founder intent | HN, Reddit, IH | Daily | `signal` |
| Hiring signals | LinkedIn, job boards | 3x/week | `job_posting` |
| Funding announcements | OTF, ISED, grants.gc.ca | Daily | `funding` |
| Website audit | Target org list | Weekly batch | `website_audit` |

### 4.2 New Entity Fields

| Field | Type | Purpose |
|-------|------|---------|
| `audit_payload` | JSON | Raw Lighthouse results for debugging and future report generation |
| `signal_strength` | integer (0–100) | Intent signal quality. Examples: "looking for CTO" → 90, "thinking about rebuilding" → 40 |
| `audit_score` | integer (0–100) | Composite: accessibility (40%) + performance (25%) + mobile (25%) + content (10%) |

### 4.3 north-cloud Crawler Additions

| Crawler | Target | Keywords/Rules | Output Format |
|---------|--------|---------------|---------------|
| `hn-intent` | Hacker News | "looking for CTO", "need developer", "rebuild MVP", "technical co-founder" | JSON → NorthOps API |
| `reddit-intent` | r/startups, r/SaaS, r/webdev | Same keyword set | JSON → NorthOps API |
| `funding-monitor` | otf.ca/funded-grants, grants.gc.ca | Grant announcement patterns | JSON → NorthOps API |

### 4.4 New NorthOps Ingestion Endpoints

All gated by `PIPELINE_API_KEY` (existing mechanism).

| Endpoint | Method | Payload |
|----------|--------|---------|
| `/api/leads/ingest/signal` | POST | `{ label, source_url, signal_strength, sector, notes }` |
| `/api/leads/ingest/funding` | POST | `{ label, source_url, funding_status, organization_type, notes }` |
| `/api/leads/ingest/audit` | POST | `{ label, source_url, audit_score, audit_payload, accessibility_score, notes }` |

Each endpoint calls `LeadFactory::create()` with appropriate `lead_source` and brand routing.

### 4.5 Website Audit Pipeline

```
Target list (50 non-profit URLs, stored as config/CSV)
         │
         ▼
Lighthouse CLI batch script (standalone, runs locally or via cron)
         │
         ├── Accessibility score (axe-core subset)
         ├── Performance score
         ├── Mobile score
         ├── Content score (SSL, sitemap, freshness)
         │
         ▼
Composite audit_score calculated (40/25/25/10 weighting)
         │
         ▼
POST /api/leads/ingest/audit
         │
         ▼
Lead created:
  - brand: webnetworks (auto-routed)
  - lead_source: website_audit
  - audit_score, audit_payload, accessibility_score populated
```

### 4.6 Outreach Templates

Stored in NorthOps (entity or config). **Not sent automatically** — rendered in admin UI for manual copy/customize/send.

| Template ID | Brand | Sequence | Purpose |
|------------|-------|----------|---------|
| `FOUNDER-CONNECT` | NorthOps | Founder Lead | LinkedIn connection note |
| `FOUNDER-VALUE` | NorthOps | Founder Lead | Value-first follow-up |
| `RFP-INTRO` | NorthOps | RFP Lead | Initial RFP response |
| `SIGNAL-INTRO` | NorthOps | Signal Lead | Signal-referenced cold email |
| `FUNDING-CONGRATS` | Web Networks | Funding Lead | Grant congratulations |
| `AUDIT-REPORT` | Web Networks | Audit Lead | Free audit report offer |
| `REFERRAL-INTRO` | Both | Referral Lead | Warm intro |
| `COLD-INTRO` | Web Networks | Directory Lead | Mission-aligned intro |

Each template includes a `template_variables` JSON field declaring required variables for validation and admin UI clarity:

```json
{
  "org_name": "string",
  "issue_summary": "string",
  "funding_program": "string"
}
```

### 4.7 Discord T1 Alert

Event subscriber hooks into existing `DiscordNotifier`:

```
Lead scored ≥80 (T1) → DiscordNotifier::sendEmbed()
  - Lead name, score, tier, brand
  - Source + specialist reasoning summary
  - Link to admin lead detail page
```

### 4.8 Phase 3 Deliverables

- [ ] 3 new Lead entity fields (audit_payload, signal_strength, audit_score)
- [ ] 3 new ingestion API endpoints (signal, funding, audit)
- [ ] north-cloud: `hn-intent` crawler config
- [ ] north-cloud: `reddit-intent` crawler config
- [ ] north-cloud: `funding-monitor` crawler config
- [ ] Lighthouse batch audit script (standalone)
- [ ] 8 outreach templates with `template_variables`
- [ ] Template rendering in admin UI (manual send)
- [ ] Discord T1 alert event subscriber
- [ ] Score decay cron deployed and running
- [ ] Tests for ingestion endpoints, template rendering, audit scoring

---

## 5. Phase 4 — Claudriel Bridge (4 weeks)

**Goal:** The engine can *remember* — pipeline events create commitments in Claudriel, surfaced in daily briefs.

**Repos:** `northops-waaseyaa` (webhook sender), `claudriel` (commitment receiver)

### 5.1 Event-to-Commitment Mappings

NorthOps owns due-date inference. Claudriel is a consumer, not a controller.

| NorthOps Event | Claudriel Commitment Title | Due Date Rule | Priority |
|---------------|---------------------------|---------------|----------|
| T1 lead created (score ≥80) | "Follow up with {lead} — hot lead, {source}" | +1 day | High |
| Lead moved to `contacted` | "Waiting for reply from {lead}" | +3 days | Medium |
| Lead moved to `proposal` | "Send proposal to {lead}" | +3 days | High |
| Lead moved to `negotiation` | "Close deal with {lead}" | +7 days | High |
| Lead stale (no activity 14d) | "Check in on {lead} — going cold" | Today | Medium |
| T1 no response 48h | "Re-engage {lead} — no response to outreach" | +1 day | High |

### 5.2 Webhook Protocol

```
NorthOps event (stage transition, score threshold, decay alert)
         │
         ▼
NorthOpsWebhookSubscriber (event subscriber)
         │
         ▼
WebhookDispatcher
         │
         POST {CLAUDRIEL_WEBHOOK_URL}/api/commitments
         Headers: Authorization: Bearer {CLAUDRIEL_API_KEY}
         Body:
         {
           "title": "Follow up with Acme Corp — hot lead, rfp",
           "due_date": "2026-05-15",
           "priority": "high",
           "source": "northops",
           "metadata": {
             "lead_id": "uuid-here",
             "lead_url": "https://northops.ca/admin/leads/uuid-here",
             "event": "t1_lead_created",
             "score": 85,
             "tier": "T1",
             "brand": "northops"
           }
         }
```

Gated by `CLAUDRIEL_WEBHOOK_URL` env var. If not set, no webhooks sent.

### 5.3 Claudriel Side

- Commitment ingestion API endpoint (accept external commitments)
- `source` field on Commitment entity (for filtering: "northops", "manual", "gmail", etc.)
- NorthOps-sourced commitments appear naturally in daily briefs
- Example brief line: **NorthOps:** 2 hot leads need follow-up, 1 proposal due Thursday

### 5.4 What Claudriel Does NOT Do

- Score leads
- Route leads
- Generate outreach
- Call agency-agents specialists
- Manage pipeline stages

### 5.5 New Services

| Service | Repo | Responsibility |
|---------|------|---------------|
| `WebhookDispatcher` | northops-waaseyaa | Generic webhook sender (reusable for future integrations) |
| `NorthOpsWebhookSubscriber` | northops-waaseyaa | Maps pipeline events → webhook payloads with due-date inference |
| Commitment ingestion endpoint | claudriel | Accept external commitments via authenticated API |

### 5.6 Phase 4 Deliverables

- [ ] `WebhookDispatcher` service (generic, reusable)
- [ ] `NorthOpsWebhookSubscriber` (6 event-to-commitment mappings with due-date rules)
- [ ] Claudriel: commitment ingestion API endpoint
- [ ] Claudriel: `source` field on Commitment entity
- [ ] Env var gating (`CLAUDRIEL_WEBHOOK_URL`)
- [ ] Tests for webhook dispatch, event mapping, due-date inference, commitment creation

---

## 6. GitHub Project Structure

### 6.1 Project Board

**Name:** NorthOps Lead Engine & Automation

**Columns:** Backlog → Next Up → In Progress → Review → Done

**Views:**
- By repo (filter by repo label)
- By phase (filter by milestone)
- By priority (sort by priority field)
- By automation layer (think/reason/act/remember labels)

### 6.2 Milestones Per Repo

| Repo | Phase 1 Milestone | Phase 2 Milestone | Phase 3 Milestone | Phase 4 Milestone |
|------|------------------|------------------|------------------|------------------|
| `northops-waaseyaa` | CRM Foundations | Agent Integration | Outreach Engine | Claudriel Bridge |
| `jonesrussell/agency-agents` | — | NorthOps Integration | — | — |
| `north-cloud` | — | — | Signal Crawlers | — |
| `claudriel` | — | — | — | NorthOps Bridge |
| `waaseyaa/framework` | Entity Extensions | — | — | — |

Only create milestones where a repo has work. No empty milestones.

### 6.3 Label Schema

| Label | Purpose |
|-------|---------|
| `phase:1-foundations` | CRM foundations work |
| `phase:2-agents` | Agent integration work |
| `phase:3-outreach` | Outreach engine work |
| `phase:4-bridge` | Claudriel bridge work |
| `layer:think` | Scoring, routing, classification |
| `layer:reason` | Specialist-enhanced intelligence |
| `layer:act` | Outreach, signals, alerts |
| `layer:remember` | Claudriel commitments, briefs |
| `priority:p0` | Must have for milestone |
| `priority:p1` | Should have for milestone |
| `priority:p2` | Nice to have |

### 6.4 Existing Boards

The following existing project boards remain separate and are NOT consolidated:

- **NorthOps Launch (#6)** — original launch scope
- **Claudriel Roadmap (#7)** — Claudriel's own roadmap
- **NorthCloud Roadmap (#5)** — north-cloud's own roadmap

The new board is specifically for cross-system lead engine + automation work.

---

## 7. Decisions Log

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Agent loading mechanism | Filesystem via env var (`AGENCY_AGENTS_PATH`) | Zero coupling, works immediately, API extracted later when second consumer exists |
| Number of specialists (Phase 2) | 7 | Covers qualification, scoping, outreach, growth. Lean but complete. |
| Phasing model | 4 phases (2w/2w/4w/4w) | Tighter milestones, clearer deliverables, working engine at every step |
| Phase 1 scope | Intelligence layer only | Get the brain working first; outreach/automation layered on top |
| Outreach sending (Phase 3) | Manual (render in admin, human sends) | Automated sending is premature; templates + UI is the right Phase 3 deliverable |
| Claudriel role | Commitment consumer only | NorthOps owns work; Claudriel owns operations. Clean separation. |
| Due-date inference | NorthOps owns it | NorthOps knows pipeline semantics; Claudriel shouldn't decide urgency |
| Existing project boards | Keep separate | New board is cross-system lead engine scope, not a replacement |

---

## 8. Environment Variables (Cumulative)

| Variable | Repo | Phase | Purpose |
|----------|------|-------|---------|
| `ANTHROPIC_API_KEY` | northops-waaseyaa | 1 | Claude API for AI qualification (exists) |
| `COMPANY_PROFILE` | northops-waaseyaa | 1 | Company description for prompts (exists) |
| `DISCORD_WEBHOOK_URL` | northops-waaseyaa | 1 | Discord notifications (exists) |
| `NORTHCLOUD_URL` | northops-waaseyaa | 1 | north-cloud RFP API (exists) |
| `PIPELINE_API_KEY` | northops-waaseyaa | 1 | API auth for machine-to-machine (exists) |
| `AGENCY_AGENTS_PATH` | northops-waaseyaa | 2 | Filesystem path to agency-agents repo |
| `CLAUDRIEL_WEBHOOK_URL` | northops-waaseyaa | 4 | Claudriel commitment API URL |
| `CLAUDRIEL_API_KEY` | northops-waaseyaa | 4 | Claudriel API auth |

---

## 9. Risk Register

| Risk | Impact | Mitigation |
|------|--------|-----------|
| Specialist prompts too long for Claude context | Scoring quality degrades | Max 3 specialists per call, truncate to essential sections |
| north-cloud crawler rate limiting | Signal detection gaps | Implement backoff, prioritize high-value sources |
| Agency-agents upstream changes break NorthOps | Loader fails | Pin to specific commit/tag in deployment, validate frontmatter on load |
| Claudriel API not ready for Phase 4 | Bridge blocked | Webhook dispatcher is generic; can target any endpoint. Phase 4 Claudriel work is independent. |
| Solo founder bandwidth | Phases slip | Each phase produces a working system. Slippage doesn't cascade. |
