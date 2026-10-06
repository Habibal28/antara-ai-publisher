<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\WordPressService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
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
            ->when($articleId, fn ($query) => $query->whereKey($articleId))
            ->orderBy('id')
            ->first();

        if (! $article) {
            $this->info('Tidak ada artikel approved yang menunggu publikasi.');

            return self::SUCCESS;
        }

        if (! $article->wordpress_media_id
            && (blank($article->image_path) || ! Storage::disk('local')->exists($article->image_path))) {
            $reason = 'Publikasi dibatalkan: file gambar lokal tidak tersedia.';
            $article->update([
                'status' => 'cancelled',
                'error_message' => $reason,
            ]);
            $this->warn("Artikel {$article->id} dibatalkan karena file gambar lokal tidak tersedia.");

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
