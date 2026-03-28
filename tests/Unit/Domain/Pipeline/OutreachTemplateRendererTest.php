<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Pipeline;

use App\Domain\Pipeline\OutreachTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class OutreachTemplateRendererTest extends TestCase
{
    private OutreachTemplateRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new OutreachTemplateRenderer();
    }

    public function testRenderWebNetOutdatedWebsiteTemplate(): void
    {
        $result = $this->renderer->render('outdated_website', 'webnet', [
            'contact_name' => 'Sarah',
            'organization_name' => 'Maple Health',
            'signal_detail' => 'your website is running on an older CMS platform',
            'sender_name' => 'Russell Jones',
        ]);

        $this->assertSame('Web modernization services for Maple Health', $result['subject']);
        $this->assertStringContainsString('Hi Sarah', $result['body']);
        $this->assertStringContainsString('Maple Health', $result['body']);
        $this->assertStringContainsString('your website is running on an older CMS platform', $result['body']);
        $this->assertStringContainsString('Web Networks', $result['body']);
        $this->assertStringContainsString('web.net', $result['body']);
        $this->assertStringContainsString('Canadian not-for-profit', $result['body']);
        $this->assertStringContainsString('Russell Jones', $result['body']);
        $this->assertSame('outdated_website', $result['template_used']);
        $this->assertSame('webnet', $result['brand']);
    }

    public function testRenderNorthOpsTemplate(): void
    {
        $result = $this->renderer->render('job_posting', 'northops', [
            'contact_name' => 'David',
            'organization_name' => 'TechCo',
            'signal_detail' => 'hiring a senior web developer',
            'sender_name' => 'Russell Jones',
        ]);

        $this->assertStringContainsString('NorthOps', $result['body']);
        $this->assertStringContainsString('northops.ca', $result['body']);
        $this->assertStringContainsString('Go, PHP, and Rust', $result['body']);
        $this->assertStringNotContainsString('Web Networks', $result['body']);
        $this->assertSame('northops', $result['brand']);
    }

    public function testRenderFundingWinTemplate(): void
    {
        $result = $this->renderer->render('funding_win', 'webnet', [
            'contact_name' => 'Claudette',
            'organization_name' => 'Sagamok',
            'signal_detail' => 'receiving a federal grant for digital literacy',
            'sender_name' => 'Russell Jones',
        ]);

        $this->assertStringContainsString('Congratulations', $result['subject']);
        $this->assertStringContainsString('Sagamok', $result['body']);
        $this->assertStringContainsString('digital', $result['body']);
        $this->assertSame('funding_win', $result['template_used']);
    }

    public function testAvailableTemplates(): void
    {
        $templates = $this->renderer->availableTemplates();

        $this->assertCount(4, $templates);
        $this->assertContains('outdated_website', $templates);
        $this->assertContains('funding_win', $templates);
        $this->assertContains('job_posting', $templates);
        $this->assertContains('new_program', $templates);
    }
}
