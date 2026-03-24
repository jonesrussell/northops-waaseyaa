# NorthOps Lead Pipeline — Phase 1 Design Spec

## Context

NorthOps has a proven prototype (web-networks-pipeline) that monitors RFPs, qualifies leads with Claude AI, and generates branded PDF responses. It's time to productionize this into the NorthOps/Waaseyaa platform as a full omnichannel lead generation and CRM system.

**Business model:** Russell operates two brands — **NorthOps** (company with Luc, the delivery engine) and **Web Networks** (co-op where Russell brings in work, takes a finder's fee, and subcontracts delivery to NorthOps). The platform must support both brands through a single pipeline with brand-aware output.

**Target users:** Small team (2-5) — Russell, Luc, and contractors.

**Phase 1 goal:** Core pipeline + admin dashboard + AI qualification + inbound lead capture + brand support. A working CRM that can receive, qualify, and manage leads from day one.

## Architecture

**Approach:** Modular monolith within northops-waaseyaa. Bounded contexts in `Domain/`, shared entity layer, single deploy.

```
src/
├── Domain/
│   ├── Pipeline/          # Lead lifecycle, stages, transitions
│   │   ├── LeadManager.php
│   │   ├── LeadFactory.php
│   │   ├── StageTransitionRules.php
│   │   └── EventSubscriber/
│   │       ├── LeadCreatedSubscriber.php
│   │       ├── LeadQualifiedSubscriber.php
│   │       └── StageChangedSubscriber.php
│   ├── Qualification/     # AI scoring, sector matching
│   │   ├── QualificationService.php
│   │   ├── SectorNormalizer.php
│   │   └── CompanyProfile.php
│   └── Integration/       # External service adapters (Phase 2+)
├── Entity/
│   ├── Brand.php
│   ├── Lead.php
│   ├── LeadActivity.php
│   ├── LeadAttachment.php
│   └── ContactSubmission.php  (existing — enhanced)
├── Controller/
│   ├── MarketingController.php  (existing)
│   ├── DashboardController.php  (new — admin UI)
│   └── Api/
│       └── LeadController.php   (new — JSON API)
├── Access/
│   ├── DashboardAccessPolicy.php
│   └── LeadAccessPolicy.php
└── Provider/
    ├── AppServiceProvider.php       (existing — add new routes)
    └── PipelineServiceProvider.php  (new — registers LeadManager, LeadFactory, QualificationService, SectorNormalizer, event subscribers into DI container)
```

## Data Model

### Brand Entity

| Field | Type | Notes |
|-------|------|-------|
| `id` | int (PK) | Auto-increment |
| `uuid` | string (unique) | |
| `name` | string | "NorthOps", "Web Networks" |
| `slug` | string | "northops", "web-networks" |
| `logo_path` | string | Path to logo file |
| `primary_color` | string | Hex color for UI/templates |
| `tagline` | string | Brand tagline |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### Lead Entity

Replaces prototype's `prospects` table. Entity keys: `id` → `id`, `uuid` → `uuid`, `label` → `label`.

| Field | Type | Notes |
|-------|------|-------|
| `id` | int (PK) | Auto-increment |
| `uuid` | string (unique) | |
| `label` | string | Display name |
| `brand_id` | int (FK) | Which brand faces the client |
| `source` | enum | inbound, rfp, referral, cold_outreach, partner, manual, other |
| `source_url` | string | Original listing URL (for RFPs) |
| `external_id` | string | External key (RFP slug, etc.) for dedup |
| `stage` | enum | lead, qualified, contacted, proposal, negotiation, won, lost |
| `stage_changed_at` | datetime | |
| `contact_name` | string | |
| `contact_email` | string | |
| `contact_phone` | string | |
| `company_name` | string | |
| `sector` | enum | IT, Networks, Security, Cloud, Telecom, Software, Infrastructure, DevOps, AI, Other |
| `value` | decimal | Estimated deal value in dollars |
| `finder_fee_percent` | decimal | Finder's fee percentage (e.g., 15.0 for 15%) |
| `closing_date` | datetime | |
| `qualify_rating` | int | 0–100 |
| `qualify_confidence` | float | 0–1 |
| `qualify_keywords` | JSON | Array of matching keywords |
| `qualify_notes` | text | AI summary |
| `qualify_raw` | text | Full AI response |
| `draft_email_subject` | string | |
| `draft_email_body` | text | |
| `draft_pdf_markdown` | text | Source of truth for proposals |
| `assigned_to` | string | Team member |
| `deleted_at` | datetime | Soft delete |
| `created_at` | datetime | |
| `updated_at` | datetime | |

### LeadActivity Entity

Replaces prototype's `lead_audit` table.

| Field | Type | Notes |
|-------|------|-------|
| `id` | int (PK) | |
| `uuid` | string (unique) | |
| `lead_id` | int (FK) | |
| `user_id` | string | Who performed the action |
| `action` | enum | stage_change, note, qualification, email_sent, call, meeting, created, updated |
| `payload` | JSON | Action-specific data |
| `created_at` | datetime | |

### LeadAttachment Entity

Replaces prototype's `lead_attachments` table.

| Field | Type | Notes |
|-------|------|-------|
| `id` | int (PK) | |
| `uuid` | string (unique) | |
| `lead_id` | int (FK) | |
| `filename` | string | |
| `storage_path` | string | |
| `content_type` | string | |
| `size` | int | File size in bytes (for upload validation and UI display) |
| `generated_at` | datetime | |

### ContactSubmission (existing — unchanged)

Lead auto-creation is wired directly in `MarketingController::submitContact()` — after saving the `ContactSubmission`, the controller calls `LeadFactory::fromContactSubmission($submission)` to create the Lead. No framework event system needed; this is explicit controller orchestration matching the existing pattern.

## Domain Services

### Pipeline/LeadManager

- `create(array $data): Lead` — validates, persists, fires `LeadCreated` event
- `update(Lead $lead, array $data): Lead` — updates mutable fields only
- `changeStage(Lead $lead, string $newStage): Lead` — validates transition via `StageTransitionRules`, updates `stage_changed_at`, fires `StageChanged` event
- `softDelete(Lead $lead): void` — sets `deleted_at`

### Pipeline/LeadFactory

- `fromContactSubmission(ContactSubmission $submission): Lead` — sets source=inbound, stage=lead, maps name/email/message
- `fromRfpImport(array $rfpData): Lead` — normalizes sector, sets source=rfp, maps external_id for dedup. Pre-qualified RFPs may be created directly at `qualified` stage by passing `stage: 'qualified'` in data.
- `fromManualEntry(array $data): Lead` — direct creation with validation

### Pipeline/StageTransitionRules

Allowed transitions:
- `lead` → `qualified`, `lost`
- `qualified` → `contacted`, `lost`
- `contacted` → `proposal`, `lost`
- `proposal` → `negotiation`, `lost`
- `negotiation` → `won`, `lost`
- Any stage → `lost` (always allowed)

Constraints:
- `proposal` requires `contact_email` to be set
- `won` requires `value` to be set

### Qualification/QualificationService

Port from prototype's `server.ts` qualify handler:
- Calls Claude API (claude-haiku-4-5) with lead description + company profile
- Parses structured JSON response: rating (0-100), keywords (array), sector (canonical), confidence (0-1), notes
- Updates lead with qualification fields
- Fires `LeadQualified` event
- Env: `ANTHROPIC_API_KEY`

### Qualification/SectorNormalizer

Port from prototype's `normaliseSector()` — maps free-text sectors to canonical enum values.

### Event Subscribers

- **LeadCreatedSubscriber:** Discord notification (extend existing webhook pattern from MarketingController), auto-trigger qualification for inbound leads
- **StageChangedSubscriber:** Log to LeadActivity, Discord notification for key transitions (qualified, won, lost)
- **LeadQualifiedSubscriber:** Log qualification results to LeadActivity, Discord notification with score/sector summary

## Routes & Controllers

### Admin Routes (Twig SSR)

| Route | Method | Handler |
|-------|--------|---------|
| `/admin` | GET | `DashboardController::index` — Pipeline board |
| `/admin/leads` | GET | `DashboardController::leadList` — Filterable list |
| `/admin/leads/{id}` | GET | `DashboardController::leadDetail` — Detail/edit |
| `/admin/settings` | GET | `DashboardController::settings` — Brand config |

### API Routes (JSON)

| Route | Method | Handler |
|-------|--------|---------|
| `/api/leads` | GET | List leads (filtered, paginated) |
| `/api/leads` | POST | Create lead |
| `/api/leads/{id}` | GET | Lead detail |
| `/api/leads/{id}` | PATCH | Update lead |
| `/api/leads/{id}` | DELETE | Soft delete |
| `/api/leads/{id}/stage` | PATCH | Change stage |
| `/api/leads/{id}/qualify` | POST | Trigger AI qualification |
| `/api/leads/{id}/activity` | GET | Activity timeline |
| `/api/leads/{id}/activity` | POST | Add note/activity |
| `/api/leads/{id}/attachments` | GET | List attachments |
| `/api/leads/import` | POST | Import from north-cloud (API key auth via `X-Api-Key` header, not session) |
| `/api/brands` | GET | List brands |
| `/api/config` | GET | Stages, sectors, sources |

### Access Control

- **DashboardAccessPolicy:** admin = full access, member = view + update assigned leads
- **LeadAccessPolicy:** field-level — members can't modify `brand_id`, `finder_fee`, or `deleted_at`
- Auth via Waaseyaa `AuthManager` (session-based)

## Dashboard UI

**Tech:** Twig SSR page shell + vanilla JS for interactivity (no framework).

**Views:**
1. **Pipeline board** — Kanban columns per stage, lead cards showing: label, company, score badge (color-coded 0-40/40-70/70-100), brand tag, urgency indicator (days to close), assigned_to
2. **Lead list** — Table/card view with filters: stage, brand, sector, source, assigned_to, date range. Search by name/company/email.
3. **Lead detail** — Full field edit, activity timeline, qualification results panel, attachment list, stage change buttons with transition validation
4. **Settings** — Brand management, team profile

**Interactions (vanilla JS):**
- Stage change via card drag or button click → PATCH `/api/leads/{id}/stage`
- Inline editing on lead detail → PATCH `/api/leads/{id}`
- Qualify button → POST `/api/leads/{id}/qualify` with loading state
- Add note → POST `/api/leads/{id}/activity`
- Filter/search → GET `/api/leads?stage=...&brand=...`

## Lead Flow

```
SOURCES                         PIPELINE                         NOTIFICATIONS
───────                         ────────                         ─────────────

Contact Form ──→ ContactSubmission
                  ↓ (post-save hook)
                  LeadFactory::fromContactSubmission()
                  ↓
North-cloud  ──→ /api/leads/import
                  ↓ LeadFactory::fromRfpImport()
                  ↓
Manual       ──→ /api/leads POST
                  ↓ LeadFactory::fromManualEntry()
                  ↓
              ┌───────────────┐
              │  LeadManager  │──→ Discord webhook (new lead)
              │               │──→ LeadActivity log
              │  Stages:      │
              │  lead ────────│──→ Auto-qualify (Claude AI)
              │  qualified    │
              │  contacted    │
              │  proposal     │──→ (Phase 2: PDF generation)
              │  negotiation  │──→ (Phase 2: Email sequences)
              │  won / lost   │──→ Discord webhook (outcome)
              └───────────────┘    (Phase 4: Revenue tracking)
```

## Configuration

New env vars:
- `ANTHROPIC_API_KEY` — for Claude API qualification
- `NORTHCLOUD_URL` — north-cloud API base URL (default: http://localhost:8090)
- `COMPANY_PROFILE` — NorthOps service description for AI qualification prompts

- `PIPELINE_API_KEY` — API key for machine-to-machine endpoints (e.g., `/api/leads/import`)

Added to `config/waaseyaa.php`:
```php
'pipeline' => [
    'anthropic_api_key' => getenv('ANTHROPIC_API_KEY') ?: '',
    'northcloud_url' => getenv('NORTHCLOUD_URL') ?: 'http://localhost:8090',
    'company_profile' => getenv('COMPANY_PROFILE') ?: 'NorthOps — DevOps, CI/CD, AI Engineering, Web Application Engineering',
    'default_brand' => 'northops',
    'api_key' => getenv('PIPELINE_API_KEY') ?: '',
],
```

## Phasing (Future)

| Phase | Scope | Depends on |
|-------|-------|------------|
| **1 (this spec)** | Core pipeline, dashboard, AI qualification, inbound capture, brand support | — |
| **2** | Outreach & response: email sequences, PDF generation (port LaTeX pipeline), follow-up tracking, brand templates | Phase 1 |
| **3** | External integrations: Calendly, LinkedIn, Mailchimp, webhook receivers | Phase 1 |
| **4** | Analytics & reporting: pipeline metrics, conversion rates, revenue/P&L, deal velocity | Phase 1 |

## Key Files to Port from Prototype

| Prototype file | Port to | What to extract |
|---------------|---------|-----------------|
| `server.ts` (qualify handler) | `QualificationService.php` | Claude API call, prompt, JSON parsing |
| `server.ts` (fetch-leads handler) | `LeadFactory::fromRfpImport()` | North-cloud API call, sector normalization, dedup |
| `db.ts` (schema) | Entity definitions | Field names, types, constraints |
| `lib/schemas.ts` | Validation in domain services | Stage/sector enums, field constraints |
| `public/app.js` | Dashboard JS | Lead card rendering, score badges, urgency badges, pipeline board layout |
| `public/styles.css` | Dashboard CSS | Score colors, urgency badges, card styling |

## Brand Seeding

Two brands are seeded on first deploy via a CLI command (`bin/waaseyaa pipeline:seed-brands`) or a seed script:

| Brand | Slug | Notes |
|-------|------|-------|
| NorthOps | `northops` | Primary brand, default for new leads |
| Web Networks | `web-networks` | Co-op brand, leads get `finder_fee_percent` |

The seed is idempotent — skips if brands already exist (matched by slug).

## Verification

1. **Entity persistence:** Create Brand, Lead, LeadActivity, LeadAttachment entities via CLI or test. Verify SQLite storage.
2. **Lead lifecycle:** Create lead → qualify → change stages → soft delete. Verify stage transition rules enforce constraints.
3. **AI qualification:** Trigger qualification on a test lead. Verify Claude API call returns structured data, updates lead fields.
4. **Inbound capture:** Submit contact form → verify ContactSubmission saved AND Lead auto-created with source=inbound.
5. **Dashboard:** Navigate to /admin, verify pipeline board renders with test leads. Test stage changes, filtering, search.
6. **Discord notifications:** Create lead, change stage — verify Discord embeds fire.
7. **Access control:** Test admin vs. member role restrictions.
8. **North-cloud import:** Call /api/leads/import — verify leads created with source=rfp, duplicates skipped.
