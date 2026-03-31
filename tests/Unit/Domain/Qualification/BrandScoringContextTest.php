<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Qualification;

use App\Domain\Qualification\BrandScoringContext;
use PHPUnit\Framework\TestCase;

final class BrandScoringContextTest extends TestCase
{
    public function testNorthopsContext(): void
    {
        $ctx = new BrandScoringContext();
        $result = $ctx->forBrand('northops');

        $this->assertSame('NorthOps', $result['brand_name']);
        $this->assertStringContainsString('funded startups', $result['brand_context']);
        $this->assertStringContainsString('CTO', $result['brand_context']);
    }

    public function testWebnetContext(): void
    {
        $ctx = new BrandScoringContext();
        $result = $ctx->forBrand('webnet');

        $this->assertSame('Web Networks', $result['brand_name']);
        $this->assertStringContainsString('non-profit', $result['brand_context']);
        $this->assertStringContainsString('Indigenous', $result['brand_context']);
    }

    public function testUnknownBrandFallsBackToNorthops(): void
    {
        $ctx = new BrandScoringContext();
        $result = $ctx->forBrand('unknown');

        $this->assertSame('NorthOps', $result['brand_name']);
    }

    public function testBuildPromptIncludesCompanyProfile(): void
    {
        $ctx = new BrandScoringContext();
        $prompt = $ctx->buildSystemPrompt('northops', 'We build custom software.');

        $this->assertStringContainsString('NorthOps', $prompt);
        $this->assertStringContainsString('We build custom software.', $prompt);
    }

    public function testBuildPromptIncludesBrandContext(): void
    {
        $ctx = new BrandScoringContext();
        $prompt = $ctx->buildSystemPrompt('webnet', 'Co-op network.');

        $this->assertStringContainsString('Web Networks', $prompt);
        $this->assertStringContainsString('non-profit', $prompt);
        $this->assertStringContainsString('Co-op network.', $prompt);
    }

    public function testBuildPromptIncludesOutputInstructions(): void
    {
        $ctx = new BrandScoringContext();
        $prompt = $ctx->buildSystemPrompt('northops', '');

        $this->assertStringContainsString('score', $prompt);
        $this->assertStringContainsString('confidence', $prompt);
        $this->assertStringContainsString('tier', $prompt);
        $this->assertStringContainsString('reasoning', $prompt);
    }

    public function testSerializeLeadData(): void
    {
        $ctx = new BrandScoringContext();
        $serialized = $ctx->serializeLeadData([
            'label' => 'Test RFP',
            'sector' => 'IT',
            'value' => '50000',
            'contact_name' => 'Jane',
        ]);

        $this->assertStringContainsString('Test RFP', $serialized);
        $this->assertStringContainsString('IT', $serialized);
        $this->assertStringContainsString('50000', $serialized);
    }

    public function testSerializeLeadDataOmitsEmptyFields(): void
    {
        $ctx = new BrandScoringContext();
        $serialized = $ctx->serializeLeadData([
            'label' => 'Test',
            'sector' => '',
            'value' => '0',
        ]);

        $this->assertStringContainsString('Test', $serialized);
        $this->assertStringNotContainsString('sector', $serialized);
    }
}
