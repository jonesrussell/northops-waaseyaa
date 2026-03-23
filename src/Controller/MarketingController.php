<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContactSubmission;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment as Twig;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrServiceProvider;
use Waaseyaa\User\Middleware\CsrfMiddleware;

final class MarketingController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
    ) {}

    private function twig(): Twig
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            throw new \RuntimeException('Twig environment not initialized');
        }

        return $twig;
    }

    public function home(): string
    {
        return $this->twig()->render('home.html.twig');
    }

    public function contact(Request $request): string
    {
        $status = $request->query->get('status');

        return $this->twig()->render('contact.html.twig', [
            'status' => $status,
            'errors' => [],
            'old' => [],
            'csrf_token' => CsrfMiddleware::token(),
        ]);
    }

    public function submitContact(Request $request): RedirectResponse|string
    {
        $name = trim((string) $request->request->get('name', ''));
        $email = trim((string) $request->request->get('email', ''));
        $message = trim((string) $request->request->get('message', ''));

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

        if ($errors !== []) {
            return $this->twig()->render('contact.html.twig', [
                'errors' => $errors,
                'old' => ['name' => $name, 'email' => $email, 'message' => $message],
                'status' => null,
                'csrf_token' => CsrfMiddleware::token(),
            ]);
        }

        $submission = new ContactSubmission([
            'name' => $name,
            'email' => $email,
            'message' => $message,
        ]);

        $storage = $this->entityTypeManager->getStorage('contact_submission');
        $storage->save($submission);

        return new RedirectResponse('/contact?status=' . urlencode('Thanks! We\'ll be in touch soon.'));
    }
}
