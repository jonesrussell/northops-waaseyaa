# Signal Crawler Hardening Design

**Date:** 2026-04-05
**Status:** Approved
**Repo:** jonesrussell/north-cloud
**Milestone:** Phase 3: Signal Crawlers
**Depends on:** PR #127 (Lead Intelligence Pipeline foundation, shipped)

## Purpose

Harden the existing signal-crawler service for production readiness. The service scaffolding shipped March 31 (10 commits, all tests passing). This spec covers bug fixes, convention alignment, funding adapter tuning, and production deployment.

## Scope

Three phases, each its own PR:

| Phase | Focus | Issues |
|-------|-------|--------|
| 1 | Bug fixes (data correctness) | #601, #602, #603 |
| 2 | Convention alignment + test quality | #604, #605 |
| 3 | Funding tuning + production deploy | #588, #589 |

Out of scope: job boards adapter (#590), new signal sources.

## Phase 1: Bug Fixes

### Funding adapter early return (#601)

**Problem:** `funding.go:44` returns on the first URL fetch error, aborting all remaining URLs.

**Fix:** Accumulate signals across all URLs. On per-URL error, log it and continue. Return accumulated signals plus a summary error via `errors.Join` if any URLs failed. The runner already handles partial failure gracefully.

```go
func (f *Adapter) Scan(ctx context.Context) ([]adapter.Signal, error) {
    var allSignals []adapter.Signal
    var errs []error
    for _, u := range f.urls {
        signals, err := f.fetchAndParse(ctx, u)
        if err != nil {
            f.log.Warn("funding: skipping URL", zap.String("url", u), zap.Error(err))
            errs = append(errs, fmt.Errorf("funding: %s: %w", u, err))
            continue
        }
        allSignals = append(allSignals, signals...)
    }
    return allSignals, errors.Join(errs...)
}
```

### Dedup key collision (#602)

**Problem:** `funding.go:103` constructs ExternalID as `url.QueryEscape(org) + "|" + url.QueryEscape(program)`. Empty `program` produces colliding keys.

**Fix:** Add `program != ""` check in `extractRow` alongside existing `org != ""` guard. Rows with empty program are skipped and logged at debug level.

### HN silent errors (#603)

**Problem:** `hn.go:67-69` silently discards per-item fetch errors with `continue`.

**Fix:** Add a `skipped` counter. Log each fetch error at debug level with item ID. After the scan loop, log a summary: `"hn: scanned %d items, matched %d, skipped %d (fetch errors)"`. No change to return signature.

## Phase 2: Convention Alignment

### Error wrapping (#604)

Add `fmt.Errorf("context: %w", err)` to all bare error returns in:
- `hn.go`: `fetchNewStories`, `fetchItem`
- `funding.go`: `fetchAndParse`

### Context propagation (#604)

Change dedup store interface:
```go
type Store interface {
    Seen(ctx context.Context, source, id string) (bool, error)
    Mark(ctx context.Context, source, id string) error
    Close() error
}
```

Implementation uses `QueryRowContext`/`ExecContext`. Update runner calls and all tests.

### Configuration expansion (#604)

Add to `config.go`:
```go
type HNConfig struct {
    MaxItems int    `yaml:"max_items"`  // default 200
    BaseURL  string `yaml:"base_url"`   // default HN Firebase URL
}

type FundingConfig struct {
    URLs []string `yaml:"urls"`  // default ["https://otf.ca/funded-grants"]
}
```

Remove hardcoded `fundingURLs` and `defaultHNMaxItems` from `main.go`. Add `SetDefaults` for both. Add `DBPath != ""` validation in `Validate()`.

### Signal handling (#604)

Replace `context.Background()` in `main.go`:
```go
ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
defer stop()
```

### Scoring constants (#605)

```go
const (
    ScoreDirectAsk    = 90
    ScoreStrongSignal = 70
    ScoreWeakSignal   = 40
)
```

### Test improvements (#605)

- Convert `runner_test.go` to `testify/assert`
- Add missing test cases: ingest failure, Mark failure, multi-source, HTTP errors in adapters, context cancellation
- Add `test:cover` task with `-coverprofile` and `-race` flags
- Create `.layers` file for layer boundary enforcement
- Include response body snippet (up to 512 bytes) in ingest error messages
- Remove dead code in `funding_test.go:51`

## Phase 3: Funding Tuning + Deploy

### Funding adapter tuning (#589)

- Capture real HTML from OTF and grants.gc.ca as test fixtures
- Fix CSS selectors and parsing logic to match actual markup
- Add per-source filtering: `task run:dry -- --source=funding`

### Production deploy (#588)

**Binary location:** `/opt/north-cloud/signal-crawler/signal-crawler`

**systemd timer:**
```ini
# signal-crawler.timer
[Unit]
Description=Signal crawler daily scan

[Timer]
OnCalendar=*-*-* 06:00:00 UTC
Persistent=true

[Install]
WantedBy=timers.target
```

**systemd service:**
```ini
# signal-crawler.service
[Unit]
Description=Signal crawler
After=network-online.target

[Service]
Type=oneshot
ExecStart=/opt/north-cloud/signal-crawler/signal-crawler
WorkingDirectory=/opt/north-cloud/signal-crawler
EnvironmentFile=/opt/north-cloud/signal-crawler/.env
StandardOutput=journal
StandardError=journal
```

**Environment:** `.env` with `NORTHOPS_URL` and `PIPELINE_API_KEY`

**Data:** SQLite dedup DB at `/opt/north-cloud/signal-crawler/data/seen.db`

**Monitoring:** Structured JSON to stdout, captured by journald. No HTTP health endpoint (cron job, not a daemon).

**Deploy validation:** First deploy uses `--dry-run` to verify connectivity. Then remove the flag.

## Issue Map

| Issue | Phase | Description |
|-------|-------|-------------|
| #601 | 1 | Funding adapter aborts on first URL error |
| #602 | 1 | Dedup key collision when program is empty |
| #603 | 1 | HN per-item fetch errors silently swallowed |
| #604 | 2 | Convention fixes (error wrapping, context, config, signal handling) |
| #605 | 2 | Test and quality improvements |
| #589 | 3 | Tune funding adapter for real HTML |
| #588 | 3 | Deploy to production with cron schedule |
| #590 | — | Job boards adapter (future, out of scope) |
