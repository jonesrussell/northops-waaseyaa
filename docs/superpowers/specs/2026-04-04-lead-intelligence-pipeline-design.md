# Lead Intelligence Pipeline v1 — Design Spec

**Date:** 2026-04-04
**Status:** Approved
**Repos:** jonesrussell/northops-waaseyaa, jonesrussell/north-cloud, jonesrussell/jonesrussell

## Overview

End-to-end architecture for signal ingestion, lead enrichment, and pipeline intelligence across the NorthOps platform.

**Waaseyaa** (northops.ca) is the canonical source of truth for leads: the CRM, the pipeline, and the public website in one application.

**North-cloud** is the upstream signal and enrichment provider: crawlers, RFP ingestion, funding signals, hiring signals, tech migrations, and company intelligence.

A clean API boundary separates the two systems. North-cloud pushes signals and enrichment data to Waaseyaa. Waaseyaa requests enrichment from north-cloud when needed. Neither system depends on the other's internals.

## 1. Domain Model

Three entities with clear responsibilities.

### Lead (existing, unchanged)

The human-facing CRM object. Owned by sales. Contains contact info, pipeline stage, qualification scores, deal value, sector, source. Entity class `App\Entity\Lead` extends `ContentEntityBase`. No schema changes.

### LeadSignal (new)

Raw intelligence from upstream sources. Immutable after creation (append-only). Multiple signals can map to one lead, or exist unmatched (`lead_id = null`).

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | int | auto | Primary key |
| uuid | string | auto | External reference |
| label | string | yes | Signal title/summary |
| lead_id | int | no | FK to Lead (null = unmatched) |
| signal_type | string | yes | rfp, funding_win, job_posting, tech_migration, outdated_website, hn_mention, new_program |
| source | string | yes | north-cloud, signal-crawler |
| source_url | string | no | Original URL |
| external_id | string | yes | Dedup key from upstream |
| strength | int | yes | 0-100 signal quality/relevance score |
| payload | JSON | no | Full raw data from upstream |
| organization_name | string | no | Detected organization |
| sector | string | no | Normalized sector |
| province | string | no | Geographic signal |
| expires_at | datetime | no | For time-bound signals (RFP closing dates) |
| created_at | datetime | yes | Ingestion timestamp |

Entity class: `App\Entity\LeadSignal` extends `ContentEntityBase`.

### LeadEnrichment (new)

Structured intelligence attached to a lead. Multiple enrichments per lead, from different providers or at different times. Append-only: new enrichments are created, never updated.

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| id | int | auto | Primary key |
| uuid | string | auto | External reference |
| label | string | yes | Enrichment summary |
| lead_id | int | yes | FK to Lead |
| provider | string | yes | north-cloud, anthropic, manual |
| enrichment_type | string | yes | company_intel, contact_discovery, tech_stack, financial, competitor_analysis |
| data | JSON | yes | Structured enrichment payload |
| confidence | float | yes | 0.0-1.0 provider confidence score |
| created_at | datetime | yes | When enrichment was received |

Entity class: `App\Entity\LeadEnrichment` extends `ContentEntityBase`.

### Relationships

- Lead 1:N LeadSignal (a lead can have many source signals)
- Lead 1:N LeadEnrichment (a lead can have many enrichment records)
- Lead 1:N LeadActivity (existing, unchanged)
- LeadSignal can exist without a Lead (unmatched signals)

## 2. API Boundary

Three endpoints with clear responsibilities.

### POST /api/signals — Ingest raw signals (batch)

**Auth:** `X-Api-Key` header (machine-to-machine, same pattern as existing import endpoint).

**Request:**
```json
{
  "signals": [
    {
      "signal_type": "rfp",
      "external_id": "canadabuys-12345",
      "source": "north-cloud",
      "source_url": "https://canadabuys.canada.ca/...",
      "label": "Web Application Development Services",
      "strength": 75,
      "organization_name": "Health Canada",
      "sector": "government",
      "province": "ON",
      "expires_at": "2026-05-15T00:00:00Z",
      "payload": {}
    }
  ]
}
```

**Behavior:**
1. Validate and dedup by `external_id` + `source` (skip duplicates)
2. Create LeadSignal entity for each new signal
3. Attempt lead matching (by external_id, then org name normalization)
4. Auto-create Lead if strength >= threshold (default 50) and no match
5. Store unmatched if below threshold
6. Fire `SignalIngestedEvent` for each signal

**Response (201):**
```json
{
  "jsonapi": {"version": "1.1"},
  "data": {"ingested": 5, "skipped": 2, "leads_created": 3, "leads_matched": 1, "unmatched": 1}
}
```

### POST /api/leads/{id}/enrich — Request enrichment (pull)

**Auth:** Session (admin user) or `X-Api-Key`.

**Request (optional):**
```json
{"types": ["company_intel", "tech_stack"]}
```

If omitted, requests all available types.

**Behavior:** Load lead, build payload from lead data + linked signals, POST to north-cloud `/api/v1/enrich` with callback URL. Fire-and-forget: north-cloud calls back asynchronously.

**Response (200):**
```json
{
  "jsonapi": {"version": "1.1"},
  "data": {"enrichments_requested": 2, "types": ["company_intel", "tech_stack"]}
}
```

### POST /api/leads/{id}/enrichment — Receive enrichment push

**Auth:** `X-Api-Key` header.

**Request:**
```json
{
  "provider": "north-cloud",
  "enrichment_type": "company_intel",
  "confidence": 0.85,
  "data": {
    "website": "https://example.gc.ca",
    "tech_stack": ["WordPress", "PHP 7.4"],
    "employee_count": 500,
    "hiring_signals": ["Senior Developer", "DevOps Engineer"]
  }
}
```

**Behavior:** Validate lead exists, create LeadEnrichment entity, fire `LeadEnrichedEvent`.

**Response (201):** JSON:API confirmation.

### Design decisions

- **Batch signals, single enrichment.** Signals come in bulk. Enrichment is per-lead.
- **Two enrichment paths.** Pull (Waaseyaa asks north-cloud) and push (north-cloud sends back). Pull for user-initiated, push for async/scheduled.
- **Idempotency.** Signal dedup by `external_id` + `source`. Enrichment is append-only (multiple enrichments of same type are valid: data at different points in time).

## 3. Ingestion Flows

### Flow A: Manual/Website origin → Enrichment

```
User submits contact form
  → ContactSubmission saved
  → Lead created (source: inbound)
  → If auto-enrich enabled: POST to north-cloud /api/v1/enrich
  → North-cloud calls back POST /api/leads/{id}/enrichment
  → LeadEnrichment entities created
  → LeadEnrichedEvent fired
```

### Flow B: North-cloud signal origin → Lead + Signal

```
North-cloud detects signal
  → POST /api/signals with batch
  → SignalIngestionService validates, deduplicates
  → For each signal:
      1. Create LeadSignal
      2. Match to existing Lead (external_id, then org name)
      3. Auto-create Lead if strength >= threshold
      4. Fire SignalIngestedEvent
  → Optionally trigger enrichment for new leads
```

### Flow C: User-initiated enrichment

```
User clicks "Enrich" on lead detail
  → POST /api/leads/{id}/enrich
  → EnrichmentService builds payload from lead + signals
  → POST to north-cloud /api/v1/enrich
  → North-cloud calls back with results
```

### Signal-to-Lead matching rules

| Priority | Strategy | Confidence |
|----------|----------|------------|
| 1 | `external_id` + `source` exact match on Lead | 1.0 |
| 2 | `organization_name` normalized against `company_name` | 0.8 |
| 3 | No match, strength >= threshold | Auto-create lead |
| 4 | No match, strength < threshold | Store unmatched |

Organization name normalization: strip legal suffixes (Inc, Ltd, Corp, LLC, Limited, Incorporated, Corporation), lowercase, trim. Lives in `SignalMatcher`, testable in isolation.

### RfpImportService migration

1. Build new signal ingestion endpoint
2. North-cloud signal producer pushes to Waaseyaa
3. Run both paths in parallel (1-2 weeks)
4. Deprecate `RfpImportService` and `pipeline:import-rfps` CLI
5. Remove after confirming stability

### Event model

| Event | Fired when | Subscribers |
|-------|-----------|-------------|
| SignalIngestedEvent | New LeadSignal created | Discord notification, activity log |
| LeadEnrichedEvent | New LeadEnrichment created | Discord notification, activity log, score update |
| LeadCreatedEvent | (existing) Lead created | Existing subscribers + optional auto-enrich |

## 4. Waaseyaa Implementation

### New files

```
src/
├── Entity/
│   ├── LeadSignal.php
│   └── LeadEnrichment.php
├── Domain/
│   ├── Signal/
│   │   ├── SignalIngestionService.php
│   │   ├── SignalMatcher.php
│   │   ├── IngestResult.php
│   │   └── Event/SignalIngestedEvent.php
│   ├── Enrichment/
│   │   ├── EnrichmentService.php
│   │   ├── EnrichmentReceiver.php
│   │   └── Event/LeadEnrichedEvent.php
│   └── Pipeline/EventSubscriber/
│       ├── SignalIngestedSubscriber.php
│       └── LeadEnrichedSubscriber.php
├── Controller/Api/
│   ├── SignalController.php
│   └── EnrichmentController.php
└── Provider/
    └── SignalServiceProvider.php
```

### Entities

Both extend `ContentEntityBase`, registered in `config/entity-types.php` with keys `id`, `uuid`, `label`. All fields stored in `_data` JSON column (standard Waaseyaa pattern).

LeadSignal is immutable after creation: no `update()` path. LeadEnrichment is append-only.

### Domain services

**SignalIngestionService** (`App\Domain\Signal\SignalIngestionService`):
- `ingest(array $signals): IngestResult`
- Validates required fields, deduplicates via EntityQuery, delegates to SignalMatcher for lead linking, delegates to LeadFactory::fromSignal() for auto-creation.
- Returns `IngestResult` value object with counts.

**SignalMatcher** (`App\Domain\Signal\SignalMatcher`):
- `match(array $signalData): ?Lead`
- Priority 1: exact external_id lookup. Priority 2: normalized org name match.
- `normalizeOrgName(string $name): string` strips Inc/Ltd/Corp/LLC, lowercases, trims.

**EnrichmentService** (`App\Domain\Enrichment\EnrichmentService`):
- `requestEnrichment(Lead $lead, array $types = []): void`
- Builds payload from lead + linked signals, POSTs to north-cloud. Fire-and-forget.

**EnrichmentReceiver** (`App\Domain\Enrichment\EnrichmentReceiver`):
- `receive(Lead $lead, array $enrichmentData): LeadEnrichment`
- Validates, creates entity, fires LeadEnrichedEvent.

### Controllers

**SignalController**: `ingest(Request): JsonResponse`. Validates API key, delegates to SignalIngestionService.

**EnrichmentController**: Two actions:
- `requestEnrichment(Request, string $id): JsonResponse` (session or API key)
- `receiveEnrichment(Request, string $id): JsonResponse` (API key only)

### Service provider

**SignalServiceProvider** registered in `composer.json` `extra.waaseyaa.providers`. Owns route registration and service construction for all signal/enrichment components.

### LeadFactory addition

New method `fromSignal(array $signalData, int $brandId): ?Lead`. Maps signal fields to lead fields:

| Signal field | Lead field |
|-------------|-----------|
| label | label |
| organization_name | company_name |
| source_url | source_url |
| external_id | external_id |
| sector | sector |
| signal_type → mapped | source (rfp→rfp, funding_win→referral, job_posting→cold_outreach, other→other) |

### Config additions

```php
// config/waaseyaa.php → 'pipeline' section
'signal_auto_create_threshold' => (int) (getenv('SIGNAL_AUTO_CREATE_THRESHOLD') ?: 50),
'signal_auto_enrich' => filter_var(getenv('SIGNAL_AUTO_ENRICH') ?: true, FILTER_VALIDATE_BOOLEAN),
```

### Tests

| Test | Coverage |
|------|----------|
| LeadSignalTest | Entity hydration, typed getters, defaults |
| LeadEnrichmentTest | Entity hydration, typed getters |
| SignalIngestionServiceTest | Validate, dedup, create, match, auto-create, batch |
| SignalMatcherTest | Exact match, org name normalization, no match |
| EnrichmentReceiverTest | Create enrichment, fire event, validation |
| SignalControllerTest | API key auth, batch ingest, error responses |
| EnrichmentControllerTest | Request + receive endpoints |
| LeadFactorySignalTest | fromSignal field mapping |

## 5. North-cloud Implementation

### Signal producer

New standalone Go binary at `signal-producer/`. Runs on 15-minute schedule via cron/systemd timer.

**Flow:**
1. Load checkpoint timestamp (last successful run)
2. Query ES `*_classified_content` for docs since checkpoint (content_type in rfp/need_signal, quality_score >= 40)
3. Map each hit to Waaseyaa signal payload format
4. Batch into groups of 50
5. POST each batch to Waaseyaa `POST /api/signals` with API key
6. Advance checkpoint past successfully delivered batches
7. Log results

**ES query:**
```json
{
  "query": {
    "bool": {
      "must": [
        {"range": {"crawled_at": {"gte": "CHECKPOINT_TIMESTAMP"}}},
        {"terms": {"content_type": ["rfp", "need_signal"]}}
      ],
      "filter": [{"range": {"quality_score": {"gte": 40}}}]
    }
  },
  "sort": [{"crawled_at": "asc"}],
  "size": 100
}
```

**Mapper rules:**

For RFP hits: external_id = "nc-rfp-{_id}", signal_type = "rfp", maps rfp.organization_name, rfp.closing_date → expires_at.

For need_signal hits: external_id = "nc-sig-{_id}", signal_type from need_signal.signal_type, maps need_signal.organization_name.

**Checkpoint:** JSON file, persists last_successful_run timestamp. Lookback buffer of 5 minutes to catch ES indexing delays. Defaults to 24 hours ago on first run.

### Enrichment service

New Go HTTP server at `enrichment/`. Long-running service on port 8095.

**Endpoint:** `POST /api/v1/enrich`

**Request from Waaseyaa:**
```json
{
  "lead_id": 42,
  "company_name": "Health Canada",
  "domain": "canada.ca",
  "sector": "government",
  "requested_types": ["company_intel", "tech_stack"],
  "signals": [{"signal_type": "rfp", "label": "...", "strength": 75}],
  "callback_url": "https://northops.ca/api/leads/42/enrichment",
  "callback_api_key": "xxx"
}
```

**Flow:** Validate, spawn enricher goroutines per type, each calls back independently. Returns 202 Accepted immediately.

**Enricher interface:**
```go
type Enricher interface {
    Type() string
    Enrich(ctx context.Context, req EnrichRequest) (*EnrichResult, error)
}
```

**v1 enrichers:**

| Type | ES query | Output |
|------|----------|--------|
| company_intel | Articles mentioning org name (90 days) | Recent articles, sectors, first/last seen |
| tech_stack | need_signals with outdated_website/tech_migration for org | Technologies, migration intent, outdated flags |
| hiring | need_signals with job_posting for org | Open roles, role count, growth indicator |

**Confidence:** Based on data quality (0 results = 0.3, 1-5 = 0.6, 5+ = 0.85).

### Retry and idempotency

**Signal producer retries:** 3 retries with exponential backoff (1s, 5s, 15s). Checkpoint only advances past successfully delivered batches.

**Enrichment callback retries:** Same 3-retry pattern. On persistent failure: log error, enrichment is lost (acceptable for v1).

**Idempotency:** Signal ingestion deduplicated by external_id + source on Waaseyaa side. Enrichment is append-only (duplicate callbacks create duplicate records, timestamped).

### Config

```yaml
# signal-producer
waaseyaa:
  url: "${WAASEYAA_URL}"
  api_key: "${WAASEYAA_API_KEY}"
  batch_size: 50
  min_quality_score: 40
schedule:
  lookback_buffer: "5m"

# enrichment service
server:
  port: 8095
enrichment:
  enabled_types: ["company_intel", "tech_stack", "hiring"]
  callback_timeout: "30s"
  max_retries: 3
```

## 6. UI Integration

### Pipeline board cards

Add signal/enrichment counts below existing card content. Counts from API (signal_count, enrichment_count computed via entity queries). Only show when either count > 0.

### Lead detail tabs

Restructure into 4 tabs:

1. **Overview** (default): existing content + "Enrich" button (calls POST /api/leads/{id}/enrich)
2. **Signals**: chronological LeadSignal list with type badges, strength, expandable payload JSON
3. **Enrichment**: grouped by enrichment_type, structured rendering per type (company_intel, tech_stack, hiring), raw JSON toggle
4. **Activity**: existing LeadActivity timeline (moved from inline to tab)

### New read endpoints

- `GET /api/leads/{id}/signals` — all signals for a lead, ordered by created_at desc
- `GET /api/leads/{id}/enrichments` — all enrichments for a lead, ordered by created_at desc
- `GET /api/leads` — add signal_count and enrichment_count to each lead in list response

### Unmatched signals view

New admin page `/admin/signals/unmatched`. Lists LeadSignals where `lead_id IS NULL`. Actions per row: "Create Lead" (creates and links), "Link to existing" (dropdown), "Dismiss" (deletes signal). Count badge in admin sidebar.

### v1 scope

| Include | Defer |
|---------|-------|
| Signal/enrichment counts on cards | Real-time signal streaming |
| Tabbed detail view | Signal search/filter |
| Signals + Enrichment tabs | Enrichment comparison |
| Unmatched signals list | Auto-link suggestions |
| Manual "Enrich" button | Scheduled auto-enrichment |
| "Create Lead" from signal | Bulk signal operations |

## GitHub Issue Map

### Umbrella (jonesrussell/jonesrussell) — Milestone: Lead Intelligence Pipeline v1

| Issue | Title |
|-------|-------|
| #47 | Domain Model |
| #48 | API Boundary |
| #49 | Ingestion Flows |
| #50 | Waaseyaa Implementation |
| #51 | North-cloud Implementation |
| #52 | UI Integration |
| #53 | Migration Plan |
| #54 | Future Extensions |

### Waaseyaa (jonesrussell/northops-waaseyaa) — Milestone: Lead Intelligence Integration (Waaseyaa)

| Issue | Title |
|-------|-------|
| #110 | LeadSignal entity |
| #111 | LeadEnrichment entity |
| #112 | SignalIngestionService |
| #113 | SignalMatcher |
| #114 | EnrichmentService |
| #115 | EnrichmentReceiver |
| #116 | SignalController |
| #117 | EnrichmentController |
| #118 | SignalServiceProvider + config + LeadFactory.fromSignal |
| #119 | Event subscribers |
| #120 | GET endpoints for signals/enrichments |
| #121 | Deprecate RfpImportService |
| #122 | UI: pipeline card counts |
| #123 | UI: tabbed lead detail |
| #124 | UI: signals tab |
| #125 | UI: enrichment tab |
| #126 | UI: unmatched signals view |

### North-cloud (jonesrussell/north-cloud) — Milestone: Lead Intelligence Integration (north-cloud)

| Issue | Title |
|-------|-------|
| #592 | Signal producer binary |
| #593 | ES → Waaseyaa mapper |
| #594 | Waaseyaa HTTP client |
| #595 | Checkpoint persistence |
| #596 | Enrichment service |
| #597 | Enricher implementations |
| #598 | Enrichment callback client |
| #599 | Signal producer deployment |
| #600 | Enrichment service deployment |

## Implementation Order

**Phase 1: Foundation (Waaseyaa)**
1. LeadSignal + LeadEnrichment entities (#110, #111)
2. SignalMatcher (#113)
3. SignalIngestionService (#112)
4. LeadFactory.fromSignal + config (#118)

**Phase 2: API (Waaseyaa)**
5. SignalController (#116)
6. EnrichmentReceiver + EnrichmentService (#115, #114)
7. EnrichmentController (#117)
8. SignalServiceProvider wiring (#118)
9. Event subscribers (#119)
10. Read endpoints (#120)

**Phase 3: North-cloud**
11. ES mapper (#593)
12. Waaseyaa client (#594)
13. Checkpoint (#595)
14. Signal producer binary (#592)
15. Enricher implementations (#597)
16. Callback client (#598)
17. Enrichment service (#596)

**Phase 4: UI (Waaseyaa + Admin SPA)**
18. Pipeline card counts (#122)
19. Tabbed lead detail (#123)
20. Signals tab (#124)
21. Enrichment tab (#125)
22. Unmatched signals view (#126)

**Phase 5: Migration**
23. Parallel run, then deprecate RfpImportService (#121)

**Phase 6: Deployment**
24. Signal producer deployment (#599)
25. Enrichment service deployment (#600)
