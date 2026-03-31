# Frontend Pipeline Catch-Up Design

**Date:** 2026-03-30
**Status:** Approved
**Milestone:** Phase 1: CRM Foundations

## Problem

The backend Lead entity has 9 fields (tier, routing_confidence, organization_type, lead_source, budget_range, urgency, funding_status, last_scored_at, specialist_context) that are not exposed in the API response or displayed in the admin dashboard. Frontend and backend are out of sync.

## Approach

API-first: add all missing fields to the JSON response in one pass, then update each frontend view.

## 1. API Layer

Add to `LeadController::leadToArray()`:

| Field | Type | Source |
|-------|------|--------|
| tier | string | getTier() |
| routing_confidence | int | getRoutingConfidence() |
| organization_type | string | getOrganizationType() |
| lead_source | string | getLeadSource() |
| budget_range | string | getBudgetRange() |
| urgency | string | getUrgency() |
| funding_status | string | getFundingStatus() |
| last_scored_at | string | getLastScoredAt() |
| specialist_context | string | getSpecialistContext() |

Purely additive, no breaking changes.

## 2. Kanban Board Cards

Add to each lead card:

- **Tier badge** — colored pill (T1=red/hot, T2=amber/warm, T3=grey/cold)
- **Routing confidence** — muted percentage text
- **Organization type** — subtle tag below company name when present
- **Lead source** — small label (rfp/signal/funding/audit/inbound)

Cards stay compact. Score + tier are the most prominent additions.

## 3. Lead List Table

Add four columns after Score:

| Column | Display | Sortable |
|--------|---------|----------|
| Tier | Colored badge | Yes |
| Routing | Confidence % + brand slug | Yes (by confidence) |
| Org Type | Text label | Yes |
| Lead Source | Text label | Yes |

budget_range, urgency, funding_status, specialist_context are detail-level only.

## 4. Lead Detail Page

New **Routing & Scoring** section below existing fields:

| Field | Display |
|-------|---------|
| Tier | Large colored badge with label ("T1 — Hot") |
| Routing Confidence | Progress bar + percentage + matched rule |
| Organization Type | Text |
| Lead Source | Text label |
| Budget Range | Text |
| Urgency | Text with color coding |
| Funding Status | Text |
| Last Scored At | Relative timestamp |
| Specialist Context | Expandable text block |

Edit form adds manual override for: tier, organization_type, lead_source, budget_range, urgency, funding_status. Routing confidence and last_scored_at are system-set only.

## Execution Order

1. #84 — ProspectScoringService brand-aware scoring with tiers (populates tier values)
2. Issue A — Expose 9 new Lead fields in API response
3. Issue B — Add tier, routing, org type, lead source to kanban cards and lead list
4. Issue C — Add Routing & Scoring section to lead detail page with edit support

A blocks B and C. B and C are independent.

## Files Affected

- `src/Controller/Api/LeadController.php` (leadToArray method)
- `templates/admin/dashboard.html.twig` (kanban cards)
- `templates/admin/lead-list.html.twig` (table columns)
- `templates/admin/lead-detail.html.twig` (detail section + edit form)
- `public/js/dashboard.js` (card rendering, table logic)
