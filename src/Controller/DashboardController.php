<?php

declare(strict_types=1);

namespace App\Controller;

use Twig\Environment as Twig;
use Waaseyaa\SSR\SsrServiceProvider;

final class DashboardController
{
    public function __construct() {}

    private function twig(): Twig
    {
        $twig = SsrServiceProvider::getTwigEnvironment();
        if ($twig === null) {
            throw new \RuntimeException('Twig environment not initialized');
        }

        return $twig;
    }

    public function index(): string
    {
        return $this->twig()->render('admin/dashboard.html.twig');
    }

    public function leadList(): string
    {
        return $this->twig()->render('admin/lead-list.html.twig');
    }

    public function leadDetail(string $id): string
    {
        return $this->twig()->render('admin/lead-detail.html.twig', ['lead_id' => $id]);
    }

    public function settings(): string
    {
        return $this->twig()->render('admin/settings.html.twig');
    }
}
