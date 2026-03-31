# Frontend Pipeline Catch-Up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Display the 9 new Lead fields (tier, routing_confidence, organization_type, lead_source, budget_range, urgency, funding_status, last_scored_at, specialist_context) across the admin dashboard — kanban cards, lead list table, and lead detail page.

**Architecture:** Pure frontend changes. The API already returns all 9 fields (#101 merged). We modify 4 files: `dashboard.js` (rendering logic), `dashboard.css` (new badge/progress styles), `lead-list.html.twig` (table header columns), and `lead-detail.html.twig` (new Routing & Scoring section + edit form fields). No PHP changes needed.

**Tech Stack:** Vanilla JS (ES5-style, no framework), Twig templates, CSS custom properties (existing `--dash-*` design tokens).

**Spec:** `docs/superpowers/specs/2026-03-30-frontend-pipeline-catchup-design.md`

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `public/js/dashboard.js` | Modify | Add tier/routing/orgType/source to `createCard()`, add 4 columns to `renderTable()`, add Routing & Scoring section rendering + edit field population in `populateDetailFields()`, add `formatRelativeTime()` helper, add `renderRoutingScoring()` function |
| `public/css/dashboard.css` | Modify | Add `.tier-badge`, `.tier-T1/T2/T3`, `.routing-confidence`, `.org-type-tag`, `.lead-source-tag`, `.progress-bar`, `.routing-scoring-section`, `.expandable-text` styles |
| `templates/admin/lead-list.html.twig` | Modify | Add 4 `<th>` columns after Score (Tier, Routing, Org Type, Lead Source), update colspan |
| `templates/admin/lead-detail.html.twig` | Modify | Add Routing & Scoring section HTML, add 6 editable fields to the edit form |

---

## Task 1: Add tier badge and routing styles to CSS

**Files:**
- Modify: `public/css/dashboard.css` (append after existing score-badge styles)

- [ ] **Step 1: Add tier badge, routing, org-type, lead-source, and progress bar CSS**

Append to `public/css/dashboard.css` before the closing comment or at end of the lead card section:

```css
/* Tier badges
   ========================================================================== */

.tier-badge {
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 3px;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    white-space: nowrap;
}

.tier-T1 {
    background: #dc262620;
    color: #dc2626;
}

.tier-T2 {
    background: #d9770620;
    color: #d97706;
}

.tier-T3 {
    background: #6b728020;
    color: #6b7280;
}

.tier-T4 {
    background: #6b728015;
    color: #9ca3af;
}

.tier-T5 {
    background: #6b728010;
    color: #d1d5db;
}

/* Routing confidence (inline muted text) */

.routing-confidence {
    font-size: 0.7rem;
    color: var(--dash-text-muted);
    white-space: nowrap;
}

/* Organization type tag */

.org-type-tag {
    font-size: 0.7rem;
    color: var(--dash-text-muted);
    background: var(--dash-bg);
    padding: 0.1rem 0.35rem;
    border-radius: 3px;
}

/* Lead source tag */

.lead-source-tag {
    font-size: 0.7rem;
    color: var(--dash-text-muted);
    background: var(--dash-bg);
    padding: 0.1rem 0.35rem;
    border-radius: 3px;
    text-transform: lowercase;
}

/* Progress bar (routing confidence on detail page) */

.progress-bar-track {
    width: 100%;
    max-width: 200px;
    height: 8px;
    background: var(--dash-border);
    border-radius: 4px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
    margin-right: 0.5rem;
}

.progress-bar-fill {
    height: 100%;
    border-radius: 4px;
    background: var(--dash-primary);
    transition: width 0.3s ease;
}

/* Routing & Scoring section (detail page)
   ========================================================================== */

.routing-scoring-section {
    background: var(--dash-surface);
    border: 1px solid var(--dash-border);
    border-radius: var(--dash-radius);
    padding: 1.25rem;
    margin-bottom: 1.5rem;
}

.routing-scoring-section h3 {
    margin: 0 0 1rem 0;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dash-text);
}

.routing-scoring-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}

.routing-scoring-field {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.routing-scoring-field .field-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--dash-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

.routing-scoring-field .field-value {
    font-size: 0.85rem;
    color: var(--dash-text);
}

.routing-scoring-field.full-width {
    grid-column: 1 / -1;
}

/* Urgency color coding */

.urgency-text-low { color: var(--dash-text-muted); }
.urgency-text-medium { color: #d97706; }
.urgency-text-high { color: #dc2626; }
.urgency-text-critical { color: #dc2626; font-weight: 700; }

/* Expandable text block (specialist context) */

.expandable-text {
    position: relative;
}

.expandable-text-content {
    max-height: 3rem;
    overflow: hidden;
    font-size: 0.8rem;
    color: var(--dash-text);
    line-height: 1.5;
    transition: max-height 0.3s ease;
}

.expandable-text-content.expanded {
    max-height: none;
}

.expandable-toggle {
    font-size: 0.75rem;
    color: var(--dash-primary);
    cursor: pointer;
    border: none;
    background: none;
    padding: 0.25rem 0;
}
```

- [ ] **Step 2: Verify styles load without errors**

Run: Open `http://localhost:8080/admin` in browser, check console for CSS errors. Existing UI should look unchanged.

- [ ] **Step 3: Commit**

```bash
git add public/css/dashboard.css
git commit -m "feat(#102,#103): add tier badge, routing, and scoring CSS styles"
```

---

## Task 2: Add tier badge and new metadata to kanban cards (#102)

**Files:**
- Modify: `public/js/dashboard.js` — `createCard()` function (lines 238-287)

- [ ] **Step 1: Add `tierLabel()` helper function**

Add this helper after the `getBrand()` function (after line 70) in `dashboard.js`:

```javascript
    function tierLabel(tier) {
        if (!tier) return null;
        var labels = { T1: 'T1 — Hot', T2: 'T2 — Warm', T3: 'T3 — Cold', T4: 'T4', T5: 'T5' };
        return labels[tier] || tier;
    }

    function formatRelativeTime(dateStr) {
        if (!dateStr) return '';
        var now = new Date();
        var then = new Date(dateStr);
        var diffMs = now - then;
        var diffMins = Math.floor(diffMs / 60000);
        if (diffMins < 1) return 'just now';
        if (diffMins < 60) return diffMins + 'm ago';
        var diffHrs = Math.floor(diffMins / 60);
        if (diffHrs < 24) return diffHrs + 'h ago';
        var diffDays = Math.floor(diffHrs / 24);
        if (diffDays < 30) return diffDays + 'd ago';
        return formatDate(dateStr);
    }
```

- [ ] **Step 2: Modify `createCard()` to include tier badge, routing confidence, org type, and lead source**

Replace the `createCard()` function (lines 238-287) with:

```javascript
    function createCard(lead) {
        var brand = getBrand(lead.brand_id);
        var score = lead.qualify_rating;
        var scoreClass = score >= 70 ? 'score-high' : score >= 40 ? 'score-mid' : 'score-low';
        var urgency = getUrgency(lead.closing_date);

        // Card header: label + tier badge (or score badge if no tier)
        var headerChildren = [el('span', { className: 'card-label', textContent: lead.label || '' })];
        if (lead.tier) {
            headerChildren.push(el('span', {
                className: 'tier-badge tier-' + lead.tier,
                textContent: lead.tier,
            }));
        } else if (score != null) {
            headerChildren.push(el('span', { className: 'score-badge ' + scoreClass, textContent: String(score) }));
        }
        var cardHeader = el('div', { className: 'card-header' }, headerChildren);

        // Card body parts
        var parts = [cardHeader];

        if (lead.company_name) {
            parts.push(el('div', { className: 'card-company', textContent: lead.company_name }));
        }

        // Organization type (subtle tag below company name)
        if (lead.organization_type) {
            parts.push(el('div', {}, [
                el('span', { className: 'org-type-tag', textContent: lead.organization_type }),
            ]));
        }

        // Meta row: brand, source, lead_source, routing confidence, urgency
        var metaChildren = [];
        if (brand) {
            metaChildren.push(el('span', {
                className: 'brand-tag',
                style: 'background:' + brand.primary_color + '20;color:' + brand.primary_color,
                textContent: brand.name,
            }));
        }
        if (lead.lead_source) {
            metaChildren.push(el('span', { className: 'lead-source-tag', textContent: lead.lead_source }));
        } else if (lead.source) {
            metaChildren.push(el('span', { className: 'source-tag', textContent: lead.source }));
        }
        if (lead.routing_confidence != null) {
            metaChildren.push(el('span', { className: 'routing-confidence', textContent: lead.routing_confidence + '%' }));
        }
        if (urgency) {
            metaChildren.push(el('span', { className: 'urgency-badge urgency-' + urgency.level, textContent: urgency.label }));
        }
        if (metaChildren.length) {
            parts.push(el('div', { className: 'card-meta' }, metaChildren));
        }

        if (lead.value) {
            parts.push(el('div', { className: 'card-value', textContent: '$' + Number(lead.value).toLocaleString() }));
        }

        var card = el('div', { className: 'lead-card', 'data-id': lead.id }, parts);
        card.addEventListener('click', function () {
            window.location.href = '/admin/leads/' + lead.id;
        });

        return card;
    }
```

- [ ] **Step 3: Verify kanban cards render with new fields**

Run: Open `http://localhost:8080/admin`. Cards should show:
- Tier badge (colored T1/T2/T3) in the card header (replaces score badge when tier present)
- Organization type tag below company name
- Lead source tag in meta row
- Routing confidence percentage in meta row

- [ ] **Step 4: Commit**

```bash
git add public/js/dashboard.js
git commit -m "feat(#102): add tier, org type, lead source, routing to kanban cards"
```

---

## Task 3: Add 4 new columns to lead list table (#102)

**Files:**
- Modify: `templates/admin/lead-list.html.twig` (lines 24-34 — `<thead>`)
- Modify: `public/js/dashboard.js` — `renderTable()` function (lines 293-369)

- [ ] **Step 1: Add 4 column headers to `lead-list.html.twig`**

Insert 4 new `<th>` elements after the Score column (after line 30):

```html
                    <th class="sortable" data-sort="tier">Tier</th>
                    <th class="sortable" data-sort="routing_confidence">Routing</th>
                    <th class="sortable" data-sort="organization_type">Org Type</th>
                    <th class="sortable" data-sort="lead_source">Lead Source</th>
```

The full `<thead>` should now be:

```html
            <thead>
                <tr>
                    <th class="sortable" data-sort="label">Name</th>
                    <th class="sortable" data-sort="company_name">Company</th>
                    <th class="sortable" data-sort="stage">Stage</th>
                    <th class="sortable" data-sort="brand_id">Brand</th>
                    <th class="sortable" data-sort="source">Source</th>
                    <th class="sortable" data-sort="qualify_rating">Score</th>
                    <th class="sortable" data-sort="tier">Tier</th>
                    <th class="sortable" data-sort="routing_confidence">Routing</th>
                    <th class="sortable" data-sort="organization_type">Org Type</th>
                    <th class="sortable" data-sort="lead_source">Lead Source</th>
                    <th class="sortable" data-sort="value">Value</th>
                    <th class="sortable" data-sort="closing_date">Closing</th>
                    <th class="sortable" data-sort="updated_at">Updated</th>
                </tr>
            </thead>
```

- [ ] **Step 2: Add 4 cell renderers in `renderTable()` in `dashboard.js`**

In the `renderTable()` function, after the Score cell block (after line 355 `cells.push(scoreCell);`), add:

```javascript
            // Tier
            var tierCell = el('td');
            if (l.tier) {
                tierCell.appendChild(el('span', { className: 'tier-badge tier-' + l.tier, textContent: l.tier }));
            }
            cells.push(tierCell);
            // Routing
            var routingCell = el('td');
            if (l.routing_confidence != null) {
                var brandForRouting = getBrand(l.brand_id);
                var routingText = l.routing_confidence + '%';
                if (brandForRouting) routingText += ' · ' + brandForRouting.name;
                routingCell.appendChild(el('span', { className: 'routing-confidence', textContent: routingText }));
            }
            cells.push(routingCell);
            // Org Type
            cells.push(el('td', { textContent: l.organization_type || '' }));
            // Lead Source
            var sourceCell2 = el('td');
            if (l.lead_source) {
                sourceCell2.appendChild(el('span', { className: 'lead-source-tag', textContent: l.lead_source }));
            }
            cells.push(sourceCell2);
```

- [ ] **Step 3: Update the empty row colspan**

In `renderTable()`, find the line (around line 319):
```javascript
            emptyRow.querySelector('td').setAttribute('colspan', '9');
```
Change `'9'` to `'13'` to match the 13 columns.

- [ ] **Step 4: Verify table renders with new columns**

Run: Open `http://localhost:8080/admin/leads`. The table should show 13 columns. Tier column shows colored badges. Routing shows percentage + brand name. Sorting works on all new columns.

- [ ] **Step 5: Commit**

```bash
git add templates/admin/lead-list.html.twig public/js/dashboard.js
git commit -m "feat(#102): add tier, routing, org type, lead source columns to lead list table"
```

---

## Task 4: Add Routing & Scoring section to lead detail page (#103)

**Files:**
- Modify: `templates/admin/lead-detail.html.twig` (add section between qualification panel and edit form)
- Modify: `public/js/dashboard.js` — add `renderRoutingScoring()` function, call from `loadLeadDetail()`

- [ ] **Step 1: Add Routing & Scoring section HTML to `lead-detail.html.twig`**

Insert after the qualification panel `</div>` (after line 42) and before the edit form `<form>` (line 45):

```html
            <!-- Routing & Scoring -->
            <div class="routing-scoring-section" id="routing-scoring-section" style="display:none;">
                <h3>Routing &amp; Scoring</h3>
                <div class="routing-scoring-grid" id="routing-scoring-grid">
                    <!-- Populated by JS -->
                </div>
            </div>
```

- [ ] **Step 2: Add `renderRoutingScoring()` function to `dashboard.js`**

Add this function after `renderQualification()` (after line 519):

```javascript
    function renderRoutingScoring(lead) {
        var section = document.getElementById('routing-scoring-section');
        if (!section) return;

        var hasData = lead.tier || lead.routing_confidence != null || lead.organization_type ||
            lead.lead_source || lead.budget_range || lead.urgency || lead.funding_status ||
            lead.last_scored_at || lead.specialist_context;

        if (!hasData) {
            section.style.display = 'none';
            return;
        }

        section.style.display = '';
        var grid = document.getElementById('routing-scoring-grid');
        grid.textContent = '';

        // Tier — large badge
        if (lead.tier) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Tier' }),
                el('span', {
                    className: 'tier-badge tier-' + lead.tier,
                    style: 'font-size:1rem;padding:0.25rem 0.6rem;',
                    textContent: tierLabel(lead.tier),
                }),
            ]));
        }

        // Routing Confidence — progress bar
        if (lead.routing_confidence != null) {
            var progressBar = el('div', { className: 'progress-bar-track' }, [
                el('div', {
                    className: 'progress-bar-fill',
                    style: 'width:' + lead.routing_confidence + '%',
                }),
            ]);
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Routing Confidence' }),
                el('div', { className: 'field-value' }, [progressBar, ' ' + lead.routing_confidence + '%']),
            ]));
        }

        // Organization Type
        if (lead.organization_type) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Organization Type' }),
                el('span', { className: 'field-value', textContent: lead.organization_type }),
            ]));
        }

        // Lead Source
        if (lead.lead_source) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Lead Source' }),
                el('span', { className: 'field-value', textContent: lead.lead_source }),
            ]));
        }

        // Budget Range
        if (lead.budget_range) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Budget Range' }),
                el('span', { className: 'field-value', textContent: lead.budget_range.replace(/_/g, ' ') }),
            ]));
        }

        // Urgency — color coded
        if (lead.urgency) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Urgency' }),
                el('span', {
                    className: 'field-value urgency-text-' + lead.urgency,
                    textContent: lead.urgency,
                }),
            ]));
        }

        // Funding Status
        if (lead.funding_status) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Funding Status' }),
                el('span', { className: 'field-value', textContent: lead.funding_status }),
            ]));
        }

        // Last Scored At — relative time
        if (lead.last_scored_at) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Last Scored' }),
                el('span', { className: 'field-value', textContent: formatRelativeTime(lead.last_scored_at) }),
            ]));
        }

        // Specialist Context — expandable
        if (lead.specialist_context) {
            var contextText = typeof lead.specialist_context === 'string'
                ? lead.specialist_context
                : JSON.stringify(lead.specialist_context, null, 2);

            var contentDiv = el('div', { className: 'expandable-text-content', textContent: contextText });
            var toggleBtn = el('button', { className: 'expandable-toggle', textContent: 'Show more' });
            toggleBtn.addEventListener('click', function () {
                var isExpanded = contentDiv.classList.toggle('expanded');
                toggleBtn.textContent = isExpanded ? 'Show less' : 'Show more';
            });

            grid.appendChild(el('div', { className: 'routing-scoring-field full-width' }, [
                el('span', { className: 'field-label', textContent: 'Specialist Context' }),
                el('div', { className: 'expandable-text' }, [contentDiv, toggleBtn]),
            ]));
        }
    }
```

- [ ] **Step 3: Wire `renderRoutingScoring()` into `loadLeadDetail()`**

In the `loadLeadDetail()` function (around line 426-435), add the call after `renderQualification(lead)`:

Change:
```javascript
            populateDetailFields(lead);
            renderStageActions(lead);
            renderQualification(lead);
```

To:
```javascript
            populateDetailFields(lead);
            renderStageActions(lead);
            renderQualification(lead);
            renderRoutingScoring(lead);
```

- [ ] **Step 4: Verify Routing & Scoring section renders on detail page**

Run: Open `http://localhost:8080/admin/leads/{id}` for a lead that has tier/routing data. The Routing & Scoring section should appear between the qualification panel and the edit form, showing a 2-column grid with the available fields.

- [ ] **Step 5: Commit**

```bash
git add templates/admin/lead-detail.html.twig public/js/dashboard.js
git commit -m "feat(#103): add Routing & Scoring display section to lead detail page"
```

---

## Task 5: Add editable fields for Routing & Scoring to lead detail form (#103)

**Files:**
- Modify: `templates/admin/lead-detail.html.twig` — add 6 form fields to the edit form
- Modify: `public/js/dashboard.js` — populate new fields in `populateDetailFields()`, populate new select options

- [ ] **Step 1: Add 6 editable fields to the edit form in `lead-detail.html.twig`**

Insert after the description form-group (after line 98 `</div>` closing the description group) and before the closing `</form>` tag:

```html
                <h4 style="margin:1.5rem 0 0.75rem;font-size:0.85rem;color:var(--dash-text-muted);text-transform:uppercase;letter-spacing:0.03em;">Routing &amp; Scoring Overrides</h4>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-tier">Tier</label>
                        <select id="edit-tier" name="tier">
                            <option value="">—</option>
                            <option value="T1">T1 — Hot</option>
                            <option value="T2">T2 — Warm</option>
                            <option value="T3">T3 — Cold</option>
                            <option value="T4">T4</option>
                            <option value="T5">T5</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-organization-type">Organization Type</label>
                        <select id="edit-organization-type" name="organization_type">
                            <option value="">—</option>
                            <option value="startup">Startup</option>
                            <option value="nonprofit">Nonprofit</option>
                            <option value="charity">Charity</option>
                            <option value="indigenous">Indigenous</option>
                            <option value="community">Community</option>
                            <option value="government">Government</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-lead-source">Lead Source</label>
                        <select id="edit-lead-source" name="lead_source">
                            <option value="">—</option>
                            <option value="rfp">RFP</option>
                            <option value="signal">Signal</option>
                            <option value="job_posting">Job Posting</option>
                            <option value="funding">Funding</option>
                            <option value="website_audit">Website Audit</option>
                            <option value="manual">Manual</option>
                            <option value="directory">Directory</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-budget-range">Budget Range</label>
                        <select id="edit-budget-range" name="budget_range">
                            <option value="">—</option>
                            <option value="under_2k">Under $2K</option>
                            <option value="2k_5k">$2K – $5K</option>
                            <option value="5k_10k">$5K – $10K</option>
                            <option value="10k_25k">$10K – $25K</option>
                            <option value="25k_plus">$25K+</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit-urgency">Urgency</label>
                        <select id="edit-urgency" name="urgency">
                            <option value="">—</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-funding-status">Funding Status</label>
                        <select id="edit-funding-status" name="funding_status">
                            <option value="">—</option>
                            <option value="none">None</option>
                            <option value="applied">Applied</option>
                            <option value="received">Received</option>
                            <option value="unknown">Unknown</option>
                        </select>
                    </div>
                </div>
```

- [ ] **Step 2: Populate new edit fields in `populateDetailFields()` in `dashboard.js`**

Add these lines at the end of `populateDetailFields()` (after line 467 `setFieldValue('edit-description', lead.description);`):

```javascript
        setFieldValue('edit-tier', lead.tier);
        setFieldValue('edit-organization-type', lead.organization_type);
        setFieldValue('edit-lead-source', lead.lead_source);
        setFieldValue('edit-budget-range', lead.budget_range);
        setFieldValue('edit-urgency', lead.urgency);
        setFieldValue('edit-funding-status', lead.funding_status);
```

- [ ] **Step 3: Verify edit fields populate and save correctly**

Run: Open `http://localhost:8080/admin/leads/{id}` for a lead with tier data. The "Routing & Scoring Overrides" section should appear at the bottom of the edit form. Fields should be pre-populated with existing values. Change a field, click "Save Changes", reload — the value should persist.

- [ ] **Step 4: Commit**

```bash
git add templates/admin/lead-detail.html.twig public/js/dashboard.js
git commit -m "feat(#103): add editable routing & scoring fields to lead detail form"
```

---

## Task 6: Manual smoke test

No code changes — verification only.

- [ ] **Step 1: Verify kanban board**

Open `http://localhost:8080/admin`. Check:
- Cards show tier badge (colored T1/T2/T3) in header
- Organization type appears below company name
- Lead source tag visible in meta row
- Routing confidence percentage visible in meta row
- Cards still link to detail page on click

- [ ] **Step 2: Verify lead list table**

Open `http://localhost:8080/admin/leads`. Check:
- 13 columns visible (Name, Company, Stage, Brand, Source, Score, Tier, Routing, Org Type, Lead Source, Value, Closing, Updated)
- Tier column shows colored badges
- Routing column shows percentage + brand name
- All 4 new columns are sortable (click header toggles asc/desc)
- Empty state row spans full width

- [ ] **Step 3: Verify lead detail page**

Open a lead detail. Check:
- Routing & Scoring section visible between qualification panel and edit form
- Tier shows large colored badge with label ("T1 — Hot")
- Routing confidence shows progress bar + percentage
- Specialist context is expandable (click "Show more"/"Show less")
- Last Scored shows relative time ("2d ago", "5h ago")
- Edit form has 6 new fields under "Routing & Scoring Overrides" header
- Saving changes to tier/org_type/etc persists correctly

- [ ] **Step 4: Close issues**

```bash
gh issue close 102 --repo jonesrussell/northops-waaseyaa --comment "Implemented: tier badges, routing confidence, org type, lead source on kanban cards and lead list table."
gh issue close 103 --repo jonesrussell/northops-waaseyaa --comment "Implemented: Routing & Scoring section on lead detail with display + editable overrides."
```
