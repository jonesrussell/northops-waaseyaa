# Phase 1 Wrap-Up Design

**Date:** 2026-03-31
**Scope:** Update Waaseyaa to alpha.97, wire auth, framework compliance, UI polish, close merged issues, E2E capstone
**Related issues:** #55, #50, #51, #52, #53, #54, #77, #80, #81, #83, #84, #86, #88, #91, #101, #106, #107, #108

## Context

Waaseyaa alpha.97 unblocked the `/admin/login` flow. Phase 1 backend work is largely complete (entity fields, routing service, brand scoring, decay scores all merged). This wrap-up closes out Phase 1 by: updating the framework, securing routes, cleaning up framework compliance debt, polishing the UI, and validating everything with an E2E test.

## Section 1: Framework Update + Login Verification

- Run `composer clear-cache && composer update 'waaseyaa/*'` to pull alpha.97
- Commit updated `composer.lock`, push — let CI/CD deploy
- After deploy lands, use Playwright MCP to navigate to `northops.ca/admin/login`, submit credentials, verify redirect to `/admin` and session persistence

## Section 2: Wire Auth to Admin + API Routes (#55)

- Replace `->allowAll()` on all `/admin/*` routes with `->requireAuth()` — unauthenticated users redirect to `/admin/login`
- Replace `->allowAll()` on `/api/leads/*` routes with access policy enforcement — `DashboardAccessPolicy` and `LeadAccessPolicy` already exist, just need wiring
- Keep `/api/leads/import` using API key auth as-is (machine-to-machine)
- Keep public marketing routes (`/`, `/about`, `/services`, `/contact`) as `allowAll()`

## Section 3: Framework Compliance (#50, #51, #52, #53, #54)

Five issues in dependency order:

1. **#50 — Inject Twig via constructor** — Both `MarketingController` and `DashboardController` use `SsrServiceProvider::getTwigEnvironment()` statically. Switch to constructor-injected `Twig\Environment`, register controllers as singletons in the container.

2. **#54 — Deduplicate service construction** — `AppServiceProvider` and `PipelineServiceProvider` both construct services manually. Consolidate to single registrations via `singleton()`.

3. **#51 — Extract Discord notification to EventBus subscriber** — `MarketingController::submitContact()` sends Discord inline. Move to an event subscriber (pattern already established by `DiscordNotificationSubscriber` in the pipeline domain).

4. **#53 — Move contact form validation to domain service** — Extract inline validation from `MarketingController::submitContact()` into a domain service or the `LeadFactory`.

5. **#52 — Controllers return Response objects** — Have `DashboardController` and `MarketingController` return `SsrResponse` instead of `string`. Remove the wrapping closures in `AppServiceProvider` route definitions.

Order matters: #50 (DI) before #52 (Response objects) since both touch the controllers. #54 before #51/#53 since deduplication simplifies the providers before we restructure what they register.

## Section 4: UI Polish (#106, #107, #108)

1. **#106 — Org-type-tag bare span** — Add `display: block` or wrap in a styled `<div>` on kanban cards so the tag renders properly.
2. **#107 — Confidence progress bar clamping** — Clamp `routing_confidence` to 0-100 in `renderRoutingScoring()` in `dashboard.js` before setting width style.
3. **#108 — Inline styles on overrides header** — Replace the inline `style` attribute on the "Routing & Scoring Overrides" `<h4>` in `lead-detail.html.twig` with a CSS class.

## Section 5: Close Already-Merged Issues

Close #80, #81, #83, #84, #86, #88, #91, #101 — all have corresponding commits on `feat/phase1-sprint`.

## Section 6: E2E Test (#77)

Capstone Playwright test covering:
- Navigate to `/admin/login`, authenticate
- Verify kanban board loads with correct stage columns
- Verify leads render with new fields (tier, routing, org type, lead source)
- Drag a lead between stages, verify stage transition persists via API

Validates auth wiring (Section 2), compliance-cleaned controllers (Section 3), and UI polish (Section 4) all at once.

## Approach

Sequential (Approach A). Auth must come before E2E since login is part of the test flow. Compliance fixes clean up controllers before we test them. UI polish is independent but quick.

## Out of Scope

- Phase 2 (agent integration) and Phase 3 (outreach engine) issues
- New feature work beyond what's listed
- Deploy automation changes (#56) — separate concern
