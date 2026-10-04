<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExtractArticleFactsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_processes_only_one_waiting_article_per_run(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-3.8-flash',
            'services.gemini.fallback_model' => 'gemini-3.8-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'main_event' => 'Berita satu.',
                        'who' => ['Polda Babel'],
                        'what' => 'Menertibkan kendaraan.',
                        'when' => null,
                        'where' => 'Bangka Belitung',
                        'why' => null,
                        'how' => null,
                        'key_facts' => [[
                            'fact' => 'Kendaraan ditertibkan.',
                            'evidence' => 'menertibkan kendaraan',
                        ]],
                    ])]]],
                ]],
            ]),
        ]);

        $first = Article::create([
            'source' => 'antara',
            'source_url' => 'https://example.test/1',
            'source_title' => 'Berita satu',
            'source_content' => 'Polda Babel menertibkan kendaraan.',
            'status' => 'pending',
        ]);
        $second = Article::create([
            'source' => 'antara',
            'source_url' => 'https://example.test/2',
            'source_title' => 'Berita dua',
            'source_content' => 'Isi berita kedua.',
            'status' => 'pending',
        ]);

        $this->assertSame(0, Artisan::call('ai:extract-facts'));

        $this->assertNotNull($first->fresh()->facts);
        $this->assertNull($second->fresh()->facts);
        $this->assertSame('pending', $second->fresh()->status);
        Http::assertSentCount(1);
    }
}
