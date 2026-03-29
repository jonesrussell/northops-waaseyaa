# Lead Pipeline Kanban Board Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a kanban board for the NorthOps lead pipeline in the Waaseyaa admin SPA, powered entirely by the surface API.

**Architecture:** Framework gets three generic primitives (list filtering/sorting, custom action handlers, pipeline page). NorthOps registers lead-specific actions and gets a kanban board. All data flows through the surface transport adapter — no direct REST API calls.

**Tech Stack:** PHP 8.4 (Waaseyaa framework), TypeScript/Vue 3 (Nuxt admin SPA), HTML5 drag-and-drop, Vitest, PHPUnit

**Repos:** `waaseyaa/framework` (Tasks 1-5), `jonesrussell/northops-waaseyaa` (Tasks 6-8)

---

## File Map

### waaseyaa/framework — new files

| File | Responsibility |
|------|---------------|
| `packages/admin-surface/src/Query/SurfaceFilterOperator.php` | Canonical operator enum |
| `packages/admin-surface/src/Query/SurfaceQuery.php` | Parsed query value object |
| `packages/admin-surface/src/Query/SurfaceQueryParser.php` | Request → SurfaceQuery |
| `packages/admin-surface/src/Action/SurfaceActionHandler.php` | Custom action interface |
| `packages/admin/app/composables/useEntityPipeline.ts` | Pipeline state + API |
| `packages/admin/app/components/pipeline/PipelineCard.vue` | Card presentation |
| `packages/admin/app/components/pipeline/PipelineColumn.vue` | Column with drop zone |
| `packages/admin/app/components/pipeline/EntityViewNav.vue` | List/Pipeline tab bar |
| `packages/admin/app/pages/[entityType]/pipeline.vue` | Kanban board page |

### waaseyaa/framework — modified files

| File | Change |
|------|--------|
| `packages/admin-surface/src/Host/GenericAdminSurfaceHost.php` | Add filtered list + action dispatch |
| `packages/admin/app/contracts/transport.ts` | Add `runAction` to `TransportAdapter` |
| `packages/admin/app/composables/useEntity.ts` | Expose `runAction` |
| `packages/admin/app/adapters/JsonApiTransportAdapter.ts` | Stub `runAction` |

### jonesrussell/northops-waaseyaa — new files

| File | Responsibility |
|------|---------------|
| `src/Surface/LeadSurfaceHost.php` | Custom host with lead actions |
| `src/Surface/Action/LeadTransitionStageAction.php` | Stage transition handler |
| `src/Surface/Action/LeadQualifyAction.php` | AI qualification handler |
| `src/Surface/Action/LeadBoardConfigAction.php` | Board config handler |

### jonesrussell/northops-waaseyaa — modified files

| File | Change |
|------|--------|
| `src/Provider/AppServiceProvider.php` | Wire `LeadSurfaceHost` instead of generic |

---

## Task 1: SurfaceFilterOperator enum and SurfaceQuery value object

**Repo:** waaseyaa/framework
**Issue:** waaseyaa/framework#752
**Files:**
- Create: `packages/admin-surface/src/Query/SurfaceFilterOperator.php`
- Create: `packages/admin-surface/src/Query/SurfaceQuery.php`
- Test: `packages/admin-surface/tests/Unit/Query/SurfaceQueryTest.php`

- [ ] **Step 1: Write the test for SurfaceFilterOperator**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\Query\SurfaceFilterOperator;

#[CoversClass(SurfaceFilterOperator::class)]
final class SurfaceFilterOperatorTest extends TestCase
{
    #[Test]
    public function from_string_returns_operator_for_valid_name(): void
    {
        $this->assertSame(SurfaceFilterOperator::EQUALS, SurfaceFilterOperator::fromString('EQUALS'));
        $this->assertSame(SurfaceFilterOperator::IN, SurfaceFilterOperator::fromString('IN'));
        $this->assertSame(SurfaceFilterOperator::CONTAINS, SurfaceFilterOperator::fromString('CONTAINS'));
    }

    #[Test]
    public function from_string_is_case_insensitive(): void
    {
        $this->assertSame(SurfaceFilterOperator::EQUALS, SurfaceFilterOperator::fromString('equals'));
        $this->assertSame(SurfaceFilterOperator::NOT_EQUALS, SurfaceFilterOperator::fromString('not_equals'));
    }

    #[Test]
    public function from_string_returns_null_for_unknown(): void
    {
        $this->assertNull(SurfaceFilterOperator::fromString('LIKE'));
        $this->assertNull(SurfaceFilterOperator::fromString(''));
    }

    #[Test]
    public function to_sql_operator_returns_correct_sql(): void
    {
        $this->assertSame('=', SurfaceFilterOperator::EQUALS->toSqlOperator());
        $this->assertSame('!=', SurfaceFilterOperator::NOT_EQUALS->toSqlOperator());
        $this->assertSame('IN', SurfaceFilterOperator::IN->toSqlOperator());
        $this->assertSame('LIKE', SurfaceFilterOperator::CONTAINS->toSqlOperator());
        $this->assertSame('>', SurfaceFilterOperator::GT->toSqlOperator());
        $this->assertSame('<', SurfaceFilterOperator::LT->toSqlOperator());
        $this->assertSame('>=', SurfaceFilterOperator::GTE->toSqlOperator());
        $this->assertSame('<=', SurfaceFilterOperator::LTE->toSqlOperator());
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/Unit/Query/SurfaceFilterOperatorTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement SurfaceFilterOperator**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Query;

enum SurfaceFilterOperator: string
{
    case EQUALS = 'EQUALS';
    case NOT_EQUALS = 'NOT_EQUALS';
    case IN = 'IN';
    case CONTAINS = 'CONTAINS';
    case GT = 'GT';
    case LT = 'LT';
    case GTE = 'GTE';
    case LTE = 'LTE';

    public static function fromString(string $name): ?self
    {
        return self::tryFrom(strtoupper($name));
    }

    public function toSqlOperator(): string
    {
        return match ($this) {
            self::EQUALS => '=',
            self::NOT_EQUALS => '!=',
            self::IN => 'IN',
            self::CONTAINS => 'LIKE',
            self::GT => '>',
            self::LT => '<',
            self::GTE => '>=',
            self::LTE => '<=',
        };
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/Unit/Query/SurfaceFilterOperatorTest.php`
Expected: PASS

- [ ] **Step 5: Write the test for SurfaceQuery**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AdminSurface\Query\SurfaceFilterOperator;
use Waaseyaa\AdminSurface\Query\SurfaceQuery;

#[CoversClass(SurfaceQuery::class)]
final class SurfaceQueryTest extends TestCase
{
    #[Test]
    public function empty_query_has_defaults(): void
    {
        $query = new SurfaceQuery();

        $this->assertSame([], $query->filters);
        $this->assertNull($query->sortField);
        $this->assertSame('ASC', $query->sortDirection);
        $this->assertSame(0, $query->offset);
        $this->assertSame(50, $query->limit);
    }

    #[Test]
    public function constructor_accepts_all_parameters(): void
    {
        $filters = [
            ['field' => 'stage', 'operator' => SurfaceFilterOperator::EQUALS, 'value' => 'lead'],
        ];
        $query = new SurfaceQuery(
            filters: $filters,
            sortField: 'created_at',
            sortDirection: 'DESC',
            offset: 10,
            limit: 25,
        );

        $this->assertCount(1, $query->filters);
        $this->assertSame('stage', $query->filters[0]['field']);
        $this->assertSame(SurfaceFilterOperator::EQUALS, $query->filters[0]['operator']);
        $this->assertSame('lead', $query->filters[0]['value']);
        $this->assertSame('created_at', $query->sortField);
        $this->assertSame('DESC', $query->sortDirection);
        $this->assertSame(10, $query->offset);
        $this->assertSame(25, $query->limit);
    }

    #[Test]
    public function limit_is_clamped_to_500(): void
    {
        $query = new SurfaceQuery(limit: 1000);
        $this->assertSame(500, $query->limit);
    }

    #[Test]
    public function limit_below_1_defaults_to_50(): void
    {
        $query = new SurfaceQuery(limit: 0);
        $this->assertSame(50, $query->limit);
    }
}
```

- [ ] **Step 6: Implement SurfaceQuery**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Query;

final readonly class SurfaceQuery
{
    /** @var array<array{field: string, operator: SurfaceFilterOperator, value: mixed}> */
    public array $filters;
    public ?string $sortField;
    public string $sortDirection;
    public int $offset;
    public int $limit;

    /**
     * @param array<array{field: string, operator: SurfaceFilterOperator, value: mixed}> $filters
     */
    public function __construct(
        array $filters = [],
        ?string $sortField = null,
        string $sortDirection = 'ASC',
        int $offset = 0,
        int $limit = 50,
    ) {
        $this->filters = $filters;
        $this->sortField = $sortField;
        $this->sortDirection = $sortDirection;
        $this->offset = max(0, $offset);
        $this->limit = $limit < 1 ? 50 : min($limit, 500);
    }
}
```

- [ ] **Step 7: Run tests to verify they pass**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/Unit/Query/`
Expected: All PASS

- [ ] **Step 8: Commit**

```bash
cd ~/dev/waaseyaa
git add packages/admin-surface/src/Query/ packages/admin-surface/tests/Unit/Query/
git commit -m "feat(admin-surface): add SurfaceFilterOperator enum and SurfaceQuery value object

Part of #752 — surface API list filtering and sorting."
```

---

## Task 2: SurfaceQueryParser

**Repo:** waaseyaa/framework
**Issue:** waaseyaa/framework#752
**Files:**
- Create: `packages/admin-surface/src/Query/SurfaceQueryParser.php`
- Test: `packages/admin-surface/tests/Unit/Query/SurfaceQueryParserTest.php`

- [ ] **Step 1: Write the test**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Waaseyaa\AdminSurface\Query\SurfaceFilterOperator;
use Waaseyaa\AdminSurface\Query\SurfaceQueryParser;

#[CoversClass(SurfaceQueryParser::class)]
final class SurfaceQueryParserTest extends TestCase
{
    #[Test]
    public function empty_request_returns_default_query(): void
    {
        $request = Request::create('/admin/surface/lead');
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertSame([], $query->filters);
        $this->assertNull($query->sortField);
        $this->assertSame(0, $query->offset);
        $this->assertSame(50, $query->limit);
    }

    #[Test]
    public function parses_single_filter(): void
    {
        $request = Request::create('/admin/surface/lead', 'GET', [
            'filter' => [
                'stage' => ['operator' => 'EQUALS', 'value' => 'lead'],
            ],
        ]);
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertCount(1, $query->filters);
        $this->assertSame('stage', $query->filters[0]['field']);
        $this->assertSame(SurfaceFilterOperator::EQUALS, $query->filters[0]['operator']);
        $this->assertSame('lead', $query->filters[0]['value']);
    }

    #[Test]
    public function parses_multiple_filters(): void
    {
        $request = Request::create('/admin/surface/lead', 'GET', [
            'filter' => [
                'stage' => ['operator' => 'IN', 'value' => 'lead,qualified'],
                'sector' => ['operator' => 'EQUALS', 'value' => 'IT'],
            ],
        ]);
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertCount(2, $query->filters);
    }

    #[Test]
    public function ignores_invalid_operator(): void
    {
        $request = Request::create('/admin/surface/lead', 'GET', [
            'filter' => [
                'stage' => ['operator' => 'LIKE', 'value' => 'lead'],
            ],
        ]);
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertSame([], $query->filters);
    }

    #[Test]
    public function parses_sort_ascending(): void
    {
        $request = Request::create('/admin/surface/lead', 'GET', [
            'sort' => 'created_at',
        ]);
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertSame('created_at', $query->sortField);
        $this->assertSame('ASC', $query->sortDirection);
    }

    #[Test]
    public function parses_sort_descending_with_minus_prefix(): void
    {
        $request = Request::create('/admin/surface/lead', 'GET', [
            'sort' => '-stage_changed_at',
        ]);
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertSame('stage_changed_at', $query->sortField);
        $this->assertSame('DESC', $query->sortDirection);
    }

    #[Test]
    public function parses_pagination(): void
    {
        $request = Request::create('/admin/surface/lead', 'GET', [
            'page' => ['offset' => '10', 'limit' => '25'],
        ]);
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertSame(10, $query->offset);
        $this->assertSame(25, $query->limit);
    }

    #[Test]
    public function parses_bracket_style_pagination(): void
    {
        $request = Request::create('/admin/surface/lead?page%5Boffset%5D=5&page%5Blimit%5D=100');
        $query = SurfaceQueryParser::fromRequest($request);

        $this->assertSame(5, $query->offset);
        $this->assertSame(100, $query->limit);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/Unit/Query/SurfaceQueryParserTest.php`
Expected: FAIL — class not found

- [ ] **Step 3: Implement SurfaceQueryParser**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Query;

use Symfony\Component\HttpFoundation\Request;

final class SurfaceQueryParser
{
    public static function fromRequest(Request $request): SurfaceQuery
    {
        $filters = self::parseFilters($request);
        [$sortField, $sortDirection] = self::parseSort($request);
        [$offset, $limit] = self::parsePagination($request);

        return new SurfaceQuery(
            filters: $filters,
            sortField: $sortField,
            sortDirection: $sortDirection,
            offset: $offset,
            limit: $limit,
        );
    }

    /**
     * @return array<array{field: string, operator: SurfaceFilterOperator, value: mixed}>
     */
    private static function parseFilters(Request $request): array
    {
        $raw = $request->query->all('filter');
        if (!is_array($raw)) {
            return [];
        }

        $filters = [];
        foreach ($raw as $field => $condition) {
            if (!is_array($condition) || !isset($condition['operator'], $condition['value'])) {
                continue;
            }
            $operator = SurfaceFilterOperator::fromString($condition['operator']);
            if ($operator === null) {
                continue;
            }
            $filters[] = [
                'field' => (string) $field,
                'operator' => $operator,
                'value' => $condition['value'],
            ];
        }

        return $filters;
    }

    /**
     * @return array{?string, string}
     */
    private static function parseSort(Request $request): array
    {
        $sort = $request->query->getString('sort');
        if ($sort === '') {
            return [null, 'ASC'];
        }
        if (str_starts_with($sort, '-')) {
            return [substr($sort, 1), 'DESC'];
        }

        return [$sort, 'ASC'];
    }

    /**
     * @return array{int, int}
     */
    private static function parsePagination(Request $request): array
    {
        $page = $request->query->all('page');
        if (!is_array($page)) {
            $page = [];
        }

        $offset = max(0, (int) ($page['offset'] ?? 0));
        $limit = (int) ($page['limit'] ?? 50);

        return [$offset, $limit];
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/Unit/Query/`
Expected: All PASS

- [ ] **Step 5: Commit**

```bash
cd ~/dev/waaseyaa
git add packages/admin-surface/src/Query/SurfaceQueryParser.php packages/admin-surface/tests/Unit/Query/SurfaceQueryParserTest.php
git commit -m "feat(admin-surface): add SurfaceQueryParser for request-to-query parsing

Part of #752 — surface API list filtering and sorting."
```

---

## Task 3: Wire filtering and sorting into GenericAdminSurfaceHost::list()

**Repo:** waaseyaa/framework
**Issue:** waaseyaa/framework#752
**Files:**
- Modify: `packages/admin-surface/src/Host/GenericAdminSurfaceHost.php` (the `list()` method)
- Modify: `packages/admin-surface/src/Host/AbstractAdminSurfaceHost.php` (the `handleList()` method to pass Request)
- Test: `packages/admin-surface/tests/Unit/Host/GenericAdminSurfaceHostTest.php` (extend)

- [ ] **Step 1: Write the failing test for filtered list**

Add to `GenericAdminSurfaceHostTest.php`:

```php
#[Test]
public function list_applies_equals_filter(): void
{
    $entity1 = $this->createEntityStub('1', 'node', ['title' => 'A', 'status' => 'published']);
    $entity2 = $this->createEntityStub('2', 'node', ['title' => 'B', 'status' => 'draft']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([$entity1, $entity2]);

    $etm = $this->createEntityTypeManager('node', $storage);
    $host = new GenericAdminSurfaceHost($etm);

    $query = new SurfaceQuery(
        filters: [['field' => 'status', 'operator' => SurfaceFilterOperator::EQUALS, 'value' => 'published']],
    );
    $result = $host->list('node', $query);

    $this->assertTrue($result->ok);
    $this->assertCount(1, $result->data['entities']);
    $this->assertSame('1', $result->data['entities'][0]['id']);
}

#[Test]
public function list_applies_in_filter(): void
{
    $entity1 = $this->createEntityStub('1', 'node', ['title' => 'A', 'stage' => 'lead']);
    $entity2 = $this->createEntityStub('2', 'node', ['title' => 'B', 'stage' => 'qualified']);
    $entity3 = $this->createEntityStub('3', 'node', ['title' => 'C', 'stage' => 'won']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([$entity1, $entity2, $entity3]);

    $etm = $this->createEntityTypeManager('node', $storage);
    $host = new GenericAdminSurfaceHost($etm);

    $query = new SurfaceQuery(
        filters: [['field' => 'stage', 'operator' => SurfaceFilterOperator::IN, 'value' => 'lead,qualified']],
    );
    $result = $host->list('node', $query);

    $this->assertTrue($result->ok);
    $this->assertCount(2, $result->data['entities']);
}

#[Test]
public function list_applies_sort_descending(): void
{
    $entity1 = $this->createEntityStub('1', 'node', ['title' => 'A', 'created_at' => '2026-01-01']);
    $entity2 = $this->createEntityStub('2', 'node', ['title' => 'B', 'created_at' => '2026-03-01']);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadMultiple')->willReturn([$entity1, $entity2]);

    $etm = $this->createEntityTypeManager('node', $storage);
    $host = new GenericAdminSurfaceHost($etm);

    $query = new SurfaceQuery(sortField: 'created_at', sortDirection: 'DESC');
    $result = $host->list('node', $query);

    $this->assertTrue($result->ok);
    $this->assertSame('2', $result->data['entities'][0]['id']);
    $this->assertSame('1', $result->data['entities'][1]['id']);
}
```

Note: These tests use in-memory filtering (since mocked storage doesn't support query builder). You'll need a helper method `createEntityStub` that returns an entity-like object with `get()` support. Check the existing test file for how entity stubs are created and follow that pattern.

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/Unit/Host/GenericAdminSurfaceHostTest.php --filter "list_applies"`
Expected: FAIL

- [ ] **Step 3: Modify GenericAdminSurfaceHost::list() to accept SurfaceQuery**

Change the `list()` method signature from `array $query = []` to `SurfaceQuery|array $query = []` for backward compatibility. When `$query` is an array, wrap it in a `SurfaceQuery` using pagination from the array. When it's a `SurfaceQuery`, use its filters, sort, and pagination.

The implementation strategy for filtering:
1. Load all entities via `$storage->loadMultiple()` (existing behavior — keeps working with all storage backends)
2. Apply filters in-memory by iterating entities and checking `$entity->get($field)`
3. Apply sort in-memory via `usort()`
4. Apply pagination via `array_slice()`

For the `IN` operator, split the comma-separated value string into an array and check with `in_array()`.
For `CONTAINS`, use `str_contains()` (case-insensitive via `mb_stripos`).

```php
public function list(string $type, SurfaceQuery|array $query = []): AdminSurfaceResultData
{
    if (!$this->entityTypeManager->hasDefinition($type)) {
        return AdminSurfaceResultData::error(404, 'Unknown entity type', "Type '{$type}' is not registered.");
    }

    if (is_array($query)) {
        $surfaceQuery = new SurfaceQuery(
            offset: max(0, (int) ($query['page[offset]'] ?? $query['page']['offset'] ?? 0)),
            limit: (int) ($query['page[limit]'] ?? $query['page']['limit'] ?? 50),
        );
    } else {
        $surfaceQuery = $query;
    }

    $storage = $this->entityTypeManager->getStorage($type);
    $entities = $storage->loadMultiple();

    if ($this->accessHandler !== null && $this->currentAccount !== null) {
        $entities = array_filter(
            $entities,
            fn($e) => $this->accessHandler->check($e, 'view', $this->currentAccount)->isAllowed(),
        );
    }

    $entities = array_values($entities);

    // Apply filters
    foreach ($surfaceQuery->filters as $filter) {
        $entities = $this->applyFilter($entities, $filter['field'], $filter['operator'], $filter['value']);
    }

    // Apply sort
    if ($surfaceQuery->sortField !== null) {
        $field = $surfaceQuery->sortField;
        $desc = $surfaceQuery->sortDirection === 'DESC';
        usort($entities, function ($a, $b) use ($field, $desc): int {
            $va = $a->get($field);
            $vb = $b->get($field);
            $cmp = $va <=> $vb;
            return $desc ? -$cmp : $cmp;
        });
    }

    $total = count($entities);
    $pageEntities = array_slice($entities, $surfaceQuery->offset, $surfaceQuery->limit);

    $serializer = $this->serializer();
    $surfaceEntities = [];
    foreach ($pageEntities as $entity) {
        $surfaceEntities[] = $this->jsonApiResourceToSurfaceEntity(
            $serializer->serialize($entity, $this->accessHandler, $this->currentAccount),
        );
    }

    return AdminSurfaceResultData::success([
        'entities' => $surfaceEntities,
        'total' => $total,
        'offset' => $surfaceQuery->offset,
        'limit' => $surfaceQuery->limit,
    ]);
}

/**
 * @param array<\Waaseyaa\Entity\EntityInterface> $entities
 * @return array<\Waaseyaa\Entity\EntityInterface>
 */
private function applyFilter(array $entities, string $field, SurfaceFilterOperator $operator, mixed $value): array
{
    return array_values(array_filter($entities, function ($entity) use ($field, $operator, $value): bool {
        $fieldValue = $entity->get($field);

        return match ($operator) {
            SurfaceFilterOperator::EQUALS => (string) $fieldValue === (string) $value,
            SurfaceFilterOperator::NOT_EQUALS => (string) $fieldValue !== (string) $value,
            SurfaceFilterOperator::IN => in_array((string) $fieldValue, explode(',', (string) $value), true),
            SurfaceFilterOperator::CONTAINS => mb_stripos((string) $fieldValue, (string) $value) !== false,
            SurfaceFilterOperator::GT => $fieldValue > $value,
            SurfaceFilterOperator::LT => $fieldValue < $value,
            SurfaceFilterOperator::GTE => $fieldValue >= $value,
            SurfaceFilterOperator::LTE => $fieldValue <= $value,
        };
    }));
}
```

- [ ] **Step 4: Update handleList() in AbstractAdminSurfaceHost to parse query from Request**

Read `AbstractAdminSurfaceHost.php` and find the `handleList()` method. It currently passes `$request->query->all()` as the `$query` array to `$this->list()`. Change it to pass `SurfaceQueryParser::fromRequest($request)` instead.

- [ ] **Step 5: Run all admin-surface tests**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/`
Expected: All PASS (existing + new)

- [ ] **Step 6: Commit**

```bash
cd ~/dev/waaseyaa
git add packages/admin-surface/src/Host/ packages/admin-surface/tests/Unit/Host/
git commit -m "feat(admin-surface): wire filtering and sorting into GenericAdminSurfaceHost::list()

Closes #752 — surface API now supports filter[field][operator]=value and sort params."
```

---

## Task 4: SurfaceActionHandler interface and custom action dispatch

**Repo:** waaseyaa/framework
**Issue:** waaseyaa/framework#753
**Files:**
- Create: `packages/admin-surface/src/Action/SurfaceActionHandler.php`
- Modify: `packages/admin-surface/src/Host/GenericAdminSurfaceHost.php` (`action()` method)
- Test: `packages/admin-surface/tests/Unit/Host/GenericAdminSurfaceHostTest.php` (extend)

- [ ] **Step 1: Write the test for custom action dispatch**

Add to `GenericAdminSurfaceHostTest.php`:

```php
#[Test]
public function action_dispatches_to_custom_handler(): void
{
    $handler = new class implements SurfaceActionHandler {
        public function handle(string $type, array $payload): AdminSurfaceResultData
        {
            return AdminSurfaceResultData::success(['custom' => true, 'received' => $payload]);
        }
    };

    $storage = $this->createMock(EntityStorageInterface::class);
    $etm = $this->createEntityTypeManager('lead', $storage);

    $host = new class($etm, $handler) extends GenericAdminSurfaceHost {
        public function __construct(EntityTypeManager $etm, private SurfaceActionHandler $testHandler)
        {
            parent::__construct($etm);
        }

        protected array $actions = [];

        public function resolveActions(): void
        {
            $this->actions['my-action'] = $this->testHandler;
        }
    };
    $host->resolveActions();

    $result = $host->action('lead', 'my-action', ['key' => 'val']);

    $this->assertTrue($result->ok);
    $this->assertTrue($result->data['custom']);
    $this->assertSame(['key' => 'val'], $result->data['received']);
}

#[Test]
public function action_returns_400_for_unknown_action(): void
{
    $storage = $this->createMock(EntityStorageInterface::class);
    $etm = $this->createEntityTypeManager('lead', $storage);
    $host = new GenericAdminSurfaceHost($etm);

    $result = $host->action('lead', 'nonexistent');

    $this->assertFalse($result->ok);
    $this->assertSame(400, $result->error['status']);
}

#[Test]
public function custom_action_takes_precedence_over_unknown(): void
{
    // Verify that built-in actions (create, update, delete, schema) still work
    // by checking that 'schema' is not intercepted by custom actions
    $storage = $this->createMock(EntityStorageInterface::class);
    $etm = $this->createEntityTypeManager('node', $storage);
    $host = new GenericAdminSurfaceHost($etm);

    // schema is a built-in — should not return 400
    $result = $host->action('node', 'schema');
    // It may fail for other reasons (no schema presenter), but NOT with "Unknown action"
    $this->assertNotSame('Unknown action', $result->error['title'] ?? '');
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/Unit/Host/GenericAdminSurfaceHostTest.php --filter "action_dispatches|action_returns_400|custom_action"`
Expected: FAIL — interface not found

- [ ] **Step 3: Create SurfaceActionHandler interface**

```php
<?php

declare(strict_types=1);

namespace Waaseyaa\AdminSurface\Action;

use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;

interface SurfaceActionHandler
{
    /**
     * Handle a custom surface action.
     *
     * @param string $type The entity type ID
     * @param array<string, mixed> $payload The request payload
     */
    public function handle(string $type, array $payload): AdminSurfaceResultData;
}
```

- [ ] **Step 4: Modify GenericAdminSurfaceHost::action() to check custom actions**

Add a `protected array $actions = [];` property to the class. Modify the `action()` method to check `$this->actions` before the built-in `match`:

```php
public function action(string $type, string $action, array $payload = []): AdminSurfaceResultData
{
    if (!$this->entityTypeManager->hasDefinition($type)) {
        return AdminSurfaceResultData::error(404, 'Unknown entity type', "Type '{$type}' is not registered.");
    }

    // Check custom actions first
    if (isset($this->actions[$action])) {
        $handler = $this->actions[$action];
        if ($handler instanceof SurfaceActionHandler) {
            return $handler->handle($type, $payload);
        }
    }

    return match ($action) {
        'schema' => $this->handleSchema($type),
        'create' => $this->handleCreate($type, $payload),
        'update' => $this->handleUpdate($type, $payload),
        'delete' => $this->handleDelete($type, $payload),
        default => AdminSurfaceResultData::error(400, 'Unknown action', "Action '{$action}' is not supported."),
    };
}
```

- [ ] **Step 5: Run all admin-surface tests**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/`
Expected: All PASS

- [ ] **Step 6: Commit**

```bash
cd ~/dev/waaseyaa
git add packages/admin-surface/src/Action/ packages/admin-surface/src/Host/GenericAdminSurfaceHost.php packages/admin-surface/tests/
git commit -m "feat(admin-surface): add SurfaceActionHandler interface and custom action dispatch

Closes #753 — hosts can now register custom actions via protected \$actions array."
```

---

## Task 5: Add runAction to TransportAdapter contract and useEntity composable

**Repo:** waaseyaa/framework
**Issue:** waaseyaa/framework#754
**Files:**
- Modify: `packages/admin/app/contracts/transport.ts`
- Modify: `packages/admin/app/composables/useEntity.ts`
- Modify: `packages/admin/app/adapters/JsonApiTransportAdapter.ts`

- [ ] **Step 1: Add runAction to TransportAdapter interface**

Read `packages/admin/app/contracts/transport.ts` and add `runAction` to the interface:

```ts
export interface TransportAdapter {
  list(type: string, query?: ListQuery): Promise<ListResult>
  get(type: string, id: string): Promise<EntityResource>
  create(type: string, attributes: Record<string, any>): Promise<EntityResource>
  update(type: string, id: string, attributes: Record<string, any>): Promise<EntityResource>
  remove(type: string, id: string): Promise<void>
  schema(type: string): Promise<EntitySchema>
  search(type: string, field: string, query: string, limit?: number): Promise<EntityResource[]>
  runAction(type: string, action: string, payload?: Record<string, unknown>): Promise<unknown>
}
```

- [ ] **Step 2: Add runAction stub to JsonApiTransportAdapter**

Read `packages/admin/app/adapters/JsonApiTransportAdapter.ts`. Add a `runAction` method that throws (JSON:API adapter doesn't support surface actions):

```ts
async runAction(_type: string, _action: string, _payload?: Record<string, unknown>): Promise<unknown> {
  throw new TransportError(501, 'Not Implemented', 'runAction is not supported by the JSON:API transport adapter')
}
```

- [ ] **Step 3: Add runAction to useEntity composable**

Read `packages/admin/app/composables/useEntity.ts`. Add the `runAction` function and include it in the return:

```ts
async function runAction(type: string, action: string, payload?: Record<string, unknown>): Promise<unknown> {
  return transport.runAction(type, action, payload)
}

return { list, get, create, update, remove, search, runAction }
```

- [ ] **Step 4: Run admin SPA tests**

Run: `cd ~/dev/waaseyaa/packages/admin && npm test`
Expected: All PASS (existing tests should not break — runAction is additive)

- [ ] **Step 5: Commit**

```bash
cd ~/dev/waaseyaa
git add packages/admin/app/contracts/transport.ts packages/admin/app/composables/useEntity.ts packages/admin/app/adapters/JsonApiTransportAdapter.ts packages/admin/app/adapters/AdminSurfaceTransportAdapter.ts
git commit -m "feat(admin): add runAction to TransportAdapter contract and useEntity composable

Part of #754 — pipeline composable needs runAction to call custom surface actions."
```

---

## Task 6: NorthOps LeadSurfaceHost and custom action handlers

**Repo:** jonesrussell/northops-waaseyaa
**Issue:** jonesrussell/northops-waaseyaa#76
**Files:**
- Create: `src/Surface/Action/LeadBoardConfigAction.php`
- Create: `src/Surface/Action/LeadTransitionStageAction.php`
- Create: `src/Surface/Action/LeadQualifyAction.php`
- Create: `src/Surface/LeadSurfaceHost.php`
- Modify: `src/Provider/AppServiceProvider.php`
- Test: `tests/Unit/Surface/Action/LeadBoardConfigActionTest.php`
- Test: `tests/Unit/Surface/Action/LeadTransitionStageActionTest.php`

- [ ] **Step 1: Write the test for LeadBoardConfigAction**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Surface\Action;

use App\Surface\Action\LeadBoardConfigAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(LeadBoardConfigAction::class)]
final class LeadBoardConfigActionTest extends TestCase
{
    #[Test]
    public function handle_returns_stages_and_transitions(): void
    {
        $action = new LeadBoardConfigAction();
        $result = $action->handle('lead', []);

        $this->assertTrue($result->ok);
        $this->assertSame(
            ['lead', 'qualified', 'contacted', 'proposal', 'negotiation', 'won', 'lost'],
            $result->data['stages'],
        );
        $this->assertSame(['qualified', 'lost'], $result->data['transitions']['lead']);
        $this->assertSame(['won', 'lost'], $result->data['transitions']['negotiation']);
        $this->assertSame([], $result->data['transitions']['won']);
        $this->assertArrayHasKey('sources', $result->data);
        $this->assertArrayHasKey('sectors', $result->data);
    }
}
```

- [ ] **Step 2: Implement LeadBoardConfigAction**

```php
<?php

declare(strict_types=1);

namespace App\Surface\Action;

use App\Domain\Pipeline\StageTransitionRules;
use Waaseyaa\AdminSurface\Action\SurfaceActionHandler;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;

final class LeadBoardConfigAction implements SurfaceActionHandler
{
    public function handle(string $type, array $payload): AdminSurfaceResultData
    {
        return AdminSurfaceResultData::success([
            'stages' => StageTransitionRules::STAGES,
            'transitions' => StageTransitionRules::TRANSITIONS,
            'sources' => StageTransitionRules::SOURCES,
            'sectors' => StageTransitionRules::SECTORS,
        ]);
    }
}
```

Note: Read `StageTransitionRules.php` to check if `STAGES`, `TRANSITIONS`, `SOURCES`, `SECTORS` are public constants. If they're private methods, refactor them to public constants first.

- [ ] **Step 3: Run test to verify it passes**

Run: `cd ~/dev/northops-waaseyaa && ./vendor/bin/phpunit tests/Unit/Surface/Action/LeadBoardConfigActionTest.php`
Expected: PASS

- [ ] **Step 4: Write the test for LeadTransitionStageAction**

```php
<?php

declare(strict_types=1);

namespace App\Tests\Unit\Surface\Action;

use App\Domain\Pipeline\StageTransitionRules;
use App\Surface\Action\LeadTransitionStageAction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\Entity\Storage\EntityStorageInterface;

#[CoversClass(LeadTransitionStageAction::class)]
final class LeadTransitionStageActionTest extends TestCase
{
    #[Test]
    public function handle_rejects_missing_id(): void
    {
        $action = $this->createAction();
        $result = $action->handle('lead', ['stage' => 'qualified']);

        $this->assertFalse($result->ok);
        $this->assertSame(400, $result->error['status']);
    }

    #[Test]
    public function handle_rejects_missing_stage(): void
    {
        $action = $this->createAction();
        $result = $action->handle('lead', ['id' => '1']);

        $this->assertFalse($result->ok);
        $this->assertSame(400, $result->error['status']);
    }

    #[Test]
    public function handle_rejects_invalid_transition(): void
    {
        $lead = $this->createLeadStub('1', 'lead');
        $action = $this->createAction($lead);
        $result = $action->handle('lead', ['id' => '1', 'stage' => 'won']);

        $this->assertFalse($result->ok);
        $this->assertSame(422, $result->error['status']);
    }

    #[Test]
    public function handle_succeeds_for_valid_transition(): void
    {
        $lead = $this->createLeadStub('1', 'lead');
        $action = $this->createAction($lead);
        $result = $action->handle('lead', ['id' => '1', 'stage' => 'qualified']);

        $this->assertTrue($result->ok);
        $this->assertSame('1', $result->data['id']);
    }

    private function createAction($lead = null): LeadTransitionStageAction
    {
        $storage = $this->createMock(EntityStorageInterface::class);
        if ($lead !== null) {
            $storage->method('load')->willReturn($lead);
            $storage->method('save')->willReturnCallback(fn($e) => $e);
        } else {
            $storage->method('load')->willReturn(null);
        }

        $etm = $this->createMock(EntityTypeManager::class);
        $etm->method('getStorage')->willReturn($storage);

        return new LeadTransitionStageAction($etm, new StageTransitionRules());
    }

    private function createLeadStub(string $id, string $stage): object
    {
        // Create a minimal stub that behaves like a Lead entity
        // Read the actual Lead entity class to match its interface
        $lead = $this->createMock(\App\Entity\Lead::class);
        $lead->method('id')->willReturn($id);
        $lead->method('get')->willReturnMap([
            ['stage', $stage],
            ['contact_email', 'test@example.com'],
            ['value', 1000],
        ]);
        return $lead;
    }
}
```

- [ ] **Step 5: Implement LeadTransitionStageAction**

```php
<?php

declare(strict_types=1);

namespace App\Surface\Action;

use App\Domain\Pipeline\StageTransitionRules;
use Waaseyaa\AdminSurface\Action\SurfaceActionHandler;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadTransitionStageAction implements SurfaceActionHandler
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly StageTransitionRules $rules,
    ) {}

    public function handle(string $type, array $payload): AdminSurfaceResultData
    {
        $id = $payload['id'] ?? null;
        $targetStage = $payload['stage'] ?? null;

        if ($id === null || $id === '') {
            return AdminSurfaceResultData::error(400, 'Missing ID', 'Payload must include an id field.');
        }
        if ($targetStage === null || $targetStage === '') {
            return AdminSurfaceResultData::error(400, 'Missing stage', 'Payload must include a stage field.');
        }

        $storage = $this->entityTypeManager->getStorage($type);
        $lead = $storage->load($id);

        if ($lead === null) {
            return AdminSurfaceResultData::error(404, 'Not found', "Lead '{$id}' does not exist.");
        }

        $currentStage = (string) $lead->get('stage');

        if (!$this->rules->canTransition($currentStage, $targetStage)) {
            return AdminSurfaceResultData::error(
                422,
                'Invalid transition',
                "Cannot transition from '{$currentStage}' to '{$targetStage}'.",
            );
        }

        $errors = $this->rules->validateTransition($currentStage, $targetStage, [
            'contact_email' => $lead->get('contact_email'),
            'value' => $lead->get('value'),
        ]);

        if ($errors !== []) {
            return AdminSurfaceResultData::error(422, 'Validation failed', implode('; ', $errors));
        }

        $lead->set('stage', $targetStage);
        $lead->set('stage_changed_at', date('c'));
        $lead->set('updated_at', date('c'));
        $storage->save([$lead]);

        return AdminSurfaceResultData::success([
            'type' => $type,
            'id' => (string) $lead->id(),
            'attributes' => $lead->toArray(),
        ]);
    }
}
```

- [ ] **Step 6: Implement LeadQualifyAction**

```php
<?php

declare(strict_types=1);

namespace App\Surface\Action;

use App\Domain\Qualification\QualificationService;
use Waaseyaa\AdminSurface\Action\SurfaceActionHandler;
use Waaseyaa\AdminSurface\Host\AdminSurfaceResultData;
use Waaseyaa\Entity\EntityTypeManager;

final class LeadQualifyAction implements SurfaceActionHandler
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly QualificationService $qualificationService,
    ) {}

    public function handle(string $type, array $payload): AdminSurfaceResultData
    {
        $id = $payload['id'] ?? null;
        if ($id === null || $id === '') {
            return AdminSurfaceResultData::error(400, 'Missing ID', 'Payload must include an id field.');
        }

        $storage = $this->entityTypeManager->getStorage($type);
        $lead = $storage->load($id);

        if ($lead === null) {
            return AdminSurfaceResultData::error(404, 'Not found', "Lead '{$id}' does not exist.");
        }

        try {
            $result = $this->qualificationService->qualify($lead);
        } catch (\Throwable $e) {
            return AdminSurfaceResultData::error(502, 'Qualification failed', $e->getMessage());
        }

        // QualificationService already saves the lead with updated fields
        // Reload to get fresh data
        $lead = $storage->load($id);

        return AdminSurfaceResultData::success([
            'type' => $type,
            'id' => (string) $lead->id(),
            'attributes' => $lead->toArray(),
        ]);
    }
}
```

- [ ] **Step 7: Create LeadSurfaceHost**

```php
<?php

declare(strict_types=1);

namespace App\Surface;

use App\Surface\Action\LeadBoardConfigAction;
use App\Surface\Action\LeadQualifyAction;
use App\Surface\Action\LeadTransitionStageAction;
use Waaseyaa\AdminSurface\Host\GenericAdminSurfaceHost;

final class LeadSurfaceHost extends GenericAdminSurfaceHost
{
    protected array $actions = [];

    public function registerActions(
        LeadTransitionStageAction $transitionStage,
        LeadQualifyAction $qualify,
        LeadBoardConfigAction $boardConfig,
    ): void {
        $this->actions = [
            'transition-stage' => $transitionStage,
            'qualify' => $qualify,
            'board-config' => $boardConfig,
        ];
    }
}
```

- [ ] **Step 8: Wire LeadSurfaceHost in AppServiceProvider**

Read `src/Provider/AppServiceProvider.php` and find where `AdminSurfaceServiceProvider::registerRoutes()` is called or where the generic host is instantiated. Replace with `LeadSurfaceHost` and call `registerActions()` with the three action handlers.

The exact wiring depends on how the service provider currently initializes the surface host. Read the file and follow the existing pattern.

- [ ] **Step 9: Run all northops tests**

Run: `cd ~/dev/northops-waaseyaa && ./vendor/bin/phpunit`
Expected: All PASS

- [ ] **Step 10: Commit**

```bash
cd ~/dev/northops-waaseyaa
git add src/Surface/ tests/Unit/Surface/ src/Provider/AppServiceProvider.php
git commit -m "feat: add LeadSurfaceHost with transition, qualify, and board-config actions

Closes #76 — custom surface actions for the lead pipeline kanban board."
```

---

## Task 7: Admin SPA pipeline composable, card component, and page

**Repo:** waaseyaa/framework
**Issue:** waaseyaa/framework#754
**Files:**
- Create: `packages/admin/app/composables/useEntityPipeline.ts`
- Create: `packages/admin/app/components/pipeline/PipelineCard.vue`
- Create: `packages/admin/app/components/pipeline/PipelineColumn.vue`
- Create: `packages/admin/app/components/pipeline/EntityViewNav.vue`
- Create: `packages/admin/app/pages/[entityType]/pipeline.vue`
- Test: `packages/admin/tests/composables/useEntityPipeline.test.ts`

- [ ] **Step 1: Write the test for useEntityPipeline**

```ts
import { describe, it, expect, vi, beforeEach } from 'vitest'

// Mock useNuxtApp to return a mock admin runtime
const mockList = vi.fn()
const mockRunAction = vi.fn()

vi.mock('#app', () => ({
  useNuxtApp: () => ({
    $admin: {
      transport: {
        list: mockList,
        runAction: mockRunAction,
      },
    },
  }),
}))

// Mock Vue reactivity
vi.mock('vue', async () => {
  const actual = await vi.importActual<typeof import('vue')>('vue')
  return { ...actual }
})

import { useEntityPipeline } from '~/composables/useEntityPipeline'

describe('useEntityPipeline', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('loadBoard fetches config and entities', async () => {
    mockRunAction.mockResolvedValueOnce({
      stages: ['lead', 'qualified', 'won'],
      transitions: { lead: ['qualified'], qualified: ['won'] },
    })
    mockList.mockResolvedValueOnce({
      data: [
        { type: 'lead', id: '1', attributes: { label: 'A', stage: 'lead' } },
        { type: 'lead', id: '2', attributes: { label: 'B', stage: 'qualified' } },
      ],
      meta: { total: 2, offset: 0, limit: 500 },
    })

    const pipeline = useEntityPipeline()
    await pipeline.loadBoard('lead')

    expect(mockRunAction).toHaveBeenCalledWith('lead', 'board-config', undefined)
    expect(mockList).toHaveBeenCalledWith('lead', expect.objectContaining({
      sort: '-stage_changed_at',
      page: { offset: 0, limit: 500 },
    }))
    expect(pipeline.config.value).toBeTruthy()
    expect(pipeline.columns.value.get('lead')).toEqual(['1'])
    expect(pipeline.columns.value.get('qualified')).toEqual(['2'])
  })

  it('moveCard calls transition-stage action', async () => {
    mockRunAction
      .mockResolvedValueOnce({
        stages: ['lead', 'qualified'],
        transitions: { lead: ['qualified'] },
      })
      .mockResolvedValueOnce({
        type: 'lead', id: '1', attributes: { label: 'A', stage: 'qualified' },
      })
    mockList.mockResolvedValueOnce({
      data: [{ type: 'lead', id: '1', attributes: { label: 'A', stage: 'lead' } }],
      meta: { total: 1, offset: 0, limit: 500 },
    })

    const pipeline = useEntityPipeline()
    await pipeline.loadBoard('lead')
    await pipeline.moveCard('lead', '1', 'qualified')

    expect(mockRunAction).toHaveBeenCalledWith('lead', 'transition-stage', { id: '1', stage: 'qualified' })
  })
})
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `cd ~/dev/waaseyaa/packages/admin && npx vitest run tests/composables/useEntityPipeline.test.ts`
Expected: FAIL — module not found

- [ ] **Step 3: Implement useEntityPipeline composable**

```ts
// packages/admin/app/composables/useEntityPipeline.ts

import type { AdminRuntime } from '../contracts/runtime'
import type { EntityResource, ListQuery } from '../contracts/transport'

export type CardDensity = 'compact' | 'standard' | 'detailed'

export interface BoardConfig {
  stages: string[]
  transitions: Record<string, string[]>
  [key: string]: unknown
}

export interface PipelineCard {
  id: string
  label: string
  stage: string
  attributes: Record<string, unknown>
}

export interface PipelineFilters {
  [field: string]: { operator: string; value: string }
}

export function useEntityPipeline() {
  const { $admin } = useNuxtApp() as unknown as { $admin: AdminRuntime }
  const transport = $admin.transport

  const cards = ref<Map<string, PipelineCard>>(new Map())
  const columns = ref<Map<string, string[]>>(new Map())
  const config = ref<BoardConfig | null>(null)
  const activeFilters = ref<PipelineFilters>({})
  const loading = ref(false)
  const error = ref<string | null>(null)

  async function loadBoard(entityType: string): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const boardConfig = await transport.runAction(entityType, 'board-config') as BoardConfig
      config.value = boardConfig

      const query: ListQuery = {
        sort: '-stage_changed_at',
        page: { offset: 0, limit: 500 },
        filter: { ...activeFilters.value },
      }

      const result = await transport.list(entityType, query)
      buildColumns(boardConfig.stages, result.data)
    } catch (e: unknown) {
      error.value = e instanceof Error ? e.message : String(e)
    } finally {
      loading.value = false
    }
  }

  function buildColumns(stages: string[], entities: EntityResource[]): void {
    const newCards = new Map<string, PipelineCard>()
    const newColumns = new Map<string, string[]>()

    for (const stage of stages) {
      newColumns.set(stage, [])
    }

    for (const entity of entities) {
      const stage = entity.attributes.stage as string
      const card: PipelineCard = {
        id: entity.id,
        label: entity.attributes.label as string ?? entity.id,
        stage,
        attributes: entity.attributes,
      }
      newCards.set(entity.id, card)
      const col = newColumns.get(stage)
      if (col) {
        col.push(entity.id)
      }
    }

    cards.value = newCards
    columns.value = newColumns
  }

  async function moveCard(entityType: string, cardId: string, toStage: string): Promise<void> {
    const card = cards.value.get(cardId)
    if (!card) return

    const fromStage = card.stage

    // Optimistic update
    card.stage = toStage
    card.attributes.stage = toStage
    const fromCol = columns.value.get(fromStage)
    const toCol = columns.value.get(toStage)
    if (fromCol) {
      const idx = fromCol.indexOf(cardId)
      if (idx !== -1) fromCol.splice(idx, 1)
    }
    if (toCol) {
      toCol.unshift(cardId)
    }

    try {
      const updated = await transport.runAction(entityType, 'transition-stage', {
        id: cardId,
        stage: toStage,
      }) as { type: string; id: string; attributes: Record<string, unknown> }

      // Update card with server response
      card.attributes = updated.attributes
      card.stage = updated.attributes.stage as string
    } catch (e: unknown) {
      // Rollback
      card.stage = fromStage
      card.attributes.stage = fromStage
      if (toCol) {
        const idx = toCol.indexOf(cardId)
        if (idx !== -1) toCol.splice(idx, 1)
      }
      if (fromCol) {
        fromCol.unshift(cardId)
      }
      error.value = e instanceof Error ? e.message : String(e)
    }
  }

  async function applyFilters(entityType: string, filters: PipelineFilters): Promise<void> {
    activeFilters.value = filters
    await loadBoard(entityType)
  }

  async function runCardAction(entityType: string, action: string, payload?: Record<string, unknown>): Promise<unknown> {
    return transport.runAction(entityType, action, payload)
  }

  return {
    cards,
    columns,
    config,
    activeFilters,
    loading,
    error,
    loadBoard,
    moveCard,
    applyFilters,
    runCardAction,
  }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `cd ~/dev/waaseyaa/packages/admin && npx vitest run tests/composables/useEntityPipeline.test.ts`
Expected: PASS

- [ ] **Step 5: Create PipelineCard.vue**

```vue
<!-- packages/admin/app/components/pipeline/PipelineCard.vue -->
<script setup lang="ts">
import type { PipelineCard, CardDensity } from '~/composables/useEntityPipeline'

const props = defineProps<{
  card: PipelineCard
  density: CardDensity
}>()

const emit = defineEmits<{
  'open-detail': [id: string]
  'run-action': [action: string, payload: Record<string, unknown>]
}>()

const attrs = computed(() => props.card.attributes)

const scoreClass = computed(() => {
  const rating = attrs.value.qualify_rating as number | undefined
  if (rating == null) return ''
  if (rating >= 70) return 'score-high'
  if (rating >= 40) return 'score-mid'
  return 'score-low'
})

const urgencyLabel = computed(() => {
  const closing = attrs.value.closing_date as string | undefined
  if (!closing) return null
  const days = Math.ceil((new Date(closing).getTime() - Date.now()) / 86400000)
  if (days < 0) return 'overdue'
  if (days === 0) return 'today'
  if (days === 1) return 'tomorrow'
  if (days <= 7) return `${days}d`
  return null
})

const formattedValue = computed(() => {
  const v = attrs.value.value as number | undefined
  if (v == null) return null
  return new Intl.NumberFormat('en-CA', { style: 'currency', currency: 'CAD', maximumFractionDigits: 0 }).format(v)
})
</script>

<template>
  <div
    class="pipeline-card"
    draggable="true"
    @click="emit('open-detail', card.id)"
    @dragstart="$event.dataTransfer?.setData('text/plain', card.id)"
  >
    <div class="card-header">
      <span class="card-label">{{ card.label }}</span>
      <span v-if="attrs.qualify_rating != null" class="card-score" :class="scoreClass">
        {{ attrs.qualify_rating }}
      </span>
    </div>

    <div v-if="attrs.company_name" class="card-company">
      {{ attrs.company_name }}
      <template v-if="density === 'detailed' && attrs.contact_name"> &mdash; {{ attrs.contact_name }}</template>
    </div>

    <div v-if="density === 'detailed' && attrs.contact_email" class="card-email">
      {{ attrs.contact_email }}
    </div>

    <div class="card-tags">
      <span v-if="attrs.source" class="tag tag-source">{{ attrs.source }}</span>
      <span v-if="density === 'detailed' && attrs.sector" class="tag tag-sector">{{ attrs.sector }}</span>
      <span v-if="formattedValue" class="card-value">{{ formattedValue }}</span>
    </div>

    <div v-if="urgencyLabel" class="card-footer">
      <span class="card-urgency" :class="{ 'urgency-critical': urgencyLabel === 'overdue' || urgencyLabel === 'today' || urgencyLabel === 'tomorrow' }">
        closes {{ urgencyLabel }}
      </span>
    </div>
  </div>
</template>

<style scoped>
.pipeline-card {
  background: var(--color-surface, #fff);
  border: 1px solid var(--color-border, #e0e0e0);
  border-radius: 6px;
  padding: 12px;
  cursor: grab;
  transition: border-color 0.15s, box-shadow 0.15s;
}
.pipeline-card:hover { border-color: var(--color-primary, #2563eb); }
.pipeline-card:active { cursor: grabbing; }
.card-header { display: flex; justify-content: space-between; align-items: start; }
.card-label { font-weight: 600; font-size: 14px; }
.card-score { font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 10px; color: #fff; }
.score-high { background: #22c55e; }
.score-mid { background: #f59e0b; }
.score-low { background: #ef4444; }
.card-company { font-size: 13px; color: var(--color-muted, #666); margin-top: 2px; }
.card-email { font-size: 12px; color: var(--color-muted, #999); margin-top: 1px; }
.card-tags { display: flex; gap: 6px; margin-top: 8px; align-items: center; flex-wrap: wrap; }
.tag { font-size: 11px; padding: 1px 6px; border-radius: 3px; }
.tag-source { background: #e8f0fe; color: #1a56db; }
.tag-sector { background: #f3e8ff; color: #7c3aed; }
.card-value { font-size: 12px; color: var(--color-muted, #666); }
.card-footer { margin-top: 6px; }
.card-urgency { font-size: 11px; color: var(--color-muted, #999); }
.urgency-critical { color: var(--color-danger, #c00); }
</style>
```

- [ ] **Step 6: Create PipelineColumn.vue**

```vue
<!-- packages/admin/app/components/pipeline/PipelineColumn.vue -->
<script setup lang="ts">
import type { PipelineCard, CardDensity, BoardConfig } from '~/composables/useEntityPipeline'

const props = defineProps<{
  stage: string
  cardIds: string[]
  cards: Map<string, PipelineCard>
  config: BoardConfig
  density: CardDensity
}>()

const emit = defineEmits<{
  drop: [cardId: string, toStage: string]
  'open-detail': [id: string]
  'run-action': [action: string, payload: Record<string, unknown>]
}>()

const dragOver = ref(false)

function canDrop(cardId: string): boolean {
  const card = props.cards.get(cardId)
  if (!card) return false
  const allowed = props.config.transitions[card.stage] ?? []
  return allowed.includes(props.stage)
}

function onDragOver(e: DragEvent) {
  const cardId = e.dataTransfer?.types.includes('text/plain') ? 'pending' : ''
  if (cardId) {
    e.preventDefault()
    dragOver.value = true
  }
}

function onDragLeave() {
  dragOver.value = false
}

function onDrop(e: DragEvent) {
  dragOver.value = false
  const cardId = e.dataTransfer?.getData('text/plain')
  if (cardId && canDrop(cardId)) {
    emit('drop', cardId, props.stage)
  }
}

const stageCards = computed(() =>
  props.cardIds.map(id => props.cards.get(id)).filter(Boolean) as PipelineCard[]
)
</script>

<template>
  <div
    class="pipeline-column"
    :class="{ 'drag-over': dragOver }"
    @dragover="onDragOver"
    @dragleave="onDragLeave"
    @drop="onDrop"
  >
    <div class="column-header">
      <span class="column-title">{{ stage }}</span>
      <span class="column-count">{{ cardIds.length }}</span>
    </div>
    <div class="column-body">
      <PipelineCard
        v-for="card in stageCards"
        :key="card.id"
        :card="card"
        :density="density"
        @open-detail="emit('open-detail', $event)"
        @run-action="(a, p) => emit('run-action', a, p)"
      />
      <div v-if="stageCards.length === 0" class="column-empty">No leads</div>
    </div>
  </div>
</template>

<style scoped>
.pipeline-column {
  min-width: 280px;
  max-width: 320px;
  flex-shrink: 0;
  background: var(--color-bg, #f8f9fa);
  border-radius: 8px;
  display: flex;
  flex-direction: column;
}
.pipeline-column.drag-over {
  outline: 2px dashed var(--color-primary, #2563eb);
  outline-offset: -2px;
}
.column-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 12px 16px;
  font-weight: 600;
  font-size: 14px;
  text-transform: capitalize;
  border-bottom: 1px solid var(--color-border, #e0e0e0);
}
.column-count {
  font-size: 12px;
  font-weight: 500;
  background: var(--color-border, #e0e0e0);
  padding: 1px 8px;
  border-radius: 10px;
}
.column-body {
  padding: 8px;
  display: flex;
  flex-direction: column;
  gap: 8px;
  flex: 1;
  overflow-y: auto;
}
.column-empty {
  font-size: 13px;
  color: var(--color-muted, #999);
  text-align: center;
  padding: 20px;
}
</style>
```

- [ ] **Step 7: Create EntityViewNav.vue**

```vue
<!-- packages/admin/app/components/pipeline/EntityViewNav.vue -->
<script setup lang="ts">
import { useEntity } from '~/composables/useEntity'

const props = defineProps<{
  entityType: string
}>()

const hasPipeline = ref(false)

onMounted(async () => {
  try {
    const { runAction } = useEntity()
    await runAction(props.entityType, 'board-config')
    hasPipeline.value = true
  } catch {
    hasPipeline.value = false
  }
})

const route = useRoute()
const isListActive = computed(() => !route.path.endsWith('/pipeline'))
const isPipelineActive = computed(() => route.path.endsWith('/pipeline'))
</script>

<template>
  <nav v-if="hasPipeline" class="entity-view-nav">
    <NuxtLink :to="`/${entityType}`" class="nav-tab" :class="{ active: isListActive }">
      List
    </NuxtLink>
    <NuxtLink :to="`/${entityType}/pipeline`" class="nav-tab" :class="{ active: isPipelineActive }">
      Pipeline
    </NuxtLink>
  </nav>
</template>

<style scoped>
.entity-view-nav {
  display: flex;
  gap: 0;
  margin-bottom: 16px;
  border-bottom: 1px solid var(--color-border, #e0e0e0);
}
.nav-tab {
  padding: 8px 16px;
  font-size: 14px;
  font-weight: 500;
  text-decoration: none;
  color: var(--color-muted, #666);
  border-bottom: 2px solid transparent;
  transition: color 0.15s, border-color 0.15s;
}
.nav-tab:hover { color: var(--color-text, #111); }
.nav-tab.active {
  color: var(--color-primary, #2563eb);
  border-bottom-color: var(--color-primary, #2563eb);
}
</style>
```

- [ ] **Step 8: Create the pipeline page**

```vue
<!-- packages/admin/app/pages/[entityType]/pipeline.vue -->
<script setup lang="ts">
import { useEntityPipeline } from '~/composables/useEntityPipeline'
import { useLanguage } from '~/composables/useLanguage'

const route = useRoute()
const entityType = route.params.entityType as string
const { t, entityLabel } = useLanguage()
const config = useRuntimeConfig()

const pipeline = useEntityPipeline()

useHead({ title: computed(() => `${entityLabel(entityType)} Pipeline | ${config.public.appName}`) })

const activeStages = computed(() => {
  if (!pipeline.config.value) return []
  return pipeline.config.value.stages.filter((s: string) => s !== 'won' && s !== 'lost')
})

onMounted(async () => {
  await pipeline.loadBoard(entityType)
})

function onDrop(cardId: string, toStage: string) {
  pipeline.moveCard(entityType, cardId, toStage)
}

function onOpenDetail(id: string) {
  navigateTo(`/${entityType}/${id}`)
}
</script>

<template>
  <div>
    <div class="page-header">
      <h1>{{ entityLabel(entityType) }} Pipeline</h1>
    </div>

    <EntityViewNav :entity-type="entityType" />

    <div v-if="pipeline.loading.value" class="loading">Loading...</div>
    <div v-else-if="pipeline.error.value" class="error">{{ pipeline.error.value }}</div>

    <div v-else-if="pipeline.config.value" class="pipeline-board">
      <PipelineColumn
        v-for="stage in activeStages"
        :key="stage"
        :stage="stage"
        :card-ids="pipeline.columns.value.get(stage) ?? []"
        :cards="pipeline.cards.value"
        :config="pipeline.config.value"
        density="detailed"
        @drop="onDrop"
        @open-detail="onOpenDetail"
      />
    </div>

    <div v-else class="not-configured">
      Pipeline is not configured for this entity type.
    </div>
  </div>
</template>

<style scoped>
.pipeline-board {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 16px;
}
.loading, .error, .not-configured {
  font-size: 14px;
  color: var(--color-muted, #666);
  padding: 40px;
  text-align: center;
}
.error { color: var(--color-danger, #c00); }
</style>
```

- [ ] **Step 9: Run admin SPA tests**

Run: `cd ~/dev/waaseyaa/packages/admin && npm test`
Expected: All PASS

- [ ] **Step 10: Commit**

```bash
cd ~/dev/waaseyaa
git add packages/admin/app/composables/useEntityPipeline.ts \
  packages/admin/app/components/pipeline/ \
  packages/admin/app/pages/\[entityType\]/pipeline.vue \
  packages/admin/tests/composables/
git commit -m "feat(admin): add pipeline page, composable, card, and column components

Closes #754 — generic kanban board for any entity type with board-config action."
```

---

## Task 8: Tag, deploy, and verify end-to-end

**Repos:** Both
**Issues:** waaseyaa/framework#752, #753, #754, jonesrussell/northops-waaseyaa#76, #77

- [ ] **Step 1: Run full framework test suite**

Run: `cd ~/dev/waaseyaa && ./vendor/bin/phpunit packages/admin-surface/tests/ && cd packages/admin && npm test`
Expected: All PHP + JS tests PASS

- [ ] **Step 2: Tag and push framework**

```bash
cd ~/dev/waaseyaa
git tag v0.1.0-alpha.79
git push origin main v0.1.0-alpha.79
```

- [ ] **Step 3: Update northops-waaseyaa composer lock**

```bash
cd ~/dev/northops-waaseyaa
composer clear-cache
composer update 'waaseyaa/*' --no-interaction
```

- [ ] **Step 4: Run northops tests**

Run: `cd ~/dev/northops-waaseyaa && ./vendor/bin/phpunit`
Expected: All PASS

- [ ] **Step 5: Commit lock file and push**

```bash
cd ~/dev/northops-waaseyaa
git add composer.lock
git commit -m "chore: update waaseyaa packages for pipeline kanban support"
git push origin main
```

- [ ] **Step 6: Wait for deploy to complete**

```bash
cd ~/dev/northops-waaseyaa && gh run watch $(gh run list --limit 1 --json databaseId -q '.[0].databaseId') --exit-status
```

- [ ] **Step 7: Verify with Playwright**

1. Navigate to `https://northops.ca/login`
2. Login with credentials
3. Navigate to `/admin/lead/pipeline`
4. Verify kanban columns render with correct stage names
5. Verify lead cards appear in correct columns with full detail (label, company, contact, score, value, source, sector)
6. Screenshot the pipeline board

- [ ] **Step 8: Commit screenshot and close issues**

```bash
cd ~/dev/northops-waaseyaa
git add northops-pipeline-verified.png
git commit -m "docs: verify lead pipeline kanban board end-to-end"
```

Close the issues:
```bash
gh issue close 76 --comment "Lead surface host deployed and verified."
gh issue close 77 --comment "E2E verification complete — pipeline board renders with drag-and-drop."
cd ~/dev/waaseyaa
gh issue close 752 --comment "Surface API filtering and sorting shipped in alpha.79."
gh issue close 753 --comment "Custom action handler extension point shipped in alpha.79."
gh issue close 754 --comment "Pipeline page, composable, and components shipped in alpha.79."
```
