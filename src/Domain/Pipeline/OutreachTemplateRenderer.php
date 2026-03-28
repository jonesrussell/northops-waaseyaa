<?php

declare(strict_types=1);

namespace App\Domain\Pipeline;

final class OutreachTemplateRenderer
{
    /** @var array<string, array{subject: string, body: string}> */
    private const TEMPLATES = [
        'outdated_website' => [
            'subject' => 'Web modernization services for {{ organization_name }}',
            'body' => <<<'TPL'
Hi {{ contact_name }},

I came across {{ organization_name }} and noticed {{ signal_detail }}. Modernizing a website can feel daunting, but it doesn't have to be — especially with the right partner.

At {{ brand_company }}, we specialize in technology transitions that respect what already works while bringing your digital presence up to current standards. We are {{ brand_description }}.

If you're considering a refresh, I'd welcome the chance to chat about how we could help {{ organization_name }} move forward confidently.

Best regards,
{{ sender_name }}
{{ brand_company }} — {{ brand_tagline }}
TPL,
        ],
        'funding_win' => [
            'subject' => 'Congratulations on your funding — digital support for {{ organization_name }}',
            'body' => <<<'TPL'
Hi {{ contact_name }},

Congratulations to {{ organization_name }} on {{ signal_detail }}. That's a significant milestone, and it opens up exciting possibilities for your digital presence.

We are {{ brand_company }}, {{ brand_description }}. We've helped organizations like yours translate new funding into lasting digital impact — from content platforms to public-facing applications.

I'd love to explore how we might support {{ organization_name }} as you put this funding to work.

Best regards,
{{ sender_name }}
{{ brand_company }} — {{ brand_tagline }}
TPL,
        ],
        'job_posting' => [
            'subject' => 'Web development support for {{ organization_name }}',
            'body' => <<<'TPL'
Hi {{ contact_name }},

I noticed {{ organization_name }} is {{ signal_detail }}. Finding the right web talent can be challenging, and we may be able to help bridge the gap.

We are {{ brand_company }}, {{ brand_description }}. Whether you need to supplement your team during a hiring cycle or want ongoing development support, we can step in quickly and deliver production-ready work.

Would it make sense to have a brief conversation about how we could support {{ organization_name }}?

Best regards,
{{ sender_name }}
{{ brand_company }} — {{ brand_tagline }}
TPL,
        ],
        'new_program' => [
            'subject' => 'Digital platform support for {{ organization_name }}\'s new initiative',
            'body' => <<<'TPL'
Hi {{ contact_name }},

I was excited to learn about {{ organization_name }}'s {{ signal_detail }}. New initiatives like this often need strong digital foundations to reach their full potential.

We are {{ brand_company }}, {{ brand_description }}. We've built platforms for organizations launching new programs — from custom web applications to content management systems tailored to specific workflows.

I'd welcome the opportunity to discuss how we could support {{ organization_name }}'s new initiative with the right digital tools.

Best regards,
{{ sender_name }}
{{ brand_company }} — {{ brand_tagline }}
TPL,
        ],
    ];

    /** @var array<string, array{company: string, tagline: string, description: string}> */
    private const BRANDS = [
        'webnet' => [
            'company' => 'Web Networks',
            'tagline' => 'web.net',
            'description' => 'a Canadian not-for-profit with 38 years of experience building websites, web applications, and digital platforms — with a focus on open-source, privacy, and Canadian hosting',
        ],
        'northops' => [
            'company' => 'NorthOps',
            'tagline' => 'northops.ca',
            'description' => 'a Canadian development studio specializing in modern web applications, APIs, and custom platforms built with Go, PHP, and Rust',
        ],
    ];

    /**
     * Render an outreach template with brand-specific variables.
     *
     * @param array<string, string> $variables Must include: contact_name, organization_name, signal_detail, sender_name
     * @return array{subject: string, body: string, template_used: string, brand: string}
     */
    public function render(string $templateName, string $brand, array $variables): array
    {
        if (!isset(self::TEMPLATES[$templateName])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown template "%s". Available: %s',
                $templateName,
                implode(', ', array_keys(self::TEMPLATES)),
            ));
        }

        if (!isset(self::BRANDS[$brand])) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown brand "%s". Available: %s',
                $brand,
                implode(', ', array_keys(self::BRANDS)),
            ));
        }

        $brandData = self::BRANDS[$brand];
        $replacements = array_merge($variables, [
            'brand_company' => $brandData['company'],
            'brand_tagline' => $brandData['tagline'],
            'brand_description' => $brandData['description'],
        ]);

        $template = self::TEMPLATES[$templateName];

        return [
            'subject' => $this->interpolate($template['subject'], $replacements),
            'body' => $this->interpolate($template['body'], $replacements),
            'template_used' => $templateName,
            'brand' => $brand,
        ];
    }

    /**
     * @return list<string>
     */
    public function availableTemplates(): array
    {
        return array_keys(self::TEMPLATES);
    }

    /**
     * @param array<string, string> $variables
     */
    private function interpolate(string $text, array $variables): string
    {
        $search = [];
        $replace = [];

        foreach ($variables as $key => $value) {
            $search[] = '{{ ' . $key . ' }}';
            $replace[] = $value;
        }

        return str_replace($search, $replace, $text);
    }
}
