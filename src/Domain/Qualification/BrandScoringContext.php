<?php

declare(strict_types=1);

namespace App\Domain\Qualification;

final class BrandScoringContext
{
    /** @var array<string, array{brand_name: string, brand_context: string}> */
    private const BRANDS = [
        'northops' => [
            'brand_name' => 'NorthOps',
            'brand_context' => 'ICP: funded startups, CTO/VP Engineering buyers, urgent builds. '
                . 'Score highly for: founder/CTO role, recently funded, urgent timeline, '
                . 'tech stack match (cloud, DevOps, SaaS), budget >=5K. '
                . 'Red flags: no budget, no timeline, non-technical scope.',
        ],
        'webnet' => [
            'brand_name' => 'Web Networks',
            'brand_context' => 'ICP: non-profit organizations, Indigenous orgs, grant-funded projects. '
                . 'Score highly for: registered non-profit, has grant/government funding, '
                . 'outdated or missing website, accessibility issues, community-focused mission. '
                . 'Red flags: commercial enterprise, no social mission, no funding source.',
        ],
    ];

    private const LEAD_FIELDS = [
        'label', 'sector', 'value', 'contact_name', 'contact_email',
        'company_name', 'organization_type', 'lead_source', 'budget_range',
        'urgency', 'funding_status', 'source_url',
    ];

    /**
     * @return array{brand_name: string, brand_context: string}
     */
    public function forBrand(string $brandSlug): array
    {
        return self::BRANDS[$brandSlug] ?? self::BRANDS['northops'];
    }

    public function buildSystemPrompt(string $brandSlug, string $companyProfile): string
    {
        $brand = $this->forBrand($brandSlug);

        return <<<PROMPT
            You are a lead qualification assistant for {$brand['brand_name']}.
            {$brand['brand_context']}
            {$companyProfile}

            Evaluate the lead and return JSON with these fields:
            - score: integer 0-100 representing lead quality
            - confidence: float 0-1 representing your confidence in the assessment
            - tier: T1 (hot, >=80), T2 (warm, 50-79), or T3 (cold, <50)
            - reasoning: 1-2 sentence explanation of the score
            - recommended_action: one of "qualify", "nurture", "disqualify"
            PROMPT;
    }

    public function serializeLeadData(array $leadData): string
    {
        $fields = [];

        foreach (self::LEAD_FIELDS as $field) {
            $val = (string) ($leadData[$field] ?? '');
            if ($val !== '' && $val !== '0') {
                $fields[] = "{$field}: {$val}";
            }
        }

        return implode("\n", $fields);
    }
}
