# Signal Crawler Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Harden the existing signal-crawler service for production readiness: fix 3 bugs, align with north-cloud conventions, and deploy.

**Architecture:** Existing Go service at `north-cloud/signal-crawler/` with adapter pattern (HN + funding). Three PRs: bug fixes, convention alignment, deploy. All work is in the `jonesrussell/north-cloud` repo.

**Tech Stack:** Go 1.26, SQLite (dedup), infrastructure/logger (Zap), infrastructure/config, testify, golangci-lint

**Spec:** `docs/superpowers/specs/2026-04-05-signal-crawler-hardening-design.md` (in northops-waaseyaa repo)

---

## PR 1: Bug Fixes (#601, #602, #603)

### Task 1: Fix funding adapter early return (#601)

**Files:**
- Modify: `signal-crawler/internal/adapter/funding/funding.go`
- Modify: `signal-crawler/internal/adapter/funding/funding_test.go`

- [ ] **Step 1: Write the failing test for partial URL failure**

Add to `signal-crawler/internal/adapter/funding/funding_test.go`:

```go
func TestFundingAdapter_PartialURLFailure(t *testing.T) {
	fixture, err := os.ReadFile("testdata/otf_grants.html")
	require.NoError(t, err)

	// First URL fails (404), second URL succeeds.
	callCount := 0
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		callCount++
		if callCount == 1 {
			w.WriteHeader(http.StatusNotFound)
			return
		}
		w.Header().Set("Content-Type", "text/html")
		w.Write(fixture)
	}))
	defer srv.Close()

	a := funding.New([]string{srv.URL + "/bad", srv.URL + "/good"})
	signals, err := a.Scan(context.Background())

	// Should return signals from the second URL despite the first failing.
	assert.Error(t, err, "should report the partial failure")
	assert.Len(t, signals, 2, "should still return signals from successful URLs")
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -run TestFundingAdapter_PartialURLFailure -v ./internal/adapter/funding/`

Expected: FAIL — current code returns `nil, error` on the first URL failure, so `signals` is nil.

- [ ] **Step 3: Implement the fix in funding.go**

Replace the `Scan` method (lines 38-50) in `signal-crawler/internal/adapter/funding/funding.go`:

```go
// Scan fetches each configured URL, parses grant rows, and returns signals.
// Continues on per-URL errors, returning partial results with a combined error.
func (a *Adapter) Scan(ctx context.Context) ([]adapter.Signal, error) {
	var allSignals []adapter.Signal
	var errs []error

	for _, rawURL := range a.urls {
		grants, err := a.fetchAndParse(ctx, rawURL)
		if err != nil {
			errs = append(errs, fmt.Errorf("funding adapter: fetch %s: %w", rawURL, err))
			continue
		}
		allSignals = append(allSignals, grants...)
	}

	return allSignals, errors.Join(errs...)
}
```

Add `"errors"` to the import block.

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -run TestFundingAdapter -v ./internal/adapter/funding/`

Expected: All funding tests PASS.

- [ ] **Step 5: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/adapter/funding/funding.go internal/adapter/funding/funding_test.go
git commit -m "fix(signal-crawler): funding adapter continues on URL errors (#601)"
```

---

### Task 2: Fix dedup key collision for empty program (#602)

**Files:**
- Modify: `signal-crawler/internal/adapter/funding/funding.go`
- Modify: `signal-crawler/internal/adapter/funding/funding_test.go`
- Create: `signal-crawler/internal/adapter/funding/testdata/otf_grants_empty_program.html`

- [ ] **Step 1: Create test fixture with empty program**

Create `signal-crawler/internal/adapter/funding/testdata/otf_grants_empty_program.html`:

```html
<html><body>
<div class="view-content">
  <div class="views-row">
    <span class="views-field-title"><a href="/funded-grants/200">OrgWithProgram</a></span>
    <span class="views-field-field-grant-program">Innovation Grant</span>
    <span class="views-field-field-amount">$100,000</span>
    <span class="views-field-field-org-type">Startup</span>
  </div>
  <div class="views-row">
    <span class="views-field-title"><a href="/funded-grants/201">OrgNoProgram</a></span>
    <span class="views-field-field-grant-program"></span>
    <span class="views-field-field-amount">$50,000</span>
    <span class="views-field-field-org-type">Nonprofit</span>
  </div>
</div>
</body></html>
```

- [ ] **Step 2: Write the failing test**

Add to `signal-crawler/internal/adapter/funding/funding_test.go`:

```go
func TestFundingAdapter_SkipsEmptyProgram(t *testing.T) {
	fixture, err := os.ReadFile("testdata/otf_grants_empty_program.html")
	require.NoError(t, err)

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/html")
		w.Write(fixture)
	}))
	defer srv.Close()

	a := funding.New([]string{srv.URL})
	signals, err := a.Scan(context.Background())
	require.NoError(t, err)

	// Only the row with a non-empty program should be returned.
	require.Len(t, signals, 1)
	assert.Contains(t, signals[0].Label, "OrgWithProgram")
	assert.Contains(t, signals[0].Label, "Innovation Grant")
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -run TestFundingAdapter_SkipsEmptyProgram -v ./internal/adapter/funding/`

Expected: FAIL — current code returns 2 signals because `extractRow` only checks `org != ""`.

- [ ] **Step 4: Fix extractRow guard in funding.go**

In `signal-crawler/internal/adapter/funding/funding.go`, change line 128 in `parseGrantRows`:

```go
			row := extractRow(n)
			if row.org != "" && row.program != "" {
				rows = append(rows, row)
			}
```

- [ ] **Step 5: Run tests to verify all pass**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./internal/adapter/funding/`

Expected: All funding tests PASS.

- [ ] **Step 6: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/adapter/funding/funding.go internal/adapter/funding/funding_test.go internal/adapter/funding/testdata/otf_grants_empty_program.html
git commit -m "fix(signal-crawler): skip funding rows with empty program to prevent dedup collision (#602)"
```

---

### Task 3: Fix HN silent error swallowing (#603)

**Files:**
- Modify: `signal-crawler/internal/adapter/hn/hn.go`
- Modify: `signal-crawler/internal/adapter/hn/hn_test.go`

- [ ] **Step 1: Add logger to HN adapter**

The HN adapter currently has no logger. Add one. In `signal-crawler/internal/adapter/hn/hn.go`, update the struct and constructor:

```go
import (
	"context"
	"encoding/json"
	"fmt"
	"net/http"
	"strconv"
	"time"

	infralogger "github.com/jonesrussell/north-cloud/infrastructure/logger"
	"github.com/jonesrussell/north-cloud/signal-crawler/internal/adapter"
	"github.com/jonesrussell/north-cloud/signal-crawler/internal/scoring"
)

// Adapter fetches founder intent signals from Hacker News via the Firebase API.
type Adapter struct {
	baseURL    string
	maxItems   int
	httpClient *http.Client
	log        infralogger.Logger
}

// New creates a new HN Adapter. If baseURL is empty, the production Firebase URL is used.
func New(baseURL string, maxItems int, log infralogger.Logger) *Adapter {
	if baseURL == "" {
		baseURL = defaultBaseURL
	}
	return &Adapter{
		baseURL:    baseURL,
		maxItems:   maxItems,
		httpClient: &http.Client{Timeout: defaultHTTPTimeout},
		log:        log,
	}
}
```

- [ ] **Step 2: Add skip counter and logging to Scan**

Replace the `Scan` method (lines 53-87) in `signal-crawler/internal/adapter/hn/hn.go`:

```go
// Scan fetches recent HN stories and returns those that match founder intent signals.
func (a *Adapter) Scan(ctx context.Context) ([]adapter.Signal, error) {
	ids, err := a.fetchNewStories(ctx)
	if err != nil {
		return nil, fmt.Errorf("hn: fetch new stories: %w", err)
	}

	if len(ids) > a.maxItems {
		ids = ids[:a.maxItems]
	}

	var signals []adapter.Signal
	skipped := 0

	for _, id := range ids {
		it, err := a.fetchItem(ctx, id)
		if err != nil {
			a.log.Debug("hn: skipping item",
				infralogger.Int("item_id", id),
				infralogger.Error(err),
			)
			skipped++
			continue
		}

		combined := it.Title + " " + it.Text
		score, matched := scoring.Score(combined)
		if score == 0 {
			continue
		}

		signals = append(signals, adapter.Signal{
			Label:          it.Title,
			SourceURL:      fmt.Sprintf("https://news.ycombinator.com/item?id=%d", it.ID),
			ExternalID:     strconv.Itoa(it.ID),
			SignalStrength: score,
			Notes:          "Matched: " + matched,
		})
	}

	a.log.Info("hn: scan complete",
		infralogger.Int("total", len(ids)),
		infralogger.Int("matched", len(signals)),
		infralogger.Int("skipped", skipped),
	)

	return signals, nil
}
```

- [ ] **Step 3: Update all HN adapter call sites**

In `signal-crawler/main.go`, update line 77:

```go
	sources := []adapter.Source{
		hn.New("", defaultHNMaxItems, log),
		funding.New(fundingURLs),
	}
```

In `signal-crawler/internal/adapter/hn/hn_test.go`, update all `hn.New` calls to include the logger:

```go
// In TestHNAdapter_Name (line 17):
	a := hn.New("", 10, infralogger.NewNop())

// In TestHNAdapter_Scan (line 45):
	a := hn.New(srv.URL, 10, infralogger.NewNop())

// In TestHNAdapter_EmptyList (line 65):
	a := hn.New(srv.URL, 10, infralogger.NewNop())
```

Add `infralogger "github.com/jonesrussell/north-cloud/infrastructure/logger"` to the test imports.

- [ ] **Step 4: Write the test for fetch error logging**

Add to `signal-crawler/internal/adapter/hn/hn_test.go`:

```go
func TestHNAdapter_SkipsFailedItems(t *testing.T) {
	withSignal, err := os.ReadFile(filepath.Join("testdata", "item_with_signal.json"))
	require.NoError(t, err)

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		switch r.URL.Path {
		case "/v0/newstories.json":
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write([]byte(`[99001,99002]`))
		case "/v0/item/99001.json":
			// This item returns a 500 error.
			w.WriteHeader(http.StatusInternalServerError)
		case "/v0/item/99002.json":
			w.Header().Set("Content-Type", "application/json")
			_, _ = w.Write(withSignal)
		default:
			http.NotFound(w, r)
		}
	}))
	defer srv.Close()

	a := hn.New(srv.URL, 10, infralogger.NewNop())
	signals, err := a.Scan(context.Background())
	require.NoError(t, err, "scan should succeed despite individual item failures")

	// Item 99001 failed but 99002 (with signal) should still be returned.
	// Note: withSignal fixture has id 99001, but here 99002 serves it.
	// The signal uses the item's own ID field, not the requested ID.
	assert.NotEmpty(t, signals, "should return signals from successful items")
}
```

- [ ] **Step 5: Run all tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./internal/adapter/hn/ && go test -v ./...`

Expected: All PASS.

- [ ] **Step 6: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/adapter/hn/hn.go internal/adapter/hn/hn_test.go main.go
git commit -m "fix(signal-crawler): log HN per-item fetch errors with skip counter (#603)"
```

---

### Task 4: Run full test suite and create PR 1

**Files:** None (verification only)

- [ ] **Step 1: Run full test suite with race detector**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -race -v ./...`

Expected: All PASS.

- [ ] **Step 2: Run linter**

Run: `cd ~/dev/north-cloud/signal-crawler && task lint`

Expected: Clean.

- [ ] **Step 3: Create PR**

```bash
cd ~/dev/north-cloud
git push -u origin HEAD
gh pr create --title "fix(signal-crawler): data correctness bugs" --body "$(cat <<'EOF'
## Summary

- Funding adapter continues on per-URL errors instead of aborting (#601)
- Skip funding rows with empty program to prevent dedup key collision (#602)
- Log HN per-item fetch errors with skip counter instead of silent continue (#603)

## Test plan

- [ ] `task test` passes with race detector
- [ ] `task lint` clean
- [ ] `task run:dry` produces expected output

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## PR 2: Convention Alignment (#604, #605)

### Task 5: Add context.Context to dedup store (#604)

**Files:**
- Modify: `signal-crawler/internal/dedup/dedup.go`
- Modify: `signal-crawler/internal/dedup/dedup_test.go`
- Modify: `signal-crawler/internal/runner/runner.go`
- Modify: `signal-crawler/internal/runner/runner_test.go`

- [ ] **Step 1: Update dedup tests to pass context**

In `signal-crawler/internal/dedup/dedup_test.go`, add `"context"` to imports and update every `Seen` and `Mark` call to include `context.Background()`:

```go
package dedup_test

import (
	"context"
	"testing"

	"github.com/jonesrussell/north-cloud/signal-crawler/internal/dedup"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

func TestStore_NewItem(t *testing.T) {
	store, err := dedup.New(":memory:")
	require.NoError(t, err)
	defer store.Close()

	seen, err := store.Seen(context.Background(), "hn", "12345")
	require.NoError(t, err)
	assert.False(t, seen, "new item should not be seen")
}

func TestStore_MarkAndCheck(t *testing.T) {
	store, err := dedup.New(":memory:")
	require.NoError(t, err)
	defer store.Close()

	err = store.Mark(context.Background(), "hn", "12345")
	require.NoError(t, err)

	seen, err := store.Seen(context.Background(), "hn", "12345")
	require.NoError(t, err)
	assert.True(t, seen, "marked item should be seen")
}

func TestStore_DifferentSources(t *testing.T) {
	store, err := dedup.New(":memory:")
	require.NoError(t, err)
	defer store.Close()

	err = store.Mark(context.Background(), "hn", "12345")
	require.NoError(t, err)

	seen, err := store.Seen(context.Background(), "funding", "12345")
	require.NoError(t, err)
	assert.False(t, seen, "same id from different source should not be seen")
}

func TestStore_MarkIdempotent(t *testing.T) {
	store, err := dedup.New(":memory:")
	require.NoError(t, err)
	defer store.Close()

	err = store.Mark(context.Background(), "hn", "12345")
	require.NoError(t, err)

	err = store.Mark(context.Background(), "hn", "12345")
	require.NoError(t, err, "marking same item twice should not error")
}
```

- [ ] **Step 2: Run dedup tests to verify they fail**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./internal/dedup/`

Expected: FAIL — `Seen` and `Mark` don't accept context yet.

- [ ] **Step 3: Update dedup.go to accept context**

Replace `signal-crawler/internal/dedup/dedup.go`:

```go
package dedup

import (
	"context"
	"database/sql"
	"fmt"

	_ "github.com/mattn/go-sqlite3"
)

// Store tracks which signals have already been ingested.
type Store struct {
	db *sql.DB
}

// New opens (or creates) a SQLite dedup database. Use ":memory:" for testing.
func New(dbPath string) (*Store, error) {
	db, err := sql.Open("sqlite3", dbPath)
	if err != nil {
		return nil, fmt.Errorf("open dedup db: %w", err)
	}

	_, err = db.Exec(`CREATE TABLE IF NOT EXISTS seen (
		source      TEXT     NOT NULL,
		external_id TEXT     NOT NULL,
		first_seen  DATETIME NOT NULL DEFAULT (datetime('now')),
		PRIMARY KEY (source, external_id)
	)`)
	if err != nil {
		db.Close()
		return nil, fmt.Errorf("create seen table: %w", err)
	}

	return &Store{db: db}, nil
}

// Seen checks whether a signal has already been ingested.
func (s *Store) Seen(ctx context.Context, source, externalID string) (bool, error) {
	var count int
	err := s.db.QueryRowContext(ctx,
		`SELECT COUNT(*) FROM seen WHERE source = ? AND external_id = ?`,
		source, externalID,
	).Scan(&count)
	if err != nil {
		return false, fmt.Errorf("query seen: %w", err)
	}
	return count > 0, nil
}

// Mark records a signal as ingested.
func (s *Store) Mark(ctx context.Context, source, externalID string) error {
	_, err := s.db.ExecContext(ctx,
		`INSERT OR IGNORE INTO seen (source, external_id) VALUES (?, ?)`,
		source, externalID,
	)
	if err != nil {
		return fmt.Errorf("mark seen: %w", err)
	}
	return nil
}

// Close closes the database connection.
func (s *Store) Close() error {
	return s.db.Close()
}
```

- [ ] **Step 4: Update runner Dedup interface**

In `signal-crawler/internal/runner/runner.go`, update the `Dedup` interface (lines 11-14):

```go
// Dedup is the interface for the deduplication store.
type Dedup interface {
	Seen(ctx context.Context, source, externalID string) (bool, error)
	Mark(ctx context.Context, source, externalID string) error
}
```

Add `"context"` to the import block (it's already there).

Update the `Run` method calls at lines 68 and 87:

```go
			seen, err := r.dedup.Seen(ctx, src.Name(), sig.ExternalID)
```

```go
				if err := r.dedup.Mark(ctx, src.Name(), sig.ExternalID); err != nil {
```

- [ ] **Step 5: Update runner test fakes**

In `signal-crawler/internal/runner/runner_test.go`, update fakeDedup methods:

```go
func (f *fakeDedup) Seen(_ context.Context, source, externalID string) (bool, error) {
	return f.seen[source+":"+externalID], nil
}

func (f *fakeDedup) Mark(_ context.Context, source, externalID string) error {
	f.seen[source+":"+externalID] = true
	return nil
}
```

Add `"context"` to the test imports if not already present.

- [ ] **Step 6: Run all tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./...`

Expected: All PASS.

- [ ] **Step 7: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/dedup/dedup.go internal/dedup/dedup_test.go internal/runner/runner.go internal/runner/runner_test.go
git commit -m "refactor(signal-crawler): add context.Context to dedup Seen/Mark (#604)"
```

---

### Task 6: Error wrapping in adapter helpers (#604)

**Files:**
- Modify: `signal-crawler/internal/adapter/hn/hn.go`
- Modify: `signal-crawler/internal/adapter/funding/funding.go`

- [ ] **Step 1: Wrap errors in hn.go helpers**

In `signal-crawler/internal/adapter/hn/hn.go`, update `fetchNewStories`:

Line 93 (request creation error): `return nil, fmt.Errorf("hn: create stories request: %w", err)`
Line 98 (HTTP do error): `return nil, fmt.Errorf("hn: fetch stories: %w", err)`
Line 108 (decode error): `return nil, fmt.Errorf("hn: decode stories: %w", err)`

Update `fetchItem`:

Line 116 (request creation): `return nil, fmt.Errorf("hn: create item request: %w", err)`
Line 121 (HTTP do): `return nil, fmt.Errorf("hn: fetch item %d: %w", id, err)`
Line 131 (decode): `return nil, fmt.Errorf("hn: decode item %d: %w", id, err)`

- [ ] **Step 2: Wrap errors in funding.go helpers**

In `signal-crawler/internal/adapter/funding/funding.go`, update `fetchAndParse`:

Line 63 (request creation): `return nil, fmt.Errorf("funding: create request: %w", err)`
Line 68 (HTTP do): `return nil, fmt.Errorf("funding: fetch %s: %w", rawURL, err)`
Line 78 (read body): `return nil, fmt.Errorf("funding: read body: %w", err)`
Line 83 (parse rows): `return nil, fmt.Errorf("funding: parse grants: %w", err)`
Line 88 (parse base URL): `return nil, fmt.Errorf("funding: parse base URL: %w", err)`

- [ ] **Step 3: Run all tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./...`

Expected: All PASS (error wrapping is additive, doesn't change behavior).

- [ ] **Step 4: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/adapter/hn/hn.go internal/adapter/funding/funding.go
git commit -m "refactor(signal-crawler): wrap all errors in adapter helpers (#604)"
```

---

### Task 7: Config expansion — HN and funding settings (#604)

**Files:**
- Modify: `signal-crawler/internal/config/config.go`
- Modify: `signal-crawler/internal/config/config_test.go`
- Modify: `signal-crawler/main.go`
- Modify: `signal-crawler/config.yml`

- [ ] **Step 1: Write config validation test for empty DBPath**

Add to `signal-crawler/internal/config/config_test.go`:

```go
func TestConfig_Validate_EmptyDBPath(t *testing.T) {
	cfg := &config.Config{
		NorthOps: config.NorthOpsConfig{URL: "https://northops.ca", APIKey: "key"},
		Dedup:    config.DedupConfig{DBPath: ""},
	}
	config.SetDefaults(cfg)
	// After SetDefaults, DBPath should be "data/seen.db", so validation passes.
	err := cfg.Validate()
	assert.NoError(t, err)

	// But if someone explicitly sets it to empty after defaults:
	cfg.Dedup.DBPath = ""
	err = cfg.Validate()
	assert.Error(t, err)
	assert.Contains(t, err.Error(), "db_path")
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -run TestConfig_Validate_EmptyDBPath -v ./internal/config/`

Expected: FAIL — `Validate()` doesn't check `DBPath`.

- [ ] **Step 3: Add HN/Funding config structs and validation**

Replace `signal-crawler/internal/config/config.go`:

```go
package config

import (
	"fmt"

	infraconfig "github.com/jonesrussell/north-cloud/infrastructure/config"
)

// NorthOpsConfig holds connection configuration for the NorthOps ingest API.
type NorthOpsConfig struct {
	URL    string `env:"NORTHOPS_URL" yaml:"url"`
	APIKey string `env:"PIPELINE_API_KEY" yaml:"api_key"`
}

// DedupConfig holds deduplication store configuration.
type DedupConfig struct {
	DBPath string `env:"SIGNAL_DB_PATH" yaml:"db_path"`
}

// LoggingConfig holds logging configuration.
type LoggingConfig struct {
	Level  string `env:"LOG_LEVEL"  yaml:"level"`
	Format string `env:"LOG_FORMAT" yaml:"format"`
}

// HNConfig holds Hacker News adapter configuration.
type HNConfig struct {
	MaxItems int    `env:"HN_MAX_ITEMS" yaml:"max_items"`
	BaseURL  string `env:"HN_BASE_URL"  yaml:"base_url"`
}

// FundingConfig holds funding adapter configuration.
type FundingConfig struct {
	URLs []string `yaml:"urls"`
}

// Config is the top-level configuration for signal-crawler.
type Config struct {
	NorthOps NorthOpsConfig `yaml:"northops"`
	Dedup    DedupConfig    `yaml:"dedup"`
	Logging  LoggingConfig  `yaml:"logging"`
	HN       HNConfig       `yaml:"hn"`
	Funding  FundingConfig  `yaml:"funding"`
}

// Validate checks that all required fields are present.
func (c *Config) Validate() error {
	if c.NorthOps.URL == "" {
		return fmt.Errorf("northops_url is required")
	}
	if c.NorthOps.APIKey == "" {
		return fmt.Errorf("api_key is required")
	}
	if c.Dedup.DBPath == "" {
		return fmt.Errorf("db_path is required")
	}
	return nil
}

// SetDefaults fills in default values for optional fields.
func SetDefaults(cfg *Config) {
	if cfg.Dedup.DBPath == "" {
		cfg.Dedup.DBPath = "data/seen.db"
	}
	if cfg.Logging.Level == "" {
		cfg.Logging.Level = "info"
	}
	if cfg.Logging.Format == "" {
		cfg.Logging.Format = "json"
	}
	if cfg.HN.MaxItems == 0 {
		cfg.HN.MaxItems = 200
	}
	if cfg.HN.BaseURL == "" {
		cfg.HN.BaseURL = "https://hacker-news.firebaseio.com"
	}
	if len(cfg.Funding.URLs) == 0 {
		cfg.Funding.URLs = []string{"https://otf.ca/funded-grants"}
	}
}

// Load reads config from path, applies defaults, then applies env overrides.
func Load(path string) (*Config, error) {
	cfg, err := infraconfig.LoadWithDefaults[Config](path, SetDefaults)
	if err != nil {
		return nil, fmt.Errorf("load config: %w", err)
	}
	return cfg, nil
}
```

- [ ] **Step 4: Update main.go to use config values**

Remove the hardcoded constants and var at the top of `signal-crawler/main.go` (lines 21-25):

```go
// Remove these lines:
// const defaultHNMaxItems = 200
// var fundingURLs = []string{
//     "https://otf.ca/funded-grants",
// }
```

Update the sources creation (line 76-79):

```go
	sources := []adapter.Source{
		hn.New(cfg.HN.BaseURL, cfg.HN.MaxItems, log),
		funding.New(cfg.Funding.URLs),
	}
```

- [ ] **Step 5: Update config.yml**

Add the new sections to `signal-crawler/config.yml`:

```yaml
# Hacker News adapter
hn:
  max_items: 200        # Max stories to scan per run (env: HN_MAX_ITEMS)
  base_url: ""          # Leave empty for production Firebase URL (env: HN_BASE_URL)

# Funding adapter
funding:
  urls:                 # Grant portal URLs to scrape
    - "https://otf.ca/funded-grants"
```

- [ ] **Step 6: Run all tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./...`

Expected: All PASS.

- [ ] **Step 7: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/config/config.go internal/config/config_test.go main.go config.yml
git commit -m "refactor(signal-crawler): move hardcoded values to config (#604)"
```

---

### Task 8: Signal handling for graceful shutdown (#604)

**Files:**
- Modify: `signal-crawler/main.go`

- [ ] **Step 1: Add signal handling to main.go**

Add `"os/signal"` and `"syscall"` to imports in `signal-crawler/main.go`.

Replace line 88:

```go
	// Before:
	// stats := r.Run(context.Background())

	// After:
	ctx, stop := signal.NotifyContext(context.Background(), syscall.SIGINT, syscall.SIGTERM)
	defer stop()

	stats := r.Run(ctx)
```

- [ ] **Step 2: Update dedup close to use explicit discard**

Replace line 72:

```go
	// Before:
	// defer dedupStore.Close()

	// After:
	defer func() { _ = dedupStore.Close() }()
```

- [ ] **Step 3: Run all tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./...`

Expected: All PASS.

- [ ] **Step 4: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add main.go
git commit -m "refactor(signal-crawler): add signal handling for graceful shutdown (#604)"
```

---

### Task 9: Scoring constants and ingest error body (#605)

**Files:**
- Modify: `signal-crawler/internal/scoring/scoring.go`
- Modify: `signal-crawler/internal/ingest/client.go`
- Modify: `signal-crawler/internal/ingest/client_test.go`

- [ ] **Step 1: Add named scoring constants**

In `signal-crawler/internal/scoring/scoring.go`, add constants and update the keywords slice:

```go
package scoring

import "strings"

const (
	// ScoreDirectAsk is for posts explicitly looking for technical help.
	ScoreDirectAsk = 90
	// ScoreStrongSignal is for posts indicating active technical need.
	ScoreStrongSignal = 70
	// ScoreWeakSignal is for posts hinting at future technical need.
	ScoreWeakSignal = 40
)

type keyword struct {
	phrase string
	score  int
}

var keywords = []keyword{
	// Direct ask
	{phrase: "looking for cto", score: ScoreDirectAsk},
	{phrase: "looking for a cto", score: ScoreDirectAsk},
	{phrase: "need developer", score: ScoreDirectAsk},
	{phrase: "need a developer", score: ScoreDirectAsk},
	{phrase: "need an engineer", score: ScoreDirectAsk},
	{phrase: "hiring first engineer", score: ScoreDirectAsk},
	{phrase: "hiring our first", score: ScoreDirectAsk},
	{phrase: "technical co-founder", score: ScoreDirectAsk},

	// Strong signal
	{phrase: "rebuild mvp", score: ScoreStrongSignal},
	{phrase: "rewriting our stack", score: ScoreStrongSignal},
	{phrase: "migrating to cloud", score: ScoreStrongSignal},
	{phrase: "scaling infrastructure", score: ScoreStrongSignal},
	{phrase: "rewrite from scratch", score: ScoreStrongSignal},
	{phrase: "modernize our", score: ScoreStrongSignal},
	{phrase: "platform migration", score: ScoreStrongSignal},
	{phrase: "moving to microservices", score: ScoreStrongSignal},

	// Weak signal
	{phrase: "considering rewrite", score: ScoreWeakSignal},
	{phrase: "evaluating platforms", score: ScoreWeakSignal},
	{phrase: "tech debt", score: ScoreWeakSignal},
	{phrase: "technical debt", score: ScoreWeakSignal},
	{phrase: "legacy system", score: ScoreWeakSignal},
	{phrase: "need to modernize", score: ScoreWeakSignal},
}
```

Also use the constant in `signal-crawler/internal/adapter/funding/funding.go` line 105:

```go
	SignalStrength: scoring.ScoreStrongSignal,
```

Add `"github.com/jonesrussell/north-cloud/signal-crawler/internal/scoring"` to funding.go imports.

- [ ] **Step 2: Add response body to ingest error**

In `signal-crawler/internal/ingest/client.go`, add `"io"` to imports and update the error status check (lines 56-58):

```go
	if resp.StatusCode >= 300 {
		body, _ := io.ReadAll(io.LimitReader(resp.Body, 512))
		return fmt.Errorf("ingest: unexpected status %d: %s", resp.StatusCode, string(body))
	}
```

- [ ] **Step 3: Update ingest error test**

In `signal-crawler/internal/ingest/client_test.go`, update `TestClient_ServerError`:

```go
func TestClient_ServerError(t *testing.T) {
	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.WriteHeader(http.StatusInternalServerError)
		w.Write([]byte(`{"error":"internal failure"}`))
	}))
	defer srv.Close()

	client := ingest.New(srv.URL, "test-api-key")
	sig := adapter.Signal{
		Label:     "Some Signal",
		SourceURL: "https://example.com/789",
	}

	err := client.Post(context.Background(), sig)
	require.Error(t, err)
	assert.Contains(t, err.Error(), "500")
	assert.Contains(t, err.Error(), "internal failure")
}
```

- [ ] **Step 4: Run all tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./...`

Expected: All PASS.

- [ ] **Step 5: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/scoring/scoring.go internal/ingest/client.go internal/ingest/client_test.go internal/adapter/funding/funding.go
git commit -m "refactor(signal-crawler): named scoring constants and ingest error body (#605)"
```

---

### Task 10: Runner test improvements (#605)

**Files:**
- Modify: `signal-crawler/internal/runner/runner_test.go`

- [ ] **Step 1: Convert existing tests to testify and add missing cases**

Replace `signal-crawler/internal/runner/runner_test.go`:

```go
package runner_test

import (
	"context"
	"errors"
	"testing"

	infralogger "github.com/jonesrussell/north-cloud/infrastructure/logger"
	"github.com/jonesrussell/north-cloud/signal-crawler/internal/adapter"
	"github.com/jonesrussell/north-cloud/signal-crawler/internal/runner"
	"github.com/stretchr/testify/assert"
	"github.com/stretchr/testify/require"
)

// fakeSource returns canned signals.
type fakeSource struct {
	name    string
	signals []adapter.Signal
	err     error
}

func (f *fakeSource) Name() string { return f.name }
func (f *fakeSource) Scan(_ context.Context) ([]adapter.Signal, error) {
	return f.signals, f.err
}

// fakeDedup is a map-backed Seen/Mark implementation.
type fakeDedup struct {
	seen    map[string]bool
	seenErr error
	markErr error
}

func newFakeDedup(preseeded ...string) *fakeDedup {
	d := &fakeDedup{seen: make(map[string]bool)}
	for _, k := range preseeded {
		d.seen[k] = true
	}
	return d
}

func (f *fakeDedup) Seen(_ context.Context, source, externalID string) (bool, error) {
	if f.seenErr != nil {
		return false, f.seenErr
	}
	return f.seen[source+":"+externalID], nil
}

func (f *fakeDedup) Mark(_ context.Context, source, externalID string) error {
	if f.markErr != nil {
		return f.markErr
	}
	f.seen[source+":"+externalID] = true
	return nil
}

func (f *fakeDedup) WasSeen(source, externalID string) bool {
	return f.seen[source+":"+externalID]
}

// fakeIngest records posted signals.
type fakeIngest struct {
	posted []adapter.Signal
	err    error
}

func (f *fakeIngest) Post(_ context.Context, sig adapter.Signal) error {
	if f.err != nil {
		return f.err
	}
	f.posted = append(f.posted, sig)
	return nil
}

func TestRunner_ProcessesSignals(t *testing.T) {
	signals := []adapter.Signal{
		{Label: "Signal A", ExternalID: "ext-1"},
		{Label: "Signal B", ExternalID: "ext-2"},
	}
	src := &fakeSource{name: "test-source", signals: signals}
	dd := newFakeDedup()
	ing := &fakeIngest{}

	r := runner.New([]adapter.Source{src}, dd, ing, false, infralogger.NewNop())
	stats := r.Run(context.Background())

	require.Len(t, stats, 1)
	s := stats[0]
	assert.Equal(t, 2, s.Scanned)
	assert.Equal(t, 2, s.Ingested)
	assert.Equal(t, 0, s.Skipped)
	assert.Equal(t, 0, s.Errors)
	assert.Len(t, ing.posted, 2)
	assert.True(t, dd.WasSeen("test-source", "ext-1"))
	assert.True(t, dd.WasSeen("test-source", "ext-2"))
}

func TestRunner_SkipsSeen(t *testing.T) {
	signals := []adapter.Signal{
		{Label: "Old Signal", ExternalID: "ext-old"},
		{Label: "New Signal", ExternalID: "ext-new"},
	}
	src := &fakeSource{name: "test-source", signals: signals}
	dd := newFakeDedup("test-source:ext-old")
	ing := &fakeIngest{}

	r := runner.New([]adapter.Source{src}, dd, ing, false, infralogger.NewNop())
	stats := r.Run(context.Background())

	require.Len(t, stats, 1)
	s := stats[0]
	assert.Equal(t, 2, s.Scanned)
	assert.Equal(t, 1, s.Ingested)
	assert.Equal(t, 1, s.Skipped)
	require.Len(t, ing.posted, 1)
	assert.Equal(t, "ext-new", ing.posted[0].ExternalID)
}

func TestRunner_DryRun(t *testing.T) {
	signals := []adapter.Signal{
		{Label: "Signal A", ExternalID: "ext-1"},
		{Label: "Signal B", ExternalID: "ext-2"},
	}
	src := &fakeSource{name: "test-source", signals: signals}
	dd := newFakeDedup()
	ing := &fakeIngest{}

	r := runner.New([]adapter.Source{src}, dd, ing, true, infralogger.NewNop())
	stats := r.Run(context.Background())

	require.Len(t, stats, 1)
	s := stats[0]
	assert.Equal(t, 2, s.Ingested, "dry-run should count as ingested")
	assert.Empty(t, ing.posted, "dry-run should not POST")
	assert.False(t, dd.WasSeen("test-source", "ext-1"), "dry-run should not mark")
	assert.False(t, dd.WasSeen("test-source", "ext-2"), "dry-run should not mark")
}

func TestRunner_ScanError(t *testing.T) {
	src := &fakeSource{name: "bad-source", err: errors.New("network error")}
	dd := newFakeDedup()
	ing := &fakeIngest{}

	r := runner.New([]adapter.Source{src}, dd, ing, false, infralogger.NewNop())
	stats := r.Run(context.Background())

	require.Len(t, stats, 1)
	s := stats[0]
	assert.Equal(t, 1, s.Errors)
	assert.Equal(t, 0, s.Scanned)
}

func TestRunner_IngestError(t *testing.T) {
	signals := []adapter.Signal{
		{Label: "Signal A", ExternalID: "ext-1"},
		{Label: "Signal B", ExternalID: "ext-2"},
	}
	src := &fakeSource{name: "test-source", signals: signals}
	dd := newFakeDedup()
	ing := &fakeIngest{err: errors.New("server down")}

	r := runner.New([]adapter.Source{src}, dd, ing, false, infralogger.NewNop())
	stats := r.Run(context.Background())

	require.Len(t, stats, 1)
	s := stats[0]
	assert.Equal(t, 2, s.Scanned)
	assert.Equal(t, 0, s.Ingested, "failed ingests should not count as ingested")
	assert.Equal(t, 2, s.Errors)
	assert.False(t, dd.WasSeen("test-source", "ext-1"), "failed ingest should not mark")
}

func TestRunner_MarkError(t *testing.T) {
	signals := []adapter.Signal{
		{Label: "Signal A", ExternalID: "ext-1"},
	}
	src := &fakeSource{name: "test-source", signals: signals}
	dd := newFakeDedup()
	dd.markErr = errors.New("disk full")
	ing := &fakeIngest{}

	r := runner.New([]adapter.Source{src}, dd, ing, false, infralogger.NewNop())
	stats := r.Run(context.Background())

	require.Len(t, stats, 1)
	s := stats[0]
	assert.Equal(t, 1, s.Errors)
	assert.Equal(t, 0, s.Ingested, "mark failure should not count as ingested")
	assert.Len(t, ing.posted, 1, "signal was posted before mark failed")
}

func TestRunner_MultipleSources(t *testing.T) {
	src1 := &fakeSource{name: "hn", signals: []adapter.Signal{
		{Label: "HN Signal", ExternalID: "hn-1"},
	}}
	src2 := &fakeSource{name: "funding", signals: []adapter.Signal{
		{Label: "Grant Signal", ExternalID: "fund-1"},
		{Label: "Grant Signal 2", ExternalID: "fund-2"},
	}}
	dd := newFakeDedup()
	ing := &fakeIngest{}

	r := runner.New([]adapter.Source{src1, src2}, dd, ing, false, infralogger.NewNop())
	stats := r.Run(context.Background())

	require.Len(t, stats, 2)
	assert.Equal(t, "hn", stats[0].Source)
	assert.Equal(t, 1, stats[0].Ingested)
	assert.Equal(t, "funding", stats[1].Source)
	assert.Equal(t, 2, stats[1].Ingested)
	assert.Len(t, ing.posted, 3)
}
```

- [ ] **Step 2: Run tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./internal/runner/`

Expected: All PASS.

- [ ] **Step 3: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/runner/runner_test.go
git commit -m "test(signal-crawler): convert runner tests to testify, add missing cases (#605)"
```

---

### Task 11: Taskfile improvements and .layers file (#605)

**Files:**
- Modify: `signal-crawler/Taskfile.yml`
- Create: `signal-crawler/.layers`
- Modify: `signal-crawler/internal/adapter/funding/funding_test.go` (dead code cleanup)

- [ ] **Step 1: Add test:cover task to Taskfile.yml**

Add after the `test` task in `signal-crawler/Taskfile.yml`:

```yaml
  test:cover:
    desc: "Run tests with coverage and race detector"
    cmds:
      - go test -race -coverprofile=coverage.out -count=1 ./...
      - go tool cover -func=coverage.out
```

- [ ] **Step 2: Create .layers file**

Create `signal-crawler/.layers`:

```
# Layer boundaries for signal-crawler
# Dependencies flow downward only.

adapter < scoring
adapter < runner
config < adapter
config < runner
dedup < runner
ingest < runner
scoring < adapter
```

- [ ] **Step 3: Remove dead code in funding_test.go**

In `signal-crawler/internal/adapter/funding/funding_test.go`, fix line 51:

```go
// Before:
		_, _ = strings.NewReader(html), w
		w.Write([]byte(html))

// After:
		w.Write([]byte(html))
```

Remove `"strings"` from the import block if no longer used.

- [ ] **Step 4: Run all tests with coverage**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -race -count=1 -v ./...`

Expected: All PASS.

- [ ] **Step 5: Run linter**

Run: `cd ~/dev/north-cloud/signal-crawler && task lint`

Expected: Clean.

- [ ] **Step 6: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add Taskfile.yml .layers internal/adapter/funding/funding_test.go
git commit -m "chore(signal-crawler): add test:cover, .layers, remove dead test code (#605)"
```

---

### Task 12: Create PR 2

- [ ] **Step 1: Push and create PR**

```bash
cd ~/dev/north-cloud
git push -u origin HEAD
gh pr create --title "refactor(signal-crawler): convention alignment and test improvements" --body "$(cat <<'EOF'
## Summary

- Add `context.Context` to dedup `Seen`/`Mark` methods (#604)
- Wrap all bare errors in adapter helpers with `fmt.Errorf` (#604)
- Move hardcoded HN/funding config to `config.yml` (#604)
- Add OS signal handling for graceful shutdown (#604)
- Named scoring constants, response body in ingest errors (#605)
- Convert runner tests to testify, add ingest/mark/multi-source tests (#605)
- Add `test:cover` task, `.layers` file, remove dead test code (#605)

## Test plan

- [ ] `task test` passes with race detector
- [ ] `task lint` clean
- [ ] `task run:dry` produces expected output

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## PR 3: Funding Tuning + Deploy (#588, #589)

### Task 13: Capture real funding HTML fixtures (#589)

**Files:**
- Create: `signal-crawler/internal/adapter/funding/testdata/otf_real.html`
- Create: `signal-crawler/internal/adapter/funding/testdata/grants_gc_real.html`
- Modify: `signal-crawler/internal/adapter/funding/funding_test.go`

- [ ] **Step 1: Fetch and save real HTML**

```bash
cd ~/dev/north-cloud/signal-crawler
curl -s -o internal/adapter/funding/testdata/otf_real.html "https://otf.ca/funded-grants" 2>/dev/null || echo "OTF fetch failed — will need manual fixture"
curl -s -o internal/adapter/funding/testdata/grants_gc_real.html "https://www.nserc-crsng.gc.ca/NSERC-CRSNG/FundingDecisions-DecisionsFinancement/index_eng.asp" 2>/dev/null || echo "grants.gc fetch failed — will need manual fixture"
```

Note: These URLs may require browser-like headers or may have different structure than expected. If the fetch fails, capture the HTML manually via a browser's "Save As" feature.

- [ ] **Step 2: Write test against real HTML**

Add to `signal-crawler/internal/adapter/funding/funding_test.go`:

```go
func TestFundingAdapter_RealOTF(t *testing.T) {
	fixture, err := os.ReadFile("testdata/otf_real.html")
	if errors.Is(err, os.ErrNotExist) {
		t.Skip("real OTF fixture not available — run curl to fetch")
	}
	require.NoError(t, err)

	srv := httptest.NewServer(http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		w.Header().Set("Content-Type", "text/html")
		w.Write(fixture)
	}))
	defer srv.Close()

	a := funding.New([]string{srv.URL})
	signals, err := a.Scan(context.Background())
	require.NoError(t, err)

	t.Logf("Parsed %d signals from real OTF HTML", len(signals))
	for i, s := range signals {
		t.Logf("  [%d] %s (strength=%d)", i, s.Label, s.SignalStrength)
	}

	// This test is diagnostic — it shows what the parser extracts.
	// If it returns 0 signals, the CSS selectors need updating.
	if len(signals) == 0 {
		t.Error("No signals parsed from real OTF HTML — selectors may need updating")
	}
}
```

Add `"errors"` to the import block.

- [ ] **Step 3: Run the diagnostic test**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -run TestFundingAdapter_RealOTF -v ./internal/adapter/funding/`

Expected: Either signals are parsed (selectors work) or 0 signals (selectors need fixing). If 0, examine the real HTML structure and update `parseGrantRows` and `extractRow` in `funding.go` to match the actual CSS classes.

- [ ] **Step 4: Fix selectors if needed**

Based on the real HTML structure, update `parseGrantRows` and `extractRow` in `funding.go`. The current code looks for:
- `div.views-row` containers
- `span.views-field-title` with `a` child (org name + link)
- `span.views-field-field-grant-program` (program name)
- `span.views-field-field-amount` (dollar amount)
- `span.views-field-field-org-type` (organization type)

If OTF uses different class names, update accordingly. Keep the same extraction logic, just change the CSS class selectors.

- [ ] **Step 5: Run all tests**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -v ./...`

Expected: All PASS.

- [ ] **Step 6: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add internal/adapter/funding/testdata/ internal/adapter/funding/funding.go internal/adapter/funding/funding_test.go
git commit -m "fix(signal-crawler): tune funding adapter for real OTF HTML (#589)"
```

---

### Task 14: Add --source flag for single-adapter runs

**Files:**
- Modify: `signal-crawler/main.go`

- [ ] **Step 1: Add --source flag**

In `signal-crawler/main.go`, add after line 29:

```go
	sourceFilter := flag.String("source", "", "Run only this adapter (hn, funding)")
```

After the sources slice is built (after line 79), add filtering:

```go
	if *sourceFilter != "" {
		var filtered []adapter.Source
		for _, src := range sources {
			if src.Name() == *sourceFilter {
				filtered = append(filtered, src)
			}
		}
		if len(filtered) == 0 {
			log.Error("unknown source", infralogger.String("source", *sourceFilter))
			os.Exit(1)
		}
		sources = filtered
	}
```

- [ ] **Step 2: Test manually**

Run: `cd ~/dev/north-cloud/signal-crawler && go build -o bin/signal-crawler main.go && ./bin/signal-crawler --dry-run --source=hn`

Expected: Only HN adapter runs.

- [ ] **Step 3: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add main.go
git commit -m "feat(signal-crawler): add --source flag for single-adapter runs"
```

---

### Task 15: Create systemd units for deploy (#588)

**Files:**
- Create: `signal-crawler/deploy/signal-crawler.service`
- Create: `signal-crawler/deploy/signal-crawler.timer`
- Create: `signal-crawler/deploy/.env.example`

- [ ] **Step 1: Create systemd service unit**

Create `signal-crawler/deploy/signal-crawler.service`:

```ini
[Unit]
Description=Signal crawler — scan HN and funding portals for lead signals
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/opt/north-cloud/signal-crawler/signal-crawler
WorkingDirectory=/opt/north-cloud/signal-crawler
EnvironmentFile=/opt/north-cloud/signal-crawler/.env
StandardOutput=journal
StandardError=journal
```

- [ ] **Step 2: Create systemd timer unit**

Create `signal-crawler/deploy/signal-crawler.timer`:

```ini
[Unit]
Description=Signal crawler daily scan

[Timer]
OnCalendar=*-*-* 06:00:00 UTC
Persistent=true
RandomizedDelaySec=300

[Install]
WantedBy=timers.target
```

- [ ] **Step 3: Create .env.example**

Create `signal-crawler/deploy/.env.example`:

```bash
# Required
NORTHOPS_URL=https://northops.ca
PIPELINE_API_KEY=your-api-key-here

# Optional (defaults shown)
# SIGNAL_DB_PATH=data/seen.db
# LOG_LEVEL=info
# LOG_FORMAT=json
# HN_MAX_ITEMS=200
```

- [ ] **Step 4: Commit**

```bash
cd ~/dev/north-cloud/signal-crawler
git add deploy/
git commit -m "feat(signal-crawler): add systemd service and timer units (#588)"
```

---

### Task 16: Full validation and PR 3

- [ ] **Step 1: Build release binary**

Run: `cd ~/dev/north-cloud/signal-crawler && task build`

Expected: `bin/signal-crawler` built successfully.

- [ ] **Step 2: Run full test suite**

Run: `cd ~/dev/north-cloud/signal-crawler && go test -race -count=1 -v ./...`

Expected: All PASS.

- [ ] **Step 3: Run linter**

Run: `cd ~/dev/north-cloud/signal-crawler && task lint`

Expected: Clean.

- [ ] **Step 4: Dry run test**

Run: `cd ~/dev/north-cloud/signal-crawler && ./bin/signal-crawler --dry-run`

Expected: Structured JSON log output showing scanned/matched/skipped counts for both adapters.

- [ ] **Step 5: Push and create PR**

```bash
cd ~/dev/north-cloud
git push -u origin HEAD
gh pr create --title "feat(signal-crawler): funding tuning and production deploy" --body "$(cat <<'EOF'
## Summary

- Tune funding adapter CSS selectors for real OTF HTML (#589)
- Add `--source` flag for single-adapter dry runs
- Add systemd service + timer units for daily 06:00 UTC cron (#588)
- Production deploy: binary at `/opt/north-cloud/signal-crawler/`, env file, journald logging

## Test plan

- [ ] `task test` passes with race detector
- [ ] `task lint` clean
- [ ] `task run:dry` produces expected output
- [ ] `task run:dry -- --source=funding` runs only funding adapter
- [ ] Deploy to VPS: copy binary, install systemd units, verify timer

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```
