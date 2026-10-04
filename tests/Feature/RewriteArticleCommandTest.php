<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RewriteArticleCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rewrites_only_one_article_and_marks_it_drafted(): void
    {
        config([
            'services.gemini.api_key' => 'test-key',
            'services.gemini.model' => 'gemini-3.5-flash-lite',
            'services.gemini.fallback_model' => 'gemini-3.8-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => json_encode([
                        'title' => 'Polda Babel Tertibkan Kendaraan',
                        'content' => 'Polda Babel menertibkan kendaraan sesuai fakta yang tersedia.',
                    ])]]],
                ]],
            ]),
        ]);

        $facts = [
            'main_event' => 'Polda Babel menertibkan kendaraan.',
            'who' => ['Polda Babel'],
            'what' => 'Menertibkan kendaraan.',
            'when' => null,
            'where' => null,
            'why' => null,
            'how' => null,
            'key_facts' => [],
        ];
        $first = Article::create([
            'source' => 'antara',
            'source_url' => 'https://example.test/rewrite-1',
            'source_title' => 'Polda Babel tertibkan kendaraan',
            'source_content' => 'Polda Babel menertibkan kendaraan.',
            'facts' => $facts,
            'status' => 'processing',
        ]);
        $second = Article::create([
            'source' => 'antara',
            'source_url' => 'https://example.test/rewrite-2',
            'source_title' => 'Berita kedua',
            'source_content' => 'Isi berita kedua.',
            'facts' => $facts,
            'status' => 'processing',
        ]);

        $this->assertSame(0, Artisan::call('ai:rewrite'));

        $this->assertSame('drafted', $first->fresh()->status);
        $this->assertSame('Polda Babel Tertibkan Kendaraan', $first->fresh()->rewritten_title);
        $this->assertNotEmpty($first->fresh()->rewritten_content);
        $this->assertSame('processing', $second->fresh()->status);
        $this->assertNull($second->fresh()->rewritten_title);
        Http::assertSentCount(1);
    }
}
