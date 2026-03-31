<?php

declare(strict_types=1);

namespace App\Controller;

use Twig\Environment as Twig;
use Waaseyaa\SSR\SsrResponse;

final class DashboardController
{
    public function __construct(
        private readonly Twig $twig,
    ) {}

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
}
