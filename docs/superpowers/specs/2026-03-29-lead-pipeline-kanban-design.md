# Lead Pipeline Kanban Board — Design Spec

**Date:** 2026-03-29
**Repos:** waaseyaa/framework, jonesrussell/northops-waaseyaa
**Status:** Approved

## Overview

Build a kanban board for the NorthOps lead pipeline as a page in the Waaseyaa admin SPA, powered entirely by the surface API. The framework gets generic primitives (filtering, sorting, custom actions); the app gets a pipeline-specific board view.

No direct REST API calls from the SPA. No Caddy routing hacks. No bypassing the surface contract.

## Part 1: Framework Extensions (waaseyaa/framework)

### 1A. Surface API List Filtering & Sorting

**Problem:** `GenericAdminSurfaceHost::list()` loads all entities into memory, then paginates. No filtering or sorting.

**Solution:** Parse `filter[]` and `sort` query parameters, delegate to `EntityStorageInterface::getQuery()`.

**Request:**
```
GET /admin/surface/lead?filter[stage][operator]=IN&filter[stage][value]=lead,qualified,contacted&sort=-created_at&page[offset]=0&page[limit]=500
```

**Response** (envelope unchanged):
```json
{
  "ok": true,
  "data": {
    "entities": [{ "type": "lead", "id": "42", "attributes": { ... } }],
    "total": 38,
    "offset": 0,
    "limit": 500
  }
}
```

**Operator vocabulary** (canonical enum):
- `EQUALS`, `NOT_EQUALS`, `IN`, `CONTAINS`, `GT`, `LT`, `GTE`, `LTE`

**Implementation:**
- New class: `SurfaceQueryParser::fromRequest(Request $request): SurfaceQuery`
- `SurfaceQuery` value object holds filters (field, operator, value), sort field/direction, page offset/limit
- `GenericAdminSurfaceHost::list()` uses `SurfaceQuery` to build `EntityQuery` via `getQuery()`
- Fallback: when storage returns `supportsQueryBuilder() === false`, apply filters in-memory (test compat)

**Breaking changes:** None. All params optional. Existing consumers unchanged.

**Files:**
- `packages/admin-surface/src/Query/SurfaceQueryParser.php` (new)
- `packages/admin-surface/src/Query/SurfaceQuery.php` (new)
- `packages/admin-surface/src/Query/SurfaceFilterOperator.php` (new, enum)
- `packages/admin-surface/src/Host/GenericAdminSurfaceHost.php` (modify `list()`)
- `packages/admin-surface/tests/Unit/Query/SurfaceQueryParserTest.php` (new)
- `packages/admin-surface/tests/Unit/Host/GenericAdminSurfaceHostTest.php` (extend)

### 1B. Custom Surface Actions

**Problem:** `GenericAdminSurfaceHost::action()` only supports `create`, `update`, `delete`, `schema`. No extension point for domain-specific operations.

**Solution:** Declarative action registration via `SurfaceActionHandler` interface.

**Interface:**
```php
interface SurfaceActionHandler
{
    public function handle(string $type, array $payload): AdminSurfaceResultData;
}
```

**Host registration (declarative):**
```php
class LeadSurfaceHost extends GenericAdminSurfaceHost
{
    protected array $actions = [
        'transition-stage' => LeadTransitionStageAction::class,
        'qualify'           => LeadQualifyAction::class,
        'board-config'      => LeadBoardConfigAction::class,
    ];
}
```

**Request/Response example:**
```
POST /admin/surface/lead/action/transition-stage
{"id": "42", "stage": "contacted"}

→ {"ok": true, "data": {"type": "lead", "id": "42", "attributes": {...}}}
```

**Implementation:**
- New interface: `SurfaceActionHandler` in `packages/admin-surface/src/Action/`
- `GenericAdminSurfaceHost::action()` checks `$this->actions` map before built-in actions
- Actions resolved via DI container for constructor injection
- Built-in actions (`create`, `update`, `delete`, `schema`) remain hardcoded — they're framework primitives, not app extensions

**Breaking changes:** None. New `$actions` property defaults to `[]`. Existing subclasses unaffected.

**Files:**
- `packages/admin-surface/src/Action/SurfaceActionHandler.php` (new, interface)
- `packages/admin-surface/src/Host/GenericAdminSurfaceHost.php` (modify `action()`)
- `packages/admin-surface/tests/Unit/Action/SurfaceActionHandlerTest.php` (new)

### 1C. TypeScript — No Changes Required

`AdminSurfaceTransportAdapter` already supports `filter`, `sort`, `page` in `ListQuery` and `runAction()` for custom actions. The adapter is ready.

**Optional enhancement:** Add typed action names per entity type for autocomplete. Not blocking.

## Part 2: NorthOps App (jonesrussell/northops-waaseyaa)

### 2A. Custom Surface Host

Replace `GenericAdminSurfaceHost` with `LeadSurfaceHost` that registers three custom actions:

| Action | Handler | Purpose |
|--------|---------|---------|
| `transition-stage` | `LeadTransitionStageAction` | Validates and applies stage transition, fires events |
| `qualify` | `LeadQualifyAction` | Triggers AI qualification via Claude API |
| `board-config` | `LeadBoardConfigAction` | Returns stages, valid transitions, sources, sectors |

**`board-config` response:**
```json
{
  "ok": true,
  "data": {
    "stages": ["lead", "qualified", "contacted", "proposal", "negotiation", "won", "lost"],
    "transitions": {
      "lead": ["qualified", "lost"],
      "qualified": ["contacted", "lost"],
      "contacted": ["proposal", "lost"],
      "proposal": ["negotiation", "lost"],
      "negotiation": ["won", "lost"]
    },
    "sources": ["inbound", "rfp", "referral", "cold_outreach", "partner", "manual", "other"],
    "sectors": ["IT", "Networks", "Security", "Cloud", "Telecom", "Software", "Infrastructure"]
  }
}
```

**Files:**
- `src/Surface/LeadSurfaceHost.php` (new)
- `src/Surface/Action/LeadTransitionStageAction.php` (new)
- `src/Surface/Action/LeadQualifyAction.php` (new)
- `src/Surface/Action/LeadBoardConfigAction.php` (new)
- `src/Provider/AppServiceProvider.php` (wire `LeadSurfaceHost`)

### 2B. Admin SPA Kanban Page

**Route:** `/admin/lead/pipeline` → `packages/admin/app/pages/lead/pipeline.vue` (Nuxt file-routing)

Wait — this page lives in the **framework** admin SPA package, but it's NorthOps-specific. Two options:

1. **App-level page override** — Nuxt layers or runtime page injection
2. **Framework page with config guard** — the page exists in the framework but only renders when `board-config` action succeeds

**Decision:** Option 2. The kanban page is a framework feature — any Waaseyaa app that registers a `board-config` action for an entity type gets a pipeline view. If the action returns 400 (unknown action), the page shows a "Pipeline not configured" message. This is the same pattern as `IngestSummaryWidget` — feature-detect via the surface API.

**Page location:** `packages/admin/app/pages/[entityType]/pipeline.vue`

This means `/admin/lead/pipeline`, `/admin/opportunity/pipeline`, etc. all work — any entity type with a `board-config` action gets a kanban board.

### 2C. Composable: `useEntityPipeline`

Framework-level composable (not NorthOps-specific):

```ts
// packages/admin/app/composables/useEntityPipeline.ts

interface PipelineState {
  leads: Map<string, PipelineCard>
  columns: Map<string, string[]>
  config: BoardConfig | null
  activeFilters: PipelineFilters
  loading: boolean
  error: string | null
}

interface PipelineCard {
  id: string
  label: string
  stage: string
  attributes: Record<string, unknown>  // all entity attributes
}

interface BoardConfig {
  stages: string[]
  transitions: Record<string, string[]>
  [key: string]: unknown  // extensible for app-specific config
}

interface PipelineFilters {
  [field: string]: { operator: string; value: string }
}
```

**Exported functions:**
- `loadBoard(entityType)` — fetch `board-config` + filtered entity list
- `moveCard(entityType, id, toStage)` — optimistic transition with rollback
- `applyFilters(filters)` — re-fetch with new filter params
- `runCardAction(entityType, action, payload)` — generic action dispatch (qualify, etc.)

### 2D. Card Component

**Location:** `packages/admin/app/components/pipeline/PipelineCard.vue`

**Props:**
```ts
defineProps<{
  card: PipelineCard
  density: CardDensity  // 'compact' | 'standard' | 'detailed'
  config?: BoardConfig
}>()
```

**Emits:** `open-detail`, `transition-stage`, `run-action`

The card is pure presentation. It renders fields from `card.attributes` based on `density`. NorthOps ships with `detailed` density showing: label, company_name, contact_name, contact_email, qualify_rating (score badge), value, source, sector, brand, closing_date, stage_changed_at.

**Field rendering is driven by the entity schema** — the card fetches the schema once via `runAction(type, 'schema')` and uses field types to format values. No hardcoded field names in the framework component. NorthOps-specific field display order can be configured via `board-config` response (optional `cardFields` array).

### 2E. Navigation

Sidebar link for "Lead" goes to `/admin/lead` (entity list). A tab bar component (`EntityViewNav.vue`) appears on both the list page and pipeline page:

```
[List] [Pipeline]
```

The pipeline tab only appears when the entity type has a `board-config` action (feature-detected on mount).

### 2F. Drag-and-Drop

Use HTML5 native drag-and-drop (no library dependency). The pipeline page handles:
- `dragstart` on card → set `dataTransfer` with lead ID
- `dragover` on column → highlight drop zone, validate transition via `config.transitions`
- `drop` on column → call `moveCard()` with optimistic UI update

Invalid transitions (e.g., `lead` → `won`) show the column as non-droppable (no highlight, cursor: not-allowed).

## Migration & Breaking Changes

**None.** All changes are additive:

| Change | Breaking? | Why |
|--------|-----------|-----|
| `SurfaceQueryParser` + filter params | No | New params, optional |
| `SurfaceActionHandler` interface | No | New interface, opt-in |
| `$actions` property on host | No | Defaults to `[]` |
| `[entityType]/pipeline.vue` page | No | New route, no conflict |
| `useEntityPipeline` composable | No | New file |
| `PipelineCard` component | No | New file |

Existing surface consumers (Minoo, other Waaseyaa apps) are unaffected.

## Testing Strategy

**Framework (waaseyaa):**
- `SurfaceQueryParserTest` — parse filters, sort, pagination from request
- `SurfaceFilterOperator` enum — validate operator vocabulary
- `GenericAdminSurfaceHostTest` — extend with filter/sort assertions
- `SurfaceActionHandlerTest` — custom action routing, unknown action 400
- Admin SPA: Vitest tests for `useEntityPipeline` composable
- Admin SPA: Component test for `PipelineCard` with different densities

**App (northops-waaseyaa):**
- PHPUnit: `LeadTransitionStageAction` — valid/invalid transitions, event dispatch
- PHPUnit: `LeadQualifyAction` — API call mock, field updates
- PHPUnit: `LeadBoardConfigAction` — correct stage/transition map
- E2E (Playwright): Login → /admin/lead/pipeline → verify columns render → drag card → verify transition

## Issue Breakdown

### waaseyaa/framework

1. **Surface API: Add list filtering and sorting** — `SurfaceQueryParser`, `SurfaceQuery`, `SurfaceFilterOperator` enum, modify `GenericAdminSurfaceHost::list()`
2. **Surface API: Add custom action handler extension point** — `SurfaceActionHandler` interface, `$actions` property, DI resolution
3. **Admin SPA: Pipeline page and composable** — `[entityType]/pipeline.vue`, `useEntityPipeline.ts`, `PipelineCard.vue`, `EntityViewNav.vue`

### jonesrussell/northops-waaseyaa

4. **Lead surface host with custom actions** — `LeadSurfaceHost`, `LeadTransitionStageAction`, `LeadQualifyAction`, `LeadBoardConfigAction`
5. **E2E: Verify lead pipeline kanban board** — Playwright test for full login → kanban → drag-and-drop flow
