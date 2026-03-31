<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

final class RoutingService
{
    private const TECH_SECTORS = ['SaaS', 'AI/ML', 'DevOps', 'Cloud', 'Cybersecurity'];

    private const TECHNICAL_SECTORS = [
        'SaaS', 'AI/ML', 'DevOps', 'Cloud', 'Cybersecurity',
        'IT', 'Software', 'Infrastructure', 'Telecom',
    ];

    private const NON_PROFIT_TYPES = ['non_profit', 'charity'];

    /**
     * Route lead data to a brand with confidence score.
     *
     * @param array<string, mixed> $data Lead data (source, lead_source, brand_id, organization_type, sector, value)
     * @return array{brand: string, confidence: int, rule: string}
     */
    public function route(array $data): array
    {
        $leadSource = (string) ($data['lead_source'] ?? '');
        $brandId = (int) ($data['brand_id'] ?? 0);
        $orgType = (string) ($data['organization_type'] ?? '');
        $sector = (string) ($data['sector'] ?? '');
        $value = (float) ($data['value'] ?? 0);

        // Rule 1: NorthOps contact form
        if ($leadSource === 'northops_contact') {
            return self::result('northops', 100, 'northops_contact_form');
        }

        // Rule 2: Web Networks contact form
        if ($leadSource === 'webnet_contact') {
            return self::result('webnet', 100, 'webnet_contact_form');
        }

        // Rule 3: Brand explicitly set
        if ($brandId > 0) {
            return self::result('explicit', 100, 'explicit_brand');
        }

        // Rule 4: Non-profit / charity
        if (\in_array($orgType, self::NON_PROFIT_TYPES, true)) {
            return self::result('webnet', 95, 'non_profit_org');
        }

        // Rule 5: Indigenous org
        if ($orgType === 'indigenous') {
            return self::result('webnet', 95, 'indigenous_org');
        }

        // Rule 6: Tech sectors → NorthOps
        if (\in_array($sector, self::TECH_SECTORS, true)) {
            return self::result('northops', 90, 'tech_sector');
        }

        // Rule 7: Funding lead source
        if ($leadSource === 'funding') {
            return self::result('webnet', 90, 'funding_source');
        }

        // Rule 8: Website audit lead source
        if ($leadSource === 'website_audit') {
            return self::result('webnet', 90, 'audit_source');
        }

        // Rule 9: Signal (founder intent)
        if ($leadSource === 'signal') {
            return self::result('northops', 85, 'signal_source');
        }

        // Rule 10: Budget >15K with technical scope
        if ($value > 15_000 && \in_array($sector, self::TECHNICAL_SECTORS, true)) {
            return self::result('northops', 80, 'high_budget_technical');
        }

        // Rule 11: Government digital services
        if ($sector === 'Government') {
            return self::result('both', 50, 'government_digital');
        }

        // Rule 12: No match
        return self::result('manual', 0, 'no_match');
    }

    /**
     * @return array{brand: string, confidence: int, rule: string}
     */
    private static function result(string $brand, int $confidence, string $rule): array
    {
        return [
            'brand' => $brand,
            'confidence' => $confidence,
            'rule' => $rule,
        ];
    }
}
