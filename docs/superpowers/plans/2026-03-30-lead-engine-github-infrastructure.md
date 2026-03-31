# Lead Engine & Automation — GitHub Infrastructure Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create the complete GitHub project board, milestones, labels, and issues across 5 repos for the NorthOps Lead Engine & Automation initiative.

**Architecture:** One GitHub Project (v2) board with 5 views. Milestones created only where repos have work. Issues created with full implementation descriptions so each issue is self-contained enough for a developer to pick up.

**Tech Stack:** GitHub CLI (`gh`), GitHub Projects v2 API

**Spec:** `docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md`

---

## File Structure

No files created or modified — this plan operates entirely via `gh` CLI commands to create GitHub infrastructure.

**Repos involved:**
- `jonesrussell/northops-waaseyaa`
- `jonesrussell/agency-agents`
- `jonesrussell/north-cloud`
- `jonesrussell/claudriel`
- `waaseyaa/framework`

---

### Task 1: Create GitHub Project Board

**Files:** None (GitHub API only)

- [ ] **Step 1: Create the project**

```bash
gh project create --owner jonesrussell --title "NorthOps Lead Engine & Automation" --format json
```

Expected: JSON output with project number and ID. Record the project number for later steps.

- [ ] **Step 2: Record the project number**

The output will contain a `number` field. Save it:

```bash
# Example: if project number is 10
PROJECT_NUM=10
```

- [ ] **Step 3: Verify the project exists**

```bash
gh project list --owner jonesrussell --format json | grep "Lead Engine"
```

Expected: One matching project.

- [ ] **Step 4: Commit progress note**

No git commit needed — this is GitHub-only infrastructure.

---

### Task 2: Create Labels Across All 5 Repos

**Files:** None (GitHub API only)

Labels are created in each repo that will have issues. Labels use a consistent color scheme.

- [ ] **Step 1: Create phase labels in northops-waaseyaa**

```bash
gh label create "phase:1-foundations" --repo jonesrussell/northops-waaseyaa --color "1D76DB" --description "Phase 1: CRM Foundations"
gh label create "phase:2-agents" --repo jonesrussell/northops-waaseyaa --color "5319E7" --description "Phase 2: Agent Integration"
gh label create "phase:3-outreach" --repo jonesrussell/northops-waaseyaa --color "0E8A16" --description "Phase 3: Outreach Engine"
gh label create "phase:4-bridge" --repo jonesrussell/northops-waaseyaa --color "D93F0B" --description "Phase 4: Claudriel Bridge"
```

- [ ] **Step 2: Create layer labels in northops-waaseyaa**

```bash
gh label create "layer:think" --repo jonesrussell/northops-waaseyaa --color "C2E0C6" --description "Scoring, routing, classification"
gh label create "layer:reason" --repo jonesrussell/northops-waaseyaa --color "BFDADC" --description "Specialist-enhanced intelligence"
gh label create "layer:act" --repo jonesrussell/northops-waaseyaa --color "FEF2C0" --description "Outreach, signals, alerts"
gh label create "layer:remember" --repo jonesrussell/northops-waaseyaa --color "F9D0C4" --description "Claudriel commitments, briefs"
```

- [ ] **Step 3: Create priority labels in northops-waaseyaa**

```bash
gh label create "priority:p0" --repo jonesrussell/northops-waaseyaa --color "B60205" --description "Must have for milestone"
gh label create "priority:p1" --repo jonesrussell/northops-waaseyaa --color "D93F0B" --description "Should have for milestone"
gh label create "priority:p2" --repo jonesrussell/northops-waaseyaa --color "FBCA04" --description "Nice to have"
```

- [ ] **Step 4: Create labels in agency-agents**

```bash
gh label create "phase:2-agents" --repo jonesrussell/agency-agents --color "5319E7" --description "Phase 2: Agent Integration"
gh label create "layer:reason" --repo jonesrussell/agency-agents --color "BFDADC" --description "Specialist-enhanced intelligence"
gh label create "priority:p0" --repo jonesrussell/agency-agents --color "B60205" --description "Must have for milestone"
gh label create "priority:p1" --repo jonesrussell/agency-agents --color "D93F0B" --description "Should have for milestone"
```

- [ ] **Step 5: Create labels in north-cloud**

```bash
gh label create "phase:3-outreach" --repo jonesrussell/north-cloud --color "0E8A16" --description "Phase 3: Outreach Engine"
gh label create "layer:act" --repo jonesrussell/north-cloud --color "FEF2C0" --description "Outreach, signals, alerts"
gh label create "priority:p0" --repo jonesrussell/north-cloud --color "B60205" --description "Must have for milestone"
gh label create "priority:p1" --repo jonesrussell/north-cloud --color "D93F0B" --description "Should have for milestone"
```

- [ ] **Step 6: Create labels in claudriel**

```bash
gh label create "phase:4-bridge" --repo jonesrussell/claudriel --color "D93F0B" --description "Phase 4: Claudriel Bridge"
gh label create "layer:remember" --repo jonesrussell/claudriel --color "F9D0C4" --description "Claudriel commitments, briefs"
gh label create "priority:p0" --repo jonesrussell/claudriel --color "B60205" --description "Must have for milestone"
gh label create "priority:p1" --repo jonesrussell/claudriel --color "D93F0B" --description "Should have for milestone"
```

- [ ] **Step 7: Create labels in waaseyaa/framework**

```bash
gh label create "phase:1-foundations" --repo waaseyaa/framework --color "1D76DB" --description "Phase 1: CRM Foundations"
gh label create "layer:think" --repo waaseyaa/framework --color "C2E0C6" --description "Scoring, routing, classification"
gh label create "priority:p0" --repo waaseyaa/framework --color "B60205" --description "Must have for milestone"
gh label create "priority:p1" --repo waaseyaa/framework --color "D93F0B" --description "Should have for milestone"
```

- [ ] **Step 8: Verify labels exist**

```bash
gh label list --repo jonesrussell/northops-waaseyaa | grep "phase:\|layer:\|priority:"
```

Expected: 11 labels in northops-waaseyaa, 4 in agency-agents, 3 in north-cloud, 3 in claudriel, 3 in waaseyaa/framework.

---

### Task 3: Create Milestones

**Files:** None (GitHub API only)

Milestones are created only in repos where that phase has work.

- [ ] **Step 1: Create milestones in northops-waaseyaa (all 4 phases)**

```bash
gh api repos/jonesrussell/northops-waaseyaa/milestones -f title="Phase 1: CRM Foundations" -f description="Entity fields, routing service, brand-specific scoring, score decay. 2-week target." -f state="open"
gh api repos/jonesrussell/northops-waaseyaa/milestones -f title="Phase 2: Agent Integration" -f description="AgentPromptLoader, SpecialistSelector, 7 specialists enhance scoring. 2-week target." -f state="open"
gh api repos/jonesrussell/northops-waaseyaa/milestones -f title="Phase 3: Outreach Engine" -f description="Signal ingestion, audit pipeline, outreach templates, Discord T1 alerts. 4-week target." -f state="open"
gh api repos/jonesrussell/northops-waaseyaa/milestones -f title="Phase 4: Claudriel Bridge" -f description="WebhookDispatcher, event-to-commitment mappings, due-date inference. 4-week target." -f state="open"
```

- [ ] **Step 2: Create milestone in agency-agents**

```bash
gh api repos/jonesrussell/agency-agents/milestones -f title="Phase 2: NorthOps Integration" -f description="Create AI engineer agent, document NorthOps integration, verify frontmatter consistency." -f state="open"
```

- [ ] **Step 3: Create milestone in north-cloud**

```bash
gh api repos/jonesrussell/north-cloud/milestones -f title="Phase 3: Signal Crawlers" -f description="HN intent crawler, Reddit intent crawler, funding monitor crawler. 4-week target." -f state="open"
```

- [ ] **Step 4: Create milestone in claudriel**

```bash
gh api repos/jonesrussell/claudriel/milestones -f title="Phase 4: NorthOps Bridge" -f description="Commitment ingestion endpoint, source field on Commitment entity. 4-week target." -f state="open"
```

- [ ] **Step 5: Create milestone in waaseyaa/framework**

```bash
gh api repos/waaseyaa/framework/milestones -f title="Phase 1: Entity Extensions" -f description="Any entity system changes needed to support new Lead fields (JSON, timestamp types)." -f state="open"
```

- [ ] **Step 6: Record milestone numbers**

```bash
gh api repos/jonesrussell/northops-waaseyaa/milestones --jq '.[] | "\(.number) \(.title)"'
gh api repos/jonesrussell/agency-agents/milestones --jq '.[] | "\(.number) \(.title)"'
gh api repos/jonesrussell/north-cloud/milestones --jq '.[] | "\(.number) \(.title)"'
gh api repos/jonesrussell/claudriel/milestones --jq '.[] | "\(.number) \(.title)"'
gh api repos/waaseyaa/framework/milestones --jq '.[] | "\(.number) \(.title)"'
```

Record all milestone numbers for use in issue creation.

---

### Task 4: Create Phase 1 Issues (northops-waaseyaa + waaseyaa/framework)

**Files:** None (GitHub API only)

Use the milestone number from Task 3 Step 6 for `northops-waaseyaa` Phase 1 milestone.

- [ ] **Step 1: Create issue — Add 9 new Lead entity fields**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Add 9 new fields to Lead entity type" \
  --label "phase:1-foundations,layer:think,priority:p0" \
  --milestone "Phase 1: CRM Foundations" \
  --body "$(cat <<'EOF'
## Summary

Add 9 new fields to the Lead entity type definition to support brand-specific scoring, routing, and specialist context.

## Fields

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
| `specialist_context` | JSON | `{ specialists: [...], reasoning: {...} }` | Audit trail (field added Phase 1, populated Phase 2) |

## Implementation

- Modify Lead entity type definition in `src/Entity/` to register new fields
- If `waaseyaa/framework` doesn't support JSON or timestamp field types for entities, that work is tracked separately in waaseyaa/framework
- Update any admin UI forms that display lead fields
- Add field definitions to `fieldDefinitions()` method

## Testing

- Unit test: create Lead with all new fields, verify storage and retrieval
- Unit test: verify field defaults (null/empty for optional fields)

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 2.1
EOF
)"
```

- [ ] **Step 2: Create issue — RoutingService**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Implement RoutingService with 12 routing rules" \
  --label "phase:1-foundations,layer:think,priority:p0" \
  --milestone "Phase 1: CRM Foundations" \
  --body "$(cat <<'EOF'
## Summary

Create `RoutingService` in `src/Domain/Pipeline/` that assigns brand and routing confidence when a lead is created.

## Routing Rules (ordered by confidence)

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

## Files

- Create: `src/Domain/Pipeline/RoutingService.php`
- Create: `tests/Unit/Domain/Pipeline/RoutingServiceTest.php`
- Modify: `src/Provider/PipelineServiceProvider.php` (register service)

## Interface

```php
class RoutingService
{
    public function route(array $leadData): RoutingResult
    // Returns: RoutingResult { brand: string, confidence: int, rule: string }
}
```

## Testing

- Test each of the 12 rules individually
- Test rule precedence (higher confidence wins)
- Test dual-brand fallback (confidence <60%)
- Test no-match fallback (manual review, confidence 0)

## Dependencies

- Depends on: Lead entity fields issue (needs `lead_source`, `organization_type`)

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 2.2
EOF
)"
```

- [ ] **Step 3: Create issue — BrandScoringContext**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Implement BrandScoringContext for brand-specific AI prompts" \
  --label "phase:1-foundations,layer:think,priority:p0" \
  --milestone "Phase 1: CRM Foundations" \
  --body "$(cat <<'EOF'
## Summary

Create `BrandScoringContext` in `src/Domain/Qualification/` that returns brand-specific prompt context for AI qualification via Claude API.

## Prompt Structure

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

## Brand Contexts

**NorthOps:** ICP = funded startups, CTOs, urgent builds. Score highly for: founder/CTO role, funded, urgent timeline, tech stack match (Laravel, Vue, Go, Python), budget ≥$5K. Score low for: no budget, no urgency, scope too large for sprint, maintenance-only.

**Web Networks:** ICP = non-profits, Indigenous orgs, grant-funded. Score highly for: registered non-profit, has funding, outdated/missing website, accessibility issues. Score low for: for-profit, no digital need, good existing website, no funding source.

## Files

- Create: `src/Domain/Qualification/BrandScoringContext.php`
- Create: `tests/Unit/Domain/Qualification/BrandScoringContextTest.php`
- Modify: `src/Provider/PipelineServiceProvider.php` (register service)

## Interface

```php
class BrandScoringContext
{
    public function getContext(string $brand): string
    // Returns the brand-specific prompt context string

    public function buildPrompt(Lead $lead): string
    // Returns the full prompt with brand context + company profile + lead data
}
```

## Testing

- Test NorthOps context returned for 'northops' brand
- Test Web Networks context returned for 'webnetworks' brand
- Test prompt includes company profile from `COMPANY_PROFILE` env var
- Test prompt includes lead data serialization

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 2.2
EOF
)"
```

- [ ] **Step 4: Create issue — Update ProspectScoringService**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Update ProspectScoringService for brand-aware scoring with tiers" \
  --label "phase:1-foundations,layer:think,priority:p0" \
  --milestone "Phase 1: CRM Foundations" \
  --body "$(cat <<'EOF'
## Summary

Modify `ProspectScoringService` to use `BrandScoringContext` for brand-specific prompts, return tier alongside score, and set `last_scored_at` timestamp.

## Changes

1. Inject `BrandScoringContext` dependency
2. Use brand-specific prompt when calling Claude API
3. Calculate tier from score (T1: 80-100, T2: 60-79, T3: 40-59, T4: 20-39, T5: 0-19)
4. Set `last_scored_at` to current timestamp after scoring
5. Set `tier` field on lead after scoring

## Tier Calculation

```php
private function calculateTier(int $score): string
{
    return match(true) {
        $score >= 80 => 'T1',
        $score >= 60 => 'T2',
        $score >= 40 => 'T3',
        $score >= 20 => 'T4',
        default => 'T5',
    };
}
```

## Files

- Modify: `src/Domain/Pipeline/ProspectScoringService.php`
- Modify: `tests/Unit/Domain/Pipeline/ProspectScoringServiceTest.php`

## Testing

- Test tier calculation for each boundary (79→T2, 80→T1, etc.)
- Test that `last_scored_at` is set after scoring
- Test that brand context is used in prompt (mock Claude API)
- Test NorthOps vs Web Networks leads get different prompts

## Dependencies

- Depends on: BrandScoringContext issue
- Depends on: Lead entity fields issue (needs `tier`, `last_scored_at`)

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Sections 2.3, 2.5
EOF
)"
```

- [ ] **Step 5: Create issue — Update LeadFactory with routing**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Update LeadFactory to call RoutingService on create" \
  --label "phase:1-foundations,layer:think,priority:p0" \
  --milestone "Phase 1: CRM Foundations" \
  --body "$(cat <<'EOF'
## Summary

Modify `LeadFactory` to call `RoutingService` during lead creation, populating brand, routing_confidence, and lead_source fields automatically.

## Changes

1. Inject `RoutingService` dependency
2. On `create()`: call `RoutingService::route()` to get brand + confidence
3. Set `lead_source` from input data (normalize to allowed values)
4. Set `routing_confidence` from routing result
5. Set `brand_id` from routing result (lookup Brand entity by slug)

## Files

- Modify: `src/Domain/Pipeline/LeadFactory.php`
- Modify: `tests/Unit/Domain/Pipeline/LeadFactoryTest.php` (or create if not exists)

## Testing

- Test lead creation with explicit brand (routing skipped, confidence 100%)
- Test lead creation without brand (routing applied)
- Test lead_source normalization (valid values pass, unknown defaults to 'manual')
- Test routing_confidence stored correctly

## Dependencies

- Depends on: RoutingService issue
- Depends on: Lead entity fields issue

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 2.3
EOF
)"
```

- [ ] **Step 6: Create issue — Update SectorNormalizer**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Add organization_type detection to SectorNormalizer" \
  --label "phase:1-foundations,layer:think,priority:p1" \
  --milestone "Phase 1: CRM Foundations" \
  --body "$(cat <<'EOF'
## Summary

Extend `SectorNormalizer` to detect `organization_type` from sector keywords and lead data.

## Detection Rules

| Keywords / Signals | organization_type |
|-------------------|-------------------|
| "non-profit", "nonprofit", "charity", "foundation", "501c3" | `nonprofit` |
| "charity", "registered charity" | `charity` |
| "indigenous", "first nation", "band council", "métis", "inuit" | `indigenous` |
| "community", "co-op", "cooperative", "neighbourhood", "association" | `community` |
| "government", "municipal", "public sector", "crown" | `government` |
| "startup", "saas", "tech", "funded", "series a", "seed" | `startup` |
| No match | null (unset) |

## Files

- Modify: `src/Domain/Qualification/SectorNormalizer.php`
- Modify: `tests/Unit/Domain/Qualification/SectorNormalizerTest.php`

## Testing

- Test each organization_type detection rule
- Test case insensitivity
- Test no-match returns null
- Test multiple keyword matches (first match wins by priority: indigenous > nonprofit > charity > community > government > startup)

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 2.3
EOF
)"
```

- [ ] **Step 7: Create issue — Score decay CLI command**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Implement pipeline:decay-scores CLI command" \
  --label "phase:1-foundations,layer:think,priority:p1" \
  --milestone "Phase 1: CRM Foundations" \
  --body "$(cat <<'EOF'
## Summary

Create a CLI command `pipeline:decay-scores` that reduces lead scores based on inactivity, using `last_scored_at` for precision. Different decay rates per brand.

## Decay Rules

| Brand | 14 days inactive | 30 days | 60 days | 90 days |
|-------|-----------------|---------|---------|---------|
| NorthOps | −5 | −10 | −20 | — |
| Web Networks | — | −3 | −8 | −15 |

Inactivity measured from `last_scored_at` (or `changed` if never scored).

## Behavior

- Query all leads where `last_scored_at` (or `changed`) exceeds threshold
- Apply the LARGEST applicable decay (not cumulative — a 60-day inactive NorthOps lead gets −20, not −5−10−20)
- Update score (floor at 0)
- Recalculate tier
- Log activity: "Score decayed by {amount} due to {days} days inactivity"
- Support `--dry-run` flag to preview changes without saving
- Support `--brand=northops|webnetworks` filter

## Files

- Create: `src/Command/DecayScoresCommand.php`
- Create: `tests/Unit/Command/DecayScoresCommandTest.php`
- Modify: `src/Provider/PipelineServiceProvider.php` (register command)

## Usage

```bash
bin/waaseyaa pipeline:decay-scores              # Run decay for all brands
bin/waaseyaa pipeline:decay-scores --dry-run    # Preview without saving
bin/waaseyaa pipeline:decay-scores --brand=northops  # NorthOps only
```

## Testing

- Test NorthOps 14-day decay (−5)
- Test NorthOps 60-day decay (−20, not cumulative −35)
- Test Web Networks 30-day decay (−3)
- Test score floor at 0
- Test tier recalculation after decay
- Test dry-run produces output but doesn't save
- Test brand filter

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 2.4
EOF
)"
```

- [ ] **Step 8: Create issue — waaseyaa/framework entity extensions (if needed)**

```bash
gh issue create --repo waaseyaa/framework \
  --title "Ensure entity system supports JSON and timestamp field types" \
  --label "phase:1-foundations,layer:think,priority:p1" \
  --milestone "Phase 1: Entity Extensions" \
  --body "$(cat <<'EOF'
## Summary

northops-waaseyaa needs JSON and timestamp field types for new Lead entity fields. Verify these types are supported by the entity system; if not, add them.

## Required Field Types

- **JSON field type**: for `specialist_context`, `audit_payload` (Phase 3), `template_variables` (Phase 3)
  - Must serialize to TEXT column in SQLite
  - Must auto-encode on save, auto-decode on load
- **Timestamp field type**: for `last_scored_at`
  - ISO 8601 format
  - Must store as TEXT in SQLite

## Investigation First

Check if `waaseyaa/framework` already supports these field types before implementing. If both exist, close this issue.

## Context

Part of the NorthOps Lead Engine initiative. See `jonesrussell/northops-waaseyaa` — `docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md`
EOF
)"
```

- [ ] **Step 9: Verify all Phase 1 issues created**

```bash
gh issue list --repo jonesrussell/northops-waaseyaa --milestone "Phase 1: CRM Foundations" --json number,title --jq '.[] | "#\(.number) \(.title)"'
gh issue list --repo waaseyaa/framework --milestone "Phase 1: Entity Extensions" --json number,title --jq '.[] | "#\(.number) \(.title)"'
```

Expected: 7 issues in northops-waaseyaa, 1 in waaseyaa/framework.

---

### Task 5: Create Phase 2 Issues (northops-waaseyaa + agency-agents)

**Files:** None (GitHub API only)

- [ ] **Step 1: Create issue — AgentPromptLoader**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Implement AgentPromptLoader with in-memory caching" \
  --label "phase:2-agents,layer:reason,priority:p0" \
  --milestone "Phase 2: Agent Integration" \
  --body "$(cat <<'EOF'
## Summary

Create `AgentPromptLoader` service that reads agency-agents markdown files from a filesystem path (configured via `AGENCY_AGENTS_PATH` env var), parses frontmatter, and returns `AgentPrompt` value objects. Includes in-memory cache.

## AgentPrompt Value Object

```php
class AgentPrompt
{
    public function __construct(
        public readonly string $slug,        // e.g. "sales/deal-strategist"
        public readonly string $name,        // from frontmatter
        public readonly string $description, // from frontmatter
        public readonly string $vibe,        // from frontmatter
        public readonly string $content,     // full markdown body (after frontmatter)
    ) {}
}
```

## AgentPromptLoader

```php
class AgentPromptLoader
{
    private array $cache = []; // keyed by slug

    public function loadAgent(string $slug): AgentPrompt
    // Reads {AGENCY_AGENTS_PATH}/{slug}.md, parses YAML frontmatter, caches result

    public function listAgents(?string $division = null): array
    // Scans directories, returns array of slugs. Optional division filter.
}
```

## Frontmatter Parsing

Agent files use YAML frontmatter:
```yaml
---
name: Deal Strategist
description: Specializes in deal qualification
color: blue
emoji: 🎯
vibe: Strategic and analytical
---
```

Parse with a simple regex or YAML parser. Only extract: name, description, vibe.

## Files

- Create: `src/Domain/Pipeline/AgentPrompt.php`
- Create: `src/Domain/Pipeline/AgentPromptLoader.php`
- Create: `tests/Unit/Domain/Pipeline/AgentPromptLoaderTest.php`
- Modify: `src/Provider/PipelineServiceProvider.php` (register service, bind AGENCY_AGENTS_PATH)

## Testing

- Test loading a valid agent file returns correct AgentPrompt
- Test cache hit (second load doesn't read filesystem)
- Test missing file throws exception
- Test malformed frontmatter handled gracefully
- Test listAgents returns slugs
- Test listAgents with division filter
- Use a test fixtures directory with sample agent markdown files

## Environment

- `AGENCY_AGENTS_PATH`: filesystem path to agency-agents repo root. If not set, service methods throw descriptive exception.

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Sections 3.1, 3.6
EOF
)"
```

- [ ] **Step 2: Create issue — SpecialistSelector**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Implement SpecialistSelector for sector-to-specialist mapping" \
  --label "phase:2-agents,layer:reason,priority:p0" \
  --milestone "Phase 2: Agent Integration" \
  --body "$(cat <<'EOF'
## Summary

Create `SpecialistSelector` that maps lead sector tags and lead_source to relevant specialist agent slugs. Max 3 specialists per scoring call.

## Selection Rules

```
- always include: sales/deal-strategist (every lead gets deal qualification)
- sector includes "ai" or "ml" → add engineering/engineering-ai-engineer
- sector includes "saas" or "startup" → add product/product-strategist
- sector includes "devops" or "infrastructure" → add engineering/engineering-devops-automator
- sector includes "web" or "application" → add engineering/engineering-backend-architect
- lead_source is "signal" or "job_posting" → add marketing/marketing-growth-hacker
- max 3 specialists per call (deal-strategist + top 2 by relevance)
```

## Interface

```php
class SpecialistSelector
{
    public function select(Lead $lead): array
    // Returns array of specialist slugs, max 3
    // First element is always 'sales/deal-strategist'
}
```

## Files

- Create: `src/Domain/Pipeline/SpecialistSelector.php`
- Create: `tests/Unit/Domain/Pipeline/SpecialistSelectorTest.php`

## Testing

- Test deal-strategist always included
- Test AI sector → ai-engineer selected
- Test SaaS sector → product-strategist selected
- Test DevOps sector → devops-engineer selected
- Test signal lead_source → growth-hacker selected
- Test max 3 cap enforced
- Test lead with no sector matches → only deal-strategist returned
- Test multiple sector matches → top 2 selected by order

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 3.3
EOF
)"
```

- [ ] **Step 3: Create issue — Specialist-enhanced scoring**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Wire specialist context into ProspectScoringService" \
  --label "phase:2-agents,layer:reason,priority:p0" \
  --milestone "Phase 2: Agent Integration" \
  --body "$(cat <<'EOF'
## Summary

Update `ProspectScoringService` to use `AgentPromptLoader` and `SpecialistSelector` to append specialist expertise to the Claude API scoring prompt. Store specialist context on the lead.

## Enhanced Scoring Flow

1. `SpecialistSelector::select($lead)` → get relevant specialist slugs (max 3)
2. `AgentPromptLoader::loadAgent($slug)` → get specialist prompts
3. Append specialist content as additional sections in Claude API prompt
4. Store results in `specialist_context` JSON field on lead:
   ```json
   {
     "specialists": ["sales/deal-strategist", "engineering/engineering-backend-architect"],
     "reasoning": {
       "sales/deal-strategist": "Strong deal signal — funded startup with urgent timeline...",
       "engineering/engineering-backend-architect": "Technical scope feasible for 5-day sprint..."
     }
   }
   ```

## Files

- Modify: `src/Domain/Pipeline/ProspectScoringService.php`
- Modify: `tests/Unit/Domain/Pipeline/ProspectScoringServiceTest.php`

## Testing

- Test that specialists are selected and loaded for a lead
- Test that specialist content appears in Claude API prompt
- Test that specialist_context JSON is populated on lead after scoring
- Test graceful degradation if AGENCY_AGENTS_PATH not set (score without specialists)
- Test max 3 specialists enforced

## Dependencies

- Depends on: AgentPromptLoader issue
- Depends on: SpecialistSelector issue

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 3.4
EOF
)"
```

- [ ] **Step 4: Create issue — source_url field**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Add source_url field to Lead entity" \
  --label "phase:2-agents,layer:reason,priority:p1" \
  --milestone "Phase 2: Agent Integration" \
  --body "$(cat <<'EOF'
## Summary

Add `source_url` string field to the Lead entity. Stores the origin URL of the signal (HN post, Reddit thread, grant page, RFP URL). Useful for admin UI context and outreach personalization.

## Files

- Modify: Lead entity type definition in `src/Entity/`
- Update admin UI if it displays lead fields

## Testing

- Test field storage and retrieval
- Test null/empty accepted (field is optional)

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 3.5
EOF
)"
```

- [ ] **Step 5: Create issue — Create AI engineer agent (agency-agents)**

```bash
gh issue create --repo jonesrussell/agency-agents \
  --title "Create engineering-ai-engineer.md specialist agent" \
  --label "phase:2-agents,layer:reason,priority:p0" \
  --milestone "Phase 2: NorthOps Integration" \
  --body "$(cat <<'EOF'
## Summary

Create a new AI engineer specialist agent at `engineering/engineering-ai-engineer.md`. This agent provides expertise in AI feature scoping: RAG pipelines, LLM integrations, embeddings, prompt engineering, vector databases.

No equivalent exists in the upstream repo. This is needed by NorthOps for AI feature feasibility assessment during lead qualification.

## Frontmatter

```yaml
---
name: AI Engineer
description: Specializes in AI/ML feature design, LLM integrations, RAG pipelines, and production AI systems
color: purple
emoji: 🤖
vibe: Pragmatic and production-focused — ships AI features, not research papers
---
```

## Content Areas

The agent should cover expertise in:
- RAG pipeline architecture (document ingestion, chunking, embeddings, vector search, retrieval)
- LLM API integration (Claude, GPT-4, model selection, prompt engineering)
- Production AI patterns (caching, fallbacks, cost management, latency)
- Vector databases (Pinecone, Qdrant, pgvector, Chroma)
- AI feature scoping (what's feasible in 3–5 days vs what needs more time)
- Common AI anti-patterns (fine-tuning when RAG suffices, over-engineering embeddings)

## Testing

- Verify frontmatter parses correctly
- Verify file follows same structure as other engineering agents

## Context

Part of the NorthOps Lead Engine initiative. NorthOps uses this agent for lead qualification — assessing whether AI-related leads are technically feasible for sprint delivery.
EOF
)"
```

- [ ] **Step 6: Create issue — NorthOps integration docs (agency-agents)**

```bash
gh issue create --repo jonesrussell/agency-agents \
  --title "Document NorthOps integration in README or NORTHOPS.md" \
  --label "phase:2-agents,layer:reason,priority:p1" \
  --milestone "Phase 2: NorthOps Integration" \
  --body "$(cat <<'EOF'
## Summary

Document how NorthOps uses agency-agents specialists. Include which agents are used, how they're loaded, and the integration architecture.

## Content

- Which 7 agents NorthOps uses and why
- How NorthOps loads agents (filesystem path via env var)
- The specialist selection logic (sector → agents mapping)
- How to add a new specialist for NorthOps use

## Files

- Create: `NORTHOPS.md` in repo root (or add section to README.md)

## Context

Part of the NorthOps Lead Engine initiative. See `jonesrussell/northops-waaseyaa` — `docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md`
EOF
)"
```

- [ ] **Step 7: Verify all Phase 2 issues created**

```bash
gh issue list --repo jonesrussell/northops-waaseyaa --milestone "Phase 2: Agent Integration" --json number,title --jq '.[] | "#\(.number) \(.title)"'
gh issue list --repo jonesrussell/agency-agents --milestone "Phase 2: NorthOps Integration" --json number,title --jq '.[] | "#\(.number) \(.title)"'
```

Expected: 4 issues in northops-waaseyaa, 2 in agency-agents.

---

### Task 6: Create Phase 3 Issues (northops-waaseyaa + north-cloud)

**Files:** None (GitHub API only)

- [ ] **Step 1: Create issue — Phase 3 entity fields**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Add Phase 3 entity fields: audit_payload, signal_strength, audit_score" \
  --label "phase:3-outreach,layer:act,priority:p0" \
  --milestone "Phase 3: Outreach Engine" \
  --body "$(cat <<'EOF'
## Summary

Add 3 new fields to the Lead entity for signal and audit data.

| Field | Type | Purpose |
|-------|------|---------|
| `audit_payload` | JSON | Raw Lighthouse results |
| `signal_strength` | integer (0–100) | Intent signal quality (e.g., "looking for CTO" → 90) |
| `audit_score` | integer (0–100) | Composite: accessibility 40% + performance 25% + mobile 25% + content 10% |

## Files

- Modify: Lead entity type definition
- Update admin UI to display these fields on lead detail

## Testing

- Test storage and retrieval for each field type
- Test null accepted (all optional)

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.2
EOF
)"
```

- [ ] **Step 2: Create issue — Signal ingestion endpoint**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Create /api/leads/ingest/signal endpoint" \
  --label "phase:3-outreach,layer:act,priority:p0" \
  --milestone "Phase 3: Outreach Engine" \
  --body "$(cat <<'EOF'
## Summary

Create API endpoint for ingesting founder intent signals from north-cloud crawlers.

## Endpoint

`POST /api/leads/ingest/signal`

**Auth:** `PIPELINE_API_KEY` header (existing mechanism)

**Payload:**
```json
{
  "label": "Acme Corp",
  "source_url": "https://news.ycombinator.com/item?id=12345",
  "signal_strength": 85,
  "sector": "saas",
  "notes": "Founder posted: looking for technical co-founder to rebuild MVP"
}
```

**Behavior:**
1. Validate payload
2. Call `LeadFactory::create()` with `lead_source: signal`
3. Auto-routes via RoutingService (likely → NorthOps)
4. Auto-scores via ProspectScoringService
5. Returns created lead JSON

## Files

- Modify: `src/Controller/Api/LeadController.php` (add ingest method)
- Create: `tests/Unit/Controller/Api/LeadControllerIngestTest.php`

## Testing

- Test valid payload creates lead with correct lead_source
- Test signal_strength stored on lead
- Test source_url stored on lead
- Test PIPELINE_API_KEY auth required
- Test invalid payload returns 422

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.4
EOF
)"
```

- [ ] **Step 3: Create issue — Funding ingestion endpoint**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Create /api/leads/ingest/funding endpoint" \
  --label "phase:3-outreach,layer:act,priority:p0" \
  --milestone "Phase 3: Outreach Engine" \
  --body "$(cat <<'EOF'
## Summary

Create API endpoint for ingesting funding announcements from north-cloud crawlers.

## Endpoint

`POST /api/leads/ingest/funding`

**Auth:** `PIPELINE_API_KEY` header

**Payload:**
```json
{
  "label": "Sudbury Indigenous Cultural Centre",
  "source_url": "https://otf.ca/funded-grants/12345",
  "funding_status": "received",
  "organization_type": "indigenous",
  "notes": "Received OTF Grow grant for digital capacity building"
}
```

**Behavior:** Same as signal endpoint but with `lead_source: funding`, auto-routes to Web Networks.

## Files

- Modify: `src/Controller/Api/LeadController.php`
- Add to existing ingest tests

## Testing

- Test valid payload creates lead with `lead_source: funding`
- Test auto-routes to webnetworks brand
- Test funding_status and organization_type stored

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.4
EOF
)"
```

- [ ] **Step 4: Create issue — Audit ingestion endpoint**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Create /api/leads/ingest/audit endpoint" \
  --label "phase:3-outreach,layer:act,priority:p0" \
  --milestone "Phase 3: Outreach Engine" \
  --body "$(cat <<'EOF'
## Summary

Create API endpoint for ingesting website audit results from the Lighthouse batch script.

## Endpoint

`POST /api/leads/ingest/audit`

**Auth:** `PIPELINE_API_KEY` header

**Payload:**
```json
{
  "label": "Northern Community Services",
  "source_url": "https://northerncommunityservices.ca",
  "audit_score": 35,
  "audit_payload": { "accessibility": 28, "performance": 45, "mobile": 38, "content": 30 },
  "accessibility_score": 28,
  "notes": "Missing alt text, no skip-to-content, poor color contrast"
}
```

**Behavior:** Same as other ingest endpoints but with `lead_source: website_audit`, auto-routes to Web Networks.

## Files

- Modify: `src/Controller/Api/LeadController.php`
- Add to existing ingest tests

## Testing

- Test valid payload creates lead with `lead_source: website_audit`
- Test audit_payload JSON stored correctly
- Test audit_score and accessibility_score stored
- Test auto-routes to webnetworks brand

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.4
EOF
)"
```

- [ ] **Step 5: Create issue — Lighthouse batch audit script**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Create Lighthouse batch audit script for website scanning" \
  --label "phase:3-outreach,layer:act,priority:p1" \
  --milestone "Phase 3: Outreach Engine" \
  --body "$(cat <<'EOF'
## Summary

Standalone script that runs Lighthouse CLI against a list of URLs and POSTs results to the audit ingestion endpoint.

## Input

CSV file with columns: `url,label,organization_type`

## Behavior

For each URL:
1. Run Lighthouse CLI in headless mode
2. Extract accessibility, performance, mobile, content scores
3. Calculate composite audit_score (40/25/25/10 weighting)
4. POST to `/api/leads/ingest/audit`

## Files

- Create: `scripts/audit-batch.sh` (or `scripts/audit-batch.js` if Node preferred)
- Create: `scripts/audit-targets.csv` (sample target list)

## Usage

```bash
./scripts/audit-batch.sh --targets=scripts/audit-targets.csv --api-url=http://localhost:8080 --api-key=secret
```

## Dependencies

- Lighthouse CLI (`npm install -g lighthouse`)
- curl or node-fetch for API calls

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.5
EOF
)"
```

- [ ] **Step 6: Create issue — Outreach templates**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Create 8 outreach templates with template_variables" \
  --label "phase:3-outreach,layer:act,priority:p1" \
  --milestone "Phase 3: Outreach Engine" \
  --body "$(cat <<'EOF'
## Summary

Create 8 outreach email templates stored in NorthOps. Templates are rendered in admin UI for manual copy/customize/send. NOT sent automatically.

## Templates

| ID | Brand | Purpose |
|----|-------|---------|
| `FOUNDER-CONNECT` | NorthOps | LinkedIn connection note |
| `FOUNDER-VALUE` | NorthOps | Value-first follow-up |
| `RFP-INTRO` | NorthOps | Initial RFP response |
| `SIGNAL-INTRO` | NorthOps | Signal-referenced cold email |
| `FUNDING-CONGRATS` | Web Networks | Grant congratulations |
| `AUDIT-REPORT` | Web Networks | Free audit report offer |
| `REFERRAL-INTRO` | Both | Warm intro |
| `COLD-INTRO` | Web Networks | Mission-aligned intro |

Each template includes a `template_variables` JSON declaring required variables:
```json
{ "org_name": "string", "lead_name": "string", "specific_detail": "string" }
```

## Implementation

Store as config files (e.g., `config/outreach-templates/`) or as seeded entities. Config files preferred for version control.

## Full template content

See `~/org/northops/outreach-playbook.md` and `~/org/webnetworks/outreach-playbook.md` for complete template text.

## Files

- Create: `config/outreach-templates/*.md` (8 files) or equivalent
- Create/modify: Admin UI template rendering (display template with variables filled)

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.6
EOF
)"
```

- [ ] **Step 7: Create issue — Discord T1 alert**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Add Discord alert for T1 (hot) leads" \
  --label "phase:3-outreach,layer:act,priority:p1" \
  --milestone "Phase 3: Outreach Engine" \
  --body "$(cat <<'EOF'
## Summary

Create an event subscriber that sends a Discord embed when a lead is scored T1 (≥80).

## Behavior

When `ProspectScoringService` scores a lead ≥80:
1. Fire a pipeline event
2. `HotLeadAlertSubscriber` catches it
3. Sends Discord embed via existing `DiscordNotifier::sendEmbed()`

## Embed Content

- Lead name, score, tier, brand
- Source + specialist reasoning summary (from specialist_context)
- Link to admin lead detail page

## Files

- Create: `src/Domain/Pipeline/EventSubscriber/HotLeadAlertSubscriber.php`
- Modify: `src/Provider/PipelineServiceProvider.php` (register subscriber)
- Create: `tests/Unit/Domain/Pipeline/EventSubscriber/HotLeadAlertSubscriberTest.php`

## Testing

- Test T1 lead triggers Discord notification
- Test T2 lead does NOT trigger notification
- Test embed contains lead name, score, tier
- Test graceful handling if DISCORD_WEBHOOK_URL not set

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.7
EOF
)"
```

- [ ] **Step 8: Create issue — HN intent crawler (north-cloud)**

```bash
gh issue create --repo jonesrussell/north-cloud \
  --title "Create hn-intent crawler for founder intent signals" \
  --label "phase:3-outreach,layer:act,priority:p0" \
  --milestone "Phase 3: Signal Crawlers" \
  --body "$(cat <<'EOF'
## Summary

Configure a north-cloud crawler that monitors Hacker News for founder intent signals and pushes matches to NorthOps.

## Keywords

- "looking for CTO"
- "looking for technical co-founder"
- "need developer"
- "need engineer"
- "rebuild MVP"
- "rebuild our app"
- "technical co-founder"
- "hiring first engineer"

## Output

POST to NorthOps `/api/leads/ingest/signal`:
```json
{
  "label": "[HN username or company if mentioned]",
  "source_url": "https://news.ycombinator.com/item?id=...",
  "signal_strength": [computed 0-100],
  "sector": "[inferred]",
  "notes": "[matched text excerpt]"
}
```

## Signal Strength Heuristic

- Direct ask ("looking for CTO") → 90
- Strong signal ("need to rebuild") → 70
- Weak signal ("thinking about") → 40

## Frequency

Daily scan. Deduplicate by HN item ID.

## Context

Part of NorthOps Lead Engine. See `jonesrussell/northops-waaseyaa` — `docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 4.3
EOF
)"
```

- [ ] **Step 9: Create issue — Reddit intent crawler (north-cloud)**

```bash
gh issue create --repo jonesrussell/north-cloud \
  --title "Create reddit-intent crawler for founder intent signals" \
  --label "phase:3-outreach,layer:act,priority:p0" \
  --milestone "Phase 3: Signal Crawlers" \
  --body "$(cat <<'EOF'
## Summary

Configure a north-cloud crawler that monitors Reddit for founder intent signals. Same keyword set as hn-intent crawler.

## Subreddits

- r/startups
- r/SaaS
- r/webdev
- r/entrepreneur

## Keywords

Same as hn-intent crawler.

## Output

POST to NorthOps `/api/leads/ingest/signal` (same format as hn-intent).

## Frequency

Daily scan. Deduplicate by Reddit post/comment ID.

## Context

Part of NorthOps Lead Engine. See `jonesrussell/northops-waaseyaa` spec — Section 4.3
EOF
)"
```

- [ ] **Step 10: Create issue — Funding monitor crawler (north-cloud)**

```bash
gh issue create --repo jonesrussell/north-cloud \
  --title "Create funding-monitor crawler for grant announcements" \
  --label "phase:3-outreach,layer:act,priority:p1" \
  --milestone "Phase 3: Signal Crawlers" \
  --body "$(cat <<'EOF'
## Summary

Configure a north-cloud crawler that monitors grant/funding announcement pages and pushes funded organizations to NorthOps.

## Target Sources

- otf.ca/funded-grants (Ontario Trillium Foundation)
- grants.gc.ca (federal grants)

## Detection

Look for: newly listed funded organizations, grant amount, program name, organization name.

## Output

POST to NorthOps `/api/leads/ingest/funding`:
```json
{
  "label": "[Organization name]",
  "source_url": "[grant page URL]",
  "funding_status": "received",
  "organization_type": "[inferred: nonprofit, indigenous, community]",
  "notes": "[Grant program name, amount if available]"
}
```

## Frequency

Daily scan. Deduplicate by organization name + grant program.

## Context

Part of NorthOps Lead Engine. See `jonesrussell/northops-waaseyaa` spec — Section 4.3
EOF
)"
```

- [ ] **Step 11: Verify all Phase 3 issues created**

```bash
gh issue list --repo jonesrussell/northops-waaseyaa --milestone "Phase 3: Outreach Engine" --json number,title --jq '.[] | "#\(.number) \(.title)"'
gh issue list --repo jonesrussell/north-cloud --milestone "Phase 3: Signal Crawlers" --json number,title --jq '.[] | "#\(.number) \(.title)"'
```

Expected: 7 issues in northops-waaseyaa, 3 in north-cloud.

---

### Task 7: Create Phase 4 Issues (northops-waaseyaa + claudriel)

**Files:** None (GitHub API only)

- [ ] **Step 1: Create issue — WebhookDispatcher**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Implement WebhookDispatcher service" \
  --label "phase:4-bridge,layer:remember,priority:p0" \
  --milestone "Phase 4: Claudriel Bridge" \
  --body "$(cat <<'EOF'
## Summary

Create a generic, reusable `WebhookDispatcher` service that sends HTTP POST requests to configured webhook URLs. Used by NorthOpsWebhookSubscriber but designed for reuse.

## Interface

```php
class WebhookDispatcher
{
    public function dispatch(string $url, array $payload, array $headers = []): bool
    // Sends POST request, returns true on 2xx, false otherwise
    // Uses waaseyaa/http-client HttpClientInterface (NOT raw curl)
    // Logs failures, does not throw
}
```

## Files

- Create: `src/Domain/Pipeline/WebhookDispatcher.php`
- Create: `tests/Unit/Domain/Pipeline/WebhookDispatcherTest.php`

## Testing

- Test successful dispatch (mock HTTP client, verify POST body and headers)
- Test failure handling (non-2xx response → returns false, logs error)
- Test Authorization header passed correctly

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Section 5.5
EOF
)"
```

- [ ] **Step 2: Create issue — NorthOpsWebhookSubscriber**

```bash
gh issue create --repo jonesrussell/northops-waaseyaa \
  --title "Implement NorthOpsWebhookSubscriber with due-date inference" \
  --label "phase:4-bridge,layer:remember,priority:p0" \
  --milestone "Phase 4: Claudriel Bridge" \
  --body "$(cat <<'EOF'
## Summary

Create event subscriber that maps pipeline events to Claudriel commitment payloads with due-date inference. NorthOps owns due-date semantics.

## Event-to-Commitment Mappings

| Event | Title Template | Due Date | Priority |
|-------|---------------|----------|----------|
| T1 lead created (score ≥80) | "Follow up with {lead} — hot lead, {source}" | +1 day | high |
| Stage → contacted | "Waiting for reply from {lead}" | +3 days | medium |
| Stage → proposal | "Send proposal to {lead}" | +3 days | high |
| Stage → negotiation | "Close deal with {lead}" | +7 days | high |
| Stale lead (14d inactive) | "Check in on {lead} — going cold" | today | medium |
| T1 no response 48h | "Re-engage {lead} — no response" | +1 day | high |

## Webhook Payload

```json
{
  "title": "Follow up with Acme Corp — hot lead, rfp",
  "due_date": "2026-05-15",
  "priority": "high",
  "source": "northops",
  "metadata": {
    "lead_id": "uuid",
    "lead_url": "https://northops.ca/admin/leads/uuid",
    "event": "t1_lead_created",
    "score": 85,
    "tier": "T1",
    "brand": "northops"
  }
}
```

## Gating

If `CLAUDRIEL_WEBHOOK_URL` is not set, subscriber does nothing (no error, no log noise).

## Files

- Create: `src/Domain/Pipeline/EventSubscriber/NorthOpsWebhookSubscriber.php`
- Create: `tests/Unit/Domain/Pipeline/EventSubscriber/NorthOpsWebhookSubscriberTest.php`
- Modify: `src/Provider/PipelineServiceProvider.php` (register subscriber)

## Testing

- Test each of the 6 event mappings produces correct payload
- Test due-date calculation for each rule
- Test lead_url contains correct admin URL
- Test no-op when CLAUDRIEL_WEBHOOK_URL not set
- Test WebhookDispatcher called with correct URL and auth header

## Dependencies

- Depends on: WebhookDispatcher issue

## Spec Reference

`docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` — Sections 5.1, 5.2
EOF
)"
```

- [ ] **Step 3: Create issue — Claudriel commitment ingestion endpoint**

```bash
gh issue create --repo jonesrussell/claudriel \
  --title "Create commitment ingestion API endpoint for external sources" \
  --label "phase:4-bridge,layer:remember,priority:p0" \
  --milestone "Phase 4: NorthOps Bridge" \
  --body "$(cat <<'EOF'
## Summary

Create an API endpoint in Claudriel that accepts commitment creation from external systems (NorthOps).

## Endpoint

`POST /api/commitments`

**Auth:** Bearer token (`CLAUDRIEL_API_KEY`)

**Payload:**
```json
{
  "title": "Follow up with Acme Corp — hot lead, rfp",
  "due_date": "2026-05-15",
  "priority": "high",
  "source": "northops",
  "metadata": {
    "lead_id": "uuid",
    "lead_url": "https://northops.ca/admin/leads/uuid",
    "event": "t1_lead_created",
    "score": 85,
    "tier": "T1",
    "brand": "northops"
  }
}
```

**Behavior:**
1. Validate auth token
2. Create Commitment entity with `source` field set
3. Return 201 with commitment ID

## New Field

Add `source` field to Commitment entity (string: "northops", "manual", "gmail", etc.)

## Testing

- Test valid payload creates commitment
- Test source field populated correctly
- Test auth required (401 without token)
- Test invalid payload returns 422
- Test metadata stored as JSON

## Context

Part of NorthOps Lead Engine. Commitments from NorthOps appear in daily briefs automatically.
See `jonesrussell/northops-waaseyaa` spec — Sections 5.2, 5.3
EOF
)"
```

- [ ] **Step 4: Verify all Phase 4 issues created**

```bash
gh issue list --repo jonesrussell/northops-waaseyaa --milestone "Phase 4: Claudriel Bridge" --json number,title --jq '.[] | "#\(.number) \(.title)"'
gh issue list --repo jonesrussell/claudriel --milestone "Phase 4: NorthOps Bridge" --json number,title --jq '.[] | "#\(.number) \(.title)"'
```

Expected: 2 issues in northops-waaseyaa, 1 in claudriel.

---

### Task 8: Link All Issues to Project Board

**Files:** None (GitHub API only)

- [ ] **Step 1: Get project ID**

```bash
PROJECT_ID=$(gh project list --owner jonesrussell --format json | jq -r '.projects[] | select(.title == "NorthOps Lead Engine & Automation") | .id')
echo $PROJECT_ID
```

- [ ] **Step 2: Add all northops-waaseyaa issues to project**

```bash
# Get all issues with our phase labels
for issue_url in $(gh issue list --repo jonesrussell/northops-waaseyaa --label "phase:1-foundations,phase:2-agents,phase:3-outreach,phase:4-bridge" --json url --jq '.[].url' 2>/dev/null); do
  gh project item-add $PROJECT_NUM --owner jonesrussell --url "$issue_url"
done
```

If the label filter doesn't support OR, add issues individually by number:

```bash
# Alternative: add by issue URL one at a time
gh issue list --repo jonesrussell/northops-waaseyaa --json url --jq '.[].url' | while read url; do
  gh project item-add $PROJECT_NUM --owner jonesrussell --url "$url" 2>/dev/null
done
```

- [ ] **Step 3: Add agency-agents issues to project**

```bash
gh issue list --repo jonesrussell/agency-agents --json url --jq '.[].url' | while read url; do
  gh project item-add $PROJECT_NUM --owner jonesrussell --url "$url" 2>/dev/null
done
```

- [ ] **Step 4: Add north-cloud issues to project**

```bash
gh issue list --repo jonesrussell/north-cloud --milestone "Phase 3: Signal Crawlers" --json url --jq '.[].url' | while read url; do
  gh project item-add $PROJECT_NUM --owner jonesrussell --url "$url" 2>/dev/null
done
```

- [ ] **Step 5: Add claudriel issues to project**

```bash
gh issue list --repo jonesrussell/claudriel --milestone "Phase 4: NorthOps Bridge" --json url --jq '.[].url' | while read url; do
  gh project item-add $PROJECT_NUM --owner jonesrussell --url "$url" 2>/dev/null
done
```

- [ ] **Step 6: Add waaseyaa/framework issues to project**

```bash
gh issue list --repo waaseyaa/framework --milestone "Phase 1: Entity Extensions" --json url --jq '.[].url' | while read url; do
  gh project item-add $PROJECT_NUM --owner jonesrussell --url "$url" 2>/dev/null
done
```

- [ ] **Step 7: Verify project board has all issues**

```bash
gh project item-list $PROJECT_NUM --owner jonesrussell --format json | jq '.items | length'
```

Expected: ~24 total issues across all repos.

---

### Task 9: Commit Plan and Spec

**Files:**
- `docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md` (already written)
- `docs/superpowers/plans/2026-03-30-lead-engine-github-infrastructure.md` (this file)

- [ ] **Step 1: Stage both files**

```bash
cd /home/jones/dev/northops-waaseyaa
git add docs/superpowers/specs/2026-03-30-lead-engine-automation-design.md
git add docs/superpowers/plans/2026-03-30-lead-engine-github-infrastructure.md
```

- [ ] **Step 2: Commit**

```bash
git commit -m "$(cat <<'EOF'
docs: add lead engine design spec and GitHub infrastructure plan

Design spec covers 4-phase lead engine across 5 repos:
- Phase 1: CRM foundations (scoring, routing, entity fields)
- Phase 2: Agent integration (7 specialists from agency-agents)
- Phase 3: Outreach engine (signals, audits, templates)
- Phase 4: Claudriel bridge (commitments, daily briefs)

GitHub infrastructure plan creates project board, milestones,
labels, and ~24 issues across northops-waaseyaa, agency-agents,
north-cloud, claudriel, and waaseyaa/framework.

Co-Authored-By: Claude Opus 4.6 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 3: Verify commit**

```bash
git log --oneline -1
git status
```

Expected: Clean working tree (for these files), commit message visible.
