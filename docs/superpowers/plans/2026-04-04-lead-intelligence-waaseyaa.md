# Lead Intelligence Pipeline — Waaseyaa Foundation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the signal ingestion and lead enrichment domain in the Waaseyaa application, providing API endpoints that north-cloud and the admin SPA consume.

**Architecture:** Two new entities (LeadSignal, LeadEnrichment) follow existing ContentEntityBase patterns. Domain services handle ingestion, matching, and enrichment orchestration. Controllers expose JSON:API endpoints with API key auth. A dedicated SignalServiceProvider wires routes and services.

**Tech Stack:** PHP 8.4, Waaseyaa framework (alpha.104), PHPUnit, Symfony HttpFoundation, waaseyaa/http-client

**Spec:** `docs/superpowers/specs/2026-04-04-lead-intelligence-pipeline-design.md`

**GitHub issues:** jonesrussell/northops-waaseyaa #110-#121

---

## File Map

| File | Action | Responsibility |
|------|--------|---------------|
| `src/Entity/LeadSignal.php` | Create | Signal entity with typed getters |
| `src/Entity/LeadEnrichment.php` | Create | Enrichment entity with typed getters |
| `config/entity-types.php` | Modify | Register lead_signal and lead_enrichment types |
| `src/Domain/Signal/SignalMatcher.php` | Create | Match signals to existing leads |
| `src/Domain/Signal/IngestResult.php` | Create | Value object for ingestion stats |
| `src/Domain/Signal/SignalIngestionService.php` | Create | Core ingestion: validate, dedup, match, create |
| `src/Domain/Signal/Event/SignalIngestedEvent.php` | Create | Event fired on signal creation |
| `src/Domain/Enrichment/EnrichmentReceiver.php` | Create | Handle inbound enrichment pushes |
| `src/Domain/Enrichment/EnrichmentService.php` | Create | Request enrichment from north-cloud |
| `src/Domain/Enrichment/Event/LeadEnrichedEvent.php` | Create | Event fired on enrichment creation |
| `src/Domain/Pipeline/EventSubscriber/SignalIngestedSubscriber.php` | Create | Discord + activity log for signals |
| `src/Domain/Pipeline/EventSubscriber/LeadEnrichedSubscriber.php` | Create | Discord + activity log for enrichments |
| `src/Domain/Pipeline/LeadFactory.php` | Modify | Add fromSignal() method |
| `src/Controller/Api/SignalController.php` | Create | POST /api/signals, GET /api/signals/unmatched |
| `src/Controller/Api/EnrichmentController.php` | Create | POST /api/leads/{id}/enrich, POST /api/leads/{id}/enrichment |
| `src/Controller/Api/LeadController.php` | Modify | Add signal_count/enrichment_count to list, GET signals/enrichments |
| `src/Provider/SignalServiceProvider.php` | Create | Route registration and service wiring |
| `config/waaseyaa.php` | Modify | Add signal config keys |
| `composer.json` | Modify | Register SignalServiceProvider |
| `tests/Unit/Entity/LeadSignalTest.php` | Create | Entity hydration and getters |
| `tests/Unit/Entity/LeadEnrichmentTest.php` | Create | Entity hydration and getters |
| `tests/Unit/Domain/Signal/SignalMatcherTest.php` | Create | Matching strategies and normalization |
| `tests/Unit/Domain/Signal/SignalIngestionServiceTest.php` | Create | Ingestion flow with mocks |
| `tests/Unit/Domain/Enrichment/EnrichmentReceiverTest.php` | Create | Enrichment creation and validation |
| `tests/Unit/Domain/Pipeline/LeadFactorySignalTest.php` | Create | fromSignal field mapping |

---

### Task 1: LeadSignal Entity

**Files:**
- Create: `src/Entity/LeadSignal.php`
- Create: `tests/Unit/Entity/LeadSignalTest.php`
- Modify: `config/entity-types.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Entity/LeadSignalTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\LeadSignal;
use PHPUnit\Framework\TestCase;

final class LeadSignalTest extends TestCase
{
    public function testFieldsStoredAndRetrieved(): void
    {
        $signal = new LeadSignal([
            'label' => 'Web App Dev RFP',
            'lead_id' => 42,
            'signal_type' => 'rfp',
            'source' => 'north-cloud',
            'source_url' => 'https://canadabuys.canada.ca/123',
            'external_id' => 'nc-rfp-456',
            'strength' => 75,
            'payload' => ['title' => 'Test', 'score' => 80],
            'organization_name' => 'Health Canada',
            'sector' => 'government',
            'province' => 'ON',
            'expires_at' => '2026-05-15T00:00:00Z',
        ]);

        $this->assertSame('Web App Dev RFP', $signal->getLabel());
        $this->assertSame(42, $signal->getLeadId());
        $this->assertSame('rfp', $signal->getSignalType());
        $this->assertSame('north-cloud', $signal->getSource());
        $this->assertSame('https://canadabuys.canada.ca/123', $signal->getSourceUrl());
        $this->assertSame('nc-rfp-456', $signal->getExternalId());
        $this->assertSame(75, $signal->getStrength());
        $this->assertSame(['title' => 'Test', 'score' => 80], $signal->getPayload());
        $this->assertSame('Health Canada', $signal->getOrganizationName());
        $this->assertSame('government', $signal->getSector());
        $this->assertSame('ON', $signal->getProvince());
        $this->assertSame('2026-05-15T00:00:00Z', $signal->getExpiresAt());
        $this->assertNotEmpty($signal->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $signal = new LeadSignal([
            'label' => 'Minimal signal',
            'signal_type' => 'hn_mention',
            'source' => 'signal-crawler',
            'external_id' => 'hn-789',
        ]);

        $this->assertNull($signal->getLeadId());
        $this->assertSame('', $signal->getSourceUrl());
        $this->assertSame(50, $signal->getStrength());
        $this->assertSame([], $signal->getPayload());
        $this->assertSame('', $signal->getOrganizationName());
        $this->assertSame('', $signal->getSector());
        $this->assertSame('', $signal->getProvince());
        $this->assertNull($signal->getExpiresAt());
        $this->assertNotEmpty($signal->getCreatedAt());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Entity/LeadSignalTest.php`
Expected: FAIL — class `App\Entity\LeadSignal` not found

- [ ] **Step 3: Implement LeadSignal entity**

Create `src/Entity/LeadSignal.php`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class LeadSignal extends ContentEntityBase
{
    protected string $entityTypeId = 'lead_signal';
    protected array $entityKeys = ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'];

    public function __construct(array $values = [])
    {
        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }
        if (!isset($values['strength'])) {
            $values['strength'] = 50;
        }
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getLabel(): string
    {
        return (string) ($this->get('label') ?? '');
    }

    public function getLeadId(): ?int
    {
        $val = $this->get('lead_id');
        return $val !== null ? (int) $val : null;
    }

    public function getSignalType(): string
    {
        return (string) ($this->get('signal_type') ?? '');
    }

    public function getSource(): string
    {
        return (string) ($this->get('source') ?? '');
    }

    public function getSourceUrl(): string
    {
        return (string) ($this->get('source_url') ?? '');
    }

    public function getExternalId(): string
    {
        return (string) ($this->get('external_id') ?? '');
    }

    public function getStrength(): int
    {
        return (int) ($this->get('strength') ?? 50);
    }

    public function getPayload(): array
    {
        $val = $this->get('payload');
        if (is_string($val)) {
            return json_decode($val, true) ?: [];
        }
        return is_array($val) ? $val : [];
    }

    public function getOrganizationName(): string
    {
        return (string) ($this->get('organization_name') ?? '');
    }

    public function getSector(): string
    {
        return (string) ($this->get('sector') ?? '');
    }

    public function getProvince(): string
    {
        return (string) ($this->get('province') ?? '');
    }

    public function getExpiresAt(): ?string
    {
        $val = $this->get('expires_at');
        return $val !== null && $val !== '' ? (string) $val : null;
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }
}
```

- [ ] **Step 4: Register entity type**

Add to `config/entity-types.php` in the return array:

```php
new EntityType(
    id: 'lead_signal',
    entityClass: \App\Entity\LeadSignal::class,
    keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
),
```

- [ ] **Step 5: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Unit/Entity/LeadSignalTest.php`
Expected: OK (2 tests, 26 assertions)

- [ ] **Step 6: Run full suite to check for regressions**

Run: `./vendor/bin/phpunit`
Expected: OK (120 tests)

- [ ] **Step 7: Commit**

```bash
git add src/Entity/LeadSignal.php tests/Unit/Entity/LeadSignalTest.php config/entity-types.php
git commit -m "feat: implement LeadSignal entity (#110)"
```

---

### Task 2: LeadEnrichment Entity

**Files:**
- Create: `src/Entity/LeadEnrichment.php`
- Create: `tests/Unit/Entity/LeadEnrichmentTest.php`
- Modify: `config/entity-types.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Entity/LeadEnrichmentTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Entity;

use App\Entity\LeadEnrichment;
use PHPUnit\Framework\TestCase;

final class LeadEnrichmentTest extends TestCase
{
    public function testFieldsStoredAndRetrieved(): void
    {
        $enrichment = new LeadEnrichment([
            'label' => 'Company intel for Health Canada',
            'lead_id' => 42,
            'provider' => 'north-cloud',
            'enrichment_type' => 'company_intel',
            'data' => ['website' => 'https://example.gc.ca', 'tech_stack' => ['WordPress']],
            'confidence' => 0.85,
        ]);

        $this->assertSame('Company intel for Health Canada', $enrichment->getLabel());
        $this->assertSame(42, $enrichment->getLeadId());
        $this->assertSame('north-cloud', $enrichment->getProvider());
        $this->assertSame('company_intel', $enrichment->getEnrichmentType());
        $this->assertSame(['website' => 'https://example.gc.ca', 'tech_stack' => ['WordPress']], $enrichment->getData());
        $this->assertSame(0.85, $enrichment->getConfidence());
        $this->assertNotEmpty($enrichment->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $enrichment = new LeadEnrichment([
            'label' => 'Minimal enrichment',
            'lead_id' => 1,
            'provider' => 'manual',
            'enrichment_type' => 'tech_stack',
            'data' => [],
        ]);

        $this->assertSame(0.0, $enrichment->getConfidence());
        $this->assertSame([], $enrichment->getData());
        $this->assertNotEmpty($enrichment->getCreatedAt());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Entity/LeadEnrichmentTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement LeadEnrichment entity**

Create `src/Entity/LeadEnrichment.php`:

```php
<?php

declare(strict_types=1);

namespace App\Entity;

use Waaseyaa\Entity\ContentEntityBase;

final class LeadEnrichment extends ContentEntityBase
{
    protected string $entityTypeId = 'lead_enrichment';
    protected array $entityKeys = ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'];

    public function __construct(array $values = [])
    {
        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }
        if (!isset($values['confidence'])) {
            $values['confidence'] = 0.0;
        }
        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getLabel(): string
    {
        return (string) ($this->get('label') ?? '');
    }

    public function getLeadId(): int
    {
        return (int) ($this->get('lead_id') ?? 0);
    }

    public function getProvider(): string
    {
        return (string) ($this->get('provider') ?? '');
    }

    public function getEnrichmentType(): string
    {
        return (string) ($this->get('enrichment_type') ?? '');
    }

    public function getData(): array
    {
        $val = $this->get('data');
        if (is_string($val)) {
            return json_decode($val, true) ?: [];
        }
        return is_array($val) ? $val : [];
    }

    public function getConfidence(): float
    {
        return (float) ($this->get('confidence') ?? 0.0);
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }
}
```

- [ ] **Step 4: Register entity type**

Add to `config/entity-types.php`:

```php
new EntityType(
    id: 'lead_enrichment',
    entityClass: \App\Entity\LeadEnrichment::class,
    keys: ['id' => 'id', 'uuid' => 'uuid', 'label' => 'label'],
),
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Entity/LeadEnrichmentTest.php`
Expected: OK (2 tests)

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 6: Commit**

```bash
git add src/Entity/LeadEnrichment.php tests/Unit/Entity/LeadEnrichmentTest.php config/entity-types.php
git commit -m "feat: implement LeadEnrichment entity (#111)"
```

---

### Task 3: SignalMatcher

**Files:**
- Create: `src/Domain/Signal/SignalMatcher.php`
- Create: `tests/Unit/Domain/Signal/SignalMatcherTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Domain/Signal/SignalMatcherTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Signal;

use App\Domain\Signal\SignalMatcher;
use PHPUnit\Framework\TestCase;

final class SignalMatcherTest extends TestCase
{
    public function testNormalizeOrgNameStripsInc(): void
    {
        $this->assertSame('health canada', SignalMatcher::normalizeOrgName('Health Canada Inc.'));
    }

    public function testNormalizeOrgNameStripsLtd(): void
    {
        $this->assertSame('acme solutions', SignalMatcher::normalizeOrgName('Acme Solutions Ltd'));
    }

    public function testNormalizeOrgNameStripsCorp(): void
    {
        $this->assertSame('big tech', SignalMatcher::normalizeOrgName('Big Tech Corp.'));
    }

    public function testNormalizeOrgNameStripsLlc(): void
    {
        $this->assertSame('startup co', SignalMatcher::normalizeOrgName('Startup Co LLC'));
    }

    public function testNormalizeOrgNameStripsLimited(): void
    {
        $this->assertSame('northern services', SignalMatcher::normalizeOrgName('Northern Services Limited'));
    }

    public function testNormalizeOrgNameStripsIncorporated(): void
    {
        $this->assertSame('megacorp', SignalMatcher::normalizeOrgName('MegaCorp Incorporated'));
    }

    public function testNormalizeOrgNameStripsCorporation(): void
    {
        $this->assertSame('global', SignalMatcher::normalizeOrgName('Global Corporation'));
    }

    public function testNormalizeOrgNameHandlesNoSuffix(): void
    {
        $this->assertSame('health canada', SignalMatcher::normalizeOrgName('Health Canada'));
    }

    public function testNormalizeOrgNameTrimsWhitespace(): void
    {
        $this->assertSame('health canada', SignalMatcher::normalizeOrgName('  Health Canada  '));
    }

    public function testNormalizeOrgNameHandlesEmpty(): void
    {
        $this->assertSame('', SignalMatcher::normalizeOrgName(''));
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Signal/SignalMatcherTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement SignalMatcher**

Create `src/Domain/Signal/SignalMatcher.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Signal;

use App\Entity\Lead;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalMatcher
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    public function match(array $signalData): ?Lead
    {
        $lead = $this->matchByExternalId($signalData);
        if ($lead !== null) {
            return $lead;
        }

        $orgName = $signalData['organization_name'] ?? '';
        if ($orgName !== '') {
            return $this->matchByOrgName($orgName);
        }

        return null;
    }

    private function matchByExternalId(array $signalData): ?Lead
    {
        $externalId = $signalData['external_id'] ?? '';
        if ($externalId === '') {
            return null;
        }

        $storage = $this->entityTypeManager->getStorage('lead');
        $ids = $storage->getQuery()
            ->condition('external_id', $externalId)
            ->execute();

        if ($ids === []) {
            return null;
        }

        return $storage->load((int) reset($ids));
    }

    private function matchByOrgName(string $orgName): ?Lead
    {
        $normalizedInput = self::normalizeOrgName($orgName);
        if ($normalizedInput === '') {
            return null;
        }

        $storage = $this->entityTypeManager->getStorage('lead');
        $ids = $storage->getQuery()->execute();

        foreach ($ids as $id) {
            $lead = $storage->load((int) $id);
            if ($lead === null) {
                continue;
            }
            $normalizedCompany = self::normalizeOrgName($lead->getCompanyName());
            if ($normalizedCompany !== '' && $normalizedCompany === $normalizedInput) {
                return $lead;
            }
        }

        return null;
    }

    public static function normalizeOrgName(string $name): string
    {
        $name = trim($name);
        $name = mb_strtolower($name);

        $suffixes = [
            ' incorporated',
            ' corporation',
            ' limited',
            ' corp.',
            ' corp',
            ' inc.',
            ' inc',
            ' ltd.',
            ' ltd',
            ' llc',
            ' l.l.c.',
            ' co.',
            ' co',
        ];

        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                $name = substr($name, 0, -strlen($suffix));
                break;
            }
        }

        return trim($name);
    }
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Signal/SignalMatcherTest.php`
Expected: OK (10 tests)

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Signal/SignalMatcher.php tests/Unit/Domain/Signal/SignalMatcherTest.php
git commit -m "feat: implement SignalMatcher with org name normalization (#113)"
```

---

### Task 4: IngestResult + SignalIngestedEvent

**Files:**
- Create: `src/Domain/Signal/IngestResult.php`
- Create: `src/Domain/Signal/Event/SignalIngestedEvent.php`

- [ ] **Step 1: Create IngestResult value object**

Create `src/Domain/Signal/IngestResult.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Signal;

final readonly class IngestResult
{
    public function __construct(
        public int $ingested = 0,
        public int $skipped = 0,
        public int $leadsCreated = 0,
        public int $leadsMatched = 0,
        public int $unmatched = 0,
        public array $errors = [],
    ) {}

    public function toArray(): array
    {
        return [
            'ingested' => $this->ingested,
            'skipped' => $this->skipped,
            'leads_created' => $this->leadsCreated,
            'leads_matched' => $this->leadsMatched,
            'unmatched' => $this->unmatched,
        ];
    }
}
```

- [ ] **Step 2: Create SignalIngestedEvent**

Create `src/Domain/Signal/Event/SignalIngestedEvent.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Signal\Event;

use App\Entity\Lead;
use App\Entity\LeadSignal;

final readonly class SignalIngestedEvent
{
    public function __construct(
        public LeadSignal $signal,
        public ?Lead $lead = null,
    ) {}
}
```

- [ ] **Step 3: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: OK (all tests, no regressions)

- [ ] **Step 4: Commit**

```bash
git add src/Domain/Signal/IngestResult.php src/Domain/Signal/Event/SignalIngestedEvent.php
git commit -m "feat: add IngestResult value object and SignalIngestedEvent"
```

---

### Task 5: LeadFactory.fromSignal

**Files:**
- Modify: `src/Domain/Pipeline/LeadFactory.php`
- Create: `tests/Unit/Domain/Pipeline/LeadFactorySignalTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Domain/Pipeline/LeadFactorySignalTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\LeadFactory;
use App\Entity\Lead;
use PHPUnit\Framework\TestCase;

final class LeadFactorySignalTest extends TestCase
{
    public function testFromSignalMapsRfpFields(): void
    {
        $factory = $this->createFactory();

        $lead = $factory->fromSignal([
            'label' => 'Web App Dev RFP',
            'organization_name' => 'Health Canada',
            'source_url' => 'https://canadabuys.canada.ca/123',
            'external_id' => 'nc-rfp-456',
            'sector' => 'government',
            'signal_type' => 'rfp',
            'strength' => 75,
        ], 1);

        $this->assertInstanceOf(Lead::class, $lead);
        $this->assertSame('Web App Dev RFP', $lead->getLabel());
        $this->assertSame('Health Canada', $lead->getCompanyName());
        $this->assertSame('https://canadabuys.canada.ca/123', $lead->getSourceUrl());
        $this->assertSame('nc-rfp-456', $lead->getExternalId());
        $this->assertSame('government', $lead->getSector());
        $this->assertSame('rfp', $lead->getSource());
    }

    public function testFromSignalMapsFundingToReferral(): void
    {
        $factory = $this->createFactory();

        $lead = $factory->fromSignal([
            'label' => 'Funding win',
            'signal_type' => 'funding_win',
            'external_id' => 'nc-sig-789',
        ], 1);

        $this->assertSame('referral', $lead->getSource());
    }

    public function testFromSignalMapsJobPostingToColdOutreach(): void
    {
        $factory = $this->createFactory();

        $lead = $factory->fromSignal([
            'label' => 'Hiring signal',
            'signal_type' => 'job_posting',
            'external_id' => 'nc-sig-101',
        ], 1);

        $this->assertSame('cold_outreach', $lead->getSource());
    }

    public function testFromSignalMapsUnknownTypeToOther(): void
    {
        $factory = $this->createFactory();

        $lead = $factory->fromSignal([
            'label' => 'HN mention',
            'signal_type' => 'hn_mention',
            'external_id' => 'hn-202',
        ], 1);

        $this->assertSame('other', $lead->getSource());
    }

    private function createFactory(): LeadFactory
    {
        $etm = $this->createMock(\Waaseyaa\Entity\EntityTypeManager::class);
        $leadManager = $this->createMock(\App\Domain\Pipeline\LeadManager::class);
        $leadManager->method('create')->willReturnCallback(function (array $data) {
            return new Lead($data);
        });
        $routingService = new \App\Domain\Pipeline\RoutingService();

        return new LeadFactory($leadManager, $etm, $routingService);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Pipeline/LeadFactorySignalTest.php`
Expected: FAIL — method fromSignal does not exist

- [ ] **Step 3: Add fromSignal to LeadFactory**

Add this method to `src/Domain/Pipeline/LeadFactory.php` (after the existing `fromRfpImport` method):

```php
public function fromSignal(array $signalData, int $brandId): Lead
{
    $sourceMap = [
        'rfp' => 'rfp',
        'funding_win' => 'referral',
        'job_posting' => 'cold_outreach',
        'tech_migration' => 'cold_outreach',
        'outdated_website' => 'cold_outreach',
        'new_program' => 'other',
        'hn_mention' => 'other',
    ];

    $signalType = $signalData['signal_type'] ?? '';
    $source = $sourceMap[$signalType] ?? 'other';

    $data = [
        'label' => $signalData['label'] ?? '',
        'company_name' => $signalData['organization_name'] ?? '',
        'source_url' => $signalData['source_url'] ?? '',
        'external_id' => $signalData['external_id'] ?? '',
        'sector' => $signalData['sector'] ?? '',
        'source' => $source,
        'brand_id' => $brandId,
        'stage' => 'lead',
    ];

    return $this->leadManager->create($data);
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Pipeline/LeadFactorySignalTest.php`
Expected: OK (4 tests)

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Pipeline/LeadFactory.php tests/Unit/Domain/Pipeline/LeadFactorySignalTest.php
git commit -m "feat: add LeadFactory.fromSignal method (#118)"
```

---

### Task 6: SignalIngestionService

**Files:**
- Create: `src/Domain/Signal/SignalIngestionService.php`
- Create: `tests/Unit/Domain/Signal/SignalIngestionServiceTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Domain/Signal/SignalIngestionServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Signal;

use App\Domain\Pipeline\LeadFactory;
use App\Domain\Signal\SignalIngestionService;
use App\Domain\Signal\SignalMatcher;
use App\Entity\Lead;
use App\Entity\LeadSignal;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalIngestionServiceTest extends TestCase
{
    private function validSignal(array $overrides = []): array
    {
        return array_merge([
            'signal_type' => 'rfp',
            'external_id' => 'nc-rfp-1',
            'source' => 'north-cloud',
            'label' => 'Test RFP Signal',
            'strength' => 75,
            'organization_name' => 'Test Org',
        ], $overrides);
    }

    public function testValidSignalIsIngested(): void
    {
        $service = $this->buildService(existingExternalIds: []);

        $result = $service->ingest([$this->validSignal()]);

        $this->assertSame(1, $result->ingested);
        $this->assertSame(0, $result->skipped);
    }

    public function testDuplicateExternalIdIsSkipped(): void
    {
        $service = $this->buildService(existingExternalIds: ['nc-rfp-1']);

        $result = $service->ingest([$this->validSignal()]);

        $this->assertSame(0, $result->ingested);
        $this->assertSame(1, $result->skipped);
    }

    public function testMissingRequiredFieldReturnsError(): void
    {
        $service = $this->buildService(existingExternalIds: []);

        $result = $service->ingest([['signal_type' => 'rfp']]);

        $this->assertSame(0, $result->ingested);
        $this->assertNotEmpty($result->errors);
    }

    public function testHighStrengthAutoCreatesLead(): void
    {
        $service = $this->buildService(existingExternalIds: [], matchResult: null);

        $result = $service->ingest([$this->validSignal(['strength' => 80])]);

        $this->assertSame(1, $result->ingested);
        $this->assertSame(1, $result->leadsCreated);
    }

    public function testLowStrengthStoresUnmatched(): void
    {
        $service = $this->buildService(existingExternalIds: [], matchResult: null);

        $result = $service->ingest([$this->validSignal(['strength' => 30])]);

        $this->assertSame(1, $result->ingested);
        $this->assertSame(1, $result->unmatched);
        $this->assertSame(0, $result->leadsCreated);
    }

    public function testMatchedSignalLinksToLead(): void
    {
        $lead = new Lead(['label' => 'Existing Lead']);
        $service = $this->buildService(existingExternalIds: [], matchResult: $lead);

        $result = $service->ingest([$this->validSignal()]);

        $this->assertSame(1, $result->ingested);
        $this->assertSame(1, $result->leadsMatched);
    }

    public function testBatchMixedSignals(): void
    {
        $service = $this->buildService(existingExternalIds: ['nc-rfp-dup'], matchResult: null);

        $result = $service->ingest([
            $this->validSignal(['external_id' => 'nc-rfp-new', 'strength' => 80]),
            $this->validSignal(['external_id' => 'nc-rfp-dup']),
            $this->validSignal(['external_id' => 'nc-rfp-low', 'strength' => 20]),
        ]);

        $this->assertSame(2, $result->ingested);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(1, $result->leadsCreated);
        $this->assertSame(1, $result->unmatched);
    }

    private function buildService(array $existingExternalIds, ?Lead $matchResult = null): SignalIngestionService
    {
        $mockQuery = $this->createMock(\Waaseyaa\Entity\Query\EntityQueryInterface::class);
        $mockQuery->method('condition')->willReturnSelf();
        $mockQuery->method('execute')->willReturnCallback(function () use (&$existingExternalIds) {
            return $existingExternalIds !== [] ? [1] : [];
        });

        $mockStorage = $this->createMock(\Waaseyaa\Entity\Storage\EntityStorageInterface::class);
        $mockStorage->method('getQuery')->willReturn($mockQuery);
        $mockStorage->method('save')->willReturn(null);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($mockStorage);

        $matcher = $this->createMock(SignalMatcher::class);
        $matcher->method('match')->willReturn($matchResult);

        $factory = $this->createMock(LeadFactory::class);
        $factory->method('fromSignal')->willReturn(new Lead(['label' => 'Auto-created']));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        return new SignalIngestionService($etm, $matcher, $factory, $dispatcher, 50, 1);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Signal/SignalIngestionServiceTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement SignalIngestionService**

Create `src/Domain/Signal/SignalIngestionService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Signal;

use App\Domain\Pipeline\LeadFactory;
use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Entity\LeadSignal;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalIngestionService
{
    private const VALID_SIGNAL_TYPES = [
        'rfp', 'funding_win', 'job_posting', 'tech_migration',
        'outdated_website', 'hn_mention', 'new_program',
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly SignalMatcher $signalMatcher,
        private readonly LeadFactory $leadFactory,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly int $autoCreateThreshold = 50,
        private readonly int $defaultBrandId = 1,
    ) {}

    public function ingest(array $signals): IngestResult
    {
        $ingested = 0;
        $skipped = 0;
        $leadsCreated = 0;
        $leadsMatched = 0;
        $unmatched = 0;
        $errors = [];

        foreach ($signals as $signalData) {
            $validation = $this->validate($signalData);
            if ($validation !== null) {
                $errors[] = $validation;
                continue;
            }

            if ($this->isDuplicate($signalData)) {
                $skipped++;
                continue;
            }

            $signal = $this->createSignal($signalData);
            $lead = $this->signalMatcher->match($signalData);

            if ($lead !== null) {
                $signal->set('lead_id', $lead->id());
                $this->entityTypeManager->getStorage('lead_signal')->save($signal);
                $leadsMatched++;
            } else {
                $strength = (int) ($signalData['strength'] ?? 50);
                if ($strength >= $this->autoCreateThreshold) {
                    $lead = $this->leadFactory->fromSignal($signalData, $this->defaultBrandId);
                    $signal->set('lead_id', $lead->id());
                    $this->entityTypeManager->getStorage('lead_signal')->save($signal);
                    $leadsCreated++;
                } else {
                    $unmatched++;
                }
            }

            $this->dispatcher->dispatch(new SignalIngestedEvent($signal, $lead));
            $ingested++;
        }

        return new IngestResult($ingested, $skipped, $leadsCreated, $leadsMatched, $unmatched, $errors);
    }

    private function validate(array $data): ?string
    {
        foreach (['signal_type', 'external_id', 'source', 'label'] as $field) {
            if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
                return "Missing required field: {$field}";
            }
        }

        if (!in_array($data['signal_type'], self::VALID_SIGNAL_TYPES, true)) {
            return "Invalid signal_type: {$data['signal_type']}";
        }

        return null;
    }

    private function isDuplicate(array $data): bool
    {
        $ids = $this->entityTypeManager->getStorage('lead_signal')
            ->getQuery()
            ->condition('external_id', $data['external_id'])
            ->condition('source', $data['source'])
            ->execute();

        return $ids !== [];
    }

    private function createSignal(array $data): LeadSignal
    {
        $signal = new LeadSignal([
            'label' => $data['label'],
            'signal_type' => $data['signal_type'],
            'source' => $data['source'],
            'source_url' => $data['source_url'] ?? '',
            'external_id' => $data['external_id'],
            'strength' => (int) ($data['strength'] ?? 50),
            'payload' => $data['payload'] ?? [],
            'organization_name' => $data['organization_name'] ?? '',
            'sector' => $data['sector'] ?? '',
            'province' => $data['province'] ?? '',
            'expires_at' => $data['expires_at'] ?? null,
        ]);

        $this->entityTypeManager->getStorage('lead_signal')->save($signal);

        return $signal;
    }
}
```

- [ ] **Step 4: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Signal/SignalIngestionServiceTest.php`
Expected: OK (7 tests)

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 5: Commit**

```bash
git add src/Domain/Signal/SignalIngestionService.php tests/Unit/Domain/Signal/SignalIngestionServiceTest.php
git commit -m "feat: implement SignalIngestionService (#112)"
```

---

### Task 7: LeadEnrichedEvent + EnrichmentReceiver

**Files:**
- Create: `src/Domain/Enrichment/Event/LeadEnrichedEvent.php`
- Create: `src/Domain/Enrichment/EnrichmentReceiver.php`
- Create: `tests/Unit/Domain/Enrichment/EnrichmentReceiverTest.php`

- [ ] **Step 1: Create LeadEnrichedEvent**

Create `src/Domain/Enrichment/Event/LeadEnrichedEvent.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Enrichment\Event;

use App\Entity\Lead;
use App\Entity\LeadEnrichment;

final readonly class LeadEnrichedEvent
{
    public function __construct(
        public Lead $lead,
        public LeadEnrichment $enrichment,
    ) {}
}
```

- [ ] **Step 2: Write the failing test for EnrichmentReceiver**

Create `tests/Unit/Domain/Enrichment/EnrichmentReceiverTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Enrichment;

use App\Domain\Enrichment\EnrichmentReceiver;
use App\Entity\Lead;
use App\Entity\LeadEnrichment;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class EnrichmentReceiverTest extends TestCase
{
    public function testReceiveCreatesEnrichment(): void
    {
        $receiver = $this->buildReceiver();
        $lead = new Lead(['label' => 'Test Lead']);

        $enrichment = $receiver->receive($lead, [
            'provider' => 'north-cloud',
            'enrichment_type' => 'company_intel',
            'data' => ['website' => 'https://example.com'],
            'confidence' => 0.85,
        ]);

        $this->assertInstanceOf(LeadEnrichment::class, $enrichment);
        $this->assertSame('north-cloud', $enrichment->getProvider());
        $this->assertSame('company_intel', $enrichment->getEnrichmentType());
        $this->assertSame(0.85, $enrichment->getConfidence());
    }

    public function testReceiveRejectsMissingProvider(): void
    {
        $receiver = $this->buildReceiver();
        $lead = new Lead(['label' => 'Test Lead']);

        $this->expectException(\InvalidArgumentException::class);
        $receiver->receive($lead, [
            'enrichment_type' => 'company_intel',
            'data' => [],
            'confidence' => 0.5,
        ]);
    }

    public function testReceiveRejectsInvalidEnrichmentType(): void
    {
        $receiver = $this->buildReceiver();
        $lead = new Lead(['label' => 'Test Lead']);

        $this->expectException(\InvalidArgumentException::class);
        $receiver->receive($lead, [
            'provider' => 'north-cloud',
            'enrichment_type' => 'invalid_type',
            'data' => [],
            'confidence' => 0.5,
        ]);
    }

    private function buildReceiver(): EnrichmentReceiver
    {
        $mockStorage = $this->createMock(\Waaseyaa\Entity\Storage\EntityStorageInterface::class);
        $mockStorage->method('save')->willReturn(null);

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($mockStorage);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);

        return new EnrichmentReceiver($etm, $dispatcher);
    }
}
```

- [ ] **Step 3: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Enrichment/EnrichmentReceiverTest.php`
Expected: FAIL — class not found

- [ ] **Step 4: Implement EnrichmentReceiver**

Create `src/Domain/Enrichment/EnrichmentReceiver.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Enrichment;

use App\Domain\Enrichment\Event\LeadEnrichedEvent;
use App\Entity\Lead;
use App\Entity\LeadEnrichment;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Waaseyaa\Entity\EntityTypeManager;

final class EnrichmentReceiver
{
    private const VALID_TYPES = [
        'company_intel', 'contact_discovery', 'tech_stack', 'financial', 'competitor_analysis',
    ];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EventDispatcherInterface $dispatcher,
    ) {}

    public function receive(Lead $lead, array $enrichmentData): LeadEnrichment
    {
        $this->validate($enrichmentData);

        $enrichment = new LeadEnrichment([
            'label' => sprintf('%s enrichment for %s', $enrichmentData['enrichment_type'], $lead->getLabel()),
            'lead_id' => $lead->id(),
            'provider' => $enrichmentData['provider'],
            'enrichment_type' => $enrichmentData['enrichment_type'],
            'data' => $enrichmentData['data'],
            'confidence' => (float) $enrichmentData['confidence'],
        ]);

        $this->entityTypeManager->getStorage('lead_enrichment')->save($enrichment);
        $this->dispatcher->dispatch(new LeadEnrichedEvent($lead, $enrichment));

        return $enrichment;
    }

    private function validate(array $data): void
    {
        if (!isset($data['provider']) || trim((string) $data['provider']) === '') {
            throw new \InvalidArgumentException('Missing required field: provider');
        }

        $type = $data['enrichment_type'] ?? '';
        if (!in_array($type, self::VALID_TYPES, true)) {
            throw new \InvalidArgumentException("Invalid enrichment_type: {$type}");
        }

        if (!isset($data['data']) || !is_array($data['data'])) {
            throw new \InvalidArgumentException('Missing required field: data (must be array)');
        }

        if (!isset($data['confidence'])) {
            throw new \InvalidArgumentException('Missing required field: confidence');
        }
    }
}
```

- [ ] **Step 5: Run tests**

Run: `./vendor/bin/phpunit tests/Unit/Domain/Enrichment/EnrichmentReceiverTest.php`
Expected: OK (3 tests)

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 6: Commit**

```bash
git add src/Domain/Enrichment/Event/LeadEnrichedEvent.php src/Domain/Enrichment/EnrichmentReceiver.php tests/Unit/Domain/Enrichment/EnrichmentReceiverTest.php
git commit -m "feat: implement EnrichmentReceiver and LeadEnrichedEvent (#115)"
```

---

### Task 8: EnrichmentService (outbound)

**Files:**
- Create: `src/Domain/Enrichment/EnrichmentService.php`

- [ ] **Step 1: Implement EnrichmentService**

Create `src/Domain/Enrichment/EnrichmentService.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Enrichment;

use App\Entity\Lead;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\HttpClient\HttpClientInterface;

final class EnrichmentService
{
    private const DEFAULT_TYPES = ['company_intel', 'tech_stack', 'hiring'];

    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly HttpClientInterface $httpClient,
        private readonly string $northcloudUrl,
        private readonly string $callbackApiKey,
        private readonly string $appUrl,
    ) {}

    public function requestEnrichment(Lead $lead, array $types = []): void
    {
        if ($types === []) {
            $types = self::DEFAULT_TYPES;
        }

        $signals = $this->loadSignals($lead);

        $payload = [
            'lead_id' => $lead->id(),
            'company_name' => $lead->getCompanyName(),
            'domain' => '',
            'sector' => $lead->getSector(),
            'requested_types' => $types,
            'signals' => $signals,
            'callback_url' => rtrim($this->appUrl, '/') . '/api/leads/' . $lead->id() . '/enrichment',
            'callback_api_key' => $this->callbackApiKey,
        ];

        $this->httpClient->post("{$this->northcloudUrl}/api/v1/enrich", [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);
    }

    private function loadSignals(Lead $lead): array
    {
        $ids = $this->entityTypeManager->getStorage('lead_signal')
            ->getQuery()
            ->condition('lead_id', $lead->id())
            ->execute();

        $signals = [];
        $storage = $this->entityTypeManager->getStorage('lead_signal');

        foreach ($ids as $id) {
            $signal = $storage->load((int) $id);
            if ($signal !== null) {
                $signals[] = [
                    'signal_type' => $signal->getSignalType(),
                    'label' => $signal->getLabel(),
                    'strength' => $signal->getStrength(),
                ];
            }
        }

        return $signals;
    }
}
```

- [ ] **Step 2: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 3: Commit**

```bash
git add src/Domain/Enrichment/EnrichmentService.php
git commit -m "feat: implement EnrichmentService for outbound enrichment requests (#114)"
```

---

### Task 9: SignalController

**Files:**
- Create: `src/Controller/Api/SignalController.php`

- [ ] **Step 1: Implement SignalController**

Create `src/Controller/Api/SignalController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Signal\SignalIngestionService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalController
{
    public function __construct(
        private readonly SignalIngestionService $ingestionService,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly string $apiKey,
    ) {}

    public function ingest(Request $request): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Unauthorized', 'detail' => 'Invalid API key.']],
            ], 401);
        }

        try {
            $body = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'Invalid JSON.']],
            ], 400);
        }

        $signals = $body['signals'] ?? [];
        if (!is_array($signals) || $signals === []) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'signals array is required and must not be empty.']],
            ], 400);
        }

        $result = $this->ingestionService->ingest($signals);

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => $result->toArray(),
        ], 201);
    }

    public function listUnmatched(Request $request): JsonResponse
    {
        $storage = $this->entityTypeManager->getStorage('lead_signal');
        $ids = $storage->getQuery()
            ->condition('lead_id', null)
            ->execute();

        $signals = [];
        foreach ($ids as $id) {
            $signal = $storage->load((int) $id);
            if ($signal === null) {
                continue;
            }
            $signals[] = [
                'id' => $signal->id(),
                'label' => $signal->getLabel(),
                'signal_type' => $signal->getSignalType(),
                'source' => $signal->getSource(),
                'strength' => $signal->getStrength(),
                'organization_name' => $signal->getOrganizationName(),
                'created_at' => $signal->getCreatedAt(),
            ];
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => $signals,
        ]);
    }

    private function validateApiKey(Request $request): bool
    {
        if ($this->apiKey === '') {
            return false;
        }
        $provided = $request->headers->get('X-Api-Key', '');
        return hash_equals($this->apiKey, $provided);
    }
}
```

- [ ] **Step 2: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/SignalController.php
git commit -m "feat: implement SignalController for POST /api/signals (#116)"
```

---

### Task 10: EnrichmentController

**Files:**
- Create: `src/Controller/Api/EnrichmentController.php`

- [ ] **Step 1: Implement EnrichmentController**

Create `src/Controller/Api/EnrichmentController.php`:

```php
<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Domain\Enrichment\EnrichmentReceiver;
use App\Domain\Enrichment\EnrichmentService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;

final class EnrichmentController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EnrichmentService $enrichmentService,
        private readonly EnrichmentReceiver $enrichmentReceiver,
        private readonly string $apiKey,
    ) {}

    public function requestEnrichment(Request $request, string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        $body = json_decode((string) $request->getContent(), true) ?? [];
        $types = $body['types'] ?? [];

        try {
            $this->enrichmentService->requestEnrichment($lead, $types);
        } catch (\Throwable $e) {
            error_log(sprintf('[NorthOps] Enrichment request failed: %s', $e->getMessage()));
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '502', 'title' => 'Bad Gateway', 'detail' => 'Enrichment service unavailable.']],
            ], 502);
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => [
                'enrichments_requested' => count($types ?: ['company_intel', 'tech_stack', 'hiring']),
                'types' => $types ?: ['company_intel', 'tech_stack', 'hiring'],
            ],
        ]);
    }

    public function receiveEnrichment(Request $request, string $id): JsonResponse
    {
        if (!$this->validateApiKey($request)) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '401', 'title' => 'Unauthorized', 'detail' => 'Invalid API key.']],
            ], 401);
        }

        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        try {
            $body = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '400', 'title' => 'Bad Request', 'detail' => 'Invalid JSON.']],
            ], 400);
        }

        try {
            $enrichment = $this->enrichmentReceiver->receive($lead, $body);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse([
                'jsonapi' => ['version' => '1.1'],
                'errors' => [['status' => '422', 'title' => 'Unprocessable Entity', 'detail' => $e->getMessage()]],
            ], 422);
        }

        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'data' => [
                'id' => $enrichment->id(),
                'enrichment_type' => $enrichment->getEnrichmentType(),
                'provider' => $enrichment->getProvider(),
            ],
        ], 201);
    }

    public function listSignals(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        $ids = $this->entityTypeManager->getStorage('lead_signal')
            ->getQuery()
            ->condition('lead_id', (int) $id)
            ->execute();

        $signals = [];
        $storage = $this->entityTypeManager->getStorage('lead_signal');
        foreach ($ids as $signalId) {
            $signal = $storage->load((int) $signalId);
            if ($signal === null) {
                continue;
            }
            $signals[] = [
                'id' => $signal->id(),
                'signal_type' => $signal->getSignalType(),
                'source' => $signal->getSource(),
                'label' => $signal->getLabel(),
                'strength' => $signal->getStrength(),
                'organization_name' => $signal->getOrganizationName(),
                'source_url' => $signal->getSourceUrl(),
                'expires_at' => $signal->getExpiresAt(),
                'created_at' => $signal->getCreatedAt(),
                'payload' => $signal->getPayload(),
            ];
        }

        return new JsonResponse(['jsonapi' => ['version' => '1.1'], 'data' => $signals]);
    }

    public function listEnrichments(string $id): JsonResponse
    {
        $lead = $this->loadLead($id);
        if ($lead === null) {
            return $this->notFound($id);
        }

        $ids = $this->entityTypeManager->getStorage('lead_enrichment')
            ->getQuery()
            ->condition('lead_id', (int) $id)
            ->execute();

        $enrichments = [];
        $storage = $this->entityTypeManager->getStorage('lead_enrichment');
        foreach ($ids as $eid) {
            $e = $storage->load((int) $eid);
            if ($e === null) {
                continue;
            }
            $enrichments[] = [
                'id' => $e->id(),
                'provider' => $e->getProvider(),
                'enrichment_type' => $e->getEnrichmentType(),
                'confidence' => $e->getConfidence(),
                'data' => $e->getData(),
                'created_at' => $e->getCreatedAt(),
            ];
        }

        return new JsonResponse(['jsonapi' => ['version' => '1.1'], 'data' => $enrichments]);
    }

    private function loadLead(string $id): ?\App\Entity\Lead
    {
        return $this->entityTypeManager->getStorage('lead')->load((int) $id);
    }

    private function notFound(string $id): JsonResponse
    {
        return new JsonResponse([
            'jsonapi' => ['version' => '1.1'],
            'errors' => [['status' => '404', 'title' => 'Not Found', 'detail' => "Lead {$id} not found."]],
        ], 404);
    }

    private function validateApiKey(Request $request): bool
    {
        if ($this->apiKey === '') {
            return false;
        }
        return hash_equals($this->apiKey, $request->headers->get('X-Api-Key', ''));
    }
}
```

- [ ] **Step 2: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 3: Commit**

```bash
git add src/Controller/Api/EnrichmentController.php
git commit -m "feat: implement EnrichmentController (#117)"
```

---

### Task 11: Event Subscribers

**Files:**
- Create: `src/Domain/Pipeline/EventSubscriber/SignalIngestedSubscriber.php`
- Create: `src/Domain/Pipeline/EventSubscriber/LeadEnrichedSubscriber.php`

- [ ] **Step 1: Implement SignalIngestedSubscriber**

Create `src/Domain/Pipeline/EventSubscriber/SignalIngestedSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Entity\LeadActivity;
use App\Support\DiscordNotifier;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalIngestedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DiscordNotifier $notifier,
    ) {}

    public function __invoke(SignalIngestedEvent $event): void
    {
        $signal = $event->signal;
        $lead = $event->lead;

        $this->notifier->sendEmbed(
            title: sprintf('[Signal] %s: %s', $signal->getSignalType(), $signal->getLabel()),
            fields: array_filter([
                'Strength' => (string) $signal->getStrength(),
                'Source' => $signal->getSource(),
                'Organization' => $signal->getOrganizationName(),
                'Lead' => $lead !== null ? $lead->getLabel() : 'Unmatched',
            ]),
            color: $lead !== null ? 0x2ECC71 : 0xF39C12,
        );

        if ($lead !== null) {
            $activity = new LeadActivity([
                'lead_id' => $lead->id(),
                'action' => 'signal_ingested',
                'payload' => json_encode([
                    'signal_type' => $signal->getSignalType(),
                    'signal_id' => $signal->id(),
                    'strength' => $signal->getStrength(),
                ]),
            ]);
            $this->entityTypeManager->getStorage('lead_activity')->save($activity);
        }
    }
}
```

- [ ] **Step 2: Implement LeadEnrichedSubscriber**

Create `src/Domain/Pipeline/EventSubscriber/LeadEnrichedSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Domain\Enrichment\Event\LeadEnrichedEvent;
use App\Entity\LeadActivity;
use App\Support\DiscordNotifier;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadEnrichedSubscriber
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DiscordNotifier $notifier,
    ) {}

    public function __invoke(LeadEnrichedEvent $event): void
    {
        $lead = $event->lead;
        $enrichment = $event->enrichment;

        $this->notifier->sendEmbed(
            title: sprintf('[Enrichment] %s for %s', $enrichment->getEnrichmentType(), $lead->getLabel()),
            fields: [
                'Provider' => $enrichment->getProvider(),
                'Confidence' => sprintf('%.0f%%', $enrichment->getConfidence() * 100),
                'Type' => $enrichment->getEnrichmentType(),
            ],
            color: 0x3498DB,
        );

        $activity = new LeadActivity([
            'lead_id' => $lead->id(),
            'action' => 'lead_enriched',
            'payload' => json_encode([
                'enrichment_type' => $enrichment->getEnrichmentType(),
                'provider' => $enrichment->getProvider(),
                'confidence' => $enrichment->getConfidence(),
            ]),
        ]);
        $this->entityTypeManager->getStorage('lead_activity')->save($activity);
    }
}
```

- [ ] **Step 3: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 4: Commit**

```bash
git add src/Domain/Pipeline/EventSubscriber/SignalIngestedSubscriber.php src/Domain/Pipeline/EventSubscriber/LeadEnrichedSubscriber.php
git commit -m "feat: implement signal and enrichment event subscribers (#119)"
```

---

### Task 12: SignalServiceProvider + Config

**Files:**
- Create: `src/Provider/SignalServiceProvider.php`
- Modify: `config/waaseyaa.php`
- Modify: `composer.json`

- [ ] **Step 1: Add config keys**

Add to `config/waaseyaa.php` inside the existing `'pipeline'` array, after the `'api_key'` line:

```php
'signal_auto_create_threshold' => (int) (getenv('SIGNAL_AUTO_CREATE_THRESHOLD') ?: 50),
'signal_auto_enrich' => filter_var(getenv('SIGNAL_AUTO_ENRICH') ?: true, FILTER_VALIDATE_BOOLEAN),
```

- [ ] **Step 2: Implement SignalServiceProvider**

Create `src/Provider/SignalServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Provider;

use App\Controller\Api\EnrichmentController;
use App\Controller\Api\SignalController;
use App\Domain\Enrichment\EnrichmentReceiver;
use App\Domain\Enrichment\EnrichmentService;
use App\Domain\Enrichment\Event\LeadEnrichedEvent;
use App\Domain\Pipeline\EventSubscriber\LeadEnrichedSubscriber;
use App\Domain\Pipeline\EventSubscriber\SignalIngestedSubscriber;
use App\Domain\Pipeline\LeadFactory;
use App\Domain\Pipeline\LeadManager;
use App\Domain\Pipeline\RoutingService;
use App\Domain\Signal\Event\SignalIngestedEvent;
use App\Domain\Signal\SignalIngestionService;
use App\Domain\Signal\SignalMatcher;
use App\Support\DiscordNotifier;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\HttpClient\StreamHttpClient;
use Waaseyaa\Routing\RouteBuilder;
use Waaseyaa\Routing\WaaseyaaRouter;

final class SignalServiceProvider extends ServiceProvider
{
    private ?SignalController $signalController = null;
    private ?EnrichmentController $enrichmentController = null;

    public function register(): void {}

    private function signalController(): SignalController
    {
        if ($this->signalController === null) {
            $etm = $this->resolve(EntityTypeManager::class);
            $matcher = new SignalMatcher($etm);
            $notifier = new DiscordNotifier(
                new StreamHttpClient(timeout: 5.0),
                $this->config['discord']['webhook_url'] ?? '',
            );

            $leadManager = $this->buildLeadManager($etm, $notifier);
            $factory = new LeadFactory($leadManager, $etm, new RoutingService());

            $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
            if ($dispatcher instanceof EventDispatcherInterface) {
                $dispatcher->addListener(SignalIngestedEvent::class, new SignalIngestedSubscriber($etm, $notifier));
                $dispatcher->addListener(LeadEnrichedEvent::class, new LeadEnrichedSubscriber($etm, $notifier));
            }

            $threshold = $this->config['pipeline']['signal_auto_create_threshold'] ?? 50;
            $defaultBrandId = $this->resolveDefaultBrandId($etm);

            $ingestionService = new SignalIngestionService(
                $etm, $matcher, $factory, $dispatcher, $threshold, $defaultBrandId,
            );

            $this->signalController = new SignalController(
                $ingestionService, $etm, $this->config['pipeline']['api_key'] ?? '',
            );
        }

        return $this->signalController;
    }

    private function enrichmentController(): EnrichmentController
    {
        if ($this->enrichmentController === null) {
            $etm = $this->resolve(EntityTypeManager::class);
            $notifier = new DiscordNotifier(
                new StreamHttpClient(timeout: 5.0),
                $this->config['discord']['webhook_url'] ?? '',
            );

            $dispatcher = $this->resolve(\Symfony\Contracts\EventDispatcher\EventDispatcherInterface::class);
            if ($dispatcher instanceof EventDispatcherInterface) {
                $dispatcher->addListener(LeadEnrichedEvent::class, new LeadEnrichedSubscriber($etm, $notifier));
            }

            $enrichmentService = new EnrichmentService(
                $etm,
                new StreamHttpClient(),
                $this->config['pipeline']['northcloud_url'] ?? 'http://localhost:8090',
                $this->config['pipeline']['api_key'] ?? '',
                $this->config['app']['url'] ?? 'http://localhost:8080',
            );

            $receiver = new EnrichmentReceiver($etm, $dispatcher);

            $this->enrichmentController = new EnrichmentController(
                $etm, $enrichmentService, $receiver, $this->config['pipeline']['api_key'] ?? '',
            );
        }

        return $this->enrichmentController;
    }

    private function resolveDefaultBrandId(EntityTypeManager $etm): int
    {
        $slug = $this->config['pipeline']['default_brand'] ?? 'northops';
        try {
            $ids = $etm->getStorage('brand')->getQuery()->condition('slug', $slug)->execute();
            if ($ids !== []) {
                return (int) reset($ids);
            }
        } catch (\Throwable) {}

        return 1;
    }

    private function buildLeadManager(EntityTypeManager $etm, DiscordNotifier $notifier): LeadManager
    {
        $leadCreatedSubscriber = new \App\Domain\Pipeline\EventSubscriber\LeadCreatedSubscriber($etm, $notifier);
        $stageChangedSubscriber = new \App\Domain\Pipeline\EventSubscriber\StageChangedSubscriber($etm, $notifier);
        return new LeadManager($etm, $leadCreatedSubscriber, $stageChangedSubscriber);
    }

    public function routes(WaaseyaaRouter $router, ?EntityTypeManager $entityTypeManager = null): void
    {
        // Signal ingestion
        $router->addRoute(
            'api.signals.ingest',
            RouteBuilder::create('/api/signals')
                ->controller(fn () => $this->signalController()->ingest(Request::createFromGlobals()))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // Unmatched signals
        $router->addRoute(
            'api.signals.unmatched',
            RouteBuilder::create('/api/signals/unmatched')
                ->controller(fn () => $this->signalController()->listUnmatched(Request::createFromGlobals()))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // Request enrichment (pull)
        $router->addRoute(
            'api.leads.enrich',
            RouteBuilder::create('/api/leads/{id}/enrich')
                ->controller(fn (string $id) => $this->enrichmentController()->requestEnrichment(Request::createFromGlobals(), $id))
                ->requireAuthentication()
                ->methods('POST')
                ->build(),
        );

        // Receive enrichment (push)
        $router->addRoute(
            'api.leads.enrichment.receive',
            RouteBuilder::create('/api/leads/{id}/enrichment')
                ->controller(fn (string $id) => $this->enrichmentController()->receiveEnrichment(Request::createFromGlobals(), $id))
                ->allowAll()
                ->methods('POST')
                ->build(),
        );

        // List signals for lead
        $router->addRoute(
            'api.leads.signals',
            RouteBuilder::create('/api/leads/{id}/signals')
                ->controller(fn (string $id) => $this->enrichmentController()->listSignals($id))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );

        // List enrichments for lead
        $router->addRoute(
            'api.leads.enrichments',
            RouteBuilder::create('/api/leads/{id}/enrichments')
                ->controller(fn (string $id) => $this->enrichmentController()->listEnrichments($id))
                ->requireAuthentication()
                ->methods('GET')
                ->build(),
        );
    }
}
```

- [ ] **Step 3: Register provider in composer.json**

In `composer.json`, add `"App\\Provider\\SignalServiceProvider"` to the `extra.waaseyaa.providers` array:

```json
"providers": [
    "Waaseyaa\\Mail\\MailServiceProvider",
    "App\\Provider\\AppServiceProvider",
    "App\\Provider\\PipelineServiceProvider",
    "App\\Provider\\SignalServiceProvider"
]
```

- [ ] **Step 4: Run full suite**

Run: `./vendor/bin/phpunit`
Expected: OK (all tests)

- [ ] **Step 5: Commit**

```bash
git add src/Provider/SignalServiceProvider.php config/waaseyaa.php composer.json
git commit -m "feat: implement SignalServiceProvider with all signal/enrichment routes (#118)"
```

---

### Task 13: Integration Smoke Test

- [ ] **Step 1: Start dev server**

```bash
kill $(lsof -i :8080 -t) 2>/dev/null; php -S localhost:8080 -t public public/router.php &
```

- [ ] **Step 2: Test signal ingestion endpoint**

```bash
curl -s -X POST http://localhost:8080/api/signals \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: $(grep -oP "(?<=PIPELINE_API_KEY=).+" .env 2>/dev/null || echo 'test-key')" \
  -d '{"signals":[{"signal_type":"rfp","external_id":"smoke-test-1","source":"test","label":"Smoke Test Signal","strength":80,"organization_name":"Test Corp"}]}'
```

Expected: 201 with `{"jsonapi":{"version":"1.1"},"data":{"ingested":1,...}}`

- [ ] **Step 3: Test enrichment receive endpoint**

```bash
curl -s -X POST http://localhost:8080/api/leads/1/enrichment \
  -H "Content-Type: application/json" \
  -H "X-Api-Key: $(grep -oP "(?<=PIPELINE_API_KEY=).+" .env 2>/dev/null || echo 'test-key')" \
  -d '{"provider":"test","enrichment_type":"company_intel","data":{"test":true},"confidence":0.9}'
```

Expected: 201 or 404 (if lead 1 doesn't exist, that's expected and correct)

- [ ] **Step 4: Run full test suite one final time**

```bash
./vendor/bin/phpunit
```

Expected: OK (all tests pass including new tests)

- [ ] **Step 5: Commit any fixes, then tag**

```bash
git log --oneline -15
```

Verify all commits are present and clean.
