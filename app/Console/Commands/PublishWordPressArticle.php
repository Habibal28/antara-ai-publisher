<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\WordPressService;
use Illuminate\Console\Command;
use Throwable;

class PublishWordPressArticle extends Command
{
    protected $signature = 'wordpress:publish {article? : ID artikel approved yang akan dipublikasikan}';

    protected $description = 'Publikasikan satu artikel approved ke WordPress';

    public function handle(WordPressService $wordpress): int
    {
        $articleId = $this->argument('article');
        $article = Article::query()
            ->where('status', 'approved')
            ->whereNull('wordpress_post_id')
            ->when($articleId, fn ($query) => $query->whereKey($articleId))
            ->orderBy('id')
            ->first();

        if (! $article) {
            $this->info('Tidak ada artikel approved yang menunggu publikasi.');

            return self::SUCCESS;
        }

        try {
            $postId = $wordpress->publish($article);
            $article->update([
                'wordpress_post_id' => $postId,
                'published_at' => now(),
                'status' => 'published',
                'error_message' => null,
            ]);

            $this->info("Artikel {$article->id} dipublikasikan ke WordPress sebagai post {$postId}.");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $article->update(['error_message' => $exception->getMessage()]);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
