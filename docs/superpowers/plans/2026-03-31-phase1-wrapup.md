# Phase 1 Wrap-Up Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close out Phase 1 by updating Waaseyaa to alpha.97, securing routes, fixing framework compliance debt, polishing the admin UI, and validating with E2E.

**Architecture:** Sequential approach — framework update first (unblocks auth), then auth wiring (security P0), framework compliance (DI/Response cleanup), UI polish, housekeeping, and E2E capstone.

**Tech Stack:** PHP 8.4, Waaseyaa framework (Symfony 7-based), PHPUnit, Playwright MCP, vanilla JS dashboard

**Spec:** `docs/superpowers/specs/2026-03-31-phase1-wrapup-design.md`

---

### Task 1: Update Waaseyaa to alpha.97

**Files:**
- Modify: `composer.lock` (auto-updated by composer)
- Modify: `composer.json` (if minimum version constraint needs bumping)

- [ ] **Step 1: Clear composer cache and update**

```bash
composer clear-cache && composer update 'waaseyaa/*'
```

Expected: All `waaseyaa/*` packages update from `0.1.0-alpha.96` to `0.1.0-alpha.97`.

- [ ] **Step 2: Verify installed version**

```bash
composer show waaseyaa/auth | grep -E '^versions'
```

Expected: `0.1.0-alpha.97`

- [ ] **Step 3: Run tests to confirm nothing broke**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 4: Commit and push**

```bash
git add composer.json composer.lock
git commit -m "chore: update waaseyaa packages to v0.1.0-alpha.97"
git push
```

CI/CD will deploy automatically.

- [ ] **Step 5: Verify login with Playwright MCP**

After CI/CD deploy completes, use Playwright MCP to:
1. Navigate to `https://northops.ca/admin/login`
2. Verify the login form renders
3. Submit credentials
4. Verify redirect to `/admin`
5. Verify session persists (page shows dashboard content, not login redirect)

---

### Task 2: Wire auth to admin routes (#55)

**Files:**
- Modify: `src/Provider/AppServiceProvider.php:245-278` (admin route definitions)
- Modify: `src/Provider/AppServiceProvider.php:285-399` (API route definitions)

The framework provides `RouteBuilder::requireAuthentication()` and `RouteBuilder::requireRole(string $role)`. Replace `->allowAll()` on protected routes.

- [ ] **Step 1: Secure admin routes**

In `src/Provider/AppServiceProvider.php`, replace `->allowAll()` with `->requireAuthentication()` on all four admin routes:

```php
// Line 249: admin.dashboard
->requireAuthentication()

// Line 258: admin.leads
->requireAuthentication()

// Line 267: admin.lead.detail
->requireAuthentication()

// Line 276: admin.settings
->requireAuthentication()
```

- [ ] **Step 2: Secure API lead routes**

Replace `->allowAll()` with `->requireAuthentication()` on all API lead routes (lines 285-382). These are:

- `api.leads.list` (line 289)
- `api.leads.create` (line 298)
- `api.leads.get` (line 316)
- `api.leads.update` (line 325)
- `api.leads.delete` (line 334)
- `api.leads.change_stage` (line 343)
- `api.leads.qualify` (line 352)
- `api.leads.activity.list` (line 361)
- `api.leads.activity.create` (line 370)
- `api.leads.attachments.list` (line 379)
- `api.brands.list` (line 388)
- `api.config` (line 397)

**Keep `->allowAll()` on:**
- `api.leads.import` (line 307) — uses API key auth via `X-Api-Key` header check inside `LeadController::importLeads()`
- All `marketing.*` routes (lines 173-238) — public pages

- [ ] **Step 3: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: All existing tests pass (unit tests don't hit routes).

- [ ] **Step 4: Verify with Playwright MCP**

Use Playwright MCP to:
1. Navigate to `https://northops.ca/admin` without being logged in — should redirect to `/admin/login`
2. Log in — should redirect back to `/admin`
3. Navigate to `/api/leads` without session — should get 401/403 response

- [ ] **Step 5: Commit**

```bash
git add src/Provider/AppServiceProvider.php
git commit -m "fix: wire authentication to admin and API routes (#55)"
git push
```

---

### Task 3: Inject Twig via constructor (#50)

**Files:**
- Modify: `src/Controller/MarketingController.php`
- Modify: `src/Controller/DashboardController.php`
- Modify: `src/Provider/AppServiceProvider.php`

- [ ] **Step 1: Add Twig to MarketingController constructor**

In `src/Controller/MarketingController.php`, add `Twig` as a constructor parameter and remove the static accessor:

```php
final class MarketingController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly DiscordNotifier $discordNotifier,
        private readonly ?LeadFactory $leadFactory = null,
        private readonly ?int $defaultBrandId = null,
    ) {}
```

Remove the `twig()` method (lines 26-34) and replace all `$this->twig()->render(...)` calls with `$this->twig->render(...)`. There are 6 occurrences: lines 38, 43, 48, 55, 62, 91.

Remove the `use Waaseyaa\SSR\SsrServiceProvider;` import (line 14).

- [ ] **Step 2: Add Twig to DashboardController constructor**

In `src/Controller/DashboardController.php`, same pattern:

```php
use Twig\Environment as Twig;
use Waaseyaa\SSR\SsrResponse;

final class DashboardController
{
    public function __construct(
        private readonly Twig $twig,
    ) {}

    public function index(): string
    {
        return $this->twig->render('admin/dashboard.html.twig');
    }

    public function leadList(): string
    {
        return $this->twig->render('admin/lead-list.html.twig');
    }

    public function leadDetail(string $id): string
    {
        return $this->twig->render('admin/lead-detail.html.twig', ['lead_id' => $id]);
    }

    public function settings(): string
    {
        return $this->twig->render('admin/settings.html.twig');
    }
}
```

Remove the `twig()` method and the `use Waaseyaa\SSR\SsrServiceProvider;` import.

- [ ] **Step 3: Pass Twig when constructing controllers in AppServiceProvider**

In `src/Provider/AppServiceProvider.php`, resolve Twig from the SSR provider and pass it to both controllers.

Update `controller()` method (line 43-65):

```php
private function controller(): MarketingController
{
    if ($this->controller === null) {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            throw new \RuntimeException('Twig environment not initialized');
        }

        $etm = $this->resolve(EntityTypeManager::class);
        $discordNotifier = $this->buildDiscordNotifier();

        $leadCreatedSubscriber = new LeadCreatedSubscriber($etm, $discordNotifier);
        $stageChangedSubscriber = new StageChangedSubscriber($etm, $discordNotifier);
        $leadManager = new LeadManager($etm, $leadCreatedSubscriber, $stageChangedSubscriber);
        $leadFactory = new LeadFactory($leadManager, $etm, new RoutingService());

        $defaultBrandId = $this->resolveDefaultBrandId($etm);

        $this->controller = new MarketingController(
            $twig,
            $etm,
            $discordNotifier,
            $leadFactory,
            $defaultBrandId,
        );
    }

    return $this->controller;
}
```

Update `dashboardController()` method (line 94-101):

```php
private function dashboardController(): DashboardController
{
    if ($this->dashboardController === null) {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            throw new \RuntimeException('Twig environment not initialized');
        }

        $this->dashboardController = new DashboardController($twig);
    }

    return $this->dashboardController;
}
```

Add `use Waaseyaa\SSR\SsrServiceProvider;` import to AppServiceProvider if not already present.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Controller/MarketingController.php src/Controller/DashboardController.php src/Provider/AppServiceProvider.php
git commit -m "refactor: inject Twig via constructor in controllers (#50)"
```

---

### Task 4: Deduplicate service construction (#54)

**Files:**
- Modify: `src/Provider/AppServiceProvider.php`
- Modify: `src/Provider/PipelineServiceProvider.php`

Both providers construct identical object graphs: `StreamHttpClient`, `DiscordNotifier`, `LeadCreatedSubscriber`, `StageChangedSubscriber`, `LeadManager`, `LeadFactory`, `RoutingService`, `QualificationService`, `RfpImportService`. The fix: build shared services once in `AppServiceProvider` and cache them as private properties. `PipelineServiceProvider` gets them passed via a shared registry or resolves them from the container.

- [ ] **Step 1: Add shared service properties to AppServiceProvider**

Add cached private properties and a shared builder for the common service graph:

```php
private ?DiscordNotifier $discordNotifier = null;
private ?LeadManager $leadManager = null;
private ?LeadFactory $leadFactory = null;
private ?QualificationService $qualificationService = null;
private ?LeadQualifiedSubscriber $leadQualifiedSubscriber = null;
private ?RfpImportService $rfpImportService = null;
```

Extract shared builder methods:

```php
private function getDiscordNotifier(): DiscordNotifier
{
    return $this->discordNotifier ??= $this->buildDiscordNotifier();
}

private function getLeadManager(): LeadManager
{
    if ($this->leadManager === null) {
        $etm = $this->resolve(EntityTypeManager::class);
        $notifier = $this->getDiscordNotifier();
        $leadCreatedSubscriber = new LeadCreatedSubscriber($etm, $notifier);
        $stageChangedSubscriber = new StageChangedSubscriber($etm, $notifier);
        $this->leadManager = new LeadManager($etm, $leadCreatedSubscriber, $stageChangedSubscriber);
    }
    return $this->leadManager;
}

private function getLeadFactory(): LeadFactory
{
    return $this->leadFactory ??= new LeadFactory(
        $this->getLeadManager(),
        $this->resolve(EntityTypeManager::class),
        new RoutingService(),
    );
}

private function getQualificationService(): QualificationService
{
    return $this->qualificationService ??= new QualificationService(
        new StreamHttpClient(),
        $this->config['pipeline']['anthropic_api_key'] ?? '',
        new CompanyProfile($this->config['pipeline']['company_profile'] ?? ''),
        new ProspectScoringService(),
    );
}

private function getLeadQualifiedSubscriber(): LeadQualifiedSubscriber
{
    return $this->leadQualifiedSubscriber ??= new LeadQualifiedSubscriber(
        $this->resolve(EntityTypeManager::class),
        $this->getDiscordNotifier(),
    );
}

private function getRfpImportService(): RfpImportService
{
    return $this->rfpImportService ??= new RfpImportService(
        $this->getLeadFactory(),
        $this->getLeadManager(),
        $this->getQualificationService(),
        $this->getLeadQualifiedSubscriber(),
        new StreamHttpClient(),
        $this->config['pipeline']['northcloud_url'] ?? '',
    );
}
```

- [ ] **Step 2: Simplify controller() and apiController() to use shared builders**

```php
private function controller(): MarketingController
{
    if ($this->controller === null) {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            throw new \RuntimeException('Twig environment not initialized');
        }

        $this->controller = new MarketingController(
            $twig,
            $this->resolve(EntityTypeManager::class),
            $this->getDiscordNotifier(),
            $this->getLeadFactory(),
            $this->resolveDefaultBrandId($this->resolve(EntityTypeManager::class)),
        );
    }

    return $this->controller;
}

private function apiController(): LeadController
{
    if ($this->apiController === null) {
        $this->apiController = new LeadController(
            $this->resolve(EntityTypeManager::class),
            $this->getLeadManager(),
            $this->getLeadFactory(),
            $this->getQualificationService(),
            $this->getRfpImportService(),
            $this->getLeadQualifiedSubscriber(),
            $this->config,
        );
    }

    return $this->apiController;
}
```

- [ ] **Step 3: Simplify PipelineServiceProvider to use AppServiceProvider's shared builders**

Since `PipelineServiceProvider` only needs these services in `commands()`, and it extends `ServiceProvider` with access to `$this->config`, the cleanest approach is to accept the services via a public method or resolve them similarly. For now, keep `PipelineServiceProvider` constructing its own command instances but reuse the same lazy builder pattern to avoid redundant `new StreamHttpClient()` calls:

```php
public function commands(
    EntityTypeManager $entityTypeManager,
    DatabaseInterface $database,
    EventDispatcherInterface $dispatcher,
): array {
    $discordNotifier = new DiscordNotifier(
        new StreamHttpClient(timeout: 5.0),
        $this->config['discord']['webhook_url'] ?? '',
    );

    $leadCreatedSubscriber = new LeadCreatedSubscriber($entityTypeManager, $discordNotifier);
    $stageChangedSubscriber = new StageChangedSubscriber($entityTypeManager, $discordNotifier);
    $leadManager = new LeadManager($entityTypeManager, $leadCreatedSubscriber, $stageChangedSubscriber);
    $leadFactory = new LeadFactory($leadManager, $entityTypeManager, new RoutingService());

    $scoringService = new ProspectScoringService();
    $qualificationService = new QualificationService(
        new StreamHttpClient(),
        $this->config['pipeline']['anthropic_api_key'] ?? '',
        new CompanyProfile($this->config['pipeline']['company_profile'] ?? ''),
        $scoringService,
    );

    $leadQualifiedSubscriber = new LeadQualifiedSubscriber($entityTypeManager, $discordNotifier);
    $rfpImportService = new RfpImportService(
        $leadFactory,
        $leadManager,
        $qualificationService,
        $leadQualifiedSubscriber,
        new StreamHttpClient(),
        $this->config['pipeline']['northcloud_url'] ?? '',
    );

    $defaultBrandId = $this->resolveDefaultBrandId($entityTypeManager);

    return [
        new SeedBrandsCommand($entityTypeManager),
        new ImportRfpsCommand($rfpImportService, $defaultBrandId),
        new ScoreLeadsCommand($entityTypeManager, $scoringService),
        new OutreachListCommand($entityTypeManager, new OutreachTemplateRenderer()),
        new DecayScoresCommand($entityTypeManager),
    ];
}
```

Note: Full deduplication between providers requires a shared container registration, which is a larger refactor. This task focuses on deduplicating within `AppServiceProvider` where the same objects were constructed 3 times (in `controller()`, `apiController()`, and `surfaceHost()`).

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 5: Commit**

```bash
git add src/Provider/AppServiceProvider.php src/Provider/PipelineServiceProvider.php
git commit -m "refactor: deduplicate service construction in AppServiceProvider (#54)"
```

---

### Task 5: Extract Discord notification to event subscriber (#51)

**Files:**
- Modify: `src/Controller/MarketingController.php`
- Modify: `src/Provider/AppServiceProvider.php`
- Create: `src/Domain/Pipeline/EventSubscriber/ContactSubmittedSubscriber.php`
- Create: `src/Domain/Pipeline/Event/ContactSubmittedEvent.php`

The `MarketingController::submitContact()` calls `$this->discordNotifier->notifyContactSubmission()` directly. Move this to an event subscriber following the existing pattern (`LeadCreatedSubscriber`, `StageChangedSubscriber`).

- [ ] **Step 1: Create the event class**

Create `src/Domain/Pipeline/Event/ContactSubmittedEvent.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\Event;

final class ContactSubmittedEvent
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $message,
    ) {}
}
```

- [ ] **Step 2: Create the subscriber**

Create `src/Domain/Pipeline/EventSubscriber/ContactSubmittedSubscriber.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Pipeline\EventSubscriber;

use App\Domain\Pipeline\Event\ContactSubmittedEvent;
use App\Support\DiscordNotifier;

final class ContactSubmittedSubscriber
{
    public function __construct(
        private readonly DiscordNotifier $discordNotifier,
    ) {}

    public function __invoke(ContactSubmittedEvent $event): void
    {
        $this->discordNotifier->notifyContactSubmission(
            $event->name,
            $event->email,
            $event->message,
        );
    }
}
```

- [ ] **Step 3: Dispatch event from MarketingController instead of calling notifier directly**

In `src/Controller/MarketingController.php`:

Add constructor parameter for the event dispatcher:

```php
use App\Domain\Pipeline\Event\ContactSubmittedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class MarketingController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ?LeadFactory $leadFactory = null,
        private readonly ?int $defaultBrandId = null,
    ) {}
```

Remove `DiscordNotifier` from constructor and its import. Replace line 108:

```php
// Before:
$this->discordNotifier->notifyContactSubmission($name, $email, $message);

// After:
$this->dispatcher->dispatch(new ContactSubmittedEvent($name, $email, $message));
```

- [ ] **Step 4: Wire subscriber in AppServiceProvider**

Update the `controller()` method to pass the dispatcher instead of the notifier. Register the subscriber with the dispatcher.

In `AppServiceProvider::controller()`, replace `$this->getDiscordNotifier()` with `$this->resolve(EventDispatcherInterface::class)`:

```php
$dispatcher = $this->resolve(EventDispatcherInterface::class);
$contactSubscriber = new ContactSubmittedSubscriber($this->getDiscordNotifier());
$dispatcher->addListener(ContactSubmittedEvent::class, $contactSubscriber);

$this->controller = new MarketingController(
    $twig,
    $this->resolve(EntityTypeManager::class),
    $dispatcher,
    $this->getLeadFactory(),
    $this->resolveDefaultBrandId($this->resolve(EntityTypeManager::class)),
);
```

Add the necessary imports:

```php
use App\Domain\Pipeline\Event\ContactSubmittedEvent;
use App\Domain\Pipeline\EventSubscriber\ContactSubmittedSubscriber;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
```

- [ ] **Step 5: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 6: Commit**

```bash
git add src/Domain/Pipeline/Event/ContactSubmittedEvent.php src/Domain/Pipeline/EventSubscriber/ContactSubmittedSubscriber.php src/Controller/MarketingController.php src/Provider/AppServiceProvider.php
git commit -m "refactor: extract Discord notification to ContactSubmittedSubscriber (#51)"
```

---

### Task 6: Move contact form validation to domain service (#53)

**Files:**
- Modify: `src/Controller/MarketingController.php`
- Create: `src/Domain/Pipeline/ContactFormValidator.php`

- [ ] **Step 1: Write the test**

Create `tests/Unit/Domain/Pipeline/ContactFormValidatorTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\ContactFormValidator;
use PHPUnit\Framework\TestCase;

final class ContactFormValidatorTest extends TestCase
{
    private ContactFormValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new ContactFormValidator();
    }

    public function testValidInputReturnsNoErrors(): void
    {
        $errors = $this->validator->validate('Alice', 'alice@example.com', 'Hello');
        $this->assertSame([], $errors);
    }

    public function testEmptyNameReturnsError(): void
    {
        $errors = $this->validator->validate('', 'alice@example.com', 'Hello');
        $this->assertArrayHasKey('name', $errors);
    }

    public function testNameOver255ReturnsError(): void
    {
        $errors = $this->validator->validate(str_repeat('A', 256), 'alice@example.com', 'Hello');
        $this->assertArrayHasKey('name', $errors);
    }

    public function testEmptyEmailReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', '', 'Hello');
        $this->assertArrayHasKey('email', $errors);
    }

    public function testInvalidEmailReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', 'not-an-email', 'Hello');
        $this->assertArrayHasKey('email', $errors);
    }

    public function testEmptyMessageReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', 'alice@example.com', '');
        $this->assertArrayHasKey('message', $errors);
    }

    public function testMessageOver5000ReturnsError(): void
    {
        $errors = $this->validator->validate('Alice', 'alice@example.com', str_repeat('A', 5001));
        $this->assertArrayHasKey('message', $errors);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
./vendor/bin/phpunit tests/Unit/Domain/Pipeline/ContactFormValidatorTest.php
```

Expected: FAIL — class `ContactFormValidator` not found.

- [ ] **Step 3: Create the validator**

Create `src/Domain/Pipeline/ContactFormValidator.php`:

```php
<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

final class ContactFormValidator
{
    /**
     * @return array<string, string> Field name => error message (empty if valid)
     */
    public function validate(string $name, string $email, string $message): array
    {
        $errors = [];

        if ($name === '' || strlen($name) > 255) {
            $errors['name'] = 'Name is required (max 255 characters).';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {
            $errors['email'] = 'A valid email address is required.';
        }

        if ($message === '' || strlen($message) > 5000) {
            $errors['message'] = 'Message is required (max 5000 characters).';
        }

        return $errors;
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

```bash
./vendor/bin/phpunit tests/Unit/Domain/Pipeline/ContactFormValidatorTest.php
```

Expected: All 7 tests pass.

- [ ] **Step 5: Use validator in MarketingController**

In `src/Controller/MarketingController.php`, add the validator as a constructor parameter:

```php
use App\Domain\Pipeline\ContactFormValidator;

final class MarketingController
{
    public function __construct(
        private readonly Twig $twig,
        private readonly EntityTypeManager $entityTypeManager,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ContactFormValidator $contactValidator,
        private readonly ?LeadFactory $leadFactory = null,
        private readonly ?int $defaultBrandId = null,
    ) {}
```

Replace the inline validation in `submitContact()` (lines 76-88):

```php
// Before: inline validation
$errors = [];
if ($name === '' || strlen($name) > 255) { ... }
if ($email === '' || ...) { ... }
if ($message === '' || ...) { ... }

// After:
$errors = $this->contactValidator->validate($name, $email, $message);
```

- [ ] **Step 6: Wire validator in AppServiceProvider**

In the `controller()` method, pass a new `ContactFormValidator` instance:

```php
$this->controller = new MarketingController(
    $twig,
    $this->resolve(EntityTypeManager::class),
    $dispatcher,
    new ContactFormValidator(),
    $this->getLeadFactory(),
    $this->resolveDefaultBrandId($this->resolve(EntityTypeManager::class)),
);
```

- [ ] **Step 7: Run all tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 8: Commit**

```bash
git add src/Domain/Pipeline/ContactFormValidator.php tests/Unit/Domain/Pipeline/ContactFormValidatorTest.php src/Controller/MarketingController.php src/Provider/AppServiceProvider.php
git commit -m "refactor: extract contact form validation to ContactFormValidator (#53)"
```

---

### Task 7: Controllers return Response objects (#52)

**Files:**
- Modify: `src/Controller/MarketingController.php`
- Modify: `src/Controller/DashboardController.php`
- Modify: `src/Provider/AppServiceProvider.php`

- [ ] **Step 1: Update MarketingController to return SsrResponse**

In `src/Controller/MarketingController.php`, add import and change return types:

```php
use Waaseyaa\SSR\SsrResponse;
```

Change each page method from `string` to `SsrResponse`:

```php
public function home(): SsrResponse
{
    return new SsrResponse($this->twig->render('home.html.twig'));
}

public function about(): SsrResponse
{
    return new SsrResponse($this->twig->render('about.html.twig'));
}

public function servicesIndex(): SsrResponse
{
    return new SsrResponse($this->twig->render('services/index.html.twig'));
}

public function serviceDetail(string $slug): SsrResponse
{
    $template = "services/{$slug}.html.twig";
    return new SsrResponse($this->twig->render($template));
}

public function contact(Request $request): SsrResponse
{
    $status = $request->query->get('status');
    return new SsrResponse($this->twig->render('contact.html.twig', [
        'status' => $status,
        'errors' => [],
        'old' => [],
        'csrf_token' => CsrfMiddleware::token(),
    ]));
}
```

For `submitContact()`, change the return type to `RedirectResponse|SsrResponse`:

```php
public function submitContact(Request $request): RedirectResponse|SsrResponse
{
    // ... validation ...

    if ($errors !== []) {
        return new SsrResponse($this->twig->render('contact.html.twig', [
            'errors' => $errors,
            'old' => ['name' => $name, 'email' => $email, 'message' => $message],
            'status' => null,
            'csrf_token' => CsrfMiddleware::token(),
        ]));
    }

    // ... save, dispatch event, create lead ...

    return new RedirectResponse('/contact?status=success');
}
```

- [ ] **Step 2: Update DashboardController to return SsrResponse**

```php
use Waaseyaa\SSR\SsrResponse;

public function index(): SsrResponse
{
    return new SsrResponse($this->twig->render('admin/dashboard.html.twig'));
}

public function leadList(): SsrResponse
{
    return new SsrResponse($this->twig->render('admin/lead-list.html.twig'));
}

public function leadDetail(string $id): SsrResponse
{
    return new SsrResponse($this->twig->render('admin/lead-detail.html.twig', ['lead_id' => $id]));
}

public function settings(): SsrResponse
{
    return new SsrResponse($this->twig->render('admin/settings.html.twig'));
}
```

- [ ] **Step 3: Remove SsrResponse wrapping from AppServiceProvider route closures**

In `src/Provider/AppServiceProvider.php`, simplify all route closures to call controllers directly without wrapping:

Marketing routes (lines 173-238):
```php
// Before:
->controller(fn () => new SsrResponse($this->controller()->home()))
// After:
->controller(fn () => $this->controller()->home())
```

Apply the same pattern to all marketing routes: `home`, `about`, `servicesIndex`, `serviceDetail`.

Contact route (lines 218-238): simplify to just call the controller:
```php
->controller(function () {
    $request = Request::createFromGlobals();
    if ($request->getMethod() === 'POST') {
        return $this->controller()->submitContact($request);
    }
    return $this->controller()->contact($request);
})
```

Admin routes (lines 245-278):
```php
// Before:
->controller(fn () => new SsrResponse($this->dashboardController()->index()))
// After:
->controller(fn () => $this->dashboardController()->index())
```

Apply the same to all four admin routes.

Remove `use Waaseyaa\SSR\SsrResponse;` from AppServiceProvider imports (line 32) — it's no longer needed here.

- [ ] **Step 4: Run tests**

```bash
./vendor/bin/phpunit
```

Expected: All tests pass.

- [ ] **Step 5: Commit and push**

```bash
git add src/Controller/MarketingController.php src/Controller/DashboardController.php src/Provider/AppServiceProvider.php
git commit -m "refactor: controllers return Response objects directly (#52)"
git push
```

---

### Task 8: UI polish — org-type-tag (#106)

**Files:**
- Modify: `public/js/dashboard.js:284`
- Modify: `public/css/dashboard.css`

- [ ] **Step 1: Add display block to org-type-tag CSS**

In `public/css/dashboard.css`, find the `.org-type-tag` rule and add `display: inline-block`:

```css
.org-type-tag {
    display: inline-block;
    /* ... existing styles ... */
}
```

If `.org-type-tag` doesn't exist yet, add it near the tier badge styles:

```css
.org-type-tag {
    display: inline-block;
    font-size: 0.7rem;
    padding: 0.15rem 0.4rem;
    border-radius: 3px;
    background: var(--dash-border);
    color: var(--dash-text-muted);
    white-space: nowrap;
}
```

- [ ] **Step 2: Verify visually with Playwright MCP**

Navigate to `/admin` and verify org-type tags render as styled badges on kanban cards.

- [ ] **Step 3: Commit**

```bash
git add public/css/dashboard.css
git commit -m "fix: style org-type-tag on kanban cards (#106)"
```

---

### Task 9: UI polish — progress bar clamping (#107)

**Files:**
- Modify: `public/js/dashboard.js:616`

- [ ] **Step 1: Add clamping to progress bar width**

At line 616 in `public/js/dashboard.js`, clamp the value:

```javascript
// Before:
style: 'width:' + lead.routing_confidence + '%',

// After:
style: 'width:' + Math.min(100, Math.max(0, lead.routing_confidence)) + '%',
```

- [ ] **Step 2: Commit**

```bash
git add public/js/dashboard.js
git commit -m "fix: clamp routing confidence progress bar to 0-100% (#107)"
```

---

### Task 10: UI polish — inline styles on overrides header (#108)

**Files:**
- Modify: `templates/admin/lead-detail.html.twig:108`
- Modify: `public/css/dashboard.css`

- [ ] **Step 1: Add CSS class**

In `public/css/dashboard.css`, add a new class near the routing-scoring section styles:

```css
.form-section-heading {
    margin: 1.5rem 0 0.75rem;
    font-size: 0.85rem;
    color: var(--dash-text-muted);
    text-transform: uppercase;
    letter-spacing: 0.03em;
}
```

- [ ] **Step 2: Replace inline style in template**

In `templates/admin/lead-detail.html.twig` line 108:

```html
<!-- Before: -->
<h4 style="margin:1.5rem 0 0.75rem;font-size:0.85rem;color:var(--dash-text-muted);text-transform:uppercase;letter-spacing:0.03em;">Routing &amp; Scoring Overrides</h4>

<!-- After: -->
<h4 class="form-section-heading">Routing &amp; Scoring Overrides</h4>
```

- [ ] **Step 3: Commit**

```bash
git add public/css/dashboard.css templates/admin/lead-detail.html.twig
git commit -m "fix: replace inline styles with CSS class on overrides header (#108)"
```

---

### Task 11: Close already-merged issues

- [ ] **Step 1: Close issues that have been implemented**

```bash
gh issue close 80 --comment "Implemented in 19b95d7"
gh issue close 81 --comment "Implemented in f9f6eaf"
gh issue close 83 --comment "Implemented in e085720"
gh issue close 84 --comment "Implemented in 02c4a64"
gh issue close 86 --comment "Implemented in 735fdff"
gh issue close 88 --comment "Implemented in 10b3bf3"
gh issue close 91 --comment "Implemented in 13c64c6"
gh issue close 101 --comment "Implemented in a9ee07c"
```

- [ ] **Step 2: Commit** (no code changes — just issue management)

---

### Task 12: E2E test — verify lead pipeline kanban board (#77)

**Files:**
- This task uses Playwright MCP (browser automation), not a test file

- [ ] **Step 1: Navigate to login page**

Use Playwright MCP to navigate to `https://northops.ca/admin/login`. Verify the login form renders with username and password fields.

- [ ] **Step 2: Log in**

Fill in credentials and submit the form. Verify redirect to `/admin`.

- [ ] **Step 3: Verify kanban board renders**

On `/admin`, verify:
- The pipeline board element exists
- Stage columns render (lead, qualified, contacted, proposal, negotiation, won, lost)
- At least one lead card is visible (if leads exist)

- [ ] **Step 4: Verify lead card fields**

Click on a lead card or navigate to `/admin/leads/{id}`. Verify the new fields render:
- Tier badge (T1-T5)
- Routing confidence with progress bar (clamped 0-100%)
- Organization type tag (styled, not bare span)
- Lead source

- [ ] **Step 5: Verify routing & scoring overrides section**

On the lead detail page, verify:
- The "Routing & Scoring Overrides" heading uses the `.form-section-heading` CSS class (no inline styles)
- Override fields are present and editable

- [ ] **Step 6: Verify auth protection**

Open an incognito/new context. Navigate to `/admin` — should redirect to `/admin/login`.
Navigate to `/api/leads` — should get 401/403.

- [ ] **Step 7: Close issue**

```bash
gh issue close 77 --comment "E2E verified via Playwright MCP — login, kanban board, lead detail fields, auth protection all confirmed."
```

---

### Task 13: Final push and verify deployment

- [ ] **Step 1: Push all remaining commits**

```bash
git push
```

- [ ] **Step 2: Wait for CI/CD to complete**

Monitor the GitHub Actions run to confirm deployment succeeds.

- [ ] **Step 3: Final Playwright MCP smoke test**

After deploy:
1. Verify `https://northops.ca` loads (public marketing)
2. Verify `https://northops.ca/admin/login` renders
3. Log in, verify kanban board
4. Log out, verify `/admin` redirects to login
