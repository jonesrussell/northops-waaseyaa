# GitHub Workflow Specification

## Versioning Model

This repo uses milestone-based versioning. Milestones group related issues into deliverable units. No SemVer — milestones are the roadmap.

## Current Milestones

| # | Title | Scope |
|---|-------|-------|
| 1 | M1: Scaffold & Home Page | Initial site structure, public pages |
| 2 | M2: Contact Form & Admin | Contact submission, admin shell |
| 3 | M3: Deploy & Cutover | Production deployment, DNS |
| 4 | M1: Company Definition & Positioning | Business strategy, pricing |
| 5 | M2: Brand Identity & Narrative | Logo, colors, voice |
| 6 | M3: Operational Infrastructure | Contracts, invoicing, SOPs |
| 7 | M4: Marketing & Launch Prep | Social, outreach, case studies |
| 8 | M5: Public Launch | Launch announcement, QA |
| 9 | M4: Codified Context Infrastructure | Three-tier context system, MCP retrieval, workflow governance |

## The 5 Workflow Rules

1. **All work begins with an issue** — ask for issue number before writing code; create one if missing
2. **Every issue belongs to a milestone** — unassigned issues are incomplete triage
3. **Milestones define the roadmap** — check active milestone before proposing work; don't invent new ones without discussion
4. **PRs must reference issues** — title format: `feat(#N): description`, `fix(#N): description`
5. **Claude reads the drift report** — flag `bin/check-milestones` warnings before beginning work

## Labels

| Label | Purpose |
|-------|---------|
| engineering | Technical implementation work |
| ops | Operational infrastructure |
| marketing | Marketing and outreach |
| content | Written content |
| branding | Brand identity |
| strategy | Business strategy |
| design | Visual design |

## PR Workflow

1. Create branch from `main`: `feat/#N-short-description` or `fix/#N-short-description`
2. Implement with commits referencing the issue
3. Open PR using `.github/pull_request_template.md`
4. Merge to `main` after review
