<?php

declare(strict_types=1);

namespace App\Domain\Qualification;

use App\Entity\Lead;
use Waaseyaa\HttpClient\HttpClientInterface;
use Waaseyaa\HttpClient\HttpRequestException;

final class QualificationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $apiKey,
        private readonly CompanyProfile $companyProfile,
    ) {}

    /**
     * Qualify a lead using the Anthropic API.
     *
     * @return array{rating: int, keywords: string[], sector: ?string, summary: ?string, confidence: float, raw: string}
     */
    public function qualify(Lead $lead): array
    {
        if ($this->apiKey === '') {
            throw new \RuntimeException('Anthropic API key is not configured.');
        }

        $promptInput = $this->buildPrompt($lead);

        try {
            $response = $this->httpClient->post(
                'https://api.anthropic.com/v1/messages',
                [
                    'x-api-key' => $this->apiKey,
                    'anthropic-version' => '2023-06-01',
                ],
                [
                    'model' => 'claude-haiku-4-5',
                    'max_tokens' => 600,
                    'messages' => [
                        ['role' => 'user', 'content' => json_encode($promptInput, JSON_THROW_ON_ERROR)],
                    ],
                ],
            );
        } catch (HttpRequestException $e) {
            throw new \RuntimeException('Failed to connect to Anthropic API.', 0, $e);
        }

        return $this->parseResponse($response->body);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPrompt(Lead $lead): array
    {
        $description = $lead->getDraftPdfMarkdown();
        if ($description === '') {
            $description = $lead->getQualifyNotes();
        }

        $sectorValue = $lead->getSector();

        return [
            'task' => 'Qualify a business-development lead for NorthOps.',
            'instructions' => [
                'You will receive a lead with title, description, and optional metadata.',
                'Your job is to extract structured fields only. Do not add fields not defined in the schema.',
                'If the lead already has a sector value, DO NOT modify or reassign it. Leave it unchanged.',
                'If sector is null, assign exactly one sector from the allowed list.',
                'Keep responses concise and factual. No commentary.',
            ],
            'allowed_sectors' => SectorNormalizer::SECTORS,
            'company_profile' => $this->companyProfile->description,
            'output_schema' => [
                'rating' => 'integer 0-100 representing how strong this lead is for NorthOps',
                'keywords' => 'array of 3-8 short keywords extracted from the lead',
                'sector' => 'string or null; if null in input, assign one from allowed_sectors',
                'summary' => '1-2 sentence summary of the opportunity',
                'confidence' => '0-1 float representing your confidence in this extraction',
            ],
            'input' => [
                'title' => $lead->getLabel(),
                'description' => $description,
                'sector' => $sectorValue !== '' ? $sectorValue : null,
            ],
            'output_format' => 'Return ONLY valid JSON matching output_schema. No prose.',
        ];
    }

    /**
     * @return array{rating: int, keywords: string[], sector: ?string, summary: ?string, confidence: float, raw: string}
     */
    private function parseResponse(string $response): array
    {
        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);

        if (isset($decoded['error'])) {
            throw new \RuntimeException('Anthropic API error: ' . ($decoded['error']['message'] ?? 'Unknown error'));
        }

        $textContent = '';
        foreach ($decoded['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $textContent = $block['text'];
                break;
            }
        }

        if ($textContent === '') {
            throw new \RuntimeException('No text content in Anthropic API response.');
        }

        // Strip markdown code fences if present.
        $cleaned = preg_replace('/^```(?:json)?\s*/i', '', trim($textContent));
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);

        $result = json_decode($cleaned, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($result)) {
            throw new \RuntimeException('Unexpected response format from Anthropic API.');
        }

        // Clamp and validate values.
        $rating = is_numeric($result['rating'] ?? null) ? (int) $result['rating'] : 0;
        $rating = max(0, min(100, $rating));

        $confidence = is_numeric($result['confidence'] ?? null) ? (float) $result['confidence'] : 0.0;
        $confidence = max(0.0, min(1.0, $confidence));

        $keywords = is_array($result['keywords'] ?? null) ? $result['keywords'] : [];
        $keywords = array_slice(array_filter($keywords, 'is_string'), 0, 8);

        $sector = is_string($result['sector'] ?? null) ? $result['sector'] : null;
        $summary = is_string($result['summary'] ?? null) ? $result['summary'] : null;

        return [
            'rating' => $rating,
            'keywords' => $keywords,
            'sector' => $sector,
            'summary' => $summary,
            'confidence' => $confidence,
            'raw' => $textContent,
        ];
    }
}
