<?php

namespace Tests\Feature;

use App\Models\Article;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublishWordPressArticleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.wordpress.url' => 'https://news.example',
            'services.wordpress.username' => 'publisher',
            'services.wordpress.application_password' => 'test-password',
        ]);
    }

    public function test_it_publishes_an_approved_article_and_saves_the_wordpress_post_id(): void
    {
        Http::fake(['news.example/*' => Http::response(['id' => 321], 201)]);
        $article = $this->article(['status' => 'approved']);

        $this->artisan('wordpress:publish', ['article' => $article->id])
            ->expectsOutput("Artikel {$article->id} dipublikasikan ke WordPress sebagai post 321.")
            ->assertSuccessful();

        $article->refresh();
        $this->assertSame(321, $article->wordpress_post_id);
        $this->assertSame('published', $article->status);
        $this->assertNotNull($article->published_at);
        Http::assertSent(fn ($request) => $request->url() === 'https://news.example/wp-json/wp/v2/posts'
            && $request['title'] === 'Draft title'
            && $request['content'] === '<p>Draft content</p>'
            && $request['status'] === 'publish');
    }

    public function test_it_does_not_publish_an_unapproved_article(): void
    {
        Http::fake();
        $article = $this->article(['status' => 'waiting_approval']);

        $this->artisan('wordpress:publish', ['article' => $article->id])
            ->expectsOutput('Tidak ada artikel approved yang menunggu publikasi.')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('waiting_approval', $article->fresh()->status);
    }

    public function test_it_keeps_the_article_approved_when_wordpress_returns_an_error(): void
    {
        Http::fake(['news.example/*' => Http::response(['message' => 'Unauthorized'], 401)]);
        $article = $this->article(['status' => 'approved']);

        $this->artisan('wordpress:publish', ['article' => $article->id])
            ->expectsOutput('WordPress API gagal: Unauthorized')
            ->assertFailed();

        $article->refresh();
        $this->assertSame('approved', $article->status);
        $this->assertNull($article->wordpress_post_id);
        $this->assertSame('WordPress API gagal: Unauthorized', $article->error_message);
    }

    private function article(array $attributes = []): Article
    {
        return Article::query()->create(array_merge([
            'source' => 'antara',
            'source_id' => uniqid(),
            'source_url' => 'https://example.test/article',
            'source_title' => 'Source title',
            'source_content' => 'Source content',
            'rewritten_title' => 'Draft title',
            'rewritten_content' => '<p>Draft content</p>',
            'status' => 'drafted',
        ], $attributes));
    }
}
