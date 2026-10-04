<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\AIService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class AIServiceTest extends TestCase
{
    public function test_it_extracts_structured_facts_from_gemini_response(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-3.8-flash',
        ]);

        $facts = [
            'main_event' => 'Polda Babel menertibkan 229 kendaraan.',
            'who' => ['Polda Kepulauan Bangka Belitung'],
            'what' => 'Menertibkan 229 kendaraan.',
            'when' => '25-27 September 2026',
            'where' => 'Bangka Belitung',
            'why' => 'Mengatasi antrean panjang kendaraan di SPBU.',
            'how' => null,
            'key_facts' => [[
                'fact' => 'Sebanyak 229 kendaraan ditertibkan.',
                'evidence' => 'menertibkan sebanyak 229 kendaraan',
            ]],
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [['text' => json_encode($facts)]],
                    ],
                ]],
            ]),
        ]);

        $article = new Article([
            'source_title' => 'Polda Babel tertibkan kendaraan',
            'source_content' => 'Polda Kepulauan Bangka Belitung menertibkan sebanyak 229 kendaraan.',
        ]);

        $this->assertSame($facts, app(AIService::class)->extractFacts($article));

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('x-goog-api-key', 'test-key')
                && str_contains($request->url(), '/models/gemini-3.8-flash:generateContent')
                && data_get($request->data(), 'generationConfig.responseMimeType') === 'application/json'
                && data_get($request->data(), 'generationConfig.responseJsonSchema.type') === 'object';
        });
    }

    public function test_it_falls_back_when_the_primary_model_is_overloaded(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-3.8-flash',
            'services.gemini.fallback_model' => 'gemini-3.5-flash-lite',
            'services.gemini.retry_delays_ms' => [0],
        ]);

        $facts = [
            'main_event' => 'Polda Babel menertibkan 229 kendaraan.',
            'who' => ['Polda Kepulauan Bangka Belitung'],
            'what' => 'Menertibkan 229 kendaraan.',
            'when' => '25-27 September 2026',
            'where' => 'Bangka Belitung',
            'why' => 'Mengatasi antrean panjang kendaraan di SPBU.',
            'how' => null,
            'key_facts' => [[
                'fact' => 'Sebanyak 229 kendaraan ditertibkan.',
                'evidence' => 'menertibkan sebanyak 229 kendaraan',
            ]],
        ];
        $response = ['candidates' => [[
            'content' => ['parts' => [['text' => json_encode($facts)]]],
        ]]];

        Http::fake([
            '*/models/gemini-3.8-flash:generateContent' => Http::sequence()
                ->push(['error' => ['message' => 'Temporarily unavailable.']], 503)
                ->push(['error' => ['message' => 'Temporarily unavailable.']], 503),
            '*/models/gemini-3.5-flash-lite:generateContent' => Http::response($response),
        ]);

        $result = app(AIService::class)->extractFactsWithModel(new Article([
            'source_title' => 'Polda Babel tertibkan kendaraan',
            'source_content' => 'Polda Kepulauan Bangka Belitung menertibkan sebanyak 229 kendaraan.',
        ]));

        $this->assertSame($facts, $result['facts']);
        $this->assertSame('gemini-3.5-flash-lite', $result['model']);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gemini-3.5-flash-lite:generateContent')
            && data_get($request->data(), 'generationConfig.responseMimeType') === 'application/json'
            && data_get($request->data(), 'generationConfig.responseJsonSchema.type') === 'object'
            && data_get($request->data(), 'generationConfig.responseFormat') === null);
    }

    public function test_it_rewrites_an_article_using_its_extracted_facts(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
            'services.gemini.fallback_model' => 'gemini-3.8-flash',
        ]);

        $rewrite = [
            'title' => 'Polda Babel Tertibkan 229 Kendaraan',
            'content' => "Polda Kepulauan Bangka Belitung menertibkan 229 kendaraan.\n\nPenertiban dilakukan untuk mengatasi antrean panjang di SPBU.",
        ];

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode($rewrite)]]],
                ]],
            ]),
        ]);

        $article = new Article([
            'source_title' => 'Polda Babel tertibkan kendaraan',
            'source_content' => 'Polda Kepulauan Bangka Belitung menertibkan sebanyak 229 kendaraan.',
            'facts' => [
                'main_event' => 'Polda Babel menertibkan 229 kendaraan.',
                'who' => ['Polda Kepulauan Bangka Belitung'],
                'what' => 'Menertibkan kendaraan.',
                'when' => null,
                'where' => 'Bangka Belitung',
                'why' => null,
                'how' => null,
                'key_facts' => [[
                    'fact' => 'Sebanyak 229 kendaraan ditertibkan.',
                    'evidence' => 'menertibkan sebanyak 229 kendaraan',
                ]],
            ],
        ]);

        $this->assertSame([
            ...$rewrite,
            'model' => 'gemini-3.5-flash-lite',
        ], app(AIService::class)->rewriteArticleWithModel($article));

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gemini-3.5-flash-lite:generateContent')
            && str_contains(data_get($request->data(), 'contents.0.parts.0.text'), 'Fakta terverifikasi:')
            && data_get($request->data(), 'generationConfig.responseJsonSchema.properties.title.type') === 'string'
            && data_get($request->data(), 'generationConfig.responseJsonSchema.properties.content.type') === 'string');
    }

    public function test_it_requires_a_gemini_api_key(): void
    {
        config(['services.gemini.api_key' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GEMINI_API_KEY belum diatur');

        app(AIService::class)->extractFacts(new Article([
            'source_title' => 'Judul',
            'source_content' => 'Isi berita.',
        ]));
    }

    public function test_it_rejects_evidence_that_is_not_in_the_source_article(): void
    {
        config(['services.gemini.api_key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'main_event' => null,
                                'who' => [],
                                'what' => null,
                                'when' => null,
                                'where' => null,
                                'why' => null,
                                'how' => null,
                                'key_facts' => [[
                                    'fact' => 'Klaim yang tidak ada.',
                                    'evidence' => 'Teks yang tidak ada di artikel.',
                                ]],
                            ]),
                        ]],
                    ],
                ]],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Evidence fakta tidak ditemukan');

        app(AIService::class)->extractFacts(new Article([
            'source_title' => 'Judul',
            'source_content' => 'Isi berita asli.',
        ]));
    }
}
