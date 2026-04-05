<?php

declare(strict_types=1);

namespace App\Domain\Signal;

use App\Entity\Lead;
use Waaseyaa\Entity\EntityTypeManager;

final class SignalMatcher implements SignalMatcherInterface
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

        // Extract the first word for a LIKE pre-filter to avoid full table scan
        $firstWord = explode(' ', $normalizedInput)[0];
        $storage = $this->entityTypeManager->getStorage('lead');
        $ids = $storage->getQuery()
            ->condition('company_name', "%{$firstWord}%", 'LIKE')
            ->execute();

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
