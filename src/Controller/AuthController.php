<?php

declare(strict_types=1);

namespace App\Controller;

use Waaseyaa\SSR\Flash\Flash;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\SSR\SsrResponse;
use Waaseyaa\User\User;

final class AuthController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    public function loginForm(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $html = $this->twig->render('auth/login.html.twig', [
            'errors' => [],
            'values' => [],
            'redirect' => (string) $request->query->get('redirect', ''),
        ]);

        return new SsrResponse(content: $html);
    }

    public function submitLogin(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        $email = trim((string) $request->request->get('email', ''));
        $password = (string) $request->request->get('password', '');

        $errors = [];
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        }
        if ($password === '') {
            $errors['password'] = 'Password is required.';
        }

        if ($errors !== []) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => $errors,
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $ids = $storage->getQuery()
            ->condition('mail', $email)
            ->execute();

        if ($ids === []) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        /** @var User|null $user */
        $user = $storage->load(reset($ids));

        if ($user === null || !$user->checkPassword($password)) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'Invalid email or password.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        if (!$user->isActive()) {
            $html = $this->twig->render('auth/login.html.twig', [
                'errors' => ['email' => 'This account has been deactivated.'],
                'values' => compact('email'),
            ]);
            return new SsrResponse(content: $html);
        }

        $_SESSION['waaseyaa_uid'] = $user->id();

        Flash::success('Welcome back, ' . $user->get('name') . '.');

        $redirect = $this->safeRedirect(
            (string) $request->request->get('redirect', ''),
            '/admin',
        );

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => $redirect]);
    }

    public function logout(array $params, array $query, AccountInterface $account, HttpRequest $request): SsrResponse
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        return new SsrResponse(content: '', statusCode: 302, headers: ['Location' => '/']);
    }

    private function safeRedirect(string $target, string $fallback): string
    {
        if ($target === '' || !str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return $fallback;
        }

        return $target;
    }
}
