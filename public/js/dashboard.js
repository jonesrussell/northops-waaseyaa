/* ==========================================================================
   Dashboard JS — Pipeline CRM
   Vanilla JS, no framework. Drives all admin pages.
   All dynamic content is escaped via escapeHtml() before DOM insertion.
   ========================================================================== */

(function () {
    'use strict';

    // -----------------------------------------------------------------------
    // State
    // -----------------------------------------------------------------------

    var leads = [];
    var brands = [];
    var config = { stages: [], sectors: [], sources: [] };
    var filters = { brand_id: '', sector: '', source: '', stage: '', search: '' };
    var sortField = '';
    var sortDir = 'asc';

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    async function fetchJson(url, options) {
        options = options || {};
        var res = await fetch(url, {
            headers: Object.assign({ 'Accept': 'application/json' }, options.headers || {}),
            method: options.method || 'GET',
            body: options.body || undefined,
        });
        if (!res.ok) {
            var text = await res.text().catch(function () { return ''; });
            throw new Error('API ' + res.status + ': ' + (text || res.statusText));
        }
        return res.json();
    }

    function escapeHtml(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.textContent = String(str);
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        return d.toLocaleDateString('en-CA');
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        return d.toLocaleDateString('en-CA') + ' ' + d.toLocaleTimeString('en-CA', { hour: '2-digit', minute: '2-digit' });
    }

    function getUrgency(closingDate) {
        if (!closingDate) return null;
        var days = Math.ceil((new Date(closingDate) - new Date()) / (1000 * 60 * 60 * 24));
        if (days < 0) return { level: 'closed', label: 'Closed' };
        if (days <= 7) return { level: 'urgent', label: days + 'd left' };
        if (days <= 21) return { level: 'soon', label: days + 'd left' };
        if (days <= 60) return { level: 'normal', label: days + 'd left' };
        return { level: 'far', label: days + 'd left' };
    }

    function getBrand(brandId) {
        return brands.find(function (b) { return b.id == brandId; });
    }

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

    function showToast(message, type) {
        type = type || 'info';
        var container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () {
            toast.remove();
            if (container.children.length === 0) container.remove();
        }, 3500);
    }

    // -----------------------------------------------------------------------
    // Safe DOM builders — use textContent/createElement instead of innerHTML
    // -----------------------------------------------------------------------

    function el(tag, attrs, children) {
        var node = document.createElement(tag);
        if (attrs) {
            Object.keys(attrs).forEach(function (k) {
                if (k === 'className') node.className = attrs[k];
                else if (k === 'textContent') node.textContent = attrs[k];
                else if (k.indexOf('data-') === 0) node.dataset[k.substring(5)] = attrs[k];
                else if (k === 'style') node.setAttribute('style', attrs[k]);
                else node.setAttribute(k, attrs[k]);
            });
        }
        if (children) {
            if (!Array.isArray(children)) children = [children];
            children.forEach(function (c) {
                if (typeof c === 'string') node.appendChild(document.createTextNode(c));
                else if (c) node.appendChild(c);
            });
        }
        return node;
    }

    // -----------------------------------------------------------------------
    // Filter logic
    // -----------------------------------------------------------------------

    function applyFilters(list) {
        return list.filter(function (l) {
            if (filters.brand_id && l.brand_id != filters.brand_id) return false;
            if (filters.sector && l.sector !== filters.sector) return false;
            if (filters.source && l.source !== filters.source) return false;
            if (filters.stage && l.stage !== filters.stage) return false;
            if (filters.search) {
                var q = filters.search.toLowerCase();
                var haystack = [l.label, l.company_name, l.contact_name, l.contact_email].join(' ').toLowerCase();
                if (haystack.indexOf(q) === -1) return false;
            }
            return true;
        });
    }

    // -----------------------------------------------------------------------
    // Populate filter dropdowns (safe DOM methods)
    // -----------------------------------------------------------------------

    function populateFilters() {
        populateSelect('filter-brand', brands.map(function (b) {
            return { value: b.id, label: b.name };
        }), 'All Brands');

        populateSelect('filter-sector', (config.sectors || []).map(function (s) {
            return { value: s, label: s };
        }), 'All Sectors');

        populateSelect('filter-source', (config.sources || []).map(function (s) {
            return { value: s, label: s };
        }), 'All Sources');

        populateSelect('filter-stage', (config.stages || []).map(function (s) {
            return { value: s, label: s };
        }), 'All Stages');

        // New lead form selects
        populateSelect('lead-brand', brands.map(function (b) {
            return { value: b.id, label: b.name };
        }), '');

        populateSelect('lead-source', (config.sources || []).map(function (s) {
            return { value: s, label: s };
        }), '');

        populateSelect('lead-sector', (config.sectors || []).map(function (s) {
            return { value: s, label: s };
        }), '\u2014');

        // Detail page selects
        populateSelect('edit-brand', brands.map(function (b) {
            return { value: b.id, label: b.name };
        }), '');

        populateSelect('edit-source', (config.sources || []).map(function (s) {
            return { value: s, label: s };
        }), '');

        populateSelect('edit-sector', (config.sectors || []).map(function (s) {
            return { value: s, label: s };
        }), '\u2014');
    }

    function populateSelect(id, options, placeholder) {
        var selectEl = document.getElementById(id);
        if (!selectEl) return;
        selectEl.textContent = ''; // clear existing options

        if (placeholder !== undefined && placeholder !== null) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            selectEl.appendChild(opt);
        }
        for (var i = 0; i < options.length; i++) {
            var opt2 = document.createElement('option');
            opt2.value = String(options[i].value);
            opt2.textContent = options[i].label;
            selectEl.appendChild(opt2);
        }
    }

    // -----------------------------------------------------------------------
    // Pipeline board (Kanban) — dashboard page
    // -----------------------------------------------------------------------

    function renderBoard() {
        var board = document.getElementById('pipeline-board');
        if (!board) return;

        board.textContent = '';
        var filtered = applyFilters(leads);

        for (var i = 0; i < config.stages.length; i++) {
            var stage = config.stages[i];
            var stageLeads = filtered.filter(function (l) { return l.stage === stage; });
            board.appendChild(createColumn(stage, stageLeads));
        }
    }

    function createColumn(stage, stageLeads) {
        var header = el('div', { className: 'column-header' }, [
            el('span', { className: 'column-title', textContent: stage }),
            el('span', { className: 'column-count', textContent: String(stageLeads.length) }),
        ]);

        var cardsContainer = el('div', { className: 'column-cards' });
        if (stageLeads.length === 0) {
            cardsContainer.appendChild(el('div', { className: 'empty-state', textContent: 'No leads' }));
        } else {
            for (var i = 0; i < stageLeads.length; i++) {
                cardsContainer.appendChild(createCard(stageLeads[i]));
            }
        }

        var col = el('div', { className: 'pipeline-column', 'data-stage': stage }, [header, cardsContainer]);
        return col;
    }

    function createCard(lead) {
        var brand = getBrand(lead.brand_id);
        var score = lead.qualify_rating;
        var scoreClass = score >= 70 ? 'score-high' : score >= 40 ? 'score-mid' : 'score-low';
        var urgency = getUrgency(lead.closing_date);

        // Card header
        var headerChildren = [el('span', { className: 'card-label', textContent: lead.label || '' })];
        if (score != null) {
            headerChildren.push(el('span', { className: 'score-badge ' + scoreClass, textContent: String(score) }));
        }
        var cardHeader = el('div', { className: 'card-header' }, headerChildren);

        // Card body parts
        var parts = [cardHeader];

        if (lead.company_name) {
            parts.push(el('div', { className: 'card-company', textContent: lead.company_name }));
        }

        // Meta row
        var metaChildren = [];
        if (brand) {
            metaChildren.push(el('span', {
                className: 'brand-tag',
                style: 'background:' + brand.primary_color + '20;color:' + brand.primary_color,
                textContent: brand.name,
            }));
        }
        if (lead.source) {
            metaChildren.push(el('span', { className: 'source-tag', textContent: lead.source }));
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

    // -----------------------------------------------------------------------
    // Lead table (list view)
    // -----------------------------------------------------------------------

    function renderTable() {
        var tbody = document.getElementById('lead-table-body');
        if (!tbody) return;

        var filtered = applyFilters(leads);

        // Sort
        if (sortField) {
            filtered.sort(function (a, b) {
                var av = a[sortField] || '';
                var bv = b[sortField] || '';
                if (typeof av === 'number' && typeof bv === 'number') {
                    return sortDir === 'asc' ? av - bv : bv - av;
                }
                av = String(av).toLowerCase();
                bv = String(bv).toLowerCase();
                if (av < bv) return sortDir === 'asc' ? -1 : 1;
                if (av > bv) return sortDir === 'asc' ? 1 : -1;
                return 0;
            });
        }

        tbody.textContent = '';

        if (filtered.length === 0) {
            var emptyRow = el('tr', {}, [el('td', { className: 'empty-state' }, ['No leads found'])]);
            emptyRow.querySelector('td').setAttribute('colspan', '9');
            tbody.appendChild(emptyRow);
            return;
        }

        for (var i = 0; i < filtered.length; i++) {
            var l = filtered[i];
            var brand = getBrand(l.brand_id);
            var score = l.qualify_rating;
            var scoreClass = score >= 70 ? 'score-high' : score >= 40 ? 'score-mid' : 'score-low';

            var cells = [];

            // Name
            cells.push(el('td', {}, [el('strong', { textContent: l.label || '' })]));
            // Company
            cells.push(el('td', { textContent: l.company_name || '' }));
            // Stage
            cells.push(el('td', {}, [el('span', { className: 'stage-badge', textContent: l.stage || '' })]));
            // Brand
            var brandCell = el('td');
            if (brand) {
                brandCell.appendChild(el('span', {
                    className: 'brand-tag',
                    style: 'background:' + brand.primary_color + '20;color:' + brand.primary_color,
                    textContent: brand.name,
                }));
            }
            cells.push(brandCell);
            // Source
            cells.push(el('td', { textContent: l.source || '' }));
            // Score
            var scoreCell = el('td');
            if (score != null) {
                scoreCell.appendChild(el('span', { className: 'score-badge ' + scoreClass, textContent: String(score) }));
            }
            cells.push(scoreCell);
            // Value
            cells.push(el('td', { textContent: l.value ? '$' + Number(l.value).toLocaleString() : '' }));
            // Closing
            cells.push(el('td', { textContent: formatDate(l.closing_date) }));
            // Updated
            cells.push(el('td', { textContent: formatDate(l.updated_at) }));

            var row = el('tr', { 'data-id': l.id }, cells);
            row.addEventListener('click', (function (leadId) {
                return function () { window.location.href = '/admin/leads/' + leadId; };
            })(l.id));
            tbody.appendChild(row);
        }
    }

    // Table sorting
    function initTableSort() {
        var headers = document.querySelectorAll('.lead-table th.sortable');
        for (var i = 0; i < headers.length; i++) {
            headers[i].addEventListener('click', function () {
                var field = this.dataset.sort;
                if (sortField === field) {
                    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
                } else {
                    sortField = field;
                    sortDir = 'asc';
                }
                var allHeaders = document.querySelectorAll('.lead-table th.sortable');
                for (var j = 0; j < allHeaders.length; j++) {
                    allHeaders[j].classList.remove('sort-asc', 'sort-desc');
                }
                this.classList.add(sortDir === 'asc' ? 'sort-asc' : 'sort-desc');
                renderTable();
            });
        }
    }

    // -----------------------------------------------------------------------
    // Lead detail page
    // -----------------------------------------------------------------------

    function initDetailPage() {
        var container = document.querySelector('[data-page="lead-detail"]');
        if (!container) return;

        var leadId = container.dataset.leadId;
        if (!leadId) return;

        loadLeadDetail(leadId);
        loadActivity(leadId);

        var saveBtn = document.getElementById('save-lead-btn');
        if (saveBtn) {
            saveBtn.addEventListener('click', function () { saveLead(leadId); });
        }

        var qualifyBtn = document.getElementById('qualify-btn');
        if (qualifyBtn) {
            qualifyBtn.addEventListener('click', function () { qualifyLead(leadId); });
        }

        var noteForm = document.getElementById('add-note-form');
        if (noteForm) {
            noteForm.addEventListener('submit', function (e) {
                e.preventDefault();
                addNote(leadId);
            });
        }
    }

    async function loadLeadDetail(leadId) {
        try {
            var lead = await fetchJson('/api/leads/' + leadId);
            populateDetailFields(lead);
            renderStageActions(lead);
            renderQualification(lead);
            renderRoutingScoring(lead);
        } catch (err) {
            showToast('Failed to load lead: ' + err.message, 'error');
        }
    }

    function populateDetailFields(lead) {
        var labelEl = document.getElementById('detail-label');
        if (labelEl) labelEl.textContent = lead.label || 'Untitled';

        var titleEl = document.getElementById('detail-title');
        if (titleEl) titleEl.textContent = lead.label || 'Untitled';

        var stageEl = document.getElementById('detail-stage');
        if (stageEl) stageEl.textContent = lead.stage || '';

        var brandTagEl = document.getElementById('detail-brand-tag');
        if (brandTagEl) {
            var brand = getBrand(lead.brand_id);
            if (brand) {
                brandTagEl.style.background = brand.primary_color + '20';
                brandTagEl.style.color = brand.primary_color;
                brandTagEl.textContent = brand.name;
            }
        }

        setFieldValue('edit-label', lead.label);
        setFieldValue('edit-company', lead.company_name);
        setFieldValue('edit-contact-name', lead.contact_name);
        setFieldValue('edit-contact-email', lead.contact_email);
        setFieldValue('edit-contact-phone', lead.contact_phone);
        setFieldValue('edit-brand', lead.brand_id);
        setFieldValue('edit-source', lead.source);
        setFieldValue('edit-sector', lead.sector);
        setFieldValue('edit-value', lead.value);
        setFieldValue('edit-closing-date', lead.closing_date ? lead.closing_date.substring(0, 10) : '');
        setFieldValue('edit-description', lead.description);
        setFieldValue('edit-tier', lead.tier);
        setFieldValue('edit-organization-type', lead.organization_type);
        setFieldValue('edit-lead-source', lead.lead_source);
        setFieldValue('edit-budget-range', lead.budget_range);
        setFieldValue('edit-urgency', lead.urgency);
        setFieldValue('edit-funding-status', lead.funding_status);
    }

    function setFieldValue(id, value) {
        var fieldEl = document.getElementById(id);
        if (fieldEl) fieldEl.value = value || '';
    }

    function renderStageActions(lead) {
        var container = document.getElementById('stage-actions');
        if (!container) return;

        container.textContent = '';
        for (var i = 0; i < config.stages.length; i++) {
            var stage = config.stages[i];
            var btn = el('button', {
                className: 'btn btn-sm' + (stage === lead.stage ? ' active-stage' : ''),
                'data-stage': stage,
                textContent: stage,
            });
            btn.addEventListener('click', (function (stageVal, leadId) {
                return function () { changeStage(leadId, stageVal); };
            })(stage, lead.id));
            container.appendChild(btn);
        }
    }

    function renderQualification(lead) {
        var panel = document.getElementById('qualification-panel');
        if (!panel) return;

        if (lead.qualify_rating == null) {
            panel.style.display = 'none';
            return;
        }

        panel.style.display = '';
        var scoreEl = document.getElementById('qual-score');
        if (scoreEl) {
            scoreEl.textContent = '';
            var scoreClass = lead.qualify_rating >= 70 ? 'score-high' : lead.qualify_rating >= 40 ? 'score-mid' : 'score-low';
            var badge = el('span', {
                className: 'score-badge ' + scoreClass,
                style: 'font-size:1.5rem;padding:0.3rem 0.8rem;',
                textContent: String(lead.qualify_rating),
            });
            scoreEl.appendChild(badge);
        }

        var reasoningEl = document.getElementById('qual-reasoning');
        if (reasoningEl) {
            reasoningEl.textContent = lead.qualify_reasoning || '';
        }
    }

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

        if (lead.organization_type) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Organization Type' }),
                el('span', { className: 'field-value', textContent: lead.organization_type }),
            ]));
        }

        if (lead.lead_source) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Lead Source' }),
                el('span', { className: 'field-value', textContent: lead.lead_source }),
            ]));
        }

        if (lead.budget_range) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Budget Range' }),
                el('span', { className: 'field-value', textContent: lead.budget_range.replace(/_/g, ' ') }),
            ]));
        }

        if (lead.urgency) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Urgency' }),
                el('span', {
                    className: 'field-value urgency-text-' + lead.urgency,
                    textContent: lead.urgency,
                }),
            ]));
        }

        if (lead.funding_status) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Funding Status' }),
                el('span', { className: 'field-value', textContent: lead.funding_status }),
            ]));
        }

        if (lead.last_scored_at) {
            grid.appendChild(el('div', { className: 'routing-scoring-field' }, [
                el('span', { className: 'field-label', textContent: 'Last Scored' }),
                el('span', { className: 'field-value', textContent: formatRelativeTime(lead.last_scored_at) }),
            ]));
        }

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

    async function saveLead(leadId) {
        var form = document.getElementById('lead-edit-form');
        if (!form) return;

        var data = Object.fromEntries(new FormData(form));
        Object.keys(data).forEach(function (k) {
            if (data[k] === '') delete data[k];
        });

        try {
            await fetchJson('/api/leads/' + leadId, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            showToast('Lead saved', 'success');
            loadLeadDetail(leadId);
        } catch (err) {
            showToast('Failed to save: ' + err.message, 'error');
        }
    }

    async function changeStage(leadId, newStage) {
        try {
            await fetchJson('/api/leads/' + leadId + '/stage', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ stage: newStage }),
            });
            showToast('Stage changed to ' + newStage, 'success');
            loadLeadDetail(leadId);
            loadActivity(leadId);
        } catch (err) {
            showToast('Failed to change stage: ' + err.message, 'error');
        }
    }

    async function qualifyLead(leadId) {
        var btn = document.getElementById('qualify-btn');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Qualifying...';
        }
        try {
            await fetchJson('/api/leads/' + leadId + '/qualify', { method: 'POST' });
            showToast('Qualification complete', 'success');
            loadLeadDetail(leadId);
            loadActivity(leadId);
        } catch (err) {
            showToast('Qualification failed: ' + err.message, 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Qualify';
            }
        }
    }

    // -----------------------------------------------------------------------
    // Activity timeline
    // -----------------------------------------------------------------------

    async function loadActivity(leadId) {
        var timeline = document.getElementById('activity-timeline');
        if (!timeline) return;

        try {
            var activities = await fetchJson('/api/leads/' + leadId + '/activity');
            renderActivityTimeline(activities);
        } catch (err) {
            timeline.textContent = '';
            timeline.appendChild(el('div', { className: 'empty-state', textContent: 'Failed to load activity' }));
        }
    }

    function renderActivityTimeline(activities) {
        var timeline = document.getElementById('activity-timeline');
        if (!timeline) return;

        timeline.textContent = '';

        if (!activities || activities.length === 0) {
            timeline.appendChild(el('div', { className: 'empty-state', textContent: 'No activity yet' }));
            return;
        }

        for (var i = 0; i < activities.length; i++) {
            var a = activities[i];
            var item = el('div', { className: 'activity-item' }, [
                el('div', { className: 'activity-type', textContent: a.type || 'note' }),
                el('div', { className: 'activity-body', textContent: a.body || a.description || '' }),
                el('div', { className: 'activity-time', textContent: formatDateTime(a.created_at) }),
            ]);
            timeline.appendChild(item);
        }
    }

    async function addNote(leadId) {
        var bodyEl = document.getElementById('note-body');
        var typeEl = document.getElementById('note-type');
        if (!bodyEl || !bodyEl.value.trim()) return;

        var data = {
            body: bodyEl.value.trim(),
            type: typeEl ? typeEl.value : 'note',
        };

        try {
            await fetchJson('/api/leads/' + leadId + '/activity', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            bodyEl.value = '';
            showToast('Note added', 'success');
            loadActivity(leadId);
        } catch (err) {
            showToast('Failed to add note: ' + err.message, 'error');
        }
    }

    // -----------------------------------------------------------------------
    // Settings page
    // -----------------------------------------------------------------------

    function renderSettings() {
        renderBrandList();
        renderConfigDisplay();
    }

    function renderBrandList() {
        var container = document.getElementById('brand-list');
        if (!container) return;

        container.textContent = '';

        if (brands.length === 0) {
            container.appendChild(el('div', { className: 'empty-state', textContent: 'No brands configured' }));
            return;
        }

        for (var i = 0; i < brands.length; i++) {
            var b = brands[i];
            var card = el('div', { className: 'brand-card' }, [
                el('div', { className: 'brand-swatch', style: 'background:' + (b.primary_color || '#ccc') }),
                el('div', { className: 'brand-info' }, [
                    el('div', { className: 'brand-name', textContent: b.name }),
                    el('div', { className: 'brand-code', textContent: b.code || b.id }),
                ]),
            ]);
            container.appendChild(card);
        }
    }

    function renderConfigDisplay() {
        var container = document.getElementById('config-display');
        if (!container) return;

        container.textContent = '';

        var sections = [
            { key: 'stages', title: 'Stages' },
            { key: 'sectors', title: 'Sectors' },
            { key: 'sources', title: 'Sources' },
        ];

        var hasContent = false;
        for (var s = 0; s < sections.length; s++) {
            var items = config[sections[s].key];
            if (!items || !items.length) continue;
            hasContent = true;

            var itemEls = items.map(function (item) {
                return el('span', { className: 'config-item', textContent: item });
            });

            container.appendChild(el('div', { className: 'config-group' }, [
                el('h4', { textContent: sections[s].title }),
                el('div', { className: 'config-items' }, itemEls),
            ]));
        }

        if (!hasContent) {
            container.appendChild(el('div', { className: 'empty-state', textContent: 'No configuration loaded' }));
        }
    }

    // -----------------------------------------------------------------------
    // New lead modal (dashboard page)
    // -----------------------------------------------------------------------

    function initModal() {
        var addBtn = document.getElementById('add-lead-btn');
        var modal = document.getElementById('new-lead-modal');
        var closeBtn = document.getElementById('modal-close');
        var cancelBtn = document.getElementById('modal-cancel');

        if (!modal) return;

        if (addBtn) {
            addBtn.addEventListener('click', function () { modal.style.display = 'flex'; });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
        }
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () { modal.style.display = 'none'; });
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal) modal.style.display = 'none';
        });

        var form = document.getElementById('new-lead-form');
        if (form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                var data = Object.fromEntries(new FormData(form));
                Object.keys(data).forEach(function (k) {
                    if (data[k] === '') delete data[k];
                });

                try {
                    await fetchJson('/api/leads', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(data),
                    });
                    modal.style.display = 'none';
                    form.reset();
                    showToast('Lead created', 'success');
                    leads = await fetchJson('/api/leads');
                    renderBoard();
                    renderTable();
                } catch (err) {
                    showToast('Failed to create lead: ' + err.message, 'error');
                }
            });
        }
    }

    // -----------------------------------------------------------------------
    // Filter change handlers
    // -----------------------------------------------------------------------

    function initFilterHandlers() {
        bindFilter('filter-brand', 'brand_id');
        bindFilter('filter-sector', 'sector');
        bindFilter('filter-source', 'source');
        bindFilter('filter-stage', 'stage');

        var searchEl = document.getElementById('filter-search');
        if (searchEl) {
            searchEl.addEventListener('input', function (e) {
                filters.search = e.target.value;
                renderBoard();
                renderTable();
            });
        }
    }

    function bindFilter(id, key) {
        var filterEl = document.getElementById(id);
        if (filterEl) {
            filterEl.addEventListener('change', function (e) {
                filters[key] = e.target.value;
                renderBoard();
                renderTable();
            });
        }
    }

    // -----------------------------------------------------------------------
    // Init
    // -----------------------------------------------------------------------

    async function init() {
        try {
            var results = await Promise.all([
                fetchJson('/api/config'),
                fetchJson('/api/brands'),
            ]);
            config = results[0] || config;
            brands = results[1] || brands;

            leads = await fetchJson('/api/leads');

            populateFilters();
            initFilterHandlers();
            initModal();
            initTableSort();

            renderBoard();
            renderTable();
            renderSettings();
            initDetailPage();

        } catch (err) {
            console.error('Dashboard init failed:', err);
            showToast('Failed to load dashboard data: ' + err.message, 'error');
        }
    }

    document.addEventListener('DOMContentLoaded', init);

})();
