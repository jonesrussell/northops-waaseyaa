<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Pipeline\ContactFormValidator;
use App\Domain\Pipeline\Event\ContactSubmittedEvent;
use App\Domain\Pipeline\LeadFactory;
use App\Entity\ContactSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twig\Environment as Twig;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\Middleware\CsrfMiddleware;

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

    public function submitContact(Request $request): RedirectResponse|SsrResponse
    {
        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $message = trim((string) $request->request->get('message', ''));

        $errors = $this->contactValidator->validate($name, $email, $message);

        if ($errors !== []) {
            return new SsrResponse($this->twig->render('contact.html.twig', [
                'errors' => $errors,
                'old' => ['name' => $name, 'email' => $email, 'message' => $message],
                'status' => null,
                'csrf_token' => CsrfMiddleware::token(),
            ]));
        }

        $submission = new ContactSubmission([
            'name' => $name,
            'email' => $email,
            'message' => $message,
        ]);

        $storage = $this->entityTypeManager->getStorage('contact_submission');
        $storage->save($submission);

        $this->dispatcher->dispatch(new ContactSubmittedEvent($name, $email, $message));
        $this->createLeadFromSubmission($submission);

        return new RedirectResponse('/contact?status=success');
    }

    private function createLeadFromSubmission(ContactSubmission $submission): void
    {
        if ($this->leadFactory === null || $this->defaultBrandId === null) {
            return;
        }

        try {
            $this->leadFactory->fromContactSubmission($submission, $this->defaultBrandId);
        } catch (\Throwable $e) {
            // Lead creation is non-blocking — contact form submission succeeds regardless
            error_log(sprintf('[NorthOps] Lead auto-creation failed: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine()));
        }
    }

}
